<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

/*
 * TRANSMISJA LIVE — Drużyny i kadry (panel admina).
 *
 * ?p=teams                      — lista drużyn (zakładki typu menu „Drużyny")
 * ?p=teams&sOption=new-player   — szybkie dodanie zawodnika (tytuł, drużyna,
 *                                 numer, skład meczowy, pozycja Skład 3D);
 *                                 zdjęcie miniaturki → edycja strony zawodnika
 * ?p=teams&iTeam=X              — kadra drużyny: numer na koszulce (input) +
 *                                 skład jednym kliknięciem (11 / Rezerwa / Poza)
 *                                 i ZBIORCZY zapis — jak pozycje w Liście stron.
 *
 * Nowe DRUŻYNY dodaje się przez Strony → Nowa strona (pełny formularz
 * z logo i opisem) z typem menu „Drużyny" — tu celowo nie ma skrótu.
 * Zapis kadry celowanym UPDATE (sNumber/sSquad/sLineup/iPosition) — nie
 * rusza opisów ani innych pól zawodnika. CSRF sprawdza globalnie adm.php.
 */

$iTeamsMenu = (int) ( $config['teams_menu'] ?? 0 );
$iTeam      = (int) ( $_GET['iTeam'] ?? 0 );

// sortowanie pozycyjne kadry (BR → OBR → POM → ATAK) — helpery modułu live
require_once 'plugins/live/view-helpers.php';

// ============================================================
// ZAPIS KADRY (zbiorczy: numery + składy)
// ============================================================
if( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' && ( $_POST['sAction'] ?? '' ) === 'squad_save' ){

    $iTeam    = (int) ( $_POST['iTeam'] ?? 0 );
    $aNumbers = (array) ( $_POST['aNumbers'] ?? Array( ) );
    $aSquads  = (array) ( $_POST['aSquads'] ?? Array( ) );
    $aLineup  = (array) ( $_POST['aLineup'] ?? Array( ) );

    // ustawienie taktyczne drużyny (plansza Skład 3D) — tylko znana formacja
    $sFormation = (string) ( $_POST['sFormation'] ?? '' );
    if( $sFormation !== '' && !isset( $config['live_formations'][$sFormation] ) ){
        $sFormation = '';
    }
    $oFormation = $oSql->prepare(
        'UPDATE pages SET sFormation = :formation WHERE iPage = :id AND iMenu = :menu AND iPageParent = 0'
    );
    $oFormation->execute( Array( ':formation' => $sFormation, ':id' => $iTeam, ':menu' => $iTeamsMenu ) );

    // tylko realni zawodnicy tej drużyny (obrona przed podmianą iPage w POST)
    $aAllowed = Array( );
    $oQuery = $oSql->prepare( 'SELECT iPage FROM pages WHERE iPageParent = :team' );
    $oQuery->execute( Array( ':team' => $iTeam ) );
    while( $iPage = $oQuery->fetchColumn( ) ){
        $aAllowed[(int) $iPage] = true;
    }

    $oUpdate = $oSql->prepare(
        'UPDATE pages SET sNumber = :number, sSquad = :squad, sLineup = :lineup, iPosition = :position WHERE iPage = :id'
    );
    foreach( $aNumbers as $iPage => $sNumber ){
        $iPage = (int) $iPage;
        if( !isset( $aAllowed[$iPage] ) ){
            continue;
        }
        $sNumber = trim( (string) $sNumber );
        $iSquad  = (int) ( $aSquads[$iPage] ?? 0 );
        $iSlot   = (int) ( $aLineup[$iPage] ?? 0 ); // pozycja na boisku: 1-11, 0 = brak
        $oUpdate->execute( Array(
            ':number'   => $sNumber,
            ':squad'    => isset( $config['squad_types'][$iSquad] ) ? (string) $iSquad : '',
            ':lineup'   => ( $iSlot >= 1 && $iSlot <= 11 ) ? (string) $iSlot : '',
            ':position' => (int) $sNumber, // sortowanie list wg numeru (jak importer)
            ':id'       => $iPage,
        ) );
    }
    clearCache( );

    header( 'Location: '.$config['admin_file'].'?p=teams&iTeam='.$iTeam.'&sOption=save' );
    exit;
}

