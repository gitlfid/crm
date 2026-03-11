<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. INIT FILTER VARIABLES ---
$search    = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client  = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';
$f_status  = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';

// Bangun Query WHERE Dasar (Hanya PO Received atau Invoiced)
$where = "q.status IN ('po_received', 'invoiced')";

// Filter Text (PO No, Quote No)
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (q.po_number_client LIKE '%$safe_search%' OR q.quotation_no LIKE '%$safe_search%')";
}

// Filter Client
if (!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND q.client_id = $safe_client";
}

// Filter Status
if (!empty($f_status)) {
    $safe_status = $conn->real_escape_string($f_status);
    $where .= " AND q.status = '$safe_status'";
}

// --- 3. LOGIKA EXPORT EXCEL (CSV) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    $sqlEx = "SELECT q.*, c.company_name, c.pic_name, u.username 
              FROM quotations q 
              JOIN clients c ON q.client_id = c.id 
              JOIN users u ON q.created_by_user_id = u.id 
              WHERE $where 
              ORDER BY q.created_at DESC";
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=PO_Client_Data_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, array('PO Number', 'Quote Ref', 'Date', 'Client', 'PIC', 'Total Amount', 'Currency', 'Status', 'Sales Person', 'PO File'));
    
    while($row = $resEx->fetch_assoc()) {
        $qId = $row['id'];
        // Hitung Total Amount PO
        $total = $conn->query("SELECT SUM(qty * unit_price) as t FROM quotation_items WHERE quotation_id = $qId")->fetch_assoc()['t'];
        
        fputcsv($output, array(
            $row['po_number_client'],
            $row['quotation_no'],
            $row['quotation_date'],
            $row['company_name'],
            $row['pic_name'],
            $total,
            $row['currency'],
            strtoupper($row['status']),
            $row['username'],
            $row['po_file_client'] ? 'Uploaded' : 'Pending'
        ));
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "PO From Client";
include 'includes/header.php';
include 'includes/sidebar.php';

// Ambil List Client untuk Filter
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// LOGIKA CANCEL PO
if (isset($_GET['cancel_id'])) {
    $q_id = intval($_GET['cancel_id']);
    $conn->query("UPDATE quotations SET status='cancel' WHERE id=$q_id");
    echo "<script>alert('PO dan Quotation berhasil dibatalkan!'); window.location='po_client_list.php';</script>";
}

// LOGIKA PROCESS TO INVOICE
if (isset($_GET['process_invoice_id'])) {
    $q_id = intval($_GET['process_invoice_id']);
    echo "<script>window.location='invoice_form.php?source_id=$q_id';</script>";
}

// LOGIKA UPLOAD PO DOC
if (isset($_POST['upload_po_doc'])) {
    $q_id = intval($_POST['quotation_id']);
    $uploadDir = __DIR__ . '/../uploads/';
    
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['po_file']) && $_FILES['po_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['po_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $fileName = 'PO_' . time() . '_' . $q_id . '.' . $ext;
            if (move_uploaded_file($_FILES['po_file']['tmp_name'], $uploadDir . $fileName)) {
                $sqlUp = "UPDATE quotations SET po_file_client = '$fileName' WHERE id = $q_id";
                if ($conn->query($sqlUp)) {
                    echo "<script>alert('Dokumen PO berhasil diupload!'); window.location='po_client_list.php';</script>";
                } else {
                    echo "<script>alert('Gagal update database.');</script>";
                }
            } else {
                echo "<script>alert('Gagal memindahkan file ke server.');</script>";
            }
        } else {
            echo "<script>alert('Format file tidak didukung. Harap gunakan PDF, JPG, atau PNG.');</script>";
        }
    } else {
        echo "<script>alert('Silakan pilih file terlebih dahulu.');</script>";
    }
}

// --- QUERY STATISTIK DINAMIS ---
$sql_stats = "SELECT q.status, COUNT(*) as count FROM quotations q WHERE $where GROUP BY q.status";
$res_stats = $conn->query($sql_stats);

$stats = ['total' => 0, 'po_received' => 0, 'invoiced' => 0];

if ($res_stats) {
    while($s = $res_stats->fetch_assoc()) {
        $stats['total'] += $s['count'];
        $st_key = strtolower($s['status']);
        if (isset($stats[$st_key])) {
            $stats[$st_key] += $s['count'];
        }
    }
}

