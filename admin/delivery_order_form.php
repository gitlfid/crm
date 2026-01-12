<?php
$page_title = "Form Delivery Order";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Inisialisasi Variabel
$do_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$from_inv_id = isset($_GET['from_invoice_id']) ? intval($_GET['from_invoice_id']) : 0;

// --- 1. GENERATOR NOMOR DO (DO + YYYYMM + 0001) ---
$prefixDO = "DO" . date('Ym'); 
$sqlCek = "SELECT do_number FROM delivery_orders WHERE do_number LIKE '$prefixDO%' AND CHAR_LENGTH(do_number) = 12 ORDER BY do_number DESC LIMIT 1";
$resCek = $conn->query($sqlCek);
if ($resCek && $resCek->num_rows > 0) {
    $rowLast = $resCek->fetch_assoc();
    $lastUrut = (int)substr($rowLast['do_number'], -4); 
    $newUrut = $lastUrut + 1;
} else {
    $newUrut = 1;
}
$do_number_auto = $prefixDO . str_pad($newUrut, 4, "0", STR_PAD_LEFT);

// Default Values
$do_number = $do_number_auto;
$do_date = date('Y-m-d');
$status = 'draft';
$pic_name = '';
$pic_phone = '';
$payment_id = 0;
$client_name = '';
$client_address = '';
$ref_info = '';
$items_list = []; // Array penampung item

// --- KASUS 1: CREATE BARU DARI INVOICE ---
if ($from_inv_id > 0) {
    // Cari Payment ID
    $sqlPay = "SELECT id FROM payments WHERE invoice_id = $from_inv_id ORDER BY id DESC LIMIT 1";
    $resPay = $conn->query($sqlPay);
    
    if ($resPay->num_rows > 0) {
        $payRow = $resPay->fetch_assoc();
        $payment_id = $payRow['id'];

        // Info Client
        $sqlInfo = "SELECT c.company_name, c.address, c.pic_name, c.pic_phone, i.invoice_no 
                    FROM invoices i
                    JOIN quotations q ON i.quotation_id = q.id
                    JOIN clients c ON q.client_id = c.id
                    WHERE i.id = $from_inv_id";
        $info = $conn->query($sqlInfo)->fetch_assoc();
        
        if ($info) {
            $client_name = $info['company_name'];
            $client_address = $info['address'];
            $pic_name = $info['pic_name'];
            $pic_phone = $info['pic_phone'];
            $ref_info = "Ref: Invoice #" . $info['invoice_no'];
        }

        // AMBIL ITEM DARI INVOICE -> PAKSA 'PREPAID'
        $sqlItems = "SELECT item_name, qty, description FROM invoice_items WHERE invoice_id = $from_inv_id";
        $resItems = $conn->query($sqlItems);
        while($itm = $resItems->fetch_assoc()) {
            $itm['card_type'] = "Prepaid"; // [FORCE PREPAID]
            $items_list[] = $itm;
        }
    } else {
        echo "<script>alert('Error: Invoice belum dibayar/Payment belum ada.'); window.location='invoice_list.php';</script>";
        exit;
    }
}

// --- KASUS 2: EDIT EXISTING DO ---
if ($do_id > 0) {
    $sqlData = "SELECT d.*, c.company_name, c.address, i.invoice_no, i.id as inv_id 
                FROM delivery_orders d
                JOIN payments p ON d.payment_id = p.id
                JOIN invoices i ON p.invoice_id = i.id
                JOIN quotations q ON i.quotation_id = q.id
                JOIN clients c ON q.client_id = c.id
                WHERE d.id = $do_id";
    $resData = $conn->query($sqlData);
    if ($resData->num_rows > 0) {
        $row = $resData->fetch_assoc();
        
        // Load data header
        $do_number = $row['do_number']; // Pakai nomor lama
        $do_date = $row['do_date'];
        $status = $row['status'];
        $pic_name = $row['pic_name'];
        $pic_phone = $row['pic_phone'];
        $payment_id = $row['payment_id'];
        $client_name = $row['company_name'];
        $client_address = $row['address'];
        $ref_info = "Ref: Invoice #" . $row['invoice_no'];

        // LOAD ITEM (Cek apakah sudah pernah diedit/disimpan di delivery_order_items?)
        $sqlItems = "SELECT * FROM delivery_order_items WHERE delivery_order_id = $do_id";
        $resItems = $conn->query($sqlItems);
        
        if ($resItems->num_rows > 0) {
            // Jika sudah ada data tersimpan, pakai data itu (apa adanya)
            while($itm = $resItems->fetch_assoc()) {
                $items_list[] = [
                    'item_name' => $itm['item_name'],
                    'qty' => $itm['unit'],
                    'card_type' => $itm['charge_mode'], // Ini hasil save sebelumnya
                    'description' => $itm['description']
                ];
            }
        } else {
            // [FALLBACK] Jika belum ada item tersimpan (DO lama), ambil dari Invoice
            // DAN PAKSA JADI PREPAID
            $inv_id_src = $row['inv_id'];
            $sqlItemsInv = "SELECT item_name, qty, description FROM invoice_items WHERE invoice_id = $inv_id_src";
            $resItemsInv = $conn->query($sqlItemsInv);
            while($itm = $resItemsInv->fetch_assoc()) {
                $itm['card_type'] = "Prepaid"; // [FORCE PREPAID DISINI]
                $items_list[] = $itm;
            }
        }
    }
}

