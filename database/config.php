<?php
/*
* Główne dane konfiguracyjne strony, niezależne od języka
* Więcej: https://opensolution.org/docs/?p=pl-settings
*/
unset( $config, $lang, $aData );

// @claude-lock
// ============================================================
// TRYB DEWELOPERSKI
// ============================================================
/*
* Jeśli strona jest w trakcie tworzenia, warto pozostawić włączoną opcję DEVELOPER_MODE
* Więcej: https://opensolution.org/docs/?p=pl-settings#DEVELOPER_MODE
*/
define( 'DEVELOPER_MODE', true ); // po uruchomieniu strony zakomentuj tę linię
if( defined( 'DEVELOPER_MODE' ) ){
  // E_STRICT jest już zawarte w E_ALL (PHP 5.4+), a sama stała E_STRICT jest
  // deprecated w PHP 8.4 (emitowała ostrzeżenie przy każdym żądaniu).
  error_reporting( E_ALL );
  @ini_set( 'display_errors', '1' ); // w trybie dev pokazuj błędy (produkcja: index.php/adm.php trzymają je wyłączone)
}

// ============================================================
// LOGOWANIE DO PANELU ADMINISTRACYJNEGO
// ============================================================
/*
* Login w postaci emaila i hasło do zalogowania się do panelu administracyjnego
* Dbaj o ich bezpieczeństwo. Nie ustawiaj hasła na "admin", "1234", "qwerty" itp.
* Więcej: https://opensolution.org/docs/?p=pl-settings#login_email
*/
$config['login_email'] = "k@kosiorski.pl";
$config['login_pass']  = "haslo";

// ============================================================
// ZDJĘCIA I MENU — USTAWIENIA BAZOWE
// ============================================================
/*
* Rozmiary miniaturek i lokalizacje zdjęć. Dodając nową lokalizację, nadaj jej cyfrę nie mniejszą niż 50
* Więcej: https://opensolution.org/docs/?p=pl-settings#images_thumbnails
*/
$config['images_thumbnails'] = Array( 500, 920 ); // domyślna wartość: Array( 100, 200, 300 )
$config['images_locations']  = Array( 1 => 'Galeria', 0 => 'Ukryte' ); // domyślna wartość: 1 => 'Lewa strona', 2 => 'Prawa strona', 3 => 'Galeria', 0 => 'Brak'
$config['pages_menus']       = Array( 1 => 'Nawigacja', 2 => 'Sklep - kategorie', 3 => 'Plansze', 4 => 'Drużyny', 0 => 'Ukryte' ); // domyślna wartość: Array( 1 => 'Menu górne', 0 => 'Ukryte' )
// @claude-unlock


// ============================================================
// PHP MAILER (SMTP)
// ============================================================
// @claude-note: dane per-projekt, zmienne z każdym nowym klientem
$config['smtp_host'] = "mail.kosa-x.com";
$config['smtp_user'] = "k@kosa-x.com";
$config['smtp_pass'] = "***";
$config['smtp_port'] = "587";
$config['smtp_secure'] = "tls";


// ============================================================
// MOTYWY / DEDYKOWANE SZABLONY ZAKŁADEK
// ============================================================
/*
* Ustawienia motywów do wyboru w czasie edycji strony
* Więcej: https://opensolution.org/docs/?p=pl-settings#themes
*/
// @claude-note: poniżej lista plików będących dedykowanymi szablonami z customowym wygladem
// i przeznaczeniem — np. page-contact.php to szablon zakładki "KONTAKT" z formularzem
// kontaktowym i mapą Google. Żeby dodać nowy dedykowany wygląd zakładki: (1) dopisz plik
// do tej listy, (2) utwórz plik szablonu w /templates/theme/page-*.php
$config['themes'] = Array(
  1  => 'page.php',
  2  => 'page-index.php',
  3  => 'page-contact.php',
  4  => 'page-user.php',
  5  => 'page-order.php',
  6  => 'page-shop.php',
  7  => 'page-search.php',
  8  => 'page-examples.php',
  9  => 'page-form.php',
  10 => 'page-payment.php',
  11 => 'page-live-panel.php',
  12 => 'page-live-overlay.php',
  13 => 'page-telebim.php',
);


// ============================================================
// UŻYTKOWNICY (MODUŁ REJESTRACJI)
// ============================================================
// @claude-note: statusy zarejestrowanych użytkowników, dotyczy tabeli "users" w bazie danych
$config['user_status'] = Array(
  0 => 'Nowy',
  1 => 'Aktywny',
  2 => 'Zablokowany',
);


