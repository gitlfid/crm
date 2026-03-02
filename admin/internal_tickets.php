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
                <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400 flex items-center justify-center text-xl shadow-inner">
                    <i class="ph-bold ph-buildings"></i>
                </div>
                Internal Tickets
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Kelola permintaan dan koordinasi antar divisi perusahaan.</p>
        </div>
        <a href="internal_create.php" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
            <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
            <i class="ph-bold ph-plus text-xl relative z-10"></i> 
            <span class="relative z-10">Buat Ticket Baru</span>
        </a>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-lg"></i> Filter & Pencarian
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET">
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-12 gap-5 items-end">
                    
                    <div class="lg:col-span-5">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Kata Kunci</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Cari ID, Subject, User..." value="<?= htmlspecialchars($search_keyword) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Target Divisi</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <select name="division" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
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

                    <div class="lg:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Status</label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <select name="status" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua</option>
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
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-sm active:scale-95 flex items-center justify-center gap-2">
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
                <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Ticket Info</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider min-w-[250px]">Subject</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Creator</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Target & PIC</th>
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
                                    <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm line-clamp-2 leading-snug group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" title="<?= htmlspecialchars($row['subject']) ?>">
                                    <?= htmlspecialchars($row['subject']) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-slate-200 to-slate-100 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 font-bold text-sm uppercase shadow-inner">
                                        <?= strtoupper(substr($row['creator_name'], 0, 1)) ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-sm <?= ($row['creator_name'] == $current_username) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300' ?>" title="<?= htmlspecialchars($row['creator_name']) ?>">
                                            <?= htmlspecialchars($row['creator_name']) ?>
                                        </span>
                                        <?php if($row['creator_name'] == $current_username): ?>
                                            <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider">You</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="flex flex-col gap-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-md bg-orange-100 dark:bg-orange-500/20 flex items-center justify-center text-orange-600 dark:text-orange-400 shrink-0">
                                            <i class="ph-bold ph-buildings text-xs"></i>
                                        </div>
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                            <?= htmlspecialchars($row['target_div_name']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        <?php if(!empty($row['pic_name'])): ?>
                                            <div class="w-6 h-6 rounded-md bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                                                <i class="ph-bold ph-user text-xs"></i>
                                            </div>
                                            <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($row['pic_name']) ?></span>
                                        <?php else: ?>
                                            <div class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 dark:border-slate-700">
                                                <i class="ph-bold ph-minus text-xs"></i>
                                            </div>
                                            <span class="text-[11px] font-bold italic text-slate-400">Unassigned</span>
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
                                <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl border text-[10px] font-black uppercase tracking-wider w-28 <?= $sStyle ?>">
                                    <i class="ph-bold <?= $sIcon ?> text-sm"></i> <?= htmlspecialchars($st) ?>
                                </span>
                            </td>

                            <td class="px-6 py-5 align-middle text-center">
                                <a href="internal_view.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 hover:bg-indigo-600 text-slate-500 hover:text-white dark:bg-slate-800 dark:hover:bg-indigo-600 dark:text-slate-400 dark:hover:text-white transition-all shadow-sm active:scale-95 group/btn" title="Lihat Detail">
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
                                    <p class="text-sm font-medium">Tiket internal tidak ditemukan dengan filter saat ini.</p>
                                    <?php if(!empty($search_keyword) || !empty($filter_division) || !empty($filter_status)): ?>
                                        <a href="internal_tickets.php" class="mt-4 text-indigo-600 dark:text-indigo-400 font-bold text-sm hover:underline">Reset Filter</a>
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
                Total Data: <span class="text-indigo-500 ml-1"><?= $result->num_rows ?> Tiket</span>
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
                // Toggle Logic menggunakan Tailwind Classes
                if (body.classList.contains('hidden')) {
                    body.classList.remove('hidden');
                    // Tambahkan delay kecil agar efek display block ke opacity berfungsi jika dianimasikan lebih lanjut
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