<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

/**
 * MENU GŁÓWNE
 */
function listPagesMenuView($aData, $aParametersExt)
{
    $sClassName = '';
    $submenu    = false;
    $arrow      = '';
    $icon       = '';
    $link       = '';
    $icon_url   = '';
    $oFile      = Files::getInstance();

    $show_all = !empty($aParametersExt['show_all']);
    $sSubMenu = $aParametersExt['sSubMenu'] ?? '';

    // ikona w menu
    $icon_url = $oFile->getDefaultImageUrl($aData['iPage'], [
        'iType'   => 2,
        'sLink'   => (!isset($aParametersExt['bNoLinks']) ? $aData['sLinkName'] : null),
        'bNoLinks'=> isset($aParametersExt['bNoLinks']) ? true : null
    ]);

    $safeName = html($aData['sName']);
    $safeLink = html($aData['sLinkName']);

    if ($icon_url) {
        $icon = '<img src="' . html($icon_url) . '" alt="' . $safeName . '" class="menu_icon invert" loading="lazy">';
    }

    if (!empty($aParametersExt['bSelected'])) {
        $sClassName .= ' selected';
    }

    if ((int)$aData['sExpandMenu'] === 0 && !$show_all) {
        $sSubMenu = '';
    }

    if ($sSubMenu) {
        $sClassName .= ' menu_item_submenu';
        $arrow = '<span class="menu_arrow invert" data-menu="' . (int)$aData['iPage'] . '"></span>';
    }

    if ((int)$aData['iPageParent'] > 0) {
        $submenu = true;
    }

    return '<li class="' . ($submenu ? 'submenu_item' : 'menu_item') . $sClassName . '" id="menu_item_' . (int)$aData['iPage'] . '">'
         . $icon
         . '<a href="' . $safeLink . '" class="' . ($submenu ? 'submenu_link' : 'menu_link') . '" title="' . $safeName . '">' . $safeName . '</a>'
         . $arrow
         . $sSubMenu
         . '</li>';
}

/**
 * LINKI W WIDGETACH (LEWA KOLUMNA)
 */
function listMenuView($aData, $aParametersExt)
{
    $selected = !empty($aParametersExt['selected']);
    $safeName = html($aData['sName']);
    $safeLink = html($aData['sLinkName']);

    return '<a href="' . $safeLink . '" class="widget__link' . ($selected ? ' selected' : '') . '">' . $safeName . '</a>';
}

/**
 * LISTA STRON / PRODUKTÓW
 */
