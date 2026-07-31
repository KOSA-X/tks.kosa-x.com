<?php
// ======================================================
// SHOP FUNCTIONS – koszyk, ceny, zamówienia, PayU
// ======================================================

if (!defined('BASE_URL')) {
    // bezpieczeństwo gdyby ktoś odpalił bez kontekstu
    exit;
}

if (!function_exists('cartList')) {
    /**
     * Buduje listę produktów w koszyku.
     *
     * Zwraca tablicę:
     *  - cart         => oczyszczony koszyk z kluczami złożonymi
     *  - html         => HTML listy produktów
     *  - products_sum => suma wartości produktów (bez dostawy)
     *  - cartPayload  => JSON koszyka (do zapisu w bazie)
     *
     * Nowy format koszyka z kluczami złożonymi:
     * {"31_abc123": {"product_id": 31, "qty": 2, "filters": {"color": "1"}}}
     */
    function cartList(): array
    {
        global $config, $oPage;

        // koszyk z cookie
        $rawCart = $_COOKIE['cart'] ?? '';
        $cart    = [];

        if ($rawCart !== '') {
            $decoded = json_decode($rawCart, true);
            if (is_array($decoded)) {
                $cart = $decoded;
            }
        }

        $cart_html     = '';
        $products_sum  = 0.0;
        $cartSanitized = [];

        // ZAWSZE zaczynamy od wrappera
        $cart_html .= '<div class="cartListWrapper">';

        if (empty($cart)) {
            // pusty koszyk – komunikat w miejscu listy
            $cart_html .= '<div class="card__content">';
            $cart_html .= 'Brak produktów w koszyku. <br>';
            $cart_html .= '<a href="'.getUrl($config['shop_page']).'" class="link_underline">Przejdź do sklepu</a>.';
            $cart_html .= '</div>';
        } else {
            // Sanityzacja i zbieranie ID produktów
            $productIds = [];
            foreach ($cart as $cartKey => $itemData) {
                // Nowy format z kluczem złożonym: "31_abc123"
                if (is_array($itemData) && isset($itemData['product_id'])) {
                    $product_id = (int)$itemData['product_id'];
                    $quantity = isset($itemData['qty']) ? (int)$itemData['qty'] : 1;
                    $filters = $itemData['filters'] ?? [];
                }
                // Stary format z qty/filters ale bez product_id
                elseif (is_array($itemData) && isset($itemData['qty'])) {
                    $product_id = (int)explode('_', $cartKey)[0];
                    $quantity = (int)$itemData['qty'];
                    $filters = $itemData['filters'] ?? [];
                }
                // Najstarszy format: {"31": 2}
                elseif (is_numeric($itemData)) {
                    $product_id = (int)$cartKey;
                    $quantity = (int)$itemData;
                    $filters = [];
                }
                else {
                    continue;
                }

                if ($product_id <= 0 || $quantity < 1) {
                    continue;
                }

                $cartSanitized[$cartKey] = [
                    'product_id' => $product_id,
                    'qty' => $quantity,
                    'filters' => is_array($filters) ? $filters : []
                ];
                if (!in_array($product_id, $productIds)) {
                    $productIds[] = $product_id;
                }
            }

            // OPTYMALIZACJA: Pobierz wszystkie dane produktów JEDNYM zapytaniem
            $productsData = [];
            $filesData = [];

            if (!empty($productIds)) {
                $oSql = Sql::getInstance();
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));

                // Pobierz dane produktów (nazwa, ceny, filtry)
                $stmt = $oSql->prepare("SELECT iPage, sName, sPrice, xPrice, sFilter FROM pages WHERE iPage IN ($placeholders)");
                $stmt->execute($productIds);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $productsData[$row['iPage']] = $row;
                }

                // Pobierz pliki/miniatury dla produktów
                $stmt = $oSql->prepare("SELECT iPage, sFileName, iSize FROM files WHERE iPage IN ($placeholders) AND iSize > 0 AND iDefault = 1");
                $stmt->execute($productIds);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $filesData[$row['iPage']] = $row;
                }
            }

            $cart_html .= '<ul class="cartList">';

            foreach ($cartSanitized as $cartKey => $itemData) {
                $product_id = $itemData['product_id'];
                $quantity = $itemData['qty'];
                $selectedFilters = $itemData['filters'];

                // Użyj danych z wcześniej pobranych zapytań
                $productRow = $productsData[$product_id] ?? null;
                $fileRow = $filesData[$product_id] ?? null;

                $name = $productRow['sName'] ?? 'Produkt #'.$product_id;
                $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

                // Oblicz cenę z pobranych danych (EUR → PLN po kursie dziennym)
                $itemCurrency = getProductCurrency($product_id);
                $sConv = priceToPln(isset($productRow['sPrice']) ? (float)$productRow['sPrice'] : 0.0, $itemCurrency);
                $xConv = priceToPln(isset($productRow['xPrice']) && $productRow['xPrice'] ? (float)$productRow['xPrice'] : 0.0, $itemCurrency);
                $sPrice = $sConv['value'];
                $xPrice = $xConv['value'];
                $priceDecimals = $sConv['converted'] ? 0 : 2;
                $price = $xPrice > 0 ? $xPrice : $sPrice;

                $itemTotal    = $price * $quantity;
                $products_sum += $itemTotal;

                $link = $oPage->aPages[$product_id]['sLinkName'] ?? '#';

                // Użyj danych pliku z wcześniej pobranego zapytania;
                // produkt bez zdjęć dostaje uniwersalną miniaturę no-photo
                $thumb = $fileRow['sFileName'] ?? null;
                $thumb_size = $fileRow['iSize'] ?? null;
                $thumbUrl = ($thumb && $thumb_size)
                    ? FILES.$thumb_size."/".$thumb
                    : THEME.'images/no-photo.webp';

                // Przygotuj HTML ceny dla wyświetlenia (po przeliczeniu zawsze PLN)
                $curr = 'PLN';
                $sFormatted = number_format($sPrice, $priceDecimals, ',', ' ');
                $xFormatted = $xPrice ? number_format($xPrice, $priceDecimals, ',', ' ') : null;
                $priceHtml = "<div class='".($xPrice ? "productPrice productPrice-promo" : "productPrice")."'>"
                     . ($xPrice
                        ? "<span class='value productPrice-x'>{$xFormatted}</span><span class='value productPrice-normal'>{$sFormatted}</span>"
                        : "<span class='value productPrice-normal'>{$sFormatted}</span>")
                     . "<span class='label productPrice-currency'>{$curr}</span>"
                     . "</div>";

                // Formatuj wybrane filtry
                $filtersHtml = '';
                if (!empty($selectedFilters) && function_exists('formatSelectedFilters')) {
                    $filtersJson = json_encode($selectedFilters, JSON_UNESCAPED_UNICODE);
                    $filtersHtml = formatSelectedFilters($filtersJson, true);
                }

                // Przygotuj data-filters dla JS (do usuwania/modyfikacji)
                $filtersDataAttr = htmlspecialchars(json_encode($selectedFilters), ENT_QUOTES, 'UTF-8');

                $cart_html .= '<li class="cartItem" data-product-id="'.$product_id.'" data-cart-key="'.htmlspecialchars($cartKey, ENT_QUOTES, 'UTF-8').'" data-filters="'.$filtersDataAttr.'">';

                // lewa kolumna (miniatura + nazwa + filtry + cena jednostkowa)
                $cart_html .= '<div class="cartItem__product">';
                $cart_html .= '  <div class="cartItem__thumb">';
                $cart_html .= '      <a href="'.$link.'" title="'.$safeName.'">';
                $cart_html .= '          <img src="'.$thumbUrl.'" alt="'.$safeName.'">';
                $cart_html .= '      </a>';
                $cart_html .= '  </div>';
                $cart_html .= '  <div class="cartItem__content">';
                $cart_html .= '      <h5 class="cartItem__title"><a href="'.$link.'" title="'.$safeName.'">'.$safeName.'</a></h5>';
                if ($filtersHtml) {
                    $cart_html .= '      <div class="cartItem__filters">'.$filtersHtml.'</div>';
                }
                $cart_html .= '      <div class="cartItem__price">'.$quantity.' x '.$priceHtml.'</div>';
                $cart_html .= '  </div>';
                $cart_html .= '</div>';

                // prawa kolumna (ilość + suma + usuń)
                $cart_html .= '<div class="cartItem__info">';

                $cart_html .= '  <div class="cartItem__quantity">';
                $cart_html .= '      <button class="remove-from-cart quantity-minus" data-product-id="'.$product_id.'" data-filters="'.$filtersDataAttr.'">-</button>';
                $cart_html .= '      <input type="text" class="form-control" value="'.$quantity.'" readonly>';
                $cart_html .= '      <button class="add-to-cart-1 quantity-plus" data-product-id="'.$product_id.'" data-filters="'.$filtersDataAttr.'" data-action="add">+</button>';
                $cart_html .= '  </div>';

                $cart_html .= '  <div class="cartItem__price">'.number_format($itemTotal, 2, ',', ' ').' PLN</div>';

                $cart_html .= '  <button class="delete-from-cart" data-product-id="'.$product_id.'" data-filters="'.$filtersDataAttr.'">';
                $cart_html .= '      <img src="'.ICONS.'close.svg" alt="Usuń" class="invert">';
                $cart_html .= '  </button>';

                $cart_html .= '</div>'; // .cartItem__info

                $cart_html .= '</li>';
            }

            $cart_html .= '</ul>';
            $cart       = $cartSanitized;


            $cart_html .= '<div class="card__content cartWidget__button">
                <a href="'.getUrl($config['order_page']).'" class="button w-100">'.getData($config['order_page'], 'sName').'</a>
            </div>';

        }

        // domknięcie wrappera
        $cart_html .= '</div>';


        // payload koszyka – po sanityzacji
        $cartPayload = json_encode($cart, JSON_UNESCAPED_UNICODE);
        if ($cartPayload === false) {
            $cartPayload = '[]';
        }

        return [
            'cart'         => $cart,
            'html'         => $cart_html,
            'products_sum' => $products_sum,
            'cartPayload'  => $cartPayload,
        ];
    }
}


 

