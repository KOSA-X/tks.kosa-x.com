<?php 
if( !defined( 'ADMIN_PAGE' ) )
  exit( 'Script by OpenSolution.org' );

if( isset( $_POST['status'] ) ){
    
    $oSql->query( 'UPDATE orders SET status="'.$_POST['status'].'" WHERE id = "'.$_POST['id'].'"' );
         
     
     
    header( 'Location: '.$config['admin_file'].'?p=orders&sOption=save&id='.$id );
  exit;
}

if( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ){
  $aData = $oPage->throwOrderAdmin( $_GET['id'] );
}

$sSelectedMenu = 'orders';
 
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>


  <form action="?p=<?php echo $_GET['p']; ?><?php if( isset( $aData['id'] ) ) echo '&id='.$aData['id']; ?>" name="form" method="post" class="main-form">
  
  <input type="hidden" name="id" id="id" value="<?php if( isset( $aData['id'] ) ) echo $aData['id']; ?>" />
  

  <header class="mainPage__header">
  
  <div class="pagesTree">
      <ol class="pagesTree__list">
          <li><a href="?p=orders">Lista zamówień</a></li>
          <li>Informacje o zamówieniu</li>
        </ol>
</div>
  
  
   <h1 class="mainPage__title">Zamówienie <?php echo $aData['id']; ?></h1>
   
   <div class="mainPage__buttons d-flex ">
     
     <input type="submit" name="sOption" class="button button" value="<?php echo $lang['save']; ?>" />
    
      
         
      
 
   </div>
   
 
   
</header>



 
 
  <?php if( isset( $config['manual_link'] ) ){
    echo '<div class="manual"><a href="'.$config['manual_link'].'instruction#pages-form" title="'.$lang['Help'].'" target="_blank"></a></div>';
  }
  if( isset( $_POST['status'] ) ){
    echo '<div class="alert alert-info">'.$lang['Operation_completed'].'</div>';
  }?>
  


    
      
 
      
      

      <ul class="tabs">
        <!-- tabs start -->
        <li id="content" class="selected"><a href="#content">Informacje</a></li>
        <li id="edit"><a href="#">Edytuj zamówienie</a></li>
        <!-- tabs end -->
      </ul>

     <script>
      var aLoginAjax = {};
      $(function(){
        displayTabInit();
        $( ".main-form" ).quickform();
      });
      </script>
      
      <div id="tab-content" class="forms full tabsContent">
         
         
         <div class="form-item">
          <label for="status">Zmień status</label>
          <select name="status" id="status"><?php echo getSelectFromArray( $config['orders_status'], isset( $aData['status'] ) ? $aData['status'] : 0 ); ?></select>
            </div>
            
            
          <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
            <thead>
              <tr>
                <th>Nazwa</th>
                <th>Wartość</th>

              </tr>
            </thead>
            <tbody>
               <tr>
                   <td>ID</td>
                   <td><?php echo $aData['id']; ?></td>
               </tr>
               <tr>
                   <td>Status</td>
                   <td><?php echo orderStatus($aData['status']); ?></td>
               </tr>
               <tr>
                   <td>Imię i nazwisko</td>
                   <td><?php echo $aData['name']; ?></td>
               </tr>
               <tr>
                   <td>E-mail</td>
                   <td><?php echo $aData['email']; ?></td>
               </tr>
               <tr>
                   <td>Telefon</td>
                   <td><?php echo $aData['phone']; ?></td>
               </tr>
               <tr>
                   <td>Adres</td>
                   <td><?php echo $aData['street']; ?><br><?php echo $aData['city']; ?> <?php echo $aData['code']; ?></td>
               </tr>
               <tr>
                   <td>NIP</td>
                   <td><?php echo $aData['nip']; ?></td>
                </tr>
                <tr>
                   <td>Data</td>
                   <td><?php echo gmdate("d.m.Y, H:i", $aData['date']); ?></td>
               </tr>
               
                <tr>
                   <td>Produkty</td>
                   <td><?php echo orderProducts($aData['products']); ?></td>
               </tr>
                  <tr>
                   <td>Płatność</td>
                   <td><?php echo orderPayment($aData['payment']); ?></td>
               </tr>
                   <tr>
                   <td>Dostawa</td>
                   <td><?php echo orderDelivery($aData['payment']); ?></td>
               </tr>
                  
                <tr>
                   <td>Do zapłaty</td>
                   <td><?php echo $aData['price']; ?> PLN</td>
               </tr>
                <tr>
                   <td>Wiadomość</td>
                   <td><?php echo $aData['message']; ?></td>
               </tr>
            </tbody>
          </table>

       
         
        
        
 
         
        <!-- tab content -->
      </div>

      <ul id="tab-edit" class="forms list tabsContent">
      
        <!-- tab options -->
      </ul>

     
       
   


  </form>

<script>
  // files tabs variables
  var sTypesSelect = '<?php echo getSelectFromArray( $config['images_locations'], $config['default_image_location'] ); ?>',
      sSizeSelect = '<?php echo getThumbnailsSelect( $config['default_image_size'] ); ?>';
  $(function(){
    // file uploader
    var uploader = new qq.FileUploader({
      element: document.getElementById('fileUploader'),
      action: aQuick['sPhpSelf']+'?p=ajax-files-upload',
      inputName: 'sFileName',
      uploadButtonText: '<?php echo addslashes( $lang['Files_from_computer'] ); ?>',
      cancelButtonText: '<?php echo addslashes( $lang['Cancel'] ); ?>',
      failUploadText: '<?php echo addslashes( $lang['Upload_failed'] ); ?>',
      <?php echo ( isset( $config['enable_files_uploader_dropzone'] ) ? 'hideDropzones: false,' : null ); ?>
      dragText: '<?php echo addslashes( $lang['Drop_files_to_upload'] ); ?>',
      onComplete: function(id, fileName, response){
        if (!response.success){
          return;
        }
        if( uploader.getInProgress() == 0 )
          refreshFiles( );
        if( response.size_info ){
          qq.addClass(uploader._getItemByFileId(id), 'qq-upload-maxdimension');
          uploader._getItemByFileId(id).innerHTML += '<span class="qq-upload-warning"><?php echo addslashes( $lang['Image_over_max_dimension'] ); ?></span>';
        }
      }
    });

    displayTabInit( checkPagesTab );
    checkPageParent(); // hide menu field for subpages
    $( '#iPageParent' ).on( 'change', checkPageParent );
    checkChangedFile( ); // was there any changes in files tab
    $( ".main-form" ).quickform();

    // Hide/show links
    $( '#tab-content li.short-description label a.expand' ).click( function(){ displayShortDescriptionField( true ) } );
    <?php
    if( !empty( $aData['sDescriptionShort'] ) ){
      echo 'displayShortDescriptionField( false );';
    }
    ?>

    filesFromServerEvents( );

  });
</script>
<?php
require_once 'templates/admin/_footer.php';
?>
