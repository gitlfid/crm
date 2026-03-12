<?php
$page_title = "Detail Ticket";
include 'includes/header.php';
include 'includes/sidebar.php';
// include '../config/functions.php'; // Jika tidak di-load otomatis

// 1. Cek ID Ticket dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID Ticket tidak valid!'); window.location='tickets.php';</script>";
    exit;
}

$ticket_id = intval($_GET['id']);
$msg_status = "";

// Helper Alert Tailwind
function tailwindAlert($type, $msg, $icon) {
    $colors = [
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
        'danger'  => 'bg-rose-50 border-rose-200 text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400'
    ];
    $c = $colors[$type] ?? $colors['success'];
    return "<div class='p-4 mb-6 rounded-2xl border flex items-center gap-3 text-sm font-bold shadow-sm animate-fade-in-up $c'><i class='ph-fill $icon text-xl'></i> $msg</div>";
}

// --- LOGIKA 1: ASSIGN TICKET ---
if (isset($_POST['submit_assign'])) {
    $assign_to = intval($_POST['assigned_to']);
    $assign_to_sql = ($assign_to == 0) ? "NULL" : $assign_to;
    
    if ($conn->query("UPDATE tickets SET assigned_to = $assign_to_sql WHERE id = $ticket_id")) {
        $msg_status = tailwindAlert('success', 'Tiket berhasil ditugaskan (Assigned).', 'ph-check-circle');
        
        // Kirim Notif Log ke Discord
        if (function_exists('sendToDiscord')) {
            $adminName = "Unassigned";
            if($assign_to > 0) {
                $resAdm = $conn->query("SELECT username FROM users WHERE id = $assign_to");
                if($resAdm->num_rows > 0) $adminName = $resAdm->fetch_assoc()['username'];
            }
            $t_check = $conn->query("SELECT ticket_code, discord_thread_id FROM tickets WHERE id = $ticket_id")->fetch_assoc();
            $discordFields = [
                ["name" => "Ticket ID", "value" => $t_check['ticket_code'], "inline" => true],
                ["name" => "Assigned To", "value" => $adminName, "inline" => true],
                ["name" => "Updated By", "value" => $_SESSION['username'], "inline" => true]
            ];
            $thread_id = isset($t_check['discord_thread_id']) ? $t_check['discord_thread_id'] : null;
            sendToDiscord("Ticket Assigned", "Ticket ownership has been updated.", $discordFields, $thread_id);
        }
    } else {
        $msg_status = tailwindAlert('danger', 'Gagal update assignment.', 'ph-warning-circle');
    }
}

