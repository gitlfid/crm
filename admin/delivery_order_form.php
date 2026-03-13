<?php
$page_title = "Form Delivery Order";
include 'includes/header.php';
include 'includes/sidebar.php';
// include '../config/functions.php'; // Sesuaikan jika ada

// --- AUTO-PATCH DATABASE ---
$checkCol = $conn->query("SHOW COLUMNS FROM delivery_orders LIKE 'invoice_id'");
if ($checkCol && $checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE delivery_orders ADD COLUMN invoice_id INT NULL AFTER payment_id");
}

$do_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$from_inv_id = isset($_GET['from_invoice_id']) ? intval($_GET['from_invoice_id']) : 0;

// =====================================================================
// --- GENERATOR NOMOR DO OTOMATIS (FORMAT: DO2026030001) ---
// =====================================================================
$prefix = "DO";
$periode = date('Ym'); // Mendapatkan Tahun & Bulan saat ini (Contoh: 202603)

// Cari nomor DO terakhir di database pada bulan ini
$cek_last_do = $conn->query("SELECT do_number FROM delivery_orders WHERE do_number LIKE '$prefix$periode%' ORDER BY id DESC LIMIT 1");

if ($cek_last_do && $cek_last_do->num_rows > 0) {
    $row_do = $cek_last_do->fetch_assoc();
    $last_no = $row_do['do_number'];
    
    // Ambil 4 digit angka terakhir dari nomor sebelumnya lalu tambah 1
    $last_urut = intval(substr($last_no, -4));
    $new_urut = $last_urut + 1;
} else {
    // Jika belum ada DO di bulan ini, mulai dari 0001
    $new_urut = 1;
}

// Format hasil akhir: DO + 202603 + 0001
$do_number_auto = $prefix . $periode . str_pad($new_urut, 4, "0", STR_PAD_LEFT);
// =====================================================================

// Default Values
$do_number = $do_number_auto;
$do_date = date('Y-m-d');
$status = 'draft';
$pic_name = ''; $pic_phone = ''; $payment_id = 0; $current_invoice_id = 0;
$client_name = ''; $client_address = ''; $ref_info = '';
$items_list = [];

// KASUS 1: CREATE DARI INVOICE
if ($from_inv_id > 0 && $do_id == 0) {
    $current_invoice_id = $from_inv_id;
    
    $sqlPay = "SELECT id FROM payments WHERE invoice_id = $from_inv_id ORDER BY id DESC LIMIT 1";
    $resPay = $conn->query($sqlPay);
    if ($resPay && $resPay->num_rows > 0) {
        $payRow = $resPay->fetch_assoc();
        $payment_id = $payRow['id'];
    }
    
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
    
    $sqlItems = "SELECT item_name, qty, description FROM invoice_items WHERE invoice_id = $from_inv_id";
    $resItems = $conn->query($sqlItems);
    if($resItems) {
        while($itm = $resItems->fetch_assoc()) {
            $itm['card_type'] = "Prepaid"; 
            $items_list[] = $itm;
        }
    }
}

// KASUS 2: EDIT DO
if ($do_id > 0) {
    $sqlData = "SELECT d.*, d.address as do_addr, d.client_name as do_client_name,
                       c.company_name, c.address as client_addr, 
                       i.invoice_no, i.id as inv_id 
                FROM delivery_orders d 
                LEFT JOIN payments p ON d.payment_id = p.id 
                LEFT JOIN invoices i ON p.invoice_id = i.id
                LEFT JOIN quotations q ON i.quotation_id = q.id 
                LEFT JOIN clients c ON q.client_id = c.id
                WHERE d.id = $do_id";
                
    $resData = $conn->query($sqlData);
    if ($resData && $resData->num_rows > 0) {
        $row = $resData->fetch_assoc();
        
        $do_number = $row['do_number']; 
        $do_date = $row['do_date'];
        $status = $row['status'];
        $pic_name = $row['pic_name'];
        $pic_phone = $row['pic_phone'];
        $payment_id = $row['payment_id'] ? $row['payment_id'] : 0;
        $current_invoice_id = $row['invoice_id'] ? $row['invoice_id'] : ($row['inv_id'] ?? 0);
        
        $client_name = !empty($row['do_client_name']) ? $row['do_client_name'] : $row['company_name'];
        $client_address = !empty($row['do_addr']) ? $row['do_addr'] : $row['client_addr'];
        
        if (!empty($row['invoice_no'])) {
            $ref_info = "Ref: Invoice #" . $row['invoice_no'];
        }

        $sqlItems = "SELECT * FROM delivery_order_items WHERE delivery_order_id = $do_id";
        $resItems = $conn->query($sqlItems);
        if ($resItems && $resItems->num_rows > 0) {
            while($itm = $resItems->fetch_assoc()) {
                $mode = $itm['charge_mode'];
                if (stripos($mode, 'BBC') !== false || empty($mode)) $mode = 'Prepaid';
                $items_list[] = [
                    'item_name' => $itm['item_name'],
                    'qty' => $itm['unit'],
                    'card_type' => $mode, 
                    'description' => $itm['description']
                ];
            }
        }
    }
}

