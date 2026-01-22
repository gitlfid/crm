<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. LOGIKA DELETE (BARU) ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    
    // Hapus Item Detail dulu (Child)
    $conn->query("DELETE FROM delivery_order_items WHERE delivery_order_id = $del_id");
    
    // Hapus Header DO (Parent)
    if ($conn->query("DELETE FROM delivery_orders WHERE id = $del_id")) {
        echo "<script>alert('Delivery Order berhasil dihapus!'); window.location='delivery_order_list.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data: " . $conn->error . "'); window.location='delivery_order_list.php';</script>";
    }
    exit; // Stop eksekusi agar tidak lanjut render HTML
}

// --- 3. INIT FILTER VARIABLES ---
$search   = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';

// Bangun Query WHERE
$where = "1=1";

if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (d.do_number LIKE '%$safe_search%' OR i.invoice_no LIKE '%$safe_search%')";
}

if (!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND c.id = $safe_client";
}

// --- 4. LOGIKA EXPORT EXCEL (CSV) DETAIL & RAPI ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=DeliveryOrders_Detail_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');

    // Header CSV
    fputcsv($output, array(
        'DO Number', 
        'Ref Invoice', 
        'DO Date', 
        'Client Name', 
        'Delivery Address', 
        'Item Name', 
        'Unit (Qty)', 
        'Charge Mode', 
        'Description', 
        'Receiver Name', 
        'Receiver Phone', 
        'Status'
    ));
    
    // Query Data DO
    $sqlEx = "SELECT d.*, d.address as do_address_fix, 
                     c.company_name, c.address as client_address_fix, 
                     p.invoice_id, i.quotation_id, i.invoice_no
              FROM delivery_orders d 
              LEFT JOIN payments p ON d.payment_id = p.id 
              LEFT JOIN invoices i ON p.invoice_id = i.id
              LEFT JOIN quotations q ON i.quotation_id = q.id
              LEFT JOIN clients c ON q.client_id = c.id
              WHERE $where
              ORDER BY d.do_number DESC"; 
    
    $resEx = $conn->query($sqlEx);
    
    while($row = $resEx->fetch_assoc()) {
        $final_address = !empty($row['do_address_fix']) ? $row['do_address_fix'] : $row['client_address_fix'];
        $final_address = str_replace(array("\r", "\n"), " ", $final_address); 

        $do_id = $row['id'];
        $itemsData = [];

        // 1. Cek Item Edit Manual
        $sqlDOItems = "SELECT item_name, unit as qty, charge_mode, description FROM delivery_order_items WHERE delivery_order_id = $do_id";
        $resDOItems = $conn->query($sqlDOItems);

        if ($resDOItems && $resDOItems->num_rows > 0) {
            while($itm = $resDOItems->fetch_assoc()) {
                $itemsData[] = $itm;
            }
        } else {
            // 2. Ambil dari Invoice/Quotation
            $inv_id = $row['invoice_id'];
            if ($inv_id > 0) {
                $items_sql = "SELECT item_name, qty, card_type as charge_mode, description FROM invoice_items WHERE invoice_id = $inv_id";
                $resItems = $conn->query($items_sql);
                
                if($resItems->num_rows == 0 && isset($row['quotation_id'])) {
                    $resItems = $conn->query("SELECT item_name, qty, card_type as charge_mode, description FROM quotation_items WHERE quotation_id=".$row['quotation_id']);
                }
                
                while($itm = $resItems->fetch_assoc()) {
                    $itemsData[] = $itm;
                }
            }
        }

        // Loop Item (1 Baris per Item)
        if (count($itemsData) > 0) {
            foreach ($itemsData as $item) {
                $itemName = trim(preg_replace('/\s+/', ' ', $item['item_name']));
                $itemDesc = trim(preg_replace('/\s+/', ' ', $item['description']));

                fputcsv($output, array(
                    $row['do_number'], 
                    $row['invoice_no'],
                    $row['do_date'], 
                    $row['company_name'], 
                    $final_address,
                    $itemName, 
                    floatval($item['qty']), 
                    $item['charge_mode'], 
                    $itemDesc,
                    $row['pic_name'], 
                    $row['pic_phone'], 
                    strtoupper($row['status'])
                ));
            }
        } else {
            fputcsv($output, array(
                $row['do_number'], 
                $row['invoice_no'], 
                $row['do_date'], 
                $row['company_name'], 
                $final_address, 
                '- No Item Found -', 
                '', '', '', 
                $row['pic_name'], 
                $row['pic_phone'], 
                strtoupper($row['status'])
            ));
        }
    }
    
    fclose($output);
    exit();
}

