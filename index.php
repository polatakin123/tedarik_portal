<?php
// index.php - Ana sayfa
require 'config.php';

// Tam URL yolu oluştur
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if ($base_path == '/' || $base_path == '\\') {
    $base_path = '';
}

// Kullanıcı giriş yapmışsa rolüne göre ilgili panele yönlendir
if (isset($_SESSION['giris']) && $_SESSION['giris'] === true) {
    $rol = $_SESSION['rol'] ?? '';
    
    switch ($rol) {
        case 'Admin':
            header("Location: {$base_path}/admin/index.php");
            exit;
        case 'Tedarikci':
            header("Location: {$base_path}/tedarikci/index.php");
            exit;
        case 'Sorumlu':
            header("Location: {$base_path}/sorumlu/index.php");
            exit;
        default:
            // Eğer rol belirtilmemişse veya bilinmeyen bir rol ise
            // Ana sayfaya yönlendir
            header("Location: {$base_path}/index.php");
            exit;
    }
}

// Kullanıcı giriş yapmamışsa giris sayfasına yönlendir
header("Location: {$base_path}/giris.php");
exit;
?> 