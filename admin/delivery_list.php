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

    // B. PROCESS DELIVERY STAGE 1 (HR) - Simpan Data Klien Saja
    if (isset($_POST['process_hr_info'])) {
        $id = intval($_POST['delivery_id']);
        $address = $conn->real_escape_string($_POST['address']);
        $pic_name = $conn->real_escape_string($_POST['pic_name']);
        $pic_phone = $conn->real_escape_string($_POST['pic_phone']);
        $courier = $conn->real_escape_string($_POST['courier_name']);
        
        // Simpan info namun status tetap waiting_hr
        $sql = "UPDATE deliveries SET 
                address = '$address', receiver_name = '$pic_name', pic_phone = '$pic_phone', 
                courier_name = '$courier'
                WHERE id = $id";
                
        if($conn->query($sql)) {
            echo "<script>alert('Data Alamat dan PIC berhasil disimpan! Silakan lanjut input Resi/Tracking.'); window.location='delivery_list.php?tab=process';</script>";
        }
    }

    // C. PROCESS DELIVERY STAGE 2 (HR) - Input Resi & Pindah ke On Going
    if (isset($_POST['process_hr_resi'])) {
        $id = intval($_POST['delivery_id']);
        $tracking = $conn->real_escape_string($_POST['tracking_number']);
        
        // Pindah status ke on_going
        $sql = "UPDATE deliveries SET tracking_number = '$tracking', status = 'on_going' WHERE id = $id";
                
        if($conn->query($sql)) {
            echo "<script>alert('Resi berhasil diinput! Data dipindahkan ke tab On Going.'); window.location='delivery_list.php?tab=ongoing';</script>";
        }
    }
}

// --- 5. FETCH DATA FOR TABS ---
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// Ambil Data Unik "Item Name / Card Type" untuk Datalist Dropdown
$card_types = $conn->query("SELECT DISTINCT item_name FROM deliveries WHERE item_name IS NOT NULL AND item_name != '' ORDER BY item_name ASC");

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

// Helper UI Tailwind untuk Tab Model Pill
$tab_btn_active = "flex items-center gap-2 py-2.5 px-5 rounded-xl font-bold text-sm transition-all duration-300 bg-white dark:bg-indigo-600 shadow-sm text-indigo-600 dark:text-white shrink-0";
$tab_btn_inactive = "flex items-center gap-2 py-2.5 px-5 rounded-xl font-semibold text-sm transition-all duration-300 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50 shrink-0";

