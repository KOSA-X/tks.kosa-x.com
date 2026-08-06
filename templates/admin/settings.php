<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

if( isset( $_POST['sOption'] ) ){
    unset( $_POST['login_email'], $_POST['login_pass'] );

    if(
        !empty( $_POST['login_pass_old'] )
        && !empty( $_POST['login_email_old'] )
        && hash_equals( (string) ( $config['login_email'] ?? '' ), changeSpecialChars( $_POST['login_email_old'] ) )
        && adminPasswordVerify( $_POST['login_pass_old'] )
    ){
        if( !empty( $_POST['login_email_new'] ) && checkEmail( $_POST['login_email_new'] ) ){
            $_POST['login_email'] = $_POST['login_email_new'];
        }

        if( !empty( $_POST['login_pass_new'] ) ){
            $_POST['login_pass'] = $_POST['login_pass_new'];
        }
    }

    saveVariables( $_POST, $config['dir_database'].'config.php' );
    saveVariables( $_POST, $config['dir_database'].'config_'.$config['language'].'.php' );

    header( 'Location: '.$config['admin_file'].'?p=settings&sOption=save' );
    exit;
}

$sSelectedMenu = 'settings';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

/**
 * Helper: bezpieczny odczyt configu do value
 */
function cfgValue( array $config, string $key, string $default = '' ): string {
    return isset( $config[$key] ) ? html( (string) $config[$key] ) : html( $default );
}

/**
 * Helper: pole input text
 */
function renderTextInput( string $name, string $label, array $config, string $type = 'text', string $help = '' ): string {
    $id = $name;

    $out = '
        <li>
            <div class="form-item">
                <label for="'.$id.'">'.html( $label ).'</label>
                <input type="'.html( $type ).'" name="'.$name.'" id="'.$id.'" value="'.cfgValue( $config, $name ).'" placeholder="" />
                '.( $help !== '' ? '<p class="form-text">'.html( $help ).'</p>' : '' ).'
            </div>
        </li>
    ';

    return $out;
}

/**
 * Helper: select "Strona XYZ" (wybór strony z listy)
 */
function renderPageSelect( string $name, string $label, array $config, $oPage, bool $required = false ): string {
    $id = $name;

    $selected = isset( $config[$name] ) ? $config[$name] : '';

    $out = '
        <li>
            <div class="form-item">
                <label for="'.$id.'">'.html( $label ).'</label>
                <select name="'.$name.'" id="'.$id.'"'.( $required ? ' data-form-check="required"' : '' ).'>
                    '.( empty( $selected ) ? '<option value="" disabled="disabled" selected="selected">'.$GLOBALS['lang']['none'].'</option>' : '' ).'
                    '.$oPage->listPagesSelectAdmin( $selected ).'
                </select>
            </div>
        </li>
    ';

    return $out;
}

/**
 * Helper: godziny otwarcia (para inputów)
 */
function renderHoursPair( string $labelOpen, string $nameOpen, string $labelClose, string $nameClose, array $config ): string {
    return
        renderTextInput( $nameOpen, $labelOpen, $config )
        .renderTextInput( $nameClose, $labelClose, $config );
}
?>

