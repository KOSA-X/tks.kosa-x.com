<?php

// BEZPIECZEŃSTWO: domyślnie NIE pokazuj błędów odwiedzającym (wyciek ścieżek/SQL/schematu).
// Tryb deweloperski (DEVELOPER_MODE w database/config.php) włącza wyświetlanie z powrotem.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

/*
* Quick.Cms by OpenSolution.org
* www.OpenSolution.org
*/
define( 'CUSTOMER_PAGE', true );
require_once 'database/config.php';


header( 'Content-Type: text/html; charset=utf-8' );
require_once 'core/libraries/file-jobs.php';
require_once 'core/libraries/trash.php';
require_once 'core/libraries/sql.php';

$oSql = Sql::getInstance( );


require_once 'plugins/settings.php';

require_once 'core/common.php';
getBinValues( );

require_once 'templates/'.$config['skin'].'/_lists.php';
require_once 'core/pages.php';
$oPage = Pages::getInstance( );


require_once 'core/shortcode.php';
$oShortcode = Shortcode::getInstance( );

require_once 'core/files.php';
$oFile = Files::getInstance( );

require_once 'core/sliders.php';
$oSlider = Sliders::getInstance( );



$config['logo_link'] = ( !empty( $config['start_page'] ) && isset( $oPage->aPages[$config['start_page']]['sLinkName'] ) ) ? $oPage->aPages[$config['start_page']]['sLinkName'] : null;
$config['body_id_class_name'] = null;

if( isset( $config['current_page_id'] ) && is_numeric( $config['current_page_id'] ) && isset( $oPage->aPages[$config['current_page_id']] ) ){
  $aData = $oPage->throwPage( $config['current_page_id'] );

  if( !empty( $aData['sRedirect'] ) ){
    header( 'Location: '.$aData['sRedirect'] );
    exit;
  }

  $config['title'] = trim( !empty( $aData['sTitle'] ) ? $aData['sTitle'].' - '.$config['title'] : ( ( !isset( $config['display_homepage_name_title'] ) && $config['current_page_id'] == $config['start_page'] ) ? $config['title'] : strip_tags( $aData['sName'] ?? '' ).' - '.$config['title'] ) );

  if( !empty( $aData['sDescriptionMeta'] ) )
    $config['description'] = $aData['sDescriptionMeta'];
  $aData['sPagesTree'] = $oPage->getPagesTree( $aData['iPage'] );
  $config['theme_file'] = isset( $config['themes'][$aData['iTheme']] ) ? $config['themes'][$aData['iTheme']] : $config['themes'][1];
  
  if( empty( $aData['sDescriptionFull'] ) && !empty( $aData['sDescriptionShort'] ) && isset( $config['short_to_full_description'] ) )
    $aData['sDescriptionFull'] = $aData['sDescriptionShort'];

  $config['body_id_class_name'] = ' id="page-'.$aData['iPage'].'"'.$oPage->getClassName( $config['current_page_id'] );
}
elseif( isset( $_GET['p'] ) ){
  if( $_GET['p'] == 'test' ){
  }
  // plugins actions
  else{
    $bError404 = true;
  }
}
else{
  $bError404 = true;
}

if( isset( $bError404 ) ){
  header( "HTTP/1.0 404 Not Found\r\n" );
  $config['title'] = $lang['404_error'].' - ';
  //$config['theme_file'] = '404.php';
}

if( !isset( $config['theme_file'] ) )
  $config['theme_file'] = $config['themes'][1];

require_once 'templates/'.$config['skin'].'/'.$config['theme_file'];
?>