<?php
/*
* Główne dane konfiguracyjne strony, niezależne od języka
* Więcej: https://opensolution.org/docs/?p=pl-settings
*/
unset( $config, $lang, $aData );

/*
* Jeśli strona jest w trakcie tworzenia, warto pozostawić włączoną opcję DEVELOPER_MODE
* Więcej: https://opensolution.org/docs/?p=pl-settings#DEVELOPER_MODE
*/
//define( 'DEVELOPER_MODE', true ); // po uruchomieniu strony zakomentuj ta linie
if( defined( 'DEVELOPER_MODE' ) ){
  error_reporting( E_ALL | E_STRICT );
}

/*
* Login w postaci emaila i hasło do zalogowania się do panelu administracyjnego
* Dbaj o ich bezpieczeństwo. Nie ustawiaj hasła na "admin", "1234", "qwerty" itp.
* Więcej: https://opensolution.org/docs/?p=pl-settings#login_email
*/
$config['login_email'] = "k@kosiorski.pl";
$config['login_pass'] = "haslo";

/*
* Ustawienie adresu IP do logowania do administracji
* Więcej: https://opensolution.org/docs/?p=pl-settings#allowed_ip_admin_panel
*/
$config['allowed_ip_admin_panel'] = null; // domyślna wartość: null

/*
* Zmienna przechowuje nazwę katalogu skórki
* Więcej: https://opensolution.org/docs/?p=pl-settings#skin
*/
$config['skin'] = 'theme'; // domyślna wartość: 'default'

/*
* Rozmiary miniaturek i lokalizacje zdjęć. Dodając nową lokalizację, nadaj jej cyfrę nie mniejszą niż 50
* Więcej: https://opensolution.org/docs/?p=pl-settings#images_thumbnails
*/
$config['images_thumbnails'] = Array( 500 ); // domyślna wartość: Array( 100, 200, 300 )
$config['images_locations'] = Array( 1 => 'Galeria', 0 => 'Ukryte' ); // domyślna wartość: 1 => 'Lewa strona', 2 => 'Prawa strona', 3 => 'Galeria', 0 => 'Brak'

/*
* Rodzaje menu przedstawione w postaci tablicy. Klucz 0 w zmiennej jest zarezerwowany dla ukrytego menu!
* Więcej: https://opensolution.org/docs/?p=pl-settings#pages_menus
*/
$config['pages_menus'] = Array( 1 => 'Nawigacja', 0 => 'Ukryte', 3 => 'Składy' ); // domyślna wartość: Array( 1 => 'Menu górne', 0 => 'Ukryte' )
/*
* Ustawienia motywów do wyboru w czasie edycji strony
* Więcej: https://opensolution.org/docs/?p=pl-settings#themes
*/
$config['themes'] = Array(
  1 => 'page.php',
  2 => 'page-index.php',
  3 => 'page-fetch.php',
  4 => 'page-screen.php',
  5 => 'page-update.php',
  6 => 'page-live.php',
  7 => 'page-admin.php'
);

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
* Opcja włączania edytora WYSIWYG (domyslnie tinyMCE)
* Więcej: https://opensolution.org/docs/?p=pl-settings#wysiwyg
*/
$config['wysiwyg'] = 'tinymce'; // możliwe wartości: 'tinymce' (domyślne), null

/*
* Zmienna wyłącza wyświetlanie się linka do podstrony aktualnie przeglądanej w scieżce nawigacji
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
* Możliwość wyświetlenia opisu krótkiego strony w miejscu pełnego opisu, jesli ta strona nie posiada pełnego opisu
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
// Rozszerzenia dla plików - nie zdjęć
$config['allowed_not_image_extensions'] = 'pdf|swf|doc|txt|xls|ppt|rtf|odt|ods|odp|rar|zip|7z|bz2|tar|gz|tgz|arj|docx'; // domyślna wartość: 'pdf|swf|doc|txt|xls|ppt|rtf|odt|ods|odp|rar|zip|7z|bz2|tar|gz|tgz|arj|docx'
// Rozszerzenia dla zdjęc
$config['allowed_image_extensions'] = 'jpg|jpeg|gif|png|webp'; // domyślna wartość: 'jpg|jpeg|gif|png|webp'

/*
* Ustawienia dla rozmiarów i jakości wgrywanych zdjęć
* Więcej: https://opensolution.org/docs/?p=pl-settings#max_image_size
*/
// Maksymalny rozmiar dłuższego boku zdjęcia dla którego wygeneruje się miniaturka
$config['max_image_size'] = 6000; // domyślna wartość: 4000
// Maksymalna wielkość dłuższego boku zdjęcia. Gdy poniższa wartość zostanie przekroczona, to zostanie pomniejszony do niżej zdefiniowanej.
$config['max_dimension_of_image'] = 6000; // domyślna wartość: 1100
// Jakość zapisywanego i pomniejszanego zdjęcia
$config['image_quality'] = 100; // domyślna wartość: 80

/*
* Zmiana nazwy pliku do nazwy strony, do której jest dodawany
* Więcej: https://opensolution.org/docs/?p=pl-settings#change_files_names
*/
$config['change_files_names'] = null; // możliwe wartości: true, null (domyślne)

