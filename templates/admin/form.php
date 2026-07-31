<?php
if (!defined('ADMIN_PAGE')) {
    exit('Script by OpenSolution.org');
}

if (isset($_GET['iItemDelete']) && is_numeric($_GET['iItemDelete'])) {
    $id = (int)$_GET['iItemDelete'];

    if ($id > 0) {
        $oSql->query('DELETE FROM form WHERE iForm=' . $id);
    }

    header('Location: ' . $config['admin_file'] . '?p=form&sOption=del');
    exit;
}

$sSelectedMenu = 'form';

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

$sRows = $oPage->listFormsAdmin();
?>

<?php if (!empty($sRows)) { ?>
    <form action="?p=form<?php echo isset($_GET['sSort']) ? '&amp;sSort=' . html($_GET['sSort']) : ''; ?>"
          name="form"
          method="post"
          class="main-form">

        <header class="mainPage__header">
            <h1 class="mainPage__title">Rezerwacje</h1>
        </header>

        <?php
        if (isset($_GET['sOption'])) {
            echo '<div class="alert alert-success mb-3">' . html($lang['Operation_completed']) . '</div>';
        }
        ?>

        <div class="table-responsive">
            <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Klient</th>
                        <th>Typ usługi</th>
                        <th>Data</th>
                        <th>Godzina</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $sRows; ?>
                </tbody>
            </table>
        </div>
    </form>
<?php } else { ?>
    <div class="alert alert-danger"><?php echo html($lang['Data_not_found']); ?></div>
<?php } ?>

<?php
require_once 'templates/admin/_footer.php';
?>
