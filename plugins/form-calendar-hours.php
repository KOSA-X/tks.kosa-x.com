<?php
// plugins/form-calendar-hours.php

//ini_set('display_errors', 1);
//error_reporting(E_ALL);

// przechodzimy do katalogu głównego
chdir(__DIR__ . '/..');

require_once 'database/config.php';
require_once 'core/libraries/sql.php';

$oSql = Sql::getInstance();

header('Content-Type: application/json; charset=utf-8');

$date = isset($_GET['date']) ? trim($_GET['date']) : '';
if ($date === '') {
    echo json_encode([
        'available' => [],
        'booked'    => [],
    ]);
    exit;
}

$dt  = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt) {
    echo json_encode([
        'available' => [],
        'booked'    => [],
    ]);
    exit;
}
$dow = (int)$dt->format('N'); // 1=pn ... 6=sob 7=nd

// NIEDZIELA -> zamknięte
if ($dow === 7) {
    echo json_encode([
        'available' => [],
        'booked'    => [],
        'closed'    => true,
        'reason'    => 'Sunday closed',
    ]);
    exit;
}

// wybór listy godzin
if ($dow === 6) {
    // SOBOTA
    $allHours = $config['calendar_hours_saturday'] ?? ($config['calendar_hours'] ?? []);
} else {
    // PN-PT
    $allHours = $config['calendar_hours'] ?? [];
}

// pobieramy zajęte godziny na ten dzień (tylko zatwierdzone!)
$stmt = $oSql->prepare("
    SELECT sTime 
    FROM form
    WHERE iStatus = 2
      AND sDateStart = :date
      AND sTime IS NOT NULL
      AND sTime != ''
");
$stmt->execute([
    ':date' => $date,
]);
$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

$booked = [];
if (!empty($rows)) {
    foreach ($rows as $h) {
        if (in_array($h, $allHours, true)) {
            $booked[] = $h;
        }
    }
}

// dostępne = wszystkie - zajęte
$available = array_values(array_diff($allHours, $booked));

echo json_encode([
    'available' => $available,
    'booked'    => $booked,
    'closed'    => false,
]);
exit;
