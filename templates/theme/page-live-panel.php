<?php
if (!defined('CUSTOMER_PAGE')) { exit; }

/*
 * TRANSMISJA LIVE — panel operatora meczu.
 * Sterowanie: wynik, zegar (z pauzą), plansze nakładki OBS, zdarzenia meczowe
 * (kafelki zawodników → arkusz akcji), edycja/kasowanie zdarzeń.
 * Wszystkie komendy przez plugins/live/api.php (POST: admin + CSRF).
 * Dostęp: wyłącznie zalogowany admin CMS. Logika JS: js/page-live-panel.js.
 */

require_once $theme.'_header.php';

$bLiveAdmin = (
    !empty($config['session_key_name'])
    && isset($_SESSION[$config['session_key_name']])
    && is_int($_SESSION[$config['session_key_name']])
);

if (!$bLiveAdmin) {

    echo '<div class="container"><div class="mainPage__content">';
    echo '<div class="alert alert-danger">'.$lang['live_panel_login_required'].'</div>';
    echo '<p class="mt-2"><a class="button" href="'.$config['admin_file'].'">'.$lang['live_panel_login_link'].'</a></p>';
    echo '</div></div>';

} else {

    // ------------------------------------------------------------
    // DANE: stan meczu, drużyny, plansze, zawodnicy
    // ------------------------------------------------------------
    $aLive = $oSql->throwAll('SELECT * FROM live_state WHERE id = 1') ?: Array();
    $iTeam1 = (int) ($aLive['iTeam1'] ?? 0);
    $iTeam2 = (int) ($aLive['iTeam2'] ?? 0);

    // drużyny = zakładki najwyższego poziomu z typem menu „Drużyny";
    // iPageParent = 0 konieczne, bo zawodnicy dziedziczą iMenu po drużynie
    $aTeamOptions = Array();
    $oTeams = $oSql->prepare('SELECT iPage, sName FROM pages WHERE iMenu = :menu AND iPageParent = 0 AND iStatus = 1 ORDER BY iPosition ASC, sName COLLATE NOCASE ASC');
    $oTeams->execute(Array(':menu' => (int) ($config['teams_menu'] ?? 0)));
    while ($aRow = $oTeams->fetch(PDO::FETCH_ASSOC)) {
        $aTeamOptions[(int) $aRow['iPage']] = (string) $aRow['sName'];
    }

    $aBoards = Array();
    foreach ($oSql->getQuery('SELECT sName, sLabel, iVisible FROM live_boards ORDER BY iPosition ASC') as $aRow) {
        $aBoards[] = $aRow;
    }

    // zawodnicy drużyny: wyjściowa 11 → rezerwa → poza kadrą
    $fTeamPlayers = function ($iTeam) use ($oSql) {
        if ($iTeam <= 0) {
            return Array();
        }
        $oQuery = $oSql->prepare(
            'SELECT iPage, sName, sNumber, sSquad FROM pages WHERE iPageParent = :team AND iStatus = 1
             ORDER BY CASE WHEN sSquad = "1" THEN 0 WHEN sSquad = "2" THEN 1 ELSE 2 END, iPosition ASC, sName COLLATE NOCASE ASC'
        );
        $oQuery->execute(Array(':team' => $iTeam));
        return $oQuery->fetchAll(PDO::FETCH_ASSOC);
    };

    // kafelki zawodników jednej drużyny, pogrupowane wg składu
    $fRenderTeam = function ($iTeam, $sFallbackTitle) use ($fTeamPlayers, $aTeamOptions, $lang) {
        $sTitle = $aTeamOptions[$iTeam] ?? $sFallbackTitle;
        $aGroups = Array('1' => Array(), '2' => Array(), '' => Array());
        foreach ($fTeamPlayers($iTeam) as $aPlayer) {
            $sSquad = in_array((string) $aPlayer['sSquad'], Array('1', '2'), true) ? (string) $aPlayer['sSquad'] : '';
            $aGroups[$sSquad][] = $aPlayer;
        }

        $content = '<div class="col-6">';
//        $content = '<h2 class="">'.$lang['live_players'].'</h2>';
        $content .= '<div class="livePanel__team" data-team="'.$iTeam.'">';
        $content .= '<h3 class="livePanel__sectionTitle">'.html($sTitle).'</h3>';

        if ($iTeam <= 0) {
            $content .= '<p class="livePanel__hint">'.$lang['live_no_teams'].'</p></div>';
            return $content;
        }

        // szybkie filtrowanie kafelków zawodników (na żywo, per drużyna)
        $content .= '<div class="livePanel__search mb-3">'
            .'<input type="text" class="form-control lp-player-search" data-team="'.$iTeam.'"'
            .' placeholder="'.html($lang['live_search_player']).'" autocomplete="off" />'
            .'<button type="button" class="button lp-player-search-clear">'.html($lang['clear']).'</button>'
            .'</div>';

        $aGroupLabels = Array('1' => $lang['live_first_squad'], '2' => $lang['live_reserve'], '' => $lang['live_off_squad']);
        foreach ($aGroups as $sSquad => $aPlayers) {
            if (empty($aPlayers)) {
                continue;
            }
            $content .= '<h4 class="livePanel__groupLabel">'.$aGroupLabels[$sSquad].'</h4>';
            $content .= '<div class="livePanel__players">';
            foreach ($aPlayers as $aPlayer) {
                $content .= '<button type="button" class="button livePanel__player'.($sSquad === '2' ? ' is-bench' : '').($sSquad === '' ? ' is-off' : '').'"'
                    .' data-player="'.(int) $aPlayer['iPage'].'"'
                    .' data-team="'.$iTeam.'"'
                    .' data-name="'.html((string) $aPlayer['sName']).'"'
                    .' data-number="'.html((string) $aPlayer['sNumber']).'">'
                    .'<span class="livePanel__playerNumber">'.html((string) $aPlayer['sNumber'] !== '' ? (string) $aPlayer['sNumber'] : '–').'</span>'
                    .'<span class="livePanel__playerName">'.html((string) $aPlayer['sName']).'</span>'
                    .'<span class="livePanel__playerBadges"></span>'
                    .'<span class="livePanel__playerDot" data-state="" title="'.$lang['live_dot_title'].'"></span>'
                    .'</button>';
            }
            $content .= '</div>';
        }
        $content .= '</div>';
        $content .= '</div>';
        return $content;
    };
    ?>

<div class="container">
<div class="livePanel" id="livePanel">

    <!-- wybór gospodarza i gościa: panel admina → Transmisja → Konfiguracja -->

    <!-- WYNIK + ZEGAR (sticky na górze) -->
    <div class="livePanel__scoreboard">
        <div class="livePanel__scoreTeam">
            <span class="livePanel__scoreTeamName"><?php echo getData($iTeam1, 'sDesc'); ?></span>
            <div class="livePanel__scoreButtons">
                <button type="button" class="button livePanel__scoreBtn" data-score-team="1" data-delta="1">+</button>
                <button type="button" class="button livePanel__scoreBtn" data-score-team="1" data-delta="-1">−</button>
            </div>
        </div>
        <div class="livePanel__scoreCenter">
            <div class="livePanel__scoreValue"><span id="lp-score1">0</span>:<span id="lp-score2">0</span></div>
        </div>
        <div class="livePanel__scoreTeam livePanel__scoreTeam2">
            <span class="livePanel__scoreTeamName"><?php echo getData($iTeam2, 'sDesc'); ?></span>
            <div class="livePanel__scoreButtons">
                <button type="button" class="button livePanel__scoreBtn" data-score-team="2" data-delta="1">+</button>
                <button type="button" class="button livePanel__scoreBtn" data-score-team="2" data-delta="-1">−</button>
            </div>
        </div>
    </div>

    <!-- STEROWANIE ZEGAREM -->
    <div class="livePanel__section">
        <h2 class="livePanel__sectionTitle">
        <span class="livePanel__sectionTitle-name"><?php echo $lang['live_clock']; ?> </span>
        <span class="livePanel__sectionTitle-value" id="lp-timer">00:00</span>
        <span class="livePanel__sectionTitle-half" id="lp-half"></span>
        </h2>
        <div class="livePanel__buttonRow">
            <button type="button" class="button button-bold" data-timer="start1"><?php echo $lang['live_half_1']; ?></button>
            <button type="button" class="button button-bold" data-timer="start2"><?php echo $lang['live_half_2']; ?></button>
            <button type="button" class="button" data-timer="pause" id="lp-pause" hidden>▐▐</button>
            <button type="button" class="button" data-timer="resume" id="lp-resume">▶</button>
            <button type="button" class="button" data-timer="plus"><?php echo $lang['live_plus_min']; ?></button>
            <button type="button" class="button" data-timer="minus"><?php echo $lang['live_minus_min']; ?></button>
            <button type="button" class="button button-danger livePanel__danger" data-timer="reset">◼</button>
        </div>
    </div>

    <!-- PLANSZE -->
    <div class="livePanel__section">
        <h2 class="livePanel__sectionTitle"><?php echo $lang['live_boards']; ?></h2>
        <div class="livePanel__buttonRow livePanel__boards">
            <?php foreach ($aBoards as $aBoard): ?>
                <button type="button" class="button livePanel__board<?php echo ((int) $aBoard['iVisible'] === 1) ? ' is-active' : ''; ?>"
                        data-board="<?php echo html((string) $aBoard['sName']); ?>"><?php echo html((string) $aBoard['sLabel']); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="livePanel__buttonRow mt-2">
<!--
            <button type="button" class="button" id="lp-scorebar-pos"
                    data-pos="<?php echo (int) ($aLive['iScorebarPos'] ?? 0); ?>"><?php echo $lang['live_scorebar_pos']; ?>: <span id="lp-scorebar-label">—</span></button>
-->
            <button type="button" class="button livePanel__replay" id="lp-replay">▶ <?php echo $lang['live_replay_button']; ?></button>
        </div>
    </div>

    <!-- ZAWODNICY -->
    <div class="livePanel__section">
        
        <div class="row">
            <?php echo $fRenderTeam($iTeam1, $lang['live_team_home']); ?>
            <?php echo $fRenderTeam($iTeam2, $lang['live_team_away']); ?>
        </div>
    </div>

    <!-- ZDARZENIA -->
    <div class="livePanel__section">
        <h2 class="livePanel__sectionTitle"><?php echo $lang['live_events']; ?></h2>
        <div class="livePanel__eventList" id="lp-events"></div>
        <div class="livePanel__buttonRow mt-2">
            <button type="button" class="button livePanel__danger" id="lp-clear-events"><?php echo $lang['live_clear_events']; ?></button>
            <button type="button" class="button livePanel__danger" id="lp-new-match"><?php echo $lang['live_new_match']; ?></button>
        </div>
    </div>

    <!-- ARKUSZ AKCJI ZAWODNIKA -->
    <div class="livePanel__sheet" id="lp-sheet" hidden>
        <div class="livePanel__sheetCard">
            <header class="livePanel__sheetHeader" id="lp-sheet-title"></header>
            <label class="livePanel__sheetMinute">
                <?php echo $lang['live_minute']; ?>
                <input type="text" id="lp-sheet-minute" class="form-control" inputmode="numeric" maxlength="3" />
            </label>
            <div class="livePanel__sheetActions">
                <?php foreach ($config['live_actions'] as $sKey => $sLabel): ?>
                    <button type="button" class="button livePanel__sheetAction" data-action="<?php echo html($sKey); ?>"><?php echo $sLabel; ?></button>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button livePanel__sheetCancel" id="lp-sheet-cancel"><?php echo $lang['live_cancel']; ?></button>
        </div>
    </div>

    <!-- KOMUNIKATY -->
    <div class="livePanel__toast" id="lp-toast" hidden></div>

</div>
</div>

<script>
<?php
// zawodnicy obu drużyn — select zmiany zawodnika przy edycji zdarzenia
$aPlayersConfig = Array();
foreach (Array($iTeam1, $iTeam2) as $iTeamId) {
    $aList = Array();
    foreach ($fTeamPlayers($iTeamId) as $aPlayer) {
        $aList[] = Array(
            'id'     => (int) $aPlayer['iPage'],
            'name'   => (string) $aPlayer['sName'],
            'number' => (string) $aPlayer['sNumber'],
        );
    }
    $aPlayersConfig[(string) $iTeamId] = $aList;
}
?>
window.livePanelConfig = <?php echo json_encode(Array(
    // root-relative (nie BASE_URL) — działa na każdej domenie/środowisku
    'api'     => $config['base_path_with_slash'].'plugins/live/api.php',
    'csrf'    => hash('sha256', 'csrf|'.$config['session_key_name']),
    'actions' => $config['live_actions'],
    'badges'  => $config['live_action_badges'],
    'teams'   => Array('1' => $iTeam1, '2' => $iTeam2),
    'players' => $aPlayersConfig,
    'teamNames' => Array(
        (string) $iTeam1 => (string) ($aTeamOptions[$iTeam1] ?? ''),
        (string) $iTeam2 => (string) ($aTeamOptions[$iTeam2] ?? ''),
    ),
    'labels' => Array(
        'confirmNewMatch' => $lang['live_confirm_new_match'],
        'confirmClear'    => $lang['live_confirm_clear'],
        'confirmDelete'   => $lang['live_confirm_delete'],
        'noEvents'        => $lang['live_no_events'],
        'save'            => $lang['live_save'],
        'delete'          => $lang['live_delete'],
        'halfShort'       => $lang['live_half_short'],
        'posLeft'         => $lang['live_pos_left'],
        'posRight'        => $lang['live_pos_right'],
        'replaySent'      => $lang['live_replay_sent'],
    ),
), JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo THEME; ?>js/page-live-panel.js?ver=1"></script>

    <?php
}

require_once $theme.'_footer.php';
