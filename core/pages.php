<?php
if( !defined( 'CUSTOMER_PAGE' ) && !defined( 'ADMIN_PAGE' ) )
  exit( 'Quick.Cms by OpenSolution.org' );

 
class Pages
{
    
  public ?array $mData = null;
  public ?array $aMenuParams = null;
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
   * Sprawdza czy dana strona (rekord z pages) spełnia aktywne filtry z $_GET
   * Obsługuje wielokrotny wybór filtrów - strona pasuje jeśli ma JAKĄKOLWIEK
   * z wybranych wartości dla danego filtra.
   *
   * @param array $page
   * @param array $activeFilters  np. ['color' => '2', 'size' => '3'] lub ['color' => ['1','2']]
   * @return bool
   */
  private function pageMatchesFilters(array $page, array $activeFilters): bool
  {
      if (empty($activeFilters)) {
          return true;
      }

      $pageFilters = [];
      if (!empty($page['sFilter'])) {
          // gdyby w bazie były encje html
          $raw = html_entity_decode($page['sFilter'], ENT_QUOTES, 'UTF-8');
          $decoded = json_decode($raw, true);
          if (is_array($decoded)) {
              $pageFilters = $decoded;
          }
      }

      foreach ($activeFilters as $key => $val) {
          if ($val === '' || $val === null) {
              continue;
          }
          if (!isset($pageFilters[$key])) {
              return false;
          }

          // Normalizuj wartości strony do tablicy
          $pageValues = is_array($pageFilters[$key])
              ? array_map('strval', $pageFilters[$key])
              : [(string)$pageFilters[$key]];

          // Normalizuj wartości filtra z GET do tablicy
          $filterValues = is_array($val)
              ? array_map('strval', $val)
              : [(string)$val];

          // Sprawdź czy jest jakiekolwiek przecięcie (strona ma jedną z wybranych wartości)
          $intersection = array_intersect($pageValues, $filterValues);
          if (empty($intersection)) {
              return false;
          }
      }

      return true;
  }


