<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php'; 

// --- 2. INIT FILTER VARIABLES ---
$search    = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client  = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';
$f_status  = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
$f_start   = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : '';
$f_end     = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : '';

// Bangun Query WHERE
$where = "1=1";
if(!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND q.quotation_no LIKE '%$safe_search%'";
}
if(!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND q.client_id = $safe_client";
}
if(!empty($f_status)) {
    $safe_status = $conn->real_escape_string($f_status);
    $where .= " AND q.status = '$safe_status'";
}
if(!empty($f_start) && !empty($f_end)) {
    $where .= " AND q.quotation_date BETWEEN '$f_start' AND '$f_end'";
}

// --- 3. LOGIKA EXPORT EXCEL ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    $sqlEx = "SELECT q.quotation_no, q.quotation_date, q.currency, q.status,
                     c.company_name, c.pic_name, 
                     u.username,
                     qi.item_name, qi.description, qi.qty, qi.unit_price, qi.card_type
              FROM quotations q 
              JOIN clients c ON q.client_id = c.id 
              JOIN users u ON q.created_by_user_id = u.id 
              JOIN quotation_items qi ON q.id = qi.quotation_id
              WHERE $where 
              ORDER BY q.created_at DESC, qi.id ASC";
              
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Quotations_Detail_Items_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, array(
        'Quotation No', 'Date', 'Client', 'PIC', 'Item Name', 'Description', 
        'Qty', 'Unit Price', 'Line Amount', 'Card Type', 'Currency', 'Status', 'Created By'
    ));
    
    while($row = $resEx->fetch_assoc()) {
        $lineAmount = floatval($row['qty']) * floatval($row['unit_price']);
        $itemName = trim(preg_replace('/\s+/', ' ', $row['item_name']));
        $desc = trim(preg_replace('/\s+/', ' ', $row['description']));

        fputcsv($output, array(
            $row['quotation_no'], $row['quotation_date'], $row['company_name'], $row['pic_name'],
            $itemName, $desc, $row['qty'], $row['unit_price'], $lineAmount,
            $row['card_type'], $row['currency'], $row['status'], $row['username']
        ));
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "Quotations";
include 'includes/header.php';
include 'includes/sidebar.php';

$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// --- LOGIKA ACTION LAIN ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $cek = $conn->query("SELECT status FROM quotations WHERE id=$del_id")->fetch_assoc();
    if(in_array($cek['status'], ['po_received', 'invoiced'])) {
        echo "<script>alert('Gagal: Quotation sudah diproses!'); window.location='quotation_list.php';</script>";
    } else {
        $conn->query("DELETE FROM quotation_items WHERE quotation_id = $del_id");
        if ($conn->query("DELETE FROM quotations WHERE id = $del_id")) {
            echo "<script>alert('Quotation berhasil dihapus!'); window.location='quotation_list.php';</script>";
        }
    }
}
if (isset($_GET['status_id']) && isset($_GET['st'])) {
    $st_id = intval($_GET['status_id']);
    $st_val = $conn->real_escape_string($_GET['st']);
    $conn->query("UPDATE quotations SET status = '$st_val' WHERE id = $st_id");
    echo "<script>window.location='quotation_list.php';</script>";
}
if (isset($_POST['process_po'])) {
    $q_id = intval($_POST['quotation_id']);
    $po_no = $conn->real_escape_string($_POST['po_number_client']);
    $po_file = null;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['po_document']) && $_FILES['po_document']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['po_document']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'jpg', 'png', 'jpeg'])) {
            $fileName = 'PO_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['po_document']['tmp_name'], $uploadDir . $fileName)) {
                $po_file = $fileName;
            }
        }
    }

    if ($po_file) {
        $sql = "UPDATE quotations SET status='po_received', po_number_client='$po_no', po_file_client='$po_file' WHERE id=$q_id";
        if ($conn->query($sql)) {
            echo "<script>alert('Berhasil diproses ke PO Client!'); window.location='po_client_list.php';</script>";
        }
    } else {
        echo "<script>alert('Gagal upload dokumen PO. Pastikan format file PDF/JPG/PNG.');</script>";
    }
}

// --- QUERY STATISTIK DINAMIS ---
$sql_stats = "SELECT q.status, COUNT(*) as count FROM quotations q WHERE $where GROUP BY q.status";
$res_stats = $conn->query($sql_stats);

$stats = [
    'total' => 0, 'draft' => 0, 'sent' => 0, 'po_received' => 0, 'invoiced' => 0
];

if ($res_stats) {
    while($s = $res_stats->fetch_assoc()) {
        $stats['total'] += $s['count'];
        $st_key = strtolower($s['status']);
        if (isset($stats[$st_key])) {
            $stats[$st_key] += $s['count'];
        }
    }
}

