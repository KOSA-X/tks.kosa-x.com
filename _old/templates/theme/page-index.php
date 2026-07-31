<?php 
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;
require_once 'templates/'.$config['skin'].'/_header.php';

   
?>
 


  

  
<div class="mainPage" >

 
            
             <article class="mainPage__article">
                 <?php echo ($aData['sDescriptionShort'] != ""  ? '<div class="mainPage__desc font-md">'.$aData['sDescriptionShort'].'</div>' : null); ?>
                 
                 <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 1, 'class' => 'galleryList', 'full_image' => TRUE,  'bNoLinks' => TRUE)); ?>
                 
                 <?php echo (($aData['sDescriptionFull'] != "" && $aData['sDescriptionFull'] != $aData['sDescriptionShort'] ) ? '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>' : null); ?>
                 
                 <?php echo $oFile->listFiles( $aData['iPage'] ); ?>
             </article>
             
             <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 3, 'class' => 'galleryGrid')); ?>
             
             <?php echo ($aData['sDate'] ? '<div class="mainPage__date">Data publikacji '.$aData['sDate'].'</div>' : null); ?>
               
            
    <?php echo $oPage->listPages($aData['iPage'], array('hide_cat' => TRUE, 'class' => 'pagesList-'.$aData['iPage'])); ?>
       
        
</div>
 
<?php require_once 'templates/'.$config['skin'].'/_footer.php'; ?>