  /**
  * Generates cache variables
  * @return void
  */
  public function generateCache( ){
    global $config;

    $sLinksPath = $config['dir_database'].'cache/links';
    $sLinksIdsPath = $config['dir_database'].'cache/links_ids';

    if( !is_file( $sLinksIdsPath ) || !is_file( $sLinksPath ) )
      $this->generateLinks( );

    // OPTYMALIZACJA: Użyj JSON zamiast serialize (szybsze, bardziej przenośne)
    if( !isset( $config['pages_links'] ) ){
      $linksContent = file_get_contents( $sLinksPath );
      $config['pages_links'] = json_decode( $linksContent, true );
      // Kompatybilność wsteczna - jeśli JSON nie działa, spróbuj unserialize
      if( $config['pages_links'] === null ){
        $config['pages_links'] = unserialize( $linksContent );
      }
    }

    $linksIdsContent = file_get_contents( $sLinksIdsPath );
    $aLinksIds = json_decode( $linksIdsContent, true );
    // Kompatybilność wsteczna
    if( $aLinksIds === null ){
      $aLinksIds = unserialize( $linksIdsContent );
    }

    $bRegenerate = false;
    if( !empty( $config['pages_links'] ) ){
      foreach( $config['pages_links'] as $sKey => $aValue ){
        if( strpos( $sKey, '?' ) === 0 ){
          $bRegenerate = true;
        }
        break;
      }
    }
    if( !$bRegenerate && !empty( $aLinksIds ) ){
      foreach( $aLinksIds as $sValue ){
        if( is_string( $sValue ) && strpos( $sValue, '?' ) === 0 ){
          $bRegenerate = true;
        }
        break;
      }
    }
    if( $bRegenerate ){
      $this->generateLinks( );
      // OPTYMALIZACJA: Użyj JSON zamiast serialize
      $linksContent = file_get_contents( $sLinksPath );
      $config['pages_links'] = json_decode( $linksContent, true );
      if( $config['pages_links'] === null ){
        $config['pages_links'] = unserialize( $linksContent );
      }

      $linksIdsContent = file_get_contents( $sLinksIdsPath );
      $aLinksIds = json_decode( $linksIdsContent, true );
      if( $aLinksIds === null ){
        $aLinksIds = unserialize( $linksIdsContent );
      }
    }
    $oSql = Sql::getInstance( );
    $oQuery = $oSql->getQuery( 'SELECT * FROM pages WHERE iStatus > 0   AND sLang = "'.$config['language'].'" ORDER BY iPosition ASC, sName COLLATE NOCASE ASC' );
    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      if( isset( $aData['sDescriptionShort'] ) ){
        $aData['sDescriptionShort'] = stripslashes( $aData['sDescriptionShort'] );
      }

      $this->aPages[$aData['iPage']] = $aData;

      $this->aPages[$aData['iPage']]['sLinkName'] = isset( $aLinksIds[$aData['iPage']] ) ? $aLinksIds[$aData['iPage']] : null;
      if( $config['start_page'] == $aData['iPage'] && $config['language'] == $config['default_language'] ){
        $sHomepageLink = $config['base_path_with_slash'];
        if( empty( $config['seo_trailing_slash'] ) ){
          $sHomepageLink = rtrim( $sHomepageLink, '/' );
          if( $sHomepageLink == '' )
            $sHomepageLink = '/';
        }
        $this->aPages[$aData['iPage']]['sLinkName'] = $sHomepageLink;
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
    $oQuery = $oSql->getQuery( 'SELECT sUrl, sName, sLang, iPage, sRedirect FROM pages ORDER BY iPosition ASC, iPage ASC' );

    $sBasePath = isset( $config['base_path'] ) ? $config['base_path'] : '';
    if( $sBasePath == '/' )
      $sBasePath = '';
    $sBasePath = rtrim( $sBasePath, '/' );
    $bTrailingSlash = !empty( $config['seo_trailing_slash'] );

    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $aData['iPage'] = (int) $aData['iPage'];
      if( !empty( $aData['sRedirect'] ) ){
        $aLinksIds[$aData['iPage']] = $aData['sRedirect'];
      }

      $sSlugSource = !empty( $aData['sUrl'] ) ? $aData['sUrl'] : $aData['sName'];
      $sSlug = change2Url( $sSlugSource );
      if( isset( $config['language_separator'] ) && $config['language_separator'] !== null ){
        $sSlug = $aData['sLang'].$config['language_separator'].$sSlug;
      }
      $sSlug = trim( $sSlug, '/' );

      $sPath = $sBasePath;
      if( $sSlug !== '' )
        $sPath .= ( $sPath !== '' ? '/' : '' ).$sSlug;

      if( $sPath === '' )
        $sPath = '/';
      else
        $sPath = '/'.ltrim( $sPath, '/' );

      if( $sPath !== '/' ){
        $sPath = $bTrailingSlash ? rtrim( $sPath, '/' ).'/' : rtrim( $sPath, '/' );
      }

      $sStoredPath = $sPath;
      if( isset( $aLinks[$sStoredPath] ) ){
        $sStoredPath = rtrim( $sPath, '/' ).','.$aData['iPage'];
        if( $bTrailingSlash && $sStoredPath !== '/' )
          $sStoredPath .= '/';
      }

      $aLinks[$sStoredPath] = Array( $aData['iPage'], $aData['sLang'] );

      if( $sStoredPath !== '/' ){
        $sAlternate = $bTrailingSlash ? rtrim( $sStoredPath, '/' ) : $sStoredPath.'/';
        if( $sAlternate !== '' && !isset( $aLinks[$sAlternate] ) )
          $aLinks[$sAlternate] = Array( $aData['iPage'], $aData['sLang'] );
      }

      if( !isset( $aLinksIds[$aData['iPage']] ) )
        $aLinksIds[$aData['iPage']] = $sStoredPath;

      if( $config['start_page'] == $aData['iPage'] && $aData['sLang'] == $config['default_language'] ){
        $sHomepageLink = $config['base_path_with_slash'];
        if( empty( $config['seo_trailing_slash'] ) ){
          $sHomepageLink = rtrim( $sHomepageLink, '/' );
          if( $sHomepageLink == '' )
            $sHomepageLink = '/';
        }
        $aLinks[$sHomepageLink] = Array( $aData['iPage'], $aData['sLang'] );
        if( $sHomepageLink !== '/' ){
          $sHomeAlternate = !empty( $config['seo_trailing_slash'] ) ? rtrim( $sHomepageLink, '/' ) : $sHomepageLink.'/';
          if( $sHomeAlternate !== '' && !isset( $aLinks[$sHomeAlternate] ) )
            $aLinks[$sHomeAlternate] = Array( $aData['iPage'], $aData['sLang'] );
        }
        $aLinksIds[$aData['iPage']] = $sHomepageLink;
      }
    } // end while

    if( isset( $aLinks ) ){
      // OPTYMALIZACJA: Użyj JSON zamiast serialize (szybsze, bardziej przenośne)
      file_put_contents( $config['dir_database'].'cache/links', json_encode( $aLinks ) );
      file_put_contents( $config['dir_database'].'cache/links_ids', json_encode( $aLinksIds ) );
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
  public function listPages($mData, $aParametersExt = null)
  {
      global $config, $lang;

      // 1. Ustal listę stron wejściowych
      if (is_array($mData)) {
          $aPages = $mData;
      } else {
          if (isset($this->aPagesChildrens[$mData])) {
              $aPages = $this->aPagesChildrens[$mData];
          }
      }

      if (!isset($aPages)) {
          return;
      }

      // 2. Bazowe zmienne
      // OPTYMALIZACJA: Użyj tablicy zamiast konkatenacji stringów
      $content            = [];
      $class              = 'pagesList pagesList-' . $mData;
      $random             = false;
      $pagination         = true;
      $pagination_content = [];
      $per_page           = 0;

      // 3. Klasa wg typu strony (sklep / blog / oferta / projekty)
      if ($mData == $config['projects_page']) {
          $class .= ' projectsList';
          $aParametersExt['hide_cat'] = true;
      }
      if ($mData == $config['offer_page']) {
          $class .= ' offerList';
      }
      if (getData($mData, 'iMenu') == 2) {
          // sklep
          $class .= ' productsList';
      }
      if ($mData == $config['blog_page']) {
          $class .= ' blogList';
      }

      // 4. Ustawienia dodatkowe z $aParametersExt
      if (isset($aParametersExt['random']) && $aParametersExt['random'] == true) {
          $random = true;
      }

      // 5. Odczytaj aktywne filtry z URL na podstawie konfiguracji
      // Obsługa wielokrotnego wyboru (tablica wartości)
      $activeFilters = [];
      if (function_exists('getFiltersConfig')) {
          $cfgFilters = getFiltersConfig();
      } else {
          $cfgFilters = [];
      }

      foreach ($cfgFilters as $fid => $fdata) {
          if (!empty($fdata['key'])) {
              $key = $fdata['key']; // np. color
              if (isset($_GET[$key]) && $_GET[$key] !== '') {
                  // Obsługa tablicy wartości (wielokrotny wybór)
                  if (is_array($_GET[$key])) {
                      $values = array_filter($_GET[$key], function($v) {
                          return $v !== '' && $v !== null;
                      });
                      if (!empty($values)) {
                          $activeFilters[$key] = $values;
                      }
                  } else {
                      $activeFilters[$key] = $_GET[$key];
                  }
              }
          }
      }

      // 6. PRZEFILTRUJ listę stron wg filtrów – to jest kluczowa różnica
      $filteredPages = [];
      foreach ($aPages as $pid) {
          $pageData = $this->aPages[$pid];
          if (!empty($activeFilters) && !$this->pageMatchesFilters($pageData, $activeFilters)) {
              continue;
          }
          $filteredPages[] = $pid;
      }

      // teraz liczymy już po przefiltrowanej liście
      $iCount = count($filteredPages);

      // 7. Paginacja / limit
      $useLimit = false;
      if (isset($aParametersExt['per_page']) && $aParametersExt['per_page']) {
          $useLimit = true;
          $per_page = (int)$aParametersExt['per_page'];
      }

      if (isset($aParametersExt['pagination']) && $aParametersExt['pagination'] == false) {
          $pagination = false;
      }

      // 8. Adres bazowy do paginacji
      // np. /sklep/ albo /sklep?color=2
      $baseUrl = $this->aPages[$mData]['sLinkName'];
      // do wyświetlenia gdzieś niżej
      $aParametersExt['category_url'] = $baseUrl;

      // Zbuduj aktualne parametry GET (żeby paginacja nie kasowała filtrów)
      $currentQuery = $_GET;
      unset($currentQuery['page']); // page dopiszemy osobno

      // 9. Render z limitem (paginacja)
      if ($useLimit && $per_page > 0) {
          // ile stron po odfiltrowaniu
          $totalPages = (int)ceil($iCount / $per_page);
          if ($totalPages <= 1) {
              $pagination = false;
          }

          // aktualna strona
          $currentPage = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
          if ($currentPage > $totalPages) {
              $currentPage = $totalPages;
          }

          $startIndex = ($currentPage - 1) * $per_page;
          $endIndex   = $startIndex + $per_page;

          $i = 0;
          for ($idx = $startIndex; $idx < $endIndex; $idx++) {
              if (!isset($filteredPages[$idx])) {
                  break;
              }
              $pageId   = $filteredPages[$idx];
              $pageData = $this->aPages[$pageId];

              $aParametersExt['iElement'] = $i++;

              if ($random) {
                  $aParametersExt['order'] = rand(1, $iCount);
              }

              // OPTYMALIZACJA: Dodaj do tablicy zamiast konkatenacji
              $content[] = listPagesView($pageData, $aParametersExt);
          }

          // 10. Paginacja – tylko jeśli ma sens
            if ($pagination && $totalPages > 1) {
                $pagination_content[] = '<ul class="pagination">';

                // Zbuduj listę elementów do wyświetlenia
                $pagesToShow = [];

                if ($totalPages <= 7) {
                    // Pełna paginacja
                    for ($p = 1; $p <= $totalPages; $p++) {
                        $pagesToShow[] = $p;
                    }
                } else {
                    // Skrócona paginacja z wielokropkami (zakres ±2 od aktywnej)
                    $range = 2;
                    $start = max(2, $currentPage - $range);
                    $end   = min($totalPages - 1, $currentPage + $range);

                    // Pierwsza strona zawsze
                    $pagesToShow[] = 1;

                    // Wielokropek lub pojedyncza strona 2
                    if ($start > 3) {
                        $pagesToShow[] = '...';
                    } elseif ($start == 3) {
                        $pagesToShow[] = 2;
                    }

                    // Strony środkowe
                    for ($p = $start; $p <= $end; $p++) {
                        $pagesToShow[] = $p;
                    }

                    // Wielokropek lub pojedyncza strona przedostatnia
                    if ($end < $totalPages - 2) {
                        $pagesToShow[] = '...';
                    } elseif ($end == $totalPages - 2) {
                        $pagesToShow[] = $totalPages - 1;
                    }

                    // Ostatnia strona zawsze
                    $pagesToShow[] = $totalPages;
                }

                foreach ($pagesToShow as $p) {
                    if ($p === '...') {
                        $pagination_content[] = '<li class="page-item page-item-ellipsis"><span class="page-link">&hellip;</span></li>';
                    } else {
                        $q = $currentQuery;
                        $q['page'] = $p;
                        $qs = http_build_query($q);
                        $url = $baseUrl . (strpos($baseUrl, '?') === false ? '?' . $qs : '&' . $qs);

                        $pagination_content[] = '<li class="page-item' . ($p == $currentPage ? ' active' : '') . '">';
                        $pagination_content[] = '<a class="page-link" href="' . $url . '">' . $p . '</a>';
                        $pagination_content[] = '</li>';
                    }
                }

                $pagination_content[] = '</ul>';
            }

      } else {
          // 11. Wersja bez limitu – po prostu leć po przefiltrowanych
          $i = 0;
          foreach ($filteredPages as $pageId) {
              $pageData = $this->aPages[$pageId];

              $aParametersExt['iElement'] = $i++;

              if ($random) {
                  $aParametersExt['order'] = rand(1, $iCount);
              }

              // OPTYMALIZACJA: Dodaj do tablicy zamiast konkatenacji
              $content[] = listPagesView($pageData, $aParametersExt);
          }
      }

      // 12. Zwróć HTML
      if (!empty($content)) {
          // OPTYMALIZACJA: Złącz tablicę na końcu
          return '<ul class="' . $class . (isset($aParametersExt['class']) ? ' ' . $aParametersExt['class'] : '') . '">' . implode('', $content) . '</ul>' . implode('', $pagination_content);
      }
  }

    
    


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

    $this->aMenuParams = [];
    $this->aMenuParams['iDepthLimit'] = isset($aParametersExt['iDepthLimit']) ? (int)$aParametersExt['iDepthLimit'] : 1;
    $this->aMenuParams['bExpanded']   = isset($aParametersExt['bExpanded']) ? true : null;


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
  } 
    
    

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
        return '<div class="submenu" id="submenu_'.$iPageParent.'"><ul class="submenu_list level-'.$iDepth.'-menu" >'.$content.'</ul></div>';
//        return '<div class="submenu" id="submenu_'.$iPageParent.'"><div class="container"><ul class="submenu_list level-'.$iDepth.'-menu" >'.$content.'</ul></div></div>';
      }
    }
  } 

    
