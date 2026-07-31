<?php
if (!defined('ADMIN_PAGE')) {
    exit('Script by OpenSolution.org');
}

$currentPage = $_GET['p'] ?? 'languages';
$langEdit    = isset($_GET['sLangEdit']) ? strtolower(trim($_GET['sLangEdit'])) : '';
$itemDelete  = isset($_GET['sItemDelete']) ? strtolower(trim($_GET['sItemDelete'])) : '';

$isLangCode = static function ($v): bool {
    return is_string($v) && preg_match('/^[a-z]{2}$/', $v);
};

// Zapis zmiennych językowych
if (isset($_POST['sOption']) && $isLangCode($langEdit)) {
    saveVariables($_POST, $config['dir_database'] . 'lang_' . $langEdit . '.php', 'lang');
    header('Location: ' . $config['admin_file'] . '?p=' . $currentPage . '&sOption=save&sLangEdit=' . $langEdit);
    exit;
}

// Usunięcie języka
if ($isLangCode($itemDelete)) {
    deleteLanguage($itemDelete);
    header('Location: ' . $config['admin_file'] . '?p=' . $currentPage . '&sOption=del');
    exit;
}

$sSelectedMenu = 'tools';

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

$aVariables = $isLangCode($langEdit) ? listLangVariables($langEdit) : null;
?>

<a href="?p=languages-form"><?php echo $lang['New_language']; ?></a>

<section id="body" class="langs">

    <h1>
        <?php echo $lang['Languages'] . ($isLangCode($langEdit) ? ' ' . html($langEdit) : ''); ?>
    </h1>

    <?php
    if (isset($config['manual_link'])) {
        echo '<div class="manual"><a href="' . $config['manual_link'] . 'instruction#languages" title="' . $lang['Help'] . '" target="_blank"></a></div>';
    }

    if (isset($_GET['sOption'])) {
        echo '<h2 class="msg">' . $lang['Operation_completed'] . '</h2>';
    }
    ?>

    <?php if (isset($aVariables)): ?>

        <form action="" method="get" class="search box" onsubmit="return false;">
            <fieldset>
                <label for="sSearch"><?php echo $lang['search']; ?></label>
                <input
                    type="text"
                    name="sSearch"
                    id="sSearch"
                    class="search"
                    placeholder="<?php echo $lang['search']; ?>"
                    value=""
                    size="50"
                    onkeyup="listSearch(this, 'tab-front-end', true)"
                />
            </fieldset>
        </form>

        <form action="?p=<?php echo html($currentPage); ?>&amp;sLangEdit=<?php echo html($langEdit); ?>" name="form" method="post" class="main-form">
            <fieldset>

                <ul class="buttons">
                    <li class="save">
                        <input type="submit" name="sOption" class="main" value="<?php echo $lang['save']; ?>" />
                    </li>
                </ul>

                <ul class="tabs">
                    <li id="front-end" class="selected"><a href="#"><?php echo $lang['Front_end_back_end']; ?></a></li>
                    <li id="back-end"><a href="#"><?php echo $lang['Back_end_only']; ?></a></li>
                </ul>

                <script>
                    $(function () {
                        var sCurrentTab = displayTabInit(changeSearchAttr);
                        if ($('#tab-' + sCurrentTab).length > 0) {
                            changeSearchAttr($('#' + sCurrentTab));
                        }
                    });
                </script>

                <ul id="tab-front-end" class="forms list">
                    <?php echo $aVariables[0]; ?>
                </ul>

                <ul id="tab-back-end" class="forms list">
                    <?php echo $aVariables[1]; ?>
                </ul>

                <ul class="buttons bottom">
                    <li class="save">
                        <input type="submit" name="sOption" class="main" value="<?php echo $lang['save']; ?>" />
                    </li>
                </ul>

            </fieldset>
        </form>

    <?php else: ?>

        <table class="list">
            <thead>
                <tr>
                    <th class="name"><?php echo $lang['Name']; ?></th>
                    <th class="options">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <?php echo listLanguages(); ?>
            </tbody>
        </table>

    <?php endif; ?>

</section>

<?php
require_once 'templates/admin/_footer.php';
?>
