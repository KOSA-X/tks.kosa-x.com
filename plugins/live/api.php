<?php
/**
 * KOSA X CMS — moduł transmisji live: JSON API panelu i nakładki OBS.
 *
 * GET (publiczne — tylko odczyt stanu meczu):
 *   ?action=state[&since=ID] — zbiorczy stan: zegar, wynik, plansze + zdarzenia
 *                              NOWSZE niż ID (jedno żądanie dla nakładki)
 *   ?action=events           — pełna lista zdarzeń (podsumowanie / edycja)
 *
 * POST (wymaga zalogowanego ADMINA CMS + tokenu CSRF w polu sTokenCsrf):
 *   event_add {iPlayer, iTeam, sAction, sMinute}   event_update {id, iPlayer, sMinute}
 *   event_delete {id}                              events_clear
 *   score_adjust {team: 1|2, delta: 1|-1}
 *   timer_start {half: 1|2}   timer_pause   timer_resume   timer_adjust {delta}   timer_reset
 *   board_toggle {sName, iVisible}   teams_set {iTeam1, iTeam2}   match_reset
 *   scorebar_pos {pos: 0|1} — pozycja paska wyniku (lewy/prawy górny róg)
 *   replay_show — powtórka z OBS na telebimie (inkrementuje live_state.iReplayCount;
 *                 telebim wykrywa zmianę licznika w state i odtwarza klip
 *                 z lokalnego serwera replay buffera — plugins/live/replay/)
 *
 * Bezpieczeństwo (w odróżnieniu od starego _old/ajax.php): każdy zapis wymaga
 * sesji admina + CSRF, wszystkie zapytania przez prepared statements.
 */

session_start();
define('CUSTOMER_PAGE', true);
define('LIVE_ROOT', realpath(__DIR__.'/../../'));
chdir(LIVE_ROOT);
require_once LIVE_ROOT.'/database/config.php';
require_once LIVE_ROOT.'/core/libraries/sql.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$oSql = Sql::getInstance();

/** Odpowiedź JSON + koniec skryptu. */
function liveApiOut(array $aData, int $iCode = 200): void
{
    http_response_code($iCode);
    echo json_encode($aData, JSON_UNESCAPED_UNICODE);
    exit;
}

function liveApiError(string $sError, int $iCode = 400): void
{
    liveApiOut(['ok' => false, 'error' => $sError], $iCode);
}

/**
 * Klucz sesji admina — CMS trzyma go w tabeli bin (core/common.php →
 * getBinValues); ten sam wzorzec odczytu co plugins/products/import.php.
 */
function liveApiSessionKey(Sql $oSql): ?string
{
    global $config;
    if (!empty($config['session_key_name'])) {
        return (string) $config['session_key_name'];
    }
    $sValue = $oSql->getColumn('SELECT sValue FROM bin WHERE sKey = "session_key_name" LIMIT 1');
    return ($sValue !== false && $sValue !== null && $sValue !== '') ? (string) $sValue : null;
}

