<?php
/**
 * KOSA X CMS — Import felg od dostawców (panel + silnik)
 *
 * Źródła (plugins/products/):
 *   felgeo.csv        (CSV ;)   → tylko marki: Carbonado, Seventy9        [PLN]
 *   wheeltrade.csv    (CSV ;)   → tylko marki: JR Wheels, Vesser, Concaver [PLN]
 *   forzza.csv        (CSV TAB) → wszystkie felgi Forzza                   [EUR]
 *   zperformance.xls  (CSV ,)   → wszystkie felgi Z-Performance            [EUR]
 *   motec.xls         (XLS bin) → wszystkie felgi Motec                    [EUR]
 *   wheelforce.xlsx   (XLSX)    → felgi WheelForce (bez akcesoriów)        [EUR]
 *
 * Zasady:
 *  - każdy produkt ma unikalny sSKU ({PREFIKS}-{kod dostawcy}) — UPSERT po sSKU
 *  - kategorie sklepu = zakładki per marka (iMenu=2), tworzone automatycznie
 *  - filtry w plugins/shop_filters.php mają STABILNE ID wartości (append-only),
 *    wpisy spoza zarządzanych kluczy (np. portfolio) są zachowywane
 *  - ceny 1:1 z plików, waluta zapisywana per produkt (sCurrency)
 *  - zdjęcia pobierane na serwer do files/ + miniatury (500, 920)
 *
 * Uruchamianie:
 *   WWW: /plugins/products/import.php  (wymaga zalogowanego admina)
 *   CLI: php plugins/products/import.php --source=felgeo [--limit=10] [--dry] [--photos]
 */

declare(strict_types=1);

define('IMPORT_ROOT', realpath(__DIR__.'/../../'));
define('IMPORT_DIR', __DIR__);
define('IMPORT_CACHE_DIR', __DIR__.'/.cache');
define('DB_PATH', IMPORT_ROOT.'/database/database.db');
define('FILTERS_PATH', IMPORT_ROOT.'/plugins/shop_filters.php');
define('FILES_DIR', IMPORT_ROOT.'/files');
define('CACHE_LINKS', IMPORT_ROOT.'/database/cache/links');
define('CACHE_LINKS_IDS', IMPORT_ROOT.'/database/cache/links_ids');
define('PHOTO_TIME_BUDGET', 20);   // sekundy na pobieranie zdjęć w jednym żądaniu WWW
define('IS_CLI', php_sapi_name() === 'cli');

require_once __DIR__.'/lib/XlsReader.php';
require_once __DIR__.'/lib/XlsxReader.php';

chdir(IMPORT_ROOT);

// ============================================================
// KONFIG CMS + FILTRY (config.php includuje shop_filters.php)
// ============================================================
require IMPORT_ROOT.'/database/config.php';      // → $config
require IMPORT_ROOT.'/database/config_pl.php';   // → $config['*_page'] itd.
/** @var array $config */
/** @var array $filters */

// KOREKTA base_path: config.php wylicza go z lokalizacji SKRYPTU (tu:
// /plugins/products), a cache routingu musi być budowany względem KATALOGU
// GŁÓWNEGO strony — inaczej wygenerowane linki dostają zły prefiks.
$config['base_path'] = preg_replace('#/plugins/products$#', '', (string)($config['base_path'] ?? ''));
$config['base_path_with_slash'] = ($config['base_path'] === '' ? '/' : rtrim($config['base_path'], '/').'/');

// ============================================================
// AUTORYZACJA — tylko admin (WWW) lub CLI
// ============================================================
if (!IS_CLI) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // klucz sesji admina jest dynamiczny — CMS trzyma go w tabeli bin
    // (core/common.php → getBinValues), stamtąd też go odczytujemy
    $sessionKey = $config['session_key_name'] ?? null;
    if ($sessionKey === null) {
        try {
            $pdoAuth = new PDO('sqlite:'.DB_PATH);
            $stmt = $pdoAuth->query("SELECT sValue FROM bin WHERE sKey = 'session_key_name' LIMIT 1");
            $val = $stmt ? $stmt->fetchColumn() : false;
            $sessionKey = ($val !== false && $val !== null) ? (string)$val : null;
            $pdoAuth = null;
        } catch (Throwable $e) {
            $sessionKey = null;
        }
    }
    if ($sessionKey === null || !isset($_SESSION[$sessionKey]) || !is_int($_SESSION[$sessionKey])) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;background:#0a0a0c;color:#eee;display:flex;align-items:center;justify-content:center;height:100vh">'
           . '<div style="text-align:center"><h2>Brak dostępu</h2><p>Import produktów wymaga zalogowania do panelu administracyjnego.</p>'
           . '<p><a href="'.htmlspecialchars(($config['admin_file'] ?? 'admin.php'), ENT_QUOTES).'" style="color:#d00e1c">Przejdź do logowania →</a></p></div></body>';
        exit;
    }
    // token CSRF dla akcji
    if (empty($_SESSION['import_csrf'])) {
        $_SESSION['import_csrf'] = bin2hex(random_bytes(24));
    }
}

// ============================================================
// REJESTR ŹRÓDEŁ
// ============================================================
const SOURCES = [
    'felgeo' => [
        'label'  => 'Felgeo',
        'file'   => 'felgeo.csv',
        'parser' => 'parseFelgeo',
        'note'   => 'tylko: Carbonado, Seventy9 · ceny PLN',
    ],
    'wheeltrade' => [
        'label'  => 'WheelTrade',
        'file'   => 'wheeltrade.csv',
        'parser' => 'parseWheelTrade',
        'note'   => 'tylko: JR Wheels, Vesser, Concaver · ceny PLN',
    ],
    'forzza' => [
        'label'  => 'Forzza',
        'file'   => 'forzza.csv',
        'parser' => 'parseForzza',
        'note'   => 'wszystkie felgi · ceny EUR',
    ],
    'zperformance' => [
        'label'  => 'Z-Performance',
        'file'   => 'zperformance.xls',
        'parser' => 'parseZPerformance',
        'note'   => 'wszystkie felgi · ceny EUR',
    ],
    'motec' => [
        'label'  => 'Motec',
        'file'   => 'motec.xls',
        'parser' => 'parseMotec',
        'note'   => 'wszystkie felgi · ceny EUR · bez zdjęć i stanów w pliku',
    ],
    'wheelforce' => [
        'label'  => 'WheelForce',
        'file'   => 'wheelforce.xlsx',
        'parser' => 'parseWheelforce',
        'note'   => 'felgi (bez akcesoriów) · ceny EUR · bez zdjęć i stanów w pliku',
    ],
];

// klucze filtrów zarządzane przez import (reszta, np. portfolio, zostaje nietknięta)
const MANAGED_FILTER_KEYS = ['brand', 'size', 'width', 'pcd', 'et', 'color', 'technology'];

const FILTER_LABELS = [
    'brand'      => 'Marka',
    'size'       => 'Rozmiar',
    'width'      => 'Szerokość',
    'pcd'        => 'Rozstaw śrub',
    'et'         => 'Offset ET',
    'color'      => 'Kolor',
    'technology' => 'Technologia',
];

// ============================================================
// NORMALIZACJA: KOLORY (grupowanie odcieni → kolor bazowy PL)
// ============================================================

/** dokładne dopasowania (lowercase, po zdjęciu ozdobników) */
const COLOR_EXACT = [
    'czarny' => 'Czarny', 'biały' => 'Biały', 'srebrny' => 'Srebrny', 'szary' => 'Szary',
    'grafitowy' => 'Grafitowy', 'brązowy' => 'Brązowy', 'złoty' => 'Złoty', 'chrom' => 'Chrom',
    'kolorowe' => 'Kolorowe', 'tytanowy' => 'Tytanowy', 'miedziany' => 'Miedziany',
    'czerwony' => 'Czerwony', 'niebieski' => 'Niebieski', 'fioletowy' => 'Fioletowy',
];

/** słowa-klucze — o wyniku decyduje pierwsze wystąpienie w tekście */
const COLOR_KEYWORDS = [
    'czarn'      => 'Czarny',   'black'    => 'Czarny',   'schwarz'  => 'Czarny',
    'srebr'      => 'Srebrny',  'silver'   => 'Srebrny',  'silber'   => 'Srebrny',
    'alumin'     => 'Srebrny',  'rhodium'  => 'Srebrny',  'rhoduim'  => 'Srebrny',
    'crystal'    => 'Srebrny',
    'grafit'     => 'Grafitowy','graphit'  => 'Grafitowy','gunmetal' => 'Grafitowy',
    'gun metal'  => 'Grafitowy','gun '     => 'Grafitowy','anthracite' => 'Grafitowy',
    'szar'       => 'Szary',    'grey'     => 'Szary',    'gray'     => 'Szary',
    'grau'       => 'Szary',    'steel'    => 'Szary',    'steelgrey'=> 'Szary',
    'brąz'       => 'Brązowy',  'bronze'   => 'Brązowy',  'americano'=> 'Brązowy',
    'brown'      => 'Brązowy',
    'złot'       => 'Złoty',    'gold'     => 'Złoty',    'champagne'=> 'Złoty',
    'amber'      => 'Złoty',
    'miedz'      => 'Miedziany','copper'   => 'Miedziany',
    'tytan'      => 'Tytanowy', 'titan'    => 'Tytanowy',
    'czerwon'    => 'Czerwony', 'red'      => 'Czerwony', 'blood'    => 'Czerwony',
    'biał'       => 'Biały',    'white'    => 'Biały',    'weiss'    => 'Biały',
    'niebiesk'   => 'Niebieski','blue'     => 'Niebieski',
    'fiolet'     => 'Fioletowy','purple'   => 'Fioletowy','violet'   => 'Fioletowy',
    'chrom'      => 'Chrom',    'chrome'   => 'Chrom',
];

