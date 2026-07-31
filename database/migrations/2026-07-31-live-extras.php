<?php
/**
 * KOSA X CMS — Migracja: moduł transmisji live — rozszerzenia (szlif)
 *
 * Zakres:
 *  - plansza „Sędziowie" w live_boards (treść: opis SKRÓCONY strony „Mecz")
 *  - live_state.iScorebarPos — pozycja paska wyniku na nakładce (0 = lewy
 *    górny róg, 1 = prawy górny róg), przełączana z panelu operatora
 *
 * Skrypt IDEMPOTENTNY. Uruchamianie (tylko CLI):
 *   php database/migrations/2026-07-31-live-extras.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('MIGRATE_ROOT', realpath(__DIR__.'/../../'));
chdir(MIGRATE_ROOT);

require MIGRATE_ROOT.'/database/config.php';
require_once MIGRATE_ROOT.'/core/libraries/sql.php';
$oSql = Sql::getInstance();

$aLog = [];

// kolumna pozycji paska wyniku
$aExisting = [];
foreach ($oSql->getQuery("PRAGMA table_info('live_state')") as $aField) {
    $aExisting[$aField['name']] = true;
}
if (isset($aExisting['iScorebarPos'])) {
    $aLog[] = 'kolumna live_state.iScorebarPos — już istnieje, pomijam';
} else {
    $oSql->exec("ALTER TABLE live_state ADD COLUMN 'iScorebarPos' INTEGER DEFAULT 0");
    $aLog[] = 'kolumna live_state.iScorebarPos — DODANA';
}

// plansza „Sędziowie"
$oCheck = $oSql->prepare('SELECT COUNT(*) FROM live_boards WHERE sName = :name');
$oCheck->execute([':name' => 'sedziowie']);
if ((int) $oCheck->fetchColumn() === 0) {
    $iPosition = (int) $oSql->getColumn('SELECT COALESCE(MAX(iPosition), 0) + 1 FROM live_boards');
    $oInsert = $oSql->prepare('INSERT INTO live_boards (sName, sLabel, iVisible, iPosition) VALUES ("sedziowie", "Sędziowie", 0, :pos)');
    $oInsert->execute([':pos' => $iPosition]);
    $aLog[] = 'plansza „Sędziowie" — dodana (iPosition='.$iPosition.')';
} else {
    $aLog[] = 'plansza „Sędziowie" — już istnieje, pomijam';
}

echo "Migracja: moduł transmisji live — rozszerzenia\n";
echo str_repeat('-', 48)."\n";
foreach ($aLog as $sLine) {
    echo '  * '.$sLine."\n";
}
