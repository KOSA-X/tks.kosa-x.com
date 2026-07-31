<?php 
if( !defined( 'ADMIN_PAGE' ) )
  exit;

if( !isset( $config['url_domain'] ) )
  getSiteUrl( );

$sSelectedMenu = 'dashboard';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>

<section id="body" class="dashboard">
  <h1><?php echo $lang['Dashboard']; ?></h1>
  
  <ul id="messages">
      <li class="notices"><a href="#" onclick="<?php if( isset( $_SESSION['sMessagesNotices'] ) ){ echo 'throwMessages( \'notices\' );'; } ?>return false;"><img src="templates/admin/img/bell.png" alt="<?php echo $lang['Messages']; ?>" /><strong><?php echo ( ( isset( $_SESSION['iMessagesNoticesNumber'] ) && isset( $_SESSION['sMessagesNotices'] ) ) ? $_SESSION['iMessagesNoticesNumber'] : 0 ); ?></strong></a>
        <section>
          <header><?php echo $lang['Messages']; ?></header>
          <div><span class="loading"></span></div>
        </section>
      </li>
      <?php if( isset( $_SESSION['sMessagesNews'] ) ){ ?>
      <li class="news"><a href="#" onclick="throwMessages( 'news' );return false;"><img src="templates/admin/img/news.png" alt="<?php echo $lang['News']; ?>" /><strong><?php echo ( isset( $_SESSION['iMessagesNewsNumber'] ) ? $_SESSION['iMessagesNewsNumber'] : 0 ); ?></strong></a>
        <section>
          <header><?php echo $lang['News']; ?></header>
          <div><footer class="loading"></footer></div>
        </section>
      </li>
      <?php } ?>
    </ul>
    
  <section>
    <div id="welcome" class="panel">
      <section>
        <!-- LICENSE REQUIREMENTS, DONT DELETE OR HIDE THIS IFRAME AND CONTENT OF THIS IFRAME -->
        <iframe src="https://opensolution.org/dashboard-iframe.html?sLang=<?php echo $config['admin_lang']; ?>&amp;sUrl=<?php echo $config['url_domain']; ?>&amp;sScript=Quick.Cms&amp;sVersion=<?php echo $config['version'].( defined( 'DEVELOPER_MODE' ) ? '&amp;bDeveloper=' : null ); ?>"></iframe>
        <!-- LICENSE REQUIREMENTS, DONT DELETE OR HIDE THIS IFRAME AND CONTENT OF THIS IFRAME -->
      </section>
    </div>
  </section>

</section>
<?php
require_once 'templates/admin/_footer.php';
?>
