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

// Base Filter Logic
$filter_query = "";
if (!empty($filter_id)) {
    $safe_id = $conn->real_escape_string($filter_id);
    $filter_query .= " AND t.ticket_code LIKE '%$safe_id%'";
}
if (!empty($filter_company)) {
    $safe_company = $conn->real_escape_string($filter_company);
    $filter_query .= " AND t.company LIKE '%$safe_company%'";
}

// Tambahkan Filter ke Query Utama
$sql_main = $sql . $filter_query;
if (!empty($filter_status)) {
    $safe_status = $conn->real_escape_string($filter_status);
    $sql_main .= " AND t.status = '$safe_status'";
}

// Urutkan Query Utama
$sql_main .= " ORDER BY t.created_at DESC";
$result = $conn->query($sql_main);

// --- LOGIKA STATISTIK DINAMIS ---
// Mengambil statistik tiket berdasarkan filter pencarian (tanpa memfilter status agar kartu ringkasan tetap akurat)
$sql_stats = "SELECT status, COUNT(*) as count FROM tickets t WHERE 1=1 " . $filter_query . " GROUP BY status";
$res_stats = $conn->query($sql_stats);

$stats = ['total' => 0, 'open' => 0, 'progress' => 0, 'closed' => 0, 'other' => 0];
if($res_stats) {
    while($s = $res_stats->fetch_assoc()) {
        $st = strtolower($s['status']);
        $stats['total'] += $s['count'];
        if (isset($stats[$st])) {
            $stats[$st] += $s['count'];
        } else {
            $stats['other'] += $s['count'];
        }
    }
}

// --- Helper Mapping untuk UI Tailwind ---
$type_styles = [
    'support' => 'bg-indigo-50 text-indigo-600 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400',
    'payment' => 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400',
    'info'    => 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400'
];
$type_icons = [
    'support' => 'ph-lifebuoy',
    'payment' => 'ph-credit-card',
    'info'    => 'ph-info'
];

