<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}
require_once $theme.'_header.php';
if (isset($aData['sName'])) {

    
    ?>
    <div class="container">
        <div class="mainPage__wrapper shopPage">
            <?php require_once $theme.'_column.php'; ?>

            <div class="mainPage__content">
                <?php
    
                echo renderActiveFilters($oPage->aPages[$aData['iPage']]['sLinkName'], ['sort', 'page' ]);
    
                // --------------------------------------------------
                // WIDOK PRODUKTU (jeśli strona ma cenę)
                // --------------------------------------------------
                if (!empty($aData['sPrice'])): ?>
                    <article class="mainPage__article negativeMargin productMore">
                        
                        

                        <?php
                        // pełny opis
                        

                        // pliki produktu
                        echo $oFile->listFiles($aData['iPage']);

                        // dodatkowa galeria (typ 3)
                        echo $oFile->listImages($aData['iPage'], [
                            'iType' => 3,
                            'class' => 'galleryGrid productMore__galleryGrid'
                        ]);
                        ?>
                    </article>
                    

                <?php
                // --------------------------------------------------
                // WIDOK STRONY / KATEGORII (jeśli strona nie jest produktem)
                // --------------------------------------------------
                else:

                    $content = '';

                    // krótki opis
                    if (!empty($aData['sDescriptionShort'])) {
                        $content .= '<div class="mainPage__desc font-md">'.$aData['sDescriptionShort'].'</div>';
                    }

                    // galeria główna
                    $content .= $oFile->listImages($aData['iPage'], [
                        'iType'      => 1,
                        'class'      => 'mainPage',
                        'full_image' => true,
                        'parallax'   => true,
                        'slider'     => true
                    ]);

                    // pełny opis
                    if (!empty($aData['sDescriptionFull']) && $aData['sDescriptionFull'] !== $aData['sDescriptionShort']) {
                        $content .= '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>';
                    }

                    // pliki
                    $content .= $oFile->listFiles($aData['iPage']);

                    if ($content !== '') {
                        echo '<article class="mainPage__article negativeMargin">'.$content.'</article>';
                    }

                    // jeśli to główna strona sklepu:
                    if (defined('SHOP_PAGE') && SHOP_PAGE) {
                        // np. pokazujemy “produkty” = podstrony w menu 2
                        echo listPagesQuery([
                            'sql'   => 'iPageParent!=0 AND iMenu=2',
                            'class' => 'productsList pagesList-'.$aData['iPage'],
//                            'per_page' => 1
                        ]);
                    } else {
                        // zwykła lista podstron
                        echo $oPage->listPages($aData['iPage'], [
                            'hide_cat' => true,
                            'per_page' => 1
                        ]);
                    }

                    // dodatkowa galeria (typ 3)
                    echo $oFile->listImages($aData['iPage'], [
                        'iType' => 3,
                        'class' => 'galleryGrid'
                    ]);

                    // data publikacji
                    if (!empty($aData['sDate'])) {
                        echo '<div class="mainPage__date">Data publikacji '.$aData['sDate'].'</div>';
                    }

                endif; // koniec: widok produktu vs zwykłej strony
                ?>
            </div>
        </div>

        <?php if (!empty($aData['sPrice'])): ?>
            <hr class="separator">
            <div class="productMore__discover">
                <header class="section__header">
                    <h2 class="section__title">Zobacz więcej</h2>
                </header>

                <script>
                    $(function() {
                        $('.ourProducts__slider').owlCarousel({
                            loop: true,
                            margin: 20,
                            nav: true,
                            dots: true,
                            autoplay: true,
                            autoplayHoverPause: true,
                            autoplayTimeout: 5000,
                            responsive : {
                                0: { items: 1 },
                                578: { items: 1 },
                                768: { items: 2 },
                                992: { items: 3 },
                                1200: { items: 4 }
                            }
                        });
                    });
                </script>

                <?php
                // produkty z tej samej kategorii / tego samego rodzica
                echo $oPage->listPages($aData['iPageParent'], [
                    'hide_cat' => true,
                    'class'    => 'ourProducts__slider owl-carousel'
                ]);
                ?>
            </div>
        <?php endif; ?>

    </div>
    

    
    <?php

} else {
    require_once $theme.'_404.php';
}

require_once $theme.'_footer.php';
?>

