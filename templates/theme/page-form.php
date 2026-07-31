<?php
// page-form.php (uniwersalny)

$mode = 'visit'; // domyślnie: 'range' (nocleg) lub 'visit' (wizyta)

/**
 * Czy logowanie jest wymagane do złożenia rezerwacji?
 * 0 = nie, 1 = tak
 */
$login_required = 0; // <-- ustaw na 1, jeśli rezerwacja tylko dla zalogowanych

if (!defined('CUSTOMER_PAGE')) {
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $config, $oPage, $oSql;

// kategorie usług z configu
$formCategories = $config['form_categories'] ?? [];

// --------------------------------------------------
// Logowanie / bieżący użytkownik
// --------------------------------------------------
$isLoggedIn  = !empty($_SESSION['user_id']);
$currentUser = null;

// link do strony logowania
$loginPageUrl = getUrl($config['user_page']);

if ($isLoggedIn) {
    $userId = (int) $_SESSION['user_id'];

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
    }
}

$successMsg = '';
$errorMsg   = '';

// =====================================================
// OBSŁUGA POST – jedna, główna
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_reservation'])) {

    // Jeżeli wymagamy logowania, a user nie jest zalogowany → blokujemy
    if ($login_required && !$isLoggedIn) {
        $errorMsg = '<div class="alert alert-danger">Aby zarezerwować termin, musisz być zalogowany.</div>';
    } else {

        // pozwalamy, żeby POST nadpisał tryb
        if (isset($_POST['mode']) && in_array($_POST['mode'], ['range','visit'], true)) {
            $mode = $_POST['mode'];
        }

        // Dane kontaktowe
        if ($isLoggedIn && $currentUser) {
            // Dla zalogowanego – bierzemy dane z bazy
            $userName  = trim($currentUser['name'] ?? $currentUser['email'] ?? '');
            $userPhone = trim($currentUser['phone'] ?? '');
            $userEmail = trim($currentUser['email'] ?? '');
        } else {
            // Niezalogowany – dane z formularza
            $userName  = trim($_POST['name'] ?? '');
            $userPhone = trim($_POST['phone'] ?? '');
            $userEmail = trim($_POST['email'] ?? '');
        }

        $category  = trim($_POST['category'] ?? '');
        $message   = trim($_POST['message'] ?? '');
        $regulamin = isset($_POST['regulamin']);

        // wspólne pola dat/godziny
        $dateStart = trim($_POST['date_start'] ?? ''); // YYYY-MM-DD
        $dateEnd   = trim($_POST['date_end'] ?? '');   // YYYY-MM-DD lub pusty
        $time      = trim($_POST['time'] ?? '');       // HH:MM lub pusty
        $dayCount  = 0;

        // WALIDACJA OGÓLNA
        if ($userName === '' || $userEmail === '' || !$regulamin) {
            $errorMsg = '<div class="alert alert-danger">Uzupełnij wymagane pola i zaakceptuj regulamin.</div>';
        } elseif (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = '<div class="alert alert-danger">Podaj poprawny adres e-mail.</div>';
        } else {
            // =====================================
            // TRYB: RANGE (np. nocleg)
            // =====================================
            if ($mode === 'range') {
                if ($dateStart === '' || $dateEnd === '') {
                    $errorMsg = '<div class="alert alert-danger">Wybierz zakres dat w kalendarzu.</div>';
                } else {
                    $dt1 = DateTime::createFromFormat('Y-m-d', $dateStart);
                    $dt2 = DateTime::createFromFormat('Y-m-d', $dateEnd);
                    if ($dt1 && $dt2) {
                        $diff = $dt1->diff($dt2)->days;
                        $dayCount = $diff + 1;
                    } else {
                        $errorMsg = '<div class="alert alert-danger">Nieprawidłowy format dat.</div>';
                    }

                    if ($errorMsg === '') {
                        // sprawdzamy konflikt TYLKO z rezerwacjami dniowymi (bez godziny)
                        $confSql = "
                            SELECT sDateStart, sDateEnd
                            FROM form
                            WHERE iStatus = 2
                              AND (sTime IS NULL OR sTime = '')
                              AND sDateStart <= :endDate
                              AND (sDateEnd IS NULL OR sDateEnd = '' OR sDateEnd >= :startDate)
                        ";
                        $confStmt = $oSql->prepare($confSql);
                        $confStmt->execute([
                            ':startDate' => $dateStart,
                            ':endDate'   => $dateEnd,
                        ]);
                        $conflicts = $confStmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($conflicts)) {
                            $errorMsg = '<div class="alert alert-danger">Wybrany zakres dat jest częściowo zajęty. Wybierz inny termin.</div>';
                        }
                    }
                }
            }
            // =====================================
            // TRYB: VISIT (np. fryzjer)
            // =====================================
            elseif ($mode === 'visit') {
                if ($dateStart === '' || $time === '') {
                    $errorMsg = '<div class="alert alert-danger">Wybierz dzień w kalendarzu oraz godzinę wizyty.</div>';
                } else {
                    $dateEnd  = '';
                    $dayCount = 1;

                    // czy ta konkretna godzina jest już zajęta (tylko zatwierdzone!)
                    $checkStmt = $oSql->prepare("
                        SELECT COUNT(*) 
                        FROM form 
                        WHERE iStatus = 2 
                          AND sDateStart = :dateStart 
                          AND sTime = :time
                    ");
                    $checkStmt->execute([
                        ':dateStart' => $dateStart,
                        ':time'      => $time,
                    ]);
                    $isTaken = (int)$checkStmt->fetchColumn();

                    if ($isTaken > 0) {
                        $errorMsg = '<div class="alert alert-danger">Wybrana godzina jest już zajęta. Wybierz inną.</div>';
                    }
                }
            }
        }

        // =====================================
        // JEŚLI BRAK BŁĘDÓW → ZAPIS
        // =====================================
        if ($errorMsg === '') {
            // iUserId — ID zalogowanego użytkownika lub NULL
            $iUserId = ($isLoggedIn && !empty($currentUser['id']))
                ? (int)$currentUser['id']
                : null;

            $stmt = $oSql->prepare("
                INSERT INTO form
                (
                    iStatus,
                    iDate,
                    iUserId,
                    sUserName,
                    sUserPhone,
                    sUserEmail,
                    sDateStart,
                    sDateEnd,
                    sTime,
                    sCategory,
                    sMessage,
                    sDayCount
                )
                VALUES
                (
                    :iStatus,
                    :iDate,
                    :iUserId,
                    :sUserName,
                    :sUserPhone,
                    :sUserEmail,
                    :sDateStart,
                    :sDateEnd,
                    :sTime,
                    :sCategory,
                    :sMessage,
                    :sDayCount
                )
            ");

            $ok = $stmt->execute([
                ':iStatus'   => 0,
                ':iDate'     => time(),
                ':iUserId'   => $iUserId,
                ':sUserName' => $userName,
                ':sUserPhone'=> $userPhone,
                ':sUserEmail'=> $userEmail,
                ':sDateStart'=> $dateStart,
                ':sDateEnd'  => $dateEnd,
                ':sTime'     => $time,
                ':sCategory' => $category,
                ':sMessage'  => $message,
                ':sDayCount' => $dayCount,
            ]);

            if ($ok) {
                if (function_exists('sendEmail')) {

                    // ładny HTML maila
                    $body  = '<table width="100%" cellpadding="0" cellspacing="0" style="font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;padding:20px 0;"><tr><td align="center"><table cellpadding="0" cellspacing="0"  style="background:#ffffff;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden; max-width:800px">';
                    $body .= '<tr><td style="background:#0f172a;color:#ffffff;padding:16px 20px;font-size:18px;font-weight:bold;">Rezerwacja - '.html(getElement($category, $formCategories)).'</td></tr>';
                    $body .= '<tr><td style="padding:20px;"><table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#1f2937;">';
                    $body .= '<tr><td style="padding:6px 0;width:180px;font-weight:bold;">Imię i nazwisko:</td><td style="padding:6px 0;">'.html($userName).'</td></tr>';
                    if ($userPhone !== '') {
                        $body .= '<tr><td style="padding:6px 0;font-weight:bold;">Telefon:</td><td style="padding:6px 0;">'.html($userPhone).'</td></tr>';
                    }
                    $body .= '<tr><td style="padding:6px 0;font-weight:bold;">E-mail:</td><td style="padding:6px 0;">'.html($userEmail).'</td></tr>';
                    if ($dateStart !== '') {
                        $body .= '<tr><td style="padding:6px 0;font-weight:bold;">Data początkowa:</td><td style="padding:6px 0;">'.html($dateStart).'</td></tr>';
                    }
                    if ($mode === 'range' && $dateEnd !== '') {
                        $body .= '<tr><td style="padding:6px 0;font-weight:bold;">Data końcowa:</td><td style="padding:6px 0;">'.html($dateEnd).'</td></tr>';
                    }
                    if ($mode === 'visit' && $time !== '') {
                        $body .= '<tr><td style="padding:6px 0;font-weight:bold;">Godzina:</td><td style="padding:6px 0;">'.html($time).'</td></tr>';
                    }
                    if ($mode === 'range' && $dayCount > 0) {
                        $body .= '<tr><td style="padding:6px 0;font-weight:bold;">Liczba dni:</td><td style="padding:6px 0;">'.(int)$dayCount.'</td></tr>';
                    }
                    if ($message !== '') {
                        $body .= '<tr><td style="padding:10px 0 0;font-weight:bold;vertical-align:top;">Wiadomość:</td><td style="padding:10px 0 0;">'.nl2br(html($message)).'</td></tr>';
                    }
                    $body .= '</table></td></tr>';
                    $body .= '<tr><td style="background:#f3f4f6;padding:14px 20px;font-size:12px;color:#6b7280;text-align:right;">Wysłane ze strony: '.html(BASE_URL).', dnia '.date('Y-m-d H:i:s').'</td></tr>';
                    $body .= '</table></td></tr></table>';

                    // mail do admina
                    sendEmail([
                        'reply_to'        => $userEmail,
                        'subject'         => 'Nowa rezerwacja - '.$config['logo'],
                        'body'            => $body,
                        'alert'           => false,
                        'recaptcha_token' => $_POST['g-recaptcha-response'] ?? '',
                        'recaptcha_action'=> $_POST['action'] ?? 'reservation_form',
                    ]);

                    // mail do klienta
                    sendEmail([
                        'to'       => $userEmail,
                        'to_name'  => $userName,
                        'subject'  => 'Przyjęliśmy Twoją rezerwację',
                        'body'     => '<p>Dziękujemy za zgłoszenie. Wkrótce potwierdzimy termin.</p>'.$body,
                        'alert'    => false,
                        'reply_to' => $config['email'],
                    ]);
                }

                // redirect po sukcesie (żeby uniknąć F5 duplikującego POST)
                $currentPageId = $config['current_page_id'] ?? ($aData['iPage'] ?? null);
                $currentUrl    = $currentPageId ? $oPage->aPages[$currentPageId]['sLinkName'] : '/';
                header('Location: '.$currentUrl.'?sent=1&mode='.$mode);
                exit;
            } else {
                $errorMsg = '<div class="alert alert-danger">Błąd zapisu. Spróbuj ponownie.</div>';
            }
        }
    }
}

