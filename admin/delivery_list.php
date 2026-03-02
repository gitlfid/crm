<?php
$page_title = "Delivery List";
include 'includes/header.php';
include 'includes/sidebar.php';
require_once '../config/database.php';

// --- 1. PREPARE FILTER DATA (Dropdown Options) ---
$opt_projects = $conn->query("SELECT DISTINCT project_name FROM deliveries WHERE project_name IS NOT NULL AND project_name != '' ORDER BY project_name ASC");
$opt_couriers = $conn->query("SELECT DISTINCT courier_name FROM deliveries ORDER BY courier_name ASC");
$opt_receivers = $conn->query("SELECT DISTINCT receiver_name FROM deliveries ORDER BY receiver_name ASC");

// --- 2. HANDLE FILTER LOGIC ---
$search_track = isset($_GET['search_track']) ? $_GET['search_track'] : '';
$filter_project = isset($_GET['filter_project']) ? $_GET['filter_project'] : '';
$filter_courier = isset($_GET['filter_courier']) ? $_GET['filter_courier'] : '';
$filter_receiver = isset($_GET['filter_receiver']) ? $_GET['filter_receiver'] : '';

$where_clause = "WHERE 1=1";

if (!empty($search_track)) {
    $safe_track = $conn->real_escape_string($search_track);
    $where_clause .= " AND tracking_number LIKE '%$safe_track%'";
}

if (!empty($filter_project)) {
    $safe_proj = $conn->real_escape_string($filter_project);
    $where_clause .= " AND project_name = '$safe_proj'";
}

if (!empty($filter_courier)) {
    $safe_cour = $conn->real_escape_string($filter_courier);
    $where_clause .= " AND courier_name = '$safe_cour'";
}

if (!empty($filter_receiver)) {
    $safe_recv = $conn->real_escape_string($filter_receiver);
    $where_clause .= " AND receiver_name = '$safe_recv'";
}

// --- 3. MAIN QUERY ---
$sql = "SELECT * FROM deliveries $where_clause ORDER BY delivery_date DESC";
$result = $conn->query($sql);
?>

