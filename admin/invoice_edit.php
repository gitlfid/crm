<?php
$page_title = "Edit Invoice";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

if (!isset($_GET['id']) || empty($_GET['id'])) die("ID Invoice tidak ditemukan.");
$inv_id = intval($_GET['id']);

// --- PROSES UPDATE INVOICE ---
if (isset($_POST['update_invoice'])) {
    $inv_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    $pymt_method = $conn->real_escape_string($_POST['payment_method_col']);
    $inv_type = $conn->real_escape_string($_POST['invoice_type']);
    
    // TENTUKAN CURRENCY
    $new_currency = ($inv_type == 'International') ? 'USD' : 'IDR';

    // 1. Update Invoice Header
    $sqlUpdate = "UPDATE invoices SET 
                  invoice_date='$inv_date', 
                  due_date='$due_date', 
                  payment_method='$pymt_method', 
                  invoice_type='$inv_type' 
                  WHERE id=$inv_id";
    $conn->query($sqlUpdate);

    // 2. Update Quotation Related Info
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

    // 3. UPDATE ITEMS (Delete All & Re-insert)
    $curr = $new_currency;
    $conn->query("DELETE FROM invoice_items WHERE invoice_id=$inv_id");

    $items = $_POST['item_name'];
    $qtys  = $_POST['qty'];
    $prices= $_POST['unit_price']; 
    $descs = $_POST['description'];
    $cards = isset($_POST['card_type']) ? $_POST['card_type'] : [];

    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i])) {
            $it_name = $conn->real_escape_string($items[$i]);
            
            // Clean Qty
            $raw_qty = $qtys[$i];
            $clean_qty = str_replace(',', '.', $raw_qty);
            $it_qty  = floatval($clean_qty); 
            
            // Clean Price
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

    // 4. UPDATE ADJUSTMENTS (Delete All & Re-insert) - [FITUR BARU]
    $conn->query("DELETE FROM invoice_adjustments WHERE invoice_id=$inv_id");
    
    if (isset($_POST['adj_label'])) {
        $adj_labels = $_POST['adj_label'];
        $adj_amounts = $_POST['adj_amount'];

        for ($j = 0; $j < count($adj_labels); $j++) {
            if (!empty($adj_labels[$j])) {
                $lbl = $conn->real_escape_string($adj_labels[$j]);
                $raw_amt = $adj_amounts[$j];
                
                // Bersihkan format uang adjustment
                $clean_amt = str_replace(['Rp', '$', ' '], '', $raw_amt);
                if ($curr == 'IDR') {
                    $clean_amt = str_replace('.', '', $clean_amt); 
                    $clean_amt = str_replace(',', '.', $clean_amt); 
                } else {
                    $clean_amt = str_replace(',', '', $clean_amt); 
                }
                $amt_db = floatval($clean_amt);

                if ($amt_db != 0) { // Hanya simpan jika ada nilainya
                    $conn->query("INSERT INTO invoice_adjustments (invoice_id, label, amount) VALUES ($inv_id, '$lbl', '$amt_db')");
                }
            }
        }
    }

    echo "<script>alert('Invoice Updated Successfully!'); window.location='invoice_list.php';</script>";
}

// --- AMBIL DATA TAMPILAN ---
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
    while($itm = $resItems->fetch_assoc()) $invoice_items[] = $itm;
} else {
    $q_id = $invoice['quotation_id'];
    $resQItems = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $q_id");
    while($itm = $resQItems->fetch_assoc()) $invoice_items[] = $itm;
}

// Ambil Adjustments [BARU]
$adjustments = [];
$checkTbl = $conn->query("SHOW TABLES LIKE 'invoice_adjustments'");
if ($checkTbl && $checkTbl->num_rows > 0) {
    $resAdj = $conn->query("SELECT * FROM invoice_adjustments WHERE invoice_id = $inv_id");
    if ($resAdj) {
        while($row = $resAdj->fetch_assoc()) $adjustments[] = $row;
    }
}
?>

