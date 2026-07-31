<?php
if (!defined('ADMIN_PAGE')) {
    exit('Script by OpenSolution.org');
}

$deleteId = isset($_GET['iItemDelete']) ? (int) $_GET['iItemDelete'] : 0;
if ($deleteId > 0) {
    $oSql->query('DELETE FROM orders WHERE id="' . $deleteId . '"');
    header('Location: ' . $config['admin_file'] . '?p=orders&sOption=del');
    exit;
}

$sSelectedMenu = 'shop';

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

$sPagesList = $oPage->listOrdersAdmin();

if (!empty($sPagesList)):
    $action = '?p=orders';
    if (isset($_GET['sSort'])) {
        $action .= '&amp;sSort=' . html($_GET['sSort']);
    }
    ?>

    <form action="<?php echo $action; ?>" name="form" method="post" class="main-form">

        <header class="mainPage__header">
            <h1 class="mainPage__title">Zamówienia</h1>
        </header>

        <?php
        if (isset($_GET['sOption'])) {
            echo '<div class="alert alert-success mb-3">' . $lang['Operation_completed'] . '</div>';
        }
        ?>

        <div class="table-responsive">
            <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zamawiający</th>
                        <th>Kwota</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $sPagesList; ?>
                </tbody>
            </table>
        </div>

    </form>

<?php
else:
    echo '<div class="alert alert-danger">' . $lang['Data_not_found'] . '</div>';
endif;

require_once 'templates/admin/_footer.php';
?>
