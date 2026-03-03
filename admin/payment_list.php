<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. INIT FILTER VARIABLES ---
$search   = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';

// Bangun Query WHERE
$where = "1=1";
if(!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND i.invoice_no LIKE '%$safe_search%'";
}
if(!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND c.id = $safe_client";
}

// --- 3. LOGIKA EXPORT EXCEL (CSV) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    $sqlEx = "SELECT p.*, i.invoice_no, i.payment_method, c.company_name, u.username as admin_name
              FROM payments p 
              JOIN invoices i ON p.invoice_id = i.id 
              JOIN quotations q ON i.quotation_id = q.id 
              JOIN clients c ON q.client_id = c.id
              LEFT JOIN users u ON p.created_by = u.id
              WHERE $where
              ORDER BY p.payment_date DESC";
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Payments_Data_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, array('Payment Date', 'Invoice No', 'Client', 'Amount', 'Method', 'SIM Data Count', 'Proof Status', 'Processed By'));
    
    while($row = $resEx->fetch_assoc()) {
        $pid = $row['id'];
        $countSim = $conn->query("SELECT COUNT(*) as t FROM payment_sim_data WHERE payment_id=$pid")->fetch_assoc()['t'];
        
        fputcsv($output, array(
            $row['payment_date'],
            $row['invoice_no'],
            $row['company_name'],
            $row['amount'],
            $row['payment_method'] ? $row['payment_method'] : 'Transfer',
            $countSim . " Data",
            $row['proof_file'] ? 'Uploaded' : 'Pending',
            $row['admin_name'] ?? 'System'
        ));
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "Payment List";
include 'includes/header.php';
include 'includes/sidebar.php';

// Ambil List Client untuk Filter
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// QUERY DATA TAMPILAN
$sql = "SELECT p.*, i.invoice_no, i.payment_method, c.company_name, u.username as admin_name
        FROM payments p 
        JOIN invoices i ON p.invoice_id = i.id 
        JOIN quotations q ON i.quotation_id = q.id 
        JOIN clients c ON q.client_id = c.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE $where
        ORDER BY p.payment_date DESC";
$res = $conn->query($sql);
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
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Payment List</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Riwayat pembayaran masuk dan injeksi data SIM kard.</p>
        </div>
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
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">No Invoice</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="search" class="w-full pl-8 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[11px] font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="Cari No Invoice..." value="<?= htmlspecialchars($search ?? '') ?>">
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
                            <a href="payment_list.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2 px-3 rounded-xl transition-colors text-[11px] text-center border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-sm"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="overflow-x-auto custom-scrollbar w-full pb-20">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                    <tr>
                        <th class="px-5 py-3.5 whitespace-nowrap w-[20%]">Payment Info</th>
                        <th class="px-5 py-3.5 whitespace-nowrap w-[25%]">Client & Invoice Ref</th>
                        <th class="px-5 py-3.5 whitespace-nowrap w-[15%]">Amount & Method</th>
                        <th class="px-5 py-3.5 text-center whitespace-nowrap w-[15%]">SIM Status & Proof</th>
                        <th class="px-5 py-3.5 text-center whitespace-nowrap w-[10%]">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px]">
                    <?php if($res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <?php 
                            // Hitung jumlah data SIM
                            $pid = $row['id'];
                            $countSim = $conn->query("SELECT COUNT(*) as t FROM payment_sim_data WHERE payment_id=$pid")->fetch_assoc()['t'];
                        ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-5 py-3 align-middle whitespace-nowrap">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs mb-0.5 flex items-center gap-1.5">
                                    <i class="ph-fill ph-calendar-blank text-indigo-500"></i> 
                                    <?= date('d M Y', strtotime($row['payment_date'])) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1">
                                    By: <span class="bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded font-bold text-slate-600 dark:text-slate-300"><?= htmlspecialchars($row['admin_name'] ?? 'System') ?></span>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 leading-snug truncate max-w-[250px] text-xs mb-0.5" title="<?= htmlspecialchars($row['company_name'] ?? '') ?>">
                                    <?= htmlspecialchars($row['company_name'] ?? '') ?>
                                </div>
                                <div class="font-mono font-extrabold text-indigo-600 dark:text-indigo-400 tracking-tight text-[11px]">
                                    <?= htmlspecialchars($row['invoice_no']) ?>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle whitespace-nowrap">
                                <div class="font-black text-emerald-600 dark:text-emerald-400 text-xs mb-0.5">
                                    Rp <?= number_format($row['amount'], 0, ',', '.') ?>
                                </div>
                                <span class="inline-flex px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 text-[9px] font-black uppercase tracking-widest">
                                    <?= htmlspecialchars($row['payment_method'] ? $row['payment_method'] : 'Transfer') ?>
                                </span>
                            </td>

                            <td class="px-5 py-3 align-middle text-center whitespace-nowrap">
                                <div class="flex flex-col items-center justify-center gap-1.5">
                                    <?php if($countSim > 0): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-sky-50 text-sky-600 border border-sky-100 dark:bg-sky-500/10 dark:text-sky-400 dark:border-sky-500/20 text-[9px] font-bold uppercase tracking-widest">
                                            <i class="ph-fill ph-sim-card text-[11px]"></i> <?= $countSim ?> Data
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:border-slate-600 text-[9px] font-bold uppercase tracking-widest italic">
                                            Empty Data
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if($row['proof_file']): ?>
                                        <a href="../uploads/<?= $row['proof_file'] ?>" target="_blank" class="inline-flex items-center gap-1 text-[9px] font-bold text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors uppercase tracking-widest">
                                            <i class="ph-bold ph-image text-[11px]"></i> View Proof
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[9px] font-bold text-rose-400 dark:text-rose-500 uppercase tracking-widest italic">No Proof</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle text-center whitespace-nowrap relative">
                                <button onclick="toggleActionMenu(<?= $row['id'] ?>)" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-all shadow-sm active:scale-95 focus:outline-none">
                                    <i class="ph-bold ph-dots-three-vertical text-base"></i>
                                </button>

                                <div id="action-menu-<?= $row['id'] ?>" class="hidden absolute right-10 top-2 w-48 bg-white dark:bg-[#24303F] rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden text-left origin-top-right">
                                    <div class="py-1">
                                        <a href="payment_view.php?id=<?= $row['id'] ?>" class="flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-eye text-sm"></i> View & Upload SIM
                                        </a>

                                        <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                            
                                        <a href="delivery_order_form.php?payment_id=<?= $row['id'] ?>" class="flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-truck text-sm"></i> Create Delivery Order
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-14 h-14 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-wallet text-2xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-[13px] mb-0.5">Tidak Ada Data</h4>
                                    <p class="text-[11px] font-medium">Riwayat pembayaran tidak ditemukan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($res->num_rows > 0): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Menampilkan total <?= $res->num_rows ?> riwayat pembayaran.</p>
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