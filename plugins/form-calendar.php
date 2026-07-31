 <?php
// plugins/form-calendar.php

// jeśli chcesz debugować – odkomentuj
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// przechodzimy do katalogu głównego CMS-a
chdir(__DIR__ . '/..');

require_once 'database/config.php';
require_once 'core/libraries/sql.php';
require_once 'plugins/settings.php';

$oSql = Sql::getInstance();

/**
 * Parsuje datę z bazy na DateTime (format YYYY-MM-DD).
 */
function fc_parse_date(string $date): ?DateTime {
    $date = trim($date);
    if ($date === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if ($dt instanceof DateTime) {
            $dt->setTime(0,0,0);
            return $dt;
        }
    }
    return null;
}

/**
 * Zwraca listę godzin dla konkretnego dnia tygodnia
 * używane tylko w trybie VISIT.
 */
function fc_get_hours_for_day(DateTime $date, array $config): array {
    // 1=pon ... 6=sob 7=nd
    $dow = (int)$date->format('N');

    // niedziela -> zamknięte
    if ($dow === 7) {
        return [];
    }

    // sobota
    if ($dow === 6) {
        if (!empty($config['calendar_hours_saturday']) && is_array($config['calendar_hours_saturday'])) {
            return $config['calendar_hours_saturday'];
        }
    }

    // pon-pt
    return $config['calendar_hours'] ?? [];
}

// tryb działania kalendarza
// - "range" (domyślnie): zakresy dat
// - "visit": pojedynczy dzień + godziny
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'range';

// dzisiejsza data – na jej podstawie blokujemy przeszłość
$today = new DateTime('today');
$minYear  = (int)$today->format('Y');
$minMonth = (int)$today->format('m');

// 1. rok / miesiąc z GET
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// 1a. NIE pozwalamy cofnąć się wcześniej niż bieżący miesiąc
if ($year < $minYear || ($year === $minYear && $month < $minMonth)) {
    $year  = $minYear;
    $month = $minMonth;
}

// poprzedni – ale TYLKO jeśli jest później niż minimalny
$prevYear  = $year;
$prevMonth = $month - 1;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

// następny – normalnie do przodu
$nextYear  = $year;
$nextMonth = $month + 1;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// nazwy miesięcy PL
$monthsPl = [
    1  => 'styczeń',
    2  => 'luty',
    3  => 'marzec',
    4  => 'kwiecień',
    5  => 'maj',
    6  => 'czerwiec',
    7  => 'lipiec',
    8  => 'sierpień',
    9  => 'wrzesień',
    10 => 'październik',
    11 => 'listopad',
    12 => 'grudzień',
];

// zakres miesiąca w DateTime
$monthStart = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month));
$monthStart->setTime(0,0,0);
$monthEnd = clone $monthStart;
$monthEnd->modify('last day of this month');

// ---------------------------------------------------------------------------------
// 2. POBIERAMY REZERWACJE
// ---------------------------------------------------------------------------------
if ($mode === 'visit') {
    // tryb WIZYTY – tylko zatwierdzone, tylko w tym miesiącu
    $stmt = $oSql->prepare("
        SELECT sDateStart, sTime
        FROM form
        WHERE iStatus = 2
          AND sDateStart >= :start
          AND sDateStart <= :end
    ");
    $stmt->execute([
        ':start' => $monthStart->format('Y-m-d'),
        ':end'   => $monthEnd->format('Y-m-d'),
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // struktura: dzień => [godziny...]
    $bookedByDay = [];
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $d = trim($row['sDateStart']);
            $t = trim($row['sTime']);
            if ($d === '' || $t === '') {
                continue;
            }
            if (!isset($bookedByDay[$d])) {
                $bookedByDay[$d] = [];
            }
            $bookedByDay[$d][] = $t;
        }
    }

} else {
    // tryb RANGE – bierzemy TYLKO rezerwacje "dniowe" (bez godziny!)
    try {
        $sql = "
            SELECT sDateStart, sDateEnd
            FROM form
            WHERE iStatus = 2
              AND (sTime IS NULL OR sTime = '')
        ";
        $stmt = $oSql->getQuery($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        echo '<div class="alert alert-danger">Błąd kalendarza: '.$e->getMessage().'</div>';
        exit;
    }

    // budujemy listę zajętych dni (YYYY-mm-dd => true)
    $busyDays = [];
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $dtStart = fc_parse_date($row['sDateStart'] ?? '');
            $dtEnd   = fc_parse_date($row['sDateEnd']   ?? '');

            if (!$dtStart) {
                continue;
            }
            if (!$dtEnd) {
                $dtEnd = clone $dtStart;
            }

            // czy ten zakres dotyka aktualnie wyświetlanego miesiąca?
            if ($dtEnd < $monthStart || $dtStart > $monthEnd) {
                continue;
            }

            // przycinamy do miesiąca
            $loopStart = $dtStart < $monthStart ? clone $monthStart : clone $dtStart;
            $loopEnd   = $dtEnd   > $monthEnd   ? clone $monthEnd   : clone $dtEnd;

            while ($loopStart <= $loopEnd) {
                $dayYmd = $loopStart->format('Y-m-d');
                $busyDays[$dayYmd] = true;
                $loopStart->modify('+1 day');
            }
        }
    }
}

// 4. dane do renderu kalendarza
$firstDayTs   = strtotime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth  = (int)date('t', $firstDayTs);
$firstWeekday = (int)date('N', $firstDayTs); // 1=pn
$weekdays     = ['Pn','Wt','Śr','Cz','Pt','So','Nd'];

