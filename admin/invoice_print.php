<?php
include '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$id = intval($_GET['id']);

// 1. AMBIL DATA HEADER INVOICE
$sql = "SELECT i.*, q.po_number_client, q.currency, q.remarks,
               c.company_name, c.address as c_address, c.pic_name, c.pic_phone,
               u.username as sales_name, u.email as sales_email, u.phone as sales_phone, 
               u.signature_file as sales_sign 
        FROM invoices i
        JOIN quotations q ON i.quotation_id = q.id
        JOIN clients c ON q.client_id = c.id
        JOIN users u ON i.created_by_user_id = u.id
        WHERE i.id = $id";
$inv = $conn->query($sql)->fetch_assoc();
if(!$inv) die("Invoice not found");

// 2. AMBIL ITEM (Prioritas: Invoice Items > Quotation)
$itemsData = [];
$sql_inv_items = "SELECT item_name, qty, unit_price, card_type, description FROM invoice_items WHERE invoice_id = $id";
$check_items = $conn->query($sql_inv_items);

if ($check_items && $check_items->num_rows > 0) {
    while($row = $check_items->fetch_assoc()) { $itemsData[] = $row; }
} else {
    $resQ = $conn->query("SELECT item_name, qty, unit_price, card_type, description FROM quotation_items WHERE quotation_id = " . $inv['quotation_id']);
    while($row = $resQ->fetch_assoc()) { $itemsData[] = $row; }
}

// 3. AMBIL ADJUSTMENTS (Data Tambahan: DP, Fee, dll)
$adjData = [];
$checkTable = $conn->query("SHOW TABLES LIKE 'invoice_adjustments'");
if ($checkTable && $checkTable->num_rows > 0) {
    $resAdj = $conn->query("SELECT * FROM invoice_adjustments WHERE invoice_id = $id");
    if ($resAdj) {
        while($row = $resAdj->fetch_assoc()) { $adjData[] = $row; }
    }
}

// 4. AMBIL SETTINGS
$sets = [];
$res = $conn->query("SELECT * FROM settings");
if($res) {
    while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];
}

// 5. CEK PERMISSION UNTUK EDIT NOTE & TOTAL (Hanya Admin & Divisi Finance)
$user_id_session = $_SESSION['user_id'];
$user_role_session = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : 'standard';
$is_finance = false;

$cek_div = $conn->query("SELECT d.name FROM users u LEFT JOIN divisions d ON u.division_id = d.id WHERE u.id = $user_id_session");
if ($cek_div && $cek_div->num_rows > 0) {
    $row_div = $cek_div->fetch_assoc();
    if (!empty($row_div['name']) && stripos($row_div['name'], 'finance') !== false) {
        $is_finance = true;
    }
}

// Berikan hak 'contenteditable' jika user adalah Admin atau divisi Finance
$can_edit_note = ($user_role_session === 'admin' || $is_finance) ? 'contenteditable="true"' : '';


// --- LOGIKA TIPE INVOICE (DOMESTIC / INTERNATIONAL) ---
$inv_type = isset($inv['invoice_type']) ? $inv['invoice_type'] : 'Domestic'; 
$is_international = ($inv_type == 'International');

// A. SETTING PAJAK
$tax_rate = $is_international ? 0 : 0.11;

// B. SETTING PAYMENT DETAILS & NOTE
if ($is_international) {
    $payment_title = "Payment Method (USD)";
    $special_note_usd = "Please note that the payer is responsible for any bank charges incurred in preparing bank transfers.";
    $payment_details = [
        "Banking Nation"      => "Indonesia",
        "Bank Name"           => "PT. Bank Central Asia (BCA)",
        "Bank Address"        => "Jl. M. H. Thamrin No. 1 Kec. Menteng, Kota Jakarta Pusat, DKI Jakarta",
        "SWIFT CODE"          => "CENAIDJAXXX",
        "Acc No"              => "2060802761",
        "Acc Name"            => "PT Linksfield Networks Indonesia",
        "Settlement Currency" => "USD"
    ];
} else {
    $payment_title = "Payment Method (IDR)";
    $final_note = isset($sets['invoice_note_default']) ? $sets['invoice_note_default'] : '';
    $special_note_usd = ""; 
    $payment_details = [
        "Acc Name"     => "PT. LINKSFIELD NETWORKS INDONESIA",
        "Bank Name"    => "BCA (Bank Central Asia)",
        "Acc No"       => "2060752705",
        "SWIFT CODE"   => "CENAIDJA",
        "Bank Address" => "Jl. M. H. Thamrin No. 1 Kec. Menteng"
    ];
}

