<?php
$page_title = "Dashboard Clients";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- 1. DATA PENGAMBILAN (QUERIES) ---

// A. Total Company
$total_clients = $conn->query("SELECT COUNT(*) as t FROM clients")->fetch_assoc()['t'];

// B. Dokumen Stats (NDA, Contract, Both)
$nda_only = $conn->query("SELECT COUNT(*) as t FROM clients WHERE nda_file IS NOT NULL AND nda_file != ''")->fetch_assoc()['t'];
$contract_only = $conn->query("SELECT COUNT(*) as t FROM clients WHERE contract_file IS NOT NULL AND contract_file != ''")->fetch_assoc()['t'];
$both_docs = $conn->query("SELECT COUNT(*) as t FROM clients WHERE (nda_file IS NOT NULL AND nda_file != '') AND (contract_file IS NOT NULL AND contract_file != '')")->fetch_assoc()['t'];

// C. Statistik Berdasarkan Tipe Langganan (Subscription) - DIPERBAIKI
$sub_labels = [];
$sub_data = [];
// Menggunakan COALESCE untuk menangani data NULL/Kosong
$sqlSub = "SELECT COALESCE(NULLIF(subscription_type, ''), 'Unknown') as sub_type, COUNT(*) as total 
           FROM clients 
           GROUP BY sub_type";
$resSub = $conn->query($sqlSub);

if ($resSub->num_rows > 0) {
    while($row = $resSub->fetch_assoc()) {
        $sub_labels[] = $row['sub_type'];
        $sub_data[] = (int)$row['total'];
    }
} else {
    // Default data jika kosong agar chart tetap muncul (placeholder)
    $sub_labels = ['No Data'];
    $sub_data = [0];
}

// D. Statistik Berdasarkan Status Client
$stat_labels = [];
$stat_data = [];
$sqlStat = "SELECT COALESCE(NULLIF(status, ''), 'Unknown') as status_name, COUNT(*) as total 
            FROM clients 
            GROUP BY status_name";
$resStat = $conn->query($sqlStat);

if ($resStat->num_rows > 0) {
    while($row = $resStat->fetch_assoc()) {
        $stat_labels[] = $row['status_name'];
        $stat_data[] = (int)$row['total'];
    }
} else {
    $stat_labels = ['No Data'];
    $stat_data = [0];
}

// E. Sales Person Performance
$sales_stats = [];
$sqlSales = "SELECT u.username, COUNT(c.id) as total_client,
             SUM(CASE WHEN c.status = 'Subscribe' THEN 1 ELSE 0 END) as active_client
             FROM users u
             LEFT JOIN clients c ON c.sales_person_id = u.id
             JOIN divisions d ON u.division_id = d.id
             WHERE d.name = 'Business Development' OR d.code = 'BD'
             GROUP BY u.id ORDER BY total_client DESC";
