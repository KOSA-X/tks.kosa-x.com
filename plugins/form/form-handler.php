<?php
// /plugins/form/form-handler.php

 

global $form_settings;

if (!is_array($form_settings ?? null)) {
    $form_settings = [];
}

// Domyślne ustawienia formularzy (email, security, messages, field_defaults)
$form_defaults = require __DIR__ . '/form-settings.php';

// Tu trzymamy statusy formularzy w trakcie requestu
$form_statuses = [];

// =============================
// HELPERY DO UŻYCIA W SZABLONIE
// =============================

/**
 * Zwraca status formularza (wynik wysyłki, błędy, stare dane)
 */
function form_get_status(string $formId): ?array
{
    global $form_statuses;
    return $form_statuses[$formId] ?? null;
}

/**
 * Zwraca starą wartość pola (po błędnym submitcie)
 */
function form_old(string $formId, string $fieldName, $default = '')
{
    $status = form_get_status($formId);
    if (!$status || !array_key_exists($fieldName, $status['old'] ?? [])) {
        return $default;
    }
    return $status['old'][$fieldName];
}

/**
 * Czy formularz został poprawnie wysłany?
 */
function form_is_success(string $formId): bool
{
    $status = form_get_status($formId);
    return !empty($status['ok']);
}

/**
 * Zwraca komunikat błędu dla pola (jeśli jest)
 */
function form_error(string $formId, string $fieldName): ?string
{
    $status = form_get_status($formId);
    if (!$status || empty($status['errors'][$fieldName])) {
        return null;
    }
    return $status['errors'][$fieldName];
}

/**
 * Zwraca klasę CSS jeśli pole ma błąd, np. " is-invalid"
 */
function form_error_class(string $formId, string $fieldName, string $class = ' is-invalid'): string
{
    return form_error($formId, $fieldName) ? $class : '';
}

/**
 * Wyświetla (echo) gotowy alert z komunikatem dla formularza
 */
function form_alert(string $formId, string $successClass = 'success', string $errorClass = 'danger'): void
{
    $status = form_get_status($formId);
    if (!$status || empty($status['message'])) {
        return;
    }

    $class = $status['ok'] ? $successClass : $errorClass;

    echo '<div class="alert alert-' . html($class) . '">' . html($status['message']) . '</div>';
}

/**
 * Pomoc: merge z domyślnymi (form_defaults + form_settings[formId])
 */
function form_merge_config(array $defaults, array $settings): array
{
    return array_replace_recursive($defaults, $settings);
}

/**
 * Główna obsługa formularza – wywoływana raz na końcu pliku
 */
