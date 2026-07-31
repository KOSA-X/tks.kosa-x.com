<?php
/*
* Dane konfiguracyjne strony zależne od języka
* Więcej: https://opensolution.org/docs/?p=pl-settings
*/

// @claude-note: zmienne $config['*_page'] poniżej to ID zakładek CMS.
// Zasady użycia (linki/treści zawsze z bazy, nigdy hardkodowane) — patrz CLAUDE.md.

setlocale( LC_CTYPE, 'pl_PL' );

// ============================================================
// PODSTAWOWE DANE STRONY
// ============================================================
$config['start_page'] = "1";
$config['logo'] = "KOS X";
$config['title'] = "KOSA X | Digital Content Creator";
$config['description'] = "Opis w Google";
$config['slogan'] = "Digital Content Creator";
$config['foot_info'] = "Copyright ©";

// ============================================================
// ID ZAKŁADEK — patrz @claude-note na górze pliku
// ============================================================
$config['shop_page'] = "10";
$config['user_page'] = "9";
$config['order_page'] = "12";
$config['blog_page'] = "21";
$config['about_page'] = "17";
$config['private_policy'] = "6";
$config['terms_page'] = "7";
$config['video_page'] = "0";
$config['slider_page'] = "1";
$config['contact_page'] = "2";
$config['offer_page'] = "18";
$config['projects_page'] = "19";
$config['faq_page'] = "16";
$config['search_page'] = "8";
$config['form_page'] = "20";
$config['sitemap_page'] = "3";
$config['payment_page'] = "14";

// Moduł transmisji live (TKS): rodzic drużyn — podstrony to drużyny,
// a ich podstrony to zawodnicy (pages.sNumber, pages.sSquad).
// Tworzone przez: php database/migrations/2026-07-31-live-schema.php
$config['teams_page'] = "28";

// Strony TREŚCI używane w szablonach (dawniej magiczne ID w kodzie).
// Zmień ID per projekt zamiast edytować szablony.
$config['transfer_page']      = "13";   // "Dane do wpłaty" (przelew) — page-order.php
$config['payu_page']          = "15";   // opis PayU — page-order.php
$config['footer_popups_page'] = "32";   // strona-rodzic popupów w stopce — _footer.php
$config['payment_info_page']  = "35";   // opis na stronie płatności — page-payment.php

// ============================================================
// INTEGRACJE ZEWNĘTRZNE / KLUCZE
// ============================================================
// @claude-note: reCAPTCHA
$config['publicKey'] = "6LfmVvwrAAAAADvUjD_Hd9-lZ9hs1GDqE67SViYz";
$config['secretKey'] = "6LfmVvwrAAAAADDm0nUmbA5kJMihZFuoe1QYkRBY";

$config['analytics'] = "";
$config['tagmenager'] = "";
$config['tinymce'] = "";
$config['google-site-verification'] = "";

// ============================================================
// DANE KONTAKTOWE FIRMY
// ============================================================
// @claude-note: dane per-projekt — kontakt konkretnej firmy, dla której robiona jest strona
$config['phone'] = "+48 785 942 911";
$config['phone2'] = "";
$config['email'] = "konrad@kosiorski.pl";

$config['facebook'] = "https://www.facebook.com/#";
$config['instagram'] = "https://www.instagram.com/kosa.x";
// Klucz konta użyty w sekcji Instagram na stronie głównej (renderInstaFeed).
// Pusty = sekcja się nie pokazuje.
$config['instagram_account'] = "main";
$config['youtube'] = "";
$config['xcom'] = "";
$config['whatsapp'] = "";
$config['linkedin'] = "";
$config['tiktok'] = "https://www.tiktok.com/@kosa.x";
$config['maps'] = "https://maps.app.goo.gl/CLR69hKMUwJCV8kf6";

// ============================================================
// INSTAGRAM — WIDGET / INSTAFEED (plugins/instagram/)
// ============================================================
// Całe sterowanie feedem Instagram jest TUTAJ. Nowe konto = nowy wpis w
// $config['instagram_accounts']. Klucz (np. 'main') to identyfikator używany
// w renderInstaFeed('main') i w $config['instagram_account'] powyżej.
//
// ⚠️ SEKRETY (access_token, cron_secret): zalecane trzymać w
// database/config.secrets.php (poza gitem — patrz config.secrets.dist.php),
// które nadpisuje wartości poniżej. Tu wtedy zostawiasz placeholdery.
$config['instagram_cron_secret'] = 'CRON_SECRET_HERE';   // cron.php?key=...  (losowy ciąg)
$config['instagram_accounts'] = array(
    'main' => array(
        'ig_user_id'           => 'IG_USER_ID_HERE',      // numeryczny IG user id (Business/Creator)
        'access_token'         => 'LONG_LIVED_TOKEN_HERE', // long-lived token (60 dni)
        'token_updated_at'     => '2026-01-01',           // data ostatniego odświeżenia (Y-m-d)
        'limit'                => 15,                      // ile postów trzymamy (reszta czyszczona z dysku)
        'handle'               => '@nazwa_konta',          // uchwyt do nagłówka widgetu
        'cover_from_permalink' => true,                    // reels: okładka z permalinku (og:image) zamiast klatki wideo
    ),
);

$config['city'] = "Tomaszów Lubelski";
$config['code'] = "22-600";
$config['street'] = "Lwowska";

// ============================================================
// GODZINY OTWARCIA
// ============================================================
$config['hours_1'] = "07:00";
$config['hours_2'] = "17:00";
$config['hours_3'] = "09:00";
$config['hours_4'] = "14:00";
$config['hours_5'] = "";
$config['hours_6'] = "";

// ============================================================
// TŁUMACZENIA
// ============================================================
$lang['clear'] = "Wyczyść";
?>