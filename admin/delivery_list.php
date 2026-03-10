<?php
$page_title = "Delivery List";
include 'includes/header.php';
include 'includes/sidebar.php';
require_once '../config/database.php';

// --- 1. PREPARE FILTER DATA ---
// Catatan: Karena project_name diganti logika jadi client_name di form baru, filter disesuaikan.
$opt_clients = $conn->query("SELECT DISTINCT receiver_company as client_name FROM deliveries ORDER BY receiver_company ASC");
$opt_couriers = $conn->query("SELECT DISTINCT courier_name FROM deliveries WHERE courier_name != '' ORDER BY courier_name ASC");

// --- 2. HANDLE FILTER LOGIC ---
$search_track = isset($_GET['search_track']) ? $_GET['search_track'] : '';
$filter_client = isset($_GET['filter_client']) ? $_GET['filter_client'] : '';
$filter_courier = isset($_GET['filter_courier']) ? $_GET['filter_courier'] : '';

$where_clause = "WHERE 1=1";

if (!empty($search_track)) {
    $safe_track = $conn->real_escape_string($search_track);
    $where_clause .= " AND tracking_number LIKE '%$safe_track%'";
}
if (!empty($filter_client)) {
    $safe_client = $conn->real_escape_string($filter_client);
    $where_clause .= " AND receiver_company = '$safe_client'";
}
if (!empty($filter_courier)) {
    $safe_cour = $conn->real_escape_string($filter_courier);
    $where_clause .= " AND courier_name = '$safe_cour'";
}

