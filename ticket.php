<?php
// =================================================================
// 1. BACKEND LOGIC (TIDAK DIUBAH SAMA SEKALI)
// =================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

$configPath = __DIR__ . '/config/functions.php';
if (file_exists($configPath)) { include $configPath; }

// Variabel Default
$ticket = null;
$replies = [];
$track_error = "";
$msg_success = "";
$msg_error = "";
$currentQueue = ""; // Variabel Antrian

// Default View
$current_view = 'default'; 

// Cek Navigasi dari URL
if (isset($_GET['view'])) {
    $current_view = $_GET['view'];
}

// LOGIKA 1: TRACKING TICKET
if (isset($_GET['track_id']) && !empty($_GET['track_id'])) {
    $current_view = 'track_result'; 
    $track_id = $conn->real_escape_string($_GET['track_id']);
    
    // Ambil Data Ticket
    $sql = "SELECT * FROM tickets WHERE ticket_code = '$track_id'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $ticket = $result->fetch_assoc();
        
        // --- LOGIKA HITUNG ANTRIAN ---
        if (strtolower($ticket['status']) == 'open') {
            $ticketDbId = intval($ticket['id']);
            $qSql = "SELECT COUNT(*) as pos FROM tickets WHERE status = 'open' AND id <= $ticketDbId";
            $qRes = $conn->query($qSql);
            if ($qRes && $qRow = $qRes->fetch_assoc()) {
                $currentQueue = $qRow['pos'];
            }
        }
        
        // LOGIKA 2: KIRIM BALASAN (REPLY)
        if (isset($_POST['submit_reply'])) {
            
            // Cek Status Ticket
            if (strtolower($ticket['status']) == 'open') {
                $msg_error = "Mohon menunggu antrian. Chat akan terbuka saat status berubah menjadi IN PROGRESS.";
            } 
            elseif (strtolower($ticket['status']) == 'closed' || strtolower($ticket['status']) == 'canceled') {
                $msg_error = "Tiket sudah ditutup, tidak dapat mengirim pesan.";
            }
            else {
                $reply_msg = $conn->real_escape_string($_POST['reply_message']);
                $ticket_id = $ticket['id'];
                $user_name = $ticket['name']; 

                // Upload File Logic
                $attachment = null;
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $uploadOk = true;

                if (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] == 0) {
                    $allowed = 2 * 1024 * 1024; 
                    if ($_FILES['reply_attachment']['size'] <= $allowed) {
                        $fileExt = pathinfo($_FILES['reply_attachment']['name'], PATHINFO_EXTENSION);
                        $cleanName = preg_replace("/[^a-zA-Z0-9]/", "", pathinfo($_FILES['reply_attachment']['name'], PATHINFO_FILENAME));
                        $fileName = time() . '_user_' . $cleanName . '.' . $fileExt;
                        
                        if (move_uploaded_file($_FILES['reply_attachment']['tmp_name'], $uploadDir . $fileName)) {
                            $attachment = $fileName;
                        } else { 
                            $msg_error = "Gagal upload file ke server."; 
                            $uploadOk = false; 
                        }
                    } else { 
                        $msg_error = "File terlalu besar (Max 2MB)."; 
                        $uploadOk = false; 
                    }
                }

                if ($uploadOk) {
                    $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user, message, attachment) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $ticket_id, $user_name, $reply_msg, $attachment);
                    
                    if ($stmt->execute()) {
                        if (function_exists('sendToDiscord')) {
                            $discordFields = [
                                ["name" => "Ticket ID", "value" => $ticket['ticket_code'], "inline" => true],
                                ["name" => "Reply From", "value" => $user_name . " (Customer)", "inline" => true],
                                ["name" => "Message", "value" => (strlen($reply_msg)>900?substr($reply_msg,0,900).'...':$reply_msg)]
                            ];
                            if($attachment) $discordFields[] = ["name" => "Attachment", "value" => "Yes", "inline" => true];
                            $thread_id = isset($ticket['discord_thread_id']) ? $ticket['discord_thread_id'] : null;
                            sendToDiscord("New Reply from Customer", "Customer has replied.", $discordFields, $thread_id);
                        }
                        // [UPDATE] Redirect ke ticket.php
                        header("Location: ticket.php?track_id=$track_id&view=track_result");
                        exit;
                    } else { 
                        $msg_error = "Gagal simpan ke database."; 
                    }
                }
            }
        }

        // Ambil History Chat
        $replies = [];
        $reply_res = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id = " . intval($ticket['id']) . " ORDER BY created_at ASC");
        if ($reply_res) { while($row = $reply_res->fetch_assoc()) { $replies[] = $row; } }

    } else {
        $track_error = "Ticket ID <strong>" . htmlspecialchars($track_id) . "</strong> tidak ditemukan.";
        $current_view = 'track_search'; 
    }
}