<div class="page-heading">
    <h3>Edit Invoice: <?= $invoice['invoice_no'] ?></h3>
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
                                <?php if ($clients_list->num_rows > 0) { $clients_list->data_seek(0); while($cl = $clients_list->fetch_assoc()): ?>
                                    <option value="<?= $cl['id'] ?>" <?= ($invoice['current_client_id'] == $cl['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cl['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="fw-bold">PO Reference</label><input type="text" name="po_ref" class="form-control" value="<?= htmlspecialchars($invoice['po_number_client']) ?>"></div>
                        <div class="mb-3"><label>Address</label><textarea class="form-control bg-light" rows="3" readonly><?= $invoice['address'] ?></textarea></div>
                        <div class="mb-3"><label>PIC</label><input type="text" class="form-control bg-light" value="<?= $invoice['pic_name'] ?>" readonly></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark"><strong>Invoice Details</strong></div>
                    <div class="card-body pt-3">
                        <div class="mb-2"><label class="fw-bold">Invoice No</label><input type="text" class="form-control fw-bold fs-5 bg-light" value="<?= $invoice['invoice_no'] ?>" readonly></div>
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
                                <input type="date" name="invoice_date" class="form-control" value="<?= $invoice['invoice_date'] ?>" required onchange="updateDueDate()">
                            </div>
                            <div class="col-6 mb-3"><label class="fw-bold">Due Date (+5 Work Days)</label><input type="date" name="due_date" class="form-control" value="<?= $invoice['due_date'] ?>" required></div>
                        </div>
                        <div class="mb-3"><label class="fw-bold">Currency (Auto)</label><input type="text" id="currency_display" class="form-control bg-light" value="<?= $invoice['currency'] ?>" readonly></div>
                        <div class="mt-2"><label>Payment Method Label</label><input type="text" name="payment_method_col" class="form-control" value="<?= htmlspecialchars($invoice['payment_method']) ?>"></div>

                        <div class="mt-4 pt-3 border-top">
                            <label class="fw-bold text-success mb-2">Adjustments / Payment Term</label>
                            <table class="table table-sm table-borderless mb-2" id="adjTable">
                                <?php if(count($adjustments) > 0): ?>
                                    <?php foreach($adjustments as $adj): 
                                        $val = floatval($adj['amount']);
                                        $dispVal = ($invoice['currency']=='IDR') ? number_format($val,0,',','.') : number_format($val,2,'.',',');
                                    ?>
                                    <tr>
                                        <td width="50%"><input type="text" name="adj_label[]" class="form-control form-control-sm" placeholder="Label (e.g. DP 50%)" value="<?= htmlspecialchars($adj['label']) ?>"></td>
                                        <td width="40%"><input type="text" name="adj_amount[]" class="form-control form-control-sm text-end" placeholder="Amount" value="<?= $dispVal ?>"></td>
                                        <td width="10%"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">x</button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td width="50%"><input type="text" name="adj_label[]" class="form-control form-control-sm" placeholder="Label (e.g. DP 50%)"></td>
                                        <td width="40%"><input type="text" name="adj_amount[]" class="form-control form-control-sm text-end" placeholder="Amount"></td>
                                        <td width="10%"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">x</button></td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                            <button type="button" class="btn btn-sm btn-outline-success w-100 border-dashed" onclick="addAdjRow()">+ Add Adjustment Row</button>
                            <div class="text-muted small mt-2 fst-italic">* Gunakan tanda minus (-) untuk pengurangan (misal: -500.000).</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Items List</strong>
                <button type="button" class="btn btn-sm btn-primary" onclick="addItemRow()"><i class="bi bi-plus"></i> Add Item</button>
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
                                $display_price = ($invoice['currency'] == 'IDR') ? number_format($db_price, 0, ',', '.') : number_format($db_price, 2, '.', ',');
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
        if(disp) disp.value = (type === 'International') ? 'USD' : 'IDR';
    }

    // [UPDATE] LOGIKA JS DUE DATE (5 Working Days)
    function updateDueDate() {
        var invDateInput = document.getElementsByName("invoice_date")[0];
        var dueDateInput = document.getElementsByName("due_date")[0];
        
        if (invDateInput.value) {
            var date = new Date(invDateInput.value);
            var addedDays = 0;
            var targetDays = 5;

            while (addedDays < targetDays) {
                date.setDate(date.getDate() + 1);
                var dayOfWeek = date.getDay(); // 0 = Minggu, 6 = Sabtu
                if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                    addedDays++;
                }
            }
            
            var yyyy = date.getFullYear();
            var mm = String(date.getMonth() + 1).padStart(2, '0');
            var dd = String(date.getDate()).padStart(2, '0');
            
            dueDateInput.value = yyyy + '-' + mm + '-' + dd;
        }
    }

    // Fungsi Tambah Baris Item
    function addItemRow() {
        var table = document.getElementById("itemTable").getElementsByTagName('tbody')[0];
        var newRow = table.rows[0].cloneNode(true);
        var inputs = newRow.getElementsByTagName("input");
        for(var i=0; i<inputs.length; i++) { 
            inputs[i].value = ""; 
            if(inputs[i].name == "qty[]") { inputs[i].value = "1"; inputs[i].setAttribute("step", "any"); }
        }
        table.appendChild(newRow);
    }

    // Fungsi Tambah Baris Adjustment
    function addAdjRow() {
        var table = document.getElementById("adjTable");
        var newRow = table.insertRow();
        newRow.innerHTML = `
            <td><input type="text" name="adj_label[]" class="form-control form-control-sm" placeholder="Label"></td>
            <td><input type="text" name="adj_amount[]" class="form-control form-control-sm text-end" placeholder="Amount"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">x</button></td>
        `;
    }

    function removeRow(btn) {
        var row = btn.parentNode.parentNode;
        var table = row.parentNode;
        // Cek apakah tabel item atau adjustment
        if (table.closest('#itemTable') && table.rows.length <= 1) {
            alert("Minimal 1 item harus ada.");
        } else {
            // Jika adjustment, bisa dihapus sampai habis
            // Jika item, sisakan 1
            if (table.closest('#adjTable')) {
                row.remove();
            } else {
                table.removeChild(row);
            }
        }
    }
</script>