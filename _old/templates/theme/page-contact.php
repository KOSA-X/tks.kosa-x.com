<?php 
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;
require_once 'templates/'.$config['skin'].'/_header.php';
require_once 'templates/'.$config['skin'].'/_title.php';
           
?>
<div class="container pageContact">
  <div class="mainPage__wrapper">


        <div class="mainPage__content">
           
           <div class="row">
               <div class="col-6">
                  <div class="pageContact__list">
                     <?php echo contacts(); ?>
                     <?php  echo hoursOpen(TRUE); ?>
                     <?php echo socialMedia(); ?>
                    </div>
                    
                    
                    <div class="widget">
                    <div class="widget__content">
                        
                        
                        <?php echo ($aData['sDescriptionShort'] != ""  ? $aData['sDescriptionShort'] : null); ?>
                        
                         
                     </div>
                     </div>
               </div>
               <div class="col-6">
                   <div class="widget">
                       <h5 class="widget__title"><img src="<?php echo ICONS; ?>email.svg" class="invert" alt="Formularz">Formularz kontaktowy</h5>
                       <div class="widget__content">
                           <?php include("plugins/contact-mail.php"); ?>
                       </div>
                   </div>
               </div>
           </div>
           
           
           <?php echo (($aData['sDescriptionFull'] != "" && $aData['sDescriptionFull'] != $aData['sDescriptionShort'] ) ? '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>' : null); ?>
            
             

             <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 1, 'class' => 'galleryList', 'full_image' => TRUE,  'bNoLinks' => TRUE)); ?>

             

             <?php echo $oFile->listFiles( $aData['iPage'] ); ?>
             

         <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 3, 'class' => 'galleryGrid')); ?>

         <?php echo ($aData['sDate'] ? '<div class="mainPage__date">Data publikacji '.$aData['sDate'].'</div>' : null); ?>

       </div>
   </div>
    <?php echo $oPage->listPages($aData['iPage'], array('hide_cat' => TRUE, 'type' => $aData['sType'], 'class' => 'pagesList-'.$aData['iPage'])); ?>   
</div>
 
 
<div class="pageContact__map">
   <div class="widget">
    <h5 class="widget__title"><img src="<?php echo ICONS; ?>location.svg" class="invert" alt="Lokalizacja"><a href="<?php echo $config['maps']; ?>" target="_blank" title="Uruchom nawigację">Nawigacja</a></h5>
    <div class="widget__content"> 
        <?php echo $config['city'] != "" ? ($config['street'] ? $config['street'].", " : "").($config['city'] ? $config['city'] : "") : ""; ?>
    </div>

    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d6246.553308725744!2d23.266477311113597!3d50.7078413396355!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x472367fb1bf93a9b%3A0xce8ec40526750943!2sRoztocza%C5%84skie%20Muzeum!5e0!3m2!1sen!2spl!4v1741101213058!5m2!1sen!2spl" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
</div> 
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo $config['publicKey']; ?>"></script>
<script>
    grecaptcha.ready(function() {
        grecaptcha.execute('<?php echo $config['publicKey']; ?>', {action:'validate_captcha'})
                  .then(function(token) {
            document.getElementById('g-recaptcha-response').value = token;
        });
    });
</script> 
<?php 

require_once 'templates/'.$config['skin'].'/_footer.php'; ?>