function normalizeColor(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return 'Inne';
    }
    // wiele wartości "a;b" → pierwsza
    if (str_contains($raw, ';')) {
        $raw = trim(explode(';', $raw)[0]);
    }

    $t = mb_strtolower($raw);
    // separatory wariantów ("Gun/Lip Machined", "BFM + Black Clear Coat") → spacje
    $t = str_replace(['/', '+', '_'], ' ', $t);
    // zdejmij ozdobniki dostawców (nie niosą koloru)
    $t = str_replace(
        ['flowforged', 'flow forged', '(custom finish)', 'custom finish', 'deep concave', 'super deep', 'concave'],
        ' ',
        $t
    );
    $t = trim(preg_replace('/\s+/', ' ', $t));

    if ($t === '') {
        return 'Inne'; // np. samo "Custom Finish"
    }
    if (isset(COLOR_EXACT[$t])) {
        return COLOR_EXACT[$t];
    }

    // pierwsze słowo-klucz wg pozycji w tekście
    $best = null;
    $bestPos = PHP_INT_MAX;
    foreach (COLOR_KEYWORDS as $kw => $color) {
        $pos = mb_strpos($t, $kw);
        if ($pos !== false && $pos < $bestPos) {
            $bestPos = $pos;
            $best = $color;
        }
    }

    return $best ?? 'Inne';
}

// ============================================================
// NORMALIZACJA: POZOSTAŁE
// ============================================================

// ---- Zakresy sensowności parametrów technicznych felg ----
// Wartości spoza zakresów to śmieci w plikach dostawców (np. puste drugie
// PCD zapisane jako "0.00" + Holes2 "0" → "0x0") — takie POMIJAMY.
const RANGE_SIZE      = [10, 26];    // średnica felgi [cale]
const RANGE_WIDTH     = [3, 18];     // szerokość [J]
const RANGE_ET        = [-70, 100];  // offset [mm]
const RANGE_PCD_HOLES = [3, 10];     // liczba otworów
const RANGE_PCD_DIA   = [70, 250];   // średnica rozstawu [mm]
const RANGE_CB        = [40, 130];   // otwór centrujący [mm]

function inRange(float $v, array $range): bool
{
    return $v >= $range[0] && $v <= $range[1];
}

function normSize(string $s): string
{
    $s = trim(str_replace(['"', "''"], '', $s));
    if ($s === '' || !is_numeric(str_replace(',', '.', $s))) {
        return '';
    }
    $v = (int)round((float)str_replace(',', '.', $s));
    return inRange($v, RANGE_SIZE) ? (string)$v : '';
}

function normWidth(string $w): string
{
    $w = trim(str_replace(['"', 'J', 'j', ','], ['', '', '', '.'], $w));
    if ($w === '' || !is_numeric($w)) {
        return '';
    }
    $v = (float)$w;
    return inRange($v, RANGE_WIDTH) ? number_format($v, 1, '.', '') : '';
}

/** waliduje pojedynczy rozstaw: otwory 3–10, średnica 70–250 mm */
function validPcd(int $holes, float $dia): bool
{
    return inRange((float)$holes, RANGE_PCD_HOLES) && inRange($dia, RANGE_PCD_DIA);
}

/** "5/120", "5x114,3", "5x108+120", "4/100" → lista "5x120"; śmieci (0x0) odpadają */
function normPcdList(string $raw, string $holes = ''): array
{
    $out = [];
    $raw = trim($raw);
    if ($raw === '') {
        return $out;
    }
    foreach (preg_split('/[,;]\s*/', $raw) as $part) {
        $part = trim(str_replace(['/', 'X', ' '], ['x', 'x', ''], $part));
        if ($part === '') {
            continue;
        }
        // "5x108+120" → 5x108, 5x120
        if (preg_match('/^(\d+)x([\d.]+)\+([\d.]+)$/', str_replace(',', '.', $part), $m)) {
            foreach ([$m[2], $m[3]] as $dia) {
                if (validPcd((int)$m[1], (float)$dia)) {
                    $out[] = $m[1].'x'.rtrim($dia, '.');
                }
            }
            continue;
        }
        $part = str_replace(',', '.', $part);
        if (preg_match('/^(\d+)x([\d.]+)$/', $part, $m)) {
            if (validPcd((int)$m[1], (float)$m[2])) {
                $out[] = rtrim($part, '.');
            }
        } elseif ($holes !== '' && is_numeric($part)) {
            // sama średnica (np. forzza PCD=112.00 + Holes1=5)
            if (validPcd((int)$holes, (float)$part)) {
                $out[] = (int)$holes.'x'.rtrim(rtrim(number_format((float)$part, 2, '.', ''), '0'), '.');
            }
        }
    }
    return array_values(array_unique($out));
}

/** "20, 21, 22" / "35" → lista wartości całkowitych jako stringi (w zakresie ET) */
function normEtList(string $raw): array
{
    $out = [];
    foreach (preg_split('/[,;]\s*/', trim($raw)) as $v) {
        $v = trim($v);
        if ($v !== '' && is_numeric(str_replace(',', '.', $v))) {
            $et = (int)round((float)str_replace(',', '.', $v));
            if (inRange((float)$et, RANGE_ET)) {
                $out[] = (string)$et;
            }
        }
    }
    return array_values(array_unique($out));
}

/**
 * Otwór centrujący — tylko sensowne wartości mm.
 * Obsługuje pojedynczą wartość z przecinkiem dziesiętnym ("66,5" — felgeo,
 * motec) oraz listy (felgi BLANK mają wiele CB): "57.1, 58.1, 0" → "57.1, 58.1".
 */
function normCb(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    // pojedyncza wartość z przecinkiem dziesiętnym — zamień PRZED splitem listy
    if (preg_match('/^\d{2,3},\d{1,2}$/', $raw)) {
        $raw = str_replace(',', '.', $raw);
    }
    $valid = [];
    foreach (preg_split('/[,;]\s*/', $raw) as $part) {
        $part = trim($part);
        if ($part === '' || !is_numeric($part)) {
            continue;
        }
        $v = (float)$part;
        if (inRange($v, RANGE_CB)) {
            $valid[] = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        }
    }
    return implode(', ', array_unique($valid));
}

/** maks. obciążenie [kg] — odrzuca zera i śmieci */
function normLoad(string $raw): string
{
    $raw = trim(str_ireplace([' kg', 'kg', '-'], '', str_replace(',', '.', $raw)));
    if ($raw === '' || !is_numeric($raw)) {
        return '';
    }
    $v = (float)$raw;
    return ($v >= 100 && $v <= 3000) ? (string)(int)round($v) : '';
}

function normPrice(string $raw): string
{
    $raw = trim(str_replace([' ', "\xC2\xA0"], '', $raw));
    $raw = str_replace(',', '.', $raw);
    if ($raw === '' || !is_numeric($raw) || (float)$raw <= 0) {
        return '';
    }
    return number_format((float)$raw, 2, '.', '');
}

function normTechnology(string $raw): string
{
    $t = mb_strtolower(trim($raw));
    if ($t === '') {
        return '';
    }
    if (str_contains($t, 'flow')) {
        return 'Flow Forged';
    }
    if (str_contains($t, 'forg') || str_contains($t, 'kut')) {
        return 'Forged';
    }
    if (str_contains($t, 'cast') || str_contains($t, 'odlew') || str_contains($t, 'gravity') || str_contains($t, 'low pressure')) {
        return 'Cast';
    }
    return trim($raw);
}

function cleanName(string $name): string
{
    $name = str_replace('""', '"', $name);
    $name = preg_replace('/^Alloy Wheels\s+/i', '', $name);
    return trim(preg_replace('/\s+/', ' ', $name));
}

function slugify(string $text): string
{
    $map = ['ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
            'Ą'=>'a','Ć'=>'c','Ę'=>'e','Ł'=>'l','Ń'=>'n','Ó'=>'o','Ś'=>'s','Ź'=>'z','Ż'=>'z',
            'ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','é'=>'e','è'=>'e'];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-zA-Z0-9\s.-]/', '', $text);
    $text = preg_replace('/[.]+/', '-', $text);
    $text = preg_replace('/[\s-]+/', '-', trim(strtolower($text)));
    return trim($text, '-');
}

/** wspólny odczyt CSV: BOM, separator, nagłówki → wiersze asocjacyjne */
function readCsvAssoc(string $path, string $delimiter): array
{
    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new RuntimeException('Nie można otworzyć: '.$path);
    }
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }
    $header = fgetcsv($handle, 0, $delimiter, '"', '\\');
    if (!$header) {
        fclose($handle);
        return [[], []];
    }
    // normalizuj nagłówki: bez @, cudzysłowów, trim, lowercase
    $keys = [];
    foreach ($header as $i => $h) {
        $k = mb_strtolower(trim(str_replace(['@', '"'], '', (string)$h)));
        $keys[$i] = $k !== '' ? $k : 'col'.$i;
    }
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        if (count($row) === 1 && trim((string)$row[0]) === '') {
            continue;
        }
        $assoc = [];
        foreach ($keys as $i => $k) {
            $assoc[$k] = isset($row[$i]) ? trim((string)$row[$i]) : '';
        }
        $rows[] = $assoc;
    }
    fclose($handle);
    return [$keys, $rows];
}

