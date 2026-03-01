<?php
// 1. Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cek Login (Keamanan)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$page_title = isset($page_title) ? $page_title : "Helpdesk System";
?>
<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <link rel="stylesheet" href="../assets/compiled/css/app.css">
    <link rel="stylesheet" href="../assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="../assets/compiled/css/iconly.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', 
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#4F46E5', primaryDark: '#3730A3' }
                }
            }
        }
    </script>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        // 1. INIT DARK MODE (Mencegah layar berkedip putih saat direfresh)
        const currentTheme = localStorage.getItem('color-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (currentTheme === 'dark' || (!currentTheme && prefersDark)) {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-bs-theme', 'dark'); // Sinkron CSS lama
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.setAttribute('data-bs-theme', 'light');
        }

        // Fungsi Tombol Switch Dark Mode
        function toggleDarkMode() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            if (isDark) {
                html.classList.remove('dark');
                html.setAttribute('data-bs-theme', 'light');
                if(document.body) document.body.classList.remove('theme-dark');
                localStorage.setItem('color-theme', 'light');
            } else {
                html.classList.add('dark');
                html.setAttribute('data-bs-theme', 'dark');
                if(document.body) document.body.classList.add('theme-dark');
                localStorage.setItem('color-theme', 'dark');
            }
        }

        // 2. DOM READY (Menjalankan fungsi interaktif setelah HTML selesai dimuat)
        document.addEventListener('DOMContentLoaded', () => {
            
            // Terapkan class background untuk komponen lama
            if (document.documentElement.classList.contains('dark')) {
                document.body.classList.add('theme-dark');
            }

            // Aksi Dropdown Profil
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            const profileCaret = document.getElementById('profileCaret');

            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('hidden');
                    if(profileCaret) profileCaret.classList.toggle('rotate-180');
                });
                document.addEventListener('click', (e) => {
                    if (!profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {
                        profileDropdown.classList.add('hidden');
                        if(profileCaret) profileCaret.classList.remove('rotate-180');
                    }
                });
            }

            // Aksi Buka/Tutup Sidebar (Hamburger)
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const closeBtn = document.getElementById('closeSidebarMobile');

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (window.innerWidth < 1024) {
                        sidebar.classList.toggle('-translate-x-full'); // Geser di Mobile
                    } else {
                        sidebar.classList.toggle('is-collapsed'); // Mengecil di Desktop
                    }
                });
            }
            if (closeBtn && sidebar) {
                closeBtn.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                });
            }
        });
    </script>

    <style>
        /* Menyembunyikan scrollbar agar UI bersih */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 dark:bg-[#1A222C] dark:text-slate-200 font-sans antialiased overflow-hidden selection:bg-indigo-500 selection:text-white transition-colors duration-300">
    
    <div id="app" class="flex h-screen w-full overflow-hidden">