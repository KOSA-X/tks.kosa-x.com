<?php 
if( !defined( 'ADMIN_PAGE' ) )
  exit( 'Script by OpenSolution.org' );

 
if( isset( $_GET['iItemDelete'] ) && is_numeric( $_GET['iItemDelete'] ) ){
    
   $oSql->query( 'DELETE FROM orders WHERE id="'.$_GET['iItemDelete']);
    
  header( 'Location: '.$config['admin_file'].'?p=orders&sOption=del' );
  exit;
}

$sSelectedMenu = 'orders';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>



 
  <?php

  $sPagesList = null;
  
    $sPagesList = $oPage->listOrdersAdmin( );
   // end foreach

  if( !empty( $sPagesList ) ){
  ?>
  
   <form action="?p=orders<?php if( isset( $_GET['sSort'] ) ) echo '&amp;sSort='.$_GET['sSort']; ?>" name="form" method="post" class="main-form">
       
  <header class="mainPage__header">
   <h1 class="mainPage__title">Zamówienia</h1>
   
   <div class="mainPage__buttons d-flex justify-content-between">
      <a href="?p=pages-form" class="button"><?php echo $lang['New_page']; ?></a>
       <input type="submit" name="sOption" class="button button-border" value="<?php echo $lang['save']; ?> " />
   </div>
   
 
   
</header>

<?php 

  if( isset( $_GET['sOption'] ) ){
    echo '<div class="alert alert-info mb-3">'.$lang['Operation_completed'].'</div>';
  }
 
    
    

   ?>
            
            
            
            
   
       

      <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
        <thead>
          <tr>
            <th>ID</th>
                    <th>Zamawiający</th>
                    <th>Kwota</th>
                    <th>Data</th>
                    <th>Status</th>
                    <th>Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php echo $sPagesList; ?>
        </tbody>
      </table>
      

     
  </form>
  <?php
    }
    else{
      echo '<div class="alert alert-danger">'.$lang['Data_not_found'].'</div>';
    }
  ?>
 
<?php
require_once 'templates/admin/_footer.php';
?>