$page_title = "Delivery Management";
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .modern-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .modern-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .modern-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .modern-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
    
    /* Global CSS HACK: Menghilangkan panah bawaan browser untuk tag <input list="..."> 
       agar tidak bentrok dengan icon kustom Tailwind.
    */
    input[list]::-webkit-calendar-picker-indicator {
        display: none !important;
        opacity: 0 !important;
    }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 flex items-center justify-center text-xl shadow-inner">
                    <i class="ph-bold ph-truck"></i>
                </div>
                Delivery Hub
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Sistem manajemen logistik terpadu (IT & HR Collaboration).</p>
        </div>
    </div>

    <div class="inline-flex bg-slate-200/50 dark:bg-slate-800/50 p-1.5 rounded-2xl shadow-inner backdrop-blur-sm overflow-x-auto max-w-full modern-scrollbar">
        <button onclick="switchTab('dashboard')" id="btn-tab-dashboard" class="<?= $active_tab=='dashboard'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-bold ph-squares-four text-lg"></i> Dashboard
        </button>
        <button onclick="switchTab('submit')" id="btn-tab-submit" class="<?= $active_tab=='submit'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-bold ph-paper-plane-tilt text-lg"></i> Submit (IT)
        </button>
        <button onclick="switchTab('process')" id="btn-tab-process" class="<?= $active_tab=='process'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-bold ph-hourglass-high text-lg"></i> HR Process 
            <span class="bg-rose-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-sm"><?= $data_waiting->num_rows ?></span>
        </button>
        <button onclick="switchTab('ongoing')" id="btn-tab-ongoing" class="<?= $active_tab=='ongoing'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-bold ph-truck text-lg"></i> On Going 
            <span class="bg-amber-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-sm"><?= $data_ongoing->num_rows ?></span>
        </button>
        <button onclick="switchTab('complete')" id="btn-tab-complete" class="<?= $active_tab=='complete'?$tab_btn_active:$tab_btn_inactive ?>">
            <i class="ph-bold ph-check-circle text-lg"></i> Complete
        </button>
    </div>

    <div id="content-dashboard" class="tab-content <?= $active_tab=='dashboard'?'block':'hidden' ?> transition-opacity duration-300">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
            <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-3xl shadow-lg shadow-indigo-500/20 p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-indigo-100 font-bold uppercase tracking-widest text-[10px] mb-1">Total Data</p>
                        <h3 class="text-4xl font-black tracking-tight"><?= $data_waiting->num_rows + $data_ongoing->num_rows + $data_complete->num_rows ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shadow-inner border border-white/20">
                        <i class="ph-fill ph-package text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-rose-500 to-red-600 rounded-3xl shadow-lg shadow-rose-500/20 p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-rose-100 font-bold uppercase tracking-widest text-[10px] mb-1">Waiting HR</p>
                        <h3 class="text-4xl font-black tracking-tight"><?= $data_waiting->num_rows ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shadow-inner border border-white/20">
                        <i class="ph-fill ph-hourglass-high text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-amber-500 to-orange-500 rounded-3xl shadow-lg shadow-amber-500/20 p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-amber-100 font-bold uppercase tracking-widest text-[10px] mb-1">On Going</p>
                        <h3 class="text-4xl font-black tracking-tight"><?= $data_ongoing->num_rows ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shadow-inner border border-white/20">
                        <i class="ph-fill ph-truck text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-emerald-500 to-teal-500 rounded-3xl shadow-lg shadow-emerald-500/20 p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-emerald-100 font-bold uppercase tracking-widest text-[10px] mb-1">Completed</p>
                        <h3 class="text-4xl font-black tracking-tight"><?= $data_complete->num_rows ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shadow-inner border border-white/20">
                        <i class="ph-fill ph-check-circle text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm p-10 text-center text-slate-500 dark:text-slate-400">
            <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mx-auto mb-4 border border-slate-100 dark:border-slate-700">
                <i class="ph-fill ph-chart-polar text-5xl text-slate-300 dark:text-slate-600"></i>
            </div>
            <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg">Welcome to Delivery Hub</h4>
            <p class="text-sm mt-1 font-medium max-w-md mx-auto">Gunakan navigasi tab di atas untuk mengelola alur pengiriman dari Request IT hingga proses logistik HR.</p>
        </div>
    </div>

    <div id="content-submit" class="tab-content <?= $active_tab=='submit'?'block':'hidden' ?> transition-opacity duration-300">
        
        <?php if($is_it): ?>
        <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden mb-8">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors" id="toggleSubmitFormBtn">
                <h3 class="font-black text-indigo-600 dark:text-indigo-400 text-sm uppercase tracking-widest flex items-center gap-2">
                    <i class="ph-fill ph-paper-plane-tilt text-lg"></i> Form Submit Delivery (IT Role)
                </h3>
                <div class="w-8 h-8 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-500 transition-transform">
                    <i id="toggleSubmitFormIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
                </div>
            </div>

            <div id="submitFormBody" class="p-6 block transition-all duration-300">
                <form method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-widest">Client Name <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <select name="client_id" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all shadow-inner" required>
                                    <option value="">- Pilih Client Database -</option>
                                    <?php $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-widest">Item / Card Type <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <i class="ph-bold ph-sim-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                                
                                <input list="card_options" name="card_type" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner placeholder-slate-400 cursor-pointer" placeholder="Ketik atau pilih list..." required autocomplete="off">
                                
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none transition-transform group-focus-within:-rotate-180"></i>
                                
                                <datalist id="card_options">
                                    <?php if($card_types && $card_types->num_rows > 0): ?>
                                        <?php while($ct = $card_types->fetch_assoc()): ?>
                                            <option value="<?= htmlspecialchars($ct['item_name']) ?>">
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </datalist>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-widest">Quantity <span class="text-rose-500">*</span></label>
                            <input type="number" name="qty" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-center focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" required value="1" min="1">
                        </div>

                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-widest">Data Package (Optional)</label>
                            <div class="relative group">
                                <i class="ph-bold ph-database absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                                <input type="text" name="data_package" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner placeholder-slate-400" placeholder="e.g. 10GB / Month">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-widest">Ref Invoice No</label>
                            <input type="text" name="invoice_no" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-mono font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner placeholder-slate-400" placeholder="INV-...">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-widest">Ref PO / DO No</label>
                            <div class="flex gap-2">
                                <input type="text" name="po_no" class="w-1/2 px-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner placeholder-slate-400" placeholder="PO...">
                                <input type="text" name="do_no" class="w-1/2 px-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner placeholder-slate-400" placeholder="DO...">
                            </div>
                        </div>

                        <div class="lg:col-span-4 p-5 rounded-2xl bg-indigo-50/50 dark:bg-indigo-500/5 border border-indigo-100 dark:border-indigo-500/10">
                            <h4 class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-4"><i class="ph-bold ph-paperclip"></i> Upload Dokumen Pendukung (PDF)</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Invoice File</label>
                                    <input type="file" name="invoice_file" accept=".pdf" class="w-full block text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-white file:text-indigo-700 hover:file:bg-indigo-50 dark:file:bg-slate-800 dark:file:text-indigo-400 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 cursor-pointer shadow-sm transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">PO File</label>
                                    <input type="file" name="po_file" accept=".pdf" class="w-full block text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-white file:text-indigo-700 hover:file:bg-indigo-50 dark:file:bg-slate-800 dark:file:text-indigo-400 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 cursor-pointer shadow-sm transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">DO File</label>
                                    <input type="file" name="do_file" accept=".pdf" class="w-full block text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-white file:text-indigo-700 hover:file:bg-indigo-50 dark:file:bg-slate-800 dark:file:text-indigo-400 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 cursor-pointer shadow-sm transition-all">
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" name="submit_it_delivery" class="px-8 py-3 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30 active:scale-95 flex items-center gap-2">
                            <i class="ph-bold ph-paper-plane-right text-lg"></i> Submit to HR
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
            <div class="bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 p-5 rounded-2xl border border-rose-200 dark:border-rose-500/20 text-sm font-bold flex items-center gap-3 mb-8 shadow-sm">
                <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-500/20 flex items-center justify-center shrink-0"><i class="ph-fill ph-warning-circle text-xl"></i></div>
                <div>Akses Ditolak. Form submit ini khusus untuk divisi IT.</div>
            </div>
        <?php endif; ?>

        <h4 class="font-black text-slate-800 dark:text-white text-base mb-4 flex items-center gap-2"><i class="ph-fill ph-clock-countdown text-rose-500"></i> Submissions Waiting for HR</h4>
        <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto modern-scrollbar w-full">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                        <tr>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider">Client</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider">Item Details</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider">Docs</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                        <?php if($data_waiting->num_rows > 0): $data_waiting->data_seek(0); while($row = $data_waiting->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            <td class="px-6 py-4 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars($row['client_name']) ?></div>
                            </td>
                            <td class="px-6 py-4 align-middle">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400 text-sm mb-0.5">
                                    <?= htmlspecialchars($row['item_name']) ?> 
                                    <span class="inline-flex items-center justify-center bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-md font-black ml-2 text-[10px]">x<?= $row['qty'] ?></span>
                                </div>
                                <div class="text-xs text-slate-500 font-medium flex items-center gap-1.5"><i class="ph-fill ph-database text-slate-400"></i> Pkg: <?= htmlspecialchars($row['data_package']) ?></div>
                            </td>
                            <td class="px-6 py-4 align-middle text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <?php if($row['invoice_file']): ?><span class="px-2.5 py-1 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20 rounded-lg text-[9px] font-black tracking-widest shadow-sm" title="Invoice">INV</span><?php endif; ?>
                                    <?php if($row['po_file']): ?><span class="px-2.5 py-1 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-500/20 rounded-lg text-[9px] font-black tracking-widest shadow-sm" title="Purchase Order">PO</span><?php endif; ?>
                                    <?php if($row['do_file']): ?><span class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20 rounded-lg text-[9px] font-black tracking-widest shadow-sm" title="Delivery Order">DO</span><?php endif; ?>
                                    <?php if(!$row['invoice_file'] && !$row['po_file'] && !$row['do_file']) echo '<span class="text-slate-400 italic text-xs font-medium">- No Docs -</span>'; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-middle text-center">
                                <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20 text-[10px] font-black uppercase tracking-widest shadow-sm w-32">
                                    <i class="ph-bold ph-hourglass-high animate-pulse text-sm"></i> Waiting HR
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-check-circle text-3xl text-emerald-500"></i>
                                    </div>
                                    <p class="text-sm font-bold text-slate-600 dark:text-slate-300">All caught up!</p>
                                    <p class="text-xs mt-1">Tidak ada data yang menunggu diproses.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="content-process" class="tab-content <?= $active_tab=='process'?'block':'hidden' ?> transition-opacity duration-300">
        
        <?php if(!$is_hr): ?>
            <div class="bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 p-5 rounded-2xl border border-rose-200 dark:border-rose-500/20 text-sm font-bold flex items-center gap-3 mb-6 shadow-sm">
                <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-500/20 flex items-center justify-center shrink-0"><i class="ph-fill ph-lock-key text-xl"></i></div>
                <div>Akses Terbatas. Hanya divisi HR yang memiliki izin memproses logistik pengiriman.</div>
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto modern-scrollbar w-full">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/30">
                        <tr>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-widest min-w-[250px]">Submission Details</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Package Info</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Ref Docs</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest whitespace-nowrap w-48">Action (2 Steps)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                        <?php if($data_waiting->num_rows > 0): $data_waiting->data_seek(0); while($row = $data_waiting->fetch_assoc()): ?>
                        <?php 
                            // Pengecekan step 1 selesai
                            $is_info_ready = (!empty($row['address']) && !empty($row['receiver_name']) && !empty($row['courier_name']));
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-6 align-middle">
                                <div class="font-black text-slate-800 dark:text-slate-200 text-sm mb-1.5 uppercase tracking-wide"><?= htmlspecialchars($row['client_name']) ?></div>
                                <div class="text-[10px] text-slate-500 font-bold uppercase tracking-widest flex items-center gap-1.5 mb-2">
                                    <i class="ph-bold ph-calendar-blank text-slate-400"></i> <?= strtoupper(date('d MAR Y', strtotime($row['delivery_date']))) ?>
                                </div>
                                
                                <?php if($is_info_ready): ?>
                                    <div class="mt-3 p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-700 transition-colors">
                                        <div class="font-bold text-slate-700 dark:text-slate-300 text-xs flex items-center gap-1.5 mb-1">
                                            <i class="ph-fill ph-user-circle text-slate-400"></i> 
                                            <?= htmlspecialchars($row['receiver_name']) ?> 
                                            <span class="text-slate-500 font-medium text-[10px]">(<?= htmlspecialchars($row['pic_phone']) ?>)</span>
                                        </div>
                                        <div class="text-slate-500 text-[10px] leading-snug line-clamp-2" title="<?= htmlspecialchars($row['address']) ?>">
                                            <i class="ph-fill ph-map-pin mr-1 text-slate-400"></i><?= htmlspecialchars($row['address']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-6 py-6 align-middle">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400 text-sm mb-2.5">
                                    <?= htmlspecialchars($row['item_name']) ?> 
                                </div>
                                <div class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-[10px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-2.5 shadow-sm">
                                    QTY: <?= $row['qty'] ?>
                                </div>
                                <?php if(!empty($row['data_package'])): ?>
                                    <div class="text-xs text-slate-500 font-medium flex items-center gap-1.5">
                                        <i class="ph-fill ph-database text-slate-400 text-sm"></i> <?= htmlspecialchars($row['data_package']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-6 align-middle text-center">
                                <div class="flex flex-col gap-2 items-center">
                                    <?php if($row['invoice_no']) echo "<span class='inline-flex px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-[10px] font-mono font-bold text-slate-700 dark:text-slate-300 shadow-sm w-36 justify-center'>INV: {$row['invoice_no']}</span>"; ?>
                                    <?php if($row['po_no']) echo "<span class='inline-flex px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-[10px] font-mono font-bold text-slate-700 dark:text-slate-300 shadow-sm w-36 justify-center'>PO: {$row['po_no']}</span>"; ?>
                                    <?php if($row['do_no']) echo "<span class='inline-flex px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-[10px] font-mono font-bold text-slate-700 dark:text-slate-300 shadow-sm w-36 justify-center'>DO: {$row['do_no']}</span>"; ?>
                                    <?php if(!$row['invoice_no'] && !$row['po_no'] && !$row['do_no']) echo '<span class="text-slate-400 italic text-xs font-medium">- No Ref -</span>'; ?>
                                </div>
                            </td>

                            <td class="px-6 py-6 align-middle bg-slate-50/50 dark:bg-slate-900/30">
                                <?php if($is_hr): ?>
                                    <div class="flex flex-col gap-3">
                                        <?php $jsonRow = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                        
                                        <button onclick='openProcessInfoModal(<?= $jsonRow ?>)' class="w-full <?= $is_info_ready ? 'bg-white border-slate-200 text-slate-600 hover:border-amber-400 hover:text-amber-600 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 shadow-sm' : 'bg-[#EAA13A] hover:bg-[#D98E2A] text-white border-transparent shadow-md shadow-amber-500/20' ?> border font-bold py-2.5 px-4 rounded-xl transition-all text-[11px] uppercase tracking-wide flex items-center justify-start gap-3 active:scale-95">
                                            <div class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 <?= $is_info_ready ? 'bg-emerald-500 text-white' : 'bg-white/30 text-white' ?>">
                                                <?= $is_info_ready ? '<i class="ph-bold ph-check"></i>' : '1' ?>
                                            </div>
                                            <?= $is_info_ready ? 'Edit Alamat' : 'Isi Alamat' ?>
                                        </button>
                                        
                                        <?php if($is_info_ready): ?>
                                            <button onclick="openResiModal(<?= $row['id'] ?>)" class="w-full bg-slate-50 hover:bg-blue-600 hover:text-white text-slate-600 border border-slate-200 hover:border-transparent dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-blue-600 font-bold py-2.5 px-4 rounded-xl shadow-sm transition-all text-[11px] uppercase tracking-wide flex items-center justify-start gap-3 active:scale-95 group">
                                                <div class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 group-hover:bg-white/30 group-hover:text-white flex items-center justify-center text-[10px] font-black shrink-0 transition-colors">2</div>
                                                Input Resi
                                            </button>
                                        <?php else: ?>
                                            <button disabled class="w-full bg-slate-50 dark:bg-slate-800/50 text-slate-400 dark:text-slate-600 border border-slate-100 dark:border-slate-700 font-bold py-2.5 px-4 rounded-xl text-[11px] uppercase tracking-wide flex items-center justify-start gap-3 cursor-not-allowed">
                                                <div class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-400 flex items-center justify-center text-[10px] font-black shrink-0">2</div>
                                                Input Resi
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-center text-slate-400 font-medium text-xs shadow-sm">
                                        <i class="ph-fill ph-lock-key text-lg mb-1 block"></i> Restricted
                                    </div>
                                <?php endif; ?>
                            </td>

                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-check-circle text-4xl text-emerald-500"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Queue Empty</h4>
                                    <p class="text-sm font-medium">Tidak ada antrean request pengiriman dari IT yang perlu diproses.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="content-ongoing" class="tab-content <?= $active_tab=='ongoing'?'block':'hidden' ?> transition-opacity duration-300">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto modern-scrollbar w-full pb-10">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                        <tr>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider min-w-[250px]">Client & Destination</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Package Details</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Courier & Tracking</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Verification</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50 text-sm">
                        <?php if($data_ongoing->num_rows > 0): $data_ongoing->data_seek(0); while($row = $data_ongoing->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-2"><?= htmlspecialchars($row['client_name']) ?></div>
                                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-700">
                                    <div class="font-bold text-slate-600 dark:text-slate-300 text-xs mb-1 flex items-center gap-1.5"><i class="ph-fill ph-user-circle text-slate-400"></i> <?= htmlspecialchars($row['receiver_name']) ?> <span class="font-mono text-[10px] text-slate-400">(<?= htmlspecialchars($row['pic_phone']) ?>)</span></div>
                                    <div class="text-[11px] text-slate-500 font-medium leading-relaxed line-clamp-2" title="<?= htmlspecialchars($row['address']) ?>"><i class="ph-fill ph-map-pin text-slate-400 mr-1"></i><?= htmlspecialchars($row['address']) ?></div>
                                </div>
                            </td>
                            
                            <td class="px-6 py-5 align-top">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400 text-sm mb-1.5">
                                    <?= htmlspecialchars($row['item_name']) ?> 
                                    <span class="inline-flex items-center justify-center bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20 px-2 py-0.5 rounded-md font-black ml-2 text-[10px] shadow-sm">x<?= $row['qty'] ?></span>
                                </div>
                                <?php if(!empty($row['data_package'])): ?>
                                <div class="text-[11px] text-slate-500 font-medium flex items-center gap-1.5"><i class="ph-fill ph-database text-slate-400"></i> <?= htmlspecialchars($row['data_package']) ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-6 py-5 align-top">
                                <div class="flex flex-col gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20 text-[10px] font-black uppercase tracking-widest w-fit shadow-sm">
                                        <i class="ph-fill ph-truck text-amber-500"></i> <?= htmlspecialchars($row['courier_name']) ?>
                                    </span>
                                    <div class="font-mono font-bold text-slate-800 dark:text-white text-sm bg-slate-100 dark:bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 inline-block w-fit">
                                        <?= htmlspecialchars($row['tracking_number']) ?>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="px-6 py-5 align-middle text-center">
                                <button onclick="autoCheckTracking(<?= $row['id'] ?>, '<?= htmlspecialchars($row['tracking_number']) ?>', '<?= htmlspecialchars($row['courier_name']) ?>')" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-xl shadow-lg shadow-blue-600/20 transition-all active:scale-95 text-[11px] uppercase tracking-widest flex items-center justify-center gap-2" id="btn-track-<?= $row['id'] ?>">
                                    <i class="ph-bold ph-crosshair text-base"></i> Track Live
                                </button>
                                <p class="text-[9px] text-slate-400 mt-2.5 font-medium leading-tight">Sistem akan otomatis memindahkan<br>data jika status <strong class="text-emerald-500">Delivered</strong>.</p>
                            </td>

                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-truck text-4xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Clear Road</h4>
                                    <p class="text-sm font-medium">Tidak ada paket yang sedang dalam perjalanan.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="content-complete" class="tab-content <?= $active_tab=='complete'?'block':'hidden' ?> animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto modern-scrollbar w-full pb-10">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/30">
                        <tr>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-widest min-w-[200px]">Delivery Summary</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-widest min-w-[280px]">Client & Destination</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Item Detail</th>
                            <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                        <?php if($data_complete->num_rows > 0): $data_complete->data_seek(0); while($row = $data_complete->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-6 align-top">
                                <div class="font-mono font-bold text-slate-700 dark:text-slate-300 text-xs mb-1.5 uppercase tracking-widest"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 shadow-sm mb-2.5">
                                    <i class="ph-fill ph-truck text-slate-400 text-xs"></i> <?= htmlspecialchars($row['courier_name']) ?>
                                </div>
                                <div class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <i class="ph-fill ph-check-circle text-sm"></i> Delivered: <?= date('d M Y', strtotime($row['delivered_date'])) ?>
                                </div>
                            </td>
                            
                            <td class="px-6 py-6 align-top">
                                <div class="font-black text-slate-800 dark:text-slate-200 text-sm mb-2 uppercase tracking-wide"><?= htmlspecialchars($row['client_name']) ?></div>
                                <div class="font-bold text-slate-600 dark:text-slate-300 text-xs mb-1 flex items-center gap-1.5">
                                    <i class="ph-fill ph-user-circle text-slate-400 text-sm"></i> <?= htmlspecialchars($row['receiver_name']) ?> 
                                    <span class="font-mono text-[10px] text-slate-400 font-medium">(<?= htmlspecialchars($row['pic_phone']) ?>)</span>
                                </div>
                                <div class="text-[11px] text-slate-500 font-medium leading-relaxed line-clamp-2 max-w-[320px] flex items-start gap-1.5" title="<?= htmlspecialchars($row['address']) ?>">
                                    <i class="ph-fill ph-map-pin text-slate-400 mt-0.5 text-sm"></i> <span><?= htmlspecialchars($row['address']) ?></span>
                                </div>
                            </td>
                            
                            <td class="px-6 py-6 align-top">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400 text-sm mb-2.5">
                                    <?= htmlspecialchars($row['item_name']) ?> 
                                </div>
                                <div class="inline-flex items-center justify-center bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 px-2.5 py-0.5 rounded-md font-black text-[10px] shadow-sm">
                                    x<?= $row['qty'] ?>
                                </div>
                            </td>
                            
                            <td class="px-6 py-6 align-top text-center">
                                <span class="inline-flex items-center justify-center gap-1.5 px-4 py-1.5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 text-[10px] font-black uppercase tracking-widest shadow-sm">
                                    <i class="ph-bold ph-checks text-sm"></i> Completed
                                </span>
                            </td>

                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-archive-box text-4xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">No History Yet</h4>
                                    <p class="text-sm font-medium">Belum ada riwayat pengiriman yang selesai.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div id="processInfoModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('processInfoModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl w-full max-w-3xl transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
        <form method="POST" class="flex flex-col h-full">
            
            <div class="px-6 py-5 border-b border-amber-500/20 bg-amber-500 text-white flex justify-between items-center shrink-0">
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-map-pin-line text-xl"></i> Tahap 1: Lengkapi Alamat & Klien</h3>
                <button type="button" onclick="closeModal('processInfoModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto modern-scrollbar flex-1 bg-slate-50/30 dark:bg-slate-800/20">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="delivery_id" id="modal_info_id">
                    
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-1.5">Alamat Pengiriman Client <span class="text-rose-500">*</span></label>
                        <textarea name="address" id="modal_address" class="w-full px-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-amber-500/50 dark:text-white outline-none transition-all resize-none shadow-sm" rows="3" required placeholder="Masukkan alamat lengkap tujuan..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-1.5">PIC Name <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="pic_name" id="modal_pic_name" class="w-full pl-11 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-amber-500/50 dark:text-white outline-none transition-all shadow-sm" required placeholder="Nama penerima">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-1.5">PIC Phone <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="ph-bold ph-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="pic_phone" id="modal_pic_phone" class="w-full pl-11 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-amber-500/50 dark:text-white outline-none transition-all shadow-sm" required placeholder="Nomor telepon aktif">
                        </div>
                    </div>
                    
                    <div class="md:col-span-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-1.5">Delivery Method (Courier) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="ph-fill ph-moped absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <select name="courier_name" id="modal_courier" class="w-full pl-11 pr-10 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-amber-500/50 dark:text-white appearance-none outline-none transition-all shadow-sm" required>
                                <option value="">- Pilih Kurir -</option>
                                <option value="JNE">JNE</option><option value="J&T">J&T</option><option value="SICEPAT">SiCepat</option><option value="ANTERAJA">AnterAja</option><option value="GOJEK">Gojek / Grab</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3 bg-white dark:bg-[#24303F] shrink-0 rounded-b-3xl">
                <button type="button" onclick="closeModal('processInfoModal')" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="process_hr_info" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-amber-500 hover:bg-amber-600 transition-colors shadow-md shadow-amber-500/30 active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-floppy-disk text-lg"></i> Simpan Data Alamat
                </button>
            </div>
        </form>
    </div>
</div>

<div id="processResiModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('processResiModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl flex flex-col overflow-hidden">
        <form method="POST">
            
            <div class="px-6 py-5 border-b border-blue-500/20 bg-blue-600 text-white flex justify-between items-center shrink-0">
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-barcode text-xl"></i> Tahap 2: Input Resi</h3>
                <button type="button" onclick="closeModal('processResiModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6">
                <input type="hidden" name="delivery_id" id="modal_resi_id">
                
                <div class="bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 rounded-2xl p-5 mb-5 text-sm font-medium flex items-start gap-3 shadow-sm">
                    <i class="ph-fill ph-info text-2xl shrink-0 mt-0.5"></i>
                    <div>Silakan input nomor resi / tracking. Setelah disimpan, data ini akan otomatis dipindahkan ke tab <strong class="font-black tracking-widest uppercase">On Going</strong>.</div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-1.5">Tracking Number / Resi <span class="text-rose-500">*</span></label>
                    <div class="relative group">
                        <i class="ph-bold ph-barcode absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl group-focus-within:text-blue-500 transition-colors"></i>
                        <input type="text" name="tracking_number" class="w-full pl-12 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-base font-mono font-bold focus:ring-2 focus:ring-blue-500/50 text-blue-700 dark:text-blue-400 outline-none transition-all shadow-inner" required placeholder="Input resi disini...">
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3 bg-white dark:bg-[#24303F] shrink-0 rounded-b-3xl">
                <button type="button" onclick="closeModal('processResiModal')" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="process_hr_resi" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-md shadow-blue-600/30 active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-paper-plane-right text-lg"></i> Submit & Go
                </button>
            </div>
        </form>
    </div>
</div>

<div id="trackingModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('trackingModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
        
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0 bg-slate-50/50 dark:bg-slate-800/50 rounded-t-3xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 flex items-center justify-center text-xl shadow-inner border border-indigo-200 dark:border-indigo-500/30">
                    <i class="ph-fill ph-truck"></i>
                </div>
                <h3 class="text-lg font-extrabold text-slate-800 dark:text-white tracking-tight">Live Shipment Status</h3>
            </div>
            <button onclick="closeModal('trackingModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-200 hover:bg-rose-100 text-slate-500 hover:text-rose-600 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-rose-500/20 dark:hover:text-rose-400 transition-colors">
                <i class="ph-bold ph-x text-lg"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto modern-scrollbar flex-1 bg-white dark:bg-slate-800 rounded-b-3xl text-sm" id="trackingResult">
            </div>
    </div>
</div>

<script>
    function switchTab(tabId) {
        const url = new URL(window.location); url.searchParams.set('tab', tabId); window.history.pushState({}, '', url);
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('[id^="btn-tab-"]').forEach(el => {
            el.className = "flex items-center gap-2 py-2.5 px-5 rounded-xl font-semibold text-sm transition-all duration-300 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50 shrink-0";
        });
        document.getElementById('content-' + tabId).classList.remove('hidden');
        document.getElementById('content-' + tabId).classList.add('block');
        document.getElementById('btn-tab-' + tabId).className = "flex items-center gap-2 py-2.5 px-5 rounded-xl font-bold text-sm transition-all duration-300 bg-white dark:bg-indigo-600 shadow-sm text-indigo-600 dark:text-white shrink-0";
    }

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
    
    function openProcessInfoModal(data) {
        document.getElementById('modal_info_id').value = data.id;
        document.getElementById('modal_address').value = data.address || '';
        document.getElementById('modal_pic_name').value = data.receiver_name || '';
        document.getElementById('modal_pic_phone').value = data.pic_phone || '';
        document.getElementById('modal_courier').value = data.courier_name || '';
        openModal('processInfoModal');
    }

    function openResiModal(id) {
        document.getElementById('modal_resi_id').value = id;
        openModal('processResiModal');
    }

    function autoCheckTracking(id, resi, kurir) {
        let btn = document.getElementById('btn-track-'+id);
        let origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner-gap animate-spin text-lg"></i> Checking...';
        btn.disabled = true;

        openModal('trackingModal');
        document.getElementById('trackingResult').innerHTML = `
            <div class="flex flex-col items-center justify-center py-20 opacity-70">
                <div class="w-16 h-16 rounded-full bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center mb-4 border border-blue-100 dark:border-blue-500/20">
                    <i class="ph-bold ph-spinner-gap text-4xl animate-spin text-blue-500"></i>
                </div>
                <p class="font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest text-xs">Connecting to API Engine...</p>
            </div>`;

        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('trackingResult').innerHTML = data;
                btn.innerHTML = origHtml; btn.disabled = false;
                
                let upperData = data.toUpperCase();
                if (upperData.includes('DELIVERED') || upperData.includes('BERHASIL DIKIRIM')) {
                    const formData = new FormData();
                    formData.append('action', 'auto_complete_delivery');
                    formData.append('id', id);
                    fetch('', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(res => {
                        if(res.status == 'success') {
                            alert("Sistem mendeteksi paket telah terkirim! Data akan dipindahkan ke tab 'Complete'.");
                            location.reload(); 
                        }
                    });
                }
            })
            .catch(err => {
                document.getElementById('trackingResult').innerHTML = `
                    <div class="p-6 bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 rounded-3xl border border-rose-200 dark:border-rose-500/20 flex flex-col items-center justify-center text-center">
                        <i class="ph-fill ph-warning-circle text-5xl mb-3"></i>
                        <h4 class="font-black text-lg mb-1">Koneksi Gagal</h4>
                        <p class="text-sm font-medium opacity-80">Tidak dapat terhubung ke server kurir saat ini. Silakan coba lagi nanti.</p>
                    </div>`;
                btn.innerHTML = origHtml; btn.disabled = false;
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('toggleSubmitFormBtn');
        const body = document.getElementById('submitFormBody');
        const icon = document.getElementById('toggleSubmitFormIcon');

        if(btn && body && icon) {
            btn.addEventListener('click', () => {
                if (body.classList.contains('hidden')) {
                    body.classList.remove('hidden');
                    setTimeout(() => body.style.opacity = '1', 10); 
                    icon.classList.replace('ph-caret-down', 'ph-caret-up');
                } else {
                    body.classList.add('hidden');
                    body.style.opacity = '0';
                    icon.classList.replace('ph-caret-up', 'ph-caret-down');
                }
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>