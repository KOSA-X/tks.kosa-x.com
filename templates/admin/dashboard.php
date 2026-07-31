<?php 
if( !defined( 'ADMIN_PAGE' ) )
  exit;

if( !isset( $config['url_domain'] ) )
  getSiteUrl( );

$sSelectedMenu = 'dashboard';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>

  <header class="mainPage__header mainPage__header_row">
   <h1 class="mainPage__title"><?php echo $lang['Dashboard']; ?></h1>

</header>


<div class="row">
    <div class="col-6">
        <div class="card mb-4">
            <header class="card__header">
                <h4 class="card__title"><img src="templates/admin/img/icons/page.svg" alt="">Strony</h4>
            </header>
            <div class="card__wrapper">
                <ul class="card__list">
                    <li><a href="?p=pages" class="submenu_link" title="Dodaj">Lista stron</a></li>
                    <li><a href="?p=pages-form" class="submenu_link" title="Dodaj">Nowa strona</a></li>
                </ul>
            </div>
        </div>
        
    </div>
    
    <div class="col-6">
        <div class="card mb-4">
            <header class="card__header">
                <h4 class="card__title"><img src="templates/admin/img/icons/info.svg" alt="">Pomoc</h4>
            </header>
            <div class="card__content">
               <p>Jeżeli masz jakieś pytania i potrzebujesz pomocy skontaktuj się:</p>
               <a href="mailto:konrad@kosiorski.pl">konrad@kosiorski.pl</a> | <a href="https://kosa-x.com">kosa-x.com</a>
               
            </div>
        </div>
        
    </div>
    
    <div class="col-6">
        <div class="card mb-4">
            <header class="card__header">
                <h4 class="card__title"><img src="templates/admin/img/icons/settings.svg" alt="">Quick CMS</h4>
            </header>
            <div class="card__wrapper">
               <div id="welcome" class="panel">
      <section>
        <!-- LICENSE REQUIREMENTS, DONT DELETE OR HIDE THIS IFRAME AND CONTENT OF THIS IFRAME -->
        <iframe style="width:100%; height:300px; border:0" src="https://opensolution.org/dashboard-iframe.html?sLang=<?php echo $config['admin_lang']; ?>&amp;sUrl=<?php echo $config['url_domain']; ?>&amp;sScript=Quick.Cms&amp;sVersion=<?php echo $config['version'].( defined( 'DEVELOPER_MODE' ) ? '&amp;bDeveloper=' : null ); ?>"></iframe>
        <!-- LICENSE REQUIREMENTS, DONT DELETE OR HIDE THIS IFRAME AND CONTENT OF THIS IFRAME -->
      </section>
    </div>
               
            </div>
        </div>
    </div>
    
    
    <div class="col-6">2</div>
</div>

 
<?php
require_once 'templates/admin/_footer.php';
?>
