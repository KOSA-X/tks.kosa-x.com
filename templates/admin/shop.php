<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

if( isset( $_POST['sOption'] ) ){
    $oPage->savePages( $_POST );
    header(
        'Location: '
        .str_replace( '&amp;', '&', $_SERVER['REQUEST_URI'] )
        .( strstr( $_SERVER['REQUEST_URI'], 'sOption=' ) ? null : '&sOption=' )
    );
    exit;
}

if( isset( $_GET['iItemDelete'] ) && is_numeric( $_GET['iItemDelete'] ) ){
    $oPage->deletePage( $_GET['iItemDelete'] );
    header( 'Location: '.$config['admin_file'].'?p=shop&sOption=del' );
    exit;
}

$sSelectedMenu = 'shop';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

// ------------------------------------------------------------
// Lista produktów: paginacja (50/stronę), sortowanie, filtr marki
// ------------------------------------------------------------
$aList = $oPage->listProductsAdmin( [
    'page'     => isset( $_GET['iSite'] ) ? (int)$_GET['iSite'] : 1,
    'per_page' => 50,
    'brand'    => $_GET['sBrand'] ?? '',
    'sort'     => $_GET['sSort'] ?? 'date_desc',
] );

$aSortOptions = [
    'date_desc'  => 'Najnowsze',
    'brand'      => 'Marka (grupowanie)',
    'name_asc'   => 'Nazwa A→Z',
    'name_desc'  => 'Nazwa Z→A',
    'price_asc'  => 'Cena rosnąco',
    'price_desc' => 'Cena malejąco',
    'stock_desc' => 'Stan magazynowy',
];

/**
 * Link do strony listy z zachowaniem filtra/sortowania
 */
function shopListUrl( int $iSite, array $aList ): string {
    $aQuery = [ 'p' => 'shop' ];
    if( $iSite > 1 )               $aQuery['iSite']  = $iSite;
    if( $aList['brand'] !== '' )   $aQuery['sBrand'] = $aList['brand'];
    if( $aList['sort'] !== 'date_desc' ) $aQuery['sSort'] = $aList['sort'];
    return '?'.http_build_query( $aQuery );
}

/**
 * Paginacja: « 1 … 4 5 [6] 7 8 … 99 »
 */
function shopPagination( array $aList ): string {
    if( $aList['pages'] <= 1 ){
        return '';
    }

    $iPage  = $aList['page'];
    $iPages = $aList['pages'];

    // zakres stron wokół bieżącej + pierwsza/ostatnia
    $aShow = [ 1, $iPages ];
    for( $i = $iPage - 2; $i <= $iPage + 2; $i++ ){
        if( $i >= 1 && $i <= $iPages ) $aShow[] = $i;
    }
    $aShow = array_unique( $aShow );
    sort( $aShow );

    $out = '<nav class="shopPagination" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:15px 0">';
    if( $iPage > 1 ){
        $out .= '<a class="button button-border" href="'.shopListUrl( $iPage - 1, $aList ).'">&laquo;</a>';
    }
    $iPrev = 0;
    foreach( $aShow as $i ){
        if( $iPrev && $i > $iPrev + 1 ){
            $out .= '<span class="muted">&hellip;</span>';
        }
        $out .= ( $i === $iPage )
            ? '<span class="button">'.$i.'</span>'
            : '<a class="button button-border" href="'.shopListUrl( $i, $aList ).'">'.$i.'</a>';
        $iPrev = $i;
    }
    if( $iPage < $iPages ){
        $out .= '<a class="button button-border" href="'.shopListUrl( $iPage + 1, $aList ).'">&raquo;</a>';
    }
    $out .= '</nav>';

    return $out;
}

// Link do strony sklepu (jeśli skonfigurowana)
$shopHref = null;
if( !empty( $config['shop_page'] ) && isset( $oPage->aLinksIds[$config['shop_page']] ) ){
    $shopHref = './'.$oPage->aLinksIds[$config['shop_page']];
}
?>

<header class="mainPage__header mainPage__header_row">
    <h1 class="mainPage__title">
        <?php if( $shopHref ){ ?>
            <a href="<?php echo $shopHref; ?>"><?php echo $lang['Shop']; ?></a>
        <?php } else { ?>
            <?php echo $lang['Shop']; ?>
        <?php } ?>
        <small class="muted" style="font-size:14px;font-weight:400">
            &nbsp;produkty: <?php echo $aList['total']; ?>
            <?php if( $aList['pages'] > 1 ){ ?>
                &middot; strona <?php echo $aList['page']; ?> z <?php echo $aList['pages']; ?>
            <?php } ?>
        </small>
    </h1>
</header>

<?php
if( isset( $_GET['sOption'] ) ){
    echo '<div class="alert alert-success mb-3">'.$lang['Operation_completed'].'</div>';
}
?>

<!-- FILTRY I SORTOWANIE LISTY -->
<form action="" method="get" class="shopToolbar" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:15px">
    <input type="hidden" name="p" value="shop" />

    <div class="form-item" style="margin:0">
        <label for="sBrand">Marka</label>
        <select name="sBrand" id="sBrand" onchange="this.form.submit()">
            <option value="">— wszystkie —</option>
            <?php foreach( $aList['brands'] as $iBrandId => $sBrandLabel ){ ?>
                <option value="<?php echo (int)$iBrandId; ?>"<?php echo ( (string)$iBrandId === $aList['brand'] ) ? ' selected="selected"' : ''; ?>>
                    <?php echo html( $sBrandLabel ); ?>
                </option>
            <?php } ?>
        </select>
    </div>

    <div class="form-item" style="margin:0">
        <label for="sSort">Sortowanie</label>
        <select name="sSort" id="sSort" onchange="this.form.submit()">
            <?php foreach( $aSortOptions as $sKey => $sLabel ){ ?>
                <option value="<?php echo $sKey; ?>"<?php echo ( $sKey === $aList['sort'] ) ? ' selected="selected"' : ''; ?>>
                    <?php echo html( $sLabel ); ?>
                </option>
            <?php } ?>
        </select>
    </div>

    <?php if( $aList['brand'] !== '' || $aList['sort'] !== 'date_desc' ){ ?>
        <a href="?p=shop" class="button button-border">Wyczyść</a>
    <?php } ?>
</form>

<?php if( !empty( $aList['rows'] ) ){ ?>

    <?php echo shopPagination( $aList ); ?>

    <form action="<?php echo html( shopListUrl( $aList['page'], $aList ) ); ?>" name="form" method="post" class="main-form">

        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <header class="card__header">
                        <h4 class="card__title">Produkty</h4>
                        <input type="submit" name="sOption" class="button" value="<?php echo $lang['save']; ?>" />
                    </header>
                    <div class="table-responsive">
                        <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <th class="slim">ID</th>
                                <th class="slim"></th>
                                <th>Nazwa / SKU</th>
                                <th class="slim">Marka</th>
                                <th class="slim">Cena</th>
                                <th class="slim">Stan</th>
                                <th class="slim mobile-hide">Pozycja</th>
                                <th class="slim mobile-hide">Opcje</th>
                                <th class="slim mobile-hide">Status</th>
                            </tr>
                            <?php echo $aList['rows']; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </form>

    <?php echo shopPagination( $aList ); ?>

<?php } else { ?>

    <div class="alert alert-danger"><?php echo $lang['Data_not_found']; ?></div>

<?php } ?>

<?php
require_once 'templates/admin/_footer.php';
?>
