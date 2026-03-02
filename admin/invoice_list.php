<?php
// --- 1. LOAD CONFIG DULUAN (Agar Export Excel berjalan sebelum HTML) ---
include '../config/functions.php';

// Pastikan sesi dimulai jika belum (untuk cek role)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

// --- 2. INIT FILTER VARIABLES ---
$search       = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client     = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';
$f_status     = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
$f_tax        = isset($_REQUEST['tax_status']) ? $_REQUEST['tax_status'] : '';
$f_start_date = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : '';
$f_end_date   = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : '';

// Bangun Query WHERE
$where = "1=1";

// 1. Filter Search (No Invoice)
if(!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (i.invoice_no LIKE '%$safe_search%')";
}

// 2. Filter Client
if(!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND q.client_id = $safe_client"; 
}

// 3. Filter Status Invoice
if(!empty($f_status)) {
    $safe_status = $conn->real_escape_string($f_status);
    $where .= " AND i.status = '$safe_status'";
}

// 4. Filter Status Faktur Pajak
if($f_tax == 'uploaded') {
    $where .= " AND i.tax_invoice_file IS NOT NULL AND i.tax_invoice_file != ''";
} elseif($f_tax == 'pending') {
    $where .= " AND (i.tax_invoice_file IS NULL OR i.tax_invoice_file = '')";
}

// 5. Filter Tanggal
if(!empty($f_start_date)) {
    $safe_start = $conn->real_escape_string($f_start_date);
    $where .= " AND i.invoice_date >= '$safe_start'";
}
if(!empty($f_end_date)) {
    $safe_end = $conn->real_escape_string($f_end_date);
    $where .= " AND i.invoice_date <= '$safe_end'";
}