/** Zbiorczy stan meczu (+ zdarzenia nowsze niż $iSince). */
function liveApiState(Sql $oSql, int $iSince): array
{
    $aState = $oSql->throwAll('SELECT * FROM live_state WHERE id = 1') ?: [];

    $bRunning = ((int) ($aState['iTimerStop'] ?? 1) === 0);
    $iSeconds = $bRunning
        ? (time() - (int) $aState['iTimerStart'] + (int) $aState['iTimerBase'])
        : (int) ($aState['iTimerBase'] ?? 0);

    $aBoards = [];
    foreach ($oSql->getQuery('SELECT sName, iVisible FROM live_boards ORDER BY iPosition ASC') as $aRow) {
        $aBoards[$aRow['sName']] = (int) $aRow['iVisible'];
    }

    // kartki per drużyna — kropki pod nazwami na pasku wyniku (kolejność
    // jak w 'score': [gospodarz, gość]); liczone z całej historii zdarzeń,
    // więc odświeżenie nakładki niczego nie gubi
    $aCards = [
        ['yellow' => 0, 'red' => 0],
        ['yellow' => 0, 'red' => 0],
    ];
    $aTeamIndex = [
        (int) ($aState['iTeam1'] ?? 0) => 0,
        (int) ($aState['iTeam2'] ?? 0) => 1,
    ];
    $oCardQuery = $oSql->getQuery(
        'SELECT iTeam, sAction, COUNT(*) AS iCount FROM live_events
         WHERE sAction IN ("yellow_card", "red_card") GROUP BY iTeam, sAction'
    );
    foreach ($oCardQuery as $aRow) {
        $iIndex = $aTeamIndex[(int) $aRow['iTeam']] ?? null;
        if ($iIndex !== null) {
            $aCards[$iIndex][$aRow['sAction'] === 'red_card' ? 'red' : 'yellow'] = (int) $aRow['iCount'];
        }
    }

    // Statusy wyświetlania popupów na nakładce — symulacja czasowa po stronie
    // serwera (nakładka nie ma prawa zapisu). Model: nakładka podnosi event
    // ~1 s po INSERT (poll), pokazuje 8 s + 0,6 s animacji, jeden naraz.
    // Przybliżenie zakłada, że nakładka jest otwarta i odpytuje na bieżąco.
    $aDisplay = [];
    $fPrevEnd = 0.0;
    foreach ($oSql->getQuery('SELECT id, iClock FROM live_events ORDER BY id ASC') as $aRow) {
        $fStart = max((float) $aRow['iClock'] + 1.0, $fPrevEnd);
        $fEnd   = $fStart + 8.6;
        $iNow   = time();
        $aDisplay[(int) $aRow['id']] = ($iNow < $fStart) ? 'queued' : (($iNow < $fEnd) ? 'showing' : 'done');
        $fPrevEnd = $fEnd;
    }

    // zdarzenia nowsze niż wskazany ID — z danymi zawodnika w jednym zapytaniu
    $aEvents = [];
    $iLastId = $iSince;
    $oQuery  = $oSql->prepare(
        'SELECT e.id, e.iPlayer, e.iTeam, e.sAction, e.sMinute,
                COALESCE(p.sName, "") AS sPlayerName, COALESCE(p.sNumber, "") AS sPlayerNumber,
                COALESCE(( SELECT f.sFileName FROM files f WHERE f.iPage = e.iPlayer AND f.iSize > 0
                           ORDER BY f.iDefault DESC, f.iPosition ASC LIMIT 1 ), "") AS sPlayerImage,
                COALESCE(( SELECT f.sFileName FROM files f WHERE f.iPage = e.iPlayer AND f.iSize = 0
                           AND (f.sFileName LIKE "%.mp4" OR f.sFileName LIKE "%.webm")
                           ORDER BY f.iPosition ASC LIMIT 1 ), "") AS sPlayerVideo
         FROM live_events e LEFT JOIN pages p ON p.iPage = e.iPlayer
         WHERE e.id > :since ORDER BY e.id ASC'
    );
    $oQuery->execute([':since' => $iSince]);
    while ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
        $iLastId   = max($iLastId, (int) $aRow['id']);
        $aEvents[] = [
            'id'      => (int) $aRow['id'],
            'action'  => (string) $aRow['sAction'],
            'minute'  => (string) $aRow['sMinute'],
            'team'    => (int) $aRow['iTeam'],
            'display' => $aDisplay[(int) $aRow['id']] ?? 'done',
            'player'  => [
                'id'     => (int) $aRow['iPlayer'],
                'name'   => (string) $aRow['sPlayerName'],
                'number' => (string) $aRow['sPlayerNumber'],
                'photo'  => (string) $aRow['sPlayerImage'],
                'video'  => (string) $aRow['sPlayerVideo'],
            ],
        ];
    }

    return [
        'ok'    => true,
        'timer' => [
            'seconds' => max(0, $iSeconds),
            'half'    => (int) ($aState['iHalf'] ?? 1),
            'running' => $bRunning,
        ],
        'score'  => [(int) ($aState['iScore1'] ?? 0), (int) ($aState['iScore2'] ?? 0)],
        'teams'  => [(int) ($aState['iTeam1'] ?? 0), (int) ($aState['iTeam2'] ?? 0)],
        'scorebar' => (int) ($aState['iScorebarPos'] ?? 0),
        'replay'   => (int) ($aState['iReplayCount'] ?? 0),
        'cards'    => $aCards,
        'boards' => $aBoards,
        'events' => $aEvents,
        'last_event_id' => $iLastId,
    ];
}

