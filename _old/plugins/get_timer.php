<?php
session_start();

// Inicjalizacja sesji, jeśli jeszcze nie jest ustawiona
if (!isset($_SESSION['time'])) {
    $_SESSION['time'] = 0; // Czas początkowy
}

// Zwróć czas w formacie mm:ss
echo gmdate("i:s", $_SESSION['time']);
?>





 