<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

/**
 * CSRF musi być zainicjalizowany PRZED jakimkolwiek HTML (przed _header.php)
 * Funkcja jest w plugins/shop-functions.php
 */
$csrfToken = orderInitCsrfToken();

$skinPath = 'templates/'.$config['skin'].'/';
require_once $skinPath.'_header.php';

if (!isset($aData['sName'])) {
    require_once $skinPath.'_404.php';
    require_once $skinPath.'_footer.php';
    exit;
}

require_once $skinPath.'_title.php';

global $config, $oPage, $oSql;

// --------------------------------------------------
// LOGOWANIE / BIEŻĄCY UŻYTKOWNIK (AUTO-FILL DANYCH)
// --------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn  = !empty($_SESSION['user_id']);
$currentUser = null;

if ($isLoggedIn) {
    $userId = (int) $_SESSION['user_id'];

    $stmt = $oSql->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_email'],
            $_SESSION['user_name'],
            $_SESSION['user_role']
        );
        $isLoggedIn  = false;
        $currentUser = null;
    }
}

// domyślne wartości dla formularza zamówienia (auto-uzupełnianie)
$orderDefaults = [
    'name'    => '',
    'email'   => '',
    'phone'   => '',
    'street'  => '',
    'city'    => '',
    'code'    => '',
    'nip'     => '',
    'message' => '',
];

if ($isLoggedIn && $currentUser) {
    $orderDefaults['name']   = trim($currentUser['name']   ?? $currentUser['email'] ?? '');
    $orderDefaults['email']  = trim($currentUser['email']  ?? '');
    $orderDefaults['phone']  = trim($currentUser['phone']  ?? '');
    $orderDefaults['street'] = trim($currentUser['street'] ?? '');
    $orderDefaults['city']   = trim($currentUser['city']   ?? '');
    $orderDefaults['code']   = trim($currentUser['code']   ?? '');

    if (isset($currentUser['nip'])) {
        $orderDefaults['nip'] = trim($currentUser['nip'] ?? '');
    }
}

// --------------------------------------------------
// 1. Dane wejściowe: koszyk, dostawy, płatności
// --------------------------------------------------

// dane koszyka z funkcji cartList()
$cartData = cartList();

$cart         = $cartData['cart'];
$cart_html    = $cartData['html'];
$products_sum = $cartData['products_sum'];

/**
 * Konwertuje koszyk do JSON dla zapisu w bazie.
 * Nowy format z kluczami złożonymi: {"31_abc": {"product_id": 31, "qty": 2, "filters": {...}}}
 * Zapisuje w formacie czytelnym dla panelu admina.
 */
if (!function_exists('order_cart_to_json')) {
    function order_cart_to_json(array $cart): string
    {
        $clean = [];

        foreach ($cart as $cartKey => $itemData) {
            // Nowy format z kluczem złożonym
            if (is_array($itemData) && isset($itemData['product_id'])) {
                $pid = (int) $itemData['product_id'];
                $q = (int) ($itemData['qty'] ?? 1);
                $filters = $itemData['filters'] ?? [];
            }
            // Stary format z qty/filters
            elseif (is_array($itemData) && isset($itemData['qty'])) {
                $pid = (int) explode('_', $cartKey)[0];
                $q = (int) $itemData['qty'];
                $filters = $itemData['filters'] ?? [];
            }
            // Najstarszy format: {"31": 2}
            elseif (is_numeric($itemData)) {
                $pid = (int) $cartKey;
                $q = (int) $itemData;
                $filters = [];
            }
            else {
                continue;
            }

            if ($pid <= 0 || $q < 1) {
                continue;
            }

            // Używaj klucza złożonego dla zapisu (pozwala na wiele wariantów tego samego produktu)
            $clean[$cartKey] = [
                'product_id' => $pid,
                'qty' => $q,
                'filters' => is_array($filters) ? $filters : []
            ];
        }

        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ($json !== false) ? $json : '{}';
    }
}

/**
 * Ekstrahuje wszystkie filtry z koszyka do jednego JSON.
 * Używane do zapisu w kolumnie 'filters' w tabeli orders.
 */
if (!function_exists('order_extract_filters')) {
    function order_extract_filters(array $cart): string
    {
        $allFilters = [];

        foreach ($cart as $cartKey => $itemData) {
            // Nowy format z kluczem złożonym
            if (is_array($itemData) && isset($itemData['product_id'])) {
                $pid = (int) $itemData['product_id'];
                $filters = $itemData['filters'] ?? [];
            }
            // Stary format
            elseif (is_array($itemData) && isset($itemData['qty'])) {
                $pid = (int) explode('_', $cartKey)[0];
                $filters = $itemData['filters'] ?? [];
            }
            else {
                continue;
            }

            if (!empty($filters) && is_array($filters)) {
                // Używaj klucza złożonego aby zachować warianty
                $allFilters[$cartKey] = [
                    'product_id' => $pid,
                    'filters' => $filters
                ];
            }
        }

        if (empty($allFilters)) {
            return '';
        }

        $json = json_encode($allFilters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ($json !== false) ? $json : '';
    }
}

$cartPayload = order_cart_to_json($cart);

// ewentualne komunikaty koszyka, jeśli będziesz chciał dodać w przyszłości
$cart_message = '';

// dozwolone ID dostaw / płatności
$allowedDeliveries = array_map('intval', array_keys($config['order_delivery']));
$allowedPayments   = array_map('intval', array_keys($config['order_payment']));

// aktualnie wybrana dostawa (z POST lub domyślna)
$delivery_id = isset($_POST['delivery']) ? (int)$_POST['delivery'] : 1;
if (!in_array($delivery_id, $allowedDeliveries, true)) {
    $delivery_id = in_array(1, $allowedDeliveries, true) ? 1 : reset($allowedDeliveries);
}

$delivery_cost = isset($config['order_delivery'][$delivery_id]) ? (float)$config['order_delivery'][$delivery_id]['price'] : 0.0;

// rabat z kodu (kod trzyma sesja — plugins/shop-discount.php; ważność
// sprawdzana ponownie przy każdym odczycie, więc kwota liczona jest
// zawsze po stronie serwera, także przy zapisie zamówienia)
$appliedDiscount  = function_exists('orderAppliedDiscount') ? orderAppliedDiscount() : null;
$discount_code    = (string)($appliedDiscount['code'] ?? '');
$discount_percent = (float)($appliedDiscount['percent'] ?? 0);
$discount_amount  = $discount_percent > 0 ? round($products_sum * $discount_percent / 100, 2) : 0.0;

// suma końcowa (produkty − rabat + dostawa)
$price_sum = $products_sum - $discount_amount + $delivery_cost;

// --------------------------------------------------
// 4. Funkcje walidujące pola (lokalne dla zamówienia)
// --------------------------------------------------
if (!function_exists('order_sanitize_field')) {
    function order_sanitize_field(?string $value, int $maxLen = 255): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value, 'UTF-8') > $maxLen) {
            $value = mb_substr($value, 0, $maxLen, 'UTF-8');
        }
        return $value;
    }
}

