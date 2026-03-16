<?php
// Set header 200 OK (Ini halaman informasi resmi)
header("HTTP/1.1 200 OK");
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Announcement - Linksfield Networks</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    animation: {
                        'fade-in-up': 'fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'blob': 'blob 10s infinite alternate',
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
            overflow: hidden; /* MENGUNCI LAYAR AGAR TIDAK BISA DI-SCROLL */
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

        /* Gradient Text */
        .text-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Kustom scrollbar untuk area teks jika dilayar sangat kecil (failsafe) */
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark .custom-scroll::-webkit-scrollbar-thumb { background: #475569; }
    </style>
</head>
<body class="h-[100dvh] w-full flex items-center justify-center relative text-slate-800 dark:text-slate-200 transition-colors duration-500 p-4 sm:p-6">

    <div class="absolute top-10 left-10 w-96 h-96 bg-indigo-500/20 rounded-full mix-blend-multiply filter blur-[100px] animate-blob pointer-events-none"></div>
    <div class="absolute bottom-10 right-10 w-96 h-96 bg-emerald-500/20 rounded-full mix-blend-multiply filter blur-[100px] animate-blob animation-delay-2000 pointer-events-none"></div>

    <div class="absolute top-6 right-6 z-50 flex items-center gap-1 bg-white/80 dark:bg-slate-800/80 backdrop-blur-md border border-slate-200 dark:border-slate-700 p-1.5 rounded-full shadow-lg">
        <button onclick="setLang('id')" id="btn-id" class="px-3 py-1.5 rounded-full text-xs font-bold transition-all w-10 text-center">ID</button>
        <button onclick="setLang('en')" id="btn-en" class="px-3 py-1.5 rounded-full text-xs font-bold transition-all w-10 text-center">EN</button>
        <div class="w-px h-5 bg-slate-300 dark:bg-slate-600 mx-1"></div>
        <button onclick="toggleTheme()" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors focus:outline-none">
            <i id="theme-icon" class="ph-bold ph-moon text-lg"></i>
        </button>
    </div>

    <div class="relative z-10 w-full max-w-6xl h-full max-h-[800px] glass-panel rounded-[2rem] sm:rounded-[3rem] shadow-2xl shadow-indigo-900/5 animate-fade-in-up flex flex-col overflow-hidden">
        
        <div class="h-2 w-full bg-gradient-to-r from-indigo-600 via-blue-500 to-emerald-500 shrink-0"></div>

        <div class="flex flex-col lg:flex-row h-full overflow-hidden">
            
            <div class="w-full lg:w-3/5 p-8 sm:p-10 lg:p-14 flex flex-col justify-between h-full overflow-y-auto custom-scroll">
                
                <div class="shrink-0">
                    <div class="flex items-center gap-3 mb-8">
                        <i class="ph-fill ph-buildings text-indigo-600 dark:text-indigo-400 text-3xl sm:text-4xl"></i>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white">PT. Linksfield Networks Indonesia</h1>
                            <p class="text-[10px] sm:text-xs font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-widest mt-0.5 lang-id">Pengumuman Layanan Resmi</p>
                            <p class="text-[10px] sm:text-xs font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-widest mt-0.5 lang-en hidden">Official Service Announcement</p>
                        </div>
                    </div>

                    <div class="space-y-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-medium lang-id">
                        <p><strong>Yth. Pelanggan PT Linksfield Networks Indonesia,</strong></p>
                        <p>Terima kasih atas kepercayaan Anda dalam menggunakan solusi konektivitas kami. Sejalan dengan komitmen perusahaan untuk terus meningkatkan efisiensi dan kecepatan penanganan keluhan (SLA), kami informasikan bahwa <strong class="text-rose-500 dark:text-rose-400">Portal Web Ticketing versi lama kini telah dinonaktifkan.</strong></p>
                        <p>Mulai saat ini, seluruh layanan pelaporan kendala teknis dan permintaan bantuan terpusat secara eksklusif melalui kontak resmi kami di panel kanan.</p>
                        <p>Tim teknis kami selalu memantau saluran tersebut dan siap membantu Anda pada jam operasional kerja. Mohon maaf atas ketidaknyamanan selama masa transisi ini.</p>
                    </div>

                    <div class="space-y-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-medium lang-en hidden">
                        <p><strong>Dear Valued Customers and Partners,</strong></p>
                        <p>Thank you for your continued trust in our connectivity solutions. In line with our commitment to improving efficiency and Service Level Agreements (SLA), please be informed that the <strong class="text-rose-500 dark:text-rose-400">legacy Web Ticketing Portal has now been deactivated.</strong></p>
                        <p>Effective immediately, all technical issue reporting and support requests are centralized exclusively through our official channels on the right panel.</p>
                        <p>Our technical team actively monitors these channels and is ready to assist you during business hours. We sincerely apologize for any inconvenience caused during this transition.</p>
                    </div>
                </div>

                <div class="mt-8 shrink-0">
                    <p class="font-bold text-slate-800 dark:text-slate-200 text-sm lang-id">Hormat Kami,</p>
                    <p class="font-bold text-slate-800 dark:text-slate-200 text-sm lang-en hidden">Sincerely,</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Manajemen PT. Linksfield Networks Indonesia</p>
                    
                    <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest hidden sm:block">&copy; <?= date('Y') ?> Linksfield Networks Indonesia</p>
                        <a href="login.php" class="inline-flex items-center justify-center gap-2 text-xs font-bold text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 transition-colors">
                            <span class="lang-id"><i class="ph-bold ph-lock-key"></i> Login Internal Staff</span>
                            <span class="lang-en hidden"><i class="ph-bold ph-lock-key"></i> Internal Staff Login</span>
                        </a>
                    </div>
                </div>

            </div>

            <div class="w-full lg:w-2/5 bg-slate-50/80 dark:bg-[#151e2e]/80 p-8 sm:p-10 flex flex-col justify-center gap-5 sm:gap-6 border-l border-slate-200 dark:border-slate-700/50">
                
                <h3 class="text-xs font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center mb-2 lang-id">Pilih Saluran Bantuan</h3>
                <h3 class="text-xs font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center mb-2 lang-en hidden">Select Support Channel</h3>

                <a href="mailto:support@linksfield.id" class="group relative bg-white dark:bg-[#1e293b] rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl hover:shadow-indigo-500/10 hover:border-indigo-400 transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform shrink-0">
                            <i class="ph-fill ph-envelope-simple-open text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-0.5 lang-id">Email Support</h3>
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-0.5 lang-en hidden">Email Support</h3>
                            <h2 class="text-lg font-black text-slate-800 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">support@linksfield.id</h2>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium leading-relaxed lang-id">
                        Laporan mendetail, lampiran dokumen, atau eskalasi masalah sistem.
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium leading-relaxed lang-en hidden">
                        For detailed reports, document attachments, or system escalations.
                    </p>
                </a>

                <a href="https://wa.me/6282120159237" target="_blank" class="group relative bg-white dark:bg-[#1e293b] rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl hover:shadow-emerald-500/10 hover:border-emerald-400 transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:scale-110 transition-transform shrink-0">
                            <i class="ph-fill ph-whatsapp-logo text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-0.5 lang-id">WhatsApp Bisnis</h3>
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-0.5 lang-en hidden">WhatsApp Business</h3>
                            <h2 class="text-lg font-black text-slate-800 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">0821-2015-9237</h2>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium leading-relaxed lang-id">
                        Kendala mendesak (urgent) & komunikasi real-time (fast response).
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium leading-relaxed lang-en hidden">
                        For urgent issues and real-time communication (fast response).
                    </p>
                </a>

            </div>
        </div>

    </div>

    <script>
        const html = document.documentElement;
        
        // --- LOGIKA TEMA (DARK/LIGHT) ---
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
        // Inisialisasi Tema
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            setTheme(true);
        } else {
            setTheme(false);
        }
        function toggleTheme() { setTheme(!html.classList.contains('dark')); }

        // --- LOGIKA SWITCH BAHASA (ID/EN) ---
        function setLang(lang) {
            // Sembunyikan/Tampilkan Elemen
            document.querySelectorAll('.lang-id').forEach(el => el.classList.toggle('hidden', lang !== 'id'));
            document.querySelectorAll('.lang-en').forEach(el => el.classList.toggle('hidden', lang !== 'en'));

            // Styling Tombol Toggle (Active/Inactive)
            const activeClass = "bg-indigo-600 text-white shadow-md";
            const inactiveClass = "text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white";

            const btnId = document.getElementById('btn-id');
            const btnEn = document.getElementById('btn-en');

            if (lang === 'id') {
                btnId.className = `px-3 py-1.5 rounded-full text-[11px] font-black transition-all w-10 text-center ${activeClass}`;
                btnEn.className = `px-3 py-1.5 rounded-full text-[11px] font-bold transition-all w-10 text-center cursor-pointer ${inactiveClass}`;
            } else {
                btnEn.className = `px-3 py-1.5 rounded-full text-[11px] font-black transition-all w-10 text-center ${activeClass}`;
                btnId.className = `px-3 py-1.5 rounded-full text-[11px] font-bold transition-all w-10 text-center cursor-pointer ${inactiveClass}`;
            }
            localStorage.setItem('lang', lang);
        }
        // Inisialisasi Bahasa
        setLang(localStorage.getItem('lang') || 'id');
    </script>
</body>
</html>