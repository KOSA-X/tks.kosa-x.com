<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

/**
 * SKLEP → USTAWIENIA
 * Zarządzanie kodami rabatowymi ($config['order_discounts']).
 * Zapis trafia do database/config.shop.php (poza repo, nadpisuje config.php).
 *
 * @claude-extend: kolejne sekcje ustawień sklepu (sposoby dostawy,
 * metody płatności — dziś w config.php) dodawaj w tym widoku, a ich
 * zapis dopisuj do shopSettingsWrite() poniżej.
 */

define( 'SHOP_SETTINGS_FILE', 'database/config.shop.php' );

/** zapis ustawień sklepu do pliku nadpisującego config.php */
function shopSettingsWrite( array $aDiscounts ): bool
{
    $sCode = "<?php\n"
        ."/**\n"
        ." * USTAWIENIA SKLEPU — plik generowany z panelu (Sklep → Ustawienia).\n"
        ." * NIE edytuj ręcznie: zapis z panelu nadpisze ten plik.\n"
        ." * Ostatnia aktualizacja: ".date( 'Y-m-d H:i:s' )."\n"
        ." */\n\n"
        ."\$config['order_discounts'] = ".var_export( $aDiscounts, true ).";\n";

    return file_put_contents( SHOP_SETTINGS_FILE, $sCode, LOCK_EX ) !== false;
}

// ------------------------------------------------------------
// ZAPIS (POST) — token CSRF sprawdza globalnie adm.php
// ------------------------------------------------------------
if( isset( $_POST['sOption'] ) ){

    $aDiscounts = [];
    $aErrors    = [];

    $aCodes    = is_array( $_POST['aDiscountCode'] ?? null )    ? $_POST['aDiscountCode']    : [];
    $aPercents = is_array( $_POST['aDiscountPercent'] ?? null ) ? $_POST['aDiscountPercent'] : [];
    $aExpires  = is_array( $_POST['aDiscountExpires'] ?? null ) ? $_POST['aDiscountExpires'] : [];
    $aDeletes  = is_array( $_POST['aDiscountDelete'] ?? null )  ? $_POST['aDiscountDelete']  : [];

    foreach( $aCodes as $i => $sCode ){
        $sCode = trim( (string) $sCode );

        // pusty wiersz albo zaznaczone usunięcie — pomijamy
        if( $sCode === '' || !empty( $aDeletes[$i] ) ){
            continue;
        }

        if( !preg_match( '/^[a-zA-Z0-9ąćęłńóśźżĄĆĘŁŃÓŚŹŻ_-]{2,60}$/u', $sCode ) ){
            $aErrors[] = 'Kod „'.html( $sCode ).'” — dozwolone litery, cyfry, myślnik i podkreślenie (2–60 znaków).';
            continue;
        }

        $fPercent = (float) str_replace( ',', '.', (string) ( $aPercents[$i] ?? '' ) );
        if( $fPercent <= 0 || $fPercent > 100 ){
            $aErrors[] = 'Kod „'.html( $sCode ).'” — rabat musi być w zakresie 1–100%.';
            continue;
        }

        // input type=date daje RRRR-MM-DD; akceptujemy też DD.MM.RRRR
        $sExpires = trim( (string) ( $aExpires[$i] ?? '' ) );
        if( $sExpires !== '' ){
            if( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $sExpires, $m ) ){
                $sExpires = sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
            }
            if( !preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sExpires )
                || !checkdate( (int) substr( $sExpires, 5, 2 ), (int) substr( $sExpires, 8, 2 ), (int) substr( $sExpires, 0, 4 ) ) ){
                $aErrors[] = 'Kod „'.html( $sCode ).'” — nieprawidłowa data ważności.';
                continue;
            }
        }

        // duplikaty kodów (bez rozróżniania wielkości liter)
        $sKeyUpper = mb_strtoupper( $sCode, 'UTF-8' );
        foreach( $aDiscounts as $sExisting => $_ ){
            if( mb_strtoupper( $sExisting, 'UTF-8' ) === $sKeyUpper ){
                $aErrors[] = 'Kod „'.html( $sCode ).'” — zduplikowany.';
                continue 2;
            }
        }

        $aDiscounts[$sCode] = [
            'percent' => $fPercent,
            'expires' => $sExpires,
        ];
    }

    if( empty( $aErrors ) ){
        if( shopSettingsWrite( $aDiscounts ) ){
            header( 'Location: '.$config['admin_file'].'?p=shop-settings&sOption=saved' );
            exit;
        }
        $aErrors[] = 'Nie udało się zapisać pliku '.SHOP_SETTINGS_FILE.' — sprawdź uprawnienia katalogu database/.';
    }

    // przy błędach: pokaż formularz z komunikatami, ale bez zapisu
    $_SESSION['shop_settings_errors'] = $aErrors;
}

$sSelectedMenu = 'shop';
require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

