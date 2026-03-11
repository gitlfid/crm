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

// --- STATISTIK DINAMIS ---
$sql_stats = "SELECT COUNT(*) as total_trx, SUM(p.amount) as total_val 
              FROM payments p 
              JOIN invoices i ON p.invoice_id = i.id 
              JOIN quotations q ON i.quotation_id = q.id 
              JOIN clients c ON q.client_id = c.id 
              WHERE $where";
$res_stats = $conn->query($sql_stats)->fetch_assoc();

$total_trx = $res_stats['total_trx'] ?? 0;
$total_val = $res_stats['total_val'] ?? 0;

$sql_sim_stats = "SELECT COUNT(*) as total_sim FROM payment_sim_data psd 
                  JOIN payments p ON psd.payment_id = p.id 
                  JOIN invoices i ON p.invoice_id = i.id 
                  JOIN quotations q ON i.quotation_id = q.id 
                  JOIN clients c ON q.client_id = c.id 
                  WHERE $where";
$total_sim = $conn->query($sql_sim_stats)->fetch_assoc()['total_sim'] ?? 0;


// --- QUERY DATA TAMPILAN UTAMA ---
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
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-teal-500 to-emerald-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-teal-500/30">
                    <i class="ph-fill ph-wallet"></i>
                </div>
                Payment History
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Pantau riwayat pembayaran masuk dan kelola injeksi data SIM Card Klien.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='payment_list.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh / Reset Filter">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-receipt"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Transaksi</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($total_trx) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-money"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Nilai Pembayaran</p>
                <h4 class="text-3xl font-black text-emerald-600 dark:text-emerald-400 leading-none">
                    <span class="text-lg font-bold mr-1 opacity-70">Rp</span><?= number_format($total_val, 0, ',', '.') ?>
                </h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-sim-card"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Data SIM (ICCID)</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($total_sim) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-teal-500 text-lg"></i> Filter Data & Export
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET" id="filterForm">
                <div class="flex flex-col xl:flex-row gap-5 xl:items-end">
                    
                    <div class="w-full xl:w-72 shrink-0">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">No Invoice</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-teal-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-teal-500/50 focus:border-teal-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner uppercase" placeholder="e.g. INV-..." value="<?= htmlspecialchars($search ?? '') ?>">
                        </div>
                    </div>

                    <div class="w-full xl:w-80 shrink-0 flex-1">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Perusahaan Klien</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-teal-500 transition-colors"></i>
                            <select name="client_id" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-teal-500/50 focus:border-teal-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Perusahaan</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="w-full xl:w-auto flex gap-3 h-[46px] shrink-0">
                        <button type="submit" class="flex-1 xl:flex-none bg-slate-800 hover:bg-slate-900 dark:bg-teal-600 dark:hover:bg-teal-700 text-white font-bold py-2 px-5 rounded-xl transition-all shadow-md shadow-slate-200 dark:shadow-teal-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-funnel"></i> Terapkan Filter
                        </button>
                        
                        <button type="submit" formmethod="POST" name="export_excel" class="flex-1 xl:flex-none bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500 dark:hover:text-white font-bold border border-emerald-200 dark:border-emerald-500/20 py-2 px-5 rounded-xl transition-all shadow-sm active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-microsoft-excel-logo text-lg"></i> Export CSV
                        </button>

                        <?php if(!empty($search) || !empty($f_client)): ?>
                            <a href="payment_list.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2 px-4 rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
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
            <table class="w-full text-left border-collapse table-auto min-w-[1000px]">
                <thead class="bg-slate-50/80 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap w-[20%]">Payment Info</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap min-w-[200px]">Client & Invoice Ref</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap w-[20%]">Amount Received</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap w-[15%]">SIM Status & Proof</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap w-[10%]">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if($res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <?php 
                            // Hitung jumlah data SIM
                            $pid = $row['id'];
                            $countSim = $conn->query("SELECT COUNT(*) as t FROM payment_sim_data WHERE payment_id=$pid")->fetch_assoc()['t'];
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle whitespace-nowrap">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-1.5 flex items-center gap-1.5">
                                    <i class="ph-fill ph-calendar-blank text-teal-500"></i> 
                                    <?= date('d M Y', strtotime($row['payment_date'])) ?>
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1A222C] shadow-sm">
                                    <i class="ph-fill ph-user-circle text-slate-400"></i>
                                    <span class="text-[10px] font-bold text-slate-600 dark:text-slate-300">By: <?= htmlspecialchars($row['admin_name'] ?? 'System') ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 leading-snug truncate max-w-[250px] text-sm mb-1.5 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" title="<?= htmlspecialchars($row['company_name'] ?? '') ?>">
                                    <?= htmlspecialchars($row['company_name'] ?? '') ?>
                                </div>
                                <div class="font-mono font-black text-teal-600 dark:text-teal-400 tracking-widest text-[11px] bg-teal-50 dark:bg-teal-500/10 px-2 py-0.5 rounded inline-block border border-teal-100 dark:border-teal-500/20">
                                    <?= htmlspecialchars($row['invoice_no']) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-right whitespace-nowrap">
                                <div class="font-black text-slate-800 dark:text-white text-[15px] tracking-wide mb-1">
                                    <span class="text-[10px] font-bold text-slate-400 mr-1">Rp</span><?= number_format($row['amount'], 0, ',', '.') ?>
                                </div>
                                <span class="inline-flex px-2 py-0.5 rounded text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 text-[9px] font-black uppercase tracking-widest bg-slate-50 dark:bg-slate-900 shadow-sm">
                                    <?= htmlspecialchars($row['payment_method'] ? $row['payment_method'] : 'Transfer') ?>
                                </span>
                            </td>

                            <td class="px-6 py-5 align-middle text-center whitespace-nowrap">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <?php if($countSim > 0): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/10 dark:text-sky-400 dark:border-sky-500/20 text-[10px] font-black uppercase tracking-widest shadow-sm">
                                            <i class="ph-fill ph-sim-card text-sm"></i> <?= $countSim ?> ICCID
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700 text-[10px] font-bold uppercase tracking-widest italic shadow-sm">
                                            <i class="ph-bold ph-minus"></i> No SIM Data
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if($row['proof_file']): ?>
                                        <a href="../uploads/<?= $row['proof_file'] ?>" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-500 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors uppercase tracking-widest bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-100 dark:border-emerald-500/20">
                                            <i class="ph-bold ph-image text-xs"></i> View Proof
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[9px] font-bold text-rose-400 dark:text-rose-500 uppercase tracking-widest italic">Missing Proof</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-center whitespace-nowrap relative">
                                <div class="relative inline-block text-left" data-dropdown>
                                    <button type="button" class="inline-flex justify-center items-center w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-teal-600 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-400 dark:hover:text-teal-400 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500/50 dropdown-toggle-btn active:scale-95" aria-expanded="true" aria-haspopup="true">
                                        <i class="ph-bold ph-dots-three-vertical text-lg pointer-events-none"></i>
                                    </button>

                                    <div class="dropdown-menu hidden absolute right-8 top-0 w-52 bg-white dark:bg-[#24303F] rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden text-left origin-top-right transition-all divide-y divide-slate-50 dark:divide-slate-700/50">
                                        <div class="py-1">
                                            <a href="payment_view.php?id=<?= $row['id'] ?>" class="flex items-center gap-3 px-4 py-2.5 text-[11px] font-bold text-teal-600 dark:text-teal-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-eye text-base text-slate-400 group-hover:text-teal-500"></i> Detail & Upload SIM
                                            </a>
                                        </div>
                                        <div class="py-1 bg-slate-50/50 dark:bg-slate-800/30">
                                            <a href="delivery_order_form.php?payment_id=<?= $row['id'] ?>" class="flex items-center gap-3 px-4 py-2.5 text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors">
                                                <i class="ph-bold ph-truck text-base"></i> Create Delivery Order
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-wallet text-5xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Riwayat pembayaran tidak ditemukan dengan filter saat ini.</p>
                                    <?php if(!empty($search) || !empty($f_client)): ?>
                                        <a href="payment_list.php" class="mt-5 inline-flex items-center gap-2 bg-slate-800 dark:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors shadow-sm">
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
                Menampilkan Total <span class="text-teal-600 dark:text-teal-400 font-black mx-1"><?= $res->num_rows ?></span> Data
            </p>
        </div>
        <?php endif; ?>
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
    let currentOpenDropdown = null;
    function toggleActionMenu(id) {
        const menu = document.getElementById('action-menu-' + id);
        if (currentOpenDropdown && currentOpenDropdown !== menu) {
            currentOpenDropdown.classList.add('hidden');
        }
        menu.classList.toggle('hidden');
        currentOpenDropdown = menu.classList.contains('hidden') ? null : menu;
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (currentOpenDropdown && !e.target.closest('td.relative')) {
            currentOpenDropdown.classList.add('hidden');
            currentOpenDropdown = null;
        }
    });
</script>

<?php include 'includes/footer.php'; ?>