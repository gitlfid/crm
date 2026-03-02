<?php
$page_title = "Manage Tickets";
include 'includes/header.php';
include 'includes/sidebar.php';
// include '../config/functions.php'; // Sesuaikan jika ada

// --- LOGIKA FILTER & PENCARIAN ---
$filter_id = isset($_GET['search_id']) ? $_GET['search_id'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_company = isset($_GET['filter_company']) ? $_GET['filter_company'] : '';

// 1. QUERY JOIN TABLE (Tickets + Users)
$sql = "SELECT t.*, u.username as assigned_name 
        FROM tickets t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE 1=1";

// 2. Filter Logic 
if (!empty($filter_id)) {
    $safe_id = $conn->real_escape_string($filter_id);
    $sql .= " AND t.ticket_code LIKE '%$safe_id%'";
}

if (!empty($filter_status)) {
    $safe_status = $conn->real_escape_string($filter_status);
    $sql .= " AND t.status = '$safe_status'";
}

if (!empty($filter_company)) {
    $safe_company = $conn->real_escape_string($filter_company);
    $sql .= " AND t.company LIKE '%$safe_company%'";
}

// Urutkan
$sql .= " ORDER BY t.created_at DESC";

$result = $conn->query($sql);

// --- Helper Mapping untuk UI Tailwind (Diperbarui agar lebih kontras & modern) ---
$type_styles = [
    'support' => 'bg-indigo-100 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400',
    'payment' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400',
    'info'    => 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400'
];
$type_icons = [
    'support' => 'ph-lifebuoy',
    'payment' => 'ph-credit-card',
    'info'    => 'ph-info'
];

$status_styles = [
    'open'     => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20',
    'progress' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border-amber-200 dark:border-amber-500/20',
    'hold'     => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400 border-blue-200 dark:border-blue-500/20',
    'closed'   => 'bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-300 border-slate-200 dark:border-slate-600',
    'canceled' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200 dark:border-rose-500/20'
];
$status_icons = [
    'open'     => 'ph-envelope-open',
    'progress' => 'ph-spinner-gap animate-spin-slow', 
    'hold'     => 'ph-pause-circle',
    'closed'   => 'ph-check-circle',
    'canceled' => 'ph-x-circle'
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

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-100 text-cyan-600 dark:bg-cyan-900/30 dark:text-cyan-400 flex items-center justify-center text-xl shadow-inner">
                    <i class="ph-bold ph-users-three"></i>
                </div>
                External Tickets
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Pusat manajemen dukungan pelanggan dan penyelesaian keluhan masuk.</p>
        </div>
        <nav aria-label="breadcrumb" class="hidden md:block">
            <ol class="flex items-center gap-2 text-xs font-bold tracking-widest uppercase text-slate-400 bg-white dark:bg-[#24303F] px-4 py-2 rounded-xl border border-slate-100 dark:border-slate-800 shadow-sm">
                <li><a href="dashboard.php" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors flex items-center gap-1.5"><i class="ph-bold ph-squares-four text-sm"></i> Dashboard</a></li>
                <li><i class="ph-bold ph-caret-right"></i></li>
                <li class="text-cyan-600 dark:text-cyan-400 flex items-center gap-1.5"><i class="ph-bold ph-headset text-sm"></i> Tickets</li>
            </ol>
        </nav>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-lg"></i> Filter & Pencarian
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-12 gap-5 items-end">
                    
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Nomor Ticket</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <input type="text" name="search_id" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="e.g. LFID-..." value="<?= htmlspecialchars($filter_id) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-4">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Nama Perusahaan</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <input type="text" name="filter_company" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Cari nama client/perusahaan..." value="<?= htmlspecialchars($filter_company) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Status Tiket</label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <select name="filter_status" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Status</option>
                                <option value="open" <?= $filter_status == 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="progress" <?= $filter_status == 'progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="hold" <?= $filter_status == 'hold' ? 'selected' : '' ?>>Hold</option>
                                <option value="closed" <?= $filter_status == 'closed' ? 'selected' : '' ?>>Closed</option>
                                <option value="canceled" <?= $filter_status == 'canceled' ? 'selected' : '' ?>>Canceled</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-2 flex gap-3 h-[46px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-cyan-600 dark:hover:bg-cyan-700 text-white font-bold rounded-xl transition-all shadow-sm active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-funnel"></i> Filter
                        </button>
                        <?php if(!empty($filter_id) || !empty($filter_status) || !empty($filter_company)): ?>
                            <a href="tickets.php" class="flex-none w-[46px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
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
                <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Ticket Info</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider min-w-[250px]">Subject & Type</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Client / Company</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Assignment</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-mono font-bold text-xs border border-indigo-100 dark:border-indigo-500/20 mb-2">
                                    <i class="ph-bold ph-hash"></i>
                                    <?= htmlspecialchars($row['ticket_code']) ?>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1.5 whitespace-nowrap">
                                    <i class="ph-fill ph-calendar-blank"></i>
                                    <?= date('d M Y', strtotime($row['created_at'])) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <?php 
                                    $t = strtolower($row['type']);
                                    $tStyle = isset($type_styles[$t]) ? $type_styles[$t] : $type_styles['info'];
                                    $tIcon  = isset($type_icons[$t]) ? $type_icons[$t] : $type_icons['info'];
                                ?>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-widest border mb-2 <?= $tStyle ?>">
                                    <i class="ph-fill <?= $tIcon ?> text-[11px]"></i> <?= htmlspecialchars($row['type']) ?>
                                </span>
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm line-clamp-2 leading-snug group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" title="<?= htmlspecialchars($row['subject']) ?>">
                                    <?= htmlspecialchars($row['subject']) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-black text-sm uppercase shrink-0 border border-slate-200 dark:border-slate-600 shadow-sm">
                                        <?= strtoupper(substr($row['company'], 0, 1)) ?>
                                    </div>
                                    <div class="font-bold text-slate-700 dark:text-slate-300 text-sm truncate max-w-[180px]" title="<?= htmlspecialchars($row['company']) ?>">
                                        <?= htmlspecialchars($row['company']) ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <?php if($row['assigned_name']): ?>
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/80 shadow-sm">
                                        <div class="w-6 h-6 rounded-full overflow-hidden shrink-0 bg-slate-100 ring-2 ring-white dark:ring-slate-700">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['assigned_name']) ?>&background=random&size=64" alt="Avatar" class="w-full h-full object-cover">
                                        </div>
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap"><?= htmlspecialchars($row['assigned_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/30 text-slate-400 dark:text-slate-500">
                                        <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center">
                                            <i class="ph-bold ph-user-minus text-xs"></i>
                                        </div>
                                        <span class="text-[10px] font-bold uppercase tracking-widest">Unassigned</span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-5 align-middle text-center">
                                <?php 
                                    $st = strtolower($row['status']);
                                    $sStyle = isset($status_styles[$st]) ? $status_styles[$st] : $status_styles['closed'];
                                    $sIcon  = isset($status_icons[$st]) ? $status_icons[$st] : $status_icons['closed'];
                                ?>
                                <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl border text-[10px] font-black uppercase tracking-wider w-28 shadow-sm <?= $sStyle ?>">
                                    <i class="ph-bold <?= $sIcon ?> text-sm"></i> <?= htmlspecialchars($st) ?>
                                </span>
                            </td>

                            <td class="px-6 py-5 align-middle text-center">
                                <a href="view_ticket.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 hover:bg-cyan-600 text-slate-500 hover:text-white dark:bg-slate-800 dark:hover:bg-cyan-600 dark:text-slate-400 dark:hover:text-white transition-all shadow-sm active:scale-95 group/btn" title="Kelola Tiket">
                                    <i class="ph-bold ph-arrow-right text-lg group-hover/btn:translate-x-0.5 transition-transform"></i>
                                </a>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-ticket text-4xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Data ticket eksternal tidak ditemukan dengan filter saat ini.</p>
                                    <?php if(!empty($filter_id) || !empty($filter_status) || !empty($filter_company)): ?>
                                        <a href="tickets.php" class="mt-4 text-cyan-600 dark:text-cyan-400 font-bold text-sm hover:underline">Reset Filter Pencarian</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($result && $result->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-center">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white dark:bg-[#1A222C] px-4 py-1.5 rounded-full border border-slate-100 dark:border-slate-700 shadow-sm">
                Total Data: <span class="text-cyan-600 dark:text-cyan-400 ml-1"><?= $result->num_rows ?> Tiket</span>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Skrip untuk Interaksi Form Toggle Filter (Smooth Accordion)
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