function listPagesView($aData, $aParametersExt)
{
    global $lang, $config;

    $oFile  = Files::getInstance();
    $class  = ' pageItem-' . (int)$aData['iPage'];
    $footer = '';
    $image  = '';
    $price  = '';
    $desc   = '';
    $project_category = '';
    $popup  = !empty($aParametersExt['popup']);
    $horizontal = false;

    $safeName = html($aData['sName']);
    $safeLink = html($aData['sLinkName']);

    // 1. AKTUALNOŚCI – wyświetlane poziomo
    if (!empty($config['blog_page']) && (int)$aData['iPageParent'] === (int)$config['blog_page']) {
        $horizontal = true;
    }

    // 2. KATEGORIA
    if (!empty($aData['iPageParent']) && empty($aParametersExt['hide_cat'])) {
        $parentId   = (int)$aData['iPageParent'];
        $parentName = getData($parentId, 'sName') ?? '';
        $parentUrl  = getUrl($parentId) ?? '#';

        $category = '<div class="category"><a href="' . html($parentUrl) . '">' . html($parentName) . '</a></div>';
    } else {
        $category = '';
    }

    // 3. LINK (popup lub klasyczny)
    if ($popup) {
        $link = '<a href="javascript:;" data-fancybox data-src="#pagePopup-' . (int)$aData['iPage'] . '" title="' . $safeName . '">';
    } else {
        $link = empty($aParametersExt['bNoLinks'])
            ? '<a href="' . $safeLink . '" title="' . $safeName . '">'
            : '';
    }

    // 4. Ikona / zdjęcie
    $icon = (
        (!empty($aParametersExt['icon']) && $aParametersExt['icon'] === true)
        || (!empty($config['offer_page']) && (int)$aData['iPageParent'] === (int)$config['offer_page'])
    );

    $image = $oFile->getDefaultImage($aData['iPage'], [
        'sLink'     => $link,
        'full_size' => $icon
    ]);

    // 5. POPUP – zawartość
    $popup_content = '';
    if ($popup) {
        $class .= ' pageItem-popup';

        $popup_content .= '<div class="popup" style="display:none" id="pagePopup-' . (int)$aData['iPage'] . '">'
                        . '<a href="" style="width:0;border:0;height:0;outline:0"></a>'
                        . '<h3 class="popup__title">' . $safeName . '</h3>';

        if (!empty($aData['sDescriptionShort'])) {
            $popup_content .= '<div class="popup__lead">' . $aData['sDescriptionShort'] . '</div>';
        }

        $popup_content .= '<div class="popup__desc">' . $aData['sDescriptionFull'] . '</div>';
        $popup_content .= $oFile->listImages($aData['iPage'], [
            'iType' => 3,
            'class' => 'galleryGrid'
        ]);
        $popup_content .= '</div>';
    }

    // 6. CENA
    if (!empty($aData['sPrice'])) {
        $price = getPrice($aData['iPage']);
    }

    // 7. OPIS (priorytety jak miałeś)
    if (empty($aParametersExt['hide_desc'])) {
        $maxWords = $popup ? 50 : 30;

        if (!empty($aData['sDesc'])) {
            $desc = '<div class="desc">' . trunc($aData['sDesc'], $maxWords) . '</div>';
        } elseif (!empty($aData['sDescriptionShort'])) {
            $desc = '<div class="desc">' . trunc($aData['sDescriptionShort'], $maxWords) . '</div>';
        } elseif (!empty($aData['sDescriptionFull'])) {
            $desc = '<div class="desc">' . trunc($aData['sDescriptionFull'], $maxWords) . '</div>';
        }
    }

    // 8. FILTRY / CECHY
    // Produkty: tylko cechy wskazane w $config['product_card_filters']
    // (łatwa zmiana zestawu w database/config.php); inne strony — wszystkie.
    $isProduct = ((int)($aData['sType'] ?? 0) === 2) || !empty($price);
    $cardFilterKeys = ($isProduct && !empty($config['product_card_filters']) && is_array($config['product_card_filters']))
        ? $config['product_card_filters']
        : null;
    $filtersBadges = getFilters($aData['sFilter'] ?? null, true, '', $cardFilterKeys);

    // 9. STOPKA
    if (!$popup) {
        $footer = '<footer class="footer">';

        if ($price) {
            $addCartLabel = $lang['add_cart'] ?? 'Do koszyka';

            // format ceny na liście: "od [cena] PLN /szt."
            $footer .= '<div class="priceFrom">'
                     . '<span class="priceFrom__label">'.html($lang['price_from'] ?? 'od').'</span>'
                     . $price
                     . '<span class="priceFrom__unit">'.html($lang['price_unit'] ?? 'szt.').'</span>'
                     . '</div>';
            $footer .= '<button class="add-to-cart button button-light button-xs"'
                     . ' title="Do koszyka"'
                     . ' data-product-id="' . (int)$aData['iPage'] . '"'
                     . ' data-action="add"'
                     . ' data-loading="Dodawanie...">'
                     . '<img src="' . ICONS . 'cart.svg" alt="Koszyk" class="invert">'
                     . html($addCartLabel)
                     . '</button>';
        } else {
            $moreLabel = $lang['more'] ?? 'Zobacz więcej';

            $footer .= '<a href="' . $safeLink . '" title="' . $safeName . '" class="link_underline">'
                     . html($moreLabel)
                     . '</a>';

            if (!empty($aData['sDate'])) {
                $footer .= '<time class="date">' . html($aData['sDate']) . '</time>';
            }
        }

        $footer .= '</footer>';
    }

    // 10. RENDER
    return '
    <li class="pageItem' . $class . '" ' . $project_category . '>
        ' . $image . '
        <div class="content">
            ' . $category . '
            <h3 class="title">' . $link . $safeName . ($link !== '' ? '</a>' : '') . '</h3>
            ' . $filtersBadges . '
            ' . $desc . '
            ' . ($horizontal ? $footer : '') . '
        </div>
        ' . ($horizontal ? '' : $footer) . '
        ' . $popup_content . '
    </li>';
}

/**
 * MINIATURY / GALERIE
 */
