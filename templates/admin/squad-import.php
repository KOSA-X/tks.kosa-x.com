<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

/*
 * TRANSMISJA LIVE — Import składu z protokołu meczowego.
 * Krok 1 (ten plik): upload zdjęcia protokołu + wybór drużyny.
 * Kolejne kroki (OCR przez Anthropic vision → ekran korekty → zapis zawodników
 * przez PagesAdmin::savePage) dochodzą w następnych etapach na tej bazie.
 *
 * Drużyny = podstrony zakładki $config['teams_page'] (database/config_pl.php).
 * Wgrane protokoły lądują w plugins/live/cache/protocols/ (poza gitem,
 * katalog zablokowany po HTTP) — podgląd tylko przez ten moduł (admin).
 */

$sProtocolsDir = 'plugins/live/cache/protocols/';
$iTeamsPage    = (int) ( $config['teams_page'] ?? 0 );

// ============================================================
// PODGLĄD WGRANEGO PROTOKOŁU (tylko zalogowany admin — loginActions()
// w adm.php kończy żądanie przed dojściem tutaj dla niezalogowanych)
// ============================================================
if( isset( $_GET['sAction'] ) && $_GET['sAction'] === 'preview' ){
    $sFile = (string) ( $_GET['sFile'] ?? '' );
    if( preg_match( '/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/', $sFile ) && is_file( $sProtocolsDir.$sFile ) ){
        $aTypes = Array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
        $sExt   = strtolower( pathinfo( $sFile, PATHINFO_EXTENSION ) );
        header( 'Content-Type: '.$aTypes[$sExt] );
        header( 'Content-Length: '.filesize( $sProtocolsDir.$sFile ) );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $sProtocolsDir.$sFile );
    }
    else{
        http_response_code( 404 );
    }
    exit;
}

// ============================================================
// OBSŁUGA UPLOADU (CSRF sprawdzany globalnie w adm.php)
// ============================================================
$aErrors  = Array( );
$iOldTeam = 0;
$sOldNew  = '';

if( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' && ( $_POST['sAction'] ?? '' ) === 'upload' ){

    $iTeam    = (int) ( $_POST['iTeam'] ?? 0 );
    $sNewTeam = trim( (string) ( $_POST['sNewTeam'] ?? '' ) );
    $iOldTeam = $iTeam;
    $sOldNew  = $sNewTeam;

    // --- drużyna: istniejąca podstrona „Drużyn" albo nowa ---
    if( $iTeamsPage === 0 ){
        $aErrors[] = 'Brak zakładki „Drużyny" — uruchom migrację: php database/migrations/2026-07-31-live-schema.php i ustaw $config[\'teams_page\'] w database/config_pl.php.';
    }
    elseif( $iTeam === -1 ){
        if( $sNewTeam === '' ){
            $aErrors[] = 'Podaj nazwę nowej drużyny.';
        }
    }
    else{
        $oCheck = $oSql->prepare( 'SELECT COUNT(*) FROM pages WHERE iPage = :id AND iPageParent = :parent' );
        $oCheck->execute( Array( ':id' => $iTeam, ':parent' => $iTeamsPage ) );
        if( (int) $oCheck->fetchColumn( ) === 0 ){
            $aErrors[] = 'Wybierz drużynę z listy.';
        }
    }

    // --- plik protokołu (JPG/PNG/WEBP, max 12 MB) ---
    $sSaveExt = null;
    $aUpload  = $_FILES['sProtocol'] ?? null;
    if( !isset( $aUpload['error'] ) || is_array( $aUpload['error'] ) || $aUpload['error'] !== UPLOAD_ERR_OK ){
        $aErrors[] = 'Wgraj zdjęcie protokołu meczowego (JPG).';
    }
    elseif( $aUpload['size'] > 12 * 1024 * 1024 ){
        $aErrors[] = 'Zdjęcie jest za duże (limit 12 MB).';
    }
    else{
        $aImageInfo = @getimagesize( $aUpload['tmp_name'] );
        $aAllowed   = Array( IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp' );
        if( $aImageInfo === false || !isset( $aAllowed[$aImageInfo[2]] ) ){
            $aErrors[] = 'Nieprawidłowy plik — dozwolone formaty: JPG, PNG, WEBP.';
        }
        else{
            $sSaveExt = $aAllowed[$aImageInfo[2]];
        }
    }

    if( empty( $aErrors ) ){

        // nowa drużyna → zwykła podstrona „Drużyn" (dziedziczy iMenu=3 po rodzicu)
        if( $iTeam === -1 ){
            $iTeam = (int) $oPage->savePage( Array(
                'sName'       => $sNewTeam,
                'iPageParent' => $iTeamsPage,
                'iStatus'     => 1,
                'iPosition'   => 0,
                'sDate'       => date( 'Y-m-d H:i' ),
            ) );
        }

        if( !is_dir( $sProtocolsDir ) ){
            mkdir( $sProtocolsDir, 0755, true );
        }

        $sFileName = bin2hex( random_bytes( 16 ) ).'.'.$sSaveExt;
        if( move_uploaded_file( $aUpload['tmp_name'], $sProtocolsDir.$sFileName ) ){
            header( 'Location: '.$config['admin_file'].'?p=squad-import&sOption=uploaded&sFile='.$sFileName.'&iTeam='.$iTeam );
            exit;
        }
        $aErrors[] = 'Nie udało się zapisać pliku na serwerze (uprawnienia katalogu '.$sProtocolsDir.').';
    }
}

$sSelectedMenu = 'squad-import';

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

// ============================================================
// WIDOK: POTWIERDZENIE PO UPLOADZIE
// ============================================================
if( ( $_GET['sOption'] ?? '' ) === 'uploaded' && preg_match( '/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/', (string) ( $_GET['sFile'] ?? '' ) ) ){

    $sFile     = (string) $_GET['sFile'];
    $iTeam     = (int) ( $_GET['iTeam'] ?? 0 );
    $sTeamName = (string) ( getData( $iTeam, 'sName' ) ?? '' );
    ?>

    <header class="mainPage__header">
        <h1 class="mainPage__title">Import składu — protokół wgrany</h1>
    </header>

    <div class="alert alert-success mb-3">
        Protokół dla drużyny <strong><?php echo html( $sTeamName !== '' ? $sTeamName : ( 'ID '.$iTeam ) ); ?></strong> został wgrany.
        Rozpoznawanie składu (OCR) i ekran korekty — w następnym etapie.
    </div>

    <div class="card mb-4">
        <header class="card__header">
            <h4 class="card__title">Podgląd protokołu</h4>
        </header>
        <div class="card__wrapper">
            <div class="card__content">
                <img src="<?php echo $config['admin_file']; ?>?p=squad-import&amp;sAction=preview&amp;sFile=<?php echo html( $sFile ); ?>"
                     alt="Protokół meczowy" style="max-width:100%;height:auto" />
            </div>
            <footer class="card__footer">
                <a href="?p=squad-import" class="button main">Wgraj kolejny protokół</a>
            </footer>
        </div>
    </div>

    <?php
}
// ============================================================
// WIDOK: FORMULARZ UPLOADU
// ============================================================
else{

    // lista drużyn — podstrony zakładki „Drużyny"
    $sTeamsOptions = '';
    if( $iTeamsPage > 0 ){
        $oTeams = $oSql->prepare( 'SELECT iPage, sName FROM pages WHERE iPageParent = :parent AND sLang = :lang ORDER BY iPosition ASC, sName COLLATE NOCASE ASC' );
        $oTeams->execute( Array( ':parent' => $iTeamsPage, ':lang' => $config['language'] ) );
        while( $aTeam = $oTeams->fetch( PDO::FETCH_ASSOC ) ){
            $sTeamsOptions .= '<option value="'.(int) $aTeam['iPage'].'"'.( $iOldTeam === (int) $aTeam['iPage'] ? ' selected="selected"' : '' ).'>'.html( $aTeam['sName'] ).'</option>';
        }
    }
    ?>

    <header class="mainPage__header">
        <h1 class="mainPage__title">Import składu z protokołu</h1>
    </header>

    <?php foreach( $aErrors as $sError ): ?>
        <div class="alert alert-danger mb-3"><?php echo html( $sError ); ?></div>
    <?php endforeach; ?>

    <div class="card mb-4">
        <header class="card__header">
            <h4 class="card__title">Wgraj zdjęcie protokołu meczowego</h4>
        </header>
        <div class="card__wrapper">
            <div class="card__content">

                <form action="?p=squad-import" method="post" enctype="multipart/form-data" class="main-form" id="squad-import-form">

                    <div class="form-item">
                        <label for="iTeam" class="form-label mb-1">Drużyna</label>
                        <select name="iTeam" id="iTeam" class="form-control">
                            <option value="0">- wybierz -</option>
                            <?php echo $sTeamsOptions; ?>
                            <option value="-1"<?php echo ( $iOldTeam === -1 ? ' selected="selected"' : '' ); ?>>+ Nowa drużyna…</option>
                        </select>
                    </div>

                    <div class="form-item" id="new-team-item" style="display:none">
                        <label for="sNewTeam" class="form-label mb-1">Nazwa nowej drużyny</label>
                        <input type="text" name="sNewTeam" id="sNewTeam" class="form-control"
                               value="<?php echo html( $sOldNew ); ?>" placeholder="np. Avia Świdnik" maxlength="120" />
                    </div>

                    <div class="form-item">
                        <label for="sProtocol" class="form-label mb-1">Zdjęcie protokołu (JPG, PNG lub WEBP, max 12 MB)</label>
                        <input type="file" name="sProtocol" id="sProtocol" class="form-control"
                               accept="image/jpeg,image/png,image/webp" />
                    </div>

                    <input type="hidden" name="sAction" value="upload" />
                    <input type="hidden" name="sTokenCsrf" value="<?php echo html( getCsrfToken( ) ); ?>" />

                    <div class="form-button">
                        <input type="submit" class="button main" value="Wgraj protokół" />
                    </div>

                </form>

                <script>
                    $( function( ){
                        var toggleNewTeam = function( ){
                            $( '#new-team-item' ).toggle( $( '#iTeam' ).val( ) === '-1' );
                        };
                        $( '#iTeam' ).on( 'change', toggleNewTeam );
                        toggleNewTeam( );
                    } );
                </script>

            </div>
        </div>
    </div>

    <?php
}

require_once 'templates/admin/_footer.php';
?>
