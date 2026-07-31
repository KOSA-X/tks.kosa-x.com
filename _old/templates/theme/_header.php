<?php
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;
require_once 'templates/'.$config['skin'].'/_meta.php';
?>
<header class="mainHeader">
    <div class="mainHeader__center">
        <div class="container">
            <div class="mainHeader__logo">
                <a href="<?php echo $base_url ?>">
                   <img src="<?php echo $logo_image; ?>" alt="<?php echo $config['logo']; ?>" class="logo_desktop logo_img">
                   <img src="<?php echo IMAGES; ?>favicon.webp" alt="<?php echo $config['logo']; ?>" class="logo_mobile logo_img">
                </a>
            </div>
            <?php // echo cartIcon(); ?>
            <div class="mainHeader__menu_button">
                <div class="menuHamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <div class="mainHeader__menu">
              <div class="mainHeader__menu_head"><?php echo $logo; ?></div>
              <?php echo $oPage->listPagesMenu(1, Array('sClassName' => 'headerMenu', 'bExpanded' => TRUE, 'iDepthLimit' => 1, 'scroll' => ($aData['iPage'] == 1 ? TRUE : FALSE) ) );  ?>
     
              
              
            </div>
        </div>
    </div>
 
</header>
<?php if($aData['iPage'] == 10000){ ?>
<section class="videoHeader">
    <video class="videoHeader__background" autoplay loop muted="muted" playsinline>
        <source src="images/video-1.mp4" type="video/mp4">
        Twoja przeglądarka nie obsługuje elementu wideo.
    </video>
    <div class="videoHeader__content showUp">
       <div class="logo"><img src="<?php echo IMAGES; ?>logo-circle.svg" alt="<?php echo $config['logo']; ?>"></div>
        <h1><?php echo $config['logo']; ?></h1>
        <p><?php echo $config['slogan']; ?></p>
        <a href="images/intro-winnica-juliana.mp4" data-fancybox="video" class="videoHeader__button button button-border">Zobacz wideo</a>
    </div>
    <a class="videoHeader__arrow scrollTo" href="#" class="scroll-to" data-id="aboutUs"><img src="<?php echo ICONS; ?>arrow-long.svg" alt="Przeglądaj dalej"></a>
</section>
<?php } ?>

<main class="mainBody">


 