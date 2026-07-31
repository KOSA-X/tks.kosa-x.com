<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

 
 
$descTab = "";

if (!empty($aData['sDescriptionFull']) && $aData['sDescriptionFull'] !== $aData['sDescriptionShort']) {
    $descTab = '<div class="mainPage__descFull">'.$aData['sDescriptionFull'].'</div>';
} 
$descTab .= $oFile->listFiles($aData['iPage']);

    
?>

<div class="productMore negativeMargin">
<div class="row">
                            <div class="col-6">
                                <?php
                                // galeria produktu
                                echo $oFile->listImages($aData['iPage'], [
                                    'iType'  => 1,
                                    'slider' => true,
                                    'class'  => 'productMore__gallery negativeMargin'
                                ]);
                                ?>
                            </div>
                            <div class="col-6">
                                <div class="productMore__content " data-product-id="<?php echo $aData['iPage']; ?>">
                                   <div class="productMore__price">
                                        <div class="label">Cena</div>
                                        <div class="value"><?php echo getPrice($aData['iPage']); ?></div>
                                    </div>
                                    
                                    <?php
                                    if (!empty($aData['sDescriptionShort'])) {
                                        echo '<div class="mainPage__descShort">'.$aData['sDescriptionShort'].'</div>';
                                    }
                                    
    
//    require_once $theme.'_title.php';
 
                                    // Konfigurator produktu - SELECT dla wielu wartości, tekst dla jednej
                                    $productConfig = renderProductConfigFilters($aData['sFilter'] ?? null, $aData['iPage']);
                                    if ($productConfig) {
                                        echo $productConfig;
                                    }

 
                                    
                                    ?>
                                    <?php
                                    // Dostępność: stock 0 = produkt na zamówienie (nadal można kupić)
                                    $productStock = (int)($aData['iStock'] ?? 0);
                                    ?>
                                    <div class="productAvailability">
                                        <?php if ($productStock > 0): ?>
                                            <span class="productAvailability__status productAvailability__status-in">
                                                <?php echo $lang['stock_available'] ?? 'W magazynie'; ?>:
                                                <strong><?php echo $productStock; ?> szt.</strong>
                                            </span>
                                        <?php else: ?>
                                            <span class="productAvailability__status productAvailability__status-order">
                                                <?php echo $lang['stock_on_order'] ?? 'Produkt dostępny na zamówienie'; ?>
                                            </span>
                                            <small class="productAvailability__note">
                                                <?php echo $lang['stock_on_order_note'] ?? 'Termin realizacji potwierdzimy po złożeniu zamówienia.'; ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($aData['sSKU'])): ?>
                                            <small class="productAvailability__sku">SKU: <?php echo html($aData['sSKU']); ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="productMore__addToCart">
                                        <button
                                          class="add-to-cart button button-lg w-100"
                                          data-product-id="<?php echo $aData['iPage']; ?>"
                                          data-action="add"
                                          data-loading="<img src='<?php echo ICONS; ?>loader.svg' alt='Ładowanie...' class='spin'>">
                                          <img src="<?php echo ICONS; ?>cart.svg" alt="Dodaj do koszyka">
                                          Dodaj do koszyka
                                        </button>
                                        <div class="productConfig__message" style="display: none;">
                                            <img src="<?php echo ICONS; ?>info.svg" alt="Info" class="invert">
                                            <?php echo $lang['choose_product_options'] ?? 'Wybierz wszystkie opcje produktu przed dodaniem do koszyka.'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        
                <div class="tabs">
                  <nav class="tabs__nav">
                   <?php if($descTab){ ?>
                       <a href="#" data-target="product-description" class="selected">Opis</a>
                       <a href="#" data-target="product-delivery">Dostawa i płatnosć</a>
                    <?php }else{ ?>
                        <a href="#" data-target="product-delivery" class="selected">Dostawa i płatnosć</a>
                    <?php } ?>
                  </nav>
                  <div class="tabs__content">
                   <?php if($descTab){ ?>
                    <div class="tabs__item" id="tabs-product-description">
                        <?php echo $descTab; ?>
                    </div>
                    <?php } ?>
                    <div class="tabs__item" id="tabs-product-delivery">
                      <?php
                        echo "<strong>Metody płatności:</strong>";
                        echo '<ul class="simpleList mb-4">';
                        foreach($config['order_payment'] as $payment) {
                            if(empty($payment['disabled'])) {
                                echo "<li>" . $payment['label'] . "</li>";
                            }
                        }
                        echo '</ul>';
                        echo "<strong>Sposoby dostawy:</strong>";
                        echo '<ul class="simpleList">';
                        foreach($config['order_delivery'] as $delivery) {
                            if(empty($payment['disabled'])) {
                                echo "<li>" . $delivery['label'] . " - <strong>" . $delivery['price'] . " zł</strong></li>";
                            }
                        }
                        echo '</ul>';
                    ?>
                     </div>
                  </div>
                </div>
                </div>
                <script type="application/ld+json">
                        {
                          "@context": "https://schema.org",
                          "@type": "Product",
                          "name": "<?php echo $aData['sName']; ?>",
                          "image": [
                            "<?php echo $oFile->getDefaultImageUrl( $aData['iPage'], Array( 'iType' => 1, 'full_image' => TRUE, 'bNoLinks' => TRUE) ); ?>"
                          ],
                          "description": "<?php echo htmlspecialchars(strip_tags($aData['sDescriptionShort'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>",
                          "sku": "PIZ-ANANAS-001",
                          "brand": {
                            "@type": "Brand",
                            "name": "<?php echo $config['logo']; ?>"
                          },
                          "offers": {
                            "@type": "Offer",
                            "url": "<?php echo getUrl($aData['iPage']); ?>",
                            "priceCurrency": "<?php echo $config['currency']; ?>",
                            "price": "<?php echo getPrice($aData['iPage'], 'number'); ?>",
                            "availability": "https://schema.org/InStock"
                          }
                        }
                        </script>
                        

    <?php 


