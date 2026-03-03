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

// --- 3. LOGIKA EXPORT EXCEL (UPDATED: 1 BARIS = 1 ITEM) ---
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

$sql = "SELECT q.*, c.company_name, u.username 
        FROM quotations q 
        JOIN clients c ON q.client_id = c.id 
        JOIN users u ON q.created_by_user_id = u.id 
        WHERE $where
        ORDER BY q.created_at DESC";
$res = $conn->query($sql);

// Helper Mapping Status Tailwind
$status_styles = [
    'draft'       => 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300',
    'sent'        => 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-500/10 dark:text-sky-400',
    'po_received' => 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400',
    'invoiced'    => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400',
    'cancel'      => 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400'
];
$status_icons = [
    'draft'       => 'ph-file-dashed',
    'sent'        => 'ph-paper-plane-tilt',
    'po_received' => 'ph-file-earmark-check',
    'invoiced'    => 'ph-receipt',
    'cancel'      => 'ph-x-circle'
];
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
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Quotations</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Daftar penawaran harga, manajemen persetujuan, dan konversi ke PO/Invoice.</p>
        </div>
        <a href="quotation_form.php" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 active:scale-95 whitespace-nowrap">
            <i class="ph-bold ph-plus text-lg"></i> Create New
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/80 rounded-t-2xl" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-base"></i> Filter Data & Export
            </h3>
            <i id="filterIcon" class="ph-bold ph-caret-up text-slate-400 transition-transform duration-300"></i>
        </div>
        
        <div id="filterBody" class="p-5 block transition-all duration-300">
            <form method="GET" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 items-end">
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">No Quotation</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="search" class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="e.g. QUO-..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Perusahaan Klien</label>
                        <div class="relative">
                            <select name="client_id" class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">- Semua Client -</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Status</label>
                        <div class="relative">
                            <select name="status" class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">- Semua Status -</option>
                                <option value="draft" <?= $f_status=='draft'?'selected':'' ?>>Draft</option>
                                <option value="sent" <?= $f_status=='sent'?'selected':'' ?>>Sent</option>
                                <option value="po_received" <?= $f_status=='po_received'?'selected':'' ?>>PO Received</option>
                                <option value="invoiced" <?= $f_status=='invoiced'?'selected':'' ?>>Invoiced</option>
                                <option value="cancel" <?= $f_status=='cancel'?'selected':'' ?>>Cancelled</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Rentang Tanggal</label>
                        <div class="flex items-center gap-2">
                            <input type="date" name="start_date" class="w-full px-2 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" value="<?= $f_start ?>">
                            <span class="text-slate-400">-</span>
                            <input type="date" name="end_date" class="w-full px-2 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" value="<?= $f_end ?>">
                        </div>
                    </div>

                    <div class="flex gap-2 xl:col-span-1">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold py-2 px-3 rounded-xl transition-colors text-xs shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                            <i class="ph-bold ph-funnel text-sm"></i> Filter
                        </button>
                        <?php if(!empty($search) || !empty($f_client) || !empty($f_status) || !empty($f_start)): ?>
                            <a href="quotation_list.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2 px-3 rounded-xl transition-colors text-xs text-center border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-sm"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 flex justify-end">
                <form method="POST">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($f_client) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($f_status) ?>">
                    <input type="hidden" name="start_date" value="<?= htmlspecialchars($f_start) ?>">
                    <input type="hidden" name="end_date" value="<?= htmlspecialchars($f_end) ?>">
                    <button type="submit" name="export_excel" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 px-5 rounded-xl transition-colors text-xs shadow-sm shadow-emerald-500/30 active:scale-95 flex items-center justify-center gap-2">
                        <i class="ph-bold ph-file-csv text-base"></i> Export Item Details
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="overflow-x-auto custom-scrollbar w-full pb-32"> <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                    <tr>
                        <th class="px-5 py-3.5">Quotation Info</th>
                        <th class="px-5 py-3.5">Client Details</th>
                        <th class="px-5 py-3.5">Package & Items</th>
                        <th class="px-5 py-3.5 text-right">Amount</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
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
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-5 py-4 align-top">
                                <div class="font-mono font-extrabold text-indigo-600 dark:text-indigo-400 text-xs">
                                    <?= htmlspecialchars($row['quotation_no']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 font-medium flex items-center gap-1">
                                    <i class="ph-fill ph-calendar-blank"></i> <?= date('d M Y', strtotime($row['quotation_date'])) ?>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs truncate max-w-[200px]" title="<?= htmlspecialchars($row['company_name']) ?>">
                                    <?= htmlspecialchars($row['company_name']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1 font-medium">
                                    <i class="ph-fill ph-user-circle"></i> <?= htmlspecialchars($row['username']) ?>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="flex flex-wrap gap-1 mb-1.5 max-w-[200px]">
                                    <?php if(!empty($cardList)): ?>
                                        <?php foreach($cardList as $ctype): ?>
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">
                                                <?= htmlspecialchars($ctype) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[10px] italic">-</span>
                                    <?php endif; ?>
                                </div>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-500 dark:text-indigo-400">
                                    <i class="ph-fill ph-cube"></i> <?= $countItem ?> Items
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top text-right">
                                <span class="text-[10px] font-bold text-slate-400 mr-1"><?= $row['currency'] ?></span>
                                <span class="font-black text-slate-800 dark:text-slate-200 text-sm">
                                    <?= number_format($total, 0, ',', '.') ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg border text-[9px] font-black uppercase tracking-widest w-28 <?= $sStyle ?>">
                                    <i class="ph-fill <?= $sIcon ?> text-[11px]"></i> <?= str_replace('_', ' ', $st) ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top text-center relative">
                                <button onclick="toggleActionMenu(<?= $row['id'] ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-all shadow-sm active:scale-95 focus:outline-none">
                                    <i class="ph-bold ph-dots-three-vertical text-lg"></i>
                                </button>

                                <div id="action-menu-<?= $row['id'] ?>" class="hidden absolute right-10 top-4 w-44 bg-white dark:bg-[#24303F] rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden text-left origin-top-right">
                                    <div class="py-1">
                                        <a href="quotation_print.php?id=<?= $row['id'] ?>" target="_blank" class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-printer text-sm"></i> Print PDF
                                        </a>
                                        
                                        <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>

                                        <?php if($st == 'draft'): ?>
                                            <a href="quotation_form.php?edit_id=<?= $row['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-pencil-simple text-sm"></i> Edit Quote
                                            </a>
                                            <a href="?status_id=<?= $row['id'] ?>&st=sent" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-paper-plane-tilt text-sm"></i> Mark as Sent
                                            </a>
                                            <button onclick="openPOModal(<?= $row['id'] ?>, '<?= $row['quotation_no'] ?>')" class="w-full text-left flex items-center gap-2 px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-file-earmark-check text-sm"></i> Process to PO
                                            </button>
                                            <a href="?status_id=<?= $row['id'] ?>&st=cancel" onclick="return confirm('Batalkan Quotation ini?')" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-x-circle text-sm"></i> Cancel Quote
                                            </a>
                                            <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                            <a href="?delete_id=<?= $row['id'] ?>" onclick="return confirm('Hapus permanen?')" class="flex items-center gap-2 px-4 py-2 text-xs font-black text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                                                <i class="ph-fill ph-trash text-sm"></i> Delete
                                            </a>
                                        
                                        <?php elseif(in_array($st, ['sent', 'po_received', 'invoiced'])): ?>
                                            <a href="?status_id=<?= $row['id'] ?>&st=draft" onclick="return confirm('Kembalikan status ke DRAFT agar bisa diedit?')" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-arrow-counterclockwise text-sm"></i> Revert to Draft
                                            </a>
                                            <?php if($st == 'sent'): ?>
                                                <button onclick="openPOModal(<?= $row['id'] ?>, '<?= $row['quotation_no'] ?>')" class="w-full text-left flex items-center gap-2 px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                    <i class="ph-bold ph-file-earmark-check text-sm"></i> Process to PO
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if($st == 'cancel'): ?>
                                            <a href="?status_id=<?= $row['id'] ?>&st=draft" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-arrow-counterclockwise text-sm"></i> Revert to Draft
                                            </a>
                                            <a href="?delete_id=<?= $row['id'] ?>" onclick="return confirm('Hapus permanen?')" class="flex items-center gap-2 px-4 py-2 text-xs font-black text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                                                <i class="ph-fill ph-trash text-sm"></i> Delete
                                            </a>
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
                                    <div class="w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-file-text text-3xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mb-0.5">Tidak Ada Data</h4>
                                    <p class="text-[11px] font-medium">Quotation tidak ditemukan dengan filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($res->num_rows > 0): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Menampilkan total <?= $res->num_rows ?> quotation.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="poModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700 flex flex-col">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-4 border-b border-emerald-500 bg-emerald-500 rounded-t-2xl flex justify-between items-center text-white">
                <h3 class="text-sm font-bold flex items-center gap-2"><i class="ph-bold ph-file-earmark-check text-lg"></i> Process to PO Client</h3>
                <button type="button" onclick="closeModal('poModal')" class="text-white/70 hover:text-white"><i class="ph-bold ph-x text-lg"></i></button>
            </div>
            
            <div class="p-6">
                <input type="hidden" name="quotation_id" id="modal_quotation_id">
                
                <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-xl p-4 text-center mb-5">
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest font-bold">Mengkonversi Quotation</p>
                    <p id="modal_q_no" class="text-sm font-bold text-emerald-800 dark:text-emerald-400 font-mono mt-1"></p>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Client PO Number <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="ph-bold ph-text-t absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="po_number_client" class="w-full pl-9 pr-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-emerald-500 dark:text-white outline-none transition-all" required placeholder="Input nomor PO dari klien...">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Upload PO Document (PDF/Image) <span class="text-rose-500">*</span></label>
                        <input type="file" name="po_document" class="w-full block text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-500/10 dark:file:text-emerald-400 dark:hover:file:bg-emerald-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl" accept=".pdf,.jpg,.png,.jpeg" required>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex bg-slate-50/50 dark:bg-slate-800/50 rounded-b-2xl justify-end gap-2">
                <button type="button" onclick="closeModal('poModal')" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-xs">Batal</button>
                <button type="submit" name="process_po" class="px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition-colors shadow-md shadow-emerald-500/30 flex items-center justify-center gap-2">
                    <i class="ph-bold ph-check text-sm"></i> Proses PO
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- FILTER TOGGLE ---
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('filterToggleBtn');
        const body = document.getElementById('filterBody');
        const icon = document.getElementById('filterIcon');

        if(btn && body && icon) {
            btn.addEventListener('click', () => {
                body.classList.toggle('hidden');
                if (body.classList.contains('hidden')) {
                    icon.classList.replace('ph-caret-up', 'ph-caret-down');
                } else {
                    icon.classList.replace('ph-caret-down', 'ph-caret-up');
                }
            });
        }
    });

    // --- MODAL HANDLERS ---
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

    // --- ACTION MENU DROPDOWN LOGIC ---
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
</script>

<?php include 'includes/footer.php'; ?>