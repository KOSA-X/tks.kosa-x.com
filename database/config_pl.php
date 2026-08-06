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
$config['logo'] = "Tomasovia";
$config['title'] = "Tomasovia LIVE";
$config['description'] = "Transmisje na żywo TKS Tomasovia";
$config['slogan'] = "Transmisje na żywo TKS Tomasovia";
$config['foot_info'] = "Copyright ©";

// ============================================================
// ID ZAKŁADEK — patrz @claude-note na górze pliku
// ============================================================
$config['shop_page'] = "0";
$config['user_page'] = "0";
$config['order_page'] = "0";
$config['blog_page'] = "0";
$config['about_page'] = "0";
$config['private_policy'] = "0";
$config['terms_page'] = "0";
$config['video_page'] = "0";
$config['slider_page'] = "0";
$config['contact_page'] = "0";
$config['offer_page'] = "0";
$config['projects_page'] = "0";
$config['faq_page'] = "0";
$config['search_page'] = "0";
$config['form_page'] = "0";
$config['sitemap_page'] = "0";
$config['payment_page'] = "0";

// Moduł transmisji live (TKS): rodzic drużyn — podstrony to drużyny,
// a ich podstrony to zawodnicy (pages.sNumber, pages.sSquad).
// Zakładki 28-34 i cały schemat live SĄ JUŻ w database/database.db —
// świeże wdrożenie repo działa bez żadnych migracji.
$config['teams_page'] = "28";

// Transmisja live — strony meczowe:
// „Mecz" = treść meczu dnia (opis, plakat, data w sDate, sędziowie w opisie
// skróconym); panel operatora i nakładka OBS mają dedykowane szablony
// (themes 11 i 12).
$config['match_page']        = "31";
$config['live_panel_page']   = "32";
$config['live_overlay_page'] = "33";

// Telebim — ekran LED przy boisku (theme 13, cieszynki wideo + powtórki OBS).
$config['telebim_page'] = "34";

// Strony-źródła treści plansz nakładki OBS (0 = plansza bez treści, nie renderuje się):
// sponsorzy = zakładka z logotypami (obrazki strony), realizacja = zakładka
// promująca produkcję transmisji (tytuł + zdjęcia).
$config['live_sponsors_page']   = "0";
$config['live_production_page'] = "0";

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
$config['phone'] = "";
$config['phone2'] = "";
$config['email'] = "konrad@kosiorski.pl";

$config['facebook'] = "https://www.facebook.com/TomasoviaTKS";
$config['instagram'] = "https://www.instagram.com/tomasovia";
// Klucz konta użyty w sekcji Instagram na stronie głównej (renderInstaFeed).
// Pusty = sekcja się nie pokazuje.
$config['instagram_account'] = "main";
$config['youtube'] = "https://www.youtube.com/@TKSTomasovia";
$config['xcom'] = "";
$config['whatsapp'] = "";
$config['linkedin'] = "";
$config['tiktok'] = "";
$config['maps'] = "";

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
$config['code'] = "";
$config['street'] = "";

// ============================================================
// GODZINY OTWARCIA
// ============================================================
$config['hours_1'] = "";
$config['hours_2'] = "";
$config['hours_3'] = "";
$config['hours_4'] = "";
$config['hours_5'] = "";
$config['hours_6'] = "";

// ============================================================
// TŁUMACZENIA
// ============================================================
$lang['clear'] = "Wyczyść";
?>