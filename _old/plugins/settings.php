<?php



// LINKOWANIE
$base_url = $_SERVER['HTTPS'] != "on" ? "https://" : "http://".$_SERVER['SERVER_NAME'];
$base_url = "https://tks.kosa-x.com";
$actual_url = $base_url.$_SERVER['REQUEST_URI'];


$header_class = "mainHeader";
$image_for_facebook = $base_url."/images/default-image.png";

// LOGO
$logo_url = file_exists("images/logo.svg") ? "images/logo.svg" : "images/logo.jpg";
$logo_image = $logo_url."?ver=".filemtime($logo_url);
$logo = '<img src="'.$logo_image.'" alt="'.$config['logo'].'" class="logo_img">';
 

$favicon =  file_exists("images/favicon.jpg") ? "images/favicon.jpg" : "images/favicon.jpg";

// JS CSS
$css_file = "templates/".$config['skin']."/css/style.css";
$js_file = "templates/".$config['skin']."/js/scripts.js";


$page_desc = $config['description'];

define('ICONS', $base_url."/images/icons/");
define('IMAGES', $base_url."/images/");

define('SHOP_PAGE', $config['current_page_id'] == $config['shop_page'] ? TRUE : FALSE);

function icon($file){
    return $base_url."images/icons/".$file;
}
function image($file){
    return $base_url."images/".$file;
}

function cartIcon(){
    global $config;
    $content = "";
    $cart = isset($_COOKIE['cart']) ? json_decode($_COOKIE['cart'], true) : [];
    $total_items = array_sum($cart);
    $count = $total_items > 0 ? " in-cart" : "";
    $content .= TRUE ? '<a href="./?koszyk"><img src="'.ICONS.'cart.svg" alt="Koszyk" class="icon"></a>' : "";

    if($content != ""){
        return '<div class="cartIcon'.$count.'">'.$content.'</div>';
    }
}

function orderProducts($data){
    $content = "";
    if (is_string($data)) {
        $data = str_replace("'", '"', $data); // Zamiana pojedynczych cudzysłowów na podwójne
        $data = json_decode($data, true); // Dekodowanie JSON-a do tablicy asocjacyjnej
    }
    if (!is_array($data) || empty($data)) {
        return "<p>Brak produktów</p>";
    }
    if (!empty($data)) {
        $content .= '<ul class="products">';
        foreach ($data as $product_id => $quantity) {
            $name = getData($product_id, 'sName');
            $price_item = getData($product_id, 'sPrice') * $quantity;
            $content .= '<li>';
            $content .= '<strong style="margin-right:10px" class="name">'.$name.'</strong> ';
            $content .= ' <span class="price">'.$quantity.' x '.$price_item.' zł</span>';
            $content .= '</li>';
        }
        $content .= "</ul>";
        return $content;   
    }
}

function orderPayment($data){
    switch($data){
        case 1:
            return "Płatność przelewem na konto";
            break;
        case 2:
            return "Płatność payU";
            break;
    }
}

function orderDelivery($data){
    switch($data){
        case 1:
            return "Dostawa Kurierem DPD (20zł)";
            break;
        case 2:
            return "Dostawa do Paczkomatu Inpost (15zł)";
            break;
    }
}

function orderStatus($data){
    switch($data){
        case 0:
            return "Nowe";
            break;
        case 1:
            return "Wysłano paczkę";
            break;
    }
}