// jeśli przeszliśmy po redirectcie
if (isset($_GET['sent']) && $_GET['sent'] == '1') {
    $successMsg = '<div class="alert alert-success">Dziękujemy! Rezerwacja została zapisana i czeka na potwierdzenie.</div>';
}

require_once $theme.'_header.php';

if (isset($aData['sName'])) {
    require_once $theme.'_title.php';

    // wartości domyślne do pól formularza (prefill)
    if ($isLoggedIn && $currentUser) {
        $nameValue  = trim($currentUser['name'] ?? $currentUser['email'] ?? '');
        $phoneValue = trim($currentUser['phone'] ?? '');
        $emailValue = trim($currentUser['email'] ?? '');
    } else {
        $nameValue  = old('name')   !== '' ? old('name')   : '';
        $phoneValue = old('phone')  !== '' ? old('phone')  : '';
        $emailValue = old('email')  !== '' ? old('email')  : '';
    }

    $msgValue = old('message') !== '' ? old('message') : '';
    ?>

    <div class="container pageForm pageForm--<?php echo html($mode); ?>"
         data-mode="<?php echo html($mode); ?>">
        <div class="mainPage__wrapper">
            <div class="mainPage__content">
                <?php
                echo $errorMsg;
                echo $successMsg;

                if (!empty($aData['sDescriptionShort'])) {
                    echo '<div class="mainPage__desc font-md">'.html($aData['sDescriptionShort']).'</div>';
                }

                if (!isset($_GET['sent'])):

                    // Jeżeli wymagamy logowania, a user nie jest zalogowany → komunikat zamiast formularza
                    if ($login_required && !$isLoggedIn):
                        ?>
                        <div class="alert alert-info mb-4">
                            Aby zarezerwować termin, zaloguj się na swoje konto.
                        </div>
                        <a href="<?php echo html($loginPageUrl); ?>" class="button">
                            Przejdź do logowania
                        </a>
                        <?php
                    else:
                ?>
                <form action="<?php echo html($oPage->aPages[$aData['iPage']]['sLinkName']); ?>" method="post" class="form pageContact__form" id="reservation-form">
                    <input type="hidden" name="form_reservation" value="1">
                    <input type="hidden" name="mode" value="<?php echo html($mode); ?>">

                    <?php if (!empty($config['publicKey'])): ?>
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                        <input type="hidden" name="action" value="reservation_form">
                    <?php endif; ?>

                    <div class="row">
                        <!-- LEWA: kalendarz -->
                        <div class="col-5 pageForm__col-calendar">
                            <h5 class="form-title mb-2">
                                <?php echo ($mode === 'visit') ? 'Wybierz termin' : 'Wybierz zakres dat'; ?>
                            </h5>
                            <div id="availability-calendar" data-url="/plugins/form-calendar.php?mode=<?php echo urlencode($mode); ?>">
                                <?php
                                $_GET['mode'] = $mode;
                                require 'plugins/form-calendar.php';
                                ?>
                            </div>
                            <?php if ($mode === 'visit'): ?>
                            <div id="visit-hours-wrapper" class="visit-hours-wrapper mb-4">
                                <p class="text-muted mb-2">Wybierz dzień z kalendarza, żeby zobaczyć wolne godziny.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- PRAWA: formularz -->
                        <div class="col-7 pageForm__col-form">
                            <h5 class="form-title mb-2">Uzupełnij dane</h5>

                            <div class="form-floating form-item">
                                <select class="form-control form-select" id="category" name="category">
                                    <?php foreach ($formCategories as $key => $label): ?>
                                        <option value="<?php echo html($key); ?>">
                                            <?php echo html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="category">Usługa</label>
                            </div>

                            <?php if (!$isLoggedIn || !$currentUser): ?>
                                <!-- Niezalogowany: normalne pola input -->
                                <div class="form-floating form-item">
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="name"
                                        name="name"
                                        value="<?php echo html($nameValue); ?>"
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
                                                value="<?php echo html($phoneValue); ?>"
                                                placeholder="Telefon"
                                            >
                                            <label for="phone">Telefon</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating form-item">
                                            <input
                                                type="email"
                                                class="form-control"
                                                id="email"
                                                name="email"
                                                value="<?php echo html($emailValue); ?>"
                                                placeholder="E-mail"
                                                required
                                            >
                                            <label for="email">E-mail <span class="text-danger">*</span></label>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Zalogowany: ukryte pola z danymi z bazy -->
                                <input type="hidden" name="name"  value="<?php echo html($nameValue); ?>">
                                <input type="hidden" name="phone" value="<?php echo html($phoneValue); ?>">
                                <input type="hidden" name="email" value="<?php echo html($emailValue); ?>">
                            <?php endif; ?>

                            <div class="form-floating form-item">
                                <textarea
                                    name="message"
                                    class="form-control"
                                    id="message"
                                    rows="2"
                                    placeholder="Dodatkowe informacje"
                                ><?php echo html($msgValue); ?></textarea>
                                <label for="message">Dodatkowe informacje</label>
                            </div>
                            
                            <?php if ($mode === 'range'): ?>
                                <!-- RANGE -->
                                <input type="hidden" name="date_start" id="date_start" value="">
                                <input type="hidden" name="date_end" id="date_end" value="">
                                <input type="hidden" name="time" id="time" value="">
                                <input type="hidden" name="day_count" id="day_count" value="">

                                <ul class="pageForm__selectedList mb-3">
                                    <?php if ($isLoggedIn && $currentUser): ?>
                                        <li><span class="label">Imię i nazwisko:</span> <span class="value"><?php echo html($nameValue); ?></span></li>
                                        <li><span class="label">E-mail:</span> <span class="value"><?php echo html($emailValue); ?></span></li>
                                        <?php if ($phoneValue): ?>
                                            <li><span class="label">Telefon:</span> <span class="value"><?php echo html($phoneValue); ?></span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <li><span class="label">Data początkowa:</span> <span id="pageForm__selectedDateStart" class="value">—</span></li>
                                    <li><span class="label">Data końcowa:</span> <span id="pageForm__selectedDateEnd" class="value">—</span></li>
                                    <li><span class="label">Liczba dni:</span> <span id="pageForm__selectedDateCount" class="value">—</span></li>
                                </ul>

                            <?php elseif ($mode === 'visit'): ?>
                                <!-- VISIT -->
                                <input type="hidden" name="date_start" id="date_start" value="">
                                <input type="hidden" name="date_end" id="date_end" value="">
                                <input type="hidden" name="time" id="time" value="">

                                <ul class="pageForm__selectedList mb-3">
                                    <?php if ($isLoggedIn && $currentUser): ?>
                                        <li><span class="label">Imię i nazwisko:</span> <span class="value"><?php echo html($nameValue); ?></span></li>
                                        <li><span class="label">E-mail:</span> <span class="value"><?php echo html($emailValue); ?></span></li>
                                        <?php if ($phoneValue): ?>
                                            <li><span class="label">Telefon:</span> <span class="value"><?php echo html($phoneValue); ?></span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <li><span class="label">Wybrany dzień:</span> <span id="visit-selected-date" class="value">—</span></li>
                                    <li><span class="label">Wybrana godzina:</span> <span id="visit-selected-time" class="value">—</span></li>
                                </ul>
                            <?php endif; ?>

                            <div class="form-check form-item mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="regulamin" name="regulamin" required>
                                <label class="form-check-label" for="regulamin">
                                   <?= acceptLabel(); ?>
                                </label>
                            </div>

                            <button type="submit" class="button button-lg">Rezerwuję termin</button>
                        </div>
                    </div>
                </form>
                <?php
                    endif; // wymaganie logowania
                endif; // !sent

                if (!empty($aData['sDescriptionFull']) && $aData['sDescriptionFull'] !== $aData['sDescriptionShort']) {
                    echo '<div class="mainPage__desc">'.html($aData['sDescriptionFull']).'</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <script src="<?php echo THEME; ?>js/page-form.js?ver=1"></script>
       
    <?php if (!empty($config['publicKey'])): ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?php echo $config['publicKey']; ?>"></script>
        <script>
        grecaptcha.ready(function() {
            grecaptcha.execute('<?php echo $config['publicKey']; ?>', {action: 'reservation_form'}).then(function(token) {
                var el = document.getElementById('g-recaptcha-response');
                if (el) el.value = token;
            });
        });
        </script>
    <?php endif; ?>

    <?php

} else {
    require_once $theme.'_404.php';
}

require_once $theme.'_footer.php';
