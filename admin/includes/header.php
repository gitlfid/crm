<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Pastikan Koneksi Database Ada
if (!isset($conn)) {
    $db_path = __DIR__ . '/../../config/database.php'; 
    if (file_exists($db_path)) {
        require_once $db_path;
    }
}

// 2. Global User Variables (Dipindah dari sidebar.php agar bisa dipakai di header)
$current_page = basename($_SERVER['PHP_SELF']);
$role_name = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : 'standard';
$username = $_SESSION['username'] ?? 'User';
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Refresh Division ID
$user_division_id = isset($_SESSION['division_id']) ? intval($_SESSION['division_id']) : 0;
if ($user_division_id <= 0 && $user_id > 0 && isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT division_id FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $resDiv = $stmt->get_result();
        if ($rowDiv = $resDiv->fetch_assoc()) {
            $user_division_id = intval($rowDiv['division_id']);
            $_SESSION['division_id'] = $user_division_id;
        }
        $stmt->close();
    }
}

$page_title = isset($page_title) ? $page_title : "Helpdesk System";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <link rel="stylesheet" href="../assets/compiled/css/app.css">
    <link rel="stylesheet" href="../assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="../assets/compiled/css/iconly.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">

    <script>
        // Cek Tema Saat Load
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            document.body.classList.add('theme-dark');
        } else {
            document.documentElement.setAttribute('data-bs-theme', 'light');
        }

        // Fungsi Toggle (Global)
        function toggleTheme() {
            const html = document.documentElement;
            const themeIcon = document.getElementById('theme-icon');
            
            if (html.getAttribute('data-bs-theme') === 'dark') {
                html.setAttribute('data-bs-theme', 'light');
                document.body.classList.remove('theme-dark');
                localStorage.setItem('theme', 'light');
                if(themeIcon) {
                    themeIcon.classList.remove('bi-moon-fill', 'text-white');
                    themeIcon.classList.add('bi-sun-fill');
                }
            } else {
                html.setAttribute('data-bs-theme', 'dark');
                document.body.classList.add('theme-dark');
                localStorage.setItem('theme', 'dark');
                if(themeIcon) {
                    themeIcon.classList.remove('bi-sun-fill');
                    themeIcon.classList.add('bi-moon-fill', 'text-white');
                }
            }
        }
        
        // Update Icon saat DOM Ready
        document.addEventListener('DOMContentLoaded', () => {
            const themeIcon = document.getElementById('theme-icon');
            if(themeIcon && localStorage.getItem('theme') === 'dark') {
                themeIcon.classList.remove('bi-sun-fill');
                themeIcon.classList.add('bi-moon-fill', 'text-white');
            }
        });
    </script>
</head>

<body>
    <div id="app">