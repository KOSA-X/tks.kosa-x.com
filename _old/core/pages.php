<?php
if( !defined( 'CUSTOMER_PAGE' ) && !defined( 'ADMIN_PAGE' ) )
  exit( 'Quick.Cms by OpenSolution.org' );


// licznik wyświetleń strony
function update_views($page_id){
    $oSql = Sql::getInstance( );
    $set_views = $oSql->getQuery( 'UPDATE pages SET sViews=sViews+1  WHERE iPage="'.$page_id.'" ' );
}


// skracanie stringa $string, ilość słow
function trunc($phrase, $max_words) {
   $phrase_array = explode(' ',$phrase);
   if(count($phrase_array) > $max_words && $max_words > 0)
      $phrase = implode(' ',array_slice($phrase_array, 0, $max_words)).'...';
   return strip_tags($phrase);
}



// ilość zdjęć 
function image_count($id){
    $oSql = Sql::getInstance( );
    $photo_array = $oSql->getQuery( 'SELECT * FROM files WHERE iPage="'.$id.'" AND iType="1"');
    $i = 0;
    while( $photo = $photo_array->fetch( PDO::FETCH_ASSOC ) ){
        $i++;
    }
    return $i;
}

function getAvatar($id){
    
	$oSql = Sql::getInstance( );
    
    $oQuery = $oSql->getQuery('SELECT iPage, '.$name.' FROM '.$type.' 
    WHERE iPage="'.$id.'"
    '.($type == 'files' ? 'AND iSize > 0  AND iDefault = "1" ' : '').' LIMIT 1');
    
    $getData = $oQuery->fetch(PDO::FETCH_ASSOC);
    
    if($getData[$name] != ""){
        return $getData[$name];
    } else {
        return FALSE;
    }
}


function getData($id, $name, $type = 'pages'){
	$oSql = Sql::getInstance( );
    
    $oQuery = $oSql->getQuery('SELECT iPage, '.$name.' FROM '.$type.' 
    WHERE iPage="'.$id.'"
    '.($type == 'files' ? 'AND iSize > 0  AND iDefault = "1" ' : '').' LIMIT 1');
    
    $getData = $oQuery->fetch(PDO::FETCH_ASSOC);
    
    if($getData[$name] != ""){
        return $getData[$name];
    } else {
        return FALSE;
    }
}

function parametrs($data, $options){
        $parametrs = "";
        $counterParametrs = 0;
        $counterWhile = 0;
        $contentParametrs = "";
        $string = str_replace(' ', '', $data);
        $parametrs = explode("|", $string);
        $counterParametrs = count($parametrs);
        if($counterParametrs > 0){
            while($counterWhile < $counterParametrs){
                if($parametrs[$counterWhile]){
                $contentParametrs .= "<li>".$parametrs[$counterWhile]."</li>";
//                $contentParametrs .= "<li><a href='szukaj/?tag=".$parametrs[$counterWhile]."'>".$parametrs[$counterWhile]."</a></li>";
                    }
                $counterWhile++;
            }
            return '<ul class="parametrs__list '.($options['class']).'">'.$contentParametrs.'</ul>';
        }
}
 
class Pages
{

  public $aPagesParentsMenus = null;
  public $aPages = null;
  public $sLanguageBackEndChoosed = null;
  public $aPagesChildrens = null;
  public $aPagesAllChildrens = null;
  public $aPagesParents = null;
  private static $oInstance = null;

  public static function getInstance( ){
    if( !isset( self::$oInstance ) ){  
      self::$oInstance = new Pages( );  
    }  
    return self::$oInstance;  
  } // end function getInstance

  /**
  * Constructor
  * @return void
  */
  private function __construct( ){
    $this->generateCache( );
  } // end function __construct

  /**
  * Generates cache variables
  * @return void
  */
  public function generateCache( ){
    global $config;

    if( !is_file( $config['dir_database'].'cache/links_ids' ) || !is_file( $config['dir_database'].'cache/links' ) )
      $this->generateLinks( );

    if( !isset( $config['pages_links'] ) )
      $config['pages_links'] = unserialize( file_get_contents( $config['dir_database'].'cache/links' ) );

    $aLinksIds = unserialize( file_get_contents( $config['dir_database'].'cache/links_ids' ) );
    $oSql = Sql::getInstance( );
    $oQuery = $oSql->getQuery( 'SELECT * FROM pages WHERE sLang = "'.$config['language'].'" ORDER BY sDesc DESC, iPosition ASC,  sName COLLATE NOCASE ASC' );
    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      if( isset( $aData['sDescriptionShort'] ) ){
        $aData['sDescriptionShort'] = stripslashes( $aData['sDescriptionShort'] );
      }

      $this->aPages[$aData['iPage']] = $aData;

      $this->aPages[$aData['iPage']]['sLinkName'] = $aLinksIds[$aData['iPage']];
      if( $config['start_page'] == $aData['iPage'] && $config['language'] == $config['default_language'] ){
        $this->aPages[$aData['iPage']]['sLinkName'] = './';
      }

      if( $aData['iPageParent'] > 0 ){
        $this->aPagesChildrens[$aData['iPageParent']][] = $aData['iPage'];
        $this->aPagesParents[$aData['iPage']] = $aData['iPageParent'];
      }
      else{
        if( isset( $aData['iMenu'] ) )
          $this->aPagesParentsMenus[$aData['iMenu']][] = $aData['iPage'];
      }
    } // end while

    $this->generateAllChildrens( );

  } // end function generateCache

  /**
  * Generates links
  * @return void
  */
  public function generateLinks( ){
    global $config;

    $oSql = Sql::getInstance( );
    $oQuery = $oSql->getQuery( 'SELECT sUrl, sName, sLang, iPage, sRedirect FROM pages ORDER BY sDesc DESC, iPosition ASC, iPage ASC' );

    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $aData['iPage'] = (int) $aData['iPage'];
      if( !empty( $aData['sRedirect'] ) ){
        $aLinksIds[$aData['iPage']] = $aData['sRedirect'];
      }

      if( !isset( $aLinksIds[$aData['iPage']] ) ){
        $sUrl1 = '?'.( isset( $config['language_separator'] ) ? $aData['sLang'].$config['language_separator'] : null ).change2Url( !empty( $aData['sUrl'] ) ? $aData['sUrl'] : $aData['sName'] );
        $sUrl2 = ','.$aData['iPage'];

        if( isset( $aLinks[$sUrl1] ) ){
          $aLinksIds[$aData['iPage']] = $sUrl1.$sUrl2;
          $aLinks[$sUrl1.$sUrl2] = Array( $aData['iPage'], $aData['sLang'] );
        }
        else{
          $aLinksIds[$aData['iPage']] = $sUrl1;
          $aLinks[$sUrl1] = Array( $aData['iPage'], $aData['sLang'] );
        }
      }
    } // end while

    if( isset( $aLinks ) ){
      file_put_contents( $config['dir_database'].'cache/links', serialize( $aLinks ) );
      file_put_contents( $config['dir_database'].'cache/links_ids', serialize( $aLinksIds ) );
    }
  } // end function generateLinks

  /**
  * Returns page data
  * @return array
  * @param int  $iPage
  */
  public function throwPage( $iPage ){
    if( isset( $this->aPages[$iPage] ) ){
      $oSql = Sql::getInstance( );
      $aData = array_merge( $this->aPages[$iPage], $oSql->throwAll( 'SELECT sDescriptionFull FROM pages WHERE iPage = '.$iPage ) );
      if( !empty( $aData['sDescriptionFull'] ) ){
        $aData['sDescriptionFull'] = stripslashes( $aData['sDescriptionFull'] );
      }

      $aData['iTheme'] = $this->getParentTheme( $iPage );
      return $aData;
    }
    else
      return null;
  } // end function throwPage

  /**
  * Returns a list of pages
  * @return string
  * @param mixed $mData
  * @param array $aParametersExt
  * Default options: sClassName, bNoLinks, iType
  */
public function listPages( $mData, $aParametersExt = null ){
    global $config, $lang;

    if( is_array( $mData ) ){
      $aPages = $mData;
    }
    else{
      if( isset( $this->aPagesChildrens[$mData] ) )
        $aPages = $this->aPagesChildrens[$mData];
    }

    if( isset( $aPages ) ){
        $iCount = count( $aPages );
        $content = null;
        $random = FALSE;
        $limit = FALSE;
        $pagination = TRUE;
        $category_url = "";
        $class = "";
        $type = $aParametersExt['type'];
        $i = 0;
        $item = 0;
        $pagintionUrl = $this->aPages[$mData]['sLinkName'];
        $aParametersExt['category_url'] = $this->aPages[$mData]['sLinkName'];
        $pagination_content = "";
        $pagination_pages = 0;
        
        
        if($type == 1){
            $class = "productsList";
        }else{
            $class = "pagesList";
        }
        
  
        
        if( isset($aParametersExt['random']) && $aParametersExt['random'] == TRUE)
            $random = TRUE;
        
        if( isset($aParametersExt['limit']) && $aParametersExt['limit'] == TRUE){
            $limit = TRUE;
            $per_page = $aParametersExt['limit'];
            $pagination_pages = ceil($iCount / $per_page);
            
            if($per_page >= $iCount)
                $pagination = FALSE;
        }
            
        
        if( isset($aParametersExt['pagination']) && $aParametersExt['pagination'] == FALSE)
            $pagination = FALSE;
        
        if($limit){
            
            if(isset($_GET['page'])  ){
                $item = ($_GET['page']-1) * $per_page ;
            }
            
			while($i < $per_page){
                if($iCount > $item){
                    $content .= listPagesView( $this->aPages[$aPages[$item]], $aParametersExt );
                }
                $item++;
				$i++;
			  } // end foreach
            
            if($pagination){
                $j = 1;
                $pagination_content .= '<ul class="pagination">';
                while($j <= $pagination_pages){
                    $pagination_content .= '<li id="'.$roznica.'" class="page-item'.(isset($_GET['page']) && $_GET['page'] == $j ? ' active' : null).(!isset($_GET['page']) && $j == 1 ? ' active' : null).'"><a class="page-link" href="'.$pagintionUrl.'&page='.$j.'">'.$j.'</a></li>';
                    $j++;
                }
                $pagination_content .= '</ul>';
            }
            
		} else {
            
            foreach( $aPages as $iPage ){
                $aParametersExt['iElement'] = $i++;
                
                if($random)
                    $aParametersExt['order'] = rand(1, $iCount);
                
                $content .= listPagesView( $this->aPages[$iPage], $aParametersExt );
            } 
        }
            

      if( isset( $content ) ){
        return '<ul class="'.$class.( isset( $aParametersExt['class'] ) ? ' '.$aParametersExt['class'] : '' ).'">'.$content.'</ul>'.$pagination_content;
      }
    }  
  }  // end function listPages

  /**
  * Generates and displays a menu
  * @return string
  * @param int $iMenu
  * @param array $aParametersExt
  * Default options: sClassName, iDepthLimit, bExpanded, bDisplayTitles
  */
  public function listPagesMenu( $iMenu, $aParametersExt = null ){
    global $lang, $config;

    if( !isset( $this->aPagesParentsMenus[$iMenu] ) )
      return null;

    $this->aMenuParams['iDepthLimit'] = isset( $aParametersExt['iDepthLimit'] ) ? $aParametersExt['iDepthLimit'] : 1;
    $this->aMenuParams['bExpanded'] = isset( $aParametersExt['bExpanded'] ) ? true : null;

    $aParametersExt['iDepth'] = 0;
    $content = null;
    foreach( $this->aPagesParentsMenus[$iMenu] as $iElement => $iPage ){
      $aParametersExt['sSubMenu'] = ( isset( $this->aPagesChildrens[$iPage] ) && ( isset( $this->aMenuParams['bExpanded'] ) || ( isset( $config['current_page_id'] ) && ( $iPage == $config['current_page_id'] || isset( $this->aPagesAllChildrens[$iPage][$config['current_page_id']] ) ) ) ) && $this->aMenuParams['iDepthLimit'] > 0 ) ? $this->listPagesSubMenu( $iPage, 1 ) : null;
      $aParametersExt['bSelected'] = ( isset( $config['current_page_id'] ) && $config['current_page_id'] == $iPage ) ? true : null;
      $aParametersExt['iElement'] = $iElement;

      $content .= listPagesMenuView( $this->aPages[$iPage], $aParametersExt );
    } // end foreach
    unset( $this->aMenuParams );

    if( isset( $content ) ){
      return
        ( isset( $aParametersExt['bHamburger'] ) ? displayHamburger( ) : null )
        .'<nav id="menu-'.$iMenu.'" class="menu-'.$iMenu.( isset( $aParametersExt['sClassName'] ) ? ' '.$aParametersExt['sClassName'] : null ).'" aria-label="menu-'.$iMenu.'">'
          .'<ul class="'.$aParametersExt['sClassName'].'__list" >'.$content.'</ul>'
        .'</nav>';
    }
  } // end function listPagesMenu
    
    

  /**
  * Displays a sub menu
  * @return string
  * @param int $iPageParent
  * @param int $iDepth
  */
  public function listPagesSubMenu( $iPageParent, $iDepth = 1 ){
    global $config;

    if( isset( $this->aPagesChildrens[$iPageParent] ) ){

      $aParametersExt['iDepth'] = $iDepth;
      $content = null;
      foreach( $this->aPagesChildrens[$iPageParent] as $iElement => $iPage ){
        $aParametersExt['sSubMenu'] = ( isset( $this->aPagesChildrens[$iPage] ) && ( ( isset( $this->aMenuParams['bExpanded'] ) || ( isset( $config['current_page_id'] ) && ( $iPage == $config['current_page_id'] || isset( $this->aPagesAllChildrens[$iPage][$config['current_page_id']] ) ) ) ) && $this->aMenuParams['iDepthLimit'] > $iDepth ) ? $this->listPagesSubMenu( $iPage, $iDepth + 1 ) : null );
        $aParametersExt['bSelected'] = ( isset( $config['current_page_id'] ) && $config['current_page_id'] == $iPage ) ? true : null;
        $aParametersExt['iElement'] = $iElement;
        $content .= listPagesMenuView( $this->aPages[$iPage], $aParametersExt );
      } // end foreach

      if( isset( $content ) ){
        return '<ul class="submenu level-'.$iDepth.'-menu" id="submenu_'.$iPageParent.'">'.$content.'</ul>';
      }
    }
  } // end function listPagesSubMenu

    
    
public function listMenu( $mData, $aParametersExt = null ){
    global $config, $lang;

    if( is_array( $mData ) ){
      $aPages = $mData;
    }
    else{
      if( isset( $this->aPagesChildrens[$mData] ) )
        $aPages = $this->aPagesChildrens[$mData];
    }

    if( isset( $aPages ) ){
      $iCount = count( $aPages );
      $content = null;
      $i = 1;
      foreach( $aPages as $iPage ){
          
        $aParametersExt['selected'] = ( isset( $config['current_page_id'] ) && $config['current_page_id'] == $iPage ) ? TRUE : FALSE;
          
        $aParametersExt['iElement'] = $i++;
        $content .= listMenuView( $this->aPages[$iPage], $aParametersExt );
      } // end foreach

      if( isset( $content ) ){
        return '<div class="widget widget-menu">'.( isset( $aParametersExt['title'] ) ? '<h4 class="widget__title">'.$aParametersExt['title'].( isset( $aParametersExt['toggle'] ) ? '<span class="widget__arrow"></span>' : null ).'</h4>' : null ).'<nav class="widget__menu">'.$content.'</nav></div>';
      }
    }  
  }    
    
    
public function listFaq( $mData, $aParametersExt = null ){
    global $config, $lang;

    if( is_array( $mData ) ){
      $aPages = $mData;
    }
    else{
      if( isset( $this->aPagesChildrens[$mData] ) )
        $aPages = $this->aPagesChildrens[$mData];
    }

    if( isset( $aPages ) ){
      $iCount = count( $aPages );
      $content = null;
      $shema_main = "";
      $shema = "";
      $i = 1;
      foreach( $aPages as $iPage ){
          
        $aParametersExt['selected'] = ( isset( $config['current_page_id'] ) && $config['current_page_id'] == $iPage ) ? TRUE : FALSE;
        $aParametersExt['iElement'] = $i++;
        $class = " faqItem-".$iPage;
        $aData = $this->aPages[$iPage];
         
          
          if($i == 2){
              $shema_main .= '"@type": "Question",
                "name": "'.$aData['sName'].'",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "'.$aData['sDescriptionFull'].'"
                }';
          }else{
              $shema .= ',{
                "@type": "Question",
                "name": "'.$aData['sName'].'",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "'.$aData['sDescriptionFull'].'"
                }
              }';
          }
        
          
          
          $content .= '<li class="faqItem'.$class.'">
  <h3 class="title"><a href="#" class="faqItemCollapse" data-id="'.$aData['iPage'].'" >'.$aData['sName'].'<span class="icon invertImg"></span></a></h3>
    <div class="content">'.$aData['sDescriptionFull'].'</div>
    </li>';
          
          
      } // end foreach

      if( isset( $content ) ){
        return '<script type="application/ld+json">
            {
              "@context": "https://schema.org",
              "@type": "FAQPage",
              "mainEntity": [{
                '.$shema_main.'
              }'.$shema.']
            }
        </script>
        <ul class="faqList">'.$content.'</ul>';
      }
    }  
  }
    
    
    
  /**
  * Returns all main pages childrens
  * @return null
  */
  protected function generateAllChildrens( $iPageParentMain = null, $iPageParent = null ){
    if( isset( $this->aPagesChildrens ) ){
      if( isset( $iPageParent ) ){
        foreach( $this->aPagesChildrens[$iPageParent] as $iSubPage ){
          $this->aPagesAllChildrens[$iPageParentMain][$iSubPage] = true;
          $this->aPagesAllChildrens[$iPageParent][$iSubPage] = true;
          if( isset( $this->aPagesChildrens[$iSubPage] ) ){
            $this->generateAllChildrens( $iPageParentMain, $iSubPage );
          }
        } // end foreach      
      }
      else{
        foreach( $this->aPagesChildrens as $iPageParent => $aChildrens ){
          if( !isset( $this->aPagesParents[$iPageParent] ) && isset( $this->aPages[$iPageParent] ) && $this->aPages[$iPageParent]['iMenu'] != 0 ){
            foreach( $aChildrens as $iSubPage ){
              $this->aPagesAllChildrens[$iPageParent][$iSubPage] = true;
              if( isset( $this->aPagesChildrens[$iSubPage] ) ){
                $this->generateAllChildrens( $iPageParent, $iSubPage );
              }
            } // end foreach
          }
        } // end foreach
      }
    }
  } // end function generateAllChildrens

  /**
  * Returns a page tree
  * @return string
  * @param int  $iPage
  * @param int  $iPageCurrent
  */
  public function getPagesTree( $iPage, $iPageCurrent = null ){
    if( !isset( $iPageCurrent ) ){
      $iPageCurrent = $iPage;
      $this->mData = null;
    }

    if( isset( $this->aPagesParents[$iPage] ) && isset( $this->aPages[$this->aPagesParents[$iPage]] ) ){
      $this->mData[] = '<li><a href="'.$this->aPages[$this->aPagesParents[$iPage]]['sLinkName'].'">'.$this->aPages[$this->aPagesParents[$iPage]]['sName'].'</a></li>';
      return $this->getPagesTree( $this->aPagesParents[$iPage], $iPageCurrent );
    }
    else{
      if( isset( $this->mData ) ){
        array_unshift( $this->mData, isset( $GLOBALS['config']['page_link_in_navigation_path'] ) ? '<li><a href="'.$this->aPages[$iPageCurrent]['sLinkName'].'" aria-current="page">'.$this->aPages[$iPageCurrent]['sName'].'</a></li>' : '<li><span>'.$this->aPages[$iPageCurrent]['sName'].'</span></li>' );
        $aReturn = array_reverse( $this->mData );
        $this->mData = null;
        return implode( '', $aReturn );
      }
    }
  } // end function getPagesTree


  /**
  * Returns class names to the BODY element
  * @return string
  * @param int $iPage
  */
  public function getClassName( $iPage ){
    global $config;

    if( !empty( $this->aPages[$iPage]['iPageParent'] ) )
      $aClasses[] = 'is-parent-page-'.$this->aPages[$iPage]['iPageParent'];

    if( !empty( $config['start_page'] ) && $config['start_page'] == $iPage ){
      $aClasses[] = 'is-page-home';
    }

    if( isset( $this->aPagesChildrens[$iPage] ) )
      $aClasses[] = 'is-subpages-list';

    if( !empty( $GLOBALS['aData']['sDescriptionFull'] ) )
      $aClasses[] = 'is-page-description';

    if( isset( $aClasses ) )
      return ' class="'.implode( ' ', $aClasses ).'"';
  } // end function getClassName

  /**
  * Returns a parent theme set
  * @return string
  * @param int $iPage
  */
  public function getParentTheme( $iPage ){
    if( $this->aPages[$iPage]['iTheme'] > 0 )
      return $this->aPages[$iPage]['iTheme'];
    elseif( $this->aPages[$iPage]['iTheme'] == 0 && isset( $this->aPages[$this->aPages[$iPage]['iPageParent']] ) )
      return $this->getParentTheme( $this->aPages[$iPage]['iPageParent'] );
  } // end function getParentTheme


};
?>