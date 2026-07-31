<?php 
if (!defined('CUSTOMER_PAGE')) {
    exit;
}
require_once $theme.'_header.php';
if (isset($aData['sName'])) {
    
    $product = $aData['sType'] == 2 ? TRUE : FALSE; 
    $content = '';
    
    $sDescriptionShort = !empty($aData['sDescriptionShort']) ? '<div class="mainPage__descShort">'.parseShortcodes($aData['sDescriptionShort']).'</div>' : '';
    $sDescriptionFull = !empty($aData['sDescriptionFull']) && $aData['sDescriptionFull'] !== $aData['sDescriptionShort'] ? '<div class="mainPage__descFull">'.parseShortcodes($aData['sDescriptionFull']).'</div>' : '';
    $listFiles = $oFile->listFiles($aData['iPage']);
    $sDate = !empty($aData['sDate']) ? '<div class="mainPage__date">Data publikacji '.$aData['sDate'].'</div>' : '';
         

    // SEKCJA mainPage_HEADER
    require_once $theme.'_title.php';

   
    echo '<div class="container">';
    echo '<div class="mainPage__wrapper">';
    
        require_once $theme.'_column.php'; // PANEL BOCZNY
        
        echo '<div class="mainPage__content">';
    
            // Filtry dla sklepu
            echo renderActiveFilters($oPage->aPages[$aData['iPage']]['sLinkName'], ['sort', 'page' ]);
    
                
           if ($product){
                ob_start();
                require_once $theme.'_shop_cart.php';// karta produktu
                $content .= ob_get_clean();
           }else{
               
                // =============================================
                // TREŚĆ ZAKŁADKI 
                // krótki opis
                $content .= $sDescriptionShort;
    
                

                // galeria główna (typ 1)
                $content .= $oFile->listImages($aData['iPage'], [
                    'iType'      => 1,
                    'full_image' => true,
                    'parallax'   => true,
                    'slider'     => true,
                    'class'      => 'mainPage negativeMargin'
                ]);

                // pełny opis – tylko jeśli inny niż krótki
                $content .= $sDescriptionFull;
                // pliki do pobrania
                $content .= $listFiles;
                // data publikacji
                $content .= $sDate;
                // =============================================
               
    
           }
    
            if ($content !== ''){
                echo '<article class="mainPage__article">';
                echo $content;
                echo '</article>';
            }

   
               
             
    
             // Gallery Grid
            echo $oFile->listImages($aData['iPage'], [
                'iType' => 3,
                'class' => 'galleryGrid'
            ]);
    
    

            // LISTA PODSTRON & PRODUKTOW W SKLEPIE

            if (defined('SHOP_PAGE') && SHOP_PAGE) { // SKLEP
                
                // lista produktow ze wszystkich kategorii
                echo listPagesQuery([
                    'sql'   => 'iPageParent!=0 AND iMenu=2',
                    'class' => 'productsList pagesList-'.$aData['iPage'],
//                         'per_page' => 1
                ]);
                
            }elseif($aData['iPage'] == $config['faq_page']){ // faq 
                
                echo $oPage->listFaq($config['faq_page'], ['bNoLinks' => true]);

            } else { // zwykła lista podstron
                
                echo $oPage->listPages($aData['iPage'], [
                    'footer' => true,
                    'per_page' => 10,
                ]);
            }
    
               
    
    
                
                
            echo '</div>'; // mainPage__content
        echo '</div>'; // mainPage__wrapper
        

    
        
        
    
    echo '</div>'; // container
    

   

} else {
    require_once $theme.'_404.php';
}

require_once $theme.'_footer.php';
?>
