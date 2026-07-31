<?php 
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;
require_once 'templates/'.$config['skin'].'/_header.php';

require_once 'templates/'.$config['skin'].'/_title.php';
           
?>
<style>
    .mainHeader{
        display: none;
    }
    .mainPage__header{
        padding: 30px 0 !important
    }
    .pagesTree{
        display: none;
    }
</style>
<div class="container">
  <div class="mainPage__wrapper">
   

        <div class="mainPage__content">
         <article class="mainPage__article">
                      
             <?php echo ($aData['sDescriptionShort'] != ""  ? '<div class="mainPage__desc font-md">'.$aData['sDescriptionShort'].'</div>' : null); ?>

             <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 1, 'class' => 'gallerySlider', 'full_image' => TRUE,  'slider' => TRUE)); ?>

             <?php echo (($aData['sDescriptionFull'] != "" && $aData['sDescriptionFull'] != $aData['sDescriptionShort'] ) ? '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>' : null); ?>

             <?php echo $oFile->listFiles( $aData['iPage'] ); ?>
             
         </article>

      
       </div>
   </div>
   
</div>
 
<?php 
 
//require_once 'templates/'.$config['skin'].'/_footer.php';
?>