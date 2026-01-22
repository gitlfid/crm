<?php
$page_title = "Form Delivery Order";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

$do_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$from_inv_id = isset($_GET['from_invoice_id']) ? intval($_GET['from_invoice_id']) : 0;

// GENERATOR NOMOR
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

$do_number = $do_number_auto;
$do_date = date('Y-m-d');
$status = 'draft';
$pic_name = ''; $pic_phone = ''; $payment_id = 0;
$client_name = ''; $client_address = ''; $ref_info = '';
$items_list = [];

// KASUS 1: CREATE DARI INVOICE
if ($from_inv_id > 0) {
    $sqlPay = "SELECT id FROM payments WHERE invoice_id = $from_inv_id ORDER BY id DESC LIMIT 1";
    $resPay = $conn->query($sqlPay);
    if ($resPay->num_rows > 0) {
        $payRow = $resPay->fetch_assoc();
        $payment_id = $payRow['id'];
    } else {
        $payment_id = 0; 
    }

    $sqlInfo = "SELECT c.company_name, c.address, c.pic_name, c.pic_phone, i.invoice_no 
                FROM invoices i JOIN quotations q ON i.quotation_id = q.id JOIN clients c ON q.client_id = c.id
                WHERE i.id = $from_inv_id";
    $info = $conn->query($sqlInfo)->fetch_assoc();
    if ($info) {
        $client_name = $info['company_name'];
        $client_address = $info['address']; 
        $pic_name = $info['pic_name'];
        $pic_phone = $info['pic_phone'];
        $ref_info = "Ref: Invoice #" . $info['invoice_no'];
    }
    
    $sqlItems = "SELECT item_name, qty, description FROM invoice_items WHERE invoice_id = $from_inv_id";
    $resItems = $conn->query($sqlItems);
    while($itm = $resItems->fetch_assoc()) {
        $itm['card_type'] = "Prepaid"; 
        $items_list[] = $itm;
    }
}

// KASUS 2: EDIT DO
if ($do_id > 0) {
    // [UPDATE] Mengambil kolom client_name dari tabel delivery_orders (jika ada)
    // Menggunakan IFNULL/Coalesce untuk kompatibilitas jika kolom belum ada di query tapi di DB sudah
    $sqlData = "SELECT d.*, d.address as do_addr, 
                       c.company_name, c.address as client_addr, 
                       i.invoice_no, i.id as inv_id 
                FROM delivery_orders d 
                LEFT JOIN payments p ON d.payment_id = p.id 
                LEFT JOIN invoices i ON p.invoice_id = i.id
                LEFT JOIN quotations q ON i.quotation_id = q.id 
                LEFT JOIN clients c ON q.client_id = c.id
                WHERE d.id = $do_id";
    $resData = $conn->query($sqlData);
    
    if ($resData->num_rows > 0) {
        $row = $resData->fetch_assoc();
        $do_number = $row['do_number'];
        $do_date = $row['do_date'];
        $status = $row['status'];
        $pic_name = $row['pic_name'];
        $pic_phone = $row['pic_phone'];
        $payment_id = $row['payment_id'] ? $row['payment_id'] : 0;
        
        // Cek apakah ada data client manual di DO (support fitur baru)
        if (isset($row['client_name']) && !empty($row['client_name'])) {
            $client_name = $row['client_name'];
        } else {
            $client_name = $row['company_name'];
        }
        
        $client_address = !empty($row['do_addr']) ? $row['do_addr'] : $row['client_addr'];
        $ref_info = "Ref: Invoice #" . $row['invoice_no'];

        $sqlItems = "SELECT * FROM delivery_order_items WHERE delivery_order_id = $do_id";
        $resItems = $conn->query($sqlItems);
        if ($resItems->num_rows > 0) {
            while($itm = $resItems->fetch_assoc()) {
                $mode = $itm['charge_mode'];
                if (stripos($mode, 'BBC') !== false || empty($mode)) $mode = 'Prepaid';
                $items_list[] = [
                    'item_name' => $itm['item_name'],
                    'qty' => $itm['unit'],
                    'card_type' => $mode, 
                    'description' => $itm['description']
                ];
            }
        }
    }
}

