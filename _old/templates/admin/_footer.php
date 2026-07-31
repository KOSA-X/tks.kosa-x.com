<?php
if( !defined( 'ADMIN_PAGE' ) )
  exit;
?>
 </div>
 
<footer class="mainFooter">
    <div class="container">
        <div class="mainFooter__row">
           
          
           
         
            <div class="mainFooter__column">
            <h5 class="mainFooter__title">Pomoc techniczna</h5>
            <p class="mt-2"><strong>konrad@kosiorski.pl</strong></p>
            <p class=""><a href="https://kosa-x.com" target="_blank">www.kosa-x.com</a></p>
            
            </div>
             
             <div class="mainFooter__column">
             <h5 class="mainFooter__title">OpenSolution</h5>
             <!-- Don't delete or hide OpenSolution logo and links to www.OpenSolution.org -->
                  <nav class="footerMenu">
                    <ul class="footerMenu__list">
                        <li class="menu_item"><a href="http://opensolution.org" target="_blank" class="menu_link" >OpenSolution.org</a></li>
                        <li class="menu_item"><a href="http://opensolution.org/?p=support" target="_blank" class="menu_link"><?php echo $lang['Support']; ?></a></li>
                        <li class="menu_item"><a href="<?php echo $config['manual_link']; ?>start" target="_blank" class="menu_link" ><?php echo $lang['Manual']; ?></a></li>
                        <li class="menu_item"><a href="http://opensolution.org/?p=licenses" target="_blank" class="menu_link" ><?php echo $lang['License']; ?></a></li>
                        <li class="menu_item"><a href="?p=plugins"  class="menu_link" ><?php echo $lang['Plugins']; ?></a></li>
                        <li class="menu_item"><a href="?p=languages"  class="submenu_link menu_link" ><?php echo $lang['Languages']; ?></a></li>
                    </ul>
                </nav>
            </div>
                   
              <div class="mainFooter__column">
               <div class="flex-justify">
        
                    <?php echo language(); ?>
                    <?php echo darkMode(); ?>
               </div>
                
                
               
           </div>
        </div>
        <div class="mainFooter__bottom">
            <div class="mainFooter__info">
                <div class="copy"><?php echo $config['foot_info']; ?> <?php echo date("Y"); ?> ©  <a href="<?php echo $oPage->aPages[17]['sLinkName']; ?>" ><?php echo $oPage->aPages[17]['sName']; ?></a> </div>
            </div>
            <div class="mainFooter__author">
                <span class="designer"><a href="https://kosa-x.com/" target="_blank" title="KOSΛ X">KOSΛ X</a></span><span class="cms"><a href="http://opensolution.org/" target="_blank" title="CMS by Quick.CMS">CMS by Quick.CMS</a></span>
            </div>
        </div>
    </div>
</footer>
  
</main>
<?php
if( isset( $_COOKIE['bLicense'.str_replace( '.', '', $config['version'] )] ) && !isset( $_COOKIE['bNoticesDisplayed'] ) && isset( $_SESSION['iMessagesNoticesNumber'] ) && $_SESSION['iMessagesNoticesNumber'] > 0 ){ ?>
  <script>
  $(function(){
    $( '#messages .notices > a:first-child' ).trigger( 'click' );
    createCookie( 'bNoticesDisplayed', 1 );
  });
  </script><?php
} ?>
 

  <script src="plugins/chosen/chosen.jquery.min.js"></script>
  <script src="core/libraries/quick.form.min.js"></script>
  <script src="core/libraries/quick.box.min.js"></script>
</body>
</html>