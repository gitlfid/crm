<?php
// Ambil URL yang sedang diakses
$request = $_SERVER['REQUEST_URI'];

// Blokir akses ke file lama dan lemparkan ke halaman 404
if (strpos($request, 'ticket.php') !== false || strpos($request, 'track_ticket.php') !== false) {
    header("HTTP/1.0 404 Not Found");
    header("Location: 404.php");
    exit();
}

// Redirect default halaman utama aplikasi ke login
header("Location: login.php");
exit();
?>