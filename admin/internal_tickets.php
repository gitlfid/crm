<?php
$page_title = "Internal Tickets";
include 'includes/header.php';
include 'includes/sidebar.php';
// Pastikan path functions benar sesuai struktur direktori Anda
// include '../config/functions.php'; 

// --- 0. AMBIL DATA USER LOGIN ---
$current_user_id = $_SESSION['user_id'];
$current_role    = strtolower(trim($_SESSION['role']));
$current_username = $_SESSION['username'] ?? '';

// Ambil ID Divisi user saat ini
$uQuery = $conn->query("SELECT division_id FROM users WHERE id = $current_user_id");
$uData  = $uQuery->fetch_assoc();
$current_user_div = $uData['division_id'];

// --- 1. LOGIKA FILTER PENCARIAN ---
$search_keyword  = isset($_GET['search']) ? $_GET['search'] : '';
$filter_division = isset($_GET['division']) ? $_GET['division'] : '';
$filter_status   = isset($_GET['status']) ? $_GET['status'] : '';

// --- 2. BANGUN QUERY FILTER DASAR ---
$filter_query = "";

// Filter Permission (Role Standard hanya bisa melihat tiket miliknya atau divisinya)
if ($current_role != 'admin') {
    if ($current_user_div) {
        $filter_query .= " AND (t.user_id = $current_user_id OR t.target_division_id = $current_user_div)";
    } else {
        $filter_query .= " AND t.user_id = $current_user_id";
    }
}

// Filter Kata Kunci
if (!empty($search_keyword)) {
    $safe_key = $conn->real_escape_string($search_keyword);
    $filter_query .= " AND (t.ticket_code LIKE '%$safe_key%' OR t.subject LIKE '%$safe_key%' OR u.username LIKE '%$safe_key%' OR u2.username LIKE '%$safe_key%')";
}

// Filter Divisi
if (!empty($filter_division)) {
    $safe_div = intval($filter_division);
    $filter_query .= " AND t.target_division_id = $safe_div";
}

// --- 3. QUERY UTAMA DATA TABEL ---
$sql_main = "SELECT t.*, 
               u.username as creator_name, 
               d.name as target_div_name, d.code as target_div_code,
               u2.username as pic_name
        FROM internal_tickets t 
        JOIN users u ON t.user_id = u.id 
        JOIN divisions d ON t.target_division_id = d.id 
        LEFT JOIN users u2 ON t.assigned_to = u2.id 
        WHERE 1=1 " . $filter_query;

if (!empty($filter_status)) {
    $safe_stat = $conn->real_escape_string($filter_status);
    $sql_main .= " AND t.status = '$safe_stat'";
}
$sql_main .= " ORDER BY t.created_at DESC";
$result = $conn->query($sql_main);

$div_list = $conn->query("SELECT * FROM divisions");