// ============================================================
// GET — publiczny odczyt
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {

    $sAction = (string) ($_GET['action'] ?? '');

    if ($sAction === 'state') {
        liveApiOut(liveApiState($oSql, (int) ($_GET['since'] ?? 0)));
    }

    if ($sAction === 'events') {
        $aEvents = [];
        $oQuery  = $oSql->getQuery(
            'SELECT e.id, e.iPlayer, e.iTeam, e.sAction, e.sMinute,
                    COALESCE(p.sName, "") AS sPlayerName, COALESCE(p.sNumber, "") AS sPlayerNumber,
                    COALESCE(( SELECT f.sFileName FROM files f WHERE f.iPage = e.iPlayer AND f.iSize > 0
                               ORDER BY f.iDefault DESC, f.iPosition ASC LIMIT 1 ), "") AS sPlayerImage
             FROM live_events e LEFT JOIN pages p ON p.iPage = e.iPlayer
             ORDER BY CAST(e.sMinute AS INTEGER) ASC, e.id ASC'
        );
        while ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
            $aEvents[] = [
                'id'     => (int) $aRow['id'],
                'action' => (string) $aRow['sAction'],
                'minute' => (string) $aRow['sMinute'],
                'team'   => (int) $aRow['iTeam'],
                'player' => [
                    'id'     => (int) $aRow['iPlayer'],
                    'name'   => (string) $aRow['sPlayerName'],
                    'number' => (string) $aRow['sPlayerNumber'],
                    'photo'  => (string) $aRow['sPlayerImage'],
                ],
            ];
        }
        liveApiOut(['ok' => true, 'events' => $aEvents]);
    }

    liveApiError('Nieznana akcja.', 400);
}

// ============================================================
// POST — komendy (tylko zalogowany admin + CSRF)
// ============================================================
$sSessionKey = liveApiSessionKey($oSql);
if ($sSessionKey === null || !isset($_SESSION[$sSessionKey]) || !is_int($_SESSION[$sSessionKey])) {
    liveApiError('Brak autoryzacji — zaloguj się do panelu administracyjnego.', 403);
}

// token CSRF — ta sama formuła co getCsrfToken() w core/common-admin.php
$sTokenExpected = hash('sha256', 'csrf|'.$sSessionKey);
$sTokenGiven    = (string) ($_POST['sTokenCsrf'] ?? '');
if ($sTokenGiven === '' || !hash_equals($sTokenExpected, $sTokenGiven)) {
    liveApiError('Nieprawidłowy token CSRF.', 403);
}

$sAction = (string) ($_POST['action'] ?? '');
$aState  = $oSql->throwAll('SELECT * FROM live_state WHERE id = 1') ?: [];
$aMatchTeams = [(int) ($aState['iTeam1'] ?? 0), (int) ($aState['iTeam2'] ?? 0)];

