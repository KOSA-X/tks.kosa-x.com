<?php
// sitemap.xml.php
define('CUSTOMER_PAGE', true);
require_once 'database/config.php';     // u Ciebie może być inna ścieżka
require_once 'core/libraries/sql.php';
require_once 'core/pages.php';
require_once 'plugins/settings.php';

$oPage  = Pages::getInstance();
$config = $config ?? [];

// nagłówki XML
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

if (!empty($oPage->aPages)) {
    foreach ($oPage->aPages as $id => $page) {

//        // tylko aktywne
        if ((int)$page['iStatus'] <= 0) {
            continue;
        }
//
        $loc = $page['sLinkName'] ?? '';
        // strona bez wpisu w cache linków (np. świeżo usunięta) — pomiń
        if ($loc === '') {
            continue;
        }
//        // pełny adres
        if (strpos($loc, 'http') !== 0) {
            $loc = rtrim(BASE_URL, '/').$loc;
        }
//
//        // lastmod – możesz podmienić na kolumnę z bazy
        $lastmod = date('Y-m-d');

        echo "  <url>\n";
        echo "    <loc>".htmlspecialchars($loc, ENT_QUOTES, 'UTF-8')."</loc>\n";
        echo "    <lastmod>".$lastmod."</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.6</priority>\n";
        echo "  </url>\n";
    }
}

echo "</urlset>";
 