<?php
$page_title = "Dashboard Utama";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- 0. DATA USER ---
$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];
$uData = $conn->query("SELECT division_id, username FROM users WHERE id = $current_user_id")->fetch_assoc();
$current_div_id = $uData['division_id'];

// =================================================================================
// LOGIKA 1: ADMIN VIEW (FULL DATA)
// =================================================================================
if ($current_role == 'admin') {
    
    // A. STATISTIK EXTERNAL TICKETS
    $extStats = ['open'=>0, 'progress'=>0, 'closed'=>0, 'total'=>0];
    $sqlExt = "SELECT status, COUNT(*) as total FROM tickets GROUP BY status";
    $resExt = $conn->query($sqlExt);
    while($row = $resExt->fetch_assoc()) {
        $extStats[$row['status']] = $row['total'];
        $extStats['total'] += $row['total'];
    }

    // B. STATISTIK INTERNAL TICKETS
    $intStats = ['open'=>0, 'progress'=>0, 'closed'=>0, 'total'=>0];
    $sqlInt = "SELECT status, COUNT(*) as total FROM internal_tickets GROUP BY status";
    $resInt = $conn->query($sqlInt);
    while($row = $resInt->fetch_assoc()) {
        $intStats[$row['status']] = $row['total'];
        $intStats['total'] += $row['total'];
    }

    // C. GRAFIK TREND (EXTERNAL VS INTERNAL - 6 BULAN)
    $months = [];
    $dataTrendExt = [];
    $dataTrendInt = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $months[] = date('M Y', strtotime("-$i months"));
        
        $dataTrendExt[] = $conn->query("SELECT COUNT(*) as t FROM tickets WHERE DATE_FORMAT(created_at, '%Y-%m') = '$m'")->fetch_assoc()['t'];
        $dataTrendInt[] = $conn->query("SELECT COUNT(*) as t FROM internal_tickets WHERE DATE_FORMAT(created_at, '%Y-%m') = '$m'")->fetch_assoc()['t'];
    }

    // D. USER PERFORMANCE SUMMARY (UPDATED: INTERNAL ASSIGN)
    $userPerformances = [];
    $sqlPerf = "SELECT u.id, u.username, u.role,
                -- External Metrics
                (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id) as ext_assigned,
                (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id AND status='closed') as ext_closed,
                -- Internal Metrics
                (SELECT COUNT(*) FROM internal_tickets WHERE user_id = u.id) as int_created,
                (SELECT COUNT(*) FROM internal_tickets WHERE assigned_to = u.id) as int_assigned,
                (SELECT COUNT(*) FROM internal_tickets WHERE assigned_to = u.id AND status='closed') as int_closed
                FROM users u 
                WHERE u.role != 'custom'
                ORDER BY ext_assigned DESC, int_assigned DESC";
    $resPerf = $conn->query($sqlPerf);
} 

