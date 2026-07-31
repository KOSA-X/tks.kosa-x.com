<?php
if (!defined('CUSTOMER_PAGE')) { exit; }

/*
 * TRANSMISJA LIVE — panel operatora meczu (wynik, zegar, plansze, zdarzenia).
 * Placeholder — pełny panel wchodzi w kroku 2 modułu live.
 * Dostęp: tylko zalogowany admin CMS (sprawdzane niżej, jak w plugins/live/api.php).
 */

require_once $theme.'_header.php';

$bLiveAdmin = false;
if (!empty($config['session_key_name']) && isset($_SESSION[$config['session_key_name']]) && is_int($_SESSION[$config['session_key_name']])) {
    $bLiveAdmin = true;
}

echo '<div class="container"><div class="mainPage__content">';
if (!$bLiveAdmin) {
    echo '<div class="alert alert-danger">'.$lang['live_panel_login_required'].'</div>';
} else {
    echo '<p>'.$lang['live_panel_placeholder'].'</p>';
}
echo '</div></div>';

require_once $theme.'_footer.php';
