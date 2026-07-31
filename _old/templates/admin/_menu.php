<?php
if( !defined( 'ADMIN_PAGE' ) )
  exit;

if( !strstr( $_GET['p'], 'ajax-' ) ){
?>
  <div id="javascript" class="top-alert"><?php echo ( $config['admin_lang'] == 'pl' ? 'Włącz obsługę JavaScript w przeglądarce.' : 'Enable JavaScript in your web browser' ); ?></div>
  <script>
  document.getElementById( 'javascript' ).style.display = 'none';
  </script>
<?php
}


?>




<header class="mainHeader">
   <div class="mainHeader__top">
      <div class="container">
      <span class="mainHeader__top-title">Panel administracyjny <strong><?php echo $config['logo']; ?></strong></span>
       <ul class="topLinks">
           <li><a href="https://kosa-x.com" target="_blank"><img src="templates/admin/img/kosa-x-favicon.webp" alt="">KOSA X</a></li>
          <li><a href="?p=logout"><img src="templates/admin/img/off.svg" alt="" class="invert"><?php echo $lang['Log_out']; ?></a></li>
          <?php echo listLanguagesMenu( ); ?>
       </ul>
       
        </div>
   </div>
    <div class="mainHeader__center">
        <div class="container">
            <div class="mainHeader__logo">
                <a href="<?php echo $base_url ?>">
                   <img src="<?php echo $logo_image; ?>" alt="<?php echo $config['logo']; ?>" class="logo_desktop logo_img">
                   <img src="<?php echo IMAGES; ?>favicon.jpg" alt="<?php echo $config['logo']; ?>" class="logo_mobile logo_img">
                </a>
            </div>
             
            <div class="mainHeader__menu_button">
                <div class="menuHamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <div class="mainHeader__menu">
             
              
             
              <div class="mainHeader__menu_head"><?php echo $logo; ?></div>
              
               <nav id="menu-1" class="menu-1 headerMenu" aria-label="menu-1">
               
               <ul class="headerMenu__list">
                   <li class="menu_item "><a href="./" class="menu_link" title="Strona główna">Layout OBS</a></li>
                   
                   <li class="menu_item<?php echo ($sSelectedMenu == "pages" ? " selected" : ""); ?>"><a href="?p=pages" class="menu_link">Edycja zakładek</a></li>
                   
<!--                   <li class="menu_item<?php echo ($sSelectedMenu == "shop" ? " selected" : ""); ?>"><a href="?p=shop" class="menu_link"><?php echo $lang['Shop']; ?></a></li>-->
                   
<!--                   <li class="menu_item<?php echo ($sSelectedMenu == "orders" ? " selected" : ""); ?>"><a href="?p=orders" class="menu_link">Zamówienia</a></li>-->
                   
<!--                   <li class="menu_item<?php echo ($sSelectedMenu == "sliders" ? " selected" : ""); ?>"><a href="?p=sliders" class="menu_link"><?php echo $lang['Sliders']; ?></a></li>-->
                   
                   
                   <li class="menu_item<?php echo ($sSelectedMenu == "settings" ? " selected" : ""); ?>"><a href="?p=settings" class="menu_link"><?php echo $lang['Settings']; ?></a></li>
                   
                   
                           
                            
                   
                   
                  </ul>
                </nav>
              
              
              
            </div>
        </div>
    </div>
    <div class="mainHeader__bottom"></div>
</header>


<main class="mainBody">
<div class="container">
 