// --- AMBIL DRAFT INVOICES ---
$q_inv = "SELECT id, invoice_no FROM invoices WHERE status = 'draft'";
$q_inv .= " AND (id NOT IN (SELECT invoice_id FROM delivery_orders WHERE invoice_id IS NOT NULL)";
if ($current_invoice_id > 0) {
    $q_inv .= " OR id = $current_invoice_id";
}
$q_inv .= ") ORDER BY id DESC";
$draft_invoices = $conn->query($q_inv);

// --- PROSES SIMPAN ---
if (isset($_POST['save_do'])) {
    $p_id_raw = intval($_POST['payment_id']);
    $p_id_sql = ($p_id_raw > 0) ? $p_id_raw : "NULL";
    
    $inv_id_raw = intval($_POST['invoice_id']);
    $inv_id_sql = ($inv_id_raw > 0) ? $inv_id_raw : "NULL";

    $d_num = $conn->real_escape_string($_POST['do_number']);
    $d_date = $_POST['do_date'];
    $d_stat = $_POST['status'];
    $d_pic = $conn->real_escape_string($_POST['pic_name']);
    $d_phone = $conn->real_escape_string($_POST['pic_phone']);
    $d_addr = $conn->real_escape_string($_POST['address']);
    $d_client = $conn->real_escape_string($_POST['client_name']);
    
    $user_id = $_SESSION['user_id'];

    if ($do_id > 0) {
        $sql = "UPDATE delivery_orders SET 
                do_number='$d_num', 
                do_date='$d_date', 
                status='$d_stat', 
                payment_id=$p_id_sql, 
                invoice_id=$inv_id_sql,
                pic_name='$d_pic', 
                pic_phone='$d_phone', 
                address='$d_addr',
                client_name='$d_client' 
                WHERE id=$do_id";
        
        if (!$conn->query($sql)) {
             $sql_fb = "UPDATE delivery_orders SET 
                do_number='$d_num', do_date='$d_date', status='$d_stat', payment_id=$p_id_sql, 
                pic_name='$d_pic', pic_phone='$d_phone', address='$d_addr' WHERE id=$do_id";
             $conn->query($sql_fb);
        }
        
        $curr_do_id = $do_id;
        $conn->query("DELETE FROM delivery_order_items WHERE delivery_order_id=$curr_do_id");
    } else {
        $sql = "INSERT INTO delivery_orders (do_number, do_date, status, payment_id, invoice_id, pic_name, pic_phone, created_by_user_id, address, client_name) 
                VALUES ('$d_num', '$d_date', '$d_stat', $p_id_sql, $inv_id_sql, '$d_pic', '$d_phone', $user_id, '$d_addr', '$d_client')";
        
        if (!$conn->query($sql)) {
             $sql_fb = "INSERT INTO delivery_orders (do_number, do_date, status, payment_id, pic_name, pic_phone, created_by_user_id, address) 
                VALUES ('$d_num', '$d_date', '$d_stat', $p_id_sql, '$d_pic', '$d_phone', $user_id, '$d_addr')";
             if($conn->query($sql_fb)) {
                 $curr_do_id = $conn->insert_id;
             } else {
                 echo "<script>alert('Error Saving: " . addslashes($conn->error) . "');</script>";
                 exit;
             }
        } else {
            $curr_do_id = $conn->insert_id;
        }
    }

    $item_names = $_POST['item_name'];
    $qtys = $_POST['qty'];
    $modes = $_POST['charge_mode'];
    $descs = $_POST['description'];

    if (!empty($item_names)) {
        for ($i = 0; $i < count($item_names); $i++) {
            if (!empty($item_names[$i])) {
                $i_name = $conn->real_escape_string($item_names[$i]);
                $i_qty = floatval($qtys[$i]);
                $i_mode = $conn->real_escape_string($modes[$i]); 
                $i_desc = $conn->real_escape_string($descs[$i]);
                $conn->query("INSERT INTO delivery_order_items (delivery_order_id, item_name, unit, charge_mode, description) VALUES ($curr_do_id, '$i_name', $i_qty, '$i_mode', '$i_desc')");
            }
        }
    }
    echo "<script>alert('Delivery Order Berhasil Disimpan!'); window.location='delivery_order_list.php';</script>";
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
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-amber-500/30">
                    <i class="ph-fill ph-truck"></i>
                </div>
                <?= $do_id > 0 ? 'Edit Delivery Order' : 'Create Delivery Order' ?>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Formulir untuk membuat surat jalan pengiriman dokumen / SIM Card ke klien.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="delivery_order_list.php" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-[#24303F] text-slate-600 dark:text-slate-300 font-bold py-3 px-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all hover:bg-slate-50 dark:hover:bg-slate-800 active:scale-95 whitespace-nowrap">
                <i class="ph-bold ph-arrow-left text-lg"></i> Back to List
            </a>
        </div>
    </div>

    <form method="POST" class="space-y-6">
        <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800">
                <h3 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 mb-5 flex items-center gap-2">
                    <i class="ph-fill ph-file-text text-amber-500 text-lg"></i> General Information
                </h3>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">DO Number <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-hash absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="do_number" value="<?= htmlspecialchars($do_number) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-amber-600 dark:text-amber-400 focus:ring-2 focus:ring-amber-500/50 outline-none transition-all shadow-inner uppercase tracking-wider" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Reference Invoice (Draft)</label>
                        <div class="relative group">
                            <i class="ph-bold ph-receipt absolute left-4 top-1/2 -translate-y-1/2 text-emerald-500 text-lg"></i>
                            <select name="invoice_id" id="invoice_id" onchange="loadInvoiceData(this.value)" class="w-full pl-11 pr-10 py-3 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-xl text-sm font-bold text-emerald-700 dark:text-emerald-400 focus:ring-2 focus:ring-emerald-500/50 appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">- Tanpa Invoice / Pilih Invoice -</option>
                                <?php if($draft_invoices && $draft_invoices->num_rows > 0): ?>
                                    <?php while($invOpt = $draft_invoices->fetch_assoc()): ?>
                                        <option value="<?= $invOpt['id'] ?>" <?= ($current_invoice_id == $invOpt['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($invOpt['invoice_no']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-emerald-500 pointer-events-none"></i>
                        </div>
                        <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1.5 font-medium"><i class="ph-fill ph-info"></i> Memilih invoice akan mengisi otomatis data Client dan Item.</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Date <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-calendar-blank absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="date" name="do_date" value="<?= $do_date ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-amber-500/50 dark:text-white outline-none transition-all shadow-inner" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Status Dokumen</label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <select name="status" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-amber-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="draft" <?= $status=='draft'?'selected':'' ?>>DRAFT</option>
                                <option value="sent" <?= $status=='sent'?'selected':'' ?>>SENT / DELIVERED</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800">
                <h3 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 mb-5 flex items-center gap-2">
                    <i class="ph-fill ph-map-pin text-sky-500 text-lg"></i> Destination & Receiver
                </h3>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Client / Company <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" name="client_name" value="<?= htmlspecialchars($client_name) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-sky-500/50 dark:text-white outline-none transition-all shadow-inner" placeholder="Nama Perusahaan Tujuan" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Delivery Address <span class="text-rose-500">*</span></label>
                        <textarea name="address" rows="3" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-sky-500/50 dark:text-white outline-none transition-all shadow-inner resize-none" placeholder="Alamat lengkap pengiriman..." required><?= htmlspecialchars($client_address) ?></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Receiver (PIC) <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="text" name="pic_name" value="<?= htmlspecialchars($pic_name) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-sky-500/50 dark:text-white outline-none transition-all shadow-inner" placeholder="Nama Penerima" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PIC Phone</label>
                            <div class="relative group">
                                <i class="ph-bold ph-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <input type="text" name="pic_phone" value="<?= htmlspecialchars($pic_phone) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-sky-500/50 dark:text-white outline-none transition-all shadow-inner" placeholder="0812...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800">
            <h3 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 mb-5 flex items-center gap-2">
                <i class="ph-fill ph-package text-indigo-500 text-lg"></i> Delivery Items
            </h3>

            <div class="overflow-x-auto modern-scrollbar pb-2">
                <table class="w-full text-left border-collapse min-w-[700px]">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[30%]">Item Name</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[15%] text-center">Unit / Qty</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[20%]">Charge Mode</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest w-[30%]">Description</th>
                            <th class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 text-center w-[5%]"></th>
                        </tr>
                    </thead>
                    <tbody id="doItemsBody" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (!empty($items_list)): foreach ($items_list as $item): ?>
                            <tr class="item-row group">
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="item_name[]" value="<?= htmlspecialchars($item['item_name']) ?>" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all">
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="number" step="any" name="qty[]" value="<?= floatval($item['qty']) ?>" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-center focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all">
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="charge_mode[]" value="<?= htmlspecialchars($item['card_type']) ?>" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all">
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="description[]" value="<?= htmlspecialchars($item['description']) ?>" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all">
                                </td>
                                <td class="px-2 py-3 align-top text-center">
                                    <button type="button" onclick="removeRow(this)" class="mt-0.5 w-8 h-8 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white dark:bg-rose-500/10 dark:hover:bg-rose-500 transition-colors flex items-center justify-center mx-auto opacity-50 group-hover:opacity-100 shadow-sm" title="Remove Item">
                                        <i class="ph-bold ph-trash text-base"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr class="item-row group">
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="item_name[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all" required placeholder="Nama Barang">
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="number" step="any" name="qty[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-center focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all" value="1" required>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="charge_mode[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all" value="Prepaid">
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <input type="text" name="description[]" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all" placeholder="Keterangan tambahan...">
                                </td>
                                <td class="px-2 py-3 align-top text-center">
                                    <button type="button" onclick="removeRow(this)" class="mt-0.5 w-8 h-8 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white dark:bg-rose-500/10 dark:hover:bg-rose-500 transition-colors flex items-center justify-center mx-auto opacity-50 group-hover:opacity-100 shadow-sm" title="Remove Item">
                                        <i class="ph-bold ph-trash text-base"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-2">
                <button type="button" onclick="addRow()" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 dark:text-indigo-400 font-bold rounded-xl text-xs transition-colors border border-indigo-200 dark:border-indigo-500/20 shadow-sm">
                    <i class="ph-bold ph-plus"></i> Add Item Row
                </button>
            </div>
        </div>

        <div class="sticky bottom-0 z-40 bg-white/90 dark:bg-[#1A222C]/90 backdrop-blur-md p-4 sm:p-6 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-700 flex flex-col-reverse sm:flex-row justify-end items-center gap-3">
            <a href="delivery_order_list.php" class="w-full sm:w-auto px-8 py-3.5 rounded-2xl font-bold text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors text-center border border-transparent dark:border-slate-600">
                Cancel
            </a>
            <button type="submit" name="save_do" class="w-full sm:w-auto px-10 py-3.5 rounded-2xl font-bold text-sm text-white bg-amber-500 hover:bg-amber-600 shadow-lg shadow-amber-500/30 transition-all flex items-center justify-center gap-2 active:scale-95">
                <i class="ph-bold ph-floppy-disk text-xl"></i> Save Document
            </button>
        </div>
    </form>
</div>

<script>
    // Logika hapus baris item
    function removeRow(btn) { 
        var row = btn.parentNode.parentNode; 
        if(row.parentNode.rows.length > 1) {
            row.parentNode.removeChild(row); 
        } else {
            alert('Minimal harus ada 1 item!');
        }
    }

    // Logika tambah baris item
    function addRow() { 
        var tbody = document.getElementById("doItemsBody"); 
        var newRow = tbody.rows[0].cloneNode(true); 
        var inputs = newRow.getElementsByTagName("input"); 
        
        for(var i=0; i<inputs.length; i++) {
            if(inputs[i].name === "item_name[]" || inputs[i].name === "description[]") {
                inputs[i].value = "";
            } else if(inputs[i].name === "charge_mode[]") {
                inputs[i].value = "Prepaid";
            } else if(inputs[i].name === "qty[]") {
                inputs[i].value = "1";
            }
        } 
        tbody.appendChild(newRow); 
    }

    // Logika Fetch Invoice Data (Auto-Reload Page)
    function loadInvoiceData(invId) {
        if (invId) {
            let searchParams = new URLSearchParams(window.location.search);
            if (!searchParams.has('edit_id') && searchParams.get('from_invoice_id') != invId) {
                if (confirm("Ingin memuat otomatis data klien dan daftar barang dari invoice ini?")) {
                    window.location.href = 'delivery_order_form.php?from_invoice_id=' + invId;
                }
            }
        }
    }
</script>

<?php include 'includes/footer.php'; ?>