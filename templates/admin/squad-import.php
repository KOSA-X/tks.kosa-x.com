<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

/*
 * TRANSMISJA LIVE — Import składu z protokołu meczowego.
 * Przepływ: upload zdjęcia + wybór drużyny → OCR (Anthropic vision,
 * plugins/live/ocr.php) → ekran korekty → zapis zawodników (następny krok).
 *
 * Drużyny = podstrony zakładki $config['teams_page'] (database/config_pl.php).
 * Wgrane protokoły i wyniki OCR ({token}.json) lądują w
 * plugins/live/cache/protocols/ (poza gitem, katalog zablokowany po HTTP) —
 * podgląd tylko przez ten moduł (admin).
 */

require_once 'plugins/live/ocr.php';

$sProtocolsDir = 'plugins/live/cache/protocols/';
$iTeamsPage    = (int) ( $config['teams_page'] ?? 0 );
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
// ZAPIS DO BAZY — celowo jeszcze NIE zaimplementowany (następny krok);
// stub przyjmuje POST z ekranu korekty i informuje o statusie
// ============================================================
if( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' && ( $_POST['sAction'] ?? '' ) === 'save' ){
    $sFile = (string) ( $_POST['sFile'] ?? '' );
    if( preg_match( $sFilePattern, $sFile ) ){
        header( 'Location: '.$config['admin_file'].'?p=squad-import&sOption=review&sFile='.$sFile.'&sNotice=save_pending' );
        exit;
    }
    header( 'Location: '.$config['admin_file'].'?p=squad-import' );
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
// WIDOK: EKRAN KOREKTY PO OCR
// ============================================================
$sReviewFile = (string) ( $_GET['sFile'] ?? '' );
$sReviewJson = preg_match( $sFilePattern, $sReviewFile )
    ? $sProtocolsDir.preg_replace( '/\.(jpg|jpeg|png|webp)$/', '.json', $sReviewFile )
    : '';

if( ( $_GET['sOption'] ?? '' ) === 'review' && $sReviewJson !== '' && is_file( $sReviewJson ) ){

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

    <?php if( ( $_GET['sNotice'] ?? '' ) === 'save_pending' ): ?>
        <div class="alert alert-danger mb-3">Zapis do bazy to następny krok wdrożenia — dane z formularza jeszcze nie są zapisywane.</div>
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