// FORMAT ANGKA
function format_money($num, $is_intl) {
    if ($is_intl) {
        return number_format((float)$num, 2, '.', ','); 
    } else {
        return number_format((float)$num, 0, ',', '.');
    }
}

// FUNGSI KONVERSI ANGKA KE HURUF (TERBILANG BAHASA INGGRIS)
function getSpelledOutNumber($number) {
    $number = str_replace(',', '', $number);
    $hyphen      = '-';
    $conjunction = ' ';
    $separator   = ' ';
    $negative    = 'Negative ';
    $dictionary  = array(
        0                   => 'Zero',
        1                   => 'One',
        2                   => 'Two',
        3                   => 'Three',
        4                   => 'Four',
        5                   => 'Five',
        6                   => 'Six',
        7                   => 'Seven',
        8                   => 'Eight',
        9                   => 'Nine',
        10                  => 'Ten',
        11                  => 'Eleven',
        12                  => 'Twelve',
        13                  => 'Thirteen',
        14                  => 'Fourteen',
        15                  => 'Fifteen',
        16                  => 'Sixteen',
        17                  => 'Seventeen',
        18                  => 'Eighteen',
        19                  => 'Nineteen',
        20                  => 'Twenty',
        30                  => 'Thirty',
        40                  => 'Forty',
        50                  => 'Fifty',
        60                  => 'Sixty',
        70                  => 'Seventy',
        80                  => 'Eighty',
        90                  => 'Ninety',
        100                 => 'Hundred',
        1000                => 'Thousand',
        1000000             => 'Million',
        1000000000          => 'Billion',
        1000000000000       => 'Trillion'
    );

    if (!is_numeric($number)) return false;
    if ($number < 0) return $negative . getSpelledOutNumber(abs($number));
    
    $string = $fraction = null;
    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[(int)$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) $string .= $hyphen . $dictionary[$units];
            break;
        case $number < 1000:
            $hundreds  = floor($number / 100);
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) $string .= $conjunction . getSpelledOutNumber($remainder);
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = fmod($number, $baseUnit);
            $string = getSpelledOutNumber($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= getSpelledOutNumber($remainder);
            }
            break;
    }

    if (null !== $fraction && is_numeric($fraction)) {
        $fraction = substr($fraction, 0, 2);
        if((int)$fraction > 0) {
            $string .= ' and ' . getSpelledOutNumber((int)$fraction) . ' Cents';
        }
    }
    return $string;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $inv['invoice_no'] ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact; 
            background-color: #f1f5f9; 
            color: #1e293b; 
        }
        
        @page { 
            size: A4 portrait; 
            margin: 0; 
        }
        
        @media print {
            body { background-color: #ffffff; }
            .no-print { display: none !important; }
            .print-container { 
                box-shadow: none !important; 
                margin: 0 !important; 
                padding: 10mm 15mm !important; 
                width: 100% !important;
                max-width: 100% !important;
                height: 100vh !important; 
                display: flex;
                flex-direction: column;
            }
            
            table { page-break-inside: auto; }
            tr    { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            
            .avoid-break { page-break-inside: avoid; }
            
            [contenteditable="true"]:hover, [contenteditable="true"]:focus { 
                background: transparent !important; 
                box-shadow: none !important; 
                outline: none !important; 
                border: none !important; 
            }
        }

        [contenteditable="true"] { transition: all 0.2s; outline: none; }
        [contenteditable="true"]:hover { background-color: #fef08a; box-shadow: 0 0 0 4px #fef08a; border-radius: 2px; cursor: text; }
        [contenteditable="true"]:focus { background-color: #fef08a; box-shadow: 0 0 0 4px #fef08a; border-radius: 2px; cursor: text; border-bottom: 2px dashed #eab308; }
        
    </style>
</head>
<body class="py-8 print:py-0 text-[11px]">

    <div class="no-print fixed bottom-8 right-8 flex flex-col items-end gap-3 z-50">
        <div class="bg-white px-5 py-4 rounded-2xl shadow-xl border border-slate-200 max-w-sm text-right animate-bounce">
            <p class="text-xs font-bold text-emerald-600 mb-1 flex items-center justify-end gap-1.5"><i class="ph-fill ph-info"></i> Edit Mode Active</p>
            <p class="text-[10px] text-slate-500 mb-2">Klik area teks (Alamat, Harga, Adjusment, Catatan) untuk <strong>edit manual</strong>.</p>
            <div class="inline-flex gap-2 text-[9px] font-bold uppercase tracking-widest bg-slate-100 px-2 py-1 rounded text-slate-500">
                <i class="ph-bold ph-globe"></i> Mode: <?= $inv_type ?> (<?= $inv['currency'] ?>)
            </div>
        </div>
        <button onclick="window.print()" class="group flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-8 rounded-full shadow-lg shadow-emerald-600/40 transition-all hover:scale-105 active:scale-95">
            <i class="ph-bold ph-printer text-2xl group-hover:animate-pulse"></i> 
            <span class="text-lg">Print Invoice</span>
        </button>
    </div>

    <div class="print-container bg-white w-full max-w-[210mm] min-h-[297mm] mx-auto p-10 shadow-2xl rounded flex flex-col">
        
        <div class="flex justify-between items-center border-b-[3px] border-slate-800 pb-4 mb-5 shrink-0">
            <div class="w-1/3">
                <img src="../uploads/<?= $sets['company_logo'] ?? 'default-logo.png' ?>" class="max-h-12 object-contain" onerror="this.style.display='none'">
            </div>
            <div class="w-1/3 text-center">
                <h1 class="text-xl font-black tracking-widest text-slate-900 uppercase">INVOICE</h1>
            </div>
            <div class="w-1/3 text-right">
                <div class="text-[9px] text-slate-600 leading-snug font-medium text-right ml-auto max-w-[200px]">
                    <?= nl2br(htmlspecialchars($sets['company_address_full'] ?? '')) ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-5 shrink-0">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <h3 class="text-[9px] font-black uppercase tracking-widest text-emerald-600 mb-2 flex items-center gap-1.5"><i class="ph-fill ph-buildings text-sm"></i> Billed To</h3>
                <div class="font-black text-slate-800 text-xs mb-1" <?= $can_edit_note ?>><?= htmlspecialchars($inv['company_name']) ?></div>
                <div class="text-[10px] text-slate-600 leading-snug mb-2" <?= $can_edit_note ?>><?= nl2br(htmlspecialchars($inv['c_address'])) ?></div>
                
                <div class="border-t border-slate-200 pt-2">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Attention:</p>
                    <p class="text-[10px] font-bold text-slate-800 leading-tight" <?= $can_edit_note ?>><?= htmlspecialchars($inv['pic_name']) ?></p>
                    <p class="text-[10px] text-slate-500 font-medium" <?= $can_edit_note ?>><?= htmlspecialchars($inv['pic_phone']) ?></p>
                </div>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <h3 class="text-[9px] font-black uppercase tracking-widest text-emerald-600 mb-2 flex items-center gap-1.5"><i class="ph-fill ph-receipt text-sm"></i> Invoice Details</h3>
                
                <table class="w-full text-[10px]">
                    <tbody>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium w-24">Invoice No</td>
                            <td class="py-0.5 font-bold text-slate-800 font-mono">#<?= $inv['invoice_no'] ?></td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium">Invoice Date</td>
                            <td class="py-0.5 font-bold text-slate-800" <?= $can_edit_note ?>><?= date('F d, Y', strtotime($inv['invoice_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium">Due Date</td>
                            <td class="py-0.5 font-bold text-rose-600" <?= $can_edit_note ?>><?= date('F d, Y', strtotime($inv['due_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium">PO. Reference</td>
                            <td class="py-0.5 font-bold text-slate-800" <?= $can_edit_note ?>><?= htmlspecialchars($inv['po_number_client'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium pb-1.5">Currency</td>
                            <td class="py-0.5 font-bold text-slate-800 border-b border-slate-200 pb-1.5">
                                <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded text-[9px] font-black tracking-widest"><?= $inv['currency'] ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium pt-1.5">Sales Person</td>
                            <td class="py-0.5 font-bold text-slate-800 pt-1.5" <?= $can_edit_note ?>><?= htmlspecialchars($inv['sales_name']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="border border-slate-800 rounded-lg overflow-hidden mb-4 shrink-0">
            <table class="w-full text-left text-[10px]">
                <thead class="bg-slate-800 text-white font-bold uppercase tracking-wider text-[9px]">
                    <tr>
                        <th class="py-2.5 px-3 text-center w-[5%]">No</th>
                        <th class="py-2.5 px-3 w-[40%]">Description</th>
                        <th class="py-2.5 px-3 text-center w-[8%]">Qty</th>
                        <th class="py-2.5 px-3 text-center w-[15%]">Pay Mode</th>
                        <th class="py-2.5 px-3 text-right w-[15%]">Unit Price</th>
                        <th class="py-2.5 px-3 text-right w-[17%]">Line Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php 
                    $no = 1; 
                    $grandTotal = 0;
                    
                    foreach($itemsData as $item): 
                        $qty = floatval($item['qty']); 
                        $price = floatval($item['unit_price']);
                        $lineTotal = $qty * $price;
                        $grandTotal += $lineTotal;
                        
                        $payMethod = !empty($inv['payment_method']) ? $inv['payment_method'] : (!empty($item['card_type']) ? $item['card_type'] : 'Prepaid');
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-2.5 px-3 text-center font-medium text-slate-500 align-middle"><?= $no++ ?></td>
                        <td class="py-2.5 px-3 align-middle">
                            <div class="font-bold text-slate-800" <?= $can_edit_note ?>><?= htmlspecialchars($item['item_name']) ?></div>
                            <?php if(!empty($item['description']) && $item['description'] != 'Exclude Tax'): ?>
                                <div class="text-[9px] text-slate-500 mt-0.5 leading-snug" <?= $can_edit_note ?>><?= nl2br(htmlspecialchars($item['description'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-2.5 px-3 text-center font-bold text-slate-800 align-middle" <?= $can_edit_note ?>><?= $qty ?></td> 
                        <td class="py-2.5 px-3 text-center align-middle">
                            <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-widest border border-slate-200" <?= $can_edit_note ?>>
                                <?= htmlspecialchars($payMethod) ?>
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-right font-medium text-slate-700 align-middle" <?= $can_edit_note ?>><?= format_money($price, $is_international) ?></td>
                        <td class="py-2.5 px-3 text-right font-bold text-slate-800 align-middle" <?= $can_edit_note ?>><?= format_money($lineTotal, $is_international) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr>
                        <td colspan="6" class="py-1"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php 
            if ($is_international) {
                $vatAmount = 0;
                $grandTotal = round($grandTotal, 2); 
            } else {
                $grandTotal = round($grandTotal, 0, PHP_ROUND_HALF_DOWN); 
                $vatAmount = round($grandTotal * $tax_rate, 0, PHP_ROUND_HALF_DOWN); 
            }
            
            $totalInvoice = $grandTotal + $vatAmount;
            
            foreach($adjData as $adj) {
                $totalInvoice += floatval($adj['amount']);
            }
            
            $currency_text = $is_international ? ($inv['currency'] == 'USD' ? "US Dollars" : $inv['currency']) : "Rupiah";
            $amountInWords = ucwords(strtolower(getSpelledOutNumber($totalInvoice))) . " " . $currency_text;
        ?>

        <div class="mt-auto flex flex-col gap-4 shrink-0 avoid-break">
            
            <div class="flex justify-end w-full">
                <div class="w-1/2 rounded-xl bg-slate-50 border border-slate-200 p-3">
                    <table class="w-full text-[10px]">
                        <tbody>
                            <tr id="row-subtotal">
                                <td class="py-1 text-slate-500 font-bold uppercase tracking-widest text-[9px]">
                                    <?php if($can_edit_note != ''): ?>
                                        <span contenteditable="false" class="no-print inline-block bg-rose-500 text-white rounded px-1.5 py-0.5 text-[8px] mr-1.5 cursor-pointer hover:bg-rose-600 transition-colors" onclick="document.getElementById('row-subtotal').style.display='none'" title="Klik untuk menyembunyikan baris Sub Total">✖ Hapus</span>
                                    <?php endif; ?>
                                    <span <?= $can_edit_note ?>>Sub Total</span>
                                </td>
                                <td class="py-1 text-right font-bold text-slate-800" <?= $can_edit_note ?>><?= format_money($grandTotal, $is_international) ?></td>
                            </tr>
                            
                            <?php if(!$is_international): ?>
                            <tr>
                                <td class="py-1 text-slate-500 font-bold uppercase tracking-widest text-[9px] border-b border-slate-200 pb-2">VAT (11%)</td>
                                <td class="py-1 text-right font-bold text-slate-800 border-b border-slate-200 pb-2" <?= $can_edit_note ?>><?= format_money($vatAmount, $is_international) ?></td>
                            </tr>
                            <?php endif; ?>

                            <?php foreach($adjData as $adj): ?>
                            <tr>
                                <td class="py-1 text-rose-500 font-bold uppercase tracking-widest text-[9px]" <?= $can_edit_note ?>><?= htmlspecialchars($adj['label']) ?></td>
                                <td class="py-1 text-right font-bold text-rose-600" <?= $can_edit_note ?>><?= format_money($adj['amount'], $is_international) ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <tr>
                                <td class="py-2 pt-2 text-emerald-600 font-black uppercase tracking-widest text-[11px] <?= empty($adjData) && $is_international ? 'border-t border-slate-200' : '' ?>">Total (<?= $inv['currency'] ?>)</td>
                                <td class="py-2 pt-2 text-right font-black text-emerald-600 text-sm <?= empty($adjData) && $is_international ? 'border-t border-slate-200' : '' ?>" <?= $can_edit_note ?>><?= format_money($totalInvoice, $is_international) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6 w-full">
                <div class="col-span-8 flex flex-col gap-3">
                    <div class="bg-emerald-50/50 border border-emerald-100 rounded-xl p-3">
                        <span class="text-[9px] font-black text-emerald-600 uppercase tracking-widest block mb-0.5">Amount in words:</span>
                        <div class="font-bold text-emerald-800 italic text-[10px] leading-snug" <?= $can_edit_note ?>><?= htmlspecialchars($amountInWords) ?></div>
                    </div>

                    <div>
                        <strong class="text-[9px] uppercase tracking-widest text-slate-500 block mb-0.5">Note:</strong>
                        <div class="text-[9px] text-slate-700 leading-relaxed bg-slate-50 p-2.5 rounded-lg border border-slate-200" <?= $can_edit_note ?>>
                            <?php if($is_international): ?>
                                <?= htmlspecialchars($special_note_usd) ?>
                            <?php else: ?>
                                <?= nl2br(htmlspecialchars($final_note)) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <strong class="text-[9px] uppercase tracking-widest text-slate-800 block border-b border-slate-800 pb-1 mb-1.5 w-max"><?= htmlspecialchars($payment_title) ?></strong>
                        <table class="w-full text-[9px] text-slate-700" <?= $can_edit_note ?>>
                            <?php foreach($payment_details as $label => $value): ?>
                            <tr>
                                <td class="py-0.5 font-bold w-24 align-top text-slate-600"><?= htmlspecialchars($label) ?></td>
                                <td class="py-0.5 w-3 align-top text-center">:</td>
                                <td class="py-0.5 align-top font-medium"><?= htmlspecialchars($value) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <div class="col-span-4 text-center flex flex-col justify-end pt-2">
                    <p class="text-[10px] font-bold text-slate-800 mb-1">PT. Linksfield Networks Indonesia</p>
                    
                    <?php 
                        $signPath = ''; $signerName = 'Niawati'; $baseDir = dirname(__DIR__);
                        $sqlNia = "SELECT id, username, signature_file FROM users WHERE username LIKE '%Niawati%' OR email LIKE '%nia@%' LIMIT 1";
                        $resNia = $conn->query($sqlNia);
                        $nia = $resNia->fetch_assoc();
                        if ($nia) {
                            $signerName = $nia['username']; $niaId = $nia['id'];
                            if (!empty($nia['signature_file']) && file_exists($baseDir . '/uploads/signatures/' . $nia['signature_file'])) {
                                $signPath = '../uploads/signatures/' . $nia['signature_file'];
                            } elseif (count(glob($baseDir . '/uploads/signatures/SIG_*_' . $niaId . '_*.png')) > 0) {
                                $files = glob($baseDir . '/uploads/signatures/SIG_*_' . $niaId . '_*.png');
                                $signPath = '../uploads/signatures/' . basename($files[0]);
                            }
                        }
                        if (empty($signPath) && file_exists($baseDir . '/assets/images/signature.png')) {
                            $signPath = '../assets/images/signature.png';
                        }
                    ?>
                    
                    <div class="h-20 flex items-center justify-center my-1 relative">
                        <?php if (!empty($signPath)): ?>
                            <img src="<?= $signPath ?>" class="max-h-full max-w-[160px] object-contain relative z-10 mix-blend-multiply">
                        <?php else: ?>
                            <div class="w-full h-16 border border-dashed border-slate-300 rounded-lg flex items-center justify-center text-[9px] font-bold text-slate-400 bg-slate-50">
                                (Signature Missing)
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="inline-block border-b border-slate-800 pb-1 px-4 mb-0.5 font-bold text-[11px]" <?= $can_edit_note ?>>
                        <?= htmlspecialchars($signerName) ?>
                    </div>
                    <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mt-0.5">Authorized Signature</p>
                </div>
            </div>
            
        </div>

    </div>

</body>
</html>