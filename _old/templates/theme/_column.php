<?php

if ($aData['sPanel'] != 0 ){  ?>
  <aside class="mainPage__column">
   
   <?php
                            
                            
      if(isset($aData['iPageParent']) && $aData['iPageParent'] != 0){
          
          
          $title_page = $oPage->throwPage( $aData['iPageParent'] );
          
          $menu_name = "<a href='".$oPage->aPages[$aData['iPageParent']]['sLinkName']."' class='back'>".$title_page['sName']."</a>";
          
          
          echo $oPage->listMenu($aData['iPageParent'], array('title' => $menu_name));
      }
  ?>
      
      
   <div class="widget">
       <h4 class="widget__title">Szybki kontakt</h4>
       <div class="widget__content">
           <?php echo contacts(); ?>
           <?php echo hoursOpen(TRUE); ?>
       </div>
   </div>
   
   <div class="widget">
       <h4 class="widget__title">Social media</h4>
       <div class="widget__content">
           <?php echo socialMedia(); ?>
       </div>
   </div>
   
   <?php echo $oFile->listImages( 58, Array( 'iType' => 3, 'class' => 'widgetImages'  ) );  ?>
   
<!--   <h5 class="mb-2">Ostatnio na blogu</h5>-->
<!--
   <script>
        $(document).ready(function() {
            $('.pagesListColumn').owlCarousel({
                loop:true,
                margin: 20,
                nav:false,
                dots:true,
                autoplay:true,
                autoplayHoverPause:true,
//                        animateOut: 'fadeOut',
                autoplayTimeout: 5000,
                items: 1,
            });
        });
    </script>
-->
   <?php // echo $oPage->listPages(10, array('hide_cat' => TRUE, 'limit' => 3, 'pagination' => FALSE, 'class' => 'pagesListColumn owl-carousel')); ?>
      
    
  </aside>
<?php } ?>

     