if (!function_exists('order_validate_phone')) {
    function order_validate_phone(string $phone): bool
    {
        return (bool)preg_match('/^[0-9+\s\-]{6,20}$/', $phone);
    }
}

/**
 * Buduje ładny e-mail HTML ze szczegółami zamówienia (wspólny dla PayU i przelewu)
 */
if (!function_exists('orderBuildOrderEmailHtml')) {
    function orderBuildOrderEmailHtml(array $args): string
    {
        $subject       = (string)($args['subject'] ?? '');
        $orderId       = (int)($args['order_id'] ?? 0);
        $data          = (array)($args['data'] ?? []);
        $productsHtml  = (string)($args['products_html'] ?? '');
        $deliveryLabel = (string)($args['delivery_label'] ?? '');
        $paymentLabel  = (string)($args['payment_label'] ?? '');
        $productsSum   = (float)($args['products_sum'] ?? 0);
        $deliveryCost  = (float)($args['delivery_cost'] ?? 0);
        $discountCode  = (string)($args['discount_code'] ?? '');
        $discountAmount = (float)($args['discount_amount'] ?? 0);
        $total         = (float)($args['total'] ?? 0);
        $statusUrl     = (string)($args['status_url'] ?? '');
        $config        = (array)($args['config'] ?? []);
        $isPayu        = (bool)($args['is_payu'] ?? false);

        $brand = htmlspecialchars((string)($config['logo'] ?? 'Sklep'), ENT_QUOTES, 'UTF-8');

        $safe = static function($v, $max = 500) {
            $v = trim((string)$v);
            if ($v === '') return '';
            if (mb_strlen($v, 'UTF-8') > (int)$max) {
                $v = mb_substr($v, 0, (int)$max, 'UTF-8');
            }
            return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        };

        $name   = $safe($data['name'] ?? '', 200);
        $email  = $safe($data['email'] ?? '', 220);
        $phone  = $safe($data['phone'] ?? '', 60);
        $street = $safe($data['street'] ?? '', 220);
        $city   = $safe($data['city'] ?? '', 160);
        $code   = $safe($data['code'] ?? '', 20);
        $nip    = $safe($data['nip'] ?? '', 40);

        $dateTs   = (int)($data['date'] ?? time());
        $dateNice = date('d.m.Y H:i', $dateTs);

        $statusUrlSafe = htmlspecialchars($statusUrl, ENT_QUOTES, 'UTF-8');

        $money = static function(float $v): string {
            return number_format($v, 2, ',', ' ') . ' zł';
        };

        $messageBlock = '';
        $msg = trim((string)($data['message'] ?? ''));
        if ($msg !== '') {
            $messageBlock = '<tr>
                <td style="padding:6px 0; color:#444; width:180px;"><strong>Uwagi</strong></td>
                <td style="padding:6px 0; color:#111;">'.nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')).'</td>
            </tr>';
        }

        $nipBlock = '';
        if ($nip !== '') {
            $nipBlock = '<tr>
                <td style="padding:6px 0; color:#444; width:180px;"><strong>NIP</strong></td>
                <td style="padding:6px 0; color:#111;">'.$nip.'</td>
            </tr>';
        }

        $inpostBlock = '';
        if ((int)($data['delivery'] ?? 0) === 2) {
            $inpostId   = $safe($data['inpost_point_id'] ?? '', 120);
            $inpostName = $safe($data['inpost_point_name'] ?? '', 200);
            $inpostAddr = $safe($data['inpost_point_address'] ?? '', 260);

            $inpostBlock = '
            <div style="margin:18px 0; padding:12px; border:1px solid #eee; border-radius:10px;">
                <div style="font-size:16px; font-weight:700; margin-bottom:8px;">Paczkomat InPost</div>
                <div style="color:#111; line-height:1.5;">
                    <div><strong>ID:</strong> '.$inpostId.'</div>
                    <div><strong>Nazwa:</strong> '.$inpostName.'</div>
                    <div><strong>Adres:</strong> '.$inpostAddr.'</div>
                </div>
            </div>';
        }

        // Opcjonalnie: instrukcje dla przelewu (jeśli masz opis na stronie ID=13 jak u Ciebie)
        $paymentHelp = '';
        if (!$isPayu && function_exists('getData')) {
            $transferHtml = (string)getData($config['transfer_page'], 'sDescriptionFull');
            if (trim(strip_tags($transferHtml)) !== '') {
                $paymentHelp = '
                <div style="margin:18px 0; padding:12px; border:1px solid #eee; border-radius:10px;">
                    <div style="font-size:16px; font-weight:700; margin-bottom:8px;">Instrukcje płatności</div>
                    <div style="color:#111; line-height:1.6;">'.$transferHtml.'</div>
                </div>';
            }
        } elseif ($isPayu) {
            $paymentHelp = '
            <div style="margin:18px 0; padding:12px; border:1px solid #eee; border-radius:10px;">
                <div style="font-size:16px; font-weight:700; margin-bottom:8px;">Płatność PayU</div>
                <div style="color:#111; line-height:1.6;">
                    Jeśli nie dokończyłeś płatności lub chcesz wrócić do zamówienia – użyj linku do szczegółów poniżej.
                </div>
            </div>';
        }

        $html = '<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>'.htmlspecialchars($subject, ENT_QUOTES, "UTF-8").'</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f5; font-family:Arial, Helvetica, sans-serif;">
  <div style="padding:24px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
      <tr>
        <td align="center">
          <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="border-collapse:collapse; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,0.06);">
            <tr>
              <td style="padding:18px 20px; background:#111; color:#fff;">
                <div style="font-size:18px; font-weight:700;">'.$brand.'</div>
                <div style="font-size:13px; opacity:0.9; margin-top:4px;">Potwierdzenie zamówienia</div>
              </td>
            </tr>

            <tr>
              <td style="padding:20px;">
                <div style="font-size:20px; font-weight:700; color:#111; margin:0 0 6px;">Zamówienie #'.$orderId.'</div>
                <div style="font-size:13px; color:#666;">Data złożenia: '.$dateNice.'</div>

                <div style="margin:16px 0 0;">
                  <a href="'.$statusUrlSafe.'" style="display:inline-block; padding:12px 16px; background:#1a73e8; color:#fff; text-decoration:none; border-radius:10px; font-weight:700; font-size:14px;">
                    Zobacz szczegóły zamówienia
                  </a>
                  <div style="font-size:12px; color:#777; margin-top:8px;">
                    Jeśli przycisk nie działa, skopiuj link: <span style="word-break:break-all;">'.$statusUrlSafe.'</span>
                  </div>
                </div>

                <hr style="border:none; border-top:1px solid #eee; margin:18px 0;">

                <div style="font-size:16px; font-weight:700; color:#111; margin-bottom:10px;">Produkty</div>
                <div>'.$productsHtml.'</div>

                <div style="margin:14px 0; padding:12px; background:#fafafa; border:1px solid #eee; border-radius:10px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px;">
                    <tr>
                      <td style="padding:6px 0; color:#444;">Suma produktów</td>
                      <td style="padding:6px 0; color:#111;" align="right"><strong>'.$money($productsSum).'</strong></td>
                    </tr>
                    '.($discountAmount > 0 ? '<tr>
                      <td style="padding:6px 0; color:#444;">Rabat'.($discountCode !== '' ? ' ('.$safe($discountCode, 60).')' : '').'</td>
                      <td style="padding:6px 0; color:#2f9e44;" align="right"><strong>-'.$money($discountAmount).'</strong></td>
                    </tr>' : '').'
                    <tr>
                      <td style="padding:6px 0; color:#444;">Dostawa</td>
                      <td style="padding:6px 0; color:#111;" align="right"><strong>'.$money($deliveryCost).'</strong></td>
                    </tr>
                    <tr>
                      <td style="padding:10px 0 0; color:#111; font-size:15px;"><strong>Razem do zapłaty</strong></td>
                      <td style="padding:10px 0 0; color:#111; font-size:15px;" align="right"><strong>'.$money($total).'</strong></td>
                    </tr>
                  </table>
                </div>

                '.$inpostBlock.'

                <div style="font-size:16px; font-weight:700; color:#111; margin:18px 0 10px;">Dane zamawiającego</div>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px;">
                  <tr>
                    <td style="padding:6px 0; color:#444; width:180px;"><strong>Imię i nazwisko</strong></td>
                    <td style="padding:6px 0; color:#111;">'.$name.'</td>
                  </tr>
                  <tr>
                    <td style="padding:6px 0; color:#444;"><strong>E-mail</strong></td>
                    <td style="padding:6px 0; color:#111;">'.$email.'</td>
                  </tr>
                  <tr>
                    <td style="padding:6px 0; color:#444;"><strong>Telefon</strong></td>
                    <td style="padding:6px 0; color:#111;">'.$phone.'</td>
                  </tr>
                  <tr>
                    <td style="padding:6px 0; color:#444;"><strong>Adres</strong></td>
                    <td style="padding:6px 0; color:#111;">'.$street.', '.$city.' '.$code.'</td>
                  </tr>
                  '.$nipBlock.'
                  '.$messageBlock.'
                </table>

                <div style="font-size:16px; font-weight:700; color:#111; margin:18px 0 10px;">Dostawa i płatność</div>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px;">
                  <tr>
                    <td style="padding:6px 0; color:#444; width:180px;"><strong>Dostawa</strong></td>
                    <td style="padding:6px 0; color:#111;">'.htmlspecialchars($deliveryLabel, ENT_QUOTES, "UTF-8").'</td>
                  </tr>
                  <tr>
                    <td style="padding:6px 0; color:#444;"><strong>Płatność</strong></td>
                    <td style="padding:6px 0; color:#111;">'.htmlspecialchars($paymentLabel, ENT_QUOTES, "UTF-8").'</td>
                  </tr>
                </table>

                '.$paymentHelp.'

                <hr style="border:none; border-top:1px solid #eee; margin:18px 0;">

                <div style="font-size:12px; color:#777; line-height:1.5;">
                  Wiadomość wysłana automatycznie ze strony '.htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8').'.
                  <br>Jeśli masz pytania – odpowiedz na tego maila.
                </div>

              </td>
            </tr>
          </table>

          <div style="font-size:11px; color:#888; margin-top:10px;">
            © '.$brand.'
          </div>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>';

        return $html;
    }
}