switch ($sAction) {

    // --------------------------------------------------------
    // ZDARZENIA MECZOWE
    // --------------------------------------------------------
    case 'event_add': {
        $iPlayer = (int) ($_POST['iPlayer'] ?? 0);
        $iTeam   = (int) ($_POST['iTeam'] ?? 0);
        $sType   = (string) ($_POST['sAction'] ?? '');
        $sMinute = trim((string) ($_POST['sMinute'] ?? ''));

        if (!isset($config['live_actions'][$sType])) {
            liveApiError('Nieznany typ zdarzenia.');
        }
        if (!in_array($iTeam, $aMatchTeams, true) || $iTeam === 0) {
            liveApiError('Drużyna nie gra w tym meczu.');
        }
        if ($iPlayer > 0) {
            $oCheck = $oSql->prepare('SELECT COUNT(*) FROM pages WHERE iPage = :p AND iPageParent = :t');
            $oCheck->execute([':p' => $iPlayer, ':t' => $iTeam]);
            if ((int) $oCheck->fetchColumn() === 0) {
                liveApiError('Zawodnik nie należy do tej drużyny.');
            }
        }

        $oInsert = $oSql->prepare(
            'INSERT INTO live_events (iPlayer, iTeam, sAction, sMinute, iClock)
             VALUES (:player, :team, :type, :minute, :clock)'
        );
        $oInsert->execute([
            ':player' => $iPlayer, ':team' => $iTeam,
            ':type' => $sType, ':minute' => $sMinute, ':clock' => time(),
        ]);
        liveApiOut(['ok' => true, 'id' => (int) $oSql->lastInsertId()]);
    }

    case 'event_update': {
        $iId     = (int) ($_POST['id'] ?? 0);
        $iPlayer = (int) ($_POST['iPlayer'] ?? 0);
        $sMinute = trim((string) ($_POST['sMinute'] ?? ''));

        // nowy zawodnik musi należeć do drużyny tego zdarzenia
        if ($iPlayer > 0) {
            $iEventTeam = (int) $oSql->getColumn('SELECT iTeam FROM live_events WHERE id = '.$iId);
            $oCheck = $oSql->prepare('SELECT COUNT(*) FROM pages WHERE iPage = :p AND iPageParent = :t');
            $oCheck->execute([':p' => $iPlayer, ':t' => $iEventTeam]);
            if ((int) $oCheck->fetchColumn() === 0) {
                liveApiError('Zawodnik nie należy do drużyny tego zdarzenia.');
            }
        }

        $oUpdate = $oSql->prepare('UPDATE live_events SET iPlayer = :player, sMinute = :minute WHERE id = :id');
        $oUpdate->execute([':player' => $iPlayer, ':minute' => $sMinute, ':id' => $iId]);
        liveApiOut(['ok' => true]);
    }

    case 'event_delete': {
        $oDelete = $oSql->prepare('DELETE FROM live_events WHERE id = :id');
        $oDelete->execute([':id' => (int) ($_POST['id'] ?? 0)]);
        liveApiOut(['ok' => true]);
    }

    case 'events_clear': {
        $oSql->exec('DELETE FROM live_events');
        liveApiOut(['ok' => true]);
    }

    // --------------------------------------------------------
    // WYNIK
    // --------------------------------------------------------
    case 'score_adjust': {
        $iTeam  = (int) ($_POST['team'] ?? 0);
        $iDelta = ((int) ($_POST['delta'] ?? 0) >= 0) ? 1 : -1;
        if (!in_array($iTeam, [1, 2], true)) {
            liveApiError('Podaj drużynę 1 albo 2.');
        }
        // MAX(0, ...) — wynik nie schodzi poniżej zera
        $oSql->exec('UPDATE live_state SET iScore'.$iTeam.' = MAX(0, iScore'.$iTeam.' + ('.$iDelta.')) WHERE id = 1');
        $aRow = $oSql->throwAll('SELECT iScore1, iScore2 FROM live_state WHERE id = 1');
        liveApiOut(['ok' => true, 'score' => [(int) $aRow['iScore1'], (int) $aRow['iScore2']]]);
    }

    // --------------------------------------------------------
    // ZEGAR
    // --------------------------------------------------------
    case 'timer_start': {
        $iHalf = ((int) ($_POST['half'] ?? 1) === 2) ? 2 : 1;
        $iBase = ($iHalf === 2) ? 2700 : 0;
        $oStart = $oSql->prepare('UPDATE live_state SET iTimerBase = :base, iTimerStart = :now, iTimerStop = 0, iHalf = :half WHERE id = 1');
        $oStart->execute([':base' => $iBase, ':now' => time(), ':half' => $iHalf]);
        liveApiOut(['ok' => true]);
    }

    case 'timer_pause': {
        // prawdziwa pauza (usprawnienie vs stary system): zamrażamy upływ czasu
        if ((int) ($aState['iTimerStop'] ?? 1) === 0) {
            $iElapsed = time() - (int) $aState['iTimerStart'] + (int) $aState['iTimerBase'];
            $oPause = $oSql->prepare('UPDATE live_state SET iTimerBase = :base, iTimerStop = 1 WHERE id = 1');
            $oPause->execute([':base' => max(0, $iElapsed)]);
        }
        liveApiOut(['ok' => true]);
    }

    case 'timer_resume': {
        if ((int) ($aState['iTimerStop'] ?? 1) === 1) {
            $oResume = $oSql->prepare('UPDATE live_state SET iTimerStart = :now, iTimerStop = 0 WHERE id = 1');
            $oResume->execute([':now' => time()]);
        }
        liveApiOut(['ok' => true]);
    }

    case 'timer_adjust': {
        $iDelta = ((int) ($_POST['delta'] ?? 0) >= 0) ? 60 : -60;
        $oSql->exec('UPDATE live_state SET iTimerBase = MAX(0, iTimerBase + ('.$iDelta.')) WHERE id = 1');
        liveApiOut(['ok' => true]);
    }

    case 'timer_reset': {
        $oSql->exec('UPDATE live_state SET iTimerBase = 0, iTimerStart = 0, iTimerStop = 1, iHalf = 1 WHERE id = 1');
        liveApiOut(['ok' => true]);
    }

    // --------------------------------------------------------
    // PLANSZE
    // --------------------------------------------------------
    case 'board_toggle': {
        $sName    = (string) ($_POST['sName'] ?? '');
        $iVisible = ((int) ($_POST['iVisible'] ?? 0) === 1) ? 1 : 0;

        $oCheck = $oSql->prepare('SELECT COUNT(*) FROM live_boards WHERE sName = :name');
        $oCheck->execute([':name' => $sName]);
        if ((int) $oCheck->fetchColumn() === 0) {
            liveApiError('Nieznana plansza.');
        }

        // logika jak w starym systemie: naraz widoczna jedna plansza;
        // pasek „wynik" i narożny badge „sponsor_meczu" są NIEZALEŻNE —
        // mogą wisieć razem z dowolną planszą (jak logo realizatora)
        if ($iVisible === 1 && !in_array($sName, Array('wynik', 'sponsor_meczu'), true)) {
            $oSql->exec('UPDATE live_boards SET iVisible = 0 WHERE sName NOT IN ("wynik", "sponsor_meczu")');
        }
        $oToggle = $oSql->prepare('UPDATE live_boards SET iVisible = :vis WHERE sName = :name');
        $oToggle->execute([':vis' => $iVisible, ':name' => $sName]);

        $aBoards = [];
        foreach ($oSql->getQuery('SELECT sName, iVisible FROM live_boards ORDER BY iPosition ASC') as $aRow) {
            $aBoards[$aRow['sName']] = (int) $aRow['iVisible'];
        }
        liveApiOut(['ok' => true, 'boards' => $aBoards]);
    }

    case 'scorebar_pos': {
        // pozycja paska wyniku na nakładce: 0 = lewy górny, 1 = prawy górny
        $iPos = ((int) ($_POST['pos'] ?? 0) === 1) ? 1 : 0;
        $oPos = $oSql->prepare('UPDATE live_state SET iScorebarPos = :pos WHERE id = 1');
        $oPos->execute([':pos' => $iPos]);
        liveApiOut(['ok' => true, 'scorebar' => $iPos]);
    }

    case 'replay_show': {
        // powtórka z OBS: telebim porównuje licznik ze stanu i przy zmianie
        // odtwarza świeży klip z lokalnego serwera replay buffera
        $oSql->exec('UPDATE live_state SET iReplayCount = iReplayCount + 1 WHERE id = 1');
        liveApiOut(['ok' => true, 'replay' => (int) $oSql->getColumn('SELECT iReplayCount FROM live_state WHERE id = 1')]);
    }

    // --------------------------------------------------------
    // KONFIGURACJA MECZU
    // --------------------------------------------------------
    case 'teams_set': {
        $iTeam1 = (int) ($_POST['iTeam1'] ?? 0);
        $iTeam2 = (int) ($_POST['iTeam2'] ?? 0);
        $iTeamsMenu = (int) ($config['teams_menu'] ?? 0);

        if ($iTeam1 === $iTeam2) {
            liveApiError('Wybierz dwie różne drużyny.');
        }
        // drużyna = zakładka najwyższego poziomu z typem menu „Drużyny"
        // (iPageParent = 0 odsiewa zawodników, którzy dziedziczą iMenu)
        $oCheck = $oSql->prepare('SELECT COUNT(*) FROM pages WHERE iPage = :id AND iMenu = :menu AND iPageParent = 0');
        foreach ([$iTeam1, $iTeam2] as $iTeam) {
            $oCheck->execute([':id' => $iTeam, ':menu' => $iTeamsMenu]);
            if ((int) $oCheck->fetchColumn() === 0) {
                liveApiError('Drużyna (ID '.$iTeam.') nie jest zakładką typu „Drużyny".');
            }
        }
        $oTeams = $oSql->prepare('UPDATE live_state SET iTeam1 = :t1, iTeam2 = :t2 WHERE id = 1');
        $oTeams->execute([':t1' => $iTeam1, ':t2' => $iTeam2]);
        liveApiOut(['ok' => true]);
    }

    case 'match_reset': {
        // nowy mecz: wynik 0:0, zegar wyzerowany, historia zdarzeń wyczyszczona,
        // plansze schowane (poza paskiem wyniku)
        $oSql->exec('UPDATE live_state SET iScore1 = 0, iScore2 = 0, iTimerBase = 0, iTimerStart = 0, iTimerStop = 1, iHalf = 1 WHERE id = 1');
        $oSql->exec('DELETE FROM live_events');
        $oSql->exec('UPDATE live_boards SET iVisible = 0 WHERE sName != "wynik"');
        liveApiOut(['ok' => true]);
    }

    default:
        liveApiError('Nieznana akcja.', 400);
}
