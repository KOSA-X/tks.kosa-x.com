<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

/*
 * TRANSMISJA LIVE — Konfiguracja transmisji (osobno od Konfiguracji CMS).
 *
 * Sekcja MECZ: gospodarz + gość (live_state — to samo, co „Konfiguracja
 * meczu" w panelu meczowym), data meczu i kolejka (pola sDate/sPrice
 * strony wskazanej w match_page — wyświetlane w belkach plansz).
 * Sekcja PLANSZE: dopasowanie zakładek-źródeł treści (saveVariables →
 * database/config_pl.php, jak w module Konfiguracja).
 *
 * CSRF sprawdzany globalnie w adm.php (POST wymaga sTokenCsrf).
 */

$iTeamsMenu = (int) ( $config['teams_menu'] ?? 0 );

// ============================================================
// ZAPIS
// ============================================================
$aErrors = Array( );

if( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' && isset( $_POST['sOption'] ) ){

    // --- mecz: drużyny → live_state (walidacja jak w API teams_set) ---
    $iTeam1 = (int) ( $_POST['iTeam1'] ?? 0 );
    $iTeam2 = (int) ( $_POST['iTeam2'] ?? 0 );

    if( $iTeam1 > 0 && $iTeam1 === $iTeam2 ){
        $aErrors[] = 'Gospodarz i gość muszą być dwiema różnymi drużynami.';
    }
    else{
        $oCheck = $oSql->prepare( 'SELECT COUNT(*) FROM pages WHERE iPage = :id AND iMenu = :menu AND iPageParent = 0' );
        foreach( Array( $iTeam1, $iTeam2 ) as $iTeam ){
            if( $iTeam > 0 ){
                $oCheck->execute( Array( ':id' => $iTeam, ':menu' => $iTeamsMenu ) );
                if( (int) $oCheck->fetchColumn( ) === 0 ){
                    $aErrors[] = 'Strona (ID '.$iTeam.') nie jest zakładką typu „Drużyny".';
                }
            }
        }
        if( empty( $aErrors ) ){
            $oTeams = $oSql->prepare( 'UPDATE live_state SET iTeam1 = :t1, iTeam2 = :t2 WHERE id = 1' );
            $oTeams->execute( Array( ':t1' => $iTeam1, ':t2' => $iTeam2 ) );
        }
    }

    // --- plansze: dopasowanie zakładek → config_pl.php ---
    saveVariables( $_POST, $config['dir_database'].'config_'.$config['language'].'.php' );

    // --- data meczu + kolejka → strona meczu (bierzemy ID z POST, bo mogło
    // się właśnie zmienić); celowany UPDATE nie rusza opisów strony ---
    $iMatchPage = (int) ( $_POST['match_page'] ?? ( $config['match_page'] ?? 0 ) );
    if( $iMatchPage > 0 ){
        $oMatch = $oSql->prepare( 'UPDATE pages SET sDate = :date, sPrice = :round WHERE iPage = :id' );
        $oMatch->execute( Array(
            ':date'  => trim( (string) ( $_POST['sMatchDate'] ?? '' ) ),
            ':round' => trim( (string) ( $_POST['sMatchRound'] ?? '' ) ),
            ':id'    => $iMatchPage,
        ) );
        clearCache( );
    }

    if( empty( $aErrors ) ){
        header( 'Location: '.$config['admin_file'].'?p=live-config&sOption=save' );
        exit;
    }
}

// ============================================================
// DANE DO FORMULARZA
// ============================================================
$aLive  = $oSql->throwAll( 'SELECT * FROM live_state WHERE id = 1' ) ?: Array( );
$iTeam1 = (int) ( $aLive['iTeam1'] ?? 0 );
$iTeam2 = (int) ( $aLive['iTeam2'] ?? 0 );

$aTeamOptions = Array( );
$oTeams = $oSql->prepare( 'SELECT iPage, sName FROM pages WHERE iMenu = :menu AND iPageParent = 0 AND iStatus = 1 ORDER BY iPosition ASC, sName COLLATE NOCASE ASC' );
$oTeams->execute( Array( ':menu' => $iTeamsMenu ) );
while( $aRow = $oTeams->fetch( PDO::FETCH_ASSOC ) ){
    $aTeamOptions[(int) $aRow['iPage']] = (string) $aRow['sName'];
}

