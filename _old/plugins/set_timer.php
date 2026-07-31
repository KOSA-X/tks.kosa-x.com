<?php
session_start();

// Inicjalizacja sesji, jeśli jeszcze nie jest ustawiona
if (!isset($_SESSION['time'])) {
    $_SESSION['time'] = 0; // Czas początkowy
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'start1':
        $_SESSION['time'] = 0; // Start od 00:00
        break;

    case 'start2':
        $_SESSION['time'] = 2700; // Start od 45:00 (45 * 60 = 2700)
        break;

    case 'add1':
        $_SESSION['time'] += 60; // Dodaj 1 minutę
        break;

    case 'subtract1':
        $_SESSION['time'] -= 60; // Odejmij 1 minutę
        break;

    default:
        break;
}

// Upewnij się, że czas nie przekroczy 90 minut (5400 sekund)
if ($_SESSION['time'] > 5400) {
    $_SESSION['time'] = 5400;
}

// Upewnij się, że czas nie spadnie poniżej 0
if ($_SESSION['time'] < 0) {
    $_SESSION['time'] = 0;
}
?>
