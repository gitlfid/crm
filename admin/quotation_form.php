<?php
$page_title = "Quotation Form";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

$my_id = $_SESSION['user_id'];

// --- INIT VARIABLES ---
$is_edit = false;
$edit_id = 0;
$q_items = [];

// Default Values
$current_date     = date('Y-m-d');
$current_curr     = 'IDR';
$client_addr      = "";
$client_pic       = "";
$po_ref_val       = "";
$client_id_val    = ""; 

// Generate Nomor Baru
$prefix = "QLF" . date('Ym'); 
$sqlNum = "SELECT quotation_no FROM quotations WHERE quotation_no LIKE '$prefix%' ORDER BY quotation_no DESC LIMIT 1";
$resNum = $conn->query($sqlNum);
$newUrut = ($resNum && $resNum->num_rows > 0) ? ((int)substr($resNum->fetch_assoc()['quotation_no'], -4) + 1) : 1;
$display_quote_no = $prefix . str_pad($newUrut, 4, "0", STR_PAD_LEFT);

// --- EDIT MODE ---
if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $sqlHeader = "SELECT q.*, c.address, c.pic_name, c.company_name FROM quotations q JOIN clients c ON q.client_id = c.id WHERE q.id = $edit_id";
    $resHeader = $conn->query($sqlHeader);
    
    if ($resHeader && $resHeader->num_rows > 0) {
        $is_edit = true;
        $q_data = $resHeader->fetch_assoc();
        
        $display_quote_no = $q_data['quotation_no'];
        $current_date     = $q_data['quotation_date'];
        $current_curr     = $q_data['currency'];
        $po_ref_val       = $q_data['po_number_client'];
        $client_id_val    = $q_data['client_id'];
        $client_addr      = $q_data['address'];
        $client_pic       = $q_data['pic_name'];
        $page_title = "Edit Quotation: " . $display_quote_no;

        $resItems = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $edit_id");
        while($row = $resItems->fetch_assoc()) {
            $q_items[] = $row;
        }
    } else {
        echo "<script>alert('Data tidak ditemukan!'); window.location='quotation_list.php';</script>"; exit;
    }
} 
$clients = $conn->query("SELECT * FROM clients ORDER BY company_name ASC");

// --- SAVE PROCESS ---
if (isset($_POST['save_quotation'])) {
    $post_id = isset($_POST['quotation_id']) ? intval($_POST['quotation_id']) : 0;
    $q_no    = $conn->real_escape_string($_POST['quotation_no']);
    $q_date  = $_POST['quotation_date'];
    $client  = intval($_POST['client_id']);
    $curr    = $_POST['currency'];
    $po_ref  = isset($_POST['po_ref']) ? $conn->real_escape_string($_POST['po_ref']) : '';
    
    if ($post_id > 0) {
        $conn->query("UPDATE quotations SET quotation_date='$q_date', client_id=$client, currency='$curr', po_number_client='$po_ref' WHERE id=$post_id");
        $conn->query("DELETE FROM quotation_items WHERE quotation_id=$post_id"); 
        $quot_id = $post_id;
        $msg = "Quotation Updated Successfully!";
    } else {
        $chk = $conn->query("SELECT id FROM quotations WHERE quotation_no='$q_no'");
        if($chk->num_rows > 0) { $newUrut++; $q_no = $prefix . str_pad($newUrut, 4, "0", STR_PAD_LEFT); }
        $conn->query("INSERT INTO quotations (quotation_no, client_id, created_by_user_id, quotation_date, currency, status, po_number_client) VALUES ('$q_no', $client, $my_id, '$q_date', '$curr', 'draft', '$po_ref')");
        $quot_id = $conn->insert_id;
        $msg = "Quotation Created Successfully!";
    }

    // ITEM ARRAYS
    $items = $_POST['item_name'];
    $qtys  = $_POST['qty']; 
    $prices= $_POST['unit_price']; // Input Text Universal
    $descs = $_POST['description'];
    $dur_texts = $_POST['duration_text']; 

    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i])) {
            $raw_name = $conn->real_escape_string($items[$i]);
            
            // [FIX] Jangan hapus tanda kurung. Simpan apa adanya.
            $db_item_name = $raw_name;

            $text_duration = isset($dur_texts[$i]) ? $conn->real_escape_string($dur_texts[$i]) : 'One Time';
            $it_qty  = floatval($qtys[$i]);
            $it_dsc  = $conn->real_escape_string($descs[$i]);
            
            // --- [FIX] LOGIKA PEMBERSIH HARGA UNIVERSAL ---
            $raw_price = $prices[$i];
            // Hapus Rp, $, spasi
            $clean_price = str_replace(['Rp', '$', ' '], '', $raw_price);

            if ($curr == 'IDR') {
                // Format IDR: 1.500.000 (Titik = Ribuan)
                $clean_price = str_replace('.', '', $clean_price); 
                $clean_price = str_replace(',', '.', $clean_price); // Jika user iseng pakai koma
            } else {
                // Format USD: 1,500.50 (Koma = Ribuan)
                $clean_price = str_replace(',', '', $clean_price); 
            }
            
            $it_prc = floatval($clean_price);
            // ----------------------------------------------
            
            $conn->query("INSERT INTO quotation_items (quotation_id, item_name, qty, unit_price, description, card_type) VALUES ($quot_id, '$db_item_name', $it_qty, $it_prc, '$it_dsc', '$text_duration')");
        }
    }
    echo "<script>alert('$msg'); window.location='quotation_list.php';</script>"; exit;
}
?>

