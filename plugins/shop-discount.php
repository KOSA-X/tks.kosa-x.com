<?php
/**
 * AJAX: kod rabatowy zamówienia (formularz na page-order.php)
 *
 * POST:
 *   action     = apply | remove
 *   code       = wpisany kod (tylko dla apply)
 *   csrf_token = token formularza zamówienia (orderInitCsrfToken)
 *
 * Odpowiedź JSON: { status: success|error, message, code, percent }
 * Zastosowany kod trzymany jest w sesji (order_discount_code) — kwotę
 * zamówienia zawsze przelicza serwer w page-order.php, nigdy przeglądarka.
 *
 * Endpoint celowo ładuje MINIMUM: config (tablica rabatów) + klasę Sql
 * + shop-functions (orderFindDiscount). Zero HTML-owych zależności szablonu.
 */

// odpowiedź musi być czystym JSON-em — żadnych warningów/notice w treści
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('CUSTOMER_PAGE', true);
chdir(dirname(__DIR__));

// bufor łapie każdy przypadkowy output z include'ów; czyszczony przed echo
ob_start();

$respond = static function (array $data): void {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    require_once 'database/config.php';           // + config.shop.php (rabaty z panelu)
    require_once 'core/libraries/sql.php';        // klasa Sql (wymagana przez shop-functions)

    if (!defined('BASE_URL')) {
        // strażnik w shop-functions.php; pełny BASE_URL nie jest tu potrzebny
        define('BASE_URL', '');
    }
    require_once 'plugins/shop-functions.php';    // orderFindDiscount()
    require_once 'database/lang_pl.php';          // komunikaty

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Throwable $e) {
    error_log('shop-discount bootstrap: '.$e->getMessage());
    http_response_code(500);
    $respond(['status' => 'error', 'message' => 'Błąd serwera przy inicjalizacji rabatów.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    $respond(['status' => 'error', 'message' => 'Nieprawidłowe żądanie.']);
}

if (!function_exists('orderFindDiscount')) {
    // niepełny deploy: nowy page-order.php, stary plugins/shop-functions.php
    http_response_code(500);
    $respond(['status' => 'error', 'message' => 'Rabaty niedostępne — wgraj aktualny plik plugins/shop-functions.php.']);
}

// CSRF — ten sam token, którym podpisany jest formularz zamówienia
$token = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
    http_response_code(400);
    $respond(['status' => 'error', 'message' => 'Sesja wygasła — odśwież stronę zamówienia.']);
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'remove') {
    unset($_SESSION['order_discount_code']);
    $respond([
        'status'  => 'success',
        'message' => $lang['discount_removed'] ?? 'Kod rabatowy usunięty.',
        'code'    => '',
        'percent' => 0,
    ]);
}

if ($action !== 'apply') {
    $respond(['status' => 'error', 'message' => 'Nieznana akcja.']);
}

$code = trim((string)($_POST['code'] ?? ''));
if ($code === '' || mb_strlen($code, 'UTF-8') > 60) {
    unset($_SESSION['order_discount_code']);
    $respond([
        'status'  => 'error',
        'message' => $lang['discount_empty'] ?? 'Wpisz kod rabatowy.',
        'code'    => '',
        'percent' => 0,
    ]);
}

$discount = orderFindDiscount($code);
if ($discount === null) {
    unset($_SESSION['order_discount_code']);
    $respond([
        'status'  => 'error',
        'message' => $lang['discount_invalid'] ?? 'Kod rabatowy jest nieprawidłowy lub stracił ważność.',
        'code'    => '',
        'percent' => 0,
    ]);
}

$_SESSION['order_discount_code'] = $discount['code'];

// "10" zamiast "10.00", ale "7.5" zostaje
$percentLabel = rtrim(rtrim(number_format($discount['percent'], 2, '.', ''), '0'), '.');

$respond([
    'status'  => 'success',
    'message' => sprintf($lang['discount_applied'] ?? 'Rabat %s%% naliczony.', $percentLabel),
    'code'    => $discount['code'],
    'percent' => $discount['percent'],
]);