// ============================================================
// TYPY STRON
// ============================================================
$config['pages_types'] = Array(
  1 => 'Standardowa zakładka',
  2 => 'Produkt',
);


// ============================================================
// FORMULARZ REZERWACJI
// ============================================================
// @claude-note: kategorie formularza rezerwacji (templates/theme/page-form.php) —
// określa rodzaje usług, jakie klient może zarezerwować / o jakie zapytać
$config['form_categories'] = Array(
  ''  => '- wybierz -',
  1   => 'Nocleg',
  2   => 'Usługa fryzjerska',
);

// @claude-note: dostępne godziny do wyboru w formularzu rezerwacji
$config['calendar_hours'] = [ '07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00' ];
$config['calendar_hours_saturday'] = [ '07:00','08:00','09:00','10:00','11:00','12:00','13:00' ];


// ============================================================
// SKLEP ONLINE — WALUTA, STATUSY, DOSTAWA, PŁATNOŚCI
// ============================================================
$config['currency'] = 'PLN';

// @claude-note: statusy zamówień w sklepie online
$config['order_status'] = Array(
  0 => 'Nowe',
  1 => 'Zrealizowane',
  2 => 'Anulowane',
  3 => 'Opłacone',
);

// @claude-note: metody dostawy wraz z cenami w sklepie online
$config['order_delivery'] = [
  1 => [ 'label' => 'Kurier DPD',       'price' => 223 ],
  2 => [ 'label' => 'Paczkomat InPost', 'price' => 15,  'disabled' => false ],
  3 => [ 'label' => 'Odbiór osobisty',  'price' => 0,   'disabled' => false ],
];

// @claude-note: metody płatności w sklepie online
$config['order_payment'] = [
  1 => [ 'label' => 'PayU' ],
  2 => [ 'label' => 'Przelew na konto', 'disabled' => false ],
];

// @claude-extend: kody rabatowe sklepu — klucz = kod (wielkość liter bez
// znaczenia przy wpisywaniu), percent = rabat % na wartość produktów,
// expires = ostatni dzień ważności ('RRRR-MM-DD' lub 'DD.MM.RRRR';
// pusty = bezterminowo). Zarządzanie z panelu: Sklep → Ustawienia
// (zapis do database/config.shop.php, który nadpisuje tę tablicę).
$config['order_discounts'] = [
  // 'WAKACJE10' => [ 'percent' => 10, 'expires' => '2026-08-31' ],
];

// @claude-note: dodatkowy plik z filtrami produktów w sklepie online.
// plugins/shop_filters.php jest GENEROWANY przez import produktów (stabilne ID
// wartości powiązane z sFilter w bazie) i NIE jest wersjonowany w repo —
// deploy nie może go nadpisywać, bo ID przestaną pasować do produktów.
// Świeża instalacja startuje z kopii dystrybucyjnej shop_filters.dist.php.
if( is_file( "plugins/shop_filters.php" ) ){
  include ("plugins/shop_filters.php");
}
else{
  include ("plugins/shop_filters.dist.php");
}

// Cechy pokazywane jako znaczniki na kafelku produktu (lista produktów).
// Kolejność ma znaczenie. Dostępne klucze: brand, size, width, pcd, et, color, technology
$config['product_card_filters'] = Array( 'brand', 'size' );


// ============================================================
// LOGOWANIE PRZEZ GOOGLE (Google Identity Services)
// ============================================================
/*
* Klucze z Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client ID
* (typ: Web application). W "Authorized JavaScript origins" dodaj domenę strony.
* Po wklejeniu Client ID na stronie logowania pojawi się przycisk Google
* oraz okienko One Tap. Puste pole = logowanie Google wyłączone.
* Client Secret nie jest wymagany dla One Tap/przycisku (weryfikacja tokenem ID),
* pole zostaje na przyszłość (np. pełny OAuth code flow).
*/
$config['google_client_id'] = "";
$config['google_client_secret'] = "";

/*
* LOGOWANIE DO PANELU ADMINA PRZEZ GOOGLE
* Lista adresów e-mail Google uprawnionych do logowania się do panelu
* administracyjnego BEZ hasła (przycisk "Zaloguj przez Google" na stronie
* logowania). Bezpieczeństwo: dostęp dostaje wyłącznie zweryfikowane przez
* Google konto, którego e-mail znajduje się na tej liście.
* Logowanie hasłem nadal działa jako metoda zapasowa.
* Puste = logowanie Google do panelu wyłączone (zostaje tylko hasło).
*/
$config['admin_google_emails'] = Array(
    "konradkosiorski@gmail.com",
    // "moderator@gmail.com",   // dopisz kolejnych administratorów/moderatorów
);


