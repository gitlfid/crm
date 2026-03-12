<?php
// =========================================================================
// 1. CONFIGURATION & LOGIC
// =========================================================================
ob_start(); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Validasi Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role'])); 

// Ambil Data User Lengkap
$stmt = $conn->prepare("SELECT u.*, d.name as div_name FROM users u LEFT JOIN divisions d ON u.division_id = d.id WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$currUser = $stmt->get_result()->fetch_assoc();

$my_div_id = $currUser['division_id'];
$my_job    = $currUser['job_title']; 
$my_quota  = $currUser['leave_quota'];

// Tentukan Role Logika
$is_admin   = ($role == 'admin');
$is_hr      = ($is_admin || stripos($currUser['div_name'] ?? '', 'HR') !== false || stripos($currUser['div_name'] ?? '', 'Human') !== false);
$is_manager = ($my_job == 'Manager' || $my_job == 'General Manager');
$is_staff   = (!$is_manager && !$is_hr && !$is_admin);

// --- LOGIC EXPORT EXCEL ---
if (isset($_POST['export_excel']) && ($is_manager || $is_hr || $is_admin)) {
    if (ob_get_length()) ob_end_clean();
    $filename = "Laporan_Cuti_" . date('Ymd_His') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    $whereEx = "WHERE 1=1";
    if(!$is_hr && !$is_admin && $is_manager) {
        $whereEx .= " AND u.division_id = $my_div_id";
    }

    $sqlEx = "SELECT l.*, u.username, u.job_title, d.name as div_name 
              FROM leaves l 
              JOIN users u ON l.user_id = u.id 
              LEFT JOIN divisions d ON l.division_id = d.id 
              $whereEx
              ORDER BY l.created_at DESC";
    $resEx = $conn->query($sqlEx);
    
    echo "<table border='1'>";
    echo "<thead>
            <tr style='background-color:#eee;'>
                <th>No</th><th>Karyawan</th><th>Divisi</th><th>Jabatan</th><th>Tipe Cuti</th>
                <th>Mulai</th><th>Selesai</th><th>Durasi (Hari)</th><th>Alasan</th>
                <th>Status</th><th>Tgl Request</th><th>Mgr Note</th><th>HR Note</th>
            </tr>
          </thead><tbody>";
    
    $no = 1;
    while($row = $resEx->fetch_assoc()) {
        echo "<tr>
            <td>$no</td><td>{$row['username']}</td><td>{$row['div_name']}</td><td>{$row['job_title']}</td>
            <td>{$row['leave_type']}</td><td>'{$row['start_date']}</td><td>'{$row['end_date']}</td>
            <td>{$row['total_days']}</td><td>{$row['reason']}</td><td>".strtoupper($row['status'])."</td>
            <td>{$row['created_at']}</td><td>{$row['manager_note']}</td><td>{$row['hr_note']}</td>
        </tr>";
        $no++;
    }
    echo "</tbody></table>";
    exit; 
}

// View Default
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : (($is_manager || $is_hr || $is_admin) ? 'dashboard' : 'myleaves');

// Security Redirect Tabs
if (($active_tab == 'dashboard' || $active_tab == 'approval' || $active_tab == 'history') && !$is_manager && !$is_hr && !$is_admin) {
    $active_tab = 'myleaves';
}

// Helper Alert Message (Tailwind Style)
function renderAlert($type, $msg, $icon) {
    $colors = [
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
        'danger'  => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400',
        'info'    => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400'
    ];
    $c = $colors[$type] ?? $colors['info'];
    return "
    <div class='flex items-center justify-between p-4 mb-6 rounded-2xl border shadow-sm animate-fade-in-up $c'>
        <div class='flex items-center gap-3'>
            <i class='ph-fill $icon text-2xl'></i>
            <span class='text-sm font-bold'>$msg</span>
        </div>
        <button type='button' onclick='this.parentElement.remove()' class='opacity-50 hover:opacity-100 transition-opacity'>
            <i class='ph-bold ph-x text-lg'></i>
        </button>
    </div>";
}

$msg = "";

// =========================================
// 2. BACKEND ACTIONS
// =========================================

// A. UPDATE QUOTA (HR ONLY)
if (isset($_POST['update_quota']) && $is_hr) {
    $target_uid = intval($_POST['quota_user_id']);
    $new_name   = $conn->real_escape_string($_POST['username']); 
    $new_quota  = intval($_POST['new_quota']);
    $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : NULL;
    $valid_until= !empty($_POST['valid_until']) ? $_POST['valid_until'] : NULL;
    
    $stmt = $conn->prepare("UPDATE users SET username=?, leave_quota=?, quota_valid_from=?, quota_valid_until=? WHERE id=?");
    $stmt->bind_param("sissi", $new_name, $new_quota, $valid_from, $valid_until, $target_uid);
    
    if ($stmt->execute()) {
        $msg = renderAlert('success', 'Data kuota karyawan berhasil diperbarui.', 'ph-check-circle');
    }
}

// B. SUBMIT CUTI
if (isset($_POST['submit_leave'])) {
    $start = $_POST['start_date'];
    $end   = $_POST['end_date'];
    $type  = $_POST['leave_type'];
    $reason= $conn->real_escape_string($_POST['reason']);
    
    $d1 = new DateTime($start); $d2 = new DateTime($end);
    $days = $d1->diff($d2)->days + 1; 
    
    if ($type == 'Annual' && $days > $my_quota) {
        $msg = renderAlert('danger', "Gagal: Sisa kuota tidak mencukupi ($my_quota hari).", 'ph-warning-circle');
    } else {
        $init_status = ($is_manager) ? 'pending_hr' : 'pending_manager';
        
        $sql = "INSERT INTO leaves (user_id, division_id, leave_type, start_date, end_date, total_days, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssiss", $user_id, $my_div_id, $type, $start, $end, $days, $reason, $init_status);
        
        if ($stmt->execute()) {
            echo "<script>window.location='leave_list.php?tab=myleaves&success=1';</script>";
            exit;
        } else {
            $msg = renderAlert('danger', "Database Error: " . $conn->error, 'ph-warning');
        }
    }
}
if(isset($_GET['success']) && $_GET['success'] == 1){
    $msg = renderAlert('success', 'Pengajuan cuti berhasil dikirim!', 'ph-paper-plane-right');
}

