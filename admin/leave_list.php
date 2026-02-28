<?php
// =========================================
// 1. CONFIGURATION & LOGIC
// =========================================
ob_start(); // Penting untuk Export Excel
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Validasi Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; 

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
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'myleaves';

// Security Redirect Tabs
if (($active_tab == 'dashboard' || $active_tab == 'approval' || $active_tab == 'history') && !$is_manager && !$is_hr && !$is_admin) {
    $active_tab = 'myleaves';
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
        $msg = "<div class='mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex justify-between items-center shadow-sm'><span><i class='ph-fill ph-check-circle mr-2'></i> Data karyawan berhasil diperbarui.</span><button onclick='this.parentElement.remove()' class='text-emerald-500 hover:text-emerald-800'><i class='ph-bold ph-x'></i></button></div>";
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
        $msg = "<div class='mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 flex justify-between items-center shadow-sm'><span><i class='ph-fill ph-warning-circle mr-2'></i> Gagal: Sisa kuota tidak mencukupi ($my_quota hari).</span><button onclick='this.parentElement.remove()' class='text-rose-500 hover:text-rose-800'><i class='ph-bold ph-x'></i></button></div>";
    } else {
        $init_status = ($is_manager) ? 'pending_hr' : 'pending_manager';
        
        $sql = "INSERT INTO leaves (user_id, division_id, leave_type, start_date, end_date, total_days, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssiss", $user_id, $my_div_id, $type, $start, $end, $days, $reason, $init_status);
        
        if ($stmt->execute()) {
            echo "<script>window.location='leave_list.php?tab=myleaves&success=1';</script>";
            exit;
        } else {
            $msg = "<div class='mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700'>Database Error: ".$conn->error."</div>";
        }
    }
}
if(isset($_GET['success']) && $_GET['success'] == 1){
    $msg = "<div class='mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex justify-between items-center shadow-sm'><span><i class='ph-fill ph-check-circle mr-2'></i> Pengajuan cuti berhasil dikirim!</span><button onclick='this.parentElement.remove()' class='text-emerald-500 hover:text-emerald-800'><i class='ph-bold ph-x'></i></button></div>";
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
        $msg = "<div class='mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex justify-between items-center'><span><i class='ph-fill ph-check-circle mr-2'></i> Disetujui Manager. Menunggu HR.</span><button onclick='this.parentElement.remove()'><i class='ph-bold ph-x'></i></button></div>";
    } 
    elseif ($action == 'reject_mgr') {
        $conn->query("UPDATE leaves SET status='rejected', manager_note='$note', manager_approved_at='$now' WHERE id=$leave_id");
        $msg = "<div class='mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex justify-between items-center'><span><i class='ph-fill ph-warning-circle mr-2'></i> Permintaan ditolak (Manager).</span><button onclick='this.parentElement.remove()'><i class='ph-bold ph-x'></i></button></div>";
    }
    elseif ($action == 'approve_hr') {
        if ($lData['leave_type'] == 'Annual') {
            $conn->query("UPDATE users SET leave_quota = leave_quota - $req_days WHERE id = $requester_id");
        }
        $conn->query("UPDATE leaves SET status='approved', hr_note='$note', hr_approved_at='$now' WHERE id=$leave_id");
        $msg = "<div class='mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex justify-between items-center'><span><i class='ph-fill ph-check-circle mr-2'></i> Disetujui HR (Final). Kuota terpotong.</span><button onclick='this.parentElement.remove()'><i class='ph-bold ph-x'></i></button></div>";
    }
    elseif ($action == 'reject_hr') {
        $conn->query("UPDATE leaves SET status='rejected', hr_note='$note', hr_approved_at='$now' WHERE id=$leave_id");
        $msg = "<div class='mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex justify-between items-center'><span><i class='ph-fill ph-warning-circle mr-2'></i> Permintaan ditolak (HR).</span><button onclick='this.parentElement.remove()'><i class='ph-bold ph-x'></i></button></div>";
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
        $msg = "<div class='mb-4 p-4 rounded-xl bg-slate-100 border border-slate-300 text-slate-700 flex justify-between items-center'><span><i class='ph-fill ph-info mr-2'></i> Cuti berhasil dibatalkan.</span><button onclick='this.parentElement.remove()'><i class='ph-bold ph-x'></i></button></div>";
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
        $msg = "<div class='mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 flex justify-between items-center'><span><i class='ph-fill ph-warning-circle mr-2'></i> Gagal: Revisi hanya diperbolehkan 1 kali.</span><button onclick='this.parentElement.remove()'><i class='ph-bold ph-x'></i></button></div>";
    } else {
        $d1 = new DateTime($new_start); $d2 = new DateTime($new_end); 
        $new_days = $d1->diff($d2)->days + 1;
        
        if ($lData['status'] == 'approved' && $lData['leave_type'] == 'Annual') {
            $conn->query("UPDATE users SET leave_quota = leave_quota + {$lData['total_days']} WHERE id = $user_id");
        }
        $sqlRev = "UPDATE leaves SET start_date='$new_start', end_date='$new_end', total_days=$new_days, status='pending_hr', edit_reason='$reason', revision_count = revision_count + 1 WHERE id=$leave_id";
        if ($conn->query($sqlRev)) {
            $msg = "<div class='mb-4 p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-700 flex justify-between items-center'><span><i class='ph-fill ph-info mr-2'></i> Revisi berhasil diajukan. Menunggu persetujuan HR.</span><button onclick='this.parentElement.remove()'><i class='ph-bold ph-x'></i></button></div>";
        }
    }
}

