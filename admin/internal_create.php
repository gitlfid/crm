<?php
$page_title = "Buat Tiket Internal";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Ambil Data User yang Login
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_data = $conn->query($user_sql)->fetch_assoc();

// Ambil Daftar Divisi
$divisions = [];
$div_sql = "SELECT * FROM divisions";
$div_res = $conn->query($div_sql);
while($row = $div_res->fetch_assoc()) {
    $divisions[] = $row;
}

// PROSES SUBMIT
if (isset($_POST['submit_internal'])) {
    $target_div = intval($_POST['target_division']);
    
    // 1. Data Mentah & Aman
    $subjectRaw = $_POST['subject'];
    $descRaw    = $_POST['description']; 
    
    $subjectDB  = $conn->real_escape_string($subjectRaw);
    $descDB     = $conn->real_escape_string($descRaw);
    $type_ticket = $conn->real_escape_string($_POST['type']);
    
    // Generate ID
    $ticketCode = generateInternalTicketID($target_div, $conn);
    
    // --- LOGIKA UPLOAD ---
    $attachment = null;
    $uploadDir = __DIR__ . '/../uploads/';
    $uploadErrorMsg = "";

    // Cek Folder
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            $uploadErrorMsg = "Gagal membuat folder uploads.";
        }
    }

    if (isset($_FILES['attachment']) && $_FILES['attachment']['name'] != '') {
        $fErr = $_FILES['attachment']['error'];
        
        if ($fErr === 0) {
            // Bersihkan nama file
            $cleanName = preg_replace("/[^a-zA-Z0-9.]/", "", $_FILES['attachment']['name']);
            $fileName = time() . '_int_' . $cleanName;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName)) {
                $attachment = $fileName;
            } else {
                $uploadErrorMsg = "Gagal memindahkan file (Permission denied).";
            }
        } elseif ($fErr === 1) {
            $uploadErrorMsg = "File terlalu besar (Melebihi upload_max_filesize server).";
        } else {
            $uploadErrorMsg = "Error Upload Code: $fErr";
        }
    }
    
    if (!empty($uploadErrorMsg)) {
        echo "<script>alert('$uploadErrorMsg');</script>";
        // Script tidak exit, tetap lanjut simpan tiket tanpa lampiran (opsional)
    }
    
    // Insert Database
    $stmt = $conn->prepare("INSERT INTO internal_tickets (ticket_code, user_id, target_division_id, subject, description, attachment) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siisss", $ticketCode, $user_id, $target_div, $subjectDB, $descDB, $attachment);
    
    if ($stmt->execute()) {
        // Ambil Nama Divisi Tujuan
        $targetDivName = "Unknown";
        foreach($divisions as $d) { if($d['id'] == $target_div) $targetDivName = $d['name']; }

        // --- 1. NOTIF DISCORD INTERNAL ---
        if (function_exists('sendInternalDiscord')) {
            $discordDesc = (strlen($descRaw) > 1000) ? substr($descRaw, 0, 1000) . "..." : $descRaw;

            $discordFields = [
                ["name" => "From", "value" => $user_data['username'], "inline" => true],
                ["name" => "To Division", "value" => $targetDivName, "inline" => true],
                ["name" => "Type", "value" => $type_ticket, "inline" => true],
                ["name" => "Description", "value" => $discordDesc]
            ];
            
            if ($attachment) {
                $discordFields[] = ["name" => "Attachment", "value" => "Ada File Terlampir", "inline" => true];
            }
            
            $titleDiscord = "$ticketCode: $subjectRaw";
            
            // Try Catch agar error notifikasi tidak membatalkan proses
            try {
                $response = sendInternalDiscord($titleDiscord, "A new internal ticket has been submitted.", $discordFields, null, $titleDiscord);
                if (isset($response['id'])) {
                    $thread_id = $response['id'];
                    $conn->query("UPDATE internal_tickets SET discord_thread_id = '$thread_id' WHERE ticket_code = '$ticketCode'");
                }
            } catch(Exception $e) {}
        }
        
        // --- 2. KIRIM EMAIL KE PEMBUAT (KONFIRMASI) ---
        if (function_exists('sendEmailNotification')) {
            try {
                $body = "Halo " . $user_data['username'] . ",<br><br>Tiket Internal Anda ke divisi <strong>$targetDivName</strong> berhasil dibuat.<br>ID: <strong>$ticketCode</strong><br>Subject: $subjectRaw<br><br>Mohon pantau sistem untuk melihat status update tiket ini.";
                sendEmailNotification($user_data['email'], "Internal Ticket Created: $ticketCode", $body);
            } catch(Exception $e) {}
        }

        // --- 3. KIRIM EMAIL KE SELURUH USER DIVISI TUJUAN ---
        if (function_exists('sendEmailNotification')) {
            $stmtDivUsers = $conn->prepare("SELECT username, email FROM users WHERE division_id = ?");
            $stmtDivUsers->bind_param("i", $target_div);
            $stmtDivUsers->execute();
            $resDivUsers = $stmtDivUsers->get_result();

            while ($targetUser = $resDivUsers->fetch_assoc()) {
                if ($targetUser['email'] === $user_data['email']) continue;

                $subjectTarget = "[New Internal Ticket] Masuk dari " . $user_data['username'];
                $bodyTarget  = "<h3>Halo " . $targetUser['username'] . ",</h3>";
                $bodyTarget .= "<p>Ada permintaan tugas/tiket internal baru yang ditujukan ke Divisi Anda (<strong>$targetDivName</strong>).</p>";
                $bodyTarget .= "<hr>";
                $bodyTarget .= "<ul>
                                    <li><strong>Ticket ID:</strong> $ticketCode</li>
                                    <li><strong>Pengirim:</strong> " . $user_data['username'] . "</li>
                                    <li><strong>Jenis Permintaan:</strong> $type_ticket</li>
                                    <li><strong>Subject:</strong> " . htmlspecialchars($subjectRaw) . "</li>
                                </ul>";
                
                $bodyTarget .= "<p><strong>Deskripsi Detail:</strong><br>" . nl2br(htmlspecialchars($descRaw)) . "</p>";
                
                if ($attachment) {
                    $bodyTarget .= "<p><em>*Tiket ini memiliki lampiran file. Silakan cek sistem.</em></p>";
                }
                
                $bodyTarget .= "<hr>";
                $bodyTarget .= "<p>Mohon segera dicek dan ditindaklanjuti pada sistem Helpdesk Internal.</p>";

                try {
                    sendEmailNotification($targetUser['email'], $subjectTarget, $bodyTarget);
                } catch(Exception $e) {}
            }
        }
        
        echo "<script>alert('Tiket Internal Berhasil Dibuat & Notifikasi telah dikirim!'); window.location='internal_tickets.php';</script>";
    } else {
        echo "<script>alert('Gagal membuat tiket: " . $conn->error . "');</script>";
    }
}
?>

