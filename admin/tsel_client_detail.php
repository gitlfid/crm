<?php
$page_title = "Client Inject Detail";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Validasi ID Client
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID Client tidak ditemukan!'); window.location='tsel_dashboard.php';</script>";
    exit;
}

$client_id = intval($_GET['id']);

// Ambil Nama Client
$client = $conn->query("SELECT company_name FROM clients WHERE id = $client_id")->fetch_assoc();
if (!$client) {
    echo "<script>alert('Client tidak valid!'); window.location='tsel_dashboard.php';</script>";
    exit;
}
$clientName = $client['company_name'];

// --- LOGIKA EXPORT DETAIL KE EXCEL (CSV) ---
if (isset($_POST['export_detail'])) {
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Detail_Inject_' . str_replace(' ', '_', $clientName) . '_' . date('Ymd') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Month', 'Total Request', 'Success', 'Failed', 'Success Rate (%)'));
    
    $sqlExport = "SELECT DATE_FORMAT(h.created_at, '%Y-%m') as month_key,
                         DATE_FORMAT(h.created_at, '%M %Y') as month_label,
                         COUNT(h.id) as total,
                         SUM(CASE WHEN h.status='SUCCESS' THEN 1 ELSE 0 END) as s,
                         SUM(CASE WHEN h.status='FAILED' THEN 1 ELSE 0 END) as f
                  FROM inject_history h
                  JOIN inject_batches b ON h.batch_id = b.id
                  WHERE b.client_id = $client_id
                  GROUP BY month_key
                  ORDER BY month_key DESC";
    $resExport = $conn->query($sqlExport);
    
    while($row = $resExport->fetch_assoc()) {
        $rate = ($row['total'] > 0) ? round(($row['s']/$row['total'])*100, 2) : 0;
        fputcsv($output, array($row['month_label'], $row['total'], $row['s'], $row['f'], $rate . '%'));
    }
    fclose($output);
    exit();
}

// --- QUERY DATA CHART & TABEL (GROUP BY MONTH) ---
$sqlMonthly = "SELECT DATE_FORMAT(h.created_at, '%Y-%m') as month_key,
                      DATE_FORMAT(h.created_at, '%M %Y') as month_label,
                      COUNT(h.id) as total,
                      SUM(CASE WHEN h.status='SUCCESS' THEN 1 ELSE 0 END) as s,
                      SUM(CASE WHEN h.status='FAILED' THEN 1 ELSE 0 END) as f
               FROM inject_history h
               JOIN inject_batches b ON h.batch_id = b.id
               WHERE b.client_id = $client_id
               GROUP BY month_key
               ORDER BY month_key ASC"; // Ascending untuk Chart agar urut waktu
$resMonthly = $conn->query($sqlMonthly);

$months = [];
$dataSuccess = [];
$dataFailed = [];
$tableData = []; 

while($row = $resMonthly->fetch_assoc()) {
    $months[] = $row['month_label'];
    $dataSuccess[] = intval($row['s']);
    $dataFailed[] = intval($row['f']);
    $tableData[] = $row;
}

// Balik urutan untuk tabel (Bulan Terbaru di atas)
$tableDataReversed = array_reverse($tableData);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-8">
            <h3>
                <a href="tsel_dashboard.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a> 
                Detail: <?= htmlspecialchars($clientName) ?>
            </h3>
            <p class="text-subtitle text-muted ps-4">Rincian performa inject bulanan.</p>
        </div>
        <div class="col-12 col-md-4 text-end">
            <form method="POST" target="_blank">
                <button type="submit" name="export_detail" class="btn btn-success shadow-sm">
                    <i class="bi bi-download me-2"></i> Export Detail CSV
                </button>
            </form>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="m-0 text-primary">Monthly Injection Trend</h5>
        </div>
        <div class="card-body">
            <div style="height: 300px;">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="m-0 text-dark">Rincian Data Per Bulan</h5>
        </div>
        <div class="card-body px-0 py-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Bulan</th>
                            <th class="text-center">Total Request</th>
                            <th class="text-center text-success">Success</th>
                            <th class="text-center text-danger">Failed</th>
                            <th class="text-center">Success Rate</th>
                            <th class="pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tableDataReversed)): ?>
                            <?php foreach($tableDataReversed as $row): 
                                $total = intval($row['total']);
                                $s = intval($row['s']);
                                $f = intval($row['f']);
                                $rate = ($total > 0) ? round(($s/$total)*100, 1) : 0;
                                
                                // Warna badge rate
                                $rateColor = 'success';
                                if($rate < 50) $rateColor = 'danger';
                                elseif($rate < 80) $rateColor = 'warning text-dark';
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= $row['month_label'] ?></td>
                                <td class="text-center"><span class="badge bg-light text-dark border"><?= number_format($total) ?></span></td>
                                <td class="text-center text-success fw-bold"><?= number_format($s) ?></td>
                                <td class="text-center text-danger fw-bold"><?= number_format($f) ?></td>
                                <td class="text-center"><span class="badge bg-<?= $rateColor ?>"><?= $rate ?>%</span></td>
                                <td class="pe-4 text-end">
                                    <a href="tsel_history.php?client_id=<?= $client_id ?>&month=<?= $row['month_key'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-list-ul me-1"></i> Lihat Data
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada history transaksi untuk client ini.</td></tr>
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
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [
                    {
                        label: 'Success',
                        data: <?= json_encode($dataSuccess) ?>,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Failed',
                        data: <?= json_encode($dataFailed) ?>,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });
</script>