<?php
session_start();
define('CUSTOMER_PAGE', true);
require_once 'database/config.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'core/libraries/sql.php';
$oSql = Sql::getInstance();

$action = $_POST['action'] ?? '';

switch ($action) {

    // =============================================
    // ZBIORCZY ENDPOINT DLA page-live.php
    // =============================================
    case 'get_all':
        // Timer
        $timeData = $oSql->getQuery("SELECT * FROM time WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $timer = ($timeData && $timeData['stop'] == 0) ? (int)(time() - $timeData['start_time'] + $timeData['time']) : 0;

        // Score
        $scoreData = $oSql->getQuery("SELECT * FROM score WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

        // Panels visibility
        $panelsResult = $oSql->getQuery("SELECT name, visible FROM info");
        $panels = [];
        while ($row = $panelsResult->fetch(PDO::FETCH_ASSOC)) {
            $panels[$row['name']] = (int)$row['visible'];
        }

        echo json_encode([
            'timer' => $timer,
            'half' => (int)($timeData['half'] ?? 1),
            'score' => ($scoreData['team_1'] ?? 0) . ":" . ($scoreData['team_2'] ?? 0),
            'panels' => $panels
        ]);
        break;

    // =============================================
    // TIMER
    // =============================================
    case 'get_timer':
        $data = $oSql->getQuery("SELECT * FROM time WHERE id = 1 AND stop = 0")->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'timer' => $data ? (int)(time() - $data['start_time'] + $data['time']) : 0,
            'half' => $data['half'] ?? 1
        ]);
        break;

    case 'start_1':
        $oSql->getQuery("UPDATE time SET time = 0, half = 1, stop = 0, start_time = " . time() . " WHERE id = 1");
        echo json_encode(['status' => 'started']);
        break;

    case 'start_2':
        $oSql->getQuery("UPDATE time SET time = 2700, half = 2, stop = 0, start_time = " . time() . " WHERE id = 1");
        echo json_encode(['status' => 'started']);
        break;

    case 'add_1':
        $oSql->getQuery("UPDATE time SET time = time + 60 WHERE id = 1");
        echo json_encode(['status' => 'updated']);
        break;

    case 'subtract_1':
        $oSql->getQuery("UPDATE time SET time = time - 60 WHERE id = 1");
        echo json_encode(['status' => 'updated']);
        break;

    case 'stop':
        $oSql->getQuery("UPDATE time SET stop = 1, half = 1 WHERE id = 1");
        echo json_encode(['status' => 'stopped']);
        break;

    // =============================================
    // EVENTY
    // =============================================
    case 'clear':
        $oSql->getQuery("DELETE FROM events");
        echo json_encode(['status' => 'cleared']);
        break;

    // =============================================
    // PLANSZE (INFOBOX)
    // =============================================
    case 'infoBox':
        $name = $_POST['name'];
        $visible = $_POST['visible'];

        // Jeśli włączamy planszę (nie "wynik") — ukryj wszystkie inne poza "wynik"
        if ($visible == 1 && $name != 'wynik') {
            $oSql->getQuery("UPDATE info SET visible = 0 WHERE name != 'wynik'");
        }

        $oSql->getQuery("UPDATE info SET visible = " . $visible . " WHERE name = '" . $name . "'");
        echo json_encode(['status' => 'updated']);
        break;

    case 'get_visibility':
        $name = $_POST['name'];
        $data = $oSql->getQuery("SELECT visible FROM info WHERE name = '" . $name . "'")->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['visible' => $data['visible'] ?? 0]);
        break;

    // =============================================
    // SCORE
    // =============================================
    case 'get_score':
        $data = $oSql->getQuery("SELECT * FROM score WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['score' => $data['team_1'] . ":" . $data['team_2']]);
        break;

    case 'update_score':
        $team = $_POST['team'];
        $modifier = $_POST['modifier'] === 'increase' ? "+ 1" : "- 1";
        $oSql->getQuery("UPDATE score SET team_{$team} = team_{$team} {$modifier} WHERE id = 1");
        echo json_encode(['status' => 'score_updated']);
        break;

    // =============================================
    // DEFAULT
    // =============================================
    default:
        echo json_encode(['status' => 'invalid_action']);
}
?>