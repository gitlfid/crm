<?php
// Set Judul Halaman
$page_title = "System Settings";

// 1. Load Header & Sidebar
include 'includes/header.php';
include 'includes/sidebar.php';

// 2. Load Functions & Config
$funcPath = __DIR__ . '/../config/functions.php';
if (file_exists($funcPath)) {
    include $funcPath;
} else {
    die("Error: File config/functions.php tidak ditemukan.");
}

// 3. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('Akses Ditolak!'); window.location='dashboard.php';</script>";
    exit;
}

$msg = "";

// Helper untuk Alert Tailwind
function setTailwindAlert($type, $message, $icon) {
    $colors = [
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
        'danger'  => 'bg-rose-50 border-rose-200 text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400',
        'info'    => 'bg-sky-50 border-sky-200 text-sky-700 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-700 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400'
    ];
    $c = $colors[$type];
    return "
    <div class='flex items-center justify-between p-4 mb-6 rounded-2xl border shadow-sm animate-fade-in-up $c'>
        <div class='flex items-center gap-3'>
            <i class='ph-fill $icon text-2xl'></i>
            <span class='text-sm font-bold'>$message</span>
        </div>
        <button type='button' onclick='this.parentElement.remove()' class='opacity-50 hover:opacity-100 transition-opacity'>
            <i class='ph-bold ph-x text-lg'></i>
        </button>
    </div>";
}

// LOGIC: Handle Save Settings (All in One)
if (isset($_POST['save_settings'])) {
    
    // 1. Simpan Text Settings (SMTP, Discord, Quotation, Invoice)
    $configs = [
        'smtp_host'                => $_POST['smtp_host'],
        'smtp_user'                => $_POST['smtp_user'],
        'smtp_pass'                => $_POST['smtp_pass'],
        'smtp_port'                => $_POST['smtp_port'],
        'smtp_secure'              => $_POST['smtp_secure'],
        'discord_webhook'          => $_POST['discord_webhook'],
        'discord_webhook_internal' => $_POST['discord_webhook_internal'],
        'company_address_full'     => $_POST['company_address_full'],
        'default_quotation_remarks'=> $_POST['default_quotation_remarks'],
        'invoice_payment_info'     => $_POST['invoice_payment_info'],
        'invoice_note_default'     => $_POST['invoice_note_default']
    ];

    foreach ($configs as $key => $val) {
        $val = $conn->real_escape_string($val);
        $conn->query("UPDATE settings SET setting_value = '$val' WHERE setting_key = '$key'");
    }

    // 2. Simpan Upload Files (Logo & Watermark)
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    function uploadAsset($fileKey, $dbKey, $conn, $dir) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
            if(in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $newName = $dbKey . '.' . $ext; 
                if(move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dir . $newName)) {
                    $conn->query("UPDATE settings SET setting_value = '$newName' WHERE setting_key = '$dbKey'");
                }
            }
        }
    }

    uploadAsset('company_logo', 'company_logo', $conn, $uploadDir);
    uploadAsset('company_watermark', 'company_watermark', $conn, $uploadDir);

    $msg = setTailwindAlert('success', 'Konfigurasi sistem berhasil disimpan!', 'ph-check-circle');
}

// LOGIC: Handle Test Email
if (isset($_POST['test_email'])) {
    $test_to = $_POST['test_to'];
    if (function_exists('sendEmailNotification')) {
        $result = sendEmailNotification($test_to, "Test Email System", "<h1>Koneksi Berhasil!</h1><p>Email ini dikirim menggunakan PHPMailer melalui sistem pengaturan.</p>");
        if ($result === true) {
            $msg = setTailwindAlert('success', "Email berhasil dikirim ke <strong>$test_to</strong>!", 'ph-paper-plane-tilt');
        } else {
            $msg = setTailwindAlert('danger', "Gagal Mengirim Email. Detail: <strong>$result</strong>", 'ph-warning-circle');
        }
    }
}

