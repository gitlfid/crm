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
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', // Menggunakan class 'dark' untuk Dark Mode
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
    
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-slate-200 font-sans antialiased overflow-hidden selection:bg-indigo-500 selection:text-white">
    
    <div id="app" class="flex h-screen w-full overflow-hidden">
        
        <div class="flex flex-col flex-1 w-full overflow-hidden">
            
            <header class="sticky top-0 z-40 flex w-full bg-white/80 backdrop-blur-md dark:bg-[#1A222C]/80 shadow-soft transition-all duration-300 border-b border-slate-100 dark:border-slate-800">
                <div class="flex flex-grow items-center justify-between px-4 py-4 md:px-6 2xl:px-11 h-20">
                    
                    <div class="flex items-center gap-4 sm:gap-6">
                        <button id="sidebarToggle" class="z-50 block rounded-lg p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer transition-colors" title="Toggle Menu">
                             <i class="ph ph-list text-2xl"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-3 2xsm:gap-6">
                        
                        <ul class="flex items-center gap-2">
                             <li>
                                <button id="darkModeToggle" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all" title="Ubah Tema">
                                    <i class="ph ph-moon text-xl dark:hidden"></i>
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
                                <i class="ph ph-caret-down text-slate-400 text-sm hidden lg:block transition-transform duration-200"></i>
                            </div>

                            <div id="profileDropdown" class="hidden absolute right-0 mt-4 flex w-64 flex-col rounded-xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-soft-lg z-50 overflow-hidden transition-all origin-top-right">
                                
                                <div class="px-6 py-5 bg-slate-50 dark:bg-slate-800/50">
                                    <p class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($username) ?></p>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5"><?= htmlspecialchars($email) ?></p>
                                </div>

                                <ul class="flex flex-col gap-1 px-4 py-2">
                                    <li>
                                        <a href="../admin/profile.php" class="flex items-center gap-3.5 rounded-lg px-2 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph ph-user text-xl"></i>
                                            Edit profile
                                        </a>
                                    </li>
                                    <li>
                                        <a href="../admin/settings.php" class="flex items-center gap-3.5 rounded-lg px-2 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph ph-gear text-xl"></i>
                                            Account settings
                                        </a>
                                    </li>
                                </ul>

                                <div class="px-4 my-1">
                                     <div class="border-t border-slate-100 dark:border-slate-700"></div>
                                </div>

                                <div class="px-4 pb-4 pt-1">
                                     <a href="../logout.php" class="flex items-center gap-3.5 rounded-lg px-2 py-2 text-sm font-bold text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                                        <i class="ph ph-sign-out text-xl"></i>
                                        Sign out
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 dark:bg-slate-900 relative">