// ------------------------------------------------------
// 1. Informacja o kolumnach w tabeli orders
// ------------------------------------------------------
function ordersHasColumn(string $column): bool
{
    static $columns = null;

    if ($columns === null) {
        $columns = [];
        $db = Sql::getInstance();
        $stmt = $db->query('PRAGMA table_info(orders)');
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['name'])) {
                    $columns[$row['name']] = true;
                }
            }
        }
    }

    return isset($columns[$column]);
}

// ------------------------------------------------------
// 1b. Informacja o kolumnach w tabeli pages
//     (kolumny importu felg: sSKU, iStock, sCurrency...)
// ------------------------------------------------------
function pagesHasColumn(string $column): bool
{
    static $columns = null;

    if ($columns === null) {
        $columns = [];
        $db = Sql::getInstance();
        $stmt = $db->query('PRAGMA table_info(pages)');
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['name'])) {
                    $columns[$row['name']] = true;
                }
            }
        }
    }

    return isset($columns[$column]);
}

// ------------------------------------------------------
// 1b2. KODY RABATOWE ($config['order_discounts'])
//      Format wpisu: 'KOD' => ['percent' => 10, 'expires' => '2026-08-31']
//      Kod bez rozróżniania wielkości liter; 'expires' = ostatni dzień
//      ważności ('RRRR-MM-DD' lub 'DD.MM.RRRR'), pusty = bezterminowo.
// ------------------------------------------------------