// --- 3. LOGIKA EXPORT EXCEL (ITEM TERPISAH) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean(); // Bersihkan buffer HTML
    
    $sqlEx = "SELECT 
                i.id, 
                i.quotation_id, 
                i.invoice_date,
                i.invoice_no,
                i.invoice_type, 
                i.status,
                i.tax_invoice_file,
                c.company_name,
                q.quotation_no,
                q.po_number_client,
                q.currency,
                u.username as sales_name,
                isp.general_notes, 
                (
                    SELECT GROUP_CONCAT(do.do_number SEPARATOR ', ') 
                    FROM delivery_orders do 
                    JOIN payments pay ON do.payment_id = pay.id 
                    WHERE pay.invoice_id = i.id
                ) as do_numbers
              FROM invoices i 
              JOIN quotations q ON i.quotation_id = q.id 
              JOIN clients c ON q.client_id = c.id 
              LEFT JOIN users u ON c.sales_person_id = u.id 
              LEFT JOIN invoice_scratchpads isp ON i.invoice_no = isp.invoice_no 
              WHERE $where 
              ORDER BY i.created_at DESC";
              
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Invoices_Rekap_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, array('Date', 'Client', 'Invoice No', 'Type', 'PO Client', 'Ref Quote', 'Description', 'Quantity', 'Unit Price', 'Currency', 'Sub Total', 'VAT (11%)', 'Grand Total', 'Status', 'Sales Person', 'Delivery Order No', 'Status Faktur Pajak', 'Notes'));
    
    while($row = $resEx->fetch_assoc()) {
        $invId = $row['id'];
        $quotId = $row['quotation_id'];

        // 1. Ambil Item Detail
        $itemsData = [];
        $sqlItems = "SELECT item_name, description, qty, unit_price FROM invoice_items WHERE invoice_id = $invId";
        $resItems = $conn->query($sqlItems);
        
        if ($resItems->num_rows == 0) {
            $sqlItems = "SELECT item_name, description, qty, unit_price FROM quotation_items WHERE quotation_id = $quotId";
            $resItems = $conn->query($sqlItems);
        }

        // 2. Hitung Total Invoice
        $calcSub = 0;
        while ($itm = $resItems->fetch_assoc()) {
            $itemsData[] = $itm;
            $calcSub += floatval($itm['qty']) * floatval($itm['unit_price']);
        }
        
        // Logika Pajak
        $is_international = ($row['invoice_type'] == 'International');
        $tax_rate = $is_international ? 0 : 0.11;
        $vat = $calcSub * $tax_rate;

        // Logika Rounding Excel
        if (!$is_international) {
            $calcSub = round($calcSub, 0, PHP_ROUND_HALF_DOWN);
            $vat = round($vat, 0, PHP_ROUND_HALF_DOWN);
        } else {
            $calcSub = round($calcSub, 2);
            $vat = round($vat, 2);
        }
        $grandTotal = $calcSub + $vat;
        
        // Data Umum Baris
        $doNum = !empty($row['do_numbers']) ? $row['do_numbers'] : '-';
        $poClient = !empty($row['po_number_client']) ? $row['po_number_client'] : '-';
        $salesPerson = !empty($row['sales_name']) ? $row['sales_name'] : '-';
        $taxStatus = (!empty($row['tax_invoice_file'])) ? 'Uploaded' : 'Pending';
        $cleanNotes = !empty($row['general_notes']) ? str_replace(array("\r", "\n"), " ", $row['general_notes']) : '-';

        // 3. Tulis Baris CSV
        if (count($itemsData) > 0) {
            foreach ($itemsData as $item) {
                $desc = $item['item_name'];
                if(!empty($item['description']) && $item['description'] !== 'Exclude Tax') {
                    $desc .= ' (' . $item['description'] . ')';
                }

                fputcsv($output, array(
                    $row['invoice_date'],
                    $row['company_name'],
                    $row['invoice_no'],
                    $row['invoice_type'],
                    $poClient,
                    $row['quotation_no'],
                    $desc,                  
                    $item['qty'],           
                    $item['unit_price'],    
                    $row['currency'],
                    $calcSub,    
                    $vat,        
                    $grandTotal, 
                    strtoupper($row['status']),
                    $salesPerson,
                    $doNum,
                    $taxStatus,
                    $cleanNotes 
                ));
            }
        } else {
            fputcsv($output, array(
                $row['invoice_date'], $row['company_name'], $row['invoice_no'], 
                $row['invoice_type'],
                $poClient, $row['quotation_no'], 'No Items Found', 0, 0, 
                $row['currency'], 0, 0, 0, 
                strtoupper($row['status']), $salesPerson, $doNum, $taxStatus, $cleanNotes
            ));
        }
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "Invoices";
include 'includes/header.php';
include 'includes/sidebar.php';

$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// --- LOGIKA ACTION: PAYMENT (DENGAN DP & NOTE) ---
if (isset($_POST['confirm_payment'])) {
    $inv_id = intval($_POST['invoice_id']);
    $pay_date = $_POST['payment_date'];
    $amount_input = floatval(str_replace(['.', ','], '', $_POST['amount']));
    $grand_total_system = floatval($_POST['grand_total_system']);
    $notes = isset($_POST['payment_notes']) ? $conn->real_escape_string($_POST['payment_notes']) : '';
    $user_id = $_SESSION['user_id'];

    if ($amount_input > ($grand_total_system + 100)) { // Tolerance 100 perak
        echo "<script>alert('GAGAL: Nominal pembayaran melebihi total tagihan!'); window.location='invoice_list.php';</script>";
        exit;
    }

    $proof_file = null;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $fileName = 'PAY_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $uploadDir . $fileName)) {
                $proof_file = $fileName;
            }
        }
    }

    if ($proof_file) {
        $sqlPay = "INSERT INTO payments (invoice_id, payment_date, amount, proof_file, created_by, notes) 
                   VALUES ($inv_id, '$pay_date', $amount_input, '$proof_file', $user_id, '$notes')";
        
        if ($conn->query($sqlPay)) {
            if ($amount_input >= ($grand_total_system - 100)) {
                $conn->query("UPDATE invoices SET status='paid' WHERE id=$inv_id");
            }
            echo "<script>alert('Pembayaran berhasil disimpan!'); window.location='payment_list.php';</script>";
        }
    } else {
        echo "<script>alert('Gagal upload bukti pembayaran.');</script>";
    }
}