// ============================================================
// MODUŁY / FUNKCJE
// ============================================================
// Moduły włącza się WYŁĄCZNIE w panelu: Konfiguracja → „Dopasowanie stron".
// Przypisana strona = moduł włączony; wyczyszczona („-") = wyłączony.
// W kodzie sprawdzaj przez feature('shop'|'blog'|'portfolio'|'reservations'|
// 'users'|'search'|'payments') (plugins/settings.php) — czyta klucz $config['*_page'].
// Nie ma osobnej tablicy flag, żeby nie dublować przełącznika z panelu.


// ============================================================
// INTEGRACJA — INPOST
// ============================================================
// @claude-note: tokeny do widgetu paczkomatów (geowidget) i API ShipX
$config['inpost_organization_id'] = "51946";
$config['inpost_geowidget_token'] = "";
$config['inpost_shipx_token'] = "";


// ============================================================
// INTEGRACJA — PAYU
// ============================================================
// @claude-note: dane środowiska i klucze do płatności PayU
$config['payu_env'] = 'sandbox';
$config['payu_pos_id'] = '';
$config['payu_client_id'] = '';
$config['payu_client_secret'] = '';
$config['payu_second_key'] = ''; // dla autoryzacji notify
$config['payu_currency'] = 'PLN';
$config['payment_page'] = "0"; // strona powrotu w Quick.CMS
$config['payu_debug'] = false;

// @claude-note: token do generowania linków do strony ze szczegółami zamówienia
$config['orderKey'] = 'rX8dP2bQeF9mL4zK7yW1aT6uH3sN0vC5';


// ============================================================
// TRANSMISJA LIVE — MODUŁ MECZOWY (TKS)
// ============================================================
// @claude-note: słownik składu meczowego zawodnika — klucz zapisywany
// w pages.sSquad ('' = poza kadrą meczową). Używaj przez getElement().
// Etykiety spójne z frontem (lang: live_first_squad / live_reserve).
$config['squad_types'] = Array(
  1 => 'Wyjściowa 11',
  2 => 'Rezerwa',
);

// Drużyna = zakładka NAJWYŻSZEGO poziomu z typem menu „Drużyny"
// (klucz z $config['pages_menus'] powyżej). Zawodnicy = jej podstrony
// (savePage dziedziczy iMenu po rodzicu, dlatego filtry drużyn zawsze
// łączą iMenu z iPageParent = 0). Używane w: panelu meczowym,
// plugins/live/api.php (teams_set) i imporcie składu.
$config['teams_menu'] = 4;

// Klucze API modułu live — tu tylko PLACEHOLDERY. Prawdziwe klucze wstawiaj
// w database/config.secrets.php (poza repo — patrz config.secrets.dist.php),
// który ładuje się na końcu tego pliku i nadpisuje te wartości.
$config['anthropic_api_key']  = '';   // Etap 1: import składu z protokołu (vision OCR)
$config['elevenlabs_api_key'] = '';   // Etap 2: komunikaty głosowe (TTS)

// Model vision do OCR protokołu (plugins/live/ocr.php)
$config['anthropic_ocr_model'] = 'claude-opus-5';

// @claude-note: słownik zdarzeń meczowych (live_events.sAction) — klucz w bazie,
// etykieta w panelu operatora i na nakładce OBS. Obsługa: plugins/live/api.php.
$config['live_actions'] = Array(
  'goal'        => '⚽ GOL',
  'yellow_card' => '🟨 Żółta kartka',
  'red_card'    => '🟥 Czerwona kartka',
  'in'          => '<img src="images/icons/in.png">Wchodzi',
  'out'         => '<img src="images/icons/out.png">Schodzi',
  'own_goal'    => 'Samobój',
);

// Krótkie etykiety zdarzeń — badge przy nazwisku w panelu operatora
$config['live_action_badges'] = Array(
  'goal'        => 'GOL',
  'own_goal'    => 'SAM',
  'yellow_card' => 'ŻÓŁTA',
  'red_card'    => 'CZERW',
  'in'          => '▲',
  'out'         => '▼',
);

// Powtórki z OBS na telebimie: adres lokalnego mini-serwera replay buffera
// (plugins/live/replay/replay-server.py na komputerze z OBS; telebim = drugi
// ekran tego samego komputera, więc localhost działa mimo zdalnego hostingu).
$config['live_replay_url'] = 'http://localhost:8766/replay.mp4';

