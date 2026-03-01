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
    <title><?= $page_title ?></title>
    
    <script>
        tailwind = {
            config: {
                darkMode: 'class', // Cukup gunakan 'class' standar Tailwind
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
        // 1. Eksekusi Instan saat Render (Mencegah Flash Putih)
        const html = document.documentElement;
        const currentTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (currentTheme === 'dark' || (!currentTheme && systemPrefersDark)) {
            html.classList.add('dark');
            html.classList.add('theme-dark');
            html.setAttribute('data-bs-theme', 'dark');
        } else {
            html.classList.remove('dark');
            html.classList.remove('theme-dark');
            html.setAttribute('data-bs-theme', 'light');
        }

        // 2. Event Listener untuk Tombol Toggle setelah DOM Load
        document.addEventListener('DOMContentLoaded', () => {
            const darkModeToggle = document.getElementById('darkModeToggle');

            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // Cek apakah saat ini sedang Dark Mode
                    const isDarkMode = html.classList.contains('dark');
                    
                    if (isDarkMode) {
                        // Switch ke Light Mode
                        html.classList.remove('dark');
                        html.classList.remove('theme-dark');
                        html.setAttribute('data-bs-theme', 'light');
                        localStorage.setItem('theme', 'light');
                    } else {
                        // Switch ke Dark Mode
                        html.classList.add('dark');
                        html.classList.add('theme-dark');
                        html.setAttribute('data-bs-theme', 'dark');
                        localStorage.setItem('theme', 'dark');
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

<body class="bg-slate-50 text-slate-800 dark:bg-[#1A222C] dark:text-slate-200 transition-colors duration-300 font-sans">
    
    <div id="app" class="flex h-screen w-full overflow-hidden">