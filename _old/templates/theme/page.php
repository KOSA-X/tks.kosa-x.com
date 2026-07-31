<?php 
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;
require_once 'templates/'.$config['skin'].'/_header.php';

if( isset( $aData['sName'] ) ){
    require_once 'templates/'.$config['skin'].'/_title.php';
           
?>
<div class="container">
  <div class="mainPage__wrapper">
    <?php require_once 'templates/'.$config['skin'].'/_column.php'; ?>

        <div class="mainPage__content">
        
        <?php if($aData['sPrice'] != ""){ ?>
            <div class="productMore">
                <div class="productMore__gallery">
                    <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 1,  'slider' => TRUE)); ?>
                </div>
                <div class="productMore__content">
                    <?php echo ($aData['sDescriptionShort'] != ""  ? '<div class="productMore__desc">'.$aData['sDescriptionShort'].'</div>' : null); ?>
                    <div class="productMore__price">
                        <div class="label">Cena</div>
                        <div class="value"><?php echo $aData['sPrice']; ?> zł</div>
                    </div>
                    <button class="add-to-cart button button-lg w-100" data-product-id="<?php echo $aData['iPage']; ?>" data-action="add"><img src="<?php echo ICONS; ?>cart.svg" alt="Dodaj do koszyka">Dodaj do koszyka</button>
                </div>
            </div>
            
            <article class="mainPage__article productMore__article ">
                 <?php echo (($aData['sDescriptionFull'] != "" && $aData['sDescriptionFull'] != $aData['sDescriptionShort'] ) ? '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>' : null); ?>
                 <?php echo $oFile->listFiles( $aData['iPage'] ); ?>
             </article>
             
             <hr class="separator">
             
        <div class="productMore__discover">
            <header class="section__header">
                   <h2 class="section__title">Odkryj więcej smaków</h2>
                   <div class="section__subtitle"><?php echo getData(16, "sDescriptionShort"); ?></div>
            </header>
           <script>
                $(document).ready(function() {
                    $('.ourProducts__slider').owlCarousel({
                        loop:true,
                        margin: 20,
                        nav:true,
                        dots:true,
                        autoplay:true,
                        autoplayHoverPause:true,
//                        animateOut: 'fadeOut',
                        autoplayTimeout: 5000,
                        responsive : {
                            0 : {
                               items: 1,
                            },
                            578 : {
                                items: 1,
                            },
                            768 : {
                                items: 2,
                            },
                            992 : {
                                items: 3,
                            },
                            1200 : {
                                items: 4,
                            }
                        }
                    });
                });
            </script>
            <?php echo $oPage->listPages(6, array('hide_cat' => TRUE, 'class' => 'pagesList-6 ourProducts__slider owl-carousel')); ?>
        </div>
            
        <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 3, 'class' => 'galleryGrid productMore__galleryGrid')); ?>
             
        <?php }else{ ?>
        
        
         <article class="mainPage__article">
                      
             <?php echo ($aData['sDescriptionShort'] != ""  ? '<div class="mainPage__desc font-md">'.$aData['sDescriptionShort'].'</div>' : null); ?>

             <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 1, 'class' => 'gallerySlider', 'full_image' => TRUE,  'slider' => TRUE)); ?>

             <?php echo (($aData['sDescriptionFull'] != "" && $aData['sDescriptionFull'] != $aData['sDescriptionShort'] ) ? '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>' : null); ?>

             <?php echo $oFile->listFiles( $aData['iPage'] ); ?>
             
         </article>
         
         

         <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 3, 'class' => 'galleryGrid')); ?>

         <?php echo ($aData['sDate'] ? '<div class="mainPage__date">Data publikacji '.$aData['sDate'].'</div>' : null); ?>
         
         <?php } ?>

       </div>
   </div>
    <?php echo $oPage->listPages($aData['iPage'], array('hide_cat' => TRUE, 'type' => $aData['sType'], 'class' => 'pagesList-'.$aData['iPage'])); ?>   
</div>
 
<?php 
} else{ 
    require_once 'templates/'.$config['skin'].'/_404.php';
}

require_once 'templates/'.$config['skin'].'/_footer.php'; ?>