// C. APPROVAL PROCESS
if (isset($_POST['process_approval'])) {
    $leave_id = intval($_POST['leave_id']);
    $action   = $_POST['action_type']; 
    $note     = $conn->real_escape_string($_POST['approval_note']);
    $now      = date('Y-m-d H:i:s');
    
    $lData = $conn->query("SELECT * FROM leaves WHERE id=$leave_id")->fetch_assoc();
    $requester_id = $lData['user_id'];
    $req_days     = $lData['total_days'];
    
    if ($action == 'approve_mgr') {
        $conn->query("UPDATE leaves SET status='pending_hr', manager_note='$note', manager_approved_at='$now' WHERE id=$leave_id");
        $msg = renderAlert('info', 'Disetujui Manager. Diteruskan ke HR.', 'ph-check-circle');
    } 
    elseif ($action == 'reject_mgr') {
        $conn->query("UPDATE leaves SET status='rejected', manager_note='$note', manager_approved_at='$now' WHERE id=$leave_id");
        $msg = renderAlert('warning', 'Permintaan ditolak oleh Manager.', 'ph-x-circle');
    }
    elseif ($action == 'approve_hr') {
        if ($lData['leave_type'] == 'Annual') {
            $conn->query("UPDATE users SET leave_quota = leave_quota - $req_days WHERE id = $requester_id");
        }
        $conn->query("UPDATE leaves SET status='approved', hr_note='$note', hr_approved_at='$now' WHERE id=$leave_id");
        $msg = renderAlert('success', 'Disetujui HR (Final). Kuota telah disesuaikan.', 'ph-check-circle');
    }
    elseif ($action == 'reject_hr') {
        $conn->query("UPDATE leaves SET status='rejected', hr_note='$note', hr_approved_at='$now' WHERE id=$leave_id");
        $msg = renderAlert('warning', 'Permintaan ditolak oleh HR.', 'ph-x-circle');
    }
}

// D. CANCEL LEAVE
if (isset($_POST['cancel_leave_request'])) {
    $leave_id = intval($_POST['cancel_id']);
    $reason   = $conn->real_escape_string($_POST['cancel_reason']);
    
    $lData = $conn->query("SELECT * FROM leaves WHERE id=$leave_id AND user_id=$user_id")->fetch_assoc();
    if ($lData) {
        if ($lData['status'] == 'approved' && $lData['leave_type'] == 'Annual') {
            $conn->query("UPDATE users SET leave_quota = leave_quota + {$lData['total_days']} WHERE id = $user_id");
        }
        $full_note = "CANCELLED by User: " . $reason;
        $conn->query("UPDATE leaves SET status='cancelled', edit_reason='$full_note' WHERE id=$leave_id");
        $msg = renderAlert('info', 'Pengajuan cuti berhasil dibatalkan.', 'ph-info');
        $currUser = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc(); 
        $my_quota = $currUser['leave_quota'];
    }
}

// E. REVISE LEAVE
if (isset($_POST['revise_leave_request'])) {
    $leave_id = intval($_POST['revise_id']);
    $new_start= $_POST['revise_start'];
    $new_end  = $_POST['revise_end'];
    $reason   = $conn->real_escape_string($_POST['revise_reason']);
    
    $lData = $conn->query("SELECT * FROM leaves WHERE id=$leave_id AND user_id=$user_id")->fetch_assoc();
    $rev_count = isset($lData['revision_count']) ? $lData['revision_count'] : 0;
    
    if ($rev_count >= 1) {
        $msg = renderAlert('danger', 'Gagal: Revisi hanya diperbolehkan 1 kali.', 'ph-warning-circle');
    } else {
        $d1 = new DateTime($new_start); $d2 = new DateTime($new_end); 
        $new_days = $d1->diff($d2)->days + 1;
        
        if ($lData['status'] == 'approved' && $lData['leave_type'] == 'Annual') {
            $conn->query("UPDATE users SET leave_quota = leave_quota + {$lData['total_days']} WHERE id = $user_id");
        }
        $sqlRev = "UPDATE leaves SET start_date='$new_start', end_date='$new_end', total_days=$new_days, status='pending_hr', edit_reason='$reason', revision_count = revision_count + 1 WHERE id=$leave_id";
        if ($conn->query($sqlRev)) {
            $msg = renderAlert('info', 'Revisi diajukan. Menunggu persetujuan ulang.', 'ph-calendar-edit');
        }
    }
}

// DELETE ACTION (Direct Link)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    $conn->query("DELETE FROM leaves WHERE id = $del_id AND (status = 'pending_manager' OR status = 'pending_hr' OR '$role' = 'admin')");
    echo "<script>window.location='leave_list.php';</script>";
    exit;
}