<form action="?p=<?php echo html( $_GET['p'] ?? 'settings' ); ?>" name="form" method="post" class="main-form">

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title"><?php echo $lang['Settings']; ?></h1>
        <div class="mainPage__buttons">
            <input type="submit" name="sOption" class="button" value="<?php echo $lang['save']; ?>" />
        </div>
    </header>

    <?php
    if( isset( $config['manual_link'] ) ){
        echo '<div class="manual"><a href="'.$config['manual_link'].'instruction#settings" title="'.$lang['Help'].'" target="_blank"></a></div>';
    }

    if( isset( $_GET['sOption'] ) ){
        echo '<div class="alert alert-success">'.$lang['Operation_completed'].'</div>';
    }
    ?>

    <ul class="tabs">
        <li id="pages" class="selected"><a href="#">Główne & SEO</a></li>
        <li id="contacts"><a href="#">Dane kontaktowe</a></li>
        <li id="socialmedia"><a href="#">Social media</a></li>
        <li id="tools"><a href="#">Narzędzia</a></li>
        <!-- <li id="loging"><a href="#"><?php echo $lang['Loging']; ?></a></li> -->
    </ul>

    <script>
        var aLoginAjax = {};
        $(function(){
            displayTabInit();
            $(".main-form").quickform();
        });
    </script>

    <!-- TAB: PAGES -->
    <ul id="tab-pages" class="forms list tabsContent">

        <li><h5 class="form-separator">SEO Meta Google</h5></li>

        <?php
        echo renderTextInput( 'title', 'Tytuł strony', $config );
        echo renderTextInput( 'description', 'Opis', $config );
        echo renderTextInput( 'logo', 'Nazwa skrócona', $config );
        echo renderTextInput( 'slogan', 'Slogan', $config );
        ?>
        

        <li><h5 class="form-separator">Dopasowanie stron</h5></li>

        <?php
        echo renderPageSelect( 'start_page', $lang['Start_page'], $config, $oPage, true );
        ?>

        <li>
            <h5 class="form-separator">Transmisja live — treści plansz</h5>
            <p class="form-text">Wskaż zakładki, z których plansze biorą treść (opisy + zdjęcia w gridzie). Zakładki tworzysz w Strony (typ menu „Plansze").</p>
        </li>

        <?php
        // źródła treści plansz nakładki OBS / telebimu — klucze z config_pl.php
        echo renderPageSelect( 'match_page', 'Plansza DZIEŃ MECZOWY (mecz dnia: opis, plakat, data)', $config, $oPage );
        echo renderPageSelect( 'live_referees_page', 'Plansza SĘDZIOWIE (opis + zdjęcia)', $config, $oPage );
        echo renderPageSelect( 'live_sponsors_page', 'Plansza SPONSORZY (grid logotypów)', $config, $oPage );
        echo renderPageSelect( 'live_production_page', 'Plansza REALIZACJA TRANSMISJI (tytuł + zdjęcia)', $config, $oPage );
        ?>

    </ul>

    <!-- TAB: CONTACTS -->
    <ul id="tab-contacts" class="forms list tabsContent">

        <?php
        echo renderTextInput( 'email', 'E-mail', $config );
        echo renderTextInput( 'phone', 'Telefon', $config );
        echo renderTextInput( 'phone2', 'Telefon 2', $config );
        echo renderTextInput( 'street', 'Ulica i nr', $config );
        echo renderTextInput( 'city', 'Miasto', $config );
        echo renderTextInput( 'code', 'Kod pocztowy', $config );
        echo renderTextInput( 'maps', 'Google Maps URL', $config );
        ?>

        <li>
            <h5 class="form-separator">Godziny otwarcia</h5>
            <p class="form-text">Format godziny to np. 08:00</p>
        </li>

        <?php
        echo renderHoursPair( 'Otwarcie Pon - Pt', 'hours_1', 'Zamknięcie Pon - Pt', 'hours_2', $config );
        echo renderHoursPair( 'Otwarcie Sobota', 'hours_3', 'Zamknięcie Sobota', 'hours_4', $config );
        echo renderHoursPair( 'Otwarcie Niedziela', 'hours_5', 'Zamknięcie Niedziela', 'hours_6', $config );
        ?>

    </ul>

    <!-- TAB: SOCIAL MEDIA -->
    <ul id="tab-socialmedia" class="forms list tabsContent">

        <?php
        echo renderTextInput( 'facebook', 'Facebook', $config );
        echo renderTextInput( 'instagram', 'Instagram', $config );
        echo renderTextInput( 'tiktok', 'Tik Tok', $config );
        echo renderTextInput( 'youtube', 'Youtube', $config );
        echo renderTextInput( 'xcom', 'X.com', $config );
        echo renderTextInput( 'whatsapp', 'Whatsapp', $config );
        echo renderTextInput( 'linkedin', 'LinkedIn', $config );
        ?>

    </ul>

    <!-- TAB: TOOLS -->
    <ul id="tab-tools" class="forms list tabsContent">

        <?php
        echo renderTextInput( 'publicKey', 'Google Recaptcha (Klucz witryny)', $config );
        echo renderTextInput( 'secretKey', 'Google Recaptcha (Tajny klucz)', $config );
        echo renderTextInput( 'analytics', 'Google Analytics', $config );
        echo renderTextInput( 'tagmenager', 'Google Tag Menager', $config ); // FIX: poprawiony klucz isset/value
        echo renderTextInput( 'google-site-verification', 'Google Site Verification', $config ); // FIX: poprawiony klucz isset/value
        echo renderTextInput( 'tinymce', 'Tinymce', $config );
        echo renderTextInput( 'google_client_id', 'Logowanie Google — Client ID', $config, 'text', 'OAuth 2.0 Client ID z Google Cloud Console. Puste = logowanie Google wyłączone.' );
        echo renderTextInput( 'google_client_secret', 'Logowanie Google — Client Secret', $config, 'text', 'Opcjonalny (One Tap go nie wymaga).' );
        ?>

        <li>
            <a href="https://www.google.com/recaptcha/admin/create" target="_blank" class="button button-border mr-2">Google Recaptcha</a>
            <a href="https://analytics.google.com/analytics/web/" target="_blank" class="button button-border mr-2">Google Analytics</a>
            <a href="https://tagmanager.google.com/?hl=pl#/home" target="_blank" class="button button-border mr-2">Google Tag Menager</a>
            <a href="https://www.tiny.cloud/" target="_blank" class="button button-border mr-2">Tinymce</a>
        </li>

    </ul>

    <!-- (opcjonalnie) LOGING + OPTIONS zostawiamy jako wyłączone/legacy
         bo nie masz do nich taba w menu.
         Jak chcesz — w kolejnym kroku całkiem to wycinamy i usuwamy checkLoginChange(). -->

    <p class="mt-3 muted"><?php echo $lang['Settings_in_config_file'].' '.$config['dir_database']; ?>config.php</p>

</form>

<?php
require_once 'templates/admin/_footer.php';
?>