// czy można iść w lewo?
$canGoPrev = !($year === $minYear && $month === $minMonth);
?>
<div class="card">
   <header class="card__header">
    <h5 class="card__title">
        <div class="pageFormCalendar__nav">
            <?php if ($canGoPrev): ?>
                <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>&mode=<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>" class="calendar-prev button button-xs button-light">
                    <img src="<?php echo ICONS; ?>arrow.svg" class="invert" alt="Poprzedni miesiąc">
                </a>
            <?php else: ?>
                <span class="calendar-prev button button-xs button-light disabled" aria-disabled="true">
                    <img src="<?php echo ICONS; ?>arrow.svg" class="invert" alt="Poprzedni miesiąc">
                </span>
            <?php endif; ?>

            <span class="calendar-title">
                <img src="<?php echo ICONS; ?>calendar.svg" class="invert" alt="Kalendarz dostępności">
                <?php echo $monthsPl[$month].' '.$year; ?>
            </span>

            <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>&mode=<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>" class="calendar-next button button-xs button-light">
                <img src="<?php echo ICONS; ?>arrow.svg" class="invert" alt="Następny miesiąc">
            </a>
        </div>
    </h5>
    </header>
    <div class="card__content">

        <table class="pageFormCalendar">
            <thead>
            <tr>
                <?php foreach ($weekdays as $wd): ?>
                    <th><?php echo $wd; ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <tr>
            <?php
            // puste komórki przed 1. dniem
            for ($i = 1; $i < $firstWeekday; $i++) {
                echo '<td class="empty"></td>';
            }

            $dayCounter = 1;
            $cellPos    = $firstWeekday;

            while ($dayCounter <= $daysInMonth) {
                $currentYmd = sprintf('%04d-%02d-%02d', $year, $month, $dayCounter);
                $cellDate   = DateTime::createFromFormat('Y-m-d', $currentYmd);
                $isPast     = $cellDate < $today;
                $dow        = (int)$cellDate->format('N'); // 1=pn ... 7=nd

                $classes = ['day'];

                if ($mode === 'visit') {
                    // VISIT – niedziela wyłączona
                    if ($dow === 7) {
                        $classes[] = 'day-sunday';
                        $classes[] = 'day-busy';
                        echo '<td class="'.implode(' ', $classes).'" data-date="'.$currentYmd.'"><span class="day-circle">'.$dayCounter.'</span></td>';
                    } else {
                        $allHoursForThisDay = fc_get_hours_for_day($cellDate, $config);
                        $capacity = count($allHoursForThisDay);

                        // policz ile godzin faktycznie jest zajętych
                        $bookedCount = 0;
                        if (isset($bookedByDay[$currentYmd])) {
                            foreach ($bookedByDay[$currentYmd] as $h) {
                                if (in_array($h, $allHoursForThisDay, true)) {
                                    $bookedCount++;
                                }
                            }
                        }
                        $isFull = ($capacity > 0 && $bookedCount >= $capacity);

                        if ($isPast) {
                            $classes[] = 'day-past';
                            echo '<td class="'.implode(' ', $classes).'" data-date="'.$currentYmd.'"><span class="day-circle">'.$dayCounter.'</span></td>';
                        } elseif ($capacity === 0) {
                            // dzień bez godzin = niedostępny
                            $classes[] = 'day-busy';
                            echo '<td class="'.implode(' ', $classes).'" data-date="'.$currentYmd.'"><span class="day-circle">'.$dayCounter.'</span></td>';
                        } elseif ($isFull) {
                            // wszystkie sloty zajęte
                            $classes[] = 'day-busy';
                            echo '<td class="'.implode(' ', $classes).'" data-date="'.$currentYmd.'"><span class="day-circle">'.$dayCounter.'</span></td>';
                        } else {
                            // można klikać
                            $classes[] = 'day-free';
                            $id = 'cal-'.$currentYmd;
                            echo '<td class="'.implode(' ', $classes).'" data-date="'.$currentYmd.'">';
                            echo '<a href="#" class="day-circle day-link" id="'.$id.'" data-date="'.$currentYmd.'">'.$dayCounter.'</a>';
                            echo '</td>';
                        }
                    }
                } else {
                    // RANGE – niedziela = NORMALNY dzień
                    $isBusy = isset($busyDays[$currentYmd]);

                    if ($isPast) {
                        $classes[] = 'day-past';
                        echo '<td class="'.implode(' ', $classes).'" data-date="'.$currentYmd.'"><span class="day-circle">'.$dayCounter.'</span></td>';
                    } else {
                        $classes[] = $isBusy ? 'day-busy' : 'day-free';
                        echo '<td class="'.implode(' ', $classes).'" data-date="'.$currentYmd.'">';
                        if ($isBusy) {
                            echo '<span class="day-circle">'.$dayCounter.'</span>';
                        } else {
                            $id = 'cal-'.$currentYmd;
                            echo '<a href="#" class="day-circle day-link" id="'.$id.'" data-date="'.$currentYmd.'">'.$dayCounter.'</a>';
                        }
                        echo '</td>';
                    }
                }

                if ($cellPos % 7 == 0) {
                    echo '</tr><tr>';
                }

                $dayCounter++;
                $cellPos++;
            }

            // dopełnienie wiersza
            while (($cellPos - 1) % 7 != 0) {
                echo '<td class="empty"></td>';
                $cellPos++;
            }
            ?>
            </tr>
            </tbody>
        </table>
        
         <div class="mt-2" id="date-reset-wrapper" style="display:none;">
            <button type="button" class="button button-xs button-light" id="date-reset-btn">Resetuj zaznaczenie</button>
        </div>

        <div class="pageFormCalendar__head">
            <div class="pageFormCalendar__legend">
                <span class="legend-item legend-busy">Niedostępne</span>
                <span class="legend-item legend-yours">Twoja rezerwacja</span>
            </div>
        </div>
    </div>
</div>