// LOGIC: Handle Test Discord
if (isset($_POST['test_discord_customer'])) {
    if (function_exists('sendToDiscord')) {
        $res = sendToDiscord("Test Customer Webhook", "Ini adalah tes notifikasi untuk Customer Ticket.", [["name" => "Status", "value" => "Active", "inline" => true]]);
        if(isset($res['id']) || isset($res['channel_id'])) {
            $msg = setTailwindAlert('info', "Tes Customer Webhook Berhasil!", 'ph-discord-logo');
        } else {
            $msg = setTailwindAlert('warning', "Gagal mengirim ke Customer Webhook. Periksa kembali URL.", 'ph-warning-circle');
        }
    }
}
if (isset($_POST['test_discord_internal'])) {
    if (function_exists('sendInternalDiscord')) {
        $res = sendInternalDiscord("Test Internal Webhook", "Ini adalah tes notifikasi untuk Internal Ticket.", [["name" => "Status", "value" => "Active", "inline" => true]]);
        if($res) {
            $msg = setTailwindAlert('info', "Tes Internal Webhook Berhasil!", 'ph-discord-logo');
        } else {
            $msg = setTailwindAlert('warning', "Gagal mengirim ke Internal Webhook. Periksa kembali URL.", 'ph-warning-circle');
        }
    }
}