// --- 4. QUERY STATISTIK DINAMIS (Untuk Summary Cards) ---
$sql_stats = "SELECT t.status, COUNT(*) as count 
              FROM internal_tickets t 
              JOIN users u ON t.user_id = u.id 
              LEFT JOIN users u2 ON t.assigned_to = u2.id 
              WHERE 1=1 " . $filter_query . " GROUP BY t.status";
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

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-orange-500/30">
                    <i class="ph-fill ph-buildings"></i>
                </div>
                Internal Tickets
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Kelola permintaan tugas dan koordinasi bantuan antar divisi perusahaan.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.reload()" class="group inline-flex items-center justify-center w-11 h-11 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-xl shadow-sm transition-all active:scale-95" title="Refresh Data">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
            <a href="internal_create.php" class="group inline-flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 px-6 rounded-xl shadow-md shadow-orange-500/20 transition-all active:scale-95 whitespace-nowrap">
                <i class="ph-bold ph-plus text-lg"></i> 
                <span>Buat Ticket Baru</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-ticket"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total</p>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['total']) ?></h4>
            </div>
        </div>
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-envelope-open"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-0.5">Open</p>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['open']) ?></h4>
            </div>
        </div>
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-hourglass-high"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-0.5">Progress</p>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['progress']) ?></h4>
            </div>
        </div>
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-check-circle"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-0.5">Closed</p>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['closed']) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-orange-500 text-lg"></i> Filter Data & Pencarian
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET">
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-12 gap-5 items-end">
                    
                    <div class="lg:col-span-4">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Kata Kunci</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-orange-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Cari ID, Subject, Pembuat..." value="<?= htmlspecialchars($search_keyword) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Target Divisi</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-orange-500 transition-colors"></i>
                            <select name="division" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Divisi</option>
                                <?php 
                                if($div_list->num_rows > 0) {
                                    $div_list->data_seek(0);
                                    while($d = $div_list->fetch_assoc()): 
                                ?>
                                    <option value="<?= $d['id'] ?>" <?= ($filter_division == $d['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Status</label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-orange-500 transition-colors"></i>
                            <select name="status" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Status</option>
                                <option value="open" <?= ($filter_status == 'open') ? 'selected' : '' ?>>Open</option>
                                <option value="progress" <?= ($filter_status == 'progress') ? 'selected' : '' ?>>In Progress</option>
                                <option value="hold" <?= ($filter_status == 'hold') ? 'selected' : '' ?>>Hold</option>
                                <option value="closed" <?= ($filter_status == 'closed') ? 'selected' : '' ?>>Closed</option>
                                <option value="canceled" <?= ($filter_status == 'canceled') ? 'selected' : '' ?>>Canceled</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-2 flex gap-3 h-[46px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-orange-600 dark:hover:bg-orange-700 text-white font-bold rounded-xl transition-all shadow-md shadow-slate-200 dark:shadow-orange-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-funnel"></i> Filter
                        </button>
                        <?php if(!empty($search_keyword) || !empty($filter_division) || !empty($filter_status)): ?>
                            <a href="internal_tickets.php" class="flex-none w-[46px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
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
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Informasi Tiket</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider min-w-[250px]">Subject</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Dibuat Oleh</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Target Divisi & PIC</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-orange-50 dark:bg-orange-500/10 text-orange-600 dark:text-orange-400 font-mono font-bold text-[11px] border border-orange-100 dark:border-orange-500/20 mb-2">
                                    <i class="ph-bold ph-hash"></i>
                                    <?= htmlspecialchars($row['ticket_code']) ?>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1.5">
                                    <i class="ph-fill ph-calendar-blank"></i>
                                    <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm line-clamp-2 leading-snug group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors" title="<?= htmlspecialchars($row['subject']) ?>">
                                    <?= htmlspecialchars($row['subject']) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-black text-sm uppercase shrink-0 border border-slate-200 dark:border-slate-600 shadow-sm">
                                        <?= strtoupper(substr($row['creator_name'], 0, 1)) ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-700 dark:text-slate-300 text-sm truncate max-w-[150px]">
                                            <?= htmlspecialchars($row['creator_name']) ?>
                                        </span>
                                        <?php if($row['creator_name'] == $current_username): ?>
                                            <span class="text-[10px] font-bold text-orange-500 uppercase tracking-wider mt-0.5">Anda</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="flex flex-col gap-2">
                                    <div class="font-bold text-slate-700 dark:text-slate-300 text-xs flex items-center gap-1.5">
                                        <i class="ph-fill ph-buildings text-orange-500"></i> <?= htmlspecialchars($row['target_div_name']) ?>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 mt-1">
                                        <?php if(!empty($row['pic_name'])): ?>
                                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1A222C] shadow-sm">
                                                <div class="w-5 h-5 rounded-full overflow-hidden shrink-0 bg-slate-100 ring-2 ring-white dark:ring-slate-800">
                                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['pic_name']) ?>&background=random&color=fff&size=64" alt="Avatar" class="w-full h-full object-cover">
                                                </div>
                                                <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap"><?= htmlspecialchars($row['pic_name']) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/30 text-slate-400 dark:text-slate-500">
                                                <i class="ph-bold ph-minus text-xs"></i>
                                                <span class="text-[10px] font-bold uppercase tracking-widest">Belum Ditugaskan</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
                                <a href="internal_view.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-orange-500 hover:border-orange-500 text-slate-600 hover:text-white dark:text-slate-300 dark:hover:bg-orange-500 dark:hover:border-orange-500 dark:hover:text-white transition-all shadow-sm active:scale-95 group/btn" title="Lihat Detail">
                                    <i class="ph-bold ph-arrow-right text-lg group-hover/btn:translate-x-0.5 transition-transform"></i>
                                </a>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-ticket text-5xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Tiket internal tidak ditemukan dengan filter saat ini.</p>
                                    <?php if(!empty($search_keyword) || !empty($filter_division) || !empty($filter_status)): ?>
                                        <a href="internal_tickets.php" class="mt-5 inline-flex items-center gap-2 bg-slate-800 dark:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors shadow-sm">
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
        
        <?php if($result && $result->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-center">
            <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest bg-white dark:bg-[#1A222C] px-5 py-2 rounded-full border border-slate-100 dark:border-slate-700 shadow-sm">
                Menampilkan Total <span class="text-orange-500 dark:text-orange-400 font-black mx-1"><?= $result->num_rows ?></span> Tiket
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