$aFormErrors = $_SESSION['shop_settings_errors'] ?? [];
unset( $_SESSION['shop_settings_errors'] );

$aDiscountsNow = ( isset( $config['order_discounts'] ) && is_array( $config['order_discounts'] ) )
    ? $config['order_discounts']
    : [];
$bManaged = is_file( SHOP_SETTINGS_FILE );
?>

<form action="?p=shop-settings" name="form" method="post" class="main-form">
    <input type="hidden" name="sTokenCsrf" value="<?php echo html( getCsrfToken() ); ?>">

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title">Ustawienia sklepu</h1>
        <div class="mainPage__buttons">
            <input type="submit" name="sOption" class="button" value="<?php echo $lang['save']; ?>" />
        </div>
    </header>

    <?php
    if( isset( $_GET['sOption'] ) && $_GET['sOption'] === 'saved' ){
        echo '<div class="alert alert-success">'.$lang['Operation_completed'].'</div>';
    }
    foreach( $aFormErrors as $sError ){
        echo '<div class="alert alert-danger">'.$sError.'</div>';
    }
    ?>

   <div class="tabsContent">
    <ul class="forms list">
       <li>
            <p >
                Kod działa w formularzu zamówienia (pole „Kod rabatowy”), obniża wartość
                <strong>produktów</strong> o podany procent (dostawa bez rabatu).
                Wielkość liter przy wpisywaniu kodu nie ma znaczenia.
                Pusta data = kod bezterminowy; kod ważny jest do <strong>końca</strong> wskazanego dnia.
                <?php if( !$bManaged ): ?>
                    <br>Aktualna lista pochodzi z <code>database/config.php</code> — pierwszy zapis
                    utworzy plik <code><?php echo SHOP_SETTINGS_FILE; ?></code>, który ją nadpisze.
                <?php endif; ?>
            </p>
        </li>
        <li class="mt-4"><h5 class="form-separator">Kody rabatowe</h5></li>
        
        <li>
            <div class="shopDiscounts" id="discounts-list">
                <div class="shopDiscounts__head">
                    <span>Kod</span>
                    <span>Rabat %</span>
                    <span>Ważny do</span>
                    <span>Usuń</span>
                </div>

                <?php
                $i = 0;
                foreach( $aDiscountsNow as $sCode => $aDef ){
                    $sExpires = (string) ( $aDef['expires'] ?? '' );
                    // znormalizuj DD.MM.RRRR do formatu pola date
                    if( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $sExpires, $m ) ){
                        $sExpires = sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
                    }
                    ?>
                    <div class="shopDiscounts__row">
                        <input type="text" name="aDiscountCode[<?php echo $i; ?>]" value="<?php echo html( (string) $sCode ); ?>" maxlength="60">
                        <input type="number" name="aDiscountPercent[<?php echo $i; ?>]" value="<?php echo html( (string) ( $aDef['percent'] ?? '' ) ); ?>" min="1" max="100" step="0.01">
                        <input type="date" name="aDiscountExpires[<?php echo $i; ?>]" value="<?php echo html( $sExpires ); ?>">
                        <label class="shopDiscounts__delete" title="Zaznacz, aby usunąć przy zapisie">
                            <input type="checkbox" name="aDiscountDelete[<?php echo $i; ?>]" value="1"> usuń
                        </label>
                    </div>
                    <?php
                    $i++;
                }
                ?>

                <!-- pusty wiersz na nowy kod -->
                <div class="shopDiscounts__row">
                    <input type="text" name="aDiscountCode[<?php echo $i; ?>]" value="" maxlength="60" placeholder="np. WAKACJE10">
                    <input type="number" name="aDiscountPercent[<?php echo $i; ?>]" value="" min="1" max="100" step="0.01" placeholder="np. 10">
                    <input type="date" name="aDiscountExpires[<?php echo $i; ?>]" value="">
                    <span></span>
                </div>
            </div>
        </li>
        <li>
            <button type="button" class="button button-border" id="discount-add-row">Dodaj kolejny kod</button>
        </li>
    </ul>
    </div>
</form>


<script>
// dokładanie pustych wierszy na kolejne kody (indeksy kontynuowane)
$(function(){
    var iNext = <?php echo $i + 1; ?>;
    $('#discount-add-row').on('click', function(){
        var sRow = '<div class="shopDiscounts__row">'
            + '<input type="text" name="aDiscountCode[' + iNext + ']" value="" maxlength="60" placeholder="np. WAKACJE10">'
            + '<input type="number" name="aDiscountPercent[' + iNext + ']" value="" min="1" max="100" step="0.01" placeholder="np. 10">'
            + '<input type="date" name="aDiscountExpires[' + iNext + ']" value="">'
            + '<span></span>'
            + '</div>';
        $('#discounts-list').append(sRow);
        iNext++;
    });
});
</script>

<?php
require_once 'templates/admin/_footer.php';
?>