// --------------------------------------------------
// WIĘCEJ MODELI OD TEJ SAMEJ MARKI (9 losowych felg)
// Renderowane w page.php poniżej .mainPage__article,
// wewnątrz .mainPage__content (tylko dla produktów).
// --------------------------------------------------

$currentFilters = parsePageFilters($aData['sFilter'] ?? null);
$brandIdRaw     = $currentFilters['brand'] ?? null;
$brandId        = is_array($brandIdRaw) ? (string)($brandIdRaw[0] ?? '') : (string)($brandIdRaw ?? '');

if ($brandId !== '' && ctype_digit($brandId)) {

    // etykieta marki z konfiguracji filtrów
    $brandName = '';
    foreach (getFiltersConfig() as $cfgFilter) {
        if (($cfgFilter['key'] ?? '') === 'brand' && !empty($cfgFilter['values'][$brandId])) {
            $brandName = (string)$cfgFilter['values'][$brandId];
            break;
        }
    }

    if ($brandName !== '') {
        $moreList = listPagesQuery([
            'sql'        => 'sType = 2 AND iMenu = 2 AND iPage != '.(int)$aData['iPage']
                          . ' AND sFilter LIKE \'%"brand":["'.$brandId.'"]%\'',
            'pagination' => false,
            'per_page'   => 9,
            'order'      => 'random',
            'hide_cat'   => true,
            'class'      => 'productsList moreFromBrand__list',
        ]);

        // sekcja tylko, gdy są jakiekolwiek inne modele tej marki
        if (strpos($moreList, 'pageItem') !== false) {
            ?>
            <section class="section productMore__seeAlso">
                <header class="section__header">
                    <h3 class="section__title">
                        <?php echo html(($lang['more_from_brand'] ?? 'Więcej modeli od').' '.$brandName); ?>
                    </h3>
                </header>
                <?php echo $moreList; ?>
            </section>
            <?php
        }
    }
}