// @claude-note: ustawienia taktyczne do planszy „Skład 3D" (nakładka OBS).
// Klucz = nazwa formacji zapisywana w pages.sFormation drużyny; wartość =
// 11 slotów [x%, y%] na POZIOMYM boisku (własna bramka z lewej, x rośnie
// w stronę bramki przeciwnika, y = 0 górna linia boczna). Slot 1 = bramkarz,
// dalej linie od obrony do ataku (w linii od góry boiska w dół). Numer slotu
// zapisywany w pages.sLineup zawodnika ('' = nieprzypisany). Etykiety linii
// (OBR/POM/ATAK) panel admina wyprowadza z członów nazwy formacji.
// @claude-extend: nowa formacja = kolejny wpis w tej mapie, zero zmian w kodzie.
// W liniach 3-, 4- i 5-osobowych SKRAJNI zawodnicy są przesunięci kilka %
// do przodu (wyższy x) — łamie to sztywną linię, jak na grafikach meczowych.
$config['live_formations'] = Array(
  '4-4-2' => Array(
    1 => Array(6, 50),
    2 => Array(26, 15), 3 => Array(22, 38), 4 => Array(22, 62), 5 => Array(26, 85),
    6 => Array(52, 15), 7 => Array(48, 38), 8 => Array(48, 62), 9 => Array(52, 85),
    10 => Array(72, 38), 11 => Array(72, 62),
  ),
  '4-3-3' => Array(
    1 => Array(6, 50),
    2 => Array(26, 15), 3 => Array(22, 38), 4 => Array(22, 62), 5 => Array(26, 85),
    6 => Array(50, 28), 7 => Array(46, 50), 8 => Array(50, 72),
    9 => Array(76, 18), 10 => Array(72, 50), 11 => Array(76, 82),
  ),
  '4-2-3-1' => Array(
    1 => Array(6, 50),
    2 => Array(24, 15), 3 => Array(20, 38), 4 => Array(20, 62), 5 => Array(24, 85),
    6 => Array(38, 38), 7 => Array(38, 62),
    8 => Array(60, 20), 9 => Array(56, 50), 10 => Array(60, 80),
    11 => Array(74, 50),
  ),
  '4-2-2-2' => Array(
    1 => Array(6, 50),
    2 => Array(24, 15), 3 => Array(20, 38), 4 => Array(20, 62), 5 => Array(24, 85),
    6 => Array(40, 38), 7 => Array(40, 62),
    8 => Array(58, 25), 9 => Array(58, 75),
    10 => Array(74, 38), 11 => Array(74, 62),
  ),
  '4-5-1' => Array(
    1 => Array(6, 50),
    2 => Array(26, 15), 3 => Array(22, 38), 4 => Array(22, 62), 5 => Array(26, 85),
    6 => Array(52, 12), 7 => Array(48, 31), 8 => Array(48, 50), 9 => Array(48, 69), 10 => Array(52, 88),
    11 => Array(74, 50),
  ),
  '4-1-4-1' => Array(
    1 => Array(6, 50),
    2 => Array(24, 15), 3 => Array(20, 38), 4 => Array(20, 62), 5 => Array(24, 85),
    6 => Array(36, 50),
    7 => Array(58, 15), 8 => Array(54, 38), 9 => Array(54, 62), 10 => Array(58, 85),
    11 => Array(74, 50),
  ),
  '3-5-2' => Array(
    1 => Array(6, 50),
    2 => Array(26, 25), 3 => Array(22, 50), 4 => Array(26, 75),
    5 => Array(50, 10), 6 => Array(46, 32), 7 => Array(46, 50), 8 => Array(46, 68), 9 => Array(50, 90),
    10 => Array(72, 38), 11 => Array(72, 62),
  ),
  '3-4-3' => Array(
    1 => Array(6, 50),
    2 => Array(26, 25), 3 => Array(22, 50), 4 => Array(26, 75),
    5 => Array(50, 15), 6 => Array(46, 38), 7 => Array(46, 62), 8 => Array(50, 85),
    9 => Array(76, 20), 10 => Array(72, 50), 11 => Array(76, 80),
  ),
  '5-3-2' => Array(
    1 => Array(6, 50),
    2 => Array(26, 10), 3 => Array(22, 30), 4 => Array(22, 50), 5 => Array(22, 70), 6 => Array(26, 90),
    7 => Array(50, 28), 8 => Array(46, 50), 9 => Array(50, 72),
    10 => Array(72, 38), 11 => Array(72, 62),
  ),
  '5-4-1' => Array(
    1 => Array(6, 50),
    2 => Array(24, 10), 3 => Array(20, 30), 4 => Array(20, 50), 5 => Array(20, 70), 6 => Array(24, 90),
    7 => Array(52, 15), 8 => Array(48, 38), 9 => Array(48, 62), 10 => Array(52, 85),
    11 => Array(74, 50),
  ),
);


