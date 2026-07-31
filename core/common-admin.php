<?php

/**
 * Log in and out actions to back-end
 * @return void
 */
function loginActions(){
    global $config, $lang;
    
    // BEZPIECZEŃSTWO (CVE-2025-9982): nigdy nie wstawiaj loginu/hasła do formularza.
    // Usunięto tryb "?poka", który ujawniał dane logowania w value pola input
    // każdemu anonimowemu odwiedzającemu stronę logowania.
    $admin_login = null;
    $admin_pass  = '';

    $content = null;
    $p = $_GET['p'] ?? '';



    if( !isset( $_SESSION[$config['session_key_name']] ) || !is_int( $_SESSION[$config['session_key_name']] ) ){
        $oSql = Sql::getInstance();
        $bFirstLog = null;

        // Konto uznajemy za "nieustawione" tylko gdy nie ma ANI hasła jawnego
        // ANI hasha w bazie. Dzięki temu można bezpiecznie usunąć jawne hasło
        // z config.php po migracji do hasha (patrz checkLogin / adminPasswordVerify).
        if( empty( $config['login_email'] ) || !checkEmail( $config['login_email'] ) || ( empty( $config['login_pass'] ) && !adminPasswordHashExists() ) ){
            $bFirstLog = true;
        }

        if(
            isset( $config['failed_logs'] ) &&
            isset( $config['failed_login_time'] ) &&
            $config['failed_logs'] > 2 &&
            time() - $config['failed_login_time'] <= 900
        ){
            $content = '<div class="alert alert-danger mt-2"><strong>'.$lang['Failed_login_wait_time'].'</strong></div>';
        }
        else{
            if( $p === 'login' && isset( $_POST['sEmail'] ) && checkEmail( $_POST['sEmail'] ) && !empty( $_POST['sPass'] ) ){

                if( isset( $bFirstLog ) ){
                    saveVariables(
                        array(
                            'login_email' => $_POST['sEmail'],
                            'login_pass'  => $_POST['sPass'],
                        ),
                        $config['dir_database'].'config.php'
                    );
                }

                if( checkLogin( $_POST['sEmail'], $_POST['sPass'], $bFirstLog ) === true ){

                    if( isset( $_POST['bAcceptLicense'] ) && isset( $_POST['iAcceptLicense'] ) ){
                        @setCookie( 'bLicense'.str_replace( '.', '', $config['version'] ), true, time() + 15552000 );
                    }

                    if( !isset( $_COOKIE['sEmail'] ) || $_COOKIE['sEmail'] != $_POST['sEmail'] ){
                        @setCookie( 'sEmail', $_POST['sEmail'], time() + 2592000 );
                    }

                    if( isset( $config['last_login'] ) ){
                        updateBin( 'before_last_login', $config['last_login'] );
                    }

                    updateBin( 'last_login', time() );
                    updateBin( 'failed_logs', 0 );

                    header( 'Location: '.( !empty( $_SESSION['sLoginNextPage'] ) ? $_SESSION['sLoginNextPage'] : $config['admin_file'] ) );
                    exit;
                }
                else{
                    $content = '<div class="alert alert-danger mt-2"><strong>'.$lang['Wrong_email_or_pass'].'</strong><a href="javascript:history.back()">&laquo; '.$lang['back'].'</a></div>';
                }
            }
            else{
                $_SESSION['sLoginNextPage'] = str_replace( '&amp;', '&', $_SERVER['REQUEST_URI'] );

                $emailValue = '';
                if( isset( $_COOKIE['sEmail'] ) ){
                    $emailValue = strip_tags( $_COOKIE['sEmail'] );
                }
                elseif( $admin_login !== null ){
                    $emailValue = $admin_login;
                }

                // Komunikat po nieudanym logowaniu Google (przekierowanie z endpointu)
                $googleNotice = '';
                if( isset( $_GET['google_error'] ) ){
                    $sErr = preg_replace( '/[^a-z]/', '', (string) $_GET['google_error'] );
                    $aErrMap = array(
                        'forbidden' => ( $config['admin_lang'] == 'pl' ? 'To konto Google nie ma uprawnień do panelu administracyjnego.' : 'This Google account is not allowed to access the admin panel.' ),
                        'disabled'  => ( $config['admin_lang'] == 'pl' ? 'Logowanie Google do panelu nie jest skonfigurowane.' : 'Google sign-in to the panel is not configured.' ),
                    );
                    $sMsg = $aErrMap[$sErr] ?? ( $config['admin_lang'] == 'pl' ? 'Logowanie przez Google nie powiodło się. Spróbuj ponownie.' : 'Google sign-in failed. Please try again.' );
                    $googleNotice = '<div class="alert alert-danger mt-2">'.html( $sMsg ).'</div>';
                }

                // Przycisk "Zaloguj przez Google" — tylko gdy skonfigurowano Client ID i listę adminów
                $googleButton = '';
                if( !empty( $config['google_client_id'] ) && !empty( $config['admin_google_emails'] ) ){

                    // Podpisane, krótko żyjące ciasteczko intencji logowania do PANELU.
                    // Odbiera je plugins/google-auth.php (ten sam, zarejestrowany w Google
                    // endpoint), żeby odróżnić logowanie admina od logowania klienta —
                    // bez potrzeby dodatkowej konfiguracji w Google Cloud Console.
                    $adminGoogleTs    = time();
                    $adminGoogleToken = $adminGoogleTs.'.'.hash_hmac( 'sha256', (string) $adminGoogleTs, (string) ( $config['session_key_name'] ?? '' ) );
                    $adminGoogleSecure = ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' );
                    @setcookie( 'kx_admin_glogin', $adminGoogleToken, time() + 600, '/', '', $adminGoogleSecure, true );

                    $googleButton = '
                        <style>
                            .login-google{ margin-top:18px; }
                            .login-google .googleSignIn__separator{ position:relative; text-align:center; margin:16px 0; color:#8a8a92; text-transform:uppercase; letter-spacing:.1em; font-size:11px; }
                            .login-google .googleSignIn__separator::before{ content:""; position:absolute; left:0; right:0; top:50%; height:1px; background:#e5e5ea; }
                            .login-google .googleSignIn__separator span{ position:relative; background:#fff; padding:0 12px; }
                            .login-google .g_id_signin{ display:flex; justify-content:center; }
                        </style>
                        <div class="googleSignIn login-google">
                            <div class="googleSignIn__separator"><span>'.( $config['admin_lang'] == 'pl' ? 'albo' : 'or' ).'</span></div>
                            <div id="g_id_onload"
                                 data-client_id="'.html( $config['google_client_id'] ).'"
                                 data-login_uri="'.html( BASE_URL.'/plugins/google-auth.php' ).'"
                                 data-ux_mode="redirect"
                                 data-auto_prompt="false"></div>
                            <div class="g_id_signin"
                                 data-type="standard"
                                 data-theme="filled_black"
                                 data-text="signin_with"
                                 data-shape="pill"
                                 data-logo_alignment="center"></div>
                            <script src="https://accounts.google.com/gsi/client" async></script>
                        </div>';
                }

                $content = '
                <div class="card mt-3">
                    <header class="card__header">
                        <h4 class="card__title">Panel administracyjny - '.( isset( $bFirstLog ) ? $lang['Type_login_password'] : $lang['log_in'] ).'</h4>
                    </header>
                    <div class="card__wrapper">
                        <div class="card__content">
                            '.$googleNotice.'
                            <form method="post" action="?p=login" id="login-form" class="login-form">

                                <div class="form-item">
                                    <label for="sEmail" class="form-label mb-1">'.$lang['Email'].'</label>
                                    <input type="email" class="form-control input" id="sEmail" name="sEmail"
                                        value="'.html( $emailValue ).'"
                                        placeholder="'.$lang['Email'].'"
                                        data-form-check="email">
                                </div>

                                <div class="form-item">
                                    <label for="sPass" class="form-label mb-1">'.$lang['Password'].'</label>
                                    <input type="'.( isset( $bFirstLog ) ? 'text' : 'password' ).'" class="form-control" id="sPass" name="sPass"
                                        value="'.html( $admin_pass ).'"
                                        placeholder="'.$lang['Password'].'"
                                        required data-form-check="required">
                                </div>

                                '.( !isset( $_COOKIE['bLicense'.str_replace( '.', '', $config['version'] )] ) ? '
                                <div class="form-check form-item login-panel__license">
                                    <input type="hidden" name="bAcceptLicense" value="1" class="mr-2" />
                                    '.getYesNoBox(
                                        'iAcceptLicense',
                                        0,
                                        array(
                                            'value' => 'true',
                                            'data-form-check' => 'required',
                                            'data-form-msg' => ( $config['admin_lang'] == 'pl' ? 'Zaakceptuj licencję' : 'Accept the license' ),
                                        )
                                    ).'
                                    <label for="iAcceptLicense" class="ml-1 form-check-label">'.(
                                        $config['admin_lang'] == 'pl'
                                            ? 'Akceptuję licencję systemu <a href="http://opensolution.org/licencje.html?notice=" target="_blank">Quick.Cms &raquo;</a>'
                                            : 'I accept <a href="http://opensolution.org/licenses.html?notice=" target="_blank">Quick.Cms license &raquo;</a>'
                                    ).'</label>
                                    <span class="label">&nbsp;</span>
                                </div>' : '' ).'

                                <div class="form-button flex align-center">
                                    <input type="submit" class="button main mr-2" value="'.$lang['log_in'].'" />
                                    <a href="mailto:konrad@kosiorski.pl" target="_blank">'.$lang['forgot_your_password'].'</a>
                                </div>

                            </form>
                            '.$googleButton.'
                            <script>
                                $(function(){
                                    focusCursor(["sEmail","sPass"]);
                                    $("#login-form").quickform();
                                });
                            </script>

                        </div>

                        <footer class="card__footer flex-justify">
                            <a href="./">'.$lang['homepage'].' <strong>'.html( $config['logo'] ).'</strong></a>
                            <a href="http://opensolution.org/" target="_blank">Quick.Cms v'.html( $config['version'] ).'</a>
                        </footer>

                    </div>
                </div>
                ';
            }
        }

        require_once 'templates/admin/_header.php';

        $panelClass = 'login-panel'.( isset( $bFirstLog ) ? ' init' : '' );

        echo '
        <div class="login-wrapper">
            <section class="'.$panelClass.'" id="login-panel">
                <header class="login-header flex align-center">
                    <img src="templates/admin/img/logo-kosax.png" alt="KOSA X">
                    <a class="ml-2" href="https://kosa-x.com" target="_blank">kosa-x.com</a>
                </header>
                '.$content.'
            </section>
        </div>
        <style>.mainFooter{display:none;}</style>';

        require_once 'templates/admin/_footer.php';
        exit;
    }
    else{
        if( $p === 'logout' ){
            foreach( $_SESSION as $sKey => $mValue ){
                unset( $_SESSION[$sKey] );
            }
            header( 'Location: '.$config['admin_file'] );
            exit;
        }
        elseif( $p !== 'dashboard' && !strstr( $p, 'ajax-' ) && !isset( $_COOKIE['bLicense'.str_replace( '.', '', $config['version'] )] ) ){
            header( 'Location: '.$config['admin_file'].'?p=dashboard' );
            exit;
        }
    }
}

/**
 * Checks login and password saved in database/config.php
 * @return bool
 * @param string $sEmailRaw
 * @param string $sPassRaw
 * @param bool   $bFirstLog
 */
function checkLogin( $sEmailRaw, $sPassRaw, $bFirstLog = null ){
    global $config;

    $sEmail = changeSpecialChars( $sEmailRaw );

    // Porównanie e-maila w stałym czasie (ochrona przed timing-attack)
    $bEmailOk = hash_equals( (string) ( $config['login_email'] ?? '' ), (string) $sEmail );

    if( ( $bEmailOk && adminPasswordVerify( $sPassRaw ) ) || isset( $bFirstLog ) ){

        // Migracja hasła do postaci hasha (CVE-2025-9982) — wykonywana
        // przy pierwszym poprawnym logowaniu lub po ręcznej zmianie hasła w config.php.
        if( !empty( $sPassRaw ) ){
            adminPasswordMigrate( $sPassRaw );
        }

        // Ochrona przed fixacją sesji — nowe ID sesji przy zmianie uprawnień.
        if( session_status() === PHP_SESSION_ACTIVE ){
            session_regenerate_id( true );
        }

        $_SESSION[$config['session_key_name']] = 0;
        return true;
    }
    else{
        updateBin( 'failed_logs', ( isset( $config['failed_logs'] ) ? ( $config['failed_logs'] + 1 ) : 1 ) );
        updateBin( 'failed_login_time', time() );
        return false;
    }
}

/**
 * Czy w bazie (tabela bin) istnieje poprawny hash hasła administratora.
 * @return bool
 */
function adminPasswordHashExists(){
    global $config;
    $sHash = isset( $config['login_pass_hash'] ) ? (string) $config['login_pass_hash'] : '';
    return ( $sHash !== '' && preg_match( '/^\$(2y|2a|2b|argon2)/', $sHash ) === 1 );
}

/**
 * Weryfikuje hasło administratora.
 * Preferuje hash (bcrypt/argon2) z tabeli bin; z kompatybilnością wsteczną
 * dla jawnego hasła z config.php oraz wykrywaniem ręcznej zmiany hasła
 * (odcisk palca "login_pass_fp").
 * @return bool
 * @param string $sPassRaw
 */
function adminPasswordVerify( $sPassRaw ){
    global $config;

    $sPass  = changeSpecialChars( str_replace( '"', '&quot;', $sPassRaw ) );
    $sPlain = (string) ( $config['login_pass'] ?? '' );

    if( adminPasswordHashExists() ){
        // Jeżeli w config.php nadal jest jawne hasło i zostało ono ręcznie
        // ZMIENIONE (odcisk się nie zgadza) — użyj hasła jawnego i pozwól na
        // ponowną migrację. Dzięki temu edycja hasła w config.php nadal działa.
        if( $sPlain !== '' ){
            $sFp = isset( $config['login_pass_fp'] ) ? (string) $config['login_pass_fp'] : '';
            if( $sFp === '' || !hash_equals( $sFp, hash( 'sha256', $sPlain ) ) ){
                return hash_equals( $sPlain, $sPass );
            }
        }
        return password_verify( (string) $sPassRaw, (string) $config['login_pass_hash'] );
    }

    // Brak hasha — logika legacy (jawne hasło z config.php)
    return hash_equals( $sPlain, $sPass );
}

/**
 * Zapisuje hash hasła administratora w tabeli bin (jednorazowa migracja
 * lub po zmianie jawnego hasła). Eliminuje przechowywanie hasła jawnym tekstem.
 * @return void
 * @param string $sPassRaw
 */
function adminPasswordMigrate( $sPassRaw ){
    global $config;

    $sPlain = (string) ( $config['login_pass'] ?? '' );
    $sFpNow = ( $sPlain !== '' ) ? hash( 'sha256', $sPlain ) : '';

    $bNeed = false;
    if( !adminPasswordHashExists() ){
        $bNeed = true; // pierwsza migracja
    }
    elseif( $sPlain !== '' ){
        $sFp = isset( $config['login_pass_fp'] ) ? (string) $config['login_pass_fp'] : '';
        if( $sFp === '' || !hash_equals( $sFp, $sFpNow ) ){
            $bNeed = true; // jawne hasło zmienione ręcznie → re-migracja
        }
    }

    if( $bNeed ){
        $sHash = password_hash( (string) $sPassRaw, PASSWORD_DEFAULT );
        updateBin( 'login_pass_hash', $sHash );
        updateBin( 'login_pass_fp', $sFpNow );
        // aktualizacja w pamięci (spójność w obrębie tego żądania)
        $config['login_pass_hash'] = $sHash;
        $config['login_pass_fp']   = $sFpNow;
    }
}

/**
 * Zwraca wartość jako bezpieczny literał łańcucha JS (z cudzysłowami).
 * Zapobiega wyjściu z kontekstu &lt;script&gt; (escapuje &lt;, &gt;, cudzysłowy, apostrofy).
 * @return string
 * @param mixed $mValue
 */
function adminJsStr( $mValue ){
    return json_encode( (string) $mValue, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Token CSRF panelu administracyjnego.
 * Wyprowadzany z sekretnego, losowego "session_key_name" (patrz core/common.php),
 * dzięki czemu jest nieprzewidywalny dla atakującego i unikatowy per instalacja.
 * @return string
 */
function getCsrfToken(){
    global $config;
    return hash( 'sha256', 'csrf|'.( $config['session_key_name'] ?? '' ) );
}

/**
 * Sprawdza token CSRF dla żądań zmieniających stan (POST / usuwanie).
 * Token przyjmowany z pola formularza, parametru GET lub nagłówka HTTP.
 * @return bool
 */
function checkCsrfToken(){
    $sToken = '';
    if( isset( $_POST['sTokenCsrf'] ) ){
        $sToken = (string) $_POST['sTokenCsrf'];
    }
    elseif( isset( $_GET['sTokenCsrf'] ) ){
        $sToken = (string) $_GET['sTokenCsrf'];
    }
    elseif( isset( $_SERVER['HTTP_X_CSRF_TOKEN'] ) ){
        $sToken = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    return ( $sToken !== '' && hash_equals( getCsrfToken(), $sToken ) );
}

/**
 * Update data in bin table
 * @return void
 * @param string $sKey
 * @param mixed  $mValue
 * @param bool   $bValueRaw
 */
function updateBin( $sKey, $mValue, $bValueRaw = null ){
    global $config;

    $oSql = Sql::getInstance();

    // Zachowujemy dotychczasową logikę (numeric/“raw” bez cudzysłowów)
    // ale robimy to bezpieczniej przez prepared statements.
    $value = ( isset( $bValueRaw ) ? $mValue : $mValue );

    if( isset( $config[$sKey] ) ){
        $stmt = $oSql->prepare( 'UPDATE bin SET sValue = :val WHERE sKey = :key' );
        $stmt->execute( array(
            ':val' => $value,
            ':key' => $sKey,
        ) );
    }
    else{
        $stmt = $oSql->prepare( 'INSERT INTO bin ("sKey","sValue") VALUES(:key,:val)' );
        $stmt->execute( array(
            ':key' => $sKey,
            ':val' => $value,
        ) );
    }
}

/**
 * Saves variables to config
 * @return void
 * @param array  $aForm
 * @param string $sFile
 * @param string $sVariable
 */
function saveVariables( $aForm, $sFile, $sVariable = 'config' ){
    if( is_file( $sFile ) && strstr( $sFile, '.php' ) ){
        $aFile  = file( $sFile );
        $iCount = count( $aFile );
        $rFile  = fopen( $sFile, 'w' );

        for( $i = 0; $i < $iCount; $i++ ){
            foreach( $aForm as $sKey => $sValue ){
                if( preg_match( '/'.$sVariable."\['".$sKey."'\]".' = /', $aFile[$i] ) && strstr( $aFile[$i], '=' ) ){
                    $mEndOfLine = strstr( $aFile[$i], '; //' );
                    if( empty( $mEndOfLine ) ){
                        $mEndOfLine = ';';
                    }

                    $sValue = changeSpecialChars( trim( str_replace(
                        array( '\\', '"', "\n", "\r" ),
                        array( '&#92;', '&quot;', '\n', '' ),
                        $sValue
                    ) ) );

                    if( preg_match( '/^(true|false|null)$/', $sValue ) ){
                        $aFile[$i] = "\$".$sVariable."['".$sKey."'] = ".$sValue.$mEndOfLine;
                    }
                    else{
                        $aFile[$i] = "\$".$sVariable."['".$sKey."'] = \"".$sValue."\"".$mEndOfLine;
                    }
                }
            }

            fwrite( $rFile, rtrim( $aFile[$i] ).( $iCount == ( $i + 1 ) ? null : "\r\n" ) );
        }

        fclose( $rFile );
    }
}

/**
 * Return themes select
 * @return string
 * @param int $iThemeSelect
 */
function getThemesSelect( $iThemeSelect ){
    global $config;

    $content = null;
    foreach( $config['themes'] as $iTheme => $sFile ){
        $content .= '<option value="'.$iTheme.'"'.( ( $iTheme == $iThemeSelect ) ? ' selected="selected"' : null ).'>'.$sFile.'</option>';
    }

    return '<option value="0"'.( ( isset( $iThemeSelect ) && $iThemeSelect == 0 ) ? ' selected="selected"' : null ).'>'.$GLOBALS['lang']['Inherit_from_parent'].'</option>'.$content;
}

/**
 * Return image thumbnails sizes select
 * @return string
 * @param int $iSizeSelect
 */
function getThumbnailsSelect( $iSizeSelect ){
    global $config;

    $content = null;
    foreach( $config['images_thumbnails'] as $iSize ){
        $content .= '<option value="'.$iSize.'"'.( ( $iSize == $iSizeSelect ) ? ' selected="selected" class="default"' : null ).'>'.$iSize.'</option>';
    }
    return $content;
}

/**
 * Clears cache from database/cache/
 * @return void
 * @param string $sName
 */
function clearCache( $sName = null ){
    global $config;

    foreach( new DirectoryIterator( $config['dir_database'].'cache/' ) as $oFileDir ){
        if( $oFileDir->isFile() && ( !isset( $sName ) || ( isset( $sName ) && strstr( $oFileDir->getFilename(), $sName ) ) ) ){
            unlink( $config['dir_database'].'cache/'.$oFileDir->getFilename() );
        }
    }
}

/**
 * List news from OpenSolution
 * @return void
 */
function listMessagesNews(){
    global $config;

    if( isset( $_COOKIE['iMessagesNewsTime'] ) && ( !isset( $_SESSION['iMessagesNewsTime'] ) || $_SESSION['iMessagesNewsTime'] != $_COOKIE['iMessagesNewsTime'] ) ){
        $_SESSION['iMessagesNewsTime'] = $_COOKIE['iMessagesNewsTime'];
        $_SESSION['iMessagesNewsNumber'] = 0;
    }

    if( isset( $_COOKIE['bMessagesNewsClear'] ) && isset( $_SESSION['sMessagesNews'] ) ){
        $_SESSION['sMessagesNews'] = str_replace( ' class="unread"', '', $_SESSION['sMessagesNews'] );
    }

    if( !isset( $_SESSION['sMessagesNews'] ) ){
        getSiteUrl();

        $content = @file_get_contents(
            'http://opensolution.org/list-messages.html?sLang='.$config['admin_lang'].
            '&sUrl='.$config['url_domain'].
            '&sScript=Quick.Cms&sVersion='.$config['version'].
            ( isset( $_COOKIE['iMessagesNewsTime'] ) ? '&iMessagesNewsTime='.$_COOKIE['iMessagesNewsTime'] : '' ).
            ( defined( 'DEVELOPER_MODE' ) ? '&amp;bDeveloper=' : '' )
        );

        if( !empty( $content ) && strstr( $content, ':"iMessagesNewsNew";' ) ){
            $aData = unserialize( $content );
            if( isset( $aData['sNews'] ) ){
                $_SESSION['sMessagesNews'] = $aData['sNews'];
                $_SESSION['iMessagesNewsNumber'] = $aData['iMessagesNewsNew'];
            }
        }
    }
}

/**
 * Lists notifications and alerts
 * @return string
 */
function listMessagesNotices(){
    global $lang, $config;

    if( !isset( $_SESSION['sMessagesNotices'] ) ){
        if( $config['failed_logs'] > 0 ){
            $aNotices[] = '<li>'.$lang['Failed_logs'].' <strong>'.displayDate( $config['failed_login_time'], $config['date_format_admin_default'] ).'</strong></li>';
        }

        if( strstr( $_SERVER['REQUEST_URI'] ?? '', 'admin.php' ) && !preg_match( '/localhost|192\.168\.|127\.0\.0\.1/', ( $_SERVER['HTTP_HOST'] ?? '' ).( $_SERVER['SERVER_ADDR'] ?? '' ) ) ){
            $aNotices[] = '<li>'.$lang['Increase_security'].' <a href="'.$config['manual_link'].'information#security" target="_blank">'.$lang['More'].' &raquo;</a></li>';
        }

        if( !defined( 'LICENSE_NO_LINK' ) && is_dir( 'templates/'.$config['skin'].'/' ) ){
            // OPTYMALIZACJA: Cache sprawdzania licencji (sprawdzaj raz na 24h zamiast przy każdym ładowaniu)
            $cacheFile = $config['dir_database'].'cache/license_check';
            $cacheValid = false;
            $licenseOk = false;

            if( is_file( $cacheFile ) ){
                $cacheData = @file_get_contents( $cacheFile );
                if( $cacheData !== false ){
                    $cacheInfo = @json_decode( $cacheData, true );
                    if( $cacheInfo && isset( $cacheInfo['timestamp'] ) && isset( $cacheInfo['result'] ) ){
                        // Cache ważny przez 24 godziny
                        if( ( time() - $cacheInfo['timestamp'] ) < 86400 ){
                            $cacheValid = true;
                            $licenseOk = $cacheInfo['result'];
                        }
                    }
                }
            }

            if( !$cacheValid ){
                // Wykonaj sprawdzenie tylko jeśli cache jest nieważny
                foreach( new DirectoryIterator( 'templates/'.$config['skin'].'/' ) as $oFileDir ){
                    if(
                        strstr( $oFileDir->getFilename(), '.php' ) &&
                        preg_match( '/http:\/\/opensolution\.org|http:\/\/www\.opensolution\.org/i', file_get_contents( 'templates/'.$config['skin'].'/'.$oFileDir->getFilename() ) )
                    ){
                        $licenseOk = true;
                        break;
                    }
                }

                // Zapisz wynik w cache
                $cacheData = json_encode( [
                    'timestamp' => time(),
                    'result' => $licenseOk
                ] );
                @file_put_contents( $cacheFile, $cacheData );
            }

            if( $licenseOk ){
                define( 'LICENSE_LINK_OK', true );
            } else {
                $aNotices[] = '<li>Restore link <strong>http://opensolution.org/</strong> located in the footer on your website <a href="http://opensolution.org/license.html" target="_blank">'.$lang['More'].' &raquo;</a></li>';
            }
        }

        if( is_file( 'index.php' ) && ( time() - filemtime( 'index.php' ) > 6480000 ) && ( isset( $aNotices ) || rand( 1, 3 ) == 2 ) ){
            $aNotices[] = '<li>'.$lang['Check_for_bug_fixes'].'</li>';
        }

        if( isset( $aNotices ) ){
            $_SESSION['sMessagesNotices'] = '<ul>'.implode( '', $aNotices ).'</ul>';
            $_SESSION['iMessagesNoticesNumber'] = count( $aNotices );
        }
    }
}

/**
 * Displays the lists of backup files
 * @return string
 */
function listPlugins(){
    global $lang, $config;

    if( !isset( $_SESSION['sPluginsList'.$config['version']] ) ){
        getSiteUrl();

        $sPlugins = @file_get_contents(
            'http://opensolution.org/plugins.html?sLang='.$config['admin_lang'].
            '&sUrl='.$config['url_domain'].
            '&sScript=Quick.Cms&sVersion='.$config['version'].
            ( defined( 'DEVELOPER_MODE' ) ? '&amp;bDeveloper=' : '' )
        );

        if( !empty( $sPlugins ) ){
            $_SESSION['sPluginsList'.$config['version']] = $sPlugins;
        }
    }

    if( isset( $_SESSION['sPluginsList'.$config['version']] ) ){
        return $_SESSION['sPluginsList'.$config['version']];
    }
}

/**
 * Function returns textarea field
 * @return string
 * @param  string $sName
 * @param  string $sContent
 * @param  array  $aParametersExt
 */
function getTextarea( $sName = 'sContent', $sContent = '', $aParametersExt = null ){
    global $config, $lang;

    $content = null;

    if( !isset( $aParametersExt['mWysiwyg'] ) ){
        $aParametersExt['mWysiwyg'] = $config['wysiwyg'];
    }

    if( !isset( $aParametersExt['sFunctionName'] ) && isset( $aParametersExt['mWysiwyg'] ) && $aParametersExt['mWysiwyg'] !== false ){
        $aParametersExt['sFunctionName'] = 'getWysiwyg'.$aParametersExt['mWysiwyg'];
    }

    if( isset( $aParametersExt['sFunctionName'] ) && !empty( $aParametersExt['sFunctionName'] ) ){
        if( function_exists( $aParametersExt['sFunctionName'] ) ){
            $content .= $aParametersExt['sFunctionName']( $sName, $aParametersExt );
        }
        else{
            return defined( 'DEVELOPER_MODE' ) ? '<p class="dev">THERE IS NO SUCH FUNCTION - '.$aParametersExt['sFunctionName'].'</p>' : null;
        }
    }

    $content .= '<textarea name="'.$sName.'" id="'.$sName.'" rows="20" cols="60" class="'.( isset( $aParametersExt['sClassName'] ) ? $aParametersExt['sClassName'] : 'text-editor' ).'" '.( isset( $aParametersExt['iTab'] ) ? ' tabindex="'.$aParametersExt['iTab'].'"' : null ).'>'.html( (string) $sContent ).'</textarea>';

    return $content;
}

?>
