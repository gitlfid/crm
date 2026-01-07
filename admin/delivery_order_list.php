<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. INIT FILTER VARIABLES ---
$search   = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';

// Bangun Query WHERE
$where = "1=1";

// Filter Pencarian (No DO)
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND d.do_number LIKE '%$safe_search%'";
}

// Filter Client
if (!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND c.id = $safe_client";
}

// --- 3. LOGIKA EXPORT EXCEL (CSV - ITEM PER ROW) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    // Ambil Data Header DO
    $sqlEx = "SELECT d.*, c.company_name, c.address, p.invoice_id, i.quotation_id
              FROM delivery_orders d 
              JOIN payments p ON d.payment_id = p.id 
              JOIN invoices i ON p.invoice_id = i.id
              JOIN quotations q ON i.quotation_id = q.id
              JOIN clients c ON q.client_id = c.id
              WHERE $where
              ORDER BY d.created_at DESC";
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=DeliveryOrders_Detailed_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, array('DO Number', 'Delivery Date', 'Client', 'Address', 'Item Name', 'Unit (Qty)', 'Charge Mode', 'Description', 'Receiver Name', 'Receiver Phone', 'Status'));
    
    while($row = $resEx->fetch_assoc()) {
        // --- Ambil Detail Item ---
        $inv_id = $row['invoice_id'];
        $quo_id = $row['quotation_id'];
        
        $itemsData = [];
        
        // Prioritas 1: Ambil dari Invoice Items
        $items_sql = "SELECT item_name, qty, card_type, description FROM invoice_items WHERE invoice_id = $inv_id";
        $resItems = $conn->query($items_sql);
        
        // Prioritas 2: Jika kosong, ambil dari Quotation Items
        if($resItems->num_rows == 0) {
            $items_sql = "SELECT item_name, qty, card_type, description FROM quotation_items WHERE quotation_id = $quo_id";
            $resItems = $conn->query($items_sql);
        }

        while($itm = $resItems->fetch_assoc()) {
            $itemsData[] = $itm;
        }

        // --- TULIS KE CSV (SATU BARIS PER ITEM) ---
        if (count($itemsData) > 0) {
            foreach ($itemsData as $item) {
                fputcsv($output, array(
                    $row['do_number'],
                    $row['do_date'],
                    $row['company_name'],
                    $row['address'],
                    $item['item_name'],         // Item Sendiri
                    floatval($item['qty']),     // Qty Sendiri
                    $item['card_type'],         // Charge Mode Sendiri
                    $item['description'],       // Desc Sendiri
                    $row['pic_name'],
                    $row['pic_phone'],
                    strtoupper($row['status'])
                ));
            }
        } else {
            // Fallback: Jika DO tidak ada item (kasus jarang/error), tetap print header DO
            fputcsv($output, array(
                $row['do_number'],
                $row['do_date'],
                $row['company_name'],
                $row['address'],
                '- No Item -', '', '', '', // Kolom Item Kosong
                $row['pic_name'],
                $row['pic_phone'],
                strtoupper($row['status'])
            ));
        }
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML (TIDAK BERUBAH) ---
$page_title = "Delivery Orders";
include 'includes/header.php';
include 'includes/sidebar.php';

$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// QUERY DATA TAMPILAN DASHBOARD
$sql = "SELECT d.*, c.company_name, c.address, p.invoice_id, i.quotation_id
        FROM delivery_orders d 
        JOIN payments p ON d.payment_id = p.id 
        JOIN invoices i ON p.invoice_id = i.id
        JOIN quotations q ON i.quotation_id = q.id
        JOIN clients c ON q.client_id = c.id
        WHERE $where
        ORDER BY d.created_at DESC";
$res = $conn->query($sql);
?>

<style>
    .table-responsive { overflow: visible !important; }
    .item-list { font-size: 0.85rem; }
    .item-row { border-bottom: 1px dashed #eee; padding: 2px 0; }
    .item-row:last-child { border-bottom: none; }
</style>

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
            <div class="row g-3">
                <div class="col-lg-9">
                    <form method="GET" class="row g-2">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Cari No DO..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <select name="client_id" class="form-select">
                                <option value="">- Semua Perusahaan -</option>
                                <?php 
                                if($clients->num_rows > 0) {
                                    $clients->data_seek(0);
                                    while($c = $clients->fetch_assoc()): 
                                ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="col-lg-3 border-start d-flex align-items-center justify-content-end">
                    <form method="POST" class="w-100">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="client_id" value="<?= htmlspecialchars($f_client) ?>">
                        
                        <button type="submit" name="export_excel" class="btn btn-success w-100 text-white">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export to Excel
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if(!empty($search) || !empty($f_client)): ?>
                <div class="mt-3 text-center border-top pt-2">
                    <small class="text-muted">Filter aktif.</small> 
                    <a href="delivery_order_list.php" class="text-danger text-decoration-none fw-bold ms-2">Reset Filter</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive" style="overflow:visible;">
                <table class="table table-hover align-middle table-sm" id="table1">
                    <thead class="bg-light">
                        <tr>
                            <th>DO Number</th>
                            <th>Date</th>
                            <th width="15%">Client & Address</th>
                            <th width="20%">Item</th>
                            <th>Unit</th>
                            <th>Charge Mode</th>
                            <th width="15%">Desc</th>
                            <th>Receiver</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <?php
                                // --- Ambil Detail Item untuk Tampilan HTML ---
                                $inv_id = $row['invoice_id'];
                                $quo_id = $row['quotation_id'];
                                
                                $items_sql = "SELECT item_name, qty, card_type, description FROM invoice_items WHERE invoice_id = $inv_id";
                                $resItems = $conn->query($items_sql);
                                if($resItems->num_rows == 0) {
                                    $items_sql = "SELECT item_name, qty, card_type, description FROM quotation_items WHERE quotation_id = $quo_id";
                                    $resItems = $conn->query($items_sql);
                                }
                                
                                // Simpan dalam array untuk diloop di TD
                                $itemsData = [];
                                while($itm = $resItems->fetch_assoc()) {
                                    $itemsData[] = $itm;
                                }
                            ?>
                            <tr>
                                <td class="align-top">
                                    <span class="fw-bold text-dark font-monospace"><?= $row['do_number'] ?></span>
                                </td>
                                <td class="align-top">
                                    <div class="small text-muted"><?= date('d M Y', strtotime($row['do_date'])) ?></div>
                                </td>
                                <td class="align-top">
                                    <div class="fw-bold text-primary mb-1"><?= htmlspecialchars($row['company_name']) ?></div>
                                    <div class="small text-muted lh-sm" style="font-size: 0.75rem;">
                                        <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars(substr($row['address'], 0, 50)) ?>...
                                    </div>
                                </td>

                                <td class="align-top">
                                    <?php foreach($itemsData as $d): ?>
                                        <div class="item-row fw-bold"><?= htmlspecialchars($d['item_name']) ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="align-top text-center">
                                    <?php foreach($itemsData as $d): ?>
                                        <div class="item-row"><?= floatval($d['qty']) ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="align-top text-center">
                                    <?php foreach($itemsData as $d): ?>
                                        <div class="item-row badge bg-light text-dark border"><?= htmlspecialchars($d['card_type']) ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="align-top">
                                    <?php foreach($itemsData as $d): ?>
                                        <div class="item-row small text-muted"><?= htmlspecialchars($d['description']) ?></div>
                                    <?php endforeach; ?>
                                </td>

                                <td class="align-top">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person-circle me-2 text-secondary"></i>
                                        <div class="lh-1">
                                            <span class="d-block small fw-bold"><?= htmlspecialchars($row['pic_name']) ?></span>
                                            <span class="d-block text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($row['pic_phone']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <?php 
                                        $st = $row['status']; 
                                        $bg = ($st == 'sent') ? 'success' : 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $bg ?>"><?= strtoupper($st) ?></span>
                                </td>
                                <td class="align-top">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-0" type="button" data-bs-toggle="dropdown">Act</button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 small">
                                            <li><a class="dropdown-item" href="delivery_order_print.php?id=<?= $row['id'] ?>" target="_blank"><i class="bi bi-printer me-2"></i> Print</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="delivery_order_form.php?edit_id=<?= $row['id'] ?>"><i class="bi bi-pencil me-2"></i> Edit</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    Tidak ada data Delivery Order ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($res->num_rows > 15): ?>
            <div class="card-footer bg-white border-top text-center py-3">
                <small class="text-muted">Menampilkan hasil pencarian</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>