// --------------------------------------------------
// 5. Obsługa submit (zapis zamówienia, PayU / mail)
// --------------------------------------------------
$order_success_message = '';

if (isset($_POST['save'], $_POST['csrf_token']) && $_POST['save'] == 1 && !empty($cart)) {

    // dane wejściowe
    $data = [
        'date'     => time(),
        'name'     => order_sanitize_field($_POST['name'] ?? '', 120),
        'email'    => order_sanitize_field($_POST['email'] ?? '', 190),
        'phone'    => order_sanitize_field($_POST['phone'] ?? '', 30),
        'street'   => order_sanitize_field($_POST['street'] ?? '', 190),
        'city'     => order_sanitize_field($_POST['city'] ?? '', 120),
        'code'     => order_sanitize_field($_POST['code'] ?? '', 10),
        'nip'      => order_sanitize_field($_POST['nip'] ?? '', 20),
        'message'  => order_sanitize_field($_POST['message'] ?? '', 2000),
        'payment'  => (int)($_POST['payment'] ?? 1),
        'delivery' => (int)($_POST['delivery'] ?? $delivery_id),
        'price'    => (float)$price_sum,
        'products' => $cartPayload,
        'inpost_point_id'      => order_sanitize_field($_POST['inpost_point_id'] ?? '', 60),
        'inpost_point_name'    => order_sanitize_field($_POST['inpost_point_name'] ?? '', 120),
        'inpost_point_address' => order_sanitize_field($_POST['inpost_point_address'] ?? '', 190),
    ];

    // dopchnięcie payment / delivery do dozwolonych ID
    if (!in_array($data['payment'], $allowedPayments, true)) {
        $data['payment'] = in_array(1, $allowedPayments, true) ? 1 : reset($allowedPayments);
    }
    if (!in_array($data['delivery'], $allowedDeliveries, true)) {
        $data['delivery'] = $delivery_id;
    }

    $recaptchaToken  = $_POST['g-recaptcha-response'] ?? '';
    $recaptchaAction = $_POST['action'] ?? 'order_form';

    $errors = [];

    // CSRF
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $errors[] = 'Nieprawidłowy token formularza. Odśwież stronę i spróbuj ponownie.';
    }

    // walidacja danych
    if ($data['name'] === '') {
        $errors[] = 'Podaj imię i nazwisko.';
    }

    if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Podaj poprawny e-mail.';
    }

    if ($data['phone'] === '' || !order_validate_phone($data['phone'])) {
        $errors[] = 'Podaj poprawny numer telefonu.';
    }

    if ($data['street'] === '') {
        $errors[] = 'Podaj ulicę i numer.';
    }

    if ($data['city'] === '') {
        $errors[] = 'Podaj miejscowość.';
    }

    if ($data['code'] === '') {
        $errors[] = 'Podaj poprawny kod pocztowy (np. 22-600).';
    }

    if (empty($_POST['regulamin'])) {
        $errors[] = 'Musisz zaakceptować regulaminy.';
    }

    if ($data['delivery'] === 2 && $data['inpost_point_id'] === '') {
        $errors[] = 'Wybierz paczkomat InPost.';
    }

    if (!empty($errors)) {
        $order_success_message = '<div class="alert alert-danger">'.implode('<br>', $errors).'</div>';
    } else {
        // --------------------------------------------
        // 5.1 Sprawdzenie duplikatu zamówienia
        // --------------------------------------------
        $stmtCheck = $oSql->prepare('SELECT id FROM orders WHERE date = :date AND email = :email LIMIT 1');
        $stmtCheck->execute([
            ':date'  => (string)$data['date'],
            ':email' => $data['email'],
        ]);
        $exists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            $order_success_message = "<div class='alert alert-success'>To zamówienie zostało już zapisane.</div>";
        } else {
            // --------------------------------------------
            // 5.2 INSERT zamówienia (z user_id)
            // --------------------------------------------
            // migracja kolumn rabatu — PRZED pierwszym użyciem ordersHasColumn()
            // (statyczny cache kolumn wypełnia się przy pierwszym wywołaniu)
            if (function_exists('orderEnsureDiscountColumns')) {
                orderEnsureDiscountColumns();
            }

            $columns = [
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'],
                'street'   => $data['street'],
                'city'     => $data['city'],
                'code'     => $data['code'],
                'nip'      => $data['nip'],
                'payment'  => $data['payment'],
                'delivery' => $data['delivery'],
                'date'     => (string)$data['date'],
                'products' => $data['products'],
                'message'  => $data['message'],
                'price'    => number_format((float)($data['price'] ?? 0), 2, '.', ''),
                'status'   => 0,
            ];

            // user_id – jeśli kolumna istnieje w tabeli orders
            if (ordersHasColumn('user_id')) {
                $columns['user_id'] = ($isLoggedIn && !empty($currentUser['id']))
                    ? (int)$currentUser['id']
                    : null;
            }

            if (ordersHasColumn('inpost_point_id')) {
                $columns['inpost_point_id'] = $data['inpost_point_id'];
            }
            if (ordersHasColumn('inpost_point_name')) {
                $columns['inpost_point_name'] = $data['inpost_point_name'];
            }
            if (ordersHasColumn('inpost_point_address')) {
                $columns['inpost_point_address'] = $data['inpost_point_address'];
            }
            if (ordersHasColumn('payu_status')) {
                $columns['payu_status'] = 'NEW';
            }
            if (ordersHasColumn('payu_order_id')) {
                $columns['payu_order_id'] = '';
            }

            // Zapisz wybrane filtry produktów w kolumnie 'filters'
            if (ordersHasColumn('filters')) {
                $columns['filters'] = order_extract_filters($cart);
            }

            // kod rabatowy (kwoty przeliczone serwerowo powyżej)
            if (ordersHasColumn('discount_code')) {
                $columns['discount_code']    = $discount_code;
                $columns['discount_percent'] = $discount_percent;
                $columns['discount_amount']  = number_format($discount_amount, 2, '.', '');
            }

            $placeholders = [];
            $values       = [];
            foreach ($columns as $column => $value) {
                $placeholders[]          = ':'.$column;
                $values[':'.$column]     = $value;
            }

            $sql        = 'INSERT INTO orders ('.implode(', ', array_keys($columns)).') VALUES ('.implode(', ', $placeholders).')';
            $stmtInsert = $oSql->prepare($sql);

            try {
                $stmtInsert->execute($values);
            } catch (PDOException $e) {
                order_log_error('DB insert error in orders', [
                    'error' => $e->getMessage(),
                    'data'  => $columns,
                ]);

                $order_success_message = '<div class="alert alert-danger">Wystąpił błąd podczas zapisu zamówienia. Spróbuj ponownie za chwilę.</div>';
                // skip dalszej logiki
                goto ORDER_END;
            }

            $orderId        = (int)$oSql->lastInsertId();

            // rabat zużyty w tym zamówieniu — nie przenosimy go na kolejne
            unset($_SESSION['order_discount_code']);

            $order_delivery = getElementArray($data['delivery'], $config['order_delivery']);
            $order_payment  = getElementArray($data['payment'], $config['order_payment']);
            $order_products = orderProducts($cart);

            $order_subject = 'Zamówienie '.($orderId ?: '').' - '.$config['logo'];

            $safeEmail   = htmlspecialchars((string)($data['email'] ?? ''), ENT_QUOTES, 'UTF-8');

            // --------------------------------------------
            // 5.3 Płatność – PayU albo zwykła + WSPÓLNY MAIL
            // --------------------------------------------
            $PAYU_PAYMENT_ID = 1;
            $isPayu          = ($data['payment'] === $PAYU_PAYMENT_ID && !empty($config['payu_pos_id']));

            // wspólny token do linku statusu zamówienia
            $tokenSecret = $config['orderKey'] ?? ($config['secretKey'] ?? BASE_URL);
            $orderToken  = hash('sha256', $orderId.'|'.$data['date'].'|'.$tokenSecret);

            // Wspólny link do statusu/szczegółów zamówienia (w mailu zawsze)
            $paymentPageUrl = getUrl($config['payment_page']);
            $statusUrl      = rtrim($paymentPageUrl ?? '', '/').'?order='.$orderId.'&token='.$orderToken.'&clear_cart=1';

            // Wspólny, ładny mail (dla klienta i admina)
            $emailBody = orderBuildOrderEmailHtml([
                'subject'        => $order_subject,
                'order_id'       => $orderId,
                'data'           => $data,
                'products_html'  => $order_products,
                'delivery_label' => $order_delivery,
                'payment_label'  => $order_payment,
                'products_sum'   => (float)$products_sum,
                'delivery_cost'  => (float)$delivery_cost,
                'discount_code'   => $discount_code,
                'discount_amount' => (float)$discount_amount,
                'total'          => (float)$data['price'],
                'status_url'     => $statusUrl,
                'config'         => $config,
                'is_payu'        => $isPayu,
            ]);

            // Wyślij mail do klienta (zawsze)
            $customerMail = sendEmail([
                'to'       => $data['email'],
                'to_name'  => $data['name'],
                'subject'  => $order_subject,
                'body'     => $emailBody,
                'alert'    => false,
                'from'     => $config['email'],
                'from_name'=> $config['logo'] ?? 'Sklep',
                'reply_to'      => $config['email'],
                'reply_to_name' => $config['logo'] ?? 'Sklep',
                'smtp_host'   => $config['smtp_host']   ?? null,
                'smtp_user'   => $config['smtp_user']   ?? null,
                'smtp_pass'   => $config['smtp_pass']   ?? null,
                'smtp_port'   => $config['smtp_port']   ?? null,
                'smtp_secure' => $config['smtp_secure'] ?? null,
            ]);

            if (empty($customerMail['success'])) {
                order_log_error('Order mail to customer failed', [
                    'order_id' => $orderId,
                    'error'    => $customerMail['message'] ?? null,
                ]);
            }

            // Wyślij mail do admina (zawsze)
            $adminMail = sendEmail([
                'to'       => $config['email'],
                'to_name'  => $config['logo'] ?? 'Sklep',
                'subject'  => $order_subject,
                'body'     => $emailBody,
                'alert'    => false,
                'recaptcha_token'  => $recaptchaToken,
                'recaptcha_action' => $recaptchaAction,
                'recaptcha_secret' => $config['secretKey'] ?? null,
                'smtp_host'   => $config['smtp_host']   ?? null,
                'smtp_user'   => $config['smtp_user']   ?? null,
                'smtp_pass'   => $config['smtp_pass']   ?? null,
                'smtp_port'   => $config['smtp_port']   ?? null,
                'smtp_secure' => $config['smtp_secure'] ?? null,
            ]);

            if (empty($adminMail['success'])) {
                order_log_error('Order mail to admin failed', [
                    'order_id' => $orderId,
                    'error'    => $adminMail['message'] ?? null,
                ]);
            }

            $mailInfo = !empty($customerMail['success'])
                ? 'Szczegóły wysłaliśmy na adres '.$safeEmail.'.'
                : 'Zamówienie zapisane, ale wystąpił problem z wysyłką e-mail. Jeśli nie dostaniesz wiadomości – skontaktuj się z nami.';

            if ($isPayu && function_exists('payuCreateOrder')) {
                // --- PAYU: budujemy koszyk dla API (w groszach) ---
                $payuProducts = [];
                $totalAmount  = 0;

                foreach ($cart as $cartKey => $itemData) {
                    // Obsługa nowego formatu z kluczami złożonymi
                    if (is_array($itemData) && isset($itemData['product_id'])) {
                        $pid = (int)$itemData['product_id'];
                        $qty = (int)($itemData['qty'] ?? 1);
                        $filters = $itemData['filters'] ?? [];
                    }
                    // Stary format z qty/filters
                    elseif (is_array($itemData) && isset($itemData['qty'])) {
                        $parts = explode('_', (string)$cartKey);
                        $pid = (int)$parts[0];
                        $qty = (int)$itemData['qty'];
                        $filters = $itemData['filters'] ?? [];
                    }
                    // Najstarszy format: {"29": 2}
                    elseif (is_numeric($itemData)) {
                        $pid = (int)$cartKey;
                        $qty = (int)$itemData;
                        $filters = [];
                    }
                    else {
                        continue;
                    }

                    if ($qty < 1) {
                        continue;
                    }

                    $pName = getData($pid, 'sName') ?? 'Produkt #'.$pid;

                    // Dodaj info o filtrach do nazwy produktu w PayU
                    if (!empty($filters) && function_exists('formatSelectedFilters')) {
                        $filtersJson = json_encode($filters, JSON_UNESCAPED_UNICODE);
                        $filtersText = formatSelectedFilters($filtersJson, false);
                        if ($filtersText) {
                            $pName .= ' (' . $filtersText . ')';
                        }
                    }

                    $pPrice    = order_get_product_price($pid);
                    $unitGross = (int)round($pPrice * 100);

                    $payuProducts[] = [
                        'name'      => $pName,
                        'unitPrice' => $unitGross,
                        'quantity'  => $qty,
                    ];
                    $totalAmount += $unitGross * $qty;
                }

                // dostawa jako osobny „produkt”
                if ($delivery_cost > 0) {
                    $deliveryGross = (int)round($delivery_cost * 100);
                    $payuProducts[] = [
                        'name'      => 'Dostawa',
                        'unitPrice' => $deliveryGross,
                        'quantity'  => 1,
                    ];
                    $totalAmount += $deliveryGross;
                }

                // rabat z kodu: lista products jest dla PayU informacyjna,
                // kwotą wiążącą jest totalAmount — obniżamy ją o rabat
                // (ujemne unitPrice w products PayU odrzuca)
                if ($discount_amount > 0) {
                    $totalAmount = max(0, $totalAmount - (int)round($discount_amount * 100));
                }

                $extOrderIdPayu = $orderId.'-'.time();

                // To samo co statusUrl – po powrocie pokaż szczegóły zamówienia
                $continueUrl = $statusUrl;

                $payuOrder = payuCreateOrder($config, [
                    'extOrderId'   => $extOrderIdPayu,
                    'totalAmount'  => $totalAmount,
                    'buyer_email'  => $data['email'],
                    'buyer_phone'  => $data['phone'],
                    'buyer_name'   => $data['name'],
                    'description'  => 'Zamówienie '.$orderId.($discount_code !== '' ? ' (rabat '.$discount_code.')' : '').' - '.($config['logo'] ?? 'Sklep'),
                    'products'     => $payuProducts,
                    'continueUrl'  => $continueUrl,
                ]);

                if (!empty($config['payu_debug'])) {
                    echo '<pre>'.htmlspecialchars(print_r($payuOrder, true), ENT_QUOTES, 'UTF-8').'</pre>';
                }

                if (!empty($payuOrder['success'])) {
                    // zapis ID zamówienia PayU
                    $updateParts  = [];
                    $updateParams = [':id' => $orderId];

                    if (ordersHasColumn('payu_order_id')) {
                        $updateParts[]                  = 'payu_order_id = :payu_order_id';
                        $updateParams[':payu_order_id'] = $payuOrder['orderId'] ?? '';
                    }
                    if (ordersHasColumn('payu_status')) {
                        $updateParts[]                  = 'payu_status = :payu_status';
                        $updateParams[':payu_status']   = 'PENDING';
                    }

                    if ($updateParts) {
                        $sqlUpdate  = 'UPDATE orders SET '.implode(', ', $updateParts).' WHERE id = :id';
                        $stmtUpdate = $oSql->prepare($sqlUpdate);
                        $stmtUpdate->execute($updateParams);
                    }

                    $redirectUrl = $payuOrder['redirectUri'] ?? '';

                    if ($redirectUrl) {
                        $redirectForJs = json_encode($redirectUrl, JSON_UNESCAPED_SLASHES);

                        $order_success_message =
                            "<div class='alert alert-success'>Zamówienie zostało zapisane. {$mailInfo} Przekierowujemy do płatności PayU...</div>".
                            "<script>setTimeout(function(){window.location.href=".$redirectForJs.";},500);</script>";
                    } else {
                        $order_success_message =
                            "<div class='alert alert-danger'>Nie udało się przekierować do płatności PayU. Prosimy spróbować ponownie. {$mailInfo}</div>";
                    }
                } else {
                    $errorMsg = $payuOrder['error'] ?? 'Nieznany błąd';
                    $httpCode = $payuOrder['http'] ?? '-';
                    $rawResp  = $payuOrder['response'] ?? null;

                    order_log_error('PayU createOrder error', [
                        'order_id' => $orderId,
                        'error'    => $errorMsg,
                        'http'     => $httpCode,
                        'response' => $rawResp,
                    ]);

                    $order_success_message =
                        "<div class='alert alert-danger'>Wystąpił błąd podczas łączenia z systemem płatności. ".
                        "Jeśli problem się powtórzy, skontaktuj się z nami. {$mailInfo}</div>";
                }
            } else {
                // --- ZWYKŁA PŁATNOŚĆ (np. przelew) ---
                $redirectJs = json_encode($statusUrl, JSON_UNESCAPED_SLASHES);
                $order_success_message =
                    "<div class='alert alert-success'>Dziękujemy, zamówienie zostało przyjęte. {$mailInfo}</div>".
                    "<script>setTimeout(function(){ window.location.href = {$redirectJs}; }, 500);</script>";
            }

            ORDER_END:
            // nic więcej
        }
    }
}
?>

