<?php
// =========================================
// 1. INITIALIZATION & CONFIG
// =========================================
ini_set('display_errors', 0); // Matikan display error di production agar UI tidak rusak
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Konfigurasi & Fungsi DULUAN (Best Practice)
require_once '../config/database.php'; 
// include '../config/functions.php'; // Sesuaikan jika ada fungsi eksternal

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
            if (function_exists('sendEmailNotification')) {
                sendEmailNotification(trim($uData['email']), "Reset Password", "Password baru Anda: $new_pass <br><br> Harap segera login dan ganti password ini.");
            }
            $msg = setTailwindMsg('warning', 'Password berhasil direset. Password baru telah dibuat (cek email user).', 'ph-key');
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

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 flex items-center justify-center text-xl shadow-inner">
                    <i class="ph-bold ph-users-three"></i>
                </div>
                Manage Users
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Kelola daftar pengguna, atur hak akses peran (Role), dan kuota cuti staf.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openModal('addUserModal')" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-user-plus text-lg relative z-10"></i> 
                <span class="relative z-10">Tambah User</span>
            </button>
        </div>
    </div>

    <?= $msg ?>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors duration-300">
        <div class="overflow-x-auto modern-scrollbar w-full">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">User Profile</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Role & Division</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Job Title</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Quota</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Security</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Sign</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if($users && $users->num_rows > 0): ?>
                        <?php while($row = $users->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-4 align-middle">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-100 to-indigo-50 dark:from-indigo-500/20 dark:to-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-black text-sm uppercase shrink-0 ring-2 ring-white dark:ring-[#24303F] shadow-sm">
                                        <?= strtoupper(substr($row['username'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-0.5 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                            <?= htmlspecialchars($row['username']) ?>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 font-medium">
                                            <i class="ph-fill ph-envelope-simple text-slate-400"></i>
                                            <?= htmlspecialchars($row['email']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-middle">
                                <div class="flex flex-col items-start gap-1.5">
                                    <?php if($row['role'] == 'admin'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest bg-rose-100 text-rose-700 border border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20">
                                            <i class="ph-fill ph-shield-check"></i> ADMIN
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                                            <i class="ph-fill ph-user"></i> STANDARD
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="text-xs text-slate-600 dark:text-slate-400 font-bold truncate max-w-[150px]" title="<?= htmlspecialchars($row['div_name'] ?? 'No Division') ?>">
                                        <?= htmlspecialchars($row['div_name'] ?? '- NO DIVISION -') ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-middle">
                                <span class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-300 font-bold border border-slate-200 dark:border-slate-600 text-xs">
                                    <?= htmlspecialchars($row['job_title']) ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 align-middle text-center">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 font-black text-sm border border-blue-100 dark:border-blue-500/20 shadow-sm" title="Sisa Cuti: <?= $row['leave_quota'] ?> Hari">
                                    <?= $row['leave_quota'] ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 align-middle text-center">
                                <?php if($row['must_change_password'] == 1): ?>
                                    <span class="inline-flex items-center justify-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 text-[10px] font-bold uppercase tracking-widest" title="User harus mengganti password saat login">
                                        <i class="ph-fill ph-warning-circle text-xs"></i> Change Pass
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center justify-center gap-1 px-2.5 py-1 rounded-lg bg-slate-50 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                                        <i class="ph-fill ph-check-circle text-xs"></i> Aman
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 align-middle text-center">
                                <?php if($row['signature']): ?>
                                    <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-500 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center mx-auto" title="Signature Uploaded">
                                        <i class="ph-bold ph-pen-nib text-lg"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="w-8 h-8 rounded-full bg-slate-50 text-slate-300 dark:bg-slate-800 dark:text-slate-600 flex items-center justify-center mx-auto" title="No Signature">
                                        <i class="ph-bold ph-minus text-lg"></i>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 align-middle text-center">
                                <div class="flex items-center justify-center gap-2">
                                    
                                    <?php $userJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                    
                                    <button onclick='openEditModal(<?= $userJson ?>)' class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 hover:bg-blue-600 hover:text-white dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-blue-600 dark:hover:text-white transition-all shadow-sm flex items-center justify-center active:scale-95" title="Edit User">
                                        <i class="ph-bold ph-pencil-simple text-sm"></i>
                                    </button>

                                    <button onclick="openResetModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')" class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 hover:bg-amber-500 hover:text-white dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-amber-500 dark:hover:text-white transition-all shadow-sm flex items-center justify-center active:scale-95" title="Reset Password">
                                        <i class="ph-bold ph-key text-sm"></i>
                                    </button>

                                    <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')" class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 hover:bg-rose-600 hover:text-white dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-rose-600 dark:hover:text-white transition-all shadow-sm flex items-center justify-center active:scale-95" title="Hapus User">
                                        <i class="ph-bold ph-trash text-sm"></i>
                                    </button>

                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-users text-4xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Tidak Ada Data Pengguna</h4>
                                    <p class="text-sm font-medium">Belum ada akun pengguna yang terdaftar di dalam sistem.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($users && $users->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-center">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white dark:bg-[#1A222C] px-4 py-1.5 rounded-full border border-slate-100 dark:border-slate-700 shadow-sm">
                Total Pengguna: <span class="text-indigo-600 dark:text-indigo-400 ml-1"><?= $users->num_rows ?> Akun</span>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="addUserModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('addUserModal')"></div>
    
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh] overflow-hidden transform transition-all scale-95 opacity-0 modal-box">
        <form method="POST" enctype="multipart/form-data" class="flex flex-col h-full">
            
            <div class="px-6 py-5 border-b border-indigo-500/20 bg-indigo-600 text-white flex justify-between items-center shrink-0">
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-user-plus text-xl"></i> Tambah User Baru</h3>
                <button type="button" onclick="closeModal('addUserModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto modern-scrollbar flex-1 bg-slate-50/30 dark:bg-slate-800/20">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="space-y-4">
                        <h4 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2 mb-4"><i class="ph-fill ph-identification-card text-indigo-500 mr-2"></i>Data Personal</h4>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="username" required class="w-full pl-11 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Email Aktif <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-bold ph-envelope-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="email" name="email" required class="w-full pl-11 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Nomor Telepon</label>
                            <div class="relative">
                                <i class="ph-bold ph-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="phone" class="w-full pl-11 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-sm">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h4 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2 mb-4"><i class="ph-fill ph-briefcase text-indigo-500 mr-2"></i>Akses & Posisi</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Hak Akses <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <select name="role" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all shadow-sm">
                                        <option value="standard">Standard</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Kuota Cuti <span class="text-rose-500">*</span></label>
                                <input type="number" name="leave_quota" value="12" required class="w-full px-4 py-2.5 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 rounded-xl text-sm font-black text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500/50 outline-none transition-all text-center shadow-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Divisi Departemen <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <select name="division_id" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all shadow-sm">
                                    <option value="">-- Pilih Divisi --</option>
                                    <?php foreach($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option><?php endforeach; ?>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Jabatan (Job Title) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <select name="job_title" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all shadow-sm">
                                    <option value="Staff">Staff</option>
                                    <option value="Manager">Manager</option>
                                    <option value="General Manager">General Manager</option>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2 mt-4 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-xs font-bold text-slate-800 dark:text-white uppercase tracking-widest"><i class="ph-bold ph-pen-nib text-indigo-500 mr-1"></i> Digital Signature</label>
                            <button type="button" onclick="clearAddSign()" class="text-xs font-bold text-rose-500 hover:text-rose-700 transition-colors flex items-center gap-1"><i class="ph-bold ph-eraser"></i> Bersihkan Canvas</button>
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
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-50 dark:bg-slate-800 px-3 py-1 rounded-full border border-slate-100 dark:border-slate-700">ATAU UPLOAD FILE</span>
                            <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                        </div>
                        
                        <input type="file" name="signature_file" accept="image/png" class="w-full block text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-500/10 dark:file:text-indigo-400 dark:hover:file:bg-indigo-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 transition-all">
                        <p class="text-[10px] text-slate-400 mt-2 italic flex items-center gap-1"><i class="ph-fill ph-info"></i> Hanya mendukung format file .PNG transparan (tanpa background).</p>
                    </div>

                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3 bg-white dark:bg-[#24303F] shrink-0 rounded-b-3xl">
                <button type="button" onclick="closeModal('addUserModal')" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="add_user" onclick="saveAddSign()" class="px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-sm active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-floppy-disk text-lg"></i> Simpan User
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
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-pencil-simple text-xl"></i> Edit Data User</h3>
                <button type="button" onclick="closeModal('editUserModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto modern-scrollbar flex-1 bg-slate-50/30 dark:bg-slate-800/20">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="space-y-4">
                        <h4 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2 mb-4"><i class="ph-fill ph-identification-card text-blue-500 mr-2"></i>Data Personal</h4>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="username" id="edit_username" required class="w-full pl-11 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500/50 outline-none dark:text-white transition-all shadow-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Email Aktif <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-bold ph-envelope-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="email" name="email" id="edit_email" required class="w-full pl-11 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/50 outline-none dark:text-white transition-all shadow-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Nomor Telepon</label>
                            <div class="relative">
                                <i class="ph-bold ph-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="phone" id="edit_phone" class="w-full pl-11 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/50 outline-none dark:text-white transition-all shadow-sm">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h4 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2 mb-4"><i class="ph-fill ph-briefcase text-blue-500 mr-2"></i>Akses & Posisi</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Hak Akses <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <select name="role" id="edit_role" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500/50 dark:text-white appearance-none outline-none transition-all shadow-sm">
                                        <option value="standard">Standard</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Kuota Cuti <span class="text-rose-500">*</span></label>
                                <input type="number" name="leave_quota" id="edit_quota" required class="w-full px-4 py-2.5 bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 rounded-xl text-sm font-black text-blue-600 dark:text-blue-400 focus:ring-2 focus:ring-blue-500/50 outline-none transition-all text-center shadow-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Divisi Departemen <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <select name="division_id" id="edit_division" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500/50 dark:text-white appearance-none outline-none transition-all shadow-sm">
                                    <option value="">-- Pilih Divisi --</option>
                                    <?php foreach($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option><?php endforeach; ?>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Jabatan (Job Title) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <select name="job_title" id="edit_job_title" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500/50 dark:text-white appearance-none outline-none transition-all shadow-sm">
                                    <option value="Staff">Staff</option>
                                    <option value="Manager">Manager</option>
                                    <option value="General Manager">General Manager</option>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2 mt-4 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-xs font-bold text-slate-800 dark:text-white uppercase tracking-widest"><i class="ph-bold ph-pen-nib text-blue-500 mr-1"></i> Update Digital Signature</label>
                            <button type="button" onclick="clearEditSign()" class="text-xs font-bold text-rose-500 hover:text-rose-700 transition-colors flex items-center gap-1"><i class="ph-bold ph-eraser"></i> Bersihkan Canvas</button>
                        </div>
                        
                        <div class="relative h-48 w-full border-2 border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 rounded-2xl overflow-hidden group">
                            <canvas id="edit-sig-canvas" class="absolute inset-0 w-full h-full z-10 cursor-crosshair"></canvas>
                            <div id="edit-sig-placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 dark:text-slate-500 pointer-events-none transition-opacity group-hover:opacity-50">
                                <i class="ph-fill ph-signature text-4xl mb-2 opacity-30"></i>
                                <span class="text-xs font-bold">Tulis Ulang Tanda Tangan (Abaikan jika tidak diubah)</span>
                            </div>
                        </div>
                        <input type="hidden" name="edit_signature_data" id="edit-sig-data">
                        
                        <div class="flex items-center gap-4 my-5">
                            <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-50 dark:bg-slate-800 px-3 py-1 rounded-full border border-slate-100 dark:border-slate-700">ATAU UPLOAD FILE</span>
                            <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                        </div>
                        
                        <input type="file" name="edit_signature_file" accept="image/png" class="w-full block text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-500/10 dark:file:text-blue-400 dark:hover:file:bg-blue-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 transition-all">
                    </div>

                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3 bg-white dark:bg-[#24303F] shrink-0 rounded-b-3xl">
                <button type="button" onclick="closeModal('editUserModal')" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="edit_user" onclick="saveEditSign()" class="px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-check text-lg"></i> Update Data
                </button>
            </div>
        </form>
    </div>
</div>

<div id="resetModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('resetModal')"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 modal-box text-center overflow-hidden flex flex-col">
        <form method="POST">
            <div class="pt-8 pb-6 px-6">
                <div class="w-20 h-20 rounded-full bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center mx-auto mb-5 border border-amber-100 dark:border-amber-500/20">
                    <i class="ph-fill ph-password text-4xl text-amber-500 dark:text-amber-400"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white mb-2">Reset Password?</h3>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 leading-relaxed">Password baru akan otomatis dibuat dan dikirimkan ke email <br><strong id="resetName" class="text-slate-700 dark:text-slate-200 mt-1 inline-block"></strong>.</p>
                <input type="hidden" name="reset_id" id="reset_id_input">
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 grid grid-cols-2 gap-3 shrink-0 rounded-b-3xl">
                <button type="button" onclick="closeModal('resetModal')" class="py-3 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 border border-transparent hover:border-slate-200 dark:hover:border-slate-600 transition-all">Batal</button>
                <button type="submit" name="reset_password" class="py-3 rounded-xl font-bold text-sm text-white bg-amber-500 hover:bg-amber-600 transition-all shadow-md shadow-amber-500/30 active:scale-95">Ya, Reset</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('deleteModal')"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 modal-box text-center overflow-hidden flex flex-col">
        <form method="POST">
            <div class="pt-8 pb-6 px-6">
                <div class="w-20 h-20 rounded-full bg-rose-50 dark:bg-rose-500/10 flex items-center justify-center mx-auto mb-5 border border-rose-100 dark:border-rose-500/20">
                    <i class="ph-fill ph-warning-circle text-4xl text-rose-500 dark:text-rose-400"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white mb-2">Hapus Akses Pengguna?</h3>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 leading-relaxed">Anda yakin ingin menghapus akses untuk <strong id="deleteName" class="text-rose-500 dark:text-rose-400"></strong> secara permanen?</p>
                <input type="hidden" name="delete_id" id="delete_id_input">
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 grid grid-cols-2 gap-3 shrink-0 rounded-b-3xl">
                <button type="button" onclick="closeModal('deleteModal')" class="py-3 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 border border-transparent hover:border-slate-200 dark:hover:border-slate-600 transition-all">Batal</button>
                <button type="submit" name="delete_user" class="py-3 rounded-xl font-bold text-sm text-white bg-rose-600 hover:bg-rose-700 transition-all shadow-md shadow-rose-500/30 active:scale-95">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<script>
    // --- CUSTOM MODAL HANDLERS (Tailwind Vanilla JS) ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        
        // Remove hidden to make it display:flex
        modal.classList.remove('hidden');
        
        // Use timeout to allow CSS transition to work after display:flex is applied
        setTimeout(() => {
            box.classList.remove('scale-95', 'opacity-0');
            box.classList.add('scale-100', 'opacity-100');
            
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
        
        // Trigger CSS transition out
        box.classList.remove('scale-100', 'opacity-100');
        box.classList.add('scale-95', 'opacity-0');
        
        // Wait for transition to finish before hiding element completely
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
        
        // Adjust pen color based on dark mode class on HTML tag
        const isDarkMode = document.documentElement.classList.contains('dark');
        const penCol = isDarkMode ? '#e2e8f0' : '#1e293b'; // slate-200 for dark mode, slate-800 for light

        var pad = new SignaturePad(canvas, { 
            backgroundColor: 'rgba(255, 255, 255, 0)', 
            penColor: penCol 
        });
        
        pad.addEventListener("beginStroke", () => { 
            if(placeholder) placeholder.style.display = 'none'; 
        });
        return pad;
    }

    // This function must run AFTER the modal is visible so offsetWidth is not 0
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