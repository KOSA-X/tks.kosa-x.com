<?php
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;

/**
* Displays page in the menu - default settings
* @return string
* @param array $aDatad
* @param array $aParametersExt
*/




function listPagesMenuView( $aData, $aParametersExt ){
    $sClassName = null;
    $submenu = FALSE;
    $arrow = "";
    $sSubMenu = $aParametersExt['sSubMenu'];
    $oFile = Files::getInstance( );
    
    $icon_url = $oFile->getDefaultImageUrl( $aData['iPage'], Array( 'iType' => 2, 'sLink' => ( !isset( $aParametersExt['bNoLinks'] ) ? $aData['sLinkName'] : null ), 'bNoLinks' => ( isset( $aParametersExt['bNoLinks'] ) ? true : null ) ) );
    
    $icon = $icon_url ? "<img src='".$icon_url."' alt='".$aData['sName']."' class='menu_icon invert'>" : "";
    

    if( isset( $aParametersExt['bSelected'] ) )
        $sClassName .= ' selected';

    if($aData['sExpandMenu'] == 0){
        $sSubMenu = FALSE;
    }

    if( $sSubMenu ){
        $sClassName .= ' menu_item_submenu';
        $arrow = '<span class="menu_arrow invert" data-menu="'.$aData['iPage'].'"></span>';
    }

    if( $aData['iPageParent'] > 0 )
        $submenu = TRUE;
        
    
  return '<li class="'.( $submenu ? "submenu_item menu_item" : "menu_item" ).( isset( $sClassName ) ? $sClassName : null ).'"><a href="'.$aData['sLinkName'].'" class="'.( $submenu ? "submenu_link menu_link" : "menu_link" ).'" title="'.$aData['sName'].'">'.$icon.$aData['sName'].'</a>'.$arrow.$sSubMenu.'</li>';
}  // end function listPagesMenu

/**
* Displays page in the list - default settings
* @return string
* @param array $aData
* @param array $aParametersExt
*/


function listMenuView($aData, $aParametersExt){
  return '<a href="'.$aData['sLinkName'].'" class="widget__link'.($aParametersExt['selected'] ? ' selected' : null).'">'.$aData['sName'].'</a>';
}


