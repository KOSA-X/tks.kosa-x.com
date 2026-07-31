<?php 
if (!defined('CUSTOMER_PAGE')) {
    exit;
}


require_once $theme.'_header.php';

if (isset($aData['sName'])) {

    require_once $theme.'_title.php';

 
    // ----------------------------------------
    // 2. Główna treść strony (article)
    // ----------------------------------------
    $articleHtml = '';

    // krótki opis
    if (!empty($aData['sDescriptionShort'])) {
        $articleHtml .= '<div class="mainPage__descShort">'.$aData['sDescriptionShort'].'</div>';
    }

    // galeria główna (typ 1)
    $articleHtml .= $oFile->listImages($aData['iPage'], [
        'iType'      => 1,
        'full_image' => true,
        'parallax'   => true,
        'slider'     => true,
        'class'      => 'mainPage negativeMargin'
    ]);

    // pełny opis – tylko jeśli inny niż krótki
    if (!empty($aData['sDescriptionFull']) && $aData['sDescriptionFull'] !== $aData['sDescriptionShort']) {
        $articleHtml .= '<div class="mainPage__descFull">'.$aData['sDescriptionFull'].'</div>';
    }

    // pliki do pobrania
    $articleHtml .= $oFile->listFiles($aData['iPage']);

    // ----------------------------------------
    // 3. Galeria dodatkowa (typ 3)
    // ----------------------------------------
    $galleryGridHtml = $oFile->listImages($aData['iPage'], [
        'iType' => 3,
        'class' => 'galleryGrid'
    ]);

    // ----------------------------------------
    // 4. Data publikacji
    // ----------------------------------------
    $publishDateHtml = '';
    if (!empty($aData['sDate'])) {
        $publishDateHtml = 'Data publikacji '.$aData['sDate'];
    }
    ?>

    <div class="container">
        <div class="mainPage__wrapper">
            <?php require_once $theme.'_column.php'; ?>

            <div class="mainPage__content">
                
                  <h4 class="mb-3">Buttons</h4>
                  <a href="" class="button">Button</a>
                  <a href="" class="button button-light">Button light</a>
                  <a href="" class="button button-dark">Button dark</a>
                  <a href="" class="button button-border">Button border</a>
                  
                   <hr>
                
                  
                   <h4 class="mb-3">Button group</h4>
       
                   <?php echo renderFilterBar('portfolio', getUrl($aData['iPage']), ['page']); ?>
                  
                  <hr>
                  <h4 class="mb-3">Tabs</h4>
                  
                   <div class="tabs ">
  <nav class="tabs__nav">
    <a href="#" data-target="strony-www" class="selected">
      <img src="https://cms.kosa-x.com/images/icons/cart.svg" alt="Strony www">Strony www
    </a>
    <a href="#" data-target="video">
      <img src="https://cms.kosa-x.com/images/icons/user.svg" alt="Video">Video
    </a>
    <a href="#" data-target="social-media">
      <img src="https://cms.kosa-x.com/images/icons/search.svg" alt="Social media">Social media
    </a>
  </nav>

  <div class="tabs__content">
    <div class="tabs__item" id="tabs-strony-www">Strony www content</div>
    <div class="tabs__item" id="tabs-video">
      
      <div class="mb-3">
        video content
        </div>
                    
                    </div>
    <div class="tabs__item" id="tabs-social-media">Social media content</div>
  </div>
</div>
                   
                  
<hr>
                  <h4 class="mb-3">Alerty</h4>
                  <div class="alert alert-info">Alert info</div>
                  <div class="alert alert-danger">Alert danger</div>
                  <div class="alert alert-success">Alert success</div>
                  <div class="alert alert-warning">Alert warning</div>
                   
                   
                    <hr>
                  <h4 class="mb-3">Card</h4>
                  
                  <div class="card">
                      <header class="card__header">
                          <h4 class="card__title">
                              <img src="<?php echo ICONS; ?>shop.svg" alt="Ikona" class="">
                          Card</h4>
                      </header>
                      <div class="card__content">
                          Zawartość
                      </div>
                      <ul class="card__list">
                          <li>Element 1</li>
                          <li>Element 2</li>
                          <li>Element 3</li>
                      </ul>
                      <footer class="card__footer">
                          Footer
                      </footer>
                      
                  </div>
                
                

                <?php if ($articleHtml !== ''): ?>
                    <article class="mainPage__article negativeMargin">
                        <?php echo $articleHtml; ?>
                    </article>
                <?php endif; ?>

                <?php echo $galleryGridHtml; ?>

                <?php if ($publishDateHtml !== ''): ?>
                    <div class="mainPage__date">
                        <?php echo $publishDateHtml; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <?php
        echo $oPage->listPages($aData['iPage'], [
            'type'   => $aData['sType'] ?? null,
            'footer' => true
        ]);
        ?>
    </div>

    <?php

} else {
    require_once $theme.'_404.php';
}

require_once $theme.'_footer.php';
?>
