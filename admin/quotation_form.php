<?php
$page_title = "Quotation Form";
include 'includes/header.php';
include 'includes/sidebar.php';
// include '../config/functions.php'; // Aktifkan jika diperlukan

$my_id = $_SESSION['user_id'];

// --- INIT VARIABLES ---
$is_edit = false;
$edit_id = 0;
$q_items = [];
$q_adjustments = []; 

// Generate Nomor Baru (Default Auto, tapi bisa diedit)
$display_quote_no = function_exists('generateQuotationNo') ? generateQuotationNo($conn) : 'QUO-' . time();

// Default Values
$current_date     = date('Y-m-d');
$current_curr     = 'IDR';
$client_addr      = "";
$client_pic       = "";
$po_ref_val       = "";
$client_id_val    = ""; 

// --- EDIT MODE (LOAD DATA) ---
if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    
    $sqlHeader = "SELECT q.*, c.address, c.pic_name, c.company_name 
                  FROM quotations q 
                  JOIN clients c ON q.client_id = c.id 
                  WHERE q.id = $edit_id";
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

        // Load Items
        $resItems = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $edit_id");
        while($row = $resItems->fetch_assoc()) {
            $q_items[] = $row;
        }

        // Load Adjustments
        $checkTbl = $conn->query("SHOW TABLES LIKE 'quotation_adjustments'");
        if ($checkTbl && $checkTbl->num_rows > 0) {
            $resAdj = $conn->query("SELECT * FROM quotation_adjustments WHERE quotation_id = $edit_id");
            while($rowAdj = $resAdj->fetch_assoc()) {
                $q_adjustments[] = $rowAdj;
            }
        }
    } else {
        echo "<script>alert('Data tidak ditemukan!'); window.location='quotation_list.php';</script>"; exit;
    }
} 

$clients = $conn->query("SELECT * FROM clients ORDER BY company_name ASC");

