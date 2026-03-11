<?php
$page_title = "Purchase Orders";
include 'includes/header.php';
include 'includes/sidebar.php';
// include '../config/functions.php'; // Aktifkan jika diperlukan

// --- LOGIKA FILTER DAN PENCARIAN ---
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$vendor_filter = $_GET['vendor_id'] ?? 'all';

// Ambil daftar Vendor untuk dropdown filter
$vendors_res = $conn->query("SELECT id, company_name FROM vendors ORDER BY company_name ASC");

// Bangun klausa WHERE
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = "(po.po_number LIKE '%" . $conn->real_escape_string($search) . "%' OR v.company_name LIKE '%" . $conn->real_escape_string($search) . "%')";
}

if ($status_filter !== 'all') {
    $where_clauses[] = "po.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($vendor_filter !== 'all' && is_numeric($vendor_filter)) {
    $where_clauses[] = "po.vendor_id = " . intval($vendor_filter);
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// --- QUERY STATISTIK DINAMIS ---
$sql_stats = "SELECT po.status, COUNT(*) as count, SUM(po.total_amount) as total_val 
              FROM purchase_orders po 
              LEFT JOIN vendors v ON po.vendor_id = v.id 
              $where_sql GROUP BY po.status";
$res_stats = $conn->query($sql_stats);

$stats = [
    'total_count' => 0, 'total_val' => 0, 
    'Draft' => 0, 'Submitted' => 0, 'Approved' => 0, 'Rejected' => 0
];

if ($res_stats) {
    while($s = $res_stats->fetch_assoc()) {
        $stats['total_count'] += $s['count'];
        $stats['total_val'] += $s['total_val'];
        if (isset($stats[$s['status']])) {
            $stats[$s['status']] += $s['count'];
        }
    }
}

// --- QUERY UTAMA ---
$sql = "SELECT po.*, v.company_name as vendor_name, u.username as created_by 
        FROM purchase_orders po
        LEFT JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN users u ON po.created_by_user_id = u.id
        " . $where_sql . "
        ORDER BY po.created_at DESC";
$pos = $conn->query($sql);

// --- Helper Mapping Status (Tailwind Colors) ---
$status_styles = [
    'Draft'     => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300',
    'Submitted' => 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400',
    'Approved'  => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
    'Rejected'  => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400'
];
$status_icons = [
    'Draft'     => 'ph-file-dashed',
    'Submitted' => 'ph-paper-plane-tilt animate-pulse',
    'Approved'  => 'ph-check-circle',
    'Rejected'  => 'ph-x-circle'
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
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-emerald-500/30">
                    <i class="ph-fill ph-shopping-cart"></i>
                </div>
                Purchase Orders
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Kelola seluruh dokumen pemesanan pembelian (PO) dan pantau status persetujuan.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='po_list.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh / Reset">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
            <a href="po_form.php" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-emerald-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-plus text-xl relative z-10"></i> 
                <span class="relative z-10">Create New PO</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-files"></i></div>
            <div class="overflow-hidden">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total PO</p>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none mb-1"><?= number_format($stats['total_count']) ?></h4>
                <p class="text-[10px] font-bold text-indigo-500 truncate" title="Rp <?= number_format($stats['total_val'], 0, ',', '.') ?>">Rp <?= number_format($stats['total_val'], 0, ',', '.') ?></p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-check-circle"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Approved</p>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['Approved']) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-paper-plane-tilt"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Submitted</p>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['Submitted']) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-file-dashed"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Draft</p>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['Draft']) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-emerald-500 text-lg"></i> Filter Data PO
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET" action="po_list.php">
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-12 gap-5 items-end">
                    
                    <div class="lg:col-span-4">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Kata Kunci</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-emerald-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Nomor PO / Nama Vendor..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Pilih Vendor</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-emerald-500 transition-colors"></i>
                            <select name="vendor_id" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="all">Semua Vendor</option>
                                <?php if($vendors_res->num_rows > 0) { $vendors_res->data_seek(0); while($vendor = $vendors_res->fetch_assoc()): ?>
                                    <option value="<?= $vendor['id'] ?>" <?= $vendor_filter == $vendor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($vendor['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Status PO</label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-checks absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-emerald-500 transition-colors"></i>
                            <select name="status" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>Semua Status</option>
                                <option value="Draft" <?= $status_filter == 'Draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="Submitted" <?= $status_filter == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-2 flex gap-3 h-[46px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-emerald-600 dark:hover:bg-emerald-700 text-white font-bold rounded-xl transition-all shadow-md shadow-slate-200 dark:shadow-emerald-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-funnel"></i> Filter
                        </button>
                        <?php if(!empty($search) || $status_filter !== 'all' || $vendor_filter !== 'all'): ?>
                            <a href="po_list.php" class="flex-none w-[46px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors duration-300">
        <div class="overflow-x-auto modern-scrollbar w-full">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">PO Details</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider min-w-[200px]">Vendor Information</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-right text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Total Amount</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Created By</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if ($pos && $pos->num_rows > 0): ?>
                        <?php while($row = $pos->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-mono font-bold text-xs border border-emerald-200 dark:border-emerald-500/20 mb-2">
                                    <i class="ph-bold ph-hash"></i>
                                    <?= htmlspecialchars($row['po_number']) ?>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1.5 whitespace-nowrap">
                                    <i class="ph-fill ph-calendar-blank"></i>
                                    <?= date('d M Y', strtotime($row['po_date'])) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-black text-sm uppercase shrink-0 shadow-inner border border-slate-200 dark:border-slate-600">
                                        <i class="ph-fill ph-buildings text-lg"></i>
                                    </div>
                                    <div class="font-bold text-slate-800 dark:text-slate-200 text-sm truncate max-w-[200px]" title="<?= htmlspecialchars($row['vendor_name'] ?? 'N/A') ?>">
                                        <?= htmlspecialchars($row['vendor_name'] ?? 'N/A') ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-right">
                                <div class="inline-flex flex-col items-end">
                                    <span class="font-black text-slate-800 dark:text-slate-200 text-sm tracking-wide">
                                        <span class="text-[10px] font-bold text-slate-400 mr-1">Rp</span><?= number_format($row['total_amount'], 0, ',', '.') ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-center">
                                <?php 
                                    $st = $row['status'];
                                    $sStyle = isset($status_styles[$st]) ? $status_styles[$st] : $status_styles['Draft'];
                                    $sIcon  = isset($status_icons[$st]) ? $status_icons[$st] : $status_icons['Draft'];
                                ?>
                                <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl border text-[10px] font-black uppercase tracking-widest w-28 shadow-sm <?= $sStyle ?>">
                                    <i class="ph-bold <?= $sIcon ?> text-sm"></i> <?= htmlspecialchars($st) ?>
                                </span>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="inline-flex items-center gap-2.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1A222C] shadow-sm">
                                    <div class="w-6 h-6 rounded-full overflow-hidden shrink-0 bg-slate-100 ring-2 ring-white dark:ring-slate-800">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['created_by'] ?? 'User') ?>&background=random&color=fff&size=64" alt="Avatar" class="w-full h-full object-cover">
                                    </div>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                        <?= htmlspecialchars($row['created_by'] ?? 'N/A') ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="po_form.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 hover:bg-indigo-600 text-slate-500 hover:text-white dark:bg-slate-800 dark:hover:bg-indigo-600 dark:text-slate-400 dark:hover:text-white transition-all shadow-sm active:scale-95 group/btn" title="View / Edit PO">
                                        <i class="ph-bold ph-pencil-simple text-lg group-hover/btn:-translate-y-0.5 transition-transform"></i>
                                    </a>
                                    <a href="po_print.php?id=<?= $row['id'] ?>" target="_blank" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 hover:bg-emerald-600 text-slate-500 hover:text-white dark:bg-slate-800 dark:hover:bg-emerald-600 dark:text-slate-400 dark:hover:text-white transition-all shadow-sm active:scale-95 group/btn" title="Print PDF">
                                        <i class="ph-bold ph-printer text-lg group-hover/btn:-translate-y-0.5 transition-transform"></i>
                                    </a>
                                </div>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-file-text text-5xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Purchase Order tidak ditemukan dengan filter saat ini.</p>
                                    <?php if(!empty($search) || $status_filter !== 'all' || $vendor_filter !== 'all'): ?>
                                        <a href="po_list.php" class="mt-5 inline-flex items-center gap-2 bg-slate-800 dark:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors shadow-sm">
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
        
        <?php if($pos && $pos->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-center">
            <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest bg-white dark:bg-[#1A222C] px-5 py-2 rounded-full border border-slate-100 dark:border-slate-700 shadow-sm">
                Menampilkan Total <span class="text-emerald-600 dark:text-emerald-400 font-black mx-1"><?= $pos->num_rows ?></span> Purchase Order
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Skrip untuk Toggle Expand/Collapse Area Filter (Smooth Accordion)
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
</script>

<?php include 'includes/footer.php'; ?>