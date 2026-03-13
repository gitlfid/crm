<?php
$page_title = "Generate Invoice";
include 'includes/header.php';
include 'includes/sidebar.php';
// include '../config/functions.php'; // Tetap dinonaktifkan tidak masalah karena logika sudah mandiri

$my_id = $_SESSION['user_id'];

// --- LOGIKA GENERATE NOMOR INVOICE (INVLF2026030001) ---
$prefix = "INVLF";
$periode = date('Ym'); // Mendapatkan Tahun & Bulan saat ini (Contoh: 202603)

// Cari nomor invoice terakhir di database pada bulan ini
$cek_last_inv = $conn->query("SELECT invoice_no FROM invoices WHERE invoice_no LIKE '$prefix$periode%' ORDER BY id DESC LIMIT 1");

if ($cek_last_inv && $cek_last_inv->num_rows > 0) {
    $row_inv = $cek_last_inv->fetch_assoc();
    $last_no = $row_inv['invoice_no'];
    
    // Ambil 4 digit angka terakhir dari nomor sebelumnya lalu tambah 1
    $last_urut = intval(substr($last_no, -4));
    $new_urut = $last_urut + 1;
} else {
    // Jika belum ada invoice di bulan ini, mulai dari 1
    $new_urut = 1;
}

// Format hasil akhir: INVLF + 202603 + 0001
$auto_inv = $prefix . $periode . str_pad($new_urut, 4, "0", STR_PAD_LEFT);
// --------------------------------------------------------

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
    $res = $conn->query($sql);
    if($res && $res->num_rows > 0) {
        $source_data = $res->fetch_assoc();
    } else {
        die("Data Quotation tidak ditemukan.");
    }

    // Ambil Item Quotation
    $resItems = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $q_id");
    if($resItems) {
        while($itm = $resItems->fetch_assoc()) {
            $source_items[] = $itm;
        }
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
                                  VALUES ($invoice_id, '$it_name', $it_qty, $it_prc, '$it_dsc', '$it_card')");
                }
            }
        }

        // --- INSERT ADJUSTMENTS ---
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

