<?php
if (!defined('ADMIN_PAGE')) {
    exit('Script by OpenSolution.org');
}

/**
 * Build safe redirect URL based on current request URI, overriding query params.
 */
function adminBuildRedirectUrl(array $override = []): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $uri = str_replace(["\r", "\n"], '', $uri); // safety

    $parts = parse_url($uri);
    $path  = $parts['path'] ?? $GLOBALS['config']['admin_file'];
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    foreach ($override as $k => $v) {
        $query[$k] = $v;
    }

    $qs = http_build_query($query);
    return $path . ($qs ? ('?' . $qs) : '');
}

if (isset($_POST['sOption'])) {
    $oPage->savePages($_POST);

    header('Location: ' . adminBuildRedirectUrl(['sOption' => 'save']));
    exit;
}

if (isset($_GET['iItemDelete']) && is_numeric($_GET['iItemDelete'])) {
    $oPage->deletePage((int) $_GET['iItemDelete']);

    header('Location: ' . $config['admin_file'] . '?p=pages&sOption=del');
    exit;
}

$sSelectedMenu = 'pages';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

$pagesList = '';
foreach ($config['pages_menus'] as $iMenu => $sMenu) {
    $pagesList .= $oPage->listPagesAdmin(['iMenu' => $iMenu]);
}

if (!empty($pagesList)):
?>
<form action="?p=pages<?php echo isset($_GET['sSort']) ? '&amp;sSort=' . html($_GET['sSort']) : ''; ?>" name="form" method="post" class="main-form">

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title"><?php echo html($lang['Pages']); ?></h1>

        <div class="mainPage__buttons d-flex justify-content-between">
            <input type="submit" name="sOption" class="button" value="<?php echo html($lang['save']); ?>" />
        </div>
    </header>

    <?php
    if (isset($_GET['sOption'])) {
        echo '<div class="alert alert-success mb-3">' . html($lang['Operation_completed']) . '</div>';
    }
    ?>

    <div class="row">
        <?php echo $pagesList; ?>
    </div>

</form>
<?php
else:
    echo '<div class="alert alert-danger">' . html($lang['Data_not_found']) . '</div>';
endif;

require_once 'templates/admin/_footer.php';
?>
