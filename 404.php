<?php
// Set header 404 Not Found
header("HTTP/1.0 404 Not Found");
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'float-delayed': 'float 6s ease-in-out 3s infinite',
                        'spin-slow': 'spin 12s linear infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px) rotate(0deg)' },
                            '50%': { transform: 'translateY(-20px) rotate(5deg)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            overflow: hidden; /* Mencegah scroll agar efek parallax sempurna */
        }
        
        /* Glassmorphism Effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .dark .glass-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Teks Gradasi 404 */
        .text-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        #parallax-wrapper { transition: transform 0.1s ease-out; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0f172a] text-slate-800 dark:text-slate-200 h-screen w-screen flex items-center justify-center relative transition-colors duration-500">

    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-indigo-500/20 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-pink-500/20 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="absolute top-20 right-32 text-indigo-400/40 dark:text-indigo-500/30 animate-float text-6xl pointer-events-none">
        <i class="ph-fill ph-planet"></i>
    </div>
    <div class="absolute bottom-32 left-24 text-pink-400/40 dark:text-pink-500/30 animate-float-delayed text-5xl pointer-events-none">
        <i class="ph-fill ph-rocket-launch"></i>
    </div>
    <div class="absolute top-1/3 left-1/3 text-amber-400/30 dark:text-amber-500/20 animate-spin-slow text-4xl pointer-events-none">
        <i class="ph-fill ph-star"></i>
    </div>

    <div id="parallax-wrapper" class="relative z-10 w-full max-w-3xl p-6">
        <div class="glass-card rounded-[2.5rem] shadow-2xl p-10 md:p-16 text-center transform transition-transform hover:scale-[1.02] duration-300">
            
            <div class="w-24 h-24 rounded-full bg-rose-50 dark:bg-rose-500/10 flex items-center justify-center mx-auto mb-6 shadow-inner border border-rose-100 dark:border-rose-500/20">
                <i class="ph-fill ph-warning-circle text-5xl text-rose-500 dark:text-rose-400"></i>
            </div>

            <h1 class="text-7xl md:text-9xl font-black tracking-tighter text-gradient drop-shadow-sm mb-4">
                404
            </h1>

            <h2 class="text-2xl md:text-3xl font-extrabold mb-4 text-slate-800 dark:text-white">
                Halaman Tidak Ditemukan!
            </h2>
            
            <p class="text-slate-500 dark:text-slate-400 font-medium mb-10 max-w-lg mx-auto leading-relaxed">
                Maaf, halaman yang Anda tuju mungkin telah dihapus, dipindahkan, atau Anda salah memasukkan URL.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="javascript:history.back()" class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95 flex items-center justify-center gap-2 group">
                    <i class="ph-bold ph-arrow-left text-lg group-hover:-translate-x-1 transition-transform"></i> Kembali
                </a>
                <a href="login.php" class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition-all shadow-lg shadow-indigo-500/30 active:scale-95 flex items-center justify-center gap-2 group">
                    <i class="ph-bold ph-house text-lg"></i> Beranda Utama
                </a>
            </div>

        </div>
    </div>

    <button onclick="toggleTheme()" class="absolute top-6 right-6 w-12 h-12 rounded-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-center text-slate-600 dark:text-slate-300 hover:scale-110 transition-transform z-50">
        <i id="theme-icon" class="ph-bold ph-moon text-xl"></i>
    </button>

    <script>
        // 1. Logika Mouse Parallax (Membuat Kotak bergerak mengikuti arah kursor)
        document.addEventListener('mousemove', function(e) {
            const wrapper = document.getElementById('parallax-wrapper');
            // Kalkulasi pergerakan sumbu X dan Y berdasarkan posisi tengah layar
            const x = (window.innerWidth / 2 - e.pageX) / 40;
            const y = (window.innerHeight / 2 - e.pageY) / 40;
            
            wrapper.style.transform = `translate(${x}px, ${y}px)`;
        });

        // 2. Logika Auto Dark Mode Bawaan Sistem
        const html = document.documentElement;
        const themeIcon = document.getElementById('theme-icon');

        function setTheme(isDark) {
            if (isDark) {
                html.classList.add('dark');
                themeIcon.classList.replace('ph-moon', 'ph-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                html.classList.remove('dark');
                themeIcon.classList.replace('ph-sun', 'ph-moon');
                localStorage.setItem('theme', 'light');
            }
        }

        // Cek LocalStorage atau Preferensi Sistem saat pertama kali load
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            setTheme(true);
        } else {
            setTheme(false);
        }

        // Fungsi klik Toggle
        function toggleTheme() {
            setTheme(!html.classList.contains('dark'));
        }
    </script>
</body>
</html>