// --- PROSES SIMPAN ---
if (isset($_POST['save_do'])) {
    $p_id = intval($_POST['payment_id']);
    $d_num = $conn->real_escape_string($_POST['do_number']);
    $d_date = $_POST['do_date'];
    $d_stat = $_POST['status'];
    $d_pic = $conn->real_escape_string($_POST['pic_name']);
    $d_phone = $conn->real_escape_string($_POST['pic_phone']);
    $user_id = $_SESSION['user_id'];

    if ($do_id > 0) {
        // Update Header
        $sql = "UPDATE delivery_orders SET do_number='$d_num', do_date='$d_date', status='$d_stat', pic_name='$d_pic', pic_phone='$d_phone' WHERE id=$do_id";
        $conn->query($sql);
        $curr_do_id = $do_id;
        
        // Hapus item lama (agar terganti dengan yang baru dari form)
        $conn->query("DELETE FROM delivery_order_items WHERE delivery_order_id=$curr_do_id");
    } else {
        // Insert Header
        $sql = "INSERT INTO delivery_orders (do_number, do_date, status, payment_id, pic_name, pic_phone, created_by_user_id) 
                VALUES ('$d_num', '$d_date', '$d_stat', $p_id, '$d_pic', '$d_phone', $user_id)";
        $conn->query($sql);
        $curr_do_id = $conn->insert_id;
    }

    // Insert Item Baru dari Form
    $item_names = $_POST['item_name'];
    $qtys = $_POST['qty'];
    $modes = $_POST['charge_mode'];
    $descs = $_POST['description'];

    if (!empty($item_names)) {
        for ($i = 0; $i < count($item_names); $i++) {
            if (!empty($item_names[$i])) {
                $i_name = $conn->real_escape_string($item_names[$i]);
                $i_qty = floatval($qtys[$i]);
                $i_mode = $conn->real_escape_string($modes[$i]); // Ini akan menyimpan 'Prepaid' atau editan user
                $i_desc = $conn->real_escape_string($descs[$i]);

                $sqlItem = "INSERT INTO delivery_order_items (delivery_order_id, item_name, unit, charge_mode, description) 
                            VALUES ($curr_do_id, '$i_name', $i_qty, '$i_mode', '$i_desc')";
                $conn->query($sqlItem);
            }
        }
    }

    echo "<script>alert('Delivery Order Berhasil Disimpan!'); window.location='delivery_order_list.php';</script>";
}
?>

<div class="page-heading">
    <h3>Form Delivery Order</h3>
</div>

<div class="page-content">
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-primary fw-bold"><i class="bi bi-truck me-2"></i> Detail Pengiriman</h6>
            <?php if(!empty($ref_info)): ?>
                <span class="badge bg-warning text-dark"><?= $ref_info ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body pt-4">
            <form method="POST">
                <input type="hidden" name="payment_id" value="<?= $payment_id ?>">

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">DO Number</label>
                            <input type="text" name="do_number" class="form-control font-monospace fw-bold bg-light" value="<?= htmlspecialchars($do_number) ?>" required readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Delivery Date</label>
                            <input type="date" name="do_date" class="form-control" value="<?= $do_date ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Client</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($client_name) ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control bg-light" rows="3" readonly><?= htmlspecialchars($client_address) ?></textarea>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Receiver Name</label>
                            <input type="text" name="pic_name" class="form-control" value="<?= htmlspecialchars($pic_name) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Receiver Phone</label>
                            <input type="text" name="pic_phone" class="form-control" value="<?= htmlspecialchars($pic_phone) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= $status=='draft'?'selected':'' ?>>DRAFT</option>
                                <option value="sent" <?= $status=='sent'?'selected':'' ?>>SENT</option>
                                <option value="received" <?= $status=='received'?'selected':'' ?>>RECEIVED</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h6 class="fw-bold mb-3">Items to Deliver</h6>
                    <table class="table table-bordered">
                        <thead class="bg-light">
                            <tr>
                                <th width="35%">Item Name</th>
                                <th width="10%">Unit (Qty)</th>
                                <th width="20%">Charge Mode</th>
                                <th>Description</th>
                                <th width="5%">Act</th>
                            </tr>
                        </thead>
                        <tbody id="doItemsBody">
                            <?php if (!empty($items_list)): ?>
                                <?php foreach ($items_list as $item): ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['item_name']) ?>"></td>
                                    <td><input type="number" name="qty[]" class="form-control form-control-sm text-center" value="<?= floatval($item['qty']) ?>"></td>
                                    <td><input type="text" name="charge_mode[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['card_type'] ?? 'Prepaid') ?>"></td>
                                    <td><input type="text" name="description[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['description']) ?>"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm px-2" onclick="removeRow(this)">X</button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="qty[]" class="form-control form-control-sm text-center" value="1"></td>
                                    <td><input type="text" name="charge_mode[]" class="form-control form-control-sm" value="Prepaid"></td>
                                    <td><input type="text" name="description[]" class="form-control form-control-sm"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm px-2" onclick="removeRow(this)">X</button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="bi bi-plus"></i> Add Row</button>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="delivery_order_list.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" name="save_do" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-save me-2"></i> Save Delivery Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function removeRow(btn) {
    var row = btn.parentNode.parentNode;
    if(row.parentNode.rows.length > 1) {
        row.parentNode.removeChild(row);
    } else {
        alert("Minimal sisakan 1 baris item.");
    }
}

function addRow() {
    var table = document.getElementById("doItemsBody");
    var newRow = table.rows[0].cloneNode(true);
    var inputs = newRow.getElementsByTagName("input");
    for(var i=0; i<inputs.length; i++) {
        inputs[i].value = "";
        if(inputs[i].name == "charge_mode[]") inputs[i].value = "Prepaid"; // Default Prepaid untuk baris baru
        if(inputs[i].name == "qty[]") inputs[i].value = "1";
    }
    table.appendChild(newRow);
}
</script>   