<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

require_once 'core/sliders-admin.php';

if( $_SERVER['REQUEST_METHOD'] === 'POST' ){
    $iSlider = saveSlider( $_POST );

    if( isset( $_POST['sOptionList'] ) ){
        header( 'Location: '.$config['admin_file'].'?p=sliders&sOption=save' );
    }
    elseif( isset( $_POST['sOptionAddNew'] ) ){
        header( 'Location: '.$config['admin_file'].'?p=sliders-form&sOption=save' );
    }
    else{
        header( 'Location: '.$config['admin_file'].'?p=sliders-form&sOption=save&iSlider='.$iSlider );
    }
    exit;
}

if( isset( $_GET['iSlider'] ) && is_numeric( $_GET['iSlider'] ) ){
    $aData = throwSlider( $_GET['iSlider'] );
}

$sSelectedMenu = 'sliders';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>

<form action="?p=<?php echo html( $_GET['p'] ); ?>" enctype="multipart/form-data" name="form" method="post" class="main-form no-tabs">

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title">
            <?php echo ( isset( $aData['iSlider'] ) ) ? $lang['Sliders_form'] : $lang['New_slider']; ?>
        </h1>

        <div class="mainPage__buttons">
            <a href="?p=sliders" class="button button-light mr-2">Wróc do listy</a>
            <input type="submit" name="sOption" class="button mr-1" value="<?php echo $lang['save']; ?>" />
        </div>
    </header>

    <?php
    if( isset( $config['manual_link'] ) ){
        echo '<div class="manual"><a href="'.$config['manual_link'].'instruction#sliders-form" title="'.$lang['Help'].'" target="_blank"></a></div>';
    }
    if( isset( $_GET['sOption'] ) ){
        echo '<div class="alert alert-success">'.$lang['Operation_completed'].'</div>';
    }
    ?>

    <input type="hidden" name="iSlider" value="<?php echo isset( $aData['iSlider'] ) ? (int) $aData['iSlider'] : ''; ?>" />

    <ul id="tab-content" class="forms list tabsContent">

        <?php if( !empty( $aData['iSlider'] ) ){ ?>
            <li>
                <div class="form-item">
                    <label><?php echo $lang['Image']; ?></label>
                    <select name="sFileName" class="file-from-server">
                        <?php echo listSlidersFilesAdmin( ( !empty( $aData['sFileName'] ) ? $aData['sFileName'] : null ) ); ?>
                    </select>
                </div>
            </li>
        <?php } else { ?>
            <li class="slider-file">
                <div class="form-item">
                    <label for="sFileName"><?php echo $lang['Image']; ?></label>

                    <div class="file-server extended">
                        <div>
                            <input
                                type="file"
                                name="aFile"
                                id="sFileName"
                                data-form-check="ext;<?php echo $config['allowed_image_extensions']; ?>"
                                data-form-if="true"
                            />
                            <span class="ext"><?php echo str_replace( '|', ' | ', $config['allowed_image_extensions'] ); ?></span>
                        </div>

                        <div class="adv-file-server adv-field mt-2">
                            <select name="sFileNameOnServer" id="sFileNameOnServer" class="file-from-server">
                                <option value=""><?php echo $lang['or_choose_from_server']; ?></option>
                                <?php echo listSlidersFilesAdmin( ); ?>
                            </select>
                        </div>
                    </div>
                </div>
            </li>
        <?php } ?>

        <li>
            <div class="form-item">
                <label for="sTitle"><?php echo $lang['Name']; ?></label>
                <input
                    type="text"
                    class="form-control"
                    id="sTitle"
                    name="sTitle"
                    value="<?php echo isset( $aData['sTitle'] ) ? html( $aData['sTitle'] ) : ''; ?>"
                >
            </div>
        </li>

        <li class="short-description">
            <div class="form-item">
                <label for="sDescription"><?php echo $lang['Description']; ?></label>
                <div class="description">
                    <?php echo getTextarea( 'sDescription', isset( $aData['sDescription'] ) ? $aData['sDescription'] : null ); ?>
                </div>
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="iPosition"><?php echo $lang['Position']; ?></label>
                <input
                    type="text"
                    id="iPosition"
                    name="iPosition"
                    value="<?php echo isset( $aData['iPosition'] ) ? (int) $aData['iPosition'] : 0; ?>"
                    class="numeric"
                    size="3"
                    maxlength="4"
                />
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="sUrl">Link</label>
                <input
                    type="text"
                    id="sUrl"
                    name="sUrl"
                    value="<?php echo isset( $aData['sUrl'] ) ? html( $aData['sUrl'] ) : ''; ?>"
                />
            </div>
        </li>

    </ul>

    <div class="mainPage__buttons d-flex justify-content-between mt-3">
        <input type="submit" name="sOption" class="button mr-1" value="<?php echo $lang['save']; ?>" />
        <input type="submit" value="<?php echo $lang['save_list']; ?>" name="sOptionList" class="button button-border" />
        <input type="submit" value="<?php echo $lang['save_add_new']; ?>" class="ml-2 button button-border" name="sOptionAddNew" />

        <?php if( isset( $aData['iSlider'] ) ){ ?>
            <a
                href="?p=sliders&amp;iItemDelete=<?php echo (int) $aData['iSlider']; ?>"
                title="<?php echo $lang['Delete']; ?>"
                onclick="return del( this );"
                class="button ml-auto button-danger"
            >
                <?php echo $lang['Delete']; ?>
            </a>
        <?php } ?>
    </div>

</form>

<script>
$(function () {
    var $form = $('.main-form');

    if (!$form.length) {
        return;
    }

    // Nowy slider: walidujemy wymagania dot. pliku (checkSliderFields)
    if ($form.find('.slider-file').length) {
        $form.quickform({ oCallbackBefore: checkSliderFields });
    } else {
        $form.quickform();
    }
});
</script>

<?php
require_once 'templates/admin/_footer.php';
?>