// @claude-lock
// ============================================================
// RDZEŃ QUICK.CMS — NIE MODYFIKOWAĆ BEZ BARDZO DOBREGO POWODU
// ============================================================
/*
* Ustawienie adresu IP do logowania do administracji
* Więcej: https://opensolution.org/docs/?p=pl-settings#allowed_ip_admin_panel
*/
$config['allowed_ip_admin_panel'] = null; // domyślna wartość: null
$config['skin'] = 'theme'; // domyślna wartość: 'default'

/*
* Zmienna przechowuje domyślną wersję języka. Strona będzie się wyświetlać w tej wersji języka dopóki klient nie zmieni tłumaczenia
* Więcej: https://opensolution.org/docs/?p=pl-settings#default_language
*/
$config['default_language'] = 'pl'; // domyślna wartość: 'pl'

/*
* Tłumaczenie opisów pól i komunikatów w panelu administracyjnym
*/
$config['admin_lang'] = 'pl'; // domyślna wartość: 'pl'

/*
* Nazwa pliku administracji
* Więcej: https://opensolution.org/docs/?p=pl-settings#admin_file
*/
$config['admin_file'] = 'adm.php'; // domyślna wartość: 'admin.php'

/*
* Opcja włączania edytora WYSIWYG (domyślnie tinyMCE)
* Więcej: https://opensolution.org/docs/?p=pl-settings#wysiwyg
*/
$config['wysiwyg'] = 'tinymce'; // możliwe wartości: 'tinymce' (domyślne), null

/*
* Zmienna wyłącza wyświetlanie się linka do podstrony aktualnie przeglądanej w ścieżce nawigacji
* Więcej: https://opensolution.org/docs/?p=pl-settings#page_link_in_navigation_path
*/
$config['page_link_in_navigation_path'] = true; // możliwe wartości: true (domyślne), null

/*
* Jeśli ustawione na true, nazwa głównej strony będzie wyświetlana w TITLE
* Więcej: https://opensolution.org/docs/?p=pl-settings#display_homepage_name_title
*/
$config['display_homepage_name_title'] = null; // możliwe wartości: true, null (domyślne)

/*
* Opcja usuwania nieużywanych plików w czasie usuwania strony
* Więcej: https://opensolution.org/docs/?p=pl-settings#delete_unused_files
*/
$config['delete_unused_files'] = true; // możliwe wartości: true (domyślne), null

/*
* Możliwość wyświetlenia opisu krótkiego strony w miejscu pełnego opisu, jeśli ta strona nie posiada pełnego opisu
* Więcej: https://opensolution.org/docs/?p=pl-settings#short_to_full_description
*/
$config['short_to_full_description'] = true; // możliwe wartości: true (domyślne), null

/*
* Strefa "przeciągnij i upuść" dla plików dodawanych do strony
* Więcej: https://opensolution.org/docs/?p=pl-settings#enable_files_uploader_dropzone
*/
$config['enable_files_uploader_dropzone'] = true; // możliwe wartości: true (domyślne), null

/*
* Przechowują możliwe rozszerzenia dla zdjęć i zwykłych plików
* Więcej: https://opensolution.org/docs/?p=pl-settings#allowed_not_image_extensions
*/
$config['allowed_not_image_extensions'] = 'pdf|swf|doc|txt|xls|ppt|rtf|odt|ods|odp|rar|zip|7z|bz2|tar|gz|tgz|arj|docx'; // domyślna wartość jak wyżej
$config['allowed_image_extensions']     = 'jpg|jpeg|gif|png|webp|svg'; // domyślna wartość: 'jpg|jpeg|gif|png|webp'

/*
* Ustawienia dla rozmiarów i jakości wgrywanych zdjęć
* Więcej: https://opensolution.org/docs/?p=pl-settings#max_image_size
*/
$config['max_image_size']         = 6000; // maks. rozmiar dłuższego boku zdjęcia, dla którego wygeneruje się miniaturka (domyślnie 4000)
$config['max_dimension_of_image'] = 6000; // maks. wielkość dłuższego boku zdjęcia — po przekroczeniu zostanie pomniejszone (domyślnie 1100)
$config['image_quality']          = 100;  // jakość zapisu/pomniejszania zdjęcia (domyślnie 80)

/*
* Zmiana nazwy pliku do nazwy strony, do której jest dodawany
* Więcej: https://opensolution.org/docs/?p=pl-settings#change_files_names
*/
$config['change_files_names'] = null; // możliwe wartości: true, null (domyślne)

// Domyślne ustawienie dla slidera — więcej opcji: https://opensolution.org/docs/?p=pl-design#libraries
$config['default_slider_config'] = 'sAnimation:"fade",iPause:4000'; // domyślna wartość jak wyżej