if (!function_exists('orderFindDiscount')) {
    /**
     * Waliduje kod rabatowy. Zwraca ['code' => KOD kanoniczny, 'percent' => float]
     * albo null (kod nieznany / nieważny / poza zakresem 0–100%).
     */
    function orderFindDiscount(string $code): ?array
    {
        global $config;

        $code = trim($code);
        if ($code === '' || empty($config['order_discounts']) || !is_array($config['order_discounts'])) {
            return null;
        }

        $needle = mb_strtoupper($code, 'UTF-8');

        foreach ($config['order_discounts'] as $defCode => $def) {
            if (mb_strtoupper((string)$defCode, 'UTF-8') !== $needle) {
                continue;
            }

            $percent = (float)($def['percent'] ?? 0);
            if ($percent <= 0 || $percent > 100) {
                return null;
            }

            // ważność: do KOŃCA wskazanego dnia
            $expires = trim((string)($def['expires'] ?? ''));
            if ($expires !== '') {
                $ts = false;
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $expires, $m)) {
                    $ts = mktime(23, 59, 59, (int)$m[2], (int)$m[3], (int)$m[1]);
                } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $expires, $m)) {
                    $ts = mktime(23, 59, 59, (int)$m[2], (int)$m[1], (int)$m[3]);
                }
                if ($ts === false || time() > $ts) {
                    return null;
                }
            }

            return ['code' => (string)$defCode, 'percent' => $percent];
        }

        return null;
    }
}

if (!function_exists('orderAppliedDiscount')) {
    /**
     * Rabat zastosowany w bieżącej sesji (ustawia plugins/shop-discount.php).
     * Waliduje kod PONOWNIE przy każdym odczycie — kod mógł stracić ważność
     * między "Przelicz rabat" a wysłaniem zamówienia; nieważny znika z sesji.
     */
    function orderAppliedDiscount(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $code = (string)($_SESSION['order_discount_code'] ?? '');
        if ($code === '') {
            return null;
        }

        $discount = orderFindDiscount($code);
        if ($discount === null) {
            unset($_SESSION['order_discount_code']);
        }

        return $discount;
    }
}