// --- QUERY UTAMA ---
$sql = "SELECT q.*, c.company_name, u.username 
        FROM quotations q 
        JOIN clients c ON q.client_id = c.id 
        JOIN users u ON q.created_by_user_id = u.id 
        WHERE $where
        ORDER BY q.created_at DESC";
$res = $conn->query($sql);

// Helper Mapping Status Tailwind
$status_styles = [
    'draft'       => 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300',
    'sent'        => 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400',
    'po_received' => 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400',
    'invoiced'    => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
    'cancel'      => 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400'
];
$status_icons = [
    'draft'       => 'ph-file-dashed',
    'sent'        => 'ph-paper-plane-tilt animate-pulse',
    'po_received' => 'ph-file-earmark-check',
    'invoiced'    => 'ph-receipt',
    'cancel'      => 'ph-x-circle'
];
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
    .animate-spin-slow { animation: spin 3s linear infinite; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-clipboard-text"></i>
                </div>
                Quotations
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Daftar penawaran harga, manajemen persetujuan, dan konversi ke PO/Invoice.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='quotation_list.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh / Reset">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
            <a href="quotation_form.php" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-plus text-xl relative z-10"></i> 
                <span class="relative z-10">Create Quotation</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col justify-center transition-transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xl shrink-0"><i class="ph-fill ph-files"></i></div>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['total']) ?></h4>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Quotes</p>
        </div>
        
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col justify-center transition-transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xl shrink-0"><i class="ph-fill ph-file-dashed"></i></div>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['draft']) ?></h4>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Draft</p>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col justify-center transition-transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xl shrink-0"><i class="ph-fill ph-paper-plane-tilt"></i></div>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['sent']) ?></h4>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Sent to Client</p>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col justify-center transition-transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shrink-0"><i class="ph-fill ph-file-earmark-check"></i></div>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['po_received']) ?></h4>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">PO Received</p>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col justify-center transition-transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0"><i class="ph-fill ph-receipt"></i></div>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['invoiced']) ?></h4>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Invoiced</p>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-lg"></i> Filter Data & Export
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-5 items-end">
                    
                    <div class="xl:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">No Quotation</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner uppercase" placeholder="e.g. QUO-..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="xl:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Klien / Perusahaan</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <select name="client_id" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Klien</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Status</label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <select name="status" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua</option>
                                <option value="draft" <?= $f_status=='draft'?'selected':'' ?>>Draft</option>
                                <option value="sent" <?= $f_status=='sent'?'selected':'' ?>>Sent</option>
                                <option value="po_received" <?= $f_status=='po_received'?'selected':'' ?>>PO Received</option>
                                <option value="invoiced" <?= $f_status=='invoiced'?'selected':'' ?>>Invoiced</option>
                                <option value="cancel" <?= $f_status=='cancel'?'selected':'' ?>>Cancelled</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Periode Tanggal</label>
                        <div class="flex items-center gap-2">
                            <input type="date" name="start_date" class="w-full px-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= $f_start ?>">
                            <span class="text-slate-400">-</span>
                            <input type="date" name="end_date" class="w-full px-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= $f_end ?>">
                        </div>
                    </div>

                    <div class="xl:col-span-2 flex gap-2 h-[46px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-md shadow-slate-200 dark:shadow-indigo-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-funnel"></i> Filter
                        </button>
                        <button type="submit" formmethod="POST" name="export_excel" class="flex-none w-[46px] bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500 dark:hover:text-white border border-emerald-200 dark:border-emerald-500/20 transition-all rounded-xl active:scale-95 flex items-center justify-center" title="Export to Excel">
                            <i class="ph-bold ph-microsoft-excel-logo text-lg"></i>
                        </button>
                        <?php if(!empty($search) || !empty($f_client) || !empty($f_status) || !empty($f_start)): ?>
                            <a href="quotation_list.php" class="flex-none w-[46px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300 relative min-h-[400px]">
        <div class="overflow-x-auto modern-scrollbar w-full pb-32">
            <table class="w-full text-left border-collapse table-auto min-w-[900px]">
                <thead class="bg-slate-50/80 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Quotation Info</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider min-w-[200px]">Client Details</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Package & Items</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Amount</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if($res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <?php 
                            $qId = $row['id'];
                            $calc = $conn->query("SELECT SUM(qty * unit_price) as t, COUNT(*) as c FROM quotation_items WHERE quotation_id = $qId")->fetch_assoc();
                            $total = $calc['t'];
                            $countItem = $calc['c'];
                            
                            $cardList = [];
                            $resCard = $conn->query("SELECT DISTINCT card_type FROM quotation_items WHERE quotation_id = $qId");
                            while($rc = $resCard->fetch_assoc()) if(!empty($rc['card_type'])) $cardList[] = $rc['card_type'];

                            $st = strtolower($row['status']);
                            $sStyle = isset($status_styles[$st]) ? $status_styles[$st] : $status_styles['draft'];
                            $sIcon  = isset($status_icons[$st]) ? $status_icons[$st] : $status_icons['draft'];
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-mono font-bold text-xs border border-indigo-100 dark:border-indigo-500/20 mb-2">
                                    <i class="ph-bold ph-hash"></i>
                                    <?= htmlspecialchars($row['quotation_no']) ?>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1.5 whitespace-nowrap">
                                    <i class="ph-fill ph-calendar-blank"></i> 
                                    <?= date('d M Y', strtotime($row['quotation_date'])) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                    <?= htmlspecialchars($row['company_name']) ?>
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1A222C] shadow-sm">
                                    <i class="ph-fill ph-user-circle text-slate-400"></i>
                                    <span class="text-[10px] font-bold text-slate-600 dark:text-slate-300 whitespace-nowrap">Creator: <?= htmlspecialchars($row['username']) ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-1 rounded-lg border border-indigo-100 dark:border-indigo-500/20 whitespace-nowrap">
                                        <i class="ph-fill ph-cube"></i> <?= $countItem ?> Items
                                    </span>
                                    <?php if(!empty($cardList)): ?>
                                        <?php foreach($cardList as $ctype): ?>
                                            <span class="px-2 py-1 rounded-lg text-[9px] font-bold uppercase tracking-widest bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 whitespace-nowrap shadow-sm">
                                                <?= htmlspecialchars($ctype) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs italic">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-right whitespace-nowrap">
                                <div class="inline-flex flex-col items-end">
                                    <span class="font-black text-slate-800 dark:text-slate-200 text-sm tracking-wide">
                                        <span class="text-[10px] font-bold text-slate-400 mr-1"><?= $row['currency'] ?></span><?= number_format($total, 0, ',', '.') ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-center whitespace-nowrap">
                                <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl border text-[10px] font-black uppercase tracking-widest w-32 shadow-sm <?= $sStyle ?>">
                                    <i class="ph-fill <?= $sIcon ?> text-sm"></i> <?= str_replace('_', ' ', $st) ?>
                                </span>
                            </td>

                            <td class="px-6 py-5 align-middle text-center whitespace-nowrap relative">
                                <div class="relative inline-block text-left" data-dropdown>
                                    <button type="button" class="inline-flex justify-center items-center w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-400 dark:hover:text-indigo-400 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 dropdown-toggle-btn active:scale-95" aria-expanded="true" aria-haspopup="true">
                                        <i class="ph-bold ph-dots-three-vertical text-lg pointer-events-none"></i>
                                    </button>

                                    <div class="dropdown-menu hidden absolute right-8 top-0 w-48 bg-white dark:bg-[#24303F] rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden text-left origin-top-right transition-all divide-y divide-slate-50 dark:divide-slate-700/50">
                                        <div class="py-1">
                                            <a href="quotation_print.php?id=<?= $row['id'] ?>" target="_blank" class="group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400 transition-colors">
                                                <i class="ph-bold ph-printer text-base"></i> Print PDF
                                            </a>
                                        </div>

                                        <?php if($st == 'draft'): ?>
                                        <div class="py-1">
                                            <a href="quotation_form.php?edit_id=<?= $row['id'] ?>" class="group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10 dark:hover:text-amber-400 transition-colors">
                                                <i class="ph-bold ph-pencil-simple text-base"></i> Edit Quote
                                            </a>
                                            <a href="?status_id=<?= $row['id'] ?>&st=sent" class="group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-500/10 dark:hover:text-sky-400 transition-colors">
                                                <i class="ph-bold ph-paper-plane-tilt text-base"></i> Mark as Sent
                                            </a>
                                            <button type="button" onclick="openPOModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['quotation_no']) ?>')" class="w-full text-left group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400 transition-colors">
                                                <i class="ph-bold ph-file-earmark-check text-base"></i> Process to PO
                                            </button>
                                        </div>
                                        <div class="py-1 bg-slate-50/50 dark:bg-slate-800/30">
                                            <a href="?status_id=<?= $row['id'] ?>&st=cancel" onclick="return confirm('Batalkan Quotation ini?')" class="group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                                                <i class="ph-bold ph-x-circle text-base"></i> Cancel Quote
                                            </a>
                                            <a href="?delete_id=<?= $row['id'] ?>" onclick="return confirm('Hapus permanen? Data tidak bisa dikembalikan.')" class="group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:bg-rose-600 dark:hover:text-white transition-colors">
                                                <i class="ph-bold ph-trash text-base"></i> Delete
                                            </a>
                                        </div>
                                        
                                        <?php elseif(in_array($st, ['sent', 'po_received', 'invoiced'])): ?>
                                        <div class="py-1">
                                            <a href="?status_id=<?= $row['id'] ?>&st=draft" onclick="return confirm('Kembalikan status ke DRAFT agar bisa diedit?')" class="group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10 dark:hover:text-amber-400 transition-colors">
                                                <i class="ph-bold ph-arrow-counterclockwise text-base"></i> Revert to Draft
                                            </a>
                                            <?php if($st == 'sent'): ?>
                                                <button type="button" onclick="openPOModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['quotation_no']) ?>')" class="w-full text-left group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400 transition-colors">
                                                    <i class="ph-bold ph-file-earmark-check text-base"></i> Process to PO
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <?php elseif($st == 'cancel'): ?>
                                        <div class="py-1">
                                            <a href="?status_id=<?= $row['id'] ?>&st=draft" class="group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10 dark:hover:text-amber-400 transition-colors">
                                                <i class="ph-bold ph-arrow-counterclockwise text-base"></i> Revert to Draft
                                            </a>
                                            <a href="?delete_id=<?= $row['id'] ?>" onclick="return confirm('Hapus permanen? Data tidak bisa dikembalikan.')" class="group flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:bg-rose-600 dark:hover:text-white transition-colors">
                                                <i class="ph-bold ph-trash text-base"></i> Delete
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-clipboard-text text-5xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Quotation tidak ditemukan dengan filter pencarian saat ini.</p>
                                    <?php if(!empty($search) || !empty($f_client) || !empty($f_status) || !empty($f_start)): ?>
                                        <a href="quotation_list.php" class="mt-5 inline-flex items-center gap-2 bg-slate-800 dark:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors shadow-sm">
                                            <i class="ph-bold ph-arrows-counter-clockwise"></i> Reset Filter
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($res->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-center mt-[-80px] relative z-10">
            <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest bg-white dark:bg-[#1A222C] px-5 py-2 rounded-full border border-slate-100 dark:border-slate-700 shadow-sm">
                Menampilkan Total <span class="text-indigo-600 dark:text-indigo-400 font-black mx-1"><?= $res->num_rows ?></span> Quotation
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="poModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('poModal')"></div>
    
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-5 border-b border-emerald-500/20 bg-emerald-500 flex justify-between items-center text-white">
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-file-earmark-check text-xl"></i> Process to PO Client</h3>
                <button type="button" onclick="closeModal('poModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6">
                <input type="hidden" name="quotation_id" id="modal_quotation_id">
                
                <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-2xl p-4 text-center mb-6 shadow-sm">
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest font-bold">Mengkonversi Quotation</p>
                    <p id="modal_q_no" class="text-base font-black text-emerald-700 dark:text-emerald-400 font-mono mt-1"></p>
                </div>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-2">Client PO Number <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-hash absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-emerald-500 transition-colors"></i>
                            <input type="text" name="po_number_client" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" required placeholder="Input nomor PO dari klien...">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-2">Upload PO Document <span class="text-rose-500">*</span></label>
                        <input type="file" name="po_document" accept=".pdf,.jpg,.png,.jpeg" required class="w-full block text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:uppercase file:tracking-widest file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-500/10 dark:file:text-emerald-400 dark:hover:file:bg-emerald-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 transition-all">
                        <p class="text-[10px] font-medium text-slate-400 mt-2 italic flex items-center gap-1"><i class="ph-fill ph-info"></i> Format yang didukung: PDF, JPG, PNG.</p>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3">
                <button type="button" onclick="closeModal('poModal')" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-sm shadow-sm">Batal</button>
                <button type="submit" name="process_po" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition-colors shadow-md shadow-emerald-500/30 flex items-center justify-center gap-2 active:scale-95">
                    <i class="ph-bold ph-check text-lg"></i> Proses PO
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- FILTER COLLAPSE LOGIC ---
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('filterToggleBtn');
        const body = document.getElementById('filterBody');
        const icon = document.getElementById('filterIcon');

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

    // --- CUSTOM DROPDOWN MENU LOGIC ---
    document.addEventListener('click', function(e) {
        // Tutup semua dropdown
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if(!menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        });

        // Buka jika tombol dropdown di-klik
        const toggleBtn = e.target.closest('.dropdown-toggle-btn');
        if (toggleBtn) {
            e.stopPropagation(); 
            const wrapper = toggleBtn.closest('[data-dropdown]');
            const menu = wrapper.querySelector('.dropdown-menu');
            if(menu) {
                menu.classList.remove('hidden');
            }
        }
    });

    // --- CUSTOM MODAL HANDLERS ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.remove('hidden');
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

    function openPOModal(id, no) {
        document.getElementById('modal_quotation_id').value = id;
        document.getElementById('modal_q_no').innerText = no;
        openModal('poModal');
    }
</script>

<?php include 'includes/footer.php'; ?>