// --- PROSES SIMPAN ---
if (isset($_POST['save_do'])) {
    // [FIX 1] Handle Payment ID agar NULL jika 0 (Solusi Fatal Error Foreign Key)
    $p_id_raw = intval($_POST['payment_id']);
    $p_id_sql = ($p_id_raw > 0) ? $p_id_raw : "NULL";

    $d_num = $conn->real_escape_string($_POST['do_number']);
    $d_date = $_POST['do_date'];
    $d_stat = $_POST['status'];
    $d_pic = $conn->real_escape_string($_POST['pic_name']);
    $d_phone = $conn->real_escape_string($_POST['pic_phone']);
    $d_addr = $conn->real_escape_string($_POST['address']);
    
    // [FITUR BARU] Simpan Nama Client Manual
    $d_client = $conn->real_escape_string($_POST['client_name']);
    
    $user_id = $_SESSION['user_id'];

    if ($do_id > 0) {
        // Update DO (Termasuk client_name)
        // Pastikan Anda sudah menjalankan ALTER TABLE di database!
        $sql = "UPDATE delivery_orders SET 
                do_number='$d_num', 
                do_date='$d_date', 
                status='$d_stat', 
                payment_id=$p_id_sql, 
                pic_name='$d_pic', 
                pic_phone='$d_phone', 
                address='$d_addr',
                client_name='$d_client' 
                WHERE id=$do_id";
        
        if (!$conn->query($sql)) {
            // Fallback jika kolom client_name belum dibuat user (menghindari error)
            $sql_fallback = "UPDATE delivery_orders SET 
                do_number='$d_num', do_date='$d_date', status='$d_stat', payment_id=$p_id_sql, 
                pic_name='$d_pic', pic_phone='$d_phone', address='$d_addr' WHERE id=$do_id";
            $conn->query($sql_fallback);
        }
        
        $curr_do_id = $do_id;
        $conn->query("DELETE FROM delivery_order_items WHERE delivery_order_id=$curr_do_id");
    } else {
        // Insert DO Baru (Termasuk client_name)
        $sql = "INSERT INTO delivery_orders (do_number, do_date, status, payment_id, pic_name, pic_phone, created_by_user_id, address, client_name) 
                VALUES ('$d_num', '$d_date', '$d_stat', $p_id_sql, '$d_pic', '$d_phone', $user_id, '$d_addr', '$d_client')";
        
        if (!$conn->query($sql)) {
             // Fallback jika kolom client_name belum dibuat user
             $sql_fallback = "INSERT INTO delivery_orders (do_number, do_date, status, payment_id, pic_name, pic_phone, created_by_user_id, address) 
                VALUES ('$d_num', '$d_date', '$d_stat', $p_id_sql, '$d_pic', '$d_phone', $user_id, '$d_addr')";
             
             if($conn->query($sql_fallback)) {
                 $curr_do_id = $conn->insert_id;
             } else {
                 echo "<script>alert('Error: " . addslashes($conn->error) . "');</script>";
                 exit;
             }
        } else {
            $curr_do_id = $conn->insert_id;
        }
    }

    $item_names = $_POST['item_name'];
    $qtys = $_POST['qty'];
    $modes = $_POST['charge_mode'];
    $descs = $_POST['description'];

    if (!empty($item_names)) {
        for ($i = 0; $i < count($item_names); $i++) {
            if (!empty($item_names[$i])) {
                $i_name = $conn->real_escape_string($item_names[$i]);
                $i_qty = floatval($qtys[$i]);
                $i_mode = $conn->real_escape_string($modes[$i]); 
                $i_desc = $conn->real_escape_string($descs[$i]);
                $conn->query("INSERT INTO delivery_order_items (delivery_order_id, item_name, unit, charge_mode, description) VALUES ($curr_do_id, '$i_name', $i_qty, '$i_mode', '$i_desc')");
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
        <div class="card-body pt-4">
            <form method="POST">
                <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3"><label class="fw-bold">DO Number</label><input type="text" name="do_number" class="form-control fw-bold" value="<?= htmlspecialchars($do_number) ?>" required></div>
                        <div class="mb-3"><label class="fw-bold">Date</label><input type="date" name="do_date" class="form-control" value="<?= $do_date ?>" required></div>
                        
                        <div class="mb-3">
                            <label class="fw-bold">Client</label>
                            <input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($client_name) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="fw-bold text-primary">Address (Edit Disini)</label>
                            <textarea name="address" class="form-control" rows="4" required><?= htmlspecialchars($client_address) ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3"><label class="fw-bold">Receiver</label><input type="text" name="pic_name" class="form-control" value="<?= htmlspecialchars($pic_name) ?>" required></div>
                        <div class="mb-3"><label class="fw-bold">Phone</label><input type="text" name="pic_phone" class="form-control" value="<?= htmlspecialchars($pic_phone) ?>"></div>
                        <div class="mb-3"><label class="fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= $status=='draft'?'selected':'' ?>>DRAFT</option>
                                <option value="sent" <?= $status=='sent'?'selected':'' ?>>SENT</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <h6 class="fw-bold">Items</h6>
                    <table class="table table-bordered">
                        <thead class="bg-light"><tr><th>Item</th><th>Unit</th><th>Charge Mode</th><th>Desc</th><th>Act</th></tr></thead>
                        <tbody id="doItemsBody">
                            <?php if (!empty($items_list)): foreach ($items_list as $item): ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['item_name']) ?>"></td>
                                    <td><input type="number" step="any" name="qty[]" class="form-control form-control-sm text-center" value="<?= floatval($item['qty']) ?>"></td>
                                    <td><input type="text" name="charge_mode[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['card_type']) ?>"></td>
                                    <td><input type="text" name="description[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['description']) ?>"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control form-control-sm"></td>
                                    <td><input type="number" step="any" name="qty[]" class="form-control form-control-sm text-center" value="1"></td>
                                    <td><input type="text" name="charge_mode[]" class="form-control form-control-sm" value="Prepaid"></td>
                                    <td><input type="text" name="description[]" class="form-control form-control-sm"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">+ Add Row</button>
                </div>
                <div class="text-end border-top pt-3 mt-3">
                    <a href="delivery_order_list.php" class="btn btn-light border">Cancel</a>
                    <button type="submit" name="save_do" class="btn btn-primary fw-bold">Save Delivery Order</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
function removeRow(btn) { var row = btn.parentNode.parentNode; if(row.parentNode.rows.length > 1) row.parentNode.removeChild(row); }
function addRow() { var t=document.getElementById("doItemsBody"); var n=t.rows[0].cloneNode(true); var i=n.getElementsByTagName("input"); for(var x=0;x<i.length;x++){i[x].value="";if(i[x].name=="charge_mode[]")i[x].value="Prepaid";if(i[x].name=="qty[]"){i[x].value="1";i[x].setAttribute("step","any");}} t.appendChild(n); }
</script>