function listImagesView($aData, $aParametersExt)
{
    $thumb    = $aData['iSize'] . '/';
    $parallax = (!empty($aParametersExt['parallax']) && $aParametersExt['parallax'] === true)
        ? ' imageParallax'
        : '';

    if (!empty($aParametersExt['full_image'])) {
        $thumb = '';
    }

    $title = (!empty($aData['sTitle']) || !empty($aData['sDescription']))
        ? $aData['sTitle'] . ' ' . $aData['sDescription']
        : 'Zdjęcie ' . $aData['sFileName'];

    $safeTitle = html($title);
    $safeFile  = html($aData['sFileName']);

    $youtube = !empty($aData['sYoutube'])
        ? "data-src='" . html($aData['sYoutube']) . "'"
        : '';

    $isEager = isset($aParametersExt['iElement']) && (int)$aParametersExt['iElement'] <= 3;
    $imgSrc   = $isEager
        ? 'src="' . FILES . $thumb . $safeFile . '"'
        : 'data-src="' . FILES . $thumb . $safeFile . '" src=""';
    $lazyClass = $isEager ? '' : ' lazy';

    $image = '<picture class="galleryItem__image' . $parallax . '">'
           . '<img ' . $imgSrc . ' alt="' . $safeTitle . '" class="' . trim($lazyClass) . '" loading="lazy"/>'
           . '</picture>';

    if (!empty($aData['sUrl'])) {
        $linkAttr = ' href="' . html($aData['sUrl']) . '" target="_blank"';
    } else {
        $caption = (!empty($aData['sTitle']) ? '<h5>' . $aData['sTitle'] . '</h5>' : '') . $aData['sDescription'];
        $linkAttr = 'href="' . FILES . $safeFile . '"'
                  . ' data-fancybox="gallery[' . (isset($aData['iPage']) ? (int)$aData['iPage'] : 0) . ']"'
                  . ' title="' . $safeTitle . '"'
                  . ' data-caption="' . html($caption) . '"';
    }

    $descHtml = !empty($aData['sDescription'])
        ? '<div class="galleryItem__desc">' . $aData['sDescription'] . '</div>'
        : '';

    return '<li class="galleryItem" id="galleryItem-' . (int)$aData['iFile'] . '">'
         . (empty($aParametersExt['bNoLinks'])
            ? '<a ' . $youtube . ' ' . $linkAttr . ' title="' . $safeTitle . '">'
            : ''
           )
         . $image
         . $descHtml
         . (empty($aParametersExt['bNoLinks']) ? '</a>' : '')
         . '</li>';
}

/**
 * LISTA PLIKÓW
 */
function listFilesView($aData, $aParametersExt)
{
    $safeFile = html($aData['sFileName']);
    $safeDesc = !empty($aData['sDescription'])
        ? '<p>' . html($aData['sDescription']) . '</p>'
        : '';

    return '<li class="' . html($aData['sIconStyle']) . '">'
         . '<a href="files/' . $safeFile . '" target="_blank" class="link_underline">' . $safeFile . '</a>'
         . $safeDesc
         . '</li>';
}

/**
 * SLIDER GŁÓWNY
 */
function listSlidersView($aData, $aParametersExt)
{
    global $lang;

    $className = !empty($aParametersExt['sClassName'])
        ? $aParametersExt['sClassName']
        : '';

    $id = ($className ? $className . '__item' : 'slider') . '-' . $aData['iSlider'];

    $bg = '';
    if (!empty($aData['sFileName'])) {
        $bg = '<div class="mainSlider__image" style="background-image:url(\'' . FILES . $aData['sFileName'] . '\')"></div>';
    }

    $titleHtml = '';
    if (!empty($aData['sTitle'])) {
        $safeTitle = html($aData['sTitle']);
        $urlStart  = !empty($aData['sUrl']) ? '<a href="' . html($aData['sUrl']) . '">' : '';
        $urlEnd    = !empty($aData['sUrl']) ? '</a>' : '';
        $titleHtml = '<h2 class="mainSlider__title">' . $urlStart . $safeTitle . $urlEnd . '</h2>';
    }

    $descHtml = !empty($aData['sDescription'])
        ? '<div class="mainSlider__desc">' . $aData['sDescription'] . '</div>'
        : '';

    $moreLabel = $lang['more'] ?? 'Zobacz więcej';

    $btnHtml = '';
    if (!empty($aData['sUrl'])) {
        $btnHtml = '<a href="' . html($aData['sUrl']) . '"'
                 . ' class="mainSlider__button button button-border button-white">'
                 . html($moreLabel)
                 . '</a>';
    }

    return '<li id="' . $id . '" class="' . ($className ? $className . '__item' : '') . '">'
         . $bg
         . '<div class="mainSlider__content"><div class="container">'
         . $titleHtml
         . $descHtml
         . $btnHtml
         . '</div></div>'
         . '</li>';
}


