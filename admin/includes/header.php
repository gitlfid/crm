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

// 3. Set Variabel Header
$page_title = isset($page_title) ? $page_title : "Helpdesk System";
$username   = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$role_name  = isset($_SESSION['role']) ? $_SESSION['role'] : 'Guest';
$email      = isset($_SESSION['email']) ? $_SESSION['email'] : 'user@example.com';
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
                    colors: { primary: '#4F46E5', primaryDark: '#3730A3' },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.05)',
                        'soft-lg': '0 10px 25px -3px rgba(0, 0, 0, 0.08)'
                    }
                }
            }
        }
    </script>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // 1. LOGIKA DARK MODE (SINKRONISASI TAILWIND & BOOTSTRAP LAMA)
            const darkModeToggle = document.getElementById('darkModeToggle');
            const html = document.documentElement;
            const body = document.body;

            function applyDarkMode(isDark) {
                if (isDark) {
                    html.classList.add('dark'); // Aktifkan Dark Mode Tailwind
                    html.setAttribute('data-bs-theme', 'dark'); // Aktifkan Dark Mode Bootstrap Lama
                    body.classList.add('theme-dark');
                    localStorage.setItem('color-theme', 'dark');
                } else {
                    html.classList.remove('dark');
                    html.setAttribute('data-bs-theme', 'light');
                    body.classList.remove('theme-dark');
                    localStorage.setItem('color-theme', 'light');
                }
            }

            // Cek status saat pertama kali load
            if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                applyDarkMode(true);
            } else {
                applyDarkMode(false);
            }

            // Event saat tombol matahari/bulan ditekan
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', () => {
                    applyDarkMode(!html.classList.contains('dark'));
                });
            }

            // 2. LOGIKA DROPDOWN PROFILE
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            const profileCaret = document.getElementById('profileCaret');

            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('hidden');
                    if (profileCaret) profileCaret.classList.toggle('rotate-180');
                });
                document.addEventListener('click', (e) => {
                    if (!profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {
                        profileDropdown.classList.add('hidden');
                        if (profileCaret) profileCaret.classList.remove('rotate-180');
                    }
                });
            }

            // 3. LOGIKA TOGGLE SIDEBAR (Animasi Buka Tutup)
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const closeBtn = document.getElementById('closeSidebarMobile');

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (window.innerWidth < 1024) {
                        sidebar.classList.toggle('-translate-x-full'); // Mobile Mode
                    } else {
                        sidebar.classList.toggle('is-collapsed'); // Desktop Mini Mode
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
</head>

<body class="bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-slate-200 font-sans antialiased overflow-hidden selection:bg-indigo-500 selection:text-white transition-colors duration-300">
    
    <div id="app" class="flex h-screen w-full overflow-hidden">