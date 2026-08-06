<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}
?>
</div> <!-- /mainPage -->

<footer class="mainFooter">
 

    <div class="mainFooter__bottom">
        <div class="container">
            <div class="mainFooter__info">
                <div class="copy">
                    Copyright <?php echo date('Y'); ?> ©
                    <?php
                    // Polityka prywatności
                    if (!empty($config['private_policy'])) {
                        $ppId     = (int)$config['private_policy'];
                        $ppUrl    = getUrl($ppId) ?? '#';
                        $ppName   = getData($ppId, 'sName') ?? '';

                        echo ' <a href="'.$ppUrl.'">'.$ppName.'</a>';
                    }

                    // Mapa strony
                    if (!empty($config['sitemap_page'])) {
                        $smId   = (int)$config['sitemap_page'];
                        $smUrl  = getUrl($smId) ?? '#';
                        $smName = getData($smId, 'sName') ?? '';

                        echo ' | <a href="'.$smUrl.'">'.$smName.'</a>';
                    }
                    ?>
                </div>
            </div>

            <div class="mainFooter__author">
                <span class="designer">
                    <a href="https://kosa-x.com/" target="_blank" rel="noopener" title="KOSΛ X">KOSΛ X</a>
                </span>
                <span class="cms">
                    <a href="https://opensolution.org/" target="_blank" rel="noopener" title="CMS by Quick.CMS">
                        CMS by Quick.CMS
                    </a>
                </span>
            </div>
        </div>
    </div>
</footer>

</main> <!-- /mainBody -->

<!--<div class="overlayWindow"></div>-->
  

            

 
<?php if (feature('shop')): // modal koszyka tylko gdy moduł sklepu włączony ?>
<div id="cart-widget" class="modal cartWidget"  style="display:none" data-selectable="true">
   <div class="card ">
        <header class="card__header">
            <h5 class="card__title">
                <img src="<?= ICONS; ?>cart.svg" alt="">
                Produkty w koszyku
            </h5>
        </header>
        <div class="card__wrapper">
            <?php
            $cartData = cartList();
            echo $cartData['html']; 
            ?>
            
        </div>
    </div>

</div>
<?php endif; ?>
 

<?php echo $oPage->listPagesPopup($config['footer_popups_page']); ?>
 
<script async defer src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

<?php
// ==========================
// JSON-LD: WebPage
// ==========================
$pageName = isset($aData['sName']) ? $aData['sName'] : ($config['title'] ?? '');
$pageName = (string)$pageName;

$pageDesc = isset($page_desc) && $page_desc !== ''
    ? $page_desc
    : ($config['description'] ?? '');

$pageJsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'WebPage',
    'name'        => $pageName,
    'url'         => CURRENT_URL,
    'description' => $pageDesc,
    'inLanguage'  => $config['language'] ?? 'pl',
    'isPartOf'    => [
        '@type' => 'WebSite',
        'name'  => $config['logo'] ?? '',
        'url'   => BASE_URL,
    ],
];

// ==========================
// JSON-LD: Organization
// ==========================
$sameAs = array_values(array_filter([
    !empty($config['facebook'])  ? $config['facebook']  : null,
    !empty($config['instagram']) ? $config['instagram'] : null,
    !empty($config['tiktok'])    ? $config['tiktok']    : null,
    !empty($config['linkedin'])  ? $config['linkedin']  : null,
]));

$orgJsonLd = [
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $config['logo'] ?? '',
    'url'      => BASE_URL,
    'logo'     => LOGO_URL,
];

if (!empty($config['phone'])) {
    $orgJsonLd['contactPoint'] = [[
        '@type'           => 'ContactPoint',
        'telephone'       => $config['phone'],
        'contactType'     => 'customer service',
        'areaServed'      => 'PL',
        'availableLanguage'=> ['pl', 'en'],
    ]];
}

if (!empty($sameAs)) {
    $orgJsonLd['sameAs'] = $sameAs;
}
?>

<script type="application/ld+json">
<?php echo json_encode($pageJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>

</script>
<script type="application/ld+json">
<?php echo json_encode($orgJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>

</script>
</body>
</html>
