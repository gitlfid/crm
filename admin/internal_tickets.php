<?php
$page_title = "Internal Tickets";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- 0. AMBIL DATA USER LOGIN ---
$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];
$current_username = $_SESSION['username'] ?? '';

// Ambil ID Divisi user saat ini
$uQuery = $conn->query("SELECT division_id FROM users WHERE id = $current_user_id");
$uData  = $uQuery->fetch_assoc();
$current_user_div = $uData['division_id'];

// --- 1. LOGIKA FILTER ---
$search_keyword  = isset($_GET['search']) ? $_GET['search'] : '';
$filter_division = isset($_GET['division']) ? $_GET['division'] : '';
$filter_status   = isset($_GET['status']) ? $_GET['status'] : '';

// --- 2. QUERY UTAMA ---
$sql = "SELECT t.*, 
               u.username as creator_name, 
               d.name as target_div_name, d.code as target_div_code,
               u2.username as pic_name
        FROM internal_tickets t 
        JOIN users u ON t.user_id = u.id 
        JOIN divisions d ON t.target_division_id = d.id 
        LEFT JOIN users u2 ON t.assigned_to = u2.id 
        WHERE 1=1";

// --- 3. FILTER PERMISSION ---
if ($current_role != 'admin') {
    if ($current_user_div) {
        $sql .= " AND (t.user_id = $current_user_id OR t.target_division_id = $current_user_div)";
    } else {
        $sql .= " AND t.user_id = $current_user_id";
    }
}

// --- 4. FILTER PENCARIAN ---
if (!empty($search_keyword)) {
    $safe_key = $conn->real_escape_string($search_keyword);
    $sql .= " AND (t.ticket_code LIKE '%$safe_key%' OR t.subject LIKE '%$safe_key%' OR u.username LIKE '%$safe_key%' OR u2.username LIKE '%$safe_key%')";
}
if (!empty($filter_division)) {
    $safe_div = intval($filter_division);
    $sql .= " AND t.target_division_id = $safe_div";
}
if (!empty($filter_status)) {
    $safe_stat = $conn->real_escape_string($filter_status);
    $sql .= " AND t.status = '$safe_stat'";
}

$sql .= " ORDER BY t.created_at DESC";
$result = $conn->query($sql);

$div_list = $conn->query("SELECT * FROM divisions");

// --- Helper Mapping untuk UI Tailwind ---
$status_styles = [
    'open'     => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
    'progress' => 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400',
    'hold'     => 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400',
    'closed'   => 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300',
    'canceled' => 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400'
];
$status_icons = [
    'open'     => 'ph-envelope-open',
    'progress' => 'ph-hourglass-high animate-pulse',
    'hold'     => 'ph-pause-circle',
    'closed'   => 'ph-check-circle',
    'canceled' => 'ph-x-circle'
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
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Internal Tickets</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Kelola tiket internal dan permintaan tugas antar divisi perusahaan.</p>
        </div>
        <a href="internal_create.php" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 active:scale-95 whitespace-nowrap">
            <i class="ph-bold ph-plus text-lg"></i> Buat Ticket
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
            <form method="GET">
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4 items-end">
                    
                    <div class="lg:col-span-2">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Kata Kunci</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="search" class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="Cari ID, Subject, User, atau PIC..." value="<?= htmlspecialchars($search_keyword) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Target Divisi</label>
                        <div class="relative">
                            <i class="ph-bold ph-buildings absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <select name="division" class="w-full pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
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
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Status</label>
                        <div class="relative">
                            <i class="ph-bold ph-list absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <select name="status" class="w-full pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">Semua Status</option>
                                <option value="open" <?= ($filter_status == 'open') ? 'selected' : '' ?>>Open</option>
                                <option value="progress" <?= ($filter_status == 'progress') ? 'selected' : '' ?>>In Progress</option>
                                <option value="hold" <?= ($filter_status == 'hold') ? 'selected' : '' ?>>Hold</option>
                                <option value="closed" <?= ($filter_status == 'closed') ? 'selected' : '' ?>>Closed</option>
                                <option value="canceled" <?= ($filter_status == 'canceled') ? 'selected' : '' ?>>Canceled</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1 flex gap-2">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold py-2 px-3 rounded-xl transition-colors text-xs shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                            Filter
                        </button>
                        <?php if(!empty($search_keyword) || !empty($filter_division) || !empty($filter_status)): ?>
                            <a href="internal_tickets.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2 px-3 rounded-xl transition-colors text-xs text-center border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
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
                        <th class="px-5 py-3.5 whitespace-nowrap">Subject</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Creator</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Target & Assignment</th>
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
                                    <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs max-w-[220px] lg:max-w-[300px] whitespace-normal line-clamp-2 leading-tight" title="<?= htmlspecialchars($row['subject']) ?>">
                                    <?= htmlspecialchars($row['subject']) ?>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-bold text-[10px] shrink-0 border border-slate-300 dark:border-slate-600">
                                        <?= strtoupper(substr($row['creator_name'], 0, 1)) ?>
                                    </div>
                                    <div class="font-bold text-[11px] truncate max-w-[120px] <?= ($row['creator_name'] == $current_username) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300' ?>" title="<?= htmlspecialchars($row['creator_name']) ?>">
                                        <?= htmlspecialchars($row['creator_name']) ?>
                                        <?= ($row['creator_name'] == $current_username) ? ' <span class="opacity-70 font-normal">(You)</span>' : '' ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="flex flex-col gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 w-fit">
                                        <i class="ph-fill ph-buildings text-slate-400"></i> <?= htmlspecialchars($row['target_div_name']) ?>
                                    </span>
                                    
                                    <?php if(!empty($row['pic_name'])): ?>
                                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg border border-indigo-100 dark:border-indigo-500/20 bg-indigo-50 dark:bg-indigo-500/10 w-fit">
                                            <i class="ph-fill ph-user-circle text-indigo-500"></i>
                                            <span class="text-[10px] font-bold text-indigo-700 dark:text-indigo-300 whitespace-nowrap"><?= htmlspecialchars($row['pic_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="inline-flex items-center gap-1 text-[10px] font-bold italic text-slate-400">
                                            <i class="ph-bold ph-minus-circle"></i> Unassigned
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top text-center">
                                <?php 
                                    $st = strtolower($row['status']);
                                    $sStyle = isset($status_styles[$st]) ? $status_styles[$st] : $status_styles['closed'];
                                    $sIcon  = isset($status_icons[$st]) ? $status_icons[$st] : $status_icons['closed'];
                                ?>
                                <span class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-[9px] font-black uppercase tracking-widest w-24 <?= $sStyle ?>">
                                    <i class="ph-fill <?= $sIcon ?> text-[11px]"></i> <?= htmlspecialchars($st) ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top text-center">
                                <a href="internal_view.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-600 text-slate-600 hover:text-white dark:bg-slate-700 dark:hover:bg-indigo-600 dark:text-slate-300 dark:hover:text-white transition-all shadow-sm active:scale-95" title="View Detail">
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
                                    <p class="text-[11px] font-medium">Tiket internal tidak ditemukan dengan filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($result && $result->num_rows > 0): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Menampilkan total <?= $result->num_rows ?> tiket internal.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Skrip untuk Interaksi Form Toggle Filter
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