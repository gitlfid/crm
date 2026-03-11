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

// --- 3. LOGIKA EXPORT EXCEL (CSV) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean(); 
    
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

        $itemsData = [];
        $sqlItems = "SELECT item_name, description, qty, unit_price FROM invoice_items WHERE invoice_id = $invId";
        $resItems = $conn->query($sqlItems);
        
        if ($resItems->num_rows == 0) {
            $sqlItems = "SELECT item_name, description, qty, unit_price FROM quotation_items WHERE quotation_id = $quotId";
            $resItems = $conn->query($sqlItems);
        }

        $calcSub = 0;
        while ($itm = $resItems->fetch_assoc()) {
            $itemsData[] = $itm;
            $calcSub += floatval($itm['qty']) * floatval($itm['unit_price']);
        }
        
        $is_international = ($row['invoice_type'] == 'International');
        $tax_rate = $is_international ? 0 : 0.11;
        $vat = $calcSub * $tax_rate;

        if (!$is_international) {
            $calcSub = round($calcSub, 0, PHP_ROUND_HALF_DOWN);
            $vat = round($vat, 0, PHP_ROUND_HALF_DOWN);
        } else {
            $calcSub = round($calcSub, 2);
            $vat = round($vat, 2);
        }
        $grandTotal = $calcSub + $vat;
        
        $doNum = !empty($row['do_numbers']) ? $row['do_numbers'] : '-';
        $poClient = !empty($row['po_number_client']) ? $row['po_number_client'] : '-';
        $salesPerson = !empty($row['sales_name']) ? $row['sales_name'] : '-';
        $taxStatus = (!empty($row['tax_invoice_file'])) ? 'Uploaded' : 'Pending';
        $cleanNotes = !empty($row['general_notes']) ? str_replace(array("\r", "\n"), " ", $row['general_notes']) : '-';

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

// --- QUERY STATISTIK DINAMIS ---
$sql_stats = "SELECT i.status, COUNT(*) as count 
              FROM invoices i 
              JOIN quotations q ON i.quotation_id = q.id 
              WHERE $where GROUP BY i.status";
$res_stats = $conn->query($sql_stats);

