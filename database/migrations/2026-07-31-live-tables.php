<?php
/**
 * KOSA X CMS — Migracja: moduł transmisji live (TKS) — tabele + zakładki
 *
 * Zakres:
 *  - live_state  — 1 wiersz: drużyny meczu, wynik, zegar, połowa
 *  - live_events — zdarzenia meczowe (gol, kartka, zmiana), pobierane po ID
 *  - live_boards — plansze nakładki OBS (sterowane z panelu operatora)
 *  - zakładki: „Mecz" + „Panel meczowy" (theme 11) + „Nakładka OBS" (theme 12)
 *  - przebudowa cache routingu
 *
 * Zastępuje 4 tabele starego systemu (_old: time, score, events, info)
 * trzema czystszymi. Skrypt IDEMPOTENTNY — można uruchamiać wielokrotnie.
 *
 * Uruchamianie (tylko CLI):
 *   php database/migrations/2026-07-31-live-tables.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('MIGRATE_ROOT', realpath(__DIR__.'/../../'));
chdir(MIGRATE_ROOT);

require MIGRATE_ROOT.'/database/config.php';

// korekta base_path — jak w plugins/products/import.php
$config['base_path'] = preg_replace('#/database/migrations$#', '', (string)($config['base_path'] ?? ''));
$config['base_path_with_slash'] = ($config['base_path'] === '' ? '/' : rtrim($config['base_path'], '/').'/');

require_once MIGRATE_ROOT.'/core/libraries/sql.php';
$oSql = Sql::getInstance();

$aLog = [];

// ============================================================
// 1. TABELE LIVE
// ============================================================
$aTables = [
    // pojedynczy wiersz stanu meczu (id zawsze = 1)
    'live_state' => "CREATE TABLE 'live_state' (
        'id'          INTEGER PRIMARY KEY CHECK (id = 1),
        'iTeam1'      INTEGER DEFAULT 0,
        'iTeam2'      INTEGER DEFAULT 0,
        'iScore1'     INTEGER DEFAULT 0,
        'iScore2'     INTEGER DEFAULT 0,
        'iTimerBase'  INTEGER DEFAULT 0,
        'iTimerStart' INTEGER DEFAULT 0,
        'iTimerStop'  INTEGER DEFAULT 1,
        'iHalf'       INTEGER DEFAULT 1
    )",
    // zdarzenia meczowe — overlay pobiera po ID (?since=), nie po oknie czasowym
    'live_events' => "CREATE TABLE 'live_events' (
        'id'      INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        'iPlayer' INTEGER DEFAULT 0,
        'iTeam'   INTEGER DEFAULT 0,
        'sAction' TEXT NOT NULL,
        'sMinute' TEXT DEFAULT '',
        'iClock'  INTEGER DEFAULT 0
    )",
    // plansze nakładki — panel renderuje przyciski z tej tabeli
    'live_boards' => "CREATE TABLE 'live_boards' (
        'id'        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        'sName'     TEXT NOT NULL UNIQUE,
        'sLabel'    TEXT NOT NULL,
        'iVisible'  INTEGER DEFAULT 0,
        'iPosition' INTEGER DEFAULT 0
    )",
];

foreach ($aTables as $sTable => $sCreate) {
    $bExists = (bool) $oSql->getColumn(
        "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=".$oSql->quote($sTable)
    );
    if ($bExists) {
        $aLog[] = "tabela {$sTable} — już istnieje, pomijam";
    } else {
        $oSql->exec($sCreate);
        $aLog[] = "tabela {$sTable} — UTWORZONA";
    }
}

// wiersz stanu (id=1)
if ((int) $oSql->getColumn('SELECT COUNT(*) FROM live_state') === 0) {
    $oSql->exec('INSERT INTO live_state (id) VALUES (1)');
    $aLog[] = 'live_state — dodany wiersz stanu (id=1)';
}

// plansze startowe (jak w starym systemie + plakat); sName = id elementu
// w nakładce, sLabel = etykieta przycisku w panelu operatora
$aBoards = [
    ['wynik',                 'Wynik i czas',          1],
    ['dzien_meczowy',         'Dzień meczowy',         2],
    ['sklad_gospodarza',      'Skład gospodarza',      3],
    ['sklad_goscia',          'Skład gościa',          4],
    ['podsumowanie',          'Podsumowanie',          5],
    ['sponsorzy',             'Sponsorzy',             6],
    ['realizacja_transmisji', 'Realizacja transmisji', 7],
    ['plakat',                'Plakat meczowy',        8],
];
$oBoardCheck  = $oSql->prepare('SELECT COUNT(*) FROM live_boards WHERE sName = :name');
$oBoardInsert = $oSql->prepare('INSERT INTO live_boards (sName, sLabel, iVisible, iPosition) VALUES (:name, :label, 0, :pos)');
foreach ($aBoards as $aBoard) {
    $oBoardCheck->execute([':name' => $aBoard[0]]);
    if ((int) $oBoardCheck->fetchColumn() === 0) {
        $oBoardInsert->execute([':name' => $aBoard[0], ':label' => $aBoard[1], ':pos' => $aBoard[2]]);
        $aLog[] = 'plansza „'.$aBoard[1].'" — dodana';
    }
}

// ============================================================
// 2. ZAKŁADKI CMS
// ============================================================
function ensurePage(Sql $oSql, array $config, array $aPage, array &$aLog): int
{
    $oCheck = $oSql->prepare('SELECT iPage FROM pages WHERE sUrl = :url AND sLang = :lang AND iPageParent = :parent LIMIT 1');
    $oCheck->execute([
        ':url'    => $aPage['sUrl'],
        ':lang'   => $config['language'],
        ':parent' => $aPage['iPageParent'],
    ]);
    $iPage = $oCheck->fetchColumn();
    if ($iPage !== false) {
        $aLog[] = 'zakładka „'.$aPage['sName'].'" — już istnieje (iPage='.$iPage.'), pomijam';
        return (int)$iPage;
    }

    $oInsert = $oSql->prepare(
        'INSERT INTO pages (iPageParent, iStatus, iPosition, iMenu, sLang, sName, sTitle, sUrl, iTheme,
                            sDescriptionMeta, sDescriptionShort, sDescriptionFull, sDate, sType)
         VALUES (:parent, 1, :position, :menu, :lang, :name, "", :url, :theme, "", "", "", :date, 1)'
    );
    $oInsert->execute([
        ':parent'   => $aPage['iPageParent'],
        ':position' => $aPage['iPosition'],
        ':menu'     => $aPage['iMenu'],
        ':lang'     => $config['language'],
        ':name'     => $aPage['sName'],
        ':url'      => $aPage['sUrl'],
        ':theme'    => $aPage['iTheme'],
        ':date'     => date('Y-m-d H:i'),
    ]);
    $iPage = (int)$oSql->lastInsertId();
    $aLog[] = 'zakładka „'.$aPage['sName'].'" — UTWORZONA (iPage='.$iPage.')';
    return $iPage;
}

// iMenu=3 „Systemowe" — nie wchodzą do publicznej nawigacji
$iMatchPage = ensurePage($oSql, $config, [
    'sName' => 'Mecz', 'sUrl' => 'mecz',
    'iPageParent' => 0, 'iMenu' => 3, 'iPosition' => 0, 'iTheme' => 1,
], $aLog);

$iPanelPage = ensurePage($oSql, $config, [
    'sName' => 'Panel meczowy', 'sUrl' => 'panel-meczowy',
    'iPageParent' => 0, 'iMenu' => 3, 'iPosition' => 0, 'iTheme' => 11,
], $aLog);

$iOverlayPage = ensurePage($oSql, $config, [
    'sName' => 'Nakładka OBS', 'sUrl' => 'nakladka-obs',
    'iPageParent' => 0, 'iMenu' => 3, 'iPosition' => 0, 'iTheme' => 12,
], $aLog);

// ============================================================
// 3. PRZEBUDOWA CACHE ROUTINGU
// ============================================================
if (!defined('CUSTOMER_PAGE')) {
    define('CUSTOMER_PAGE', true);
}
require_once MIGRATE_ROOT.'/core/libraries/trash.php';
require_once MIGRATE_ROOT.'/core/pages.php';

@unlink(MIGRATE_ROOT.'/database/cache/links');
@unlink(MIGRATE_ROOT.'/database/cache/links_ids');
Pages::getInstance();
$aLog[] = 'cache routingu (database/cache/links*) — przebudowany';

// ============================================================
// PODSUMOWANIE
// ============================================================
echo "Migracja: moduł transmisji live — tabele + zakładki\n";
echo str_repeat('-', 52)."\n";
foreach ($aLog as $sLine) {
    echo '  * '.$sLine."\n";
}
echo str_repeat('-', 52)."\n";
echo "Sprawdź w database/config_pl.php:\n";
echo "  \$config['match_page']        = \"{$iMatchPage}\";\n";
echo "  \$config['live_panel_page']   = \"{$iPanelPage}\";\n";
echo "  \$config['live_overlay_page'] = \"{$iOverlayPage}\";\n";
