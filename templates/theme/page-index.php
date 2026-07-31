<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

// ==========================================================
// STRONA GŁÓWNA — PRZYKŁADOWE SEKCJE (WZORY DO REPLIKACJI)
// ==========================================================
// To NIE jest sztywny układ. Każda sekcja to wzorzec pokazujący
// przyjęty styl: (1) moduł włączany przez feature() = przypisanie strony w
// panelu (Konfiguracja → „Dopasowanie stron"); pusta strona = sekcja znika,
// (2) treść ZAWSZE z bazy (getData()), etykiety z $lang[], linki z getUrl(),
// (3) własna klasa .section + .section__scroll pod animacje.
// Przy nowym projekcie: rozbudowuj, dokładaj i twórz nowe sekcje śmiało
// (patrz CLAUDE.md §9.7). Zachowaj tylko sens struktury, nie ograniczaj się do tych.

require_once $theme.'_header.php';

?>
<!-- HERO SECTION -->


<?php if($config['about_page']): ?>
<section class="section aboutUs">
    <div class="section__scroll" id="section-aboutUs"></div>
    <div class="container">
        <div class="aboutUs__content">
            <header class="section__header showUp">
                <div class="section__subtitle">
                    <a href="<?php echo getUrl($config['about_page']); ?>">
                        <?php echo getData($config['about_page'], 'sName'); ?>
                    </a>
                </div>
                <h2 class="section__title">
                    <?php echo $config['slogan']; ?>
                </h2>
            </header>
            <div class="section__desc">
                <?php echo getData($config['about_page'], 'sDescriptionShort'); ?>
                <p class="mt-3">
                    <a href="<?php echo getUrl($config['about_page']); ?>" class="link_underline">
                        <?php echo $lang['read_more']; ?>
                    </a>
                </p>
            </div>

            <div class="aboutUs__gallery negativeMargin showUp">
                <?php echo $oFile->listImages($config['about_page'], [
                    'iType'      => 1,
                    'slider'     => true,
                    'full_image' => true,
                    'parallax'   => true,
                ]); ?>
            </div>

            <div class="aboutUs__contact">
                <header class="section__header showUp">
                    <h3 class="section__title">
                        <?php echo getData($config['contact_page'], 'sDesc'); ?>
                    </h3>
                </header>
            </div>
        </div>
    </div>
</section>
 <?php endif; ?>
 
<?php if($config['offer_page']): ?>
<section class="section ourOffer">
    <div class="section__scroll" id="section-ourOffer"></div>
    <div class="container">
        <header class="section__header showUp">
            <div class="section__subtitle">
                <a href="<?php echo getUrl($config['offer_page']); ?>">
                    <?php echo getData($config['offer_page'], 'sName'); ?>
                </a>
            </div>
            <h2 class="section__title text-glow">
                <?php echo getData($config['offer_page'], 'sDesc'); ?>
            </h2>
        </header>
        <div class="ourOffer__content">
            <?php echo $oPage->listPages($config['offer_page'], [
                'class' => 'ourOffer__list',
                'icon'  => true,
            ]); ?>
        </div>
    </div>
</section>
<?php endif; ?>


<?php if(feature('portfolio')): ?>
<section class="section ourProjects">
    <div class="section__scroll" id="section-ourProjects"></div>
    <div class="container">
        <header class="section__header showUp">
            <div class="row">
                <div class="col-6">
                    <div class="section__subtitle">
                        <a href="<?php echo getUrl($config['projects_page']); ?>">
                            <?php echo getData($config['projects_page'], 'sName'); ?>
                        </a>
                    </div>
                    <h2 class="section__title">
                        <?php echo getData($config['projects_page'], 'sDesc'); ?>
                    </h2>
                </div>
                <div class="col-6">
                    <?php echo renderFilterBar('portfolio', getUrl($config['projects_page']), ['page']); ?>
                </div>
            </div>
        </header>
        <div class="ourProjects__content">
            <?php echo $oPage->listPages($config['projects_page'], []); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if($config['faq_page']): ?>
<section class="section faq">
    <div class="container">
        <div class="row">
            <div class="col-4">
                <header class="section__header showUp">
                    <div class="section__subtitle">
                        <a href="<?php echo getUrl($config['faq_page']); ?>">
                            <?php echo getData($config['faq_page'], 'sName'); ?>
                        </a>
                    </div>
                    <h2 class="section__title text-glow">
                        <?php echo getData($config['faq_page'], 'sDesc'); ?>
                    </h2>
                </header>
            </div>
            <div class="col-8">
                <div class="faq__content">
                    <?php echo $oPage->listFaq($config['faq_page'], ['bNoLinks' => true]); ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section cta">
    <div class="section__scroll" id="section-cta"></div>
    <div class="cta__wrapper">
        <header class="section__header showUp">
            <div class="section__subtitle">
                <a href="<?php echo getUrl($config['contact_page']); ?>">
                    <?php echo getData($config['offer_page'],'sName'); ?>
                </a>
            </div>
            <h2 class="section__title">
                <?php echo getData($config['offer_page'],'sDesc'); ?>
            </h2>
        </header>
        <div class="cta__desc">
            <?php echo getData($config['offer_page'],'sDescriptionShort'); ?>
        </div>
        <div class="cta__more">
            <a href="<?php echo getUrl($config['contact_page']); ?>" class="button">
                <?php echo getData($config['contact_page'], 'sName'); ?>
            </a>
        </div>
    </div>
</section>