function listPagesPopup(int $parentId = 32, array $options = []): string
{
    $oPage = Pages::getInstance();
    $oFile = Files::getInstance();
    $cookieDays =  7;


    $children = $oPage->aPagesChildrens[$parentId] ?? [];
    if (empty($children)) {
        return '';
    }

    $html = '';

    foreach ($children as $pageId) {
        $pageId = (int)$pageId;

        $p = $oPage->aPages[$pageId] ?? null;
        if (!$p || (int)($p['iStatus'] ?? 0) <= 0) {
            continue;
        }
        
        $cookieDays = !empty($p['iPosition']) && $p['iPosition'] != 0 ? $p['iPosition'] : 7;

        $title = html($p['sName'] ?? 'Popup');

        $lead = !empty($p['sDescriptionShort']) ? stripslashes($p['sDescriptionShort']) : '';
        $more = !empty($p['sDescriptionFull']) ? TRUE : FALSE;

        // default image strony (bez linka)
        $image = $oFile->getDefaultImage($pageId, [
            'sLink'   => ($more ? '<a href="'.$this->aPages[$p['iPage']]['sLinkName'].'" class="popupWidget__close">' : ""),
            'sClassName' => 'popupWidget__image',
            'full_size'  => true,
        ]);

        $html .= '
<div id="popup-widget-' . $pageId . '" class="modal popup popupWidget js-popup-widget" style="display:none" data-popup-id="' . $pageId . '" data-cookie-days="' . $cookieDays . '">
    <div class="card">
        <header class="card__header">
            <h5 class="card__title">
                ' . $title . '
            </h5>
        </header>
        <div class="card__wrapper">
            ' . ($image ?: '') . '
            ' . ($lead !== '' ? '<div class="card__content">' . $lead . '</div>' : '') . '
                
            <footer class="card__footer">
                ' . ($more ? '<a href="'.$this->aPages[$p['iPage']]['sLinkName'].'" class="button mr-auto popupWidget__close" >Więcej</a>' : '') . '
                <button type="button" class="button button-light" data-popup-close>
                    Zamknij
                </button>
            </footer>
        
        </div>
    </div>
</div>';
    }

    return $html;
}

     
//public function listMenu( $mData, $aParametersExt = null ){
//    global $config, $lang;
//
//    if( is_array( $mData ) ){
//      $aPages = $mData;
//    }
//    else{
//      if( isset( $this->aPagesChildrens[$mData] ) )
//        $aPages = $this->aPagesChildrens[$mData];
//    }
//
//    if( isset( $aPages ) ){
//      $iCount = count( $aPages );
//      $content = null;
//      $i = 1;
//      foreach( $aPages as $iPage ){
//          
//        $aParametersExt['selected'] = ( isset( $config['current_page_id'] ) && $config['current_page_id'] == $iPage ) ? TRUE : FALSE;
//          
//        $aParametersExt['iElement'] = $i++;
//        $content .= listMenuView( $this->aPages[$iPage], $aParametersExt );
//      } // end foreach
//
//      if( isset( $content ) ){
//        return '<aside class="widget">'.( isset( $aParametersExt['title'] ) ? '<h4 class="widget__title">'.$aParametersExt['title'].( isset( $aParametersExt['toggle'] ) ? '<span class="widget__arrow"><img src="'.ICONS.'arrow.svg" alt="Strzałka"></span>' : null ).'</h4>' : null ).'<nav class="widget__menu">'.$content.'</nav></aside>';
//      }
//    }  
//  }    