function listMenu($options){
    $oSql = Sql::getInstance();
    $oPage = Pages::getInstance();
//    $oFile = Files::getInstance();
    global $config;
    $content = "";
//    $gallery = "";
//     '.sql().' 
    
    $result = $oSql->getQuery( 'SELECT * FROM pages WHERE iStatus > 0 
    '.(isset($options['sql']) && $options['sql'] != "" ? "AND ".$options['sql'] : '').'
    ORDER BY '.(isset($options['random']) && $options['random'] == TRUE ? ' RANDOM() ' : 'iPosition DESC, iPage DESC').'
    '.(isset($options['limit']) && $options['limit'] != "" ? 'LIMIT '.$options['limit'] : '').'
    
    ');
  
    $class .= (isset($options['class']) != "" ? " ".$options['class'] : "");   
    $class .= (isset($options['toggle']) && $options['toggle'] == TRUE ? " widget-toggle" : "");   
 
    $no_data = TRUE;
    $content .= "<aside class='widget".$class."'>".( isset( $options['title'] ) ? '<h4 class="widget__title">'.$options['title'].( isset( $options['toggle'] ) ? '<span class="widget__arrow"></span>' : null ).'</h4>' : null ).'<nav class="widget__menu">';
    while( $data = $result->fetch( PDO::FETCH_ASSOC ) ){
        $no_data = FALSE;
        $content .= '<a href="'.$oPage->aPages[$data['iPage']]['sLinkName'].'" class="widget__link'.(isset($config['current_page_id']) && $config['current_page_id'] == $data['iPage'] ? ' selected' : null).'">'.$data['sName'].'</a>';
     }
    $content .= "</nav></aside>";
    
    if($no_data){
//        $content .= "<p>Brak wyników</p>";
    }
    echo $content;
    
}

function listPages($options){
    $oSql = Sql::getInstance();
    $oPage = Pages::getInstance();
    global $config;
    $content = "";
//     '.sql().' 
    
    $result = $oSql->getQuery( 'SELECT * FROM pages WHERE iStatus > 0 
    '.(isset($options['sql']) && $options['sql'] != "" ? "AND ".$options['sql'] : '').'
    ORDER BY '.(isset($options['random']) && $options['random'] == TRUE ? ' RANDOM() ' : 'iPosition DESC, iPage DESC').'
    '.(isset($options['limit']) && $options['limit'] != "" ? 'LIMIT '.$options['limit'] : '').'
    
    ');
  
    $class .= (isset($options['class']) != "" ? " ".$options['class'] : "");   
    $class .= (isset($options['toggle']) && $options['toggle'] == TRUE ? " widget-toggle" : "");   
    $content .= "<ul class='pagesList".$class."'>";
    while( $data = $result->fetch( PDO::FETCH_ASSOC ) ){
        $data['sLinkName'] = $oPage->aPages[$data['iPage']]['sLinkName'];
         $content .= listPagesView( $data, $options );
     }
    $content .= "</ul>";
 
    echo $content;
    
}
              



function sendEmail($data){
    global $config;
    
    $alert = isset($data['alert']) ? $data['alert'] : TRUE;
    
    $email = isset($data['email']) ? $data['email'] : $config['email'];
    $from = isset($data['from']) ? $data['from'] : $config['email'];
    $subject = isset($data['subject']) ? $data['subject'] : "Kontakt - ".$config['logo'];
    $message = isset($data['message']) ? $data['message'] : "";
    $replyTo = $config['logo'].' <'.$from.'>';

    $headers  = 'MIME-Version: 1.0'."\r\n";
    $headers .= 'Content-Type: text/html; charset=utf-8'."\r\n";
    $headers .= 'From: "'.$config['logo'].'" <'.$from.'>'."\r\n";
    $headers .= 'Reply-To: '.$from."\r\n";
    $additional_parameters = "-f ".$config['email'];



    if (mail($email, $subject, $message, $headers, $additional_parameters)) {
        echo $alert ? '<div class="alert alert-success">'.$data['alert'].'</div>' : '';
    } else {
        echo $alert ? '<div class="alert alert-danger">Nie wysłano wiadomości.</div>' : '';
    }


}

function contacts(){
    global $config;
    $content = "";
    $content .= $config['phone'] != "" ? '<li class="phone"><span class="label">Telefon</span><a href="tel:'.$config['phone'].'" class="value phone1"><img src="'.ICONS.'phone-line.svg" alt="Telefon" class="invert">'.$config['phone'].'</a>'.($config['phone2'] != "" ? '<a href="tel:'.$config['phone2'].'" class="value phone2">'.$config['phone2'].'</a>' : '').'</li>' : "";
    $content .= $config['email'] != "" ? '<li class="email"><span class="label">E-mail</span><a href="mailto:'.$config['email'].'" class="value"><img src="'.ICONS.'email-line.svg" alt="Email" class="invert">'.$config['email'].'</a></li>' : "";
    $content .= $config['city'] != "" ? '<li class="location"><span class="label">Lokalizacja</span><a href="'.$config['maps'].'" target="_blank" class="value"><img src="'.ICONS.'location-line.svg" alt="Lokalizacja" class="invert">'.($config['street'] ? $config['street'].", " : "").($config['city'] ? $config['city'] : "").'</a></li>' : "";

    if($content != ""){
        return '<ul class="contactsList">'.$content.'</ul>';
    }
}

function contactsButtons(){
    global $config;
    $content = "";
    $content .= $config['phone'] != '' ? '<li class="contactButtons__item phone"><a href="tel:'.$config['phone'].'" class="button button-border"><img src="'.ICONS.'phone-line.svg" alt="Zadzwoń" title="Zadzwoń" class="icon">Zadzwoń</a></li>' : '';
    $content .= $config['email'] != '' ? '<li class="contactButtons__item email"><a href="mailto:'.$config['email'].'" class="button button-border"><img src="'.ICONS.'email-line.svg" alt="Wyślij email" title="Wyślij email" class="icon">Wyślij e-mail</a></li>' : '';
    
    if($content != ""){
        return '<ul class="contactButtons">'.$content.'</ul>';
    }
}

function socialMedia($options = ""){
    global $config;
    $content = "";
    
    if(isset($options['contacts']) && $options['contacts'] == TRUE){
        $content .= $config['phone'] != '' ? '<li class="phone"><a href="tel:'.$config['phone'].'"><span class="icon"><img src="'.ICONS.'phone.svg" alt="Zadzwoń" title="Zadzwoń" class="invert"></span><span class="label">Zadzwoń</span></a></li>' : '';
        $content .= $config['email'] != '' ? '<li class="email"><a href="mailto:'.$config['email'].'"><span class="icon"><img src="'.ICONS.'email.svg" alt="Wyślij email" title="Wyślij email" class="invert"></span><span class="label">Wyślij e-mail</span></a></li>' : '';
    }
    
    $content .= $config['facebook'] != "" ? '<li class="facebook"><a href="'.$config['facebook'].'" target="_blank"><span class="icon"><img src="'.ICONS.'facebook.svg" alt="Facebook" class="invert"></span><span class="label">Facebook</span></a></li>' : "";
    $content .= $config['instagram'] != "" ? '<li class="instagram"><a href="'.$config['instagram'].'" target="_blank"><span class="icon"><img src="'.ICONS.'instagram.svg" alt="Instagram" class="invert"></span><span class="label">Instagram</span></a></li>' : "";
    //$socialMediaList .= $config['messenger'] != "" ? '<li class="messenger"><a href="'.$config['messenger'].'" target="_blank"><span class="icon"><img src="images/icons/messenger.svg" alt="Messenger"></span><span class="label">Messenger</span></a></li>' : "";
    $content .= $config['youtube'] != "" ? '<li class="youtube"><a href="'.$config['youtube'].'" target="_blank"><span class="icon"><img src="'.ICONS.'youtube.svg" alt="Youtube" class="invert"></span><span class="label">Youtube</span></a></li>' : "";
    $content .= $config['tiktok'] != "" ? '<li class="tiktok"><a href="'.$config['tiktok'].'" target="_blank"><span class="icon"><img src="'.ICONS.'tiktok.svg" alt="TikTok" class="invert"></span><span class="label">TikTok</span></a></li>' : "";
    $content .= $config['twitter'] != "" ? '<li class="twitter"><a href="'.$config['twitter'].'" target="_blank"><span class="icon"><img src="'.ICONS.'twitter.svg" alt="Twitter" class="invert"></span><span class="label">Twitter</span></a></li>' : "";
    if($content != ""){
        return '<ul class="socialMediaIcons">'.$content.'</ul>';
    }
}

function language(){
    global $config;
    return '<div class="chooseLanguage lang-select chooseLanguage-'.$config['language'].'">
               <div id="google_translate_element"></div>
                  <div class="chooseLanguage__button lang-selected" data-val="'.$config['language'].'" translate="no"><img src="'.ICONS.'flag-'.$config['language'].'.png" class="chooseLanguage__button_flag" alt="Language '.$config['language'].'"><span class="chooseLanguage__button_arrow"></span></div>
                   <ul class="chooseLanguage__list lang-select-list">
                       <li class="language-item language-item-pl lang-select-item" data-val="pl" translate="no"><img src="'.ICONS.'flag-pl.png" alt="Language PL">Polski </li>
                       <li class="language-item language-item-en lang-select-item" data-val="en" translate="no"><img src="'.ICONS.'flag-en.png" alt="Language GB">Angielski</li>
                   </ul>
               </div>'; 
}

function darkMode(){
    return '<div class="darkMode form-switch theme-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="theme-switch-checkbox">
              <label class="form-check-label" for="theme-switch-checkbox"><img src="'.ICONS.'night.svg" alt="Ciemny motyw" class="invertImg"></label>
              <span class="label">Ciemny motyw</span>
            </div>'; 
}

function location(){
    global $config;
    return $config['city'] != "" ? '<div class="location"><img src="images/icons/location.svg">'.($config['street'] ? $config['street'].", " : "").($config['city'] ? $config['city'] : "").'</div>' : ""; 
}


function hoursOpen($show = TRUE){
    global $config;
    $dni_tygodnia = date( "N" );
    $aktualna_godzina = date( "H,i" );
    $content = "";
    //$aktualna_godzina = "02,00";
    // Co mamy dzisiaj za dzień i do której są zmówienia
    $start_tydz = isset($config['hours_1']) ? str_replace(':', ',', $config['hours_1']) : "";
    $end_tydz = isset($config['hours_2']) ? str_replace(':', ',', $config['hours_2']) : "";
    $start_sob = isset($config['hours_3']) ? str_replace(':', ',', $config['hours_3']) : "";
    $end_sob = isset($config['hours_4']) ? str_replace(':', ',', $config['hours_4']) : "";
    $start_ndz = isset($config['hours_5']) ? str_replace(':', ',', $config['hours_5']) : "";
    $end_ndz = isset($config['hours_6']) ? str_replace(':', ',', $config['hours_6']) : "";
    echo "<div class='hoursOpen'>";
    
    if($show){
        echo "<div class='hoursOpen__title'><img src='".ICONS."time.svg' alt='Godziny otwarcia' class='invert'>Godziny otwarcia</div>";
        echo "<ul class='hoursOpen__list'>";
        echo isset($config['hours_1']) && $config['hours_1'] != "" && isset($config['hours_2']) ? "<li>Poniedziałek - piątek: <strong>".$config['hours_1']." - ".$config['hours_2']."</strong></li>" : "";
        echo isset($config['hours_3']) && $config['hours_3'] != "" && isset($config['hours_4']) ? "<li>Sobota: <strong>".$config['hours_3']." - ".$config['hours_4']."</strong></li>" : "";
        echo isset($config['hours_5']) && $config['hours_5'] != "" && isset($config['hours_6']) ? "<li>Niedziela: <strong>".$config['hours_5']." - ".$config['hours_6']."</strong></li>" : "";
        echo "</ul>";
    }
    
    if(($dni_tygodnia >= "1") && ($dni_tygodnia <= "5")){
        // PONIEDZIAŁEK - PIĄTEK
        // od 00:00 do otawrcia rano
       if($aktualna_godzina > $start_tydz && $aktualna_godzina < $end_tydz){
            $hoursOpen = '<span class="hoursOpen__open"></span>Winnica obecnie jest <strong>otwarta</strong> i będzie czynna do godz. '.$config['hours_2'].'.';
        }else{
            // zamknięte, czas po godzinie zamknięcia ~16:00
            $hoursOpen = '<span class="hoursOpen__close"></span>Winnica obecnie jest <strong>nieczynna</strong>.';
        }
    }elseif($dni_tygodnia == 6 && $start_sob != "" && $end_sob != "" ){
        // SOBOTA
        if($aktualna_godzina > $start_sob && $aktualna_godzina < $end_sob){
            $hoursOpen = '<span class="hoursOpen__open"></span>Winnica obecnie jest <strong>otwarta</strong> i będzie czynna do godz. '.$config['hours_4'].'.';
        }else{
            // zamknięte, czas po godzinie zamknięcia ~16:00
            $hoursOpen = '<span class="hoursOpen__close"></span>Winnica obecnie jest <strong>nieczynna</strong>.';
        }
    }elseif($dni_tygodnia == 7 && $start_ndz != "" && $end_ndz != "" ){
        // NIEDZIELA
        if($aktualna_godzina > $start_ndz && $aktualna_godzina < $end_ndz){
            $hoursOpen = '<span class="hoursOpen__open"></span>Winnica obecnie jest <strong>otwarta</strong> i będzie czynna do godz. '.$config['hours_6'].'.';
        }else{
            // zamknięte, czas po godzinie zamknięcia ~16:00
            $hoursOpen = '<span class="hoursOpen__close"></span>Winnica obecnie jest <strong>nieczynna</strong>.';
        }
    }else{
        $hoursOpen = '<span class="hoursOpen__close"></span>Winnica obecnie jest <strong>nieczynna</strong>.';
    }
    echo '<div class="hoursOpen__desc">'.$hoursOpen.'</div>';
    echo "</div>";
}



if( isset( $aData['sName'] ) ){
    
    // WYŚWIETLENIA
    update_views($aData['iPage']);
    
    if(getData($aData['iPage'], "sDescriptionShort") != ""){
        $page_desc = substr(strip_tags(getData($aData['iPage'], "sDescriptionShort")), 0, 150);
    }elseif(getData($aData['iPage'], "sDescriptionFull") != ""){
        $page_desc = substr(strip_tags(getData($aData['iPage'], "sDescriptionFull")), 0, 150);
    }

    if(($oFile->metaFacebook( $aData['iPage'], 1 )) != ""){
        $image_for_facebook = $base_url."/files/".$oFile->metaFacebook( $aData['iPage'], 1 );
    } 
}



//
function infoBoxFooter(){
    return '<footer class="footer">
            <span class="socialmedia"><img src="images/icons/facebook.svg" alt="Facebook">TomasoviaTKS</span>
            <span class="socialmedia"><img src="images/icons/instagram.svg" alt="Facebook">@tomasovia</span>
            <span class="socialmedia"><img src="images/icons/youtube.svg" alt="Facebook">@TKSTomasovia</span>
        </footer>';
}




//function infoBoxFooter(){
//    return '<footer class="footer">
//            <span class="socialmedia"><img src="images/icons/facebook.svg" alt="Facebook">Pogoonn</span>
//            <span class="socialmedia"><img src="images/icons/instagram.svg" alt="Facebook">@pogon96laszczowka</span>
//            <span class="socialmedia"><img src="images/icons/youtube.svg" alt="Facebook">@PogońŁaszczówka1996</span>
//        </footer>';
//}

  

function action($data){
    switch($data){
        case 'goal':
            return "<span class='action'><img src='images/icons/goal.png'><span class='label'>GOL!</span></span>";
            break;
        case 'yellow_card':
            return "<span class='action'><img src='images/icons/yellow_card.png'><span class='label'>Żółta kartka</span></span>";
            break;
        case 'red_card':
            return "<span class='action'><img src='images/icons/red_card.png'><span class='label'>Czerwona kartka</span></span>";
            break;
        case 'in':
            return "<span class='action'><img src='images/icons/in.png'><span class='label'>Wchodzi</span></span>";
            break;
        case 'out':
            return "<span class='action'><img src='images/icons/out.png'><span class='label'>Schodzi</span></span>";
            break;
        case 'own_goal':
            return "<span class='action'><img src='images/icons/own_goal.png'><span class='label'>Gol samobójczy</span></span>";
            break;
    }
}

  