function form_handle_request(array $form_settings, array $form_defaults): void
{
    global $form_statuses, $config;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $formId = $_POST['form_id'] ?? '';
    if ($formId === '' || !isset($form_settings[$formId])) {
        return;
    }

    // Łączymy globalne domyślne ustawienia z tym, co zdefiniowano w szablonie
    $cfg = form_merge_config($form_defaults, $form_settings[$formId]);

    $emailCfg    = $cfg['email']    ?? [];
    $securityCfg = $cfg['security'] ?? [];
    $msgCfg      = $cfg['messages'] ?? [];
    $fieldsCfg   = $cfg['fields']   ?? [];
    $fieldDef    = $cfg['field_defaults'] ?? $form_defaults['field_defaults'];

    if (empty($fieldsCfg)) {
        $form_statuses[$formId] = [
            'ok'      => false,
            'message' => 'Formularz nie ma zdefiniowanych pól.',
            'errors'  => [],
            'old'     => [],
        ];
        return;
    }

    $errors  = [];
    $clean   = [];
    $uploads = [];

    // =====================
    // Walidacja pól (POST)
    // =====================
    foreach ($fieldsCfg as $name => $opts) {
        $opts     = array_replace($fieldDef, $opts);
        $required = (bool)($opts['required'] ?? false);
        $type     = $opts['type'] ?? 'text';

        if ($type === 'file') {
            // Plik obslugujemy osobno, ale już tu zachowujemy info
            $file = $_FILES[$name] ?? null;
            if ($required && (empty($file) || $file['error'] !== UPLOAD_ERR_OK)) {
                $errors[$name] = 'To pole jest wymagane.';
            }
            $clean[$name] = $file;
            continue;
        }

        if ($type === 'checkbox') {
            $value = isset($_POST[$name]) ? '1' : '';
        } else {
            $value = trim($_POST[$name] ?? '');
        }

        if ($required && $value === '') {
            $errors[$name] = 'To pole jest wymagane.';
        }

        if ($value !== '') {
            if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Podaj poprawny adres e-mail.';
            }
        }

        $clean[$name] = $value;
    }

    // =====================
    // Upload plików
    // =====================
    $allowedExt = $securityCfg['upload_allowed_ext'] ?? $form_defaults['security']['upload_allowed_ext'];
    $maxSize    = $securityCfg['upload_max_size']    ?? $form_defaults['security']['upload_max_size'];

    foreach ($fieldsCfg as $name => $opts) {
        $opts = array_replace($fieldDef, $opts);
        $type = $opts['type'] ?? 'text';
        if ($type !== 'file') {
            continue;
        }

        $file = $clean[$name] ?? null;
        if (empty($file) || empty($file['name'])) {
            continue; // brak pliku – nie traktujemy jako błąd, jeśli nie jest wymagany
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[$name] = 'Błąd podczas przesyłania pliku.';
            continue;
        }

        $origName = $file['name'];
        $size     = $file['size'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            $errors[$name] = 'Ten typ pliku nie jest dozwolony.';
            continue;
        }

        if ($size > $maxSize) {
            $errors[$name] = 'Plik jest zbyt duży.';
            continue;
        }

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('form_', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
            $errors[$name] = 'Nie udało się zapisać załącznika.';
            continue;
        }

        $uploads[$name] = [
            'tmp'  => $tmpPath,
            'name' => $origName,
        ];
    }

    // Jeśli są błędy – zwracamy status i kończymy
    if (!empty($errors)) {
        $form_statuses[$formId] = [
            'ok'      => false,
            'message' => $msgCfg['error_validation'] ?? $form_defaults['messages']['error_validation'],
            'errors'  => $errors,
            'old'     => $clean,
        ];
        return;
    }

    // =====================
    // Budowa treści wiadomości
    // =====================
    $lines = [];

    foreach ($fieldsCfg as $name => $opts) {
        $opts = array_replace($fieldDef, $opts);

        // Możesz ukryć konkretne pole w mailu przez include_in_mail => false
        if (!($opts['include_in_mail'] ?? true)) {
            continue;
        }

        $label = $opts['label'] ?? $name;
        $type  = $opts['type'] ?? 'text';
        $val   = $clean[$name] ?? '';

        // POMIŃ PUSTE POLA tekstowe
        if ($type !== 'checkbox' && $type !== 'file') {
            if ($val === '' || $val === null) {
                continue;
            }
        }

        // CHECKBOX — pokazujemy TYLKO jeśli zaznaczony
        if ($type === 'checkbox') {
            if (!$val) {
                continue; // niezaznaczone checkboxy nie pojawiają się w mailu
            }
            $val = 'TAK';
        }

        // PLIK — pokazujemy TYLKO jeśli realnie został wrzucony
        if ($type === 'file') {
            if (empty($uploads[$name]['name'])) {
                continue; // brak pliku – nie pokazujemy pola
            }
            $val = $uploads[$name]['name'];
        }

        $lines[] = '<p><strong>' . html($label) . ':</strong> ' . nl2br(html($val)) . '</p>';
    }

    $body  = implode("\n", $lines);
    $body .= '<hr><small>Wiadomość z formularza '
        . (defined('BASE_URL') ? html(BASE_URL) : '')
        . '<br>Wysłane: ' . date('Y-m-d H:i:s') . '</small>';

    // =====================
    // Konfiguracja e-maila
    // =====================
    $sendTo      = $emailCfg['send_to']        ?? $form_defaults['email']['send_to'];
    $subject     = $emailCfg['subject']        ?? $form_defaults['email']['subject'];
    $copyToUser  = $emailCfg['copy_to_user']   ?? $form_defaults['email']['copy_to_user'];
    $replyField  = $emailCfg['reply_to_field'] ?? $form_defaults['email']['reply_to_field'];
    $fromEmail   = $emailCfg['from_email']     ?? $form_defaults['email']['from_email'];
    $fromName    = $emailCfg['from_name']      ?? $form_defaults['email']['from_name'];
    $attachmentsFields = $emailCfg['attachments'] ?? $form_defaults['email']['attachments'];

    $replyEmail = $replyField && !empty($clean[$replyField]) ? $clean[$replyField] : null;
    $replyName  = !empty($clean['name']) ? $clean['name'] : ($replyEmail ?: null);

    // Przygotowanie załączników dla sendEmail()
    // Uwaga: sendEmail() obsługuje "attachments" jako listę [path, name]
    $attachments = [];
    foreach ($attachmentsFields as $fieldName) {
        if (!empty($uploads[$fieldName])) {
            $attachments[] = [
                $uploads[$fieldName]['tmp'],
                $uploads[$fieldName]['name'],
            ];
        }
    }

    // reCAPTCHA dane – sendEmail() to obsłuży
    $recaptchaToken  = $_POST['g-recaptcha-response'] ?? '';
    $recaptchaAction = $_POST['action'] ?? ($securityCfg['recaptcha_action'] ?? $form_defaults['security']['recaptcha_action']);
    $recaptchaScore  = $securityCfg['recaptcha_score'] ?? $form_defaults['security']['recaptcha_score'];

    // =====================
    // Wysyłka maila do Ciebie
    // =====================
    $result = sendEmail([
        'to'               => $sendTo,
        'subject'          => $subject,
        'body'             => $body,
        'from'             => $fromEmail,
        'from_name'        => $fromName,
        'reply_to'         => $replyEmail,
        'reply_to_name'    => $replyName,
        'attachments'      => $attachments,
        'alert'            => $msgCfg['success'] ?? $form_defaults['messages']['success'],
        'recaptcha_token'  => ($securityCfg['use_recaptcha'] ?? true) ? $recaptchaToken : null,
        'recaptcha_action' => $recaptchaAction,
        'recaptcha_score'  => $recaptchaScore,
    ]);

    // =====================
    // Autoresponder do użytkownika
    // =====================
    if ($result['success'] && $copyToUser && $replyEmail && filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {

        // Ten sam komunikat, który wyświetlasz jako "success"
        $successText = $msgCfg['success'] ?? $form_defaults['messages']['success'];

        $autoBody  = '<p>' . html($successText) . '</p>';
        $autoBody .= '<hr><small>'
            . html($config['logo'] ?? 'Strona')
            . '<br>Wiadomość wygenerowana automatycznie – prosimy na nią nie odpowiadać.'
            . '</small>';

        sendEmail([
            'to'        => $replyEmail,
            'to_name'   => $replyName,
            'subject'   => 'Potwierdzenie otrzymania wiadomości – ' . ($config['logo'] ?? 'Strona'),
            'body'      => $autoBody,
            'from'      => $fromEmail,
            'from_name' => $fromName,
            'alert'     => false, // tu nie potrzebujemy komunikatu zwrotnego
        ]);
    }

    // =====================
    // Status dla szablonu
    // =====================
    $form_statuses[$formId] = [
        'ok'      => (bool)$result['success'],
        'message' => $result['message'],
        'errors'  => $errors,
        'old'     => $clean,
    ];
}

// Odpalamy obsługę (raz na request)
form_handle_request($form_settings, $form_defaults);