// Function to Style Badges Tailwind
function getBadgeStyle($status) {
    switch (strtolower($status)) {
        case 'approved': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400';
        case 'rejected': return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400';
        case 'pending_manager': return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400';
        case 'pending_hr': return 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400';
        case 'cancelled': return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
        default: return 'bg-slate-100 text-slate-600';
    }
}
function formatStatusLabel($status) {
    if($status == 'pending_manager') return 'Wait Mgr';
    if($status == 'pending_hr') return 'Wait HR';
    return strtoupper($status);
}
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto overflow-y-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="mb-8 animate-slide-up">
        <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Manajemen Cuti</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Portal pengajuan, persetujuan, dan pemantauan cuti karyawan.</p>
    </div>

    <?= $msg ?>

    <div class="mb-6 overflow-x-auto no-scrollbar">
        <nav class="flex space-x-2 border-b border-slate-200 dark:border-slate-700 min-w-max pb-1" aria-label="Tabs">
            <?php 
                $tabClass = "flex items-center gap-2 px-4 py-3 text-sm font-semibold rounded-t-xl transition-colors border-b-2 ";
                $activeTabClass = $tabClass . "border-indigo-600 text-indigo-600 bg-indigo-50/50 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-400";
                $inactiveTabClass = $tabClass . "border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800";
            ?>
            
            <?php if($is_manager || $is_hr || $is_admin): ?>
                <a href="?tab=dashboard" class="<?= ($active_tab == 'dashboard') ? $activeTabClass : $inactiveTabClass ?>">
                    <i class="ph-bold ph-squares-four text-lg"></i> Dashboard
                </a>
                <a href="?tab=approval" class="<?= ($active_tab == 'approval') ? $activeTabClass : $inactiveTabClass ?>">
                    <i class="ph-bold ph-check-circle text-lg"></i> Persetujuan
                    <?php 
                        $countSQL = "SELECT COUNT(*) FROM leaves WHERE status = " . (($is_hr) ? "'pending_hr'" : "'pending_manager' AND division_id = $my_div_id");
                        $cnt = $conn->query($countSQL)->fetch_row()[0];
                        if($cnt > 0) echo "<span class='ml-1 px-2 py-0.5 rounded-full bg-rose-500 text-white text-[10px]'>$cnt</span>";
                    ?>
                </a>
                <a href="?tab=history" class="<?= ($active_tab == 'history') ? $activeTabClass : $inactiveTabClass ?>">
                    <i class="ph-bold ph-clock-counter-clockwise text-lg"></i> Riwayat Semua
                </a>
            <?php endif; ?>
            
            <a href="?tab=myleaves" class="<?= ($active_tab == 'myleaves') ? $activeTabClass : $inactiveTabClass ?>">
                <i class="ph-bold ph-user-list text-lg"></i> Riwayat Saya
            </a>
            <a href="?tab=create" class="<?= ($active_tab == 'create') ? $activeTabClass : $inactiveTabClass ?>">
                <i class="ph-bold ph-plus-circle text-lg"></i> Ajukan Cuti
            </a>
        </nav>
    </div>

    <?php if ($active_tab == 'dashboard' && ($is_manager || $is_hr || $is_admin)): ?>
    <div class="animate-fade-in">
        <?php
            $whereDash = "WHERE 1=1";
            if(!$is_hr && !$is_admin && $is_manager) { $whereDash .= " AND u.division_id = $my_div_id"; }
            $totEmp = $conn->query("SELECT COUNT(*) FROM users u $whereDash AND role != 'admin'")->fetch_row()[0];
            $today = date('Y-m-d');
            $onLeave = $conn->query("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id=u.id $whereDash AND status='approved' AND '$today' BETWEEN start_date AND end_date")->fetch_row()[0];
            $pend = $conn->query("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id=u.id $whereDash AND (status='pending_manager' OR status='pending_hr')")->fetch_row()[0];
        ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-soft border border-slate-100 dark:border-slate-700 flex items-center gap-5 transition-transform hover:-translate-y-1">
                <div class="w-14 h-14 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-users"></i></div>
                <div><p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Total Karyawan</p><h3 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $totEmp ?></h3></div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-soft border border-slate-100 dark:border-slate-700 flex items-center gap-5 transition-transform hover:-translate-y-1">
                <div class="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-calendar-check"></i></div>
                <div><p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Sedang Cuti (Hari Ini)</p><h3 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $onLeave ?></h3></div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-soft border border-slate-100 dark:border-slate-700 flex items-center gap-5 transition-transform hover:-translate-y-1">
                <div class="w-14 h-14 rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-hourglass-high"></i></div>
                <div><p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Menunggu Approval</p><h3 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $pend ?></h3></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                    <h3 class="font-bold text-slate-800 dark:text-white text-lg">Monitoring Kuota Cuti</h3>
                    <form method="POST" target="_blank">
                        <button type="submit" name="export_excel" class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold py-2 px-4 rounded-lg transition-colors shadow-sm">
                            <i class="ph-bold ph-microsoft-excel-logo text-lg"></i> Export Excel
                        </button>
                    </form>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs uppercase text-slate-500 dark:text-slate-400 font-bold tracking-wider">
                            <tr>
                                <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">Karyawan</th>
                                <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">Jabatan</th>
                                <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center">Sisa Kuota</th>
                                <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center">Valid Sampai</th>
                                <?php if($is_hr): ?><th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center">Aksi</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-sm">
                            <?php
                            $sqlList = "SELECT u.*, d.name as div_name FROM users u LEFT JOIN divisions d ON u.division_id=d.id $whereDash AND role != 'admin' ORDER BY u.username ASC";
                            $resList = $conn->query($sqlList);
                            while($r = $resList->fetch_assoc()):
                                $validDate = !empty($r['quota_valid_until']) ? date('d M Y', strtotime($r['quota_valid_until'])) : '-';
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors">
                                <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($r['username']) ?></td>
                                <td class="px-6 py-4 text-slate-500 dark:text-slate-400"><?= $r['job_title'] ?> <span class="text-xs opacity-70">(<?= $r['div_name'] ?>)</span></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold"><?= $r['leave_quota'] ?></span>
                                </td>
                                <td class="px-6 py-4 text-center text-slate-500 dark:text-slate-400"><?= $validDate ?></td>
                                <?php if($is_hr): ?>
                                <td class="px-6 py-4 text-center">
                                    <button class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center gap-1" onclick="openQuotaModal(<?= $r['id'] ?>, '<?= addslashes($r['username']) ?>', <?= $r['leave_quota'] ?>, '<?= $r['quota_valid_from'] ?>', '<?= $r['quota_valid_until'] ?>')">
                                        <i class="ph-bold ph-pencil-simple"></i> Adjust
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lg:col-span-1 bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                    <h3 class="font-bold text-slate-800 dark:text-white text-lg">Statistik Bulanan</h3>
                </div>
                <div class="p-4 flex-1 flex items-center justify-center min-h-[300px]">
                    <canvas id="leaveChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'history' && ($is_manager || $is_hr || $is_admin)): ?>
    <div class="animate-fade-in bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="font-bold text-slate-800 dark:text-white text-lg">Riwayat Seluruh Cuti</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs uppercase text-slate-500 dark:text-slate-400 font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Pemohon</th>
                        <th class="px-6 py-4">Detail Cuti</th>
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-sm">
                    <?php
                    $whereHist = "WHERE status IN ('approved', 'rejected', 'cancelled')";
                    if ($is_manager && !$is_hr && !$is_admin) { $whereHist .= " AND l.division_id = $my_div_id"; }
                    
                    $sqlHist = "SELECT l.*, u.username, u.job_title, d.name as div_name FROM leaves l JOIN users u ON l.user_id = u.id LEFT JOIN divisions d ON l.division_id = d.id $whereHist ORDER BY l.created_at DESC LIMIT 100";
                    $resHist = $conn->query($sqlHist);

                    if($resHist->num_rows > 0): while($row = $resHist->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-bold"><?= strtoupper(substr($row['username'],0,1)) ?></div>
                                <div>
                                    <p class="font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($row['username']) ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?= $row['job_title'] ?> &bull; <?= $row['div_name'] ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-block px-2 py-1 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded border border-indigo-100 dark:border-indigo-500/20 text-xs font-bold mb-1"><?= $row['leave_type'] ?> (<?= $row['total_days'] ?> Hari)</span>
                            <div class="text-xs text-slate-500 dark:text-slate-400 max-w-[200px] truncate" title="<?= htmlspecialchars($row['reason']) ?>">"<?= htmlspecialchars($row['reason']) ?>"</div>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                            <?= date('d M Y', strtotime($row['start_date'])) ?> <br><span class="text-xs text-slate-400">s/d</span> <?= date('d M Y', strtotime($row['end_date'])) ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= getBadgeStyle($row['status']) ?>">
                                <?= formatStatusLabel($row['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400 max-w-[200px] whitespace-normal">
                            <?php 
                                if($row['hr_note']) echo "<b>HR:</b> {$row['hr_note']}";
                                elseif($row['manager_note']) echo "<b>Mgr:</b> {$row['manager_note']}";
                                else echo "-";
                            ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400"><i class="ph-fill ph-folder-open text-4xl mb-2 opacity-50 block"></i> Belum ada riwayat.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'approval' && ($is_manager || $is_hr || $is_admin)): ?>
    <div class="animate-fade-in bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="font-bold text-slate-800 dark:text-white text-lg">Butuh Persetujuan</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs uppercase text-slate-500 dark:text-slate-400 font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Pemohon</th>
                        <th class="px-6 py-4">Tipe & Tanggal</th>
                        <th class="px-6 py-4">Alasan</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-sm">
                    <?php
                    $whereApp = "WHERE status != 'approved' AND status != 'rejected' AND status != 'cancelled'";
                    if ($is_hr || $is_admin) { $whereApp .= " AND status = 'pending_hr'"; } 
                    elseif ($is_manager) { $whereApp .= " AND status = 'pending_manager' AND l.division_id = $my_div_id AND l.user_id != $user_id"; }

                    $sqlApp = "SELECT l.*, u.username, u.job_title FROM leaves l JOIN users u ON l.user_id=u.id $whereApp ORDER BY l.created_at ASC";
                    $resApp = $conn->query($sqlApp);

                    if($resApp->num_rows > 0): while($row = $resApp->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($row['username']) ?></div>
                            <div class="text-xs text-slate-500 dark:text-slate-400"><?= $row['job_title'] ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-indigo-600 dark:text-indigo-400"><?= $row['leave_type'] ?> (<?= $row['total_days'] ?> Hari)</div>
                            <div class="text-xs text-slate-600 dark:text-slate-300"><?= date('d M', strtotime($row['start_date'])) ?> - <?= date('d M Y', strtotime($row['end_date'])) ?></div>
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400 max-w-[250px] truncate" title="<?= htmlspecialchars($row['reason']) ?>">
                            "<?= htmlspecialchars($row['reason']) ?>"
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= getBadgeStyle($row['status']) ?>">
                                <?= formatStatusLabel($row['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button class="p-2 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white dark:bg-emerald-500/10 dark:hover:bg-emerald-500 transition-colors" onclick="openModalApprove(<?= $row['id'] ?>, 'approve')" title="Approve">
                                    <i class="ph-bold ph-check text-lg"></i>
                                </button>
                                <button class="p-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white dark:bg-rose-500/10 dark:hover:bg-rose-500 transition-colors" onclick="openModalApprove(<?= $row['id'] ?>, 'reject')" title="Reject">
                                    <i class="ph-bold ph-x text-lg"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400"><i class="ph-fill ph-check-circle text-4xl mb-2 opacity-50 block text-emerald-500"></i> Tidak ada antrian approval.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'myleaves'): ?>
    <div class="animate-fade-in bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 dark:text-white text-lg">Riwayat Pengajuan Saya</h3>
            <span class="px-3 py-1 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-bold rounded-lg text-sm border border-indigo-100 dark:border-indigo-500/20">Sisa Kuota: <?= $my_quota ?> Hari</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs uppercase text-slate-500 dark:text-slate-400 font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Tipe</th>
                        <th class="px-6 py-4">Tanggal Cuti</th>
                        <th class="px-6 py-4 text-center">Durasi</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Catatan</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-sm">
                    <?php
                    $mySql = "SELECT * FROM leaves WHERE user_id = $user_id ORDER BY created_at DESC";
                    $myRes = $conn->query($mySql);
                    if($myRes->num_rows > 0): while($row = $myRes->fetch_assoc()):
                        $rev_count = $row['revision_count'] ?? 0;
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200"><?= $row['leave_type'] ?></td>
                        <td class="px-6 py-4">
                            <div class="font-semibold text-slate-700 dark:text-slate-300"><?= date('d M', strtotime($row['start_date'])) ?> - <?= date('d M Y', strtotime($row['end_date'])) ?></div>
                            <div class="text-[11px] text-slate-400 mt-0.5">Submit: <?= date('d M Y', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td class="px-6 py-4 text-center font-medium"><?= $row['total_days'] ?> Hr</td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= getBadgeStyle($row['status']) ?>">
                                <?= formatStatusLabel($row['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400 max-w-[200px] whitespace-normal">
                            <?php if($row['manager_note']) echo "<b>Mgr:</b> {$row['manager_note']}<br>"; ?>
                            <?php if($row['hr_note']) echo "<b>HR:</b> {$row['hr_note']}<br>"; ?>
                            <?php if($row['edit_reason']) echo "<span class='text-rose-500'>{$row['edit_reason']}</span>"; ?>
                            <?php if(!$row['manager_note'] && !$row['hr_note'] && !$row['edit_reason']) echo "-"; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if($row['status'] == 'pending_manager' || $row['status'] == 'pending_hr'): ?>
                                    <button class="px-3 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white dark:bg-rose-500/10 dark:hover:bg-rose-500 transition-colors rounded-lg text-xs font-bold" onclick="openModalCancel(<?= $row['id'] ?>)">Batalkan</button>
                                <?php elseif($row['status'] == 'approved' && $rev_count < 1): ?>
                                    <button class="px-3 py-1.5 bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white dark:bg-amber-500/10 dark:hover:bg-amber-500 transition-colors rounded-lg text-xs font-bold" onclick="openModalRevise(<?= $row['id'] ?>, '<?= $row['start_date'] ?>', '<?= $row['end_date'] ?>')">Revisi</button>
                                    <button class="px-3 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-500 hover:text-white dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors rounded-lg text-xs font-bold" onclick="openModalCancel(<?= $row['id'] ?>)">Batalkan</button>
                                <?php elseif($row['status'] == 'approved'): ?>
                                    <button class="px-3 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-500 hover:text-white dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors rounded-lg text-xs font-bold" onclick="openModalCancel(<?= $row['id'] ?>)">Batalkan</button>
                                <?php else: ?>
                                    <span class="text-slate-300 dark:text-slate-600">-</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400"><i class="ph-fill ph-file-dashed text-4xl mb-2 opacity-50 block"></i> Anda belum memiliki riwayat pengajuan cuti.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'create'): ?>
    <div class="animate-fade-in flex justify-center">
        <div class="w-full max-w-3xl bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                <h2 class="text-xl font-extrabold text-slate-900 dark:text-white">Formulir Pengajuan Cuti</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Pastikan sisa kuota Anda mencukupi sebelum mengajukan cuti tahunan.</p>
            </div>
            <div class="p-8">
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Jenis Cuti</label>
                            <div class="relative">
                                <i class="ph-fill ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <select name="leave_type" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all">
                                    <option value="Annual">Annual Leave (Tahunan)</option>
                                    <option value="Sick">Sick Leave (Sakit)</option>
                                    <option value="Unpaid">Unpaid Leave</option>
                                    <option value="Other">Other</option>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Sisa Kuota Tahunan</label>
                            <div class="relative">
                                <i class="ph-fill ph-ticket absolute left-4 top-1/2 -translate-y-1/2 text-indigo-400 text-lg"></i>
                                <input type="text" class="w-full pl-11 pr-4 py-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-500/30 text-indigo-700 dark:text-indigo-300 font-bold rounded-xl outline-none" value="<?= $my_quota ?> Hari" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Tanggal Mulai</label>
                            <div class="relative">
                                <i class="ph-fill ph-calendar-blank absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="date" name="start_date" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Tanggal Selesai</label>
                            <div class="relative">
                                <i class="ph-fill ph-calendar-check absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="date" name="end_date" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all" required>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Alasan Cuti</label>
                        <textarea name="reason" rows="3" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all resize-none" placeholder="Tuliskan alasan lengkap pengajuan..." required></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                        <button type="submit" name="submit_leave" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 px-6 rounded-xl transition-all shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-paper-plane-right text-xl"></i> Kirim Pengajuan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
<div id="modalApprove" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md p-6 transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-200 dark:border-slate-700 mx-4">
        <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-700">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2"><i class="ph-fill ph-shield-check text-indigo-500"></i> Konfirmasi Persetujuan</h3>
            <button onclick="closeModal('modalApprove')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="leave_id" id="app_leave_id">
            <input type="hidden" name="action_type" id="app_action_type">
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Catatan (Opsional)</label>
            <textarea name="approval_note" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none dark:text-white focus:border-indigo-500 resize-none mb-3" rows="2" placeholder="Ketik catatan..."></textarea>
            
            <div id="app_alert" class="hidden mb-4 p-3 rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 text-xs font-medium flex items-start gap-2">
                <i class="ph-fill ph-info text-base shrink-0"></i>
                <p>Tindakan ini akan meneruskan status cuti ke HR untuk finalisasi.</p>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeModal('modalApprove')" class="px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors">Batal</button>
                <button type="submit" name="process_approval" class="px-6 py-2.5 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-500/30 transition-all">Proses</button>
            </div>
        </form>
    </div>
</div>

<div id="modalCancel" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md p-6 transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-200 dark:border-slate-700 mx-4">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 dark:border-slate-700">
            <h3 class="text-lg font-bold text-rose-600 flex items-center gap-2"><i class="ph-fill ph-warning-circle"></i> Batalkan Cuti</h3>
            <button onclick="closeModal('modalCancel')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="cancel_id" id="cancel_leave_id">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Apakah Anda yakin ingin membatalkan pengajuan cuti ini? Kuota akan dikembalikan otomatis jika status sudah disetujui.</p>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Alasan Pembatalan <span class="text-rose-500">*</span></label>
            <textarea name="cancel_reason" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none dark:text-white focus:border-rose-500 resize-none" rows="2" required></textarea>
            
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeModal('modalCancel')" class="px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 transition-colors">Tutup</button>
                <button type="submit" name="cancel_leave_request" class="px-6 py-2.5 rounded-xl font-bold text-white bg-rose-600 hover:bg-rose-700 shadow-md shadow-rose-500/30 transition-all">Ya, Batalkan</button>
            </div>
        </form>
    </div>
</div>

<div id="modalRevise" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md p-6 transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-200 dark:border-slate-700 mx-4">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 dark:border-slate-700">
            <h3 class="text-lg font-bold text-amber-600 flex items-center gap-2"><i class="ph-fill ph-calendar-edit"></i> Revisi Tanggal</h3>
            <button onclick="closeModal('modalRevise')" class="text-slate-400 hover:text-slate-600"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="revise_id" id="revise_leave_id">
            <div class="mb-4 p-3 rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 text-xs font-medium flex items-center gap-2 border border-amber-200/50">
                <i class="ph-fill ph-warning-circle text-base"></i> Hanya dapat direvisi maksimal 1 kali.
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Mulai Baru</label>
                    <input type="date" name="revise_start" id="rev_start" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none text-sm dark:text-white" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Selesai Baru</label>
                    <input type="date" name="revise_end" id="rev_end" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none text-sm dark:text-white" required>
                </div>
            </div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Alasan Revisi <span class="text-amber-500">*</span></label>
            <textarea name="revise_reason" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl outline-none dark:text-white focus:border-amber-500 resize-none" rows="2" required></textarea>
            
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeModal('modalRevise')" class="px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 transition-colors">Batal</button>
                <button type="submit" name="revise_leave_request" class="px-6 py-2.5 rounded-xl font-bold text-white bg-amber-500 hover:bg-amber-600 shadow-md shadow-amber-500/30 transition-all">Ajukan Revisi</button>
            </div>
        </form>
    </div>
</div>

<div id="modalQuota" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-sm p-6 transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-200 dark:border-slate-700 mx-4">
        <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-700">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white">Adjust Kuota</h3>
            <button onclick="closeModal('modalQuota')" class="text-slate-400 hover:text-slate-600"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="quota_user_id" id="q_uid">
            
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Nama Karyawan</label>
                <input type="text" name="username" id="q_uname" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-medium dark:text-white outline-none focus:border-indigo-500" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Jumlah Kuota</label>
                <input type="number" name="new_quota" id="q_val" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-indigo-600 dark:text-indigo-400 outline-none focus:border-indigo-500" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Berlaku Mulai</label>
                <input type="date" name="valid_from" id="q_from" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Berlaku Sampai</label>
                <input type="date" name="valid_until" id="q_until" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-indigo-500">
            </div>
            
            <div class="pt-4 mt-2 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" name="update_quota" class="w-full py-3 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-500/30 transition-all">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // --- CUSTOM MODAL LOGIC (Pengganti Bootstrap) ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.remove('hidden');
        // Delay slight to allow display:block to apply before animating opacity/transform
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
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

    // --- CHART.JS CONFIGURATION ---
    <?php if ($active_tab == 'dashboard' && ($is_manager || $is_hr || $is_admin)): ?>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('leaveChart');
        if(!ctx) return;

        // Cek dark mode untuk warna teks chart
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#94a3b8' : '#64748b';
        const gridColor = isDark ? '#334155' : '#e2e8f0';

        <?php
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $dataCounts = [];
            foreach(range(1,12) as $m) {
                $sqlC = "SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id=u.id $whereDash AND MONTH(start_date) = $m AND YEAR(start_date) = YEAR(CURRENT_DATE)";
                $dataCounts[] = $conn->query($sqlC)->fetch_row()[0];
            }
        ?>
        
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Total Cuti',
                    data: <?= json_encode($dataCounts) ?>,
                    backgroundColor: '#4F46E5', // Tailwind Indigo-600
                    borderRadius: 6,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        ticks: { stepSize: 1, color: textColor },
                        grid: { color: gridColor, borderDash: [4, 4] }
                    },
                    x: {
                        ticks: { color: textColor },
                        grid: { display: false }
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
                        padding: 10,
                        displayColors: false
                    }
                }
            }
        });
    });
    <?php endif; ?>
</script>