/*
* Rozmiary srcset informujące przeglądarkę, jakie zdjęcie załadować dla mniejszych rozdzielczości.
* Wg rozmiarów z tej zmiennej utworzą się odpowiednie dodatkowe miniaturki slidera
*/
$config['slider_srcset'] = Array( 768 ); // domyślna wartość: Array( 667, 1024 )

/*
* Ustawienia domyślne formularza dodawania strony
* Więcej: https://opensolution.org/docs/?p=pl-settings#default_pages_menu
*/
$config['default_pages_menu']    = 1;    // domyślny typ strony — opcja dla $config['pages_menus']
$config['default_pages_status']  = true; // domyślny status widoczności strony
$config['default_page_parent']   = '';   // domyślna strona nadrzędna (ID lub puste)
$config['default_image_location'] = 3;   // domyślna lokalizacja zdjęcia — opcja dla $config['images_locations']
$config['default_image_size']    = 300;  // domyślny rozmiar miniaturki — opcja dla $config['images_thumbnails']
$config['default_theme']         = 1;    // domyślny motyw — opcja dla $config['themes']

/*
* Format daty w panelu administracyjnym
* Więcej: https://opensolution.org/docs/?p=pl-settings#date_format_admin_default
*/
$config['date_format_admin_default'] = 'Y-m-d H:i'; // domyślna wartość jak wyżej

/*
* Różnica czasu (w minutach) między czasem lokalnym a czasem na serwerze
* Więcej: https://opensolution.org/docs/?p=pl-settings#time_diff
*/
$config['time_diff'] = 0; // domyślna wartość: 0

/*
* Separator znacznika języka w adresie URL strony. Po wypełnieniu — zapisz jakąkolwiek stronę w administracji,
* żeby adresy stron zaktualizowały się o nazwę języka i separator.
* Więcej: https://opensolution.org/docs/?p=pl-settings#language_separator
*/
$config['language_separator'] = null; // domyślna wartość: null

/*
* Konfiguracja zaawansowanego pola select z wyszukiwarką
* Więcej: https://opensolution.org/docs/?p=pl-settings#advanced_select_default_width
*/
$config['advanced_select_default_width']            = '300px';
$config['advanced_select_long_width']                = '520px'; // klasa adv-select-long
$config['advanced_select_very_long_width']           = '650px'; // klasa adv-select-very-long
$config['enable_searching_in_advanced_select_from']  = 20; // liczba pozycji, od której pojawia się pole wyszukiwania

/*
* Katalog z bazą danych. Zmiana nazwy wymaga zapoznania się z dokumentacją
* Więcej: https://opensolution.org/docs/?p=pl-settings#dir_database
*/
$config['dir_database'] = 'database/';
$config['database']     = $config['dir_database'].'database.db';

/*
* Ścieżka bazowa wykorzystywana do generowania adresów URL przyjaznych SEO
*/
if( !isset( $config['base_path'] ) ){
  $config['base_path'] = '';
  if( isset( $_SERVER['SCRIPT_NAME'] ) ){
    $sScriptDir = str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ) );
    $sScriptDir = rtrim( $sScriptDir, '/' );
    if( $sScriptDir != '' && $sScriptDir != '.' && $sScriptDir != '/' ){
      if( $sScriptDir[0] != '/' )
        $sScriptDir = '/'.$sScriptDir;
      $config['base_path'] = $sScriptDir;
    }
  }
}
$config['base_path_with_slash'] = ( $config['base_path'] == '' ? '/' : rtrim( $config['base_path'], '/' ).'/' );
if( !isset( $config['seo_trailing_slash'] ) ){
  $config['seo_trailing_slash'] = true;
}

/*
* Lista rozszerzeń oraz przypisanych do nich klas (styli CSS)
* Więcej: https://opensolution.org/docs/?p=pl-settings#ext_icons
*/
$config['ext_icons'] = Array( 'rar'=>'zip', 'zip'=>'zip', 'bz2'=>'zip', 'gz'=>'zip', 'fla'=>'fla', 'mp3'=>'media', 'mpeg'=>'media', 'mpe'=>'media', 'mov'=>'media', 'mid'=>'media', 'midi'=>'media', 'asf'=>'media', 'avi'=>'media', 'wav'=>'media', 'wma'=>'media', 'msg'=>'eml', 'eml'=>'eml', 'pdf'=>'pdf', 'jpg'=>'pic', 'jpeg'=>'pic', 'jpe'=>'pic', 'gif'=>'pic', 'bmp'=>'pic', 'tif'=>'pic', 'tiff'=>'pic', 'wmf'=>'pic', 'png'=>'png', 'chm'=>'chm', 'hlp'=>'chm', 'psd'=>'psd', 'swf'=>'swf', 'pps'=>'pps', 'ppt'=>'pps', 'sys'=>'sys', 'dll'=>'sys', 'txt'=>'txt', 'doc'=>'txt', 'rtf'=>'txt', 'vcf'=>'vcf', 'xls'=>'xls', 'xml'=>'xml', 'tpl'=>'web', 'html'=>'web', 'htm'=>'web', 'com'=>'exe', 'bat'=>'exe', 'exe'=>'exe' );