// --- LOGIKA 2: PROSES REPLY & UPDATE STATUS ---
if (isset($_POST['submit_reply'])) {
    $reply_msg = $_POST['reply_message'];
    $new_status = $_POST['ticket_status'];
    
    // --- AUTO TEMPLATE MESSAGE ---
    $auto_footer = "";

    if ($new_status == 'closed') {
        $auto_footer = "
<br><br><hr style='border-top: 1px solid #ddd;'><br>
<strong>Yth. Pelanggan Linksfield Networks Indonesia,</strong><br><br>
Terima kasih telah berinteraksi dengan layanan Ticketing Linksfield Networks Indonesia.<br>
Kami senantiasa berkomitmen untuk memberikan pengalaman terbaik bagi pelanggan dengan terus meningkatkan kualitas layanan kami. Masukan dan interaksi Anda sangat berarti bagi kami dalam upaya menjaga standar pelayanan yang profesional, responsif, dan optimal.<br><br>
Apabila Anda memiliki pertanyaan, kebutuhan lanjutan, atau masukan tambahan, jangan ragu untuk menghubungi kami melalui kanal layanan yang tersedia.<br><br>
Terima kasih atas kepercayaan Anda kepada Linksfield Networks Indonesia.<br><br>
Hormat kami,<br>
<strong>Linksfield Networks Indonesia</strong>
<br><br><br>
<strong>Dear Linksfield Networks Indonesia Customers,</strong><br><br>
Thank you for interacting with Linksfield Networks Indonesia's Ticketing service.<br>
We are committed to providing the best experience for our customers by continuously improving the quality of our services. Your feedback and interaction are very important to us in our efforts to maintain professional, responsive, and optimal service standards.<br><br>
If you have any questions, further needs, or additional feedback, please do not hesitate to contact us through the available service channels.<br><br>
Thank you for your trust in Linksfield Networks Indonesia.<br><br>
Sincerely,<br>
<strong>Linksfield Networks Indonesia</strong>";
    }
    elseif ($new_status == 'open') {
        $auto_footer = "
<br><br><hr style='border-top: 1px dashed #ddd;'><br>
<strong>Status Update: OPEN</strong><br>
Tiket ini telah kami buka kembali untuk peninjauan lebih lanjut. Tim kami akan segera merespons.<br><br>
<em>This ticket has been reopened for further review. Our team will respond shortly.</em>";
    }
    elseif ($new_status == 'progress') {
        $auto_footer = "
<br><br><hr style='border-top: 1px dashed #ddd;'><br>
<strong>Status Update: IN PROGRESS</strong><br>
Kami sedang menindaklanjuti laporan ini. Mohon menunggu update selanjutnya dari tim teknis kami.<br><br>
<em>We are currently working on this issue. Please wait for further updates from our technical team.</em>";
    }

    if (!empty($auto_footer)) {
        $reply_msg .= $auto_footer;
    }

    // Upload Attachment Logic
    $attachment = null;
    $uploadDir = __DIR__ . '/../uploads/'; 
    
    if (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] == 0) {
        $allowedSize = 2 * 1024 * 1024; 
        if ($_FILES['reply_attachment']['size'] <= $allowedSize) {
            $fileName = time() . '_admin_' . preg_replace("/[^a-zA-Z0-9.]/", "", $_FILES['reply_attachment']['name']);
            if (move_uploaded_file($_FILES['reply_attachment']['tmp_name'], $uploadDir . $fileName)) {
                $attachment = $fileName;
            } else {
                $msg_status = tailwindAlert('danger', 'Gagal upload file.', 'ph-warning-circle');
            }
        } else {
            $msg_status = tailwindAlert('danger', 'File terlalu besar! Max 2MB.', 'ph-warning-circle');
        }
    }

    if (strpos($msg_status, 'ph-warning-circle') === false) { 
        $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user, message, attachment) VALUES (?, 'Admin', ?, ?)");
        $stmt->bind_param("iss", $ticket_id, $reply_msg, $attachment);
        
        if ($stmt->execute()) {
            $safe_status = $conn->real_escape_string($new_status);
            $conn->query("UPDATE tickets SET status = '$safe_status' WHERE id = $ticket_id");

            $t_data = $conn->query("SELECT * FROM tickets WHERE id = $ticket_id")->fetch_assoc();

            // KIRIM EMAIL
            if (function_exists('sendEmailNotification')) {
                $emailSubject = "Balasan Ticket #" . $t_data['ticket_code'];
                $emailBody = "<h3>Halo " . $t_data['name'] . ",</h3>";
                $emailBody .= "<p>Ticket Anda <strong>#" . $t_data['ticket_code'] . "</strong> telah dibalas oleh Admin.</p>";
                $emailBody .= "<p><strong>Status Ticket:</strong> <span style='color:blue; font-weight:bold;'>" . strtoupper($new_status) . "</span></p>";
                $emailBody .= "<p><strong>Pesan Admin:</strong><br>" . $reply_msg . "</p>";
                if($attachment) $emailBody .= "<p><em>(Admin menyertakan lampiran)</em></p>";
                $emailBody .= "<hr><p>Silakan cek detailnya di website kami.</p>";
                sendEmailNotification($t_data['email'], $emailSubject, $emailBody);
            }

            // KIRIM DISCORD
            if (function_exists('sendToDiscord')) {
                $cleanMsg = strip_tags($reply_msg);
                $discordFields = [
                    ["name" => "Ticket ID", "value" => $t_data['ticket_code'], "inline" => true],
                    ["name" => "Admin Reply", "value" => (strlen($cleanMsg) > 900 ? substr($cleanMsg,0,900).'...' : $cleanMsg)],
                    ["name" => "Status", "value" => strtoupper($new_status), "inline" => true]
                ];
                if($attachment) $discordFields[] = ["name" => "Attachment", "value" => "Yes (Check Dashboard)", "inline" => true];
                
                $thread_id = isset($t_data['discord_thread_id']) ? $t_data['discord_thread_id'] : null;
                sendToDiscord("Ticket Replied by Admin", "Admin has replied.", $discordFields, $thread_id);
            }

            $msg_status = tailwindAlert('success', 'Balasan berhasil dikirim!', 'ph-check-circle');
        } else {
            $msg_status = tailwindAlert('danger', 'Gagal menyimpan database.', 'ph-warning-circle');
        }
    }
}

