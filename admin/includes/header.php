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
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#4F46E5', // Indigo 600
                        primaryDark: '#3730A3', // Indigo 800
                    },
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
        const currentTheme = localStorage.getItem('color-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (currentTheme === 'dark' || (!currentTheme && prefersDark)) {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.setAttribute('data-bs-theme', 'light');
        }

        // Fungsi Utama Toggle Dark Mode
        function toggleDarkMode() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            
            if (isDark) {
                // Ubah ke Light Mode
                html.classList.remove('dark');
                html.setAttribute('data-bs-theme', 'light'); // Untuk Bootstrap lama
                if(document.body) document.body.classList.remove('theme-dark');
                localStorage.setItem('color-theme', 'light');
            } else {
                // Ubah ke Dark Mode
                html.classList.add('dark');
                html.setAttribute('data-bs-theme', 'dark'); // Untuk Bootstrap lama
                if(document.body) document.body.classList.add('theme-dark');
                localStorage.setItem('color-theme', 'dark');
            }
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // Terapkan class body untuk Bootstrap CSS lama setelah body selesai dimuat
            if (document.documentElement.classList.contains('dark')) {
                document.body.classList.add('theme-dark');
            }

            // 1. FIX HIERARKI DOM LAYOUT (Agar Sidebar di Kiri & Header di Kanan)
            const sidebar = document.getElementById('sidebar');
            const app = document.getElementById('app');
            const rightPanel = document.getElementById('right-panel');
            
            // Memindahkan Sidebar keluar dari <main> agar layout Flexbox sejajar
            if (sidebar && app && rightPanel && sidebar.parentNode !== app) {
                app.insertBefore(sidebar, rightPanel);
            }

            // 2. LOGIKA DROPDOWN PROFILE
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            const profileCaret = document.querySelector('#profileBtn .ph-caret-down');

            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    profileDropdown.classList.toggle('hidden');
                    if(profileCaret) profileCaret.classList.toggle('rotate-180');
                });

                // Klik di luar dropdown untuk menutup
                document.addEventListener('click', (e) => {
                    if (!profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {
                        profileDropdown.classList.add('hidden');
                        if(profileCaret) profileCaret.classList.remove('rotate-180');
                    }
                });
            }

            // 3. LOGIKA TOGGLE SIDEBAR (Collapse Desktop & Slide Mobile)
            const toggleBtn = document.getElementById('sidebarToggle');
            const closeBtn = document.getElementById('closeSidebarMobile'); // Ada di sidebar.php

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (window.innerWidth < 1024) {
                        sidebar.classList.toggle('-translate-x-full');
                    } else {
                        sidebar.classList.toggle('is-collapsed');
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

<body class="bg-slate-50 text-slate-800 dark:bg-[#1A222C] dark:text-slate-200 font-sans antialiased overflow-hidden selection:bg-indigo-500 selection:text-white transition-colors duration-300">
    
    <div id="app" class="flex h-screen w-full overflow-hidden">
        
        <div id="right-panel" class="flex flex-col flex-1 w-full overflow-hidden relative">
            
            <header class="sticky top-0 z-40 flex w-full bg-white/80 backdrop-blur-md dark:bg-[#1A222C]/80 shadow-soft transition-all duration-300 border-b border-slate-100 dark:border-slate-800">
                <div class="flex flex-grow items-center justify-between px-4 py-4 md:px-6 2xl:px-11 h-20">
                    
                    <div class="flex items-center gap-4 sm:gap-6">
                        <button id="sidebarToggle" class="z-50 block rounded-lg p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer transition-colors" title="Toggle Sidebar">
                             <i class="ph-bold ph-list text-2xl"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-3 2xsm:gap-6">
                        
                        <ul class="flex items-center gap-2">
                             <li>
                                <button id="darkModeToggle" onclick="toggleDarkMode()" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all cursor-pointer" title="Ganti Tema">
                                    <i class="ph-bold ph-moon text-xl block dark:hidden"></i>
                                    <i class="ph-bold ph-sun text-xl hidden dark:block text-amber-400"></i>
                                </button>
                            </li>
                        </ul>

                        <div class="relative">
                            <div id="profileBtn" class="flex items-center gap-3 cursor-pointer pl-4 border-l border-slate-100 dark:border-slate-700 transition-colors group">
                                <span class="hidden text-right lg:block">
                                    <span class="block text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($username) ?></span>
                                    <span class="block text-xs font-medium text-slate-400"><?= ucfirst(htmlspecialchars($role_name)) ?></span>
                                </span>
                                <div class="h-11 w-11 rounded-full overflow-hidden border-2 border-white dark:border-slate-700 ring-2 ring-slate-100 dark:ring-slate-800 shadow-sm transition-all group-hover:ring-indigo-400">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($username) ?>&background=random" alt="User Avatar" class="object-cover w-full h-full">
                                </div>
                                <i class="ph-bold ph-caret-down text-slate-400 text-sm hidden lg:block transition-transform duration-200"></i>
                            </div>

                            <div id="profileDropdown" class="hidden absolute right-0 mt-4 flex w-64 flex-col rounded-xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-2xl z-[999] overflow-hidden transition-all origin-top-right">
                                
                                <div class="px-6 py-5 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700">
                                    <p class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($username) ?></p>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5"><?= htmlspecialchars($email) ?></p>
                                </div>

                                <ul class="flex flex-col gap-1 px-4 py-3">
                                    <li>
                                        <a href="../admin/profile.php" class="flex items-center gap-3.5 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                            <i class="ph-bold ph-user text-xl"></i>
                                            Edit Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a href="../admin/settings.php" class="flex items-center gap-3.5 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                            <i class="ph-bold ph-gear text-xl"></i>
                                            Account Settings
                                        </a>
                                    </li>
                                </ul>

                                <div class="px-4 pb-4 pt-1">
                                     <a href="../logout.php" class="flex items-center gap-3.5 rounded-lg px-3 py-2.5 text-sm font-bold text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                                        <i class="ph-bold ph-sign-out text-xl"></i>
                                        Sign out
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-x-hidden overflow-y-auto relative bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">