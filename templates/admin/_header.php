<?php
if (!defined('ADMIN_PAGE')) {
    exit;
}
?>
<!doctype html>
<html lang="<?php echo $config['language']; ?>">
<head>
    <meta charset="utf-8">

    <title>Admin - Quick.Cms v<?php echo $config['version']; ?></title>

    <meta name="description" content="">
    <meta name="generator" content="Quick.Cms v<?php echo $config['version']; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="kosa-x.com">

    <link id="favicon" rel="shortcut icon" type="image/x-icon" href="<?php echo FAVICON; ?>">
    <link rel="apple-touch-icon" href="<?php echo FAVICON; ?>">
    <link rel="canonical" href="<?php echo CURRENT_URL; ?>">

    <!-- Preconnect (opcjonalnie przyspiesza pobieranie z CDN) -->
    <link rel="preconnect" href="https://ajax.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://code.jquery.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

    <!-- CSS -->
    <link rel="stylesheet" href="templates/admin/css/style.css?<?php echo filemtime('templates/admin/css/style.css'); ?>" media="all">
    <link rel="stylesheet" href="plugins/valums-file-uploader/fileuploader.css" type="text/css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">
    <link rel="stylesheet" href="templates/admin/css/MultiSelect.css">

    <!-- JS: biblioteki -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>

    <!-- JS: Quick.Cms -->
    <script src="core/common.js"></script>
    <script src="core/common-admin.js"></script>
    <script src="templates/admin/js/MultiSelect.js"></script>
    <script src="templates/admin/js/files-sortable.js"></script>

    <!-- JS: Twoje -->
    <script src="templates/admin/js/scripts.js?<?php echo filemtime('templates/admin/js/scripts.js'); ?>"></script>

    <script>
        var aCF = {
                sWarning: <?php echo adminJsStr($lang['Fill_required_fields']); ?>,
                sEmail: <?php echo adminJsStr($lang['Type_correct_email']); ?>,
                sTooShort: <?php echo adminJsStr($lang['Txt_to_short']); ?>,
                sInt: <?php echo adminJsStr($lang['Wrong_value']); ?>
            },
            aQuick = {
                sPhpSelf: <?php echo adminJsStr($config['admin_file']); ?>,
                sTokenCsrf: <?php echo adminJsStr(getCsrfToken()); ?>,
                sIncorrectData: <?php echo adminJsStr($lang['Incorrect_data']); ?>,
                sConfirmShure: <?php echo adminJsStr($lang['Operation_sure']); ?>,
                sNoResultsMatch: <?php echo adminJsStr($lang['No_results_match']); ?>,
                sSelect: <?php echo adminJsStr($lang['Select']); ?>,
                sDelShure: <?php echo adminJsStr($lang['Operation_sure_delete']); ?>
            },
            aQConf = {
                aChosen: {
                    sDefaultWidth: <?php echo adminJsStr($config['advanced_select_default_width']); ?>,
                    sLongWidth: <?php echo adminJsStr($config['advanced_select_long_width']); ?>,
                    sVeryLongWidth: <?php echo adminJsStr($config['advanced_select_very_long_width']); ?>,
                    iDisableSearchThreshold: <?php echo adminJsStr($config['enable_searching_in_advanced_select_from']); ?>
                }
            },
            oLoad;

        <?php if (isset($sSelectedMenu)) { ?>
        $(function () {
            $('#header .menu li.<?php echo $sSelectedMenu; ?>').addClass('selected');
        });
        <?php } ?>
    </script>
</head>
<body>
