<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}
?>
</div> <!-- /mainPage -->

<footer class="mainFooter">
    <?php
    // Bezpieczne sprawdzenie iTheme (na wypadek 404 / braku $aData)
    $theme = isset($aData['iTheme']) ? (int)$aData['iTheme'] : null;
    $showFooterTop = !in_array($theme, [3, 4, 5, 10], true);

    if ($showFooterTop): ?>
        <div class="container">
            <div class="mainFooter__row">
                <div class="mainFooter__column">
                    <div class="mainFooter__logo"><?php echo LOGO; ?></div>
                    <?php if (!empty($config['slogan'])): ?>
                        <div class="mainFooter__slogan"><?php echo $config['slogan']; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($config['description'])): ?>
                        <div class="mainFooter__desc"><?php echo $config['description']; ?></div>
                    <?php endif; ?>

                    <?php echo socialMedia(); ?>
                </div>

                <div class="mainFooter__column">
                    <h5 class="mainFooter__title">Nawigacja</h5>
                    <?php
                    echo $oPage->listPagesMenu(1, [
                        'sClassName' => 'footerMenu',
                        'bExpanded'  => false,
                        'iDepthLimit'=> 0
                    ]);
                    ?>
                </div>

                <div class="mainFooter__column">
                    <h5 class="mainFooter__title">Kontakt</h5>
                    <?php echo contacts(['phone' => true, 'email' => true, 'location' => true]); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
 
 <div id="modal-contact" class="modal" style="display:none" data-selectable="true">
   <div class="card">
          <header class="card__header">
            <h5 class="card__title">
                <img src="<?= ICONS; ?>email.svg" alt="">
                Kontakt
            </h5>
        </header>
          <div class="card__wrapper">
           <ul class="list">
               <li>
                   <div class="contactItem contactItem-phone">
                       <div class="content">
                           <span class="label"><?php echo $lang['phone']; ?></span>
                           <a href="tel:<?php echo $config['phone']; ?>" class="value"><?php echo $config['phone']; ?></a>
                       </div>
                       <div class="action">
                           <a href="tel:<?php echo $config['phone']; ?>" class="button"><img src="<?php echo ICONS; ?>phone-line.svg" alt="<?php echo $lang['phone']; ?>" class="invert m-0"></a>
                       </div>
                   </div>
               </li>
               <li>
                   <div class="contactItem contactItem-email">
                       <div class="content">
                           <span class="label"><?php echo $lang['email']; ?></span>
                           <a href="mailto:<?php echo $config['email']; ?>" class="value"><?php echo $config['email']; ?></a>
                       </div>
                       <div class="action">
                           <a href="mailto:<?php echo $config['email']; ?>" class="button"><img src="<?php echo ICONS; ?>email-line.svg" alt="<?php echo $lang['email']; ?>" class="invert m-0"></a>
                       </div>
                   </div>
               </li>
               <li>
                   <div class="contactItem contactItem-location">
                       <div class="content">
                           <span class="label"><?php echo $lang['location']; ?></span>
                           <a href="<?php echo $config['maps']; ?>" target="_blank" class="value"><?php echo $config['street']; ?>, <?php echo $config['city']; ?></a>
                       </div>
                       <div class="action">
                           <a href="<?php echo $config['maps']; ?>" target="_blank" class="button"><img src="<?php echo ICONS; ?>location-line.svg" alt="<?php echo $lang['location']; ?>" class="invert m-0"></a>
                       </div>

                   </div>
               </li>
           </ul>
       </div>
    </div>
</div>
 
 
 


            

 
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

<?php if (feature('search')): // modal wyszukiwarki tylko gdy moduł włączony ?>
 <div id="search-widget" class="modal"  style="display:none" data-selectable="true">
    <div class="card">
        <header class="card__header">
            <h5 class="card__title">
                <img src="<?= ICONS; ?>search.svg" alt="">
                Szukaj
            </h5>
        </header>
        <div class="card__wrapper">
            <div class="card__content">
                <form action="<?php echo getUrl($config['search_page']); ?>" method="get" class="searchForm">
                    <div class="row">
                        <div class="col-8">
                            <div class="form-floating form-item">
                                <input
                                    type="text"
                                    name="q"
                                    id="q"
                                    class="form-control"
                                    placeholder="Szukaj..."
                                    value=""
                                    required
                                >
                                <label for="q">Wpisz szukaną frazę</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="button button-lg w-100 h-100">
                                Szukaj
                            </button>
                        </div>
                    </div>
                </form>
            </div>
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
