<?php
/**
 * CART API - obsługuje dodawanie/usuwanie produktów z koszyka
 *
 * Format koszyka (nowy z filtrami):
 * {
 *   "31_abc123": {"product_id": 31, "qty": 2, "filters": {"color": "1", "size": "2"}},
 *   "31_def456": {"product_id": 31, "qty": 1, "filters": {"color": "2", "size": "1"}}
 * }
 *
 * Klucz = productId_md5(sortedFiltersJson)
 */

// surowe dane z POST
$actionRaw     = $_POST['action']     ?? null;
$productIdRaw  = $_POST['product_id'] ?? null;
$filtersRaw    = $_POST['filters']    ?? null; // JSON string lub array

// bazowa odpowiedź
$response = [
    'status'  => 'error',
    'message' => 'Nieprawidłowe dane',
];

// walidacja akcji
$allowedActions = ['add', 'remove', 'delete'];
if (!is_string($actionRaw) || !in_array($actionRaw, $allowedActions, true)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// walidacja product_id – tylko dodatnie liczby całkowite
if ($productIdRaw === null || !ctype_digit((string)$productIdRaw) || (int)$productIdRaw <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$action     = $actionRaw;
$product_id = (int)$productIdRaw;

// Parsowanie filtrów
$filters = [];
if ($filtersRaw !== null) {
    if (is_string($filtersRaw)) {
        $decoded = json_decode($filtersRaw, true);
        if (is_array($decoded)) {
            $filters = $decoded;
        }
    } elseif (is_array($filtersRaw)) {
        $filters = $filtersRaw;
    }
}

// Oczyść filtry - tylko niepuste wartości
$cleanFilters = [];
foreach ($filters as $key => $val) {
    $key = trim((string)$key);
    $val = trim((string)$val);
    if ($key !== '' && $val !== '') {
        $cleanFilters[$key] = $val;
    }
}

// Sortuj filtry dla spójnego hasha
ksort($cleanFilters);

/**
 * Generuje unikalny klucz dla pozycji w koszyku
 * Ten sam produkt z różnymi filtrami = różne klucze
 */
function generateCartKey(int $productId, array $filters): string
{
    if (empty($filters)) {
        return (string)$productId . '_0';
    }
    ksort($filters);
    $hash = substr(md5(json_encode($filters)), 0, 8);
    return $productId . '_' . $hash;
}

/**
 * Znajduje istniejący klucz w koszyku dla danego produktu i filtrów
 */
function findCartKey(array $cart, int $productId, array $filters): ?string
{
    $targetKey = generateCartKey($productId, $filters);

    foreach ($cart as $key => $item) {
        if ($key === $targetKey) {
            return $key;
        }
        // Kompatybilność ze starym formatem (sam productId jako klucz)
        if ($key === (string)$productId && empty($filters)) {
            return $key;
        }
    }

    return null;
}

// odczyt koszyka z cookie
$cart = [];
if (!empty($_COOKIE['cart'])) {
    $decoded = json_decode($_COOKIE['cart'], true);
    if (is_array($decoded)) {
        $cart = $decoded;
    }
}

// Migracja starego formatu do nowego
$cart = migrateCartFormat($cart);

/**
 * Migruje stary format koszyka do nowego
 */
function migrateCartFormat(array $cart): array
{
    $newCart = [];

    foreach ($cart as $key => $item) {
        // Nowy format - już zawiera product_id
        if (is_array($item) && isset($item['product_id'])) {
            $newCart[$key] = $item;
            continue;
        }

        // Stary format z qty i filters ale bez product_id
        if (is_array($item) && isset($item['qty'])) {
            $productId = (int)$key;
            if (strpos($key, '_') !== false) {
                $productId = (int)explode('_', $key)[0];
            }
            $filters = $item['filters'] ?? [];
            $newKey = generateCartKey($productId, $filters);
            $newCart[$newKey] = [
                'product_id' => $productId,
                'qty' => (int)$item['qty'],
                'filters' => $filters
            ];
            continue;
        }

        // Najstarszy format: {"31": 2}
        if (is_numeric($item)) {
            $productId = (int)$key;
            $newKey = generateCartKey($productId, []);
            $newCart[$newKey] = [
                'product_id' => $productId,
                'qty' => (int)$item,
                'filters' => []
            ];
        }
    }

    return $newCart;
}

// Generuj klucz dla aktualnej operacji
$cartKey = generateCartKey($product_id, $cleanFilters);

switch ($action) {
    case 'add':
        // Szukaj istniejącej pozycji z tymi samymi filtrami
        $existingKey = findCartKey($cart, $product_id, $cleanFilters);

        if ($existingKey !== null && isset($cart[$existingKey])) {
            // Zwiększ ilość istniejącej pozycji
            $cart[$existingKey]['qty'] = (int)$cart[$existingKey]['qty'] + 1;
        } else {
            // Dodaj nową pozycję
            $cart[$cartKey] = [
                'product_id' => $product_id,
                'qty' => 1,
                'filters' => $cleanFilters
            ];
        }
        $response = ['status' => 'success', 'message' => 'Dodano do koszyka'];
        break;

    case 'remove':
        // Szukaj pozycji do zmniejszenia
        $existingKey = findCartKey($cart, $product_id, $cleanFilters);

        if ($existingKey !== null && isset($cart[$existingKey])) {
            $cart[$existingKey]['qty'] = (int)$cart[$existingKey]['qty'] - 1;
            if ($cart[$existingKey]['qty'] <= 0) {
                unset($cart[$existingKey]);
            }
        }
        $response = ['status' => 'success', 'message' => 'Zaktualizowano koszyk'];
        break;

    case 'delete':
        // Usuń pozycję całkowicie
        $existingKey = findCartKey($cart, $product_id, $cleanFilters);

        if ($existingKey !== null) {
            unset($cart[$existingKey]);
        } else {
            // Fallback: usuń wszystkie pozycje tego produktu (dla kompatybilności)
            foreach ($cart as $key => $item) {
                if (isset($item['product_id']) && (int)$item['product_id'] === $product_id) {
                    unset($cart[$key]);
                    break; // usuń tylko pierwszą znalezioną
                }
            }
        }
        $response = ['status' => 'success', 'message' => 'Usunięto z koszyka'];
        break;
}

// zapis koszyka na 7 dni
setcookie('cart', json_encode($cart, JSON_UNESCAPED_UNICODE), [
    'expires'  => time() + 86400 * 7,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => false, // JS musi móc czytać koszyk
    'samesite' => 'Lax',
]);

// zwracamy JSON do JS
$response['cart'] = $cart;
$response['cart_key'] = $cartKey;

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