/** szkielet znormalizowanego produktu */
function productSkeleton(): array
{
    return [
        'source' => '', 'source_id' => '', 'sku' => '', 'ean' => '',
        'name' => '', 'brand' => '', 'model' => '',
        'size' => '', 'width' => '', 'pcd' => [], 'et' => [],
        'cb' => '', 'color' => '', 'color_raw' => '', 'finishing' => '',
        'technology' => '', 'stock' => 0,
        'price' => '', 'promo_price' => '', 'currency' => 'PLN',
        'photos' => [], 'max_load' => '', 'weight' => '',
        'next_delivery' => '', 'desc_extra' => [],
    ];
}

// ============================================================
// PARSERY ŹRÓDEŁ — każdy plik mapowany osobno
// ============================================================

function parseFelgeo(string $path, int $limit = 0): array
{
    // marki wg ustaleń: tylko Carbonado i Seventy9
    $brandWhitelist = ['carbonado' => 'Carbonado', 'seventy9' => 'Seventy9'];

    [, $rows] = readCsvAssoc($path, ';');
    $products = [];

    foreach ($rows as $r) {
        $brandKey = mb_strtolower(trim($r['manufacturer'] ?? ''));
        if (!isset($brandWhitelist[$brandKey])) {
            continue;
        }
        $size = normSize($r['size'] ?? '');
        $width = normWidth($r['width'] ?? '');
        if ($size === '' || $width === '') {
            continue;
        }

        $p = productSkeleton();
        $p['source']    = 'felgeo';
        $p['source_id'] = $r['id'] ?? '';
        $p['ean']       = $r['ean'] ?? '';
        $p['brand']     = $brandWhitelist[$brandKey];
        $p['model']     = $r['model'] ?? '';
        $p['size']      = $size;
        $p['width']     = $width;
        $p['pcd']       = normPcdList(trim(($r['pcd'] ?? '').' '.($r['pcd2'] ?? '')));
        $p['et']        = normEtList($r['et'] ?? '');
        $p['cb']        = normCb($r['cb'] ?? '');
        $p['color_raw'] = $r['color_short'] ?? '';
        $p['color']     = normalizeColor($p['color_raw']);
        $p['finishing'] = $r['finishing'] ?? '';
        $p['technology']= normTechnology($r['production_technology'] ?? '');
        $p['stock']     = max(0, (int)($r['stock_(1day) 20 >'] ?? 0)) + max(0, (int)($r['stock_(4days)'] ?? 0));
        $p['price']     = normPrice($r['suggested retail price gross'] ?? '');
        $p['promo_price'] = normPrice($r['retail promotional price gross'] ?? '');
        $p['currency']  = 'PLN';
        $p['max_load']  = normLoad($r['loading(kg)'] ?? '');
        $w = str_replace(',', '.', $r['weight(grams)'] ?? '');
        $p['weight'] = ($w !== '' && $w !== '-' && is_numeric($w)) ? round((float)$w / 1000, 2).' kg' : '';
        if (!empty($r['photo'])) {
            $p['photos'][] = $r['photo'];
        }

        $code = trim($r['producer_code'] ?? '');
        $p['sku'] = 'FG-'.($code !== '' ? $code : ($p['ean'] !== '' ? $p['ean'] : $p['source_id']));

        $name = cleanName($r['model_name'] ?? '');
        $p['name'] = $name !== ''
            ? $name
            : trim($p['brand'].' '.$p['model'].' '.$p['size'].'" '.$p['width'].'J '.$p['color']);

        if ($p['price'] === '' || $p['sku'] === 'FG-') {
            continue;
        }
        $products[] = $p;
        if ($limit > 0 && count($products) >= $limit) {
            break;
        }
    }

    return $products;
}

function parseWheelTrade(string $path, int $limit = 0): array
{
    // marki wg ustaleń: tylko JR Wheels, Vesser, Concaver
    $brandWhitelist = ['jr wheels' => 'JR Wheels', 'vesser' => 'Vesser', 'concaver' => 'Concaver'];

    [, $rows] = readCsvAssoc($path, ';');
    $products = [];

    foreach ($rows as $r) {
        $model = trim($r['model'] ?? '');
        if (in_array($model, ['Complete Sets', 'Showroom', 'Blank Forged'], true)) {
            continue;
        }

        // uzupełnienie pustej marki po prefiksie modelu (jak w danych dostawcy)
        $brand = trim($r['brand'] ?? '');
        if ($brand === '' || $brand === 'CONCAVER SP. Z O.O.') {
            if ($model !== '' && str_starts_with($model, 'CVR')) {
                $brand = 'Concaver';
            }
        }
        $brandKey = mb_strtolower($brand);
        if (!isset($brandWhitelist[$brandKey])) {
            continue;
        }

        // pomijamy wiersze zbiorcze (wiele rozmiarów w jednej pozycji)
        if (str_contains($r['size'] ?? '', ';') || str_contains($r['width'] ?? '', ';')) {
            continue;
        }
        $size = normSize($r['size'] ?? '');
        $width = normWidth($r['width'] ?? '');
        if ($size === '' || $width === '') {
            continue;
        }

        $p = productSkeleton();
        $p['source']    = 'wheeltrade';
        $p['source_id'] = $r['part_number'] ?? '';
        $p['ean']       = $r['ean'] ?? '';
        $p['brand']     = $brandWhitelist[$brandKey];
        $p['model']     = $model;
        $p['size']      = $size;
        $p['width']     = $width;
        $p['pcd']       = normPcdList($r['pcd'] ?? '');
        $p['et']        = normEtList($r['et'] ?? '');
        $p['cb']        = normCb($r['center_bore'] ?? '');
        $p['color_raw'] = $r['colour'] ?? '';
        $p['color']     = normalizeColor($p['color_raw']);
        $p['technology']= normTechnology($r['production_method'] ?? '');
        $p['stock']     = max(0, (int)($r['stock'] ?? 0));
        $p['price']     = normPrice($r['suggested_retail_price'] ?? '');
        $p['promo_price'] = normPrice($r['suggested_retail_price_sale'] ?? '');
        $p['currency']  = 'PLN';
        $p['max_load']  = normLoad($r['max_load'] ?? '');
        $p['next_delivery'] = $r['next_delivery'] ?? '';
        $p['name']      = cleanName($r['name'] ?? '');

        foreach (['photo', 'photo1', 'photo2', 'photo3', 'photo4', 'photo5'] as $col) {
            if (!empty($r[$col])) {
                $p['photos'][] = $r[$col];
            }
        }

        $p['sku'] = 'WT-'.($p['source_id'] !== '' ? $p['source_id'] : $p['ean']);

        if ($p['price'] === '' || $p['sku'] === 'WT-' || $p['name'] === '') {
            continue;
        }
        $products[] = $p;
        if ($limit > 0 && count($products) >= $limit) {
            break;
        }
    }

    return $products;
}

function parseForzza(string $path, int $limit = 0): array
{
    [, $rows] = readCsvAssoc($path, "\t");
    $products = [];

    foreach ($rows as $r) {
        $size = normSize($r['inch'] ?? '');
        $width = normWidth($r['j value'] ?? '');
        if ($size === '' || $width === '') {
            continue;
        }

        $p = productSkeleton();
        $p['source']    = 'forzza';
        $p['source_id'] = $r['barcode'] ?? '';
        $p['ean']       = $r['barcode'] ?? '';
        $p['brand']     = 'Forzza';
        $p['model']     = trim($r['model'] ?? '');
        $p['size']      = $size;
        $p['width']     = $width;
        $p['et']        = normEtList($r['et value'] ?? '');
        $p['cb']        = normCb($r['hub size'] ?? '');
        $p['color_raw'] = $r['color'] ?? '';
        $p['color']     = normalizeColor($p['color_raw']);
        $p['technology']= normTechnology($r['production type'] ?? '');
        $p['stock']     = max(0, (int)($r['stock'] ?? 0));
        $p['price']     = normPrice($r['retail price'] ?? '');
        $p['currency']  = 'EUR';
        $p['max_load']  = normLoad($r['max load'] ?? '');
        $w = $r['rim weight'] ?? '';
        $p['weight']    = ($w !== '' && is_numeric($w) && (float)$w > 0) ? round((float)$w, 2).' kg' : '';
        if (!empty($r['photo'])) {
            $p['photos'][] = $r['photo'];
        }

        // PCD: średnica + liczba otworów (osobne kolumny)
        $p['pcd'] = array_merge(
            normPcdList($r['pcd'] ?? '', $r['holes1'] ?? ''),
            normPcdList($r['pcd2'] ?? '', $r['holes2'] ?? '')
        );
        $p['pcd'] = array_values(array_unique($p['pcd']));

        $et = $p['et'][0] ?? '';
        $p['name'] = trim('Forzza '.$p['model'].' '.$p['width'].'x'.$p['size'].($et !== '' ? ' ET'.$et : '').' '.$p['color_raw']);

        $p['sku'] = 'FZ-'.($p['source_id'] !== '' ? $p['source_id'] : slugify($p['name']));

        if ($p['price'] === '' || $p['sku'] === 'FZ-') {
            continue;
        }
        $products[] = $p;
        if ($limit > 0 && count($products) >= $limit) {
            break;
        }
    }

    return $products;
}

