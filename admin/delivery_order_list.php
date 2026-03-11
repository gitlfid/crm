<?php
// --- 1. LOAD CONFIG ---
include '../config/functions.php';

// --- 2. LOGIKA DELETE ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    
    // Hapus Item Detail dulu (Child)
    $conn->query("DELETE FROM delivery_order_items WHERE delivery_order_id = $del_id");
    
    // Hapus Header DO (Parent)
    if ($conn->query("DELETE FROM delivery_orders WHERE id = $del_id")) {
        echo "<script>alert('Delivery Order berhasil dihapus!'); window.location='delivery_order_list.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data: " . $conn->error . "'); window.location='delivery_order_list.php';</script>";
    }
    exit;
}

// --- 3. INIT FILTER VARIABLES ---
$search   = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';

// Bangun Query WHERE
$where = "1=1";

if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (d.do_number LIKE '%$safe_search%' OR i.invoice_no LIKE '%$safe_search%')";
}

if (!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND c.id = $safe_client";
}

// --- 4. LOGIKA EXPORT EXCEL (CSV) DETAIL & RAPI ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=DeliveryOrders_Detail_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');

    // Header CSV
    fputcsv($output, array(
        'DO Number', 
        'Ref Invoice', 
        'DO Date', 
        'Client Name', 
        'Delivery Address', 
        'Item Name', 
        'Unit (Qty)', 
        'Charge Mode', 
        'Description', 
        'Receiver Name', 
        'Receiver Phone', 
        'Status'
    ));
    
    // Query Data DO
    $sqlEx = "SELECT d.*, d.address as do_address_fix, 
                     c.company_name, c.address as client_address_fix, 
                     p.invoice_id, i.quotation_id, i.invoice_no
              FROM delivery_orders d 
              LEFT JOIN payments p ON d.payment_id = p.id 
              LEFT JOIN invoices i ON p.invoice_id = i.id
              LEFT JOIN quotations q ON i.quotation_id = q.id
              LEFT JOIN clients c ON q.client_id = c.id
              WHERE $where
              ORDER BY d.do_number DESC"; 
    
    $resEx = $conn->query($sqlEx);
    
    while($row = $resEx->fetch_assoc()) {
        $final_address = !empty($row['do_address_fix']) ? $row['do_address_fix'] : $row['client_address_fix'];
        $final_address = str_replace(array("\r", "\n"), " ", $final_address); 

        $do_id = $row['id'];
        $itemsData = [];

        // 1. Cek Item Edit Manual
        $sqlDOItems = "SELECT item_name, unit as qty, charge_mode, description FROM delivery_order_items WHERE delivery_order_id = $do_id";
        $resDOItems = $conn->query($sqlDOItems);

        if ($resDOItems && $resDOItems->num_rows > 0) {
            while($itm = $resDOItems->fetch_assoc()) {
                $itemsData[] = $itm;
            }
        } else {
            // 2. Ambil dari Invoice/Quotation
            $inv_id = $row['invoice_id'];
            if ($inv_id > 0) {
                $items_sql = "SELECT item_name, qty, card_type as charge_mode, description FROM invoice_items WHERE invoice_id = $inv_id";
                $resItems = $conn->query($items_sql);
                
                if($resItems->num_rows == 0 && isset($row['quotation_id'])) {
                    $resItems = $conn->query("SELECT item_name, qty, card_type as charge_mode, description FROM quotation_items WHERE quotation_id=".$row['quotation_id']);
                }
                
                while($itm = $resItems->fetch_assoc()) {
                    $itemsData[] = $itm;
                }
            }
        }

        // Loop Item (1 Baris per Item)
        if (count($itemsData) > 0) {
            foreach ($itemsData as $item) {
                $itemName = trim(preg_replace('/\s+/', ' ', $item['item_name']));
                $itemDesc = trim(preg_replace('/\s+/', ' ', $item['description']));

                fputcsv($output, array(
                    $row['do_number'], 
                    $row['invoice_no'],
                    $row['do_date'], 
                    $row['company_name'], 
                    $final_address,
                    $itemName, 
                    floatval($item['qty']), 
                    $item['charge_mode'], 
                    $itemDesc,
                    $row['pic_name'], 
                    $row['pic_phone'], 
                    strtoupper($row['status'])
                ));
            }
        } else {
            fputcsv($output, array(
                $row['do_number'], 
                $row['invoice_no'], 
                $row['do_date'], 
                $row['company_name'], 
                $final_address, 
                '- No Item Found -', 
                '', '', '', 
                $row['pic_name'], 
                $row['pic_phone'], 
                strtoupper($row['status'])
            ));
        }
    }
    
    fclose($output);
    exit();
}

