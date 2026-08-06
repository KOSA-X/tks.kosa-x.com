<?php
// ============================================================
// SEKRETY POZA REPOZYTORIUM — WZORZEC
// ============================================================
// 1. Skopiuj ten plik jako:  database/config.secrets.php
// 2. Wklej PRAWDZIWE klucze (najlepiej ŚWIEŻO ZROTOWANE — poprzednie były w gicie).
// 3. Usuń/zablankuj te same wartości w database/config.php i config_pl.php.
//
// Plik config.secrets.php jest ignorowany przez git (.gitignore) i blokowany
// przez .htaccess. Ładuje się jako ostatni w config.php i NADPISUJE wartości
// z config.php / config_pl.php.
//
// Uwaga: przy deployu przez `git pull` plik zostaje na serwerze (untracked).
// Przy deployu kopiowaniem plików — wgraj go ręcznie raz na serwer.

if( !defined( 'CUSTOMER_PAGE' ) && !defined( 'ADMIN_PAGE' ) ) { exit; }

// --- Panel administracyjny ---
// $config['login_pass'] = '';   // ustaw mocne hasło (albo zostaw hash w bazie po pierwszym logowaniu)

// --- Google (logowanie) ---
// $config['google_client_id']     = '';
// $config['google_client_secret'] = '';

// --- reCAPTCHA v3 ---
// $config['publicKey'] = '';
// $config['secretKey'] = '';

// --- PayU ---
// $config['payu_pos_id']        = '';
// $config['payu_client_id']     = '';
// $config['payu_client_secret'] = '';
// $config['payu_second_key']    = '';

// --- InPost ---
// $config['inpost_organization_id'] = '';
// $config['inpost_geowidget_token'] = '';
// $config['inpost_shipx_token']     = '';

// --- Linki zamówień (HMAC) ---
// $config['orderKey'] = '';

// --- SMTP ---
// $config['smtp_pass'] = '';

// --- Instagram InstaFeed ---
// $config['instagram_cron_secret'] = '';
// $config['instagram_accounts']['main']['access_token'] = '';

// --- Transmisja live (moduł meczowy TKS) ---
 $config['anthropic_api_key']  = '';   // vision OCR protokołu meczowego
// $config['elevenlabs_api_key'] = '';   // komunikaty głosowe (TTS)
