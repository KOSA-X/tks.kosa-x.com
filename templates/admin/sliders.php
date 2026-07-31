<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

require_once 'core/sliders-admin.php';

if( $_SERVER['REQUEST_METHOD'] === 'POST' ){
    saveSliders( $_POST );

    $redirect = str_replace( '&amp;', '&', $_SERVER['REQUEST_URI'] );
    if( !strstr( $redirect, 'sOption=' ) ){
        $redirect .= ( strstr( $redirect, '?' ) ? '&' : '?' ) . 'sOption=';
    }

    header( 'Location: ' . $redirect );
    exit;
}

if( isset( $_GET['iItemDelete'] ) && is_numeric( $_GET['iItemDelete'] ) ){
    deleteSlider( $_GET['iItemDelete'] );
    header( 'Location: '.$config['admin_file'].'?p=sliders&sOption=del' );
    exit;
}

$sSelectedMenu = 'sliders';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>

<form action="?p=sliders" name="form" method="post" class="main-form">

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title"><?php echo $lang['Sliders']; ?></h1>

        <div class="mainPage__buttons d-flex justify-content-between">
            <input type="submit" name="sOption" class="button" value="<?php echo $lang['save']; ?>" />
        </div>
    </header>

    <?php
    if( isset( $config['manual_link'] ) ){
        echo '<div class="manual"><a href="'.$config['manual_link'].'instruction#sliders" title="'.$lang['Help'].'" target="_blank"></a></div>';
    }
    if( isset( $_GET['sOption'] ) ){
        echo '<div class="alert alert-success">'.$lang['Operation_completed'].'</div>';
    }

    $sSlidersList = listSlidersAdmin( );

    if( !empty( $sSlidersList ) ){
    ?>
        <div class="table-responsive">
            <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                <thead>
                    <tr>
                        <th class="image-description"><?php echo $lang['Image']; ?></th>
                        <th class="position"><?php echo $lang['Position']; ?></th>
                        <th class="options">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $sSlidersList; ?>
                </tbody>
            </table>
        </div>
    <?php
    } else {
        echo '<div class="alert alert-warning">'.$lang['Data_not_found'].'</div>';
    }
    ?>

</form>

<?php
require_once 'templates/admin/_footer.php';
?>