// QUERY DATA TAMPILAN UTAMA
$sql = "SELECT q.*, c.company_name, u.username 
        FROM quotations q 
        JOIN clients c ON q.client_id = c.id 
        JOIN users u ON q.created_by_user_id = u.id 
        WHERE $where
        ORDER BY q.created_at DESC";
$res = $conn->query($sql);
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
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-blue-500/30">
                    <i class="ph-fill ph-file-arrow-down"></i>
                </div>
                Client Purchase Orders
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Daftar Purchase Order yang diterima dari klien dan siap diproses menjadi Invoice.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='po_client_list.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh / Reset">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1">
            <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-files"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Dokumen</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['total']) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-hourglass-high"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Pending Invoice</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['po_received']) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-receipt"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Sudah Invoiced</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['invoiced']) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-cyan-500 text-lg"></i> Filter Data & Export
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-12 gap-5 items-end">
                    
                    <div class="lg:col-span-4">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Pencarian</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-cyan-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Cari No PO / No Quote..." value="<?= htmlspecialchars($search ?? '') ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Klien / Perusahaan</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-cyan-500 transition-colors"></i>
                            <select name="client_id" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Klien</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Status Dokumen</label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-checks absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-cyan-500 transition-colors"></i>
                            <select name="status" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Status</option>
                                <option value="po_received" <?= $f_status=='po_received'?'selected':'' ?>>Pending Inv</option>
                                <option value="invoiced" <?= $f_status=='invoiced'?'selected':'' ?>>Invoiced</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-3 flex gap-2 h-[46px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-cyan-600 dark:hover:bg-cyan-700 text-white font-bold rounded-xl transition-all shadow-md shadow-slate-200 dark:shadow-cyan-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-funnel"></i> Filter
                        </button>
                        
                        <button type="submit" formmethod="POST" name="export_excel" class="flex-none w-[46px] bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500 dark:hover:text-white border border-emerald-200 dark:border-emerald-500/20 transition-all rounded-xl active:scale-95 flex items-center justify-center" title="Export to Excel">
                            <i class="ph-bold ph-microsoft-excel-logo text-lg"></i>
                        </button>

                        <?php if(!empty($search) || !empty($f_client) || !empty($f_status)): ?>
                            <a href="po_client_list.php" class="flex-none w-[46px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors duration-300 flex flex-col min-h-[400px]">
        
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/30">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Tampilkan</span>
                <select id="pageSize" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-cyan-500/50 outline-none cursor-pointer">
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

        <div class="overflow-x-auto modern-scrollbar flex-grow pb-24">
            <table class="w-full text-left border-collapse table-auto min-w-[1000px]">
                <thead class="bg-slate-50/80 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap w-[20%]">PO Details & Quote Ref</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider min-w-[200px]">Client Information</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-right text-[10px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap w-[15%]">Total Amount</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap w-[15%]">Document</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap w-[15%]">Status</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap w-[10%]">Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if($res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <?php 
                            // Hitung Total Amount per Baris (Supaya tabel lebih detail)
                            $qId = $row['id'];
                            $calc = $conn->query("SELECT SUM(qty * unit_price) as t FROM quotation_items WHERE quotation_id = $qId")->fetch_assoc();
                            $poTotal = $calc['t'] ?? 0;
                            $currency = $row['currency'];
                            $is_intl = ($currency !== 'IDR');
                            $formattedTotal = $is_intl ? number_format($poTotal, 2, '.', ',') : number_format($poTotal, 0, ',', '.');
                        ?>
                        <tr class="data-row hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle whitespace-nowrap">
                                <div class="font-mono font-black text-blue-600 dark:text-blue-400 text-[13px] mb-1 tracking-wide">
                                    <?= htmlspecialchars($row['po_number_client'] ?? 'N/A') ?>
                                </div>
                                <div class="flex items-center gap-1.5 text-[10px] text-slate-500 dark:text-slate-400 font-medium">
                                    <span>Ref:</span> 
                                    <span class="inline-block px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded font-mono font-bold text-[10px] border border-slate-200 dark:border-slate-600 shadow-sm">
                                        <?= htmlspecialchars($row['quotation_no'] ?? '') ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-1.5 leading-snug truncate max-w-[250px]" title="<?= htmlspecialchars($row['company_name'] ?? '') ?>">
                                    <?= htmlspecialchars($row['company_name'] ?? '') ?>
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1A222C] shadow-sm">
                                    <i class="ph-fill ph-user-circle text-slate-400"></i>
                                    <span class="text-[10px] font-bold text-slate-600 dark:text-slate-300 whitespace-nowrap">Sales: <?= htmlspecialchars($row['username'] ?? '') ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-right whitespace-nowrap">
                                <div class="flex flex-col items-end">
                                    <span class="font-black text-slate-800 dark:text-slate-200 text-sm tracking-wide">
                                        <span class="text-[10px] font-bold text-slate-400 mr-1"><?= $currency ?></span><?= $formattedTotal ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-center whitespace-nowrap">
                                <?php if($row['po_file_client']): ?>
                                    <a href="../uploads/<?= $row['po_file_client'] ?>" target="_blank" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:hover:bg-blue-500/20 transition-colors border border-blue-200 dark:border-blue-500/20 font-bold text-[10px] tracking-wide shadow-sm">
                                        <i class="ph-bold ph-file-pdf text-sm"></i> View Doc
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-400 dark:text-slate-500 italic text-[10px] flex items-center justify-center gap-1">
                                        <i class="ph-bold ph-file-dashed"></i> Not Uploaded
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-5 align-middle text-center whitespace-nowrap">
                                <?php if($row['status'] == 'po_received'): ?>
                                    <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl border border-amber-200 bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20 text-[10px] font-black uppercase tracking-widest w-32 shadow-sm">
                                        <i class="ph-fill ph-hourglass-high text-xs"></i> Pending Inv
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 text-[10px] font-black uppercase tracking-widest w-32 shadow-sm">
                                        <i class="ph-fill ph-check-circle text-xs"></i> Invoiced
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-5 align-middle text-center whitespace-nowrap relative">
                                <div class="relative inline-block text-left" data-dropdown>
                                    <button type="button" class="inline-flex justify-center items-center w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-cyan-600 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-400 dark:hover:text-cyan-400 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/50 dropdown-toggle-btn active:scale-95" aria-expanded="true" aria-haspopup="true">
                                        <i class="ph-bold ph-dots-three-vertical text-lg pointer-events-none"></i>
                                    </button>

                                    <div class="dropdown-menu hidden absolute right-8 top-0 w-48 bg-white dark:bg-[#24303F] rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden text-left origin-top-right transition-all divide-y divide-slate-50 dark:divide-slate-700/50">
                                        
                                        <div class="py-1">
                                            <a href="quotation_print.php?id=<?= $row['id'] ?>" target="_blank" class="group flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-eye text-base text-slate-400 group-hover:text-indigo-500"></i> View Quote
                                            </a>
                                            <button type="button" onclick="openUploadModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['po_number_client'] ?? '') ?>')" class="w-full text-left group flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-cloud-arrow-up text-base text-slate-400 group-hover:text-sky-500"></i> <?= $row['po_file_client'] ? 'Update PO Doc' : 'Upload PO Doc' ?>
                                            </button>
                                        </div>

                                        <?php if($row['status'] == 'po_received'): ?>
                                        <div class="py-1">
                                            <a href="?process_invoice_id=<?= $row['id'] ?>" class="group flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors">
                                                <i class="ph-bold ph-receipt text-base"></i> Process to Invoice
                                            </a>
                                        </div>
                                        <div class="py-1 bg-slate-50/50 dark:bg-slate-800/30">
                                            <a href="?cancel_id=<?= $row['id'] ?>" onclick="return confirm('PERINGATAN: Membatalkan PO ini juga akan membatalkan Quotation. Lanjutkan?')" class="group flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                                                <i class="ph-bold ph-x-circle text-base"></i> Cancel PO
                                            </a>
                                        </div>
                                        <?php else: ?>
                                        <div class="py-2 bg-slate-50/50 dark:bg-slate-800/30 px-4">
                                            <span class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-emerald-500 italic">
                                                <i class="ph-fill ph-check-circle text-base"></i> Already Invoiced
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="emptyRow">
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-file-arrow-down text-5xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Purchase Order Client tidak ditemukan dengan filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-between items-center absolute bottom-0 w-full z-10">
            <button id="btnPrev" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                &larr; Previous
            </button>
            
            <div id="pageNumbers" class="flex gap-1">
                </div>
            
            <button id="btnNext" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                Next &rarr;
            </button>
        </div>
    </div>