// =================================================================================
// LOGIKA 2: STANDARD VIEW (INTERNAL FOCUS)
// =================================================================================
else {
    $mySent = $conn->query("SELECT COUNT(*) as t FROM internal_tickets WHERE user_id = $current_user_id")->fetch_assoc()['t'];
    $myInbox = 0;
    if ($current_div_id) {
        $myInbox = $conn->query("SELECT COUNT(*) as t FROM internal_tickets WHERE target_division_id = $current_div_id")->fetch_assoc()['t'];
    }
    
    $myStatData = [0,0,0]; // Open, Progress, Closed
    $sqlStat = "SELECT status, COUNT(*) as t FROM internal_tickets WHERE user_id=$current_user_id OR target_division_id=$current_div_id GROUP BY status";
    $res = $conn->query($sqlStat);
    while($r = $res->fetch_assoc()){
        if($r['status']=='open') $myStatData[0] = $r['t'];
        if($r['status']=='progress') $myStatData[1] = $r['t'];
        if($r['status']=='closed') $myStatData[2] = $r['t'];
    }
}
?>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto overflow-y-auto min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors duration-300">
    
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Dashboard Overview</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Pantau metrik dan kinerja sistem Anda secara real-time.</p>
    </div>

    <?php if ($current_role == 'admin'): ?>
    
    <div class="mb-6">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="flex gap-4" aria-label="Tabs">
                <button onclick="switchTab('overview')" id="tab-btn-overview" class="tab-button active inline-flex items-center gap-2 py-4 px-1 border-b-2 font-bold text-sm transition-colors whitespace-nowrap border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400">
                    <i class="ph-bold ph-squares-four text-lg"></i> Global Overview
                </button>
                <button onclick="switchTab('external')" id="tab-btn-external" class="tab-button inline-flex items-center gap-2 py-4 px-1 border-b-2 font-medium text-sm transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300">
                    <i class="ph-bold ph-users text-lg"></i> External Tickets
                </button>
                <button onclick="switchTab('internal')" id="tab-btn-internal" class="tab-button inline-flex items-center gap-2 py-4 px-1 border-b-2 font-medium text-sm transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300">
                    <i class="ph-bold ph-buildings text-lg"></i> Internal Tickets
                </button>
            </nav>
        </div>
    </div>

    <div class="relative">

        <div id="panel-overview" class="tab-panel animate-fade-in block">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="lg:col-span-2 bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Traffic Tiket (6 Bulan Terakhir)</h3>
                    </div>
                    <div id="chart-main-trend" class="w-full h-[300px]"></div>
                </div>

                <div class="lg:col-span-1 flex flex-col gap-6">
                    <div class="bg-gradient-to-br from-indigo-500 to-blue-600 rounded-2xl shadow-lg shadow-indigo-500/30 p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform">
                        <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-indigo-100 font-medium tracking-wide text-sm uppercase">Total External</span>
                                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center backdrop-blur-sm">
                                    <i class="ph-fill ph-users text-xl"></i>
                                </div>
                            </div>
                            <h2 class="text-5xl font-extrabold tracking-tight"><?= $extStats['total'] ?></h2>
                            <p class="text-indigo-100 text-sm mt-2">Tiket dukungan pelanggan</p>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl shadow-lg shadow-amber-500/30 p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform">
                        <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-amber-100 font-medium tracking-wide text-sm uppercase">Total Internal</span>
                                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center backdrop-blur-sm">
                                    <i class="ph-fill ph-buildings text-xl"></i>
                                </div>
                            </div>
                            <h2 class="text-5xl font-extrabold tracking-tight"><?= $intStats['total'] ?></h2>
                            <p class="text-amber-100 text-sm mt-2">Tiket antar divisi perusahaan</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">User Performance Summary</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Memantau beban kerja admin dan penyelesaian tiket.</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr>
                                <th rowspan="2" class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider align-middle">Staff Name</th>
                                <th rowspan="2" class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider align-middle">Role</th>
                                <th colspan="2" class="px-6 py-3 bg-indigo-50/50 dark:bg-indigo-900/10 border-b border-indigo-100 dark:border-indigo-900/30 text-center text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">External (Customer)</th>
                                <th colspan="3" class="px-6 py-3 bg-amber-50/50 dark:bg-amber-900/10 border-b border-amber-100 dark:border-amber-900/30 text-center text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider border-l border-slate-200 dark:border-slate-700">Internal (Divisi)</th>
                            </tr>
                            <tr>
                                <th class="px-4 py-3 bg-indigo-50/30 dark:bg-indigo-900/5 border-b border-slate-200 dark:border-slate-700 text-center text-xs font-semibold text-indigo-500">Assigned</th>
                                <th class="px-4 py-3 bg-indigo-50/30 dark:bg-indigo-900/5 border-b border-slate-200 dark:border-slate-700 text-center text-xs font-semibold text-emerald-500">Closed</th>
                                <th class="px-4 py-3 bg-amber-50/30 dark:bg-amber-900/5 border-b border-slate-200 dark:border-slate-700 border-l border-slate-200 dark:border-slate-700 text-center text-xs font-semibold text-amber-600">Assigned</th>
                                <th class="px-4 py-3 bg-amber-50/30 dark:bg-amber-900/5 border-b border-slate-200 dark:border-slate-700 text-center text-xs font-semibold text-emerald-500">Closed</th>
                                <th class="px-4 py-3 bg-amber-50/30 dark:bg-amber-900/5 border-b border-slate-200 dark:border-slate-700 text-center text-xs font-semibold text-slate-500">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php while($u = $resPerf->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-bold text-xs uppercase">
                                            <?= substr($u['username'],0,1) ?>
                                        </div>
                                        <span class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($u['username']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        <?= ucfirst($u['role']) ?>
                                    </span>
                                </td>
                                
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 font-bold text-xs">
                                        <?= $u['ext_assigned'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center font-bold text-emerald-500"><?= $u['ext_closed'] ?></td>
                                
                                <td class="px-4 py-4 whitespace-nowrap text-center border-l border-slate-100 dark:border-slate-800">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 font-bold text-xs">
                                        <?= $u['int_assigned'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center font-bold text-emerald-500"><?= $u['int_closed'] ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-center text-slate-400 font-medium"><?= $u['int_created'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="panel-external" class="tab-panel hidden animate-fade-in">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-[#1A222C] p-5 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-ticket"></i></div>
                    <div><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total External</p><h4 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $extStats['total'] ?></h4></div>
                </div>
                <div class="bg-white dark:bg-[#1A222C] p-5 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-envelope-open"></i></div>
                    <div><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Open</p><h4 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $extStats['open'] ?></h4></div>
                </div>
                <div class="bg-white dark:bg-[#1A222C] p-5 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-spinner"></i></div>
                    <div><p class="text-sm font-medium text-slate-500 dark:text-slate-400">In Progress</p><h4 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $extStats['progress'] ?></h4></div>
                </div>
                <div class="bg-white dark:bg-[#1A222C] p-5 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-check-circle"></i></div>
                    <div><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Closed</p><h4 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $extStats['closed'] ?></h4></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4">Distribusi Status</h3>
                    <div id="chart-ext-pie" class="flex justify-center h-[320px]"></div>
                </div>
            </div>

        </div>

        <div id="panel-internal" class="tab-panel hidden animate-fade-in">
            
             <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-[#1A222C] p-5 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-buildings"></i></div>
                    <div><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Internal</p><h4 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $intStats['total'] ?></h4></div>
                </div>
                <div class="bg-white dark:bg-[#1A222C] p-5 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-envelope-open"></i></div>
                    <div><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Open</p><h4 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $intStats['open'] ?></h4></div>
                </div>
                <div class="bg-white dark:bg-[#1A222C] p-5 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-spinner"></i></div>
                    <div><p class="text-sm font-medium text-slate-500 dark:text-slate-400">In Progress</p><h4 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $intStats['progress'] ?></h4></div>
                </div>
                <div class="bg-white dark:bg-[#1A222C] p-5 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 flex items-center justify-center text-2xl shrink-0"><i class="ph-fill ph-check-circle"></i></div>
                    <div><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Closed</p><h4 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $intStats['closed'] ?></h4></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4">Distribusi Status (Internal)</h3>
                    <div id="chart-int-pie" class="flex justify-center h-[320px]"></div>
                </div>
            </div>

        </div>

    </div>

    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            
            <div class="bg-gradient-to-br from-indigo-500 to-blue-600 rounded-2xl shadow-lg p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform">
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
                <div class="relative z-10 flex items-center gap-5">
                    <div class="w-16 h-16 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shrink-0">
                        <i class="ph-bold ph-paper-plane-right text-3xl"></i>
                    </div>
                    <div>
                        <h5 class="text-indigo-100 font-medium text-sm uppercase tracking-wider mb-1">Tiket Dikirim</h5>
                        <h2 class="text-4xl font-extrabold tracking-tight"><?= $mySent ?></h2>
                        <p class="text-indigo-100/80 text-xs mt-1">Tiket yang Anda buat untuk divisi lain.</p>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-cyan-500 to-blue-500 rounded-2xl shadow-lg p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform">
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
                <div class="relative z-10 flex items-center gap-5">
                    <div class="w-16 h-16 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shrink-0">
                        <i class="ph-bold ph-tray text-3xl"></i>
                    </div>
                    <div>
                        <h5 class="text-cyan-100 font-medium text-sm uppercase tracking-wider mb-1">Tiket Masuk Divisi</h5>
                        <h2 class="text-4xl font-extrabold tracking-tight"><?= $myInbox ?></h2>
                        <p class="text-cyan-100/80 text-xs mt-1">Tiket dari divisi lain ke divisi Anda.</p>
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 p-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4">Status Tiket Anda & Divisi</h3>
                <div id="chart-std-status" class="flex justify-center h-[320px]"></div>
            </div>
        </div>
    <?php endif; ?>

</div>
<?php include 'includes/footer.php'; ?>

<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // Tab Switching Logic (Tailwind approach)
    function switchTab(tabId) {
        // Sembunyikan semua panel
        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.add('hidden');
            panel.classList.remove('block');
        });
        // Tampilkan panel yang dituju
        document.getElementById('panel-' + tabId).classList.remove('hidden');
        document.getElementById('panel-' + tabId).classList.add('block');

        // Reset styling semua tombol tab
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('border-indigo-600', 'text-indigo-600', 'dark:border-indigo-400', 'dark:text-indigo-400', 'font-bold');
            btn.classList.add('border-transparent', 'text-slate-500', 'font-medium');
        });
        
        // Aktifkan tombol yang di klik
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        activeBtn.classList.remove('border-transparent', 'text-slate-500', 'font-medium');
        activeBtn.classList.add('border-indigo-600', 'text-indigo-600', 'dark:border-indigo-400', 'dark:text-indigo-400', 'font-bold');
    }

    // Chart Check Dark Mode (Untuk warna tulisan grafik)
    const isDarkMode = document.documentElement.classList.contains('dark') || localStorage.getItem('color-theme') === 'dark';
    const textColor = isDarkMode ? '#94a3b8' : '#64748b'; // slate-400 vs slate-500

    // Common Font
    const fontFam = "'Inter', sans-serif";

    <?php if ($current_role == 'admin'): ?>
        
        // CHART 1: Area Trend
        var optionsMain = {
            series: [
                { name: 'External Tickets', data: <?php echo json_encode($dataTrendExt); ?> },
                { name: 'Internal Tickets', data: <?php echo json_encode($dataTrendInt); ?> }
            ],
            chart: { 
                height: 300, 
                type: 'area', 
                toolbar: { show: false },
                fontFamily: fontFam,
                background: 'transparent'
            },
            colors: ['#4F46E5', '#F59E0B'], // Indigo & Amber
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
            },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            xaxis: { 
                categories: <?php echo json_encode($months); ?>,
                labels: { style: { colors: textColor } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { style: { colors: textColor } }
            },
            grid: {
                borderColor: isDarkMode ? '#334155' : '#e2e8f0', // slate-700 vs slate-200
                strokeDashArray: 4,
            },
            legend: { position: 'top', horizontalAlign: 'right', labels: { colors: textColor } },
            theme: { mode: isDarkMode ? 'dark' : 'light' }
        };
        new ApexCharts(document.querySelector("#chart-main-trend"), optionsMain).render();

        // CHART 2: External Pie
        var optionsExtPie = {
            series: [<?= $extStats['open'] ?>, <?= $extStats['progress'] ?>, <?= $extStats['closed'] ?>, <?= $extStats['total'] - ($extStats['open']+$extStats['progress']+$extStats['closed']) ?>],
            chart: { type: 'donut', height: 320, fontFamily: fontFam, background: 'transparent' },
            labels: ['Open', 'Progress', 'Closed', 'Other'],
            colors: ['#10B981', '#0EA5E9', '#64748B', '#EF4444'], // Emerald, Light Blue, Slate, Red
            stroke: { show: false },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: { donut: { size: '75%', labels: { show: true, name: { color: textColor }, value: { color: isDarkMode ? '#fff' : '#0f172a', fontSize: '24px', fontWeight: 700 } } } }
            },
            legend: { position: 'bottom', labels: { colors: textColor } },
            theme: { mode: isDarkMode ? 'dark' : 'light' }
        };
        new ApexCharts(document.querySelector("#chart-ext-pie"), optionsExtPie).render();

        // CHART 3: Internal Pie
        var optionsIntPie = {
            series: [<?= $intStats['open'] ?>, <?= $intStats['progress'] ?>, <?= $intStats['closed'] ?>],
            chart: { type: 'donut', height: 320, fontFamily: fontFam, background: 'transparent' },
            labels: ['Open', 'Progress', 'Closed'],
            colors: ['#10B981', '#F59E0B', '#64748B'], // Emerald, Amber, Slate
            stroke: { show: false },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: { donut: { size: '75%', labels: { show: true, name: { color: textColor }, value: { color: isDarkMode ? '#fff' : '#0f172a', fontSize: '24px', fontWeight: 700 } } } }
            },
            legend: { position: 'bottom', labels: { colors: textColor } },
            theme: { mode: isDarkMode ? 'dark' : 'light' }
        };
        new ApexCharts(document.querySelector("#chart-int-pie"), optionsIntPie).render();

    <?php else: ?>
        
        // CHART 4: Standard User Pie
        var optionsStd = {
            series: [<?= $myStatData[0] ?>, <?= $myStatData[1] ?>, <?= $myStatData[2] ?>],
            chart: { type: 'donut', height: 320, fontFamily: fontFam, background: 'transparent' },
            labels: ['Open', 'Progress', 'Closed'],
            colors: ['#10B981', '#F59E0B', '#64748B'],
            stroke: { show: false },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: { donut: { size: '75%', labels: { show: true, name: { color: textColor }, value: { color: isDarkMode ? '#fff' : '#0f172a', fontSize: '24px', fontWeight: 700 } } } }
            },
            legend: { position: 'bottom', labels: { colors: textColor } },
            theme: { mode: isDarkMode ? 'dark' : 'light' }
        };
        new ApexCharts(document.querySelector("#chart-std-status"), optionsStd).render();
        
    <?php endif; ?>
</script>