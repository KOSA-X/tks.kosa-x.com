<?php
if (!defined('ADMIN_PAGE')) {
    exit('Script by OpenSolution.org');
}

$id = 0;

if (isset($_POST['status'], $_POST['id'])) {
    $id     = (int) $_POST['id'];
    $status = (int) $_POST['status'];

    if ($id > 0) {
        $oSql->query('UPDATE orders SET status="' . $status . '" WHERE id="' . $id . '"');
    }

    header('Location: ' . $config['admin_file'] . '?p=orders&sOption=save&id=' . $id);
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];
}

if ($id > 0) {
    $aData = $oPage->throwOrderAdmin($id);
}

$sSelectedMenu = 'shop';

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

if (empty($aData) || empty($aData['id'])) {
    echo '<div class="alert alert-danger">' . $lang['Data_not_found'] . '</div>';
    require_once 'templates/admin/_footer.php';
    exit;
}
?>

<form action="?p=<?php echo html($_GET['p'] ?? 'orders-info'); ?>&amp;id=<?php echo (int) $aData['id']; ?>" name="form" method="post" class="main-form">

    <input type="hidden" name="id" id="id" value="<?php echo (int) $aData['id']; ?>" />

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title">Zamówienie ID <?php echo (int) $aData['id']; ?></h1>

        <div class="mainPage__buttons d-flex justify-content-between">
            <a href="?p=orders" class="button button-light mr-2">Wróć do listy</a>
            <input type="submit" name="sOption" class="button" value="<?php echo html($lang['save']); ?>" />
        </div>
    </header>

    <?php
    if (isset($config['manual_link'])) {
        echo '<div class="manual"><a href="' . $config['manual_link'] . 'instruction#orders" title="' . $lang['Help'] . '" target="_blank"></a></div>';
    }

    if (isset($_POST['status'])) {
        echo '<div class="alert alert-success">' . $lang['Operation_completed'] . '</div>';
    }
    ?>

    <ul class="tabs">
        <li id="content" class="selected"><a href="#content">Informacje</a></li>
    </ul>

    <script>
        var aLoginAjax = {};
        $(function () {
            displayTabInit();
            $(".main-form").quickform();
        });
    </script>

    <div id="tab-content" class="forms full tabsContent">

        <div class="form-item">
            <label for="status">Status</label>
            <select name="status" id="status">
                <?php echo getSelectFromArray($config['order_status'], isset($aData['status']) ? (int) $aData['status'] : 0); ?>
            </select>
        </div>

        <?php echo orderProducts($aData['products'] ?? ''); ?>

        <table class="list pages table mt-4 mb-4" cellpadding="0" cellspacing="0" border="0">
            <tbody>
                <tr>
                    <td>Imię i nazwisko</td>
                    <td><?php echo html($aData['name'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>E-mail</td>
                    <td><?php echo html($aData['email'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Telefon</td>
                    <td><?php echo html($aData['phone'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Adres</td>
                    <td>
                        <?php echo html($aData['street'] ?? ''); ?><br>
                        <?php echo html($aData['city'] ?? ''); ?> <?php echo html($aData['code'] ?? ''); ?>
                    </td>
                </tr>
                <tr>
                    <td>NIP</td>
                    <td><?php echo html($aData['nip'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Wiadomość</td>
                    <td><?php echo html($aData['message'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Płatność</td>
                    <td><strong><?php echo html(getElementArray($aData['payment'] ?? null, $config['order_payment'], 'label_price')); ?></strong></td>
                </tr>
                <tr>
                    <td>Dostawa</td>
                    <td>
                        <strong><?php echo html(getElementArray($aData['delivery'] ?? null, $config['order_delivery'], 'label_price')); ?></strong>

                        <?php
                        if ((int) ($aData['delivery'] ?? 0) === 1) {
                            echo '<p>' . html($aData['street'] ?? '') . ', ' . html($aData['city'] ?? '') . ' ' . html($aData['code'] ?? '') . '</p>';
                        }

                        if (!empty($aData['inpost_point_id'])) {
                            echo '<p>' . html($aData['inpost_point_id']) . ', ' . html($aData['inpost_point_address'] ?? '') . '</p>';
                        }
                        ?>
                    </td>
                </tr>
                <?php if (!empty($aData['discount_code']) && (float) ($aData['discount_amount'] ?? 0) > 0) { ?>
                <tr>
                    <td>Rabat</td>
                    <td>
                        <strong><?php echo html((string) $aData['discount_code']); ?></strong>
                        (<?php echo html(rtrim(rtrim(number_format((float) ($aData['discount_percent'] ?? 0), 2, '.', ''), '0'), '.')); ?>%)
                        · -<?php echo html(number_format((float) $aData['discount_amount'], 2, '.', '')); ?> PLN
                    </td>
                </tr>
                <?php } ?>
                <tr>
                    <td>Do zapłaty łącznie</td>
                    <td><strong><?php echo html((string) ($aData['price'] ?? '0')); ?> PLN</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="mt-3">
            Data złożenia zamówienia:
            <?php echo !empty($aData['date']) ? html(date('d.m.Y, H:i', (int) $aData['date'])) : '—'; ?>
        </div>

    </div>

</form>

<?php
require_once 'templates/admin/_footer.php';
?>
