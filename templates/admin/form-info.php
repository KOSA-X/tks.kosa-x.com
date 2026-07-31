<?php
if (!defined('ADMIN_PAGE')) {
    exit('Script by OpenSolution.org');
}

$sSelectedMenu = 'form';

if (!isset($oSql) && class_exists('Sql')) {
    $oSql = Sql::getInstance();
}

if (!isset($oPage) && class_exists('Pages')) {
    $oPage = Pages::getInstance();
}

/**
 * Safe date format (d.m.Y) – zwraca '' jeśli data pusta / niepoprawna
 */
function formatDatePl($date): string
{
    if (empty($date)) {
        return '';
    }

    $ts = strtotime((string)$date);
    if (!$ts) {
        return '';
    }

    return date('d.m.Y', $ts);
}

/**
 * POST: update status
 */
if (isset($_POST['iStatus'], $_POST['iForm'])) {
    $iForm = (int)$_POST['iForm'];
    $iStatus = (int)$_POST['iStatus'];

    if ($iForm > 0) {
        // Inty -> bezpieczne na tym etapie
        $oSql->query('UPDATE form SET iStatus=' . $iStatus . ' WHERE iForm=' . $iForm);
    }

    header('Location: ' . $config['admin_file'] . '?p=form&sOption=save&id=' . $iForm);
    exit;
}

/**
 * GET: load data
 */
$aData = [];
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $aData = (array)$oPage->throwFormAdmin((int)$_GET['id']);
}

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

$pParam = isset($_GET['p']) ? (string)$_GET['p'] : 'form';
$action = '?p=' . urlencode($pParam);

if (!empty($aData['iForm'])) {
    $action .= '&id=' . (int)$aData['iForm'];
}

$iFormId = (int)($aData['iForm'] ?? 0);
?>
<form action="<?php echo html($action); ?>" name="form" method="post" class="main-form">
    <input type="hidden" name="iForm" id="iForm" value="<?php echo $iFormId; ?>" />

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title">Rezerwacja ID <?php echo $iFormId; ?></h1>

        <div class="mainPage__buttons d-flex">
            <a href="?p=form" class="button button-light mr-2">Wróć do listy</a>
            <input type="submit" name="sOption" class="button button" value="<?php echo html($lang['save']); ?>" />
        </div>
    </header>

    <?php
    if (isset($config['manual_link'])) {
        echo '<div class="manual"><a href="' . html($config['manual_link']) . 'instruction#form" title="' . html($lang['Help']) . '" target="_blank"></a></div>';
    }

    if (isset($_GET['sOption']) && $_GET['sOption'] === 'save') {
        echo '<div class="alert alert-info">' . html($lang['Operation_completed']) . '</div>';
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
            <label for="iStatus">Zmień status</label>
            <select name="iStatus" id="iStatus">
                <?php echo getSelectFromArray($config['order_status'], isset($aData['iStatus']) ? (int)$aData['iStatus'] : 0); ?>
            </select>
        </div>

        <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
            <thead>
                <tr>
                    <th>Nazwa</th>
                    <th>Wartość</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Typ usługi</td>
                    <td><?php echo html(getElement($aData['sCategory'] ?? '', $config['form_categories'])); ?></td>
                </tr>
                <tr>
                    <td>Imię i nazwisko</td>
                    <td><?php echo html($aData['sUserName'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>E-mail</td>
                    <td><?php echo html($aData['sUserEmail'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Telefon</td>
                    <td><?php echo html($aData['sUserPhone'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Data start</td>
                    <td><?php echo html(formatDatePl($aData['sDateStart'] ?? '')); ?></td>
                </tr>

                <?php if (!empty($aData['sDateEnd'])) { ?>
                    <tr>
                        <td>Data koniec</td>
                        <td><?php echo html(formatDatePl($aData['sDateEnd'])); ?></td>
                    </tr>
                <?php } ?>

                <?php if (!empty($aData['sTime'])) { ?>
                    <tr>
                        <td>Godzina</td>
                        <td><?php echo html($aData['sTime']); ?></td>
                    </tr>
                <?php } ?>

                <?php if (!empty($aData['sMessage'])) { ?>
                    <tr>
                        <td>Wiadomość</td>
                        <td><?php echo nl2br(html($aData['sMessage'])); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</form>

<?php
require_once 'templates/admin/_footer.php';