/*
* Uwaga!
* Zmienne i kod znajdujący się poniżej przeznaczony jest jedynie dla zaawansowanych użytkowników i nie zalecamy jego modyfikacji
*/
$config['language_cookie_name'] = defined( 'CUSTOMER_PAGE' ) ? 'sLanguage' : 'sLanguageBackEnd';

if( isset( $_GET['sLanguage'] ) && strlen( $_GET['sLanguage'] ) == 2 && is_file( $config['dir_database'].'config_'.$_GET['sLanguage'].'.php' ) ){
  setCookie( $config['language_cookie_name'], $_GET['sLanguage'], time( ) + 86400 );
  $config['language'] = $_GET['sLanguage'];
  $config['current_page_id'] = true;
}
else{
  if( !empty( $_COOKIE[$config['language_cookie_name']] ) && is_file( $config['dir_database'].'config_'.$_COOKIE[$config['language_cookie_name']].'.php' ) && strlen( $_COOKIE[$config['language_cookie_name']] ) == 2 )
    $config['language'] = $_COOKIE[$config['language_cookie_name']];
  else
    $config['language'] = $config['default_language'];
}

if( !isset( $_GET['p'] ) && !isset( $config['current_page_id'] ) && defined( 'CUSTOMER_PAGE' ) ){
  $config['current_page_id'] = getPageId( );
  if( is_numeric( $config['current_page_id'] ) && isset( $_COOKIE[$config['language_cookie_name']] ) && $config['language'] != $_COOKIE[$config['language_cookie_name']] ){
    setCookie( $config['language_cookie_name'], $config['language'], time( ) + 86400 );
  }
}

require $config['dir_database'].'config_'.$config['language'].'.php';
require defined( 'CUSTOMER_PAGE' ) ? $config['dir_database'].'lang_'.$config['language'].'.php' : ( is_file( $config['dir_database'].'lang_'.$config['admin_lang'].'.php' ) ? $config['dir_database'].'lang_'.$config['admin_lang'].'.php' : $config['dir_database'].'lang_'.$config['language'].'.php' );

if( isset( $config['current_page_id'] ) && $config['current_page_id'] === true ){
  $config['current_page_id'] = $config['start_page'];
}

$config['version'] = '6.7';
$config['manual_link'] = 'https://opensolution.org/docs/?v='.$config['version'].'&amp;p='.( ( $config['admin_lang'] == 'pl' ) ? 'pl' : 'en' ).'-';

