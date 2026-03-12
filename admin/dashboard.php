<?php
$page_title = "Dashboard Utama";
include 'includes/header.php';
include 'includes/sidebar.php';
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
    .animate-fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modern-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .modern-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .modern-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .modern-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
    
    /* Segmented Control Background */
    .segmented-control { position: relative; display: inline-flex; background: #f1f5f9; padding: 4px; border-radius: 16px; }
    .dark .segmented-control { background: #1e293b; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-8 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-2">
        <div>
            <p class="text-indigo-500 dark:text-indigo-400 font-bold uppercase tracking-widest text-[10px] mb-1">Hi, <?= htmlspecialchars($uData['username'] ?? 'User') ?></p>
            <h1 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight">System Dashboard</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1.5 font-medium text-sm">Berikut adalah ringkasan operasional dan metrik performa hari ini.</p>
        </div>
        <div class="px-4 py-2.5 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm inline-flex items-center gap-2.5 shrink-0">
            <div class="w-8 h-8 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-500">
                <i class="ph-bold ph-calendar-blank text-lg"></i>
            </div>
            <span class="text-sm font-bold text-slate-700 dark:text-slate-200"><?= date('l, d F Y') ?></span>
        </div>
    </div>

    <?php if ($current_role == 'admin'): ?>
    
    <div class="w-full flex pb-2 overflow-x-auto modern-scrollbar">
        <div class="segmented-control shadow-inner border border-slate-200 dark:border-slate-700/50">
            <button onclick="switchTab('overview')" id="tab-btn-overview" class="tab-button active relative z-10 flex items-center justify-center gap-2 py-2.5 px-8 rounded-xl font-bold text-sm transition-all duration-300 bg-white dark:bg-indigo-600 shadow-md text-indigo-600 dark:text-white min-w-[140px]">
                <i class="ph-bold ph-squares-four text-lg"></i> Overview
            </button>
            <button onclick="switchTab('external')" id="tab-btn-external" class="tab-button relative z-10 flex items-center justify-center gap-2 py-2.5 px-8 rounded-xl font-semibold text-sm transition-all duration-300 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 min-w-[140px]">
                <i class="ph-bold ph-headset text-lg"></i> External
            </button>
            <button onclick="switchTab('internal')" id="tab-btn-internal" class="tab-button relative z-10 flex items-center justify-center gap-2 py-2.5 px-8 rounded-xl font-semibold text-sm transition-all duration-300 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 min-w-[140px]">
                <i class="ph-bold ph-buildings text-lg"></i> Internal
            </button>
        </div>
    </div>

    <div class="relative">

        <div id="panel-overview" class="tab-panel block transition-all duration-500 opacity-100">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-gradient-to-br from-indigo-600 to-blue-600 rounded-[2rem] shadow-xl shadow-indigo-500/20 p-8 text-white relative overflow-hidden group hover:shadow-indigo-500/40 transition-all duration-500 hover:-translate-y-1 border border-indigo-400/30">
                    <div class="absolute -right-10 -top-10 w-64 h-64 bg-white/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-700"></div>
                    <div class="absolute right-6 bottom-6 opacity-20 group-hover:opacity-40 transition-opacity duration-500 transform group-hover:scale-110 group-hover:-rotate-12">
                        <i class="ph-fill ph-headset text-[120px]"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/20 backdrop-blur-md rounded-xl font-bold tracking-widest text-[10px] uppercase mb-6 border border-white/20 shadow-sm">
                            <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div> External Tickets
                        </div>
                        <h2 class="text-7xl font-black tracking-tighter mb-2"><?= $extStats['total'] ?></h2>
                        <p class="text-indigo-100 font-medium text-sm opacity-90 max-w-[70%]">Total tiket dukungan pelanggan yang terdata dalam sistem.</p>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-500 to-amber-500 rounded-[2rem] shadow-xl shadow-orange-500/20 p-8 text-white relative overflow-hidden group hover:shadow-orange-500/40 transition-all duration-500 hover:-translate-y-1 border border-orange-400/30">
                    <div class="absolute -right-10 -top-10 w-64 h-64 bg-white/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-700"></div>
                    <div class="absolute right-6 bottom-6 opacity-20 group-hover:opacity-40 transition-opacity duration-500 transform group-hover:scale-110 group-hover:rotate-12">
                        <i class="ph-fill ph-buildings text-[120px]"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/20 backdrop-blur-md rounded-xl font-bold tracking-widest text-[10px] uppercase mb-6 border border-white/20 shadow-sm">
                            <div class="w-2 h-2 rounded-full bg-yellow-300 animate-pulse"></div> Internal Tickets
                        </div>
                        <h2 class="text-7xl font-black tracking-tighter mb-2"><?= $intStats['total'] ?></h2>
                        <p class="text-orange-100 font-medium text-sm opacity-90 max-w-[70%]">Total tiket lintas divisi (Internal) yang tercatat di database.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 sm:p-8 mb-8 transition-colors">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-black text-slate-800 dark:text-white">Trend Traffic (6 Bulan)</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Perbandingan volume tiket External vs Internal berdasarkan bulan.</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-400 border border-slate-100 dark:border-slate-700 shadow-inner">
                        <i class="ph-bold ph-chart-line-up text-2xl"></i>
                    </div>
                </div>
                <div id="chart-main-trend" class="w-full h-[350px]"></div>
            </div>

            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors">
                <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-800 dark:text-white">User Performance Metrics</h3>
                        <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-1 uppercase tracking-widest">Penyelesaian Tiket oleh Staf</p>
                    </div>
                    <i class="ph-fill ph-ranking text-3xl text-amber-500"></i>
                </div>
                
                <div class="overflow-x-auto modern-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[900px]">
                        <thead>
                            <tr class="bg-white dark:bg-[#24303F]">
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[25%]">Staff Profile</th>
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[15%]">Role</th>
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-indigo-500 uppercase tracking-widest bg-indigo-50/50 dark:bg-indigo-900/10">Ext. Assigned</th>
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-emerald-500 uppercase tracking-widest bg-indigo-50/50 dark:bg-indigo-900/10">Ext. Closed</th>
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-orange-500 uppercase tracking-widest bg-orange-50/50 dark:bg-orange-900/10 border-l border-slate-100 dark:border-slate-800">Int. Assigned</th>
                                <th class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-emerald-500 uppercase tracking-widest bg-orange-50/50 dark:bg-orange-900/10">Int. Closed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50 text-sm">
                            <?php if($resPerf && $resPerf->num_rows > 0): while($u = $resPerf->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                <td class="px-6 py-4 align-middle">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-slate-200 to-slate-100 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 font-black text-sm uppercase shadow-inner border border-slate-200/50 dark:border-slate-600/50 shrink-0">
                                            <?= substr($u['username'],0,1) ?>
                                        </div>
                                        <span class="font-bold text-slate-800 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                            <?= htmlspecialchars($u['username']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-middle">
                                    <span class="px-3 py-1 inline-flex text-[10px] font-bold uppercase tracking-widest rounded-lg bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 shadow-sm">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 align-middle text-center bg-indigo-50/30 dark:bg-indigo-900/5">
                                    <span class="font-black text-slate-700 dark:text-slate-300 text-base"><?= $u['ext_assigned'] ?></span>
                                </td>
                                <td class="px-6 py-4 align-middle text-center bg-indigo-50/30 dark:bg-indigo-900/5">
                                    <?php if($u['ext_closed'] > 0): ?>
                                        <span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded-lg bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/20 dark:border-emerald-500/30 dark:text-emerald-400 font-black text-sm shadow-sm">
                                            <?= $u['ext_closed'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 font-bold">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 align-middle text-center border-l border-slate-100 dark:border-slate-800 bg-orange-50/30 dark:bg-orange-900/5">
                                    <span class="font-black text-slate-700 dark:text-slate-300 text-base"><?= $u['int_assigned'] ?></span>
                                </td>
                                <td class="px-6 py-4 align-middle text-center bg-orange-50/30 dark:bg-orange-900/5">
                                    <?php if($u['int_closed'] > 0): ?>
                                        <span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded-lg bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/20 dark:border-emerald-500/30 dark:text-emerald-400 font-black text-sm shadow-sm">
                                            <?= $u['int_closed'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 font-bold">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-500 font-medium">Belum ada data performa.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="panel-external" class="tab-panel hidden transition-all duration-500 opacity-0 absolute top-0 left-0 w-full">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <?php 
                $extCards = [
                    ['label'=>'Total', 'val'=>$extStats['total'], 'icon'=>'ph-ticket', 'color'=>'indigo'],
                    ['label'=>'Open', 'val'=>$extStats['open'], 'icon'=>'ph-envelope-open', 'color'=>'rose'],
                    ['label'=>'Progress', 'val'=>$extStats['progress'], 'icon'=>'ph-spinner-gap', 'color'=>'amber'],
                    ['label'=>'Closed', 'val'=>$extStats['closed'], 'icon'=>'ph-check-circle', 'color'=>'emerald']
                ];
                foreach($extCards as $c): 
                ?>
                <div class="bg-white dark:bg-[#24303F] p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col justify-center hover:-translate-y-1 transition-transform group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 rounded-2xl bg-<?= $c['color'] ?>-50 text-<?= $c['color'] ?>-600 dark:bg-<?= $c['color'] ?>-500/10 dark:text-<?= $c['color'] ?>-400 flex items-center justify-center text-2xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill <?= $c['icon'] ?>"></i></div>
                        <h4 class="text-4xl font-black text-slate-800 dark:text-white leading-none"><?= $c['val'] ?></h4>
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400"><?= $c['label'] ?> Tickets</p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <div class="lg:col-span-5 bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-8 flex flex-col">
                    <h3 class="text-lg font-black text-slate-800 dark:text-white mb-2">Distribusi Status</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-8">Proporsi tiket eksternal yang sedang berjalan.</p>
                    <div id="chart-ext-pie" class="flex-grow flex items-center justify-center min-h-[300px]"></div>
                </div>
                <div class="lg:col-span-7 bg-indigo-50 dark:bg-indigo-900/10 rounded-3xl border border-indigo-100 dark:border-indigo-800/30 p-8 flex flex-col justify-center text-center">
                    <i class="ph-fill ph-headset text-indigo-200 dark:text-indigo-800/50 text-9xl mx-auto mb-4"></i>
                    <h3 class="text-2xl font-black text-indigo-800 dark:text-indigo-300 mb-2">External Support Desk</h3>
                    <p class="text-indigo-600/80 dark:text-indigo-400/80 font-medium">Modul ini berfokus pada penanganan tiket masuk dari klien atau pihak luar. Pastikan tiket dengan status OPEN segera ditindaklanjuti.</p>
                </div>
            </div>
        </div>

        <div id="panel-internal" class="tab-panel hidden transition-all duration-500 opacity-0 absolute top-0 left-0 w-full">
             <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <?php 
                $intCards = [
                    ['label'=>'Total', 'val'=>$intStats['total'], 'icon'=>'ph-buildings', 'color'=>'orange'],
                    ['label'=>'Open', 'val'=>$intStats['open'], 'icon'=>'ph-envelope-open', 'color'=>'rose'],
                    ['label'=>'Progress', 'val'=>$intStats['progress'], 'icon'=>'ph-spinner-gap', 'color'=>'blue'],
                    ['label'=>'Closed', 'val'=>$intStats['closed'], 'icon'=>'ph-check-circle', 'color'=>'emerald']
                ];
                foreach($intCards as $c): 
                ?>
                <div class="bg-white dark:bg-[#24303F] p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col justify-center hover:-translate-y-1 transition-transform group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 rounded-2xl bg-<?= $c['color'] ?>-50 text-<?= $c['color'] ?>-600 dark:bg-<?= $c['color'] ?>-500/10 dark:text-<?= $c['color'] ?>-400 flex items-center justify-center text-2xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill <?= $c['icon'] ?>"></i></div>
                        <h4 class="text-4xl font-black text-slate-800 dark:text-white leading-none"><?= $c['val'] ?></h4>
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400"><?= $c['label'] ?> Internal</p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <div class="lg:col-span-5 bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-8 flex flex-col">
                    <h3 class="text-lg font-black text-slate-800 dark:text-white mb-2">Distribusi Internal</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-8">Proporsi tiket lintas divisi perusahaan.</p>
                    <div id="chart-int-pie" class="flex-grow flex items-center justify-center min-h-[300px]"></div>
                </div>
                <div class="lg:col-span-7 bg-orange-50 dark:bg-orange-900/10 rounded-3xl border border-orange-100 dark:border-orange-800/30 p-8 flex flex-col justify-center text-center">
                    <i class="ph-fill ph-buildings text-orange-200 dark:text-orange-800/50 text-9xl mx-auto mb-4"></i>
                    <h3 class="text-2xl font-black text-orange-800 dark:text-orange-300 mb-2">Internal Collaboration</h3>
                    <p class="text-orange-600/80 dark:text-orange-400/80 font-medium">Modul ini digunakan untuk tugas dan koordinasi antar divisi internal. Selesaikan tiket PROGRESS agar kolaborasi berjalan lancar.</p>
                </div>
            </div>
        </div>

    </div>

    <?php else: ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            
            <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-[2rem] shadow-xl shadow-indigo-500/20 p-8 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform duration-500 border border-indigo-400/30">
                <div class="absolute -right-10 -bottom-10 w-56 h-56 bg-white/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-700"></div>
                <div class="relative z-10 flex items-center gap-6">
                    <div class="w-20 h-20 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shrink-0 shadow-inner border border-white/20 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-paper-plane-right text-4xl drop-shadow-md"></i>
                    </div>
                    <div>
                        <h5 class="text-indigo-100 font-bold text-xs uppercase tracking-widest mb-1.5">Tiket Dikirim</h5>
                        <h2 class="text-5xl font-black tracking-tight leading-none"><?= $mySent ?></h2>
                        <p class="text-indigo-100/80 text-xs mt-2">Dibuat oleh Anda untuk divisi lain.</p>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-teal-500 to-emerald-600 rounded-[2rem] shadow-xl shadow-teal-500/20 p-8 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform duration-500 border border-teal-400/30">
                <div class="absolute -right-10 -bottom-10 w-56 h-56 bg-white/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-700"></div>
                <div class="relative z-10 flex items-center gap-6">
                    <div class="w-20 h-20 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md shrink-0 shadow-inner border border-white/20 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-tray text-4xl drop-shadow-md"></i>
                    </div>
                    <div>
                        <h5 class="text-teal-100 font-bold text-xs uppercase tracking-widest mb-1.5">Inbox Divisi</h5>
                        <h2 class="text-5xl font-black tracking-tight leading-none"><?= $myInbox ?></h2>
                        <p class="text-teal-100/80 text-xs mt-2">Menunggu respon divisi Anda.</p>
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-8">
                <h3 class="text-lg font-black text-slate-800 dark:text-white mb-2">Status Tiket Anda & Divisi</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">Ringkasan status seluruh tiket yang melibatkan Anda.</p>
                <div id="chart-std-status" class="flex justify-center min-h-[320px]"></div>
            </div>
            
            <div class="bg-slate-50 dark:bg-slate-800/50 rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-8 flex flex-col justify-center items-center text-center">
                 <div class="w-24 h-24 rounded-full bg-white dark:bg-slate-800 shadow-sm flex items-center justify-center mb-6">
                     <i class="ph-fill ph-rocket-launch text-5xl text-indigo-500"></i>
                 </div>
                 <h3 class="text-xl font-black text-slate-800 dark:text-white mb-2">Siap Bekerja?</h3>
                 <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm mb-6">Cek menu Internal Ticket di sidebar kiri untuk melihat detail tugas yang ditugaskan ke divisi Anda.</p>
                 <a href="internal_ticket.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-indigo-500/30 transition-all active:scale-95">
                     Buka Internal Ticket
                 </a>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // 1. TABS SWITCHING LOGIC DENGAN FADE EFFECT
    function switchTab(tabId) {
        // Handle Button States
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active', 'bg-white', 'dark:bg-indigo-600', 'shadow-md', 'text-indigo-600', 'dark:text-white');
            btn.classList.add('text-slate-500', 'hover:text-slate-700', 'dark:text-slate-400', 'dark:hover:text-slate-200');
        });
        
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        activeBtn.classList.remove('text-slate-500', 'hover:text-slate-700', 'dark:text-slate-400', 'dark:hover:text-slate-200');
        activeBtn.classList.add('active', 'bg-white', 'dark:bg-indigo-600', 'shadow-md', 'text-indigo-600', 'dark:text-white');

        // Handle Panel Visibility with Fade
        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.remove('opacity-100');
            panel.classList.add('opacity-0');
            
            setTimeout(() => {
                panel.classList.remove('block');
                panel.classList.add('hidden', 'absolute', 'top-0', 'left-0', 'w-full');
            }, 300); // Wait for fade out
        });

        setTimeout(() => {
            const activePanel = document.getElementById('panel-' + tabId);
            activePanel.classList.remove('hidden', 'absolute', 'top-0', 'left-0', 'w-full');
            activePanel.classList.add('block');
            
            // Trigger reflow before adding opacity back
            void activePanel.offsetWidth;
            
            activePanel.classList.remove('opacity-0');
            activePanel.classList.add('opacity-100');
        }, 300);
    }

    // 2. CHART CONFIGURATION & DYNAMIC THEME
    const fontFam = "'Inter', sans-serif";
    
    function getChartColors() {
        const isDark = document.documentElement.classList.contains('dark') || document.body.classList.contains('theme-dark');
        return {
            mode: isDark ? 'dark' : 'light',
            text: isDark ? '#94a3b8' : '#64748b',
            grid: isDark ? '#334155' : '#e2e8f0',
            valColor: isDark ? '#ffffff' : '#0f172a'
        };
    }

    let chartMain, chartExtPie, chartIntPie, chartStd;

    document.addEventListener('DOMContentLoaded', function() {
        const colors = getChartColors();

        <?php if ($current_role == 'admin'): ?>
            
            // CHART 1: Area Trend
            var optionsMain = {
                series: [
                    { name: 'External', data: <?php echo json_encode($dataTrendExt); ?> },
                    { name: 'Internal', data: <?php echo json_encode($dataTrendInt); ?> }
                ],
                chart: { height: 350, type: 'area', toolbar: { show: false }, fontFamily: fontFam, background: 'transparent' },
                colors: ['#4F46E5', '#F97316'], // Indigo & Orange
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 90, 100] } },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                xaxis: { 
                    categories: <?php echo json_encode($months); ?>,
                    labels: { style: { colors: colors.text, fontWeight: 600 } },
                    axisBorder: { show: false }, axisTicks: { show: false }
                },
                yaxis: { labels: { style: { colors: colors.text, fontWeight: 600 } } },
                grid: { borderColor: colors.grid, strokeDashArray: 4, padding: { top: 0, right: 0, bottom: 0, left: 10 } },
                legend: { position: 'top', horizontalAlign: 'right', labels: { colors: colors.text }, markers: { radius: 12 } },
                theme: { mode: colors.mode }
            };
            chartMain = new ApexCharts(document.querySelector("#chart-main-trend"), optionsMain);
            chartMain.render();

            // Format Pie Chart Umum
            const pieOptionsBase = {
                chart: { type: 'donut', height: 320, fontFamily: fontFam, background: 'transparent' },
                stroke: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '75%', labels: { show: true, name: { color: colors.text, fontWeight: 600 }, value: { color: colors.valColor, fontSize: '32px', fontWeight: 900 } } } } },
                legend: { position: 'bottom', labels: { colors: colors.text }, markers: { radius: 12 } },
                theme: { mode: colors.mode }
            };

            // CHART 2: External Pie
            var optionsExtPie = {
                ...pieOptionsBase,
                series: [<?= $extStats['open'] ?>, <?= $extStats['progress'] ?>, <?= $extStats['closed'] ?>, <?= max(0, $extStats['total'] - ($extStats['open']+$extStats['progress']+$extStats['closed'])) ?>],
                labels: ['Open', 'Progress', 'Closed', 'Other'],
                colors: ['#F43F5E', '#F59E0B', '#10B981', '#64748B'], // Rose, Amber, Emerald, Slate
            };
            chartExtPie = new ApexCharts(document.querySelector("#chart-ext-pie"), optionsExtPie);
            chartExtPie.render();

            // CHART 3: Internal Pie
            var optionsIntPie = {
                ...pieOptionsBase,
                series: [<?= $intStats['open'] ?>, <?= $intStats['progress'] ?>, <?= $intStats['closed'] ?>],
                labels: ['Open', 'Progress', 'Closed'],
                colors: ['#F43F5E', '#3B82F6', '#10B981'], // Rose, Blue, Emerald
            };
            chartIntPie = new ApexCharts(document.querySelector("#chart-int-pie"), optionsIntPie);
            chartIntPie.render();

        <?php else: ?>
            
            // CHART 4: Standard User Pie
            var optionsStd = {
                series: [<?= $myStatData[0] ?>, <?= $myStatData[1] ?>, <?= $myStatData[2] ?>],
                chart: { type: 'donut', height: 320, fontFamily: fontFam, background: 'transparent' },
                labels: ['Open', 'Progress', 'Closed'],
                colors: ['#F43F5E', '#3B82F6', '#10B981'],
                stroke: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '75%', labels: { show: true, name: { color: colors.text, fontWeight: 600 }, value: { color: colors.valColor, fontSize: '32px', fontWeight: 900 } } } } },
                legend: { position: 'bottom', labels: { colors: colors.text }, markers: { radius: 12 } },
                theme: { mode: colors.mode }
            };
            chartStd = new ApexCharts(document.querySelector("#chart-std-status"), optionsStd);
            chartStd.render();
            
        <?php endif; ?>

        // 3. OBSERVER: TEMA REAL-TIME
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