// --- LOGIKA ACTION: UPLOAD FAKTUR PAJAK ---
if (isset($_POST['upload_tax_invoice'])) {
    $inv_id = intval($_POST['tax_invoice_id']);
    $uploadDir = __DIR__ . '/../uploads/';
    
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['tax_file']) && $_FILES['tax_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['tax_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $fileName = 'TAX_' . time() . '_' . $inv_id . '.' . $ext;
            if (move_uploaded_file($_FILES['tax_file']['tmp_name'], $uploadDir . $fileName)) {
                $sqlTax = "UPDATE invoices SET tax_invoice_file = '$fileName' WHERE id = $inv_id";
                if ($conn->query($sqlTax)) {
                    echo "<script>alert('Faktur Pajak berhasil diupload!'); window.location='invoice_list.php';</script>";
                } else {
                    echo "<script>alert('Gagal update database.');</script>";
                }
            } else {
                echo "<script>alert('Gagal memindahkan file.');</script>";
            }
        } else {
            echo "<script>alert('Format file tidak didukung. Gunakan PDF/JPG/PNG.');</script>";
        }
    } else {
        echo "<script>alert('Pilih file terlebih dahulu.');</script>";
    }
}

// --- LOGIKA ACTION: DELETE & STATUS ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $act = $_GET['action'];
    
    if ($act == 'delete') {
        if ($user_role == 'admin') {
            $conn->query("DELETE FROM invoice_items WHERE invoice_id=$id");
            $conn->query("DELETE FROM delivery_orders WHERE payment_id IN (SELECT id FROM payments WHERE invoice_id=$id)");
            $conn->query("DELETE FROM payments WHERE invoice_id=$id");
            $conn->query("DELETE FROM invoices WHERE id=$id");
            echo "<script>alert('Invoice berhasil dihapus permanen.'); window.location='invoice_list.php';</script>";
            exit;
        } else {
            echo "<script>alert('Akses Ditolak. Hanya Admin yang bisa menghapus.'); window.location='invoice_list.php';</script>";
            exit;
        }
    }

    if ($act == 'draft') { $conn->query("UPDATE invoices SET status='draft' WHERE id=$id"); }
    if ($act == 'sent') { $conn->query("UPDATE invoices SET status='sent' WHERE id=$id"); }
    if ($act == 'cancel') {
        $inv = $conn->query("SELECT quotation_id FROM invoices WHERE id=$id")->fetch_assoc();
        $q_id = $inv['quotation_id'];
        $conn->query("UPDATE invoices SET status='cancel' WHERE id=$id");
        $conn->query("UPDATE quotations SET status='cancel' WHERE id=$q_id");
        echo "<script>alert('Invoice dan Quotation terkait telah dibatalkan.');</script>";
    }
    echo "<script>window.location='invoice_list.php';</script>";
}

// --- 5. QUERY DATA TAMPILAN UTAMA ---
$sql = "SELECT i.*, c.company_name, q.quotation_no, q.currency, 
        isp.general_notes, 
        COALESCE(
            (SELECT SUM(qty * unit_price) FROM invoice_items WHERE invoice_id = i.id),
            (SELECT SUM(qty * unit_price) FROM quotation_items WHERE quotation_id = i.quotation_id)
        ) as sub_total,
        COALESCE(
            (SELECT SUM(amount) FROM invoice_adjustments WHERE invoice_id = i.id), 0
        ) as total_adjustment,
        (SELECT COUNT(*) FROM delivery_orders do JOIN payments pay ON do.payment_id = pay.id WHERE pay.invoice_id = i.id) as do_count,
        (SELECT SUM(amount) FROM payments WHERE invoice_id = i.id) as total_paid
        FROM invoices i 
        JOIN quotations q ON i.quotation_id=q.id 
        JOIN clients c ON q.client_id=c.id 
        LEFT JOIN invoice_scratchpads isp ON i.invoice_no = isp.invoice_no
        WHERE $where
        ORDER BY i.created_at DESC";