// --- HELPER UNTUK STYLING BADGES ---
function getBadgeData($status) {
    $st = strtolower($status);
    if($st == 'approved') return ['style' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20', 'icon' => 'ph-check-circle', 'label' => 'Approved'];
    if($st == 'rejected') return ['style' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200 dark:border-rose-500/20', 'icon' => 'ph-x-circle', 'label' => 'Rejected'];
    if($st == 'pending_manager') return ['style' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border-amber-200 dark:border-amber-500/20', 'icon' => 'ph-hourglass-high', 'label' => 'Wait Mgr'];
    if($st == 'pending_hr') return ['style' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 border-indigo-200 dark:border-indigo-500/20', 'icon' => 'ph-clock', 'label' => 'Wait HR'];
    if($st == 'cancelled') return ['style' => 'bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-300 border-slate-200 dark:border-slate-600', 'icon' => 'ph-prohibit', 'label' => 'Cancelled'];
    return ['style' => 'bg-slate-100 text-slate-600', 'icon' => 'ph-info', 'label' => strtoupper($st)];
}

$page_title = "Leave Management";
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
    
    .segmented-control { position: relative; display: inline-flex; background: #f1f5f9; padding: 4px; border-radius: 16px; }
    .dark .segmented-control { background: #1e293b; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-2">
        <div>
            <p class="text-indigo-500 dark:text-indigo-400 font-bold uppercase tracking-widest text-[10px] mb-1">Hi, <?= htmlspecialchars($currUser['username'] ?? 'User') ?></p>
            <h1 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight">Leave Management</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1.5 font-medium text-sm">
                <?= ($is_manager || $is_hr || $is_admin) ? 'Kelola persetujuan, pantau sisa kuota, dan riwayat cuti staf.' : 'Ajukan permohonan cuti dan pantau status persetujuan Anda.' ?>
            </p>
        </div>
        <div class="px-4 py-2.5 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm inline-flex items-center gap-2.5 shrink-0">
            <div class="w-8 h-8 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-500">
                <i class="ph-bold ph-calendar-star text-lg"></i>
            </div>
            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Sisa Kuota: <span class="text-indigo-600 dark:text-indigo-400"><?= $my_quota ?> Hari</span></span>
        </div>
    </div>

    <?= $msg ?>

    <div class="w-full flex pb-2 overflow-x-auto modern-scrollbar mb-2">
        <div class="segmented-control shadow-inner border border-slate-200 dark:border-slate-700/50 w-full md:w-auto flex">
            <?php 
                $tabBase = "relative z-10 flex flex-1 md:flex-none items-center justify-center gap-2 py-2.5 px-6 sm:px-8 rounded-xl font-semibold text-sm transition-all duration-300 min-w-max ";
                $tabActive = $tabBase . "bg-white dark:bg-indigo-600 shadow-md text-indigo-600 dark:text-white font-bold";
                $tabInactive = $tabBase . "text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200";
            ?>
            
            <?php if($is_manager || $is_hr || $is_admin): ?>
                <a href="?tab=dashboard" class="<?= ($active_tab == 'dashboard') ? $tabActive : $tabInactive ?>">
                    <i class="ph-bold ph-squares-four text-lg"></i> Dashboard Cuti
                </a>
                <a href="?tab=approval" class="<?= ($active_tab == 'approval') ? $tabActive : $tabInactive ?>">
                    <i class="ph-bold ph-check-circle text-lg"></i> Approval List
                    <?php 
                        $countSQL = "SELECT COUNT(*) FROM leaves WHERE status = " . (($is_hr || $is_admin) ? "'pending_hr'" : "'pending_manager' AND division_id = $my_div_id AND user_id != $user_id");
                        $cnt = $conn->query($countSQL)->fetch_row()[0];
                        if($cnt > 0) echo "<span class='ml-1.5 px-2 py-0.5 rounded-full bg-rose-500 text-white text-[10px] shadow-sm'>$cnt</span>";
                    ?>
                </a>
                <a href="?tab=history" class="<?= ($active_tab == 'history') ? $tabActive : $tabInactive ?>">
                    <i class="ph-bold ph-clock-counter-clockwise text-lg"></i> Riwayat & Laporan
                </a>
            <?php endif; ?>
            
            <a href="?tab=myleaves" class="<?= ($active_tab == 'myleaves') ? $tabActive : $tabInactive ?>">
                <i class="ph-bold ph-user-list text-lg"></i> Cuti Saya
            </a>
            <a href="?tab=create" class="<?= ($active_tab == 'create') ? $tabActive : $tabInactive ?>">
                <i class="ph-bold ph-plus-circle text-lg"></i> Pengajuan Baru
            </a>
        </div>
    </div>

    <?php if ($active_tab == 'dashboard' && ($is_manager || $is_hr || $is_admin)): ?>
    <div class="animate-fade-in space-y-6">
        <?php
            $whereDash = "WHERE 1=1";
            if(!$is_hr && !$is_admin && $is_manager) { $whereDash .= " AND u.division_id = $my_div_id"; }
            $totEmp = $conn->query("SELECT COUNT(*) FROM users u $whereDash AND role != 'admin'")->fetch_row()[0];
            $today = date('Y-m-d');
            $onLeave = $conn->query("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id=u.id $whereDash AND status='approved' AND '$today' BETWEEN start_date AND end_date")->fetch_row()[0];
            $pend = $conn->query("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id=u.id $whereDash AND (status='pending_manager' OR status='pending_hr')")->fetch_row()[0];
        ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-[2rem] shadow-xl shadow-indigo-500/20 p-8 text-white relative overflow-hidden group hover:shadow-indigo-500/40 transition-all duration-300 hover:-translate-y-1 border border-indigo-400/30">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="absolute right-6 bottom-6 opacity-20 transform group-hover:scale-110 group-hover:-rotate-12 transition-all duration-500"><i class="ph-fill ph-users text-[100px]"></i></div>
                <div class="relative z-10">
                    <span class="inline-block px-3 py-1.5 bg-white/20 backdrop-blur-md rounded-xl font-bold tracking-widest text-[10px] uppercase mb-4 shadow-sm border border-white/20">Total Karyawan</span>
                    <h2 class="text-6xl font-black tracking-tighter mb-2"><?= $totEmp ?></h2>
                    <p class="text-indigo-100 font-medium text-sm">Dalam lingkup pengawasan Anda</p>
                </div>
            </div>

            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-[2rem] shadow-xl shadow-emerald-500/20 p-8 text-white relative overflow-hidden group hover:shadow-emerald-500/40 transition-all duration-300 hover:-translate-y-1 border border-emerald-400/30">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="absolute right-6 bottom-6 opacity-20 transform group-hover:scale-110 group-hover:rotate-12 transition-all duration-500"><i class="ph-fill ph-calendar-check text-[100px]"></i></div>
                <div class="relative z-10">
                    <span class="inline-block px-3 py-1.5 bg-white/20 backdrop-blur-md rounded-xl font-bold tracking-widest text-[10px] uppercase mb-4 shadow-sm border border-white/20 flex items-center w-max gap-2">
                        <div class="w-2 h-2 rounded-full bg-white animate-pulse"></div> Sedang Cuti
                    </span>
                    <h2 class="text-6xl font-black tracking-tighter mb-2"><?= $onLeave ?></h2>
                    <p class="text-emerald-100 font-medium text-sm">Staf yang tidak hadir hari ini</p>
                </div>
            </div>

            <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-[2rem] shadow-xl shadow-amber-500/20 p-8 text-white relative overflow-hidden group hover:shadow-amber-500/40 transition-all duration-300 hover:-translate-y-1 border border-amber-400/30">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="absolute right-6 bottom-6 opacity-20 transform group-hover:scale-110 group-hover:-rotate-12 transition-all duration-500"><i class="ph-fill ph-hourglass-high text-[100px]"></i></div>
                <div class="relative z-10">
                    <span class="inline-block px-3 py-1.5 bg-white/20 backdrop-blur-md rounded-xl font-bold tracking-widest text-[10px] uppercase mb-4 shadow-sm border border-white/20">Menunggu Approval</span>
                    <h2 class="text-6xl font-black tracking-tighter mb-2"><?= $pend ?></h2>
                    <p class="text-amber-100 font-medium text-sm">Antrian persetujuan aktif</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col">
                <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/30">
                    <h3 class="font-black text-slate-800 dark:text-white text-lg flex items-center gap-2">
                        <i class="ph-bold ph-ticket text-indigo-500"></i> Monitoring Kuota Karyawan
                    </h3>
                    <form method="POST" target="_blank">
                        <button type="submit" name="export_excel" class="inline-flex items-center justify-center gap-2 bg-emerald-50 hover:bg-emerald-500 text-emerald-600 hover:text-white dark:bg-emerald-500/10 dark:hover:bg-emerald-500 dark:text-emerald-400 dark:hover:text-white font-bold py-2.5 px-4 rounded-xl border border-emerald-200 dark:border-emerald-500/20 transition-all active:scale-95 shadow-sm text-xs">
                            <i class="ph-bold ph-microsoft-excel-logo text-base"></i> Export Data
                        </button>
                    </form>
                </div>
                
                <div class="overflow-x-auto modern-scrollbar flex-1">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead class="bg-white dark:bg-[#24303F]">
                            <tr>
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[35%]">Karyawan</th>
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[25%]">Jabatan & Divisi</th>
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-[15%]">Sisa Kuota</th>
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-[15%]">Berlaku S/D</th>
                                <?php if($is_hr): ?><th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-[10%]">Edit</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                            <?php
                            $sqlList = "SELECT u.*, d.name as div_name FROM users u LEFT JOIN divisions d ON u.division_id=d.id $whereDash AND role != 'admin' ORDER BY u.username ASC";
                            $resList = $conn->query($sqlList);
                            if($resList && $resList->num_rows > 0):
                                while($r = $resList->fetch_assoc()):
                                    $validDate = !empty($r['quota_valid_until']) ? date('d M Y', strtotime($r['quota_valid_until'])) : '<span class="italic text-slate-400">Unlimited</span>';
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                <td class="px-6 py-4 align-middle">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 flex items-center justify-center font-black text-xs uppercase shadow-inner shrink-0">
                                            <?= substr($r['username'], 0, 1) ?>
                                        </div>
                                        <span class="font-bold text-slate-800 dark:text-slate-200 text-sm group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">
                                            <?= htmlspecialchars($r['username']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-middle text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    <?= htmlspecialchars($r['job_title']) ?> <span class="opacity-70 font-normal">in <?= htmlspecialchars($r['div_name'] ?? 'N/A') ?></span>
                                </td>
                                <td class="px-6 py-4 align-middle text-center">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200 font-black text-sm shadow-sm border border-slate-200 dark:border-slate-600 group-hover:bg-indigo-50 group-hover:text-indigo-600 group-hover:border-indigo-200 transition-colors">
                                        <?= $r['leave_quota'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 align-middle text-center text-xs font-medium text-slate-600 dark:text-slate-300"><?= $validDate ?></td>
                                <?php if($is_hr): ?>
                                <td class="px-6 py-4 align-middle text-center">
                                    <button class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-indigo-600 hover:text-white dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-indigo-600 dark:hover:text-white transition-colors flex items-center justify-center mx-auto shadow-sm active:scale-95" onclick="openQuotaModal(<?= $r['id'] ?>, '<?= addslashes($r['username']) ?>', <?= $r['leave_quota'] ?>, '<?= $r['quota_valid_from'] ?>', '<?= $r['quota_valid_until'] ?>')" title="Sesuaikan Kuota">
                                        <i class="ph-bold ph-pencil-simple"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 text-sm font-medium">Data karyawan tidak ditemukan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lg:col-span-1 bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col transition-colors">
                <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                    <h3 class="font-black text-slate-800 dark:text-white text-lg flex items-center gap-2">
                        <i class="ph-bold ph-chart-bar text-amber-500"></i> Trend Bulanan
                    </h3>
                </div>
                <div class="p-6 flex-1 flex items-center justify-center min-h-[300px]">
                    <canvas id="leaveChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'approval' && ($is_manager || $is_hr || $is_admin)): ?>
    <div class="animate-fade-in bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col min-h-[500px]">
        <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div>
                <h3 class="font-black text-slate-800 dark:text-white text-lg flex items-center gap-2">
                    <i class="ph-fill ph-check-square-offset text-indigo-500 text-xl"></i> Antrian Persetujuan
                </h3>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mt-1">Review Pengajuan Cuti Staf</p>
            </div>
            
            <div class="relative w-full sm:w-64">
                <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                <input type="text" id="searchApproval" onkeyup="liveSearch('appTableBody', this.value)" class="w-full pl-11 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-sm placeholder-slate-400" placeholder="Cari nama staf...">
            </div>
        </div>
        
        <div class="overflow-x-auto modern-scrollbar flex-grow pb-10">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-white dark:bg-[#24303F]">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[20%]">Pemohon</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[20%]">Tipe & Durasi</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[30%]">Alasan</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[15%] text-center">Status</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-[15%]">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50" id="appTableBody">
                    <?php
                    $whereApp = "WHERE status != 'approved' AND status != 'rejected' AND status != 'cancelled'";
                    if ($is_hr || $is_admin) { $whereApp .= " AND status = 'pending_hr'"; } 
                    elseif ($is_manager) { $whereApp .= " AND status = 'pending_manager' AND l.division_id = $my_div_id AND l.user_id != $user_id"; }

                    $sqlApp = "SELECT l.*, u.username, u.job_title FROM leaves l JOIN users u ON l.user_id=u.id $whereApp ORDER BY l.created_at ASC";
                    $resApp = $conn->query($sqlApp);

                    if($resApp && $resApp->num_rows > 0): while($row = $resApp->fetch_assoc()):
                        $badge = getBadgeData($row['status']);
                    ?>
                    <tr class="search-row hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                        <td class="px-6 py-5 align-middle">
                            <div class="font-bold text-slate-800 dark:text-white text-sm mb-0.5 search-name"><?= htmlspecialchars($row['username']) ?></div>
                            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['job_title']) ?></div>
                        </td>
                        <td class="px-6 py-5 align-middle">
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest bg-indigo-50 text-indigo-600 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 mb-2 shadow-sm">
                                <?= htmlspecialchars($row['leave_type']) ?> &bull; <?= $row['total_days'] ?> Hari
                            </div>
                            <div class="text-xs font-medium text-slate-600 dark:text-slate-300 flex items-center gap-1.5">
                                <i class="ph-bold ph-calendar-blank text-slate-400"></i>
                                <?= date('d M', strtotime($row['start_date'])) ?> <span class="text-slate-400 mx-0.5">-</span> <?= date('d M Y', strtotime($row['end_date'])) ?>
                            </div>
                        </td>
                        <td class="px-6 py-5 align-middle">
                            <div class="text-sm font-medium text-slate-600 dark:text-slate-300 max-w-[250px] whitespace-normal leading-snug line-clamp-2" title="<?= htmlspecialchars($row['reason']) ?>">
                                "<?= htmlspecialchars($row['reason']) ?>"
                            </div>
                        </td>
                        <td class="px-6 py-5 align-middle text-center">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border text-[9px] font-black uppercase tracking-widest shadow-sm <?= $badge['style'] ?>">
                                <i class="ph-fill <?= $badge['icon'] ?> text-xs"></i> <?= $badge['label'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-5 align-middle text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button class="w-10 h-10 rounded-xl bg-emerald-50 hover:bg-emerald-500 text-emerald-600 hover:text-white dark:bg-emerald-500/10 dark:hover:bg-emerald-500 dark:text-emerald-400 dark:hover:text-white transition-all shadow-sm active:scale-95 flex items-center justify-center" onclick="openModalApprove(<?= $row['id'] ?>, 'approve')" title="Setujui">
                                    <i class="ph-bold ph-check text-xl"></i>
                                </button>
                                <button class="w-10 h-10 rounded-xl bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white dark:bg-rose-500/10 dark:hover:bg-rose-500 dark:text-rose-400 dark:hover:text-white transition-all shadow-sm active:scale-95 flex items-center justify-center" onclick="openModalApprove(<?= $row['id'] ?>, 'reject')" title="Tolak">
                                    <i class="ph-bold ph-x text-xl"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                    <i class="ph-fill ph-check-circle text-4xl text-emerald-500/50"></i>
                                </div>
                                <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Semua Selesai!</h4>
                                <p class="text-sm font-medium">Tidak ada antrian persetujuan saat ini.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'history' && ($is_manager || $is_hr || $is_admin)): ?>
    <div class="animate-fade-in bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col min-h-[500px]">
        <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div>
                <h3 class="font-black text-slate-800 dark:text-white text-lg flex items-center gap-2">
                    <i class="ph-bold ph-clock-counter-clockwise text-indigo-500 text-xl"></i> Riwayat Seluruh Cuti
                </h3>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mt-1">Log historis persetujuan / penolakan</p>
            </div>
            
            <div class="relative w-full sm:w-64">
                <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                <input type="text" id="searchHistory" onkeyup="liveSearch('histTableBody', this.value)" class="w-full pl-11 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-sm placeholder-slate-400" placeholder="Cari nama staf...">
            </div>
        </div>
        
        <div class="overflow-x-auto modern-scrollbar flex-grow pb-10">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-white dark:bg-[#24303F]">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[20%]">Pemohon</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[20%]">Detail Cuti</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[20%]">Tanggal & Durasi</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[15%] text-center">Status</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[25%]">Catatan Evaluasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50 text-sm" id="histTableBody">
                    <?php
                    $whereHist = "WHERE status IN ('approved', 'rejected', 'cancelled')";
                    if ($is_manager && !$is_hr && !$is_admin) { $whereHist .= " AND l.division_id = $my_div_id"; }
                    
                    $sqlHist = "SELECT l.*, u.username, u.job_title, d.name as div_name FROM leaves l JOIN users u ON l.user_id = u.id LEFT JOIN divisions d ON l.division_id = d.id $whereHist ORDER BY l.created_at DESC LIMIT 100";
                    $resHist = $conn->query($sqlHist);

                    if($resHist && $resHist->num_rows > 0): while($row = $resHist->fetch_assoc()):
                        $badge = getBadgeData($row['status']);
                    ?>
                    <tr class="search-row hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                        <td class="px-6 py-5 align-middle">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-slate-200 to-slate-100 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 font-black text-sm uppercase shadow-inner border border-white dark:border-slate-600 shrink-0">
                                    <?= substr($row['username'],0,1) ?>
                                </div>
                                <div class="min-w-0 pr-2">
                                    <p class="font-bold text-slate-800 dark:text-white text-sm mb-0.5 truncate search-name"><?= htmlspecialchars($row['username']) ?></p>
                                    <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 truncate"><?= $row['job_title'] ?> <span class="opacity-60">&bull; <?= $row['div_name'] ?? 'N/A' ?></span></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5 align-middle">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest border mb-1.5 bg-indigo-50 text-indigo-600 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 shadow-sm">
                                <?= $row['leave_type'] ?>
                            </span>
                            <div class="text-xs font-medium text-slate-600 dark:text-slate-300 max-w-[200px] truncate" title="<?= htmlspecialchars($row['reason']) ?>">
                                "<?= htmlspecialchars($row['reason']) ?>"
                            </div>
                        </td>
                        <td class="px-6 py-5 align-middle">
                            <div class="text-sm font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5 mb-1">
                                <i class="ph-bold ph-calendar-blank text-slate-400"></i> <?= date('d M', strtotime($row['start_date'])) ?> <span class="text-slate-400 mx-0.5">-</span> <?= date('d M Y', strtotime($row['end_date'])) ?>
                            </div>
                            <span class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">Durasi: <?= $row['total_days'] ?> Hari</span>
                        </td>
                        <td class="px-6 py-5 align-middle text-center">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border text-[10px] font-black uppercase tracking-widest shadow-sm <?= $badge['style'] ?>">
                                <i class="ph-fill <?= $badge['icon'] ?> text-xs"></i> <?= $badge['label'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-5 align-middle text-xs text-slate-500 dark:text-slate-400 whitespace-normal leading-relaxed">
                            <?php 
                                if($row['hr_note']) echo "<strong class='text-slate-700 dark:text-slate-300'>HR:</strong> " . htmlspecialchars($row['hr_note']);
                                elseif($row['manager_note']) echo "<strong class='text-slate-700 dark:text-slate-300'>Mgr:</strong> " . htmlspecialchars($row['manager_note']);
                                elseif($row['edit_reason']) echo "<strong class='text-slate-700 dark:text-slate-300'>Info:</strong> " . htmlspecialchars($row['edit_reason']);
                                else echo "<span class='italic opacity-50'>Tidak ada catatan khusus</span>";
                            ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                    <i class="ph-fill ph-folder-open text-4xl text-slate-300 dark:text-slate-600"></i>
                                </div>
                                <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Riwayat Kosong</h4>
                                <p class="text-sm font-medium">Belum ada data riwayat persetujuan cuti yang selesai.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'myleaves'): ?>
    <div class="animate-fade-in bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col min-h-[500px]">
        <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-between items-center">
            <div>
                <h3 class="font-black text-slate-800 dark:text-white text-lg flex items-center gap-2">
                    <i class="ph-bold ph-user-list text-indigo-500 text-xl"></i> Riwayat Pengajuan Saya
                </h3>
            </div>
            </div>
        
        <div class="overflow-x-auto modern-scrollbar flex-grow pb-10">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-white dark:bg-[#24303F]">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[15%]">Tipe Cuti</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[20%]">Tanggal Pelaksanaan</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider w-[10%]">Durasi</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider w-[15%]">Status Pengajuan</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[30%]">Catatan Keputusan</th>
                        <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider w-[10%]">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50 text-sm">
                    <?php
                    $mySql = "SELECT * FROM leaves WHERE user_id = $user_id ORDER BY created_at DESC";
                    $myRes = $conn->query($mySql);
                    if($myRes && $myRes->num_rows > 0): while($row = $myRes->fetch_assoc()):
                        $rev_count = $row['revision_count'] ?? 0;
                        $badge = getBadgeData($row['status']);
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                        <td class="px-6 py-5 align-middle">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-widest border shadow-sm bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">
                                <?= htmlspecialchars($row['leave_type']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-5 align-middle">
                            <div class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1.5">
                                <?= date('d M Y', strtotime($row['start_date'])) ?> <br> <span class="text-slate-400 text-[10px] mr-1">s/d</span> <?= date('d M Y', strtotime($row['end_date'])) ?>
                            </div>
                            <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 flex items-center gap-1">
                                <i class="ph-fill ph-paper-plane-tilt text-indigo-400"></i> Diajukan: <?= date('d M', strtotime($row['created_at'])) ?>
                            </div>
                        </td>
                        <td class="px-6 py-5 align-middle text-center">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 font-black text-base border border-indigo-100 dark:border-indigo-500/20 shadow-sm">
                                <?= $row['total_days'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-5 align-middle text-center">
                            <span class="inline-flex items-center justify-center w-full max-w-[100px] gap-1 px-2.5 py-1.5 rounded-lg border text-[9px] font-black uppercase tracking-widest shadow-sm <?= $badge['style'] ?>">
                                <i class="ph-fill <?= $badge['icon'] ?> text-xs"></i> <?= $badge['label'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-5 align-middle text-xs text-slate-500 dark:text-slate-400 whitespace-normal leading-relaxed">
                            <?php if($row['manager_note']) echo "<div class='mb-1'><strong class='text-slate-700 dark:text-slate-300'>Mgr:</strong> " . htmlspecialchars($row['manager_note']) . "</div>"; ?>
                            <?php if($row['hr_note']) echo "<div class='mb-1'><strong class='text-slate-700 dark:text-slate-300'>HR:</strong> " . htmlspecialchars($row['hr_note']) . "</div>"; ?>
                            <?php if($row['edit_reason']) echo "<div class='text-amber-500 dark:text-amber-400'><strong class='text-slate-700 dark:text-slate-300'>Info:</strong> " . htmlspecialchars($row['edit_reason']) . "</div>"; ?>
                            <?php if(!$row['manager_note'] && !$row['hr_note'] && !$row['edit_reason']) echo "<span class='italic opacity-50 text-[10px]'>Menunggu tindakan</span>"; ?>
                        </td>
                        <td class="px-6 py-5 align-middle text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if($row['status'] == 'pending_manager' || $row['status'] == 'pending_hr'): ?>
                                    <button class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-600 dark:hover:text-white transition-all shadow-sm active:scale-95 flex items-center justify-center" onclick="openModalCancel(<?= $row['id'] ?>)" title="Batalkan">
                                        <i class="ph-bold ph-x-circle text-lg"></i>
                                    </button>
                                <?php elseif($row['status'] == 'approved' && $rev_count < 1 && strtotime($row['start_date']) >= time()): ?>
                                    <button class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white dark:bg-amber-500/10 dark:text-amber-400 dark:hover:bg-amber-500 dark:hover:text-white transition-all shadow-sm active:scale-95 flex items-center justify-center" onclick="openModalRevise(<?= $row['id'] ?>, '<?= $row['start_date'] ?>', '<?= $row['end_date'] ?>')" title="Revisi Tanggal">
                                        <i class="ph-bold ph-calendar-edit text-lg"></i>
                                    </button>
                                    <button class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-600 dark:hover:text-white transition-all shadow-sm active:scale-95 flex items-center justify-center" onclick="openModalCancel(<?= $row['id'] ?>)" title="Batalkan (Kembalikan Kuota)">
                                        <i class="ph-bold ph-x-circle text-lg"></i>
                                    </button>
                                <?php elseif($row['status'] == 'approved' && strtotime($row['start_date']) >= time()): ?>
                                    <button class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-600 dark:hover:text-white transition-all shadow-sm active:scale-95 flex items-center justify-center" onclick="openModalCancel(<?= $row['id'] ?>)" title="Batalkan (Kembalikan Kuota)">
                                        <i class="ph-bold ph-x-circle text-lg"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-300 dark:text-slate-600 font-black text-xl leading-none">-</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                    <i class="ph-fill ph-file-dashed text-4xl text-slate-300 dark:text-slate-600"></i>
                                </div>
                                <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Belum Ada Pengajuan</h4>
                                <p class="text-sm font-medium">Anda belum pernah mengajukan cuti.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'create'): ?>
    <div class="animate-fade-in flex justify-center pb-10">
        <div class="w-full max-w-3xl bg-white dark:bg-[#24303F] rounded-[2rem] shadow-xl shadow-indigo-500/10 border border-slate-100 dark:border-slate-800 overflow-hidden transform transition-all relative">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="px-8 py-8 border-b border-slate-100 dark:border-slate-700/50 relative z-10">
                <h2 class="text-2xl font-black text-slate-800 dark:text-white flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 flex items-center justify-center shadow-inner">
                        <i class="ph-fill ph-paper-plane-tilt text-2xl"></i>
                    </div>
                    Formulir Pengajuan Cuti
                </h2>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-2 ml-16 font-medium">Lengkapi detail tanggal dan alasan pengajuan cuti Anda dengan cermat.</p>
            </div>
            
            <div class="p-8 relative z-10">
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Jenis Cuti</label>
                            <div class="relative group">
                                <i class="ph-bold ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                                <select name="leave_type" class="w-full pl-12 pr-10 py-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-sm text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 outline-none appearance-none transition-all shadow-inner cursor-pointer">
                                    <option value="Annual">Annual Leave (Cuti Tahunan)</option>
                                    <option value="Sick">Sick Leave (Cuti Sakit)</option>
                                    <option value="Unpaid">Unpaid Leave (Di Luar Tanggungan)</option>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Sisa Kuota Tahunan</label>
                            <div class="relative">
                                <i class="ph-bold ph-ticket absolute left-4 top-1/2 -translate-y-1/2 text-indigo-500 text-lg"></i>
                                <input type="text" class="w-full pl-12 pr-4 py-3.5 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 text-indigo-700 dark:text-indigo-400 font-black text-base rounded-xl outline-none cursor-not-allowed shadow-sm" value="<?= $my_quota ?> Hari" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Tanggal Mulai <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <i class="ph-bold ph-calendar-blank absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                                <input type="date" name="start_date" id="start_date" required onchange="calculateDays()" class="w-full pl-12 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-sm text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 outline-none transition-all shadow-inner">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Tanggal Selesai <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <i class="ph-bold ph-calendar-check absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                                <input type="date" name="end_date" id="end_date" required onchange="calculateDays()" class="w-full pl-12 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-sm text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 outline-none transition-all shadow-inner">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between bg-indigo-600 dark:bg-indigo-500 rounded-2xl p-5 shadow-lg shadow-indigo-500/30 text-white">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-md border border-white/20 shadow-inner">
                                <i class="ph-bold ph-calculator text-xl"></i>
                            </div>
                            <span class="font-bold text-sm tracking-wide">Estimasi Pemotongan Kuota</span>
                        </div>
                        <span class="font-black text-3xl tracking-tight" id="displayDays">0 <span class="text-sm font-bold uppercase tracking-widest opacity-80">Hari</span></span>
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Alasan / Keterangan Lengkap <span class="text-rose-500">*</span></label>
                        <textarea name="reason" rows="4" required class="w-full px-5 py-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-medium text-sm text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 outline-none transition-all shadow-inner resize-none placeholder-slate-400" placeholder="Jelaskan alasan pengajuan cuti secara singkat dan jelas..."></textarea>
                    </div>

                    <div class="pt-6 mt-4">
                        <button type="submit" name="submit_leave" class="w-full group bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-black py-4 px-6 rounded-2xl transition-all shadow-xl shadow-slate-900/20 dark:shadow-indigo-600/30 flex items-center justify-center gap-3 active:scale-95 text-base tracking-widest uppercase">
                            Kirim Pengajuan Sekarang <i class="ph-bold ph-arrow-right text-xl group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<div id="modalApprove" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('modalApprove')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        <form method="POST">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                <h3 class="text-base font-black text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="ph-fill ph-shield-check text-indigo-500 text-xl"></i> Konfirmasi Persetujuan
                </h3>
                <button type="button" onclick="closeModal('modalApprove')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-200/50 hover:bg-rose-100 text-slate-500 hover:text-rose-600 dark:bg-slate-700 dark:text-slate-400 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            <div class="p-6">
                <input type="hidden" name="leave_id" id="app_leave_id">
                <input type="hidden" name="action_type" id="app_action_type">
                
                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Catatan (Opsional)</label>
                <textarea name="approval_note" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none dark:text-white focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 resize-none shadow-inner mb-4 text-sm transition-all" rows="3" placeholder="Ketik catatan tambahan untuk pengajuan ini..."></textarea>
                
                <div id="app_alert" class="hidden p-4 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400 text-xs font-bold flex items-start gap-3 shadow-sm">
                    <i class="ph-fill ph-info text-2xl shrink-0 mt-0.5"></i>
                    <p class="leading-relaxed">Tindakan "Approve" ini akan meneruskan status cuti ke HR untuk tahap finalisasi dan pemotongan kuota sistem.</p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3 shrink-0">
                <button type="button" onclick="closeModal('modalApprove')" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="process_approval" class="px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 transition-all active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-check-circle text-lg"></i> Proses Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modalCancel" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('modalCancel')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden text-center">
        <form method="POST">
            <div class="pt-8 pb-6 px-6">
                <div class="w-20 h-20 rounded-full bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 flex items-center justify-center mx-auto mb-5 shadow-inner">
                    <i class="ph-fill ph-warning-circle text-4xl text-rose-500 dark:text-rose-400"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white mb-2">Batalkan Pengajuan?</h3>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-6 leading-relaxed">Pengajuan cuti yang dibatalkan tidak bisa diaktifkan kembali. Kuota akan dikembalikan otomatis (jika sebelumnya sudah disetujui).</p>
                
                <input type="hidden" name="cancel_id" id="cancel_leave_id">
                <div class="text-left">
                    <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Alasan Pembatalan <span class="text-rose-500">*</span></label>
                    <textarea name="cancel_reason" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none dark:text-white focus:ring-2 focus:ring-rose-500/50 focus:border-rose-500 resize-none shadow-inner text-sm transition-all" rows="2" placeholder="Wajib diisi..." required></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 grid grid-cols-2 gap-3 shrink-0">
                <button type="button" onclick="closeModal('modalCancel')" class="py-3 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 bg-white hover:bg-slate-100 dark:bg-slate-700 dark:hover:bg-slate-600 border border-slate-200 dark:border-slate-600 transition-all shadow-sm">Tutup</button>
                <button type="submit" name="cancel_leave_request" class="py-3 rounded-xl font-bold text-sm text-white bg-rose-600 hover:bg-rose-700 shadow-lg shadow-rose-500/30 transition-all active:scale-95">Ya, Batalkan</button>
            </div>
        </form>
    </div>
</div>

<div id="modalRevise" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('modalRevise')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        <form method="POST">
            <div class="px-6 py-5 border-b border-amber-500/20 bg-amber-500 text-white flex justify-between items-center">
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-calendar-edit text-xl"></i> Revisi Tanggal Cuti</h3>
                <button type="button" onclick="closeModal('modalRevise')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            <div class="p-6">
                <input type="hidden" name="revise_id" id="revise_leave_id">
                
                <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-400 text-xs font-bold flex items-start gap-3 shadow-sm">
                    <i class="ph-fill ph-warning-circle text-2xl shrink-0 mt-0.5"></i> 
                    <p class="leading-relaxed">Revisi tanggal hanya dapat dilakukan maksimal 1 kali. Status akan kembali menjadi "Menunggu Approval HR".</p>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Mulai Baru <span class="text-amber-500">*</span></label>
                        <div class="relative">
                            <i class="ph-bold ph-calendar-blank absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                            <input type="date" name="revise_start" id="rev_start" class="w-full pl-9 pr-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none text-xs font-bold focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 shadow-inner dark:text-white transition-all" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Selesai Baru <span class="text-amber-500">*</span></label>
                        <div class="relative">
                            <i class="ph-bold ph-calendar-check absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                            <input type="date" name="revise_end" id="rev_end" class="w-full pl-9 pr-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none text-xs font-bold focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 shadow-inner dark:text-white transition-all" required>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Alasan Revisi <span class="text-amber-500">*</span></label>
                    <textarea name="revise_reason" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none dark:text-white focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 resize-none shadow-inner text-sm transition-all" rows="3" placeholder="Mengapa Anda mengubah tanggal..." required></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3 shrink-0">
                <button type="button" onclick="closeModal('modalRevise')" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="revise_leave_request" class="px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-amber-500 hover:bg-amber-600 shadow-lg shadow-amber-500/30 transition-all active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-paper-plane-right text-lg"></i> Ajukan Revisi
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modalQuota" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('modalQuota')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        <form method="POST">
            <div class="px-6 py-5 border-b border-indigo-500/20 bg-indigo-600 text-white flex justify-between items-center">
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-sliders text-xl"></i> Adjust Kuota Cuti</h3>
                <button type="button" onclick="closeModal('modalQuota')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            <div class="p-6 space-y-5">
                <input type="hidden" name="quota_user_id" id="q_uid">
                
                <div>
                    <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Nama Karyawan</label>
                    <input type="text" name="username" id="q_uname" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/50 shadow-inner transition-all" required>
                </div>
                <div>
                    <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Jumlah Kuota Baru (Hari)</label>
                    <input type="number" name="new_quota" id="q_val" class="w-full px-4 py-3 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 rounded-xl text-lg font-black text-indigo-600 dark:text-indigo-400 outline-none focus:ring-2 focus:ring-indigo-500/50 shadow-inner transition-all text-center" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Berlaku Mulai</label>
                        <input type="date" name="valid_from" id="q_from" class="w-full px-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/50 shadow-inner transition-all">
                    </div>
                    <div>
                        <label class="block text-[11px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Berlaku S/D</label>
                        <input type="date" name="valid_until" id="q_until" class="w-full px-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/50 shadow-inner transition-all">
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                <button type="submit" name="update_quota" class="w-full py-3.5 rounded-xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 transition-all active:scale-95 flex items-center justify-center gap-2 uppercase tracking-widest">
                    <i class="ph-bold ph-check text-lg"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --- LIVE SEARCH LOGIC ---
    function liveSearch(tbodyId, inputVal) {
        let input = inputVal.toLowerCase();
        let rows = document.querySelectorAll(`#${tbodyId} .search-row`);

        rows.forEach(row => {
            let name = row.querySelector('.search-name').innerText.toLowerCase();
            let text = row.innerText.toLowerCase(); // Full text fallback
            if (name.includes(input) || text.includes(input)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    // --- ESTIMASI HARI CUTI ---
    function calculateDays() {
        let start = document.getElementById('start_date').value;
        let end = document.getElementById('end_date').value;
        let display = document.getElementById('displayDays');

        if (start && end) {
            let date1 = new Date(start);
            let date2 = new Date(end);
            
            if (date2 >= date1) {
                let diffTime = Math.abs(date2 - date1);
                let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; 
                display.innerHTML = diffDays + ' <span class="text-sm font-bold uppercase tracking-widest opacity-80">Hari</span>';
            } else {
                display.innerHTML = '<span class="text-rose-300 text-lg">Invalid</span>';
            }
        }
    }

    // --- CUSTOM MODAL LOGIC (Tailwind Vanilla JS) ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.remove('hidden');
        
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
            box.classList.add('scale-100', 'opacity-100');
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

    // --- Modal Triggers dengan Data Binding ---
    function openModalApprove(id, type) {
        document.getElementById('app_leave_id').value = id;
        let isHr = <?= $is_hr ? 'true' : 'false' ?>;
        let actType = (type === 'approve') ? (isHr ? 'approve_hr' : 'approve_mgr') : (isHr ? 'reject_hr' : 'reject_mgr');
        
        document.getElementById('app_action_type').value = actType;
        
        const alertBox = document.getElementById('app_alert');
        if (actType === 'approve_mgr') { alertBox.classList.remove('hidden'); } 
        else { alertBox.classList.add('hidden'); }
        
        openModal('modalApprove');
    }

    function openModalCancel(id) {
        document.getElementById('cancel_leave_id').value = id;
        openModal('modalCancel');
    }

    function openModalRevise(id, start, end) {
        document.getElementById('revise_leave_id').value = id;
        document.getElementById('rev_start').value = start;
        document.getElementById('rev_end').value = end;
        openModal('modalRevise');
    }

    function openQuotaModal(id, name, quota, from, until) {
        document.getElementById('q_uid').value = id;
        document.getElementById('q_uname').value = name; 
        document.getElementById('q_val').value = quota;
        document.getElementById('q_from').value = from;
        document.getElementById('q_until').value = until;
        openModal('modalQuota');
    }

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

    // --- CHART.JS CONFIGURATION ---
    <?php if ($active_tab == 'dashboard' && ($is_manager || $is_hr || $is_admin)): ?>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('leaveChart');
        if(!ctx) return;

        const isDark = document.documentElement.classList.contains('dark') || document.body.classList.contains('theme-dark');
        const textColor = isDark ? '#94a3b8' : '#64748b'; 
        const gridColor = isDark ? '#334155' : '#e2e8f0'; 

        <?php
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $dataCounts = [];
            foreach(range(1,12) as $m) {
                $sqlC = "SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id=u.id $whereDash AND MONTH(start_date) = $m AND YEAR(start_date) = YEAR(CURRENT_DATE)";
                $countObj = $conn->query($sqlC);
                $dataCounts[] = $countObj ? $countObj->fetch_row()[0] : 0;
            }
        ?>
        
        let leaveChart = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Total Cuti Terjadwal',
                    data: <?= json_encode($dataCounts) ?>,
                    backgroundColor: '#4F46E5', // Tailwind Indigo-600
                    hoverBackgroundColor: '#6366F1',
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        ticks: { stepSize: 1, color: textColor, font: {family: "'Inter', sans-serif", weight: 600} },
                        grid: { color: gridColor, borderDash: [4, 4] },
                        border: { display: false }
                    },
                    x: {
                        ticks: { color: textColor, font: {family: "'Inter', sans-serif", weight: 600} },
                        grid: { display: false },
                        border: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#ffffff',
                        titleColor: isDark ? '#ffffff' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        titleFont: {family: "'Inter', sans-serif", size: 13},
                        bodyFont: {family: "'Inter', sans-serif", size: 14, weight: 'bold'},
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' Pengajuan';
                            }
                        }
                    }
                }
            }
        });

        // Observer for Dark Mode changes to update Chart Colors
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class' || mutation.attributeName === 'data-bs-theme') {
                    const isDarkNow = document.documentElement.classList.contains('dark') || document.body.classList.contains('theme-dark');
                    const newText = isDarkNow ? '#94a3b8' : '#64748b';
                    const newGrid = isDarkNow ? '#334155' : '#e2e8f0';
                    
                    leaveChart.options.scales.x.ticks.color = newText;
                    leaveChart.options.scales.y.ticks.color = newText;
                    leaveChart.options.scales.y.grid.color = newGrid;
                    
                    leaveChart.options.plugins.tooltip.backgroundColor = isDarkNow ? '#1e293b' : '#ffffff';
                    leaveChart.options.plugins.tooltip.titleColor = isDarkNow ? '#ffffff' : '#0f172a';
                    leaveChart.options.plugins.tooltip.bodyColor = isDarkNow ? '#cbd5e1' : '#475569';
                    leaveChart.options.plugins.tooltip.borderColor = isDarkNow ? '#334155' : '#e2e8f0';
                    
                    leaveChart.update();
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });
        observer.observe(document.body, { attributes: true });
    });
    <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>