<?php
// --- 1. LOAD CONFIG DULUAN (Agar Export Excel berjalan sebelum HTML) ---
include '../config/functions.php';

// Pastikan sesi dimulai jika belum (untuk cek role)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

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
                    $row['invoice_date'], $row['company_name'], $row['invoice_no'], $row['invoice_type'],
                    $poClient, $row['quotation_no'], $desc, $item['qty'], $item['unit_price'], $row['currency'],
                    $calcSub, $vat, $grandTotal, strtoupper($row['status']), $salesPerson, $doNum, $taxStatus, $cleanNotes 
                ));
            }
        } else {
            fputcsv($output, array(
                $row['invoice_date'], $row['company_name'], $row['invoice_no'], $row['invoice_type'],
                $poClient, $row['quotation_no'], 'No Items Found', 0, 0, $row['currency'], 0, 0, 0, 
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

    if ($amount_input > ($grand_total_system + 100)) { 
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
            } else { echo "<script>alert('Gagal memindahkan file.');</script>"; }
        } else { echo "<script>alert('Format file tidak didukung.');</script>"; }
    } else { echo "<script>alert('Pilih file terlebih dahulu.');</script>"; }
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
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
    
    /* Animasi Modal */
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 animate-fade-in-up">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Invoice List</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Manajemen tagihan klien, status faktur pajak, dan riwayat pembayaran.</p>
        </div>
        <a href="invoice_form.php" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 active:scale-95 whitespace-nowrap">
            <i class="ph-bold ph-plus text-lg"></i> Create Manual Invoice
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/80 rounded-t-2xl" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-base"></i> Filter & Pencarian
            </h3>
            <i id="filterIcon" class="ph-bold ph-caret-up text-slate-400 transition-transform duration-300"></i>
        </div>
        
        <div id="filterBody" class="p-5 block transition-all duration-300">
            <form method="GET" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-4">
                    <div class="xl:col-span-2">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">No Invoice</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="search" class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="e.g. INV-..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Perusahaan / Klien</label>
                        <div class="relative">
                            <i class="ph-bold ph-buildings absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <select name="client_id" class="w-full pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">-- Semua Klien --</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Status Pembayaran</label>
                        <div class="relative">
                            <i class="ph-bold ph-wallet absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <select name="status" class="w-full pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">-- Semua Status --</option>
                                <option value="draft" <?= $f_status=='draft'?'selected':'' ?>>Draft</option>
                                <option value="sent" <?= $f_status=='sent'?'selected':'' ?>>Sent</option>
                                <option value="paid" <?= $f_status=='paid'?'selected':'' ?>>Paid</option>
                                <option value="cancel" <?= $f_status=='cancel'?'selected':'' ?>>Cancelled</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Start Date</label>
                        <input type="date" name="start_date" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" value="<?= htmlspecialchars($f_start_date) ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">End Date</label>
                        <input type="date" name="end_date" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" value="<?= htmlspecialchars($f_end_date) ?>">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Faktur Pajak</label>
                        <div class="relative">
                            <select name="tax_status" class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">- Semua -</option>
                                <option value="uploaded" <?= $f_tax=='uploaded'?'selected':'' ?>>Uploaded</option>
                                <option value="pending" <?= $f_tax=='pending'?'selected':'' ?>>Pending</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="flex gap-2 lg:col-span-3 xl:col-span-3">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold py-2 px-3 rounded-xl transition-colors text-xs shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                            <i class="ph-bold ph-funnel text-sm"></i> Terapkan Filter
                        </button>
                        <?php if(!empty($search) || !empty($f_client) || !empty($f_status) || !empty($f_tax) || !empty($f_start_date)): ?>
                            <a href="invoice_list.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2 px-3 rounded-xl transition-colors text-xs text-center border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-sm"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <form method="POST" action="invoice_list.php" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 flex justify-end">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="client_id" value="<?= htmlspecialchars($f_client) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($f_status) ?>">
                <input type="hidden" name="tax_status" value="<?= htmlspecialchars($f_tax) ?>">
                <input type="hidden" name="start_date" value="<?= htmlspecialchars($f_start_date) ?>">
                <input type="hidden" name="end_date" value="<?= htmlspecialchars($f_end_date) ?>">
                
                <button type="submit" name="export_excel" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 px-5 rounded-xl transition-colors text-xs shadow-sm shadow-emerald-500/30 active:scale-95 flex items-center justify-center gap-2">
                    <i class="ph-bold ph-file-csv text-base"></i> Export to CSV
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="overflow-x-auto custom-scrollbar w-full pb-32"> <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                    <tr>
                        <th class="px-5 py-3.5">Invoice Info</th>
                        <th class="px-5 py-3.5">Client Details</th>
                        <th class="px-5 py-3.5 text-right">Financial Info</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-center">Note</th>
                        <th class="px-5 py-3.5 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px]">
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
                                if ($is_international) return number_format($n, 2, '.', ','); 
                                return number_format($n, 0, ',', '.');
                            };

                            $st = strtolower($row['status']);
                            if ($st != 'cancel' && $totalPaid > 0 && $remaining > 100) {
                                $displayStatus = 'PARTIAL';
                                $bgClass = 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400';
                            } else {
                                $displayStatus = strtoupper($st);
                                if($st == 'paid') $bgClass = 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400';
                                elseif($st == 'cancel') $bgClass = 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400';
                                elseif($st == 'sent') $bgClass = 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-500/10 dark:text-sky-400';
                                else $bgClass = 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300';
                            }

                            $hasTax = !empty($row['tax_invoice_file']);
                            $hasNote = !empty($row['general_notes']);
                            $doCount = intval($row['do_count']);
                        ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-5 py-4 align-top">
                                <div class="font-mono font-extrabold text-indigo-600 dark:text-indigo-400 text-xs">
                                    <?= htmlspecialchars($row['invoice_no']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 mb-1.5 font-medium flex items-center gap-1">
                                    <i class="ph-fill ph-calendar-blank"></i> <?= date('d M Y', strtotime($row['invoice_date'])) ?>
                                </div>
                                <?php if($hasTax): ?>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400">
                                        <i class="ph-fill ph-receipt text-[10px]"></i> Tax Attached
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400">
                                        <i class="ph-bold ph-minus text-[10px]"></i> No Tax
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs truncate max-w-[200px]" title="<?= htmlspecialchars($row['company_name']) ?>">
                                    <?= htmlspecialchars($row['company_name']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 font-medium">
                                    Ref Quote: <span class="font-mono"><?= htmlspecialchars($row['quotation_no']) ?></span>
                                </div>
                                <div class="mt-1.5">
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 text-[9px] font-black uppercase tracking-widest">
                                        <?= htmlspecialchars($row['invoice_type']) ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top text-right">
                                <div class="grid grid-cols-2 gap-x-3 gap-y-1 justify-end text-[10px] max-w-xs ml-auto">
                                    <span class="text-slate-500 dark:text-slate-400">Sub Total:</span>
                                    <span class="font-medium text-slate-700 dark:text-slate-300"><?= $curr ?> <?= $fmt($subTotal) ?></span>
                                    
                                    <span class="text-slate-500 dark:text-slate-400">VAT (11%):</span>
                                    <span class="font-medium text-slate-700 dark:text-slate-300"><?= $curr ?> <?= $fmt($vat) ?></span>
                                    
                                    <?php if($adjTotal != 0): ?>
                                    <span class="text-slate-500 dark:text-slate-400">Termin/DP:</span>
                                    <span class="font-medium text-emerald-600 dark:text-emerald-400"><?= $curr ?> <?= $fmt($adjTotal) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 pt-1.5 border-t border-slate-100 dark:border-slate-700/50 flex justify-end items-end gap-2">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">TOTAL</span>
                                    <span class="font-bold text-indigo-600 dark:text-indigo-400 text-sm"><?= $curr ?> <?= $fmt($grandTotal) ?></span>
                                </div>
                                <?php if($totalPaid > 0): ?>
                                <div class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                                    PAID: <?= $curr ?> <?= $fmt($totalPaid) ?>
                                </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg border text-[9px] font-black uppercase tracking-widest w-20 <?= $bgClass ?>">
                                    <?= $displayStatus ?>
                                </span>
                                <?php if($doCount > 0): ?>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-1.5 py-0.5 rounded border border-indigo-100 dark:border-indigo-500/20">
                                            <i class="ph-bold ph-truck text-[10px]"></i> DO Created
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-4 align-top text-center">
                                <button onclick="openNoteModal('<?= $row['invoice_no'] ?>')" class="w-8 h-8 rounded-lg flex items-center justify-center mx-auto transition-all <?= $hasNote ? 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 hover:bg-amber-200' : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500 hover:text-indigo-500' ?>" id="btn-note-<?= $row['invoice_no'] ?>" title="Notes">
                                    <i class="ph-fill ph-notepad text-lg"></i>
                                </button>
                            </td>

                            <td class="px-5 py-4 align-top text-center relative">
                                <button onclick="toggleActionMenu(<?= $row['id'] ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-all shadow-sm active:scale-95 focus:outline-none">
                                    <i class="ph-bold ph-dots-three-vertical text-lg"></i>
                                </button>

                                <div id="action-menu-<?= $row['id'] ?>" class="hidden absolute right-10 top-4 w-44 bg-white dark:bg-[#24303F] rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden text-left origin-top-right">
                                    <div class="py-1">
                                        <a href="invoice_print.php?id=<?= $row['id'] ?>" target="_blank" class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-printer text-sm"></i> Print PDF
                                        </a>
                                        
                                        <button onclick="openTaxModal(<?= $row['id'] ?>, '<?= $row['invoice_no'] ?>')" class="w-full text-left flex items-center gap-2 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-receipt text-sm text-slate-400"></i> <?= $hasTax ? 'Update Tax' : 'Upload Tax' ?>
                                        </button>
                                        
                                        <?php if($hasTax): ?>
                                        <a href="../uploads/<?= $row['tax_invoice_file'] ?>" target="_blank" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-eye text-sm text-slate-400"></i> View Tax
                                        </a>
                                        <?php endif; ?>

                                        <?php if($st == 'paid' || $totalPaid > 0): ?>
                                            <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                            <a href="delivery_order_form.php?from_invoice_id=<?= $row['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-slate-800 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-truck text-sm text-amber-500"></i> Create DO
                                            </a>
                                        <?php endif; ?>

                                        <?php if($st != 'paid' && $st != 'cancel'): ?>
                                            <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                            
                                            <?php if($st == 'draft'): ?>
                                            <a href="invoice_edit.php?id=<?= $row['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-pencil-simple text-sm"></i> Edit Invoice
                                            </a>
                                            <a href="?action=sent&id=<?= $row['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-paper-plane-tilt text-sm"></i> Mark Sent
                                            </a>
                                            <?php endif; ?>
                                            
                                            <button onclick="openPayModal(<?= $row['id'] ?>, '<?= $row['invoice_no'] ?>', <?= $grandTotal ?>)" class="w-full text-left flex items-center gap-2 px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-wallet text-sm"></i> Add Payment / DP
                                            </button>
                                            
                                            <a href="?action=cancel&id=<?= $row['id'] ?>" onclick="return confirm('Batalkan Invoice?')" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-x-circle text-sm"></i> Cancel
                                            </a>
                                        <?php endif; ?>

                                        <?php if($user_role == 'admin'): ?>
                                            <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                            <a href="?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Hapus permanen?')" class="flex items-center gap-2 px-4 py-2 text-xs font-black text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                                                <i class="ph-fill ph-trash text-sm"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-receipt text-3xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mb-0.5">Tidak Ada Data</h4>
                                    <p class="text-[11px] font-medium">Data invoice tidak ditemukan dengan filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($res->num_rows > 0): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Menampilkan total <?= $res->num_rows ?> invoice.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="noteModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700 flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50 rounded-t-2xl">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2"><i class="ph-fill ph-notepad text-indigo-500 text-lg"></i> Internal Notes</h3>
            <button onclick="closeModal('noteModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"><i class="ph-bold ph-x text-lg"></i></button>
        </div>
        <div class="p-6">
            <input type="hidden" id="noteInvoiceNo">
            <input type="text" id="noteTitle" class="w-full mb-3 px-3 py-2 bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold font-mono outline-none" readonly>
            <textarea id="generalNotes" class="w-full px-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 transition-all resize-none" rows="6" placeholder="Tulis catatan internal di sini..."></textarea>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50 rounded-b-2xl">
            <span id="saveStatus" class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest hidden"><i class="ph-bold ph-check"></i> Saved!</span>
            <div class="ml-auto flex gap-2">
                <button onclick="closeModal('noteModal')" class="px-5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors border border-slate-200 dark:border-slate-700">Close</button>
                <button onclick="saveNote()" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-500/30">Save Note</button>
            </div>
        </div>
    </div>
</div>

<div id="payModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700 flex flex-col">
        <form method="POST" enctype="multipart/form-data" onsubmit="return validatePayment()">
            <div class="px-6 py-4 border-b border-emerald-500 bg-emerald-500 rounded-t-2xl flex justify-between items-center text-white">
                <h3 class="text-sm font-bold flex items-center gap-2"><i class="ph-bold ph-wallet text-lg"></i> Input Pembayaran / DP</h3>
                <button type="button" onclick="closeModal('payModal')" class="text-white/70 hover:text-white"><i class="ph-bold ph-x text-lg"></i></button>
            </div>
            
            <div class="p-6">
                <input type="hidden" name="invoice_id" id="modal_inv_id">
                <input type="hidden" name="grand_total_system" id="modal_grand_total">
                
                <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-xl p-4 text-center mb-5">
                    <p id="modal_inv_no" class="text-xs font-bold text-emerald-800 dark:text-emerald-400 font-mono mb-1"></p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest font-bold">Total Tagihan</p>
                    <p class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">Rp <span id="display_total"></span></p>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Tanggal Bayar</label>
                        <input type="date" name="payment_date" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-emerald-500 dark:text-white outline-none transition-all" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nominal (DP / Lunas)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rp</span>
                            <input type="number" name="amount" id="input_amount" class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-emerald-600 dark:text-emerald-400 focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 outline-none transition-all" required>
                        </div>
                        <p id="err_msg" class="text-[10px] font-bold text-rose-500 mt-1 hidden"><i class="ph-fill ph-warning-circle"></i> Nominal melebih total tagihan!</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Catatan (Opsional)</label>
                        <textarea name="payment_notes" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 resize-none transition-all" rows="2" placeholder="e.g. DP 50%, Pelunasan..."></textarea>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Bukti Transfer (Image/PDF)</label>
                        <input type="file" name="proof_file" class="w-full block text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-500/10 dark:file:text-emerald-400 dark:hover:file:bg-emerald-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex bg-slate-50/50 dark:bg-slate-800/50 rounded-b-2xl">
                <button type="submit" name="confirm_payment" class="w-full py-2.5 rounded-xl text-xs font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition-colors shadow-md shadow-emerald-500/30 flex items-center justify-center gap-2">
                    <i class="ph-bold ph-floppy-disk text-sm"></i> Simpan Pembayaran
                </button>
            </div>
        </form>
    </div>
</div>

<div id="taxModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700 flex flex-col">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-5 py-4 border-b border-amber-500 bg-amber-500 rounded-t-2xl flex justify-between items-center text-white">
                <h3 class="text-sm font-bold flex items-center gap-2"><i class="ph-bold ph-receipt text-lg"></i> Upload Faktur Pajak</h3>
                <button type="button" onclick="closeModal('taxModal')" class="text-white/70 hover:text-white"><i class="ph-bold ph-x text-lg"></i></button>
            </div>
            <div class="p-5">
                <input type="hidden" name="tax_invoice_id" id="tax_invoice_id">
                <div class="mb-3">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">No Invoice</label>
                    <input type="text" id="tax_inv_no" class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold font-mono text-slate-500 outline-none" readonly>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">File Faktur (PDF/Image)</label>
                    <input type="file" name="tax_file" class="w-full block text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 dark:file:bg-amber-500/10 dark:file:text-amber-400 dark:hover:file:bg-amber-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
            </div>
            <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 flex bg-slate-50/50 dark:bg-slate-800/50 rounded-b-2xl">
                <button type="submit" name="upload_tax_invoice" class="w-full py-2.5 rounded-xl text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 transition-colors shadow-md shadow-amber-500/30 flex items-center justify-center gap-2">
                    <i class="ph-bold ph-upload-simple text-sm"></i> Upload Dokumen
                </button>
            </div>
        </form>
    </div>
</div>


<script>
    // --- FILTER TOGGLE ---
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('filterToggleBtn');
        const body = document.getElementById('filterBody');
        const icon = document.getElementById('filterIcon');

        if(btn && body && icon) {
            btn.addEventListener('click', () => {
                body.classList.toggle('hidden');
                if (body.classList.contains('hidden')) {
                    icon.classList.replace('ph-caret-up', 'ph-caret-down');
                } else {
                    icon.classList.replace('ph-caret-down', 'ph-caret-up');
                }
            });
        }
    });

    // --- MODAL HANDLERS ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
        }, 10);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.add('opacity-0');
        box.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // --- ACTION MENU DROPDOWN LOGIC ---
    let currentOpenDropdown = null;
    function toggleActionMenu(id) {
        const menu = document.getElementById('action-menu-' + id);
        if (currentOpenDropdown && currentOpenDropdown !== menu) {
            currentOpenDropdown.classList.add('hidden');
        }
        menu.classList.toggle('hidden');
        currentOpenDropdown = menu.classList.contains('hidden') ? null : menu;
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (currentOpenDropdown && !e.target.closest('td.relative')) {
            currentOpenDropdown.classList.add('hidden');
            currentOpenDropdown = null;
        }
    });

    // --- AJAX & FORM LOGIC ---
    let systemTotal = 0;

    function openNoteModal(invoiceNo) {
        document.getElementById('noteInvoiceNo').value = invoiceNo;
        document.getElementById('noteTitle').value = invoiceNo;
        document.getElementById('generalNotes').value = "Loading...";
        document.getElementById('saveStatus').classList.add('hidden');
        
        openModal('noteModal');

        const formData = new FormData();
        formData.append('action', 'load');
        formData.append('invoice_no', invoiceNo);
        fetch('ajax_scratchpad.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            document.getElementById('generalNotes').value = (res.status === 'success' && res.data) ? (res.data.general_notes || '') : '';
        });
    }

    function saveNote() {
        const inv = document.getElementById('noteInvoiceNo').value;
        const notes = document.getElementById('generalNotes').value;
        const btn = document.getElementById('btn-note-' + inv);
        
        const formData = new FormData();
        formData.append('action', 'save'); formData.append('invoice_no', inv); formData.append('notes', notes); formData.append('calc_data', '[]'); 
        
        fetch('ajax_scratchpad.php', { method: 'POST', body: formData }).then(res => res.json()).then(res => {
            if(res.status === 'success') {
                const s = document.getElementById('saveStatus');
                s.classList.remove('hidden');
                setTimeout(() => { s.classList.add('hidden'); closeModal('noteModal'); }, 1000);
                
                // Update Button Color Dynamic
                if(notes.trim() !== "") {
                    btn.classList.remove('bg-slate-100', 'text-slate-400', 'dark:bg-slate-800', 'dark:text-slate-500');
                    btn.classList.add('bg-amber-100', 'text-amber-600', 'dark:bg-amber-500/20', 'dark:text-amber-400');
                } else {
                    btn.classList.add('bg-slate-100', 'text-slate-400', 'dark:bg-slate-800', 'dark:text-slate-500');
                    btn.classList.remove('bg-amber-100', 'text-amber-600', 'dark:bg-amber-500/20', 'dark:text-amber-400');
                }
            } else alert('Gagal simpan: ' + res.message);
        });
    }

    function openPayModal(id, no, total) {
        systemTotal = parseFloat(total);
        document.getElementById('modal_inv_id').value = id;
        document.getElementById('modal_inv_no').innerText = no;
        document.getElementById('modal_grand_total').value = total;
        document.getElementById('display_total').innerText = new Intl.NumberFormat('id-ID').format(total);
        document.getElementById('input_amount').value = ""; 
        document.getElementById('err_msg').classList.add('hidden');
        openModal('payModal');
    }

    function openTaxModal(id, no) {
        document.getElementById('tax_invoice_id').value = id;
        document.getElementById('tax_inv_no').value = no;
        openModal('taxModal');
    }

    function validatePayment() {
        let inputVal = parseFloat(document.getElementById('input_amount').value);
        if (isNaN(inputVal) || inputVal > (systemTotal + 100)) { 
            document.getElementById('err_msg').classList.remove('hidden');
            return false;
        }
        return true;
    }
</script>

<?php include 'includes/footer.php'; ?>