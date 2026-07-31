<?php 
if( !defined( 'ADMIN_PAGE' ) )
  exit( 'Script by OpenSolution.org' );

if( isset( $_POST['sOption'] ) ){
  $oPage->savePages( $_POST );
  header( 'Location: '.str_replace( '&amp;', '&', $_SERVER['REQUEST_URI'] ).( strstr( $_SERVER['REQUEST_URI'], 'sOption=' ) ? null : '&sOption=' ) );
  exit;
}

if( isset( $_GET['iItemDelete'] ) && is_numeric( $_GET['iItemDelete'] ) ){
  $oPage->deletePage( $_GET['iItemDelete'] );
  header( 'Location: '.$config['admin_file'].'?p=pages&sOption=del' );
  exit;
}

$sSelectedMenu = 'shop';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>



 

 
  
<!--
  <form action="#" method="get" class="search box ext-feature" onsubmit="$('.ext-info a').trigger('click');return false;">
    <fieldset>
      <label for="sSearch"><?php echo $lang['search']; ?></label> <input type="text" name="" id="sSearch" class="search" value="" size="50" onclick="$('.ext-info a').trigger('click');" tabindex="-1" />
      <input type="submit" value="<?php echo $lang['search']; ?> &raquo;" tabindex="-1" />
      <span class="ext-info"><a href="#" class="quickbox" data-quickbox-msg="ext-features">&nbsp;</a></span>
    </fieldset>
  </form>
-->
  <?php

    $sPagesList = null;
    $sPagesList .= $oPage->listPagesAdmin( Array( 'iMenu' => 2, 'shop' => TRUE ) );


  if( !empty( $sPagesList ) ){
  ?>
  
   <form action="?p=pages<?php if( isset( $_GET['sSort'] ) ) echo '&amp;sSort='.$_GET['sSort']; ?>" name="form" method="post" class="main-form">
       
  <header class="mainPage__header">
   <h1 class="mainPage__title"><a href="./<?php echo $oPage->aLinksIds[$config['shop_page']]; ?>" class="link_underline"><?php echo $lang['Shop']; ?></a></h1>
   
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
            <th class="id"><a href="?p=pages&amp;sSort=id" class="sort"><?php echo $lang['Id']; ?></a></th>
            <th class="name"><a href="?p=pages&amp;sSort=name" class="sort"><?php echo $lang['Name']; ?></a></th>
            <th class="position"><a href="?p=pages" class="sort"><?php echo $lang['Position']; ?></a></th>
            <th class="options">Akcje</th>
            <th class="status"><?php echo $lang['Status']; ?></th>
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