function parseZPerformance(string $path, int $limit = 0): array
{
    // plik ma rozszerzenie .xls, ale w środku jest zwykłe CSV z przecinkami
    [, $rows] = readCsvAssoc($path, ',');
    $products = [];

    foreach ($rows as $r) {
        $size = normSize($r['inch'] ?? '');
        $width = normWidth($r['j'] ?? '');
        if ($size === '' || $width === '') {
            continue;
        }

        $p = productSkeleton();
        $p['source']    = 'zperformance';
        $p['source_id'] = $r['articlenumber'] ?? '';
        $p['brand']     = 'Z-Performance';
        $p['model']     = trim($r['type'] ?? '');
        $p['size']      = $size;
        $p['width']     = $width;
        $p['pcd']       = normPcdList($r['pcd'] ?? '', $r['holes'] ?? '');
        $p['et']        = normEtList($r['offset'] ?? '');
        $p['cb']        = normCb($r['center bore'] ?? '');
        $p['color_raw'] = $r['color'] ?? '';
        $p['color']     = normalizeColor($p['color_raw']);
        $p['technology']= str_contains(mb_strtolower($r['color'] ?? ''), 'flowforged') ? 'Flow Forged' : '';
        $p['stock']     = max(0, (int)($r['quantity'] ?? 0));
        $p['price']     = normPrice($r['customer price'] ?? '');
        $p['currency']  = 'EUR';
        $w = $r['weight of wheel in gramms'] ?? '';
        $p['weight']    = ($w !== '' && is_numeric($w) && (float)$w > 0) ? round((float)$w / 1000, 2).' kg' : '';
        if (!empty($r['foto'])) {
            $p['photos'][] = $r['foto'];
        }
        if (!empty($r['car model'])) {
            $p['desc_extra'][] = 'Pasuje m.in. do: '.$r['car model'];
        }

        // czytelny kolor do nazwy (bez ozdobników dostawcy)
        $colorClean = trim(preg_replace(
            ['/flowforged/i', '/\(custom finish\)/i', '/custom finish/i', '/\s+/'],
            ['', '', '', ' '],
            $p['color_raw']
        ));

        $et = $p['et'][0] ?? '';
        $p['name'] = trim('Z-Performance '.$p['model'].' '.$p['width'].'x'.$p['size'].($et !== '' ? ' ET'.$et : '').' '.$colorClean);

        $p['sku'] = 'ZP-'.($p['source_id'] !== '' ? $p['source_id'] : slugify($p['name']));

        if ($p['price'] === '' || $p['sku'] === 'ZP-') {
            continue;
        }
        $products[] = $p;
        if ($limit > 0 && count($products) >= $limit) {
            break;
        }
    }

    return $products;
}

function parseMotec(string $path, int $limit = 0): array
{
    $rows = XlsReader::read($path);
    if (count($rows) < 3) {
        return [];
    }

    // wiersz 0: nagłówki DE, wiersz 1: nagłówki EN → mapujemy po EN
    $head = [];
    foreach ($rows[1] as $i => $h) {
        $head[mb_strtolower(trim((string)$h))] = $i;
    }
    $col = fn(array $row, string $name) => trim((string)($row[$head[$name] ?? -1] ?? ''));

    $products = [];
    $usedSku = [];

    for ($i = 2, $n = count($rows); $i < $n; $i++) {
        $r = $rows[$i];
        $size = normSize($col($r, 'diameter'));
        $width = normWidth($col($r, 'wide'));
        if ($size === '' || $width === '') {
            continue;
        }

        $p = productSkeleton();
        $p['source']    = 'motec';
        $p['source_id'] = $col($r, 'motec-no.');
        $p['ean']       = $col($r, 'ean-number');
        $p['brand']     = 'Motec';
        $p['model']     = $col($r, 'design');
        $p['size']      = $size;
        $p['width']     = $width;
        $p['pcd']       = normPcdList($col($r, 'pcd'));
        $p['et']        = normEtList($col($r, 'offset'));
        $p['cb']        = normCb($col($r, 'cb'));
        $p['color_raw'] = $col($r, 'color') !== '' ? $col($r, 'color') : $col($r, 'farbe');
        $p['color']     = normalizeColor($p['color_raw']);
        $p['stock']     = 0; // plik nie zawiera stanów magazynowych
        $p['price']     = normPrice($col($r, 'base price'));
        $p['currency']  = 'EUR';
        $p['max_load']  = normLoad($col($r, 'wheel load'));
        $w = str_replace(',', '.', $col($r, 'weight (kg)'));
        $p['weight']    = ($w !== '' && is_numeric($w) && (float)$w > 0) ? $w.' kg' : '';
        if ($col($r, 'kba-no.') !== '') {
            $p['desc_extra'][] = 'Nr KBA: '.$col($r, 'kba-no.');
        }

        $et = $p['et'][0] ?? '';
        $p['name'] = trim('Motec '.$p['model'].' '.$p['width'].'x'.$p['size'].($et !== '' ? ' ET'.$et : '').' '.$p['color_raw']);

        $base = $p['source_id'] !== '' ? $p['source_id'] : ($p['ean'] !== '' ? $p['ean'] : slugify($p['name']));
        $sku = 'MT-'.$base;
        // gwarancja unikalności w obrębie pliku
        if (isset($usedSku[$sku])) {
            $sku .= '-'.($p['ean'] !== '' ? $p['ean'] : $i);
        }
        $usedSku[$sku] = true;
        $p['sku'] = $sku;

        if ($p['price'] === '' || $p['sku'] === 'MT-') {
            continue;
        }
        $products[] = $p;
        if ($limit > 0 && count($products) >= $limit) {
            break;
        }
    }

    return $products;
}

function parseWheelforce(string $path, int $limit = 0): array
{
    $rows = XlsxReader::read($path);
    if (count($rows) < 4) {
        return [];
    }

    // wiersz 2 (0-based): nagłówki
    $head = [];
    foreach ($rows[2] as $i => $h) {
        $head[mb_strtolower(trim((string)$h))] = $i;
    }
    $col = fn(array $row, string $name) => trim((string)($row[$head[$name] ?? -1] ?? ''));

    $products = [];
    $usedSku = [];

    for ($i = 3, $n = count($rows); $i < $n; $i++) {
        $r = $rows[$i];
        // felgi mają SIZE + PCD; akcesoria (dekielki itp.) — nie
        $size = normSize($col($r, 'size'));
        $pcdRaw = $col($r, 'pcd');
        if ($size === '' || $pcdRaw === '') {
            continue;
        }
        $width = normWidth($col($r, 'widh (j)'));
        if ($width === '') {
            continue;
        }

        $p = productSkeleton();
        $p['source']    = 'wheelforce';
        $p['source_id'] = $col($r, 'article number');
        $p['brand']     = 'WheelForce';
        $p['model']     = $col($r, 'design');
        $p['size']      = $size;
        $p['width']     = $width;
        $p['pcd']       = normPcdList($pcdRaw);
        $p['et']        = normEtList($col($r, 'offset'));
        $p['cb']        = normCb($col($r, 'cb'));
        $p['color_raw'] = $col($r, 'finish');
        $p['color']     = normalizeColor($p['color_raw']);
        $p['technology']= normTechnology($col($r, 'technologie'));
        $p['stock']     = 0; // cennik bez stanów magazynowych
        $p['price']     = normPrice($col($r, 'uvp brutto'));
        $p['currency']  = 'EUR';
        $p['max_load']  = normLoad($col($r, 'maxload'));
        $w = str_replace(',', '.', $col($r, 'weight (kg)'));
        $p['weight']    = ($w !== '' && is_numeric($w) && (float)$w > 0.5) ? $w.' kg' : '';
        if (mb_strtolower($col($r, 'winterized')) === 'yes') {
            $p['desc_extra'][] = 'Przystosowane do warunków zimowych';
        }

        $et = $p['et'][0] ?? '';
        $finish = ucwords(mb_strtolower($p['color_raw']));
        $p['name'] = trim('Wheelforce '.$p['model'].' '.$p['width'].'x'.$p['size'].($et !== '' ? ' ET'.$et : '').' '.$finish);

        $base = $p['source_id'] !== '' ? $p['source_id'] : slugify($p['name']);
        $sku = 'WF-'.$base;
        // numer artykułu bywa wspólny dla kilku wykończeń → dołącz finish
        if (isset($usedSku[$sku])) {
            $sku .= '-'.slugify($p['color_raw']);
        }
        if (isset($usedSku[$sku])) {
            $sku .= '-'.$i;
        }
        $usedSku[$sku] = true;
        $p['sku'] = $sku;

        if ($p['price'] === '' || $p['sku'] === 'WF-') {
            continue;
        }
        $products[] = $p;
        if ($limit > 0 && count($products) >= $limit) {
            break;
        }
    }

    return $products;
}

// ============================================================
// FILTRY — STABILNE ID (append-only), zachowanie obcych wpisów
// ============================================================

class FilterManager
{
    private array $filters;   // pełna struktura pliku shop_filters.php
    private array $lookup = []; // key → [label(norm) → id]
    private bool $dirty = false;

    public function __construct(array $existing)
    {
        $this->filters = $existing;
        foreach ($this->filters as $entry) {
            if (!isset($entry['key'], $entry['values'])) {
                continue;
            }
            foreach ($entry['values'] as $vid => $label) {
                $this->lookup[$entry['key']][$this->norm((string)$label)] = (string)$vid;
            }
        }
    }

    private function norm(string $label): string
    {
        return mb_strtolower(trim($label));
    }

    private function ensureFilterEntry(string $key): int
    {
        foreach ($this->filters as $fid => $entry) {
            if (($entry['key'] ?? '') === $key) {
                return (int)$fid;
            }
        }
        $fid = $this->filters ? max(array_keys($this->filters)) + 1 : 1;
        $this->filters[$fid] = [
            'key'    => $key,
            'label'  => FILTER_LABELS[$key] ?? ucfirst($key),
            'values' => [],
        ];
        $this->dirty = true;
        return $fid;
    }

