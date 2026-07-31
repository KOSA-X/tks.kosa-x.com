<?php
final class PagesAdmin extends Pages
{
  private static $oInstance = null;
  private $aPageChildrens = null;
  private $sSelects = null;      // cache opcji select (różne warianty)
  public  $aLinksIds = null;

  public static function getInstance( ){
    if( !isset( self::$oInstance ) ){
      self::$oInstance = new PagesAdmin( );
    }
    return self::$oInstance;
  } // end function getInstance

  /**
  * Constructor
  * @return void
  */
  private function __construct( ){
    global $config;
    if( !is_file( $config['dir_database'].'cache/links_ids' ) || !is_file( $config['dir_database'].'cache/links' ) )
      $this->generateLinks( );
    // cache jest zapisywany jako JSON (patrz Pages::generateLinks);
    // unserialize zostaje jako fallback dla starych plików cache
    $sLinksIdsRaw = file_get_contents( $config['dir_database'].'cache/links_ids' );
    $this->aLinksIds = json_decode( $sLinksIdsRaw, true );
    if( $this->aLinksIds === null ){
      $this->aLinksIds = @unserialize( $sLinksIdsRaw );
    }
  } // end function __construct

  /**
  * Returns a list of pages in form of a HTML select
  * @return string
  * @param int $iPageSelected
  */
  public function listPagesSelectAdmin( $iPageSelected, $shop = 0 ){
  global $config;

  if( !is_array( $this->sSelects ) )
    $this->sSelects = [];

  if( isset( $this->sSelects[$shop] ) ){
    $content = $this->sSelects[$shop];
  }
  else{
    $oSql = Sql::getInstance( );

    // ✅ opcja "BRAK" na górze (value=0)
    $content = '<option value="0">-</option>';

    foreach( $config['pages_menus'] as $iMenu => $sMenu ){

      if( (int)$shop === 2 && (int)$iMenu !== 2 ) continue;
      if( (int)$shop !== 2 && (int)$iMenu === 2 ) continue;

      $oQuery = $oSql->getQuery(
        'SELECT iStatus, iPage, sName
         FROM pages
         WHERE iPageParent = 0
           AND sLang = "'.$config['language'].'"
           AND iMenu = "'.$iMenu.'"
         ORDER BY iPosition ASC, sName COLLATE NOCASE ASC'
      );

      $i = 0;
      while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
        if( $i == 0 )
          $content .= '<option value="0" disabled="disabled" class="text-strong bg-gray text-brand font-md pt-2 pb-2">'.$config['pages_menus'][$iMenu].'</option>';

        $classes = 'text-medium'.( (int)$aData['iStatus'] === 0 ? ' status' : '' );
        $nameEsc = htmlspecialchars( $aData['sName'], ENT_QUOTES, 'UTF-8' );

        $content .= '<option value="'.$aData['iPage'].'" class="'.$classes.'">'.$nameEsc.'</option>';
        $content .= $this->listSubPagesSelectAdmin( $iPageSelected, $aData['iPage'] );
        $i++;
      }
    }

    $this->sSelects[$shop] = $content;
  }

