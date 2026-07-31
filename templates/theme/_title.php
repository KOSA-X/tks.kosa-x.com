<?php
if (empty($aData['sName'])) {
    return;
}

// tło nagłówka (typ 4)
$imageBackground = $oFile->getDefaultImageUrl(
    (int) $aData['iPage'],
    ['iType' => 4, 'full_image' => true, 'bNoLinks' => true]
);

$hasBg        = !empty($imageBackground);
$headerClass  = 'mainPage__header' . ($hasBg ? ' mainPage__header-bg' : '');
$headerStyle  = $hasBg
    ? ' style="background-image:url(' . $imageBackground . ')"'
    : '';

$pageTitle    = !empty($aData['sTitle']) ? $aData['sTitle'] : $aData['sName'];
$pageSubtitle = $aData['sDesc'] ?? '';

// link do strony nadrzędnej (jeśli istnieje i ma nazwę)
$parentHtml = '';


//if (!empty($aData['iPageParent'])) {
//    $parentId   = (int) $aData['iPageParent'];
//    $parentName = getData($parentId, 'sName');
//    $parentUrl  = getUrl($parentId);
//
//    if (!empty($parentName) && !empty($parentUrl)) {
//        $parentHtml = sprintf(
//            '<div class="mainPage__parent"><a href="%s"><img src="%sarrow.svg" class="invert">%s</a></div>',
//            html($parentUrl),
//            ICONS,
//            html($parentName)
//        );
//    }
//}

if(isset($aData['sPagesTree'])){
    //<li>'.getLink($config['start_page']).'</li>
    $parentHtml = '<div class="mainPage__tree"><ol>'.$aData['sPagesTree'].'</ol></div>';
}




?>


    <header class="<?= $headerClass ?>"<?= $headerStyle ?>>
        <div class="container">
            <?= $parentHtml ?>

            <h1 class="mainPage__title">
                <?= html($pageTitle) ?>
            </h1>

            <?php if ($pageSubtitle !== ''): ?>
                <h2 class="mainPage__subtitle">
                    <?= html($pageSubtitle) ?>
                </h2>
            <?php endif; ?>
        </div>
    </header>
    
    <?php 
    if($aData['iPage'] == $config['projects_page']){ // REALIZACJE
        echo '<div class="container">'.renderFilterBar('portfolio', getUrl($aData['iPage']), ['page']).'</div>';
    }



?>
 
