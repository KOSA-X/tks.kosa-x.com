<?php
// /plugins/form/form-settings.php

global $config;


return [
    'email' => [
        'send_to'        => [ $config['email'] ?? ('no-reply@'.($_SERVER['HTTP_HOST'] ?? 'localhost')) ],
        'subject'        => 'Wiadomość z formularza '.$config['logo'],
        'cc'             => [],
        'bcc'            => [],
        'copy_to_user'   => false,
        'reply_to_field' => 'email',
        'from_email'     => $config['email'] ?? ('no-reply@'.($_SERVER['HTTP_HOST'] ?? 'localhost')),
        'from_name'      => $config['logo']  ?? 'Strona',
        'attachments'    => [], // nazwy pól typu "file"
    ],

    'security' => [
        'use_recaptcha'      => true,
        'recaptcha_action'   => 'form',
        'recaptcha_score'    => 0.3,
        'upload_allowed_ext' => ['pdf','jpg','jpeg','png','doc','docx','xls','xlsx'],
        'upload_max_size'    => 5 * 1024 * 1024, // 5 MB
    ],

    'messages' => [
        'success'          => 'Dziękujemy! Twoja wiadomość została wysłana.',
        'error_general'    => 'Wystąpił błąd podczas wysyłania wiadomości.',
        'error_validation' => 'Uzupełnij wymagane pola i spróbuj ponownie.',
        'error_recaptcha'  => 'Weryfikacja antyspamowa nie powiodła się.',
    ],

    'field_defaults' => [
        'required'        => false,
        'type'            => 'text',   // text, email, textarea, checkbox, file, tel, number...
        'include_in_mail' => true,
    ],
];
