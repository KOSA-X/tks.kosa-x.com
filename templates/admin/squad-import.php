<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

/*
 * TRANSMISJA LIVE — Import składu z protokołu meczowego.
 * Przepływ: upload zdjęcia + wybór drużyny → OCR (Anthropic vision,
 * plugins/live/ocr.php) → ekran korekty → zapis zawodników (następny krok).
 *
 * Drużyny = zakładki najwyższego poziomu z typem menu „Drużyny"
 * ($config['teams_menu']); zawodnicy = ich podstrony. Wgrane protokoły
 * i wyniki OCR ({token}.json) lądują w plugins/live/cache/protocols/
 * (poza gitem, katalog zablokowany po HTTP) — podgląd tylko przez ten
 * moduł (admin).
 */

require_once 'plugins/live/ocr.php';

$sProtocolsDir = 'plugins/live/cache/protocols/';
$iTeamsMenu    = (int) ( $config['teams_menu'] ?? 0 );
$sFilePattern  = '/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/';

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
// OBSŁUGA OCR — wysyłka protokołu do Anthropic API (vision)
// (CSRF sprawdzany globalnie w adm.php; żądanie może trwać do ~2 min)
// ============================================================
$sOcrError = '';

if( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' && ( $_POST['sAction'] ?? '' ) === 'ocr' ){

    $sFile = (string) ( $_POST['sFile'] ?? '' );
    $iTeam = (int) ( $_POST['iTeam'] ?? 0 );

    if( !preg_match( $sFilePattern, $sFile ) || !is_file( $sProtocolsDir.$sFile ) ){
        $sOcrError = 'Plik protokołu nie istnieje — wgraj go ponownie.';
    }
    else{
        @set_time_limit( 300 );
        $sTeamName = (string) ( getData( $iTeam, 'sName' ) ?? '' );
        $aOcr = liveOcrProtocol( $sProtocolsDir.$sFile, $sTeamName );

        if( $aOcr['ok'] ){
            $sJsonFile = preg_replace( '/\.(jpg|jpeg|png|webp)$/', '.json', $sFile );
            file_put_contents( $sProtocolsDir.$sJsonFile, json_encode( Array(
                'iTeam'   => $iTeam,
                'sFile'   => $sFile,
                'sDate'   => date( 'Y-m-d H:i:s' ),
                'players' => $aOcr['data']['players'],
                'staff'   => $aOcr['data']['staff'],
            ), JSON_UNESCAPED_UNICODE ) );

            header( 'Location: '.$config['admin_file'].'?p=squad-import&sOption=review&sFile='.$sFile );
            exit;
        }
        $sOcrError = $aOcr['error'];
    }
    // błąd → strona renderuje się dalej jako widok potwierdzenia (sOption=uploaded
    // w GET) z alertem $sOcrError
}

