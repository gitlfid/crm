<?php
// =========================================================================
// FILE: delivery_list.php
// DESC: Advanced Multi-Tab Delivery Management (IT -> HR -> Complete)
// =========================================================================
ob_start();
include '../config/database.php';
include '../config/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. AUTO-UPDATE DATABASE SCHEMA (FIXED) ---
// Mengecek apakah kolom sudah ada sebelum melakukan ALTER TABLE agar tidak error Duplicate
$new_columns = [
    'client_id' => 'INT NULL',
    'invoice_no' => 'VARCHAR(100) NULL',
    'po_no' => 'VARCHAR(100) NULL',
    'do_no' => 'VARCHAR(100) NULL',
    'invoice_file' => 'VARCHAR(255) NULL',
    'po_file' => 'VARCHAR(255) NULL',
    'do_file' => 'VARCHAR(255) NULL',
    'pic_phone' => 'VARCHAR(50) NULL',
    'address' => 'TEXT NULL'
];

foreach($new_columns as $col => $type) {
    $check_col = $conn->query("SHOW COLUMNS FROM deliveries LIKE '$col'");
    if ($check_col && $check_col->num_rows == 0) {
        // Kolom belum ada, maka tambahkan
        $conn->query("ALTER TABLE deliveries ADD COLUMN $col $type");
    }
}

// --- 2. CEK ROLE & DIVISI ---
$uID = $_SESSION['user_id'];
$uRole = strtolower($_SESSION['role'] ?? 'standard');
$qDiv = $conn->query("SELECT d.name FROM users u LEFT JOIN divisions d ON u.division_id = d.id WHERE u.id = $uID");
$my_division = strtolower($qDiv->fetch_assoc()['name'] ?? '');

$is_it = ($my_division == 'information technology' || $uRole == 'admin');
$is_hr = ($my_division == 'human resource' || $my_division == 'human resources' || $uRole == 'admin' || $my_division == 'hr');

// --- 3. AJAX HANDLER: AUTO COMPLETE TRACKING ---
if(isset($_POST['action']) && $_POST['action'] == 'auto_complete_delivery') {
    if (ob_get_length()) ob_end_clean();
    $id = intval($_POST['id']);
    $date = date('Y-m-d H:i:s');
    $conn->query("UPDATE deliveries SET status = 'completed', delivered_date = '$date' WHERE id = $id");
    echo json_encode(['status' => 'success']);
    exit;
}

// --- 4. FORM PROCESS HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. SUBMIT DELIVERY (IT)
    if (isset($_POST['submit_it_delivery'])) {
        $client_id = intval($_POST['client_id']);
        $card_type = $conn->real_escape_string($_POST['card_type']);
        $qty = intval($_POST['qty']);
        $data_package = $conn->real_escape_string($_POST['data_package']);
        $invoice_no = $conn->real_escape_string($_POST['invoice_no']);
        $po_no = $conn->real_escape_string($_POST['po_no']);
        $do_no = $conn->real_escape_string($_POST['do_no']);
        
        $uploadDir = __DIR__ . '/../uploads/deliveries/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $inv_file = $po_file = $do_file = "NULL";

        // Upload Helper
        function uploadDoc($fileArr, $prefix, $dir) {
            if (isset($fileArr) && $fileArr['error'] == 0) {
                $ext = pathinfo($fileArr['name'], PATHINFO_EXTENSION);
                $name = $prefix . '_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($fileArr['tmp_name'], $dir . $name)) return "'$name'";
            }
            return "NULL";
        }

        $inv_file = uploadDoc($_FILES['invoice_file'], 'INV', $uploadDir);
        $po_file = uploadDoc($_FILES['po_file'], 'PO', $uploadDir);
        $do_file = uploadDoc($_FILES['do_file'], 'DO', $uploadDir);

        $sql = "INSERT INTO deliveries (client_id, item_name, qty, data_package, invoice_no, po_no, do_no, invoice_file, po_file, do_file, status, delivery_date) 
                VALUES ($client_id, '$card_type', $qty, '$data_package', '$invoice_no', '$po_no', '$do_no', $inv_file, $po_file, $do_file, 'waiting_hr', NOW())";
        
        if($conn->query($sql)) {
            echo "<script>alert('Data berhasil di-submit ke HR!'); window.location='delivery_list.php?tab=submit';</script>";
        } else {
            echo "<script>alert('Error: " . $conn->error . "');</script>";
        }
    }

    // B. PROCESS DELIVERY (HR)
    if (isset($_POST['process_hr_delivery'])) {
        $id = intval($_POST['delivery_id']);
        $address = $conn->real_escape_string($_POST['address']);
        $pic_name = $conn->real_escape_string($_POST['pic_name']);
        $pic_phone = $conn->real_escape_string($_POST['pic_phone']);
        $courier = $conn->real_escape_string($_POST['courier_name']);
        $tracking = $conn->real_escape_string($_POST['tracking_number']);
        
        // Status berubah jadi on_going karena resi sudah diisi
        $sql = "UPDATE deliveries SET 
                address = '$address', receiver_name = '$pic_name', pic_phone = '$pic_phone', 
                courier_name = '$courier', tracking_number = '$tracking', status = 'on_going' 
                WHERE id = $id";
                
        if($conn->query($sql)) {
            echo "<script>alert('Data berhasil diproses & masuk ke On Going!'); window.location='delivery_list.php?tab=process';</script>";
        }
    }
}