    /** zwraca stabilne ID wartości filtra; dopisuje nową na końcu gdy brak */
    public function valueId(string $key, string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        $n = $this->norm($label);
        if (isset($this->lookup[$key][$n])) {
            $vid = $this->lookup[$key][$n];
            // kanonizacja etykiety (np. "seventy9" → "Seventy9") — ID bez zmian
            foreach ($this->filters as $fid => $entry) {
                if (($entry['key'] ?? '') === $key
                    && isset($entry['values'][$vid])
                    && $entry['values'][$vid] !== $label) {
                    $this->filters[$fid]['values'][$vid] = $label;
                    $this->dirty = true;
                }
            }
            return $vid;
        }
        $fid = $this->ensureFilterEntry($key);
        $values = $this->filters[$fid]['values'];
        $vid = $values ? max(array_map('intval', array_keys($values))) + 1 : 1;
        $this->filters[$fid]['values'][$vid] = $label;
        $this->lookup[$key][$n] = (string)$vid;
        $this->dirty = true;
        return (string)$vid;
    }

    public function isDirty(): bool
    {
        return $this->dirty;
    }

    /**
     * Usuwa z zarządzanych filtrów wartości, których nie używa żaden produkt
     * w bazie (np. marki z poprzednich importów). ID pozostałych wartości
     * nie ulegają zmianie, więc sFilter produktów pozostaje spójny.
     *
     * @param array<string, array<string, true>> $usedByKey klucz → set użytych ID
     */
    public function pruneUnused(array $usedByKey): void
    {
        foreach ($this->filters as $fid => $entry) {
            $key = $entry['key'] ?? '';
            if (!in_array($key, MANAGED_FILTER_KEYS, true)) {
                continue; // obce wpisy (np. portfolio) — nie ruszamy
            }
            foreach ($entry['values'] ?? [] as $vid => $label) {
                if (!isset($usedByKey[$key][(string)$vid])) {
                    unset($this->filters[$fid]['values'][$vid]);
                    unset($this->lookup[$key][$this->norm((string)$label)]);
                    $this->dirty = true;
                }
            }
        }
    }

    /** sortuje wartości zarządzanych filtrów do wyświetlania — bez zmiany ID */
    public function save(string $path): void
    {
        $sorters = [
            'size'  => fn($a, $b) => (float)$a <=> (float)$b,
            'width' => fn($a, $b) => (float)$a <=> (float)$b,
            'et'    => fn($a, $b) => (int)$a <=> (int)$b,
            'pcd'   => function ($a, $b) {
                preg_match('/(\d+)x([\d.]+)/', $a, $ma);
                preg_match('/(\d+)x([\d.]+)/', $b, $mb);
                return [(int)($ma[1] ?? 0), (float)($ma[2] ?? 0)] <=> [(int)($mb[1] ?? 0), (float)($mb[2] ?? 0)];
            },
        ];

        $code = "<?php\n/**\n * Filtry sklepu — zarządzane przez plugins/products/import.php\n"
              . " * Ostatnia aktualizacja: ".date('Y-m-d H:i:s')."\n"
              . " * ID wartości są STABILNE (append-only) — NIE zmieniaj ręcznie istniejących ID,\n"
              . " * bo pole sFilter zaimportowanych produktów odwołuje się do nich.\n */\n\n\$filters = [\n";

        foreach ($this->filters as $fid => $entry) {
            $values = $entry['values'] ?? [];
            $key = $entry['key'] ?? '';

            // kolejność wyświetlania wartości (ID zostają przypięte do etykiet)
            if (isset($sorters[$key])) {
                uasort($values, fn($a, $b) => $sorters[$key](
                    str_replace(['"', 'J'], '', (string)$a),
                    str_replace(['"', 'J'], '', (string)$b)
                ));
            } elseif (in_array($key, ['brand', 'color', 'technology'], true)) {
                uasort($values, fn($a, $b) => strcasecmp((string)$a, (string)$b));
            }

            $code .= "    {$fid} => [\n";
            $code .= "        'key'   => ".var_export($entry['key'] ?? '', true).",\n";
            $code .= "        'label' => ".var_export($entry['label'] ?? '', true).",\n";
            $code .= "        'values' => [\n";
            foreach ($values as $vid => $label) {
                $code .= "            {$vid} => ".var_export((string)$label, true).",\n";
            }
            $code .= "        ],\n    ],\n";
        }
        $code .= "];\n";

        file_put_contents($path, $code, LOCK_EX);
    }
}

/** zbiera ID wartości filtrów faktycznie użyte przez produkty w bazie */
function collectUsedFilterIds(PDO $pdo): array
{
    $used = [];
    foreach ($pdo->query("SELECT sFilter FROM pages WHERE sType = 2 AND sFilter IS NOT NULL AND sFilter != ''") as $row) {
        $decoded = json_decode((string)$row['sFilter'], true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $key => $ids) {
            foreach ((array)$ids as $id) {
                $used[$key][(string)$id] = true;
            }
        }
    }
    return $used;
}

/** buduje JSON sFilter dla produktu (klucz → tablica ID wartości) */
function buildFilterJson(array $p, FilterManager $fm): string
{
    $f = [];

    if ($p['brand'] !== '') {
        $f['brand'] = [$fm->valueId('brand', $p['brand'])];
    }
    if ($p['size'] !== '') {
        $f['size'] = [$fm->valueId('size', $p['size'].'"')];
    }
    if ($p['width'] !== '') {
        $f['width'] = [$fm->valueId('width', $p['width'].'J')];
    }
    if ($p['pcd']) {
        $f['pcd'] = array_values(array_unique(array_map(
            fn($v) => $fm->valueId('pcd', $v),
            $p['pcd']
        )));
    }
    if ($p['et']) {
        $f['et'] = array_values(array_unique(array_map(
            fn($v) => $fm->valueId('et', $v),
            $p['et']
        )));
    }
    if ($p['color'] !== '') {
        $f['color'] = [$fm->valueId('color', $p['color'])];
    }
    if ($p['technology'] !== '') {
        $f['technology'] = [$fm->valueId('technology', $p['technology'])];
    }

    $f = array_filter($f, fn($ids) => $ids !== [''] && $ids !== []);
    return json_encode($f, JSON_UNESCAPED_UNICODE);
}

// ============================================================
// OPISY PRODUKTU
// ============================================================

function buildDescriptionFull(array $p): string
{
    $rows = [];
    $add = function (string $label, string $value) use (&$rows) {
        if (trim($value) !== '') {
            $rows[] = '<strong>'.$label.':</strong> '.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    };

    $add('Marka', $p['brand']);
    $add('Model', $p['model']);
    $add('Rozmiar', $p['size'] !== '' ? $p['size'].'"' : '');
    $add('Szerokość', $p['width'] !== '' ? $p['width'].'J' : '');
    $add('Rozstaw śrub (PCD)', implode(', ', $p['pcd']));
    $add('Offset (ET)', implode(', ', $p['et']));
    $add('Otwór centrujący (CB)', $p['cb']);
    $add('Kolor', $p['color_raw']);
    $add('Wykończenie', $p['finishing']);
    $add('Technologia produkcji', $p['technology']);
    $add('Maks. obciążenie', $p['max_load'] !== '' ? $p['max_load'].' kg' : '');
    $add('Waga', $p['weight']);
    $add('EAN', $p['ean']);
    $add('SKU', $p['sku']);
    if ($p['next_delivery'] !== '' && $p['stock'] <= 0) {
        $add('Planowana dostawa', $p['next_delivery']);
    }
    foreach ($p['desc_extra'] as $extra) {
        $rows[] = htmlspecialchars($extra, ENT_QUOTES, 'UTF-8');
    }
    $rows[] = '<em>Cena dotyczy 1 sztuki.</em>';

    return implode('<br>', $rows);
}

function buildDescriptionShort(array $p): string
{
    $bits = array_filter([
        $p['size'] !== '' ? $p['size'].'"' : '',
        $p['width'] !== '' ? $p['width'].'J' : '',
        $p['pcd'] ? implode(', ', array_slice($p['pcd'], 0, 3)) : '',
        $p['et'] ? 'ET '.$p['et'][0] : '',
        $p['color'],
    ]);
    return '<p>Felga aluminiowa '.htmlspecialchars($p['brand'].' '.$p['model'], ENT_QUOTES, 'UTF-8')
         . ' — '.htmlspecialchars(implode(' · ', $bits), ENT_QUOTES, 'UTF-8').'</p>';
}

// ============================================================
// ZDJĘCIA — pobieranie + miniatury (500 / 920)
// ============================================================

function photoLocalName(array $p, int $index): string
{
    $base = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $p['sku']));
    return trim($base, '-').($index > 0 ? '-'.$index : '');
}

function downloadPhoto(string $url, string $localBase): ?string
{
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg');
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $ext = 'jpg';
    }
    $fileName = $localBase.'.'.$ext;
    $fullPath = FILES_DIR.'/'.$fileName;

    if (!file_exists($fullPath)) {
        $ctx = stream_context_create([
            'http' => ['timeout' => 12, 'user_agent' => 'KOSA-X-Import/2.0', 'follow_location' => 1, 'max_redirects' => 3],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || strlen($data) < 512) {
            return null;
        }
        if (!is_dir(FILES_DIR)) {
            @mkdir(FILES_DIR, 0755, true);
        }
        file_put_contents($fullPath, $data);
    }

    // miniatury (rozmiary z configu CMS: files/{size}/{plik})
    global $config;
    foreach (($config['images_thumbnails'] ?? [500, 920]) as $size) {
        makeThumbnail($fullPath, FILES_DIR.'/'.$size.'/'.$fileName, (int)$size);
    }

    return $fileName;
}