// ============================================================
// NOWY ZAWODNIK (szybki formularz — podstrona wybranej drużyny)
// ============================================================
$sNewPlayerError = '';
if( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' && ( $_POST['sAction'] ?? '' ) === 'player_add' ){

    $sPlayerName = trim( preg_replace( '/\s+/u', ' ', (string) ( $_POST['sName'] ?? '' ) ) );
    $iTeam       = (int) ( $_POST['iTeam'] ?? 0 );
    $sNumber     = trim( (string) ( $_POST['sNumber'] ?? '' ) );
    $iSquad      = (int) ( $_POST['sSquad'] ?? 0 );
    $iSlot       = (int) ( $_POST['sLineup'] ?? 0 );

    $oCheck = $oSql->prepare( 'SELECT COUNT(*) FROM pages WHERE iPage = :id AND iMenu = :menu AND iPageParent = 0' );
    $oCheck->execute( Array( ':id' => $iTeam, ':menu' => $iTeamsMenu ) );

    if( $sPlayerName === '' ){
        $sNewPlayerError = 'Podaj imię i nazwisko zawodnika.';
    }
    elseif( !$oCheck->fetchColumn( ) ){
        $sNewPlayerError = 'Wybierz drużynę z listy.';
    }
    else{
        // savePage: iMenu dziedziczony po rodzicu; opisy przekazane puste,
        // bo savePage zawsze je zapisuje; sLineup przechodzi jak sNumber/sSquad
        $oPage->savePage( Array(
            'sName'             => $sPlayerName,
            'iPageParent'       => $iTeam,
            'iStatus'           => 1,
            'sNumber'           => $sNumber,
            'sSquad'            => isset( $config['squad_types'][$iSquad] ) ? (string) $iSquad : '',
            'sLineup'           => ( $iSlot >= 1 && $iSlot <= 11 ) ? (string) $iSlot : '',
            'iPosition'         => (int) $sNumber, // sortowanie list wg numeru (jak importer)
            'sDescriptionShort' => '',
            'sDescriptionFull'  => '',
            'sDescriptionMeta'  => '',
            'sDate'             => date( 'Y-m-d H:i' ),
        ) );
        header( 'Location: '.$config['admin_file'].'?p=teams&iTeam='.$iTeam.'&sOption=save' );
        exit;
    }
}

$sSelectedMenu = 'teams';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

// etykiety slotów 1-11 wg wybranej formacji: 1 = BR, dalej człony nazwy
// (pierwszy = OBR, ostatni = ATAK, środkowe = POM) — czysto opisowe;
// używane w kadrze i w formularzu nowego zawodnika
$fLineupLabels = function( $sFormation ) use ( $config ){
    $aLabels = Array( 1 => '1 — BR' );
    $iSlot = 2;
    if( $sFormation !== '' && isset( $config['live_formations'][$sFormation] ) ){
        $aSegments = array_map( 'intval', explode( '-', $sFormation ) );
        foreach( $aSegments as $iIndex => $iCount ){
            $sLine = $iIndex === 0 ? 'OBR' : ( $iIndex === count( $aSegments ) - 1 ? 'ATAK' : 'POM' );
            for( $i = 0; $i < $iCount && $iSlot <= 11; $i++ ){
                $aLabels[$iSlot] = $iSlot.' — '.$sLine;
                $iSlot++;
            }
        }
    }
    for( ; $iSlot <= 11; $iSlot++ ){
        $aLabels[$iSlot] = (string) $iSlot;
    }
    return $aLabels;
};

