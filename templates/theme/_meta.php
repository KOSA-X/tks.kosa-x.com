<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

// Nagłówki bezpieczeństwa – tylko jeśli nic jeszcze nie wysłano
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    // Uwaga: CSP wymaga testów, więc zostawiamy zakomentowane:
    // header("Content-Security-Policy: default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval'");
}

// Przygotowanie zmiennych meta z bezpiecznym escapem
$title       = htmlspecialchars($config['title'] ?? '', ENT_QUOTES, 'UTF-8');
$siteName    = htmlspecialchars($config['logo'] ?? '', ENT_QUOTES, 'UTF-8');
$generator   = htmlspecialchars('Quick.Cms v'.($config['version'] ?? ''), ENT_QUOTES, 'UTF-8');
 

// Opis strony – fallback do globalnego description
$metaDesc = $config['description'] ?? '';
if (isset($page_desc) && $page_desc !== '') {
    $metaDesc = $page_desc;
}
$metaDesc = htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8');

// Obrazek dla sociali – fallback do domyślnego
$imageForFacebook = isset($image_for_facebook) && $image_for_facebook !== ''
    ? $image_for_facebook
    : (IMAGES.'default-image.png');

$imageForFacebook = htmlspecialchars($imageForFacebook, ENT_QUOTES, 'UTF-8');

// body atrybuty (id / class) – zakładam, że zaufane z configa
$bodyAttr = $config['body_id_class_name'] ?? '';
?>
<!DOCTYPE HTML>
<html lang="<?= $config['language']; ?>">
<head>
    <meta charset="utf-8">
    <title><?= $title; ?></title>

    <meta name="description" content="<?= $metaDesc; ?>">
    <meta name="generator" content="<?= $generator; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="index,follow">
    <meta name="author" content="kosa-x.com - digital marketing">

    <link id="favicon" rel="shortcut icon" type="image/x-icon" href="<?= FAVICON; ?>">
    <link rel="apple-touch-icon" href="<?= FAVICON; ?>">
    <link rel="canonical" href="<?= CURRENT_URL; ?>">
    <link rel="sitemap" type="application/xml" title="Sitemap" href="sitemap.xml">
    <meta name="theme-color" content="#000">

    <!-- Open Graph / Twitter -->
    <meta property="og:title" content="<?= $title; ?>">
    <meta name="twitter:title" content="<?= $title; ?>">
    <meta property="og:description" content="<?= $metaDesc; ?>">
    <meta name="twitter:description" content="<?= $metaDesc; ?>">
    <meta property="og:url" content="<?= CURRENT_URL; ?>">
    <meta property="og:image" content="<?= $imageForFacebook; ?>">
    <meta name="twitter:image" content="<?= $imageForFacebook; ?>">
    <meta property="og:image:secure_url" content="<?= $imageForFacebook; ?>">
    <meta property="og:site_name" content="<?= $siteName; ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pl_PL">

    <!-- Preconnect -->
    <link rel="preconnect" href="<?= BASE_URL; ?>" crossorigin>

    <!-- CSS -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1/dist/fancybox/fancybox.css">
    <link rel="stylesheet" href="<?= $css_file; ?>" media="all">

    <!-- JS: biblioteki zewnętrzne + skrypt szablonu -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@studio-freight/lenis@1.0.41/dist/lenis.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1/dist/fancybox/fancybox.umd.js"></script>
    <script src="<?= htmlspecialchars(THEME.'js/owl.carousel.min.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?= htmlspecialchars(THEME.'js/scrollReveal.min.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?= $js_file; ?>" defer></script>

    <base href="<?= BASE_URL; ?>">

    <?php if ($config['google-site-verification'] !== ''): ?>
        <meta name="google-site-verification" content="<?= $config['google-site-verification']; ?>">
    <?php endif; ?>

    <?php if ($config['tagmenager'] !== ''): ?>
        <!-- Google Tag Manager -->
        <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?= $config['tagmenager']; ?>');
        </script>
        <!-- End Google Tag Manager -->
    <?php endif; ?>

    <?php if ($config['analytics'] !== ''): ?>
        <!-- Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= $config['analytics']; ?>"></script>
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= $config['analytics']; ?>');
        </script>
    <?php endif; ?>
</head>
<body<?= $bodyAttr; ?>>
<?php if ($config['tagmenager'] !== ''): ?>
    <!-- Google Tag Manager (noscript) -->
    <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id=<?= $config['tagmenager']; ?>"
                height="0" width="0" style="display:none;visibility:hidden"></iframe>
    </noscript>
    <!-- End Google Tag Manager (noscript) -->
<?php endif; ?>