/*
* Sprawdza ustawienia serwera i konfiguracji skryptu
*/
if( defined( 'DEVELOPER_MODE' ) ){
  $sValue = (float) phpversion( );
  if( $sValue < '7.2' )
    exit( '<h1>Required PHP version is <u>7.2.0</u>, your version is '.phpversion( ).'</h1>' );
  elseif( !extension_loaded( 'pdo_sqlite' ) )
    exit( '<h1>Required <u>PDO</u> library with <u>pdo_sqlite</u> extension is not available</h1>' );
  elseif( !is_file( $config['database'] ) )
    exit( '<h1>Required file <u>'.$config['database'].'</u> is not available</h1>' );
  elseif( defined( 'ADMIN_PAGE' ) && ini_get( 'allow_url_fopen' ) != 1 ){
    exit( '<h1>Turn ON <u>allow_url_fopen</u> in PHP configuration (php.ini)</h1>' );
  }
}
elseif( isset( $_GET['error'] ) && $_GET['error'] == md5( $_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'] ) ){
  exit( '<h1>This page is temporary unavailable</h1>' );
}
// @claude-unlock


/**
* Returns page id from the $_GET
* @return array
*/
function getPageId( ){
  global $config;
  if( !is_file( $config['dir_database'].'cache/links' ) )
    exit( '<h1>'.( defined( 'DEVELOPER_MODE' ) ? 'There is no required file: '.$config['dir_database'].'cache/links' : 'This page is temporary unavailable' ).'</h1>' );

  $sLinksFile = $config['dir_database'].'cache/links';
  $sLinksRaw  = file_get_contents($sLinksFile);

  if ($sLinksRaw === false) {
    $config['pages_links'] = [];
  } else {
    // usuń BOM + białe znaki z początku
    $sLinksRaw = preg_replace('/^\xEF\xBB\xBF/', '', $sLinksRaw);
    $sLinksRaw = ltrim($sLinksRaw);

    if ($sLinksRaw !== '' && ($sLinksRaw[0] === '{' || $sLinksRaw[0] === '[')) {
      $aLinks = json_decode($sLinksRaw, true);
    } else {
      $aLinks = @unserialize($sLinksRaw);
    }

    $config['pages_links'] = is_array($aLinks) ? $aLinks : [];
  }

  $sRequestUri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
  $sPath = parse_url( $sRequestUri, PHP_URL_PATH );
  if( $sPath === false || $sPath === null || $sPath === '' )
    $sPath = '/';

  $sPath = str_replace( '\\', '/', $sPath );
  if( $sPath === '' )
    $sPath = '/';

  $aCandidates = Array();
  $aCandidates[$sPath] = true;
  if( $sPath !== '/' ){
    $aCandidates[rtrim( $sPath, '/' )] = true;
    $aCandidates[rtrim( $sPath, '/' ).'/'] = true;
  }
  if( substr( $sPath, -10 ) === '/index.php' ){
    $sAlt = substr( $sPath, 0, -10 );
    if( $sAlt === '' )
      $sAlt = '/';
    $aCandidates[$sAlt] = true;
    if( $sAlt !== '/' ){
      $aCandidates[rtrim( $sAlt, '/' )] = true;
      $aCandidates[rtrim( $sAlt, '/' ).'/'] = true;
    }
  }

  foreach( array_keys( $aCandidates ) as $sCandidate ){
    if( empty( $sCandidate ) )
      continue;
    if( $sCandidate[0] != '/' )
      $sCandidate = '/'.$sCandidate;
    $sCandidate = preg_replace( '#//+#', '/', $sCandidate );
    if( isset( $config['pages_links'][$sCandidate] ) ){
      $config['language'] = $config['pages_links'][$sCandidate][1];
      return $config['pages_links'][$sCandidate][0];
    }
  }

  if( isset( $_GET ) && is_array( $_GET ) ){
    foreach( $_GET as $mKey => $mValue ){
      if( isset( $config['pages_links']['?'.$mKey] ) ){
        $config['language'] = $config['pages_links']['?'.$mKey][1];
        return $config['pages_links']['?'.$mKey][0];
      }
      else
        return ( !empty( $mValue ) ? true : false );
      if( $mKey === 'sLanguage' )
        continue;
      if( !is_array( $mValue ) && $mValue !== '' )
        return true;
    }
    return true;
  }
  return false;
} // end function getPageId


// ============================================================
// TRANSMISJA LIVE — DOZWOLONE PLIKI WIDEO (nadpisanie poza @claude-lock)
// ============================================================
// Cieszynki zawodników na telebim: klip mp4/webm wgrywany STANDARDOWYM
// uploadem plików na podstronie zawodnika (panel → zakładka „Pliki").
// Wartość bazowa siedzi w rdzeniu (@claude-lock) — dlatego rozszerzenie tutaj.
$config['allowed_not_image_extensions'] .= '|mp4|webm';


// ============================================================
// SEKRETY POZA REPOZYTORIUM (opcjonalne — nadpisują wartości powyżej)
// ============================================================
// Docelowo trzymaj klucze (Google / PayU / InPost / reCAPTCHA / SMTP) oraz
// login_pass w NIEWERSJONOWANYM pliku database/config.secrets.php — nie trafia
// do gita (.gitignore) ani nie jest serwowany po HTTP (.htaccess). Wykonuje się
// jako ostatni, więc nadpisuje wartości powyżej (także reCAPTCHA z config_*.php).
// Wzorzec do skopiowania: database/config.secrets.dist.php
if( is_file( __DIR__.'/config.secrets.php' ) ){
  include __DIR__.'/config.secrets.php';
}

// ============================================================
// USTAWIENIA SKLEPU ZARZĄDZANE Z PANELU (Sklep → Ustawienia)
// ============================================================
// Plik generowany przez panel admina — nadpisuje np. $config['order_discounts']
// (w przyszłości także dostawy/płatności). Poza repozytorium (.gitignore),
// katalog database/ jest zablokowany przez .htaccess. Nie edytuj ręcznie.
if( is_file( __DIR__.'/config.shop.php' ) ){
  include __DIR__.'/config.shop.php';
}
?>