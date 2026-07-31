<?php 
if( !defined( 'ADMIN_PAGE' ) )
  exit( 'Script by OpenSolution.org' );

if( isset( $_POST['sOption'] ) ){
  unset( $_POST['login_email'], $_POST['login_pass'] );
  if( !empty( $_POST['login_pass_old'] ) && !empty( $_POST['login_email_old'] ) && changeSpecialChars( $_POST['login_email_old'] ) == $config['login_email'] && changeSpecialChars( str_replace( '"', '&quot;', $_POST['login_pass_old'] ) ) == $config['login_pass'] ){
    if( !empty( $_POST['login_email_new'] ) && checkEmail( $_POST['login_email_new'] ) )
      $_POST['login_email'] = $_POST['login_email_new'];
    if( !empty( $_POST['login_pass_new'] ) )
      $_POST['login_pass'] = $_POST['login_pass_new'];
  }

  saveVariables( $_POST, $config['dir_database'].'config.php' );
  saveVariables( $_POST, $config['dir_database'].'config_'.$config['language'].'.php' );
  header( 'Location: '.$config['admin_file'].'?p=settings&sOption=save' );
  exit;
}

$sSelectedMenu = 'settings';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>
  
    <form action="?p=<?php echo $_GET['p']; ?>" name="form" method="post" class="main-form" onsubmit="return checkLoginChange( this );">
 
 
   <header class="mainPage__header">
       <h1 class="mainPage__title"><?php echo $lang['Settings']; ?></h1>
       <div class="mainPage__buttons d-flex ">
           <input type="submit" name="sOption" class="button button" value="<?php echo $lang['save']; ?>" />
       </div>
</header>

  <?php if( isset( $config['manual_link'] ) ){
    echo '<div class="manual"><a href="'.$config['manual_link'].'instruction#settings" title="'.$lang['Help'].'" target="_blank"></a></div>';
  }
  if( isset( $_GET['sOption'] ) ){
    echo '<div class="alert alert-info">'.$lang['Operation_completed'].'</div>';
  }?>


     

      <ul class="tabs">
        <!-- tabs start -->
        <li id="pages" class="selected"><a href="#">Główne & SEO</a></li>
        <li id="contacts"><a href="#">Dane kontaktowe</a></li>
        <li id="socialmedia"><a href="#">Social media</a></li>
        <li id="tools"><a href="#">Narzędzia</a></li>
