<?php
$page_title = "Edit Invoice";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Cek ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID Invoice tidak ditemukan.");
}

$inv_id = intval($_GET['id']);

// --- PROSES UPDATE INVOICE ---
if (isset($_POST['update_invoice'])) {
    $inv_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    $pymt_method = $conn->real_escape_string($_POST['payment_method_col']);
    
    // 1. Update Table Invoice Header
    $sqlUpdate = "UPDATE invoices SET invoice_date='$inv_date', due_date='$due_date', payment_method='$pymt_method' WHERE id=$inv_id";
    $conn->query($sqlUpdate);

    // 2. Update Table Quotation (Client & PO Ref)
    $q_check = $conn->query("SELECT quotation_id FROM invoices WHERE id=$inv_id")->fetch_assoc();
    $q_id = $q_check['quotation_id'];

    if (isset($_POST['client_id'])) {
        $new_client_id = intval($_POST['client_id']);
        $conn->query("UPDATE quotations SET client_id=$new_client_id WHERE id=$q_id");
    }

    if (isset($_POST['po_ref'])) {
        $po_ref = $conn->real_escape_string($_POST['po_ref']);
        $conn->query("UPDATE quotations SET po_number_client='$po_ref' WHERE id=$q_id");
    }

    // 3. Ambil Mata Uang
    $curr_check = $conn->query("SELECT currency FROM quotations WHERE id=$q_id")->fetch_assoc();
    $curr = $curr_check['currency'];

    // 4. Update Items: Hapus lama, Insert baru
    $conn->query("DELETE FROM invoice_items WHERE invoice_id=$inv_id");

    $items = $_POST['item_name'];
    $qtys  = $_POST['qty'];
    $prices= $_POST['unit_price']; 
    $descs = $_POST['description'];
    $cards = isset($_POST['card_type']) ? $_POST['card_type'] : [];

    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i])) {
            $it_name = $conn->real_escape_string($items[$i]);
            
            // [UPDATE] Gunakan floatval agar desimal (koma) tersimpan
            $it_qty  = floatval($qtys[$i]); 
            
            // Logika Pembersih Harga
            $raw_price = $prices[$i];
            $clean_price = str_replace(['Rp', '$', ' '], '', $raw_price);

            if ($curr == 'IDR') {
                $clean_price = str_replace('.', '', $clean_price); 
                $clean_price = str_replace(',', '.', $clean_price); 
            } else {
                $clean_price = str_replace(',', '', $clean_price); 
            }
            
            $it_prc = floatval($clean_price);
            $it_dsc  = $conn->real_escape_string($descs[$i]);
            $it_card = isset($cards[$i]) ? $conn->real_escape_string($cards[$i]) : '';
            
            $conn->query("INSERT INTO invoice_items (invoice_id, item_name, qty, unit_price, description, card_type) 
                          VALUES ($inv_id, '$it_name', $it_qty, $it_prc, '$it_dsc', '$it_card')");
        }
    }

    echo "<script>alert('Invoice Updated Successfully!'); window.location='invoice_list.php';</script>";
}

// --- AMBIL DATA UNTUK TAMPILAN ---
$sql = "SELECT i.*, c.id as current_client_id, c.company_name, c.address, c.pic_name, q.po_number_client, q.currency
        FROM invoices i 
        JOIN quotations q ON i.quotation_id = q.id 
        JOIN clients c ON q.client_id = c.id 
        WHERE i.id = $inv_id";
$invoice = $conn->query($sql)->fetch_assoc();

if (!$invoice) die("Invoice tidak ditemukan.");
if ($invoice['status'] != 'draft') die("Invoice ini sudah tidak bisa diedit (Status: " . strtoupper($invoice['status']) . ")");

$clients_list = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// Ambil Items
$invoice_items = [];
$resItems = $conn->query("SELECT * FROM invoice_items WHERE invoice_id = $inv_id");

if ($resItems->num_rows > 0) {
    while($itm = $resItems->fetch_assoc()) {
        $invoice_items[] = $itm;
    }
} else {
    $q_id = $invoice['quotation_id'];
    $resQItems = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $q_id");
    while($itm = $resQItems->fetch_assoc()) {
        $invoice_items[] = $itm;
    }
}
?>