// --- 5. LOAD TAMPILAN HTML ---
$page_title = "Delivery Orders";
include 'includes/header.php';
include 'includes/sidebar.php';

$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// Query Tampilan Web (Tetap LEFT JOIN agar DO Draft Muncul)
$sql = "SELECT d.*, d.address as do_address_fix, 
               c.company_name, c.address as client_address_fix, 
               p.invoice_id, i.quotation_id, i.invoice_no
        FROM delivery_orders d 
        LEFT JOIN payments p ON d.payment_id = p.id 
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN quotations q ON i.quotation_id = q.id
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE $where
        ORDER BY d.do_number DESC"; 
$res = $conn->query($sql);
?>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6 order-md-1 order-last">
            <h3>Delivery Orders</h3>
            <p class="text-subtitle text-muted">Daftar surat jalan pengiriman barang ke client.</p>
        </div>
        <div class="col-12 col-md-6 order-md-2 order-first text-end">
            <a href="delivery_order_form.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-2"></i> Create New</a>
        </div>
    </div>
</div>

<div class="page-content">
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Cari No DO / No Invoice..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
                    <select name="client_id" class="form-select">
                        <option value="">- Semua Perusahaan -</option>
                        <?php if($clients->num_rows > 0) { while($c = $clients->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['company_name']) ?>
                            </option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <button type="submit" formmethod="POST" name="export_excel" class="btn btn-success text-white btn-sm">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export
                    </button>
                </div>
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
                            <th>Ref Invoice</th> <th>Date</th>
                            <th width="20%">Client & Address</th>
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
                                // Alamat
                                $displayAddress = !empty($row['do_address_fix']) ? $row['do_address_fix'] : $row['client_address_fix'];

                                // Item Tampilan Web
                                $do_id = $row['id'];
                                $itemsData = [];
                                $sqlDOItems = "SELECT item_name, unit as qty, charge_mode FROM delivery_order_items WHERE delivery_order_id = $do_id";
                                $resDOItems = $conn->query($sqlDOItems);

                                if ($resDOItems && $resDOItems->num_rows > 0) {
                                    while($itm = $resDOItems->fetch_assoc()) $itemsData[] = $itm;
                                } else {
                                    $inv_id = $row['invoice_id'];
                                    if($inv_id > 0) {
                                        $items_sql = "SELECT item_name, qty, card_type as charge_mode FROM invoice_items WHERE invoice_id = $inv_id";
                                        $resItems = $conn->query($items_sql);
                                        if($resItems->num_rows == 0 && isset($row['quotation_id'])) {
                                            $resItems = $conn->query("SELECT item_name, qty, card_type as charge_mode FROM quotation_items WHERE quotation_id=".$row['quotation_id']);
                                        }
                                        while($itm = $resItems->fetch_assoc()) $itemsData[] = $itm;
                                    }
                                }
                            ?>
                            <tr>
                                <td class="fw-bold font-monospace"><?= $row['do_number'] ?></td>
                                
                                <td>
                                    <span class="badge bg-light text-primary border border-primary text-decoration-none">
                                        <?= $row['invoice_no'] ?>
                                    </span>
                                </td>

                                <td><?= date('d M Y', strtotime($row['do_date'])) ?></td>
                                <td>
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($row['company_name'] ?? 'Manual Client') ?></div>
                                    <div class="small text-muted" style="font-size: 0.8rem; line-height: 1.2;">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        <?= htmlspecialchars(substr($displayAddress, 0, 100)) . (strlen($displayAddress)>100 ? '...' : '') ?>
                                    </div>
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
                                        <div class="pb-1 mb-1"><span class="badge bg-light text-dark border"><?= htmlspecialchars($d['charge_mode']) ?></span></div>
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
                                        <a href="delivery_order_list.php?delete_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin ingin menghapus DO ini? Data tidak bisa dikembalikan.')">Del</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="text-center py-5 text-muted">Tidak ada data.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>