$res = $conn->query($sql);
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
    
    /* Utility class for dropdown menus to stay above everything */
    .dropdown-content { z-index: 999; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 flex items-center justify-center text-xl shadow-inner">
                    <i class="ph-bold ph-receipt"></i>
                </div>
                Invoice List
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Daftar tagihan, status pembayaran, dan manajemen faktur pajak.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="invoice_form.php" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-plus text-lg relative z-10"></i> 
                <span class="relative z-10">Create Manual Invoice</span>
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-lg"></i> Filter Data Tagihan
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET" action="invoice_list.php">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5 items-end">
                    
                    <div class="lg:col-span-4">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Pencarian</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all placeholder-slate-400 shadow-inner" placeholder="Cari No Invoice..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Perusahaan (Client)</label>
                        <div class="relative group">
                            <select name="client_id" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Perusahaan</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Status</label>
                        <div class="relative group">
                            <select name="status" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Status</option>
                                <option value="draft" <?= $f_status=='draft'?'selected':'' ?>>Draft</option>
                                <option value="sent" <?= $f_status=='sent'?'selected':'' ?>>Sent</option>
                                <option value="paid" <?= $f_status=='paid'?'selected':'' ?>>Paid</option>
                                <option value="cancel" <?= $f_status=='cancel'?'selected':'' ?>>Cancelled</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Faktur Pajak</label>
                        <div class="relative group">
                            <select name="tax_status" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Kondisi</option>
                                <option value="uploaded" <?= $f_tax=='uploaded'?'selected':'' ?>>Sudah Diupload</option>
                                <option value="pending" <?= $f_tax=='pending'?'selected':'' ?>>Pending / Belum</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Dari Tanggal</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($f_start_date) ?>" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner">
                    </div>
                    
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Sampai Tanggal</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($f_end_date) ?>" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner">
                    </div>

                    <div class="lg:col-span-3 flex gap-2 h-[42px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-sm active:scale-95 flex items-center justify-center gap-2">
                            Filter
                        </button>
                        <a href="invoice_list.php" class="flex-none w-[42px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                            <i class="ph-bold ph-arrows-counter-clockwise text-lg"></i>
                        </a>
                    </div>
                    
                    <div class="lg:col-span-3 h-[42px]">
                        <button type="submit" formmethod="POST" name="export_excel" class="w-full h-full bg-emerald-50 hover:bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20 dark:text-emerald-400 font-bold rounded-xl border border-emerald-200 dark:border-emerald-500/20 transition-all active:scale-95 shadow-sm flex items-center justify-center gap-2">
                            <i class="ph-bold ph-file-csv text-lg"></i> Export CSV
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300 relative min-h-[300px]">
        <div class="overflow-x-auto modern-scrollbar w-full pb-20"> <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Invoice Info</th>
                        <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider min-w-[200px]">Client</th>
                        <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-right text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Sub Total</th>
                        <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-right text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Adj</th>
                        <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-right text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">VAT</th>
                        <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-right text-xs font-black text-indigo-500 uppercase tracking-wider whitespace-nowrap bg-indigo-50/30 dark:bg-indigo-900/10">Grand Total</th>
                        <th class="px-4 py-4 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Note</th>
                        <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if($res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <?php
                            $subTotal = floatval($row['sub_total'] ?? 0);
                            $adjTotal = floatval($row['total_adjustment'] ?? 0);
                            $totalPaid = floatval($row['total_paid'] ?? 0);
                            $curr = $row['currency'];
                            
                            $is_international = ($row['invoice_type'] == 'International');
                            $tax_rate = $is_international ? 0 : 0.11;
                            $vat = $subTotal * $tax_rate;

                            if (!$is_international) {
                                $subTotal = round($subTotal, 0, PHP_ROUND_HALF_DOWN);
                                $vat = round($vat, 0, PHP_ROUND_HALF_DOWN);
                            } else {
                                $subTotal = round($subTotal, 2);
                                $vat = round($vat, 2);
                            }
                            $grandTotal = $subTotal + $vat; 
                            $remaining = $grandTotal - $totalPaid;

                            $fmt = function($n) use ($is_international) {
                                if ($is_international) return number_format($n, 0, '.', ','); 
                                return number_format($n, 0, ',', '.');
                            };

                            $st = strtolower($row['status']);
                            if ($st != 'cancel' && $totalPaid > 0 && $remaining > 100) {
                                $displayStatus = 'PARTIAL';
                                $stStyle = 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20';
                            } else {
                                $displayStatus = strtoupper($st);
                                if($st == 'paid') $stStyle = 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20';
                                elseif($st == 'cancel') $stStyle = 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20';
                                elseif($st == 'sent') $stStyle = 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-400 dark:border-sky-500/20';
                                else $stStyle = 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600';
                            }

                            $hasTax = !empty($row['tax_invoice_file']);
                            $hasNote = !empty($row['general_notes']);
                            $doCount = intval($row['do_count']);
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-4 align-top">
                                <div class="font-mono font-extrabold text-indigo-600 dark:text-indigo-400 text-xs mb-1">
                                    <?= htmlspecialchars($row['invoice_no']) ?>
                                </div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium tracking-wide">Ref: <?= htmlspecialchars($row['quotation_no']) ?></span>
                                </div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-widest border bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">
                                        <?= htmlspecialchars($row['invoice_type']) ?>
                                    </span>
                                    <?php if($hasTax): ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-widest border bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20" title="Tax Invoice Uploaded">
                                            <i class="ph-fill ph-check-circle"></i> Tax
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-1.5 line-clamp-2" title="<?= htmlspecialchars($row['company_name']) ?>">
                                    <?= htmlspecialchars($row['company_name']) ?>
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1.5 whitespace-nowrap">
                                    <i class="ph-fill ph-calendar-blank"></i>
                                    <?= date('d M Y', strtotime($row['invoice_date'])) ?>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top text-right text-xs font-bold text-slate-500 dark:text-slate-400">
                                <?= $fmt($subTotal) ?>
                            </td>

                            <td class="px-6 py-4 align-top text-right text-xs font-bold text-emerald-500 dark:text-emerald-400">
                                <?= $adjTotal != 0 ? $fmt($adjTotal) : '-' ?>
                            </td>

                            <td class="px-6 py-4 align-top text-right text-xs font-bold text-slate-500 dark:text-slate-400">
                                <?= $fmt($vat) ?>
                            </td>

                            <td class="px-6 py-4 align-top text-right bg-indigo-50/10 dark:bg-indigo-900/5">
                                <div class="font-black text-indigo-600 dark:text-indigo-400 text-sm">
                                    <span class="text-[10px] text-slate-400 mr-0.5"><?= $curr ?></span><?= $fmt($grandTotal) ?>
                                </div>
                                <?php if($totalPaid > 0): ?>
                                    <div class="text-[10px] font-bold text-emerald-500 mt-1">
                                        Paid: <?= $fmt($totalPaid) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-4 py-4 align-top text-center">
                                <button type="button" onclick="openNoteModal('<?= htmlspecialchars($row['invoice_no']) ?>')" id="btn-note-<?= htmlspecialchars($row['invoice_no']) ?>" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors shadow-sm active:scale-95 <?= $hasNote ? 'bg-amber-100 text-amber-600 hover:bg-amber-200 dark:bg-amber-500/20 dark:text-amber-400' : 'bg-slate-100 text-slate-400 hover:bg-indigo-100 hover:text-indigo-600 dark:bg-slate-700/50 dark:hover:bg-indigo-500/20 dark:hover:text-indigo-400' ?>" title="Catatan Internal">
                                    <i class="<?= $hasNote ? 'ph-fill' : 'ph-bold' ?> ph-sticky-note text-lg"></i>
                                </button>
                            </td>

                            <td class="px-6 py-4 align-top text-center">
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-md border text-[10px] font-black uppercase tracking-widest <?= $stStyle ?>">
                                        <?= $displayStatus ?>
                                    </span>
                                    <?php if($doCount > 0): ?>
                                        <span class="inline-flex items-center gap-1 text-[9px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                            <i class="ph-bold ph-truck"></i> DO Created
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top text-center relative">
                                <div class="relative inline-block text-left" data-dropdown>
                                    <button type="button" class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-400 dark:hover:text-indigo-400 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 dropdown-toggle-btn" aria-expanded="true" aria-haspopup="true">
                                        <i class="ph-bold ph-dots-three-vertical text-lg pointer-events-none"></i>
                                    </button>

                                    <div class="dropdown-menu hidden absolute right-0 mt-2 w-48 rounded-xl shadow-lg bg-white dark:bg-slate-800 ring-1 ring-black ring-opacity-5 dark:ring-slate-700 divide-y divide-slate-100 dark:divide-slate-700/50 dropdown-content origin-top-right transition-all">
                                        <div class="py-1">
                                            <a href="invoice_print.php?id=<?= $row['id'] ?>" target="_blank" class="group flex items-center gap-2 px-4 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400 transition-colors">
                                                <i class="ph-bold ph-printer text-base"></i> Print PDF
                                            </a>
                                            <button type="button" onclick="openTaxModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['invoice_no']) ?>')" class="w-full text-left group flex items-center gap-2 px-4 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400 transition-colors">
                                                <i class="ph-bold ph-file-arrow-up text-base"></i> <?= $hasTax ? 'Update Tax' : 'Upload Tax' ?>
                                            </button>
                                            <?php if($hasTax): ?>
                                                <a href="../uploads/<?= htmlspecialchars($row['tax_invoice_file']) ?>" target="_blank" class="group flex items-center gap-2 px-4 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400 transition-colors">
                                                    <i class="ph-bold ph-eye text-base"></i> View Tax
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if($st == 'paid' || $totalPaid > 0): ?>
                                        <div class="py-1">
                                            <a href="delivery_order_form.php?from_invoice_id=<?= $row['id'] ?>" class="group flex items-center gap-2 px-4 py-2 text-xs font-bold text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors">
                                                <i class="ph-bold ph-truck text-base"></i> Create DO
                                            </a>
                                        </div>
                                        <?php endif; ?>

                                        <?php if($st != 'paid' && $st != 'cancel'): ?>
                                        <div class="py-1">
                                            <?php if($st == 'draft'): ?>
                                                <a href="invoice_edit.php?id=<?= $row['id'] ?>" class="group flex items-center gap-2 px-4 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                                    <i class="ph-bold ph-pencil-simple text-base text-slate-400"></i> Edit Invoice
                                                </a>
                                                <a href="?action=sent&id=<?= $row['id'] ?>" class="group flex items-center gap-2 px-4 py-2 text-xs font-bold text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-500/10 transition-colors">
                                                    <i class="ph-bold ph-paper-plane-right text-base"></i> Mark as Sent
                                                </a>
                                            <?php endif; ?>
                                            
                                            <button type="button" onclick="openPayModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['invoice_no']) ?>', <?= $grandTotal ?>)" class="w-full text-left group flex items-center gap-2 px-4 py-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors">
                                                <i class="ph-bold ph-check-circle text-base"></i> Payment / DP
                                            </button>
                                            
                                            <a href="?action=cancel&id=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin membatalkan Invoice dan Quotation ini?')" class="group flex items-center gap-2 px-4 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                                                <i class="ph-bold ph-x-circle text-base"></i> Cancel Invoice
                                            </a>
                                        </div>
                                        <?php endif; ?>

                                        <?php if($user_role == 'admin'): ?>
                                        <div class="py-1">
                                            <a href="?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Hapus permanen? Data tidak dapat dikembalikan.')" class="group flex items-center gap-2 px-4 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-600 hover:text-white dark:hover:bg-rose-600 dark:hover:text-white transition-colors">
                                                <i class="ph-bold ph-trash text-base"></i> Delete
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-receipt text-4xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Tidak Ada Data Invoice</h4>
                                    <p class="text-sm font-medium">Belum ada invoice atau tidak ada yang sesuai dengan filter.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($res->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-center mt-[-80px]"> <p class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white dark:bg-[#1A222C] px-4 py-1.5 rounded-full border border-slate-100 dark:border-slate-700 shadow-sm relative z-10">
                Menampilkan <span class="text-indigo-600 dark:text-indigo-400 ml-1"><?= $res->num_rows ?> Invoice</span>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="noteModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeNoteModal()"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-lg flex flex-col transform transition-all scale-100">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center rounded-t-3xl">
            <h3 class="text-sm font-black text-slate-800 dark:text-white flex items-center gap-2">
                <i class="ph-bold ph-sticky-note text-amber-500 text-lg"></i> Catatan Invoice
            </h3>
            <button type="button" onclick="closeNoteModal()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-200/50 hover:bg-rose-100 text-slate-500 hover:text-rose-600 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-rose-500/20 dark:hover:text-rose-400 transition-colors">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="noteInvoiceNo">
            <input type="text" id="noteTitle" class="w-full px-4 py-2 mb-4 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-400 outline-none cursor-not-allowed text-center font-mono" readonly>
            <textarea id="generalNotes" rows="6" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-amber-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="Tulis catatan internal di sini..."></textarea>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center rounded-b-3xl">
            <span id="saveStatus" class="hidden text-xs font-bold text-emerald-500 flex items-center gap-1"><i class="ph-fill ph-check-circle"></i> Tersimpan!</span>
            <div class="flex gap-2 ml-auto">
                <button type="button" onclick="closeNoteModal()" class="px-4 py-2 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Tutup</button>
                <button type="button" onclick="saveNote()" class="px-5 py-2 rounded-xl font-bold text-sm bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition-all active:scale-95">Simpan Note</button>
            </div>
        </div>
    </div>
</div>

<div id="payModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closePayModal()"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100">
        <form method="POST" enctype="multipart/form-data" onsubmit="return validatePayment()">
            <div class="px-6 py-4 border-b border-emerald-500/20 bg-emerald-500 text-white flex justify-between items-center">
                <h3 class="text-sm font-black flex items-center gap-2"><i class="ph-bold ph-wallet text-xl"></i> Pembayaran / DP</h3>
                <button type="button" onclick="closePayModal()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto max-h-[80vh] modern-scrollbar">
                <input type="hidden" name="invoice_id" id="modal_inv_id">
                <input type="hidden" name="grand_total_system" id="modal_grand_total">
                
                <div class="mb-5 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 text-center">
                    <strong id="modal_inv_no" class="block text-sm text-emerald-800 dark:text-emerald-300 font-mono mb-1"></strong>
                    <div class="text-xs text-emerald-600 dark:text-emerald-400 font-bold uppercase tracking-widest mb-1">Total Tagihan</div>
                    <div class="text-2xl font-black text-emerald-700 dark:text-emerald-400">Rp <span id="display_total"></span></div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Tanggal Bayar <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="ph-bold ph-calendar absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Nominal Pembayaran (Bisa DP/Partial) <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400 group-focus-within:text-emerald-500 transition-colors">Rp</span>
                            <input type="number" name="amount" id="input_amount" required class="w-full pl-11 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all placeholder-slate-400" placeholder="0">
                        </div>
                        <div id="err_msg" class="hidden text-rose-500 text-[10px] font-bold mt-1.5 flex items-center gap-1"><i class="ph-fill ph-warning-circle"></i> Nominal tidak boleh melebihi total tagihan!</div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Catatan (Opsional)</label>
                        <textarea name="payment_notes" rows="2" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all placeholder-slate-400" placeholder="Contoh: DP 50%, Termin 1, Pelunasan"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Bukti Transfer <span class="text-rose-500">*</span></label>
                        <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-500 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 dark:file:bg-emerald-500/10 dark:file:text-emerald-400 hover:file:bg-emerald-100 transition-all cursor-pointer">
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3">
                <button type="button" onclick="closePayModal()" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="confirm_payment" class="px-6 py-2.5 rounded-xl font-bold text-sm bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-all active:scale-95">Simpan Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<div id="taxModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeTaxModal()"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-4 border-b border-sky-500/20 bg-sky-500 text-white flex justify-between items-center">
                <h3 class="text-sm font-black flex items-center gap-2"><i class="ph-bold ph-file-arrow-up text-xl"></i> Upload Faktur Pajak</h3>
                <button type="button" onclick="closeTaxModal()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <input type="hidden" name="tax_invoice_id" id="tax_invoice_id">
                
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">No Invoice</label>
                    <input type="text" id="tax_inv_no" readonly class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-400 outline-none cursor-not-allowed font-mono text-center">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">File Faktur (PDF/JPG/PNG) <span class="text-rose-500">*</span></label>
                    <input type="file" name="tax_file" accept=".jpg,.jpeg,.png,.pdf" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-500 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-sky-50 file:text-sky-700 dark:file:bg-sky-500/10 dark:file:text-sky-400 hover:file:bg-sky-100 transition-all cursor-pointer">
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3">
                <button type="button" onclick="closeTaxModal()" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="upload_tax_invoice" class="px-6 py-2.5 rounded-xl font-bold text-sm bg-sky-600 hover:bg-sky-700 text-white shadow-sm transition-all active:scale-95">Upload File</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- Logika Collapse Filter ---
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('filterToggleBtn');
        const body = document.getElementById('filterBody');
        const icon = document.getElementById('filterIcon');

        if(btn && body && icon) {
            btn.addEventListener('click', () => {
                if (body.classList.contains('hidden')) {
                    body.classList.remove('hidden');
                    setTimeout(() => body.style.opacity = '1', 10); 
                    icon.classList.replace('ph-caret-down', 'ph-caret-up');
                } else {
                    body.classList.add('hidden');
                    body.style.opacity = '0';
                    icon.classList.replace('ph-caret-up', 'ph-caret-down');
                }
            });
        }
    });

    // --- Logika Dropdown Actions (Tailwind JS Toggle) ---
    document.addEventListener('click', function(e) {
        // Tutup semua dropdown
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if(!menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        });

        // Buka jika tombol dropdown di-klik
        const toggleBtn = e.target.closest('.dropdown-toggle-btn');
        if (toggleBtn) {
            e.stopPropagation(); // Cegah event tertangkap oleh window listener di atas
            const wrapper = toggleBtn.closest('[data-dropdown]');
            const menu = wrapper.querySelector('.dropdown-menu');
            if(menu) {
                menu.classList.remove('hidden');
            }
        }
    });

    // --- Logika Notes Modal & Ajax ---
    const noteModal = document.getElementById('noteModal');
    
    function openNoteModal(invoiceNo) {
        document.getElementById('noteInvoiceNo').value = invoiceNo;
        document.getElementById('noteTitle').value = invoiceNo;
        document.getElementById('generalNotes').value = "Loading...";
        document.getElementById('saveStatus').classList.add('hidden');
        
        noteModal.classList.remove('hidden');
        
        const formData = new FormData();
        formData.append('action', 'load');
        formData.append('invoice_no', invoiceNo);
        fetch('ajax_scratchpad.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            document.getElementById('generalNotes').value = (res.status === 'success' && res.data) ? (res.data.general_notes || '') : '';
        }).catch(err => {
            document.getElementById('generalNotes').value = "Gagal memuat catatan.";
        });
    }

    function closeNoteModal() {
        noteModal.classList.add('hidden');
    }

    function saveNote() {
        const inv = document.getElementById('noteInvoiceNo').value;
        const notes = document.getElementById('generalNotes').value;
        const btn = document.getElementById('btn-note-' + inv);
        const formData = new FormData();
        formData.append('action', 'save'); 
        formData.append('invoice_no', inv); 
        formData.append('notes', notes); 
        formData.append('calc_data', '[]'); 
        
        fetch('ajax_scratchpad.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                const s = document.getElementById('saveStatus');
                s.classList.remove('hidden');
                
                // Ubah styling button icon note di tabel
                if(notes.trim() !== "") {
                    btn.classList.add('bg-amber-100', 'text-amber-600', 'hover:bg-amber-200', 'dark:bg-amber-500/20', 'dark:text-amber-400');
                    btn.classList.remove('bg-slate-100', 'text-slate-400', 'hover:bg-indigo-100', 'hover:text-indigo-600', 'dark:bg-slate-700/50', 'dark:hover:bg-indigo-500/20', 'dark:hover:text-indigo-400');
                    btn.querySelector('i').classList.replace('ph-bold', 'ph-fill');
                } else {
                    btn.classList.remove('bg-amber-100', 'text-amber-600', 'hover:bg-amber-200', 'dark:bg-amber-500/20', 'dark:text-amber-400');
                    btn.classList.add('bg-slate-100', 'text-slate-400', 'hover:bg-indigo-100', 'hover:text-indigo-600', 'dark:bg-slate-700/50', 'dark:hover:bg-indigo-500/20', 'dark:hover:text-indigo-400');
                    btn.querySelector('i').classList.replace('ph-fill', 'ph-bold');
                }
                
                setTimeout(() => { 
                    s.classList.add('hidden'); 
                    closeNoteModal();
                }, 1000);
            } else {
                alert('Gagal simpan: ' + res.message);
            }
        });
    }

    // --- Logika Payment Modal ---
    let systemTotal = 0;
    const payModal = document.getElementById('payModal');

    function openPayModal(id, no, total) {
        systemTotal = parseFloat(total);
        document.getElementById('modal_inv_id').value = id;
        document.getElementById('modal_inv_no').innerText = no;
        document.getElementById('modal_grand_total').value = total;
        document.getElementById('display_total').innerText = new Intl.NumberFormat('id-ID').format(total);
        document.getElementById('input_amount').value = ""; 
        document.getElementById('err_msg').classList.add('hidden');
        
        payModal.classList.remove('hidden');
    }

    function closePayModal() {
        payModal.classList.add('hidden');
    }

    function validatePayment() {
        let inputVal = parseFloat(document.getElementById('input_amount').value);
        if (isNaN(inputVal) || inputVal > (systemTotal + 100)) { 
            document.getElementById('err_msg').classList.remove('hidden');
            return false;
        }
        return true;
    }

    // --- Logika Tax Modal ---
    const taxModal = document.getElementById('taxModal');

    function openTaxModal(id, no) {
        document.getElementById('tax_invoice_id').value = id;
        document.getElementById('tax_inv_no').value = no;
        taxModal.classList.remove('hidden');
    }

    function closeTaxModal() {
        taxModal.classList.add('hidden');
    }
</script>

<?php include 'includes/footer.php'; ?>