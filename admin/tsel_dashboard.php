<?php
$page_title = "Inject Dashboard";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- LOGIKA EXPORT STATISTIK KE EXCEL (CSV) ---
if (isset($_POST['export_stats'])) {
    if (ob_get_length()) ob_end_clean(); // Bersihkan buffer output
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Inject_Performance_Report_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header Kolom Excel
    fputcsv($output, array('Client Name', 'Total Request', 'Success', 'Failed', 'Success Rate (%)', 'Last Activity'));
    
    // Query Data Statistik Per Client (Lengkap)
    $sqlStats = "SELECT c.company_name, 
                    COUNT(h.id) as total,
                    SUM(CASE WHEN h.status='SUCCESS' THEN 1 ELSE 0 END) as s,
                    SUM(CASE WHEN h.status='FAILED' THEN 1 ELSE 0 END) as f,
                    MAX(h.created_at) as last_act
                   FROM inject_history h
                   JOIN inject_batches b ON h.batch_id = b.id
                   JOIN clients c ON b.client_id = c.id
                   GROUP BY c.id
                   ORDER BY total DESC";
    $resStats = $conn->query($sqlStats);
    
    while($row = $resStats->fetch_assoc()) {
        $total = intval($row['total']);
        $s = intval($row['s']);
        $f = intval($row['f']);
        $rate = ($total > 0) ? round(($s/$total)*100, 2) : 0;
        
        fputcsv($output, array(
            $row['company_name'],
            $total,
            $s,
            $f,
            $rate . '%',
            $row['last_act']
        ));
    }
    
    fclose($output);
    exit();
}

// --- QUERY DATA CHART ---
// 1. Global Stats
$sqlGlobal = "SELECT 
                SUM(CASE WHEN status='SUCCESS' THEN 1 ELSE 0 END) as total_success,
                SUM(CASE WHEN status='FAILED' THEN 1 ELSE 0 END) as total_failed,
                SUM(CASE WHEN status='SUBMITTED' THEN 1 ELSE 0 END) as total_pending
              FROM inject_history";
$statGlobal = $conn->query($sqlGlobal)->fetch_assoc();
$gSuccess = intval($statGlobal['total_success'] ?? 0);
$gFailed = intval($statGlobal['total_failed'] ?? 0);
$gPending = intval($statGlobal['total_pending'] ?? 0);
$gTotal = $gSuccess + $gFailed + $gPending;

// 2. Top 10 Clients Performance
$sqlClientStats = "SELECT c.company_name, 
                    COUNT(h.id) as total_req,
                    SUM(CASE WHEN h.status='SUCCESS' THEN 1 ELSE 0 END) as s,
                    SUM(CASE WHEN h.status='FAILED' THEN 1 ELSE 0 END) as f
                   FROM inject_history h
                   JOIN inject_batches b ON h.batch_id = b.id
                   JOIN clients c ON b.client_id = c.id
                   GROUP BY c.id
                   ORDER BY total_req DESC LIMIT 10";
$resClientStats = $conn->query($sqlClientStats);

$chartLabels = [];
$chartDataSuccess = [];
$chartDataFailed = [];

while($row = $resClientStats->fetch_assoc()) {
    $chartLabels[] = $row['company_name'];
    $chartDataSuccess[] = intval($row['s']);
    $chartDataFailed[] = intval($row['f']);
}

// 3. Tabel Detail Semua Client
$sqlAllClients = "SELECT c.company_name, 
                    COUNT(h.id) as total,
                    SUM(CASE WHEN h.status='SUCCESS' THEN 1 ELSE 0 END) as s,
                    SUM(CASE WHEN h.status='FAILED' THEN 1 ELSE 0 END) as f,
                    MAX(h.created_at) as last_act
                   FROM inject_history h
                   JOIN inject_batches b ON h.batch_id = b.id
                   JOIN clients c ON b.client_id = c.id
                   GROUP BY c.id
                   ORDER BY total DESC";
