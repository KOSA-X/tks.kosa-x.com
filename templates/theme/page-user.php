<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

$emailAdmin = $config['email'];
$nameAdmin = $config['logo'];


// --------------------------------------------------
// SESJA (gdyby nie była startnięta globalnie)
// --------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --------------------------------------------------
// ZMIENNE STANU
// --------------------------------------------------
$errors         = [];
$success        = '';
$isLoggedIn     = isLoggedIn();
$currentUser    = null;
$loggedUserName = '';

// --------------------------------------------------
// CSRF dla formularzy użytkownika (lokalny dla tej strony)
// --------------------------------------------------
if (!function_exists('userInitCsrfToken')) {
    function userInitCsrfToken(): string
    {
        if (empty($_SESSION['user_csrf_token'])) {
            $_SESSION['user_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['user_csrf_token'];
    }

    function userValidateCsrfToken(?string $token): bool
    {
        if (empty($_SESSION['user_csrf_token']) || $token === null) {
            return false;
        }
        return hash_equals($_SESSION['user_csrf_token'], $token);
    }
}

$csrfToken = userInitCsrfToken();

// --------------------------------------------------
// HEADER / TITLE jak w page-order.php
// --------------------------------------------------
$skinPath = 'templates/'.$config['skin'].'/';
require_once $skinPath.'_header.php';

if (!isset($aData['sName'])) {
    require_once $skinPath.'_404.php';
    require_once $skinPath.'_footer.php';
    exit;
}

require_once $skinPath.'_title.php';

// --------------------------------------------------
// Helpery walidacji / sanitizacji
// --------------------------------------------------
if (!function_exists('user_sanitize_field')) {
    function user_sanitize_field(?string $value, int $maxLen = 255): string
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

if (!function_exists('user_validate_phone')) {
    function user_validate_phone(string $phone): bool
    {
        return (bool)preg_match('/^[0-9+\s\-]{6,20}$/', $phone);
    }
}

// --------------------------------------------------
// Helper: budowa wartości dla formularza profilu
// --------------------------------------------------
if (!function_exists('user_build_profile_values')) {
    function user_build_profile_values(string $mode, ?array $currentUser): array
    {
        $fields = ['name', 'phone', 'email', 'street', 'city', 'code'];
        $values = [];

        foreach ($fields as $field) {
            $val = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST'
                && in_array(($_POST['action'] ?? ''), ['register', 'update_profile'], true)
                && isset($_POST[$field])
            ) {
                $val = (string)$_POST[$field];
            } elseif ($mode === 'edit' && $currentUser && isset($currentUser[$field])) {
                $val = (string)$currentUser[$field];
            }

            $values[$field] = $val;
        }

        return $values;
    }
}

// --------------------------------------------------
// AKTYWACJA Użytkownika
// --------------------------------------------------

 
if( isset( $_GET['activeUser'] ) ){
    
    
    // pobranie danych użytkownika po emailu
    $stmt = $oSql->prepare('SELECT * FROM users WHERE email = :email AND is_active=0 LIMIT 1');
    $stmt->execute([
        ':email' => $_GET['activeUser'] ?? ''
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // sprawdzenie czy znaleziono
    if ($user) {        
        $subject = 'Nowy status konta w serwisie '.($config['logo'] ?? '');
        $body = '
            <html>
            <head><title>'.$subject.'</title></head>
            <body>
                <p>Konto Użytkownika <strong>'.$user['name'].'</strong> ma nowy status:</p>
                <h4>'.getElement(1, $config['user_status']).'</h4>
                <hr>
                <small><i>Wiadomość wygenerowana automatycznie - '.BASE_URL.'</i></small>
            </body>
            </html>
        ';
        sendEmail([
            'to'        => $user['email'],
            'to_name'   => $user['name'],
            'subject'   => $subject,
            'body'      => $body,
            'from'      => $emailAdmin,
            'from_name' => $nameAdmin,
        ]);
        $oSql->query( 'UPDATE users SET is_active="1" WHERE id = "'.$user['id'].'"' );
        $success = $body;
        
    } else {
        echo 'Użytkownik nie znaleziony';
    }

}



// --------------------------------------------------
// Helper: render jednego formularza profilu (rejestracja / edycja)
// --------------------------------------------------
if (!function_exists('user_render_profile_form')) {
    function user_render_profile_form(
        string $mode,
        array $values,
        string $csrfToken,
        string $actionUrl
    ): void {
        $isEdit = ($mode === 'edit');
        global $lang, $config;
        $safe = [];
        foreach ($values as $k => $v) {
            $safe[$k] = htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
        }

        ?>
        <div class="card">
           <header class="card__header">
            <h5 class="card__title">
                <img src="<?= ICONS . "user.svg" ?>" alt="User icon">
                <?php echo $isEdit ? $lang['edit_profile'] : $lang['register']; ?>
            </h5>
            </header>

            <div class="card__content">
                <form action="<?php echo $actionUrl; ?>" method="post" class="form <?php echo $isEdit ? 'form-edit' : 'form-register'; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update_profile' : 'register'; ?>">

                    <div class="form-floating form-item">
                        <input
                            type="text"
                            class="form-control"
                            id="<?php echo $isEdit ? 'edit_name' : 'reg_name'; ?>"
                            name="name"
                            value="<?php echo $safe['name']; ?>"
                            placeholder="Imię i nazwisko"
                            required
                        >
                        <label for="<?php echo $isEdit ? 'edit_name' : 'reg_name'; ?>">
                            Imię i nazwisko <span class="text-danger">*</span>
                        </label>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-floating form-item">
                                <input
                                    type="text"
                                    class="form-control"
                                    id="<?php echo $isEdit ? 'edit_phone' : 'reg_phone'; ?>"
                                    name="phone"
                                    value="<?php echo $safe['phone']; ?>"
                                    placeholder="Telefon"
                                    required
                                >
                                <label for="<?php echo $isEdit ? 'edit_phone' : 'reg_phone'; ?>">
                                    Telefon <span class="text-danger">*</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating form-item">
                                <?php if ($isEdit): ?>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="edit_email"
                                        value="<?php echo $safe['email']; ?>"
                                        placeholder="Adres e-mail"
                                        disabled
                                    >
                                    <label for="edit_email">E-mail (niezmienny)</label>
                                <?php else: ?>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="reg_email"
                                        name="email"
                                        value="<?php echo $safe['email']; ?>"
                                        placeholder="Adres e-mail"
                                        required
                                    >
                                    <label for="reg_email">E-mail <span class="text-danger">*</span></label>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <!-- ADRES — tylko w edycji profilu; rejestracja wymaga
                         wyłącznie: imię i nazwisko, telefon, e-mail, hasło -->
                    <div class="form-floating form-item">
                        <input
                            type="text"
                            class="form-control"
                            id="edit_street"
                            name="street"
                            value="<?php echo $safe['street']; ?>"
                            placeholder="Ulica i numer"
                        >
                        <label for="edit_street">Ulica i numer</label>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-floating form-item">
                                <input
                                    type="text"
                                    class="form-control"
                                    id="edit_city"
                                    name="city"
                                    value="<?php echo $safe['city']; ?>"
                                    placeholder="Miejscowość"
                                >
                                <label for="edit_city">Miejscowość</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating form-item">
                                <input
                                    type="text"
                                    class="form-control city-code"
                                    maxlength="6"
                                    pattern="[0-9]{2}-[0-9]{3}"
                                    id="edit_code"
                                    name="code"
                                    value="<?php echo $safe['code']; ?>"
                                    placeholder="Kod pocztowy"
                                >
                                <label for="edit_code">Kod pocztowy</label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($isEdit): ?>

                        <!-- ZMIANA HASŁA (opcjonalna) -->
                        <div class="mt-3">
                            <h5>Zmiana hasła (opcjonalnie)</h5>
                            <p class="form-text mb-2 mt-1">Jeśli chcesz zmienić hasło, wypełnij poniższe pola.</p>

                            <div class="form-floating form-item">
                               
                                <input
                                    type="password"
                                    class="form-control"
                                    id="old_password"
                                    name="old_password"
                                    placeholder="Stare hasło"
                                >
                                <button type="button" class="togglePassword" id="togglePassword"><img src="<?php echo ICONS."eye.svg"; ?>" alt=""></button>
                                <label for="old_password">Stare hasło</label>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="form-floating form-item">
                                       
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="new_password"
                                            name="new_password"
                                            placeholder="Nowe hasło"
                                        >
                                        <button type="button" class="togglePassword" id="togglePassword"><img src="<?php echo ICONS."eye.svg"; ?>" alt=""></button>
                                        <label for="new_password">Nowe hasło</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-item">
                                       
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="new_password_confirm"
                                            name="new_password_confirm"
                                            placeholder="Powtórz nowe hasło"
                                        >
                                        <button type="button" class="togglePassword" id="togglePassword"><img src="<?php echo ICONS."eye.svg"; ?>" alt=""></button>
                                        <label for="new_password_confirm">Powtórz nowe hasło</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>

                        <!-- HASŁO + POWTÓRZ przy rejestracji -->
                        <div class="row">
                            <div class="col-6">
                                <div class="form-floating form-item">
                                   
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="reg_password"
                                        name="password"
                                        placeholder="Hasło"
                                        required
                                    >
                                    <button type="button" class="togglePassword" id="togglePassword"><img src="<?php echo ICONS."eye.svg"; ?>" alt=""></button>
                                    <label for="reg_password">Hasło <span class="text-danger">*</span></label>
                                    <span class="form-text">Min. 6 znaków</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-floating form-item">
                                   
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="reg_password_confirm"
                                        name="password_confirm"
                                        placeholder="Powtórz hasło"
                                        required
                                    >
                                    <button type="button" class="togglePassword" id="togglePassword"><img src="<?php echo ICONS."eye.svg"; ?>" alt=""></button>
                                    <label for="reg_password_confirm">Powtórz hasło <span class="text-danger">*</span></label>
                           
                                </div>
                            </div>
                        </div>

                        <!-- ZGODY przy rejestracji -->
                        <div class="form-check form-item mt-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="accept_terms"
                                name="accept_terms"
                                value="1"
                                <?php echo !empty($_POST['accept_terms']) ? 'checked' : ''; ?>
                                required
                            >
                            <label class="form-check-label" for="accept_terms">
                                <?= acceptLabel(); ?>
                            </label>
                        </div>
                        <!--
                        <div class="form-check form-item">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="accept_newsletter"
                                name="accept_newsletter"
                                value="1"
                                <?php echo !empty($_POST['accept_newsletter']) ? 'checked' : ''; ?>
                            >
                            <label class="form-check-label" for="accept_newsletter">
                                Chcę otrzymywać informacje handlowe (newsletter).
                            </label>
                        </div>
                        -->

                    <?php endif; ?>

                    <div class="form-button mt-3 flex-justify">
                        <button type="submit" class="button">
                            <?php echo $isEdit ? 'Zapisz zmiany' : 'Załóż konto'; ?>
                        </button>

                        <?php if ($isEdit): ?>
                            <a href="<?php echo $actionUrl ? htmlspecialchars(strtok($actionUrl, '?'), ENT_QUOTES, 'UTF-8') : ''; ?>?view=panel"
                               class="button button-light">
                                Anuluj
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}



// Widok (tryb) strony – przełączany przez GET
$view = $_GET['view'] ?? 'auth'; // auth | panel | edit | reset

// --------------------------------------------------
// Pobranie bieżącego użytkownika (jeśli zalogowany)
// --------------------------------------------------
if ($isLoggedIn) {
    $userId = (int)$_SESSION['user_id'];

    $stmt = $oSql->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        // Konto zniknęło – czyścimy sesję
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_email'],
            $_SESSION['user_name'],
            $_SESSION['user_role']
        );
        $isLoggedIn  = false;
        $currentUser = null;
        $view        = 'auth';
    } else {
        $loggedUserName = $currentUser['name'] ?: $currentUser['email'];
    }
}

// Bazowy URL tej strony
$baseUrl = getUrl($config['user_page']);

// --------------------------------------------------
// OBSŁUGA POST: rejestracja / logowanie / edycja / wylogowanie / reset
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $postToken = $_POST['csrf_token'] ?? '';

    if (!userValidateCsrfToken($postToken)) {
        $errors[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {

        // WYLOGOWANIE
        if ($action === 'logout') {
            unset(
                $_SESSION['user_id'],
                $_SESSION['user_email'],
                $_SESSION['user_name'],
                $_SESSION['user_role']
            );
            $isLoggedIn     = false;
            $currentUser    = null;
            $loggedUserName = '';
            $success        = 'Zostałeś wylogowany.';
            $view           = 'auth';
        }

        // REJESTRACJA
        elseif ($action === 'register') {

            $name     = user_sanitize_field($_POST['name'] ?? '', 200);
            $email    = user_sanitize_field($_POST['email'] ?? '', 190);
            $phone    = user_sanitize_field($_POST['phone'] ?? '', 30);
            $street   = user_sanitize_field($_POST['street'] ?? '', 255);
            $city     = user_sanitize_field($_POST['city'] ?? '', 150);
            $code     = user_sanitize_field($_POST['code'] ?? '', 20);
            $password = (string)($_POST['password'] ?? '');
            $password2= (string)($_POST['password_confirm'] ?? '');

            $acceptTerms      = !empty($_POST['accept_terms']) ? 1 : 0;
            $acceptNewsletter = !empty($_POST['accept_newsletter']) ? 1 : 0;

            // Walidacja pól — rejestracja wymaga TYLKO:
            // imię i nazwisko, telefon, e-mail, hasło (adres opcjonalnie w edycji)
            if ($name === '') {
                $errors[] = 'Podaj imię i nazwisko.';
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Podaj poprawny adres e-mail.';
            }

            if ($phone === '' || !user_validate_phone($phone)) {
                $errors[] = 'Podaj poprawny numer telefonu.';
            }

            if ($password === '' || mb_strlen($password, 'UTF-8') < 6) {
                $errors[] = 'Hasło musi mieć co najmniej 6 znaków.';
            }

            if ($password !== $password2) {
                $errors[] = 'Hasła nie są identyczne.';
            }

            if (!$acceptTerms) {
                $errors[] = 'Musisz zaakceptować regulamin, aby założyć konto.';
            }

            // Unikalność e-maila
            if (empty($errors)) {
                $stmt = $oSql->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $exists = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($exists) {
                    $errors[] = 'Konto z takim adresem e-mail już istnieje.';
                }
            }

            if (empty($errors)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $now = date('Y-m-d H:i:s');

                $stmt = $oSql->prepare('
                    INSERT INTO users (
                        email,
                        password_hash,
                        name,
                        phone,
                        street,
                        city,
                        code,
                        role,
                        is_active,
                        activation_token,
                        activation_expires_at,
                        reset_token,
                        reset_expires_at,
                        last_login_at,
                        last_login_ip,
                        created_at,
                        updated_at,
                        accept_terms,
                        accept_newsletter
                    ) VALUES (
                        :email,
                        :password_hash,
                        :name,
                        :phone,
                        :street,
                        :city,
                        :code,
                        :role,
                        :is_active,
                        :activation_token,
                        :activation_expires_at,
                        :reset_token,
                        :reset_expires_at,
                        :last_login_at,
                        :last_login_ip,
                        :created_at,
                        :updated_at,
                        :accept_terms,
                        :accept_newsletter
                    )
                ');

                $stmt->execute([
                    ':email'                => $email,
                    ':password_hash'        => $passwordHash,
                    ':name'                 => $name,
                    ':phone'                => $phone,
                    ':street'               => $street,
                    ':city'                 => $city,
                    ':code'                 => $code,
                    ':role'                 => 'user',
                    ':is_active'            => 0,
                    ':activation_token'     => null,
                    ':activation_expires_at'=> null,
                    ':reset_token'          => null,
                    ':reset_expires_at'     => null,
                    ':last_login_at'        => null,
                    ':last_login_ip'        => null,
                    ':created_at'           => $now,
                    ':updated_at'           => $now,
                    ':accept_terms'         => $acceptTerms,
                    ':accept_newsletter'    => $acceptNewsletter,
                ]);

                // E-mail z danymi konta (login + hasło)
                // UWAGA: przesyłanie hasła w czystym tekście NIE jest bezpieczne,
                // ale jest to zrobione na Twoje życzenie.
                if (function_exists('sendEmail')) {
                    $safeName     = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                    $safeEmail    = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
                    $safePhone    = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
                    $safeStreet   = htmlspecialchars($street, ENT_QUOTES, 'UTF-8');
                    $safeCity     = htmlspecialchars($city, ENT_QUOTES, 'UTF-8');
                    $safeCode     = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
                    $safePassword = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');

                    $subject = 'Nowe konto - '.$config['logo'];
//                    <p>Aktualnie czeka na weryfikację przez Administratora.</p>
                    $body = '
                        <html>
                        <head><title>'.$subject.'</title></head>
                        <body>
                            <h3>Twoje konto w serwisie zostało utworzone</h3>
                            
                            <h4>Dane konta:</h4>
                            <ul>
                                <li><strong>Imię i nazwisko:</strong> '.$safeName.'</li>
                                <li><strong>E-mail (login):</strong> '.$safeEmail.'</li>
                                <li><strong>Hasło:</strong> ***</li>
                                <li><strong>Telefon:</strong> '.$safePhone.'</li>
                                '.($safeStreet !== '' ? '<li><strong>Adres:</strong> '.$safeStreet.', '.$safeCode.' '.$safeCity.'</li>' : '').'
                            </ul>
                            <p>Logowanie dostępne na stronie: '.htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8').'</p>
                            <hr>
                            <small><i>Wiadomość wygenerowana automatycznie - '.BASE_URL.'</i></small>
                        </body>
                        </html>
                    ';
                    
                    $body_admin = '
                        <html>
                        <head><title>'.$subject.'</title></head>
                        <body>
                            <ul>
                                <li><strong>Imię i nazwisko:</strong> '.$safeName.'</li>
                                <li><strong>E-mail (login):</strong> '.$safeEmail.'</li>
                                <li><strong>Hasło:</strong> ***</li>
                                <li><strong>Telefon:</strong> '.$safePhone.'</li>
                                '.($safeStreet !== '' ? '<li><strong>Adres:</strong> '.$safeStreet.', '.$safeCode.' '.$safeCity.'</li>' : '').'
                            </ul>
                            <h4><a href="'.htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8').'?activeUser='.$safeEmail.'">Aktywuj użytkownika</a></h4>
                            <hr>
                            <small><i>Wiadomość wygenerowana automatycznie - '.BASE_URL.'</i></small>
                        </body>
                        </html>
                    ';

                    @sendEmail([
                        'to'        => $email,
                        'to_name'   => $name,
                        'subject'   => $subject,
                        'body'      => $body,
                        'from'      => $emailAdmin,
                        'from_name' => $nameAdmin,
                    ]);
                    
                    
                    @sendEmail([
                        'to'        => $emailAdmin,
                        'to_name'   => $config['logo'],
                        'subject'   => $subject,
                        'body'      => $body_admin,
                        'from'      => $email,
                        'from_name' => $name,
                    ]);
                    
                }

                $success = 'Rejestracja przebiegła pomyślnie. Możesz się zalogować.';
//                <strong>'.getElement(0, $config['user_status']).'</strong>
                $_POST   = []; // czyścimy formularz rejestracji
                $view    = 'auth';
            }
        }

        // LOGOWANIE
        elseif ($action === 'login') {

            $email    = user_sanitize_field($_POST['email'] ?? '', 190);
            $password = (string)($_POST['password'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Podaj poprawny adres e-mail.';
            }

            if ($password === '') {
                $errors[] = 'Podaj hasło.';
            }

            if (empty($errors)) {
                $stmt = $oSql->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $errors[] = 'Nieprawidłowy e-mail lub hasło.';
//                } elseif ((int)$user['is_active'] == 0) {
//                    $errors[] = 'Konto ma status <strong>'.getElement(0, $config['user_status']).'</strong>';
                } else {
                    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                        session_regenerate_id(true); // ochrona przed fixacją sesji
                    }
                    $_SESSION['user_id']    = (int)$user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['user_role']  = $user['role'];

                    $isLoggedIn     = true;
                    $currentUser    = $user;
                    $loggedUserName = $user['name'] ?: $user['email'];
                    $success        = 'Zalogowano pomyślnie.';
                    $view           = 'panel';

                    // Aktualizacja last_login
                    $stmtUpdate = $oSql->prepare('
                        UPDATE users
                        SET last_login_at = :last_login_at,
                            last_login_ip = :last_login_ip,
                            updated_at    = :updated_at
                        WHERE id = :id
                    ');
                    $stmtUpdate->execute([
                        ':last_login_at' => date('Y-m-d H:i:s'),
                        ':last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                        ':updated_at'    => date('Y-m-d H:i:s'),
                        ':id'            => $user['id'],
                    ]);
                }
            }
        }

        // ŻĄDANIE RESETU HASŁA
        elseif ($action === 'request_reset') {

            $email = user_sanitize_field($_POST['email'] ?? '', 190);

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Podaj poprawny adres e-mail.';
            }

            if (empty($errors)) {
                $stmt = $oSql->prepare('SELECT id, email, name, is_active FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Generujemy token tylko jeśli konto jest aktywne i nie jest zablokowane
                if ($user && (int)$user['is_active'] === 1) {
                    $token     = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 godzina ważności

                    $stmtUpd = $oSql->prepare('
                        UPDATE users
                        SET reset_token = :token,
                            reset_expires_at = :expires,
                            updated_at = :updated_at
                        WHERE id = :id
                    ');
                    $stmtUpd->execute([
                        ':token'      => $token,
                        ':expires'    => $expiresAt,
                        ':updated_at' => date('Y-m-d H:i:s'),
                        ':id'         => (int)$user['id'],
                    ]);

                    if (function_exists('sendEmail')) {
                        $subject    = 'Reset hasła w serwisie '.($config['logo'] ?? '');
                        $resetLink  = $baseUrl.'?view=reset&token='.urlencode($token);
                        $safeName   = htmlspecialchars($user['name'] ?: $user['email'], ENT_QUOTES, 'UTF-8');
                        $safeLink   = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

                        $body = '
                            <html>
                            <head><title>'.$subject.'</title></head>
                            <body>
                                <h3>Reset hasła</h3>
                                <p>Witaj '.$safeName.',</p>
                                <p>Otrzymaliśmy prośbę o zresetowanie hasła do Twojego konta w serwisie '.($config['logo'] ?? '').'.</p>
                                <p>Aby ustawić nowe hasło, kliknij w poniższy link lub skopiuj go do przeglądarki:</p>
                                <p><a href="'.$safeLink.'">'.$safeLink.'</a></p>
                                <p>Link jest ważny przez 1 godzinę.</p>
                                <p>Jeśli to nie Ty inicjowałeś reset hasła, możesz zignorować tę wiadomość.</p>
                                <hr>
                                <small><i>Wiadomość wygenerowana automatycznie.</i></small>
                            </body>
                            </html>
                        ';

                        @sendEmail([
                            'to'        => $user['email'],
                            'to_name'   => $user['name'],
                            'subject'   => $subject,
                            'body'      => $body,
                            'from'      => $emailAdmin,
                            'from_name' => $nameAdmin,
                        ]);
                    }
                }
                
                if($user && (int)$user['is_active'] === 0){
                    $errors[] = 'Konto zostało zablokowane.';
                }else{
                    // Zawsze ten sam komunikat (bez ujawniania, czy mail istnieje)
                    $success = 'Jeśli podany adres e-mail istnieje w naszej bazie, wysłaliśmy na niego instrukcję resetu hasła.';
                    $view    = 'reset';
                }

                
            }
        }

        // USTAWIENIE NOWEGO HASŁA PO KLIKU W LINK
        elseif ($action === 'set_new_password') {

            $resetToken   = (string)($_POST['reset_token'] ?? '');
            $newPassword  = (string)($_POST['password'] ?? '');
            $newPassword2 = (string)($_POST['password_confirm'] ?? '');

            if ($resetToken === '' || strlen($resetToken) < 32) {
                $errors[] = 'Link resetujący jest nieprawidłowy lub wygasł.';
            }

            if ($newPassword === '' || mb_strlen($newPassword, 'UTF-8') < 6) {
                $errors[] = 'Nowe hasło musi mieć co najmniej 6 znaków.';
            }

            if ($newPassword !== $newPassword2) {
                $errors[] = 'Nowe hasła nie są identyczne.';
            }

            if (empty($errors)) {
                $stmt = $oSql->prepare('SELECT * FROM users WHERE reset_token = :token LIMIT 1');
                $stmt->execute([':token' => $resetToken]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $errors[] = 'Link resetujący jest nieprawidłowy lub wygasł.';
                } else {
                    $expiresAt = $user['reset_expires_at'] ?? null;
                    if ($expiresAt && strtotime($expiresAt) < time()) {
                        $errors[] = 'Link resetujący wygasł. Poproś o nowy reset hasła.';
                    }
                }

                if (empty($errors) && $user) {
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                    $stmtUpd = $oSql->prepare('
                        UPDATE users
                        SET password_hash   = :hash,
                            reset_token     = NULL,
                            reset_expires_at= NULL,
                            updated_at      = :updated_at
                        WHERE id = :id
                    ');
                    $stmtUpd->execute([
                        ':hash'       => $newPasswordHash,
                        ':updated_at' => date('Y-m-d H:i:s'),
                        ':id'         => (int)$user['id'],
                    ]);

                    $success = 'Hasło zostało zmienione. Możesz zalogować się nowym hasłem.';
                    $view    = 'auth';
                }
            }
        }

        // AKTUALIZACJA PROFILU (w tym zmiana hasła zalogowanego)
        elseif ($action === 'update_profile') {

            if (!$isLoggedIn || !$currentUser) {
                $errors[] = 'Musisz być zalogowany, aby edytować profil.';
            } else {

                $name   = user_sanitize_field($_POST['name'] ?? '', 200);
                $phone  = user_sanitize_field($_POST['phone'] ?? '', 30);
                $street = user_sanitize_field($_POST['street'] ?? '', 255);
                $city   = user_sanitize_field($_POST['city'] ?? '', 150);
                $code   = user_sanitize_field($_POST['code'] ?? '', 20);

                $oldPassword        = (string)($_POST['old_password'] ?? '');
                $newPassword        = (string)($_POST['new_password'] ?? '');
                $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

                // Walidacja danych (adres jest opcjonalny)
                if ($name === '') {
                    $errors[] = 'Podaj imię i nazwisko.';
                }

                if ($phone === '' || !user_validate_phone($phone)) {
                    $errors[] = 'Podaj poprawny numer telefonu.';
                }


                $updatePassword  = false;
                $newPasswordHash = null;

                // Zmiana hasła tylko jeśli nowe hasło podane
                if ($newPassword !== '' || $newPasswordConfirm !== '') {

                    if ($oldPassword === '') {
                        $errors[] = 'Podaj stare hasło, aby je zmienić.';
                    } elseif (!password_verify($oldPassword, $currentUser['password_hash'])) {
                        $errors[] = 'Stare hasło jest nieprawidłowe.';
                    }

                    if ($newPassword === '' || mb_strlen($newPassword, 'UTF-8') < 6) {
                        $errors[] = 'Nowe hasło musi mieć co najmniej 6 znaków.';
                    }

                    if ($newPassword !== $newPasswordConfirm) {
                        $errors[] = 'Nowe hasła nie są identyczne.';
                    }

                    if (empty($errors)) {
                        $updatePassword  = true;
                        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    }
                }

                if (empty($errors)) {
                    $params = [
                        ':name'       => $name,
                        ':phone'      => $phone,
                        ':street'     => $street,
                        ':city'       => $city,
                        ':code'       => $code,
                        ':updated_at' => date('Y-m-d H:i:s'),
                        ':id'         => (int)$currentUser['id'],
                    ];

                    $sql = '
                        UPDATE users
                        SET name   = :name,
                            phone  = :phone,
                            street = :street,
                            city   = :city,
                            code   = :code,
                            updated_at = :updated_at
                    ';

                    if ($updatePassword && $newPasswordHash !== null) {
                        $sql .= ', password_hash = :password_hash';
                        $params[':password_hash'] = $newPasswordHash;
                    }

                    $sql .= ' WHERE id = :id';

                    $stmt = $oSql->prepare($sql);
                    $stmt->execute($params);

                    // Odśwież dane
                    $stmt2 = $oSql->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
                    $stmt2->execute([':id' => (int)$currentUser['id']]);
                    $currentUser           = $stmt2->fetch(PDO::FETCH_ASSOC);
                    $loggedUserName        = $currentUser['name'] ?: $currentUser['email'];
                    $_SESSION['user_name'] = $currentUser['name'];

                    $success = 'Dane profilu zostały zaktualizowane.';
                    if ($updatePassword) {
                        $success .= ' Hasło zostało zmienione.';
                    }

                    $view = 'panel';
                }
            }
        }
    }
}

// Po zalogowaniu domyślnie pokazuj panel, jeśli ktoś trafi na view=auth
if ($isLoggedIn && $view === 'auth') {
    $view = 'panel';
}

// --------------------------------------------------
// Błędy logowania Google (redirect z plugins/google-auth.php)
// --------------------------------------------------
if (isset($_GET['google_error'])) {
    $googleErrors = [
        'disabled' => 'Logowanie przez Google jest obecnie wyłączone.',
        'csrf'     => 'Sesja logowania Google wygasła. Spróbuj ponownie.',
        'token'    => 'Nie udało się zweryfikować konta Google. Spróbuj ponownie.',
        'account'  => 'Nie udało się utworzyć konta. Skontaktuj się z nami.',
        'server'   => 'Wystąpił błąd serwera podczas logowania Google. Spróbuj ponownie.',
        'request'  => 'Nieprawidłowe żądanie logowania Google.',
    ];
    $errors[] = $googleErrors[$_GET['google_error']] ?? $googleErrors['server'];
}

?>
<div class="container pageUser">
    <div class="mainPage__wrapper">
        <div class="mainPage__content">

            <?php
            // Krótszy opis strony
            if (!empty($aData['sDescriptionShort'])) {
                echo '<div class="mainPage__desc font-md">'.$aData['sDescriptionShort'].'</div>';
            }

            // Komunikaty błędów
            if (!empty($errors)) {
                echo '<div class="alert alert-danger">';
                echo implode('<br>', $errors);
                echo '</div>';
            }

            // Komunikat sukcesu
            if ($success !== '') {
                echo '<div class="alert alert-success">'.$success.'</div>';
            }
            ?>

            <?php if ($view === 'panel' && $isLoggedIn && $currentUser): ?>

                <!-- PULPIT UŻYTKOWNIKA -->
                <div class="card mb-4">
                   <header class="card__header">
                    <h5 class="card__title">
                        <img src="<?= ICONS . "user.svg" ?>" alt="User icon">
                        Witaj, <?php echo html($loggedUserName); ?> 👋
                    </h5>
                    </header>

                    <div class="card__wrapper">
                        <ul class="card__list">
                            <li>
                                <div class="label">E-mail:</div>
                                <div class="value"><?php echo html($currentUser['email']); ?></div>
                            </li>
                            <li>
                                <div class="label">Telefon:</div>
                                <div class="value"><?php echo html($currentUser['phone']); ?></div>
                            </li>
                            <?php if (!empty($currentUser['street']) || !empty($currentUser['city'])): ?>
                            <li>
                                <div class="label">Adres:</div>
                                <div class="value">
                                    <?php
                                    echo html($currentUser['street'])
                                        . ', '
                                        . html($currentUser['code'])
                                        . ' '
                                        . html($currentUser['city']);
                                    ?>
                                </div>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="card__footer flex-justify">
                        <a href="<?php echo $baseUrl; ?>?view=edit" class="button button-light button-xs">
                                    Edytuj profil
                                </a>
                                <form action="<?php echo $baseUrl; ?>" method="post" class="form form-logout" style="display:inline-block;">
                                    <input type="hidden" name="action" value="logout">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="button button-light button-xs">
                                        Wyloguj się
                                    </button>
                                </form>
                    </div>
                </div>
                
                <?php if ($isLoggedIn && !empty($currentUser['id'])): 

    // --------------------------------------------------
    // MOJE REZERWACJE – bezpieczne pobieranie po iUserId
    // --------------------------------------------------
    $stmt = $oSql->prepare("
        SELECT iForm, sDateStart, sDateEnd, sTime, sCategory
        FROM form
        WHERE iUserId = :uid
        ORDER BY sDateStart ASC
    ");
    $stmt->execute([':uid' => (int)$currentUser['id']]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="card mb-4">
   <header class="card__header">
    <h5 class="card__title">
        <img src="<?= ICONS . "calendar.svg" ?>" alt="Kalendarz">
        Moje rezerwacje
    </h5>
    </header>

    <?php if (empty($reservations)): ?>

        <div class="card__content">
            Nie masz jeszcze żadnych rezerwacji.
        </div>

    <?php else: ?>

        <div class="card__wrapper table-responsive">
           
            <table class="table ">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Godzina</th>
                        <th>Usługa</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($reservations as $r): ?>

                    <?php
                        // DATA
                        $datePretty = '';
                        if (!empty($r['sDateStart'])) {
                            $dt = DateTime::createFromFormat('Y-m-d', $r['sDateStart']);
                            if ($dt) {
                                $datePretty = $dt->format('d.m.Y');
                            }
                        }

                        // GODZINA (sTime ma np. "16:00 - 17:30")
                        $timePretty = !empty($r['sTime']) ? $r['sTime'] : '';

                        // USŁUGA z configu
                        $service = getElement($r['sCategory'], $config['form_categories']);
                    ?>

                    <tr>
                        <td><?= (int)$r['iForm'] ?></td>
                        <td><?= html($datePretty) ?></td>
                        <td><?= html($timePretty) ?></td>
                        <td><?= html($service) ?></td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>
        

    <?php endif; ?>
    <div class="card__footer">
        <a href="<?= getUrl($config['form_page']); ?>" class="button button-light button-xs">+ Nowa rezerwacja</a>
    </div>
</div>

<?php endif; ?>

                
                
                <?php if ($isLoggedIn && !empty($currentUser['id'])): 
    // --------------------------------------------------
    // MOJE ZAMÓWIENIA (lista z tabeli orders powiązana po user_id)
    // --------------------------------------------------
    $oSql      = Sql::getInstance();
    $userId    = (int) $currentUser['id'];
    $ordersRows = '';

    // pobieramy tylko zamówienia zalogowanego użytkownika
    $oQuery = $oSql->getQuery('
        SELECT *
        FROM orders
        WHERE user_id = '.$userId.'
        ORDER BY date DESC
    ');

    while ($order = $oQuery->fetch(PDO::FETCH_ASSOC)) {

        // ID zamówienia
        $orderId = isset($order['id']) ? (int)$order['id'] : 0;

        // data złożenia
        $createdAt = !empty($order['date'])
            ? date('d.m.Y H:i', (int)$order['date'])
            : '';

        // kwota
        $price = isset($order['price'])
            ? number_format((float)$order['price'], 2, ',', ' ').' '.($config['currency'] ?? 'zł')
            : '—';

        // dostawa / płatność z configu
        $deliveryLabel = isset($order['delivery'])
            ? getElementArray($order['delivery'], $config['order_delivery'])
            : '';
        $paymentLabel  = isset($order['payment'])
            ? getElementArray($order['payment'], $config['order_payment'])
            : '';

        // status z configu (tak jak w adminie)
        $statusValue = isset($order['status']) ? (int)$order['status'] : 0;
        $statusLabel = getElement($statusValue, $config['order_status']);

        // prosty CSS statusu (jak w rezerwacjach)
        $statusClass = '';
        if ($statusValue === 0) {
            $statusClass = 'badge-warning';
        } elseif ($statusValue === 2) {
            $statusClass = 'badge-success';
        } else {
            $statusClass = 'badge-secondary';
        }
        
        $tokenSecret = $config['orderKey'] ?? ($config['secretKey'] ?? BASE_URL);
        $orderToken  = hash('sha256', $orderId.'|'.$order['date'].'|'.$tokenSecret);
        
        $paymentPageUrl = getUrl($config['payment_page']);
        $continueUrl    = rtrim($paymentPageUrl ?? '', '/').'?order='.$orderId.'&token='.$orderToken;

        $ordersRows .= '
            <tr>
                <td><a href="'.$continueUrl.'">Zamówienie '.$orderId.'</a></td>
                <td>'.$createdAt.'</td>
                <td>'.$price.'</td>
                <td>'.$deliveryLabel.'</td>
                <td>'.$paymentLabel.'</td>
                <td><span class="badge '.$statusClass.'">'.$statusLabel.'</span></td>
            </tr>
        ';
    }
    ?>

    <div class="card">
       <header class="card__header">
        <h5 class="card__title">
            <img src="<?= ICONS . "cart.svg" ?>" alt="Koszyk">
            Moje zamówienia
        </h5>
        </header>
        
        <?php if (empty($ordersRows)): ?>

            <div class="card__content">
                Brak zamówień w sklepie.
            </div>

        <?php else: ?>

            <div class="card__wrapper table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Kwota</th>
                            <th>Dostawa</th>
                            <th>Płatność</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?= $ordersRows; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

<?php endif; ?>

            

                
                
 

            <?php elseif ($view === 'edit' && $isLoggedIn && $currentUser): ?>

                <!-- PANEL + FORMULARZ EDYCJI -->
                <div class="userEdit">
                    <?php
                    $editValues = user_build_profile_values('edit', $currentUser);
                    user_render_profile_form('edit', $editValues, $csrfToken, $baseUrl.'?view=edit');
                    ?>
                </div>

            <?php elseif ($view === 'reset'): ?>

                <?php
                // Jeśli w URL jest token – próbujemy sprawdzić, czy jest potencjalnie poprawny
                $resetToken     = $_GET['token'] ?? '';
                $tokenUserValid = null;

                if ($resetToken !== '') {
                    $stmt = $oSql->prepare('SELECT id, email, name, reset_expires_at FROM users WHERE reset_token = :token LIMIT 1');
                    $stmt->execute([':token' => $resetToken]);
                    $tokenUserValid = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($tokenUserValid) {
                        $expiresAt = $tokenUserValid['reset_expires_at'] ?? null;
                        if ($expiresAt && strtotime($expiresAt) < time()) {
                            $tokenUserValid = null;
                        }
                    }
                }
                ?>

                <div class="userReset mt-4">
                    <?php if ($resetToken !== '' && $tokenUserValid): ?>

                        <!-- FORMULARZ USTAWIENIA NOWEGO HASŁA -->
                        <div class="card">
                           <header class="card__header">
                            <h5 class="card__title">
                                <img src="<?= ICONS . "user.svg" ?>" alt="User icon">
                                Ustaw nowe hasło
                            </h5>
                            </header>
                            <div class="card__content">
                                <p class="mb-3">Wprowadź nowe hasło dla swojego konta.</p>

                                <form action="<?php echo $baseUrl; ?>?view=reset&amp;token=<?php echo htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="form form-reset form-reset-set">
                                    <input type="hidden" name="action" value="set_new_password">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8'); ?>">

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-floating form-item">
                                                <input
                                                    type="password"
                                                    class="form-control"
                                                    id="reset_password"
                                                    name="password"
                                                    placeholder="Nowe hasło"
                                                    required
                                                >
                                                <label for="reset_password">Nowe hasło <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-floating form-item">
                                                <input
                                                    type="password"
                                                    class="form-control"
                                                    id="reset_password_confirm"
                                                    name="password_confirm"
                                                    placeholder="Powtórz nowe hasło"
                                                    required
                                                >
                                                <label for="reset_password_confirm">Powtórz nowe hasło <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-button mt-3 flex-justify">
                                        <button type="submit" class="button">Zapisz nowe hasło</button>
                                        <a href="<?php echo $baseUrl; ?>" class="link_underline">Wróć do logowania</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php else: ?>

                        <!-- FORMULARZ ŻĄDANIA RESETU HASŁA -->
                        <div class="card">
                           <header class="card__header">
                            <h5 class="card__title">
                                <img src="<?= ICONS . "user.svg" ?>" alt="User icon">
                                Nie pamiętasz hasła?
                            </h5>
                            </header>
                            <div class="card__content">
                                <p class="mb-3">
                                    Podaj adres e-mail, którego używasz do logowania.
                                    Wyślemy Ci link do ustawienia nowego hasła.
                                </p>

                                <form action="<?php echo $baseUrl; ?>?view=reset" method="post" class="form form-reset form-reset-request">
                                    <input type="hidden" name="action" value="request_reset">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                                    <div class="form-floating form-item">
                                        <input
                                            type="email"
                                            class="form-control"
                                            id="reset_email"
                                            name="email"
                                            value="<?php
                                                if (isset($_POST['email']) && ($view === 'reset')) {
                                                    echo htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8');
                                                }
                                            ?>"
                                            placeholder="Adres e-mail"
                                            required
                                        >
                                        <label for="reset_email">Adres e-mail <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="form-button mt-3 flex-justify">
                                        <button type="submit" class="button">Wyślij link</button>
                                        <a href="<?php echo $baseUrl; ?>" class="link_underline">Wróć</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>

            <?php else: ?>

                <!-- DOMYŚLNIE: REJESTRACJA + LOGOWANIE -->
                <div class="row pageUser__form">

                    

                    <!-- PRAWA KOLUMNA – REJESTRACJA  -->
                    <div class="col-6 mb-4">
                        <div class="card">
                           <header class="card__header">
                            <h5 class="card__title">
                                <img src="<?= ICONS . "user.svg" ?>" alt="User icon">
                                <?= $lang['login_title'] ?>
                            </h5>
                            </header>

                            <div class="card__content">
                                <form action="<?php echo $baseUrl; ?>" method="post" class="form form-login">
                                    <input type="hidden" name="action" value="login">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                                    <div class="form-floating form-item">
                                        <input
                                            type="email"
                                            class="form-control"
                                            id="login_email"
                                            name="email"
                                            value="<?php echo old('email'); ?>"
                                            placeholder="Adres e-mail"
                                        >
                                        <label for="login_email">E-mail</label>
                                    </div>

                                    <div class="form-floating form-item">
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="login_password"
                                            name="password"
                                            placeholder="Hasło"
                                        >
                                        <button type="button" class="togglePassword" id="togglePassword"><img src="<?php echo ICONS."eye.svg"; ?>" alt=""></button>
                                        <label for="login_password">Hasło</label>
                                        
                                    </div>

                                    <div class="form-button flex-justify">
                                        <button type="submit" class="button">
                                            <?= $lang['login_button'] ?>
                                        </button>


                                    <a href="<?php echo $baseUrl; ?>?view=reset" class="link_underline">
                                        Reset hasła
                                    </a>
                                    </div>
                                </form>

                                <?php if (!empty($config['google_client_id'])): ?>
                                <!-- LOGOWANIE PRZEZ GOOGLE (przycisk + One Tap) -->
                                <div class="googleSignIn">
                                    <div class="googleSignIn__separator">
                                        <span><?php echo $lang['or'] ?? 'albo'; ?></span>
                                    </div>
                                    <div id="g_id_onload"
                                         data-client_id="<?php echo html($config['google_client_id']); ?>"
                                         data-login_uri="<?php echo BASE_URL; ?>/plugins/google-auth.php"
                                         data-context="signin"
                                         data-ux_mode="redirect"
                                         data-auto_prompt="true">
                                    </div>
                                    <div class="g_id_signin"
                                         data-type="standard"
                                         data-theme="filled_black"
                                         data-size="large"
                                         data-text="continue_with"
                                         data-shape="rectangular"
                                         data-logo_alignment="left"
                                         data-locale="pl">
                                    </div>
                                </div>
                                <script src="https://accounts.google.com/gsi/client" async defer></script>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- LEWA KOLUMNA – LOGOWANIE -->
                    <div class="col-6">
                        <?php
                        $registerValues = user_build_profile_values('register', null);
                        user_render_profile_form('register', $registerValues, $csrfToken, $baseUrl);
                        ?>
                    </div>

                </div>

            <?php endif; ?>

            <?php
            // Pełny opis strony
            if (!empty($aData['sDescriptionFull']) && $aData['sDescriptionFull'] !== $aData['sDescriptionShort']) {
                echo '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>';
            }
            ?>
        </div>
    </div>
</div>

<?php
require_once $skinPath.'_footer.php';