<?php if(FALSE): ?>
<section class="section clients">
    <div class="section__scroll" id="section-clientsLogo"></div>
    <div class="container">
        <header class="section__header showUp">
            <div class="section__subtitle">
                <a href="<?php echo getUrl($config['projects_page']); ?>">
                    <?php echo getData($config['projects_page'],'sName'); ?>
                </a>
            </div>
            <h2 class="section__title text-glow">
                <?php echo getData($config['projects_page'],'sDesc'); ?>
            </h2>
        </header>
        <div class="clientsLogo__content">
            <?php echo $oFile->listImages($config['projects_page'], [
                'iType' => 2,
                'class' => 'galleryLogo',
            ]); ?>
        </div>
    </div>
</section>
<?php endif; ?>

 

<?php if(feature('shop')): ?>
<div class="section shopIndex">
    <div class="section__scroll" id="section-shopIndex"></div>
    <div class="container">
        <header class="section__header showUp">
            <div class="section__subtitle">
                <a href="<?php echo getUrl($config['shop_page']); ?>">
                    <?php echo getData($config['shop_page'], 'sName'); ?>
                </a>
            </div>
            <h2 class="section__title text-glow">
                <?php echo getData($config['shop_page'], 'sDesc'); ?>
            </h2>
            <a href="<?php echo getUrl($config['shop_page']); ?>"
               class="section__header_button link_underline"
               title="<?php echo $lang['see_all_products']; ?>">
                <?php echo $lang['see_all_products']; ?>
            </a>
        </header>

        <div class="shopIndex__content negativeMargin showUp">
            <?php echo listPagesQuery([
                'sql'      => 'iPageParent!=0 AND iMenu=2',
                'hide_cat' => true,
                'per_page' => 12,
    
                'pagination' => false,
                'class'    => 'productsList owl-carousel shopIndex__products',
            ]); ?>
        </div>
    </div>
</div>
<script>
jQuery(function($) {
    if ($('.shopIndex__products').length) {
        $('.shopIndex__products').owlCarousel({
            loop: false,
            margin: 20,
            nav: false,
            dots: true,
            autoplay: false,
            autoplayHoverPause: true,
            autoplayTimeout: 50000,
            stagePadding: 40,
            responsive : {
                0 : {
                    items: 1
                },
                578 : {
                    items: 2,
                    stagePadding: 0
                },
                768 : {
                    items: 3,
                    stagePadding: 0
                },
                992 : {
                    items: 4,
                    nav: true,
                    stagePadding: 0
                }
            }
        });
    }
});
</script>
<?php endif; ?>



<?php if(feature('blog')): ?>
<hr class="separator showUp my-3">
<div class="section blogIndex">
    <div class="section__scroll" id="section-blogIndex"></div>
    <div class="container">
        <header class="section__header showUp">
            <div class="section__subtitle">
                <a href="<?php echo getUrl($config['blog_page']); ?>">
                    <?php echo getData($config['blog_page'], 'sName'); ?>
                </a>
            </div>
            <h2 class="section__title text-glow">
                <?php echo getData($config['blog_page'], 'sDesc'); ?>
            </h2>
            <a href="<?php echo getUrl($config['blog_page']); ?>"
               class="section__header_button link_underline"
               title="<?php echo $lang['see_all_blog']; ?>">
                <?php echo $lang['see_all_blog']; ?>
            </a>
        </header>

        <div class="blogIndex__content negativeMargin showUp">
            <?php echo $oPage->listPages($config['blog_page'], [
                'hide_cat' => false,
                'limit'    => 9,
                'class'    => 'blogIndex__list owl-carousel',
            ]); ?>
        </div>
    </div>
</div>
<script>
jQuery(function($) {
    if ($('.blogIndex__list').length) {
        $('.blogIndex__list').owlCarousel({
            loop: false,
            margin: 20,
            nav: false,
            dots: true,
            autoplay: false,
            autoplayHoverPause: true,
            autoplayTimeout: 50000,
            stagePadding: 40,
            responsive : {
                0 : {
                    items: 1
                },
                1200 : {
                    items: 2,
                    nav: true,
                    stagePadding: 0
                }
            }
        });
    }
});
</script>
<?php endif; ?>



<?php
// SEKCJA INSTAGRAM — moduł InstaFeed. Renderuje się tylko, gdy istnieje feed
// dla konta wskazanego w $config['instagram_account'] (pusty klucz / brak cache
// => sekcja znika, żeby nie zostawiać pustego bloku).
$instaAccount = $config['instagram_account'] ?? '';
$instaFeedHtml = $instaAccount !== '' ? renderInstaFeed($instaAccount, ['limit' => 15, 'columns' => 4]) : '';
if ($instaFeedHtml !== ''):
?>
<section class="section instagramSection">
    <div class="section__scroll" id="section-instagram"></div>
    <div class="container">
        <header class="section__header">
            <div class="section__subtitle">
                <a href="<?php echo html($config['instagram']); ?>" target="_blank" rel="noopener">
                    <?php echo $lang['instagram_subtitle']; ?>
                </a>
            </div>
            <h2 class="section__title text-glow"><?php echo $lang['instagram_title']; ?></h2>
        </header>

        <div class="instagramSection__feed">
            <?php echo $instaFeedHtml; ?>
        </div>
    </div>
</section>
<?php endif; ?>



<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "url": "<?php echo BASE_URL; ?>",
  "name": "<?php echo $config['logo']; ?>",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "<?php echo getUrl($config['search_page']); ?>?q={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
</script>

<?php require_once $theme.'_footer.php'; ?>
