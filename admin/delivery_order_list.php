<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. INIT FILTER VARIABLES ---
$search   = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';

// Bangun Query WHERE
$where = "1=1";

if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND d.do_number LIKE '%$safe_search%'";
}

if (!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND c.id = $safe_client";
}

// --- 3. LOGIKA EXPORT EXCEL ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    // Ambil Data
    $sqlEx = "SELECT d.*, c.company_name, c.address, p.invoice_id, i.quotation_id
              FROM delivery_orders d 
              JOIN payments p ON d.payment_id = p.id 
              JOIN invoices i ON p.invoice_id = i.id
              JOIN quotations q ON i.quotation_id = q.id
              JOIN clients c ON q.client_id = c.id
              WHERE $where
              ORDER BY d.do_number DESC"; // Urut No DO
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=DeliveryOrders_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, array('DO Number', 'Delivery Date', 'Client', 'Address', 'Item Name', 'Unit (Qty)', 'Charge Mode', 'Description', 'Receiver Name', 'Receiver Phone', 'Status'));
    
    while($row = $resEx->fetch_assoc()) {
        // Ambil Item dari Invoice (Sumber Asli)
        $itemsData = [];
        $inv_id = $row['invoice_id'];
        $quo_id = $row['quotation_id'];
        
        $items_sql = "SELECT item_name, qty, description FROM invoice_items WHERE invoice_id = $inv_id";
        $resItems = $conn->query($items_sql);
        
        if($resItems->num_rows == 0) {
            $items_sql = "SELECT item_name, qty, description FROM quotation_items WHERE quotation_id = $quo_id";
            $resItems = $conn->query($items_sql);
        }

        while($itm = $resItems->fetch_assoc()) {
            $itemsData[] = $itm;
        }

        if (count($itemsData) > 0) {
            foreach ($itemsData as $item) {
                fputcsv($output, array(
                    $row['do_number'],
                    $row['do_date'],
                    $row['company_name'],
                    $row['address'],
                    $item['item_name'],         
                    floatval($item['qty']),     
                    'Prepaid', // [FORCE PREPAID DI EXCEL]
                    $item['description'],       
                    $row['pic_name'],
                    $row['pic_phone'],
                    strtoupper($row['status'])
                ));
            }
        } else {
            fputcsv($output, array(
                $row['do_number'], $row['do_date'], $row['company_name'], $row['address'],
                '- No Item -', '', '', '', 
                $row['pic_name'], $row['pic_phone'], strtoupper($row['status'])
            ));
        }
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "Delivery Orders";
include 'includes/header.php';
include 'includes/sidebar.php';

$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// QUERY DASHBOARD
$sql = "SELECT d.*, c.company_name, c.address, p.invoice_id, i.quotation_id
        FROM delivery_orders d 
        JOIN payments p ON d.payment_id = p.id 
        JOIN invoices i ON p.invoice_id = i.id
        JOIN quotations q ON i.quotation_id = q.id
        JOIN clients c ON q.client_id = c.id
        WHERE $where
        ORDER BY d.do_number DESC"; // Urut sesuai No DO Paten
$res = $conn->query($sql);
?>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Delivery Orders</h3>
            <p class="text-subtitle text-muted">Daftar surat jalan pengiriman barang ke client.</p>
        </div>
    </div>
</div>

<div class="page-content">
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Cari No DO..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
                    <select name="client_id" class="form-select">
                        <option value="">- Semua Perusahaan -</option>
                        <?php if($clients->num_rows > 0) { 
                            while($c = $clients->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['company_name']) ?>
                            </option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <?php if(!empty($search) || !empty($f_client)): ?>
                        <a href="delivery_order_list.php" class="btn btn-danger"><i class="bi bi-x"></i></a>
                    <?php endif; ?>
                </div>
            </form>
            <form method="POST" class="mt-2 text-end">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="client_id" value="<?= htmlspecialchars($f_client) ?>">
                <button type="submit" name="export_excel" class="btn btn-success text-white btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export to Excel
                </button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-sm" id="table1">
                    <thead class="bg-light">
                        <tr>
                            <th>DO Number</th>
                            <th>Date</th>
                            <th width="20%">Client</th>
                            <th width="25%">Item</th>
                            <th class="text-center">Unit</th>
                            <th class="text-center">Charge Mode</th>
                            <th>Receiver</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <?php
                                // --- AMBIL ITEM DARI INVOICE (SUMBER ASLI) ---
                                $inv_id = $row['invoice_id'];
                                $quo_id = $row['quotation_id'];
                                
                                $items_sql = "SELECT item_name, qty FROM invoice_items WHERE invoice_id = $inv_id";
                                $resItems = $conn->query($items_sql);
                                
                                if($resItems->num_rows == 0) {
                                    $items_sql = "SELECT item_name, qty FROM quotation_items WHERE quotation_id = $quo_id";
                                    $resItems = $conn->query($items_sql);
                                }
                                
                                $itemsData = [];
                                while($itm = $resItems->fetch_assoc()) {
                                    $itemsData[] = $itm;
                                }
                            ?>
                            <tr>
                                <td class="fw-bold font-monospace"><?= $row['do_number'] ?></td>
                                <td><?= date('d M Y', strtotime($row['do_date'])) ?></td>
                                <td>
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($row['company_name']) ?></div>
                                    <div class="small text-muted"><?= substr($row['address'], 0, 30) ?>...</div>
                                </td>
                                <td>
                                    <?php foreach($itemsData as $d): ?>
                                        <div class="small fw-bold border-bottom pb-1 mb-1"><?= htmlspecialchars($d['item_name']) ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-center">
                                    <?php foreach($itemsData as $d): ?>
                                        <div class="pb-1 mb-1"><?= floatval($d['qty']) ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-center">
                                    <?php foreach($itemsData as $d): ?>
                                        <div class="pb-1 mb-1"><span class="badge bg-light text-dark border">Prepaid</span></div>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <div class="small fw-bold"><?= htmlspecialchars($row['pic_name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($row['pic_phone']) ?></div>
                                </td>
                                <td><span class="badge bg-secondary"><?= strtoupper($row['status']) ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="delivery_order_print.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Print</a>
                                        <a href="delivery_order_form.php?edit_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">Tidak ada data.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>