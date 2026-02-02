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
    
    // [BARU] TANGKAP INVOICE TYPE & ADJUSTMENT
    $inv_type = $conn->real_escape_string($_POST['invoice_type']);
    $adj_label = $conn->real_escape_string($_POST['adj_label']);
    $raw_adj   = $_POST['adj_amount']; // Perlu dibersihkan formatnya
    
    // TENTUKAN CURRENCY BERDASARKAN TYPE (Agar sinkron)
    $new_currency = ($inv_type == 'International') ? 'USD' : 'IDR';
    
    // Bersihkan Format Angka Adjustment Sesuai Currency
    $clean_adj = str_replace(['Rp', '$', ' '], '', $raw_adj);
    if ($new_currency == 'IDR') {
        // IDR: 100.000 -> Hapus titik, ubah koma jadi titik desimal (jika ada)
        $clean_adj = str_replace('.', '', $clean_adj); 
        $clean_adj = str_replace(',', '.', $clean_adj); 
    } else {
        // USD: 1,000.00 -> Hapus koma
        $clean_adj = str_replace(',', '', $clean_adj); 
    }
    $adj_amount_db = floatval($clean_adj);

    // 1. Update Table Invoice Header (Termasuk Type & Adjustment)
    $sqlUpdate = "UPDATE invoices SET 
                  invoice_date='$inv_date', 
                  due_date='$due_date', 
                  payment_method='$pymt_method', 
                  invoice_type='$inv_type',
                  adjustment_label='$adj_label',
                  adjustment_amount='$adj_amount_db'
                  WHERE id=$inv_id";
    $conn->query($sqlUpdate);

    // 2. Update Table Quotation (Client, PO Ref, & Currency)
    $q_check = $conn->query("SELECT quotation_id FROM invoices WHERE id=$inv_id")->fetch_assoc();
    $q_id = $q_check['quotation_id'];

    if (isset($_POST['client_id'])) {
        $new_client_id = intval($_POST['client_id']);
        $conn->query("UPDATE quotations SET client_id=$new_client_id, currency='$new_currency' WHERE id=$q_id");
    }

    if (isset($_POST['po_ref'])) {
        $po_ref = $conn->real_escape_string($_POST['po_ref']);
        $conn->query("UPDATE quotations SET po_number_client='$po_ref' WHERE id=$q_id");
    }

    // Gunakan currency baru untuk logika pembersihan harga item
    $curr = $new_currency;

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
            
            // Handle Desimal Qty
            $raw_qty = $qtys[$i];
            $clean_qty = str_replace(',', '.', $raw_qty);
            $it_qty  = floatval($clean_qty); 
            
            // Handle Harga Sesuai Currency Baru
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

// FORMAT TAMPILAN ADJUSTMENT
$adj_val = floatval($invoice['adjustment_amount'] ?? 0);
if ($invoice['currency'] == 'IDR') {
    $display_adj = number_format($adj_val, 0, ',', '.');
} else {
    $display_adj = number_format($adj_val, 2, '.', ',');
}
?>

<div class="page-heading">
    <h3>Edit Invoice: <?= $invoice['invoice_no'] ?></h3>
    <div class="alert alert-light-warning border-warning">
        <i class="bi bi-pencil-square me-2"></i>
        <strong>Mode Edit:</strong> Adjustment bersifat opsional. Kosongkan atau isi 0 jika tidak ada. Gunakan tanda minus (-) untuk pengurangan/diskon.
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
                        
                        <div class="mb-3">
                            <label class="fw-bold">Invoice Type</label>
                            <select name="invoice_type" id="invoice_type" class="form-select" onchange="autoSetCurrency()">
                                <option value="Domestic" <?= $invoice['invoice_type'] == 'Domestic' ? 'selected' : '' ?>>Domestic (IDR)</option>
                                <option value="International" <?= $invoice['invoice_type'] == 'International' ? 'selected' : '' ?>>International (USD)</option>
                            </select>
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
                            <label class="fw-bold">Currency (Auto)</label>
                            <input type="text" id="currency_display" class="form-control bg-light" value="<?= $invoice['currency'] ?>" readonly>
                        </div>

                        <div class="mt-2">
                            <label>Payment Method Label</label>
                            <input type="text" name="payment_method_col" class="form-control" value="<?= htmlspecialchars($invoice['payment_method']) ?>">
                        </div>

                        <div class="row mt-3 pt-3 border-top">
                            <div class="col-12"><label class="fw-bold text-success">Adjustment (Optional)</label></div>
                            <div class="col-6">
                                <input type="text" name="adj_label" class="form-control form-control-sm" placeholder="Label (e.g. Rounding)" value="<?= htmlspecialchars($invoice['adjustment_label'] ?? '') ?>">
                            </div>
                            <div class="col-6">
                                <input type="text" name="adj_amount" class="form-control form-control-sm text-end" placeholder="Amount" value="<?= $display_adj ?>">
                            </div>
                            <div class="col-12"><small class="text-muted fst-italic">Gunakan tanda minus (-) untuk diskon/pengurangan.</small></div>
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
                                <th width="15%">Card Type</th>
                                <th width="10%">Qty</th>
                                <th width="20%">Unit Price</th>
                                <th>Desc</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($invoice_items as $itm): 
                                $db_price = floatval($itm['unit_price']);
                                if ($invoice['currency'] == 'IDR') {
                                    $display_price = number_format($db_price, 0, ',', '.');
                                } else {
                                    $display_price = number_format($db_price, 2, '.', ',');
                                }
                            ?>
                            <tr>
                                <td><input type="text" name="item_name[]" class="form-control" value="<?= htmlspecialchars($itm['item_name']) ?>" required></td>
                                <td><input type="text" name="card_type[]" class="form-control" value="<?= htmlspecialchars($itm['card_type']) ?>"></td>
                                
                                <td><input type="number" step="any" name="qty[]" class="form-control text-center" value="<?= floatval($itm['qty']) ?>" required></td>
                                
                                <td><input type="text" name="unit_price[]" class="form-control text-end" value="<?= $display_price ?>" required></td>
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
    function autoSetCurrency() {
        var type = document.getElementById('invoice_type').value;
        var disp = document.getElementById('currency_display');
        if(disp) {
            if(type === 'International') {
                disp.value = 'USD';
            } else {
                disp.value = 'IDR';
            }
        }
    }

    function addRow() {
        var table = document.getElementById("itemTable").getElementsByTagName('tbody')[0];
        var newRow = table.rows[0].cloneNode(true);
        var inputs = newRow.getElementsByTagName("input");
        for(var i=0; i<inputs.length; i++) { 
            inputs[i].value = ""; 
            if(inputs[i].name == "qty[]") {
                inputs[i].value = "1";
                inputs[i].setAttribute("step", "any");
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