<!--        <li id="loging"><a href="#"><?php echo $lang['Loging']; ?></a></li>-->
        <!-- <li id="options"><a href="#"><?php echo $lang['Options']; ?></a></li> --> <!-- An example of creating setting fields is described below -->
        <!-- tabs end -->
      </ul>
      <script>
      var aLoginAjax = {};
      $(function(){
        displayTabInit();
        $( ".main-form" ).quickform();
      });
      </script>

  
  
      
      <ul id="tab-pages" class="forms list tabsContent">
        <li>
         <div class="form-item">
          <label for="sStartPage"><?php echo $lang['Start_page']; ?></label>
          <select name="start_page" id="sStartPage" data-form-check="required">
            <?php if( empty( $config['start_page'] ) ){ ?><option value="" disabled="disabled" selected="selected"><?php echo $lang['none']; ?></option><?php } ?>
            <?php echo $oPage->listPagesSelectAdmin( $config['start_page'] ); ?>
          </select>
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="shop_page">Strona ze sklepem</label>
          <select name="shop_page" id="shop_page" >
            <?php if( empty( $config['shop_page'] ) ){ ?><option value="" disabled="disabled" selected="selected"><?php echo $lang['none']; ?></option><?php } ?>
            <?php echo $oPage->listPagesSelectAdmin( $config['shop_page'] ); ?>
          </select>
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="cart_page">Strona z koszykiem</label>
          <select name="cart_page" id="cart_page" >
            <?php if( empty( $config['cart_page'] ) ){ ?><option value="" disabled="disabled" selected="selected"><?php echo $lang['none']; ?></option><?php } ?>
            <?php echo $oPage->listPagesSelectAdmin( $config['cart_page'] ); ?>
          </select>
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="order_page">Strona zamówienia</label>
          <select name="order_page" id="order_page" >
            <?php if( empty( $config['order_page'] ) ){ ?><option value="" disabled="disabled" selected="selected"><?php echo $lang['none']; ?></option><?php } ?>
            <?php echo $oPage->listPagesSelectAdmin( $config['order_page'] ); ?>
          </select>
            </div>
        </li>
        
        <li><h5 class="form-separator">SEO Meta</h5></li>
        <li>
         <div class="form-item">
          <label for="title">Tytuł strony</label>
          <input type="text" name="title" value="<?php if( isset( $config['title']  ) ) echo $config['title']; ?>" id="title" placeholder="" />
            </div>
        </li>
         <li>
         <div class="form-item">
          <label for="description">Opis</label>
          <input type="text" name="description" value="<?php if( isset( $config['description']  ) ) echo $config['description']; ?>" id="description" placeholder="" />
            </div>
        </li>
         
         <li>
         <div class="form-item">
          <label for="logo">Nazwa skrócona</label>
          <input type="text" name="logo" value="<?php if( isset( $config['logo']  ) ) echo $config['logo']; ?>" id="logo" placeholder="" />
            </div>
        </li>
         
         <li>
         <div class="form-item">
          <label for="slogan">Slogan</label>
          <input type="text" name="slogan" value="<?php if( isset( $config['slogan']  ) ) echo $config['slogan']; ?>" id="slogan" placeholder="" />
            </div>
          </li>
            
            <li>
         <div class="form-item">
          <label for="foot_info">Tekst w stopce</label>
          <input type="text" name="foot_info" value="<?php if( isset( $config['foot_info']  ) ) echo $config['foot_info']; ?>" id="foot_info" placeholder="" />
           <span class="form-text">np. Copyright © Wszystkie prawa zastrzeożone</span>
            </div>
        </li>
          
        <!-- tab pages -->
      </ul>
      
      
      <ul id="tab-contacts" class="forms list tabsContent">
       
        
        <li>
         <div class="form-item">
          <label for="email">E-mail</label>
          <input type="text" name="email" value="<?php if( isset( $config['email']  ) ) echo $config['email']; ?>" id="email" placeholder="" />
            </div>
        </li>
        
        <li>
        <div class="form-item">
          <label for="phone">Telefon</label>
          <input type="text" name="phone" value="<?php if( isset( $config['phone']  ) ) echo $config['phone']; ?>" id="phone" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="phone2">Telefon 2</label>
          <input type="text" name="phone2" value="<?php if( isset( $config['phone2']  ) ) echo $config['phone2']; ?>" id="phone2" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="street">Ulica i nr</label>
          <input type="text" name="street" value="<?php if( isset( $config['street']  ) ) echo $config['street']; ?>" id="street" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="city">Miasto</label>
          <input type="text" name="city" value="<?php if( isset( $config['city']  ) ) echo $config['city']; ?>" id="city" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="code">Kod pocztowy</label>
          <input type="text" name="code" value="<?php if( isset( $config['code']  ) ) echo $config['code']; ?>" id="code" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="maps">Google Maps URL</label>
          <input type="text" name="maps" value="<?php if( isset( $config['maps']  ) ) echo $config['maps']; ?>" id="maps" placeholder="" />
            </div>
        </li>
        
        <li><h5 class="form-separator">Godziny otwarcia</h5><p class="form-text">Format godziny to np. 08:00</p></li>
        
        <li>
         <div class="form-item">
          <label for="hours_1">Otwarcie Pon - Pt</label>
          <input type="text" name="hours_1" value="<?php if( isset( $config['hours_1']  ) ) echo $config['hours_1']; ?>" id="hours_1" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="hours_2">Zamknięcie Pon - Pt</label>
          <input type="text" name="hours_2" value="<?php if( isset( $config['hours_2']  ) ) echo $config['hours_2']; ?>" id="hours_2" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="hours_3">Otwarcie Sobota</label>
          <input type="text" name="hours_3" value="<?php if( isset( $config['hours_3']  ) ) echo $config['hours_3']; ?>" id="hours_3" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="hours_4">Zamknięcie Sobota</label>
          <input type="text" name="hours_4" value="<?php if( isset( $config['hours_4']  ) ) echo $config['hours_4']; ?>" id="hours_4" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="hours_5">Otwarcie Niedziela</label>
          <input type="text" name="hours_5" value="<?php if( isset( $config['hours_5']  ) ) echo $config['hours_5']; ?>" id="hours_5" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="hours_6">Zamknięcie Niedziela</label>
          <input type="text" name="hours_6" value="<?php if( isset( $config['hours_6']  ) ) echo $config['hours_6']; ?>" id="hours_6" placeholder="" />
            </div>
        </li>
        
 
    
          
        <!-- tab pages -->
      </ul>
      
       <ul id="tab-socialmedia" class="forms list tabsContent">
       
        
        <li>
         <div class="form-item">
          <label for="facebook">Facebook</label>
          <input type="text" name="facebook" value="<?php if( isset( $config['facebook']  ) ) echo $config['facebook']; ?>" id="facebook" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="instagram">Instagram</label>
          <input type="text" name="instagram" value="<?php if( isset( $config['instagram']  ) ) echo $config['instagram']; ?>" id="instagram" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="tiktok">Tik Tok</label>
          <input type="text" name="tiktok" value="<?php if( isset( $config['tiktok']  ) ) echo $config['tiktok']; ?>" id="tiktok" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="youtube">Youtube</label>
          <input type="text" name="youtube" value="<?php if( isset( $config['youtube']  ) ) echo $config['youtube']; ?>" id="youtube" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="xcom">X.com</label>
          <input type="text" name="xcom" value="<?php if( isset( $config['xcom']  ) ) echo $config['xcom']; ?>" id="xcom" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="whatsapp">Whatsapp</label>
          <input type="text" name="whatsapp" value="<?php if( isset( $config['whatsapp']  ) ) echo $config['whatsapp']; ?>" id="whatsapp" placeholder="" />
            </div>
        </li>
        
        
        
         
        <!-- tab pages -->
      </ul>
      
      
      <ul id="tab-tools" class="forms list tabsContent">
       
        
        <li>
         <div class="form-item">
          <label for="publicKey">Google Recaptcha (Klucz witryny)</label>
          <input type="text" name="publicKey" value="<?php if( isset( $config['publicKey']  ) ) echo $config['publicKey']; ?>" id="publicKey" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="secretKey">Google Recaptcha (Tajny klucz)</label>
          <input type="text" name="secretKey" value="<?php if( isset( $config['secretKey']  ) ) echo $config['secretKey']; ?>" id="secretKey" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="analytics">Google Analytics </label>
          <input type="text" name="analytics" value="<?php if( isset( $config['analytics']  ) ) echo $config['analytics']; ?>" id="analytics" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="tagmenager">Google Tag Menager</label>
          <input type="text" name="tagmenager" value="<?php if( isset( $config['analytics']  ) ) echo $config['tagmenager']; ?>" id="tagmenager" placeholder="" />
            </div>
        </li>
        <li>
         <div class="form-item">
          <label for="google-site-verification">Google Site Verification</label>
          <input type="text" name="google-site-verification" value="<?php if( isset( $config['analytics']  ) ) echo $config['google-site-verification']; ?>" id="google-site-verification" placeholder="" />
            </div>
        </li>
        
        <li>
         <div class="form-item">
          <label for="tinymce">Tinymce</label>
          <input type="text" name="tinymce" value="<?php if( isset( $config['tinymce']  ) ) echo $config['tinymce']; ?>" id="tinymce" placeholder="" />
            </div>
        </li>
        
        
         <li>
             <a href="https://www.google.com/recaptcha/admin/create" target="_blank" class="button button-border mr-2" >Google Recaptcha</a>
             <a href="https://analytics.google.com/analytics/web/" target="_blank" class="button button-border mr-2" >Google Analytics</a>
             <a href="https://tagmanager.google.com/?hl=pl#/home" target="_blank" class="button button-border mr-2" >Google Tag Menager</a>
             <a href="https://www.tiny.cloud/" target="_blank" class="button button-border mr-2" >Tinymce</a>
        </li>
         
      
          
         
        <!-- tab pages -->
      </ul>
      
      


      <ul id="tab-loging" class="forms list tabsContent">
        <li class="new">
   
          <div class="extended form-item">
           
            <label for="sLoginEmailNew"><?php echo $lang['Email_new']; ?></label>
            <input type="email" name="login_email_new" id="sLoginEmailNew" size="40" onchange="changeLoginData( 'email' );" onkeyup="changeLoginData( 'email' )" value="" />
            <em><?php echo $lang['and_or']; ?></em>
          </div>
          <div class="extended form-item">
            <label for="sLoginPassNew"><?php echo $lang['Password_new']; ?></label>
            <input type="text" name="login_pass_new" id="sLoginPassNew" size="30" value="" onchange="changeLoginData( 'pass' )" onkeyup="changeLoginData( 'pass' )" />
          </div>
        </li>
        <li class="old">
          <div class="extended form-item">
            <label for="sLoginEmailOld"><?php echo $lang['Email_old']; ?></label>
            <input type="email" name="login_email_old" id="sLoginEmailOld" size="40" value=""/>
            <em><?php echo $lang['and']; ?></em>
          </div>
          <div class="extended form-item">
            <label for="sLoginPassOld"><?php echo $lang['Password_old']; ?></label>
            <input type="text" name="login_pass_old" id="sLoginPassOld" size="30" value="" />
          </div>
        </li>
         tab loging 
      </ul>


      <!-- An example for creating setting fields -->
      <ul id="tab-options" class="forms list tabsContent">
        <li>
          <label for="display_homepage_name_title">Put here field description</label>
          <select name="display_homepage_name_title" id="display_homepage_name_title">
            <?php echo getYesNoSelect( $config['display_homepage_name_title'] ); ?>
          </select>
          <em class="help">Example of the selection YES or NO, for variable which contains values: <strong>true</strong> or <strong>null</strong></em>
        </li>
        <li>
          <label for="name_of_config_variable_here">Put here field description</label>
          <input type="text" name="name_of_config_variable_here" id="name_of_config_variable_here" size="30" value="<?php echo ( isset( $config['name_of_config_variable_here'] ) ? $config['name_of_config_variable_here'] : null ); ?>" />
          <em class="help">Example of the text field, for variable $config['title'], name_of_config_variable_here will be <strong>title</strong></em>
        </li>
        <!-- tab options -->
      </ul>

<p class="mt-3 muted"><?php echo $lang['Settings_in_config_file'].' '.$config['dir_database']; ?>config.php</p>
    

  </form>


<?php
require_once 'templates/admin/_footer.php';
?>