// --- 5. LOAD TAMPILAN HTML ---
$page_title = "Delivery Orders";
include 'includes/header.php';
include 'includes/sidebar.php';

$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// --- STATISTIK DINAMIS ---
$sql_stats = "SELECT d.status, COUNT(*) as count 
              FROM delivery_orders d 
              LEFT JOIN payments p ON d.payment_id = p.id 
              LEFT JOIN invoices i ON p.invoice_id = i.id
              LEFT JOIN quotations q ON i.quotation_id = q.id
              LEFT JOIN clients c ON q.client_id = c.id
              WHERE $where GROUP BY d.status";
$res_stats = $conn->query($sql_stats);

$stats = ['total' => 0, 'draft' => 0, 'sent' => 0, 'delivered' => 0, 'canceled' => 0];
if ($res_stats) {
    while($s = $res_stats->fetch_assoc()) {
        $stats['total'] += $s['count'];
        $st_key = strtolower($s['status']);
        if (isset($stats[$st_key])) {
            $stats[$st_key] += $s['count'];
        }
    }
}

// Query Tampilan Web
$sql = "SELECT d.*, d.address as do_address_fix, 
               c.company_name, c.address as client_address_fix, 
               p.invoice_id, i.quotation_id, i.invoice_no
        FROM delivery_orders d 
        LEFT JOIN payments p ON d.payment_id = p.id 
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN quotations q ON i.quotation_id = q.id
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE $where
        ORDER BY d.do_number DESC"; 
$res = $conn->query($sql);

