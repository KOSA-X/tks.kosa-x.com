<?php
/**
 * LOGOWANIE PRZEZ GOOGLE (Google Identity Services)
 *
 * Endpoint odbierający POST z przycisku Google / okienka One Tap
 * (data-login_uri na stronie Panelu użytkownika).
 *
 * Przepływ:
 *  1. GIS przysyła POST: credential (JWT ID token) + g_csrf_token (+ cookie).
 *  2. Weryfikacja podwójnego tokenu CSRF (cookie == POST) — wg dokumentacji Google.
 *  3. Weryfikacja ID tokenu przez endpoint Google tokeninfo
 *     (aud = nasz Client ID, wystawca Google, token nie wygasł, e-mail zweryfikowany).
 *  4. Logowanie istniejącego konta (po google_sub lub e-mailu) albo założenie nowego.
 *  5. Redirect do Panelu użytkownika.
 *
 * Konfiguracja: $config['google_client_id'] w database/config.php
 */

declare(strict_types=1);

chdir(dirname(__DIR__));

require 'database/config.php';      // → $config (bez getPageId — brak CUSTOMER_PAGE)
require 'database/config_pl.php';   // → $config['user_page']

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --------------------------------------------------
// Adres powrotny: Panel użytkownika (z cache linków CMS)
// --------------------------------------------------
function googleAuthUserPageUrl(array $config): string
{
    $base = rtrim((string)($config['url_domain'] ?? ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    $userPageId = (int)($config['user_page'] ?? 0);
    $linksIdsFile = $config['dir_database'].'cache/links_ids';
    if ($userPageId > 0 && is_file($linksIdsFile)) {
        $links = json_decode((string)file_get_contents($linksIdsFile), true);
        if (is_array($links) && !empty($links[$userPageId]) && is_string($links[$userPageId])) {
            return $base.'/'.ltrim($links[$userPageId], '/');
        }
    }

    return $base.'/';
}

function googleAuthRedirect(string $url): void
{
    header('Location: '.$url);
    exit;
}

$userPageUrl = googleAuthUserPageUrl($config);
$failUrl = $userPageUrl.(strpos($userPageUrl, '?') === false ? '?' : '&').'google_error=';

// --------------------------------------------------
// 0. Konfiguracja włączona?
// --------------------------------------------------
$clientId = trim((string)($config['google_client_id'] ?? ''));
if ($clientId === '') {
    googleAuthRedirect($failUrl.'disabled');
}

// --------------------------------------------------
// 1-2. POST + podwójny token CSRF (wg dokumentacji Google Identity)
// --------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['credential'])) {
    googleAuthRedirect($failUrl.'request');
}

$csrfCookie = $_COOKIE['g_csrf_token'] ?? '';
$csrfPost   = $_POST['g_csrf_token'] ?? '';
if ($csrfCookie === '' || $csrfPost === '' || !hash_equals($csrfCookie, $csrfPost)) {
    googleAuthRedirect($failUrl.'csrf');
}

// --------------------------------------------------
// 3. Weryfikacja ID tokenu u Google
// --------------------------------------------------
$credential = (string)$_POST['credential'];

$ctx = stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'KOSA-X-CMS/1.0']]);
$raw = @file_get_contents(
    'https://oauth2.googleapis.com/tokeninfo?id_token='.urlencode($credential),
    false,
    $ctx
);

$payload = $raw !== false ? json_decode($raw, true) : null;

$valid = is_array($payload)
    && ($payload['aud'] ?? '') === $clientId
    && in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
    && (int)($payload['exp'] ?? 0) > time()
    && ($payload['email_verified'] ?? '') === 'true'
    && !empty($payload['email'])
    && !empty($payload['sub']);

if (!$valid) {
    googleAuthRedirect($failUrl.'token');
}

$googleSub   = (string)$payload['sub'];
$googleEmail = mb_strtolower(trim((string)$payload['email']));
$googleName  = trim((string)($payload['name'] ?? ''));
if ($googleName === '') {
    $googleName = trim(((string)($payload['given_name'] ?? '')).' '.((string)($payload['family_name'] ?? '')));
}
if ($googleName === '') {
    $googleName = $googleEmail;
}