$stats = ['total' => 0, 'draft' => 0, 'sent' => 0, 'paid' => 0, 'cancel' => 0];
if ($res_stats) {
    while($s = $res_stats->fetch_assoc()) {
        $stats['total'] += $s['count'];
        $st_key = strtolower($s['status']);
        if (isset($stats[$st_key])) {
            $stats[$st_key] += $s['count'];
        }
    }
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
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-emerald-500 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-receipt"></i>
                </div>
                Invoice Management
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Pantau status tagihan, kelola pembayaran, dan unggah faktur pajak.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='invoice_list.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh / Reset Filter">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
            <a href="invoice_form.php" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-emerald-500 hover:from-indigo-500 hover:to-emerald-400 text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-plus text-xl relative z-10"></i> 
                <span class="relative z-10">Create Manual Invoice</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-files"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total Invoices</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['total']) ?></h4>
            </div>
        </div>
        
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-check-circle"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Fully Paid</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['paid']) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-paper-plane-tilt"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Sent to Client</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['sent']) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-4 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-file-dashed"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Draft / Unpaid</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stats['draft']) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-lg"></i> Filter Data & Export
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET" action="invoice_list.php">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5 mb-5">
                    
                    <div class="xl:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">No Invoice</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner uppercase" placeholder="e.g. INV-..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Perusahaan / Klien</label>
                        <div class="relative group">
                            <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <select name="client_id" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Klien</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Status Pembayaran</label>
                        <div class="relative group">
                            <i class="ph-bold ph-wallet absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <select name="status" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Status</option>
                                <option value="draft" <?= $f_status=='draft'?'selected':'' ?>>Draft</option>
                                <option value="sent" <?= $f_status=='sent'?'selected':'' ?>>Sent</option>
                                <option value="paid" <?= $f_status=='paid'?'selected':'' ?>>Paid</option>
                                <option value="cancel" <?= $f_status=='cancel'?'selected':'' ?>>Cancelled</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-12 gap-5 items-end">
                    
                    <div class="xl:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Faktur Pajak</label>
                        <div class="relative group">
                            <select name="tax_status" class="w-full pl-4 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Kondisi</option>
                                <option value="uploaded" <?= $f_tax=='uploaded'?'selected':'' ?>>Sudah Upload</option>
                                <option value="pending" <?= $f_tax=='pending'?'selected':'' ?>>Pending / Belum</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="xl:col-span-4">
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Periode Tagihan</label>
                        <div class="flex items-center gap-2">
                            <input type="date" name="start_date" class="w-full px-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= htmlspecialchars($f_start_date) ?>">
                            <span class="text-slate-400 font-bold">-</span>
                            <input type="date" name="end_date" class="w-full px-3 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= htmlspecialchars($f_end_date) ?>">
                        </div>
                    </div>

                    <div class="xl:col-span-5 flex gap-2 h-[46px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-md shadow-slate-200 dark:shadow-indigo-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-funnel"></i> Terapkan
                        </button>
                        <button type="submit" formmethod="POST" name="export_excel" class="flex-none w-[46px] bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500 dark:hover:text-white border border-emerald-200 dark:border-emerald-500/20 transition-all rounded-xl active:scale-95 flex items-center justify-center" title="Export CSV">
                            <i class="ph-bold ph-microsoft-excel-logo text-lg"></i>
                        </button>
                        <?php if(!empty($search) || !empty($f_client) || !empty($f_status) || !empty($f_tax) || !empty($f_start_date)): ?>
                            <a href="invoice_list.php" class="flex-none w-[46px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300 relative min-h-[400px]">
        <div class="overflow-x-auto modern-scrollbar w-full pb-32"> 
            <table class="w-full text-left border-collapse table-auto min-w-[1000px]">
                <thead class="bg-slate-50/80 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Invoice Info</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-black text-slate-400 uppercase tracking-wider min-w-[200px]">Client Details</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-[11px] text-right font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Financial Info</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Note</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-[11px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
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
                                if ($is_international) return number_format($n, 2, '.', ','); 
                                return number_format($n, 0, ',', '.');
                            };

                            $st = strtolower($row['status']);
                            if ($st != 'cancel' && $totalPaid > 0 && $remaining > 100) {
                                $displayStatus = 'PARTIAL';
                                $bgClass = 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400';
                            } else {
                                $displayStatus = strtoupper($st);
                                if($st == 'paid') $bgClass = 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400';
                                elseif($st == 'cancel') $bgClass = 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400';
                                elseif($st == 'sent') $bgClass = 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-400';
                                else $bgClass = 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700/50 dark:border-slate-600 dark:text-slate-300';
                            }

                            $hasTax = !empty($row['tax_invoice_file']);
                            $hasNote = !empty($row['general_notes']);
                            $doCount = intval($row['do_count']);
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle whitespace-nowrap">
                                <div class="font-mono font-black text-indigo-600 dark:text-indigo-400 text-[13px] mb-1 tracking-wide">
                                    <?= htmlspecialchars($row['invoice_no']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mb-2 font-medium flex items-center gap-1.5">
                                    <i class="ph-fill ph-calendar-blank"></i> <?= date('d M Y', strtotime($row['invoice_date'])) ?>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <?php if($hasTax): ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 shadow-sm" title="Tax Invoice Uploaded">
                                            <i class="ph-fill ph-check-circle"></i> Tax
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400 shadow-sm" title="No Tax Document">
                                            <i class="ph-bold ph-minus"></i> No Tax
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-1.5 truncate max-w-[250px] group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" title="<?= htmlspecialchars($row['company_name']) ?>">
                                    <?= htmlspecialchars($row['company_name']) ?>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">
                                        Ref: <span class="font-mono bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700"><?= htmlspecialchars($row['quotation_no']) ?></span>
                                    </div>
                                    <span class="inline-block w-max px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20 text-[9px] font-black uppercase tracking-widest mt-0.5">
                                        <?= htmlspecialchars($row['invoice_type']) ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-middle text-right whitespace-nowrap">
                                <div class="flex flex-col items-end gap-1">
                                    <div class="text-[10px] flex justify-between w-full max-w-[140px] ml-auto font-medium">
                                        <span class="text-slate-500 dark:text-slate-400">Subtotal:</span>
                                        <span class="text-slate-700 dark:text-slate-300"><span class="text-[9px] mr-0.5 opacity-70"><?= $curr ?></span><?= $fmt($subTotal) ?></span>
                                    </div>
                                    <div class="text-[10px] flex justify-between w-full max-w-[140px] ml-auto font-medium">
                                        <span class="text-slate-500 dark:text-slate-400">VAT (11%):</span>
                                        <span class="text-slate-700 dark:text-slate-300"><span class="text-[9px] mr-0.5 opacity-70"><?= $curr ?></span><?= $fmt($vat) ?></span>
                                    </div>
                                    <?php if($adjTotal != 0): ?>
                                    <div class="text-[10px] flex justify-between w-full max-w-[140px] ml-auto font-bold text-emerald-600 dark:text-emerald-400">
                                        <span>DP/Adj:</span>
                                        <span><span class="text-[9px] mr-0.5 opacity-70"><?= $curr ?></span><?= $fmt($adjTotal) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-[13px] font-black text-indigo-600 dark:text-indigo-400 mt-1.5 pt-1.5 border-t border-slate-200 dark:border-slate-700 flex justify-between w-full max-w-[140px] ml-auto items-end">
                                        <span class="text-[9px] uppercase tracking-widest text-slate-400 mb-0.5">Total</span>
                                        <span><span class="text-[10px] font-bold opacity-70 mr-0.5"><?= $curr ?></span><?= $fmt($grandTotal) ?></span>
                                    </div>
                                    
                                    <?php if($totalPaid > 0): ?>
                                    <div class="text-[10px] font-bold text-emerald-500 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-100 dark:border-emerald-500/20 mt-1">
                                        PAID: <?= $curr ?> <?= $fmt($totalPaid) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-center whitespace-nowrap">
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg border text-[9px] font-black uppercase tracking-widest w-20 shadow-sm <?= $bgClass ?>">
                                        <?= $displayStatus ?>
                                    </span>
                                    <?php if($doCount > 0): ?>
                                        <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-widest text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-1.5 py-0.5 rounded shadow-sm">
                                            <i class="ph-bold ph-truck text-[10px]"></i> DO Created
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-4 py-5 align-middle text-center whitespace-nowrap">
                                <button type="button" onclick="openNoteModal('<?= htmlspecialchars($row['invoice_no']) ?>')" id="btn-note-<?= htmlspecialchars($row['invoice_no']) ?>" class="w-8 h-8 rounded-xl flex items-center justify-center mx-auto transition-all shadow-sm active:scale-95 <?= $hasNote ? 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 hover:bg-amber-200 border border-transparent' : 'bg-slate-100 text-slate-400 dark:bg-slate-700/50 dark:border-slate-600 dark:text-slate-500 hover:bg-indigo-100 hover:text-indigo-600 dark:hover:bg-indigo-500/20 dark:hover:text-indigo-400 border border-slate-200' ?>" title="Internal Notes">
                                    <i class="<?= $hasNote ? 'ph-fill' : 'ph-bold' ?> ph-notepad text-lg"></i>
                                </button>
                            </td>

                            <td class="px-6 py-5 align-middle text-center whitespace-nowrap relative">
                                <div class="relative inline-block text-left" data-dropdown>
                                    <button type="button" onclick="toggleActionMenu(event, <?= $row['id'] ?>)" class="inline-flex justify-center items-center w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-400 dark:hover:text-indigo-400 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 dropdown-toggle-btn active:scale-95" aria-expanded="true" aria-haspopup="true">
                                        <i class="ph-bold ph-dots-three-vertical text-lg pointer-events-none"></i>
                                    </button>

                                    <div id="action-menu-<?= $row['id'] ?>" class="dropdown-menu hidden absolute right-8 top-0 w-48 bg-white dark:bg-[#24303F] rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden text-left origin-top-right transition-all divide-y divide-slate-50 dark:divide-slate-700/50">
                                        
                                        <div class="py-1">
                                            <a href="invoice_print.php?id=<?= $row['id'] ?>" target="_blank" class="group flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400 transition-colors">
                                                <i class="ph-bold ph-printer text-base text-slate-400 group-hover:text-indigo-500"></i> Print PDF
                                            </a>
                                            
                                            <button type="button" onclick="openTaxModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['invoice_no']) ?>')" class="w-full text-left group flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-500/10 dark:hover:text-sky-400 transition-colors">
                                                <i class="ph-bold ph-receipt text-base text-slate-400 group-hover:text-sky-500"></i> <?= $hasTax ? 'Update Tax' : 'Upload Tax' ?>
                                            </button>
                                            
                                            <?php if($hasTax): ?>
                                            <a href="../uploads/<?= htmlspecialchars($row['tax_invoice_file']) ?>" target="_blank" class="group flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-eye text-base text-slate-400"></i> View Tax
                                            </a>
                                            <?php endif; ?>
                                        </div>

                                        <?php if($st == 'paid' || $totalPaid > 0): ?>
                                        <div class="py-1">
                                            <a href="delivery_order_form.php?from_invoice_id=<?= $row['id'] ?>" class="group flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors">
                                                <i class="ph-bold ph-truck text-base"></i> Create DO
                                            </a>
                                        </div>
                                        <?php endif; ?>

                                        <?php if($st != 'paid' && $st != 'cancel'): ?>
                                        <div class="py-1">
                                            <?php if($st == 'draft'): ?>
                                            <a href="invoice_edit.php?id=<?= $row['id'] ?>" class="group flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-pencil-simple text-base text-slate-400"></i> Edit Invoice
                                            </a>
                                            <a href="?action=sent&id=<?= $row['id'] ?>" class="group flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-500/10 transition-colors">
                                                <i class="ph-bold ph-paper-plane-tilt text-base"></i> Mark Sent
                                            </a>
                                            <?php endif; ?>
                                            
                                            <button type="button" onclick="openPayModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['invoice_no']) ?>', <?= $grandTotal ?>)" class="w-full text-left group flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors">
                                                <i class="ph-bold ph-wallet text-base"></i> Add Payment / DP
                                            </button>
                                        </div>
                                        <div class="py-1 bg-slate-50/50 dark:bg-slate-800/30">
                                            <a href="?action=cancel&id=<?= $row['id'] ?>" onclick="return confirm('Batalkan Invoice ini?')" class="group flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                                                <i class="ph-bold ph-x-circle text-base"></i> Cancel Invoice
                                            </a>
                                        </div>
                                        <?php endif; ?>

                                        <?php if($user_role == 'admin'): ?>
                                        <div class="py-1 bg-slate-50/50 dark:bg-slate-800/30">
                                            <a href="?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Hapus permanen? Data tidak dapat dikembalikan.')" class="group flex items-center gap-2.5 px-4 py-2 text-[11px] font-black text-rose-600 hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:bg-rose-600 dark:hover:text-white transition-colors">
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
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-receipt text-5xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Invoice tidak ditemukan dengan filter pencarian saat ini.</p>
                                    <?php if(!empty($search) || !empty($f_client) || !empty($f_status) || !empty($f_tax) || !empty($f_start_date)): ?>
                                        <a href="invoice_list.php" class="mt-5 inline-flex items-center gap-2 bg-slate-800 dark:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors shadow-sm">
                                            <i class="ph-bold ph-arrows-counter-clockwise"></i> Reset Filter
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($res->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-center mt-[-80px] relative z-10">
            <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest bg-white dark:bg-[#1A222C] px-5 py-2 rounded-full border border-slate-100 dark:border-slate-700 shadow-sm">
                Menampilkan Total <span class="text-indigo-600 dark:text-indigo-400 font-black mx-1"><?= $res->num_rows ?></span> Invoice
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="noteModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('noteModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-lg transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="text-sm font-black text-slate-800 dark:text-white flex items-center gap-2"><i class="ph-fill ph-notepad text-amber-500 text-xl"></i> Internal Notes</h3>
            <button onclick="closeModal('noteModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-200/50 hover:bg-rose-100 text-slate-500 hover:text-rose-600 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-rose-500/20 dark:hover:text-rose-400 transition-colors">
                <i class="ph-bold ph-x text-lg"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="noteInvoiceNo">
            <input type="text" id="noteTitle" class="w-full mb-4 px-4 py-2.5 bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold font-mono outline-none cursor-not-allowed text-center shadow-inner" readonly>
            <textarea id="generalNotes" class="w-full px-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/50 transition-all resize-none shadow-sm" rows="6" placeholder="Tulis catatan internal, riwayat interaksi klien, atau log dokumen di sini..."></textarea>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50 shrink-0">
            <span id="saveStatus" class="text-xs font-bold text-emerald-500 uppercase tracking-widest hidden flex items-center gap-1.5"><i class="ph-fill ph-check-circle text-base"></i> Saved!</span>
            <div class="ml-auto flex gap-3">
                <button onclick="closeModal('noteModal')" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-600">Batal</button>
                <button onclick="saveNote()" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-amber-500 hover:bg-amber-600 transition-colors shadow-md shadow-amber-500/30 active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-floppy-disk text-lg"></i> Simpan Catatan
                </button>
            </div>
        </div>
    </div>
</div>

<div id="payModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('payModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        <form method="POST" enctype="multipart/form-data" onsubmit="return validatePayment()">
            <div class="px-6 py-5 border-b border-emerald-500/20 bg-emerald-500 flex justify-between items-center text-white">
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-wallet text-xl"></i> Input Pembayaran</h3>
                <button type="button" onclick="closeModal('payModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto max-h-[80vh] modern-scrollbar">
                <input type="hidden" name="invoice_id" id="modal_inv_id">
                <input type="hidden" name="grand_total_system" id="modal_grand_total">
                
                <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-2xl p-4 text-center mb-6 shadow-sm">
                    <p id="modal_inv_no" class="text-sm font-bold text-emerald-800 dark:text-emerald-400 font-mono mb-1 tracking-widest"></p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest font-bold">Total Tagihan Sistem</p>
                    <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">Rp <span id="display_total"></span></p>
                </div>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Tanggal Bayar <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="ph-bold ph-calendar absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="date" name="payment_date" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Nominal (DP / Lunas) <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400 group-focus-within:text-emerald-500 transition-colors">Rp</span>
                            <input type="number" name="amount" id="input_amount" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-lg font-black text-emerald-600 dark:text-emerald-400 focus:ring-2 focus:ring-emerald-500/50 outline-none transition-all shadow-inner placeholder-slate-400" required placeholder="0">
                        </div>
                        <p id="err_msg" class="text-[10px] font-bold text-rose-500 mt-2 hidden flex items-center gap-1"><i class="ph-fill ph-warning-circle text-sm"></i> Peringatan: Nominal melebih total tagihan!</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Catatan (Opsional)</label>
                        <textarea name="payment_notes" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:ring-2 focus:ring-emerald-500/50 resize-none transition-all shadow-inner placeholder-slate-400" rows="2" placeholder="e.g. Termin 1 (50%), Pelunasan..."></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Bukti Transfer <span class="text-rose-500">*</span></label>
                        <input type="file" name="proof_file" class="w-full block text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:uppercase file:tracking-widest file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-500/10 dark:file:text-emerald-400 dark:hover:file:bg-emerald-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 transition-all" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex bg-slate-50/50 dark:bg-slate-800/50 justify-end gap-3 shrink-0">
                <button type="button" onclick="closeModal('payModal')" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-sm shadow-sm">Batal</button>
                <button type="submit" name="confirm_payment" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition-colors shadow-md shadow-emerald-500/30 flex items-center justify-center gap-2 active:scale-95">
                    <i class="ph-bold ph-floppy-disk text-lg"></i> Simpan Pembayaran
                </button>
            </div>
        </form>
    </div>
</div>

<div id="taxModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('taxModal')"></div>
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-5 border-b border-sky-500/20 bg-sky-500 flex justify-between items-center text-white">
                <h3 class="text-base font-black flex items-center gap-2"><i class="ph-bold ph-file-arrow-up text-xl"></i> Upload Faktur Pajak</h3>
                <button type="button" onclick="closeModal('taxModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-5">
                <input type="hidden" name="tax_invoice_id" id="tax_invoice_id">
                
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">No Invoice</label>
                    <input type="text" id="tax_inv_no" class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold font-mono text-slate-600 dark:text-slate-400 outline-none cursor-not-allowed shadow-inner" readonly>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">File Faktur Pajak <span class="text-rose-500">*</span></label>
                    <input type="file" name="tax_file" class="w-full block text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:uppercase file:tracking-widest file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:file:bg-sky-500/10 dark:file:text-sky-400 dark:hover:file:bg-sky-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 transition-all" accept=".jpg,.jpeg,.png,.pdf" required>
                    <p class="text-[10px] font-medium text-slate-400 mt-2 italic flex items-center gap-1"><i class="ph-fill ph-info"></i> Format didukung: PDF, JPG, PNG.</p>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex bg-slate-50/50 dark:bg-slate-800/50 justify-end gap-3 shrink-0">
                <button type="button" onclick="closeModal('taxModal')" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-sm shadow-sm">Batal</button>
                <button type="submit" name="upload_tax_invoice" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-sky-500 hover:bg-sky-600 transition-colors shadow-md shadow-sky-500/30 flex items-center justify-center gap-2 active:scale-95">
                    <i class="ph-bold ph-upload-simple text-lg"></i> Upload Dokumen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- FILTER COLLAPSE LOGIC ---
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

    // --- CUSTOM DROPDOWN MENU LOGIC (Fix Propagation) ---
    let currentOpenDropdown = null;
    
    function toggleActionMenu(e, id) {
        e.stopPropagation(); // Mencegah klik menyebar dan menutup kembali
        const menu = document.getElementById('action-menu-' + id);
        
        if (currentOpenDropdown && currentOpenDropdown !== menu) {
            currentOpenDropdown.classList.add('hidden');
        }
        
        menu.classList.toggle('hidden');
        currentOpenDropdown = menu.classList.contains('hidden') ? null : menu;
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (currentOpenDropdown && !currentOpenDropdown.contains(e.target)) {
            currentOpenDropdown.classList.add('hidden');
            currentOpenDropdown = null;
        }
    });

    // --- CUSTOM MODAL HANDLERS ---
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

    // --- AJAX & FORM LOGIC ---
    let systemTotal = 0;

    function openNoteModal(invoiceNo) {
        document.getElementById('noteInvoiceNo').value = invoiceNo;
        document.getElementById('noteTitle').value = invoiceNo;
        document.getElementById('generalNotes').value = "Memuat catatan...";
        document.getElementById('saveStatus').classList.add('hidden');
        
        openModal('noteModal');

        const formData = new FormData();
        formData.append('action', 'load');
        formData.append('invoice_no', invoiceNo);
        fetch('ajax_scratchpad.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            document.getElementById('generalNotes').value = (res.status === 'success' && res.data) ? (res.data.general_notes || '') : '';
        }).catch(err => {
            document.getElementById('generalNotes').value = "";
        });
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
                
                if(notes.trim() !== "") {
                    btn.classList.remove('bg-slate-100', 'text-slate-400', 'dark:bg-slate-700/50', 'dark:border-slate-600', 'dark:text-slate-500');
                    btn.classList.add('bg-amber-100', 'text-amber-600', 'dark:bg-amber-500/20', 'dark:text-amber-400', 'border-transparent');
                    btn.querySelector('i').classList.replace('ph-bold', 'ph-fill');
                } else {
                    btn.classList.add('bg-slate-100', 'text-slate-400', 'dark:bg-slate-700/50', 'dark:border-slate-600', 'dark:text-slate-500');
                    btn.classList.remove('bg-amber-100', 'text-amber-600', 'dark:bg-amber-500/20', 'dark:text-amber-400', 'border-transparent');
                    btn.querySelector('i').classList.replace('ph-fill', 'ph-bold');
                }

                setTimeout(() => { 
                    s.classList.add('hidden'); 
                    closeModal('noteModal'); 
                }, 1000);
            } else {
                alert('Gagal menyimpan catatan: ' + res.message);
            }
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

    function validatePayment() {
        let inputVal = parseFloat(document.getElementById('input_amount').value);
        if (isNaN(inputVal) || inputVal > (systemTotal + 100)) { 
            document.getElementById('err_msg').classList.remove('hidden');
            return false;
        }
        return true;
    }

    function openTaxModal(id, no) {
        document.getElementById('tax_invoice_id').value = id;
        document.getElementById('tax_inv_no').value = no;
        openModal('taxModal');
    }
</script>

<?php include 'includes/footer.php'; ?>