function makeThumbnail(string $src, string $dst, int $maxWidth): void
{
    if (file_exists($dst)) {
        return;
    }
    $dir = dirname($dst);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    if (!function_exists('imagecreatetruecolor')) {
        @copy($src, $dst);
        return;
    }

    $info = @getimagesize($src);
    if (!$info) {
        @copy($src, $dst);
        return;
    }
    [$w, $h] = $info;
    if ($w <= $maxWidth) {
        @copy($src, $dst);
        return;
    }

    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default        => false,
    };
    if (!$img) {
        @copy($src, $dst);
        return;
    }

    $nw = $maxWidth;
    $nh = (int)round($h * ($maxWidth / $w));
    $thumb = imagecreatetruecolor($nw, $nh);

    if (in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 255, 255, 255, 127));
    }
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    match ($info[2]) {
        IMAGETYPE_JPEG => imagejpeg($thumb, $dst, 88),
        IMAGETYPE_PNG  => imagepng($thumb, $dst, 8),
        IMAGETYPE_GIF  => imagegif($thumb, $dst),
        IMAGETYPE_WEBP => imagewebp($thumb, $dst, 88),
        default        => imagejpeg($thumb, $dst, 88),
    };
    imagedestroy($thumb);
    imagedestroy($img);
}

// ============================================================
// BAZA DANYCH
// ============================================================

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:'.DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 10000');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}

/** idempotentna migracja schematu pod import */
function ensureSchema(PDO $pdo, array &$log): void
{
    $cols = [];
    foreach ($pdo->query('PRAGMA table_info(pages)') as $row) {
        $cols[$row['name']] = true;
    }
    $needed = [
        'sSKU'      => "ALTER TABLE pages ADD COLUMN 'sSKU' TEXT",
        'sEAN'      => "ALTER TABLE pages ADD COLUMN 'sEAN' TEXT",
        'sSource'   => "ALTER TABLE pages ADD COLUMN 'sSource' TEXT",
        'sSourceID' => "ALTER TABLE pages ADD COLUMN 'sSourceID' TEXT",
        'iStock'    => "ALTER TABLE pages ADD COLUMN 'iStock' INTEGER DEFAULT 0",
        'sCurrency' => "ALTER TABLE pages ADD COLUMN 'sCurrency' TEXT DEFAULT 'PLN'",
    ];
    foreach ($needed as $col => $sql) {
        if (!isset($cols[$col])) {
            $pdo->exec($sql);
            $log[] = "Migracja: dodano kolumnę pages.{$col}";
        }
    }
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_pages_sku ON pages(sSKU) WHERE sSKU IS NOT NULL AND sSKU != ''");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pages_source ON pages(sSource)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_files_page ON files(iPage)');
}

/**
 * Wspólna kategoria sklepu „Felgi" (iMenu=2) — wszystkie felgi trafiają tutaj,
 * marki rozróżnia filtr „Marka". Tworzy kategorię, gdy jej brak.
 */
function ensureShopCategory(PDO $pdo, array &$log): int
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $stmt = $pdo->prepare("SELECT iPage FROM pages WHERE sName = 'Felgi' AND iMenu = 2 AND sType = 1 LIMIT 1");
    $stmt->execute();
    $id = $stmt->fetchColumn();

    if (!$id) {
        $stmt = $pdo->prepare(
            "INSERT INTO pages (iPageParent, iStatus, iPosition, iMenu, sLang, sName, sTitle, sUrl, iTheme,
                                sDescriptionMeta, sExpandMenu, sDate, sType)
             VALUES (0, 1, 0, 2, 'pl', 'Felgi', 'Felgi', 'felgi', 1, :meta, 0, :date, 1)"
        );
        $stmt->execute([
            ':meta' => 'Felgi aluminiowe renomowanych producentów — sklep KJERU WHEELS',
            ':date' => date('Y-m-d'),
        ]);
        $id = (int)$pdo->lastInsertId();
        $log[] = "Utworzono kategorię sklepu: Felgi (ID {$id})";
    }

    return $cache = (int)$id;
}

/**
 * Sprząta po wcześniejszym układzie kategorii per marka: usuwa PUSTE
 * (bez podstron) kategorie o nazwach naszych marek.
 */
function removeEmptyBrandCategories(PDO $pdo, array &$log): void
{
    $brands = ['Carbonado', 'Seventy9', 'JR Wheels', 'Vesser', 'Concaver',
               'Forzza', 'Z-Performance', 'Motec', 'WheelForce'];
    $in = "'".implode("','", $brands)."'";

    $stmt = $pdo->query(
        "SELECT p.iPage, p.sName FROM pages p
         WHERE p.sName IN ({$in}) AND p.iMenu = 2 AND p.sType = 1
           AND NOT EXISTS (SELECT 1 FROM pages c WHERE c.iPageParent = p.iPage)"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec('DELETE FROM files WHERE iPage = '.(int)$row['iPage']);
        $pdo->exec('DELETE FROM pages WHERE iPage = '.(int)$row['iPage']);
        $log[] = 'Usunięto pustą kategorię marki: '.$row['sName'];
    }
}

/**
 * Przebudowuje cache routingu CMS (database/cache/links*).
 * UWAGA: samo usunięcie plików wyłącza front (getPageId robi exit),
 * dlatego od razu generujemy je na nowo silnikiem CMS-a.
 */
function rebuildRouteCache(): void
{
    if (!defined('CUSTOMER_PAGE')) {
        define('CUSTOMER_PAGE', true); // wymagane przez core/pages.php
    }
    require_once IMPORT_ROOT.'/core/libraries/trash.php';   // change2Url()
    require_once IMPORT_ROOT.'/core/libraries/sql.php';     // klasa Sql
    require_once IMPORT_ROOT.'/core/pages.php';             // klasa Pages

    @unlink(CACHE_LINKS);
    @unlink(CACHE_LINKS_IDS);
    Pages::getInstance(); // konstruktor → generateCache → generateLinks
}

// ============================================================
// SILNIK IMPORTU
// ============================================================

/**
 * @return array{inserted:int,updated:int,skipped:int,photos_ok:int,photos_left:int,log:array}
 */