// Helper Mapping Status Tailwind
$status_styles = [
    'draft'     => 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300',
    'sent'      => 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400',
    'delivered' => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
    'canceled'  => 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400'
];
$status_icons = [
    'draft'     => 'ph-file-dashed',
    'sent'      => 'ph-paper-plane-tilt animate-pulse',
    'delivered' => 'ph-check-circle',
    'canceled'  => 'ph-x-circle'
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
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center text-2xl shadow-lg shadow-amber-500/30">
                    <i class="ph-fill ph-truck"></i>
                </div>
                Delivery Orders
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Daftar surat jalan (Delivery Order) untuk pengiriman barang ke klien.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='delivery_order_list.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh / Reset Filter">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
            <a href="delivery_order_form.php" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-amber-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-plus text-xl relative z-10"></i> 
                <span class="relative z-10">Create New DO</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-files"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total DO</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['total']) ?></h4>
            </div>
        </div>
        
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-check-circle"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Delivered</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['delivered']) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-paper-plane-tilt"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Sent</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['sent']) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-file-dashed"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Draft</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['draft']) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-amber-500 text-lg"></i> Filter Data & Export
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 items-end">
                    
                    <div class="w-full">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Pencarian</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-amber-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 h-[42px] bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="No DO / No Invoice..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="w-full">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Perusahaan Klien</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-amber-500 transition-colors"></i>
                            <select name="client_id" class="w-full pl-11 pr-10 h-[42px] bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Perusahaan</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="w-full flex gap-3 h-[42px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-amber-600 dark:hover:bg-amber-700 text-white font-bold rounded-xl transition-all shadow-md shadow-slate-200 dark:shadow-amber-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-funnel"></i> Terapkan
                        </button>
                        
                        <button type="submit" formmethod="POST" name="export_excel" class="flex-none w-[46px] bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500 dark:hover:text-white font-bold border border-emerald-200 dark:border-emerald-500/20 transition-all rounded-xl active:scale-95 flex items-center justify-center" title="Export CSV">
                            <i class="ph-bold ph-microsoft-excel-logo text-lg"></i>
                        </button>

                        <?php if(!empty($search) || !empty($f_client)): ?>
                            <a href="delivery_order_list.php" class="flex-none w-[46px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors duration-300 flex flex-col min-h-[500px] relative">
        
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/30">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Tampilkan</span>
                <select id="pageSize" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-amber-500/50 outline-none cursor-pointer">
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
            <table class="w-full text-left border-collapse table-fixed min-w-[1100px]">
                <thead class="bg-slate-50/80 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[18%]">DO Info</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[28%]">Client & Destination</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[25%]">Package Details</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[12%]">Receiver</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider w-[10%]">Status</th>
                        <th class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider w-[7%]">Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if ($res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <?php
                            // Format Address
                            $displayAddress = !empty($row['do_address_fix']) ? $row['do_address_fix'] : $row['client_address_fix'];

                            // Get Item Details
                            $do_id = $row['id'];
                            $itemsData = [];
                            $sqlDOItems = "SELECT item_name, unit as qty, charge_mode FROM delivery_order_items WHERE delivery_order_id = $do_id";
                            $resDOItems = $conn->query($sqlDOItems);

                            if ($resDOItems && $resDOItems->num_rows > 0) {
                                while($itm = $resDOItems->fetch_assoc()) $itemsData[] = $itm;
                            } else {
                                $inv_id = $row['invoice_id'];
                                if($inv_id > 0) {
                                    $items_sql = "SELECT item_name, qty, card_type as charge_mode FROM invoice_items WHERE invoice_id = $inv_id";
                                    $resItems = $conn->query($items_sql);
                                    if($resItems->num_rows == 0 && isset($row['quotation_id'])) {
                                        $resItems = $conn->query("SELECT item_name, qty, card_type as charge_mode FROM quotation_items WHERE quotation_id=".$row['quotation_id']);
                                    }
                                    while($itm = $resItems->fetch_assoc()) $itemsData[] = $itm;
                                }
                            }

                            // Status Logic
                            $st = strtolower($row['status'] ?? 'draft');
                            $sStyle = isset($status_styles[$st]) ? $status_styles[$st] : $status_styles['draft'];
                            $sIcon  = isset($status_icons[$st]) ? $status_icons[$st] : $status_icons['draft'];
                        ?>
                        <tr class="data-row hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-top">
                                <div class="font-mono font-black text-amber-600 dark:text-amber-400 text-[13px] mb-1 tracking-wide">
                                    <?= htmlspecialchars($row['do_number']) ?>
                                </div>
                                <?php if(!empty($row['invoice_no'])): ?>
                                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 mb-1">
                                        <i class="ph-bold ph-receipt text-[10px] text-slate-400"></i>
                                        <span class="text-[10px] font-bold text-slate-600 dark:text-slate-300 font-mono"><?= htmlspecialchars($row['invoice_no']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 font-medium flex items-center gap-1">
                                    <i class="ph-fill ph-calendar-blank"></i> <?= date('d M Y', strtotime($row['do_date'])) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-[12px] mb-1.5 break-words whitespace-normal leading-snug group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors" title="<?= htmlspecialchars($row['company_name'] ?? 'Manual Client') ?>">
                                    <?= htmlspecialchars($row['company_name'] ?? 'Manual Client') ?>
                                </div>
                                <div class="flex items-start gap-1.5 text-[10px] text-slate-500 dark:text-slate-400 font-medium">
                                    <i class="ph-fill ph-map-pin text-slate-400 mt-0.5 shrink-0"></i>
                                    <span class="leading-relaxed break-words whitespace-normal line-clamp-3"><?= nl2br(htmlspecialchars($displayAddress)) ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-top">
                                <div class="flex flex-col gap-1.5 w-full">
                                    <?php if(!empty($itemsData)): ?>
                                        <?php foreach($itemsData as $d): ?>
                                            <div class="flex items-start justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 pb-1.5 last:border-0 last:pb-0">
                                                <span class="font-bold text-slate-700 dark:text-slate-300 text-[11px] leading-tight break-words whitespace-normal pr-2">
                                                    <?= htmlspecialchars($d['item_name']) ?>
                                                </span>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <span class="font-black text-amber-600 dark:text-amber-400 text-[11px]">x<?= floatval($d['qty']) ?></span>
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-[8px] font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-600">
                                                        <?= htmlspecialchars($d['charge_mode'] ?? 'N/A') ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[10px]">- No Item Specified -</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-[11px] break-words whitespace-normal leading-snug mb-1">
                                    <?= htmlspecialchars($row['pic_name']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1.5">
                                    <i class="ph-fill ph-phone text-slate-400"></i> 
                                    <span class="break-words whitespace-normal"><?= htmlspecialchars($row['pic_phone']) ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-top text-center">
                                <span class="inline-flex items-center justify-center gap-1.5 px-2 py-1.5 rounded-xl border text-[9px] font-black uppercase tracking-widest w-24 shadow-sm <?= $sStyle ?>">
                                    <i class="ph-bold <?= $sIcon ?> text-sm"></i> <?= htmlspecialchars(strtoupper($st)) ?>
                                </span>
                            </td>

                            <td class="px-6 py-5 align-top text-center relative">
                                <div class="relative inline-block text-left" data-dropdown>
                                    <button type="button" onclick="toggleActionMenu(event, <?= $row['id'] ?>)" class="inline-flex justify-center items-center w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-amber-600 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-400 dark:hover:text-amber-400 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 dropdown-toggle-btn active:scale-95" aria-expanded="true" aria-haspopup="true">
                                        <i class="ph-bold ph-dots-three-vertical text-lg pointer-events-none"></i>
                                    </button>

                                    <div id="action-menu-<?= $row['id'] ?>" class="dropdown-menu hidden absolute right-8 top-0 w-44 bg-white dark:bg-[#24303F] rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 z-[100] overflow-hidden text-left origin-top-right transition-all divide-y divide-slate-50 dark:divide-slate-700/50">
                                        <div class="py-1">
                                            <a href="delivery_order_print.php?id=<?= $row['id'] ?>" target="_blank" class="flex items-center gap-2.5 px-4 py-2.5 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors group/menu">
                                                <i class="ph-bold ph-printer text-base text-slate-400 group-hover/menu:text-amber-500"></i> Print PDF
                                            </a>
                                            <a href="delivery_order_form.php?edit_id=<?= $row['id'] ?>" class="flex items-center gap-2.5 px-4 py-2.5 text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors">
                                                <i class="ph-bold ph-pencil-simple text-base"></i> Edit DO
                                            </a>
                                        </div>
                                        <div class="py-1 bg-slate-50/50 dark:bg-slate-800/30">
                                            <a href="delivery_order_list.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin menghapus DO ini? Data tidak bisa dikembalikan.')" class="flex items-center gap-2.5 px-4 py-2.5 text-[11px] font-black text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                                                <i class="ph-bold ph-trash text-base"></i> Delete DO
                                            </a>
                                        </div>
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
                                        <i class="ph-fill ph-truck text-5xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Delivery Order tidak ditemukan dengan filter pencarian saat ini.</p>
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

<script>
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

            const currentEnd = end > totalRows ? totalRows : end;
            paginationInfo.innerHTML = `Menampilkan <span class="text-amber-600 dark:text-amber-400 font-black">${start + 1} - ${currentEnd}</span> dari <span class="font-black text-slate-800 dark:text-white">${totalRows}</span> data`;

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
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-black text-white bg-amber-500 shadow-sm shadow-amber-500/30 flex items-center justify-center transition-all";
                    } else {
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700 transition-all flex items-center justify-center";
                        pageBtn.onclick = () => { currentPage = i; renderTable(); };
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
            renderTable();
        });

        btnPrev.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; renderTable(); }
        });

        btnNext.addEventListener('click', () => {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            if (currentPage < totalPages) { currentPage++; renderTable(); }
        });

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

    // --- CUSTOM DROPDOWN MENU LOGIC (FIX PROPAGATION) ---
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
</script>

<?php include 'includes/footer.php'; ?>