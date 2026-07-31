<?php 
if( !defined( 'ADMIN_PAGE' ) )
  exit( 'Script by OpenSolution.org' );

require_once 'core/sliders-admin.php';

if( isset( $_POST['sOption'] ) ){
  saveSliders( $_POST );
  header( 'Location: '.str_replace( '&amp;', '&', $_SERVER['REQUEST_URI'] ).( strstr( $_SERVER['REQUEST_URI'], 'sOption=' ) ? null : '&sOption=' ) );
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
       
  <header class="mainPage__header">
   <h1 class="mainPage__title"><?php echo $lang['Sliders']; ?></h1>
   
   <div class="mainPage__buttons d-flex justify-content-between">
      <a href="?p=sliders-form" class="button"><?php echo $lang['New_slider']; ?></a>
       <input type="submit" name="sOption" class="button button-border" value="<?php echo $lang['save']; ?>" />
   </div>
   
 
   
</header>



 
  <?php if( isset( $config['manual_link'] ) ){
    echo '<div class="manual"><a href="'.$config['manual_link'].'instruction#sliders" title="'.$lang['Help'].'" target="_blank"></a></div>';
  }
  if( isset( $_GET['sOption'] ) ){
    echo '<div class="alert alert-info">'.$lang['Operation_completed'].'</div>';
  }?>

  <?php 
  $sSlidersList = listSlidersAdmin( );
  if( isset( $sSlidersList ) ){
  ?>
  
  

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
      
 
 
  </form>
  <?php
    }
    else{
      echo '<div class="alert alert-warning">'.$lang['Data_not_found'].'</div>';
    }
  ?>
 
<?php
require_once 'templates/admin/_footer.php';
?>