function runImport(string $sourceKey, int $limit = 0, bool $dryRun = false, bool $withPhotos = true): array
{
    $src = SOURCES[$sourceKey] ?? null;
    if (!$src) {
        throw new RuntimeException('Nieznane źródło: '.$sourceKey);
    }
    $path = IMPORT_DIR.'/'.$src['file'];
    if (!is_file($path)) {
        throw new RuntimeException('Brak pliku: '.$src['file']);
    }

    $log = [];
    $products = ($src['parser'])($path, $limit);
    $log[] = 'Sparsowano '.count($products).' felg z '.$src['file'];

    $pdo = db();
    ensureSchema($pdo, $log);

    global $filters;
    $fm = new FilterManager(is_array($filters) ? $filters : []);

    $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'photos_ok' => 0, 'photos_left' => 0, 'log' => []];

    if ($dryRun) {
        // tylko policz co by się stało
        $stmt = $pdo->prepare('SELECT iPage FROM pages WHERE sSKU = :sku LIMIT 1');
        foreach ($products as $p) {
            $stmt->execute([':sku' => $p['sku']]);
            $stmt->fetchColumn() !== false ? $stats['updated']++ : $stats['inserted']++;
        }
        $log[] = "DRY-RUN: nowych {$stats['inserted']}, do aktualizacji {$stats['updated']} — bez zapisu.";
        $stats['log'] = $log;
        return $stats;
    }

    // istniejące slugi (unikalne URL-e)
    $usedSlugs = [];
    foreach ($pdo->query("SELECT sUrl FROM pages WHERE sUrl IS NOT NULL AND sUrl != ''") as $row) {
        $usedSlugs[$row['sUrl']] = true;
    }

    $stmtFind = $pdo->prepare('SELECT iPage FROM pages WHERE sSKU = :sku LIMIT 1');
    $stmtInsert = $pdo->prepare(
        "INSERT INTO pages (iPageParent, iStatus, iPosition, iMenu, sLang, sName, sTitle, sUrl, iTheme,
                            sDescriptionMeta, sDescriptionShort, sDescriptionFull, sDate, sPrice, xPrice,
                            sType, sExpandMenu, sFilter, sSKU, sEAN, sSource, sSourceID, iStock, sCurrency)
         VALUES (:parent, 1, 0, 2, 'pl', :name, '', :url, 1,
                 :meta, :short, :full, :date, :price, :xprice,
                 2, 0, :filter, :sku, :ean, :source, :sid, :stock, :currency)"
    );
    $stmtUpdate = $pdo->prepare(
        'UPDATE pages SET sName = :name, sDescriptionMeta = :meta, sDescriptionShort = :short,
                          sDescriptionFull = :full, sDate = :date, sPrice = :price, xPrice = :xprice,
                          sFilter = :filter, sEAN = :ean, sSource = :source, sSourceID = :sid,
                          iStock = :stock, sCurrency = :currency, iPageParent = :parent
         WHERE iPage = :id'
    );
    $stmtHasPhoto = $pdo->prepare('SELECT COUNT(*) FROM files WHERE iPage = :page AND iSize > 0');
    $stmtAddPhoto = $pdo->prepare(
        'INSERT INTO files (iPage, iSize, iDefault, iPosition, iType, sFileName, sDescription)
         VALUES (:page, 920, :default, :position, 1, :filename, :desc)'
    );

    $photoStart = microtime(true);
    $photoBudget = IS_CLI ? PHP_FLOAT_MAX : PHOTO_TIME_BUDGET;

    $pdo->beginTransaction();

    $categoryId = ensureShopCategory($pdo, $log);

    foreach ($products as $p) {
        $filterJson = buildFilterJson($p, $fm);
        $common = [
            ':name'     => $p['name'],
            ':meta'     => trim($p['brand'].' '.$p['model'].' '.$p['size'].'" '.$p['width'].'J — felgi aluminiowe'),
            ':short'    => buildDescriptionShort($p),
            ':full'     => buildDescriptionFull($p),
            ':date'     => date('Y-m-d'),
            ':price'    => $p['price'],
            ':xprice'   => $p['promo_price'],
            ':filter'   => $filterJson,
            ':ean'      => $p['ean'],
            ':source'   => $p['source'],
            ':sid'      => $p['source_id'],
            ':stock'    => $p['stock'],
            ':currency' => $p['currency'],
            ':parent'   => $categoryId,
        ];

        $stmtFind->execute([':sku' => $p['sku']]);
        $pageId = $stmtFind->fetchColumn();

        if ($pageId !== false) {
            $stmtUpdate->execute($common + [':id' => $pageId]);
            $stats['updated']++;
        } else {
            $slug = slugify($p['name']);
            if ($slug === '' || isset($usedSlugs[$slug])) {
                $slug = trim($slug.'-'.slugify($p['sku']), '-');
            }
            $i = 2;
            $base = $slug;
            while (isset($usedSlugs[$slug])) {
                $slug = $base.'-'.$i++;
            }
            $usedSlugs[$slug] = true;

            $stmtInsert->execute($common + [':url' => $slug, ':sku' => $p['sku']]);
            $pageId = (int)$pdo->lastInsertId();
            $stats['inserted']++;
        }

        // ---- zdjęcia (z budżetem czasu na żądanie WWW) ----
        if ($withPhotos && $p['photos']) {
            $stmtHasPhoto->execute([':page' => $pageId]);
            if ((int)$stmtHasPhoto->fetchColumn() === 0) {
                if (microtime(true) - $photoStart < $photoBudget) {
                    $pos = 0;
                    foreach (array_slice($p['photos'], 0, 6) as $idx => $url) {
                        $fileName = downloadPhoto($url, photoLocalName($p, $idx));
                        if ($fileName) {
                            $stmtAddPhoto->execute([
                                ':page' => $pageId, ':default' => $pos === 0 ? 1 : 0,
                                ':position' => $pos, ':filename' => $fileName,
                                ':desc' => $p['name'],
                            ]);
                            $pos++;
                        }
                    }
                    if ($pos > 0) {
                        $stats['photos_ok']++;
                    } else {
                        $stats['photos_left']++;
                    }
                } else {
                    $stats['photos_left']++;
                }
            }
        }
    }

    $pdo->commit();

    // porządki po wcześniejszym układzie kategorii per marka
    removeEmptyBrandCategories($pdo, $log);

    // wyczyść wartości filtrów nieużywane przez żaden produkt w bazie
    $fm->pruneUnused(collectUsedFilterIds($pdo));

    if ($fm->isDirty()) {
        $fm->save(FILTERS_PATH);
        $log[] = 'Zaktualizowano filtry → plugins/shop_filters.php';
    }

    rebuildRouteCache();
    $log[] = "Dodano: {$stats['inserted']}, zaktualizowano: {$stats['updated']}";
    if ($stats['photos_left'] > 0) {
        $log[] = "Zdjęcia: pobrano dla {$stats['photos_ok']} produktów, pozostało {$stats['photos_left']} — "
               . 'użyj akcji „Pobierz zdjęcia", aby dociągnąć resztę partiami.';
    }

    $stats['log'] = $log;
    return $stats;
}

/** dociąganie brakujących zdjęć partiami (budżet czasu na żądanie) */
function runPhotos(string $sourceKey): array
{
    $src = SOURCES[$sourceKey] ?? null;
    if (!$src) {
        throw new RuntimeException('Nieznane źródło: '.$sourceKey);
    }
    $products = ($src['parser'])(IMPORT_DIR.'/'.$src['file'], 0);

    $pdo = db();
    $log = [];
    ensureSchema($pdo, $log);

    $stmtFind = $pdo->prepare('SELECT iPage FROM pages WHERE sSKU = :sku LIMIT 1');
    $stmtHasPhoto = $pdo->prepare('SELECT COUNT(*) FROM files WHERE iPage = :page AND iSize > 0');
    $stmtAddPhoto = $pdo->prepare(
        'INSERT INTO files (iPage, iSize, iDefault, iPosition, iType, sFileName, sDescription)
         VALUES (:page, 920, :default, :position, 1, :filename, :desc)'
    );

    $start = microtime(true);
    $budget = IS_CLI ? PHP_FLOAT_MAX : PHOTO_TIME_BUDGET;
    $done = 0;
    $left = 0;

    foreach ($products as $p) {
        if (!$p['photos']) {
            continue;
        }
        $stmtFind->execute([':sku' => $p['sku']]);
        $pageId = $stmtFind->fetchColumn();
        if ($pageId === false) {
            continue;
        }
        $stmtHasPhoto->execute([':page' => $pageId]);
        if ((int)$stmtHasPhoto->fetchColumn() > 0) {
            continue;
        }

        if (microtime(true) - $start >= $budget) {
            $left++;
            continue;
        }

        $pos = 0;
        foreach (array_slice($p['photos'], 0, 6) as $idx => $url) {
            $fileName = downloadPhoto($url, photoLocalName($p, $idx));
            if ($fileName) {
                $stmtAddPhoto->execute([
                    ':page' => $pageId, ':default' => $pos === 0 ? 1 : 0,
                    ':position' => $pos, ':filename' => $fileName, ':desc' => $p['name'],
                ]);
                $pos++;
            }
        }
        $pos > 0 ? $done++ : $left++;
    }

    $log[] = "Pobrano zdjęcia dla {$done} produktów".($left > 0 ? ", pozostało: {$left} (kliknij ponownie)" : ' — komplet.');
    return ['photos_ok' => $done, 'photos_left' => $left, 'log' => $log];
}

/** usuwa demo sklepu: kategoria „Samochody" + jej produkty */
function removeDemoProducts(): array
{
    $pdo = db();
    $log = [];

    $stmt = $pdo->prepare("SELECT iPage, sName FROM pages WHERE sName = 'Samochody' AND iMenu = 2 AND sType = 1");
    $stmt->execute();
    $removedTotal = 0;

    while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $catId = (int)$cat['iPage'];
        $childIds = $pdo->query("SELECT iPage FROM pages WHERE iPageParent = {$catId}")->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_merge([$catId], array_map('intval', $childIds));
        $in = implode(',', $ids);

        $pdo->exec("DELETE FROM files WHERE iPage IN ({$in})");
        $pdo->exec("DELETE FROM pages WHERE iPage IN ({$in})");
        $removedTotal += count($ids);
        $log[] = 'Usunięto demo: kategoria „'.$cat['sName'].'” (ID '.$catId.') + '.count($childIds).' podstron';
    }

    if ($removedTotal === 0) {
        $log[] = 'Brak produktów demo do usunięcia.';
    } else {
        rebuildRouteCache();
    }

    return ['removed' => $removedTotal, 'log' => $log];
}

// ============================================================
// STATYSTYKI DO PANELU (cache per mtime pliku)
// ============================================================

function sourceStats(string $sourceKey): array
{
    $src = SOURCES[$sourceKey];
    $path = IMPORT_DIR.'/'.$src['file'];

    $out = [
        'exists' => is_file($path),
        'mtime'  => null,
        'sizeMB' => null,
        'in_file' => null,
        'in_db' => 0,
        'no_photo' => 0,
        'stock_sum' => 0,
    ];

    if ($out['exists']) {
        $out['mtime'] = date('Y-m-d H:i', filemtime($path));
        $out['sizeMB'] = round(filesize($path) / 1048576, 2);

        // liczność z pliku — cache po mtime (parsowanie dużych CSV bywa kosztowne)
        if (!is_dir(IMPORT_CACHE_DIR)) {
            @mkdir(IMPORT_CACHE_DIR, 0755, true);
        }
        $cacheFile = IMPORT_CACHE_DIR.'/'.$sourceKey.'.json';
        $cache = is_file($cacheFile) ? json_decode((string)file_get_contents($cacheFile), true) : null;
        if (is_array($cache) && ($cache['mtime'] ?? 0) === filemtime($path)) {
            $out['in_file'] = $cache['count'];
        } else {
            try {
                $out['in_file'] = count(($src['parser'])($path, 0));
                file_put_contents($cacheFile, json_encode(['mtime' => filemtime($path), 'count' => $out['in_file']]));
            } catch (Throwable $e) {
                $out['in_file'] = null;
            }
        }
    }

    try {
        $pdo = db();
        $hasCols = false;
        foreach ($pdo->query('PRAGMA table_info(pages)') as $row) {
            if ($row['name'] === 'sSource') {
                $hasCols = true;
                break;
            }
        }
        if ($hasCols) {
            $stmt = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(iStock),0) FROM pages WHERE sSource = :s');
            $stmt->execute([':s' => $sourceKey]);
            [$out['in_db'], $out['stock_sum']] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));

            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM pages p
                 WHERE p.sSource = :s AND NOT EXISTS (SELECT 1 FROM files f WHERE f.iPage = p.iPage AND f.iSize > 0)'
            );
            $stmt->execute([':s' => $sourceKey]);
            $out['no_photo'] = (int)$stmt->fetchColumn();
        }
    } catch (Throwable $e) {
        // baza niedostępna — panel pokaże braki
    }

    return $out;
}

