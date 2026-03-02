<?php
$page_title = "Manage Tickets";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

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

// --- Helper Mapping untuk UI Tailwind ---
$type_styles = [
    'support' => 'bg-indigo-50 text-indigo-600 border-indigo-100 dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400',
    'payment' => 'bg-amber-50 text-amber-600 border-amber-100 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400',
    'info'    => 'bg-sky-50 text-sky-600 border-sky-100 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400'
];
$type_icons = [
    'support' => 'ph-lifebuoy',
    'payment' => 'ph-credit-card',
    'info'    => 'ph-info'
];

$status_styles = [
    'open'     => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
    'progress' => 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400',
    'hold'     => 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400',
    'closed'   => 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300',
    'canceled' => 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400'
];
$status_icons = [
    'open'     => 'ph-envelope-open',
    'progress' => 'ph-hourglass-high animate-pulse', // Animasi berdenyut
    'hold'     => 'ph-pause-circle',
    'closed'   => 'ph-check-circle',
    'canceled' => 'ph-x-circle'
];
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    /* Kustomisasi scrollbar untuk bagian tabel jika layar sangat kecil */
    .custom-scrollbar::-webkit-scrollbar { height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 animate-fade-in-up">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">External Tickets</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Pusat manajemen dukungan pelanggan dan penyelesaian keluhan masuk.</p>
        </div>
        <nav aria-label="breadcrumb" class="hidden sm:block">
            <ol class="flex items-center gap-2 text-[11px] font-bold tracking-widest uppercase text-slate-400">
                <li><a href="dashboard.php" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Dashboard</a></li>
                <li><i class="ph-bold ph-caret-right"></i></li>
                <li class="text-slate-700 dark:text-slate-300">Tickets</li>
            </ol>
        </nav>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/80 rounded-t-2xl" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-base"></i> Filter & Pencarian
            </h3>
            <i id="filterIcon" class="ph-bold ph-caret-up text-slate-400 transition-transform duration-300"></i>
        </div>
        
        <div id="filterBody" class="p-5 block transition-all duration-300">
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nomor Ticket</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="search_id" class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="e.g. LFID-..." value="<?= htmlspecialchars($filter_id) ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nama Perusahaan</label>
                        <div class="relative">
                            <i class="ph-bold ph-buildings absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="filter_company" class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="e.g. PT Linksfield..." value="<?= htmlspecialchars($filter_company) ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Status Tiket</label>
                        <div class="relative">
                            <i class="ph-bold ph-list absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <select name="filter_status" class="w-full pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">-- Semua Status --</option>
                                <option value="open" <?= $filter_status == 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="progress" <?= $filter_status == 'progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="hold" <?= $filter_status == 'hold' ? 'selected' : '' ?>>Hold</option>
                                <option value="closed" <?= $filter_status == 'closed' ? 'selected' : '' ?>>Closed</option>
                                <option value="canceled" <?= $filter_status == 'canceled' ? 'selected' : '' ?>>Canceled</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold py-2 px-3 rounded-xl transition-colors text-xs shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                            <i class="ph-bold ph-funnel text-sm"></i> Terapkan
                        </button>
                        <?php if(!empty($filter_id) || !empty($filter_status) || !empty($filter_company)): ?>
                            <a href="tickets.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2 px-3 rounded-xl transition-colors text-xs text-center border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
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
                        <th class="px-5 py-3.5 whitespace-nowrap">Ticket Info</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Subject & Type</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Client / Company</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Assignment</th>
                        <th class="px-5 py-3.5 text-center whitespace-nowrap">Status</th>
                        <th class="px-5 py-3.5 text-center whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-5 py-4 align-top">
                                <div class="font-mono font-extrabold text-indigo-600 dark:text-indigo-400 tracking-tight text-[11px]">
                                    <?= htmlspecialchars($row['ticket_code']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 font-medium flex items-center gap-1 whitespace-nowrap">
                                    <i class="ph-fill ph-calendar-blank text-slate-400"></i>
                                    <?= date('d M Y', strtotime($row['created_at'])) ?>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <?php 
                                    $t = strtolower($row['type']);
                                    $tStyle = isset($type_styles[$t]) ? $type_styles[$t] : $type_styles['info'];
                                    $tIcon  = isset($type_icons[$t]) ? $type_icons[$t] : $type_icons['info'];
                                ?>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest border mb-1.5 <?= $tStyle ?>">
                                    <i class="ph-fill <?= $tIcon ?> text-[10px]"></i> <?= htmlspecialchars($row['type']) ?>
                                </span>
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs max-w-[220px] lg:max-w-[300px] truncate" title="<?= htmlspecialchars($row['subject']) ?>">
                                    <?= htmlspecialchars($row['subject']) ?>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-black text-[10px] shrink-0 border border-slate-200 dark:border-slate-600">
                                        <?= strtoupper(substr($row['company'], 0, 1)) ?>
                                    </div>
                                    <div class="font-bold text-slate-700 dark:text-slate-300 text-[11px] truncate max-w-[150px]" title="<?= htmlspecialchars($row['company']) ?>">
                                        <?= htmlspecialchars($row['company']) ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <?php if($row['assigned_name']): ?>
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
                                        <div class="w-4 h-4 rounded-full overflow-hidden shrink-0 bg-slate-100">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['assigned_name']) ?>&background=random&size=32" alt="Avatar" class="w-full h-full object-cover">
                                        </div>
                                        <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap"><?= htmlspecialchars($row['assigned_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/50 text-slate-400 dark:text-slate-500">
                                        <i class="ph-bold ph-user-minus text-[10px]"></i>
                                        <span class="text-[9px] font-bold uppercase tracking-widest">Unassigned</span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-4 align-top text-center">
                                <?php 
                                    $st = strtolower($row['status']);
                                    $sStyle = isset($status_styles[$st]) ? $status_styles[$st] : $status_styles['closed'];
                                    $sIcon  = isset($status_icons[$st]) ? $status_icons[$st] : $status_icons['closed'];
                                ?>
                                <span class="inline-flex items-center justify-center gap-1 px-2.5 py-1 rounded-lg border text-[9px] font-black uppercase tracking-widest w-24 <?= $sStyle ?>">
                                    <i class="ph-fill <?= $sIcon ?> text-[11px]"></i> <?= htmlspecialchars($st) ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top text-center">
                                <a href="view_ticket.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-600 text-slate-600 hover:text-white dark:bg-slate-700 dark:hover:bg-indigo-600 dark:text-slate-300 dark:hover:text-white transition-all shadow-sm active:scale-95" title="Manage Ticket">
                                    <i class="ph-bold ph-arrow-right text-sm"></i>
                                </a>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-ticket text-3xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mb-0.5">Tidak Ada Data</h4>
                                    <p class="text-[11px] font-medium">Data ticket tidak ditemukan dengan filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($result && $result->num_rows > 0): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Menampilkan total <?= $result->num_rows ?> tiket.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Skrip sederhana untuk Toggle Filter Section agar interaktif
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