$iMatchPage  = (int) ( $config['match_page'] ?? 0 );
$sMatchDate  = $iMatchPage > 0 ? (string) ( getData( $iMatchPage, 'sDate' ) ?? '' ) : '';
$sMatchRound = $iMatchPage > 0 ? (string) ( getData( $iMatchPage, 'sPrice' ) ?? '' ) : '';

/** Select drużyny (0 = brak). */
function liveCfgTeamSelect( string $sName, int $iSelected, array $aTeams ): string {
    $sOptions = '<option value="0">-</option>';
    foreach( $aTeams as $iId => $sTeam ){
        $sOptions .= '<option value="'.$iId.'"'.( $iId === $iSelected ? ' selected="selected"' : '' ).'>'.html( $sTeam ).'</option>';
    }
    return '<select name="'.$sName.'" id="'.$sName.'">'.$sOptions.'</select>';
}

/** Select strony CMS dla klucza configu (jak w module Konfiguracja). */
function liveCfgPageSelect( string $sName, string $sLabel, array $config, $oPage ): string {
    $selected = $config[$sName] ?? '';
    return '
        <li>
            <div class="form-item">
                <label for="'.$sName.'">'.html( $sLabel ).'</label>
                <select name="'.$sName.'" id="'.$sName.'">
                    '.( empty( $selected ) ? '<option value="" disabled="disabled" selected="selected">'.$GLOBALS['lang']['none'].'</option>' : '' ).'
                    '.$oPage->listPagesSelectAdmin( $selected ).'
                </select>
            </div>
        </li>
    ';
}

$sSelectedMenu = 'squad-import'; // podświetlenie rodzica „Transmisja" w menu

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>

<form action="?p=live-config" name="form" method="post" class="main-form">

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title">Transmisja — konfiguracja</h1>
        <div class="mainPage__buttons">
            <input type="submit" name="sOption" class="button" value="<?php echo $lang['save']; ?>" />
        </div>
    </header>

    <?php if( isset( $_GET['sOption'] ) ): ?>
        <div class="alert alert-success"><?php echo $lang['Operation_completed']; ?></div>
    <?php endif; ?>

    <?php foreach( $aErrors as $sError ): ?>
        <div class="alert alert-danger mb-3"><?php echo html( $sError ); ?></div>
    <?php endforeach; ?>

    <ul class="forms list">

        <li><h5 class="form-separator">Mecz</h5></li>

        <li>
            <div class="form-item">
                <label for="iTeam1">Gospodarz</label>
                <?php echo liveCfgTeamSelect( 'iTeam1', $iTeam1, $aTeamOptions ); ?>
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="iTeam2">Gość</label>
                <?php echo liveCfgTeamSelect( 'iTeam2', $iTeam2, $aTeamOptions ); ?>
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="sMatchDate">Data meczu</label>
                <input type="text" name="sMatchDate" id="sMatchDate" value="<?php echo html( $sMatchDate ); ?>" placeholder="np. 31.08.2026 18:00" />
                <p class="form-text">Wyświetlana w belkach plansz (Dzień meczowy, składy).</p>
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="sMatchRound">Kolejka</label>
                <input type="text" name="sMatchRound" id="sMatchRound" value="<?php echo html( $sMatchRound ); ?>" placeholder="np. 30. kolejka 2025/26" />
                <p class="form-text">Wyświetlana w belkach plansz Dzień meczowy i Podsumowanie.</p>
            </div>
        </li>

        <li>
            <h5 class="form-separator">Plansze</h5>
<!--            <p class="form-text">Wskaż zakładki, z których plansze biorą treść (opisy + zdjęcia w gridzie). Zakładki tworzysz w Strony (typ menu „Plansze").</p>-->
        </li>

        <?php
        echo liveCfgPageSelect( 'match_page', 'DZIEŃ MECZOWY', $config, $oPage );
        echo liveCfgPageSelect( 'live_referees_page', 'SĘDZIOWIE', $config, $oPage );
        echo liveCfgPageSelect( 'live_sponsors_page', 'SPONSORZY', $config, $oPage );
        echo liveCfgPageSelect( 'live_production_page', 'REALIZACJA TRANSMISJI', $config, $oPage );
        ?>

    </ul>

    <input type="hidden" name="sTokenCsrf" value="<?php echo html( getCsrfToken( ) ); ?>" />

    <div class="mainPage__buttons mt-3">
        <input type="submit" name="sOption" class="button" value="<?php echo $lang['save']; ?>" />
    </div>

</form>

<?php
require_once 'templates/admin/_footer.php';
?>