// ============================================================
// ZAPIS SKŁADU DO BAZY (z ekranu korekty)
// Zawodnik = podstrona drużyny (PagesAdmin::savePage, sNumber + sSquad).
// Dopasowanie po nazwisku — kolejny import AKTUALIZUJE zawodnika zamiast
// tworzyć duplikat. Zawodnicy drużyny nieobecni w protokole dostają
// sSquad = '' (poza kadrą meczową). Sztab → sDescriptionShort drużyny.
// ============================================================
if( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' && ( $_POST['sAction'] ?? '' ) === 'save' ){

    $sFile = (string) ( $_POST['sFile'] ?? '' );
    $iTeam = (int) ( $_POST['iTeam'] ?? 0 );

    // klucz dopasowania nazwiska: zbite spacje, odkodowane encje, lowercase
    $fNameKey = function( $sName ){
        return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', html_entity_decode( (string) $sName, ENT_QUOTES, 'UTF-8' ) ) ) );
    };

    // drużyna musi być zakładką najwyższego poziomu z typem menu „Drużyny"
    $bTeamOk = false;
    if( $iTeamsMenu > 0 && $iTeam > 0 ){
        $oCheck = $oSql->prepare( 'SELECT COUNT(*) FROM pages WHERE iPage = :id AND iMenu = :menu AND iPageParent = 0' );
        $oCheck->execute( Array( ':id' => $iTeam, ':menu' => $iTeamsMenu ) );
        $bTeamOk = ( (int) $oCheck->fetchColumn( ) > 0 );
    }

    // normalizacja wierszy z formularza (puste nazwiska odpadają)
    $aPlayers = Array( );
    foreach( (array) ( $_POST['aPlayers'] ?? Array( ) ) as $aRow ){
        $sName = trim( preg_replace( '/\s+/u', ' ', (string) ( $aRow['sName'] ?? '' ) ) );
        if( $sName === '' || !is_array( $aRow ) ){
            continue;
        }
        $iSquad = (int) ( $aRow['sSquad'] ?? 0 );
        $aPlayers[] = Array(
            'sNumber' => trim( (string) ( $aRow['sNumber'] ?? '' ) ),
            'sName'   => $sName,
            'sSquad'  => isset( $config['squad_types'][$iSquad] ) ? $iSquad : 2,
        );
    }

    $aStaff = Array( );
    foreach( (array) ( $_POST['aStaff'] ?? Array( ) ) as $aRow ){
        $sName = trim( preg_replace( '/\s+/u', ' ', (string) ( $aRow['sName'] ?? '' ) ) );
        if( $sName === '' || !is_array( $aRow ) ){
            continue;
        }
        $aStaff[] = Array(
            'sRole' => trim( (string) ( $aRow['sRole'] ?? '' ) ),
            'sName' => $sName,
        );
    }

    if( !$bTeamOk || !preg_match( $sFilePattern, $sFile ) ){
        header( 'Location: '.$config['admin_file'].'?p=squad-import' );
        exit;
    }
    if( empty( $aPlayers ) ){
        header( 'Location: '.$config['admin_file'].'?p=squad-import&sOption=review&sFile='.$sFile.'&sNotice=no_players' );
        exit;
    }

    // istniejący zawodnicy drużyny → mapa nazwisko → dane strony.
    // Opisy pobieramy, bo savePage ZAWSZE zapisuje sDescription* — bez
    // przekazania istniejących wartości aktualizacja wyczyściłaby opisy.
    $aExisting = Array( );
    $oQuery = $oSql->prepare( 'SELECT iPage, sName, sDescriptionShort, sDescriptionFull, sDescriptionMeta FROM pages WHERE iPageParent = :team' );
    $oQuery->execute( Array( ':team' => $iTeam ) );
    while( $aRow = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
        $sKey = $fNameKey( $aRow['sName'] );
        if( $sKey !== '' && !isset( $aExisting[$sKey] ) ){
            $aExisting[$sKey] = $aRow;
        }
    }

    $iCreated = 0;
    $iUpdated = 0;
    $aImportedIds = Array( );

    foreach( $aPlayers as $aPlayer ){
        $aForm = Array(
            'sName'             => $aPlayer['sName'],
            'sNumber'           => $aPlayer['sNumber'],
            'sSquad'            => (string) $aPlayer['sSquad'],
            'iPageParent'       => $iTeam, // bez tego savePage przepiąłby stronę do korzenia
            'iStatus'           => 1,
            'iPosition'         => (int) $aPlayer['sNumber'], // sortowanie listy wg numeru
            'sDescriptionShort' => '',
            'sDescriptionFull'  => '',
            'sDescriptionMeta'  => '',
        );

        $sKey = $fNameKey( $aPlayer['sName'] );
        if( isset( $aExisting[$sKey] ) ){
            $aForm['iPage']             = (int) $aExisting[$sKey]['iPage'];
            $aForm['sDescriptionShort'] = (string) $aExisting[$sKey]['sDescriptionShort'];
            $aForm['sDescriptionFull']  = (string) $aExisting[$sKey]['sDescriptionFull'];
            $aForm['sDescriptionMeta']  = (string) $aExisting[$sKey]['sDescriptionMeta'];
            unset( $aExisting[$sKey] ); // drugi taki sam wpis nie dopasuje się ponownie
            $iUpdated++;
        }
        else{
            $aForm['sDate'] = date( 'Y-m-d H:i' );
            $iCreated++;
        }

        $aImportedIds[] = (int) $oPage->savePage( $aForm );
    }

    // zawodnicy drużyny nieobecni w tym protokole → poza kadrą meczową
    $sIds = implode( ',', array_map( 'intval', $aImportedIds ) );
    $oReset = $oSql->prepare(
        'UPDATE pages SET sSquad = "" WHERE iPageParent = :team AND sSquad IS NOT NULL AND sSquad <> ""'
        .( $sIds !== '' ? ' AND iPage NOT IN ('.$sIds.')' : '' )
    );
    $oReset->execute( Array( ':team' => $iTeam ) );
    $iOff = (int) $oReset->rowCount( );

    // sztab szkoleniowy → blok .teamStaff w sDescriptionShort drużyny
    // (kolejny import PODMIENIA blok zamiast dopisywać kolejny)
    if( !empty( $aStaff ) ){
        $aStaffLines = Array( );
        foreach( $aStaff as $aMember ){
            $aStaffLines[] = ( $aMember['sRole'] !== '' ? html( $aMember['sRole'] ).': ' : '' ).html( $aMember['sName'] );
        }
        $sStaffHtml = '<div class="teamStaff"><p><strong>Sztab szkoleniowy:</strong><br />'.implode( '<br />', $aStaffLines ).'</p></div>';

        $oDesc = $oSql->prepare( 'SELECT sDescriptionShort FROM pages WHERE iPage = :id' );
        $oDesc->execute( Array( ':id' => $iTeam ) );
        $sDesc = (string) $oDesc->fetchColumn( );

        $iReplaced = 0;
        $sNewDesc = preg_replace( '#<div class="teamStaff">.*?</div>#s', $sStaffHtml, $sDesc, 1, $iReplaced );
        if( !$iReplaced ){
            $sNewDesc = ( trim( $sDesc ) !== '' ) ? $sDesc."\n".$sStaffHtml : $sStaffHtml;
        }

        $oUpd = $oSql->prepare( 'UPDATE pages SET sDescriptionShort = :desc WHERE iPage = :id' );
        $oUpd->execute( Array( ':desc' => $sNewDesc, ':id' => $iTeam ) );
    }

    // sprzątanie plików roboczych (zdjęcie protokołu = dane osobowe)
    @unlink( $sProtocolsDir.$sFile );
    @unlink( $sProtocolsDir.preg_replace( '/\.(jpg|jpeg|png|webp)$/', '.json', $sFile ) );

    header( 'Location: '.$config['admin_file'].'?p=squad-import&sOption=saved&iTeam='.$iTeam
        .'&iNew='.$iCreated.'&iUpd='.$iUpdated.'&iOff='.$iOff.'&iStaff='.count( $aStaff ) );
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

    // --- drużyna: istniejąca zakładka typu „Drużyny" albo nowa ---
    if( $iTeamsMenu === 0 ){
        $aErrors[] = 'Brak typu menu „Drużyny" — sprawdź $config[\'pages_menus\'] i $config[\'teams_menu\'] w database/config.php.';
    }
    elseif( $iTeam === -1 ){
        if( $sNewTeam === '' ){
            $aErrors[] = 'Podaj nazwę nowej drużyny.';
        }
    }
    else{
        $oCheck = $oSql->prepare( 'SELECT COUNT(*) FROM pages WHERE iPage = :id AND iMenu = :menu AND iPageParent = 0' );
        $oCheck->execute( Array( ':id' => $iTeam, ':menu' => $iTeamsMenu ) );
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

        // nowa drużyna → zakładka najwyższego poziomu z typem menu „Drużyny"
        // (iMenu przekazane wprost — przy iPageParent=0 savePage go nie nadpisuje)
        if( $iTeam === -1 ){
            $iTeam = (int) $oPage->savePage( Array(
                'sName'       => $sNewTeam,
                'iPageParent' => 0,
                'iMenu'       => $iTeamsMenu,
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

$sReviewFile = (string) ( $_GET['sFile'] ?? '' );
$sReviewJson = preg_match( $sFilePattern, $sReviewFile )
    ? $sProtocolsDir.preg_replace( '/\.(jpg|jpeg|png|webp)$/', '.json', $sReviewFile )
    : '';

// ============================================================
// WIDOK: PODSUMOWANIE PO ZAPISIE
// ============================================================
if( ( $_GET['sOption'] ?? '' ) === 'saved' && (int) ( $_GET['iTeam'] ?? 0 ) > 0 ){

    $iTeam     = (int) $_GET['iTeam'];
    $sTeamName = (string) ( getData( $iTeam, 'sName' ) ?? '' );
    $iNew      = (int) ( $_GET['iNew'] ?? 0 );
    $iUpd      = (int) ( $_GET['iUpd'] ?? 0 );
    $iOff      = (int) ( $_GET['iOff'] ?? 0 );
    $iStaffCnt = (int) ( $_GET['iStaff'] ?? 0 );

    // aktualna kadra drużyny: podstawowi → rezerwowi → poza kadrą
    $oSquad = $oSql->prepare(
        'SELECT iPage, sName, sNumber, sSquad, iStatus FROM pages WHERE iPageParent = :team
         ORDER BY CASE WHEN sSquad = "1" THEN 0 WHEN sSquad = "2" THEN 1 ELSE 2 END,
                  iPosition ASC, sName COLLATE NOCASE ASC'
    );
    $oSquad->execute( Array( ':team' => $iTeam ) );
    ?>

    <header class="mainPage__header">
        <h1 class="mainPage__title">Skład zapisany — <?php echo html( $sTeamName !== '' ? $sTeamName : ( 'ID '.$iTeam ) ); ?></h1>
    </header>

    <div class="alert alert-success mb-3">
        Zapisano: <strong><?php echo $iNew; ?></strong> nowych zawodników,
        <strong><?php echo $iUpd; ?></strong> zaktualizowanych,
        <strong><?php echo $iOff; ?></strong> przeniesionych poza kadrę meczową<?php
        echo $iStaffCnt > 0 ? ', sztab ('.$iStaffCnt.' os.) w opisie drużyny' : ''; ?>.
        Plik protokołu został usunięty z serwera.
    </div>

    <div class="card mb-4">
        <header class="card__header">
            <h4 class="card__title">Aktualna kadra</h4>
        </header>
        <div class="table-responsive">
            <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                <thead>
                    <tr>
                        <th style="width:90px">Numer</th>
                        <th>Imię i nazwisko</th>
                        <th style="width:220px">Skład</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while( $aRow = $oSquad->fetch( PDO::FETCH_ASSOC ) ): ?>
                    <tr<?php echo ( (string) $aRow['sSquad'] === '' ? ' class="muted"' : '' ); ?>>
                        <td><?php echo html( (string) $aRow['sNumber'] ); ?></td>
                        <th class="name"><a href="?p=pages-form&amp;iPage=<?php echo (int) $aRow['iPage']; ?>"><?php echo html( (string) $aRow['sName'] ); ?></a></th>
                        <td><?php echo ( (string) $aRow['sSquad'] !== '' && isset( $config['squad_types'][(int) $aRow['sSquad']] ) )
                            ? html( $config['squad_types'][(int) $aRow['sSquad']] )
                            : '&mdash; poza kadrą meczową'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="card__wrapper"><div class="card__content flex align-center">
            <a href="?p=squad-import" class="button main mr-2">Importuj kolejny protokół</a>
            <a href="?p=pages-form&amp;iPage=<?php echo $iTeam; ?>">Edytuj stronę drużyny</a>
        </div></div>
    </div>

    <?php
}
// ============================================================
// WIDOK: EKRAN KOREKTY PO OCR
// ============================================================
elseif( ( $_GET['sOption'] ?? '' ) === 'review' && $sReviewJson !== '' && is_file( $sReviewJson ) ){

    $aReview   = json_decode( file_get_contents( $sReviewJson ), true ) ?: Array( );
    $iTeam     = (int) ( $aReview['iTeam'] ?? 0 );
    $sTeamName = (string) ( getData( $iTeam, 'sName' ) ?? '' );
    $aPlayers  = (array) ( $aReview['players'] ?? Array( ) );
    $aStaff    = (array) ( $aReview['staff'] ?? Array( ) );

    // opcje selecta składu — słownik $config['squad_types']
    $fSquadSelect = function( $iSelected ) use ( $config ){
        $sOptions = '';
        foreach( $config['squad_types'] as $iKey => $sLabel ){
            $sOptions .= '<option value="'.$iKey.'"'.( (int) $iSelected === (int) $iKey ? ' selected="selected"' : '' ).'>'.html( $sLabel ).'</option>';
        }
        return $sOptions;
    };
    ?>

    <header class="mainPage__header">
        <h1 class="mainPage__title">Korekta składu — <?php echo html( $sTeamName !== '' ? $sTeamName : ( 'ID '.$iTeam ) ); ?></h1>
    </header>

    <div class="alert alert-success mb-3">
        Rozpoznano <strong><?php echo count( $aPlayers ); ?></strong> zawodników
        i <strong><?php echo count( $aStaff ); ?></strong> osób sztabu.
        <strong>Sprawdź i popraw dane przed zapisem</strong> — OCR może się mylić (numery, pisownia nazwisk).
    </div>

    <?php if( ( $_GET['sNotice'] ?? '' ) === 'no_players' ): ?>
        <div class="alert alert-danger mb-3">Nie zapisano — lista zawodników jest pusta. Dodaj przynajmniej jednego zawodnika.</div>
    <?php endif; ?>

    <form action="?p=squad-import" method="post" class="main-form" id="squad-review-form">

        <div class="card mb-4">
            <header class="card__header">
                <h4 class="card__title">Zawodnicy</h4>
            </header>
            <div class="table-responsive">
                <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                    <thead>
                        <tr>
                            <th style="width:90px">Numer</th>
                            <th>Imię i nazwisko</th>
                            <th style="width:180px">Skład</th>
                            <th style="width:70px"></th>
                        </tr>
                    </thead>
                    <tbody id="players-rows">
                        <?php foreach( $aPlayers as $i => $aPlayer ): ?>
                        <tr>
                            <td><input type="text" name="aPlayers[<?php echo $i; ?>][sNumber]" value="<?php echo html( (string) $aPlayer['number'] ); ?>" class="form-control" maxlength="4" /></td>
                            <td><input type="text" name="aPlayers[<?php echo $i; ?>][sName]" value="<?php echo html( (string) $aPlayer['name'] ); ?>" class="form-control" maxlength="120" /></td>
                            <td><select name="aPlayers[<?php echo $i; ?>][sSquad]" class="form-control"><?php echo $fSquadSelect( $aPlayer['squad'] ); ?></select></td>
                            <td><button type="button" class="button button-sm row-remove" title="Usuń wiersz">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card__wrapper"><div class="card__content">
                <button type="button" class="button" id="add-player">+ Dodaj zawodnika</button>
            </div></div>
        </div>

        <div class="card mb-4">
            <header class="card__header">
                <h4 class="card__title">Sztab szkoleniowy</h4>
            </header>
            <div class="table-responsive">
                <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                    <thead>
                        <tr>
                            <th style="width:250px">Funkcja</th>
                            <th>Imię i nazwisko</th>
                            <th style="width:70px"></th>
                        </tr>
                    </thead>
                    <tbody id="staff-rows">
                        <?php foreach( $aStaff as $i => $aMember ): ?>
                        <tr>
                            <td><input type="text" name="aStaff[<?php echo $i; ?>][sRole]" value="<?php echo html( (string) $aMember['role'] ); ?>" class="form-control" maxlength="80" /></td>
                            <td><input type="text" name="aStaff[<?php echo $i; ?>][sName]" value="<?php echo html( (string) $aMember['name'] ); ?>" class="form-control" maxlength="120" /></td>
                            <td><button type="button" class="button button-sm row-remove" title="Usuń wiersz">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card__wrapper"><div class="card__content">
                <button type="button" class="button" id="add-staff">+ Dodaj osobę sztabu</button>
            </div></div>
        </div>

        <div class="card mb-4">
            <header class="card__header">
                <h4 class="card__title">Protokół (oryginał)</h4>
            </header>
            <div class="card__wrapper"><div class="card__content">
                <img src="<?php echo $config['admin_file']; ?>?p=squad-import&amp;sAction=preview&amp;sFile=<?php echo html( $sReviewFile ); ?>"
                     alt="Protokół meczowy" style="max-width:100%;height:auto" />
            </div></div>
        </div>

        <input type="hidden" name="sAction" value="save" />
        <input type="hidden" name="sFile" value="<?php echo html( $sReviewFile ); ?>" />
        <input type="hidden" name="iTeam" value="<?php echo $iTeam; ?>" />
        <input type="hidden" name="sTokenCsrf" value="<?php echo html( getCsrfToken( ) ); ?>" />

        <div class="form-button flex align-center">
            <input type="submit" class="button main mr-2" value="Zapisz do bazy" />
            <a href="?p=squad-import">Wgraj inny protokół</a>
        </div>

    </form>

    <script>
        $( function( ){
            var iPlayerIdx = <?php echo count( $aPlayers ); ?>;
            var iStaffIdx  = <?php echo count( $aStaff ); ?>;
            var sSquadOptions = '<?php echo str_replace( "'", "\\'", str_replace( ' selected="selected"', '', $fSquadSelect( 0 ) ) ); ?>';

            $( '#add-player' ).on( 'click', function( ){
                $( '#players-rows' ).append(
                    '<tr>'
                    + '<td><input type="text" name="aPlayers[' + iPlayerIdx + '][sNumber]" class="form-control" maxlength="4" /></td>'
                    + '<td><input type="text" name="aPlayers[' + iPlayerIdx + '][sName]" class="form-control" maxlength="120" /></td>'
                    + '<td><select name="aPlayers[' + iPlayerIdx + '][sSquad]" class="form-control">' + sSquadOptions + '</select></td>'
                    + '<td><button type="button" class="button button-sm row-remove" title="Usuń wiersz">&times;</button></td>'
                    + '</tr>'
                );
                iPlayerIdx++;
            } );

            $( '#add-staff' ).on( 'click', function( ){
                $( '#staff-rows' ).append(
                    '<tr>'
                    + '<td><input type="text" name="aStaff[' + iStaffIdx + '][sRole]" class="form-control" maxlength="80" /></td>'
                    + '<td><input type="text" name="aStaff[' + iStaffIdx + '][sName]" class="form-control" maxlength="120" /></td>'
                    + '<td><button type="button" class="button button-sm row-remove" title="Usuń wiersz">&times;</button></td>'
                    + '</tr>'
                );
                iStaffIdx++;
            } );

            $( '#squad-review-form' ).on( 'click', '.row-remove', function( ){
                $( this ).closest( 'tr' ).remove( );
            } );
        } );
    </script>

    <?php
}
// ============================================================
// WIDOK: POTWIERDZENIE PO UPLOADZIE (+ start OCR)
// ============================================================
elseif( ( $_GET['sOption'] ?? '' ) === 'uploaded' && preg_match( $sFilePattern, (string) ( $_GET['sFile'] ?? '' ) ) ){

    $sFile     = (string) $_GET['sFile'];
    $iTeam     = (int) ( $_GET['iTeam'] ?? 0 );
    $sTeamName = (string) ( getData( $iTeam, 'sName' ) ?? '' );
    ?>

    <header class="mainPage__header">
        <h1 class="mainPage__title">Import składu — protokół wgrany</h1>
    </header>

    <?php if( $sOcrError !== '' ): ?>
        <div class="alert alert-danger mb-3"><?php echo html( $sOcrError ); ?></div>
    <?php endif; ?>

    <div class="alert alert-success mb-3">
        Protokół dla drużyny <strong><?php echo html( $sTeamName !== '' ? $sTeamName : ( 'ID '.$iTeam ) ); ?></strong> został wgrany.
        Kliknij „Rozpoznaj skład", żeby wysłać go do rozpoznania (OCR).
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
            <footer class="card__footer flex align-center">
                <form action="?p=squad-import&amp;sOption=uploaded&amp;sFile=<?php echo html( $sFile ); ?>&amp;iTeam=<?php echo $iTeam; ?>" method="post" class="mr-2" id="ocr-form">
                    <input type="hidden" name="sAction" value="ocr" />
                    <input type="hidden" name="sFile" value="<?php echo html( $sFile ); ?>" />
                    <input type="hidden" name="iTeam" value="<?php echo $iTeam; ?>" />
                    <input type="hidden" name="sTokenCsrf" value="<?php echo html( getCsrfToken( ) ); ?>" />
                    <input type="submit" class="button main" value="Rozpoznaj skład (OCR)"
                           onclick="this.value='Rozpoznawanie… (do 2 min)';this.disabled=true;this.form.submit();" />
                </form>
                <a href="?p=squad-import" class="button">Wgraj inny protokół</a>
            </footer>
        </div>
    </div>

    <?php
}
// ============================================================
// WIDOK: FORMULARZ UPLOADU
// ============================================================
else{

    // lista drużyn — zakładki najwyższego poziomu z typem menu „Drużyny"
    $sTeamsOptions = '';
    if( $iTeamsMenu > 0 ){
        $oTeams = $oSql->prepare( 'SELECT iPage, sName FROM pages WHERE iMenu = :menu AND iPageParent = 0 AND sLang = :lang ORDER BY iPosition ASC, sName COLLATE NOCASE ASC' );
        $oTeams->execute( Array( ':menu' => $iTeamsMenu, ':lang' => $config['language'] ) );
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