// Default Date Calculation (5 Working Days)
$startDate = new DateTime(); 
$addDays = 0;
while ($addDays < 5) {
    $startDate->modify('+1 day');
    $dayOfWeek = $startDate->format('N'); 
    if ($dayOfWeek < 6) {
        $addDays++;
    }
}
$default_due_date = $startDate->format('Y-m-d');
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
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-emerald-500/30">
                    <i class="ph-fill ph-receipt"></i>
                </div>
                <?= $is_manual ? 'Create Manual Invoice' : 'Generate Invoice from PO' ?>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Buat dokumen penagihan untuk klien beserta opsi *adjustment* (DP/Potongan).</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="invoice_list.php" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-[#24303F] text-slate-600 dark:text-slate-300 font-bold py-3 px-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all hover:bg-slate-50 dark:hover:bg-slate-800 active:scale-95 whitespace-nowrap">
                <i class="ph-bold ph-arrow-left text-lg"></i> Back to List
            </a>
        </div>
    </div>

    <?php if(!$is_manual): ?>
        <div class="bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 text-blue-700 dark:text-blue-400 px-5 py-4 rounded-2xl flex gap-3 shadow-sm">
            <i class="ph-fill ph-info text-2xl shrink-0 mt-0.5"></i>
            <div>
                <p class="font-bold text-sm mb-0.5">Mode Otomatis (Dari PO)</p>
                <p class="text-xs font-medium">Data klien dan barang telah dimuat. Anda dapat menghapus atau mengubah jumlah (Quantity) barang sebelum menyimpan Invoice.</p>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <?php if(!$is_manual): ?>
            <input type="hidden" name="quotation_id" value="<?= $source_data['id'] ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            
            <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col h-full">
                <h3 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 mb-5 flex items-center gap-2">
                    <i class="ph-fill ph-buildings text-emerald-500 text-lg"></i> Billed To (Client)
                </h3>
                
                <div class="space-y-5">
                    <?php if($is_manual): ?>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Select Client <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <i class="ph-bold ph-storefront absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <select name="client_id" id="client_select" onchange="fillClientInfo()" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all cursor-pointer appearance-none shadow-inner" required>
                                    <option value="">-- Choose Client --</option>
                                    <?php if($clients && $clients->num_rows > 0){ $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                        <option value="<?= $c['id'] ?>" data-addr="<?= htmlspecialchars($c['address']) ?>" data-pic="<?= htmlspecialchars($c['pic_name']) ?>">
                                            <?= htmlspecialchars($c['company_name']) ?>
                                        </option>
                                    <?php endwhile; } ?>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PO Reference (Manual)</label>
                            <div class="relative group">
                                <i class="ph-bold ph-hash absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="text" name="po_ref" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="e.g. PO/001/2026">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Address</label>
                                <textarea id="cl_addr" class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-500 outline-none cursor-not-allowed resize-none shadow-inner" rows="3" readonly placeholder="Alamat otomatis terisi..."></textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PIC</label>
                                <div class="relative">
                                    <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <input type="text" id="cl_pic" class="w-full pl-11 pr-4 py-3 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-500 outline-none cursor-not-allowed shadow-inner" readonly placeholder="Nama PIC...">
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Client</label>
                            <input type="text" class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-300 outline-none cursor-not-allowed shadow-inner" value="<?= htmlspecialchars($source_data['company_name']) ?>" readonly>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Address</label>
                            <textarea class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400 outline-none cursor-not-allowed resize-none shadow-inner" rows="2" readonly><?= htmlspecialchars($source_data['address']) ?></textarea>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PIC</label>
                            <div class="relative">
                                <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="text" class="w-full pl-11 pr-4 py-3 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400 outline-none cursor-not-allowed shadow-inner" value="<?= htmlspecialchars($source_data['pic_name']) ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 rounded-xl p-4 mt-2">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-widest">PO Ref</span>
                                <span class="font-black text-indigo-700 dark:text-indigo-300 font-mono"><?= htmlspecialchars($source_data['po_number_client']) ?></span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-widest">Quote Ref</span>
                                <span class="font-black text-indigo-700 dark:text-indigo-300 font-mono"><?= htmlspecialchars($source_data['quotation_no']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col h-full">
                <h3 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 mb-5 flex items-center gap-2">
                    <i class="ph-fill ph-file-text text-teal-500 text-lg"></i> Invoice Details
                </h3>
                
                <div class="space-y-5 mb-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Invoice No <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-receipt absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="invoice_no" value="<?= htmlspecialchars($auto_inv) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-emerald-600 dark:text-emerald-400 focus:ring-2 focus:ring-emerald-500/50 outline-none transition-all shadow-inner tracking-wider" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Invoice Type</label>
                        <div class="relative group">
                            <select name="invoice_type" id="invoice_type" onchange="autoSetCurrency()" class="w-full pl-4 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="Domestic">Domestic (IDR)</option>
                                <option value="International">International (USD)</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Issue Date <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <i class="ph-bold ph-calendar-blank absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" class="w-full pl-9 pr-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" required onchange="updateDueDate()">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Due Date (+5 Days) <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <i class="ph-bold ph-calendar-check absolute left-3 top-1/2 -translate-y-1/2 text-rose-400"></i>
                                <input type="date" name="due_date" value="<?= $default_due_date ?>" class="w-full pl-9 pr-3 py-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 rounded-xl text-xs font-bold text-rose-600 dark:text-rose-400 focus:ring-2 focus:ring-rose-500/50 outline-none transition-all shadow-inner" required>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <?php if($is_manual): ?>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Currency</label>
                                <div class="relative group">
                                    <select name="currency" id="currency" class="w-full pl-4 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 dark:text-white appearance-none cursor-pointer shadow-inner transition-all">
                                        <option value="IDR">IDR (Rp)</option>
                                        <option value="USD">USD ($)</option>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                        <?php else: ?>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Currency</label>
                                <input type="text" class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-400 outline-none cursor-not-allowed shadow-inner" value="<?= htmlspecialchars($source_data['currency']) ?>" readonly>
                                <input type="hidden" name="currency" value="<?= htmlspecialchars($source_data['currency']) ?>">
                            </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Payment Method</label>
                            <input type="text" name="payment_method_col" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" value="Prepaid">
                        </div>
                    </div>
                </div>

                <div class="mt-auto border-t border-slate-100 dark:border-slate-700 pt-5">
                    <label class="block text-[11px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                        <i class="ph-bold ph-plus-minus"></i> Adjustments (DP, Potongan, dll)
                    </label>
                    
                    <div class="space-y-2 mb-3" id="adjTable">
                        <div class="flex items-center gap-2 adj-row group">
                            <input type="text" name="adj_label[]" class="w-[50%] px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white" placeholder="Label (e.g. DP 50%)">
                            <input type="text" name="adj_amount[]" class="w-[40%] px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-right focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white" placeholder="Amount">
                            <button type="button" onclick="removeRow(this)" class="w-[10%] h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-colors flex items-center justify-center opacity-50 group-hover:opacity-100">
                                <i class="ph-bold ph-x"></i>
                            </button>
                        </div>
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
                    <i class="ph-fill ph-package text-teal-500 text-xl"></i> Items List
                </h3>
                <?php if($is_manual): ?>
                <button type="button" onclick="addRow()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-50 hover:bg-teal-100 text-teal-600 dark:bg-teal-500/10 dark:hover:bg-teal-500/20 dark:text-teal-400 font-bold rounded-xl text-xs transition-colors border border-teal-200 dark:border-teal-500/20 shadow-sm active:scale-95">
                    <i class="ph-bold ph-plus"></i> Add Item
                </button>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto modern-scrollbar pb-4">
                <table class="w-full text-left border-collapse min-w-[900px]" id="itemTable">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[25%]">Item Name</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[15%]">Card Type</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[10%] text-center">Qty</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[20%] text-right">Unit Price</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[25%]">Description</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-center w-[5%]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if(!$is_manual): ?>
                            <?php foreach($source_items as $itm): 
                                $db_price = floatval($itm['unit_price']);
                                $curr_src = $source_data['currency'] ?? 'IDR';
                                
                                if ($curr_src == 'IDR') {
                                    $display_price = number_format($db_price, 0, ',', '.');
                                } else {
                                    $display_price = number_format($db_price, 2, '.', ',');
                                }
                            ?>
                            <tr class="item-row group">
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="item_name[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= htmlspecialchars($itm['item_name']) ?>" required>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="card_type[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= htmlspecialchars($itm['card_type']) ?>" placeholder="Optional">
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="number" step="any" name="qty[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-center focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= $itm['qty'] ?>" required>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="unit_price[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-right focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= $display_price ?>" required>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="description[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= htmlspecialchars($itm['description']) ?>" placeholder="Optional desc...">
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
                                    <input type="text" name="item_name[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" required placeholder="Nama Paket / Barang">
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="card_type[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="Optional">
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="number" step="any" name="qty[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-center focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" value="1" required>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="unit_price[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-right focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="0" required>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="description[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="Optional desc...">
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
            
            <?php if(!$is_manual): ?>
            <div class="mt-2">
                <button type="button" onclick="addRow()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-50 hover:bg-teal-100 text-teal-600 dark:bg-teal-500/10 dark:hover:bg-teal-500/20 dark:text-teal-400 font-bold rounded-xl text-xs transition-colors border border-teal-200 dark:border-teal-500/20 shadow-sm">
                    <i class="ph-bold ph-plus text-sm"></i> Tambah Extra Item
                </button>
            </div>
            <?php endif; ?>
        </div>

        <div class="sticky bottom-0 z-40 bg-white/90 dark:bg-[#1A222C]/90 backdrop-blur-md p-4 sm:p-6 rounded-3xl shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.1)] border border-slate-200 dark:border-slate-700 flex flex-col-reverse sm:flex-row justify-end items-center gap-3">
            <a href="invoice_list.php" class="w-full sm:w-auto px-8 py-3.5 rounded-2xl font-bold text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors text-center border border-transparent dark:border-slate-600">
                Cancel
            </a>
            <button type="submit" name="save_invoice" class="w-full sm:w-auto px-10 py-3.5 rounded-2xl font-bold text-sm text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-500/30 transition-all flex items-center justify-center gap-2 active:scale-95">
                <i class="ph-bold ph-floppy-disk text-xl"></i> Save Invoice Document
            </button>
        </div>
    </form>
</div>

<script>
    // Setel mata uang jika tipe berubah
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

    // Sinkronisasi data klien
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

    // LOGIKA DUE DATE (+5 Working Days)
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
                
                // Jika bukan Sabtu dan bukan Minggu, hitung
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

    // Tambah Baris Item
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

    // Hapus Baris
    function removeRow(btn) {
        var row = btn.closest('tr');
        if(row) { 
            var table = row.closest('tbody');
            if(table.rows.length <= 1) {
                alert("Invoice minimal harus memiliki 1 item.");
            } else {
                row.remove();
            }
        } else { 
            var adjRow = btn.closest('.adj-row');
            if(adjRow) adjRow.remove();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>