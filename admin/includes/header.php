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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <script>
        tailwind = {
            config: {
                darkMode: 'class', // Standar Tailwind
                theme: {
                    extend: {
                        colors: {
                            primary: '#4F46E5', 
                            primaryDark: '#3730A3', 
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">
    
    <script>
        // 1. FUNGSI UTAMA TEMA (Dibuat Global dan Anti-Error)
        const html = document.documentElement;
        
        function applyTheme(isDark) {
            if (isDark) {
                // Terapkan Mode Gelap
                html.classList.add('dark', 'theme-dark');
                html.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                if (document.body) document.body.classList.add('theme-dark');
            } else {
                // Terapkan Mode Terang
                html.classList.remove('dark', 'theme-dark');
                html.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('theme', 'light');
                if (document.body) document.body.classList.remove('theme-dark');
            }
        }

        // 2. EKSEKUSI INSTAN (Mencegah Layar Berkedip Putih saat Refresh)
        const currentTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (currentTheme === 'dark' || (!currentTheme && systemPrefersDark)) {
            applyTheme(true);
        } else {
            applyTheme(false);
        }

        // Fungsi yang akan dipanggil saat tombol diklik
        window.toggleDarkMode = function() {
            const isCurrentlyDark = html.classList.contains('dark');
            applyTheme(!isCurrentlyDark);
        };

        // 3. BINDING EVENT SAAT DOM SIAP
        document.addEventListener('DOMContentLoaded', () => {
            // Pastikan body mendapatkan class yang benar jika telat dimuat
            if (html.classList.contains('dark')) {
                document.body.classList.add('theme-dark');
            } else {
                document.body.classList.remove('theme-dark');
            }

            // Deteksi Tombol Toggle
            const darkModeBtn = document.getElementById('darkModeToggle');
            if (darkModeBtn) {
                // Kita menimpa aksi klik agar pasti jalan
                darkModeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation(); // Mencegah event bentrok dengan elemen lain
                    window.toggleDarkMode();
                });
            }
        });
    </script>
    
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 dark:bg-[#1A222C] dark:text-slate-200 transition-colors duration-300 font-sans">
    
    <div id="app" class="flex h-screen w-full overflow-hidden">