// Helper Functions
function formatTextOutput($text) { 
    $allowed_tags = '<br><hr><strong><em><b><i><u><p><span><div>';
    $clean_text = strip_tags($text, $allowed_tags);
    return nl2br($clean_text); 
}

function isImage($file) { return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Helpdesk Portal</title>
    
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
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
        }
        
        .chat-scroll-area::-webkit-scrollbar { width: 6px; }
        .chat-scroll-area::-webkit-scrollbar-track { background: transparent; }
        .chat-scroll-area::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.15); border-radius: 10px; }
        
        /* Utility delay classes */
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
        .delay-2000 { animation-delay: 2000ms; }
        .delay-4000 { animation-delay: 4000ms; }
    </style>
</head>

<body class="min-h-screen w-full bg-slate-50 font-sans text-slate-800 overflow-x-hidden selection:bg-primary selection:text-white relative flex items-center justify-center p-4 lg:p-8">
    
    <div class="fixed inset-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div class="absolute top-[-10%] left-[-10%] w-[40vw] h-[40vw] rounded-full bg-primary/20 mix-blend-multiply filter blur-[80px] animate-blob"></div>
        <div class="absolute top-[20%] right-[-10%] w-[35vw] h-[35vw] rounded-full bg-secondary/20 mix-blend-multiply filter blur-[80px] animate-blob delay-2000"></div>
        <div class="absolute bottom-[-20%] left-[20%] w-[45vw] h-[45vw] rounded-full bg-purple-400/20 mix-blend-multiply filter blur-[80px] animate-blob delay-4000"></div>
        <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23000000\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
    </div>

    <div class="w-full max-w-5xl z-10 relative">

        <?php if($current_view == 'default'): ?>
        <div class="flex flex-col items-center justify-center animate-slide-up">
            
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/60 backdrop-blur-md border border-white/50 shadow-sm text-primary font-semibold text-sm mb-6">
                <i class="ph-fill ph-sparkle"></i> Support Portal
            </div>

            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-slate-900 tracking-tight text-center mb-4 leading-tight">
                Bagaimana kami bisa <br class="hidden md:block" />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary">membantu Anda hari ini?</span>
            </h1>
            
            <p class="text-lg text-slate-500 text-center max-w-xl mx-auto mb-12">
                Pilih layanan di bawah ini. Laporkan masalah baru atau pantau perkembangan tiket Anda secara real-time.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full max-w-4xl mx-auto">
                <a href="?view=create" class="group relative p-8 glass-card rounded-[2rem] hover:-translate-y-2 hover:shadow-[0_30px_60px_-15px_rgba(79,70,229,0.3)] transition-all duration-300 overflow-hidden cursor-pointer">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-indigo-500 text-white flex items-center justify-center text-3xl mb-6 shadow-lg shadow-primary/30 group-hover:scale-110 transition-transform duration-300">
                            <i class="ph-bold ph-plus"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-2">Buat Tiket Baru</h3>
                        <p class="text-slate-500 leading-relaxed">Ajukan pertanyaan, laporkan bug, atau request layanan baru ke tim support kami.</p>
                        <div class="mt-6 flex items-center font-bold text-primary opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                            Mulai Sekarang <i class="ph-bold ph-arrow-right ml-2"></i>
                        </div>
                    </div>
                </a>

                <a href="?view=track_search" class="group relative p-8 glass-card rounded-[2rem] hover:-translate-y-2 hover:shadow-[0_30px_60px_-15px_rgba(14,165,233,0.3)] transition-all duration-300 overflow-hidden cursor-pointer">
                    <div class="absolute inset-0 bg-gradient-to-br from-secondary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-secondary to-cyan-500 text-white flex items-center justify-center text-3xl mb-6 shadow-lg shadow-secondary/30 group-hover:scale-110 transition-transform duration-300">
                            <i class="ph-bold ph-magnifying-glass"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-2">Lacak Status Tiket</h3>
                        <p class="text-slate-500 leading-relaxed">Cek update terbaru, progress pengerjaan, dan balas pesan dari teknisi kami.</p>
                        <div class="mt-6 flex items-center font-bold text-secondary opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                            Lacak Sekarang <i class="ph-bold ph-arrow-right ml-2"></i>
                        </div>
                    </div>
                </a>
            </div>

            <div class="mt-16">
                <a href="login.php" class="text-slate-400 hover:text-primary font-semibold flex items-center gap-2 transition-colors">
                    <i class="ph-fill ph-lock-key text-lg"></i> Admin Panel Login
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if($current_view == 'create'): ?>
        <div class="glass-card rounded-[2rem] p-6 md:p-10 animate-slide-up mx-auto max-w-3xl">
            
            <div class="flex items-center justify-between border-b border-slate-200/60 pb-6 mb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-slate-900">Tiket Baru</h2>
                    <p class="text-slate-500 mt-1">Kami siap membantu memecahkan masalah Anda.</p>
                </div>
                <a href="ticket.php" class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-primary hover:text-white transition-all shadow-sm">
                    <i class="ph-bold ph-x text-lg"></i>
                </a>
            </div>

            <form action="process_ticket.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="space-y-1.5">
                        <label class="text-sm font-bold text-slate-700">Jenis Layanan</label>
                        <div class="relative">
                            <i class="ph-fill ph-stack absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <select name="type" class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all appearance-none cursor-pointer">
                                <option value="support">Technical Support</option>
                                <option value="information">General Information</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-sm font-bold text-slate-700">Email Anda</label>
                        <div class="relative">
                            <i class="ph-fill ph-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="email" name="email" class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all" placeholder="email@perusahaan.com" required>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-sm font-bold text-slate-700">Nama Lengkap</label>
                        <div class="relative">
                            <i class="ph-fill ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="name" class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all" placeholder="John Doe" required>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-sm font-bold text-slate-700">Perusahaan</label>
                        <div class="relative">
                            <i class="ph-fill ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="company" class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all" placeholder="PT. ABC Maju" required>
                        </div>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-sm font-bold text-slate-700">No. WhatsApp / Telepon</label>
                        <div class="relative flex">
                            <span class="inline-flex items-center px-4 py-3.5 bg-slate-100 border border-r-0 border-slate-200 rounded-l-xl text-slate-500 font-semibold">+62</span>
                            <input type="text" name="phone" class="w-full px-4 py-3.5 bg-white/50 border border-slate-200 rounded-r-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all" placeholder="81234567890" required>
                        </div>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-sm font-bold text-slate-700">Judul Kendala (Subject)</label>
                        <input type="text" name="subject" class="w-full px-4 py-3.5 bg-white/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all" placeholder="Singkat, padat, jelas" required>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-sm font-bold text-slate-700">Deskripsi Lengkap</label>
                        <textarea name="description" rows="4" class="w-full px-4 py-3.5 bg-white/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all resize-none" placeholder="Ceritakan detail masalah yang Anda alami..." required></textarea>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-sm font-bold text-slate-700">Lampiran <span class="text-slate-400 font-normal">(Screenshot/Error - Max 2MB)</span></label>
                        <input type="file" name="attachment" class="w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all border border-slate-200 rounded-xl bg-white/50 cursor-pointer">
                    </div>

                </div>

                <div class="pt-6 border-t border-slate-200/60 mt-8">
                    <button type="submit" name="submit_ticket" class="w-full bg-gradient-to-r from-primary to-secondary hover:from-primaryDark hover:to-primary text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg shadow-primary/30 flex justify-center items-center gap-2 transform hover:-translate-y-1">
                        <i class="ph-bold ph-paper-plane-right text-xl"></i> Submit Tiket Sekarang
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if($current_view == 'track_search'): ?>
        <div class="glass-card rounded-[2rem] p-8 md:p-14 text-center animate-slide-up mx-auto max-w-2xl relative">
            
            <a href="ticket.php" class="absolute top-6 right-6 w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-primary hover:text-white transition-all shadow-sm">
                <i class="ph-bold ph-x text-lg"></i>
            </a>

            <div class="w-24 h-24 bg-gradient-to-br from-secondary/20 to-primary/20 rounded-full flex items-center justify-center mx-auto mb-8 animate-pulse">
                <i class="ph-fill ph-radar text-5xl text-primary"></i>
            </div>
            
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Lacak Tiket Anda</h2>
            <p class="text-slate-500 mb-10 text-lg">Masukkan nomor resi / ID tiket untuk melihat status terkini.</p>
            
            <form action="ticket.php" method="GET" class="relative">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                        <i class="ph-bold ph-magnifying-glass text-slate-400 group-focus-within:text-primary text-2xl transition-colors"></i>
                    </div>
                    <input type="text" name="track_id" class="w-full pl-14 pr-4 py-5 bg-white border-2 border-slate-200 rounded-2xl focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/20 transition-all text-center font-mono text-xl font-bold text-slate-800 placeholder:font-sans placeholder:text-slate-400 placeholder:font-normal uppercase shadow-inner" placeholder="LFID-SUP-XXXX" required autocomplete="off">
                </div>
                
                <?php if($track_error): ?>
                    <div class="mt-4 bg-red-50 text-red-600 border border-red-200 text-sm px-4 py-3 rounded-xl flex items-center justify-center gap-2 font-medium animate-fade-in">
                        <i class="ph-fill ph-warning-circle text-lg"></i> 
                        <span><?= $track_error ?></span>
                    </div>
                <?php endif; ?>

                <button type="submit" class="mt-6 w-full bg-slate-900 hover:bg-primary text-white font-bold py-4 rounded-xl transition-all shadow-lg hover:shadow-primary/30 text-lg flex items-center justify-center gap-2">
                    Lacak Sekarang <i class="ph-bold ph-arrow-right"></i>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($current_view == 'track_result' && $ticket): ?>
        <div class="glass-card rounded-[2rem] w-full max-w-5xl h-[95vh] md:h-[85vh] flex flex-col overflow-hidden animate-slide-up shadow-2xl relative border border-white/60">
            
            <div class="bg-white/80 backdrop-blur-lg px-4 md:px-8 py-5 border-b border-slate-200/80 z-20 shrink-0 flex items-center justify-between">
                <div class="flex items-center gap-4 min-w-0">
                    <a href="?view=track_search" class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition-colors shrink-0">
                        <i class="ph-bold ph-arrow-left text-lg"></i>
                    </a>
                    <div class="min-w-0">
                        <h2 class="font-extrabold text-slate-900 text-lg md:text-xl truncate leading-tight"><?= htmlspecialchars($ticket['subject']) ?></h2>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="font-mono text-xs font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded">#<?= $ticket['ticket_code'] ?></span>
                            <span class="text-xs text-slate-400 hidden sm:inline-flex items-center gap-1"><i class="ph-fill ph-calendar-blank"></i> <?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <?php if(!empty($currentQueue)): ?>
                        <div class="hidden md:flex items-center gap-1.5 bg-indigo-50 border border-indigo-100 text-primary px-3 py-1.5 rounded-full text-xs font-bold">
                            <i class="ph-fill ph-users"></i> Antrian: <?= $currentQueue ?>
                        </div>
                    <?php endif; ?>

                    <?php 
                        $st = strtolower($ticket['status']); 
                        $bgClass = ''; $iconClass = '';
                        if($st == 'open') { $bgClass = 'bg-emerald-100 text-emerald-700'; $iconClass = 'ph-check-circle'; }
                        elseif($st == 'progress') { $bgClass = 'bg-amber-100 text-amber-700'; $iconClass = 'ph-spinner gap-1 animate-spin-slow'; }
                        elseif($st == 'closed') { $bgClass = 'bg-slate-200 text-slate-600'; $iconClass = 'ph-lock-key'; }
                        else { $bgClass = 'bg-rose-100 text-rose-700'; $iconClass = 'ph-x-circle'; }
                    ?>
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wide <?= $bgClass ?>">
                        <i class="ph-bold <?= $iconClass ?>"></i> <?= $st ?>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/80 border-b border-slate-200/60 px-4 md:px-8 py-3 shrink-0 z-10">
                <details class="group">
                    <summary class="flex items-center justify-between cursor-pointer list-none text-sm font-semibold text-slate-600 hover:text-primary select-none transition-colors">
                        <span class="flex items-center gap-2"><i class="ph-fill ph-info"></i> Tampilkan Detail Masalah Awal</span>
                        <div class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center transition-transform duration-300 group-open:rotate-180">
                            <i class="ph-bold ph-caret-down text-xs"></i>
                        </div>
                    </summary>
                    <div class="mt-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm text-sm text-slate-600 animate-fade-in">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><strong class="text-slate-800 block mb-1 text-xs uppercase tracking-wider opacity-70">Pelapor</strong> <div class="font-medium"><?= htmlspecialchars($ticket['name']) ?> (<?= htmlspecialchars($ticket['company']) ?>)</div></div>
                            <div><strong class="text-slate-800 block mb-1 text-xs uppercase tracking-wider opacity-70">Kontak</strong> <div class="font-medium"><?= htmlspecialchars($ticket['email']) ?> / <?= htmlspecialchars($ticket['phone']) ?></div></div>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl text-slate-700 leading-relaxed border border-slate-100">
                            <?= formatTextOutput($ticket['description']) ?>
                        </div>
                    </div>
                </details>
            </div>

            <div class="chat-scroll-area flex-1 p-4 md:p-8 overflow-y-auto bg-[#F8FAFC]" id="chatContainer">
                
                <?php if(count($replies) > 0): ?>
                    <div class="space-y-6">
                    <?php foreach($replies as $reply): $isAdmin = ($reply['user'] == 'Admin'); ?>
                        
                        <div class="flex w-full <?= $isAdmin ? 'justify-start' : 'justify-end' ?> animate-fade-in">
                            <div class="flex max-w-[85%] md:max-w-[70%] <?= $isAdmin ? 'flex-row' : 'flex-row-reverse' ?> items-end gap-2 md:gap-3">
                                
                                <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center shrink-0 shadow-sm <?= $isAdmin ? 'bg-gradient-to-br from-primary to-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600' ?>">
                                    <?php if($isAdmin): ?>
                                        <i class="ph-fill ph-headset text-lg md:text-xl"></i>
                                    <?php else: ?>
                                        <i class="ph-fill ph-user text-lg md:text-xl"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-col <?= $isAdmin ? 'items-start' : 'items-end' ?>">
                                    <span class="text-[0.65rem] md:text-xs font-bold text-slate-400 mb-1 px-1">
                                        <?= $isAdmin ? 'Tim Support' : 'Anda' ?> &bull; <?= date('H:i', strtotime($reply['created_at'])) ?>
                                    </span>
                                    
                                    <div class="px-5 py-3.5 shadow-sm text-sm md:text-[0.95rem] leading-relaxed relative <?= $isAdmin ? 'bg-white text-slate-800 rounded-[1.5rem] rounded-bl-sm border border-slate-100' : 'bg-gradient-to-br from-slate-800 to-slate-900 text-white rounded-[1.5rem] rounded-br-sm' ?>">
                                        
                                        <div class="break-words [&>a]:underline [&>a]:font-medium <?= $isAdmin ? '[&>a]:text-primary' : '[&>a]:text-indigo-300' ?>">
                                            <?= formatTextOutput($reply['message']) ?>
                                        </div>

                                        <?php if($reply['attachment']): ?>
                                            <div class="mt-3 pt-3 border-t <?= $isAdmin ? 'border-slate-100' : 'border-white/10' ?>">
                                                <?php if(isImage($reply['attachment'])): ?>
                                                    <a href="uploads/<?= $reply['attachment'] ?>" target="_blank" class="block group relative overflow-hidden rounded-xl bg-black/5">
                                                        <img src="uploads/<?= $reply['attachment'] ?>" class="max-h-48 md:max-h-64 object-cover w-full transition-transform duration-300 group-hover:scale-105">
                                                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <i class="ph-bold ph-arrows-out-simple text-white text-2xl"></i>
                                                        </div>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="uploads/<?= $reply['attachment'] ?>" target="_blank" class="inline-flex items-center gap-2 text-xs font-bold px-4 py-2.5 rounded-xl transition-colors <?= $isAdmin ? 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200' : 'bg-white/10 text-white hover:bg-white/20' ?>">
                                                        <i class="ph-fill ph-file-arrow-down text-lg"></i> Unduh Lampiran
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center text-slate-400">
                        <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm mb-4">
                            <i class="ph-fill ph-chats-teardrop text-4xl text-slate-300"></i>
                        </div>
                        <p class="text-sm font-medium">Belum ada balasan. Tim kami akan segera merespon.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white px-4 py-4 md:px-8 md:py-5 border-t border-slate-200 shrink-0 z-20">
                <?php if($ticket['status'] == 'closed' || $ticket['status'] == 'canceled'): ?>
                    <div class="flex justify-center">
                        <div class="inline-flex items-center gap-2 bg-slate-100 text-slate-500 font-bold px-6 py-3 rounded-2xl text-sm border border-slate-200">
                            <i class="ph-fill ph-lock-key text-lg"></i> Tiket ini telah ditutup
                        </div>
                    </div>
                <?php elseif($ticket['status'] == 'open'): ?>
                    <div class="flex items-center justify-center gap-3 text-amber-600 bg-amber-50 border border-amber-200 p-4 rounded-2xl">
                        <i class="ph-fill ph-hourglass-high text-2xl animate-pulse"></i>
                        <div>
                            <h6 class="font-bold text-sm">Menunggu Tim Support</h6>
                            <p class="text-xs opacity-80">Kolom chat akan terbuka otomatis saat tiket berstatus In Progress.</p>
                        </div>
                    </div>
                <?php else: ?>
                    
                    <?php if($msg_error): ?>
                        <div class="bg-red-50 text-red-600 text-xs p-3 rounded-xl mb-3 border border-red-100 font-bold flex items-center gap-2">
                            <i class="ph-bold ph-warning-circle text-base"></i> <?= $msg_error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="ticket.php?track_id=<?= htmlspecialchars($track_id) ?>&view=track_result" method="POST" enctype="multipart/form-data" class="relative">
                        
                        <div id="filePreview" class="hidden absolute -top-10 left-0 bg-slate-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-lg flex items-center gap-2">
                            <i class="ph-fill ph-file"></i> <span id="fileNameDisplay" class="truncate max-w-[200px]"></span>
                            <button type="button" id="removeFileBtn" class="text-slate-300 hover:text-white ml-1"><i class="ph-bold ph-x"></i></button>
                        </div>

                        <div class="flex items-end gap-3 bg-slate-50 border-2 border-slate-200 rounded-[1.5rem] p-2 focus-within:border-primary focus-within:bg-white focus-within:shadow-[0_0_0_4px_rgba(79,70,229,0.1)] transition-all">
                            
                            <label id="attachBtn" class="flex items-center justify-center w-12 h-12 shrink-0 text-slate-400 bg-white shadow-sm border border-slate-200 hover:text-primary hover:border-primary hover:bg-indigo-50 rounded-[1rem] cursor-pointer transition-all relative">
                                <i class="ph-bold ph-paperclip text-xl"></i>
                                <input type="file" name="reply_attachment" id="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.pdf">
                            </label>
                            
                            <textarea name="reply_message" rows="1" class="flex-1 bg-transparent border-none focus:ring-0 text-sm md:text-base px-2 py-3.5 resize-none max-h-32 min-h-[52px]" placeholder="Ketik pesan balasan Anda di sini..." required></textarea>
                            
                            <button type="submit" name="submit_reply" class="flex items-center justify-center w-12 h-12 shrink-0 bg-primary text-white rounded-[1rem] shadow-md shadow-primary/30 hover:bg-primaryDark hover:scale-105 transition-all">
                                <i class="ph-fill ph-paper-plane-right text-xl"></i>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

        </div>
        <?php endif; ?>

    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // 1. Auto Scroll Chat to bottom smooth
            const chatBox = document.getElementById("chatContainer");
            if(chatBox) {
                setTimeout(() => {
                    chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' });
                }, 100);
            }

            // 2. Auto-resize textarea
            const tx = document.querySelector('textarea[name="reply_message"]');
            if (tx) {
                tx.addEventListener("input", function() {
                    this.style.height = "52px"; 
                    const newHeight = Math.min(this.scrollHeight, 128); 
                    this.style.height = newHeight + "px";
                });
                
                // Submit on Enter (without Shift)
                tx.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.closest('form').submit();
                    }
                });
            }

            // 3. File Upload Custom UI Logic
            const fileInput = document.getElementById('fileInput');
            const attachBtn = document.getElementById('attachBtn');
            const filePreview = document.getElementById('filePreview');
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const removeFileBtn = document.getElementById('removeFileBtn');

            if(fileInput && attachBtn && fileNameDisplay) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        attachBtn.classList.add('ring-2', 'ring-primary', 'text-primary');
                        filePreview.classList.remove('hidden');
                        filePreview.classList.add('animate-fade-in');
                        fileNameDisplay.textContent = this.files[0].name;
                    } else {
                        resetFileInput();
                    }
                });

                if(removeFileBtn) {
                    removeFileBtn.addEventListener('click', function() {
                        resetFileInput();
                    });
                }

                function resetFileInput() {
                    fileInput.value = '';
                    attachBtn.classList.remove('ring-2', 'ring-primary', 'text-primary');
                    filePreview.classList.add('hidden');
                    filePreview.classList.remove('animate-fade-in');
                }
            }
        });
    </script>
</body>
</html>