// Domyślne ustawienie dla slidera, więcej możliwych opcji znajdziesz w dokumentacji: https://opensolution.org/docs/?p=pl-design#libraries
$config['default_slider_config'] = 'sAnimation:"fade",iPause:4000'; // domyślna wartość: 'sAnimation:"fade",iPause:4000'

/*
* Rozmiary srcset informujący przeglądarkę o zdjęciu, które ma załadować się dla mniejszych rozdzielczości. 
* Wg rozmiarów ustawionych w tej zmiennej utworzą się odpowiednie dodatkowe miniaturki slidera
*/
$config['slider_srcset'] = Array( 768 ); // domyślna wartość: Array( 667, 1024 )

/*
* Ustawienia domyślne dla niektórych opcji
* Więcej: https://opensolution.org/docs/?p=pl-settings#default_pages_menu
*/
// FORMULARZ STRONY
// Domyślny typ strony. Opcja dla zmiennej: $config['pages_menus']
$config['default_pages_menu'] = 1; // domyślna wartość: 1

// Domyślny status strony. Dla wartości 'true' strona będzie widoczna
$config['default_pages_status'] = true; // możliwe wartości: true (domyślne), null

// Domyślna strona nadrzędna. Wpisz id strony nadrzędnej lub pozostaw puste jeśli strona nadrzędna ma nie być domyślnie ustawiona
$config['default_page_parent'] = ''; // możliwe wartości: 'ID strony', '' (domyślne)

// Domyślna lokalizacja zdjęcia dla strony. Opcja dla zmiennej: $config['images_locations']
$config['default_image_location'] = 3; // domyślna wartość: 3

// Domyślny rozmiar miniaturki zdjęcia dla strony. Opcja dla zmiennej: $config['images_thumbnails']
$config['default_image_size'] = 300; // domyślna wartość: 300

// Domyślny motyw. Opcja dla zmiennej: $config['themes']
$config['default_theme'] = 1; // domyślna wartość: 1

/*
* Format daty
* Więcej: https://opensolution.org/docs/?p=pl-settings#date_format_admin_default
*/
// Prezentacja daty w panelu administracyjnym
$config['date_format_admin_default'] = 'Y-m-d H:i'; // domyślna wartość: 'Y-m-d H:i'

/*
* Dodaj różnicę czasu (w minutach) między czasem lokalnym a czasem na serwerze
* Więcej: https://opensolution.org/docs/?p=pl-settings#time_diff
*/
$config['time_diff'] = 0; // domyślna wartość: 0

/*
* Jeśli w adresie URL nazwy strony ma być dodawany znacznik języka, to dodaj do niego separator np. _
* Po wypełnieniu poniższej zmiennej, edytuj jakąkolwiek stronę w administracji i zapisz ją (nie musisz w niej nic zmieniać),
* aby adresy stron zaktualizowały się o nazwę języka i separator.
* Więcej: https://opensolution.org/docs/?p=pl-settings#language_separator
*/
$config['language_separator'] = null; // domyślna wartość: null

/*
* Konfiguracja zaawansowanego pola select z wyszukiwarką
* Więcej: https://opensolution.org/docs/?p=pl-settings#advanced_select_default_width
*/
// Domyślna szerokość pola
$config['advanced_select_default_width'] = '300px'; // domyślna wartość: '300px'
// Szerokość większego pola - z klasą adv-select-long
$config['advanced_select_long_width'] = '520px'; // domyślna wartość: '520px'
// Szerokość największego pola - z klasą adv-select-very-long
$config['advanced_select_very_long_width'] = '650px'; // domyślna wartość: '650px'
// Ilość pozycji w polu wyboru, powyżej której pojawia się pole wyszukiwania
$config['enable_searching_in_advanced_select_from'] = 20; // domyślna wartość: 20

/*
* Katalog z bazą danych. Istnieje możliwość zmiany jego nazwy i w tym przypadku zapoznaj się koniecznie z dokumentacją
* Więcej: https://opensolution.org/docs/?p=pl-settings#dir_database
*/
$config['dir_database'] = 'database/'; // domyślna wartość: 'database/'
$config['database'] = $config['dir_database'].'database.db'; // domyślna wartość: $config['dir_database'].'database.db'

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

/**
* Returns page id from the $_GET
* @return array
*/
function getPageId( ){
  global $config;
  if( !is_file( $config['dir_database'].'cache/links' ) )
    exit( '<h1>'.( defined( 'DEVELOPER_MODE' ) ? 'There is no required file: '.$config['dir_database'].'cache/links' : 'This page is temporary unavailable' ).'</h1>' );

  $config['pages_links'] = unserialize( file_get_contents( $config['dir_database'].'cache/links' ) );
  if( isset( $_GET ) && is_array( $_GET ) ){
    foreach( $_GET as $mKey => $mValue ){
      if( isset( $config['pages_links']['?'.$mKey] ) ){
        $config['language'] = $config['pages_links']['?'.$mKey][1];
        return $config['pages_links']['?'.$mKey][0];
      }
      else
        return ( !empty( $mValue ) ? true : false );
    }
    return true;
  }
} // end function getPageId
?>