// --- 3. MAIN QUERY ---
$sql = "SELECT * FROM deliveries $where_clause ORDER BY delivery_date DESC";
$result = $conn->query($sql);
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 animate-fade-in-up">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Delivery Workflow</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Daftar pengajuan pengiriman dari IT dan pemrosesan logistik oleh HR.</p>
        </div>
        <a href="delivery_form.php" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 active:scale-95">
            <i class="ph-bold ph-plus text-lg"></i> New Request
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="p-5">
            <form method="GET" action="delivery_list.php">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Search Tracking</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="search_track" class="w-full pl-10 pr-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white transition-all placeholder-slate-400" placeholder="Nomor Resi..." value="<?= htmlspecialchars($search_track) ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Client Name</label>
                        <div class="relative">
                            <select name="filter_client" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white appearance-none cursor-pointer">
                                <option value="">Semua Client</option>
                                <?php if($opt_clients && $opt_clients->num_rows > 0) { while($p = $opt_clients->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($p['client_name']) ?>" <?= ($filter_client == $p['client_name']) ? 'selected' : '' ?>><?= htmlspecialchars($p['client_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Courier</label>
                        <div class="relative">
                            <select name="filter_courier" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white appearance-none cursor-pointer">
                                <option value="">Semua Kurir</option>
                                <?php if($opt_couriers && $opt_couriers->num_rows > 0) { while($c = $opt_couriers->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($c['courier_name']) ?>" <?= ($filter_courier == $c['courier_name']) ? 'selected' : '' ?>><?= strtoupper($c['courier_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl transition-colors text-sm shadow-sm active:scale-95">Filter</button>
                        <?php if(!empty($search_track) || !empty($filter_client) || !empty($filter_courier)): ?>
                            <a href="delivery_list.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 font-bold py-2.5 px-4 rounded-xl transition-colors border border-rose-100 flex items-center justify-center" title="Reset Filters"><i class="ph-bold ph-arrows-counter-clockwise text-lg"></i></a>
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
                        <th class="px-6 py-4">Request Info (IT)</th>
                        <th class="px-6 py-4">Item Details</th>
                        <th class="px-6 py-4">Logistic Process (HR)</th>
                        <th class="px-6 py-4 text-center">Workflow Status</th>
                        <th class="px-6 py-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-sm">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <?php
                            // Logika Workflow Xmind (IT Request vs HR Processed)
                            $hasTracking = !empty($row['tracking_number']);
                            $clientName = !empty($row['receiver_company']) ? $row['receiver_company'] : 'Unknown Client';
                        ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-6 py-4 align-top">
                                <div class="font-bold text-slate-800 dark:text-white text-sm mb-1"><?= htmlspecialchars($clientName) ?></div>
                                <div class="text-[10px] text-slate-500 font-medium flex items-center gap-1.5 mb-2">
                                    <i class="ph-fill ph-calendar-blank"></i> Req: <?= date('d M Y', strtotime($row['delivery_date'])) ?>
                                </div>
                                <div class="flex gap-2">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-indigo-50 text-indigo-600 border border-indigo-100 dark:bg-indigo-500/10 dark:border-indigo-500/20" title="Invoice Attached"><i class="ph-bold ph-paperclip"></i> INV</span>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-sky-50 text-sky-600 border border-sky-100 dark:bg-sky-500/10 dark:border-sky-500/20" title="PO Attached"><i class="ph-bold ph-paperclip"></i> PO</span>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-col items-start gap-1">
                                    <p class="font-bold text-slate-800 dark:text-white text-sm">
                                        <?= htmlspecialchars($row['item_name']) ?> 
                                        <span class="text-[10px] font-black text-slate-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded ml-1">x<?= $row['qty'] ?></span>
                                    </p>
                                    <?php if(!empty($row['data_package'])): ?>
                                        <p class="text-xs font-medium text-slate-500"><i class="ph-fill ph-database text-slate-400"></i> <?= htmlspecialchars($row['data_package']) ?></p>
                                    <?php else: ?>
                                        <p class="text-xs font-medium text-slate-400 italic">No Data Pkg</p>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <?php if($hasTracking): ?>
                                    <div class="flex items-start gap-2 mb-1.5">
                                        <i class="ph-fill ph-map-pin text-emerald-500 mt-0.5"></i>
                                        <div class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                            PIC: <span class="font-bold"><?= htmlspecialchars($row['receiver_name']) ?></span><br>
                                            <span class="text-[10px] text-slate-400"><?= htmlspecialchars($row['receiver_phone']) ?></span>
                                        </div>
                                    </div>
                                    <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg p-2 inline-block w-full">
                                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 flex items-center gap-1">
                                            <i class="ph-fill ph-truck"></i> <?= htmlspecialchars($row['courier_name']) ?>
                                        </div>
                                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-xs"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-xl text-center">
                                        <i class="ph-bold ph-warning text-amber-500 text-xl mb-1"></i>
                                        <p class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-widest">Waiting HR Process</p>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 align-middle text-center">
                                <?php if(!$hasTracking): ?>
                                    <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30 text-[10px] font-black uppercase tracking-wider w-full shadow-sm">
                                        <i class="ph-bold ph-hourglass-high animate-pulse text-sm"></i> Pending
                                    </span>
                                <?php elseif($row['delivered_date']): ?>
                                    <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30 text-[10px] font-black uppercase tracking-wider w-full shadow-sm">
                                        <i class="ph-bold ph-check-circle text-sm"></i> Delivered
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30 text-[10px] font-black uppercase tracking-wider w-full shadow-sm">
                                        <i class="ph-fill ph-truck text-sm"></i> On Going
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 align-middle text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if($hasTracking): ?>
                                        <button onclick="trackResi('<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')" class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition-colors flex items-center justify-center border border-indigo-200 dark:border-indigo-500/20 shadow-sm" title="Track Live Resi">
                                            <i class="ph-bold ph-crosshair text-base"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="viewDetail(<?= $row['id'] ?>)" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition-colors flex items-center justify-center border border-slate-200 dark:border-slate-600 shadow-sm" title="Detail Data">
                                        <i class="ph-bold ph-file-text text-base"></i>
                                    </button>
                                </div>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-package text-3xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mb-0.5">Belum Ada Request</h4>
                                    <p class="text-xs font-medium">Data pengiriman / request IT tidak ditemukan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="trackingModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0 rounded-t-3xl">
            <h3 class="text-base font-black text-slate-800 dark:text-white flex items-center gap-2"><i class="ph-fill ph-truck text-indigo-500 text-xl"></i> Live Shipment Status</h3>
            <button onclick="closeModal('trackingModal')" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="ph-bold ph-x text-lg"></i></button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 bg-slate-50 dark:bg-slate-900/50 rounded-b-3xl" id="trackingResult"></div>
    </div>
</div>

<div id="detailModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0 rounded-t-3xl">
            <h3 class="text-base font-black text-slate-800 dark:text-white flex items-center gap-2"><i class="ph-fill ph-file-text text-slate-500 text-xl"></i> Delivery Details</h3>
            <button onclick="closeModal('detailModal')" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="ph-bold ph-x text-lg"></i></button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 bg-slate-50 dark:bg-slate-900/50 rounded-b-3xl" id="detailResult"></div>
    </div>
</div>

<script>
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
        box.classList.add('scale-95', 'opacity-0');
        modal.classList.add('opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); }, 300);
    }

    function trackResi(resi, kurir) {
        if(!resi) return alert('Nomor resi tidak tersedia.');
        openModal('trackingModal');
        document.getElementById('trackingResult').innerHTML = `<div class="text-center py-10"><i class="ph-bold ph-spinner animate-spin text-4xl text-indigo-500 mb-2"></i><p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Connecting to API...</p></div>`;
        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`)
            .then(res => res.text()).then(data => document.getElementById('trackingResult').innerHTML = data)
            .catch(err => document.getElementById('trackingResult').innerHTML = `<div class="text-center text-rose-500 py-10 font-bold">Gagal memuat data API.</div>`);
    }

    function viewDetail(id) {
        openModal('detailModal');
        document.getElementById('detailResult').innerHTML = `<div class="text-center py-10"><i class="ph-bold ph-spinner animate-spin text-4xl text-slate-500 mb-2"></i><p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Loading Data...</p></div>`;
        const fd = new FormData(); fd.append('id', id);
        fetch('ajax_get_delivery.php', { method: 'POST', body: fd })
            .then(res => res.text()).then(data => document.getElementById('detailResult').innerHTML = data)
            .catch(err => document.getElementById('detailResult').innerHTML = `<div class="text-center text-rose-500 py-10 font-bold">Gagal memuat data.</div>`);
    }
</script>

<?php include 'includes/footer.php'; ?>