// --- SAVE / UPDATE PROCESS ---
if (isset($_POST['save_quotation'])) {
    $post_id = isset($_POST['quotation_id']) ? intval($_POST['quotation_id']) : 0;
    $q_no    = $conn->real_escape_string($_POST['quotation_no']);
    $q_date  = $_POST['quotation_date'];
    $client  = intval($_POST['client_id']);
    $curr    = $_POST['currency'];
    $po_ref  = isset($_POST['po_ref']) ? $conn->real_escape_string($_POST['po_ref']) : '';
    
    if ($post_id > 0) {
        // UPDATE MODE
        $cekDup = $conn->query("SELECT id FROM quotations WHERE quotation_no = '$q_no' AND id != $post_id");
        if($cekDup->num_rows > 0) {
            echo "<script>alert('Gagal: Nomor Quotation $q_no sudah digunakan!'); history.back();</script>"; exit;
        }

        $conn->query("UPDATE quotations SET quotation_no='$q_no', quotation_date='$q_date', client_id=$client, currency='$curr', po_number_client='$po_ref' WHERE id=$post_id");
        
        $conn->query("DELETE FROM quotation_items WHERE quotation_id=$post_id"); 
        $conn->query("DELETE FROM quotation_adjustments WHERE quotation_id=$post_id"); 

        $quot_id = $post_id;
        $msg = "Quotation Updated Successfully!";
    } else {
        // INSERT MODE
        $chk = $conn->query("SELECT id FROM quotations WHERE quotation_no='$q_no'");
        if($chk->num_rows > 0) { 
            echo "<script>alert('Gagal: Nomor Quotation $q_no sudah ada. Gunakan nomor lain.'); history.back();</script>"; exit;
        }
        
        $conn->query("INSERT INTO quotations (quotation_no, client_id, created_by_user_id, quotation_date, currency, status, po_number_client) VALUES ('$q_no', $client, $my_id, '$q_date', '$curr', 'draft', '$po_ref')");
        $quot_id = $conn->insert_id;
        $msg = "Quotation Created Successfully!";
    }

    // 1. ITEM PROCESSING
    $items = $_POST['item_name'];
    $qtys  = $_POST['qty']; 
    $prices= $_POST['unit_price']; 
    $descs = $_POST['description'];
    $dur_texts = $_POST['duration_text']; 

    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i])) {
            $db_item_name = $conn->real_escape_string($items[$i]);
            $text_duration = isset($dur_texts[$i]) ? $conn->real_escape_string($dur_texts[$i]) : 'One Time';
            $it_qty  = floatval($qtys[$i]);
            $it_dsc  = $conn->real_escape_string($descs[$i]);
            
            // Format Harga
            $raw_price = $prices[$i];
            $clean_price = str_replace(['Rp', '$', ' '], '', $raw_price);
            if ($curr == 'IDR') {
                $clean_price = str_replace('.', '', $clean_price); 
                $clean_price = str_replace(',', '.', $clean_price); 
            } else {
                $clean_price = str_replace(',', '', $clean_price); 
            }
            $it_prc = floatval($clean_price);
            
            $conn->query("INSERT INTO quotation_items (quotation_id, item_name, qty, unit_price, description, card_type) VALUES ($quot_id, '$db_item_name', $it_qty, $it_prc, '$it_dsc', '$text_duration')");
        }
    }

    // 2. ADJUSTMENT PROCESSING
    if (isset($_POST['adj_label']) && isset($_POST['adj_amount'])) {
        $adj_labels = $_POST['adj_label'];
        $adj_amounts = $_POST['adj_amount'];

        for ($j = 0; $j < count($adj_labels); $j++) {
            if (!empty($adj_labels[$j])) {
                $lbl = $conn->real_escape_string($adj_labels[$j]);
                $raw_amt = $adj_amounts[$j];
                
                $clean_amt = str_replace(['Rp', '$', ' '], '', $raw_amt);
                if ($curr == 'IDR') {
                    $clean_amt = str_replace('.', '', $clean_amt); 
                    $clean_amt = str_replace(',', '.', $clean_amt); 
                } else {
                    $clean_amt = str_replace(',', '', $clean_amt); 
                }
                $amt_db = floatval($clean_amt);

                if ($amt_db != 0) {
                    $conn->query("INSERT INTO quotation_adjustments (quotation_id, label, amount) VALUES ($quot_id, '$lbl', '$amt_db')");
                }
            }
        }
    }

    echo "<script>alert('$msg'); window.location='quotation_list.php';</script>"; exit;
}
?>

