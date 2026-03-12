<?php
// =========================================
// 1. INITIALIZATION & CONFIG
// =========================================
ini_set('display_errors', 0); // Matikan display error di production agar UI tidak rusak
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Konfigurasi & Fungsi DULUAN
require_once '../config/database.php'; 
// PENTING: Wajib di-include agar fungsi sendEmailNotification() bisa dieksekusi
require_once '../config/functions.php'; 

// --- PERMISSION CHECK ---
$can_access = false;
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $can_access = true;
} else {
    $my_div = isset($_SESSION['division_id']) ? intval($_SESSION['division_id']) : 0;
    
    if ($my_div === 0 && isset($_SESSION['user_id'])) {
        $uID = intval($_SESSION['user_id']);
        $qDiv = $conn->query("SELECT division_id FROM users WHERE id = $uID");
        if ($qDiv && $qDiv->num_rows > 0) {
            $my_div = intval($qDiv->fetch_assoc()['division_id']);
            $_SESSION['division_id'] = $my_div;
        }
    }
    
    $page_url = basename($_SERVER['PHP_SELF']); 
    $sqlPerm = "SELECT dp.menu_id FROM division_permissions dp 
                JOIN menus m ON dp.menu_id = m.id 
                WHERE dp.division_id = $my_div AND m.url LIKE '%$page_url'";
    $chk = $conn->query($sqlPerm);
    if ($chk && $chk->num_rows > 0) $can_access = true;
}

if (!$can_access) {
    echo "<script>alert('Akses Ditolak! Anda tidak memiliki izin.'); window.location='dashboard.php';</script>";
    exit;
}

// --- HELPER FUNCTIONS ---
function generateRandomPassword($length = 10) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*'), 0, $length);
}

function processSignature($fileInputName, $base64InputName) {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
        if ($ext !== 'png') return ['status' => false, 'msg' => 'Format file harus .PNG'];
        $fileName = 'sign_upload_' . time() . '_' . rand(100,999) . '.png';
        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $uploadDir . $fileName)) return ['status' => true, 'file' => $fileName];
    }

    if (!empty($_POST[$base64InputName])) {
        $base64_string = $_POST[$base64InputName];
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $type)) {
            $data = base64_decode(substr($base64_string, strpos($base64_string, ',') + 1));
            if ($data === false) return ['status' => false, 'msg' => 'Gagal decode gambar'];
            $fileName = 'sign_digital_' . time() . '_' . rand(100,999) . '.png';
            if(file_put_contents($uploadDir . $fileName, $data)) return ['status' => true, 'file' => $fileName];
        }
    }
    return ['status' => false, 'msg' => 'No Data'];
}

$msg = "";
function setTailwindMsg($type, $text, $icon) {
    $colors = [
        'success' => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
        'danger'  => 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400',
        'warning' => 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400'
    ];
    $c = $colors[$type];
    return "<div class='p-4 mb-6 rounded-2xl border flex items-center gap-4 text-sm font-bold shadow-sm animate-fade-in-up $c'><div class='w-10 h-10 rounded-full bg-white/50 dark:bg-black/20 flex items-center justify-center shrink-0'><i class='ph-fill $icon text-xl'></i></div> <div>$text</div></div>";
}

// =========================================
// 2. BACKEND LOGIC (CRUD)
// =========================================

