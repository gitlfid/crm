<?php
$page_title = "Dashboard Utama";
include 'includes/header.php';
include 'includes/sidebar.php';
// Pastikan path fungsi sudah benar sesuai struktur Anda
// include '../config/functions.php'; 

// --- 0. DATA USER ---
$current_user_id = $_SESSION['user_id'];
$current_role    = strtolower(trim($_SESSION['role']));
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
    if($resExt) {
        while($row = $resExt->fetch_assoc()) {
            $extStats[$row['status']] = $row['total'];
            $extStats['total'] += $row['total'];
        }
    }

    // B. STATISTIK INTERNAL TICKETS
    $intStats = ['open'=>0, 'progress'=>0, 'closed'=>0, 'total'=>0];
    $sqlInt = "SELECT status, COUNT(*) as total FROM internal_tickets GROUP BY status";
    $resInt = $conn->query($sqlInt);
    if($resInt) {
        while($row = $resInt->fetch_assoc()) {
            $intStats[$row['status']] = $row['total'];
            $intStats['total'] += $row['total'];
        }
    }

    // C. GRAFIK TREND (EXTERNAL VS INTERNAL - 6 BULAN)
    $months = [];
    $dataTrendExt = [];
    $dataTrendInt = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $months[] = date('M Y', strtotime("-$i months"));
        
        $tExt = $conn->query("SELECT COUNT(*) as t FROM tickets WHERE DATE_FORMAT(created_at, '%Y-%m') = '$m'")->fetch_assoc()['t'];
        $tInt = $conn->query("SELECT COUNT(*) as t FROM internal_tickets WHERE DATE_FORMAT(created_at, '%Y-%m') = '$m'")->fetch_assoc()['t'];
        
        $dataTrendExt[] = $tExt ?? 0;
        $dataTrendInt[] = $tInt ?? 0;
    }

    // D. USER PERFORMANCE SUMMARY
    $sqlPerf = "SELECT u.id, u.username, u.role,
                (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id) as ext_assigned,
                (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id AND status='closed') as ext_closed,
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
    if($res) {
        while($r = $res->fetch_assoc()){
            if($r['status']=='open') $myStatData[0] = $r['t'];
            if($r['status']=='progress') $myStatData[1] = $r['t'];
            if($r['status']=='closed') $myStatData[2] = $r['t'];
        }
    }
}
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
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight">Dashboard Overview</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Pantau metrik dan kinerja operasional hari ini.</p>
        </div>
        <div class="px-4 py-2 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm inline-flex items-center gap-2">
            <i class="ph-bold ph-calendar-blank text-indigo-500 text-lg"></i>
            <span class="text-sm font-bold text-slate-600 dark:text-slate-300"><?= date('l, d F Y') ?></span>
        </div>
    </div>

    <?php if ($current_role == 'admin'): ?>
    
    <div class="inline-flex bg-slate-200/50 dark:bg-slate-800/50 p-1.5 rounded-2xl shadow-inner backdrop-blur-sm overflow-x-auto max-w-full modern-scrollbar">
        <button onclick="switchTab('overview')" id="tab-btn-overview" class="tab-button active flex items-center gap-2 py-2.5 px-6 rounded-xl font-bold text-sm transition-all duration-300 bg-white dark:bg-indigo-600 shadow-sm text-indigo-600 dark:text-white">
            <i class="ph-bold ph-squares-four text-lg"></i> Overview
        </button>
        <button onclick="switchTab('external')" id="tab-btn-external" class="tab-button flex items-center gap-2 py-2.5 px-6 rounded-xl font-semibold text-sm transition-all duration-300 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50">
            <i class="ph-bold ph-headset text-lg"></i> External
        </button>
        <button onclick="switchTab('internal')" id="tab-btn-internal" class="tab-button flex items-center gap-2 py-2.5 px-6 rounded-xl font-semibold text-sm transition-all duration-300 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50">
            <i class="ph-bold ph-buildings text-lg"></i> Internal
        </button>
    </div>

    <div class="relative mt-2">

        <div id="panel-overview" class="tab-panel block transition-opacity duration-300">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-3xl shadow-lg shadow-indigo-500/20 p-6 sm:p-8 text-white relative overflow-hidden group hover:shadow-indigo-500/40 transition-all duration-300 hover:-translate-y-1">
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                    <div class="absolute right-6 bottom-6 opacity-20 group-hover:opacity-40 transition-opacity duration-300">
                        <i class="ph-fill ph-headset text-8xl"></i>
                    </div>
                    <div class="relative z-10">
                        <span class="inline-block px-3 py-1 bg-white/20 backdrop-blur-md rounded-lg font-bold tracking-wider text-xs uppercase mb-4 shadow-sm border border-white/10">External Tickets</span>
                        <h2 class="text-6xl font-black tracking-tighter mb-1"><?= $extStats['total'] ?></h2>
                        <p class="text-indigo-100 font-medium opacity-90">Total tiket dukungan pelanggan</p>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-500 to-amber-600 rounded-3xl shadow-lg shadow-orange-500/20 p-6 sm:p-8 text-white relative overflow-hidden group hover:shadow-orange-500/40 transition-all duration-300 hover:-translate-y-1">
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                    <div class="absolute right-6 bottom-6 opacity-20 group-hover:opacity-40 transition-opacity duration-300">
                        <i class="ph-fill ph-buildings text-8xl"></i>
                    </div>
                    <div class="relative z-10">
                        <span class="inline-block px-3 py-1 bg-white/20 backdrop-blur-md rounded-lg font-bold tracking-wider text-xs uppercase mb-4 shadow-sm border border-white/10">Internal Tickets</span>
                        <h2 class="text-6xl font-black tracking-tighter mb-1"><?= $intStats['total'] ?></h2>
                        <p class="text-orange-100 font-medium opacity-90">Total tiket lintas divisi perusahaan</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 mb-6 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Trend Traffic (6 Bulan)</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Perbandingan tiket masuk per bulan.</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                        <i class="ph-bold ph-chart-line-up text-xl"></i>
                    </div>
                </div>
                <div id="chart-main-trend" class="w-full h-[320px]"></div>
            </div>

            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors">
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">User Performance</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Metrik penyelesaian tiket oleh admin.</p>
                    </div>
                </div>
                
                <div class="overflow-x-auto modern-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 dark:bg-slate-800/30">
                                <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider">Staff Name</th>
                                <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-indigo-500 uppercase tracking-wider bg-indigo-50/30 dark:bg-indigo-900/10">Ext. Assigned</th>
                                <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-emerald-500 uppercase tracking-wider bg-indigo-50/30 dark:bg-indigo-900/10">Ext. Closed</th>
                                <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-orange-500 uppercase tracking-wider bg-orange-50/30 dark:bg-orange-900/10 border-l border-slate-100 dark:border-slate-800">Int. Assigned</th>
                                <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-emerald-500 uppercase tracking-wider bg-orange-50/30 dark:bg-orange-900/10">Int. Closed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                            <?php if($resPerf): while($u = $resPerf->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-slate-200 to-slate-100 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 font-bold text-sm uppercase shadow-inner">
                                            <?= substr($u['username'],0,1) ?>
                                        </div>
                                        <span class="font-bold text-slate-700 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"><?= htmlspecialchars($u['username']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs font-bold rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        <?= ucfirst($u['role']) ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-center bg-indigo-50/10 dark:bg-indigo-900/5">
                                    <span class="font-bold text-slate-600 dark:text-slate-300"><?= $u['ext_assigned'] ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center bg-indigo-50/10 dark:bg-indigo-900/5">
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 font-bold text-sm">
                                        <?= $u['ext_closed'] ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-center border-l border-slate-100 dark:border-slate-800 bg-orange-50/10 dark:bg-orange-900/5">
                                    <span class="font-bold text-slate-600 dark:text-slate-300"><?= $u['int_assigned'] ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center bg-orange-50/10 dark:bg-orange-900/5">
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 font-bold text-sm">
                                        <?= $u['int_closed'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="panel-external" class="tab-panel hidden transition-opacity duration-300">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <?php 
                $extCards = [
                    ['label'=>'Total', 'val'=>$extStats['total'], 'icon'=>'ph-ticket', 'color'=>'indigo'],
                    ['label'=>'Open', 'val'=>$extStats['open'], 'icon'=>'ph-envelope-open', 'color'=>'emerald'],
                    ['label'=>'Progress', 'val'=>$extStats['progress'], 'icon'=>'ph-spinner-gap', 'color'=>'amber'],
                    ['label'=>'Closed', 'val'=>$extStats['closed'], 'icon'=>'ph-check-circle', 'color'=>'slate']
                ];
                foreach($extCards as $c): 
                ?>
                <div class="bg-white dark:bg-[#24303F] p-5 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 hover:-translate-y-1 transition-transform">
                    <div class="w-14 h-14 rounded-2xl bg-<?= $c['color'] ?>-50 text-<?= $c['color'] ?>-600 dark:bg-<?= $c['color'] ?>-500/10 dark:text-<?= $c['color'] ?>-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill <?= $c['icon'] ?>"></i></div>
                    <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-0.5"><?= $c['label'] ?></p><h4 class="text-3xl font-black text-slate-800 dark:text-white"><?= $c['val'] ?></h4></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6">Distribusi Status</h3>
                    <div id="chart-ext-pie" class="flex justify-center h-[320px]"></div>
                </div>
            </div>
        </div>

        <div id="panel-internal" class="tab-panel hidden transition-opacity duration-300">
             <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <?php 
                $intCards = [
                    ['label'=>'Total', 'val'=>$intStats['total'], 'icon'=>'ph-buildings', 'color'=>'orange'],
                    ['label'=>'Open', 'val'=>$intStats['open'], 'icon'=>'ph-envelope-open', 'color'=>'emerald'],
                    ['label'=>'Progress', 'val'=>$intStats['progress'], 'icon'=>'ph-spinner-gap', 'color'=>'blue'],
                    ['label'=>'Closed', 'val'=>$intStats['closed'], 'icon'=>'ph-check-circle', 'color'=>'slate']
                ];
                foreach($intCards as $c): 
                ?>
                <div class="bg-white dark:bg-[#24303F] p-5 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 hover:-translate-y-1 transition-transform">
                    <div class="w-14 h-14 rounded-2xl bg-<?= $c['color'] ?>-50 text-<?= $c['color'] ?>-600 dark:bg-<?= $c['color'] ?>-500/10 dark:text-<?= $c['color'] ?>-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill <?= $c['icon'] ?>"></i></div>
                    <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-0.5"><?= $c['label'] ?></p><h4 class="text-3xl font-black text-slate-800 dark:text-white"><?= $c['val'] ?></h4></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6">Distribusi Status (Internal)</h3>
                    <div id="chart-int-pie" class="flex justify-center h-[320px]"></div>
                </div>
            </div>
        </div>

    </div>

    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            
            <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-3xl shadow-lg shadow-indigo-500/20 p-8 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10 flex items-center gap-6">
                    <div class="w-20 h-20 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shrink-0 shadow-inner border border-white/20">
                        <i class="ph-fill ph-paper-plane-right text-4xl"></i>
                    </div>
                    <div>
                        <h5 class="text-indigo-100 font-bold text-xs uppercase tracking-widest mb-1">Tiket Dikirim</h5>
                        <h2 class="text-5xl font-black tracking-tight"><?= $mySent ?></h2>
                        <p class="text-indigo-100/80 text-sm mt-1">Dibuat oleh Anda untuk divisi lain.</p>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-teal-500 to-emerald-600 rounded-3xl shadow-lg shadow-teal-500/20 p-8 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                <div class="relative z-10 flex items-center gap-6">
                    <div class="w-20 h-20 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shrink-0 shadow-inner border border-white/20">
                        <i class="ph-fill ph-tray text-4xl"></i>
                    </div>
                    <div>
                        <h5 class="text-teal-100 font-bold text-xs uppercase tracking-widest mb-1">Inbox Divisi</h5>
                        <h2 class="text-5xl font-black tracking-tight"><?= $myInbox ?></h2>
                        <p class="text-teal-100/80 text-sm mt-1">Masuk dari divisi lain ke divisi Anda.</p>
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6">Status Tiket Anda & Divisi</h3>
                <div id="chart-std-status" class="flex justify-center h-[320px]"></div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // 1. TABS SWITCHING (Tailwind approach)
    function switchTab(tabId) {
        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.add('hidden');
            panel.classList.remove('block');
        });
        document.getElementById('panel-' + tabId).classList.remove('hidden');
        document.getElementById('panel-' + tabId).classList.add('block');

        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.className = "tab-button flex items-center gap-2 py-2.5 px-6 rounded-xl font-semibold text-sm transition-all duration-300 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50";
        });
        
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        activeBtn.className = "tab-button active flex items-center gap-2 py-2.5 px-6 rounded-xl font-bold text-sm transition-all duration-300 bg-white dark:bg-indigo-600 shadow-sm text-indigo-600 dark:text-white";
    }

    // 2. CHART CONFIGURATION & DYNAMIC THEME FIX
    const fontFam = "'Inter', sans-serif";
    
    // Fungsi pembantu warna dinamis
    function getChartColors() {
        const isDark = document.documentElement.classList.contains('dark') || document.body.classList.contains('theme-dark');
        return {
            mode: isDark ? 'dark' : 'light',
            text: isDark ? '#94a3b8' : '#64748b',
            grid: isDark ? '#334155' : '#e2e8f0',
            valColor: isDark ? '#ffffff' : '#0f172a'
        };
    }

    // Global variable chart
    let chartMain, chartExtPie, chartIntPie, chartStd;

    document.addEventListener('DOMContentLoaded', function() {
        const colors = getChartColors();

        <?php if ($current_role == 'admin'): ?>
            
            // CHART 1: Area Trend
            var optionsMain = {
                series: [
                    { name: 'External Tickets', data: <?php echo json_encode($dataTrendExt); ?> },
                    { name: 'Internal Tickets', data: <?php echo json_encode($dataTrendInt); ?> }
                ],
                chart: { height: 320, type: 'area', toolbar: { show: false }, fontFamily: fontFam, background: 'transparent' },
                colors: ['#4F46E5', '#F97316'], // Indigo & Orange
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 90, 100] } },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                xaxis: { 
                    categories: <?php echo json_encode($months); ?>,
                    labels: { style: { colors: colors.text, fontWeight: 500 } },
                    axisBorder: { show: false }, axisTicks: { show: false }
                },
                yaxis: { labels: { style: { colors: colors.text, fontWeight: 500 } } },
                grid: { borderColor: colors.grid, strokeDashArray: 4 },
                legend: { position: 'top', horizontalAlign: 'right', labels: { colors: colors.text }, markers: { radius: 12 } },
                theme: { mode: colors.mode }
            };
            chartMain = new ApexCharts(document.querySelector("#chart-main-trend"), optionsMain);
            chartMain.render();

            // CHART 2: External Pie
            var optionsExtPie = {
                series: [<?= $extStats['open'] ?>, <?= $extStats['progress'] ?>, <?= $extStats['closed'] ?>, <?= $extStats['total'] - ($extStats['open']+$extStats['progress']+$extStats['closed']) ?>],
                chart: { type: 'donut', height: 320, fontFamily: fontFam, background: 'transparent' },
                labels: ['Open', 'Progress', 'Closed', 'Other'],
                colors: ['#10B981', '#3B82F6', '#64748B', '#EF4444'], 
                stroke: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '75%', labels: { show: true, name: { color: colors.text }, value: { color: colors.valColor, fontSize: '28px', fontWeight: 800 } } } } },
                legend: { position: 'bottom', labels: { colors: colors.text }, markers: { radius: 12 } },
                theme: { mode: colors.mode }
            };
            chartExtPie = new ApexCharts(document.querySelector("#chart-ext-pie"), optionsExtPie);
            chartExtPie.render();

            // CHART 3: Internal Pie
            var optionsIntPie = {
                series: [<?= $intStats['open'] ?>, <?= $intStats['progress'] ?>, <?= $intStats['closed'] ?>],
                chart: { type: 'donut', height: 320, fontFamily: fontFam, background: 'transparent' },
                labels: ['Open', 'Progress', 'Closed'],
                colors: ['#10B981', '#3B82F6', '#64748B'],
                stroke: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '75%', labels: { show: true, name: { color: colors.text }, value: { color: colors.valColor, fontSize: '28px', fontWeight: 800 } } } } },
                legend: { position: 'bottom', labels: { colors: colors.text }, markers: { radius: 12 } },
                theme: { mode: colors.mode }
            };
            chartIntPie = new ApexCharts(document.querySelector("#chart-int-pie"), optionsIntPie);
            chartIntPie.render();

        <?php else: ?>
            
            // CHART 4: Standard User Pie
            var optionsStd = {
                series: [<?= $myStatData[0] ?>, <?= $myStatData[1] ?>, <?= $myStatData[2] ?>],
                chart: { type: 'donut', height: 320, fontFamily: fontFam, background: 'transparent' },
                labels: ['Open', 'Progress', 'Closed'],
                colors: ['#10B981', '#3B82F6', '#64748B'],
                stroke: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '75%', labels: { show: true, name: { color: colors.text }, value: { color: colors.valColor, fontSize: '28px', fontWeight: 800 } } } } },
                legend: { position: 'bottom', labels: { colors: colors.text }, markers: { radius: 12 } },
                theme: { mode: colors.mode }
            };
            chartStd = new ApexCharts(document.querySelector("#chart-std-status"), optionsStd);
            chartStd.render();
            
        <?php endif; ?>

        // 3. OBSERVER: MENDETEKSI PERUBAHAN TEMA REAL-TIME
        // Fungsi ini yang memastikan saat tombol Light/Dark ditekan, grafik langsung berubah warna
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class' || mutation.attributeName === 'data-bs-theme') {
                    const newColors = getChartColors();
                    
                    if (chartMain) {
                        chartMain.updateOptions({
                            theme: { mode: newColors.mode },
                            xaxis: { labels: { style: { colors: newColors.text } } },
                            yaxis: { labels: { style: { colors: newColors.text } } },
                            grid: { borderColor: newColors.grid },
                            legend: { labels: { colors: newColors.text } }
                        });
                    }
                    
                    [chartExtPie, chartIntPie, chartStd].forEach(chart => {
                        if (chart) {
                            chart.updateOptions({
                                theme: { mode: newColors.mode },
                                plotOptions: { pie: { donut: { labels: { name: { color: newColors.text }, value: { color: newColors.valColor } } } } },
                                legend: { labels: { colors: newColors.text } }
                            });
                        }
                    });
                }
            });
        });

        observer.observe(document.documentElement, { attributes: true });
        observer.observe(document.body, { attributes: true });
    });
</script>