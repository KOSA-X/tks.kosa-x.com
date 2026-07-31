<?php 


if($aData['iPageParent'] == 18){
    $work = TRUE;
}else{
    $work = FALSE;
}


ob_start();
    include('contact-form.php');
    $formularz = ob_get_contents();
ob_end_clean();
error_reporting(1);


function wyswietl_forme($komunikat='') {
	global $formularz;
	$do_zmiany = array(
		'#komunikat#',
		'#strona#',
		'#name#',
		'#phone#',
		'#email#',
		'#message#'
	);
	$zmien_na = array(
		$komunikat,
		$_SERVER['REQUEST_URI'],
        $_POST['name'],
        $_POST['phone'],
		$_POST['email'],
		$_POST['message']
	);
	$formularz = str_replace ( $do_zmiany, $zmien_na, $formularz);
	return $formularz;	
}




if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
    if (isset($_POST['g-recaptcha-response'])) {
        $captcha = $_POST['g-recaptcha-response'];
    } else {
        $captcha = false;
    }
    if (!$captcha) {
        echo "<div class='alert alert-warning'>Włącz Google Recaptcha!</div>";
        print wyswietl_forme();
    } else {
        $secret = $config['secretKey'];
        $response = file_get_contents(
            "https://www.google.com/recaptcha/api/siteverify?secret=" . $secret . "&response=" . $captcha . "&remoteip=" . $_SERVER['REMOTE_ADDR']
        );
        $response = json_decode($response);
        if ($response->success === false) {
            echo '<div class="alert alert-warning">Potwierdź, że nie jesteś robotem!</div>';
            print wyswietl_forme();
        } 
    }
     
    if ($response->success==true) {
    //        $email = "k@kosiorski.pl";
            $email = $config['email'];
            $from = $_POST['name'].'<'.$_POST['email'].'>';
            $replyTo = $config['logo'].' <'.$email.'>';
            $subject = "Kontakt - ".$config['logo'];  // Temat wiadomości
            $message = '
                <html>
                <head>
                  <title>'.$subject.'</title>
                </head>
                <body>
                  <strong>Nadawca:</strong> '.$_POST['name'].'<br>
                  <strong>Telefon:</strong> '.$_POST['phone'].'<br>
                  <strong>Emial:</strong> '.$_POST['email'].'<br>
                  <p>'.$_POST['message'].'</p>
                  <small><i>Wysłane ze strony '.$base_url.'</i></small>
                </body>
                </html>
            ';

            $headers  = 'MIME-Version: 1.0'."\r\n";
            $headers .= 'Content-Type: text/html; charset=utf-8'."\r\n";
            $headers .= 'From: '.$from."\r\n";
            $headers .= 'Reply-To: '.$from."\r\n";
            $additional_parameters = "-f ".$email;



            if (mail($email, $subject, $message, $headers, $additional_parameters)) {
                echo '<div class="alert alert-success">Wiadomość została wysłana.</div>';
            } else {
                echo '<div class="alert alert-danger">Nie wysłano wiadomości. Spróbuj ponownie, lub wyślij wiadomość bezpośrednio na <a href="mailto:'.$email.'" class="text-strong text-white">'.$email.'</a>.</div>';
            }

                      
        

        }else{
//            echo '<div class="alert alert-danger">Nie wysłano wiadomości. Potwierdź, że nie jesteś robotem!</div>';
//            print wyswietl_forme();
        }

  
	
}else{
	// nowe wejście
	print wyswietl_forme();
}


 





?>