// 3. AMBIL DATA TICKET UTAMA
$sql = "SELECT t.*, u.username as assigned_name FROM tickets t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id = $ticket_id";
$ticket = $conn->query($sql)->fetch_assoc();

if (!$ticket) {
    echo "<div class='p-8 text-center text-rose-500 font-bold'>Ticket tidak ditemukan.</div>";
    include 'includes/footer.php'; exit;
}

// LOGIKA HITUNG ANTRIAN (UNTUK ADMIN)
$queue_badge = "";
if (strtolower($ticket['status']) == 'open') {
    $qSql = "SELECT COUNT(*) as pos FROM tickets WHERE status = 'open' AND id <= $ticket_id";
    $qRes = $conn->query($qSql);
    if($qRes) {
        $qRow = $qRes->fetch_assoc();
        $queue_badge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 text-[10px] font-black uppercase tracking-widest shadow-sm"><i class="ph-bold ph-users-three text-xs"></i> Antrian: ' . $qRow['pos'] . '</span>';
    }
}

// Badge Status Warna
$st = strtolower($ticket['status']);
if($st == 'open') $stClass = 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400';
elseif($st == 'progress') $stClass = 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400';
elseif($st == 'closed') $stClass = 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300';
else $stClass = 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400';

// 4. AMBIL LIST ADMIN
$admins = [];
$res_adm = $conn->query("SELECT id, username FROM users WHERE role = 'admin'");
while($row = $res_adm->fetch_assoc()) { $admins[] = $row; }

// 5. AMBIL DATA REPLIES
$replies = [];
$res_rep = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id = $ticket_id ORDER BY created_at ASC");
while($row = $res_rep->fetch_assoc()) { $replies[] = $row; }