function listPagesView( $aData, $aParametersExt ){
  $oFile = Files::getInstance( );
    $class = "";
    $footer = "";
    $image = "";
    $price = "";
    $image_full = "";
    $desc = "";
    $category = "";
    $class = " pageItem-".$aData['iPage'];
    $popup = isset( $aParametersExt['popup'] ) ? $aParametersExt['popup'] : FALSE;
    $popup_content = "";
    
    
    
    // KATEGORIA
//    if($aData['iPageParent'] != 0 && !isset( $aParametersExt['hide_cat'] )){
//        $category = '<div class="category"><a href="'.$aParametersExt['category_url'].'">'.getData($aData['iPageParent'], "sName").'</a></div>';
//    }
    
    

    
    // LINK DO PUPUP LUB NIE
    if($popup){
        $link = '<a href="javascript:;" data-fancybox data-src="#pagePopup-'.$aData['iPage'].'" title="'.$aData['sName'].'">';
        
    }else{
        $link = ( !isset( $aParametersExt['bNoLinks'] ) ? '<a href="'.$aData['sLinkName'].'" title="'.$aData['sName'].'">' : null );
    }
         
    
    
    // ZDJĘCIE LUB IKONA    
    if( isset($aParametersExt['icon']) && $aParametersExt['icon'] == TRUE){
        $image = "<div class='icon'>".$link."<img src='".$oFile->getDefaultImageUrl( $aData['iPage'])."' alt='".$aData['sName']."' class='invertImg'>".($link != "" ? "</a>" : "")."</div>";
        $image_full = $image;
        $class .= " pageItem_icon";
    }else{
        $image = $oFile->getDefaultImage( $aData['iPage'], array( 'sLink' => $link ) );
//        $image_full = $oFile->getDefaultImage( $aData['iPage'], array( 'sLink' => FALSE, 'full_size' => TRUE ) );
    }
    
    
    // POPUP
    if($popup){
        $popup_content = '<div class="popup" style="display:none" id="pagePopup-'.$aData['iPage'].'"><a href="" style="width:0px;border:0;height:0;outline:0"></a>
        
        '.($image_full != "" ? '<div class="popup__image">'.$image_full.'</div>' : "").'
        <h2 class="popup__title">'.$aData['sName'].'</h2>
         '.($aData['sDescriptionShort'] != "" ? '<div class="popup__lead">'.$aData['sDescriptionShort'].'</div>' : '').'
         <div class="popup__desc">'.$aData['sDescriptionFull'].'</div>
         </div>';
    }
    
    
    
    
    
    // OPIS 
    if(isset($aData['sPrice']) && $aData['sPrice'] != ""){
        $class .= " productItem";
        $price = '<div class="price"><img src="'.ICONS.'cart.svg" alt="Koszyk" class="invert">'.$aData['sPrice'].' zł</div>';
    }
    
    
    if( !isset($aParametersExt['hide_desc'])){
        
        if(!empty( $aData['sDesc'] )){
            $desc = '<div class="desc">'.trunc($aData['sDesc'], 20).'</div>';
        }elseif(!empty( $aData['sDescriptionShort'] )){
            $desc = '<div class="desc">'.trunc($aData['sDescriptionShort'], 20).'</div>';
        }elseif(!empty( $aData['sDescriptionFull'] )){
            $desc = '<div class="desc">'.trunc($aData['sDescriptionFull'], 20).'</div>';
        }
    }
    
//      ( !isset( $aParametersExt['bNoLinks'] ) && !isset( $aParametersExt['hide_footer'] )  ? '<div class="footer"><a href="'.$aData['sLinkName'].'" class="button-link">Więcej</a>'.( !empty( $aData['sDate'] ) ? '<div class="date">'.$aData['sDate'].'</div>' : null ). '</div>' : null ). // short description here
          
    
    $footer = '<footer class="footer">';
    if($price){
        $footer .= $price;    
        
         // Pobierz ilość produktu w koszyku
//    $cart = isset($_COOKIE['cart']) ? json_decode($_COOKIE['cart'], true) : [];
//    $quantity = $cart[$row['id']] ?? 0;
//    echo "<p>W koszyku: {$quantity} szt.</p>";
//    
//    echo "<button class='add-to-cart' data-product-id='{$row['id']}'>Dodaj do koszyka</button>";
//        
        $footer .= '<button class="add-to-cart button button-border button-xs" data-product-id="'.$aData['iPage'].'" data-action="add" title="Do koszyka">Do koszyka</button>';   
          
    }else{
        $footer .= $link.'Więcej</a>';
        $footer .= ( !empty( $aData['sDate'] ) ? '<time class="date"><img src="'.ICONS.'calendar.svg" alt="Data publikacji">'.$aData['sDate'].'</time>' : null );
    }
    $footer .= '</footer>';
    
    
    
            
  return '<li class="pageItem'.$class.'">'
        .$image.
        '<div class="content">'
            .$category.
            '<h2 class="title">'.$link.$aData['sName'].( $link != "" ? '</a>' : null ).'</h2>'. 
            $desc. 
        '</div>'.
        $footer.$popup_content.
      '<script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "NewsArticle",
      "headline": "'.$aData['sName'].'"
    }
    </script></li>
    ';
      
//    ( !empty( $aData['sPrice'] ) ? '<div class="price">'.($aData['sPrice']).' <span class="currncy">zł</span></div>' : null ). // short description here
     
      
//    shareIcons($aData['iPage']).
    
} // end function listPagesView


function listFaqView( $aData, $aParametersExt ){
  $oFile = Files::getInstance( );
    $class = "";
    $class = " faqItem-".$aData['iPage'];
    
  return '<li class="faqItem'.$class.'">
  <h3 class="title"><a href="#" class="faqItemCollapse" data-id="'.$aData['iPage'].'" >'.$aData['sName'].'<span class="icon invertImg"></span></a></h3>
    <div class="content">'.$aData['sDescriptionShort'].'</div>
    </li>';
} 
 