<div class="container pageOrder">
    <div class="mainPage__wrapper">
        <div class="mainPage__content">

            <?php
            echo $cart_message;
            echo $order_success_message;

            if (!empty($aData['sDescriptionShort'])) {
                echo '<div class="mainPage__desc font-md">'.$aData['sDescriptionShort'].'</div>';
            }

            if (empty($cart)) {
                echo '<div class="alert alert-info">Brak produktów w koszyku.</div>';
            }
            ?>

          <?php if (!empty($cart_html)) {
                echo '<div class="card mb-4">
                        <header class="card__header">
                        <h5 class="card__title">
                            <img src="'.ICONS.'cart.svg" alt="Sposób dostawy">Produkty w koszyku
                        </h5>
                        </header>
                        <div class="card__wrapper">'.$cart_html.'</div>
                        </div>
                        ';
            }   ?>

            <?php if (!isset($_POST['save']) && !empty($cart)) : ?>
                <form action="<?php echo $oPage->aPages[$aData['iPage']]['sLinkName']; ?>" method="post" class="form pageContact__form">
                    <input type="hidden" name="date" value="<?php echo time(); ?>">
                    <input type="hidden" name="price" id="price" value="<?php echo $price_sum; ?>">
                    <textarea name="products" id="products" hidden><?php echo htmlspecialchars($cartPayload, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="row">
                        <!-- LEWA KOLUMNA -->
                        <div class="col-6">
                            <div class="card mb-4">
                               <header class="card__header">
                                <h5 class="card__title"><img src="<?= ICONS."user.svg" ?>" alt="User">Dane zamawiającego</h5>
                                </header>
                                <div class="card__content">
                                   <?php if(!LOGGED){ ?>
                                    <p class="muted mt-0 mb-3"><a href="<?= getUrl($config['user_page']) ?>">Zaloguj się</a>, aby prowadzić historię zamówień i mieć możliwość rabatu.</p>
                                    <?php } ?>
                                    <div class="form-floating form-item">
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="name"
                                            name="name"
                                            value="<?php echo old('name', $orderDefaults['name']); ?>"
                                            placeholder="Imię i nazwisko"
                                            required
                                        >
                                        <label for="name">Imię i nazwisko <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-floating form-item">
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="phone"
                                                    name="phone"
                                                    value="<?php echo old('phone', $orderDefaults['phone']); ?>"
                                                    placeholder="Telefon"
                                                    required
                                                >
                                                <label for="phone">Telefon <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-floating form-item">
                                                <input
                                                    type="email"
                                                    class="form-control"
                                                    id="email"
                                                    name="email"
                                                    value="<?php echo old('email', $orderDefaults['email']); ?>"
                                                    placeholder="Adres e-mail"
                                                    required
                                                >
                                                <label for="email">E-mail <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-floating form-item">
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="street"
                                            name="street"
                                            value="<?php echo old('street', $orderDefaults['street']); ?>"
                                            placeholder="Ulica i numer"
                                            required
                                        >
                                        <label for="street">Ulica i numer <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-floating form-item">
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="city"
                                                    name="city"
                                                    value="<?php echo old('city', $orderDefaults['city']); ?>"
                                                    placeholder="Miejscowość"
                                                    required
                                                >
                                                <label for="city">Miejscowość <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-floating form-item">
                                                <input
                                                    type="text"
                                                    class="form-control city-code"
                                                    id="code"
                                                    name="code"
                                                    value="<?php echo old('code', $orderDefaults['code']); ?>"
                                                    placeholder="Kod pocztowy"
                                                    required
                                                >
                                                <label for="code">Kod pocztowy <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-floating form-item">
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="nip"
                                            name="nip"
                                            value="<?php echo old('nip', $orderDefaults['nip']); ?>"
                                            placeholder="NIP"
                                        >
                                        <label for="nip">NIP</label>
                                        <span class="form-text">Podaj numer NIP jeśli chcesz otrzymać FV.</span>
                                    </div>

                                    <div class="form-floating form-item">
                                        <textarea
                                            name="message"
                                            class="form-control"
                                            id="message"
                                            rows="2"
                                            placeholder="Dodatkowe informacje"
                                        ><?php echo old('message', $orderDefaults['message']); ?></textarea>
                                        <label for="message">Dodatkowe informacje</label>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-4">
                               <header class="card__header">
                                <h5 class="card__title"><img src="<?= ICONS."transport.svg" ?>" alt="Sposób dostawy">Sposób dostawy</h5>
                                </header>
                                <div class="card__content">
                                  <!-- SPOSÓB DOSTAWY -->
                                   <div class="formCheckBox">
                                    <?php foreach ($config['order_delivery'] as $id => $del): ?>
                                        <?php $oldDel = (int)old('delivery', $delivery_id); ?>
                                        <div class="form-check mb-2">
                                            <input
                                                class="form-check-input js-delivery-radio"
                                                type="radio"
                                                name="delivery"
                                                id="delivery_<?php echo $id; ?>"
                                                value="<?php echo $id; ?>"
                                                data-price="<?php echo htmlspecialchars($del['price'], ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo !empty($del['disabled']) ? 'disabled' : ''; ?>
                                                <?php echo $oldDel === $id ? 'checked' : ''; ?>
                                            >
                                            <label class="form-check-label <?php echo !empty($del['disabled']) ? 'muted' : ''; ?>" for="delivery_<?php echo $id; ?>">
                                               <span class="name"><?php echo $del['label']; ?></span>
                                               <span class="value"><?php echo number_format($del['price'], 2, ',', ' '); ?> zł</span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>

                                <div id="inpost-box" class="inpost-box cardInpost" style="display:none">
                                            <inpost-geowidget
                                              class="inpost-box-card"
                                              id="inpost-geowidget"
                                              onpoint="onpointselect"
                                              token="<?php echo htmlspecialchars($config['inpost_geowidget_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                              language="pl"
                                              config="parcelcollect">
                                            </inpost-geowidget>

                                            <div id="inpost-selected" class="alert alert-success mt-1" style="display:none;"></div>

                                            <input type="hidden" name="inpost_point_id" id="inpost_point_id">
                                            <input type="hidden" name="inpost_point_name" id="inpost_point_name">
                                            <input type="hidden" name="inpost_point_address" id="inpost_point_address">

                                            <button type="button" id="inpost-change" class="button button-sm button-light" style="display:none; margin-top:8px;">
                                                Zmień paczkomat
                                            </button>
                                        </div>
                                        <script>
                                        document.addEventListener('onpointselect', (event) => {
                                          const point = event.detail; // v5: event.detail
                                          if (!point) return;

                                          document.getElementById('inpost_point_id').value = point.name || '';
                                          document.getElementById('inpost_point_name').value = point.name || '';
                                          document.getElementById('inpost_point_address').value = [
                                            point.street,
                                            point.postCode,
                                            point.city
                                          ].filter(Boolean).join(', ');

                                          const box = document.getElementById('inpost-selected');
                                          box.style.display = 'block';
                                          box.innerHTML = 'Wybrano: <b>' + (point.name || '') + '</b><br>' + (document.getElementById('inpost_point_address').value);
                                        });
                                        </script>
                                    </div>
                            </div>
                        </div>

                        <!-- PRAWA KOLUMNA -->
                        <div class="col-6">
                            <div class="card mb-4">
                               <header class="card__header">
                                <h5 class="card__title"><img src="<?= ICONS."money.svg" ?>" alt="Metoda płatności">Metoda płatności</h5>
                                </header>
                                <div class="card__content">
                                  <!-- METODA PŁATNOŚCI -->
                                   <div class="formCheckBox">
                                    <?php foreach ($config['order_payment'] as $id => $pay): ?>
                                            <?php $oldPay = (int)old('payment', 1); ?>
                                            <div class="form-check mb-0">
                                                <input
                                                    class="form-check-input js-payment-radio"
                                                    type="radio"
                                                    name="payment"
                                                    id="payment_<?php echo $id; ?>"
                                                    value="<?php echo $id; ?>"
                                                    <?php echo !empty($pay['disabled']) ? 'disabled' : ''; ?>
                                                    <?php echo $oldPay === $id ? 'checked' : ''; ?>
                                                >
                                                <label class="form-check-label <?php echo !empty($pay['disabled']) ? 'muted' : ''; ?>" for="payment_<?php echo $id; ?>">
                                                    <?php echo $pay['label']; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                     <div class="details_payment details_payment_1 show">
                                         <?php echo getData($config['payu_page'], 'sDescriptionFull'); ?>
                                     </div>
                                      <div class="details_payment details_payment_2">
                                          <?php echo getData($config['transfer_page'], 'sDescriptionFull'); ?>
                                      </div>
                                </div>
                            </div>

                           <div class="card mb-4">
                                <header class="card__header">
                                    <h5 class="card__title">
                                        <img src="<?php echo ICONS; ?>money.svg" alt="<?php echo $lang['discount_code'] ?? 'Kod rabatowy'; ?>"><?php echo $lang['discount_code'] ?? 'Kod rabatowy'; ?>
                                    </h5>
                                </header>
                                <div class="card__content">
                                    <div class="orderDiscount">
                                        <div class="form-floating form-item orderDiscount__field">
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="discount-code"
                                                value="<?php echo html($discount_code); ?>"
                                                placeholder="<?php echo $lang['discount_placeholder'] ?? 'Wpisz kod'; ?>"
                                                autocomplete="off"
                                            >
                                            <label for="discount-code"><?php echo $lang['discount_code'] ?? 'Kod rabatowy'; ?></label>
                                        </div>
                                        <button type="button" id="discount-apply" class="button orderDiscount__button">
                                            <?php echo $lang['discount_apply'] ?? 'Przelicz rabat'; ?>
                                        </button>
                                    </div>
                                    <div id="discount-feedback" class="orderDiscount__feedback" role="status"></div>
                                </div>
                            </div>

                           <div class="card mb-4">
                                <header class="card__header">
                                    <h5 class="card__title">
                                            <img src="<?php echo ICONS; ?>cart.svg" alt="Podsumowanie zamówienia">Podsumowanie zamówienia
                                        </h5>
                                </header>

                                <div class="card__wrapper">
                                    <ul class="card__list">
                                        <li class="cardSummary__item">
                                            <span class="label">Wartość produktów</span>
                                            <span class="value" id="products-price">
                                                <?php echo number_format($products_sum, 2, ',', ' '); ?> zł
                                            </span>
                                        </li>
                                        <li class="cardSummary__item orderDiscount__row" id="discount-row"<?php echo $discount_amount > 0 ? '' : ' style="display:none"'; ?>>
                                            <span class="label"><?php echo $lang['discount_label'] ?? 'Rabat'; ?> <span id="discount-code-label"><?php echo $discount_code !== '' ? '('.html($discount_code).')' : ''; ?></span></span>
                                            <span class="value" id="discount-price">
                                                -<?php echo number_format($discount_amount, 2, ',', ' '); ?> zł
                                            </span>
                                        </li>
                                        <li class="cardSummary__item">
                                            <span class="label">Dostawa</span>
                                            <span class="value" id="delivery-price">
                                                <?php echo number_format($delivery_cost, 2, ',', ' '); ?> zł
                                            </span>
                                        </li>
                                    </ul>

                                    <footer class="card__footer pageOrder__summary">
                                        <strong>Suma</strong>
                                        <span id="order-total">
                                            <?php echo number_format($price_sum, 2, ',', ' '); ?> zł
                                        </span>
                                    </footer>
                                </div>
                            </div>

                            <?php if (!empty($cart)) : ?>
                                <script>
                                    // suma produktów bez dostawy + rabat % – do page-shop.js
                                    window.orderCartTotal = <?php echo json_encode($products_sum); ?>;
                                    window.orderDiscountPercent = <?php echo json_encode($discount_percent); ?>;
                                </script>
                            <?php endif; ?>

                            <div class="form-check form-item">
                                <input class="form-check-input" type="checkbox" id="regulamin" name="regulamin" value="1" required>
                                <label class="form-check-label" for="regulamin">
                                   <?= acceptLabel(); ?>
                                </label>
                            </div>

                            <?php if (!empty($config['publicKey'])): ?>
                                <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
                                <input type="hidden" name="action" value="order_form">
                                <script src="https://www.google.com/recaptcha/api.js?render=<?php echo $config['publicKey']; ?>"></script>
                                <script>
                                    grecaptcha.ready(function() {
                                        grecaptcha.execute('<?php echo $config['publicKey']; ?>', {action: 'order_form'}).then(function(token) {
                                            document.getElementById('g-recaptcha-response').value = token;
                                        });
                                    });
                                </script>
                            <?php endif; ?>

                            <div class="form-button">
                                <button type="submit" id="submit" name="save" class="button button-lg" value="1">
                                    <img src="<?php echo ICONS; ?>cart.svg" alt="Zamów"> Kup i zapłać
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <?php
            if (!empty($aData['sDescriptionFull']) && $aData['sDescriptionFull'] !== $aData['sDescriptionShort']) {
                echo '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>';
            }
            ?>
        </div>
    </div>
</div>

<!-- Geocard CSS + JS (tylko na tej stronie) -->
<link rel="stylesheet" href="https://geowidget.inpost.pl/inpost-geowidget.css">
<script src="https://geowidget.inpost.pl/inpost-geowidget.js" defer></script>
<script src="<?php echo THEME; ?>js/page-shop.js?ver=1"></script>
<?php
require_once $skinPath.'_footer.php';