// ============================================================
// OBSŁUGA CLI
// ============================================================

if (IS_CLI) {
    $args = ['source' => null, 'limit' => 0, 'dry' => false, 'photos' => false, 'demo' => false, 'nophotos' => false];
    foreach ($argv as $arg) {
        if (preg_match('/^--source=(\w+)$/', $arg, $m)) {
            $args['source'] = $m[1];
        }
        if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
            $args['limit'] = (int)$m[1];
        }
        if ($arg === '--dry') {
            $args['dry'] = true;
        }
        if ($arg === '--photos') {
            $args['photos'] = true;
        }
        if ($arg === '--no-photos') {
            $args['nophotos'] = true;
        }
        if ($arg === '--remove-demo') {
            $args['demo'] = true;
        }
    }

    if ($args['demo']) {
        $res = removeDemoProducts();
        echo implode("\n", $res['log']), "\n";
        exit(0);
    }
    if (!$args['source'] || !isset(SOURCES[$args['source']])) {
        echo "Użycie: php import.php --source={".implode('|', array_keys(SOURCES))."} [--limit=N] [--dry] [--no-photos]\n";
        echo "        php import.php --source=... --photos   (tylko dociąganie zdjęć)\n";
        echo "        php import.php --remove-demo\n";
        exit(1);
    }
    $res = $args['photos']
        ? runPhotos($args['source'])
        : runImport($args['source'], $args['limit'], $args['dry'], !$args['nophotos']);
    echo implode("\n", $res['log']), "\n";
    exit(0);
}

// ============================================================
// OBSŁUGA AKCJI WWW (POST → redirect → panel)
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['import_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Nieprawidłowy token CSRF — odśwież panel i spróbuj ponownie.');
    }

    $action = $_POST['action'] ?? '';
    $source = $_POST['source'] ?? '';
    $limit  = max(0, (int)($_POST['limit'] ?? 0));

    // import może potrwać (duże CSV + zdjęcia)
    @set_time_limit(0);

    try {
        $result = match ($action) {
            'import' => runImport($source, $limit, false),
            'dry'    => runImport($source, $limit, true),
            'photos' => runPhotos($source),
            'demo'   => removeDemoProducts(),
            default  => throw new RuntimeException('Nieznana akcja'),
        };
        $_SESSION['import_flash'] = ['ok' => true, 'log' => $result['log']];
    } catch (Throwable $e) {
        $_SESSION['import_flash'] = ['ok' => false, 'log' => ['BŁĄD: '.$e->getMessage()]];
    }

    header('Location: '.strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ============================================================
// PANEL (GET)
// ============================================================

$flash = $_SESSION['import_flash'] ?? null;
unset($_SESSION['import_flash']);
$csrf = $_SESSION['import_csrf'];

$totalDb = 0;
try {
    $pdo = db();
    $hasCols = false;
    foreach ($pdo->query('PRAGMA table_info(pages)') as $row) {
        if ($row['name'] === 'sSource') {
            $hasCols = true;
        }
    }
    if ($hasCols) {
        $totalDb = (int)$pdo->query("SELECT COUNT(*) FROM pages WHERE sType = 2 AND sSource IS NOT NULL")->fetchColumn();
    }
} catch (Throwable $e) {
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Import produktów — KOSA X CMS</title>
<style>
    :root { --bg:#0a0a0c; --card:#131318; --line:#26262c; --txt:#ececee; --muted:#9b9ba3; --brand:#d00e1c; }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { background:var(--bg); color:var(--txt); font:14px/1.5 -apple-system, "Segoe UI", Roboto, sans-serif; padding:40px 20px; }
    .wrap { max-width:1100px; margin:0 auto; }
    h1 { font-size:22px; letter-spacing:.04em; text-transform:uppercase; }
    h1 span { color:var(--brand); }
    .sub { color:var(--muted); margin:6px 0 30px; }
    .flash { border:1px solid var(--line); border-left:3px solid var(--brand); background:var(--card); padding:14px 18px; margin-bottom:26px; border-radius:6px; }
    .flash.ok { border-left-color:#2f9e44; }
    .flash pre { white-space:pre-wrap; color:var(--txt); font:12.5px/1.6 ui-monospace, monospace; }
    table { width:100%; border-collapse:collapse; background:var(--card); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
    th, td { padding:12px 14px; text-align:left; border-bottom:1px solid var(--line); vertical-align:middle; }
    th { font-size:11px; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); background:#0e0e12; }
    tr:last-child td { border-bottom:0; }
    .name b { font-size:15px; }
    .name small { display:block; color:var(--muted); margin-top:2px; }
    .num { font-variant-numeric:tabular-nums; }
    .num b { font-size:16px; }
    .miss { color:#e8590c; }
    .ok { color:#2f9e44; }
    .muted { color:var(--muted); }
    form { display:inline-block; margin:0 4px 4px 0; }
    button { background:var(--brand); border:0; color:#fff; padding:8px 13px; border-radius:5px; cursor:pointer; font-weight:600; font-size:12px; letter-spacing:.05em; text-transform:uppercase; }
    button:hover { background:#ee1b2a; }
    button.ghost { background:transparent; border:1px solid var(--line); color:var(--txt); }
    button.ghost:hover { border-color:var(--brand); color:var(--brand); background:transparent; }
    input[type=number] { width:70px; background:#0e0e12; border:1px solid var(--line); color:var(--txt); padding:7px 8px; border-radius:5px; }
    .toolbar { margin-top:26px; display:flex; gap:10px; align-items:center; }
    .badge { display:inline-block; padding:2px 8px; border:1px solid var(--line); border-radius:99px; font-size:11px; color:var(--muted); }
    .danger { background:transparent; border:1px solid #e8590c; color:#e8590c; }
    .danger:hover { background:#e8590c; color:#fff; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Import produktów <span>· felgi</span></h1>
    <p class="sub">Produkty w bazie (z importu): <strong><?= $totalDb ?></strong> ·
        UPSERT po SKU — ponowny import aktualizuje ceny, stany i filtry bez duplikatów.</p>

    <?php if ($flash): ?>
        <div class="flash <?= $flash['ok'] ? 'ok' : '' ?>">
            <pre><?= htmlspecialchars(implode("\n", $flash['log']), ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Źródło</th>
            <th>Plik</th>
            <th class="num">W pliku</th>
            <th class="num">W bazie</th>
            <th class="num">Bez zdjęcia</th>
            <th style="width:340px">Akcje</th>
        </tr>
        <?php foreach (SOURCES as $key => $src): $st = sourceStats($key); ?>
        <tr>
            <td class="name">
                <b><?= htmlspecialchars($src['label']) ?></b>
                <small><?= htmlspecialchars($src['note']) ?></small>
            </td>
            <td>
                <?php if ($st['exists']): ?>
                    <?= htmlspecialchars($src['file']) ?><br>
                    <span class="muted"><?= $st['sizeMB'] ?> MB · <?= $st['mtime'] ?></span>
                <?php else: ?>
                    <span class="miss">brak pliku: <?= htmlspecialchars($src['file']) ?></span>
                <?php endif; ?>
            </td>
            <td class="num"><b><?= $st['in_file'] !== null ? $st['in_file'] : '—' ?></b></td>
            <td class="num">
                <b class="<?= $st['in_db'] > 0 ? 'ok' : '' ?>"><?= $st['in_db'] ?></b>
                <?php if ($st['in_db'] > 0): ?><br><span class="badge">stock: <?= $st['stock_sum'] ?> szt.</span><?php endif; ?>
            </td>
            <td class="num <?= $st['no_photo'] > 0 ? 'miss' : 'ok' ?>"><?= $st['in_db'] > 0 ? $st['no_photo'] : '—' ?></td>
            <td>
                <?php if ($st['exists']): ?>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="import">
                        <input type="hidden" name="source" value="<?= $key ?>">
                        <input type="number" name="limit" placeholder="limit" min="0" title="Limit pozycji (puste = wszystko)">
                        <button type="submit">Import <?= htmlspecialchars($src['file']) ?></button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="dry">
                        <input type="hidden" name="source" value="<?= $key ?>">
                        <button type="submit" class="ghost" title="Podgląd bez zapisu do bazy">Analizuj</button>
                    </form>
                    <?php if ($st['no_photo'] > 0): ?>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="photos">
                        <input type="hidden" name="source" value="<?= $key ?>">
                        <button type="submit" class="ghost">Pobierz zdjęcia (<?= $st['no_photo'] ?>)</button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="toolbar">
        <form method="post" onsubmit="return confirm('Usunąć kategorię demo „Samochody' + String.fromCharCode(8221) + ' wraz z podstronami?')">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="demo">
            <button type="submit" class="danger">Usuń produkty demo („Samochody")</button>
        </form>
        <span class="muted">Zdjęcia pobierane są partiami (budżet <?= PHOTO_TIME_BUDGET ?>s na kliknięcie) — przy dużych importach klikaj „Pobierz zdjęcia" do skutku.</span>
    </div>
</div>
</body>
</html>