$status_styles = [
    'open'     => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20',
    'progress' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border-amber-200 dark:border-amber-500/20',
    'hold'     => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border-blue-200 dark:border-blue-500/20',
    'closed'   => 'bg-slate-50 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400 border-slate-200 dark:border-slate-600',
    'canceled' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200 dark:border-rose-500/20'
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

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-cyan-500/30">
                    <i class="ph-fill ph-headset"></i>
                </div>
                External Tickets
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Pusat manajemen dukungan pelanggan dan penyelesaian keluhan masuk.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='tickets.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh Data">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 flex items-center justify-center text-2xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-ticket"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['total']) ?></h4>
            </div>
        </div>
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-2xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-envelope-open"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Open</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['open']) ?></h4>
            </div>
        </div>
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 flex items-center justify-center text-2xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-hourglass-high"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Progress</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['progress']) ?></h4>
            </div>
        </div>
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400 flex items-center justify-center text-2xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-check-circle"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Closed</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['closed']) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-cyan-500 text-lg"></i> Filter Data Database
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-12 gap-4 items-end">
                    
                    <div class="lg:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">No Ticket</label>
                        <div class="relative group">
                            <i class="ph-bold ph-hash absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-cyan-500 transition-colors"></i>
                            <input type="text" name="search_id" class="w-full pl-11 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner uppercase" placeholder="e.g. LFID-..." value="<?= htmlspecialchars($filter_id) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-4">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Client / Perusahaan</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-cyan-500 transition-colors"></i>
                            <input type="text" name="filter_company" class="w-full pl-11 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Cari nama perusahaan..." value="<?= htmlspecialchars($filter_company) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Status Tiket</label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-cyan-500 transition-colors"></i>
                            <select name="filter_status" class="w-full pl-11 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
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

                    <div class="lg:col-span-2 flex gap-2 h-[42px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-cyan-600 dark:hover:bg-cyan-700 text-white font-bold rounded-xl transition-all shadow-md shadow-slate-200 dark:shadow-cyan-500/20 active:scale-95 flex items-center justify-center gap-1.5 text-sm">
                            <i class="ph-bold ph-funnel text-base"></i> Filter
                        </button>
                        <?php if(!empty($filter_id) || !empty($filter_status) || !empty($filter_company)): ?>
                            <a href="tickets.php" class="flex-none w-[42px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors duration-300 flex flex-col min-h-[500px] relative">
        
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-800/30">
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Tampilkan</span>
                <select id="pageSize" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-lg px-2 py-1 focus:ring-2 focus:ring-cyan-500/50 outline-none cursor-pointer">
                    <option value="10">10 Baris</option>
                    <option value="25">25 Baris</option>
                    <option value="50">50 Baris</option>
                    <option value="100">100 Baris</option>
                </select>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Data</span>
            </div>
            
            <div class="relative w-full sm:w-64">
                <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="liveSearchInput" onkeyup="liveSearchTable()" placeholder="Cari di tabel ini..." class="w-full pl-9 pr-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-cyan-500/50 outline-none dark:text-white transition-all shadow-sm">
            </div>
        </div>

        <div class="overflow-x-auto modern-scrollbar flex-grow pb-20">
            <table class="w-full text-left border-collapse table-fixed min-w-[1000px]">
                <thead class="bg-white dark:bg-[#24303F] border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-5 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest w-[16%]">Informasi Tiket</th>
                        <th class="px-5 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest w-[28%]">Subject & Tipe</th>
                        <th class="px-5 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest w-[22%]">Klien / Perusahaan</th>
                        <th class="px-5 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest w-[16%]">Ditugaskan Ke</th>
                        <th class="px-5 py-3.5 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest w-[10%]">Status</th>
                        <th class="px-5 py-3.5 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest w-[8%]">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="data-row hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-5 py-3 align-middle search-target">
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-mono font-bold text-[10px] border border-indigo-100 dark:border-indigo-500/20 mb-1.5 shadow-sm">
                                    <i class="ph-bold ph-hash"></i>
                                    <?= htmlspecialchars($row['ticket_code']) ?>
                                </div>
                                <div class="text-[9px] text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1 whitespace-nowrap">
                                    <i class="ph-fill ph-calendar-blank text-slate-400"></i>
                                    <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle search-target pr-4">
                                <?php 
                                    $t = strtolower($row['type']);
                                    $tStyle = isset($type_styles[$t]) ? $type_styles[$t] : $type_styles['info'];
                                    $tIcon  = isset($type_icons[$t]) ? $type_icons[$t] : $type_icons['info'];
                                ?>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-widest border mb-1.5 shadow-sm <?= $tStyle ?>">
                                    <i class="ph-fill <?= $tIcon ?>"></i> <?= htmlspecialchars($row['type']) ?>
                                </span>
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs line-clamp-2 leading-snug group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors" title="<?= htmlspecialchars($row['subject']) ?>">
                                    <?= htmlspecialchars($row['subject']) ?>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle search-target pr-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-slate-200 to-slate-100 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center text-slate-700 dark:text-slate-200 font-black text-xs uppercase shrink-0 shadow-inner border border-white dark:border-slate-600/50">
                                        <?= strtoupper(substr($row['company'], 0, 1)) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 dark:text-slate-200 text-xs truncate" title="<?= htmlspecialchars($row['company']) ?>">
                                            <?= htmlspecialchars($row['company']) ?>
                                        </p>
                                        <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400 truncate mt-0.5"><?= htmlspecialchars($row['name']) ?></p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle search-target">
                                <?php if($row['assigned_name']): ?>
                                    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1A222C] shadow-sm">
                                        <div class="w-5 h-5 rounded-full overflow-hidden shrink-0 bg-slate-100 ring-1 ring-slate-200 dark:ring-slate-700">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['assigned_name']) ?>&background=random&color=fff&size=64" alt="Avatar" class="w-full h-full object-cover">
                                        </div>
                                        <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap"><?= htmlspecialchars($row['assigned_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/30 text-slate-400 dark:text-slate-500">
                                        <i class="ph-bold ph-user-minus text-[10px]"></i>
                                        <span class="text-[9px] font-bold uppercase tracking-widest">Unassigned</span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-3 align-middle text-center search-target">
                                <?php 
                                    $st = strtolower($row['status']);
                                    $sStyle = isset($status_styles[$st]) ? $status_styles[$st] : $status_styles['closed'];
                                    $sIcon  = isset($status_icons[$st]) ? $status_icons[$st] : $status_icons['closed'];
                                ?>
                                <span class="inline-flex items-center justify-center gap-1 px-2 py-1 rounded-md border text-[9px] font-bold uppercase tracking-widest w-24 shadow-sm <?= $sStyle ?>">
                                    <i class="ph-bold <?= $sIcon ?> text-xs"></i> <?= htmlspecialchars($st) ?>
                                </span>
                            </td>

                            <td class="px-5 py-3 align-middle text-center">
                                <a href="view_ticket.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-cyan-600 hover:border-cyan-600 text-slate-500 hover:text-white dark:text-slate-300 dark:hover:bg-cyan-600 dark:hover:border-cyan-600 dark:hover:text-white transition-all shadow-sm active:scale-95 group/btn" title="Kelola Tiket">
                                    <i class="ph-bold ph-arrow-right text-base group-hover/btn:translate-x-0.5 transition-transform"></i>
                                </a>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="emptyRow">
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-ticket text-4xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Data tiket eksternal tidak ditemukan dengan filter saat ini.</p>
                                    <?php if(!empty($filter_id) || !empty($filter_status) || !empty($filter_company)): ?>
                                        <a href="tickets.php" class="mt-4 inline-flex items-center gap-2 bg-slate-800 dark:bg-slate-700 text-white px-4 py-2 rounded-xl font-bold text-xs hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors shadow-sm">
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

        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-between w-full mt-auto shrink-0 z-20" id="paginationControls">
            <div class="flex-1 flex justify-start">
                <button id="btnPrev" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="ph-bold ph-arrow-left"></i> Previous
                </button>
            </div>
            
            <div id="pageNumbers" class="flex-1 flex items-center justify-center gap-1.5 hidden sm:flex">
                </div>
            <div class="text-xs font-bold text-slate-500 sm:hidden" id="pageInfoMobile">
                Page 1
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
    // --- LIVE SEARCH (CLIENT SIDE) ---
    function liveSearchTable() {
        let input = document.getElementById("liveSearchInput").value.toLowerCase();
        let rows = document.querySelectorAll(".data-row");

        if(input.trim() !== '') {
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                if (text.includes(input)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
            document.getElementById("paginationControls").classList.add('hidden');
        } else {
            document.getElementById("paginationControls").classList.remove('hidden');
            if(typeof renderTable === 'function') renderTable();
        }
    }

    // --- PAGINATION LOGIC (Vanilla JS) ---
    document.addEventListener('DOMContentLoaded', () => {
        const rows = Array.from(document.querySelectorAll('#tableBody tr.data-row'));
        const totalRows = rows.length;
        
        if(totalRows === 0) return;

        const pageSizeSelect = document.getElementById('pageSize');
        const paginationInfo = document.getElementById('paginationInfo');
        const pageInfoMobile = document.getElementById('pageInfoMobile');
        const btnPrev = document.getElementById('btnPrev');
        const btnNext = document.getElementById('btnNext');
        const pageNumbersContainer = document.getElementById('pageNumbers');

        let currentPage = 1;
        let rowsPerPage = parseInt(pageSizeSelect.value);

        window.renderTable = function() {
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
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            
            paginationInfo.innerHTML = `Menampilkan <span class="text-cyan-600 dark:text-cyan-400 font-black">${start + 1} - ${currentEnd}</span> dari <span class="font-black text-slate-800 dark:text-white">${totalRows}</span>`;
            pageInfoMobile.innerText = `Page ${currentPage} of ${totalPages}`;

            updatePaginationButtons(totalPages);
        }

        function updatePaginationButtons(totalPages) {
            btnPrev.disabled = currentPage === 1;
            btnNext.disabled = currentPage === totalPages || totalPages === 0;

            pageNumbersContainer.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    const pageBtn = document.createElement('button');
                    pageBtn.innerText = i;
                    if (i === currentPage) {
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-black text-white bg-cyan-500 shadow-sm shadow-cyan-500/30 flex items-center justify-center transition-all";
                    } else {
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700 transition-all flex items-center justify-center";
                        pageBtn.onclick = () => { currentPage = i; window.renderTable(); };
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
            window.renderTable();
        });

        btnPrev.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; window.renderTable(); }
        });

        btnNext.addEventListener('click', () => {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            if (currentPage < totalPages) { currentPage++; window.renderTable(); }
        });

        window.renderTable();
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
</script>

<?php include 'includes/footer.php'; ?>