<?php
if( !defined( 'ADMIN_PAGE' ) )
  exit;
?>
<!DOCTYPE HTML>
<html lang="<?php echo $config['language']; ?>">
<head>
  <title>Admin - Quick.Cms v<?php echo $config['version']; ?></title>
  <meta name="description" content="" />
  <meta name="generator" content="Quick.Cms v<?php echo $config['version']; ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="author" content="kosa-x.com">
    <link id="favicon" rel="shortcut icon" type="image/x-icon" href="<?php echo $favicon; ?>">
    <link rel="apple-touch-icon" href="<?php echo $favicon; ?>">
    <link rel="canonical" href="<?php echo $actual_url; ?>">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js" ></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
    
    
<link rel="stylesheet" href="templates/admin/css/style.css?<?php echo filemtime("templates/admin/css/style.css"); ?>" media="all"/>
 
  <link rel="stylesheet" href="plugins/valums-file-uploader/fileuploader.css" type="text/css" />
  <link rel="stylesheet" href="plugins/chosen/chosen.css" />
<!--  <script src="plugins/jquery.min.js"></script>-->
  <script src="core/common.js"></script>
  <script src="core/common-admin.js"></script>
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    
    <script src="templates/admin/js/scripts.js?<?php echo filemtime("templates/admin/js/scripts.js"); ?>"></script>  
    
    
  <script>
    var aCF = {
          'sWarning' : '<?php echo $lang['Fill_required_fields']; ?>',
          'sEmail' : '<?php echo $lang['Type_correct_email']; ?>',
          'sTooShort' : '<?php echo $lang['Txt_to_short']; ?>',
          'sInt' : '<?php echo $lang['Wrong_value']; ?>'
        },
        aQuick = {
          'sPhpSelf' : '<?php echo $config['admin_file']; ?>',
          'sIncorrectData' : '<?php echo $lang['Incorrect_data']; ?>',
          'sConfirmShure' : '<?php echo $lang['Operation_sure']; ?>',
          'sNoResultsMatch': '<?php echo $lang['No_results_match']; ?>',
          'sSelect': '<?php echo $lang['Select']; ?>',
          'sDelShure' : '<?php echo $lang['Operation_sure_delete']; ?>'
        },
        aQConf = {
          aChosen: { sDefaultWidth: '<?php echo $config['advanced_select_default_width']; ?>', sLongWidth: '<?php echo $config['advanced_select_long_width']; ?>', sVeryLongWidth: '<?php echo $config['advanced_select_very_long_width']; ?>', iDisableSearchThreshold: '<?php echo $config['enable_searching_in_advanced_select_from']; ?>' }
        },
        oLoad;
    <?php if( isset( $sSelectedMenu ) ){ ?>$(function(){ $( '#header .menu li.<?php echo $sSelectedMenu; ?>' ).addClass( 'selected' ); }); <?php } ?>
  </script>
</head>
<body>
 