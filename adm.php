<?php

// BEZPIECZEŃSTWO: domyślnie nie pokazuj błędów (DEVELOPER_MODE w config.php włącza je z powrotem).
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);
/*
* Quick.Cms by OpenSolution.org
* www.OpenSolution.org
*/
$_SERVER['REQUEST_URI'] = htmlspecialchars( strip_tags( $_SERVER['REQUEST_URI'] ) );
$_GET['p'] = isset( $_GET['p'] ) ? htmlspecialchars( strip_tags( $_GET['p'] ) ) : '';

session_start( );

define( 'ADMIN_PAGE', true );
require_once 'database/config.php';

if( isset( $config['allowed_ip_admin_panel'] ) && $config['allowed_ip_admin_panel'] != $_SERVER['REMOTE_ADDR'] ){
  header( 'Location: ./' );
  exit;
}

header( 'Content-Type: text/html; charset=utf-8' );

require_once 'core/libraries/file-jobs.php';
require_once 'core/libraries/image-jobs.php';
$oIJ = ImageJobs::getInstance( );

require_once 'core/libraries/trash.php';
require_once 'core/libraries/forms-validate.php';
require_once 'core/libraries/sql.php';
$oSql = Sql::getInstance( );

require_once 'plugins/settings.php';


require_once 'core/common.php';
require_once 'core/common-admin.php';
getBinValues( true );
loginActions( );

require_once 'core/pages.php';
require_once 'core/pages-admin.php';
$oPage = PagesAdmin::getInstance( );

require_once 'core/files.php';
require_once 'core/files-admin.php';
$oFile = FilesAdmin::getInstance( );

require_once 'core/lang-admin.php';

require_once 'plugins/plugins-admin.php';

listMessagesNews( );
listMessagesNotices( );
// BEZPIECZEŃSTWO (CVE-2025-54174, CVE-2026-1468): ochrona przed CSRF.
// Każde żądanie zmieniające stan (POST lub usuwanie przez GET) musi zawierać
// poprawny token CSRF. Poprzednia wersja opierała się wyłącznie na nagłówku
// Referer, co można było obejść przez jego pominięcie (np. Referrer-Policy).
$bStateChanging = (
  ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' )
  || isset( $_GET['iItemDelete'] )
  || isset( $_GET['sItemDelete'] )
);
if( $bStateChanging && !checkCsrfToken() ){
  header( 'Location: '.$config['admin_file'].'?p=dashboard' );
  exit;
}

if( strstr( $_GET['p'], 'ajax-' ) ){
  if( $_GET['p'] == 'ajax-files-upload' && !empty( $_GET['sFileName'] ) ){
    echo $oFile->uploadFile( $_GET['sFileName'] );
  }
  elseif( $_GET['p'] == 'ajax-files-in-dir' ){
    header( 'Cache-Control: no-cache' );
    header( 'Content-type: text/html' );
    echo $oFile->listFilesInDir( Array(
      'sSort'   => 'time',
      'iSite'   => isset( $_GET['iSite'] ) ? (int) $_GET['iSite'] : 1,
      'sSearch' => isset( $_GET['sSearch'] ) ? (string) $_GET['sSearch'] : '',
    ) );
  }
  elseif( $_GET['p'] == 'ajax-files-thumb' && isset( $_GET['sFileName'] ) ){
    echo '<img src="templates/admin/img/none.png" />';
  }
  elseif( $_GET['p'] == 'ajax-messages-news' && isset( $_SESSION['sMessagesNews'] ) ){
    echo $_SESSION['sMessagesNews'].( $_SESSION['iMessagesNewsNumber'] > 0 ? '<footer><a href="#" onclick="clearMessages( \'news\' );return false;">'.$lang['Mark_as_read'].'</a></footer>' : null );
  }
  elseif( $_GET['p'] == 'ajax-messages-notices-clear' ){
    $_SESSION['iMessagesNoticesNumber'] = 0;
    updateBin( 'failed_logs', 0 );
  }
  elseif( $_GET['p'] == 'ajax-verify-login' ){
    if( isset( $_GET['sVerifyemail'] ) && isset( $_GET['sVerifypass'] )
        && hash_equals( (string) ( $config['login_email'] ?? '' ), changeSpecialChars( $_GET['sVerifyemail'] ) )
        && adminPasswordVerify( $_GET['sVerifypass'] ) ){
      echo 'true';
    }
  }
  elseif( $_GET['p'] == 'ajax-messages-notices' && isset( $_SESSION['sMessagesNotices'] ) ){
    echo $_SESSION['sMessagesNotices'].( $_SESSION['iMessagesNoticesNumber'] > 0 ? '<footer><a href="#" onclick="clearMessages( \'notices\' );return false;">'.$lang['Mark_as_read'].'</a></footer>' : null );
  }
  // end ajax requests
  exit;
}
elseif( !empty( $_GET['p'] ) && preg_match( '/^[a-z0-9-]+$/', $_GET['p'] ) && is_file( 'templates/admin/'.$_GET['p'].'.php' ) )
  require 'templates/admin/'.$_GET['p'].'.php';
else
  require_once 'templates/admin/home.php';
?>