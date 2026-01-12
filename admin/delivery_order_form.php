<?php
$page_title = "Form Delivery Order";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Inisialisasi Variabel ID
$do_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$from_inv_id = isset($_GET['from_invoice_id']) ? intval($_GET['from_invoice_id']) : 0;

// --- [LOGIKA BARU] GENERATOR NOMOR DO (DO + YYYYMM + 0001) ---
// 1. Tentukan Prefix Bulan Ini (Contoh: DO202601)
$prefixDO = "DO" . date('Ym'); 

// 2. Cari nomor terakhir di database yang formatnya SUDAH BENAR (12 Digit)
// Filter CHAR_LENGTH(do_number) = 12 sangat penting agar data lama yang acak tidak merusak urutan.
$sqlCek = "SELECT do_number FROM delivery_orders 
           WHERE do_number LIKE '$prefixDO%' 
           AND CHAR_LENGTH(do_number) = 12 
           ORDER BY do_number DESC LIMIT 1";
$resCek = $conn->query($sqlCek);

if ($resCek && $resCek->num_rows > 0) {
    // Jika sudah ada (0001), ambil digit terakhir dan tambah 1
    $rowLast = $resCek->fetch_assoc();
    $lastNo = $rowLast['do_number']; 
    $lastUrut = (int)substr($lastNo, -4); // Ambil 4 angka belakang
    $newUrut = $lastUrut + 1;
} else {
    // Jika belum ada data bulan ini, mulai dari 1
    $newUrut = 1;
}

// 3. Gabungkan menjadi format final (Contoh: DO2026010001)
$do_number = $prefixDO . str_pad($newUrut, 4, "0", STR_PAD_LEFT);
// -------------------------------------------------------------

$do_date = date('Y-m-d');
$status = 'draft';
$pic_name = '';
$pic_phone = '';
$payment_id = 0;
$client_name = '';
$client_address = '';
$ref_info = '';

// --- KASUS 1: CREATE DARI INVOICE (AUTO FILL) ---
if ($from_inv_id > 0) {
    // Cari Payment ID dari Invoice ini
    $sqlPay = "SELECT id FROM payments WHERE invoice_id = $from_inv_id ORDER BY id DESC LIMIT 1";
    $resPay = $conn->query($sqlPay);
    
    if ($resPay->num_rows > 0) {
        $payRow = $resPay->fetch_assoc();
        $payment_id = $payRow['id'];

        // Ambil Data Client & Invoice
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
    } else {
        echo "<script>alert('Error: Invoice ini belum memiliki data pembayaran (Payment). Buat Payment terlebih dahulu.'); window.location='invoice_list.php';</script>";
        exit;
    }
}

// --- KASUS 2: EDIT EXISTING DO ---
if ($do_id > 0) {
    $sqlData = "SELECT d.*, c.company_name, c.address, i.invoice_no 
                FROM delivery_orders d
                JOIN payments p ON d.payment_id = p.id
                JOIN invoices i ON p.invoice_id = i.id
                JOIN quotations q ON i.quotation_id = q.id
                JOIN clients c ON q.client_id = c.id
                WHERE d.id = $do_id";
    $resData = $conn->query($sqlData);
    if ($resData->num_rows > 0) {
        $row = $resData->fetch_assoc();
        
        // [PENTING] Jika sedang Edit, GUNAKAN NOMOR LAMA, jangan generate baru!
        $do_number = $row['do_number'];
        
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

// --- PROSES SIMPAN ---
if (isset($_POST['save_do'])) {
    $p_id = intval($_POST['payment_id']);
    $d_num = $conn->real_escape_string($_POST['do_number']);
    $d_date = $_POST['do_date'];
    $d_stat = $_POST['status'];
    $d_pic = $conn->real_escape_string($_POST['pic_name']);
    $d_phone = $conn->real_escape_string($_POST['pic_phone']);
    
    if ($do_id > 0) {
        // Update Existing
        $sql = "UPDATE delivery_orders SET do_number='$d_num', do_date='$d_date', status='$d_stat', pic_name='$d_pic', pic_phone='$d_phone' WHERE id=$do_id";
    } else {
        // Insert New
        $sql = "INSERT INTO delivery_orders (do_number, do_date, status, payment_id, pic_name, pic_phone) 
                VALUES ('$d_num', '$d_date', '$d_stat', $p_id, '$d_pic', '$d_phone')";
    }

    if ($conn->query($sql)) {
        echo "<script>alert('Delivery Order Berhasil Disimpan!'); window.location='delivery_order_list.php';</script>";
    } else {
        echo "<script>alert('Gagal Simpan: " . $conn->error . "');</script>";
    }
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
                            <div class="form-text text-muted small">Nomor otomatis (Paten): DO + TahunBulan + 000X (Reset tiap bulan).</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Delivery Date</label>
                            <input type="date" name="do_date" class="form-control" value="<?= $do_date ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Client (Read Only)</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($client_name) ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control bg-light" rows="3" readonly><?= htmlspecialchars($client_address) ?></textarea>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Receiver Name (PIC)</label>
                            <input type="text" name="pic_name" class="form-control" value="<?= htmlspecialchars($pic_name) ?>" placeholder="Nama Penerima Barang" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Receiver Phone</label>
                            <input type="text" name="pic_phone" class="form-control" value="<?= htmlspecialchars($pic_phone) ?>" placeholder="Nomor HP Penerima">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= $status=='draft'?'selected':'' ?>>DRAFT</option>
                                <option value="sent" <?= $status=='sent'?'selected':'' ?>>SENT</option>
                                <option value="received" <?= $status=='received'?'selected':'' ?>>RECEIVED</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info mt-4 small">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            Item yang dikirim otomatis diambil dari Invoice terkait saat ditampilkan di list atau cetak surat jalan.
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="invoice_list.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" name="save_do" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-save me-2"></i> Save Delivery Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>