public function listFaq($mData, $aParametersExt = null) {
    global $config, $lang;
    
    if (is_array($mData)) {
        $aPages = $mData;
    } else {
        if (isset($this->aPagesChildrens[$mData]))
            $aPages = $this->aPagesChildrens[$mData];
    }
    
    if (!isset($aPages) || empty($aPages)) {
        return '';
    }
    
    // =====================================================
    // OBSŁUGA PARAMETRÓW: rand + limit
    // =====================================================
    $aPages = array_values($aPages); // reset kluczy tablicy
    
    // Losowa kolejność
    if (!empty($aParametersExt['rand'])) {
        shuffle($aPages);
    }
    
    // Limit pozycji
    if (!empty($aParametersExt['limit']) && is_numeric($aParametersExt['limit'])) {
        $aPages = array_slice($aPages, 0, (int)$aParametersExt['limit']);
    }
    
    // =====================================================
    // BUDOWANIE HTML + SCHEMA
    // =====================================================
    $content = '';
    $schemaItems = [];
    $i = 0;
    
    foreach ($aPages as $iPage) {
        $aParametersExt['selected'] = (isset($config['current_page_id']) && $config['current_page_id'] == $iPage) ? TRUE : FALSE;
        $aParametersExt['iElement'] = $i++;
        
        $class = " faqItem-" . $iPage;
        $aData = $this->aPages[$iPage];
        
        // Czysta odpowiedź do schema (bez HTML, znormalizowane spacje)
        $schemaText = trim(preg_replace('/\s+/', ' ', strip_tags($aData['sDescriptionShort'] ?? '')));
        
        // Dodaj pozycję do schema jako tablicę
        $schemaItems[] = [
            '@type' => 'Question',
            'name'  => $aData['sName'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $schemaText
            ]
        ];
        
        // Buduj HTML akordeonu
        $content .= '<li class="accordionItem' . $class . '">
            <h3 class="accordionItem__title"><span class="label">' . $aData['sName'] . '</span><span class="arrow invert"><img src="' . ICONS . 'arrow.svg" alt="Arrow"></span></h3>
            <div class="accordionItem__content">' . $aData['sDescriptionShort'] . '</div>
        </li>';
    }
    
    if (empty($content)) {
        return '';
    }
    
    // =====================================================
    // POPRAWNY JSON-LD FAQPage SCHEMA
    // =====================================================
    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $schemaItems
    ];
    
    $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    
    return '<script type="application/ld+json">' . $schemaJson . '</script>
        <ul class="accordionList">' . $content . '</ul>';
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
    if (!isset($iPageCurrent)) {
        $iPageCurrent = $iPage;
        $this->mData = [];
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
      
    if($config['shop_page'] == $iPage ){
      $aClasses[] = 'pageShop';
    }
      
      
    $aClasses[] = 'pageType-'.$this->aPages[$iPage]['sType'];
      
      
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
      
    $aClasses[] = 'theme-'.$theme;  
      
      if( isset( $_COOKIE['fontSize'] ) && $_COOKIE['fontSize'] == 'large' ){
          $aClasses[] = 'theme-font';
      }
      
    if( $this->aPages[$iPage]['sPanel'] == 1 ){
          $aClasses[] = 'page-column';
      }
      
      
 
    
      
      
   
    $aClasses[] = 'theme-'.(getData($iPage, 'iTheme'));
  

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