<div class="page-heading">
    <h3>Edit Invoice: <?= $invoice['invoice_no'] ?></h3>
    <div class="alert alert-light-warning border-warning">
        <i class="bi bi-pencil-square me-2"></i>
        <strong>Mode Edit:</strong>
        <ul class="mb-0 ps-3">
            <li>Anda dapat mengubah <strong>Qty dengan Desimal</strong> (misal: 1.5).</li>
            <li>Unit Price mendukung format <strong>USD (1,500.50)</strong> dan <strong>IDR (1.500.000)</strong>.</li>
        </ul>
    </div>
</div>

<div class="page-content">
    <form method="POST">
        <div class="row">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light"><strong>Bill To</strong></div>
                    <div class="card-body pt-3">
                        <div class="mb-3">
                            <label class="fw-bold">Client / Customer</label>
                            <select name="client_id" class="form-select bg-white">
                                <?php 
                                if ($clients_list->num_rows > 0) {
                                    $clients_list->data_seek(0);
                                    while($cl = $clients_list->fetch_assoc()): 
                                ?>
                                    <option value="<?= $cl['id'] ?>" <?= ($invoice['current_client_id'] == $cl['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cl['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">PO Reference</label>
                            <input type="text" name="po_ref" class="form-control" value="<?= htmlspecialchars($invoice['po_number_client']) ?>">
                        </div>
                        <div class="mb-3">
                            <label>Address</label>
                            <textarea class="form-control bg-light" rows="3" readonly><?= $invoice['address'] ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label>PIC</label>
                            <input type="text" class="form-control bg-light" value="<?= $invoice['pic_name'] ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark"><strong>Invoice Details</strong></div>
                    <div class="card-body pt-3">
                        <div class="mb-2">
                            <label class="fw-bold">Invoice No</label>
                            <input type="text" class="form-control fw-bold fs-5 bg-light" value="<?= $invoice['invoice_no'] ?>" readonly>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="fw-bold">Invoice Date</label>
                                <input type="date" name="invoice_date" class="form-control" value="<?= $invoice['invoice_date'] ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="fw-bold">Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?= $invoice['due_date'] ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Currency</label>
                            <input type="text" class="form-control bg-light" value="<?= $invoice['currency'] ?>" readonly>
                        </div>
                        <div class="mt-2">
                            <label>Payment Method Label</label>
                            <input type="text" name="payment_method_col" class="form-control" value="<?= htmlspecialchars($invoice['payment_method']) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Items List</strong>
                <button type="button" class="btn btn-sm btn-primary" onclick="addRow()"><i class="bi bi-plus"></i> Add Item</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="itemTable">
                        <thead class="bg-light">
                            <tr>
                                <th width="30%">Item Name</th>
                                <th width="15%">Card Type (Int)</th>
                                <th width="10%">Qty</th>
                                <th width="20%">Unit Price</th>
                                <th>Desc</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($invoice_items as $itm): ?>
                            <tr>
                                <td><input type="text" name="item_name[]" class="form-control" value="<?= htmlspecialchars($itm['item_name']) ?>" required></td>
                                <td><input type="text" name="card_type[]" class="form-control" value="<?= htmlspecialchars($itm['card_type']) ?>"></td>
                                
                                <td><input type="number" step="any" name="qty[]" class="form-control text-center" value="<?= floatval($itm['qty']) ?>" required></td>
                                
                                <td><input type="text" name="unit_price[]" class="form-control text-end" value="<?= $itm['unit_price'] ?>" required></td>
                                <td><input type="text" name="description[]" class="form-control" value="<?= htmlspecialchars($itm['description']) ?>"></td>
                                <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <a href="invoice_list.php" class="btn btn-light border me-2">Cancel</a>
                <button type="submit" name="update_invoice" class="btn btn-warning px-4"><i class="bi bi-save"></i> Update Invoice</button>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function addRow() {
        var table = document.getElementById("itemTable").getElementsByTagName('tbody')[0];
        // Clone baris pertama untuk mempertahankan format
        var newRow = table.rows[0].cloneNode(true);
        var inputs = newRow.getElementsByTagName("input");
        for(var i=0; i<inputs.length; i++) { 
            inputs[i].value = ""; 
            if(inputs[i].name == "qty[]") {
                inputs[i].value = "1"; 
                inputs[i].setAttribute("step", "any"); // Pastikan baris baru juga support desimal
            }
        }
        table.appendChild(newRow);
    }

    function removeRow(btn) {
        var row = btn.parentNode.parentNode;
        var table = row.parentNode;
        if(table.rows.length > 1) {
            table.removeChild(row);
        } else {
            alert("Invoice minimal harus memiliki 1 item.");
        }
    }
</script>