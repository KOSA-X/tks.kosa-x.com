<?php
    if( isset( $aData['sName'] ) ){
        $imageBackground = "";
        $imageBackground = $oFile->getDefaultImageUrl( $aData['iPage'], Array( 'iType' => 4, 'full_image' => TRUE, 'bNoLinks' => TRUE) );
?>
<div class="mainPage<?php echo $aData['sPanel'] == 0 ? "" : " mainPage-column"; ?>" >

<header class="mainPage__header<?php echo ($imageBackground != '' ? ' mainPage__header-bg' : '' ); ?>" <?php echo ($imageBackground != '' ? 'style="background-image:url('.$imageBackground.')"' : '' ); ?> >
    <div class="container">
       <?php echo $aData['iPageParent'] != 0 ? '<div class="pagesTree"><ol class="pagesTree__list">'.(isset($aData['sPagesTree']) ? $aData['sPagesTree'] : '<li><a href="'.$oPage->aPages[$pageId]['sLinkName'].'">'.$aData['sName'].'</a></li>'  ).'</ol></div>' : ''; ?>
       <?php // echo $aData['iPageParent'] != 0 ? '<div class="pagesTree"><a href="'.$oPage->aPages[$aData['iPageParent']]['sLinkName'].'" class="link_underline">'.$oPage->aPages[$aData['iPageParent']]['sName'].'</a></div>' : '' ?>
       <?php echo "<h1 class='mainPage__title '>".(isset($aData['sTitle']) && $aData['sTitle'] != "" ? $aData['sTitle'] : $aData['sName'])."</h1>"; ?>
       <?php echo (isset($aData['sDesc']) && $aData['sDesc'] != "" ? '<h2 class="mainPage__subtitle">'.$aData['sDesc'].'</h2>' : ''); ?>
    </div>
</header>


<?php } ?>