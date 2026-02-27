<?php
session_start();
include 'config/database.php'; // Pastikan path ini benar

// 1. CEK SESSION LOGIN
// Jika sudah login, kita cek apakah dia wajib ganti password atau tidak
if (isset($_SESSION['user_id'])) {
    // Jika flag force_change_password ada, paksa ke halaman ganti password
    if (isset($_SESSION['force_change_password']) && $_SESSION['force_change_password'] === true) {
        header("Location: admin/change_password_first.php");
        exit;
    }
    // Jika aman, lempar ke dashboard
    header("Location: admin/dashboard.php");
    exit;
}

$error = "";

// 2. PROSES LOGIN
if (isset($_POST['login_btn'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // [UPDATE] Tambahkan 'must_change_password' dalam query SELECT
    $sql = "SELECT id, username, password, role, division_id, must_change_password FROM users WHERE username = ? OR email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verifikasi Password
            if (password_verify($password, $user['password'])) {
                
                // Regenerasi Session ID untuk keamanan
                session_regenerate_id(true);

                // Set Session Utama
                $_SESSION['user_id'] = intval($user['id']);
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; 
                
                // Set Division ID
                $divId = isset($user['division_id']) ? intval($user['division_id']) : 0;
                $_SESSION['division_id'] = $divId;
                
                // [LOGIKA BARU] Cek Wajib Ganti Password
                if ($user['must_change_password'] == 1) {
                    $_SESSION['force_change_password'] = true; // Set Flag Sesi
                    header("Location: admin/change_password_first.php"); // Redirect ke halaman khusus
                    exit;
                }
                
                // Jika tidak wajib ganti password, masuk Dashboard
                header("Location: admin/dashboard.php");
                exit;
            } else {
                $error = "Password salah.";
            }
        } else {
            $error = "Username atau Email tidak ditemukan.";
        }
        $stmt->close();
    } else {
        $error = "Terjadi kesalahan pada sistem database.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Helpdesk Admin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], },
                    colors: {
                        primary: '#4F46E5', // Indigo 600
                        primaryDark: '#3730A3', // Indigo 800
                        secondary: '#0EA5E9', // Indigo-blue alternative
                    },
                    animation: {
                        'blob': 'blob 7s infinite',
                        'slide-up': 'slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                    },
                    keyframes: {
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        .delay-2000 { animation-delay: 2000ms; }
        .delay-4000 { animation-delay: 4000ms; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body class="h-screen w-full bg-slate-50 font-sans text-slate-800 overflow-hidden selection:bg-primary selection:text-white">
    
    <div class="flex flex-col lg:flex-row h-full w-full">
        
        <div class="w-full lg:w-5/12 h-full bg-white shadow-[10px_0_30px_rgba(0,0,0,0.05)] z-20 flex flex-col justify-center px-8 sm:px-16 md:px-24 lg:px-12 xl:px-20 overflow-y-auto relative animate-slide-up">
            
            <div class="w-full max-w-md mx-auto">
                <div class="mb-10">
                    <a href="index.php" class="inline-flex items-center gap-3 group">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all duration-300">
                            <i class="ph-fill ph-lifebuoy text-2xl"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-xl tracking-tight">Helpdesk System</h3>
                    </a>
                </div>

                <h1 class="text-4xl font-extrabold text-slate-900 mb-2 tracking-tight">Log in.</h1>
                <p class="text-slate-500 mb-8 text-lg">Masuk dengan data akun administrator Anda.</p>

                <?php if($error): ?>
                    <div class="bg-red-50 text-red-600 border border-red-200 text-sm px-4 py-3 rounded-xl mb-6 flex items-center gap-3 font-medium animate-fade-in shadow-sm">
                        <i class="ph-fill ph-warning-circle text-xl shrink-0"></i> 
                        <span><?= $error ?></span>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-5">
                    
                    <div class="space-y-1.5 group">
                        <label class="block text-sm font-bold text-slate-700">Username / Email</label>
                        <div class="relative">
                            <i class="ph-fill ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl group-focus-within:text-primary transition-colors"></i>
                            <input type="text" name="username" class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary focus:bg-white transition-all text-slate-800 font-medium placeholder:font-normal placeholder:text-slate-400" placeholder="Masukkan username" required autocomplete="off">
                        </div>
                    </div>

                    <div class="space-y-1.5 group">
                        <label class="block text-sm font-bold text-slate-700">Password</label>
                        <div class="relative">
                            <i class="ph-fill ph-shield-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl group-focus-within:text-primary transition-colors"></i>
                            <input type="password" name="password" class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary focus:bg-white transition-all text-slate-800 font-medium placeholder:font-normal placeholder:text-slate-400" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <div class="flex items-center pt-2">
                        <input id="remember" type="checkbox" class="w-5 h-5 text-primary bg-slate-100 border-slate-300 rounded focus:ring-primary focus:ring-2 cursor-pointer transition-all">
                        <label for="remember" class="ml-3 text-sm font-medium text-slate-600 cursor-pointer select-none">
                            Keep me logged in
                        </label>
                    </div>
                    
                    <div class="pt-6">
                        <button type="submit" name="login_btn" class="w-full bg-gradient-to-r from-primary to-secondary hover:from-primaryDark hover:to-primary text-white font-bold py-4 px-6 rounded-xl transition-all shadow-[0_10px_20px_-10px_rgba(79,70,229,0.5)] hover:shadow-[0_15px_25px_-10px_rgba(79,70,229,0.6)] flex justify-center items-center gap-2 transform hover:-translate-y-0.5 text-lg">
                            Log in <i class="ph-bold ph-sign-in text-xl"></i>
                        </button>
                    </div>
                </form>

                <div class="mt-10 text-center border-t border-slate-100 pt-6">
                    <p class="text-slate-500 font-medium">Bukan Admin? 
                        <a href="index.php" class="text-primary font-bold hover:underline inline-flex items-center gap-1 transition-all hover:gap-2">
                            Kembali ke Beranda <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </p>
                </div>
            </div>

        </div>

        <div class="hidden lg:flex w-7/12 h-full bg-gradient-to-br from-primaryDark to-primary relative items-center justify-center overflow-hidden">
            
            <div class="absolute top-[-10%] left-[-10%] w-[40vw] h-[40vw] rounded-full bg-white/10 mix-blend-overlay filter blur-[80px] animate-blob"></div>
            <div class="absolute top-[20%] right-[-10%] w-[35vw] h-[35vw] rounded-full bg-secondary/30 mix-blend-overlay filter blur-[80px] animate-blob delay-2000"></div>
            <div class="absolute bottom-[-20%] left-[20%] w-[45vw] h-[45vw] rounded-full bg-indigo-400/20 mix-blend-overlay filter blur-[80px] animate-blob delay-4000"></div>
            
            <div class="absolute inset-0 opacity-[0.04]" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>

            <div class="relative z-10 glass-panel p-12 rounded-[2.5rem] max-w-xl text-white shadow-2xl text-center">
                <div class="mb-8 inline-flex items-center justify-center w-24 h-24 rounded-full bg-white/10 border border-white/20 shadow-inner">
                    <i class="ph-fill ph-shield-check text-5xl text-white"></i>
                </div>
                <h2 class="text-4xl font-extrabold mb-4 tracking-tight leading-tight">Admin Workspace</h2>
                <p class="text-indigo-100 text-lg leading-relaxed opacity-90">
                    Kelola operasional perusahaan, tangani tiket bantuan pelanggan, atur invoice, dan pantau performa tim dalam satu platform terintegrasi.
                </p>

                <div class="mt-10 flex items-center justify-center gap-3 opacity-60">
                    <div class="w-2 h-2 rounded-full bg-white"></div>
                    <div class="w-2 h-2 rounded-full bg-white/50"></div>
                    <div class="w-2 h-2 rounded-full bg-white/50"></div>
                </div>
            </div>

        </div>

    </div>

</body>
</html>