<style>
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modern-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .modern-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .modern-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .modern-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1200px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-orange-500/30">
                    <i class="ph-fill ph-paper-plane-right"></i>
                </div>
                Buat Tiket Internal
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Ajukan permintaan bantuan, pertanyaan, atau tugas khusus lintas divisi perusahaan.</p>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <a href="internal_tickets.php" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-[#24303F] text-slate-600 dark:text-slate-300 font-bold py-3 px-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all hover:bg-slate-50 dark:hover:bg-slate-800 active:scale-95 whitespace-nowrap">
                <i class="ph-bold ph-arrow-left text-lg"></i> Kembali
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-[2rem] shadow-xl shadow-orange-500/5 border border-slate-100 dark:border-slate-800 overflow-hidden relative">
        <div class="absolute -right-32 -top-32 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <form method="POST" enctype="multipart/form-data" class="relative z-10 flex flex-col min-h-[500px]">
            
            <div class="px-6 py-6 sm:px-10 border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/30">
                <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <i class="ph-fill ph-identification-card text-base"></i> Informasi Pengirim
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Nama Pembuat</label>
                        <div class="relative">
                            <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" class="w-full pl-10 pr-4 py-3 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-400 outline-none cursor-not-allowed shadow-inner" value="<?= htmlspecialchars($user_data['username']) ?>" readonly>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Email</label>
                        <div class="relative">
                            <i class="ph-bold ph-envelope-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" class="w-full pl-10 pr-4 py-3 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-500 dark:text-slate-400 outline-none cursor-not-allowed shadow-inner" value="<?= htmlspecialchars($user_data['email']) ?>" readonly>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Nomor Telepon</label>
                        <div class="relative">
                            <i class="ph-bold ph-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" class="w-full pl-10 pr-4 py-3 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-500 dark:text-slate-400 outline-none cursor-not-allowed shadow-inner" value="<?= htmlspecialchars($user_data['phone'] ?? '-') ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-10 space-y-8 flex-grow">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
                    <div>
                        <label class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-2">Tujuan Divisi <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-orange-500 transition-colors"></i>
                            <select name="target_division" required class="w-full pl-11 pr-10 py-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500 dark:text-white appearance-none cursor-pointer outline-none transition-all shadow-inner">
                                <option value="">-- Pilih Divisi Tujuan --</option>
                                <?php foreach($divisions as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?> (<?= htmlspecialchars($div['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                        <p class="text-[10px] font-medium text-slate-400 mt-2 flex items-center gap-1 italic"><i class="ph-fill ph-info"></i> Seluruh staf di divisi tujuan akan menerima notifikasi email.</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-2">Jenis Permintaan <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-orange-500 transition-colors"></i>
                            <select name="type" required class="w-full pl-11 pr-10 py-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500 dark:text-white appearance-none cursor-pointer outline-none transition-all shadow-inner">
                                <option value="Request">Request (Permintaan Bantuan/Tugas)</option>
                                <option value="Incident">Incident (Laporan Kendala/Bug)</option>
                                <option value="Question">Question (Pertanyaan Umum)</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-2">Subject (Judul) <span class="text-rose-500">*</span></label>
                    <div class="relative group">
                        <i class="ph-bold ph-text-t absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="text" name="subject" required class="w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Tuliskan inti permasalahan secara singkat...">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-2">Deskripsi Detail <span class="text-rose-500">*</span></label>
                    <textarea name="description" rows="6" required class="w-full px-5 py-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500 dark:text-white outline-none transition-all resize-none placeholder-slate-400 shadow-inner" placeholder="Jelaskan detail kebutuhan, kronologi, atau instruksi tugas secara lengkap agar divisi tujuan mudah memahaminya..."></textarea>
                </div>

                <div class="p-5 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
                    <label class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-3 flex items-center gap-1.5"><i class="ph-bold ph-paperclip text-orange-500 text-base"></i> Lampiran File (Opsional)</label>
                    <input type="file" name="attachment" class="w-full block text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100 dark:file:bg-orange-500/10 dark:file:text-orange-400 dark:hover:file:bg-orange-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 transition-all shadow-sm">
                    <p class="text-[10px] font-medium text-slate-400 mt-2.5">Maksimal ukuran file: 2MB. Format didukung: Gambar (JPG/PNG), PDF, Docs, Excel.</p>
                </div>

            </div>

            <div class="px-6 py-6 sm:px-10 border-t border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-[#1A222C]/80 backdrop-blur-md flex flex-col sm:flex-row justify-between items-center gap-4 shrink-0">
                <p class="text-[10px] text-slate-500 font-medium italic"><span class="text-rose-500 font-bold">*</span> Wajib diisi</p>
                <button type="submit" name="submit_internal" class="w-full sm:w-auto bg-slate-800 hover:bg-slate-900 dark:bg-orange-600 dark:hover:bg-orange-500 text-white font-black py-4 px-10 rounded-2xl transition-all shadow-xl shadow-slate-900/20 dark:shadow-orange-600/30 flex items-center justify-center gap-3 active:scale-95 text-sm uppercase tracking-widest group">
                    Kirim Permintaan <i class="ph-bold ph-paper-plane-right text-xl group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform"></i>
                </button>
            </div>
            
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>