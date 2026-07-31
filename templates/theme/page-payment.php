<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

// ======================================================
// 1. Czyszczenie koszyka po powrocie z PayU (clear_cart)
// ======================================================
if (isset($_GET['clear_cart']) && (string)$_GET['clear_cart'] === '1' && !headers_sent()) {
    $cookieOpts = [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => false, // koszyk był tworzony z httponly=false
        'samesite' => 'Lax',
    ];

    setcookie('cart', '', $cookieOpts);
    setcookie('cart_message', '', $cookieOpts);
}


require_once $theme.'_header.php';

if (isset($aData['sName'])) {

    require_once $theme.'_title.php';

    // ==================================================
    // 2. Pobranie ID zamówienia i tokenu z parametrów URL
    // ==================================================
    $orderIdParam = 0;
    if (isset($_GET['order'])) {
        $orderIdParam = (int)$_GET['order'];
    } elseif (isset($_GET['order_id'])) {
        $orderIdParam = (int)$_GET['order_id'];
    } elseif (isset($_GET['id'])) {
        $orderIdParam = (int)$_GET['id'];
    }

    $orderTokenParam = '';
    if (isset($_GET['t'])) {
        $orderTokenParam = (string)$_GET['t'];
    } elseif (isset($_GET['token'])) {
        $orderTokenParam = (string)$_GET['token'];
    }

    $orderRow        = null;
    $orderValid      = false;
    $paymentStatus   = 'unknown'; // success | pending | error | unknown

    // Linki pomocnicze
    $cartUrl  = $oPage->aPages[$config['shop_page']]['sLinkName']  ?? '/';
    $homeUrl  = $oPage->aPages[1]['sLinkName'] ?? '/';

    if ($orderIdParam > 0 && !empty($orderTokenParam) && isset($oSql)) {
        $oResult = $oSql->getQuery(
            'SELECT * FROM orders WHERE id='.(int)$orderIdParam.' LIMIT 1'
        );
        $row = $oResult->fetch(PDO::FETCH_ASSOC);

        if ($row && isOrderTokenValid($row, $orderTokenParam, $config)) {
            $orderRow     = $row;
            $orderValid   = true;
            $paymentStatus = detectPaymentStatus($orderRow);
        }
    }

    // ==================================================
    // 3. Przygotowanie komunikatu dla użytkownika
    // ==================================================
    $statusTitle = '';
    $statusBody  = '';
    $statusIcon  = ICONS.'info.svg';
    $statusClass = 'alert-info';

    if (!$orderValid) {
        // Token się nie zgadza albo brak zamówienia – NIE pokazujemy danych
        $statusTitle = 'Nie udało się zweryfikować zamówienia';
        $statusBody  = 'Link do podsumowania zamówienia jest nieprawidłowy lub wygasł. '
                     . 'Jeśli przed chwilą wykonywałeś płatność, sprawdź skrzynkę e-mail lub skontaktuj się z nami.';

        $statusIcon  = ICONS.'close.svg';
        $statusClass = 'alert-danger';
    } else {
        // Mamy poprawne zamówienie oraz token – korzystamy ze statusu z bazy
        if ($paymentStatus === 'success') {
            $statusTitle = 'Płatność zakończona pomyślnie';
            $statusBody  = 'Dziękujemy, Twoja płatność została poprawnie zarejestrowana. '
                         . 'Szczegóły zamówienia wysłaliśmy na podany adres e-mail.';

            $statusIcon  = ICONS.'check.svg';
            $statusClass = 'alert-success';

        } elseif ($paymentStatus === 'pending') {
            $statusTitle = 'Płatność w trakcie weryfikacji';
            $statusBody  = 'Twoje zamówienie zostało przyjęte, a płatność jest w trakcie księgowania. '
                         . 'Jeśli status nie zmieni się w ciągu kilku minut, sprawdź skrzynkę e-mail lub skontaktuj się z nami.';

            $statusIcon  = ICONS.'info.svg';
            $statusClass = 'alert-info';

        } elseif ($paymentStatus === 'error') {
            $statusTitle = 'Płatność nie została potwierdzona';
            $statusBody  = 'Wygląda na to, że płatność nie została poprawnie potwierdzona lub została odrzucona. '
                         . 'Jeśli środki zostały pobrane z Twojego konta, skontaktuj się z nami – zweryfikujemy status zamówienia.';

            $statusIcon  = ICONS.'close.svg';
            $statusClass = 'alert-danger';

        } else { // unknown
            $statusTitle = 'Status płatności nie jest jeszcze dostępny';
            $statusBody  = 'Zamówienie zostało poprawnie zapisane, jednak status płatności nie jest jeszcze jednoznaczny. '
                         . 'Jeśli przed chwilą wykonywałeś płatność, odczekaj chwilę i sprawdź ponownie lub skontaktuj się z nami.';

            $statusIcon  = ICONS.'info.svg';
            $statusClass = 'alert-info';
        }
    }

    // ==================================================
    // 4. Szczegóły zamówienia – przygotowanie danych do widoku
    // ==================================================
    $showOrderDetails = false;
    $productsHtml     = '';

    $buyerDetails  = [];
    $orderDetails  = [];
    $oPrice        = 0.0;

    if ($orderValid && $orderRow !== null) {

        $showOrderDetails = true;

        $oId      = (int)$orderRow['id'];
        $oDate    = !empty($orderRow['date']) ? date('Y-m-d H:i', (int)$orderRow['date']) : '';
        $oPrice   = isset($orderRow['price']) ? (float)$orderRow['price'] : 0.0;
        $oName    = html($orderRow['name']);
        $oEmail   = html($orderRow['email']);
        $oPhone   = html($orderRow['phone']);
        $oStreet  = html($orderRow['street']);
        $oCity    = html($orderRow['city']);
        $oCode    = html($orderRow['code']);
        $oNipRaw  = $orderRow['nip'] ?? '';
        $oNip     = $oNipRaw !== '' ? html($oNipRaw) : '';

        // ogólny status zamówienia (nie mylić z payment_status)
        $statusText = getElement($orderRow['status'], $config['order_status']);
//        if (isset($orderRow['status'])) {
//            $s = (int)$orderRow['status'];
//            if     ($s === 0) $statusText = 'Nowe';
//            elseif ($s === 1) $statusText = 'W realizacji';
//            elseif ($s === 2) $statusText = 'Zrealizowane';
//            else              $statusText = 'Inny (kod: '.$s.')';
//        }

        // metoda dostawy / płatności – jak w page-order.php
        $deliveryLabel = '';
        $paymentLabel  = '';

        if (!empty($config['order_delivery'])) {
            $deliveryLabel = getElementArray((int)($orderRow['delivery'] ?? 0), $config['order_delivery'], 'label_price');
        }
        if (!empty($config['order_payment'])) {
            $paymentLabel  = getElementArray((int)($orderRow['payment'] ?? 0), $config['order_payment']);
        }

        $deliveryLabel = html($deliveryLabel);
        $paymentLabel  = html($paymentLabel);

        // InPost – jeśli są dane
        $inpostId   = isset($orderRow['inpost_point_id'])      ? trim((string)$orderRow['inpost_point_id'])      : '';
        $inpostName = isset($orderRow['inpost_point_name'])    ? trim((string)$orderRow['inpost_point_name'])    : '';
        $inpostAddr = isset($orderRow['inpost_point_address']) ? trim((string)$orderRow['inpost_point_address']) : '';

        $inpostHtml = '';
        if ($inpostId !== '' || $inpostName !== '' || $inpostAddr !== '') {
            $inpostHtml  = '<strong>Paczkomat InPost:</strong><br>';
            if ($inpostName !== '') {
                $inpostHtml .= html($inpostName).' ';
            }
            if ($inpostId !== '') {
                $inpostHtml .= '('.html($inpostId).')';
            }
            if ($inpostAddr !== '') {
                $inpostHtml .= '<br>'.html($inpostAddr);
            }
        }

        // produkty – korzystamy z orderProducts, jeśli dostępne
        $productsCart = [];

        if (!empty($orderRow['products'])) {
            $decoded = json_decode($orderRow['products'], true);
            if (is_array($decoded)) {
                $productsCart = $decoded;
            }
        }

        if (!empty($productsCart) && function_exists('orderProducts')) {
            $productsHtml = orderProducts($productsCart);
        } elseif (!empty($productsCart)) {
            // fallback – prosty UL
            $tmp  = '<ul>';
            foreach ($productsCart as $pid => $qty) {
                if (!ctype_digit((string)$pid)) continue;
                $pid = (int)$pid;
                $qty = (int)$qty;
                if ($pid <= 0 || $qty < 1) continue;

                $name = getData($pid, 'sName') ?? ('Produkt #'.$pid);
                $tmp .= '<li>'.html($name).' x '.$qty.'</li>';
            }
            $tmp .= '</ul>';
            $productsHtml = $tmp;
        }

        // ---------------------------
        // 4a. Dane zamawiającego (tablica dla widoku)
        // ---------------------------
        $buyerDetails = [
            [
                'label' => 'Imię i nazwisko',
                'value' => $oName,
            ],
            [
                'label' => 'Ulica',
                'value' => $oStreet,
            ],
            [
                'label' => 'Miejscowość',
                'value' => trim($oCity.' '.$oCode),
            ],
            [
                'label' => 'NIP',
                'value' => $oNip,
                'show'  => ($oNip !== ''),
            ],
            [
                'label' => 'E-mail',
                'value' => $oEmail,
            ],
            [
                'label' => 'Telefon',
                'value' => $oPhone,
            ],
        ];

        // ---------------------------
        // 4b. Szczegóły zamówienia (tablica dla widoku)
        // ---------------------------
        $orderDetails = [
            [
                'label' => 'Numer zamówienia',
                'value' => $oId,
            ],
            [
                'label' => 'Data złożenia',
                'value' => $oDate,
                'show'  => ($oDate !== ''),
            ],
            [
                'label' => 'Status zamówienia',
                'value' => $statusText,
                'show'  => ($statusText !== ''),
            ],
            [
                'label' => 'Płatność',
                'value' => $paymentLabel,
                'show'  => ($paymentLabel !== ''),
            ],
            [
                'label' => 'Dostawa',
                'value' => $deliveryLabel,
                'show'  => ($deliveryLabel !== ''),
            ],
            [
                'label'  => 'Punkt odbioru',
                'value'  => $inpostHtml,
                'show'   => ($inpostHtml !== ''),
                'isHtml' => true,
            ],
            [
                'label'  => 'Kwota zamówienia',
                'value'  => number_format($oPrice, 2, ',', ' ').' zł',
                'strong' => true,
            ],
        ];
    }

    // ==================================================
    // 5. Treść CMS (opis, obrazki, pliki)
    // ==================================================
    $article = '';

    if (!empty($aData['sDescriptionShort'])) {
        $article .= '<div class="mainPage__descShort">'.$aData['sDescriptionShort'].'</div>';
    }

    $article .= $oFile->listImages($aData['iPage'], [
        'iType'      => 1,
        'full_image' => true,
        'parallax'   => true,
        'slider'     => true,
        'class'      => 'mainPage negativeMargin'
    ]);

    if (!empty($aData['sDescriptionFull']) && $aData['sDescriptionFull'] !== $aData['sDescriptionShort']) {
        $article .= '<div class="mainPage__descFull">'.$aData['sDescriptionFull'].'</div>';
    }

    $article .= $oFile->listFiles($aData['iPage']);
    ?>

    <div class="container pagePaymentStatus">
        <div class="mainPage__wrapper">
            <?php require_once $theme.'_column.php'; ?>

            <div class="mainPage__content">

                <div class="paymentStatus alert mb-4 <?php echo $statusClass; ?>">
                    <div class="paymentStatus__icon alert__icon">
                        <img src="<?php echo $statusIcon; ?>" alt="Status płatności">
                    </div>
                    <div class="paymentStatus__content">
                        <h5 class="paymentStatus__title text-strong mb-1">
                            <?php echo $statusTitle; ?>
                        </h5>
                        <p class="paymentStatus__text"><?php echo $statusBody; ?></p>
                    </div>
                </div>
                
                <?php if(isset($orderRow['payment']) && $orderRow['payment'] == 2){ ?>
                <div class="card mb-4">
                    <header class="card__header">
                        <h4 class="card__title">
                        <img src="<?php echo ICONS;?>money.svg" alt="Dane do wpłaty">
                        Dane do wpłaty</h4>
                    </header>
                    <div class="card__content">
                        <?php echo getData($config['payment_info_page'], 'sDescriptionFull'); ?>
                        <p>Tytuł przelewu: <strong>Zamówienie <?php echo $oId; ?></strong> <br>
                        Kwota: <strong><?php echo number_format($oPrice, 2, ',', ' '); ?> zł</strong></p>
                    </div>
                </div>
                <?php } ?>

                <div class="paymentStatus__buttons">
                    <?php if ($paymentStatus === 'error' && $orderValid): ?>
                        <a href="<?php echo $cartUrl; ?>" class="button">
                            Wróć do sklepu i spróbuj ponownie
                        </a>
                    <?php endif; ?>
                    <?php if (!$orderValid): ?>
                        <a href="<?php echo $homeUrl; ?>" class="button button-light">
                            Przejdź na stronę główną
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($showOrderDetails): ?>

                    <?php if ($productsHtml !== ''): ?>
                        <?php echo $productsHtml; ?>
                    <?php endif; ?>

                    <div class="row mt-4">
                        <div class="col-6">
                            <div class="card widget-orderDetails">
                               
                                <header class="card__header">
                                    <h5 class="card__title">
                                        <img src="<?php echo ICONS; ?>user.svg" alt="">Dane zamawiającego
                                    </h5>
                                </header>
                                <div class="card__wrapper">
                                    <?php if (!empty($buyerDetails)): ?>
                                        <ul class="card__list">
                                            <?php foreach ($buyerDetails as $row): ?>
                                                <?php
                                                $value = $row['value'] ?? '';
                                                $show  = $row['show']  ?? true;
                                                if (!$show || $value === '') {
                                                    continue;
                                                }
                                                ?>
                                                <li>
                                                    <span class="label"><?php echo $row['label']; ?>:</span>
                                                    <span class="value"><?php echo $value; ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="card widget-orderDetails">
                               <header class="card__header">
                                    <h5 class="card__title">
                                        <img src="<?php echo ICONS; ?>box.svg" alt="">Szczegóły zamówienia
                                    </h5>
                                </header>
                                <div class="card__wrapper">
                                    <?php if (!empty($orderDetails)): ?>
                                        <ul class="card__list">
                                            <?php foreach ($orderDetails as $row): ?>
                                                <?php
                                                $value  = $row['value']  ?? '';
                                                $show   = $row['show']   ?? true;
                                                $strong = $row['strong'] ?? false;
                                                $isHtml = $row['isHtml'] ?? false;
                                                if (!$show || $value === '') {
                                                    continue;
                                                }
                                                ?>
                                                <li>
                                                    <span class="label"><?php echo $row['label']; ?>:</span>
                                                    <?php if ($isHtml): ?>
                                                        <span class="value"><?php echo $value; ?></span>
                                                    <?php elseif ($strong): ?>
                                                        <strong class="value"><?php echo $value; ?></strong>
                                                    <?php else: ?>
                                                        <span class="value"><?php echo $value; ?></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

                <?php if ($article !== ''): ?>
                    <article class="mainPage__article negativeMargin">
                        <?php echo $article; ?>
                    </article>
                <?php endif; ?>

                <?php
                echo $oFile->listImages($aData['iPage'], [
                    'iType' => 3,
                    'class' => 'galleryGrid'
                ]);
                ?>
            </div>
        </div>

        <?php
        echo $oPage->listPages($aData['iPage'], [
            'type'   => $aData['sType'] ?? null,
            'footer' => true
        ]);
        ?>
    </div>
    <?php

} else {
    require_once $theme.'_404.php';
}

require_once $theme.'_footer.php';