// --- 5. FETCH DATA FOR TABS ---
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// Helper to fetch data by status
function getDeliveriesByStatus($conn, $status) {
    $st = $conn->real_escape_string($status);
    $sql = "SELECT d.*, c.company_name as client_name 
            FROM deliveries d 
            LEFT JOIN clients c ON d.client_id = c.id 
            WHERE d.status = '$st' ORDER BY d.id DESC";
    return $conn->query($sql);
}

$data_waiting = getDeliveriesByStatus($conn, 'waiting_hr');
$data_ongoing = getDeliveriesByStatus($conn, 'on_going');
$data_complete = getDeliveriesByStatus($conn, 'completed');

// Dapatkan tab aktif dari URL
$active_tab = $_GET['tab'] ?? 'dashboard';

// Helper UI Tailwind
$tab_btn_base = "px-6 py-3.5 text-sm font-bold border-b-2 transition-colors whitespace-nowrap flex items-center gap-2";
$tab_btn_active = "$tab_btn_base border-indigo-600 text-indigo-600 dark:text-indigo-400";
$tab_btn_inactive = "$tab_btn_base border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300";

$page_title = "Delivery Management";
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
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Delivery Hub</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Sistem manajemen logistik terpadu (IT & HR Collaboration).</p>
        </div>
    </div>

    <div class="flex overflow-x-auto custom-scrollbar border-b border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
        <button onclick="switchTab('dashboard')" id="btn-tab-dashboard" class="<?= $active_tab=='dashboard'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-fill ph-squares-four"></i> Dashboard
        </button>
        <button onclick="switchTab('submit')" id="btn-tab-submit" class="<?= $active_tab=='submit'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-fill ph-paper-plane-tilt"></i> Submit Delivery (IT)
        </button>
        <button onclick="switchTab('process')" id="btn-tab-process" class="<?= $active_tab=='process'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-fill ph-hourglass-high"></i> HR Process <span class="bg-rose-500 text-white text-[10px] px-1.5 py-0.5 rounded-full"><?= $data_waiting->num_rows ?></span>
        </button>
        <button onclick="switchTab('ongoing')" id="btn-tab-ongoing" class="<?= $active_tab=='ongoing'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-fill ph-truck"></i> On Going <span class="bg-amber-500 text-white text-[10px] px-1.5 py-0.5 rounded-full"><?= $data_ongoing->num_rows ?></span>
        </button>
        <button onclick="switchTab('complete')" id="btn-tab-complete" class="<?= $active_tab=='complete'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-fill ph-check-circle"></i> Complete
        </button>
    </div>

    <div id="content-dashboard" class="tab-content <?= $active_tab=='dashboard'?'block':'hidden' ?> animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-100 dark:border-slate-700 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-package"></i></div>
                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Data</p><h3 class="text-2xl font-black text-slate-800 dark:text-white"><?= $data_waiting->num_rows + $data_ongoing->num_rows + $data_complete->num_rows ?></h3></div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-100 dark:border-slate-700 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-500/10 text-rose-500 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-hourglass-high"></i></div>
                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Waiting HR</p><h3 class="text-2xl font-black text-slate-800 dark:text-white"><?= $data_waiting->num_rows ?></h3></div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-100 dark:border-slate-700 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-500 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-truck"></i></div>
                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">On Going</p><h3 class="text-2xl font-black text-slate-800 dark:text-white"><?= $data_ongoing->num_rows ?></h3></div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-100 dark:border-slate-700 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-check-circle"></i></div>
                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Completed</p><h3 class="text-2xl font-black text-slate-800 dark:text-white"><?= $data_complete->num_rows ?></h3></div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-6 text-center text-slate-500 dark:text-slate-400">
            <i class="ph-fill ph-chart-polar text-6xl mb-3 opacity-20"></i>
            <h4 class="font-bold text-slate-700 dark:text-slate-300">Welcome to Delivery Hub</h4>
            <p class="text-xs mt-1">Silakan gunakan navigasi tab di atas untuk mengelola alur pengiriman.</p>
        </div>
    </div>

    <div id="content-submit" class="tab-content <?= $active_tab=='submit'?'block':'hidden' ?> animate-fade-in-up" style="animation-delay: 0.2s;">
        
        <?php if($is_it): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-indigo-50/50 dark:bg-indigo-900/10">
                <h3 class="font-bold text-indigo-600 dark:text-indigo-400 text-sm uppercase tracking-widest flex items-center gap-2">
                    <i class="ph-fill ph-paper-plane-tilt text-lg"></i> Form Submit Delivery
                </h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-5">
                    <div class="xl:col-span-2">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Client Name <span class="text-rose-500">*</span></label>
                        <select name="client_id" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" required>
                            <option value="">- Pilih Client -</option>
                            <?php $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Card Type / Item <span class="text-rose-500">*</span></label>
                        <input type="text" name="card_type" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" placeholder="e.g. Telkomsel IoT" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Card Qty <span class="text-rose-500">*</span></label>
                        <input type="number" name="qty" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" required>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Data Package</label>
                        <input type="text" name="data_package" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" placeholder="e.g. 10GB / Month">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">No Invoice</label>
                        <input type="text" name="invoice_no" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">No PO</label>
                        <input type="text" name="po_no" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">No DO</label>
                        <input type="text" name="do_no" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all">
                    </div>

                    <div class="col-span-1 md:col-span-3 xl:col-span-4 border-t border-slate-100 dark:border-slate-700 pt-4 mt-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Upload Invoice (PDF)</label>
                            <input type="file" name="invoice_file" accept=".pdf" class="w-full block text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-700 dark:file:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Upload PO (PDF)</label>
                            <input type="file" name="po_file" accept=".pdf" class="w-full block text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-700 dark:file:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Upload DO (PDF)</label>
                            <input type="file" name="do_file" accept=".pdf" class="w-full block text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-700 dark:file:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl">
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit" name="submit_it_delivery" class="px-8 py-3 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-md active:scale-95 flex items-center gap-2">
                        <i class="ph-bold ph-paper-plane-right text-sm"></i> Submit to HR
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
            <div class="bg-rose-50 text-rose-600 p-4 rounded-xl border border-rose-200 text-sm font-bold flex items-center gap-3 mb-6">
                <i class="ph-fill ph-warning-circle text-xl"></i> Anda tidak memiliki akses untuk submit delivery. Hubungi divisi IT.
            </div>
        <?php endif; ?>

        <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mb-3">Submissions Waiting for HR</h4>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                        <tr>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Item Details</th>
                            <th class="px-5 py-3 text-center">Docs</th>
                            <th class="px-5 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px]">
                        <?php if($data_waiting->num_rows > 0): $data_waiting->data_seek(0); while($row = $data_waiting->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors">
                            <td class="px-5 py-3 font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($row['client_name']) ?></td>
                            <td class="px-5 py-3">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($row['item_name']) ?> <span class="text-slate-500 dark:text-slate-400 font-black ml-1">x<?= $row['qty'] ?></span></div>
                                <div class="text-[10px] text-slate-500 mt-0.5"><?= htmlspecialchars($row['data_package']) ?></div>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <?php if($row['invoice_file']): ?><span class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded text-[9px] font-bold">INV</span><?php endif; ?>
                                    <?php if($row['po_file']): ?><span class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded text-[9px] font-bold">PO</span><?php endif; ?>
                                    <?php if($row['do_file']): ?><span class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded text-[9px] font-bold">DO</span><?php endif; ?>
                                    <?php if(!$row['invoice_file'] && !$row['po_file'] && !$row['do_file']) echo '<span class="text-slate-400 italic">No Docs</span>'; ?>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex px-2 py-1 rounded bg-rose-50 text-rose-600 border border-rose-100 text-[9px] font-bold uppercase tracking-widest"><i class="ph-bold ph-hourglass-high mr-1"></i> Waiting HR</span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400 text-xs">Belum ada data menunggu.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="content-process" class="tab-content <?= $active_tab=='process'?'block':'hidden' ?> animate-fade-in-up" style="animation-delay: 0.2s;">
        
        <?php if(!$is_hr): ?>
            <div class="bg-rose-50 text-rose-600 p-4 rounded-xl border border-rose-200 text-sm font-bold flex items-center gap-3 mb-6">
                <i class="ph-fill ph-warning-circle text-xl"></i> Akses Ditolak. Hanya divisi HR yang dapat memproses pengiriman.
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                        <tr>
                            <th class="px-5 py-3">Submission Details</th>
                            <th class="px-5 py-3">Package Info</th>
                            <th class="px-5 py-3 text-center">Reference Docs</th>
                            <th class="px-5 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px]">
                        <?php if($data_waiting->num_rows > 0): $data_waiting->data_seek(0); while($row = $data_waiting->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs"><?= htmlspecialchars($row['client_name']) ?></div>
                                <div class="text-[10px] text-slate-500 mt-1"><i class="ph-fill ph-clock"></i> <?= date('d M Y, H:i', strtotime($row['delivery_date'])) ?></div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($row['item_name']) ?> <span class="bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-1.5 rounded ml-1 text-[10px]">x<?= $row['qty'] ?></span></div>
                                <div class="text-[10px] text-slate-500 mt-1">Pkg: <?= htmlspecialchars($row['data_package']) ?></div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <div class="flex flex-col gap-1 items-center font-mono text-[9px] text-slate-500">
                                    <?php if($row['invoice_no']) echo "<div>INV: {$row['invoice_no']}</div>"; ?>
                                    <?php if($row['po_no']) echo "<div>PO: {$row['po_no']}</div>"; ?>
                                    <?php if($row['do_no']) echo "<div>DO: {$row['do_no']}</div>"; ?>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <?php if($is_hr): ?>
                                    <button onclick='openProcessModal(<?= json_encode($row) ?>)' class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-1.5 px-4 rounded-lg shadow-sm transition-colors text-[10px] uppercase tracking-widest flex items-center gap-1.5 mx-auto">
                                        <i class="ph-bold ph-pencil-line text-sm"></i> Process
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-400 italic text-[10px]">- Restricted -</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-400">Tidak ada request pengiriman dari IT.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="content-ongoing" class="tab-content <?= $active_tab=='ongoing'?'block':'hidden' ?> animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto w-full pb-20">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                        <tr>
                            <th class="px-5 py-3">Client & Destination</th>
                            <th class="px-5 py-3">Package Details</th>
                            <th class="px-5 py-3">Courier & Tracking</th>
                            <th class="px-5 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px]">
                        <?php if($data_ongoing->num_rows > 0): $data_ongoing->data_seek(0); while($row = $data_ongoing->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors">
                            <td class="px-5 py-4 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs mb-1"><?= htmlspecialchars($row['client_name']) ?></div>
                                <div class="font-medium text-slate-500 mb-0.5"><i class="ph-fill ph-user-circle"></i> <?= htmlspecialchars($row['receiver_name']) ?> (<?= htmlspecialchars($row['pic_phone']) ?>)</div>
                                <div class="text-[10px] text-slate-400 line-clamp-2 max-w-[250px]"><i class="ph-fill ph-map-pin"></i> <?= htmlspecialchars($row['address']) ?></div>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($row['item_name']) ?> <span class="bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-1.5 rounded ml-1 text-[10px]">x<?= $row['qty'] ?></span></div>
                                <div class="text-[10px] text-slate-500 mt-1">Pkg: <?= htmlspecialchars($row['data_package']) ?></div>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-[9px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 mb-1.5">
                                    <i class="ph-fill ph-truck text-slate-400"></i> <?= htmlspecialchars($row['courier_name']) ?>
                                </div>
                                <div class="font-mono font-bold text-blue-600 dark:text-blue-400 tracking-wider">
                                    <?= htmlspecialchars($row['tracking_number']) ?>
                                </div>
                            </td>
                            <td class="px-5 py-4 align-top text-center relative">
                                <button onclick="autoCheckTracking(<?= $row['id'] ?>, '<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition-all active:scale-95 text-[10px] uppercase tracking-widest flex items-center gap-1.5 mx-auto" id="btn-track-<?= $row['id'] ?>">
                                    <i class="ph-bold ph-crosshair text-sm"></i> Track & Verify
                                </button>
                                <p class="text-[9px] text-slate-400 mt-2 italic">Sistem akan otomatis update ke 'Complete' jika resi Delivered.</p>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-400">Tidak ada pengiriman yang sedang berlangsung.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="content-complete" class="tab-content <?= $active_tab=='complete'?'block':'hidden' ?> animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto w-full pb-10">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                        <tr>
                            <th class="px-5 py-3">Delivery Summary</th>
                            <th class="px-5 py-3">Client & Destination</th>
                            <th class="px-5 py-3">Item Detail</th>
                            <th class="px-5 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px]">
                        <?php if($data_complete->num_rows > 0): $data_complete->data_seek(0); while($row = $data_complete->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors">
                            <td class="px-5 py-4 align-top">
                                <div class="font-mono font-bold text-slate-700 dark:text-slate-300 text-xs mb-1"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                <div class="text-[10px] text-slate-500 font-bold uppercase tracking-widest"><?= htmlspecialchars($row['courier_name']) ?></div>
                                <div class="text-[9px] text-emerald-500 mt-1 font-medium"><i class="ph-fill ph-check-circle"></i> Delivered: <?= date('d M Y', strtotime($row['delivered_date'])) ?></div>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs mb-0.5"><?= htmlspecialchars($row['client_name']) ?></div>
                                <div class="text-[10px] text-slate-500 line-clamp-2 max-w-[250px]"><?= htmlspecialchars($row['address']) ?></div>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($row['item_name']) ?> <span class="text-slate-500">x<?= $row['qty'] ?></span></div>
                            </td>
                            <td class="px-5 py-4 align-top text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 text-[9px] font-black uppercase tracking-widest">
                                    <i class="ph-bold ph-checks text-[11px]"></i> Completed
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-400">Belum ada pengiriman selesai.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div id="processModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-2xl transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl flex flex-col overflow-hidden">
        <form method="POST">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-amber-500 text-white flex justify-between items-center">
                <h3 class="text-sm font-bold flex items-center gap-2"><i class="ph-bold ph-pencil-line text-lg"></i> Process Delivery (HR)</h3>
                <button type="button" onclick="closeModal('processModal')" class="text-white/70 hover:text-white"><i class="ph-bold ph-x text-lg"></i></button>
            </div>
            
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                <input type="hidden" name="delivery_id" id="modal_del_id">
                
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Alamat Pengiriman Client <span class="text-rose-500">*</span></label>
                    <textarea name="address" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 dark:text-white outline-none transition-all resize-none" rows="3" required placeholder="Masukkan alamat lengkap tujuan..."></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">PIC Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="pic_name" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 dark:text-white outline-none transition-all" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">PIC Phone <span class="text-rose-500">*</span></label>
                    <input type="text" name="pic_phone" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 dark:text-white outline-none transition-all" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Delivery Method (Courier) <span class="text-rose-500">*</span></label>
                    <select name="courier_name" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 dark:text-white outline-none transition-all" required>
                        <option value="">- Pilih Kurir -</option>
                        <option value="JNE">JNE</option><option value="J&T">J&T</option><option value="SICEPAT">SiCepat</option><option value="ANTERAJA">AnterAja</option><option value="GOJEK">Gojek/Grab</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Tracking Number / Resi <span class="text-rose-500">*</span></label>
                    <input type="text" name="tracking_number" class="w-full px-4 py-2.5 bg-amber-50 dark:bg-slate-900 border border-amber-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold focus:ring-2 focus:ring-amber-500 text-amber-700 dark:text-amber-500 outline-none transition-all" required placeholder="Input resi pengiriman...">
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2 bg-slate-50/50 dark:bg-slate-800/50">
                <button type="button" onclick="closeModal('processModal')" class="px-6 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 transition-colors text-xs">Batal</button>
                <button type="submit" name="process_hr_delivery" class="px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 transition-colors shadow-md active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-check text-sm"></i> Submit & Pindah ke On Going
                </button>
            </div>
        </form>
    </div>
</div>

<div id="trackingModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0 bg-slate-50/50 dark:bg-slate-800/50 rounded-t-3xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400 flex items-center justify-center text-xl">
                    <i class="ph-fill ph-truck"></i>
                </div>
                <h3 class="text-lg font-extrabold text-slate-800 dark:text-white tracking-tight">Live Shipment Status</h3>
            </div>
            <button onclick="closeModal('trackingModal')" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 hover:bg-rose-100 text-slate-500 hover:text-rose-600 transition-colors">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-white dark:bg-slate-800 rounded-b-3xl text-sm" id="trackingResult">
            </div>
    </div>
</div>

<script>
    // --- TAB SWITCHER ---
    function switchTab(tabId) {
        const url = new URL(window.location); url.searchParams.set('tab', tabId); window.history.pushState({}, '', url);
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('[id^="btn-tab-"]').forEach(el => {
            el.className = "px-6 py-3.5 text-sm font-bold border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300";
        });
        document.getElementById('content-' + tabId).classList.remove('hidden');
        document.getElementById('content-' + tabId).classList.add('block');
        document.getElementById('btn-tab-' + tabId).className = "px-6 py-3.5 text-sm font-bold border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 border-indigo-600 text-indigo-600 dark:text-indigo-400";
    }

    // --- MODAL HANDLERS ---
    function openModal(id) {
        const m = document.getElementById(id); const b = m.querySelector('.modal-box');
        m.classList.remove('hidden');
        setTimeout(() => { m.classList.remove('opacity-0'); b.classList.remove('scale-95', 'opacity-0'); }, 10);
    }
    function closeModal(id) {
        const m = document.getElementById(id); const b = m.querySelector('.modal-box');
        m.classList.add('opacity-0'); b.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { m.classList.add('hidden'); }, 300);
    }
    function openProcessModal(data) {
        document.getElementById('modal_del_id').value = data.id;
        openModal('processModal');
    }

    // --- AUTO CHECK TRACKING & UPDATE TO COMPLETED ---
    function autoCheckTracking(id, resi, kurir) {
        let btn = document.getElementById('btn-track-'+id);
        let origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner animate-spin"></i> Checking...';
        btn.disabled = true;

        openModal('trackingModal');
        document.getElementById('trackingResult').innerHTML = `
            <div class="flex flex-col items-center justify-center py-16 opacity-70">
                <i class="ph-bold ph-spinner-gap text-5xl animate-spin text-blue-500 mb-4"></i>
                <p class="font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-xs">Connecting to Courier API...</p>
            </div>`;

        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('trackingResult').innerHTML = data;
                btn.innerHTML = origHtml; btn.disabled = false;
                
                // Deteksi Otomatis Jika Teks HTML mengandung kata "Delivered" atau "DELIVERED"
                let upperData = data.toUpperCase();
                if (upperData.includes('DELIVERED') || upperData.includes('BERHASIL DIKIRIM')) {
                    // Trigger Update Status Completed ke backend secara diam-diam
                    const formData = new FormData();
                    formData.append('action', 'auto_complete_delivery');
                    formData.append('id', id);
                    fetch('', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(res => {
                        if(res.status == 'success') {
                            alert("Sistem mendeteksi paket telah terkirim! Data akan dipindahkan ke tab 'Complete'.");
                            location.reload(); // Reload halaman untuk update data di tabel
                        }
                    });
                }
            })
            .catch(err => {
                document.getElementById('trackingResult').innerHTML = `<div class="p-5 bg-rose-50 text-rose-600 rounded-2xl flex items-center gap-3 font-bold"><i class="ph-fill ph-warning-circle text-3xl"></i> Gagal koneksi ke kurir.</div>`;
                btn.innerHTML = origHtml; btn.disabled = false;
            });
    }
</script>

<?php include 'includes/footer.php'; ?>