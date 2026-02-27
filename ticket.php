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

// --- [OPTIMASI MOBILE] LOGIKA TAMPILAN BERBASIS TAILWIND ---
$is_default_page = ($current_view == 'default');
$left_col_class  = $is_default_page ? 'flex' : 'hidden lg:flex';
$right_col_class = $is_default_page ? 'hidden lg:flex' : 'flex';
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
                        primary: '#435ebe',
                        primaryDark: '#25396f',
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Custom scrollbar untuk Tailwind */
        .chat-scroll-area::-webkit-scrollbar { width: 6px; }
        .chat-scroll-area::-webkit-scrollbar-track { background: transparent; }
        .chat-scroll-area::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.2); border-radius: 10px; }
        
        /* Animasi */
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide-up { animation: slideUp 0.4s ease-out forwards; }
    </style>
</head>

<body class="h-screen w-full bg-slate-50 font-sans text-slate-800 overflow-hidden selection:bg-primary selection:text-white">
    
    <div class="flex flex-col lg:flex-row h-full w-full">
        
        <div class="lg:w-5/12 w-full h-full bg-white shadow-[10px_0_30px_rgba(0,0,0,0.05)] z-20 flex-col justify-center p-8 lg:p-16 overflow-y-auto <?= $left_col_class ?>">
            
            <div class="mb-10">
                <h3 class="font-bold text-primary text-2xl flex items-center gap-3">
                    <i class="ph-fill ph-lifebuoy text-3xl"></i> Helpdesk System
                </h3>
            </div>

            <h1 class="text-4xl font-extrabold text-slate-900 mb-2 tracking-tight">Welcome</h1>
            <p class="text-slate-500 text-lg mb-10">Silakan pilih menu bantuan di bawah ini.</p>

            <div class="grid gap-4">
                <a href="?view=create" class="group flex items-center p-5 rounded-2xl border-2 transition-all duration-300 <?= ($current_view=='create') ? 'bg-primary border-primary text-white shadow-lg shadow-primary/30' : 'bg-white border-slate-200 text-slate-700 hover:border-primary hover:bg-slate-50' ?>">
                    <div class="flex items-center justify-center w-12 h-12 rounded-full <?= ($current_view=='create') ? 'bg-white/20 text-white' : 'bg-indigo-100 text-primary group-hover:bg-primary group-hover:text-white transition-colors' ?>">
                        <i class="ph-bold ph-plus-circle text-2xl"></i>
                    </div>
                    <span class="ml-4 font-bold text-lg">Buat Ticket Baru</span>
                </a>
                
                <a href="?view=track_search" class="group flex items-center p-5 rounded-2xl border-2 transition-all duration-300 <?= ($current_view=='track_search' || $current_view=='track_result') ? 'bg-primary border-primary text-white shadow-lg shadow-primary/30' : 'bg-white border-slate-200 text-slate-700 hover:border-primary hover:bg-slate-50' ?>">
                    <div class="flex items-center justify-center w-12 h-12 rounded-full <?= ($current_view=='track_search' || $current_view=='track_result') ? 'bg-white/20 text-white' : 'bg-indigo-100 text-primary group-hover:bg-primary group-hover:text-white transition-colors' ?>">
                        <i class="ph-bold ph-magnifying-glass text-2xl"></i>
                    </div>
                    <span class="ml-4 font-bold text-lg">Lacak Status Ticket</span>
                </a>
            </div>

            <div class="mt-auto pt-12 text-center">
                <p class="text-slate-500 text-sm">Staff Administrator? <a href="login.php" class="font-bold text-primary hover:underline">Login Disini</a>.</p>
            </div>
        </div>
        
        <div class="lg:w-7/12 w-full h-full bg-gradient-to-br from-primary to-primaryDark flex items-center justify-center p-4 lg:p-8 relative overflow-y-auto <?= $right_col_class ?>">
            
            <?php if($current_view == 'default'): ?>
            <div class="text-center text-white animate-slide-up">
                <div class="mb-6 flex justify-center"><i class="ph-fill ph-chats-circle text-white/80 text-8xl"></i></div>
                <h2 class="text-4xl font-extrabold mb-3 tracking-tight">Halo! Ada yang bisa kami bantu?</h2>
                <p class="text-xl text-white/80">Pilih menu di sebelah kiri untuk memulai.</p>
            </div>
            <?php endif; ?>

            <?php if($current_view == 'create'): ?>
            <div class="w-full max-w-2xl bg-white rounded-[1.5rem] shadow-2xl p-6 lg:p-10 animate-slide-up relative">
                
                <div class="lg:hidden mb-6">
                    <a href="ticket.php" class="inline-flex items-center gap-2 text-sm font-bold text-primary bg-indigo-50 px-4 py-2 rounded-lg border border-indigo-100">
                        <i class="ph-bold ph-arrow-left"></i> Kembali
                    </a>
                </div>

                <div class="border-b border-slate-200 pb-5 mb-6">
                    <h4 class="text-2xl font-bold text-primary">Buat Ticket Baru</h4>
                    <p class="text-slate-500 text-sm mt-1">Isi formulir di bawah ini untuk melaporkan masalah Anda.</p>
                </div>

                <form action="process_ticket.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Jenis Ticket</label>
                            <select name="type" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all text-sm">
                                <option value="support">Support</option>
                                <option value="information">Information</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Email</label>
                            <input type="email" name="email" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Perusahaan</label>
                            <input type="text" name="company" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Nama Anda</label>
                            <input type="text" name="name" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all text-sm" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">No. Telepon / WhatsApp</label>
                            <input type="text" name="phone" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all text-sm" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Judul (Subject)</label>
                            <input type="text" name="subject" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all text-sm" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Deskripsi Masalah</label>
                            <textarea name="description" rows="4" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all text-sm resize-none" required></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Lampiran <span class="text-slate-400 font-normal">(Opsional, Max 2MB)</span></label>
                            <input type="file" name="attachment" class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-primary hover:file:bg-indigo-100 transition-all border border-slate-200 rounded-xl bg-slate-50">
                        </div>
                    </div>
                    <div class="pt-4">
                        <button type="submit" name="submit_ticket" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3.5 px-4 rounded-xl transition-colors shadow-lg shadow-primary/30 flex justify-center items-center gap-2">
                            <i class="ph-bold ph-paper-plane-right text-lg"></i> Kirim Ticket
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if($current_view == 'track_search'): ?>
            <div class="w-full max-w-lg bg-white rounded-[1.5rem] shadow-2xl p-8 lg:p-12 text-center animate-slide-up relative">
                
                <div class="lg:hidden mb-8 text-left">
                    <a href="ticket.php" class="inline-flex items-center gap-2 text-sm font-bold text-primary bg-indigo-50 px-4 py-2 rounded-lg border border-indigo-100">
                        <i class="ph-bold ph-arrow-left"></i> Kembali
                    </a>
                </div>

                <div class="mb-6 flex justify-center text-primary">
                    <div class="bg-indigo-50 p-5 rounded-full">
                        <i class="ph-bold ph-magnifying-glass text-6xl"></i>
                    </div>
                </div>
                <h3 class="text-3xl font-extrabold text-slate-900 tracking-tight">Lacak Status</h3>
                <p class="text-slate-500 mt-2 mb-8">Masukkan Nomor ID Ticket Anda untuk melihat progres dan update terbaru.</p>
                
                <form action="ticket.php" method="GET">
                    <div class="mb-5 text-left">
                        <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider pl-1">Nomor Ticket</label>
                        <input type="text" name="track_id" class="w-full px-4 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-primary focus:ring-0 transition-all text-center font-mono text-lg font-semibold text-slate-800 placeholder:font-sans placeholder:text-slate-400 placeholder:font-normal" placeholder="Contoh: LFID-SUP-xxxx" required>
                    </div>
                    <?php if($track_error): ?>
                        <div class="bg-red-50 text-red-600 border border-red-200 text-sm px-4 py-3 rounded-xl mb-5 flex items-center justify-center gap-2 font-medium">
                            <i class="ph-fill ph-warning-circle text-lg"></i> 
                            <span><?= $track_error ?></span>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-4 rounded-xl transition-colors shadow-lg shadow-primary/30 text-lg">
                        Cari Ticket
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if($current_view == 'track_result' && $ticket): ?>
            <div class="w-full max-w-4xl h-[90vh] lg:h-[85vh] bg-slate-100 rounded-[1.5rem] shadow-2xl flex flex-col overflow-hidden animate-slide-up relative">
                
                <div class="bg-white px-5 py-4 lg:px-6 lg:py-5 border-b border-slate-200 z-10 shrink-0 shadow-sm">
                    <div class="flex flex-wrap md:flex-nowrap justify-between items-start md:items-center gap-4">
                        <div class="flex items-center gap-4 min-w-0">
                            <a href="?view=track_search" class="flex items-center justify-center w-10 h-10 rounded-full bg-slate-100 text-slate-500 hover:bg-primary hover:text-white transition-colors shrink-0">
                                <i class="ph-bold ph-arrow-left text-lg"></i>
                            </a>
                            <div class="min-w-0">
                                <h5 class="font-bold text-slate-900 text-lg leading-tight truncate pr-4"><?= htmlspecialchars($ticket['subject']) ?></h5>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="font-mono text-xs font-semibold text-slate-500">#<?= $ticket['ticket_code'] ?></span>
                                    <span class="text-xs text-slate-400 flex items-center gap-1 hidden md:flex"><i class="ph-fill ph-clock"></i> <?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <?php if(!empty($currentQueue)): ?>
                                <span class="bg-white border border-primary text-primary px-3 py-1.5 rounded-full text-xs font-bold shadow-sm flex items-center gap-1.5">
                                    <i class="ph-fill ph-users text-sm"></i> <span class="hidden sm:inline">Antrian:</span> <?= $currentQueue ?>
                                </span>
                            <?php endif; ?>

                            <?php 
                                $st = strtolower($ticket['status']); 
                                $bgClass = '';
                                if($st == 'open') $bgClass = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                                elseif($st == 'progress') $bgClass = 'bg-amber-100 text-amber-700 border-amber-200';
                                elseif($st == 'closed') $bgClass = 'bg-slate-200 text-slate-600 border-slate-300';
                                else $bgClass = 'bg-rose-100 text-rose-700 border-rose-200';
                            ?>
                            <span class="px-3 py-1.5 rounded-full border text-xs font-bold uppercase tracking-wide <?= $bgClass ?>">
                                <?= $st ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-slate-100 pl-[3.5rem]">
                        <details class="group">
                            <summary class="flex items-center cursor-pointer list-none text-sm font-semibold text-primary select-none">
                                <span>Lihat Detail Laporan</span>
                                <span class="transition group-open:rotate-180 ml-1"><i class="ph-bold ph-caret-down"></i></span>
                            </summary>
                            <div class="mt-3 bg-slate-50 p-4 rounded-xl border border-slate-100 text-sm text-slate-600">
                                <div class="mb-2"><strong class="text-slate-800">Pelapor:</strong> <?= htmlspecialchars($ticket['name']) ?> (<?= htmlspecialchars($ticket['company']) ?>)</div>
                                <div class="mb-3"><strong class="text-slate-800">Kontak:</strong> <?= htmlspecialchars($ticket['email']) ?> / <?= htmlspecialchars($ticket['phone']) ?></div>
                                <div class="font-medium text-slate-800 mb-1">Deskripsi Masalah:</div>
                                <div class="opacity-90 max-h-32 overflow-y-auto text-sm leading-relaxed"><?= formatTextOutput($ticket['description']) ?></div>
                            </div>
                        </details>
                    </div>
                </div>

                <div class="chat-scroll-area flex-1 p-4 lg:p-6" id="chatContainer" style="background-color: #f1f5f9; background-image: url('data:image/svg+xml,%3Csvg width=\'100\' height=\'100\' viewBox=\'0 0 100 100\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z\' fill=\'%23e2e8f0\' fill-opacity=\'0.5\' fill-rule=\'evenodd\'/%3E%3C/svg%3E');">
                    
                    <?php if(count($replies) > 0): ?>
                        <?php foreach($replies as $reply): $isAdmin = ($reply['user'] == 'Admin'); ?>
                            
                            <div class="flex items-end w-full mb-6 <?= $isAdmin ? 'justify-start' : 'justify-end' ?>">
                                
                                <?php if($isAdmin): ?>
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 mr-3 bg-white text-primary shadow-sm border border-slate-100 z-10">
                                        <i class="ph-fill ph-headset text-xl"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="max-w-[80%] flex flex-col <?= $isAdmin ? 'items-start' : 'items-end' ?>">
                                    <div class="px-5 py-3.5 shadow-sm text-[0.95rem] leading-relaxed relative <?= $isAdmin ? 'bg-white text-slate-800 rounded-2xl rounded-bl-none border border-slate-100' : 'bg-primary text-white rounded-2xl rounded-br-none' ?>">
                                        
                                        <div class="font-bold text-xs mb-1.5 <?= $isAdmin ? 'text-primary' : 'text-indigo-200' ?>">
                                            <?= $isAdmin ? 'Support Team' : 'Anda' ?>
                                        </div>
                                        
                                        <div class="<?= $isAdmin ? '' : 'text-indigo-50' ?> break-words">
                                            <?= formatTextOutput($reply['message']) ?>
                                        </div>

                                        <?php if($reply['attachment']): ?>
                                            <div class="mt-3 pt-3 border-t <?= $isAdmin ? 'border-slate-100' : 'border-indigo-500/50' ?>">
                                                <?php if(isImage($reply['attachment'])): ?>
                                                    <a href="uploads/<?= $reply['attachment'] ?>" target="_blank" class="block">
                                                        <img src="uploads/<?= $reply['attachment'] ?>" class="rounded-lg max-h-48 object-cover border <?= $isAdmin ? 'border-slate-200' : 'border-indigo-400' ?> bg-white/10 p-0.5 hover:opacity-90 transition">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="uploads/<?= $reply['attachment'] ?>" target="_blank" class="inline-flex items-center gap-2 text-xs font-semibold px-3 py-2 rounded-lg transition-colors <?= $isAdmin ? 'bg-slate-50 text-slate-700 hover:bg-slate-100' : 'bg-indigo-700 text-white hover:bg-indigo-800' ?>">
                                                        <i class="ph-bold ph-download-simple text-base"></i> Download File
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-[0.7rem] text-slate-400 mt-1 font-medium px-1"><?= date('H:i', strtotime($reply['created_at'])) ?></span>
                                </div>

                            </div>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="h-full flex flex-col items-center justify-center text-slate-400">
                            <i class="ph-fill ph-chat-circle-dots text-6xl mb-3 opacity-30"></i>
                            <p class="text-sm font-medium">Belum ada percakapan dimulai.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white border-t border-slate-200 p-4 shrink-0">
                    <?php if($ticket['status'] == 'closed' || $ticket['status'] == 'canceled'): ?>
                        <div class="text-center py-3">
                            <span class="inline-flex items-center gap-2 bg-slate-100 text-slate-500 font-bold px-4 py-2 rounded-full text-sm border border-slate-200">
                                <i class="ph-fill ph-lock-key"></i> Tiket Ditutup
                            </span>
                        </div>
                    <?php elseif($ticket['status'] == 'open'): ?>
                        <div class="text-center py-2 flex flex-col items-center justify-center">
                            <i class="ph-fill ph-hourglass-high text-primary text-2xl mb-1 animate-pulse"></i>
                            <h6 class="font-bold text-slate-800 text-sm">Menunggu Antrian</h6>
                            <p class="text-xs text-slate-500 mt-0.5">Chat akan terbuka otomatis saat petugas memulai pengerjaan.</p>
                        </div>
                    <?php else: ?>
                        
                        <?php if($msg_error): ?>
                            <div class="bg-red-50 text-red-600 text-xs p-2 rounded mb-2 border border-red-100 font-medium"><?= $msg_error ?></div>
                        <?php endif; ?>
                        
                        <form action="ticket.php?track_id=<?= htmlspecialchars($track_id) ?>&view=track_result" method="POST" enctype="multipart/form-data">
                            <div class="flex items-end gap-2 bg-slate-50 border border-slate-200 rounded-2xl p-1.5 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 transition-all">
                                
                                <label id="attachBtn" class="flex items-center justify-center w-10 h-10 shrink-0 text-slate-400 hover:text-primary hover:bg-indigo-50 rounded-xl cursor-pointer transition relative">
                                    <i class="ph-bold ph-paperclip text-xl"></i>
                                    <span class="file-indicator absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 border-2 border-white rounded-full hidden"></span>
                                    <input type="file" name="reply_attachment" id="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.pdf">
                                </label>
                                
                                <textarea name="reply_message" rows="1" class="flex-1 bg-transparent border-none focus:ring-0 text-sm px-3 py-2.5 resize-none max-h-24 min-h-[44px]" placeholder="Ketik balasan Anda..." required></textarea>
                                
                                <button type="submit" name="submit_reply" class="flex items-center justify-center w-10 h-10 shrink-0 bg-primary text-white rounded-xl shadow-sm hover:bg-primaryDark transition-colors">
                                    <i class="ph-fill ph-paper-plane-right text-lg"></i>
                                </button>
                            </div>
                            <div class="flex justify-between items-center mt-2 px-2">
                                <span id="fileNameDisplay" class="text-[0.65rem] font-medium text-slate-400 truncate max-w-[200px]">*Max 2MB (JPG/PNG/PDF)</span>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

            </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Auto Scroll Chat to bottom
            const chatBox = document.getElementById("chatContainer");
            if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;

            // Auto-resize textarea
            const tx = document.querySelector('textarea[name="reply_message"]');
            if (tx) {
                tx.addEventListener("input", function() {
                    this.style.height = "44px"; // reset base height
                    const newHeight = Math.min(this.scrollHeight, 96); // max height 96px
                    this.style.height = newHeight + "px";
                });
            }

            // File Upload Indicator Logic
            const fileInput = document.getElementById('fileInput');
            const attachBtn = document.getElementById('attachBtn');
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const fileIndicator = document.querySelector('.file-indicator');

            if(fileInput && attachBtn && fileNameDisplay) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        attachBtn.classList.add('text-primary', 'bg-indigo-50');
                        attachBtn.classList.remove('text-slate-400');
                        if(fileIndicator) fileIndicator.classList.remove('hidden');
                        
                        fileNameDisplay.textContent = this.files[0].name;
                        fileNameDisplay.classList.add('text-primary');
                    } else {
                        attachBtn.classList.remove('text-primary', 'bg-indigo-50');
                        attachBtn.classList.add('text-slate-400');
                        if(fileIndicator) fileIndicator.classList.add('hidden');
                        
                        fileNameDisplay.textContent = "*Max 2MB (JPG/PNG/PDF)";
                        fileNameDisplay.classList.remove('text-primary');
                    }
                });
            }
        });
    </script>
</body>
</html>