// Ambil Data Existing
$settings = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) { $settings[$row['setting_key']] = $row['setting_value']; }
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
    
    /* Hide input file default UI */
    input[type=file]::file-selector-button {
        display: none;
    }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-gear-six"></i>
                </div>
                System Settings
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Konfigurasi Email SMTP, Notifikasi Webhook, dan Aset Dokumen Perusahaan.</p>
        </div>
    </div>

    <?= $msg ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <div class="lg:col-span-3 flex flex-col gap-4">
            
            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-2 overflow-x-auto lg:overflow-visible modern-scrollbar sticky top-24 z-10">
                <ul class="flex lg:flex-col gap-2 min-w-max lg:min-w-0" id="settingTabs">
                    <li>
                        <button onclick="switchTab('general')" id="tab-general" class="tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-2xl text-sm font-bold transition-all bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 shadow-sm">
                            <i class="ph-fill ph-envelope-simple text-xl"></i> General & SMTP
                        </button>
                    </li>
                    <li>
                        <button onclick="switchTab('quotation')" id="tab-quotation" class="tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-2xl text-sm font-bold text-slate-600 border border-transparent hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800 transition-all">
                            <i class="ph-fill ph-clipboard-text text-xl"></i> Quotation Assets
                        </button>
                    </li>
                    <li>
                        <button onclick="switchTab('invoice')" id="tab-invoice" class="tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-2xl text-sm font-bold text-slate-600 border border-transparent hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800 transition-all">
                            <i class="ph-fill ph-receipt text-xl"></i> Invoice Settings
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-5 hidden lg:block">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-flask text-lg"></i> Connection Test</h4>
                
                <form method="POST" class="mb-5">
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1.5">Test SMTP Email</label>
                    <div class="relative flex items-center">
                        <i class="ph-bold ph-envelope absolute left-3 text-slate-400"></i>
                        <input type="email" name="test_to" class="w-full pl-9 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white" placeholder="Alamat email tujuan..." required>
                        <button type="submit" name="test_email" class="absolute right-1.5 w-7 h-7 bg-indigo-100 hover:bg-indigo-600 text-indigo-600 hover:text-white rounded-lg transition-colors flex items-center justify-center">
                            <i class="ph-bold ph-paper-plane-tilt"></i>
                        </button>
                    </div>
                </form>

                <form method="POST" class="flex flex-col gap-2">
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-0.5">Test Discord Webhook</label>
                    <button type="submit" name="test_discord_customer" class="w-full bg-[#5865F2] hover:bg-[#4752C4] text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all shadow-sm active:scale-95 flex items-center justify-center gap-2">
                        <i class="ph-fill ph-discord-logo text-base"></i> Customer Ticket
                    </button>
                    <button type="submit" name="test_discord_internal" class="w-full bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all shadow-sm active:scale-95 flex items-center justify-center gap-2">
                        <i class="ph-fill ph-discord-logo text-base"></i> Internal Ticket
                    </button>
                </form>
            </div>

        </div>

        <div class="lg:col-span-9 flex flex-col gap-6">
            <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col min-h-[500px] overflow-hidden relative pb-24">
                
                <div id="pane-general" class="tab-pane p-6 sm:p-8 block animate-fade-in-up">
                    
                    <div class="mb-8">
                        <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-700 pb-2 mb-5 flex items-center gap-2">
                            <i class="ph-fill ph-envelope-simple text-xl"></i> Konfigurasi SMTP
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                            <div class="md:col-span-8">
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">SMTP Host <span class="text-rose-500">*</span></label>
                                <div class="relative group">
                                    <i class="ph-bold ph-hdd-network absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <input type="text" name="smtp_host" value="<?= $settings['smtp_host'] ?? '' ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="e.g. smtp.gmail.com" required>
                                </div>
                            </div>
                            
                            <div class="md:col-span-4">
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Port <span class="text-rose-500">*</span></label>
                                <div class="relative group">
                                    <i class="ph-bold ph-plug absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <input type="number" name="smtp_port" value="<?= $settings['smtp_port'] ?? '587' ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" required>
                                </div>
                            </div>

                            <div class="md:col-span-6">
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">SMTP User (Email) <span class="text-rose-500">*</span></label>
                                <div class="relative group">
                                    <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <input type="email" name="smtp_user" value="<?= $settings['smtp_user'] ?? '' ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" required>
                                </div>
                            </div>

                            <div class="md:col-span-6">
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Security</label>
                                <div class="relative group">
                                    <i class="ph-bold ph-lock-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <select name="smtp_secure" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white appearance-none cursor-pointer shadow-inner">
                                        <option value="tls" <?= ($settings['smtp_secure']=='tls')?'selected':'' ?>>TLS (Rekomendasi Port 587)</option>
                                        <option value="ssl" <?= ($settings['smtp_secure']=='ssl')?'selected':'' ?>>SSL (Rekomendasi Port 465)</option>
                                        <option value="" <?= ($settings['smtp_secure']=='')?'selected':'' ?>>None</option>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>

                            <div class="md:col-span-12">
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">SMTP Password (App Password) <span class="text-rose-500">*</span></label>
                                <div class="relative group flex items-center">
                                    <i class="ph-bold ph-key absolute left-4 text-slate-400 text-lg"></i>
                                    <input type="password" name="smtp_pass" id="smtp_pass" value="<?= $settings['smtp_pass'] ?? '' ?>" class="w-full pl-11 pr-12 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner tracking-widest font-mono" required>
                                    <button type="button" onclick="togglePassword()" class="absolute right-3 w-8 h-8 flex items-center justify-center text-slate-400 hover:text-indigo-600 transition-colors">
                                        <i class="ph-bold ph-eye text-xl" id="toggleIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-black text-[#5865F2] dark:text-[#5865F2] uppercase tracking-widest border-b border-slate-100 dark:border-slate-700 pb-2 mb-5 flex items-center gap-2">
                            <i class="ph-fill ph-discord-logo text-xl"></i> Discord Webhooks
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Customer Ticket Webhook URL</label>
                                <input type="text" name="discord_webhook" value="<?= $settings['discord_webhook'] ?? '' ?>" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-[#5865F2]/50 outline-none dark:text-white transition-all shadow-inner placeholder-slate-400" placeholder="https://discord.com/api/webhooks/...">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Internal Ticket Webhook URL</label>
                                <input type="text" name="discord_webhook_internal" value="<?= $settings['discord_webhook_internal'] ?? '' ?>" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-[#5865F2]/50 outline-none dark:text-white transition-all shadow-inner placeholder-slate-400" placeholder="https://discord.com/api/webhooks/...">
                            </div>
                        </div>
                    </div>

                </div>

                <div id="pane-quotation" class="tab-pane p-6 sm:p-8 hidden animate-fade-in-up">
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-700 pb-2 mb-5 flex items-center gap-2">
                        <i class="ph-fill ph-images text-xl"></i> Aset Perusahaan
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        
                        <div class="bg-slate-50 dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-inner">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-3">Company Logo (Header)</label>
                            
                            <div class="h-24 w-full bg-white dark:bg-[#1A222C] rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center mb-4 overflow-hidden relative shadow-sm">
                                <?php if(!empty($settings['company_logo'])): ?>
                                    <img id="logo-preview" src="../uploads/<?= $settings['company_logo'] ?>" class="max-h-full max-w-full object-contain p-2 z-10">
                                <?php else: ?>
                                    <img id="logo-preview" src="" class="max-h-full max-w-full object-contain p-2 z-10 hidden">
                                    <div id="logo-placeholder" class="text-slate-300 dark:text-slate-600 flex flex-col items-center"><i class="ph-fill ph-image text-3xl"></i><span class="text-[9px] font-bold mt-1">No Logo</span></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="relative">
                                <input type="file" name="company_logo" id="company_logo" accept=".jpg,.jpeg,.png" onchange="previewImage(this, 'logo-preview')" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                                <div class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20 rounded-xl py-2.5 text-xs font-bold text-center transition-colors flex items-center justify-center gap-2">
                                    <i class="ph-bold ph-upload-simple text-base"></i> Pilih File Logo
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-slate-50 dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-inner">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-3">Background Watermark</label>
                            
                            <div class="h-24 w-full bg-white dark:bg-[#1A222C] rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center mb-4 overflow-hidden relative shadow-sm">
                                <?php if(!empty($settings['company_watermark'])): ?>
                                    <img id="watermark-preview" src="../uploads/<?= $settings['company_watermark'] ?>" class="max-h-full max-w-full object-contain p-2 z-10 opacity-50">
                                <?php else: ?>
                                    <img id="watermark-preview" src="" class="max-h-full max-w-full object-contain p-2 z-10 hidden opacity-50">
                                    <div id="watermark-placeholder" class="text-slate-300 dark:text-slate-600 flex flex-col items-center"><i class="ph-fill ph-drop text-3xl"></i><span class="text-[9px] font-bold mt-1">No Watermark</span></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="relative">
                                <input type="file" name="company_watermark" id="company_watermark" accept=".jpg,.jpeg,.png" onchange="previewImage(this, 'watermark-preview')" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                                <div class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20 rounded-xl py-2.5 text-xs font-bold text-center transition-colors flex items-center justify-center gap-2">
                                    <i class="ph-bold ph-upload-simple text-base"></i> Pilih File Watermark
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-1.5">Alamat Perusahaan Lengkap (Kop Surat)</label>
                            <textarea name="company_address_full" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner resize-none" rows="4"><?= $settings['company_address_full'] ?? '' ?></textarea>
                            <p class="text-[10px] text-slate-400 mt-1 italic">Alamat ini akan muncul di pojok kanan atas pada dokumen cetak PDF Quotation & Invoice.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-1.5">Default Quotation Remarks</label>
                            <textarea name="default_quotation_remarks" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner resize-none" rows="3"><?= $settings['default_quotation_remarks'] ?? '' ?></textarea>
                            <p class="text-[10px] text-slate-400 mt-1 italic">Catatan syarat dan ketentuan yang otomatis terisi saat membuat penawaran harga.</p>
                        </div>
                    </div>
                </div>

                <div id="pane-invoice" class="tab-pane p-6 sm:p-8 hidden animate-fade-in-up">
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-700 pb-2 mb-5 flex items-center gap-2">
                        <i class="ph-fill ph-bank text-xl"></i> Pengaturan Tagihan (Invoice)
                    </h3>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-1.5 flex items-center gap-1.5"><i class="ph-bold ph-credit-card text-lg text-indigo-500"></i> Invoice Payment Info (Bank Details)</label>
                            <textarea name="invoice_payment_info" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner resize-none font-mono" rows="6" placeholder="Account Name: PT...\nBank Name: BCA...\nAcc No: 123..."><?= $settings['invoice_payment_info'] ?? '' ?></textarea>
                            <p class="text-[10px] text-slate-400 mt-1 italic">Informasi rekening tujuan transfer yang akan dicetak di bagian bawah kiri dokumen Invoice Domestic.</p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-1.5 flex items-center gap-1.5"><i class="ph-bold ph-note-pencil text-lg text-indigo-500"></i> Default Invoice Note / Footer</label>
                            <textarea name="invoice_note_default" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner resize-none" rows="3" placeholder="Please note that the payer is responsible for bank charges..."><?= $settings['invoice_note_default'] ?? '' ?></textarea>
                            <p class="text-[10px] text-slate-400 mt-1 italic">Catatan tambahan atau pesan terima kasih di bagian bawah Invoice.</p>
                        </div>
                    </div>
                </div>

                <div class="absolute bottom-0 left-0 right-0 bg-white/90 dark:bg-[#24303F]/90 backdrop-blur-md border-t border-slate-200 dark:border-slate-700 p-5 flex justify-end">
                    <button type="submit" name="save_settings" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-2xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:scale-95">
                        <i class="ph-bold ph-floppy-disk text-xl"></i> Simpan Konfigurasi
                    </button>
                </div>

            </form>
        </div>

    </div>
</div>

<div class="lg:hidden mt-6 flex flex-col gap-6 animate-fade-in-up">
    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
        <h4 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-widest mb-4 flex items-center gap-2 border-b border-slate-100 dark:border-slate-700 pb-2"><i class="ph-fill ph-envelope-simple text-indigo-500 text-lg"></i> Test Email</h4>
        <form method="POST">
            <div class="relative flex items-center">
                <i class="ph-bold ph-envelope absolute left-3 text-slate-400"></i>
                <input type="email" name="test_to" class="w-full pl-9 pr-12 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white shadow-inner" placeholder="Email tujuan..." required>
                <button type="submit" name="test_email" class="absolute right-1.5 w-9 h-9 bg-indigo-100 hover:bg-indigo-600 text-indigo-600 hover:text-white rounded-lg transition-colors flex items-center justify-center shadow-sm">
                    <i class="ph-bold ph-paper-plane-tilt text-lg"></i>
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
        <h4 class="text-sm font-black text-[#5865F2] uppercase tracking-widest mb-4 flex items-center gap-2 border-b border-slate-100 dark:border-slate-700 pb-2"><i class="ph-fill ph-discord-logo text-lg"></i> Test Webhook</h4>
        <form method="POST" class="flex flex-col sm:flex-row gap-3">
            <button type="submit" name="test_discord_customer" class="flex-1 bg-[#5865F2] hover:bg-[#4752C4] text-white text-sm font-bold py-3 px-4 rounded-xl transition-all shadow-md shadow-[#5865F2]/20 active:scale-95 flex items-center justify-center gap-2">
                <i class="ph-bold ph-users"></i> Customer Ticket
            </button>
            <button type="submit" name="test_discord_internal" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-sm font-bold py-3 px-4 rounded-xl transition-all shadow-md active:scale-95 flex items-center justify-center gap-2">
                <i class="ph-bold ph-buildings"></i> Internal Ticket
            </button>
        </form>
    </div>
</div>


<script>
    // --- CUSTOM TAB SWITCH LOGIC ---
    function switchTab(tabId) {
        // Hide all tab content
        document.querySelectorAll('.tab-pane').forEach(el => {
            el.classList.add('hidden');
            el.classList.remove('block');
        });
        
        // Show active tab content
        const activePane = document.getElementById('pane-' + tabId);
        activePane.classList.remove('hidden');
        activePane.classList.add('block');
        
        // Reset tab buttons styling
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.className = "tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-2xl text-sm font-bold text-slate-600 border border-transparent hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800 transition-all";
        });
        
        // Active tab button styling
        const activeBtn = document.getElementById('tab-' + tabId);
        activeBtn.className = "tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-2xl text-sm font-bold transition-all bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 shadow-sm";
    }

    // --- PASSWORD TOGGLE LOGIC ---
    function togglePassword() {
        var input = document.getElementById("smtp_pass");
        var icon = document.getElementById("toggleIcon");
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("ph-eye");
            icon.classList.add("ph-eye-slash");
        } else {
            input.type = "password";
            icon.classList.remove("ph-eye-slash");
            icon.classList.add("ph-eye");
        }
    }

    // --- LIVE IMAGE PREVIEW LOGIC ---
    function previewImage(input, previewId) {
        const previewEl = document.getElementById(previewId);
        let placeholderId = previewId === 'logo-preview' ? 'logo-placeholder' : 'watermark-placeholder';
        const placeholderEl = document.getElementById(placeholderId);
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewEl.src = e.target.result;
                previewEl.classList.remove('hidden');
                if(placeholderEl) placeholderEl.classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php include 'includes/footer.php'; ?>