if (!function_exists('orderEnsureDiscountColumns')) {
    /**
     * Idempotentna migracja tabeli orders o kolumny rabatu.
     * Wywoływana przed INSERT-em zamówienia (wzorzec: ensureSchema w imporcie).
     */
    function orderEnsureDiscountColumns(): void
    {
        $db = Sql::getInstance();

        $cols = [];
        $stmt = $db->query('PRAGMA table_info(orders)');
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols[$row['name']] = true;
            }
        }

        $needed = [
            'discount_code'    => "ALTER TABLE orders ADD COLUMN 'discount_code' TEXT",
            'discount_percent' => "ALTER TABLE orders ADD COLUMN 'discount_percent' REAL DEFAULT 0",
            'discount_amount'  => "ALTER TABLE orders ADD COLUMN 'discount_amount' REAL DEFAULT 0",
        ];
        foreach ($needed as $col => $sql) {
            if (!isset($cols[$col])) {
                $db->exec($sql);
            }
        }
    }
}

// ------------------------------------------------------
// 1c. Waluta produktu (import zapisuje ceny 1:1 od dostawcy,
//     więc część produktów ma ceny w EUR) — fallback: waluta z configu
// ------------------------------------------------------
function getProductCurrency(int $id): string
{
    global $config;

    if (!pagesHasColumn('sCurrency')) {
        return $config['currency'] ?? 'PLN';
    }

    static $cache = [];
    if (!isset($cache[$id])) {
        $oSql = Sql::getInstance();
        $stmt = $oSql->prepare('SELECT sCurrency FROM pages WHERE iPage = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $val = $stmt->fetchColumn();
        $cache[$id] = ($val !== false && $val !== null && $val !== '')
            ? (string)$val
            : ($config['currency'] ?? 'PLN');
    }

    return $cache[$id];
}

// ------------------------------------------------------
// 1d. Kurs EUR→PLN (tabela A NBP, cache dzienny na dysku)
//     Ceny w EUR są przeliczane na PLN przy WYŚWIETLANIU —
//     w bazie zostaje oryginalna cena dostawcy w EUR.
// ------------------------------------------------------
function getEurPlnRate(): float
{
    global $config;

    // ręczne nadpisanie kursu w configu (opcjonalne)
    if (!empty($config['eur_pln_rate']) && is_numeric($config['eur_pln_rate'])) {
        return (float)$config['eur_pln_rate'];
    }

    static $rate = null;
    if ($rate !== null) {
        return $rate;
    }

    $cacheFile = ($config['dir_database'] ?? 'database/').'cache/exchange_eur.json';
    $today = date('Y-m-d');

    $cached = is_file($cacheFile) ? json_decode((string)@file_get_contents($cacheFile), true) : null;
    if (is_array($cached) && ($cached['date'] ?? '') === $today && !empty($cached['rate'])) {
        return $rate = (float)$cached['rate'];
    }

    // pobierz dzisiejszy kurs średni NBP (timeout krótki — front nie może wisieć)
    $fetched = null;
    $ctx = stream_context_create(['http' => ['timeout' => 4, 'user_agent' => 'KOSA-X-CMS/1.0']]);
    $json = @file_get_contents('https://api.nbp.pl/api/exchangerates/rates/a/eur/?format=json', false, $ctx);
    if ($json !== false) {
        $data = json_decode($json, true);
        $mid = $data['rates'][0]['mid'] ?? null;
        if (is_numeric($mid) && (float)$mid > 1) {
            $fetched = (float)$mid;
        }
    }

    if ($fetched !== null) {
        @file_put_contents($cacheFile, json_encode(['date' => $today, 'rate' => $fetched]), LOCK_EX);
        return $rate = $fetched;
    }

    // NBP niedostępne: ostatni znany kurs z cache, ostatecznie wartość awaryjna
    if (is_array($cached) && !empty($cached['rate'])) {
        return $rate = (float)$cached['rate'];
    }

    return $rate = 4.35;
}

/**
 * Cena produktu w PLN: kwoty w EUR przelicza dziennym kursem NBP
 * i ZAOKRĄGLA DO PEŁNYCH ZŁOTYCH; kwoty w PLN zwraca bez zmian.
 *
 * @return array{value: float, converted: bool}
 */
function priceToPln(float $amount, string $currency): array
{
    if (strtoupper($currency) === 'EUR') {
        return ['value' => round($amount * getEurPlnRate()), 'converted' => true];
    }
    return ['value' => $amount, 'converted' => false];
}


// ------------------------------------------------------
// 2. Cena produktu – wersja liczbowo / HTML (jak było)
// ------------------------------------------------------
function getPrice(int $id, string $mode = 'html', string $type = 'auto')
{
    global $config;
    $oSql = Sql::getInstance();

    $stmt = $oSql->prepare("SELECT sPrice, xPrice FROM pages WHERE iPage = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$r) return ($mode === 'number') ? '' : '';

    // ceny w EUR przeliczamy na PLN (kurs dzienny NBP, pełne złotówki)
    $productCurr = getProductCurrency($id);
    $sConv = priceToPln((float)$r['sPrice'], $productCurr);
    $xConv = !empty($r['xPrice']) ? priceToPln((float)$r['xPrice'], $productCurr) : null;

    $converted = $sConv['converted'];
    $curr = $converted ? 'PLN' : $productCurr;
    $s = $sConv['value'];
    $x = $xConv ? $xConv['value'] : null;

    $priceValue = ($type === 'promo' && $x)
        ? $x
        : (($type === 'regular' || !$x) ? $s : $x);

    if ($mode === 'number') {
        return number_format($priceValue, 2, '.', '');
    }

    // bez groszy: ceny przeliczone z EUR oraz kwoty okrągłe (np. 1399 zamiast 1399,00)
    $isWhole = fmod($s, 1.0) === 0.0 && ($x === null || fmod($x, 1.0) === 0.0);
    $decimals = ($converted || $isWhole) ? 0 : 2;
    $sFormatted = number_format($s, $decimals, ',', ' ');
    $xFormatted = $x ? number_format($x, $decimals, ',', ' ') : null;

    return "<div class='".($x ? "productPrice productPrice-promo" : "productPrice")."'>"
         . ($x
            ? "<span class='value productPrice-x'>{$xFormatted}</span><span class='value productPrice-normal'>{$sFormatted}</span>"
            : "<span class='value productPrice-normal'>{$sFormatted}</span>")
         . "<span class='label productPrice-currency'>{$curr}</span>"
         . "</div>";
}


// ------------------------------------------------------
// 3. Ikona koszyka (liczy cookie)
// ------------------------------------------------------
function cartIcon(): string
{
    global $config, $oPage;

    if ((int)$config['shop_page'] === 0) {
        return '';
    }

    $cart  = json_decode($_COOKIE['cart'] ?? '[]', true) ?: [];

    // Obsługa nowego formatu koszyka z kluczami złożonymi
    // Format: {"31_abc": {"product_id": 31, "qty": 2, "filters": {...}}}
    $total = 0;
    foreach ($cart as $key => $item) {
        if (is_array($item) && isset($item['qty'])) {
            $total += (int)$item['qty'];
        } elseif (is_numeric($item)) {
            // Stary format: {"31": 2}
            $total += (int)$item;
        }
    }

    $count = $total > 0 ? "<span class='count'>{$total}</span>" : '';

    return '<div class="bigIcon cartIcon">
        <a href="#" class="cartButton" data-fancybox-slide-up data-src="#cart-widget">
          <img src="'.ICONS.'cart.svg" alt="Koszyk" class="icon invert">
          <span class="label">Koszyk</span>
        </a>'.$count.'</div>';
}



// ------------------------------------------------------
// 4. Cena produktu jako float – helper używany w mailach / zamówieniach
// ------------------------------------------------------
if (!function_exists('order_get_product_price')) {
    function order_get_product_price(int $product_id): float {
        $priceRaw = getPrice($product_id);

        if (!is_string($priceRaw) || $priceRaw === '') {
            $priceRaw = '';
        }

        $normalized = str_replace(' ', '', $priceRaw);
        $normalized = preg_replace('/[^0-9,\.\-]/', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
        $price      = (float)$normalized;

        if ($price <= 0) {
            $xPrice = getData($product_id, 'xPrice');
            $sPrice = getData($product_id, 'sPrice');

            if (!empty($xPrice)) {
                $tmp = str_replace(' ', '', (string)$xPrice);
                $tmp = preg_replace('/[^0-9,\.\-]/', '', $tmp);
                $tmp = str_replace(',', '.', $tmp);
                $price = (float)$tmp;
            } elseif (!empty($sPrice)) {
                $tmp = str_replace(' ', '', (string)$sPrice);
                $tmp = preg_replace('/[^0-9,\.\-]/', '', $tmp);
                $tmp = str_replace(',', '.', $tmp);
                $price = (float)$tmp;
            }
        }

        return $price > 0 ? $price : 0.0;
    }
}


// ------------------------------------------------------
// 5. Lista produktów z zamówienia (HTML do maila / podglądu)
// ------------------------------------------------------
function orderProducts($cartRaw): string
{
    // 1) Normalizacja wejścia: array | JSON | serialize | null
    $cart = [];

    if (is_array($cartRaw)) {
        $cart = $cartRaw;
    } elseif (is_string($cartRaw)) {
        $raw = trim($cartRaw);

        if ($raw !== '') {
            // JSON?
            if ($raw[0] === '{' || $raw[0] === '[') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $cart = $decoded;
                }
            }

            // serialize?
            if (empty($cart) && preg_match('/^(a|s|i|b|d|O|C|N):/', $raw)) {
                $decoded = @unserialize($raw, ['allowed_classes' => false]);
                if (is_array($decoded)) {
                    $cart = $decoded;
                }
            }
        }
    }

    // 2) Obsłuż też format listy (np. [{id:14, qty:1}])
    if (!empty($cart) && array_is_list($cart)) {
        $map = [];
        foreach ($cart as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pid = isset($row['id']) ? (int)$row['id'] : 0;
            $qty = isset($row['qty']) ? (int)$row['qty'] : 0;
            $filters = $row['filters'] ?? [];
            if ($pid > 0 && $qty > 0) {
                $map[$pid] = ['qty' => ($map[$pid]['qty'] ?? 0) + $qty, 'filters' => $filters];
            }
        }
        $cart = $map;
    }

    // 3) Walidacja i filtr - obsługa starego i nowego formatu
    $filtered = [];
    foreach ($cart as $cartKey => $itemData) {
        // Nowy format z kluczem złożonym: "29_abc123"
        if (is_array($itemData) && isset($itemData['product_id'])) {
            $product_id = (int)$itemData['product_id'];
            $qty = (int)($itemData['qty'] ?? 1);
            $filters = $itemData['filters'] ?? [];
        }
        // Stary format z qty/filters ale bez product_id
        elseif (is_array($itemData) && isset($itemData['qty'])) {
            // Klucz może być "29_abc123" lub "29"
            $parts = explode('_', (string)$cartKey);
            $product_id = (int)$parts[0];
            $qty = (int)$itemData['qty'];
            $filters = $itemData['filters'] ?? [];
        }
        // Najstarszy format: {"29": 2}
        elseif (is_numeric($itemData) && ctype_digit((string)$cartKey)) {
            $product_id = (int)$cartKey;
            $qty = (int)$itemData;
            $filters = [];
        }
        else {
            continue;
        }

        if ($product_id <= 0 || $qty < 1) {
            continue;
        }

        // Użyj klucza złożonego, aby zachować różne warianty tego samego produktu
        $filtered[$cartKey] = [
            'product_id' => $product_id,
            'qty' => $qty,
            'filters' => is_array($filters) ? $filters : []
        ];
    }

    if (empty($filtered)) {
        return '<p>Brak produktów w zamówieniu.</p>';
    }

    // Helper do kasy
    $money = static function(float $v): string {
        return number_format($v, 2, ',', ' ') . ' zł';
    };

    $total = 0.0;

    $html  = '<div class="table-responsive">';
    $html .= '<table class="table" border="0" cellspacing="0" cellpadding="6" style="border-collapse:collapse; width:100%">';
    $html .= '<thead><tr>';
    $html .= '<th align="left">Produkt</th>';
    $html .= '<th align="center">Ilość</th>';
    $html .= '<th align="right">Cena jedn.</th>';
    $html .= '<th align="right">Razem</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($filtered as $cartKey => $itemData) {
        $product_id = $itemData['product_id'];
        $qty = $itemData['qty'];
        $filters = $itemData['filters'];

        $name = null;
        if (function_exists('getData')) {
            $name = getData($product_id, 'sName');
        }
        if (!$name) {
            $name = 'Produkt #'.$product_id;
        }

        // Dodaj informację o wybranych filtrach do nazwy produktu
        $filtersText = '';
        if (!empty($filters) && function_exists('formatSelectedFilters')) {
            $filtersJson = json_encode($filters, JSON_UNESCAPED_UNICODE);
            $filtersText = formatSelectedFilters($filtersJson, false);
        }

        $price = 0.0;
        if (function_exists('order_get_product_price')) {
            $price = (float) order_get_product_price($product_id);
        }

        $line  = $price * $qty;
        $total += $line;

        $productDisplay = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
        if ($filtersText) {
            $productDisplay .= '<br><small style="color:#666;">'.htmlspecialchars($filtersText, ENT_QUOTES, 'UTF-8').'</small>';
        }

        $html .= '<tr>';
        $html .= '<td>'.$productDisplay.'</td>';
        $html .= '<td align="center">'.$qty.'</td>';
        $html .= '<td align="right">'.$money($price).'</td>';
        $html .= '<td align="right">'.$money($line).'</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody><tfoot><tr>';
    $html .= '<td colspan="3" align="right"><strong>Suma produktów:</strong></td>';
    $html .= '<td align="right"><strong>'.$money($total).'</strong></td>';
    $html .= '</tr></tfoot></table></div>';

    return $html;
}



// ======================================================
// 6. PAYU REST API – HELPERY
// ======================================================

/**
 * Uzyskanie tokenu autoryzacyjnego PayU (client_credentials)
 */
function payuGetAuthToken(array $config, ?array &$debug = null): ?string {
    $url = ($config['payu_env'] === 'secure')
        ? 'https://secure.payu.com/pl/standard/user/oauth/authorize'
        : 'https://secure.snd.payu.com/pl/standard/user/oauth/authorize';

    $fields = [
        'grant_type'    => 'client_credentials',
        'client_id'     => $config['payu_client_id'],
        'client_secret' => $config['payu_client_secret'],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $debug = [
        'http'       => $httpCode,
        'curl_error' => $curlError,
        'raw'        => $response,
    ];

    if ($curlError || !$response || $httpCode !== 200) {
        return null;
    }

    $json = json_decode($response, true);
    return $json['access_token'] ?? null;
}


/**
 * Tworzy nowe zamówienie w PayU
 */
function payuCreateOrder(array $config, array $orderData): array {
    $tokenDebug = null;
    $token = payuGetAuthToken($config, $tokenDebug);
    if (!$token) {
        return [
            'success'  => false,
            'error'    => 'Brak tokenu PayU',
            'http'     => $tokenDebug['http'] ?? null,
            'response' => [
                'stage'      => 'token',
                'curl_error' => $tokenDebug['curl_error'] ?? null,
                'raw'        => $tokenDebug['raw'] ?? null,
            ]
        ];
    }

    $url = ($config['payu_env'] === 'secure')
        ? 'https://secure.payu.com/api/v2_1/orders'
        : 'https://secure.snd.payu.com/api/v2_1/orders';

    $notifyUrl = rtrim(BASE_URL, '/').'/plugins/shop-payu-notify.php';

    if (!empty($orderData['continueUrl'])) {
        $continueUrl = $orderData['continueUrl'];
    } else {
        $continueUrl = getUrl($config['payment_page']);
    }

    $payload = [
        'notifyUrl'    => $notifyUrl,
        'continueUrl'  => $continueUrl,
        'customerIp'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'merchantPosId'=> $config['payu_pos_id'],
        'description'  => $orderData['description'] ?? ('Zamówienie '.$orderData['extOrderId']),
        'currencyCode' => $config['payu_currency'] ?? 'PLN',
        'totalAmount'  => (int)$orderData['totalAmount'],
        'extOrderId'   => (string)$orderData['extOrderId'],
        'products'     => $orderData['products'] ?? [],
        'buyer'        => [
            'email'     => $orderData['buyer_email'] ?? '',
            'phone'     => $orderData['buyer_phone'] ?? '',
            'firstName' => $orderData['buyer_name'] ?? '',
            'language'  => 'pl',
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer '.$token
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || !$response) {
        return [
            'success'  => false,
            'error'    => 'Błąd tworzenia zamówienia w PayU',
            'http'     => $httpCode,
            'response' => [
                'stage'      => 'order',
                'curl_error' => $curlError,
                'raw'        => $response,
            ]
        ];
    }

    $json = json_decode($response, true);

    if (isset($json['status']['statusCode']) && $json['status']['statusCode'] === 'SUCCESS') {
        return [
            'success'     => true,
            'redirectUri' => $json['redirectUri'] ?? '',
            'orderId'     => $json['orderId'] ?? '',
            'response'    => $json,
            'http'        => $httpCode
        ];
    }

    return [
        'success'  => false,
        'error'    => 'Błąd tworzenia zamówienia w PayU',
        'response' => $json,
        'http'     => $httpCode
    ];
}


/**
 * Weryfikacja podpisu z PayU (OpenPayu-Signature)
 */
function payuVerifySignature(string $header, string $body, string $secondKey): bool {
    $data = [];
    foreach (explode(';', $header) as $part) {
        [$key, $value] = array_map('trim', explode('=', $part) + [null, null]);
        if ($key && $value) $data[$key] = trim($value, '"');
    }

    if (empty($data['signature']) || empty($data['algorithm']) || $data['algorithm'] !== 'MD5') {
        return false;
    }

    $computed = md5($body . $secondKey);
    return hash_equals($computed, $data['signature']);
}




// ------------------------------------------------------
// Walidacja tokenu zamówienia (link do podglądu/statusu)
// ------------------------------------------------------
if (!function_exists('isOrderTokenValid')) {
    /**
     * Sprawdza, czy token z URL zgadza się z danymi zamówienia.
     * Obsługuje zarówno nowy klucz $config['orderKey'],
     * jak i stary $config['secretKey'] (wsteczna kompatybilność).
     *
     * Token = hash('sha256', id|date|KEY)
     */
    function isOrderTokenValid(array $orderRow, $tokenFromUrl, array $config): bool
    {
        $tokenFromUrl = (string)$tokenFromUrl;
        if ($tokenFromUrl === '' || empty($orderRow['id']) || empty($orderRow['date'])) {
            return false;
        }

        $keysToCheck = [];

        if (!empty($config['orderKey'])) {
            $keysToCheck[] = (string)$config['orderKey'];
        }
        if (!empty($config['secretKey'])) {
            // stary sposób generowania tokenu – pozwala na działanie starych linków
            $keysToCheck[] = (string)$config['secretKey'];
        }

        // awaryjnie ostatni fallback – raczej tylko jeśli bardzo chcesz
        if (empty($keysToCheck) && defined('BASE_URL')) {
            $keysToCheck[] = (string)BASE_URL;
        }

        foreach ($keysToCheck as $key) {
            $expected = hash(
                'sha256',
                $orderRow['id'].'|'.$orderRow['date'].'|'.$key
            );

            if (hash_equals($expected, $tokenFromUrl)) {
                return true;
            }
        }

        return false;
    }
}

// ------------------------------------------------------
// Normalizacja statusu płatności zamówienia
// ------------------------------------------------------
if (!function_exists('detectPaymentStatus')) {
    /**
     * Ustala logiczny status płatności na podstawie kolumn w orders:
     * - payment_status (preferowane, tekst lub liczba)
     * - payu_status
     * - status (ogólny status zamówienia – fallback)
     *
     * Zwraca: success | pending | error | unknown
     */
    function detectPaymentStatus(array $orderRow): string
    {
        $raw = null;

        if (isset($orderRow['payment_status'])) {
            $raw = $orderRow['payment_status'];
        } elseif (isset($orderRow['payu_status'])) {
            $raw = $orderRow['payu_status'];
        } elseif (isset($orderRow['status'])) {
            $raw = $orderRow['status'];
        }

        if ($raw === null) {
            return 'unknown';
        }

        $normalized = strtolower(trim((string)$raw));

        // Tekstowe statusy – dopasuj do tego, co zapisujesz w notifyUrl
        if (in_array($normalized, ['paid', 'completed', 'complete', 'success', 'successful', 'finished'], true)) {
            return 'success';
        }
        if (in_array($normalized, ['pending', 'new', 'waiting', 'awaiting', 'in_progress', 'processing'], true)) {
            return 'pending';
        }
        if (in_array($normalized, ['error', 'failed', 'failure', 'canceled', 'cancelled', 'rejected', 'declined'], true)) {
            return 'error';
        }

        // Jeśli status jest liczbowy – prosty mapping
        if (ctype_digit((string)$raw)) {
            $code = (int)$raw;
            if ($code >= 2) {
                return 'success';   // np. 2 = zrealizowane
            } elseif ($code === 1 || $code === 0) {
                return 'pending';   // nowe / w realizacji
            }
        }

        // Domyślnie wszystko inne traktujemy jako błąd
        return 'error';
    }
}


// CSRF dla formularzy sklepu (np. zamówienie)
if (!function_exists('orderInitCsrfToken')) {
    function orderInitCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Spójne pobieranie ceny produktu (używane w koszyku / zamówieniu).
 * Źródło: getPrice(), fallback: xPrice / sPrice.
 */
if (!function_exists('order_get_product_price')) {
    function order_get_product_price(int $product_id): float
    {
        $priceRaw = getPrice($product_id);

        if (!is_string($priceRaw) || $priceRaw === '') {
            $priceRaw = '';
        }

        $normalized = str_replace(' ', '', $priceRaw);
        $normalized = preg_replace('/[^0-9,\.\-]/', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
        $price      = (float)$normalized;

        if ($price <= 0) {
            $xPrice = getData($product_id, 'xPrice');
            $sPrice = getData($product_id, 'sPrice');

            $fallback = $xPrice ?: $sPrice;
            if ($fallback !== null && $fallback !== '') {
                $tmp = str_replace(' ', '', (string)$fallback);
                $tmp = preg_replace('/[^0-9,\.\-]/', '', $tmp);
                $tmp = str_replace(',', '.', $tmp);
                $price = (float)$tmp;
            }
        }

        return $price > 0 ? $price : 0.0;
    }
}

/**
 * Czyści cookie koszyka po złożeniu zamówienia (tylko w zwykłej płatności).
 */
if (!function_exists('order_clear_cart_cookies')) {
    function order_clear_cart_cookies(): string
    {
        $options = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (!headers_sent()) {
            setcookie('cart', '', $options);
            setcookie('cart_message', '', $options);
        }

        return '';
    }
}

/**
 * Prosty logger błędów sklepu (np. zamówienia, PayU).
 * Plik: /logs/orders.log
 */
if (!function_exists('order_log_error')) {
    function order_log_error(string $message, array $context = []): void
    {
        $baseDir = dirname(__DIR__, 1); // katalog główny CMS-a
        $logDir  = $baseDir.'/logs';
        $logFile = $logDir.'/orders.log';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $date = date('Y-m-d H:i:s');
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '-';

        $line = '['.$date.'] ['.$ip.'] '.$message;

        if (!empty($context)) {
            $json = @json_encode($context, JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $line .= ' | '.$json;
            }
        }

        $line .= PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}




?>