<div class="page-heading">
    <h3><?= $page_title ?></h3>
</div>

<div class="page-content">
    <form method="POST">
        <input type="hidden" name="quotation_id" value="<?= $is_edit ? $edit_id : 0 ?>">

        <div class="row">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light"><strong>Customer Info</strong></div>
                    <div class="card-body pt-3">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Client</label>
                            <select name="client_id" id="client_select" class="form-select" required onchange="fillClientInfo()">
                                <option value="">-- Choose Client --</option>
                                <?php 
                                if ($clients->num_rows > 0) {
                                    $clients->data_seek(0);
                                    while($c = $clients->fetch_assoc()): 
                                        $selected = ($is_edit && $client_id_val == $c['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $c['id'] ?>" data-addr="<?= htmlspecialchars($c['address']) ?>" data-pic="<?= htmlspecialchars($c['pic_name']) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($c['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">PO Reference</label>
                            <input type="text" name="po_ref" class="form-control" value="<?= htmlspecialchars($po_ref_val) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Address</label>
                            <textarea id="cl_addr" class="form-control bg-light" rows="3" readonly><?= $client_addr ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">PIC</label>
                            <input type="text" id="cl_pic" class="form-control bg-light" value="<?= $client_pic ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white"><strong>Quotation Details</strong></div>
                    <div class="card-body pt-3">
                        <div class="mb-2">
                            <label class="fw-bold">Quotation No</label>
                            <input type="text" name="quotation_no" class="form-control fw-bold fs-5 bg-light" value="<?= $display_quote_no ?>" readonly>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="fw-bold">Date</label>
                                <input type="date" name="quotation_date" class="form-control" value="<?= $current_date ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="fw-bold">Currency</label>
                                <select name="currency" class="form-select">
                                    <option value="IDR" <?= ($current_curr == 'IDR') ? 'selected' : '' ?>>IDR (Rp)</option>
                                    <option value="USD" <?= ($current_curr == 'USD') ? 'selected' : '' ?>>USD ($)</option>
                                </select>
                            </div>
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
                                <th width="10%">Qty</th>
                                <th width="25%">Charge Mode (Duration)</th> 
                                <th width="15%">Unit Price</th>
                                <th>Desc</th>
                                <th width="5%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($is_edit && count($q_items) > 0): ?>
                                <?php foreach($q_items as $itm): 
                                    // [FIX] Tampilkan nama item apa adanya (termasuk kurung)
                                    $db_name = $itm['item_name'];
                                    $duration_text_db = $itm['card_type'];
                                ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" value="<?= htmlspecialchars($db_name) ?>" required></td>
                                    
                                    <td><input type="number" name="qty[]" class="form-control text-center" value="<?= $itm['qty'] ?>" min="1" required></td>
                                    
                                    <td>
                                        <select class="form-select duration-select" onchange="updateDuration(this)">
                                            <option value="1" data-txt="One Time" <?= $duration_text_db=='One Time'?'selected':'' ?>>One Time</option>
                                            <option value="1" data-txt="Monthly" <?= $duration_text_db=='Monthly'?'selected':'' ?>>Monthly</option>
                                            <option value="3" data-txt="3 Months" <?= $duration_text_db=='3 Months'?'selected':'' ?>>3 Months</option>
                                            <option value="6" data-txt="6 Months" <?= $duration_text_db=='6 Months'?'selected':'' ?>>6 Months</option>
                                            <option value="12" data-txt="Annually" <?= strpos($duration_text_db, 'Annually')!==false?'selected':'' ?>>Annually (12 Mo)</option>
                                            <option value="custom" class="fw-bold text-primary">Custom...</option>
                                        </select>
                                        <input type="hidden" name="duration_text[]" class="duration-text-input" value="<?= $duration_text_db ?>">
                                        
                                        <div class="input-group duration-input-group d-none" style="flex-wrap: nowrap;">
                                            <input type="number" class="form-control text-center duration-custom" placeholder="0" oninput="updateCustomDuration(this)" style="min-width: 60px;">
                                            <select class="form-select duration-unit" onchange="updateCustomDuration(this)" style="max-width: 100px; background-color: #f8f9fa;">
                                                <option value="Months">Bulan</option>
                                                <option value="Years">Tahun</option>
                                            </select>
                                            <button class="btn btn-outline-secondary" type="button" onclick="resetDuration(this)"><i class="bi bi-x"></i></button>
                                        </div>
                                    </td>

                                    <td>
                                        <input type="text" name="unit_price[]" class="form-control text-end" 
                                               value="<?= ($current_curr=='IDR') ? number_format($itm['unit_price'], 0, ',', '.') : number_format($itm['unit_price'], 2, '.', ',') ?>" 
                                               required>
                                    </td>
                                    <td><input type="text" name="description[]" class="form-control" value="<?= htmlspecialchars($itm['description']) ?>"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" required></td>
                                    <td><input type="number" name="qty[]" class="form-control text-center" value="1" min="1" required></td>
                                    <td>
                                        <select class="form-select duration-select" onchange="updateDuration(this)">
                                            <option value="1" data-txt="One Time">One Time</option>
                                            <option value="1" data-txt="Monthly">Monthly</option>
                                            <option value="3" data-txt="3 Months">3 Months</option>
                                            <option value="6" data-txt="6 Months">6 Months</option>
                                            <option value="12" data-txt="Annually">Annually (12 Mo)</option>
                                            <option value="custom" class="fw-bold text-primary">Custom...</option>
                                        </select>
                                        <input type="hidden" name="duration_text[]" class="duration-text-input" value="One Time">
                                        
                                        <div class="input-group duration-input-group d-none" style="flex-wrap: nowrap;">
                                            <input type="number" class="form-control text-center duration-custom" placeholder="0" oninput="updateCustomDuration(this)" style="min-width: 60px;">
                                            <select class="form-select duration-unit" onchange="updateCustomDuration(this)" style="max-width: 100px; background-color: #f8f9fa;">
                                                <option value="Months">Bulan</option>
                                                <option value="Years">Tahun</option>
                                            </select>
                                            <button class="btn btn-outline-secondary" type="button" onclick="resetDuration(this)"><i class="bi bi-x"></i></button>
                                        </div>
                                    </td>
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
                <a href="quotation_list.php" class="btn btn-light border me-2">Cancel</a>
                <button type="submit" name="save_quotation" class="btn btn-primary px-4"><i class="bi bi-check-circle"></i> Save Quotation</button>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function fillClientInfo() {
        var select = document.getElementById("client_select");
        if(select && select.selectedIndex > 0) {
            var opt = select.options[select.selectedIndex];
            document.getElementById("cl_addr").value = opt.getAttribute("data-addr");
            document.getElementById("cl_pic").value = opt.getAttribute("data-pic");
        } else {
            document.getElementById("cl_addr").value = "";
            document.getElementById("cl_pic").value = "";
        }
    }

    function addRow() {
        var table = document.getElementById("itemTable").getElementsByTagName('tbody')[0];
        var newRow = table.rows[0].cloneNode(true);
        var inputs = newRow.getElementsByTagName("input");
        
        for(var i=0; i<inputs.length; i++) { 
            inputs[i].value = ""; 
            if(inputs[i].name == "qty[]") inputs[i].value = "1";
            if(inputs[i].classList.contains("duration-text-input")) inputs[i].value = "One Time"; 
        }
        
        var selectElem = newRow.querySelector('.duration-select');
        var inputGroup = newRow.querySelector('.duration-input-group');
        if(selectElem && inputGroup) {
            selectElem.value = "1";
            selectElem.classList.remove('d-none');
            inputGroup.classList.add('d-none');
        }
        table.appendChild(newRow);
    }

    function removeRow(btn) {
        var row = btn.parentNode.parentNode;
        var table = row.parentNode;
        if(table.rows.length > 1) {
            table.removeChild(row);
        } else {
            alert("Minimal harus ada 1 item.");
        }
    }

    function updateDuration(selectElem) {
        let row = selectElem.closest('tr');
        let inputGroup = row.querySelector('.duration-input-group');
        let customInput = row.querySelector('.duration-custom');
        let hiddenText = row.querySelector('.duration-text-input');

        if(selectElem.value === 'custom') {
            selectElem.classList.add('d-none');
            inputGroup.classList.remove('d-none');
            customInput.value = ""; 
            customInput.focus();
            hiddenText.value = ""; 
        } else {
            let selectedText = selectElem.options[selectElem.selectedIndex].getAttribute('data-txt');
            hiddenText.value = selectedText;
        }
    }

    function updateCustomDuration(elem) {
        let row = elem.closest('tr');
        let inputNum = row.querySelector('.duration-custom');
        let unitSel = row.querySelector('.duration-unit');
        let hiddenText = row.querySelector('.duration-text-input');
        
        if(inputNum.value) {
            hiddenText.value = inputNum.value + " " + unitSel.value;
        } else {
            hiddenText.value = "";
        }
    }

    function resetDuration(btn) {
        let row = btn.closest('tr');
        let selectElem = row.querySelector('.duration-select');
        let inputGroup = row.querySelector('.duration-input-group');
        let hiddenText = row.querySelector('.duration-text-input');
        
        let unitSel = row.querySelector('.duration-unit');
        unitSel.value = "Months"; 

        inputGroup.classList.add('d-none');
        selectElem.classList.remove('d-none');
        selectElem.value = "1"; 
        hiddenText.value = "One Time"; 
    }
</script>