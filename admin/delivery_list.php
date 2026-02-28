<?php
$page_title = "Delivery List";
include 'includes/header.php';
include 'includes/sidebar.php';
require_once '../config/database.php';

// --- 1. PREPARE FILTER DATA (Dropdown Options) ---
// Mengambil data unik untuk dropdown filter agar dinamis
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

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 animate-slide-up">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Delivery Management</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Monitor status pengiriman dan riwayat logistik.</p>
        </div>
        <a href="delivery_form.php" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5">
            <i class="ph-bold ph-plus text-lg"></i> Input Delivery
        </a>
    </div>

    <div class="bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 mb-6 animate-slide-up delay-100">
        <div class="p-5">
            <form method="GET" action="delivery_list.php">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    
                    <div class="lg:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Search Tracking</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" name="search_track" class="w-full pl-9 pr-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all" placeholder="Nomor Resi..." value="<?= htmlspecialchars($search_track) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Project</label>
                        <div class="relative">
                            <select name="filter_project" class="w-full pl-3 pr-8 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all">
                                <option value="">- All Projects -</option>
                                <?php 
                                if($opt_projects->num_rows > 0){
                                    $opt_projects->data_seek(0);
                                    while($p = $opt_projects->fetch_assoc()): 
                                ?>
                                    <option value="<?= $p['project_name'] ?>" <?= ($filter_project == $p['project_name']) ? 'selected' : '' ?>><?= $p['project_name'] ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Courier</label>
                        <div class="relative">
                            <select name="filter_courier" class="w-full pl-3 pr-8 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all">
                                <option value="">- All Couriers -</option>
                                <?php 
                                if($opt_couriers->num_rows > 0){
                                    $opt_couriers->data_seek(0);
                                    while($c = $opt_couriers->fetch_assoc()): 
                                ?>
                                    <option value="<?= $c['courier_name'] ?>" <?= ($filter_courier == $c['courier_name']) ? 'selected' : '' ?>><?= strtoupper($c['courier_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Receiver</label>
                        <div class="relative">
                            <select name="filter_receiver" class="w-full pl-3 pr-8 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all">
                                <option value="">- All Receivers -</option>
                                <?php 
                                if($opt_receivers->num_rows > 0){
                                    $opt_receivers->data_seek(0);
                                    while($r = $opt_receivers->fetch_assoc()): 
                                ?>
                                    <option value="<?= $r['receiver_name'] ?>" <?= ($filter_receiver == $r['receiver_name']) ? 'selected' : '' ?>><?= $r['receiver_name'] ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-1 flex gap-2">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl transition-colors text-sm">
                            Filter
                        </button>
                        <?php if(!empty($search_track) || !empty($filter_project) || !empty($filter_courier) || !empty($filter_receiver)): ?>
                            <a href="delivery_list.php" class="flex-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold py-2.5 px-4 rounded-xl transition-colors text-sm text-center">
                                Reset
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden animate-slide-up delay-200">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">
                    <tr>
                        <th class="px-6 py-4">Sent Date</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Project</th> 
                        <th class="px-6 py-4">Tracking Info</th>
                        <th class="px-6 py-4">Sender</th>
                        <th class="px-6 py-4">Receiver</th>
                        <th class="px-6 py-4">Item Name</th>
                        <th class="px-6 py-4 text-center">Qty</th>
                        <th class="px-6 py-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-sm">
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors">
                            
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300 font-medium">
                                <?= date('d M Y', strtotime($row['delivery_date'])) ?>
                            </td>
                            
                            <td class="px-6 py-4">
                                <?php if($row['delivered_date']): ?>
                                    <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                                        <div class="w-8 h-8 rounded-full bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center shrink-0">
                                            <i class="ph-fill ph-check-circle text-lg"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-xs"><?= date('d M Y', strtotime($row['delivered_date'])) ?></div>
                                            <div class="text-[10px] opacity-70"><?= date('H:i', strtotime($row['delivered_date'])) ?></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-bold border border-amber-200 dark:border-amber-500/20">
                                        <i class="ph-bold ph-spinner animate-spin-slow"></i> In Progress
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4">
                                <?php if(!empty($row['project_name'])): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 text-xs font-bold border border-indigo-100 dark:border-indigo-500/20">
                                        <i class="ph-fill ph-folder-open"></i> <?= htmlspecialchars($row['project_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex flex-col items-start gap-1">
                                    <button type="button" onclick="trackResi('<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')" class="text-indigo-600 dark:text-indigo-400 font-bold font-mono text-sm hover:underline flex items-center gap-1">
                                        <?= htmlspecialchars($row['tracking_number']) ?>
                                    </button>
                                    <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wide">
                                        <?= htmlspecialchars($row['courier_name']) ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($row['sender_name']) ?></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[150px]" title="<?= htmlspecialchars($row['sender_company']) ?>">
                                    <?= htmlspecialchars($row['sender_company']) ?>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($row['receiver_name']) ?></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[150px]" title="<?= htmlspecialchars($row['receiver_company']) ?>">
                                    <?= htmlspecialchars($row['receiver_company']) ?>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4">
                                <div class="text-slate-800 dark:text-slate-200 font-medium"><?= htmlspecialchars($row['item_name']) ?></div>
                                <?php if(!empty($row['data_package'])): ?>
                                    <div class="text-[10px] text-slate-500 mt-1"><span class="px-1.5 py-0.5 border border-slate-200 dark:border-slate-600 rounded"><?= htmlspecialchars($row['data_package']) ?></span></div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 font-bold text-slate-700 dark:text-slate-300 text-xs">
                                    <?= $row['qty'] ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="trackResi('<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')" class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-500 hover:text-white dark:bg-indigo-500/10 dark:text-indigo-400 dark:hover:bg-indigo-500 flex items-center justify-center transition-colors" title="Lacak Paket">
                                        <i class="ph-bold ph-crosshair"></i>
                                    </button>
                                    <button onclick="viewDetail(<?= $row['id'] ?>)" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-500 hover:text-white dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 flex items-center justify-center transition-colors" title="Lihat Detail">
                                        <i class="ph-bold ph-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <i class="ph-fill ph-package text-5xl mb-3 opacity-50"></i>
                                    <p class="text-sm font-medium">Data pengiriman tidak ditemukan dengan filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($result->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/30">
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Menampilkan hasil data pengiriman terbaru.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="trackingModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl mx-4">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="ph-fill ph-truck text-indigo-500 text-xl"></i> Shipment Status
            </h3>
            <button onclick="location.reload();" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 dark:bg-slate-700 dark:text-slate-400 transition-colors">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto bg-slate-50 dark:bg-slate-900/50 flex-1 rounded-b-2xl text-sm dark:text-slate-300" id="trackingResult">
            </div>
    </div>
</div>

<div id="detailModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl mx-4">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="ph-fill ph-file-text text-indigo-500 text-xl"></i> Delivery Details
            </h3>
            <button onclick="closeModal('detailModal')" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-slate-600 transition-colors">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 dark:text-slate-300" id="detailResult">
            </div>
    </div>
</div>

<script>
    // --- CUSTOM MODAL HANDLERS ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.remove('hidden');
        // Small delay to allow display to apply before firing animation
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

    // --- ORIGINAL AJAX FUNCTIONS (Wrapped in new UI) ---
    function trackResi(resi, kurir) {
        openModal('trackingModal');
        
        document.getElementById('trackingResult').innerHTML = `
            <div class="flex flex-col items-center justify-center py-10 opacity-70">
                <i class="ph-bold ph-spinner-gap text-4xl animate-spin text-indigo-500 mb-3"></i>
                <p class="font-medium text-slate-500 dark:text-slate-400">Connecting to Courier API...</p>
            </div>
        `;

        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('trackingResult').innerHTML = data;
            })
            .catch(err => {
                document.getElementById('trackingResult').innerHTML = `
                    <div class="p-4 bg-rose-50 text-rose-600 border border-rose-200 rounded-xl flex items-center gap-2">
                        <i class="ph-bold ph-warning-circle text-lg"></i> Gagal memuat data tracking.
                    </div>`;
            });
    }

    function viewDetail(id) {
        openModal('detailModal');

        document.getElementById('detailResult').innerHTML = `
            <div class="flex flex-col items-center justify-center py-10 opacity-70">
                <i class="ph-bold ph-spinner-gap text-4xl animate-spin text-indigo-500 mb-3"></i>
                <p class="font-medium text-slate-500 dark:text-slate-400">Loading details...</p>
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
                    <div class="p-4 bg-rose-50 text-rose-600 border border-rose-200 rounded-xl flex items-center gap-2">
                        <i class="ph-bold ph-warning-circle text-lg"></i> Gagal memuat detail data.
                    </div>`;
            });
    }
</script>

<?php include 'includes/footer.php'; ?>