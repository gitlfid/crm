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
include '../config/functions.php'; 

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
    return "<div class='p-4 mb-6 rounded-xl border flex items-center gap-3 text-sm font-bold shadow-sm animate-fade-in-up $c'><i class='ph-fill $icon text-xl'></i> $text</div>";
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
        if($cek->num_rows > 0) {
            $msg = setTailwindMsg('danger', 'Username atau Email sudah terdaftar!', 'ph-warning-circle');
        } else {
            $pass_raw = generateRandomPassword(10);
            $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (username, email, phone, password, role, division_id, job_title, leave_quota, signature, must_change_password) 
                    VALUES ('$username', '$email', '$phone', '$pass_hash', '$role', $division_id, '$job_title', $leave_quota, $signVal, 1)";
            
            if ($conn->query($sql)) {
                if (function_exists('sendEmailNotification')) {
                    $emailSubject = "Selamat Datang di Helpdesk System";
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
                sendEmailNotification(trim($uData['email']), "Reset Password Helpdesk", "Password baru Anda: $new_pass <br><br> Harap segera login dan ganti password ini.");
            }
            $msg = setTailwindMsg('warning', 'Password berhasil direset. Password baru telah dikirim ke email user.', 'ph-key');
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
$users = $conn->query("SELECT u.*, d.name as div_name FROM users u LEFT JOIN divisions d ON u.division_id = d.id ORDER BY u.id DESC");
$divisions = []; 
$dRes = $conn->query("SELECT * FROM divisions");
while($d = $dRes->fetch_assoc()) $divisions[] = $d;

// --- LOAD VIEWS ---
$page_title = "Manage Users";
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 animate-fade-in-up">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Manage Users</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Kelola daftar pengguna, atur hak akses peran (Role), dan kuota cuti staf.</p>
        </div>
        <button onclick="openModal('addUserModal')" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 active:scale-95 whitespace-nowrap">
            <i class="ph-bold ph-user-plus text-lg"></i> Tambah User
        </button>
    </div>

    <?= $msg ?>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="overflow-x-auto custom-scrollbar w-full pb-10">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                    <tr>
                        <th class="px-5 py-3.5">Full Name & Email</th>
                        <th class="px-5 py-3.5">Role & Division</th>
                        <th class="px-5 py-3.5">Job Title</th>
                        <th class="px-5 py-3.5 text-center">Leave Quota</th>
                        <th class="px-5 py-3.5 text-center">Status Pass</th>
                        <th class="px-5 py-3.5 text-center">Sign</th>
                        <th class="px-5 py-3.5 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px]">
                    <?php if($users && $users->num_rows > 0): ?>
                        <?php while($row = $users->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-5 py-3 align-middle">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-black text-sm shrink-0 border border-indigo-100 dark:border-indigo-500/20">
                                        <?= strtoupper(substr($row['username'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800 dark:text-slate-200 text-xs mb-0.5">
                                            <?= htmlspecialchars($row['username']) ?>
                                        </div>
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">
                                            <?= htmlspecialchars($row['email']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle">
                                <?php if($row['role'] == 'admin'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 mb-1">
                                        ADMIN
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 mb-1">
                                        STANDARD
                                    </span>
                                <?php endif; ?>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                                    <?= $row['div_name'] ?? '- NO DIVISION -' ?>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 font-bold border border-slate-200 dark:border-slate-600 text-[10px]">
                                    <?= htmlspecialchars($row['job_title']) ?>
                                </span>
                            </td>

                            <td class="px-5 py-3 align-middle text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 font-black text-xs border border-indigo-100 dark:border-indigo-500/20">
                                    <?= $row['leave_quota'] ?>
                                </span>
                            </td>

                            <td class="px-5 py-3 align-middle text-center">
                                <?php if($row['must_change_password'] == 1): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 text-[9px] font-bold uppercase tracking-widest">
                                        <i class="ph-fill ph-warning-circle text-[11px]"></i> Harus Ganti
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 text-[9px] font-bold uppercase tracking-widest">
                                        <i class="ph-fill ph-check-circle text-[11px]"></i> Aman
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-3 align-middle text-center">
                                <?php if($row['signature']): ?>
                                    <i class="ph-fill ph-check-circle text-emerald-500 text-xl" title="Signature Uploaded"></i>
                                <?php else: ?>
                                    <i class="ph-bold ph-minus text-slate-300 dark:text-slate-600 text-xl" title="No Signature"></i>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-3 align-middle text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    
                                    <?php $userJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                    <button onclick='openEditModal(<?= $userJson ?>)' class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-600 dark:bg-slate-700 dark:hover:bg-blue-500/20 dark:text-slate-300 dark:hover:text-blue-400 transition-all active:scale-95 flex items-center justify-center" title="Edit User">
                                        <i class="ph-bold ph-pencil-simple text-[13px]"></i>
                                    </button>

                                    <button onclick="openResetModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-amber-50 text-slate-600 hover:text-amber-600 dark:bg-slate-700 dark:hover:bg-amber-500/20 dark:text-slate-300 dark:hover:text-amber-400 transition-all active:scale-95 flex items-center justify-center" title="Reset Password">
                                        <i class="ph-bold ph-key text-[13px]"></i>
                                    </button>

                                    <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 dark:bg-slate-700 dark:hover:bg-rose-500/20 dark:text-slate-300 dark:hover:text-rose-400 transition-all active:scale-95 flex items-center justify-center" title="Hapus User">
                                        <i class="ph-bold ph-trash text-[13px]"></i>
                                    </button>

                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <i class="ph-fill ph-users text-4xl mb-3 opacity-50"></i>
                                    <p class="text-xs font-medium">Belum ada data pengguna yang terdaftar.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addUserModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-3xl max-h-[90vh] overflow-y-auto custom-scrollbar transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl flex flex-col">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-indigo-600 text-white rounded-t-3xl sticky top-0 z-10">
                <h3 class="text-sm font-bold flex items-center gap-2"><i class="ph-bold ph-user-plus text-lg"></i> Tambah User Baru</h3>
                <button type="button" onclick="closeModal('addUserModal')" class="text-white/70 hover:text-white transition-colors"><i class="ph-bold ph-x text-lg"></i></button>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="username" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all font-bold" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Email Aktif <span class="text-rose-500">*</span></label>
                    <input type="email" name="email" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nomor Telepon</label>
                    <input type="text" name="phone" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Hak Akses (Role) <span class="text-rose-500">*</span></label>
                    <select name="role" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all font-bold" required>
                        <option value="standard">Standard User</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Divisi Departemen <span class="text-rose-500">*</span></label>
                    <select name="division_id" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" required>
                        <option value="">-- Pilih Divisi --</option>
                        <?php foreach($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= $div['name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Jabatan / Job Title <span class="text-rose-500">*</span></label>
                    <select name="job_title" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" required>
                        <option value="Staff">Staff</option><option value="Manager">Manager</option><option value="General Manager">General Manager</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Jatah Cuti Tahunan (Hari) <span class="text-rose-500">*</span></label>
                    <input type="number" name="leave_quota" class="w-full px-4 py-2.5 bg-indigo-50 dark:bg-slate-900 border border-indigo-200 dark:border-slate-700 rounded-xl text-xs font-black text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500 outline-none transition-all" value="12" required>
                </div>
                
                <div class="md:col-span-2 mt-2 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2"><i class="ph-bold ph-pen-nib"></i> Digital Signature</label>
                    
                    <div class="relative h-40 w-full border-2 border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 rounded-2xl overflow-hidden group">
                        <canvas id="add-sig-canvas" class="absolute inset-0 w-full h-full z-10 cursor-crosshair"></canvas>
                        <div id="add-sig-placeholder" class="absolute inset-0 flex items-center justify-center text-slate-400 dark:text-slate-500 text-xs font-bold pointer-events-none transition-opacity group-hover:opacity-50">Tulis Tanda Tangan Di Sini</div>
                    </div>
                    <input type="hidden" name="signature_data" id="add-sig-data">
                    <button type="button" onclick="clearAddSign()" class="mt-2 text-[10px] font-bold text-rose-500 hover:text-rose-700 transition-colors"><i class="ph-bold ph-eraser"></i> Bersihkan Canvas</button>
                    
                    <div class="flex items-center gap-4 my-4">
                        <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ATAU UPLOAD FILE</span>
                        <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                    </div>
                    
                    <input type="file" name="signature_file" class="w-full block text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-300 dark:hover:file:bg-slate-700 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl" accept="image/png">
                    <p class="text-[10px] text-slate-400 mt-1 italic">Hanya mendukung format file .PNG transparan.</p>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2 bg-slate-50/50 dark:bg-slate-800/50 rounded-b-3xl">
                <button type="button" onclick="closeModal('addUserModal')" class="px-6 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-xs">Batal</button>
                <button type="submit" name="add_user" onclick="saveAddSign()" class="px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-500/30 flex items-center gap-2">
                    <i class="ph-bold ph-floppy-disk text-sm"></i> Simpan User
                </button>
            </div>
        </form>
    </div>
</div>

<div id="editUserModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-3xl max-h-[90vh] overflow-y-auto custom-scrollbar transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl flex flex-col">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-blue-600 text-white rounded-t-3xl sticky top-0 z-10">
                <h3 class="text-sm font-bold flex items-center gap-2"><i class="ph-bold ph-pencil-simple text-lg"></i> Edit Data User</h3>
                <button type="button" onclick="closeModal('editUserModal')" class="text-white/70 hover:text-white transition-colors"><i class="ph-bold ph-x text-lg"></i></button>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                <input type="hidden" name="edit_id" id="edit_id">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="username" id="edit_username" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 dark:text-white outline-none transition-all font-bold" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Email Aktif <span class="text-rose-500">*</span></label>
                    <input type="email" name="email" id="edit_email" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 dark:text-white outline-none transition-all" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nomor Telepon</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 dark:text-white outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Hak Akses (Role) <span class="text-rose-500">*</span></label>
                    <select name="role" id="edit_role" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 dark:text-white outline-none transition-all font-bold" required>
                        <option value="standard">Standard User</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Divisi Departemen <span class="text-rose-500">*</span></label>
                    <select name="division_id" id="edit_division" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 dark:text-white outline-none transition-all" required>
                        <option value="">-- Pilih Divisi --</option>
                        <?php foreach($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= $div['name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Jabatan / Job Title <span class="text-rose-500">*</span></label>
                    <select name="job_title" id="edit_job_title" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 dark:text-white outline-none transition-all" required>
                        <option value="Staff">Staff</option><option value="Manager">Manager</option><option value="General Manager">General Manager</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Jatah Cuti Tahunan (Hari) <span class="text-rose-500">*</span></label>
                    <input type="number" name="leave_quota" id="edit_quota" class="w-full px-4 py-2.5 bg-blue-50 dark:bg-slate-900 border border-blue-200 dark:border-slate-700 rounded-xl text-xs font-black text-blue-600 dark:text-blue-400 focus:ring-2 focus:ring-blue-500 outline-none transition-all" required>
                </div>
                
                <div class="md:col-span-2 mt-2 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2"><i class="ph-bold ph-pen-nib"></i> Update Signature</label>
                    
                    <div class="relative h-40 w-full border-2 border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 rounded-2xl overflow-hidden group">
                        <canvas id="edit-sig-canvas" class="absolute inset-0 w-full h-full z-10 cursor-crosshair"></canvas>
                        <div id="edit-sig-placeholder" class="absolute inset-0 flex items-center justify-center text-slate-400 dark:text-slate-500 text-xs font-bold pointer-events-none transition-opacity group-hover:opacity-50">Tulis Tanda Tangan Baru (Opsional)</div>
                    </div>
                    <input type="hidden" name="edit_signature_data" id="edit-sig-data">
                    <button type="button" onclick="clearEditSign()" class="mt-2 text-[10px] font-bold text-rose-500 hover:text-rose-700 transition-colors"><i class="ph-bold ph-eraser"></i> Bersihkan Canvas</button>
                    
                    <div class="flex items-center gap-4 my-4">
                        <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ATAU UPLOAD FILE</span>
                        <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                    </div>
                    
                    <input type="file" name="edit_signature_file" class="w-full block text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-300 dark:hover:file:bg-slate-700 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl" accept="image/png">
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2 bg-slate-50/50 dark:bg-slate-800/50 rounded-b-3xl">
                <button type="button" onclick="closeModal('editUserModal')" class="px-6 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-xs">Batal</button>
                <button type="submit" name="edit_user" onclick="saveEditSign()" class="px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-md shadow-blue-500/30 flex items-center gap-2">
                    <i class="ph-bold ph-check text-sm"></i> Update Data
                </button>
            </div>
        </form>
    </div>
</div>

<div id="resetModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl flex flex-col overflow-hidden text-center">
        <form method="POST">
            <div class="pt-8 pb-6 px-6">
                <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-500 dark:bg-amber-500/20 dark:text-amber-400 flex items-center justify-center mx-auto mb-4">
                    <i class="ph-fill ph-key text-3xl"></i>
                </div>
                <h3 class="text-lg font-extrabold text-slate-800 dark:text-white mb-2">Reset Password?</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Password baru akan di-generate secara otomatis dan dikirimkan ke email <strong id="resetName" class="text-slate-700 dark:text-slate-300"></strong>.</p>
                <input type="hidden" name="reset_id" id="reset_id_input">
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 grid grid-cols-2 gap-3">
                <button type="button" onclick="closeModal('resetModal')" class="py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-xs">Batal</button>
                <button type="submit" name="reset_password" class="py-2.5 rounded-xl text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 transition-colors shadow-md shadow-amber-500/30">Ya, Reset</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl flex flex-col overflow-hidden text-center">
        <form method="POST">
            <div class="pt-8 pb-6 px-6">
                <div class="w-16 h-16 rounded-full bg-rose-100 text-rose-500 dark:bg-rose-500/20 dark:text-rose-400 flex items-center justify-center mx-auto mb-4">
                    <i class="ph-fill ph-warning-circle text-3xl"></i>
                </div>
                <h3 class="text-lg font-extrabold text-slate-800 dark:text-white mb-2">Hapus Pengguna?</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Apakah Anda yakin ingin menghapus akses untuk <strong id="deleteName" class="text-slate-700 dark:text-slate-300"></strong> secara permanen?</p>
                <input type="hidden" name="delete_id" id="delete_id_input">
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 grid grid-cols-2 gap-3">
                <button type="button" onclick="closeModal('deleteModal')" class="py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-xs">Batal</button>
                <button type="submit" name="delete_user" class="py-2.5 rounded-xl text-xs font-bold text-white bg-rose-500 hover:bg-rose-600 transition-colors shadow-md shadow-rose-500/30">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    // --- CUSTOM MODAL HANDLERS ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
            
            // Adjust canvas inside modal specific
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
        }, 10);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.add('opacity-0');
        box.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Modal Triggers
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
        var pad = new SignaturePad(canvas, { backgroundColor: 'rgba(255, 255, 255, 0)', penColor: '#1e293b' });
        pad.addEventListener("beginStroke", () => { if(placeholder) placeholder.style.display = 'none'; });
        return pad;
    }

    function resizeCanvas(canvas) {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        // Use offsetWidth to accurately get container size
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }

    document.addEventListener("DOMContentLoaded", function() {
        addPad = initSignaturePad('add-sig-canvas', 'add-sig-placeholder');
        editPad = initSignaturePad('edit-sig-canvas', 'edit-sig-placeholder');
    });

    function clearAddSign() { addPad.clear(); document.getElementById('add-sig-placeholder').style.display = 'flex'; }
    function saveAddSign() { if(!addPad.isEmpty()) document.getElementById('add-sig-data').value = addPad.toDataURL('image/png'); }
    
    function clearEditSign() { editPad.clear(); document.getElementById('edit-sig-placeholder').style.display = 'flex'; }
    function saveEditSign() { if(!editPad.isEmpty()) document.getElementById('edit-sig-data').value = editPad.toDataURL('image/png'); }
</script>

<?php include 'includes/footer.php'; ?>