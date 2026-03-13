<?php
// Set header 200 OK (Ini halaman informasi resmi)
header("HTTP/1.1 200 OK");
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembaruan Layanan Support - Linksfield Networks</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    animation: {
                        'fade-in-up': 'fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'blob': 'blob 7s infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        
        .dark body { background-color: #0f172a; }
        
        /* Efek Kaca Premium */
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
        
        .dark .glass-panel {
            background: rgba(30, 41, 59, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="min-h-screen w-full flex items-center justify-center p-4 sm:p-8 relative overflow-x-hidden text-slate-800 dark:text-slate-200 transition-colors duration-500">

    <div class="absolute top-0 -left-4 w-72 h-72 bg-indigo-500/30 rounded-full mix-blend-multiply filter blur-[80px] animate-blob pointer-events-none"></div>
    <div class="absolute top-0 -right-4 w-72 h-72 bg-emerald-500/30 rounded-full mix-blend-multiply filter blur-[80px] animate-blob animation-delay-2000 pointer-events-none"></div>
    <div class="absolute -bottom-8 left-20 w-72 h-72 bg-rose-500/30 rounded-full mix-blend-multiply filter blur-[80px] animate-blob animation-delay-4000 pointer-events-none"></div>

    <div class="relative z-10 w-full max-w-4xl glass-panel rounded-[2.5rem] shadow-2xl shadow-indigo-900/5 animate-fade-in-up flex flex-col overflow-hidden">
        
        <div class="h-2 w-full bg-gradient-to-r from-indigo-600 via-blue-500 to-emerald-500"></div>

        <div class="p-8 sm:p-12">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-10 gap-4 border-b border-slate-200 dark:border-slate-700/50 pb-8">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                        <i class="ph-fill ph-buildings text-indigo-600 dark:text-indigo-400 text-3xl"></i>
                        PT. Linksfield Networks Indonesia
                    </h1>
                    <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mt-1 ml-10">Official Service Announcement</p>
                </div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20 text-xs font-bold uppercase tracking-widest shadow-sm">
                    <i class="ph-fill ph-info text-base"></i> Informasi Penting
                </div>
            </div>

            <div class="mb-10 space-y-5 text-sm sm:text-base text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                <p><strong>Yth. Pelanggan dan Mitra Kerja Linksfield Networks,</strong></p>
                
                <p>Pertama-tama, kami mengucapkan terima kasih atas kepercayaan Anda dalam menggunakan layanan konektivitas dan solusi dari PT. Linksfield Networks Indonesia.</p>
                
                <p>Sejalan dengan komitmen kami untuk terus meningkatkan efisiensi, kecepatan respon (<em>SLA</em>), dan kualitas penanganan keluhan pelanggan, kami ingin menginformasikan bahwa <strong>Portal Web Ticketing (Helpdesk) versi lama kini telah dinonaktifkan.</strong></p>
                
                <p>Terhitung mulai saat ini, seluruh layanan pelaporan kendala teknis, pengajuan bantuan (<em>support</em>), maupun konsultasi layanan terpusat secara eksklusif melalui dua saluran resmi kami di bawah ini:</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                
                <a href="mailto:support@linksfield.id" class="group relative bg-white dark:bg-[#1A222C] rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl hover:shadow-indigo-500/10 hover:border-indigo-400 transition-all duration-300 transform hover:-translate-y-1 flex flex-col justify-between">
                    <div class="absolute top-6 right-6 w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-envelope-simple-open text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Via Email Support</h3>
                        <h2 class="text-xl font-black text-slate-800 dark:text-white mb-3 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">support@linksfield.id</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium leading-relaxed max-w-[85%]">
                            Gunakan email untuk laporan yang membutuhkan penjelasan mendetail, lampiran dokumen/screenshot, atau eskalasi masalah sistem.
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-indigo-600 dark:text-indigo-400 font-bold text-sm">
                        Kirim Email Sekarang <i class="ph-bold ph-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>

                <a href="https://wa.me/6282120159237" target="_blank" class="group relative bg-white dark:bg-[#1A222C] rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl hover:shadow-emerald-500/10 hover:border-emerald-400 transition-all duration-300 transform hover:-translate-y-1 flex flex-col justify-between">
                    <div class="absolute top-6 right-6 w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-whatsapp-logo text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Via WhatsApp Business</h3>
                        <h2 class="text-xl font-black text-slate-800 dark:text-white mb-3 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">0821-2015-9237</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium leading-relaxed max-w-[85%]">
                            Gunakan WhatsApp untuk kendala yang bersifat mendesak (urgent), pertanyaan singkat, dan komunikasi real-time (fast response).
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-emerald-600 dark:text-emerald-400 font-bold text-sm">
                        Chat via WhatsApp <i class="ph-bold ph-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>

            </div>

            <div class="text-sm text-slate-600 dark:text-slate-400 font-medium leading-relaxed bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 text-center sm:text-left">
                Tim teknis kami selalu memantau kedua saluran di atas dan siap membantu Anda pada jam operasional kerja.<br><br>
                Kami memohon maaf atas ketidaknyamanan yang mungkin terjadi selama masa transisi ini. Terima kasih atas pengertian dan kerjasamanya.<br><br>
                <span class="font-bold text-slate-800 dark:text-slate-200">Hormat Kami,</span><br>
                Manajemen PT. Linksfield Networks Indonesia
            </div>

        </div>

        <div class="px-8 py-5 border-t border-slate-200 dark:border-slate-700/50 bg-slate-50 dark:bg-slate-900/50 flex items-center justify-between">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest hidden sm:block">&copy; <?= date('Y') ?> Linksfield System</p>
            <a href="login.php" class="w-full sm:w-auto px-6 py-2.5 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 active:scale-95">
                <i class="ph-bold ph-sign-in"></i> Halaman Login Staff
            </a>
        </div>

    </div>

    <button onclick="toggleTheme()" class="absolute top-6 right-6 w-12 h-12 rounded-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-center text-slate-600 dark:text-slate-300 hover:scale-110 transition-transform z-50 focus:outline-none">
        <i id="theme-icon" class="ph-bold ph-moon text-xl"></i>
    </button>

    <script>
        // Logika Tema Otomatis & Toggle
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

        // Cek LocalStorage
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            setTheme(true);
        } else {
            setTheme(false);
        }

        function toggleTheme() {
            setTheme(!html.classList.contains('dark'));
        }
    </script>
</body>
</html>