</div>

<div id="uploadPOModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('uploadPOModal')"></div>
    
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-5 border-b border-cyan-500/20 bg-cyan-500 flex justify-between items-center text-white">
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-cloud-arrow-up text-xl"></i> Upload PO Document</h3>
                <button type="button" onclick="closeModal('uploadPOModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6">
                <input type="hidden" name="quotation_id" id="modal_q_id">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-2">Client PO Number</label>
                        <input type="text" id="modal_po_no" class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-400 outline-none font-mono cursor-not-allowed" readonly>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-2">Select File (PDF/Image) <span class="text-rose-500">*</span></label>
                        <input type="file" name="po_file" class="w-full block text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:uppercase file:tracking-widest file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 dark:file:bg-cyan-500/10 dark:file:text-cyan-400 dark:hover:file:bg-cyan-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 transition-all" accept=".pdf,.jpg,.jpeg,.png" required>
                        <p class="text-[10px] font-medium text-slate-400 mt-2 italic flex items-center gap-1"><i class="ph-fill ph-info"></i> Maksimal ukuran file 5MB.</p>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex bg-slate-50/50 dark:bg-slate-800/50 justify-end gap-3 shrink-0">
                <button type="button" onclick="closeModal('uploadPOModal')" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-sm shadow-sm">Batal</button>
                <button type="submit" name="upload_po_doc" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-cyan-500 hover:bg-cyan-600 transition-colors shadow-md shadow-cyan-500/30 flex items-center justify-center gap-2 active:scale-95">
                    <i class="ph-bold ph-upload-simple text-lg"></i> Upload File
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- PAGINATION LOGIC (Vanilla JS) ---
    document.addEventListener('DOMContentLoaded', () => {
        const rows = Array.from(document.querySelectorAll('#tableBody tr.data-row'));
        const totalRows = rows.length;
        
        if(totalRows === 0) return; // Ignore if no data

        const pageSizeSelect = document.getElementById('pageSize');
        const paginationInfo = document.getElementById('paginationInfo');
        const btnPrev = document.getElementById('btnPrev');
        const btnNext = document.getElementById('btnNext');
        const pageNumbersContainer = document.getElementById('pageNumbers');

        let currentPage = 1;
        let rowsPerPage = parseInt(pageSizeSelect.value);

        function renderTable() {
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            rows.forEach((row, index) => {
                if (index >= start && index < end) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Update Info
            const currentEnd = end > totalRows ? totalRows : end;
            paginationInfo.innerHTML = `Menampilkan <span class="text-cyan-600 dark:text-cyan-400 font-black">${start + 1} - ${currentEnd}</span> dari <span class="font-black text-slate-800 dark:text-white">${totalRows}</span> data`;

            updatePaginationButtons();
        }

        function updatePaginationButtons() {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            
            btnPrev.disabled = currentPage === 1;
            btnNext.disabled = currentPage === totalPages;

            // Generate Page Numbers
            pageNumbersContainer.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                // Limit visible pages for massive lists (e.g. show 1 2 3 ... 10)
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    const pageBtn = document.createElement('button');
                    pageBtn.innerText = i;
                    if (i === currentPage) {
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-black text-white bg-cyan-500 shadow-sm shadow-cyan-500/30 flex items-center justify-center transition-all";
                    } else {
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700 transition-all flex items-center justify-center";
                        pageBtn.onclick = () => { currentPage = i; renderTable(); };
                    }
                    pageNumbersContainer.appendChild(pageBtn);
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    const dots = document.createElement('span');
                    dots.innerText = '...';
                    dots.className = "w-8 h-8 flex items-center justify-center text-slate-400 text-xs font-bold";
                    pageNumbersContainer.appendChild(dots);
                }
            }
        }

        pageSizeSelect.addEventListener('change', (e) => {
            rowsPerPage = parseInt(e.target.value);
            currentPage = 1;
            renderTable();
        });

        btnPrev.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; renderTable(); }
        });

        btnNext.addEventListener('click', () => {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            if (currentPage < totalPages) { currentPage++; renderTable(); }
        });

        // Initialize
        renderTable();
    });

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
    let currentOpenDropdown = null;
    function toggleActionMenu(id) {
        const menu = document.getElementById('action-menu-' + id);
        if (currentOpenDropdown && currentOpenDropdown !== menu) {
            currentOpenDropdown.classList.add('hidden');
        }
        menu.classList.toggle('hidden');
        currentOpenDropdown = menu.classList.contains('hidden') ? null : menu;
    }
    
    document.addEventListener('click', (e) => {
        if (currentOpenDropdown && !e.target.closest('td.relative')) {
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

    function openUploadModal(id, poNo) {
        document.getElementById('modal_q_id').value = id;
        document.getElementById('modal_po_no').value = poNo;
        openModal('uploadPOModal');
    }
</script>

<?php include 'includes/footer.php'; ?>