<style>
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modern-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .modern-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .modern-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .modern-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-clipboard-text"></i>
                </div>
                <?= $is_edit ? 'Edit Quotation' : 'Create Quotation' ?>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Buat dan atur penawaran harga kepada klien dengan detail item dan penyesuaian.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="quotation_list.php" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-[#24303F] text-slate-600 dark:text-slate-300 font-bold py-3 px-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all hover:bg-slate-50 dark:hover:bg-slate-800 active:scale-95 whitespace-nowrap">
                <i class="ph-bold ph-arrow-left text-lg"></i> Back to List
            </a>
        </div>
    </div>

    <form method="POST" class="space-y-6">
        <input type="hidden" name="quotation_id" value="<?= $is_edit ? $edit_id : 0 ?>">

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            
            <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800">
                <h3 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 mb-5 flex items-center gap-2">
                    <i class="ph-fill ph-buildings text-indigo-500 text-lg"></i> Customer Information
                </h3>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Select Client <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-storefront absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <select name="client_id" id="client_select" onchange="fillClientInfo()" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all cursor-pointer appearance-none shadow-inner" required>
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
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PO Reference (Optional)</label>
                        <div class="relative group">
                            <i class="ph-bold ph-hash absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="po_ref" value="<?= htmlspecialchars($po_ref_val) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="e.g. PO/001/2026">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Address (Auto Sync)</label>
                            <textarea id="cl_addr" class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-500 outline-none cursor-not-allowed resize-none shadow-inner" rows="3" readonly placeholder="Alamat otomatis terisi..."><?= $client_addr ?></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PIC (Auto Sync)</label>
                            <div class="relative">
                                <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="text" id="cl_pic" value="<?= $client_pic ?>" class="w-full pl-11 pr-4 py-3 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-500 outline-none cursor-not-allowed shadow-inner" readonly placeholder="Nama PIC...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col">
                <h3 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 mb-5 flex items-center gap-2">
                    <i class="ph-fill ph-file-text text-violet-500 text-lg"></i> Quotation Details
                </h3>
                
                <div class="space-y-5 mb-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Quotation No <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-receipt absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="quotation_no" value="<?= $display_quote_no ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500/50 outline-none transition-all shadow-inner tracking-wider" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Date <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <i class="ph-bold ph-calendar-blank absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="date" name="quotation_date" value="<?= $current_date ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Currency</label>
                            <div class="relative group">
                                <i class="ph-bold ph-currency-circle-dollar absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <select name="currency" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white appearance-none cursor-pointer shadow-inner transition-all">
                                    <option value="IDR" <?= ($current_curr == 'IDR') ? 'selected' : '' ?>>IDR (Rupiah)</option>
                                    <option value="USD" <?= ($current_curr == 'USD') ? 'selected' : '' ?>>USD (Dollar)</option>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-auto border-t border-slate-100 dark:border-slate-700 pt-5">
                    <label class="block text-[11px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                        <i class="ph-bold ph-plus-minus"></i> Adjustments (DP, Potongan, Fee)
                    </label>
                    
                    <div class="space-y-2 mb-3" id="adjTable">
                        <?php if($is_edit && count($q_adjustments) > 0): ?>
                            <?php foreach($q_adjustments as $adj): 
                                $val = floatval($adj['amount']);
                                $dispVal = ($current_curr=='IDR') ? number_format($val,0,',','.') : number_format($val,2,'.',',');
                            ?>
                            <div class="flex items-center gap-2 adj-row group">
                                <input type="text" name="adj_label[]" class="w-[50%] px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white" placeholder="Label (e.g. DP 50%)" value="<?= htmlspecialchars($adj['label']) ?>">
                                <input type="text" name="adj_amount[]" class="w-[40%] px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-right focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white" placeholder="Amount" value="<?= $dispVal ?>">
                                <button type="button" onclick="removeRow(this)" class="w-[10%] h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-colors flex items-center justify-center opacity-50 group-hover:opacity-100">
                                    <i class="ph-bold ph-x"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex items-center gap-2 adj-row group">
                                <input type="text" name="adj_label[]" class="w-[50%] px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white" placeholder="Label (e.g. DP 50%)">
                                <input type="text" name="adj_amount[]" class="w-[40%] px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-right focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white" placeholder="Amount">
                                <button type="button" onclick="removeRow(this)" class="w-[10%] h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-colors flex items-center justify-center opacity-50 group-hover:opacity-100">
                                    <i class="ph-bold ph-x"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" onclick="addAdjRow()" class="w-full py-2 border-2 border-dashed border-emerald-200 dark:border-emerald-500/30 text-emerald-600 dark:text-emerald-400 font-bold text-xs rounded-xl hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors flex items-center justify-center gap-1.5">
                        <i class="ph-bold ph-plus"></i> Add Adjustment Row
                    </button>
                    <p class="text-[9px] text-slate-400 mt-2 italic text-center">* Gunakan tanda minus (-) jika nominal bersifat mengurangi (Diskon).</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700 pb-4 mb-5">
                <h3 class="text-sm font-black text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="ph-fill ph-package text-violet-500 text-xl"></i> Items List
                </h3>
            </div>

            <div class="overflow-x-auto modern-scrollbar pb-4">
                <table class="w-full text-left border-collapse min-w-[900px]" id="itemTable">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[25%]">Item Name</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[8%] text-center">Qty</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[25%]">Charge Mode (Duration)</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[15%] text-right">Unit Price</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[22%]">Description</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-center w-[5%]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if($is_edit && count($q_items) > 0): ?>
                            <?php foreach($q_items as $itm): 
                                $db_name = $itm['item_name'];
                                $duration_text_db = $itm['card_type'];
                                
                                $sel_one = ($duration_text_db == 'One Time') ? 'selected' : '';
                                $sel_mon = ($duration_text_db == 'Monthly') ? 'selected' : '';
                                $sel_3mo = ($duration_text_db == '3 Months') ? 'selected' : '';
                                $sel_6mo = ($duration_text_db == '6 Months') ? 'selected' : '';
                                $sel_ann = (strpos($duration_text_db, 'Annually') !== false) ? 'selected' : '';
                                
                                $is_custom = ($sel_one=='' && $sel_mon=='' && $sel_3mo=='' && $sel_6mo=='' && $sel_ann=='');
                                $sel_cus = $is_custom ? 'selected' : '';
                                $display_select = $is_custom ? 'hidden' : '';
                                $display_input = $is_custom ? 'flex' : 'hidden';
                                
                                $custom_val = ''; $custom_unit = 'Months';
                                if($is_custom) {
                                    $parts = explode(' ', $duration_text_db);
                                    if(count($parts) >= 2) { $custom_val = $parts[0]; $custom_unit = $parts[1]; }
                                }
                            ?>
                            <tr class="item-row group">
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="item_name[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= htmlspecialchars($db_name) ?>" required>
                                </td>
                                
                                <td class="px-2 py-3 align-top">
                                    <input type="number" step="any" name="qty[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-center focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= floatval($itm['qty']) ?>" required>
                                </td>
                                
                                <td class="px-2 py-3 align-top relative">
                                    <div class="relative">
                                        <select class="w-full pl-3 pr-8 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white appearance-none cursor-pointer duration-select shadow-inner transition-all <?= $display_select ?>" onchange="updateDuration(this)">
                                            <option value="1" data-txt="One Time" <?= $sel_one ?>>One Time</option>
                                            <option value="1" data-txt="Monthly" <?= $sel_mon ?>>Monthly</option>
                                            <option value="3" data-txt="3 Months" <?= $sel_3mo ?>>3 Months</option>
                                            <option value="6" data-txt="6 Months" <?= $sel_6mo ?>>6 Months</option>
                                            <option value="12" data-txt="Annually" <?= $sel_ann ?>>Annually (12 Mo)</option>
                                            <option value="custom" class="font-bold text-violet-600" <?= $sel_cus ?>>Custom...</option>
                                        </select>
                                        <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none <?= $display_select ?>"></i>
                                    </div>
                                    <input type="hidden" name="duration_text[]" class="duration-text-input" value="<?= $duration_text_db ?>">
                                    
                                    <div class="duration-input-group <?= $display_input ?> items-center gap-1">
                                        <input type="number" class="w-16 px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-center focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white shadow-inner duration-custom" placeholder="0" value="<?= $custom_val ?>" oninput="updateCustomDuration(this)">
                                        <div class="relative flex-1">
                                            <select class="w-full pl-3 pr-8 py-2.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:outline-none dark:text-white appearance-none duration-unit shadow-inner" onchange="updateCustomDuration(this)">
                                                <option value="Months" <?= $custom_unit=='Months'?'selected':'' ?>>Bulan</option>
                                                <option value="Years" <?= $custom_unit=='Years'?'selected':'' ?>>Tahun</option>
                                            </select>
                                            <i class="ph-bold ph-caret-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                                        </div>
                                        <button type="button" onclick="resetDuration(this)" class="w-9 h-[42px] rounded-xl bg-slate-200 hover:bg-rose-500 text-slate-600 hover:text-white dark:bg-slate-700 dark:hover:bg-rose-600 dark:text-slate-300 transition-colors flex items-center justify-center shrink-0">
                                            <i class="ph-bold ph-x"></i>
                                        </button>
                                    </div>
                                </td>

                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="unit_price[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-right focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white transition-all shadow-inner" 
                                           value="<?= ($current_curr=='IDR') ? number_format($itm['unit_price'], 0, ',', '.') : number_format($itm['unit_price'], 2, '.', ',') ?>" required>
                                </td>
                                
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="description[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="Optional desc..." value="<?= htmlspecialchars($itm['description']) ?>">
                                </td>
                                
                                <td class="px-2 py-3 align-top text-center">
                                    <button type="button" onclick="removeRow(this)" class="mt-1 w-8 h-8 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white dark:bg-rose-500/10 dark:hover:bg-rose-500 transition-colors flex items-center justify-center mx-auto opacity-50 group-hover:opacity-100 shadow-sm" title="Remove Item">
                                        <i class="ph-bold ph-trash text-base"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="item-row group">
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="item_name[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white transition-all shadow-inner" required placeholder="Nama Paket / Barang">
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="number" step="any" name="qty[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-center focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white transition-all shadow-inner" value="1" required>
                                </td>
                                <td class="px-2 py-3 align-top relative">
                                    <div class="relative">
                                        <select class="w-full pl-3 pr-8 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white appearance-none cursor-pointer duration-select shadow-inner transition-all" onchange="updateDuration(this)">
                                            <option value="1" data-txt="One Time">One Time</option>
                                            <option value="1" data-txt="Monthly">Monthly</option>
                                            <option value="3" data-txt="3 Months">3 Months</option>
                                            <option value="6" data-txt="6 Months">6 Months</option>
                                            <option value="12" data-txt="Annually">Annually (12 Mo)</option>
                                            <option value="custom" class="font-bold text-violet-600">Custom...</option>
                                        </select>
                                        <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none duration-icon"></i>
                                    </div>
                                    <input type="hidden" name="duration_text[]" class="duration-text-input" value="One Time">
                                    
                                    <div class="duration-input-group hidden items-center gap-1">
                                        <input type="number" class="w-16 px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-center focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white shadow-inner duration-custom" placeholder="0" oninput="updateCustomDuration(this)">
                                        <div class="relative flex-1">
                                            <select class="w-full pl-3 pr-8 py-2.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:outline-none dark:text-white appearance-none duration-unit shadow-inner" onchange="updateCustomDuration(this)">
                                                <option value="Months">Bulan</option>
                                                <option value="Years">Tahun</option>
                                            </select>
                                            <i class="ph-bold ph-caret-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                                        </div>
                                        <button type="button" onclick="resetDuration(this)" class="w-9 h-[42px] rounded-xl bg-slate-200 hover:bg-rose-500 text-slate-600 hover:text-white dark:bg-slate-700 dark:hover:bg-rose-600 dark:text-slate-300 transition-colors flex items-center justify-center shrink-0">
                                            <i class="ph-bold ph-x"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="unit_price[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-right focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="0" required>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="description[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-violet-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="Optional desc...">
                                </td>
                                <td class="px-2 py-3 align-top text-center">
                                    <button type="button" onclick="removeRow(this)" class="mt-1 w-8 h-8 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white dark:bg-rose-500/10 dark:hover:bg-rose-500 transition-colors flex items-center justify-center mx-auto opacity-50 group-hover:opacity-100 shadow-sm" title="Remove Item">
                                        <i class="ph-bold ph-trash text-base"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                <button type="button" onclick="addRow()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-50 hover:bg-violet-100 text-violet-600 dark:bg-violet-500/10 dark:hover:bg-violet-500/20 dark:text-violet-400 font-bold rounded-xl text-xs transition-colors border border-violet-200 dark:border-violet-500/20 shadow-sm">
                    <i class="ph-bold ph-plus text-sm"></i> Tambah Item Baru
                </button>
            </div>
        </div>

        <div class="sticky bottom-0 z-40 bg-white/90 dark:bg-[#1A222C]/90 backdrop-blur-md p-4 sm:p-6 rounded-3xl shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.1)] border border-slate-200 dark:border-slate-700 flex flex-col-reverse sm:flex-row justify-end items-center gap-3">
            <a href="quotation_list.php" class="w-full sm:w-auto px-8 py-3.5 rounded-2xl font-bold text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors text-center border border-transparent dark:border-slate-600">
                Cancel
            </a>
            <button type="submit" name="save_quotation" class="w-full sm:w-auto px-10 py-3.5 rounded-2xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 transition-all flex items-center justify-center gap-2 active:scale-95">
                <i class="ph-bold ph-floppy-disk text-xl"></i> Save Document
            </button>
        </div>
    </form>
</div>

<script>
    // Sinkronisasi data klien
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

    // Tambah Baris Item
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
            if(inputs[i].classList.contains("duration-text-input")) inputs[i].value = "One Time"; 
        }
        
        var selectElem = newRow.querySelector('.duration-select');
        var inputGroup = newRow.querySelector('.duration-input-group');
        var iconDown = newRow.querySelector('.duration-icon');
        if(selectElem && inputGroup) {
            selectElem.value = "1";
            selectElem.classList.remove('hidden');
            if(iconDown) iconDown.classList.remove('hidden');
            inputGroup.classList.remove('flex');
            inputGroup.classList.add('hidden');
        }
        table.appendChild(newRow);
    }

    // Tambah Baris Adjustment
    function addAdjRow() {
        var container = document.getElementById("adjTable");
        var newRow = document.createElement('div');
        newRow.className = "flex items-center gap-2 adj-row group";
        newRow.innerHTML = `
            <input type="text" name="adj_label[]" class="w-[50%] px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white" placeholder="Label">
            <input type="text" name="adj_amount[]" class="w-[40%] px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-right focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white" placeholder="Amount">
            <button type="button" onclick="removeRow(this)" class="w-[10%] h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-colors flex items-center justify-center opacity-50 group-hover:opacity-100">
                <i class="ph-bold ph-x"></i>
            </button>
        `;
        container.appendChild(newRow);
    }

    // Hapus Baris (Bisa Item atau Adjustment)
    function removeRow(btn) {
        var row = btn.closest('tr');
        if(row) { // Jika ini adalah row tabel item
            var table = row.closest('tbody');
            if(table.rows.length <= 1) {
                alert("Minimal harus ada 1 item barang/jasa.");
            } else {
                row.remove();
            }
        } else { // Jika ini adalah div row adjustment
            var adjRow = btn.closest('.adj-row');
            if(adjRow) adjRow.remove();
        }
    }

    // Logic Dropdown Duration
    function updateDuration(selectElem) {
        let row = selectElem.closest('td');
        let inputGroup = row.querySelector('.duration-input-group');
        let customInput = row.querySelector('.duration-custom');
        let hiddenText = row.querySelector('.duration-text-input');
        let iconDown = row.querySelector('.duration-icon');

        if(selectElem.value === 'custom') {
            selectElem.classList.add('hidden');
            if(iconDown) iconDown.classList.add('hidden');
            inputGroup.classList.remove('hidden');
            inputGroup.classList.add('flex');
            customInput.value = ""; 
            customInput.focus();
            hiddenText.value = ""; 
        } else {
            let selectedText = selectElem.options[selectElem.selectedIndex].getAttribute('data-txt');
            hiddenText.value = selectedText;
        }
    }

    function updateCustomDuration(elem) {
        let row = elem.closest('td');
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
        let row = btn.closest('td');
        let selectElem = row.querySelector('.duration-select');
        let inputGroup = row.querySelector('.duration-input-group');
        let hiddenText = row.querySelector('.duration-text-input');
        let iconDown = row.querySelector('.duration-icon');
        let unitSel = row.querySelector('.duration-unit');
        
        unitSel.value = "Months"; 

        inputGroup.classList.remove('flex');
        inputGroup.classList.add('hidden');
        selectElem.classList.remove('hidden');
        if(iconDown) iconDown.classList.remove('hidden');
        selectElem.value = "1"; 
        hiddenText.value = "One Time"; 
    }
</script>

<?php include 'includes/footer.php'; ?>