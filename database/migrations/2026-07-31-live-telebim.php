<?php
/**
 * KOSA X CMS — Migracja: moduł transmisji live — TELEBIM
 *
 * Zakres:
 *  - zakładka „Telebim" (theme 13) — widok na ekran LED przy boisku
 *  - live_state.iReplayCount — licznik powtórek z OBS: panel inkrementuje
 *    (POST replay_show), telebim wykrywa zmianę i odtwarza powtórkę
 *    z lokalnego serwera replay buffera (plugins/live/replay/)
 *  - przebudowa cache routingu
 *
 * Skrypt IDEMPOTENTNY. Uruchamianie (tylko CLI):
 *   php database/migrations/2026-07-31-live-telebim.php
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
// 1. KOLUMNA LICZNIKA POWTÓREK
// ============================================================
$aExisting = [];
foreach ($oSql->getQuery("PRAGMA table_info('live_state')") as $aField) {
    $aExisting[$aField['name']] = true;
}
if (isset($aExisting['iReplayCount'])) {
    $aLog[] = 'kolumna live_state.iReplayCount — już istnieje, pomijam';
} else {
    $oSql->exec("ALTER TABLE live_state ADD COLUMN 'iReplayCount' INTEGER DEFAULT 0");
    $aLog[] = 'kolumna live_state.iReplayCount — DODANA';
}

// ============================================================
// 2. ZAKŁADKA „TELEBIM"
// ============================================================
$oCheck = $oSql->prepare('SELECT iPage FROM pages WHERE sUrl = :url AND sLang = :lang AND iPageParent = 0 LIMIT 1');
$oCheck->execute([':url' => 'telebim', ':lang' => $config['language']]);
$iTelebimPage = $oCheck->fetchColumn();

if ($iTelebimPage !== false) {
    $aLog[] = 'zakładka „Telebim" — już istnieje (iPage='.$iTelebimPage.'), pomijam';
    $iTelebimPage = (int) $iTelebimPage;
} else {
    // iMenu=3 „Systemowe" — nie wchodzi do publicznej nawigacji
    $oInsert = $oSql->prepare(
        'INSERT INTO pages (iPageParent, iStatus, iPosition, iMenu, sLang, sName, sTitle, sUrl, iTheme,
                            sDescriptionMeta, sDescriptionShort, sDescriptionFull, sDate, sType)
         VALUES (0, 1, 0, 3, :lang, "Telebim", "", "telebim", 13, "", "", "", :date, 1)'
    );
    $oInsert->execute([':lang' => $config['language'], ':date' => date('Y-m-d H:i')]);
    $iTelebimPage = (int) $oSql->lastInsertId();
    $aLog[] = 'zakładka „Telebim" — UTWORZONA (iPage='.$iTelebimPage.')';
}

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
echo "Migracja: moduł transmisji live — telebim\n";
echo str_repeat('-', 48)."\n";
foreach ($aLog as $sLine) {
    echo '  * '.$sLine."\n";
}
echo str_repeat('-', 48)."\n";
echo "Sprawdź w database/config_pl.php:\n";
echo "  \$config['telebim_page'] = \"{$iTelebimPage}\";\n";
