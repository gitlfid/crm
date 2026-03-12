<?php
$page_title = "Generate Invoice";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

$my_id = $_SESSION['user_id'];
$auto_inv = generateInvoiceNo($conn);
$is_manual = true; // Default Manual
$source_data = [];
$source_items = [];

// MODE 1: DARI PO / QUOTATION (OTOMATIS)
if (isset($_GET['source_id'])) {
    $is_manual = false;
    $q_id = intval($_GET['source_id']);
    
    // Ambil Data Header
    $sql = "SELECT q.*, c.company_name, c.address, c.pic_name 
            FROM quotations q 
            JOIN clients c ON q.client_id = c.id 
            WHERE q.id = $q_id";
    $source_data = $conn->query($sql)->fetch_assoc();
    
    if(!$source_data) die("Data Quotation tidak ditemukan.");

    // Ambil Item Quotation
    $resItems = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $q_id");
    while($itm = $resItems->fetch_assoc()) {
        $source_items[] = $itm;
    }
}

// MODE 2: MANUAL (PERLU LIST CLIENT)
$clients = $conn->query("SELECT * FROM clients ORDER BY company_name ASC");


// --- PROSES SIMPAN INVOICE ---
if (isset($_POST['save_invoice'])) {
    $inv_no = $conn->real_escape_string($_POST['invoice_no']);
    $inv_type = $conn->real_escape_string($_POST['invoice_type']);
    $inv_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    $pymt_method = $conn->real_escape_string($_POST['payment_method_col']);
    
    $curr = isset($_POST['currency']) ? $_POST['currency'] : 'IDR';

    $items = $_POST['item_name'];
    $qtys  = $_POST['qty'];
    $prices= $_POST['unit_price']; 
    $descs = $_POST['description'];
    $cards = isset($_POST['card_type']) ? $_POST['card_type'] : [];
    
    // Tentukan Quotation ID
    if ($is_manual) {
        $client_id = intval($_POST['client_id']);
        $po_ref = isset($_POST['po_ref']) ? $conn->real_escape_string($_POST['po_ref']) : '';
        $q_no_dummy = "Q-AUTO-" . time(); 
        
        // 1. Insert Quotation Dummy
        $sqlQ = "INSERT INTO quotations (quotation_no, client_id, created_by_user_id, quotation_date, currency, status, po_number_client) 
                 VALUES ('$q_no_dummy', $client_id, $my_id, '$inv_date', '$curr', 'invoiced', '$po_ref')";
        
        if($conn->query($sqlQ)) {
            $quot_id_ref = $conn->insert_id;
            
            // 2. Insert Items ke Quotation Items
            for ($i = 0; $i < count($items); $i++) {
                if (!empty($items[$i])) {
                    $it_name = $conn->real_escape_string($items[$i]);
                    $raw_qty = str_replace(',', '.', $qtys[$i]);
                    $it_qty  = floatval($raw_qty);
                    
                    // --- LOGIKA PEMBERSIH HARGA ---
                    $raw_price = $prices[$i];
                    $clean_price = str_replace(['Rp', '$', ' '], '', $raw_price);
                    
                    if ($curr == 'IDR') {
                        $clean_price = str_replace('.', '', $clean_price); 
                        $clean_price = str_replace(',', '.', $clean_price); 
                    } else {
                        $clean_price = str_replace(',', '', $clean_price); 
                    }
                    $it_prc = floatval($clean_price);
                    // ------------------------------------

                    $it_dsc  = $conn->real_escape_string($descs[$i]);
                    $it_card = isset($cards[$i]) ? $conn->real_escape_string($cards[$i]) : '';
                    
                    $conn->query("INSERT INTO quotation_items (quotation_id, item_name, qty, unit_price, description, card_type) 
                                  VALUES ($quot_id_ref, '$it_name', $it_qty, $it_prc, '$it_dsc', '$it_card')");
                }
            }
        } else {
            die("Error creating shadow quotation: " . $conn->error);
        }

    } else {
        $quot_id_ref = intval($_POST['quotation_id']);
    }

    // --- INSERT INVOICE ---
    $sqlInv = "INSERT INTO invoices (invoice_no, invoice_type, quotation_id, invoice_date, due_date, status, payment_method, created_by_user_id) 
               VALUES ('$inv_no', '$inv_type', $quot_id_ref, '$inv_date', '$due_date', 'draft', '$pymt_method', $my_id)";
    
    if ($conn->query($sqlInv)) {
        $invoice_id = $conn->insert_id;
        
        // --- INSERT ITEMS KE invoice_items ---
        if (!$is_manual) {
            for ($i = 0; $i < count($items); $i++) {
                if (!empty($items[$i])) {
                    $it_name = $conn->real_escape_string($items[$i]);
                    $raw_qty = str_replace(',', '.', $qtys[$i]);
                    $it_qty  = floatval($raw_qty);
                    
                    // --- LOGIKA PEMBERSIH HARGA ---
                    $raw_price = $prices[$i];
                    $clean_price = str_replace(['Rp', '$', ' '], '', $raw_price);
                    
                    if ($curr == 'IDR') {
                        $clean_price = str_replace('.', '', $clean_price); 
                        $clean_price = str_replace(',', '.', $clean_price); 
                    } else {
                        $clean_price = str_replace(',', '', $clean_price); 
                    }
                    $it_prc = floatval($clean_price);
                    // ------------------------------------

                    $it_dsc  = $conn->real_escape_string($descs[$i]);
                    $it_card = isset($cards[$i]) ? $conn->real_escape_string($cards[$i]) : '';
                    
                    $conn->query("INSERT INTO invoice_items (invoice_id, item_name, qty, unit_price, description, card_type) 
                                  VALUES ($invoice_id, '$it_name', $it_qty, $it_prc, '$it_dsc', '$it_card')");
                }
            }
        }

        // --- [BARU] INSERT ADJUSTMENTS (MULTIPLE ROWS) ---
        if (isset($_POST['adj_label']) && isset($_POST['adj_amount'])) {
            $adj_labels = $_POST['adj_label'];
            $adj_amounts = $_POST['adj_amount'];

            for ($j = 0; $j < count($adj_labels); $j++) {
                if (!empty($adj_labels[$j])) {
                    $lbl = $conn->real_escape_string($adj_labels[$j]);
                    $raw_amt = $adj_amounts[$j];
                    
                    // Bersihkan Format Uang Adjustment
                    $clean_amt = str_replace(['Rp', '$', ' '], '', $raw_amt);
                    if ($curr == 'IDR') {
                        $clean_amt = str_replace('.', '', $clean_amt); 
                        $clean_amt = str_replace(',', '.', $clean_amt); 
                    } else {
                        $clean_amt = str_replace(',', '', $clean_amt); 
                    }
                    $amt_db = floatval($clean_amt);

                    if ($amt_db != 0) {
                        $conn->query("INSERT INTO invoice_adjustments (invoice_id, label, amount) VALUES ($invoice_id, '$lbl', '$amt_db')");
                    }
                }
            }
        }
        
        if (!$is_manual) {
            $conn->query("UPDATE quotations SET status='invoiced' WHERE id=$quot_id_ref");
        }
        
        echo "<script>alert('Invoice Created Successfully!'); window.location='invoice_list.php';</script>";
    } else {
        echo "<script>alert('Gagal membuat invoice: " . $conn->error . "');</script>";
    }
}
?>

<div class="page-heading">
    <h3><?= $is_manual ? 'Create Manual Invoice' : 'Generate Invoice from PO' ?></h3>
    <?php if(!$is_manual): ?>
    <div class="alert alert-light-primary border-primary">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Validasi Invoice:</strong> Anda dapat menghapus atau mengubah Quantity item di bawah sebelum menyimpan.
    </div>
    <?php endif; ?>
</div>

<div class="page-content">
    <form method="POST">
        <?php if(!$is_manual): ?>
            <input type="hidden" name="quotation_id" value="<?= $source_data['id'] ?>">
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light"><strong>Bill To</strong></div>
                    <div class="card-body pt-3">
                        
                        <?php if($is_manual): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Client</label>
                                <select name="client_id" id="client_select" class="form-select" required onchange="fillClientInfo()">
                                    <option value="">-- Choose Client --</option>
                                    <?php while($c = $clients->fetch_assoc()): ?>
                                        <option value="<?= $c['id'] ?>" 
                                            data-addr="<?= htmlspecialchars($c['address']) ?>"
                                            data-pic="<?= htmlspecialchars($c['pic_name']) ?>">
                                            <?= htmlspecialchars($c['company_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">PO Reference (Manual)</label>
                                <input type="text" name="po_ref" class="form-control" placeholder="e.g. PO-001-CLIENT">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Address</label>
                                <textarea id="cl_addr" class="form-control bg-light" rows="3" readonly></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">PIC</label>
                                <input type="text" id="cl_pic" class="form-control bg-light" readonly>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label>Client</label>
                                <input type="text" class="form-control bg-light" value="<?= $source_data['company_name'] ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label>Address</label>
                                <textarea class="form-control bg-light" rows="3" readonly><?= $source_data['address'] ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label>PIC</label>
                                <input type="text" class="form-control bg-light" value="<?= $source_data['pic_name'] ?>" readonly>
                            </div>
                            <div class="alert alert-info py-2 small">
                                <strong>PO Ref:</strong> <?= $source_data['po_number_client'] ?>
                                <br>Quotation Ref: <strong><?= $source_data['quotation_no'] ?></strong>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white"><strong>Invoice Details</strong></div>
                    <div class="card-body pt-3">
                        <div class="mb-2">
                            <label class="fw-bold">Invoice No</label>
                            <input type="text" name="invoice_no" class="form-control fw-bold fs-5" value="<?= $auto_inv ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="fw-bold">Invoice Type</label>
                            <select name="invoice_type" id="invoice_type" class="form-select" onchange="autoSetCurrency()">
                                <option value="Domestic">Domestic (IDR)</option>
                                <option value="International">International (USD)</option>
                            </select>
                        </div>

                        <?php
                            // [LOGIKA PHP] HITUNG DEFAULT DUE DATE (+5 Working Days)
                            $startDate = new DateTime(); // Hari ini
                            $addDays = 0;
                            while ($addDays < 5) {
                                $startDate->modify('+1 day');
                                $dayOfWeek = $startDate->format('N'); // 1=Senin, 7=Minggu
                                // Jika bukan Sabtu (6) dan bukan Minggu (7), hitung sebagai hari kerja
                                if ($dayOfWeek < 6) {
                                    $addDays++;
                                }
                            }
                            $default_due_date = $startDate->format('Y-m-d');
                        ?>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="fw-bold">Invoice Date</label>
                                <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required onchange="updateDueDate()">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="fw-bold">Due Date (+5 Work Days)</label>
                                <input type="date" name="due_date" class="form-control" value="<?= $default_due_date ?>" required>
                            </div>
                        </div>
                        
                        <?php if($is_manual): ?>
                            <div class="mb-3">
                                <label class="fw-bold">Currency</label>
                                <select name="currency" id="currency" class="form-select">
                                    <option value="IDR">IDR (Rp)</option>
                                    <option value="USD">USD ($)</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="fw-bold">Currency</label>
                                <input type="text" class="form-control bg-light" value="<?= $source_data['currency'] ?>" readonly>
                                <input type="hidden" name="currency" value="<?= $source_data['currency'] ?>">
                            </div>
                        <?php endif; ?>

                        <div class="mt-2">
                            <label>Payment Method Label (Table)</label>
                            <input type="text" name="payment_method_col" class="form-control" value="Prepaid">
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <label class="fw-bold text-success mb-2">Adjustments / Payment Term</label>
                            <table class="table table-sm table-borderless mb-2" id="adjTable">
                                <tr>
                                    <td width="50%"><input type="text" name="adj_label[]" class="form-control form-control-sm" placeholder="Label (e.g. DP 50%)"></td>
                                    <td width="40%"><input type="text" name="adj_amount[]" class="form-control form-control-sm text-end" placeholder="Amount"></td>
                                    <td width="10%"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">x</button></td>
                                </tr>
                            </table>
                            <button type="button" class="btn btn-sm btn-outline-success w-100 border-dashed" onclick="addAdjRow()">+ Add Adjustment Row</button>
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
                            <?php if(!$is_manual): ?>
                                <?php foreach($source_items as $itm): 
                                    // [FIX LOGIKA] Format harga sesuai mata uang SEBELUM ditampilkan di input
                                    $db_price = floatval($itm['unit_price']);
                                    $curr_src = $source_data['currency'] ?? 'IDR';
                                    
                                    if ($curr_src == 'IDR') {
                                        // IDR: 100000.00 -> 100.000
                                        $display_price = number_format($db_price, 0, ',', '.');
                                    } else {
                                        // USD: 1000.00 -> 1,000.00
                                        $display_price = number_format($db_price, 2, '.', ',');
                                    }
                                ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" value="<?= htmlspecialchars($itm['item_name']) ?>" required></td>
                                    <td><input type="text" name="card_type[]" class="form-control" value="<?= htmlspecialchars($itm['card_type']) ?>"></td>
                                    <td><input type="number" step="any" name="qty[]" class="form-control text-center" value="<?= $itm['qty'] ?>" required></td>
                                    
                                    <td><input type="text" name="unit_price[]" class="form-control text-end" value="<?= $display_price ?>" required></td>
                                    
                                    <td><input type="text" name="description[]" class="form-control" value="<?= htmlspecialchars($itm['description']) ?>"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                                </tr>
                                <?php endforeach; ?>
                            
                            <?php else: ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" required></td>
                                    <td><input type="text" name="card_type[]" class="form-control" placeholder="Optional"></td>
                                    <td><input type="number" step="any" name="qty[]" class="form-control text-center" value="1" required></td>
                                    <td><input type="text" name="unit_price[]" class="form-control text-end" required></td>
                                    <td><input type="text" name="description[]" class="form-control"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <a href="invoice_list.php" class="btn btn-light border me-2">Cancel</a>
                <button type="submit" name="save_invoice" class="btn btn-success px-4"><i class="bi bi-check-circle"></i> Save Invoice</button>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function autoSetCurrency() {
        var type = document.getElementById('invoice_type').value;
        var curr = document.getElementById('currency');
        if(curr) {
            if(type === 'International') {
                curr.value = 'USD';
            } else {
                curr.value = 'IDR';
            }
        }
    }

    function fillClientInfo() {
        var select = document.getElementById("client_select");
        if(select && select.selectedIndex > 0) {
            var opt = select.options[select.selectedIndex];
            document.getElementById("cl_addr").value = opt.getAttribute("data-addr");
            document.getElementById("cl_pic").value = opt.getAttribute("data-pic");
        } else if(select) {
            document.getElementById("cl_addr").value = "";
            document.getElementById("cl_pic").value = "";
        }
    }

    // [UPDATE] LOGIKA JS DUE DATE (5 Working Days)
    function updateDueDate() {
        var invDateInput = document.getElementsByName("invoice_date")[0];
        var dueDateInput = document.getElementsByName("due_date")[0];
        
        if (invDateInput.value) {
            var date = new Date(invDateInput.value);
            var addedDays = 0;
            var targetDays = 5;

            // Loop sampai mendapatkan 5 hari kerja
            while (addedDays < targetDays) {
                date.setDate(date.getDate() + 1);
                var dayOfWeek = date.getDay(); // 0 = Minggu, 6 = Sabtu
                
                // Jika bukan Sabtu (6) dan bukan Minggu (0), hitung
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

    function addRow() {
        var table = document.getElementById("itemTable").getElementsByTagName('tbody')[0];
        var newRow = table.rows[0].cloneNode(true);
        var inputs = newRow.getElementsByTagName("input");
        for(var i=0; i<inputs.length; i++) { 
            inputs[i].value = ""; 
            if(inputs[i].name == "qty[]") {
                inputs[i].value="1"; 
                inputs[i].setAttribute("step", "any"); 
            }
        }
        table.appendChild(newRow);
    }

    // [NEW] FUNGSI TAMBAH BARIS ADJUSTMENT
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
        // Cek tabel mana (Item atau Adjustment)
        if(table.closest('#itemTable') && table.rows.length <= 1) {
            alert("Invoice minimal harus memiliki 1 item.");
        } else {
            if(table.closest('#adjTable')) row.remove();
            else table.removeChild(row);
        }
    }
</script>