  if( isset( $content ) ){
    // ✅ zaznaczenie BRAK gdy 0 / puste
    if( !isset($iPageSelected) || (int)$iPageSelected === 0 )
      return str_replace( 'value="0"', 'value="0" selected="selected"', $content );

    return str_replace( 'value="'.$iPageSelected.'"', 'value="'.$iPageSelected.'" selected="selected"', $content );
  }
} // end function listPagesSelectAdmin

  /**
  * Returns a list of subpages in form of a HTML select
  * @return string
  * @param int $iPageSelected
  * @param int $iPageParent
  * @param int $iDepth
  */
  public function listSubPagesSelectAdmin( $iPageSelected, $iPageParent, $iDepth = 1 ){
    global $config;

    $oSql = Sql::getInstance( );
    $content = null;

    $iPageParent = (int)$iPageParent;
    $iDepth      = (int)$iDepth;

    // KRYTYCZNE: filtruj po języku (inaczej select miesza języki)
    $oQuery = $oSql->getQuery(
      'SELECT iStatus, iPage, sName
       FROM pages
       WHERE iPageParent = "'.$iPageParent.'"
         AND sLang = "'.$config['language'].'"
       ORDER BY iPosition ASC, sName COLLATE NOCASE ASC'
    );

    $sSeparator = str_repeat( '&#160; ', $iDepth );

    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $nameEsc = htmlspecialchars( $aData['sName'], ENT_QUOTES, 'UTF-8' );
      $content .= '<option'.( (int)$aData['iStatus'] === 0 ? ' class="status"' : null ).' value="'.$aData['iPage'].'">'.$sSeparator.$nameEsc.'</option>';
      $content .= $this->listSubPagesSelectAdmin( $iPageSelected, (int)$aData['iPage'], $iDepth + 1 );
    } // end while

    return $content;
  } // end function listSubPagesSelectAdmin


  /**
  * Returns the list of pages
  * @return string
  * @param array $aParametersExt
  * Default options: iDepth, iMenu, iPageParent
  */
  public function listPagesAdmin( $aParametersExt = null ){
    global $lang, $config;

    $content    = null;
    $sWhere     = null;
    $shop       = false;
    $pageparent = '';
    $oSql       = Sql::getInstance( );

    if( isset( $aParametersExt['shop'] ) && $aParametersExt['shop'] == true )
      $shop = true;

    if( !isset( $aParametersExt['iDepth'] ) )
      $aParametersExt['iDepth'] = 0;

    if( isset( $aParametersExt['iMenu'] ) && isset( $config['pages_menus'][$aParametersExt['iMenu']] ) )
      $sWhere = ' AND iMenu = "'.$aParametersExt['iMenu'].'" AND iPageParent = 0 ';
    elseif( isset( $aParametersExt['iPageParent'] ) && is_numeric( $aParametersExt['iPageParent'] ) )
      $sWhere = ' AND iPageParent = "'.$aParametersExt['iPageParent'].'" ';

    // proste: nazwa parenta tylko raz dla tej gałęzi (shop)
    if( $shop && isset( $aParametersExt['iPageParent'] ) && (int)$aParametersExt['iPageParent'] > 0 ){
      $pageparent = getData( (int)$aParametersExt['iPageParent'], "sName" );
    }

    $oQuery = $oSql->getQuery(
      'SELECT * FROM pages
       WHERE sLang = "'.$config['language'].'"
       '.$sWhere.'
       ORDER BY iPosition ASC, sDate DESC, iPage ASC, sName COLLATE NOCASE ASC'
        
    );

    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){

      // KRYTYCZNE: tu NIE może być return "" (bo ucina całą listę w połowie)
      if( !$shop && (int)$aData['iMenu'] === 2 && (int)$aData['iPageParent'] > 0 ){
        // ukrywam listę produktów na liście "Stron"
        continue;
      }

      $dataParent = '';
      $toggleBtn  = '';

      // rekursja: dzieci tej strony
      $childrenHtml = $this->listPagesAdmin( array(
        'iPageParent' => $aData['iPage'],
        'shop'        => $shop,
        'iDepth'      => ( $aParametersExt['iDepth'] + 1 )
      ) );
      $hasChildren  = !empty( $childrenHtml );

      if( $shop ){

        if( (int)$aData['iPageParent'] === 0 ){
          $rowClasses = 'd-none';
        } else {
          $rowClasses = 'page-row product-row';
        }

      } else {

        $rowClasses = 'page-row l'.$aParametersExt['iDepth'];

        if( isset( $aParametersExt['iPageParent'] ) && (int)$aParametersExt['iPageParent'] > 0 ){
          $rowClasses .= ' page-child page-parent-'.$aParametersExt['iPageParent'];
          $dataParent  = ' data-parent-id="'.$aParametersExt['iPageParent'].'"';
        }

        if( $hasChildren ){
          $toggleBtn = ' <button type="button" class="page-toggle" data-page-id="'.$aData['iPage'].'" data-expanded="0">rozwiń</button>';
        }
      }

      if( (int)$aData['iStatus'] === 0 ){
        $rowClasses .= ' muted';
      }

      $nameEsc = htmlspecialchars( $aData['sName'], ENT_QUOTES, 'UTF-8' );

      $content .= '
        <tr class="'.$rowClasses.'" data-depth="'.$aParametersExt['iDepth'].'" data-page-id="'.$aData['iPage'].'"'.$dataParent.'>
          <td class="id slim" style="padding:5px 10px; text-align:center"><small>'.$aData['iPage'].'</small></td>

          '.( $shop ? '<td class="category slim ">'.htmlspecialchars($pageparent, ENT_QUOTES, 'UTF-8').'</td>' : '' ).'

          <th class="name">
            <a href="?p=pages-form&amp;iPage='.$aData['iPage'].'">'.$nameEsc.'</a>'.$toggleBtn.'
          </th>

          '.( $shop ? '<td class="price slim ">'.htmlspecialchars( (string) $aData['sPrice'], ENT_QUOTES, 'UTF-8' ).' zł</td>' : '' ).'

          <td class="position slim mobile-hide">
            <input type="text" name="aPositions['.$aData['iPage'].']" value="'.$aData['iPosition'].'" class="form-control" size="3" maxlength="4" />
          </td>

          <td class="options slim mobile-hide">
            <div class="flex">
              <a href="'.( ( $config['start_page'] == $aData['iPage'] ) ? null : getUrl($aData['iPage']) ).'" target="_blank" class="options_button mr-2" title="'.$lang['Preview'].'"><img src="templates/admin/img/preview.svg" /></a>
              <a href="?p=pages&amp;iItemDelete='.$aData['iPage'].'" class="delete options_button" title="'.$lang['Delete'].'" onclick="return del( this )"><img src="templates/admin/img/delete.svg" /></a>
            </div>
          </td>

          <td class="status slim mobile-hide">
            <input type="checkbox" name="aStatus['.$aData['iPage'].']" id="aStatus['.$aData['iPage'].']" value="1"'.( ( $aData['iStatus'] == 1 ) ? ' checked="checked"' : null ).' />
            <label for="aStatus['.$aData['iPage'].']"></label>
          </td>
        </tr>';

      // dodaj dzieci pod rodzicem
      $content .= $childrenHtml;

    } // end while

    if( isset( $content ) ){
      if( isset( $aParametersExt['iMenu'] ) && isset( $config['pages_menus'][$aParametersExt['iMenu']] ) ){
        $content = '<div class="'.( $shop ? 'col-12' : 'col-6' ).'">
          <div class="card mb-4">
            <header class="card__header">
              <h4 class="card__title">'.( $shop ? 'Produkty' : $config['pages_menus'][$aParametersExt['iMenu']] ).'</h4>
            </header>
            <div class="table-responsive">
              <table class="list pages table" cellpadding="0" cellspacing="0" border="0">'.$content.'</table>
            </div>
          </div>
        </div>';
      }
      return $content;
    }
  } // end function listPagesAdmin


  /**
  * Lista produktów sklepu z paginacją, sortowaniem i filtrem marki.
  * Zwraca tablicę: rows (HTML <tr>), total, page, pages, per_page.
  *
  * @param array $aParametersExt  page (nr strony), per_page, brand (ID wartości
  *                               filtra brand), sort (klucz z whitelisty)
  * @return array
  */
  public function listProductsAdmin( $aParametersExt = [] ){
    global $lang, $config;

    $oSql    = Sql::getInstance( );
    $perPage = max( 1, (int)( $aParametersExt['per_page'] ?? 50 ) );
    $page    = max( 1, (int)( $aParametersExt['page'] ?? 1 ) );
    $brand   = trim( (string)( $aParametersExt['brand'] ?? '' ) );
    $sort    = (string)( $aParametersExt['sort'] ?? 'date_desc' );

    // mapa ID marki → etykieta (z konfiguracji filtrów sklepu)
    $aBrandLabels = [];
    if( function_exists( 'getFiltersConfig' ) ){
      foreach( getFiltersConfig( ) as $aFilter ){
        if( ( $aFilter['key'] ?? '' ) === 'brand' ){
          $aBrandLabels = $aFilter['values'] ?? [];
          break;
        }
      }
    }

    // wyciągnięcie ID marki z sFilter (JSON1, SQLite >= 3.38); '' gdy brak
    $sBrandExpr = "COALESCE( json_extract( p.sFilter, '$.brand[0]' ), '' )";

    $sWhere = 'p.sLang = "'.$config['language'].'" AND p.sType = 2 AND p.iMenu = 2';
    $aBind  = [];
    if( $brand !== '' && ctype_digit( $brand ) ){
      $sWhere .= ' AND '.$sBrandExpr.' = :brand';
      $aBind[':brand'] = $brand;
    }

    // whitelist sortowań — nic z GET nie trafia do SQL bezpośrednio
    $aSorts = [
      'date_desc'  => 'p.sDate DESC, p.iPage DESC',
      'name_asc'   => 'p.sName COLLATE NOCASE ASC',
      'name_desc'  => 'p.sName COLLATE NOCASE DESC',
      'price_asc'  => 'CAST( p.sPrice AS REAL ) ASC',
      'price_desc' => 'CAST( p.sPrice AS REAL ) DESC',
      'stock_desc' => 'p.iStock DESC, p.sName COLLATE NOCASE ASC',
      'brand'      => $sBrandExpr.' ASC, p.sName COLLATE NOCASE ASC',
    ];
    if( !isset( $aSorts[$sort] ) ){
      $sort = 'date_desc';
    }

    // liczba wszystkich pozycji (dla paginacji)
    $oCount = $oSql->prepare( 'SELECT COUNT(*) FROM pages p WHERE '.$sWhere );
    $oCount->execute( $aBind );
    $iTotal = (int)$oCount->fetchColumn( );
    $iPages = max( 1, (int)ceil( $iTotal / $perPage ) );
    $page   = min( $page, $iPages );

    $oQuery = $oSql->prepare(
      'SELECT p.iPage, p.sName, p.sPrice, p.xPrice, p.iPosition, p.iStatus, p.iStock,
              p.sCurrency, p.sSKU, '.$sBrandExpr.' AS iBrandId,
              ( SELECT f.sFileName FROM files f WHERE f.iPage = p.iPage AND f.iSize > 0
                ORDER BY f.iDefault DESC, f.iPosition ASC LIMIT 1 ) AS sThumb
       FROM pages p
       WHERE '.$sWhere.'
       ORDER BY '.$aSorts[$sort].'
       LIMIT '.$perPage.' OFFSET '.( ( $page - 1 ) * $perPage )
    );
    $oQuery->execute( $aBind );

    $content = null;
    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $nameEsc    = htmlspecialchars( $aData['sName'], ENT_QUOTES, 'UTF-8' );
      $sBrandName = $aBrandLabels[(int)$aData['iBrandId']] ?? '—';
      $sCurr      = !empty( $aData['sCurrency'] ) ? $aData['sCurrency'] : 'PLN';
      $sThumb     = !empty( $aData['sThumb'] )
        ? '<img src="files/500/'.htmlspecialchars( $aData['sThumb'], ENT_QUOTES, 'UTF-8' ).'" alt="" loading="lazy" style="width:38px;height:38px;object-fit:contain;background:#fff;border-radius:4px">'
        : '<span class="muted" style="display:inline-block;width:38px;text-align:center">—</span>';

      $content .= '
        <tr class="page-row product-row'.( ( (int)$aData['iStatus'] === 0 ) ? ' muted' : '' ).'">
          <td class="id slim" style="padding:5px 10px; text-align:center"><small>'.$aData['iPage'].'</small></td>
          <td class="slim" style="padding:4px 6px">'.$sThumb.'</td>
          <th class="name">
            <a href="?p=pages-form&amp;iPage='.$aData['iPage'].'">'.$nameEsc.'</a>
            '.( !empty( $aData['sSKU'] ) ? '<br><small class="muted">'.htmlspecialchars( $aData['sSKU'], ENT_QUOTES, 'UTF-8' ).'</small>' : '' ).'
          </th>
          <td class="brand slim">'.htmlspecialchars( $sBrandName, ENT_QUOTES, 'UTF-8' ).'</td>
          <td class="price slim" style="white-space:nowrap">'.htmlspecialchars( (string) $aData['sPrice'], ENT_QUOTES, 'UTF-8' ).' '.htmlspecialchars( (string) $sCurr, ENT_QUOTES, 'UTF-8' ).'</td>
          <td class="stock slim" style="text-align:center">'.(int)$aData['iStock'].'</td>
          <td class="position slim mobile-hide">
            <input type="text" name="aPositions['.$aData['iPage'].']" value="'.$aData['iPosition'].'" class="form-control" size="3" maxlength="4" />
          </td>
          <td class="options slim mobile-hide">
            <div class="flex">
              <a href="'.getUrl( $aData['iPage'] ).'" target="_blank" class="options_button mr-2" title="'.$lang['Preview'].'"><img src="templates/admin/img/preview.svg" /></a>
              <a href="?p=shop&amp;iItemDelete='.$aData['iPage'].'" class="delete options_button" title="'.$lang['Delete'].'" onclick="return del( this )"><img src="templates/admin/img/delete.svg" /></a>
            </div>
          </td>
          <td class="status slim mobile-hide">
            <input type="checkbox" name="aStatus['.$aData['iPage'].']" id="aStatus['.$aData['iPage'].']" value="1"'.( ( $aData['iStatus'] == 1 ) ? ' checked="checked"' : null ).' />
            <label for="aStatus['.$aData['iPage'].']"></label>
          </td>
        </tr>';
    }

    return [
      'rows'     => $content,
      'total'    => $iTotal,
      'page'     => $page,
      'pages'    => $iPages,
      'per_page' => $perPage,
      'brands'   => $aBrandLabels,
      'sort'     => $sort,
      'brand'    => $brand,
    ];
  } // end function listProductsAdmin


  public function listOrdersAdmin( $aParametersExt = null ){
    global $lang, $config;

    $content = null;
    $oSql = Sql::getInstance( );

    $oQuery = $oSql->getQuery( 'SELECT * FROM orders ORDER BY '.( isset( $_GET['sSort'] ) ? ( ( $_GET['sSort'] == 'id' ) ? 'id DESC' : 'id DESC, date DESC' ) : 'id DESC, date DESC' ) );
    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $content .= '
      <tr>
        <td>'.$aData['id'].'</td>
        <td><strong><a href="?p=orders-info&amp;id='.$aData['id'].'">'.htmlspecialchars( (string) $aData['name'], ENT_QUOTES, 'UTF-8' ).'</a></strong></td>
        <td>'.$aData['price'].' PLN</td>
        <td>'.gmdate("d.m.Y, H:i", $aData['date']).'</td>
        <td class="status"><div class="badge badge-'.$aData['status'].'">'.getElement($aData['status'], $config['order_status']).'</div></td>
        <td class="options">
          <div class="flex">
            <a href="?p=orders-info&amp;id='.$aData['id'].'" class="edit options_button mr-2"><img src="templates/admin/img/preview.svg"></a>
            <a href="?p=orders&amp;iItemDelete='.$aData['id'].'" class="delete options_button" title="'.$lang['Delete'].'" onclick="return del( this )"><img src="templates/admin/img/delete.svg"></a>
          </div>
        </td>
      </tr>';
    }

    if( isset( $content ) )
      return $content;
  }


  public function listFormsAdmin( $aParametersExt = null ){
    global $lang, $config;

    $content = null;
    $oSql = Sql::getInstance( );

    $oQuery = $oSql->getQuery( 'SELECT * FROM form ORDER BY '.( isset( $_GET['sSort'] ) ? ( ( $_GET['sSort'] == 'iForm' ) ? 'iForm DESC' : 'iForm DESC, iDate DESC' ) : 'iForm DESC, iDate DESC' ) );
    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $content .= '
      <tr>
        <td>'.$aData['iForm'].'</td>
        <td><strong><a href="?p=form-info&amp;id='.$aData['iForm'].'">'.htmlspecialchars( (string) $aData['sUserName'], ENT_QUOTES, 'UTF-8' ).'</a></strong></td>
        <td>'.getElement($aData['sCategory'], $config['form_categories']).'</td>
        <td>'.date("d.m.Y", strtotime($aData['sDateStart'])).' - '.date("d.m.Y", strtotime($aData['sDateEnd'])).'</td>
        <td>'.htmlspecialchars( (string) $aData['sTime'], ENT_QUOTES, 'UTF-8' ).'</td>
        <td class="status"><div class="badge badge-'.$aData['iStatus'].'">'.getElement($aData['iStatus'], $config['order_status']).'</div></td>
        <td class="options">
          <div class="flex">
            <a href="?p=form&amp;iItemDelete='.$aData['iForm'].'" class="delete options_button" title="'.$lang['Delete'].'" onclick="return del( this )"><img src="templates/admin/img/delete.svg"></a>
          </div>
        </td>
      </tr>';
    }

    if( isset( $content ) )
      return $content;
  }


  public function listUsersAdmin( $aParametersExt = null ){
    global $lang, $config;

    $content = null;
    $oSql = Sql::getInstance( );

    $oQuery = $oSql->getQuery( 'SELECT * FROM users ORDER BY id ASC' );
    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){

      // KRYTYCZNE: było iStatus (często nie istnieje) + H:s (s = sekundy)
      $muted = ( isset($aData['is_active']) && (int)$aData['is_active'] === 0 ) ? ' class="muted"' : '';

      $content .= '
      <tr'.$muted.'>
        <td>'.$aData['id'].'</td>
        <td><strong><a href="?p=users-info&amp;id='.$aData['id'].'">'.htmlspecialchars( (string) $aData['name'], ENT_QUOTES, 'UTF-8' ).'</a></strong></td>
        <td>'.htmlspecialchars( (string) $aData['email'], ENT_QUOTES, 'UTF-8' ).'</td>
        <td>'.( !empty($aData['created_at']) ? date("H:i, d.m.Y", strtotime($aData['created_at'])) : '-' ).'</td>
        <td><div class="badge badge-'.$aData['is_active'].'">'.getElement($aData['is_active'], $config['user_status']).'</div></td>
        <td class="options">
          <div class="flex">
            <a href="?p=users&amp;iItemDelete='.$aData['id'].'" class="delete options_button" title="'.$lang['Delete'].'" onclick="return del( this )"><img src="templates/admin/img/delete.svg"></a>
          </div>
        </td>
      </tr>';
    }

    if( isset( $content ) )
      return $content;
  }


  /**
  * Saves page's position and status
  * @return void
  * @param array $aForm
  */
  public function savePages( $aForm ){
    global $config;

    if( isset( $aForm['aPositions'] ) && is_array( $aForm['aPositions'] ) ){

      clearCache( );

      $oSql = Sql::getInstance( );
      $oQuery = $oSql->getQuery( 'SELECT iPage, iPosition, iStatus FROM pages WHERE sLang = "'.$config['language'].'"' );
      while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
        if( isset( $aForm['aPositions'][$aData['iPage']] ) && !isset( $aChanged[$aData['iPage']] ) ){
          $iStatus = isset( $aForm['aStatus'][$aData['iPage']] ) ? 1 : 0;
          if( $iStatus != $aData['iStatus'] ){
            $aChanged[$aData['iPage']] = true;
            if( $iStatus == 0 ){
              $this->generatePageAllChildrens( $aData['iPage'] );
              if( isset( $this->aPageChildrens ) ){
                foreach( $this->aPageChildrens as $iPage ){
                  if( isset( $aForm['aStatus'][$iPage] ) ){
                    unset( $aForm['aStatus'][$iPage] );
                    $aChanged[$iPage] = true;
                  }
                } // end foreach
              }
            }
          }

          if( !isset( $aChanged[$aData['iPage']] ) && $aForm['aPositions'][$aData['iPage']] != $aData['iPosition'] ){
            $aChanged[$aData['iPage']] = true;
          }
        }
      } // end while

      if( isset( $aChanged ) ){
        foreach( $aChanged as $iPage => $bValue ){
          $oSql->query( 'UPDATE pages SET iPosition = "'.( (int) $aForm['aPositions'][$iPage] ).'", iStatus = '.( isset( $aForm['aStatus'][$iPage] ) ? 1 : 0 ).' WHERE iPage = '.$iPage );
        } // end foreach
      }
    }
  } // end function savePages


  /**
  * Returns id's of all subpages of a given page
  * @return void
  * @param int $iPage
  */
  private function throwSubpagesIdAdmin( $iPage ){
    $iCount = count( $this->aPagesChildrens[$iPage] );
    for( $i = 0; $i < $iCount; $i++ ){
      $this->mData[$this->aPagesChildrens[$iPage][$i]] = true;
      if( isset( $this->aPagesChildrens[$this->aPagesChildrens[$iPage][$i]] ) ){
        $this->throwSubpagesIdAdmin( $this->aPagesChildrens[$iPage][$i] );
      }
    } // end for
  } // end function throwSubpagesIdAdmin


  /**
  * Saves page data including data of all attached images and files
  * @return int
  * @param array $aForm
  */
  public function savePage( $aForm ){
    global $config;

    clearCache( );

    $oFile = FilesAdmin::getInstance( );
    $oSql  = Sql::getInstance( );

    // sFilter zostawiamy "jak jest" (bez dodatkowych przeróbek)
    $rawFilter = isset($aForm['sFilter']) ? $aForm['sFilter'] : null;

    $aForm = changeMassTxt(
      $aForm,
      'ndnl',
      Array('sDescriptionShort', 'nds ndnl'),
      Array('sDescriptionFull', 'nds ndnl'),
      Array('sDescriptionMeta', 'Nds')
    );

    if( $rawFilter !== null )
      $aForm['sFilter'] = $rawFilter;

    $aForm['iStatus'] = isset( $aForm['iStatus'] ) ? 1 : 0;

    $currentPageId = ( isset($aForm['iPage']) && is_numeric($aForm['iPage']) ) ? (int)$aForm['iPage'] : 0;

    if( isset( $aForm['iPageParent'] ) && is_numeric( $aForm['iPageParent'] ) && (int)$aForm['iPageParent'] != $currentPageId ){
      $aDataParent = $oSql->throwAll( 'SELECT iMenu, iStatus FROM pages WHERE iPage = '.(int)$aForm['iPageParent'] );
      $aForm['iMenu'] = $aDataParent['iMenu'];
      if( $aForm['iStatus'] == 1 && $aDataParent['iStatus'] == 0 )
        $aForm['iStatus'] = 0;
    }
    else{
      $aForm['iPageParent'] = 0;
    }

    if( isset( $aForm['iPosition'] ) && !is_numeric( $aForm['iPosition'] ) )
      $aForm['iPosition'] = 0;

    if( isset( $aForm['iPage'] ) && is_numeric( $aForm['iPage'] ) ){
      if( $aForm['iStatus'] == 0 && $aForm['iStatus'] != $oSql->getColumn( 'SELECT iStatus FROM pages WHERE iPage = '.(int)$aForm['iPage'] ) ){
        $this->generatePageAllChildrens( (int)$aForm['iPage'] );
        if( isset( $this->aPageChildrens ) ){
          foreach( $this->aPageChildrens as $iPage ){
            $oSql->query( 'UPDATE pages SET iStatus = 0 WHERE iPage = '.(int)$iPage );
          }
        }
      }
      $oSql->update( 'pages', $aForm, Array( 'iPage' => (int)$aForm['iPage'] ), true );
    }
    else{
      $aForm['sLang'] = $config['language'];
      unset( $aForm['iPage'] );
      $aForm['iPage'] = $oSql->insert( 'pages', $aForm, true );
    }

    if( ( isset( $aForm['iChangedFiles'] ) && $aForm['iChangedFiles'] == 1 ) || isset( $aForm['aDirFiles'] ) || isset( $aForm['aFilesDelete'] ) ){
      $oFile->saveFiles( $aForm, $aForm['iPage'] );
    }

    return $aForm['iPage'];
  } // end function savePage


  /**
  * Returns all main pages childrens
  * @return null
  * @param int $iPageParent
  * @param bool $bUnset
  */
  private function generatePageAllChildrens( $iPageParent = null, $bUnset = true ){
    if( isset( $bUnset ) )
      unset( $this->aPageChildrens );
    $oSql = Sql::getInstance( );
    $oQuery = $oSql->getQuery( 'SELECT iPage FROM pages WHERE iPageParent = "'.$iPageParent.'"' );
    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $this->aPageChildrens[$aData['iPage']] = $aData['iPage'];
      $this->generatePageAllChildrens( $aData['iPage'], null );
    }
  } // end function generatePageAllChildrens

  /**
  * Deletes a page and its subpages
  * @return void
  * @param int $iPage
  */
  public function deletePage( $iPage ){
    $oSql = Sql::getInstance( );
    $oFile = FilesAdmin::getInstance( );

    clearCache( );
    $this->generatePageAllChildrens( (int)$iPage );
    $this->aPageChildrens[(int)$iPage] = (int)$iPage;

    foreach( $this->aPageChildrens as $pid ){
      $oSql->query( 'DELETE FROM pages WHERE iPage = '.(int)$pid );
    }
    $oFile->deleteFiles( $this->aPageChildrens );
  } // end function deletePage

  /**
  * Returns page data
  * @return array
  * @param int $iPage
  */
  public function throwPageAdmin( $iPage ){
    global $config;

    $oSql = Sql::getInstance( );
    $aData = $oSql->throwAll( 'SELECT * FROM pages WHERE iPage = '.(int)$iPage.' AND sLang = "'.$config['language'].'"' );
    if( isset( $aData ) && is_array( $aData ) ){
      return $aData;
    }
  } // end function throwPageAdmin

  public function throwOrderAdmin( $id ){
    $oSql = Sql::getInstance( );
    $aData = $oSql->throwAll( 'SELECT * FROM orders WHERE id = '.(int)$id );
    if( isset( $aData ) && is_array( $aData ) ){
      return $aData;
    }
  }

  public function throwFormAdmin( $id ){
    $oSql = Sql::getInstance( );
    $aData = $oSql->throwAll( 'SELECT * FROM form WHERE iForm = '.(int)$id );
    if( isset( $aData ) && is_array( $aData ) ){
      return $aData;
    }
  }

  public function throwUserAdmin( $id ){
    $oSql = Sql::getInstance( );
    $aData = $oSql->throwAll( 'SELECT * FROM users WHERE id = '.(int)$id );
    if( isset( $aData ) && is_array( $aData ) ){
      return $aData;
    }
  }

};
?>
