<?php
/**
 * KOSA X CMS — Migracja: moduł transmisji live (TKS Tomasovia) — Etap 1
 *
 * Zakres:
 *  - pages.sNumber TEXT DEFAULT ''  → numer zawodnika na koszulce
 *  - pages.sSquad  TEXT DEFAULT ''  → skład meczowy (klucz z $config['squad_types'], '' = poza kadrą)
 *  - zakładka „Drużyny" (iMenu=3 Systemowe) + podstrona „TKS Tomasovia"
 *  - przebudowa cache routingu (database/cache/links*)
 *
 * Skrypt jest IDEMPOTENTNY — wielokrotne uruchomienie niczego nie psuje.
 * Po uruchomieniu na NOWEJ bazie sprawdź, czy ID zakładki „Drużyny" zgadza się
 * z $config['teams_page'] w database/config_pl.php (skrypt wypisuje ID).
 *
 * Uruchamianie (tylko CLI — katalog database/ jest zablokowany przez .htaccess):
 *   php database/migrations/2026-07-31-live-schema.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('MIGRATE_ROOT', realpath(__DIR__.'/../../'));
chdir(MIGRATE_ROOT);

require MIGRATE_ROOT.'/database/config.php'; // → $config (+ config_pl.php + lang_pl.php)

// KOREKTA base_path: config.php wylicza go ze ścieżki SKRYPTU (tu:
// /database/migrations), a cache routingu musi być budowany względem
// KATALOGU GŁÓWNEGO strony (wzorzec: plugins/products/import.php).
$config['base_path'] = preg_replace('#/database/migrations$#', '', (string)($config['base_path'] ?? ''));
$config['base_path_with_slash'] = ($config['base_path'] === '' ? '/' : rtrim($config['base_path'], '/').'/');

require_once MIGRATE_ROOT.'/core/libraries/sql.php';
$oSql = Sql::getInstance();

$aLog = [];

// ============================================================
// 1. NOWE KOLUMNY W TABELI pages
// ============================================================
$aExisting = [];
foreach ($oSql->getQuery("PRAGMA table_info('pages')") as $aField) {
    $aExisting[$aField['name']] = true;
}

$aNewColumns = [
    'sNumber' => "ALTER TABLE pages ADD COLUMN 'sNumber' TEXT DEFAULT ''", // numer na koszulce
    'sSquad'  => "ALTER TABLE pages ADD COLUMN 'sSquad' TEXT DEFAULT ''",  // skład: klucz z $config['squad_types']
];

foreach ($aNewColumns as $sColumn => $sQuery) {
    if (isset($aExisting[$sColumn])) {
        $aLog[] = "kolumna pages.{$sColumn} — już istnieje, pomijam";
    } else {
        $oSql->exec($sQuery);
        $aLog[] = "kolumna pages.{$sColumn} — DODANA";
    }
}

// ============================================================
// 2. ZAKŁADKI: „Drużyny" (rodzic) + „TKS Tomasovia" (drużyna)
// ============================================================

/**
 * Zwraca ID istniejącej strony (po sUrl + sLang + rodzicu) albo tworzy nową.
 */
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
         VALUES (:parent, 1, :position, :menu, :lang, :name, "", :url, 1, "", "", "", :date, 1)'
    );
    $oInsert->execute([
        ':parent'   => $aPage['iPageParent'],
        ':position' => $aPage['iPosition'],
        ':menu'     => $aPage['iMenu'],
        ':lang'     => $config['language'],
        ':name'     => $aPage['sName'],
        ':url'      => $aPage['sUrl'],
        ':date'     => date('Y-m-d H:i'),
    ]);
    $iPage = (int)$oSql->lastInsertId();
    $aLog[] = 'zakładka „'.$aPage['sName'].'" — UTWORZONA (iPage='.$iPage.')';
    return $iPage;
}

// iMenu=3 „Systemowe" — jak Mapa strony / Regulamin: strona istnieje, ale nie
// pojawia się w nawigacji publicznej
$iTeamsPage = ensurePage($oSql, $config, [
    'sName'       => 'Drużyny',
    'sUrl'        => 'druzyny',
    'iPageParent' => 0,
    'iMenu'       => 3,
    'iPosition'   => 0,
], $aLog);

$iTksPage = ensurePage($oSql, $config, [
    'sName'       => 'TKS Tomasovia',
    'sUrl'        => 'tks-tomasovia',
    'iPageParent' => $iTeamsPage,
    'iMenu'       => 3,
    'iPosition'   => 1,
], $aLog);

// ============================================================
// 3. PRZEBUDOWA CACHE ROUTINGU
// ============================================================
// UWAGA: samo usunięcie plików cache wyłącza front (getPageId robi exit),
// dlatego od razu generujemy je na nowo silnikiem CMS (wzorzec:
// plugins/products/import.php → rebuildRouteCache()).
if (!defined('CUSTOMER_PAGE')) {
    define('CUSTOMER_PAGE', true); // wymagane przez core/pages.php
}
require_once MIGRATE_ROOT.'/core/libraries/trash.php'; // change2Url()
require_once MIGRATE_ROOT.'/core/pages.php';           // klasa Pages

@unlink(MIGRATE_ROOT.'/database/cache/links');
@unlink(MIGRATE_ROOT.'/database/cache/links_ids');
Pages::getInstance(); // konstruktor → generateCache → generateLinks
$aLog[] = 'cache routingu (database/cache/links*) — przebudowany';

// ============================================================
// PODSUMOWANIE
// ============================================================
echo "Migracja: moduł transmisji live — Etap 1\n";
echo str_repeat('-', 48)."\n";
foreach ($aLog as $sLine) {
    echo '  * '.$sLine."\n";
}
echo str_repeat('-', 48)."\n";
echo "Sprawdź w database/config_pl.php:\n";
echo "  \$config['teams_page'] = \"{$iTeamsPage}\";  (zakładka „Drużyny\")\n";
