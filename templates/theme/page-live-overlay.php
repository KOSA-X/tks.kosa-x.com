<?php
if (!defined('CUSTOMER_PAGE')) { exit; }

/*
 * TRANSMISJA LIVE — nakładka OBS (Browser Source).
 * Placeholder — pełny overlay wchodzi w kroku 3 modułu live.
 * Strona STANDALONE: bez _header/_footer, przezroczyste tło pod OBS.
 */
?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo html($aData['sName'] ?? 'Nakładka OBS'); ?></title>
    <style>
        html, body { margin: 0; padding: 0; background: transparent !important; }
    </style>
</head>
<body>
<!-- Nakładka OBS — w przygotowaniu (krok 3 modułu live) -->
</body>
</html>
