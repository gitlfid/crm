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
                    colors: { primary: '#4F46E5', primaryDark: '#3730A3' }
                }
            }
        }
    </script>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        // 1. Inisialisasi Dark Mode Instan
        const currentTheme = localStorage.getItem('color-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (currentTheme === 'dark' || (!currentTheme && prefersDark)) {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.setAttribute('data-bs-theme', 'light');
        }

        // 2. Fungsi Tombol Dark Mode
        function toggleDarkMode() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
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

        // 3. Event Listener UI (Dropdown & Toggle Sidebar)
        document.addEventListener('DOMContentLoaded', () => {
            if (document.documentElement.classList.contains('dark')) document.body.classList.add('theme-dark');

            // Dropdown Profil
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

            // Toggle Sidebar
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const sidebar = document.getElementById('sidebar');
                    const navbar = document.getElementById('top-navbar');
                    const mainWrapper = document.getElementById('main-wrapper');
                    
                    if (window.innerWidth < 1024) {
                        if(sidebar) sidebar.classList.toggle('-translate-x-full');
                    } else {
                        if(sidebar) sidebar.classList.toggle('is-collapsed');
                        // Menyesuaikan lebar Navbar
                        if(navbar) {
                            navbar.classList.toggle('lg:w-[calc(100%-280px)]');
                            navbar.classList.toggle('lg:w-[calc(100%-88px)]');
                        }
                        // Menyesuaikan margin konten
                        if(mainWrapper) {
                            mainWrapper.classList.toggle('lg:ml-[280px]');
                            mainWrapper.classList.toggle('lg:ml-[88px]');
                        }
                    }
                });
            }
        });
    </script>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 dark:bg-[#1A222C] dark:text-slate-200 font-sans antialiased overflow-x-hidden transition-colors duration-300">

    <header id="top-navbar" class="fixed top-0 right-0 z-40 flex w-full lg:w-[calc(100%-280px)] bg-white/80 backdrop-blur-md dark:bg-[#1A222C]/80 shadow-soft border-b border-slate-100 dark:border-slate-800 transition-all duration-300">
        <div class="flex flex-grow items-center justify-between px-4 py-4 md:px-6 2xl:px-11 h-20">
            
            <div class="flex items-center gap-4 sm:gap-6">
                <button id="sidebarToggle" class="z-50 block rounded-lg p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer transition-colors" title="Toggle Sidebar">
                     <i class="ph ph-list text-2xl"></i>
                </button>
            </div>

            <div class="flex items-center gap-3 2xsm:gap-6">
                
                <ul class="flex items-center gap-2">
                     <li>
                        <button onclick="toggleDarkMode()" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all cursor-pointer" title="Ubah Tema">
                            <i class="ph ph-moon text-xl dark:hidden block"></i>
                            <i class="ph ph-sun text-xl hidden dark:block text-amber-400"></i>
                        </button>
                    </li>
                </ul>

                <div class="relative">
                    <div id="profileBtn" class="flex items-center gap-3 cursor-pointer pl-4 border-l border-slate-100 dark:border-slate-700 transition-colors group">
                        <span class="hidden text-right lg:block">
                            <span class="block text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($username) ?></span>
                            <span class="block text-xs font-medium text-slate-400"><?= ucfirst(htmlspecialchars($role_name)) ?></span>
                        </span>
                        <div class="h-11 w-11 rounded-full overflow-hidden border-2 border-white dark:border-slate-700 ring-2 ring-slate-100 dark:ring-slate-800 shadow-sm transition-all group-hover:ring-indigo-100">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($username) ?>&background=random" alt="User" class="object-cover w-full h-full">
                        </div>
                        <i id="profileCaret" class="ph ph-caret-down text-slate-400 text-sm hidden lg:block transition-transform duration-200"></i>
                    </div>

                    <div id="profileDropdown" class="hidden absolute right-0 mt-4 flex w-64 flex-col rounded-xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-2xl z-[999] overflow-hidden transition-all origin-top-right">
                        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($username) ?></p>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5"><?= htmlspecialchars($email) ?></p>
                        </div>
                        <ul class="flex flex-col gap-1 px-4 py-3">
                            <li><a href="../admin/profile.php" class="flex items-center gap-3.5 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"><i class="ph ph-user text-xl"></i> Edit Profile</a></li>
                            <li><a href="../admin/settings.php" class="flex items-center gap-3.5 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"><i class="ph ph-gear text-xl"></i> Account Settings</a></li>
                        </ul>
                        <div class="px-4 pb-4">
                             <a href="../logout.php" class="flex items-center gap-3.5 rounded-lg px-3 py-2.5 text-sm font-bold text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"><i class="ph ph-sign-out text-xl"></i> Sign out</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <div id="main-wrapper" class="min-h-screen pt-20 lg:ml-[280px] transition-all duration-300 flex flex-col relative">
        <main class="flex-1 p-4 sm:p-6 lg:p-8">