/**
* Displays images
* @return string
* @param array $aData
* @param array $aParametersExt
*/
function listImagesView( $aData, $aParametersExt ){
  //return '<li'.( ( $aParametersExt['iElement'] % 4 ) == 1 ? ' class="row"' : null ).'>'. // oldie
    $class = "";
    $thumb = $aData['iSize'].'/';
    $desc = "";
    $title = !empty( $aData['sTitle'] ) || !empty( $aData['sDescription'] ) ? $aData['sTitle']. " ".$aData['sDescription'] : "Zdjęcie ".$aData['sFileName'];
    $youtube = !empty( $aData['sYoutube'] ) ? "data-src='".$aData['sYoutube']."'" : "";
    
    if(isset($aParametersExt['full_image']) && $aParametersExt['full_image'] == TRUE){
        $thumb = "";
    }    
    $image = '<picture class="galleryItem__image"><img src="files/'.$thumb.$aData['sFileName'].'" alt="'.$title.'" /></picture>';
    
    if(isset($aData['sUrl']) && $aData['sUrl'] != ""){
        $link = ' href="'.$aData['sUrl'].'" target="_blank"';
    }else{
        $link = 'href="files/'.$aData['sFileName'].'" data-fancybox="gallery['.( isset( $aData['iPage'] ) ? $aData['iPage'] : 0 ).']" title="'.$title.'" data-caption="'.(!empty( $aData['sTitle'] ) ? "<h5>".$aData['sTitle']."</h5>" : "").$aData['sDescription'].'"';
    }
    
  return '<li class="galleryItem'.$class.'" id="galleryItem_'.$aData['iFile'].'">'.
  ( !isset( $aParametersExt['bNoLinks'] ) ? '<a '.$youtube.' '.$link.' title="'.$title.'">'.($youtube ? '<span class="youtube"><img src="images/icons/play-button.png"></span>' : "" ) : null ).$image.(!empty( $aData['sTitle'] ) ? '<div class="galleryItem__desc">'.$aData['sTitle'].'</div>' : "").( !isset( $aParametersExt['bNoLinks'] ) ? '</a>' : null ).'</li>';
} // end function listImagesView

/**
* Displays files
* @return string
* @param array $aData
* @param array $aParametersExt
*/
function listFilesView( $aData, $aParametersExt ){
  return '<li class="'.$aData['sIconStyle'].'"><a href="files/'.$aData['sFileName'].'" target="_blank" class="link_underline">'.$aData['sFileName'].'</a>'.( !empty( $aData['sDescription'] ) ? '<p>'.$aData['sDescription'].'</p>' : null ).'</li>';
} // end function listFilesView

/**
* Displays sliders
* @return string
* @param array $aData
* @param array $aParametersExt
*/
function listSlidersView( $aData, $aParametersExt ){
  return '<li id="'.( !empty( $aParametersExt['sClassName'] ) ? $aParametersExt['sClassName'].'__item' : 'slider').'-'.$aData['iSlider'].'" class="'.( !empty( $aParametersExt['sClassName'] ) ? $aParametersExt['sClassName'].'__item' : null).'">
  
  '.( !empty( $aData['sFileName'] ) ? '<div class="image" style="background-image:url(\'files/'.$aData['sFileName'].'\')"  /></div>' : null ).
      

    '<div class="content"><div class="container">'.
      
    ( !empty( $aData['sTitle'] ) ? '<h2 class="title">'.( !empty( $aData['sUrl'] ) ? '<a href="'.$aData['sUrl'].'">' : null ).$aData['sTitle'].( !empty( $aData['sUrl'] ) ? '</a>' : null ).'</h2>' : null ).
    ( !empty( $aData['sDescription'] ) ? '<div class="desc">'.$aData['sDescription'].'</div>' : null ).
    
      ( !empty( $aData['sUrl'] ) ? '<a href="'.$aData['sUrl'].'" class="more button button-border">Więcej</a>' : null ).
      
      '</div></div></li>';
}// end function listSlidersView


/**
* Displays skip links
* @return string
*/
function displaySkipLinks( ){
  global $lang;
  return '<nav id="skiplinks" aria-label="skiplinks"><ul><li><a href="#head2">'.$lang['Skip_to_main_menu'].'</a></li><li><a href="#content">'.$lang['Skip_to_content'].'</a></li></ul></nav>';
} // end function displaySkipLinks

/**
* Displays back link
* @return string
*/
function displayBackLink( ){
  if( isset( $_SERVER['HTTP_REFERER'] ) && strstr( $_SERVER['HTTP_REFERER'], dirname( $_SERVER['SCRIPT_NAME'] ) ) ){
    return '<li class="back"><a href="javascript:history.back();"><svg class="icon" transform="rotate(180)"><use xlink:href="#svg-arrowround"></use></svg>'.$GLOBALS['lang']['back'].'</a></li>';
  }
} // end function displayBackLink

/**
* Displays hamburger for menu
* @return string
*/
function displayHamburger( ){
  global $lang;
  ob_start( );
  ?>
  <button class="hamburger hamburger--3dx" type="button">
    <span class="hamburger-box">
      <span class="hamburger-inner"></span>
    </span>
    <span class="hamburger-label"><?php echo $lang['Menu']; ?></span>
  </button>
  <?php
  $sReturn = ob_get_contents( );
  ob_end_clean( );
  return $sReturn;
} // end function displayHamburger

?>