$resSales = $conn->query($sqlSales);
?>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto overflow-y-auto min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors duration-300">
    
    <div class="mb-8 animate-slide-up">
        <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Dashboard Clients</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Analisis data pelanggan, status berlangganan, dan kelengkapan dokumen.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 animate-slide-up delay-100">
        
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl shadow-soft p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-indigo-100 font-semibold tracking-wide text-sm uppercase">Total Company</span>
                    <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm shadow-inner">
                        <i class="ph-fill ph-buildings text-2xl"></i>
                    </div>
                </div>
                <h2 class="text-4xl font-extrabold tracking-tight"><?= $total_clients ?></h2>
            </div>
        </div>

        <div class="bg-gradient-to-br from-cyan-500 to-blue-500 rounded-2xl shadow-soft p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-cyan-100 font-semibold tracking-wide text-sm uppercase">NDA Uploaded</span>
                    <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm shadow-inner">
                        <i class="ph-fill ph-file-lock text-2xl"></i>
                    </div>
                </div>
                <h2 class="text-4xl font-extrabold tracking-tight"><?= $nda_only ?></h2>
            </div>
        </div>

        <div class="bg-gradient-to-br from-amber-500 to-orange-500 rounded-2xl shadow-soft p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-amber-100 font-semibold tracking-wide text-sm uppercase">Contract Signed</span>
                    <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm shadow-inner">
                        <i class="ph-fill ph-file-signature text-2xl"></i>
                    </div>
                </div>
                <h2 class="text-4xl font-extrabold tracking-tight"><?= $contract_only ?></h2>
            </div>
        </div>

        <div class="bg-gradient-to-br from-emerald-500 to-teal-500 rounded-2xl shadow-soft p-6 text-white relative overflow-hidden group hover:-translate-y-1 transition-transform">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-emerald-100 font-semibold tracking-wide text-sm uppercase">Full Legal Docs</span>
                    <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm shadow-inner">
                        <i class="ph-fill ph-seal-check text-2xl"></i>
                    </div>
                </div>
                <h2 class="text-4xl font-extrabold tracking-tight"><?= $both_docs ?></h2>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8 animate-slide-up delay-200">
        
        <div class="bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 p-6 flex flex-col">
            <div class="mb-4">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Subscription Types</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Distribusi paket langganan klien.</p>
            </div>
            <div id="chart-subscription" class="w-full flex-grow flex justify-center items-center min-h-[300px]"></div>
        </div>

        <div class="bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 p-6 flex flex-col">
            <div class="mb-4">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Client Status Overview</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Total klien berdasarkan status aktif/tidak.</p>
            </div>
            <div id="chart-status" class="w-full flex-grow min-h-[300px]"></div>
        </div>

    </div>

    <div class="bg-white dark:bg-[#1A222C] rounded-2xl shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden animate-slide-up delay-300 mb-8">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Sales Person Performance</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Evaluasi performa berdasarkan jumlah klien aktif.</p>
            </div>
            <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg">
                <i class="ph-bold ph-trend-up text-xl"></i>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sales Person Name</th>
                        <th class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-center text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Handled</th>
                        <th class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-center text-xs font-bold text-emerald-500 uppercase tracking-wider">Active Subs</th>
                        <th class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-[30%]">Performance Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php if($resSales->num_rows > 0): ?>
                        <?php while($sales = $resSales->fetch_assoc()): ?>
                        <?php 
                            $total = $sales['total_client'];
                            $active = $sales['active_client'];
                            $percent = ($total > 0) ? round(($active / $total) * 100) : 0;
                            
                            // Styling color based on performance
                            if ($percent > 70) {
                                $barColor = 'bg-emerald-500';
                                $textColor = 'text-emerald-600 dark:text-emerald-400';
                            } elseif ($percent > 40) {
                                $barColor = 'bg-amber-500';
                                $textColor = 'text-amber-600 dark:text-amber-400';
                            } else {
                                $barColor = 'bg-rose-500';
                                $textColor = 'text-rose-600 dark:text-rose-400';
                            }
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-bold">
                                        <?= strtoupper(substr($sales['username'], 0, 1)) ?>
                                    </div>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($sales['username']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-sm">
                                    <?= $total ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="font-extrabold text-emerald-500 text-lg"><?= $active ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex justify-between items-end mb-1">
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Active Rate</span>
                                    <span class="text-xs font-bold <?= $textColor ?>"><?= $percent ?>%</span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                                    <div class="<?= $barColor ?> h-2 rounded-full transition-all duration-1000 ease-out" style="width: 0%" data-width="<?= $percent ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <i class="ph-fill ph-users-three text-4xl mb-2 opacity-50"></i>
                                    <p class="text-sm font-medium">Belum ada data Sales Person yang menangani klien.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- ANIMASI PROGRESS BAR (Triggers on load) ---
    setTimeout(() => {
        document.querySelectorAll('.progress-bar, [data-width]').forEach(bar => {
            bar.style.width = bar.getAttribute('data-width');
        });
    }, 300);

    // --- CHART CONFIGURATION ---
    const isDarkMode = document.documentElement.classList.contains('dark') || localStorage.getItem('color-theme') === 'dark';
    const textColor = isDarkMode ? '#94a3b8' : '#64748b'; // slate-400 vs slate-500
    const gridColor = isDarkMode ? '#334155' : '#e2e8f0'; // slate-700 vs slate-200
    const fontFam = "'Inter', sans-serif";

    // 1. CHART SUBSCRIPTION (PIE CHART)
    var subLabels = <?= json_encode($sub_labels) ?>;
    var subSeries = <?= json_encode($sub_data) ?>;

    if(subSeries.length > 0 && subSeries[0] !== 0) {
        var optionsSub = {
            series: subSeries,
            chart: { type: 'donut', height: 320, fontFamily: fontFam, background: 'transparent' },
            labels: subLabels,
            colors: ['#4F46E5', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444'], // Indigo, LightBlue, Emerald, Amber, Red
            stroke: { show: false },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: { 
                    donut: { 
                        size: '75%', 
                        labels: { 
                            show: true, 
                            name: { color: textColor }, 
                            value: { color: isDarkMode ? '#fff' : '#0f172a', fontSize: '24px', fontWeight: 700 } 
                        } 
                    } 
                }
            },
            legend: { position: 'bottom', labels: { colors: textColor } },
            tooltip: {
                theme: isDarkMode ? 'dark' : 'light',
                y: { formatter: function(val) { return val + " Clients" } }
            }
        };
        new ApexCharts(document.querySelector("#chart-subscription"), optionsSub).render();
    } else {
        document.querySelector("#chart-subscription").innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-slate-400 dark:text-slate-500">
                <i class="ph-fill ph-chart-pie-slice text-5xl mb-3 opacity-30"></i>
                <p class="text-sm">Belum ada data tipe langganan.</p>
            </div>
        `;
    }

    // 2. CHART STATUS (BAR CHART)
    var statLabels = <?= json_encode($stat_labels) ?>;
    var statSeries = <?= json_encode($stat_data) ?>;

    if(statSeries.length > 0 && statSeries[0] !== 0) {
        var optionsStat = {
            series: [{ name: 'Total Clients', data: statSeries }],
            chart: { 
                type: 'bar', 
                height: 320, 
                toolbar: { show: false },
                fontFamily: fontFam,
                background: 'transparent'
            },
            plotOptions: {
                bar: { borderRadius: 6, horizontal: true, barHeight: '40%', distributed: true }
            },
            colors: ['#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'], // Tailwind distinct colors
            dataLabels: { 
                enabled: true,
                style: { fontSize: '12px', colors: ['#fff'] }
            },
            xaxis: { 
                categories: statLabels,
                labels: { style: { colors: textColor } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { style: { colors: textColor, fontWeight: 600 } }
            },
            grid: {
                borderColor: gridColor,
                strokeDashArray: 4,
                xaxis: { lines: { show: true } },
                yaxis: { lines: { show: false } }
            },
            tooltip: {
                theme: isDarkMode ? 'dark' : 'light',
                y: { formatter: function(val) { return val + " Clients" } }
            }
        };
        new ApexCharts(document.querySelector("#chart-status"), optionsStat).render();
    } else {
        document.querySelector("#chart-status").innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-slate-400 dark:text-slate-500">
                <i class="ph-fill ph-chart-bar text-5xl mb-3 opacity-30"></i>
                <p class="text-sm">Belum ada data status client.</p>
            </div>
        `;
    }
});
</script>