// Helper Functions
function formatText($text) { 
    $text = str_replace(array('\r\n', '\n', '\r'), '<br>', $text);
    return nl2br($text); 
} 
function isImage($file) { return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']); }
?>

<style>
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modern-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .modern-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .modern-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .modern-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-ticket"></i>
                </div>
                Ticket #<?= htmlspecialchars($ticket['ticket_code']) ?>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Manage, assign, dan berikan tanggapan untuk tiket dari klien.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="tickets.php" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-[#24303F] text-slate-600 dark:text-slate-300 font-bold py-3 px-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all hover:bg-slate-50 dark:hover:bg-slate-800 active:scale-95 whitespace-nowrap">
                <i class="ph-bold ph-arrow-left text-lg"></i> Kembali
            </a>
        </div>
    </div>

    <?= $msg_status ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <div class="lg:col-span-8 flex flex-col gap-6">
            
            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
                <div class="p-6 border-b border-slate-100 dark:border-slate-700/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-800/30">
                    <div>
                        <h2 class="text-lg font-black text-slate-800 dark:text-white mb-1.5 leading-snug"><?= htmlspecialchars($ticket['subject']) ?></h2>
                        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><i class="ph-fill ph-calendar-blank"></i> <?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <?= $queue_badge ?>
                        <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1 rounded-lg border text-[10px] font-black uppercase tracking-widest shadow-sm <?= $stClass ?>">
                            <?= strtoupper($st) ?>
                        </span>
                    </div>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="flex items-center gap-4 p-5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 mb-8 shadow-inner">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-amber-400 to-orange-500 text-white flex items-center justify-center text-2xl font-black shadow-md shrink-0">
                            <?= strtoupper(substr($ticket['name'],0,1)) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-black text-slate-800 dark:text-white text-base truncate mb-0.5"><?= htmlspecialchars($ticket['name']) ?></h4>
                            <p class="text-xs font-bold text-slate-500 dark:text-slate-400 truncate mb-1.5"><?= htmlspecialchars($ticket['company']) ?></p>
                            <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><i class="ph-fill ph-envelope-simple text-slate-400"></i> <?= htmlspecialchars($ticket['email']) ?></p>
                        </div>
                    </div>

                    <h5 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2"><i class="ph-bold ph-text-align-left text-sm"></i> Deskripsi Masalah</h5>
                    <div class="text-sm font-medium text-slate-700 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-[#1A222C] p-6 rounded-2xl border border-slate-100 dark:border-slate-800 break-words whitespace-pre-wrap shadow-inner"><?php 
                            $desc_clean = htmlspecialchars($ticket['description']);
                            $desc_clean = str_replace(array('\r\n', '\n', '\r'), '<br>', $desc_clean);
                            echo nl2br($desc_clean);
                        ?></div>

                    <?php if($ticket['attachment']): ?>
                    <div class="mt-5">
                        <a href="../uploads/<?= $ticket['attachment'] ?>" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 dark:text-indigo-400 rounded-xl text-xs font-bold transition-colors border border-indigo-100 dark:border-indigo-500/20 shadow-sm">
                            <i class="ph-bold ph-paperclip text-base"></i> Lihat Lampiran Awal
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/30 flex items-center gap-2">
                    <i class="ph-fill ph-chats-teardrop text-indigo-500 text-2xl"></i>
                    <h3 class="font-black text-slate-800 dark:text-white text-base uppercase tracking-widest">Riwayat Percakapan</h3>
                </div>
                
                <div class="p-6 sm:p-8 bg-slate-50/50 dark:bg-[#1A222C] overflow-y-auto modern-scrollbar max-h-[700px] flex flex-col gap-6">
                    <?php if(!empty($replies)): ?>
                        <?php foreach($replies as $reply): ?>
                            <?php $isAdmin = ($reply['user'] == 'Admin'); ?>
                            
                            <div class="flex w-full <?= $isAdmin ? 'justify-end' : 'justify-start' ?>">
                                <div class="flex gap-3 max-w-[90%] sm:max-w-[75%] <?= $isAdmin ? 'flex-row-reverse' : 'flex-row' ?>">
                                    
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-black text-sm shrink-0 shadow-md <?= $isAdmin ? 'bg-gradient-to-tr from-indigo-600 to-blue-500' : 'bg-gradient-to-tr from-amber-400 to-orange-500' ?>">
                                        <?= $isAdmin ? 'A' : strtoupper(substr($ticket['name'],0,1)) ?>
                                    </div>

                                    <div class="flex flex-col <?= $isAdmin ? 'items-end' : 'items-start' ?>">
                                        <div class="flex items-center gap-2 mb-1.5 px-1">
                                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300"><?= $isAdmin ? 'Admin Support' : htmlspecialchars($ticket['name']) ?></span>
                                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?= date('d M H:i', strtotime($reply['created_at'])) ?></span>
                                        </div>
                                        
                                        <div class="p-4 sm:p-5 shadow-sm text-sm font-medium leading-relaxed break-words <?= $isAdmin ? 'bg-indigo-600 text-white rounded-2xl rounded-tr-sm' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-2xl rounded-tl-sm' ?>">
                                            <?= formatText($reply['message']) ?>
                                            
                                            <?php if(!empty($reply['attachment'])): ?>
                                                <div class="mt-4 pt-4 <?= $isAdmin ? 'border-t border-indigo-400/30' : 'border-t border-slate-200 dark:border-slate-700' ?>">
                                                    <?php if(isImage($reply['attachment'])): ?>
                                                        <a href="../uploads/<?= $reply['attachment'] ?>" target="_blank" class="block rounded-xl overflow-hidden border <?= $isAdmin ? 'border-indigo-400/50' : 'border-slate-200 dark:border-slate-700' ?>">
                                                            <img src="../uploads/<?= $reply['attachment'] ?>" class="w-auto max-h-[250px] object-cover hover:opacity-90 transition-opacity">
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="../uploads/<?= $reply['attachment'] ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold transition-colors <?= $isAdmin ? 'bg-indigo-500 hover:bg-indigo-400 text-white' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300' ?>">
                                                            <i class="ph-bold ph-file-arrow-down text-lg"></i> Download File
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-16 flex flex-col items-center opacity-50">
                            <i class="ph-fill ph-chats-teardrop text-6xl text-slate-400 mb-4"></i>
                            <p class="text-sm font-bold text-slate-500">Belum ada balasan diskusi.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="lg:col-span-4 flex flex-col gap-6">
            
            <div class="sticky top-24 flex flex-col gap-6">
                
                <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/30">
                        <h3 class="font-black text-slate-800 dark:text-white text-[11px] uppercase tracking-widest flex items-center gap-2">
                            <i class="ph-fill ph-user-gear text-indigo-500 text-lg"></i> Petugas (Assignee)
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="flex flex-col sm:flex-row lg:flex-col xl:flex-row items-center gap-3">
                            <div class="relative w-full">
                                <select name="assigned_to" class="w-full pl-4 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white appearance-none cursor-pointer shadow-inner transition-all">
                                    <option value="0">-- Unassigned --</option>
                                    <?php foreach($admins as $adm): ?>
                                        <option value="<?= $adm['id'] ?>" <?= ($ticket['assigned_to'] == $adm['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($adm['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                            <button type="submit" name="submit_assign" class="w-full sm:w-auto lg:w-full xl:w-auto px-6 py-3 bg-indigo-50 hover:bg-indigo-600 text-indigo-600 hover:text-white dark:bg-indigo-500/10 dark:hover:bg-indigo-500 dark:text-indigo-400 dark:hover:text-white rounded-xl text-sm font-bold transition-colors shadow-sm border border-indigo-200 dark:border-indigo-500/20 flex items-center justify-center shrink-0">
                                Simpan
                            </button>
                        </form>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/30">
                        <h3 class="font-black text-slate-800 dark:text-white text-[11px] uppercase tracking-widest flex items-center gap-2">
                            <i class="ph-fill ph-paper-plane-right text-indigo-500 text-lg"></i> Balas Ticket
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" enctype="multipart/form-data" class="space-y-5">
                            
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Update Status</label>
                                <div class="relative">
                                    <select name="ticket_status" class="w-full pl-4 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white appearance-none cursor-pointer shadow-inner transition-all">
                                        <option value="open" <?= $ticket['status'] == 'open' ? 'selected' : '' ?>>Open (Menunggu Respons)</option>
                                        <option value="progress" <?= $ticket['status'] == 'progress' ? 'selected' : '' ?>>In Progress (Sedang Dikerjakan)</option>
                                        <option value="hold" <?= $ticket['status'] == 'hold' ? 'selected' : '' ?>>Hold (Ditangguhkan)</option>
                                        <option value="closed" <?= $ticket['status'] == 'closed' ? 'selected' : '' ?>>Closed (Selesai)</option>
                                        <option value="canceled" <?= $ticket['status'] == 'canceled' ? 'selected' : '' ?>>Canceled (Dibatalkan)</option>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Pesan Balasan <span class="text-rose-500">*</span></label>
                                <textarea name="reply_message" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner resize-none placeholder-slate-400" rows="8" placeholder="Tulis balasan Anda untuk klien disini..." required></textarea>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Lampiran (Opsional)</label>
                                <input type="file" name="reply_attachment" class="w-full block text-xs text-slate-500 dark:text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-500/10 dark:file:text-indigo-400 dark:hover:file:bg-indigo-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 transition-all">
                                <p class="text-[10px] font-medium text-slate-400 mt-2 italic flex items-center gap-1"><i class="ph-fill ph-info"></i> Maks 2MB. Gambar atau Dokumen.</p>
                            </div>

                            <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                                <button type="submit" name="submit_reply" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 px-4 rounded-xl transition-all shadow-lg shadow-indigo-500/30 flex items-center justify-center gap-2 active:scale-95 text-base tracking-wide uppercase">
                                    <i class="ph-bold ph-paper-plane-tilt text-xl"></i> Kirim Balasan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>