<style>
    /* Animasi Halus */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 animate-fade-in-up">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Delivery Management</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Monitor status pengiriman, rute logistik, dan lacak resi.</p>
        </div>
        <a href="delivery_form.php" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 active:scale-95">
            <i class="ph-bold ph-plus text-lg"></i> Input Delivery
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="p-5">
            <form method="GET" action="delivery_list.php">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    
                    <div class="lg:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Search Tracking</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="search_track" class="w-full pl-10 pr-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="Nomor AWB/Resi..." value="<?= htmlspecialchars($search_track) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Project</label>
                        <div class="relative">
                            <select name="filter_project" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">Semua Project</option>
                                <?php 
                                if($opt_projects->num_rows > 0){
                                    $opt_projects->data_seek(0);
                                    while($p = $opt_projects->fetch_assoc()): 
                                ?>
                                    <option value="<?= htmlspecialchars($p['project_name']) ?>" <?= ($filter_project == $p['project_name']) ? 'selected' : '' ?>><?= htmlspecialchars($p['project_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Courier</label>
                        <div class="relative">
                            <select name="filter_courier" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">Semua Kurir</option>
                                <?php 
                                if($opt_couriers->num_rows > 0){
                                    $opt_couriers->data_seek(0);
                                    while($c = $opt_couriers->fetch_assoc()): 
                                ?>
                                    <option value="<?= htmlspecialchars($c['courier_name']) ?>" <?= ($filter_courier == $c['courier_name']) ? 'selected' : '' ?>><?= strtoupper($c['courier_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Receiver</label>
                        <div class="relative">
                            <select name="filter_receiver" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">Semua Penerima</option>
                                <?php 
                                if($opt_receivers->num_rows > 0){
                                    $opt_receivers->data_seek(0);
                                    while($r = $opt_receivers->fetch_assoc()): 
                                ?>
                                    <option value="<?= htmlspecialchars($r['receiver_name']) ?>" <?= ($filter_receiver == $r['receiver_name']) ? 'selected' : '' ?>><?= htmlspecialchars($r['receiver_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1 flex gap-2">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl transition-colors text-sm shadow-sm active:scale-95">
                            Filter
                        </button>
                        <?php if(!empty($search_track) || !empty($filter_project) || !empty($filter_courier) || !empty($filter_receiver)): ?>
                            <a href="delivery_list.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2.5 px-4 rounded-xl transition-colors text-sm text-center border border-rose-100 dark:border-rose-500/20 active:scale-95" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                    <tr>
                        <th class="px-6 py-4">Shipment Info</th>
                        <th class="px-6 py-4">Route (Sender &rarr; Receiver)</th>
                        <th class="px-6 py-4">Package & Project</th> 
                        <th class="px-6 py-4">Tracking (AWB)</th>
                        <th class="px-6 py-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-sm">
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-col gap-2">
                                    <div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Sent Date</div>
                                        <div class="font-bold text-slate-800 dark:text-white text-sm">
                                            <i class="ph-fill ph-calendar-blank text-slate-400 mr-1"></i> <?= date('d M Y', strtotime($row['delivery_date'])) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if($row['delivered_date']): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20 text-xs font-bold">
                                                <i class="ph-fill ph-check-circle"></i> <?= date('d M Y', strtotime($row['delivered_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20 text-xs font-bold">
                                                <i class="ph-bold ph-spinner animate-spin-slow"></i> In Transit
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-col gap-3">
                                    <div class="flex items-start gap-2.5">
                                        <i class="ph-fill ph-arrow-circle-up text-rose-500 text-lg mt-0.5"></i>
                                        <div>
                                            <p class="font-bold text-slate-800 dark:text-white text-sm leading-tight"><?= htmlspecialchars($row['sender_company']) ?></p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><i class="ph-fill ph-user text-slate-400"></i> <?= htmlspecialchars($row['sender_name']) ?></p>
                                        </div>
                                    </div>
                                    <div class="w-px h-3 bg-slate-200 dark:bg-slate-700 ml-2.5 -my-1.5"></div>
                                    <div class="flex items-start gap-2.5">
                                        <i class="ph-fill ph-map-pin text-emerald-500 text-lg mt-0.5"></i>
                                        <div>
                                            <p class="font-bold text-slate-800 dark:text-white text-sm leading-tight"><?= htmlspecialchars($row['receiver_company']) ?></p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><i class="ph-fill ph-user text-slate-400"></i> <?= htmlspecialchars($row['receiver_name']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-col items-start gap-2">
                                    <?php if(!empty($row['project_name'])): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 text-[10px] font-black uppercase tracking-widest border border-indigo-100 dark:border-indigo-500/20">
                                            <i class="ph-fill ph-folder text-sm"></i> <?= htmlspecialchars($row['project_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <p class="font-bold text-slate-800 dark:text-white text-sm">
                                            <?= htmlspecialchars($row['item_name']) ?> 
                                            <span class="text-xs font-black text-slate-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded ml-1">x<?= $row['qty'] ?></span>
                                        </p>
                                        <?php if(!empty($row['data_package'])): ?>
                                            <p class="text-xs font-medium text-slate-500 mt-1"><i class="ph-fill ph-database text-slate-400"></i> Pkg: <?= htmlspecialchars($row['data_package']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <?php if(!empty($row['tracking_number'])): ?>
                                    <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-3 inline-block">
                                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 flex items-center gap-1">
                                            <i class="ph-fill ph-truck"></i> <?= htmlspecialchars($row['courier_name']) ?>
                                        </div>
                                        <button onclick="trackResi('<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')" class="font-mono font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors text-sm flex items-center gap-1 group/btn" title="Click to Track">
                                            <?= htmlspecialchars($row['tracking_number']) ?>
                                            <i class="ph-bold ph-arrow-up-right opacity-0 group-hover/btn:opacity-100 transition-opacity transform group-hover/btn:translate-x-0.5 group-hover/btn:-translate-y-0.5"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 italic bg-slate-50 dark:bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-100 dark:border-slate-800">No Resi</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 align-top text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="trackResi('<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')" class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 dark:text-indigo-400 dark:hover:bg-indigo-500/10 dark:hover:border-indigo-500/30 flex items-center justify-center transition-all shadow-sm active:scale-95" title="Track Live">
                                        <i class="ph-bold ph-crosshair text-lg"></i>
                                    </button>
                                    <button onclick="viewDetail(<?= $row['id'] ?>)" class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-600 hover:bg-slate-50 hover:border-slate-300 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:border-slate-500 flex items-center justify-center transition-all shadow-sm active:scale-95" title="View Full Details">
                                        <i class="ph-bold ph-file-text text-lg"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-package text-4xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-lg mb-1">Tidak Ada Data</h4>
                                    <p class="text-sm font-medium">Data pengiriman tidak ditemukan dengan filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($result->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Menampilkan <?= $result->num_rows ?> hasil data pengiriman terbaru.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="trackingModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0 bg-slate-50/50 dark:bg-slate-800/50 rounded-t-3xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 flex items-center justify-center text-xl">
                    <i class="ph-fill ph-truck"></i>
                </div>
                <h3 class="text-lg font-extrabold text-slate-800 dark:text-white tracking-tight">Live Shipment Status</h3>
            </div>
            <button onclick="location.reload();" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 hover:bg-rose-100 text-slate-500 hover:text-rose-600 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-rose-500/20 dark:hover:text-rose-400 transition-colors">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-white dark:bg-slate-800 rounded-b-3xl text-sm" id="trackingResult">
            </div>
    </div>
</div>

<div id="detailModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0 bg-slate-50/50 dark:bg-slate-800/50 rounded-t-3xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 flex items-center justify-center text-xl">
                    <i class="ph-fill ph-file-text"></i>
                </div>
                <h3 class="text-lg font-extrabold text-slate-800 dark:text-white tracking-tight">Delivery Details</h3>
            </div>
            <button onclick="closeModal('detailModal')" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 hover:bg-slate-300 text-slate-500 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-slate-600 transition-colors">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-white dark:bg-slate-800 rounded-b-3xl text-sm" id="detailResult">
            </div>
    </div>
</div>

<style>
    /* Scrollbar minimalis untuk Modal */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<script>
    // --- MODAL HANDLERS ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
        }, 10);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.add('opacity-0');
        box.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // --- AJAX FUNCTIONS ---
    function trackResi(resi, kurir) {
        if(!resi) return alert('Nomor resi tidak tersedia.');
        openModal('trackingModal');
        
        document.getElementById('trackingResult').innerHTML = `
            <div class="flex flex-col items-center justify-center py-16 opacity-70">
                <i class="ph-bold ph-spinner-gap text-5xl animate-spin text-indigo-500 mb-4"></i>
                <p class="font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-xs">Connecting to Courier API...</p>
            </div>
        `;

        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('trackingResult').innerHTML = data;
            })
            .catch(err => {
                document.getElementById('trackingResult').innerHTML = `
                    <div class="p-5 bg-rose-50 text-rose-600 border border-rose-200 rounded-2xl flex items-center gap-3">
                        <i class="ph-fill ph-warning-circle text-3xl"></i> 
                        <div>
                            <p class="font-bold">Koneksi Gagal</p>
                            <p class="text-xs opacity-80 mt-0.5">Tidak dapat mengambil data dari kurir saat ini.</p>
                        </div>
                    </div>`;
            });
    }

    function viewDetail(id) {
        openModal('detailModal');

        document.getElementById('detailResult').innerHTML = `
            <div class="flex flex-col items-center justify-center py-16 opacity-70">
                <i class="ph-bold ph-spinner-gap text-5xl animate-spin text-slate-400 mb-4"></i>
                <p class="font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-xs">Loading detail data...</p>
            </div>
        `;

        const formData = new FormData();
        formData.append('id', id);

        fetch('ajax_get_delivery.php', { method: 'POST', body: formData })
            .then(response => response.text())
            .then(data => {
                document.getElementById('detailResult').innerHTML = data;
            })
            .catch(err => {
                document.getElementById('detailResult').innerHTML = `
                    <div class="p-5 bg-rose-50 text-rose-600 border border-rose-200 rounded-2xl flex items-center gap-3">
                        <i class="ph-fill ph-warning-circle text-3xl"></i> 
                        <div>
                            <p class="font-bold">Gagal Memuat</p>
                            <p class="text-xs opacity-80 mt-0.5">Terjadi kesalahan pada server.</p>
                        </div>
                    </div>`;
            });
    }
</script>

<?php include 'includes/footer.php'; ?>