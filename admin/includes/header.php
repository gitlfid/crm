<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$page_title = isset($page_title) ? $page_title : "Helpdesk System";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <link rel="stylesheet" crossorigin href="../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="../assets/compiled/css/iconly.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            // Membaca mode gelap langsung dari atribut tema bawaan template Mazer
            darkMode: ['class', '[data-bs-theme="dark"]'], 
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5', 
                        primaryDark: '#3730A3', 
                    }
                }
            },
            corePlugins: {
                // PENTING: Mematikan reset CSS bawaan Tailwind agar tidak merusak layout Sidebar Mazer / Bootstrap
                preflight: false, 
            }
        }
    </script>
    
    <style>
        /* Utility tambahan */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="font-sans antialiased">
    <script src="../assets/static/js/initTheme.js"></script>
    
    <div id="app">
        
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.body.addEventListener('click', function(e) {
                    // Jika yang diklik adalah tombol burger (di kiri atas)
                    const burgerBtn = e.target.closest('.burger-btn');
                    if (burgerBtn) {
                        e.preventDefault();
                        const sidebar = document.getElementById('sidebar');
                        if (sidebar) {
                            sidebar.classList.toggle('active');
                        }
                    }

                    // Jika yang diklik adalah tombol tutup sidebar / area gelap
                    const sidebarHideBtn = e.target.closest('.sidebar-hide');
                    if (sidebarHideBtn) {
                        e.preventDefault();
                        const sidebar = document.getElementById('sidebar');
                        if (sidebar) {
                            sidebar.classList.remove('active');
                        }
                    }
                });
            });
        </script>