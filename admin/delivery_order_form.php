<?php
$page_title = "Form Delivery Order";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

$do_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$from_inv_id = isset($_GET['from_invoice_id']) ? intval($_GET['from_invoice_id']) : 0;

// --- GENERATOR NOMOR PATEN (DO + YYYYMM + 0001) ---
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
// ----------------------------------------------------

$do_number = $do_number_auto;
$do_date = date('Y-m-d');
$status = 'draft';
$pic_name = ''; $pic_phone = ''; $payment_id = 0;
$client_name = ''; $client_address = ''; $ref_info = '';

// KASUS 1: CREATE DARI INVOICE
if ($from_inv_id > 0) {
    $sqlPay = "SELECT id FROM payments WHERE invoice_id = $from_inv_id ORDER BY id DESC LIMIT 1";
    $resPay = $conn->query($sqlPay);
    if ($resPay->num_rows > 0) {
        $payRow = $resPay->fetch_assoc();
        $payment_id = $payRow['id'];
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
    } else {
        echo "<script>alert('Invoice belum dibayar.'); window.location='invoice_list.php';</script>"; exit;
    }
}

// KASUS 2: EDIT
if ($do_id > 0) {
    $sqlData = "SELECT d.*, c.company_name, c.address, i.invoice_no 
                FROM delivery_orders d JOIN payments p ON d.payment_id = p.id JOIN invoices i ON p.invoice_id = i.id
                JOIN quotations q ON i.quotation_id = q.id JOIN clients c ON q.client_id = c.id
                WHERE d.id = $do_id";
    $resData = $conn->query($sqlData);
    if ($resData->num_rows > 0) {
        $row = $resData->fetch_assoc();
        $do_number = $row['do_number']; // Pakai nomor lama
        $do_date = $row['do_date'];
        $status = $row['status'];
        $pic_name = $row['pic_name'];
        $pic_phone = $row['pic_phone'];
        $payment_id = $row['payment_id'];
        $client_name = $row['company_name'];
        $client_address = $row['address'];
        $ref_info = "Ref: Invoice #" . $row['invoice_no'];
    }
}

if (isset($_POST['save_do'])) {
    $p_id = intval($_POST['payment_id']);
    $d_num = $conn->real_escape_string($_POST['do_number']);
    $d_date = $_POST['do_date'];
    $d_stat = $_POST['status'];
    $d_pic = $conn->real_escape_string($_POST['pic_name']);
    $d_phone = $conn->real_escape_string($_POST['pic_phone']);
    $user_id = $_SESSION['user_id'];

    if ($do_id > 0) {
        $sql = "UPDATE delivery_orders SET do_number='$d_num', do_date='$d_date', status='$d_stat', pic_name='$d_pic', pic_phone='$d_phone' WHERE id=$do_id";
    } else {
        $sql = "INSERT INTO delivery_orders (do_number, do_date, status, payment_id, pic_name, pic_phone, created_by_user_id) 
                VALUES ('$d_num', '$d_date', '$d_stat', $p_id, '$d_pic', '$d_phone', $user_id)";
    }
    if ($conn->query($sql)) {
        echo "<script>alert('Berhasil!'); window.location='delivery_order_list.php';</script>";
    } else {
        echo "<script>alert('Gagal: " . $conn->error . "');</script>";
    }
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
                        <div class="mb-3"><label class="fw-bold">DO Number</label><input type="text" name="do_number" class="form-control fw-bold bg-light" value="<?= htmlspecialchars($do_number) ?>" readonly></div>
                        <div class="mb-3"><label class="fw-bold">Date</label><input type="date" name="do_date" class="form-control" value="<?= $do_date ?>" required></div>
                        <div class="mb-3"><label class="fw-bold">Client</label><input type="text" class="form-control bg-light" value="<?= htmlspecialchars($client_name) ?>" readonly></div>
                        <div class="mb-3"><label>Address</label><textarea class="form-control bg-light" rows="3" readonly><?= htmlspecialchars($client_address) ?></textarea></div>
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
                        <div class="alert alert-info mt-4 small">Item diambil otomatis dari Invoice (Charge Mode: Prepaid).</div>
                    </div>
                </div>
                <div class="text-end border-top pt-3 mt-3">
                    <a href="delivery_order_list.php" class="btn btn-light border">Cancel</a>
                    <button type="submit" name="save_do" class="btn btn-primary fw-bold">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>