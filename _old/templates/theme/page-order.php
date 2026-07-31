<?php 
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;
require_once 'templates/'.$config['skin'].'/_header.php';

if( isset( $aData['sName'] ) ){
    require_once 'templates/'.$config['skin'].'/_title.php';

?>
 
<div class="container pageOrder">
  <div class="mainPage__wrapper">
        <div class="mainPage__content">
        <?php 
    
    
    
    
    
    $cart_message = "";
    // Pobranie koszyka z cookies
    $cart = isset($_COOKIE['cart']) ? json_decode($_COOKIE['cart'], true) : [];
    $total_items = array_sum($cart);
    $cart_input = str_replace( "\"", "'", $_COOKIE['cart']);
    $cart_list = "";
    
    if (empty($cart)) {
        $cart_message = '<div class="alert alert-info"><img src="'.ICONS.'cart.svg" alt="Koszyk">Twój koszyk jest pusty.</div>';
        
    } else {

        $delivery_cost = "20";
        $price_sum = $delivery_cost;
        $cart_list .= '<div class="widget">';
        $cart_list .= '<h5 class="widget__title"><img src="'.ICONS.'box.svg">Podsumowanie<a href="'.$oPage->aPages[$config['cart_page']]['sLinkName'].'" class="ml-auto button button-border button-xs">Popraw koszyk</a></h5>';
        $cart_list .= '<div class="widget__content">';
            $cart_list .= '<ul class="widget__list">';

        foreach ($cart as $product_id => $quantity) {
            $price_item = "";
            $name = getData($product_id, 'sName');
            $price_item = getData($product_id, 'sPrice') * $quantity;
            $price_sum = $price_sum + $price_item;

            $cart_list .= '<li>';
                $cart_list .= '<strong>'.getData($product_id, 'sName').' </strong>';
                $cart_list .= '<span>'.$quantity.' x '.getData($product_id, 'sPrice').' zł</span>';
            $cart_list .= '</li>';
        }

            $cart_list .= '<li><strong>Dostawa</strong><span>'.$delivery_cost.' zł</span></li>';
            $cart_list .= '<li class="font-l text-strong"><strong>Suma</strong><span>'.$price_sum.' zł</span></li>';
            $cart_list .= "</ul>";
            $cart_list .= "</div>";
        $cart_list .= "</div>";
   
    }
    
    
    


    
    // FORMULARZ ZMOWIENIA ZOSTAŁ WYSŁANY
    // FORMULARZ ZMOWIENIA ZOSTAŁ WYSŁANY
    // FORMULARZ ZMOWIENIA ZOSTAŁ WYSŁANY
    // FORMULARZ ZMOWIENIA ZOSTAŁ WYSŁANY
    // FORMULARZ ZMOWIENIA ZOSTAŁ WYSŁANY
    
     if(isset($_POST['save']) && $_POST['save'] == 1){
        
//        echo "<br><table class='table table-bordered'>";
            foreach ($_POST as $key => $value) {
//                echo "<tr>";
//                echo "<td>";
//                echo $key;
//                echo "</td>";
//                echo "<td>";
//                echo $value;
//                echo "</td>";
//                echo "</tr>";
                $data[$key] = $value;
            }
//        echo "</table>";
        
        
        
        // sprawdzam aby wysyłkę email wykonac tylko raz
        $result_check = $oSql->getQuery('SELECT date FROM orders WHERE date="'.$_POST['date'].'"');
        $data_check = $result_check->fetch(PDO::FETCH_ASSOC);
         
        if($data_check['date'] != ""){
            $send_email = FALSE;
        }else{
            $send_email = TRUE;
        }  
         
         
        // Działam dalej bo nie znaleziono takiego rekordu w bazie
        if($send_email){

            $insert_form = $oSql->getQuery( 'INSERT INTO orders ("id", "name", "email", "phone", "street", "city", "code", "nip", "payment", "delivery", "date", "products", "message", "price", "status" ) VALUES (null, "'.$data['name'].'",  "'.$data['email'].'", "'.$data['phone'].'", "'.$data['street'].'", "'.$data['city'].'", "'.$data['code'].'", "'.$data['nip'].'", "'.$data['payment'].'", "'.$data['delivery'].'", "'.$data['date'].'", "'.$data['products'].'", "'.$data['message'].'", "'.$data['price'].'", 0 )' );

            // zapisuje nowy rekord w bazie ORDERS
            $data_form = $insert_form->fetch( PDO::FETCH_ASSOC);
            
            
            // Pobieram ID utworzonego zlecenia
            $result_id = $oSql->getQuery('SELECT date, id FROM orders WHERE date="'.$data['date'].'"');
            $data_id = $result_id->fetch(PDO::FETCH_ASSOC);
         
           

            // WYSYŁANIE EMAILA
            $order_delivery = orderDelivery($data['delivery']);
            $order_payment = orderPayment($data['payment']);
            $order_products = orderProducts($cart);
            $order_email = $config['email']; // odbiorca
            $order_from = $data['name'].'<'.$data['email'].'>'; // nadawca
            $order_subject = "Zamówienie ".$data_id['id']." - ".$config['logo']; // temat
            $order_message = '
                <html>
                <head>
                  <title>'.$order_subject.'</title>
                </head>
                <body>
                    <h3 style="margin:margin:0 0 0px 0; font-size:20px"><u>Informacje o produktach</u></h3>
                    '.$order_products.'
                    <h3 style="margin:20px 0 0px 0; font-size:20px"><u>Dane zamawiającego</u></h3>
                    <p><strong>Imię i nazwisko:</strong> '.$data['name'].'<br>
                    <strong>E-mail:</strong> '.$data['email'].'<br>
                    <strong>Telefon:</strong> '.$data['phone'].'<br>
                    <strong>Adres:</strong> '.$data['street'].', '.$data['city'].' '.$data['code'].' <br>
                    '.($data['nip'] != '' ? '<strong>NIP:</strong> '.$data['nip'].'<br>' : '').'
                    '.$data['message'].'</p>
                    
                    <h3 style="margin:20px 0 0px 0; font-size:20px"><u>Dostawa i płatność</u></h3>
                    <p><strong>Dostawa:</strong> '.$order_delivery.'</p>
                    <p><strong>Płatność:</strong> '.$order_payment.'<br>
                    <strong>Dane do wpłaty:</strong> Gospodarstwo Rolne Barbara Bartecka<br>
                    Szarowola 183, 22-600 Tomaszów Lubelski <br>
                    Numer konta (Bank PkO BP): <br>
                    74 1020 5385 0000 9002 0016 2891 <br>
                    <strong>Tytułem:</strong> Zamówienie '.$data_id['id'].'<br>
                    
                    <strong>Do zapłaty łącznie:</strong> '.$data['price'].' zł</p>
                    
                
                  <hr>
                  <small><i>Wysłane ze strony '.$base_url.'</i></small>
                </body>
                </html>';
            
            
            // WIADOMOŚĆ DO ADMINISTRATORA
            echo sendEmail(array( 
                'email' => $config['email'],
                'from' => $config['email'],
                'subject' => $order_subject,
                'message' => $order_message,
                'alert' => FALSE
            ));
            
            // WIADOMOŚĆ DO ADMINISTRATORA
            echo sendEmail(array( 
                'email' => 'blazej@bartecki.pl',
                'from' => $config['email'],
                'subject' => $order_subject,
                'message' => $order_message,
                'alert' => FALSE
            ));
            
            // POTWIERDZENIE DLA KLIENTA
            echo sendEmail(array(
                'email' => $data['email'],
                'from' => $config['email'],
                'subject' => $order_subject,
                'message' => $order_message,
                'alert' => "Zamówienie zostało złożone. Na adres e-mail ".$data['email']." wysłaliśmy szczegóły transakcji.",
            ));
            
//            setcookie('cart_message', "Zamówienie zostało złożone!!1 Na adres e-mail ".$data['email']." wysłaliśmy szczegóły.", time() + (1), "/");
            
            
            setcookie("cart", "", time() - 3600, "/"); // PHP
            ?>
            <script>
                document.cookie = "cart=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;"; 
            </script>
           <?php
            
            
            
            
            
        }else{
            echo "<div class='alert alert-success'>Powiadomienie o zamówieniu zostało już wysłane na email ".$data['email'].".</div>";
        }
    }
    
    
    

    echo $cart_message;
    
    echo isset($_COOKIE['cart_message']) ? '<div class="alert alert-info">'.$_COOKIE['cart_message'].'</div>' : ''; ?>
               
        <?php echo ($aData['sDescriptionShort'] != ""  ? '<div class="mainPage__desc font-md">'.$aData['sDescriptionShort'].'</div>' : null); ?>
                
                
<?php if(!isset($_POST['save']) && !empty($cart)) { ?>                
                
<form action="<?php echo $oPage->aPages[$aData['iPage']]['sLinkName']; ?>" method="post" class="form pageContact__form" enctype="multipart/form-data">
    <div id="formularz" class="form-id"></div>
    
    <input type="hidden" name="date" class="form-control" id="date" value="<?php echo time();?>">
    <input type="hidden" name="price" class="form-control" id="price" value="<?php echo $price_sum; ?>">
    <textarea name="products" class="form-control" rows="4" id="products" hidden><?php echo $cart_input; ?></textarea>
    
    
    <div class="row">
        <div class="col-6">


            <div class="form-floating form-item">
                <input type="text" class="form-control" id="name" name="name" value="" placeholder="Imię i nazwisko" required>

                <label for="name">Imię i nazwisko <span class="text-danger">*</span></label>
            </div>

            <div class="row">
                <div class="col-6">
                    <div class="form-floating form-item">
                        <input type="text" class="form-control" id="phone" name="phone" value="" placeholder="Numer telefonu" required>
                        <label for="phone">Telefon <span class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-floating form-item">
                        <input type="email" class="form-control" id="email" name="email" value="" placeholder="Adres e-mail" required>
                        <label for="email">E-mail <span class="text-danger">*</span></label>
                    </div>
                </div>
            </div>

            <div class="form-floating form-item">
                <input type="text" class="form-control" id="street" name="street" value="" placeholder="Ulica i numer" required>
                <label for="street">Ulica i numer <span class="text-danger">*</span></label>
            </div>


            <div class="row">
                <div class="col-6">
                    <div class="form-floating form-item">
                        <input type="text" class="form-control" id="city" name="city" value="" placeholder="Miejscowość" required>
                        <label for="city">Miejscowość <span class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-floating form-item">
                        <input type="text" class="form-control" id="code" name="code" value="" placeholder="Kod pocztowy" required>
                        <label for="code">Kod pocztowy <span class="text-danger">*</span></label>

                    </div>
                </div>
            </div>

            <div class="form-floating form-item">
                <input type="text" class="form-control" id="nip" name="nip" value="" placeholder="NIP" >
                <label for="nip">NIP</label>
                <span class="form-text">Podaj numer NIP jeśli chcesz otrzymać FV.</span>
            </div>
            
             <div class="form-floating form-item">
        <textarea name="message" class="form-control" rows="2" id="message"  placeholder="Dodatakowe informacje"></textarea>
        <label for="message" class="col-form-label">Dodatakowe informacje</label>
    </div>
      
      <div class="widget">
            <h5 class="widget__title"><img src="<?php echo ICONS; ?>money.svg" alt="Sposób dostawy">Dostawa i płatność</h5>
            <div class="widget__content">
               
               <ul class="widget__list">
                   <li>
                      <div>
                       <div class="form-check">
                          <input class="form-check-input" type="radio" name="payment" id="payment_1" value="1" checked>
                          <label class="form-check-label" for="payment_1">
                            Płatność przelewem na konto
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="radio" name="payment" id="payment_2" value="2" disabled>
                          <label class="form-check-label muted" for="payment_2">
                            Płatność payU
                          </label>
                        </div>
                        </div>
                   </li>
                   <li>
                      <div>
                       <div class="form-check">
                          <input class="form-check-input" type="radio" name="delivery" id="delivery_1" value="1" checked >
                          <label class="form-check-label" for="delivery_1">
                            Dostawa Kurierem DPD - 20 zł
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="radio" name="delivery" id="delivery_2" value="2" disabled>
                          <label class="form-check-label muted" for="delivery_2">
                            Dostawa do Paczkomatu - 15 zł
                          </label>
                        </div>
                        </div>
                   </li>
               </ul>
            </div>
        </div>
       
        </div>
        
    <div class="col-6">
        
    

        
 
    <?php echo $cart_list; ?>

    
    
   
 
    <div class="form-check form-item">
        <input class="form-check-input" type="checkbox" value="" id="regulamin" name="regulamin"  required>
        <label class="form-check-label" for="regulamin">
        <span class="text-danger">*</span> Akcetpuję warunki zawarte w <a href="<?php echo $oPage->aPages[17]['sLinkName']; ?>" class="link_underline" target="_blank"><?php echo $oPage->aPages[17]['sName']; ?></a> oraz w <a href="<?php echo $oPage->aPages[18]['sLinkName']; ?>" class="link_underline" target="_blank"><?php echo $oPage->aPages[18]['sName']; ?></a>. 
        </label>
    </div>
    
    <div class="form-check form-item">
        <input class="form-check-input" type="checkbox" value="" id="wiek" name="wiek"  required>
        <label class="form-check-label" for="wiek">
        <span class="text-danger">*</span> Potwierdzam, że mam powyżej 18 lat. 
        </label>
    </div>

    <?php if($config['publicKey'] != ""){ ?>
        <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
        <input type="hidden" name="action" value="validate_captcha">
    <?php } ?>
    
    <?php if($total_items < 6){ ?>
        <div class="alert alert-info">
            Zamówienie musi zawierać co najmniej 6 butelek.
        </div>
        <div class="form-button">  
            <button type="submit"  class="button button-lg disabled" ><img src="<?php echo ICONS; ?>cart.svg" alt="Zamów">Zamów</button>
        </div>
        
    <?php }else{ ?>
    
        <div class="form-button">  
            <button type="submit" id="submit" name="save" class="button button-lg" value="1"><img src="<?php echo ICONS; ?>cart.svg" alt="Zamów">Zamów</button>
        </div>
    
    <?php } ?>
    
    
    </div>
    </div>
</form>
<?php } ?>    
            


             <?php echo (($aData['sDescriptionFull'] != "" && $aData['sDescriptionFull'] != $aData['sDescriptionShort'] ) ? '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>' : null); ?>

           
       </div>
   </div>
</div>

<?php if (empty($cart)) { ?>
<div class="section ourProducts">
    <div class="section__scroll" id="section-ourProducts"></div>
    <div class="container">
       <div class="section__bg"></div>
        <header class="section__header ">
               <h2 class="section__title">Dodaj pierwsze produkty</h2>
               <div class="section__subtitle"><?php echo getData(16, "sDescriptionShort"); ?></div>
        </header>
        <div class="ourProducts__offer">
           <script>
                $(document).ready(function() {
                    $('.ourProducts__slider').owlCarousel({
                        loop:true,
                        margin: 20,
                        nav:true,
                        dots:true,
                        autoplay:true,
                        autoplayHoverPause:true,
//                        animateOut: 'fadeOut',
                        autoplayTimeout: 5000,
                        stagePadding: 40,
                        responsive : {
                            0 : {
                               items: 1,
                            },
                            578 : {
                                items: 1,
                            },
                            768 : {
                                items: 2,
                            },
                            992 : {
                                items: 3,
                            },
                            1200 : {
                                items: 4,
                            }
                        }
                    });
                });
            </script>
            <?php echo $oPage->listPages(6, array('hide_cat' => TRUE, 'class' => 'pagesList-6 ourProducts__slider owl-carousel')); ?>
        </div>
    </div>
</div>
 <?php } ?>
 
 
<?php 
} else{ 
    require_once 'templates/'.$config['skin'].'/_404.php';
}
require_once 'templates/'.$config['skin'].'/_footer.php'; ?>