// --- ADD USER ---
if (isset($_POST['add_user'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $email_clean = trim($_POST['email']); 
    $email = $conn->real_escape_string($email_clean); 
    $phone = $conn->real_escape_string($_POST['phone']);
    $role = $conn->real_escape_string($_POST['role']);
    $division_id = !empty($_POST['division_id']) ? intval($_POST['division_id']) : "NULL";
    $job_title = $conn->real_escape_string($_POST['job_title']);
    $leave_quota = intval($_POST['leave_quota']);
    
    $signVal = "NULL";
    $procSign = processSignature('signature_file', 'signature_data');
    
    if ($procSign['status']) {
        $signVal = "'" . $procSign['file'] . "'";
    } elseif (isset($_FILES['signature_file']['name']) && !empty($_FILES['signature_file']['name']) && $procSign['msg'] == 'Format file harus .PNG') {
        $msg = setTailwindMsg('danger', 'Gagal: Signature file harus berformat PNG!', 'ph-x-circle');
    }
    
    if (empty($msg)) {
        $cek = $conn->query("SELECT id FROM users WHERE email = '$email' OR username = '$username'");
        if($cek && $cek->num_rows > 0) {
            $msg = setTailwindMsg('danger', 'Username atau Email sudah terdaftar!', 'ph-warning-circle');
        } else {
            $pass_raw = generateRandomPassword(10);
            $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (username, email, phone, password, role, division_id, job_title, leave_quota, signature, must_change_password) 
                    VALUES ('$username', '$email', '$phone', '$pass_hash', '$role', $division_id, '$job_title', $leave_quota, $signVal, 1)";
            
            if ($conn->query($sql)) {
                if (function_exists('sendEmailNotification')) {
                    $emailSubject = "Selamat Datang di System";
                    $emailBody = "Halo $username,<br><br>Akun Anda telah dibuat.<br><strong>Email:</strong> $email_clean<br><strong>Password:</strong> $pass_raw<br><br>Silakan login dan segera ganti password Anda.";
                    sendEmailNotification($email_clean, $emailSubject, $emailBody);
                }
                $msg = setTailwindMsg('success', 'User berhasil ditambahkan! Email notifikasi berisi password telah dikirim.', 'ph-check-circle');
            } else { 
                $msg = setTailwindMsg('danger', 'Database Error: ' . $conn->error, 'ph-warning');
            }
        }
    }
}

// --- EDIT USER ---
if (isset($_POST['edit_user'])) {
    $id = intval($_POST['edit_id']);
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string(trim($_POST['email']));
    $phone = $conn->real_escape_string($_POST['phone']);
    $role = $conn->real_escape_string($_POST['role']);
    $division_id = !empty($_POST['division_id']) ? intval($_POST['division_id']) : "NULL";
    $job_title = $conn->real_escape_string($_POST['job_title']);
    $leave_quota = intval($_POST['leave_quota']);
    
    $sql = "UPDATE users SET username='$username', email='$email', phone='$phone', role='$role', division_id=$division_id, job_title='$job_title', leave_quota=$leave_quota WHERE id=$id";
    
    if($conn->query($sql)) {
        $procSign = processSignature('edit_signature_file', 'edit_signature_data');
        if ($procSign['status']) {
            $newFile = $procSign['file'];
            $conn->query("UPDATE users SET signature='$newFile' WHERE id=$id");
        } elseif (isset($_FILES['edit_signature_file']['name']) && !empty($_FILES['edit_signature_file']['name']) && $procSign['msg'] == 'Format file harus .PNG') {
             $msg = setTailwindMsg('warning', 'Data tersimpan, TAPI pembaruan Signature Gagal (Harus format PNG).', 'ph-warning');
        }
        if(empty($msg)) $msg = setTailwindMsg('success', 'Data user berhasil diperbarui.', 'ph-check-circle');
    }
}

// --- RESET PASSWORD ---
if (isset($_POST['reset_password'])) {
    $id = intval($_POST['reset_id']);
    $uData = $conn->query("SELECT email, username FROM users WHERE id=$id")->fetch_assoc();
    if ($uData) {
        $new_pass = generateRandomPassword(10);
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        if ($conn->query("UPDATE users SET password='$new_hash', must_change_password=1 WHERE id=$id")) {
            // Pastikan fungsi berjalan
            if (function_exists('sendEmailNotification')) {
                $emailResult = sendEmailNotification(trim($uData['email']), "Reset Password Akun", "Halo " . $uData['username'] . ",<br><br>Password akun Anda telah berhasil direset oleh Administrator. <br><br>Password baru Anda: <strong>$new_pass</strong> <br><br> Harap segera login dan ganti password ini demi keamanan akun Anda.");
                
                if($emailResult === true) {
                    $msg = setTailwindMsg('success', 'Password berhasil direset. Password baru telah dikirimkan ke email user.', 'ph-key');
                } else {
                    $msg = setTailwindMsg('warning', 'Password direset, namun pengiriman email gagal! Error: ' . $emailResult, 'ph-warning-circle');
                }
            } else {
                $msg = setTailwindMsg('warning', 'Password berhasil direset, tetapi sistem email tidak aktif.', 'ph-key');
            }
        }
    }
}

// --- DELETE USER ---
if (isset($_POST['delete_user'])) {
    $id = intval($_POST['delete_id']);
    if ($id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id=$id");
        $msg = setTailwindMsg('success', 'User berhasil dihapus secara permanen.', 'ph-trash');
    } else {
        $msg = setTailwindMsg('danger', 'Tindakan Ditolak: Anda tidak bisa menghapus akun Anda sendiri!', 'ph-prohibit');
    }
}

// --- FETCH DATA ---
$users = $conn->query("SELECT u.*, d.name as div_name FROM users u LEFT JOIN divisions d ON u.division_id = d.id ORDER BY u.role ASC, u.id DESC");
$divisions = []; 
$dRes = $conn->query("SELECT * FROM divisions");
if($dRes) {
    while($d = $dRes->fetch_assoc()) $divisions[] = $d;
}

// Stats
$totalUsers = $users->num_rows;
$adminCount = $conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetch_row()[0];
$staffCount = $totalUsers - $adminCount;

// --- LOAD VIEWS ---
$page_title = "Manage Users";
include 'includes/header.php';
include 'includes/sidebar.php';
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
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-users-three"></i>
                </div>
                Manage Users
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Kelola daftar pengguna, atur hak akses (Role), penempatan divisi, dan kuota cuti.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='manage_users.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
            <button onclick="openModal('addUserModal')" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-user-plus text-xl relative z-10"></i> 
                <span class="relative z-10">Add New User</span>
            </button>
        </div>
    </div>

    <?= $msg ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-users"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Users</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($totalUsers) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1">
            <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-shield-check"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Administrator</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($adminCount) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-briefcase"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Standard Staff</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($staffCount) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-2 transition-colors duration-300">
        <div class="relative group">
            <i class="ph-bold ph-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
            <input type="text" id="searchInput" class="w-full pl-12 pr-4 py-3.5 bg-transparent border-none text-sm font-medium focus:ring-0 outline-none dark:text-white placeholder-slate-400" placeholder="Pencarian cepat nama user, email, role, atau divisi..." onkeyup="liveSearch()">
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors duration-300 flex flex-col min-h-[500px] relative">
        
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/30">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Tampilkan</span>
                <select id="pageSize" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-indigo-500/50 outline-none cursor-pointer">
                    <option value="10">10 Baris</option>
                    <option value="50">50 Baris</option>
                    <option value="100">100 Baris</option>
                </select>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Data</span>
            </div>
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400" id="paginationInfo">
                Menampilkan 0 dari 0 data
            </div>
        </div>

        <div class="overflow-x-auto modern-scrollbar flex-grow pb-32">
            <table class="w-full text-left border-collapse table-fixed min-w-[1100px]">
                <thead class="bg-slate-50/80 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[25%]">User Profile</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[20%]">Role & Division</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[15%]">Job Title</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-[10%]">Leave Quota</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-[10%]">Security</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-[8%]">Sign</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-[12%]">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if($users && $users->num_rows > 0): ?>
                        <?php while($row = $users->fetch_assoc()): ?>
                        <tr class="data-row hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-4 align-middle search-target">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-100 to-indigo-50 dark:from-indigo-500/20 dark:to-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-black text-sm uppercase shrink-0 ring-2 ring-white dark:ring-[#24303F] shadow-sm">
                                        <?= strtoupper(substr($row['username'], 0, 1)) ?>
                                    </div>
                                    <div class="flex-1 min-w-0 pr-2">
                                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-0.5 break-words whitespace-normal group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                            <?= htmlspecialchars($row['username']) ?>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400 font-medium break-words whitespace-normal">
                                            <i class="ph-fill ph-envelope-simple text-slate-400 shrink-0"></i>
                                            <?= htmlspecialchars($row['email']) ?>
                                        </div>
                                        <?php if(!empty($row['phone'])): ?>
                                        <div class="flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500 font-medium mt-0.5">
                                            <i class="ph-fill ph-phone shrink-0"></i> <?= htmlspecialchars($row['phone']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-middle search-target">
                                <div class="flex flex-col items-start gap-1.5">
                                    <?php if($row['role'] == 'admin'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20 shadow-sm">
                                            <i class="ph-fill ph-shield-check text-xs"></i> ADMIN
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 shadow-sm">
                                            <i class="ph-fill ph-user text-xs"></i> STANDARD
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="text-xs text-slate-600 dark:text-slate-400 font-bold break-words whitespace-normal leading-snug">
                                        <?= htmlspecialchars($row['div_name'] ?? '- No Division -') ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-middle search-target">
                                <span class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-300 font-bold border border-slate-200 dark:border-slate-600 text-[11px] inline-block break-words whitespace-normal text-center shadow-sm">
                                    <?= htmlspecialchars($row['job_title'] ?? 'Staff') ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 align-middle text-center">
                                <div class="inline-flex flex-col items-center justify-center w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20 shadow-sm" title="Sisa Cuti Tahunan">
                                    <span class="font-black text-lg leading-none"><?= $row['leave_quota'] ?></span>
                                    <span class="text-[8px] font-bold uppercase tracking-widest opacity-70">Hari</span>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-middle text-center">
                                <?php if($row['must_change_password'] == 1): ?>
                                    <span class="inline-flex items-center justify-center gap-1 px-2 py-1.5 rounded-lg bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 text-[9px] font-bold uppercase tracking-widest shadow-sm" title="Menunggu user mengganti password">
                                        <i class="ph-fill ph-warning-circle text-xs"></i> Change
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center justify-center gap-1 px-2 py-1.5 rounded-lg bg-slate-50 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 text-[9px] font-bold uppercase tracking-widest shadow-sm">
                                        <i class="ph-fill ph-check-circle text-xs"></i> Secure
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 align-middle text-center">
                                <?php if($row['signature']): ?>
                                    <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-500 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center mx-auto border border-emerald-200 dark:border-emerald-500/20 shadow-sm" title="Signature Uploaded">
                                        <i class="ph-bold ph-pen-nib text-sm"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="w-8 h-8 rounded-full bg-slate-50 text-slate-400 dark:bg-slate-800 dark:text-slate-500 flex items-center justify-center mx-auto border border-slate-200 dark:border-slate-700 shadow-sm" title="No Signature">
                                        <i class="ph-bold ph-minus text-sm"></i>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 align-middle text-center relative">
                                <?php $userJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                
                                <div class="relative inline-block text-left" data-dropdown>
                                    <button type="button" onclick="toggleActionMenu(event, <?= $row['id'] ?>)" class="inline-flex justify-center items-center w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-400 dark:hover:text-indigo-400 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 dropdown-toggle-btn active:scale-95" aria-expanded="true" aria-haspopup="true">
                                        <i class="ph-bold ph-dots-three-vertical text-lg pointer-events-none"></i>
                                    </button>

                                    <div id="action-menu-<?= $row['id'] ?>" class="dropdown-menu hidden absolute right-8 top-0 w-44 bg-white dark:bg-[#24303F] rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 z-[100] overflow-hidden text-left origin-top-right transition-all divide-y divide-slate-50 dark:divide-slate-700/50">
                                        
                                        <div class="py-1">
                                            <button type="button" onclick='openEditModal(<?= $userJson ?>)' class="w-full text-left group flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400 transition-colors">
                                                <i class="ph-bold ph-pencil-simple text-base text-slate-400 group-hover:text-indigo-500"></i> Edit Profile
                                            </button>
                                        </div>
                                        
                                        <div class="py-1">
                                            <button type="button" onclick="openResetModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')" class="w-full text-left group flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10 dark:hover:text-amber-400 transition-colors">
                                                <i class="ph-bold ph-key text-base text-slate-400 group-hover:text-amber-500"></i> Reset Password
                                            </button>
                                        </div>

                                        <div class="py-1 bg-slate-50/50 dark:bg-slate-800/30">
                                            <button type="button" onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')" class="w-full text-left group flex items-center gap-2.5 px-4 py-2.5 text-xs font-black text-rose-600 hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:bg-rose-600 dark:hover:text-white transition-colors">
                                                <i class="ph-bold ph-trash text-base"></i> Delete User
                                            </button>
                                        </div>
                                        
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="emptyRow">
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-users text-4xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Tidak Ada Data</h4>
                                    <p class="text-sm font-medium">Belum ada akun pengguna atau tidak ditemukan dari pencarian.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-between w-full mt-auto shrink-0 z-20">
            <div class="flex-1 flex justify-start">
                <button id="btnPrev" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="ph-bold ph-arrow-left"></i> Previous
                </button>
            </div>
            
            <div id="pageNumbers" class="flex-1 flex items-center justify-center gap-1.5">
                </div>
            
            <div class="flex-1 flex justify-end">
                <button id="btnNext" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    Next <i class="ph-bold ph-arrow-right"></i>
                </button>
            </div>
        </div>

    </div>
</div>

<div id="addUserModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('addUserModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh] overflow-hidden transform transition-all scale-95 opacity-0 modal-box">
        <form method="POST" enctype="multipart/form-data" class="flex flex-col h-full">
            <div class="px-6 py-5 border-b border-indigo-500/20 bg-indigo-600 text-white flex justify-between items-center shrink-0">
                <h3 class="text-base font-black flex items-center gap-2 tracking-wide"><i class="ph-bold ph-user-plus text-xl"></i> Tambah User Baru</h3>
                <button type="button" onclick="closeModal('addUserModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto modern-scrollbar flex-1 bg-slate-50/30 dark:bg-slate-800/20">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <div class="space-y-4">
                        <h4 class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-widest border-b border-slate-200 dark:border-slate-700 pb-2 mb-4 flex items-center"><i class="ph-fill ph-identification-card text-indigo-500 text-lg mr-2"></i> Data Personal</h4>
                        
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="text" name="username" required class="w-full pl-11 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Email Aktif <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-bold ph-envelope-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="email" name="email" required class="w-full pl-11 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Nomor Telepon</label>
                            <div class="relative">
                                <i class="ph-bold ph-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="text" name="phone" class="w-full pl-11 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h4 class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-widest border-b border-slate-200 dark:border-slate-700 pb-2 mb-4 flex items-center"><i class="ph-fill ph-briefcase text-indigo-500 text-lg mr-2"></i> Akses & Posisi</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Hak Akses <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <select name="role" required class="w-full pl-4 pr-10 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                        <option value="standard">Standard</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Kuota Cuti <span class="text-rose-500">*</span></label>
                                <input type="number" name="leave_quota" value="12" required class="w-full px-4 py-3 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 rounded-xl text-sm font-black text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500/50 outline-none transition-all text-center shadow-inner">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Divisi Departemen <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <select name="division_id" required class="w-full pl-4 pr-10 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                    <option value="">-- Pilih Divisi --</option>
                                    <?php foreach($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option><?php endforeach; ?>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Jabatan (Job Title) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <select name="job_title" required class="w-full pl-4 pr-10 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                    <option value="Staff">Staff</option>
                                    <option value="Manager">Manager</option>
                                    <option value="General Manager">General Manager</option>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2 mt-2 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-xs font-bold text-slate-800 dark:text-white uppercase tracking-widest flex items-center"><i class="ph-bold ph-pen-nib text-indigo-500 text-lg mr-2"></i> Digital Signature</label>
                            <button type="button" onclick="clearAddSign()" class="text-[10px] font-bold uppercase tracking-widest text-rose-500 hover:text-rose-700 transition-colors flex items-center gap-1 bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 px-2 py-1 rounded-md"><i class="ph-bold ph-eraser"></i> Bersihkan</button>
                        </div>
                        
                        <div class="relative h-48 w-full border-2 border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 rounded-2xl overflow-hidden group">
                            <canvas id="add-sig-canvas" class="absolute inset-0 w-full h-full z-10 cursor-crosshair"></canvas>
                            <div id="add-sig-placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 dark:text-slate-500 pointer-events-none transition-opacity group-hover:opacity-50">
                                <i class="ph-fill ph-signature text-4xl mb-2 opacity-30"></i>
                                <span class="text-xs font-bold">Tulis Tanda Tangan Anda Di Sini</span>
                            </div>
                        </div>
                        <input type="hidden" name="signature_data" id="add-sig-data">
                        
                        <div class="flex items-center gap-4 my-5">
                            <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 dark:bg-slate-800 px-3 py-1 rounded-lg border border-slate-100 dark:border-slate-700">ATAU UPLOAD FILE</span>
                            <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                        </div>
                        
                        <input type="file" name="signature_file" accept="image/png" class="w-full block text-xs text-slate-500 file:mr-4 file:py-3 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-500/10 dark:file:text-indigo-400 dark:hover:file:bg-indigo-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 transition-all shadow-inner">
                        <p class="text-[10px] font-medium text-slate-400 mt-2 italic flex items-center gap-1"><i class="ph-fill ph-info"></i> Hanya mendukung format file .PNG transparan.</p>
                    </div>

                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3 bg-slate-50/50 dark:bg-slate-800/50 shrink-0">
                <button type="button" onclick="closeModal('addUserModal')" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm">Batal</button>
                <button type="submit" name="add_user" onclick="saveAddSign()" class="px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-500/30 active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-floppy-disk text-lg"></i> Simpan User Baru
                </button>
            </div>
        </form>
    </div>
</div>

<div id="editUserModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('editUserModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh] overflow-hidden transform transition-all scale-95 opacity-0 modal-box">
        <form method="POST" enctype="multipart/form-data" class="flex flex-col h-full">
            <div class="px-6 py-5 border-b border-blue-500/20 bg-blue-600 text-white flex justify-between items-center shrink-0">
                <h3 class="text-base font-black flex items-center gap-2 tracking-wide"><i class="ph-bold ph-pencil-simple text-xl"></i> Edit Data User</h3>
                <button type="button" onclick="closeModal('editUserModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto modern-scrollbar flex-1 bg-slate-50/30 dark:bg-slate-800/20">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <div class="space-y-4">
                        <h4 class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-widest border-b border-slate-200 dark:border-slate-700 pb-2 mb-4 flex items-center"><i class="ph-fill ph-identification-card text-blue-500 text-lg mr-2"></i> Data Personal</h4>
                        
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="text" name="username" id="edit_username" required class="w-full pl-11 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500/50 outline-none dark:text-white transition-all shadow-inner">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Email Aktif <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-bold ph-envelope-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="email" name="email" id="edit_email" required class="w-full pl-11 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/50 outline-none dark:text-white transition-all shadow-inner">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Nomor Telepon</label>
                            <div class="relative">
                                <i class="ph-bold ph-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="text" name="phone" id="edit_phone" class="w-full pl-11 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/50 outline-none dark:text-white transition-all shadow-inner">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h4 class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-widest border-b border-slate-200 dark:border-slate-700 pb-2 mb-4 flex items-center"><i class="ph-fill ph-briefcase text-blue-500 text-lg mr-2"></i> Akses & Posisi</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Hak Akses <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <select name="role" id="edit_role" required class="w-full pl-4 pr-10 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                        <option value="standard">Standard</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Kuota Cuti <span class="text-rose-500">*</span></label>
                                <input type="number" name="leave_quota" id="edit_quota" required class="w-full px-4 py-3 bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 rounded-xl text-sm font-black text-blue-600 dark:text-blue-400 focus:ring-2 focus:ring-blue-500/50 outline-none transition-all text-center shadow-inner">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Divisi Departemen <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <select name="division_id" id="edit_division" required class="w-full pl-4 pr-10 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                    <option value="">-- Pilih Divisi --</option>
                                    <?php foreach($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option><?php endforeach; ?>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Jabatan (Job Title) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <select name="job_title" id="edit_job_title" required class="w-full pl-4 pr-10 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                    <option value="Staff">Staff</option>
                                    <option value="Manager">Manager</option>
                                    <option value="General Manager">General Manager</option>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2 mt-2 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-xs font-bold text-slate-800 dark:text-white uppercase tracking-widest flex items-center"><i class="ph-bold ph-pen-nib text-blue-500 text-lg mr-2"></i> Update Signature</label>
                            <button type="button" onclick="clearEditSign()" class="text-[10px] font-bold uppercase tracking-widest text-rose-500 hover:text-rose-700 transition-colors flex items-center gap-1 bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 px-2 py-1 rounded-md"><i class="ph-bold ph-eraser"></i> Bersihkan</button>
                        </div>
                        
                        <div class="relative h-48 w-full border-2 border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 rounded-2xl overflow-hidden group">
                            <canvas id="edit-sig-canvas" class="absolute inset-0 w-full h-full z-10 cursor-crosshair"></canvas>
                            <div id="edit-sig-placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 dark:text-slate-500 pointer-events-none transition-opacity group-hover:opacity-50">
                                <i class="ph-fill ph-signature text-4xl mb-2 opacity-30"></i>
                                <span class="text-[11px] font-bold">Tulis ulang jika ingin mengubah. Abaikan jika tetap.</span>
                            </div>
                        </div>
                        <input type="hidden" name="edit_signature_data" id="edit-sig-data">
                        
                        <div class="flex items-center gap-4 my-5">
                            <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 dark:bg-slate-800 px-3 py-1 rounded-lg border border-slate-100 dark:border-slate-700">ATAU UPLOAD FILE</span>
                            <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                        </div>
                        
                        <input type="file" name="edit_signature_file" accept="image/png" class="w-full block text-xs text-slate-500 file:mr-4 file:py-3 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-500/10 dark:file:text-blue-400 dark:hover:file:bg-blue-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 transition-all shadow-inner">
                    </div>

                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3 bg-slate-50/50 dark:bg-slate-800/50 shrink-0">
                <button type="button" onclick="closeModal('editUserModal')" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm">Batal</button>
                <button type="submit" name="edit_user" onclick="saveEditSign()" class="px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-md shadow-blue-500/30 active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-check text-lg"></i> Update Data
                </button>
            </div>
        </form>
    </div>
</div>

<div id="resetModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('resetModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 modal-box text-center overflow-hidden flex flex-col">
        <form method="POST">
            <div class="pt-8 pb-6 px-6">
                <div class="w-20 h-20 rounded-full bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center mx-auto mb-5 border border-amber-100 dark:border-amber-500/20 shadow-inner">
                    <i class="ph-fill ph-password text-4xl text-amber-500 dark:text-amber-400"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white mb-2">Reset Password?</h3>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 leading-relaxed">Password baru akan otomatis dibuat dan dikirimkan ke email <br><strong id="resetName" class="text-slate-700 dark:text-slate-200 mt-1 inline-block"></strong>.</p>
                <input type="hidden" name="reset_id" id="reset_id_input">
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 grid grid-cols-2 gap-3 shrink-0 rounded-b-3xl">
                <button type="button" onclick="closeModal('resetModal')" class="py-3 rounded-xl font-bold text-sm text-slate-600 bg-white hover:bg-slate-50 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-all shadow-sm">Batal</button>
                <button type="submit" name="reset_password" class="py-3 rounded-xl font-bold text-sm text-white bg-amber-500 hover:bg-amber-600 transition-all shadow-md shadow-amber-500/30 active:scale-95">Ya, Reset</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('deleteModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 modal-box text-center overflow-hidden flex flex-col">
        <form method="POST">
            <div class="pt-8 pb-6 px-6">
                <div class="w-20 h-20 rounded-full bg-rose-50 dark:bg-rose-500/10 flex items-center justify-center mx-auto mb-5 border border-rose-100 dark:border-rose-500/20 shadow-inner">
                    <i class="ph-fill ph-warning-circle text-4xl text-rose-500 dark:text-rose-400"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white mb-2">Hapus Pengguna?</h3>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 leading-relaxed">Anda yakin ingin menghapus akses untuk <br><strong id="deleteName" class="text-rose-500 dark:text-rose-400 mt-1 inline-block"></strong> secara permanen?</p>
                <input type="hidden" name="delete_id" id="delete_id_input">
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 grid grid-cols-2 gap-3 shrink-0 rounded-b-3xl">
                <button type="button" onclick="closeModal('deleteModal')" class="py-3 rounded-xl font-bold text-sm text-slate-600 bg-white hover:bg-slate-50 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-all shadow-sm">Batal</button>
                <button type="submit" name="delete_user" class="py-3 rounded-xl font-bold text-sm text-white bg-rose-600 hover:bg-rose-700 transition-all shadow-md shadow-rose-500/30 active:scale-95">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<script>
    // --- LIVE SEARCH LOGIC ---
    function liveSearch() {
        let input = document.getElementById("searchInput").value.toLowerCase();
        let rows = document.querySelectorAll(".data-row");

        if(input.trim() !== '') {
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                if (text.includes(input)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
            document.getElementById("paginationControls").classList.add('hidden');
        } else {
            document.getElementById("paginationControls").classList.remove('hidden');
            if(typeof renderTable === 'function') renderTable();
        }
    }

    // --- PAGINATION LOGIC (Vanilla JS) ---
    document.addEventListener('DOMContentLoaded', () => {
        const rows = Array.from(document.querySelectorAll('#tableBody tr.data-row'));
        const totalRows = rows.length;
        
        if(totalRows === 0) return;

        const pageSizeSelect = document.getElementById('pageSize');
        const paginationInfo = document.getElementById('paginationInfo');
        const btnPrev = document.getElementById('btnPrev');
        const btnNext = document.getElementById('btnNext');
        const pageNumbersContainer = document.getElementById('pageNumbers');

        let currentPage = 1;
        let rowsPerPage = parseInt(pageSizeSelect.value);

        window.renderTable = function() {
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            rows.forEach((row, index) => {
                if (index >= start && index < end) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            const currentEnd = end > totalRows ? totalRows : end;
            paginationInfo.innerHTML = `Menampilkan <span class="text-indigo-600 dark:text-indigo-400 font-black">${start + 1} - ${currentEnd}</span> dari <span class="font-black text-slate-800 dark:text-white">${totalRows}</span> data`;

            updatePaginationButtons();
        }

        function updatePaginationButtons() {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            
            btnPrev.disabled = currentPage === 1;
            btnNext.disabled = currentPage === totalPages;

            pageNumbersContainer.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    const pageBtn = document.createElement('button');
                    pageBtn.innerText = i;
                    if (i === currentPage) {
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-black text-white bg-indigo-600 shadow-sm shadow-indigo-500/30 flex items-center justify-center transition-all";
                    } else {
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700 transition-all flex items-center justify-center";
                        pageBtn.onclick = () => { currentPage = i; window.renderTable(); };
                    }
                    pageNumbersContainer.appendChild(pageBtn);
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    const dots = document.createElement('span');
                    dots.innerText = '...';
                    dots.className = "w-8 h-8 flex items-center justify-center text-slate-400 text-xs font-black tracking-widest";
                    pageNumbersContainer.appendChild(dots);
                }
            }
        }

        pageSizeSelect.addEventListener('change', (e) => {
            rowsPerPage = parseInt(e.target.value);
            currentPage = 1;
            window.renderTable();
        });

        btnPrev.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; window.renderTable(); }
        });

        btnNext.addEventListener('click', () => {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            if (currentPage < totalPages) { currentPage++; window.renderTable(); }
        });

        window.renderTable();
    });

    // --- CUSTOM DROPDOWN MENU LOGIC (Fix Propagation) ---
    let currentOpenDropdown = null;
    
    function toggleActionMenu(e, id) {
        e.stopPropagation(); 
        const menu = document.getElementById('action-menu-' + id);
        
        if (currentOpenDropdown && currentOpenDropdown !== menu) {
            currentOpenDropdown.classList.add('hidden');
        }
        
        menu.classList.toggle('hidden');
        currentOpenDropdown = menu.classList.contains('hidden') ? null : menu;
    }
    
    document.addEventListener('click', (e) => {
        if (currentOpenDropdown && !currentOpenDropdown.contains(e.target)) {
            currentOpenDropdown.classList.add('hidden');
            currentOpenDropdown = null;
        }
    });

    // --- CUSTOM MODAL HANDLERS ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        
        modal.classList.remove('hidden');
        
        setTimeout(() => {
            box.classList.remove('scale-95', 'opacity-0');
            box.classList.add('scale-100', 'opacity-100');
            modal.classList.remove('opacity-0');
            
            // Adjust canvas specifically for Add/Edit Modals
            if(id === 'addUserModal') {
                resizeCanvas(document.getElementById('add-sig-canvas'));
                addPad.clear(); 
                document.getElementById('add-sig-placeholder').style.display = 'flex';
            }
            if(id === 'editUserModal') {
                resizeCanvas(document.getElementById('edit-sig-canvas'));
                editPad.clear(); 
                document.getElementById('edit-sig-placeholder').style.display = 'flex';
            }
        }, 50);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        
        box.classList.remove('scale-100', 'opacity-100');
        box.classList.add('scale-95', 'opacity-0');
        modal.classList.add('opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Modal Triggers for Data Binding
    function openEditModal(data) {
        document.getElementById("edit_id").value = data.id;
        document.getElementById("edit_username").value = data.username;
        document.getElementById("edit_email").value = data.email;
        document.getElementById("edit_phone").value = data.phone;
        document.getElementById("edit_role").value = data.role;
        document.getElementById("edit_division").value = data.division_id;
        document.getElementById("edit_job_title").value = data.job_title;
        document.getElementById("edit_quota").value = data.leave_quota;
        openModal('editUserModal');
    }

    function openResetModal(id, name) {
        document.getElementById("reset_id_input").value = id;
        document.getElementById("resetName").innerText = name;
        openModal('resetModal');
    }

    function openDeleteModal(id, name) {
        document.getElementById("delete_id_input").value = id;
        document.getElementById("deleteName").innerText = name;
        openModal('deleteModal');
    }

    // --- SIGNATURE PAD LOGIC ---
    var addPad, editPad;

    function initSignaturePad(canvasId, placeholderId) {
        var canvas = document.getElementById(canvasId);
        var placeholder = document.getElementById(placeholderId);
        
        const isDarkMode = document.documentElement.classList.contains('dark');
        const penCol = isDarkMode ? '#e2e8f0' : '#1e293b'; 

        var pad = new SignaturePad(canvas, { 
            backgroundColor: 'rgba(255, 255, 255, 0)', 
            penColor: penCol 
        });
        
        pad.addEventListener("beginStroke", () => { 
            if(placeholder) placeholder.style.display = 'none'; 
        });
        return pad;
    }

    function resizeCanvas(canvas) {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }

    document.addEventListener("DOMContentLoaded", function() {
        addPad = initSignaturePad('add-sig-canvas', 'add-sig-placeholder');
        editPad = initSignaturePad('edit-sig-canvas', 'edit-sig-placeholder');
    });

    function clearAddSign() { 
        addPad.clear(); 
        document.getElementById('add-sig-placeholder').style.display = 'flex'; 
    }
    
    function saveAddSign() { 
        if(!addPad.isEmpty()) {
            document.getElementById('add-sig-data').value = addPad.toDataURL('image/png'); 
        }
    }
    
    function clearEditSign() { 
        editPad.clear(); 
        document.getElementById('edit-sig-placeholder').style.display = 'flex'; 
    }
    
    function saveEditSign() { 
        if(!editPad.isEmpty()) {
            document.getElementById('edit-sig-data').value = editPad.toDataURL('image/png'); 
        }
    }
</script>

<?php include 'includes/footer.php'; ?>