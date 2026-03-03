<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. LOGIKA DELETE (BARU) ---
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
    exit; // Stop eksekusi agar tidak lanjut render HTML
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

// Query Tampilan Web (Tetap LEFT JOIN agar DO Draft Muncul)
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
    'sent'      => 'ph-paper-plane-tilt',
    'delivered' => 'ph-check-circle',
    'canceled'  => 'ph-x-circle'
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
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Delivery Orders</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Daftar surat jalan (Delivery Order) untuk pengiriman barang ke klien.</p>
        </div>
        <a href="delivery_order_form.php" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 active:scale-95 whitespace-nowrap">
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
                <div class="flex flex-col xl:flex-row gap-4 xl:items-end">
                    
                    <div class="w-full xl:w-72 shrink-0">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Pencarian</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="search" class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[11px] font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="No DO / No Invoice..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="w-full xl:w-72 shrink-0 flex-1">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Perusahaan Klien</label>
                        <div class="relative">
                            <select name="client_id" class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[11px] font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">- Semua Perusahaan -</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="w-full xl:w-auto flex gap-2 shrink-0">
                        <button type="submit" class="flex-1 xl:flex-none bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white font-bold py-2 px-4 rounded-xl transition-colors text-[11px] shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                            <i class="ph-bold ph-funnel text-sm"></i> Filter
                        </button>
                        
                        <button type="submit" formmethod="POST" name="export_excel" class="flex-1 xl:flex-none bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 px-4 rounded-xl transition-colors text-[11px] shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                            <i class="ph-bold ph-file-csv text-sm"></i> Export
                        </button>

                        <?php if(!empty($search) || !empty($f_client)): ?>
                            <a href="delivery_order_list.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2 px-3 rounded-xl transition-colors text-[11px] text-center border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-sm"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="overflow-x-auto custom-scrollbar w-full pb-20"> <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                    <tr>
                        <th class="px-4 py-3 whitespace-nowrap w-[15%]">DO Info</th>
                        <th class="px-4 py-3 w-[25%]">Client & Destination</th>
                        <th class="px-4 py-3 w-[30%]">Package Details</th>
                        <th class="px-4 py-3 w-[15%]">Receiver</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap w-[10%]">Status</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap w-[5%]">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
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
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-4 py-3 align-top whitespace-nowrap">
                                <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-[11px] mb-1">
                                    <?= htmlspecialchars($row['do_number']) ?>
                                </div>
                                <?php if(!empty($row['invoice_no'])): ?>
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 mb-1">
                                        INV: <?= htmlspecialchars($row['invoice_no']) ?>
                                    </span>
                                <?php endif; ?>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium flex items-center gap-1">
                                    <i class="ph-fill ph-calendar-blank"></i> <?= date('d M Y', strtotime($row['do_date'])) ?>
                                </div>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-[11px] mb-1">
                                    <?= htmlspecialchars($row['company_name'] ?? 'Manual Client') ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 leading-snug line-clamp-2">
                                    <i class="ph-fill ph-map-pin text-slate-400 mr-0.5"></i>
                                    <?= htmlspecialchars($displayAddress) ?>
                                </div>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-col gap-1.5 w-full">
                                    <?php if(!empty($itemsData)): ?>
                                        <?php foreach($itemsData as $d): ?>
                                            <div class="flex items-start justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 pb-1.5 last:border-0 last:pb-0">
                                                <span class="font-bold text-slate-700 dark:text-slate-300 text-[10px] leading-tight break-words max-w-[200px]"><?= htmlspecialchars($d['item_name']) ?></span>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <span class="font-black text-indigo-600 dark:text-indigo-400 text-[10px]">x<?= floatval($d['qty']) ?></span>
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

                            <td class="px-4 py-3 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-[11px]">
                                    <?= htmlspecialchars($row['pic_name']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium flex items-center gap-1">
                                    <i class="ph-fill ph-phone"></i> <?= htmlspecialchars($row['pic_phone']) ?>
                                </div>
                            </td>

                            <td class="px-4 py-3 align-top text-center whitespace-nowrap">
                                <span class="inline-flex items-center justify-center gap-1.5 px-2 py-1 rounded-lg border text-[9px] font-black uppercase tracking-widest w-24 <?= $sStyle ?>">
                                    <i class="ph-fill <?= $sIcon ?>"></i> <?= htmlspecialchars(strtoupper($st)) ?>
                                </span>
                            </td>

                            <td class="px-4 py-3 align-top text-center whitespace-nowrap relative">
                                <button onclick="toggleActionMenu(<?= $row['id'] ?>)" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-all shadow-sm active:scale-95 focus:outline-none">
                                    <i class="ph-bold ph-dots-three-vertical text-base"></i>
                                </button>

                                <div id="action-menu-<?= $row['id'] ?>" class="hidden absolute right-8 top-0 w-40 bg-white dark:bg-[#24303F] rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden text-left origin-top-right">
                                    <div class="py-1">
                                        <a href="delivery_order_print.php?id=<?= $row['id'] ?>" target="_blank" class="flex items-center gap-2 px-4 py-2 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-printer text-sm text-slate-400"></i> Print PDF
                                        </a>
                                        
                                        <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>

                                        <a href="delivery_order_form.php?edit_id=<?= $row['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-[11px] font-semibold text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-pencil-simple text-sm"></i> Edit DO
                                        </a>
                                        
                                        <a href="delivery_order_list.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin menghapus DO ini? Data tidak bisa dikembalikan.')" class="flex items-center gap-2 px-4 py-2 text-[11px] font-black text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                                            <i class="ph-fill ph-trash text-sm"></i> Delete DO
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-14 h-14 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-truck text-2xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-[13px] mb-0.5">Tidak Ada Data</h4>
                                    <p class="text-[11px] font-medium">Delivery Order tidak ditemukan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($res->num_rows > 0): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Menampilkan total <?= $res->num_rows ?> Delivery Order.</p>
        </div>
        <?php endif; ?>
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