// --------------------------------------------------
// 3.5 LOGOWANIE DO PANELU ADMINISTRACYJNEGO (ten sam, zarejestrowany endpoint)
//
// Jeśli użytkownik przyszedł z przycisku "Zaloguj przez Google" na stronie
// logowania PANELU, przeglądarka niesie krótko żyjące, podpisane (HMAC)
// ciasteczko intencji "kx_admin_glogin". Wtedy — zamiast tworzyć konto klienta —
// logujemy do panelu, ale WYŁĄCZNIE gdy e-mail jest na białej liście
// $config['admin_google_emails']. Zwykłe logowanie klienta (bez ciasteczka)
// nie wywoła tej ścieżki, więc nie powstaje przypadkowa sesja admina.
// --------------------------------------------------
$adminIntent = (string)($_COOKIE['kx_admin_glogin'] ?? '');
if ($adminIntent !== '') {

    // ciasteczko intencji jest jednorazowe — od razu je czyścimy
    setcookie('kx_admin_glogin', '', time() - 3600, '/');

    $adminUrl  = '/'.ltrim((string)($config['admin_file'] ?? 'adm.php'), '/');
    $adminFail = $adminUrl.'?google_error=';

    // sekret HMAC = session_key_name z tabeli bin (ten sam po obu stronach)
    $sessionKey = '';
    try {
        $pdoAdmin = new PDO('sqlite:'.$config['dir_database'].'database.db');
        $pdoAdmin->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdoAdmin->exec('PRAGMA busy_timeout = 10000');
        $sessionKey = (string)$pdoAdmin->query('SELECT sValue FROM bin WHERE sKey = "session_key_name" LIMIT 1')->fetchColumn();
    } catch (Throwable $e) {
        googleAuthRedirect($adminFail.'server');
    }

    // walidacja podpisanego tokenu intencji: "timestamp.hmac(timestamp, sessionKey)"
    $intentValid = false;
    if ($sessionKey !== '' && strpos($adminIntent, '.') !== false) {
        list($intentTs, $intentSig) = explode('.', $adminIntent, 2);
        if (ctype_digit($intentTs) && (time() - (int)$intentTs) >= 0 && (time() - (int)$intentTs) <= 600) {
            $expectedSig = hash_hmac('sha256', $intentTs, $sessionKey);
            if (hash_equals($expectedSig, (string)$intentSig)) {
                $intentValid = true;
            }
        }
    }
    if (!$intentValid) {
        googleAuthRedirect($adminFail.'request');
    }

    // autoryzacja: e-mail MUSI być na białej liście administratorów
    $adminEmails = $config['admin_google_emails'] ?? array();
    if (!is_array($adminEmails)) {
        $adminEmails = array();
    }
    $adminEmails = array_map(static fn($e) => mb_strtolower(trim((string)$e)), $adminEmails);
    if (!in_array($googleEmail, $adminEmails, true)) {
        googleAuthRedirect($adminFail.'forbidden');
    }

    // sesja administratora — identycznie jak checkLogin() ($_SESSION[klucz] = 0)
    if( session_status() === PHP_SESSION_ACTIVE ){
        session_regenerate_id( true ); // ochrona przed fixacją sesji
    }
    $_SESSION[$sessionKey] = 0;
    @setcookie('bLicense'.str_replace('.', '', (string)($config['version'] ?? '')), '1', time() + 15552000, '/');
    googleAuthRedirect($adminUrl);
}

// --------------------------------------------------
// 4. Konto: znajdź po google_sub / e-mailu albo utwórz nowe
// --------------------------------------------------
try {
    $pdo = new PDO('sqlite:'.$config['dir_database'].'database.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA busy_timeout = 10000');

    // idempotentna migracja: identyfikator konta Google
    $hasSub = false;
    foreach ($pdo->query('PRAGMA table_info(users)') as $col) {
        if ($col['name'] === 'google_sub') {
            $hasSub = true;
            break;
        }
    }
    if (!$hasSub) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_sub TEXT");
    }

    // najpierw po google_sub, potem po e-mailu (łączenie istniejącego konta)
    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_sub = :sub LIMIT 1');
    $stmt->execute([':sub' => $googleSub]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $googleEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // konto istnieje (rejestracja klasyczna) — podpinamy Google
            $upd = $pdo->prepare('UPDATE users SET google_sub = :sub, updated_at = :now WHERE id = :id');
            $upd->execute([':sub' => $googleSub, ':now' => date('Y-m-d H:i:s'), ':id' => (int)$user['id']]);
        }
    }

    if (!$user) {
        // nowe konto: hasło losowe (logowanie hasłem możliwe po "resecie hasła"),
        // e-mail zweryfikowany przez Google → konto od razu aktywne
        $now = date('Y-m-d H:i:s');
        $randomHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);

        $ins = $pdo->prepare('
            INSERT INTO users (email, password_hash, name, phone, role, is_active,
                               created_at, updated_at, accept_terms, accept_newsletter, google_sub)
            VALUES (:email, :hash, :name, "", "user", 1, :created, :updated, 1, 0, :sub)
        ');
        $ins->execute([
            ':email'   => $googleEmail,
            ':hash'    => $randomHash,
            ':name'    => $googleName,
            ':created' => $now,
            ':updated' => $now,
            ':sub'     => $googleSub,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$pdo->lastInsertId()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        googleAuthRedirect($failUrl.'account');
    }

    // --------------------------------------------------
    // 5. Logowanie — sesja identyczna jak przy logowaniu hasłem
    // --------------------------------------------------
    if( session_status() === PHP_SESSION_ACTIVE ){
        session_regenerate_id( true ); // ochrona przed fixacją sesji
    }
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_role']  = $user['role'];

    $upd = $pdo->prepare('
        UPDATE users
        SET last_login_at = :at, last_login_ip = :ip, updated_at = :at2
        WHERE id = :id
    ');
    $upd->execute([
        ':at'  => date('Y-m-d H:i:s'),
        ':ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
        ':at2' => date('Y-m-d H:i:s'),
        ':id'  => (int)$user['id'],
    ]);

    googleAuthRedirect($userPageUrl.(strpos($userPageUrl, '?') === false ? '?' : '&').'view=panel');
} catch (Throwable $e) {
    googleAuthRedirect($failUrl.'server');
}
