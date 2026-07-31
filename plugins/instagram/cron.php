<?php
/**
 * KOSA X InstaFeed — cron
 * ---------------------------------------------------------------------------
 * Odświeża feed wszystkich kont: refreshTokenIfNeeded → fetchFeed →
 * downloadMedia → saveFeed. Wynik loguje do cache/cron.log (rotacja > 500 KB).
 *
 * WYWOŁANIE (zabezpieczone $config['instagram_cron_secret'] z config_pl.php):
 *   https://twojadomena.pl/plugins/instagram/cron.php?key={CRON_SECRET}
 *
 * CRON HOSTINGU (zalecane co 6 h):
 *   0 *//*6 * * * curl -s "https://twojadomena.pl/plugins/instagram/cron.php?key=SEKRET" >/dev/null
 *   (albo CLI:  php /sciezka/plugins/instagram/cron.php SEKRET )
 * ---------------------------------------------------------------------------
 */

define('INSTAFEED_CRON', true);

// Konfiguracja InstaFeed żyje w database/config_pl.php (przez główny config.php).
// Bootstrap konfiguracji CMS, żeby uzyskać $config['instagram_accounts'].
if (!defined('CUSTOMER_PAGE')) {
    define('CUSTOMER_PAGE', true);
}
if (empty($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = '/'; // fallback dla CLI (getPageId w config.php)
}
chdir(__DIR__ . '/../../');                       // web root — config.php używa ścieżek względnych
require_once __DIR__ . '/../../database/config.php';

require __DIR__ . '/InstaFeed.class.php';

$feed   = new InstaFeed();
$secret = $feed->getCronSecret();

// Klucz z query string (web) albo z argumentu (CLI).
$key = $_GET['key'] ?? ($argv[1] ?? '');

// Sekret musi być ustawiony (nie placeholder) i zgadzać się w sposób timing-safe.
if ($secret === '' || $secret === 'CRON_SECRET_HERE' || !hash_equals($secret, (string) $key)) {
    http_response_code(403);
    exit('Forbidden');
}

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

// ---------------------------------------------------------------------------
// LOG + ROTACJA
// ---------------------------------------------------------------------------
$logFile = __DIR__ . '/cache/cron.log';
if (!is_dir(dirname($logFile))) {
    @mkdir(dirname($logFile), 0755, true);
}
if (is_file($logFile) && filesize($logFile) > 500 * 1024) {
    @rename($logFile, $logFile . '.1'); // trzymamy jedną poprzednią rotację
}

$log = static function (string $msg) use ($logFile): void {
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
};

// ---------------------------------------------------------------------------
// PRZEBIEG
// ---------------------------------------------------------------------------
$accounts = $feed->getAccountNames();
$log('START (' . count($accounts) . ' kont)');

$okCount = 0;
foreach ($accounts as $account) {
    $res  = $feed->updateAccount($account);
    $line = $res['ok']
        ? sprintf('OK   konto=%s postow=%d', $res['account'], $res['count'])
        : sprintf('BLAD konto=%s: %s', $res['account'], $res['error'] ?? 'nieznany');

    if ($res['ok']) {
        $okCount++;
    }
    $log($line);
    echo $line . "\n";
}

$log('KONIEC (' . $okCount . '/' . count($accounts) . ' OK)');
echo 'KONIEC (' . $okCount . '/' . count($accounts) . " OK)\n";