// ============================================================
// WIDOK: KADRA DRUŻYNY
// ============================================================
// (?iTeam w adresie new-player to tylko preselekcja drużyny w formularzu)
if( $iTeam > 0 && ( $_GET['sOption'] ?? '' ) !== 'new-player' ){

    $oCheck = $oSql->prepare( 'SELECT sName, sFormation FROM pages WHERE iPage = :id AND iMenu = :menu AND iPageParent = 0' );
    $oCheck->execute( Array( ':id' => $iTeam, ':menu' => $iTeamsMenu ) );
    $aTeamRow       = $oCheck->fetch( PDO::FETCH_ASSOC ) ?: Array( );
    $sTeamName      = (string) ( $aTeamRow['sName'] ?? '' );
    $sTeamFormation = (string) ( $aTeamRow['sFormation'] ?? '' );

    if( $sTeamName === '' ){
        echo '<div class="alert alert-danger">Nie ma takiej drużyny.</div>';
        require_once 'templates/admin/_footer.php';
        exit;
    }

    // + miniaturka: pierwsze/domyślne zdjęcie podstrony zawodnika (files/500)
    $oPlayers = $oSql->prepare(
        'SELECT iPage, sName, sNumber, sSquad, sLineup, iStatus,
                ( SELECT sFileName FROM files f WHERE f.iPage = pages.iPage AND f.iSize > 0
                  ORDER BY f.iDefault DESC, f.iPosition ASC LIMIT 1 ) AS sPhoto
         FROM pages WHERE iPageParent = :team
         ORDER BY CASE WHEN sSquad = "1" THEN 0 WHEN sSquad = "2" THEN 1 ELSE 2 END,
                  iPosition ASC, sName COLLATE NOCASE ASC'
    );
    $oPlayers->execute( Array( ':team' => $iTeam ) );
    $aPlayers = $oPlayers->fetchAll( PDO::FETCH_ASSOC );

    // porządek pozycyjny WEWNĄTRZ grup składu: BR → OBR → POM → ATAK
    // (linia ze slotu sLineup + formacji), w linii wg numeru na koszulce
    $aBySquad = Array( '1' => Array( ), '2' => Array( ), '' => Array( ) );
    foreach( $aPlayers as $aPlayer ){
        $sKey = in_array( (string) $aPlayer['sSquad'], Array( '1', '2' ), true ) ? (string) $aPlayer['sSquad'] : '';
        $aBySquad[$sKey][] = $aPlayer;
    }
    $aPlayers = array_merge(
        liveSortPlayers( $aBySquad['1'], $sTeamFormation ),
        liveSortPlayers( $aBySquad['2'], $sTeamFormation ),
        liveSortPlayers( $aBySquad[''], $sTeamFormation )
    );

    $aSquadButtons = Array( '1' => '11', '2' => 'Rezerwa', '' => 'Poza kadrą' );
    $aLineupLabels = $fLineupLabels( $sTeamFormation );
    ?>

    <style>
        /* przełącznik składu — jedno kliknięcie ustawia stan, zapis zbiorczy */
        .teamSquadToggle { display: inline-flex; gap: 6px; }
        .teamSquadToggle .button { min-height: 34px; padding: 4px 14px; opacity: .55; }
        .teamSquadToggle .button.is-active { opacity: 1; background: var(--brand, #105585); border-color: var(--brand, #105585); color: #fff; }
        .teamNumberInput { width: 70px; text-align: center; }
        .teamPlayerThumb { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 10px; }
        tr.squad-off .name a { opacity: .55; }
    </style>

    <form action="?p=teams&amp;iTeam=<?php echo $iTeam; ?>" name="form" method="post" class="main-form">

        <header class="mainPage__header mainPage__header_row">
            <h1 class="mainPage__title">Kadra — <?php echo html( $sTeamName ); ?></h1>
            <div class="mainPage__buttons d-flex justify-content-between">
                <a href="?p=teams" class="button button-light mr-2">Wróć do drużyn</a>
                <input type="submit" name="sOption" class="button" value="<?php echo html( $lang['save'] ); ?>" />
            </div>
        </header>

        <?php if( isset( $_GET['sOption'] ) ): ?>
            <div class="alert alert-success mb-3"><?php echo $lang['Operation_completed']; ?></div>
        <?php endif; ?>

        <div class="mb-4">
                <div class="form-item" >
                    <label for="sFormation">Ustawienie taktyczne</label>
                    <select name="sFormation" id="sFormation" class="adv-select-none">
                        <option value="">— brak —</option>
                        <?php foreach( array_keys( $config['live_formations'] ) as $sFormationKey ): ?>
                            <option value="<?php echo html( $sFormationKey ); ?>"<?php echo $sTeamFormation === $sFormationKey ? ' selected="selected"' : ''; ?>><?php echo html( $sFormationKey ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="mb-0" style="opacity:.7">Po zmianie formacji zapisz — etykiety pozycji (OBR/POM/ATAK) w tabeli dopasują się do nowego ustawienia.</p>
        </div>

        <?php if( empty( $aPlayers ) ): ?>
            <div class="alert alert-info mb-3">Brak zawodników — dodaj ich przez Import składu (Transmisja) albo jako podstrony drużyny.</div>
        <?php else: ?>

        <div class=" mb-4">
            <div class="table-responsive">
                <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                    <thead>
                        <tr>
                            <th style="width:100px">Numer</th>
                            <th>Imię i nazwisko</th>
                            <th style="width:320px">Skład meczowy</th>
                            <th style="width:170px">Pozycja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach( $aPlayers as $aPlayer ):
                            $sSquad  = in_array( (string) $aPlayer['sSquad'], Array( '1', '2' ), true ) ? (string) $aPlayer['sSquad'] : '';
                            $iLineup = (int) $aPlayer['sLineup'];
                        ?>
                        <tr<?php echo $sSquad === '' ? ' class="squad-off"' : ''; ?>>
                            <td>
                                <input type="text" name="aNumbers[<?php echo (int) $aPlayer['iPage']; ?>]"
                                       value="<?php echo html( (string) $aPlayer['sNumber'] ); ?>"
                                       class="form-control teamNumberInput" inputmode="numeric" maxlength="4" />
                            </td>
                            <th class="name">
                               <div class="flex align-center">
                                <?php if( (string) ( $aPlayer['sPhoto'] ?? '' ) !== '' ): ?>
                                    <img class="teamPlayerThumb" src="files/500/<?php echo html( (string) $aPlayer['sPhoto'] ); ?>" alt="" />
                                <?php endif; ?>
                                <a href="?p=pages-form&amp;iPage=<?php echo (int) $aPlayer['iPage']; ?>"><?php echo html( (string) $aPlayer['sName'] ); ?></a>
                                </div>
                            </th>
                            <td>
                                <span class="teamSquadToggle" data-player="<?php echo (int) $aPlayer['iPage']; ?>">
                                    <?php foreach( $aSquadButtons as $sValue => $sLabel ): ?>
                                        <button type="button" class="button button-sm<?php echo $sSquad === (string) $sValue ? ' is-active' : ''; ?>"
                                                data-squad="<?php echo html( (string) $sValue ); ?>"><?php echo html( $sLabel ); ?></button>
                                    <?php endforeach; ?>
                                    <input type="hidden" name="aSquads[<?php echo (int) $aPlayer['iPage']; ?>]" value="<?php echo html( $sSquad ); ?>" />
                                </span>
                            </td>
                            <td>
                                <select name="aLineup[<?php echo (int) $aPlayer['iPage']; ?>]" class="teamLineupSelect adv-select-none">
                                    <option value="0">—</option>
                                    <?php foreach( $aLineupLabels as $iSlot => $sSlotLabel ): ?>
                                        <option value="<?php echo $iSlot; ?>"<?php echo $iLineup === $iSlot ? ' selected="selected"' : ''; ?>><?php echo html( $sSlotLabel ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card__wrapper"><div class="card__content flex align-center">
                <input type="submit" name="sOption" class="button mr-2" value="<?php echo html( $lang['save'] ); ?>" />
                <a href="?p=squad-import">Importuj skład z protokołu</a>
            </div></div>
        </div>

        <?php endif; ?>

        <input type="hidden" name="sAction" value="squad_save" />
        <input type="hidden" name="iTeam" value="<?php echo $iTeam; ?>" />
        <input type="hidden" name="sTokenCsrf" value="<?php echo html( getCsrfToken( ) ); ?>" />

    </form>

    <script>
        $( function( ){
            // klik = ustawienie składu zawodnika (zapis dopiero przyciskiem Zapisz)
            $( '.teamSquadToggle .button' ).on( 'click', function( ){
                var $toggle = $( this ).closest( '.teamSquadToggle' );
                $toggle.find( '.button' ).removeClass( 'is-active' );
                $( this ).addClass( 'is-active' );
                $toggle.find( 'input[type=hidden]' ).val( $( this ).data( 'squad' ) );
                $toggle.closest( 'tr' ).toggleClass( 'squad-off', $( this ).data( 'squad' ) === '' );
            } );

            // pozycja na boisku jest unikatowa — wybór slotu zdejmuje go
            // z zawodnika, który miał go do tej pory
            $( '.teamLineupSelect' ).on( 'change', function( ){
                var sSlot = $( this ).val( );
                if( sSlot === '0' ){
                    return;
                }
                $( '.teamLineupSelect' ).not( this ).each( function( ){
                    if( $( this ).val( ) === sSlot ){
                        $( this ).val( '0' );
                    }
                } );
            } );
        } );
    </script>

    <?php
}
// ============================================================
// WIDOK: NOWY ZAWODNIK
// ============================================================
elseif( ( $_GET['sOption'] ?? '' ) === 'new-player' ){

    // drużyny + formacje → etykiety pozycji podmieniane przy zmianie drużyny
    $oTeams = $oSql->prepare(
        'SELECT iPage, sName, sFormation FROM pages WHERE iMenu = :menu AND iPageParent = 0
         ORDER BY iPosition ASC, sName COLLATE NOCASE ASC'
    );
    $oTeams->execute( Array( ':menu' => $iTeamsMenu ) );
    $aTeams = $oTeams->fetchAll( PDO::FETCH_ASSOC );

    $aTeamLabels = Array( );
    foreach( $aTeams as $aTeam ){
        $aTeamLabels[(int) $aTeam['iPage']] = $fLineupLabels( (string) $aTeam['sFormation'] );
    }

    // sticky po błędzie walidacji; preselekcja drużyny z ?iTeam= (przycisk w kadrze)
    $iSelTeam   = (int) ( $_POST['iTeam'] ?? ( $_GET['iTeam'] ?? 0 ) );
    $sSelName   = (string) ( $_POST['sName'] ?? '' );
    $sSelNumber = (string) ( $_POST['sNumber'] ?? '' );
    $sSelSquad  = (string) ( $_POST['sSquad'] ?? '1' );
    $iSelSlot   = (int) ( $_POST['sLineup'] ?? 0 );
    $aSelLabels = $aTeamLabels[$iSelTeam] ?? $fLineupLabels( '' );
    ?>

    <header class="mainPage__header">
        <h1 class="mainPage__title">Nowy zawodnik</h1>
    </header>

    <?php if( $sNewPlayerError !== '' ): ?>
        <div class="alert alert-danger mb-3"><?php echo html( $sNewPlayerError ); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card__wrapper"><div class="card__content">
            <form action="?p=teams&amp;sOption=new-player" method="post" class="main-form">

                <div class="form-item">
                    <label for="sName">Tytuł (imię i nazwisko)</label>
                    <input type="text" name="sName" id="sName" class="form-control" maxlength="120"
                           value="<?php echo html( $sSelName ); ?>" placeholder="np. Jan Kowalski" />
                </div>

                <div class="form-item">
                    <label for="iTeam">Przypisz do</label>
                    <select name="iTeam" id="iTeam" class="form-control adv-select-none">
                        <option value="0">— wybierz drużynę —</option>
                        <?php foreach( $aTeams as $aTeam ): ?>
                            <option value="<?php echo (int) $aTeam['iPage']; ?>"<?php echo $iSelTeam === (int) $aTeam['iPage'] ? ' selected="selected"' : ''; ?>><?php echo html( (string) $aTeam['sName'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-item">
                    <label for="sNumber">Numer na koszulce</label>
                    <input type="text" name="sNumber" id="sNumber" class="form-control" style="max-width:120px"
                           inputmode="numeric" maxlength="4" value="<?php echo html( $sSelNumber ); ?>" />
                </div>

                <div class="form-item">
                    <label for="sSquad">Skład meczowy</label>
                    <select name="sSquad" id="sSquad" class="form-control adv-select-none" style="max-width:240px">
                        <option value=""<?php echo $sSelSquad === '' ? ' selected="selected"' : ''; ?>>Poza kadrą</option>
                        <?php foreach( $config['squad_types'] as $iValue => $sLabel ): ?>
                            <option value="<?php echo (int) $iValue; ?>"<?php echo $sSelSquad === (string) $iValue ? ' selected="selected"' : ''; ?>><?php echo html( $sLabel ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-item">
                    <label for="sLineup">Pozycja (Skład 3D)</label>
                    <select name="sLineup" id="sLineup" class="form-control adv-select-none" style="max-width:240px">
                        <option value="0">—</option>
                        <?php foreach( $aSelLabels as $iSlot => $sSlotLabel ): ?>
                            <option value="<?php echo $iSlot; ?>"<?php echo $iSelSlot === $iSlot ? ' selected="selected"' : ''; ?>><?php echo html( $sSlotLabel ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <p class="mb-0" style="opacity:.7">Zdjęcie (miniaturkę) zawodnika dodasz po zapisaniu — w kadrze kliknij
                nazwisko, otworzy się pełna edycja strony zawodnika ze zdjęciami.</p>

                <input type="hidden" name="sAction" value="player_add" />
                <input type="hidden" name="sTokenCsrf" value="<?php echo html( getCsrfToken( ) ); ?>" />
                <div class="form-button mt-2">
                    <input type="submit" class="button" value="<?php echo html( $lang['save'] ); ?>" />
                </div>
            </form>
        </div></div>
    </div>

    <script>
        $( function( ){
            // etykiety pozycji (BR/OBR/POM/ATAK) podążają za formacją
            // wybranej drużyny — mapa drużyna → etykiety zbudowana w PHP
            var aTeamLabels = <?php echo json_encode( $aTeamLabels, JSON_UNESCAPED_UNICODE ); ?>;
            $( '#iTeam' ).on( 'change', function( ){
                var aLabels = aTeamLabels[$( this ).val( )];
                if( !aLabels ){
                    return;
                }
                $( '#sLineup option' ).each( function( ){
                    var sSlot = $( this ).val( );
                    if( sSlot !== '0' && aLabels[sSlot] ){
                        $( this ).text( aLabels[sSlot] );
                    }
                } );
            } );
        } );
    </script>

    <?php
}
// ============================================================
// WIDOK: LISTA DRUŻYN
// ============================================================
else{

    $oTeams = $oSql->prepare(
        'SELECT t.iPage, t.sName, t.iStatus,
                ( SELECT COUNT(*) FROM pages p WHERE p.iPageParent = t.iPage ) AS iPlayers,
                ( SELECT COUNT(*) FROM pages p WHERE p.iPageParent = t.iPage AND p.sSquad IN ("1","2") ) AS iInSquad
         FROM pages t WHERE t.iMenu = :menu AND t.iPageParent = 0
         ORDER BY t.iPosition ASC, t.sName COLLATE NOCASE ASC'
    );
    $oTeams->execute( Array( ':menu' => $iTeamsMenu ) );
    $aTeams = $oTeams->fetchAll( PDO::FETCH_ASSOC );
    ?>

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title">Drużyny</h1>
        <div class="mainPage__buttons">
            <a href="?p=teams&amp;sOption=new-player" class="button">Nowy zawodnik</a>
        </div>
    </header>

    <?php if( isset( $_GET['sOption'] ) && $_GET['sOption'] === 'save' ): ?>
        <div class="alert alert-success mb-3"><?php echo $lang['Operation_completed']; ?></div>
    <?php endif; ?>

    <?php if( empty( $aTeams ) ): ?>
        <div class="alert alert-info">Brak drużyn — dodaj zakładkę przez Strony → Nowa strona z typem menu „Drużyny".</div>
    <?php else: ?>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                <thead>
                    <tr>
                        <th>Drużyna</th>
                        <th style="width:160px">Kadra meczowa</th>
                        <th style="width:140px">Zawodników</th>
                        <th style="width:220px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach( $aTeams as $aTeam ): ?>
                    <tr<?php echo (int) $aTeam['iStatus'] === 0 ? ' class="muted"' : ''; ?>>
                        <th class="name">
                            <a href="?p=teams&amp;iTeam=<?php echo (int) $aTeam['iPage']; ?>"><?php echo html( (string) $aTeam['sName'] ); ?></a>
                        </th>
                        <td><?php echo (int) $aTeam['iInSquad']; ?></td>
                        <td><?php echo (int) $aTeam['iPlayers']; ?></td>
                        <td>
                            <a href="?p=pages-form&amp;iPage=<?php echo (int) $aTeam['iPage']; ?>" class="button button-sm button-border">Edytuj stronę</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>

    <?php
}

require_once 'templates/admin/_footer.php';
?>
