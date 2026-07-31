<?php

if ($aData['sPanel'] != 0 ){  
    
    if($aData['iMenu'] == 2 || $aData['iPage'] == $config['shop_page']){
 
        // SKLEP ONLINE
        
        echo '<div class="mainPage__column">';
        
        echo listMenu(array(
            'sql' => 'iPageParent=0 AND iMenu=2', 
            'title' => '<img src="'.ICONS.'shop.svg" alt="Sklep" class="icon invert"><a href="'.getUrl($config['shop_page']).'" class="back">Sklep online</a>',
            'toggle' => TRUE
        ));
        
        $filterUrlPage = $aData['iPageParent'] != 0 || $aData['sPrice'] != "" ? getUrl($aData['iPageParent']) : getUrl($aData['iPage']);
        
        echo renderFiltersWidget($filterUrlPage, ['page']);

        
        echo '</div>';
        
    }else{ 
        // STANDARD
        echo '<div class="mainPage__column">';
          if(isset($aData['iPageParent']) && $aData['iPageParent'] != 0){
              
              echo listMenu(array(
                    'sql' => 'iPageParent="'.$aData['iPageParent'].'" ', 
                    'title' => '<a href="'.getUrl($aData['iPageParent']).'" class="back">'.getData($aData['iPageParent'], 'sName').'</a>',
                    'toggle' => TRUE
                ));

          }
        echo '</div>';
    }
 }

     