$resAllClients = $conn->query($sqlAllClients);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Inject Dashboard</h3>
            <p class="text-subtitle text-muted">Statistik & Laporan Performa Inject Paket Data.</p>
        </div>
        <div class="col-12 col-md-6 text-end">
            <form method="POST" target="_blank" class="d-inline">
                <button type="submit" name="export_stats" class="btn btn-success shadow-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export Report (CSV)
                </button>
            </form>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-primary border-5">
                <div class="card-body px-4 py-4-5">
                    <div class="row">
                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                            <div class="stats-icon blue mb-2">
                                <i class="bi bi-layers-fill"></i>
                            </div>
                        </div>
                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                            <h6 class="text-muted font-semibold">Total Requests</h6>
                            <h6 class="font-extrabold mb-0"><?= number_format($gTotal) ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-success border-5">
                <div class="card-body px-4 py-4-5">
                    <div class="row">
                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                            <div class="stats-icon green mb-2">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        </div>
                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                            <h6 class="text-muted font-semibold">Successful</h6>
                            <h6 class="font-extrabold mb-0"><?= number_format($gSuccess) ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-danger border-5">
                <div class="card-body px-4 py-4-5">
                    <div class="row">
                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                            <div class="stats-icon red mb-2">
                                <i class="bi bi-x-circle-fill"></i>
                            </div>
                        </div>
                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                            <h6 class="text-muted font-semibold">Failed</h6>
                            <h6 class="font-extrabold mb-0"><?= number_format($gFailed) ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h6 class="m-0 fw-bold text-dark">Global Success Rate</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div style="height: 250px; width: 250px; position: relative;">
                        <canvas id="chartGlobal"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h6 class="m-0 fw-bold text-dark">Top 10 Clients Performance</h6>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="chartClient"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title m-0">Detail Performa Per Client</h5>
        </div>
        <div class="card-body px-0 py-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Client Name</th>
                            <th class="text-center">Total Req</th>
                            <th class="text-center">Success</th>
                            <th class="text-center">Failed</th>
                            <th class="text-center">Success Rate</th>
                            <th class="pe-4 text-end">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($resAllClients->num_rows > 0): ?>
                            <?php while($c = $resAllClients->fetch_assoc()): 
                                $total = intval($c['total']);
                                $s = intval($c['s']);
                                $f = intval($c['f']);
                                $rate = ($total > 0) ? round(($s/$total)*100, 1) : 0;
                                
                                // Warna Rate
                                $rateColor = 'success';
                                if($rate < 50) $rateColor = 'danger';
                                elseif($rate < 80) $rateColor = 'warning text-dark';
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($c['company_name']) ?></td>
                                <td class="text-center"><span class="badge bg-light text-dark border"><?= number_format($total) ?></span></td>
                                <td class="text-center text-success fw-bold"><?= number_format($s) ?></td>
                                <td class="text-center text-danger fw-bold"><?= number_format($f) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $rateColor ?>"><?= $rate ?>%</span>
                                </td>
                                <td class="pe-4 text-end text-muted small">
                                    <?= date('d M Y, H:i', strtotime($c['last_act'])) ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data inject.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // DATA DARI PHP
        const globalSuccess = <?= $gSuccess ?>;
        const globalFailed = <?= $gFailed ?>;
        const globalPending = <?= $gPending ?>;
        
        const clientLabels = <?= json_encode($chartLabels) ?>;
        const clientSuccessData = <?= json_encode($chartDataSuccess) ?>;
        const clientFailedData = <?= json_encode($chartDataFailed) ?>;

        // 1. CHART GLOBAL (Doughnut)
        const ctxGlobal = document.getElementById('chartGlobal').getContext('2d');
        new Chart(ctxGlobal, {
            type: 'doughnut',
            data: {
                labels: ['Success', 'Failed', 'Pending'],
                datasets: [{
                    data: [globalSuccess, globalFailed, globalPending],
                    backgroundColor: ['#198754', '#dc3545', '#ffc107'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // 2. CHART CLIENT (Stacked Bar)
        const ctxClient = document.getElementById('chartClient').getContext('2d');
        new Chart(ctxClient, {
            type: 'bar',
            data: {
                labels: clientLabels,
                datasets: [
                    {
                        label: 'Success',
                        data: clientSuccessData,
                        backgroundColor: '#198754',
                        barPercentage: 0.6
                    },
                    {
                        label: 'Failed',
                        data: clientFailedData,
                        backgroundColor: '#dc3545',
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true }
                },
                plugins: {
                    legend: { position: 'top' }
                }
            }
        });
    });
</script>