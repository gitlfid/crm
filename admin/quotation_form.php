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
    $prices= $_POST['unit_price'];
    $descs = $_POST['description'];
    
    // INPUT CHARGE MODE (DURATION) - BEBAS TEKS
    $dur_texts = $_POST['duration_text']; 

    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i])) {
            $raw_name = $conn->real_escape_string($items[$i]);
            
            // Simpan Teks Duration Bebas (misal: "2 Years (Promo)", "Monthly")
            $text_duration = isset($dur_texts[$i]) ? $conn->real_escape_string($dur_texts[$i]) : 'One Time';
            
            // Bersihkan nama item (Opsional: Hapus sisa kurung credential lama agar bersih)
            $db_item_name = preg_replace('/\s*\([^)]+\)$/', '', $raw_name);

            // QTY DISIMPAN APA ADANYA
            $it_qty  = floatval($qtys[$i]);
            $it_dsc  = $conn->real_escape_string($descs[$i]);
            
            // Clean Price (Support Universal format)
            $p = $prices[$i];
            // Hapus simbol mata uang dan spasi
            $p = str_replace(['Rp', '$', ' '], '', $p);
            
            if ($curr == 'IDR') {
               // IDR: Titik = Ribuan, Koma = Desimal
               $p = str_replace('.', '', $p); // Hapus ribuan
               $p = str_replace(',', '.', $p); // Ubah desimal
            } else {
               // USD: Koma = Ribuan, Titik = Desimal
               $p = str_replace(',', '', $p); // Hapus ribuan
            }
            $it_prc = floatval($p);
            
            $conn->query("INSERT INTO quotation_items (quotation_id, item_name, qty, unit_price, description, card_type) VALUES ($quot_id, '$db_item_name', $it_qty, $it_prc, '$it_dsc', '$text_duration')");
        }
    }
    echo "<script>alert('$msg'); window.location='quotation_list.php';</script>"; exit;
}
?>

<datalist id="durationOptions">
    <option value="One Time">
    <option value="Monthly">
    <option value="3 Months">
    <option value="6 Months">
    <option value="Annually (12 Mo)">
    <option value="2 Years">
</datalist>

<div class="page-heading">
    <h3><?= $page_title ?></h3>
    <div class="alert alert-light-primary border-primary">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Info:</strong> Kolom <b>Charge Mode</b> sekarang bebas diisi teks (contoh: "3 Months (Promo)"). <br>
        Gunakan titik/koma pada harga sesuai mata uang (IDR: 1.500.000 | USD: 1,500.00).
    </div>
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
                                <th width="20%">Charge Mode (Duration)</th> 
                                <th width="20%">Unit Price</th>
                                <th>Desc</th>
                                <th width="5%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($is_edit && count($q_items) > 0): ?>
                                <?php foreach($q_items as $itm): 
                                    $db_name = $itm['item_name'];
                                    // Bersihkan nama item (hilangkan kurung credential lama)
                                    if (preg_match('/^(.*)\s\((.*)\)$/', $db_name, $matches)) {
                                        $db_name = trim($matches[1]);
                                    }
                                    
                                    // Ambil teks durasi apa adanya dari DB
                                    $duration_text_db = $itm['card_type'];
                                ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" value="<?= htmlspecialchars($db_name) ?>" required></td>
                                    
                                    <td><input type="number" name="qty[]" class="form-control text-center" value="<?= $itm['qty'] ?>" min="1" required></td>
                                    
                                    <td>
                                        <input type="text" name="duration_text[]" class="form-control" list="durationOptions" 
                                               value="<?= htmlspecialchars($duration_text_db) ?>" 
                                               placeholder="e.g. Monthly, One Time...">
                                    </td>

                                    <td><input type="text" name="unit_price[]" class="form-control text-end" value="<?= number_format($itm['unit_price'], ($current_curr=='IDR'?0:2), ($current_curr=='IDR'?',':'.'), ($current_curr=='IDR'?'.':',')) ?>" required></td>
                                    <td><input type="text" name="description[]" class="form-control" value="<?= htmlspecialchars($itm['description']) ?>"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" required></td>
                                    <td><input type="number" name="qty[]" class="form-control text-center" value="1" min="1" required></td>
                                    
                                    <td>
                                        <input type="text" name="duration_text[]" class="form-control" list="durationOptions" 
                                               placeholder="e.g. Monthly" value="One Time">
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
            // Default Charge Mode untuk baris baru
            if(inputs[i].getAttribute("list") == "durationOptions") inputs[i].value = "One Time"; 
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
</script>