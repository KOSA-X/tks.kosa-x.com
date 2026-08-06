<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

// Bezpieczne ID bieżącej strony
$currentPageId = isset($aData['iPage'])
    ? (int)$aData['iPage']
    : (int)($config['current_page_id'] ?? 0);

require_once 'templates/'.$config['skin'].'/_meta.php';
update_views($currentPageId);
    
?>

<header class="mainHeader">
    
    <div class="mainHeader__center">
        <div class="container">
            <div class="mainHeader__logo">
                <a href="<?php echo BASE_URL; ?>">
                    <?php echo LOGO; ?>
                </a>
            </div>
            <?php if (feature('shop')) echo cartIcon(); ?>
            <?php if (feature('users')) echo userIcon(); ?>
            <div class="mainHeader__menu_button">
                <div class="menuHamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <div class="mainHeader__menu">
                <?php
                    echo $oPage->listPagesMenu(1, [
                        'sClassName'  => 'headerMenu',
                        'bExpanded'   => true,
                        'iDepthLimit' => 1,
                        'scroll'      => ($currentPageId === 1),
                    ]);
                    echo contactsButtons();
                ?>
                <div class="flex-justify mainHeader__icons">
                    <?php echo socialMedia(['contacts' => false]); ?>
                </div>
            </div>
        </div>
    </div>
</header>


<?php
// =======================
// SEKCJA WIDEO (video_page)
// =======================
if (!empty($config['video_page']) && $currentPageId === (int)$config['video_page']):

    $introVideoPath = __DIR__.'/../../images/intro-video.mp4'; // dopasuj jeśli trzeba
    $introVideoVer  = file_exists($introVideoPath) ? filemtime($introVideoPath) : time();
    ?>
    <section class="videoHeader">
        <video
            class="videoHeader__background"
            autoplay
            loop
            muted="muted"
            poster="<?php echo IMAGES; ?>intro-video.webp"
            playsinline
        >
            <source src="<?php echo IMAGES; ?>intro-video.mp4?ver=<?php echo $introVideoVer; ?>" type="video/mp4">
            Twoja przeglądarka nie obsługuje elementu wideo.
        </video>

        <div class="videoHeader__content showUp">
            <div class="contentParallax">
                <h1 class="mainSlider__title"><?php echo $config['slogan'] ?? ''; ?></h1>
                <div class="mainSlider__desc"><?php echo $config['description'] ?? ''; ?></div>
                <a
                    href="<?php echo IMAGES; ?>avit-intro.mp4?ver=2"
                    class="videoHeader__button button mainSlider__button button-white"
                    data-fancybox
                >
                    <img src="<?php echo ICONS; ?>play.svg" alt="Odtwórz">
                    Zobacz film
                </a>
            </div>
        </div>
        <a
            class="videoHeader__arrow scrollTo scroll-to"
            href="#"
            data-id="aboutUs"
        >
            <img src="<?php echo ICONS; ?>arrow-long.svg" alt="Przeglądaj dalej">
        </a>
    </section>
<?php
endif;

// =======================
// SLIDER (slider_page)
// =======================
if (!empty($config['slider_page']) && $currentPageId === (int)$config['slider_page'] && isset($oSlider)): ?>
    <script>
    jQuery(function($) {
    var $slider = $('.mainSlider__list');
    var $wrapper = $('.mainSlider');

    $slider.owlCarousel({
        loop: true,
        margin: 0,
        nav: false,
        dots: true,
        autoplay: true,
        autoplayHoverPause: true,
        autoplayTimeout: 5000,
        animateOut: 'fadeOut',
        items: 1,
        onTranslated: restartDotProgress
    });

    // Restart animacji CSS na aktywnej kropce po każdej zmianie slajdu
    function restartDotProgress() {
        // requestAnimationFrame daje pewność że OWL skończył manipulować DOM-em
        requestAnimationFrame(function() {
            var el = $('.mainSlider .owl-dot.active span')[0];
            if (!el) return;
            el.style.animation = 'none';
            void el.offsetWidth; // wymuszony reflow
            el.style.animation = '';
        });
    }

    // Hover pauzuje spójnie: progres bar + zoom obrazu
    $wrapper.on('mouseenter', function() {
        $(this).addClass('is-paused');
    }).on('mouseleave', function() {
        $(this).removeClass('is-paused');
    });
});
    </script>
<?php echo $oSlider->listSliders(['sClassName' => 'mainSlider']); ?>
<?php endif; ?>
<main class="mainBody" id="smooth-scroll">
<div class="mainPage">