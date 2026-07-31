<?php
session_start();


$action = $_POST['action'] ?? null;
$product_id = $_POST['product_id'] ?? null;

if ($product_id && in_array($action, ['add', 'remove', 'delete'])) {
    // Pobierz aktualny koszyk (lub pusty)
    $cart = isset($_COOKIE['cart']) ? json_decode($_COOKIE['cart'], true) : [];

    if ($action === 'add') {
        if (isset($cart[$product_id])) {
            $cart[$product_id] += 1; // Zwiększ ilość produktu
        } else {
            $cart[$product_id] = 1; // Dodaj produkt z ilością 1
        }
         setcookie('cart_message', "<img src='images/icons/cart.svg' alt='Koszyk'>Zaktualizowano koszyk", time() + (1), "/"); // Zapisz do cookies na 7 dni
        
    } elseif ($action === 'remove') {
        if (isset($cart[$product_id])) {
            $cart[$product_id] -= 1; // Zmniejsz ilość
            if ($cart[$product_id] <= 0) {
                unset($cart[$product_id]); // Usuń jeśli ilość spadła do 0
            }
            setcookie('cart_message', "<img src=images/icons/cart.svg' alt='Koszyk'>Zaktualizowano koszyk", time() + (1), "/"); // Zapisz do cookies na 7 dni
        }
        
    } elseif ($action === 'delete') {
        if (isset($cart[$product_id])) {
            unset($cart[$product_id]); // Usuń jeśli ilość spadła do 0
            setcookie('cart_message', "<img src='images/icons/cart.svg' alt='Koszyk'>Zaktualizowano koszyk", time() + (1), "/"); // Zapisz do cookies na 7 dni
        }
    }

    setcookie('cart', json_encode($cart), time() + (86400 * 1), "/"); // Zapisz do cookies na 

//    if ($action === 'add') {
//        header("Location: cart.php"); // Przekierowanie do koszyka po dodaniu
//        exit;
//    }

    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Nieprawidłowa akcja"]);
}
?>
