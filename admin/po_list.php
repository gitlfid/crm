<?php
$page_title = "Purchase Orders";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

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

// Logic untuk mengambil data PO
$sql = "SELECT po.*, v.company_name as vendor_name, u.username as created_by 
        FROM purchase_orders po
        LEFT JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN users u ON po.created_by_user_id = u.id
        " . $where_sql . "
        ORDER BY po.created_at DESC";
$pos = $conn->query($sql);

// --- Helper Mapping Status (Tailwind Colors) ---
$status_styles = [
    'Draft'     => 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300',
    'Submitted' => 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400',
    'Approved'  => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
    'Rejected'  => 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400'
];
$status_icons = [
    'Draft'     => 'ph-file-dashed',
    'Submitted' => 'ph-paper-plane-tilt',
    'Approved'  => 'ph-check-circle',
    'Rejected'  => 'ph-x-circle'
];
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    .custom-scrollbar::-webkit-scrollbar { height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 animate-fade-in-up">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Purchase Orders</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Daftar semua Purchase Order yang dibuat dan status persetujuannya.</p>
        </div>
        <a href="po_form.php" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 active:scale-95 whitespace-nowrap">
            <i class="ph-bold ph-plus text-lg"></i> Create New PO
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/80 rounded-t-2xl" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-base"></i> Filter & Pencarian
            </h3>
            <i id="filterIcon" class="ph-bold ph-caret-up text-slate-400 transition-transform duration-300"></i>
        </div>
        
        <div id="filterBody" class="p-5 block transition-all duration-300">
            <form method="GET" action="po_list.php">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    
                    <div class="lg:col-span-1">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Pencarian</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="search" class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="PO Number / Vendor..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Pilih Vendor</label>
                        <div class="relative">
                            <i class="ph-bold ph-buildings absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <select name="vendor_id" class="w-full pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="all">-- Semua Vendor --</option>
                                <?php if($vendors_res->num_rows > 0) { $vendors_res->data_seek(0); while($vendor = $vendors_res->fetch_assoc()): ?>
                                    <option value="<?= $vendor['id'] ?>" <?= $vendor_filter == $vendor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($vendor['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Status PO</label>
                        <div class="relative">
                            <i class="ph-bold ph-list-checks absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <select name="status" class="w-full pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>Semua Status</option>
                                <option value="Draft" <?= $status_filter == 'Draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="Submitted" <?= $status_filter == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1 flex gap-2">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold py-2 px-3 rounded-xl transition-colors text-xs shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                            <i class="ph-bold ph-funnel text-sm"></i> Filter
                        </button>
                        <?php if(!empty($search) || $status_filter !== 'all' || $vendor_filter !== 'all'): ?>
                            <a href="po_list.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2 px-3 rounded-xl transition-colors text-xs text-center border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-sm"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="overflow-x-auto custom-scrollbar w-full">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                    <tr>
                        <th class="px-5 py-3.5 whitespace-nowrap">PO Details</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Vendor Information</th>
                        <th class="px-5 py-3.5 text-right whitespace-nowrap">Total Amount</th>
                        <th class="px-5 py-3.5 text-center whitespace-nowrap">Status</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Created By</th>
                        <th class="px-5 py-3.5 text-center whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                    <?php if ($pos && $pos->num_rows > 0): ?>
                        <?php while($row = $pos->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-5 py-4 align-middle">
                                <div class="font-mono font-extrabold text-indigo-600 dark:text-indigo-400 tracking-tight text-[11px]">
                                    <?= htmlspecialchars($row['po_number']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 font-medium flex items-center gap-1 whitespace-nowrap">
                                    <i class="ph-fill ph-calendar-blank text-slate-400"></i>
                                    <?= date('d M Y', strtotime($row['po_date'])) ?>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-middle">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-black text-[10px] shrink-0 border border-slate-200 dark:border-slate-600">
                                        <i class="ph-fill ph-buildings"></i>
                                    </div>
                                    <div class="font-bold text-slate-800 dark:text-slate-200 text-[11px] truncate max-w-[200px]" title="<?= htmlspecialchars($row['vendor_name'] ?? 'N/A') ?>">
                                        <?= htmlspecialchars($row['vendor_name'] ?? 'N/A') ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-middle text-right">
                                <span class="text-[10px] font-bold text-slate-400 mr-1">Rp</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 text-xs">
                                    <?= number_format($row['total_amount'], 2, ',', '.') ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 align-middle text-center">
                                <?php 
                                    $st = $row['status'];
                                    $sStyle = isset($status_styles[$st]) ? $status_styles[$st] : $status_styles['Draft'];
                                    $sIcon  = isset($status_icons[$st]) ? $status_icons[$st] : $status_icons['Draft'];
                                ?>
                                <span class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg border text-[9px] font-black uppercase tracking-widest w-28 <?= $sStyle ?>">
                                    <i class="ph-fill <?= $sIcon ?> text-[11px]"></i> <?= htmlspecialchars($st) ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 align-middle">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
                                    <div class="w-4 h-4 rounded-full overflow-hidden shrink-0 bg-slate-100">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['created_by'] ?? 'User') ?>&background=random&size=32" alt="Avatar" class="w-full h-full object-cover">
                                    </div>
                                    <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                        <?= htmlspecialchars($row['created_by'] ?? 'N/A') ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-middle text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="po_form.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 dark:bg-slate-700 dark:hover:bg-indigo-500/20 dark:text-slate-300 dark:hover:text-indigo-400 transition-all shadow-sm active:scale-95 group/btn" title="View / Edit PO">
                                        <i class="ph-bold ph-pencil-simple text-[15px] group-hover/btn:scale-110 transition-transform"></i>
                                    </a>
                                    <a href="po_print.php?id=<?= $row['id'] ?>" target="_blank" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-600 dark:bg-slate-700 dark:hover:bg-emerald-500/20 dark:text-slate-300 dark:hover:text-emerald-400 transition-all shadow-sm active:scale-95 group/btn" title="Print PDF">
                                        <i class="ph-bold ph-printer text-[15px] group-hover/btn:scale-110 transition-transform"></i>
                                    </a>
                                </div>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-file-text text-3xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mb-0.5">Tidak Ada Data</h4>
                                    <p class="text-[11px] font-medium">Purchase Order tidak ditemukan dengan filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($pos && $pos->num_rows > 0): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Menampilkan total <?= $pos->num_rows ?> Purchase Order.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Skrip untuk Toggle Expand/Collapse Area Filter
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
</script>

<?php include 'includes/footer.php'; ?>