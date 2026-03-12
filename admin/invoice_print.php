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
while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];

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

// B. SETTING PAYMENT DETAILS & NOTE (Format Array agar bisa dirender ke tabel sejajar)
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
    <title>Invoice <?= $inv['invoice_no'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; color: #000; -webkit-print-color-adjust: exact; }
        @page { margin: 1.5cm; size: A4; }

        .no-print { background: #f8f9fa; padding: 10px; text-align: center; border-bottom: 1px solid #ddd; }
        .btn-print { background: #0d6efd; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        [contenteditable="true"]:hover { background-color: #fffdd0; outline: 1px dashed #999; cursor: text; }

        .header-table { width: 100%; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .company-addr { font-size: 10px; color: #333; max-width: 300px; line-height: 1.3; }
        .doc-title { text-align: right; font-size: 24px; font-weight: bold; text-transform: uppercase; padding-top: 20px; }

        .info-wrapper { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; border: 1px solid #000; }
        .info-box { width: 48%; border: 1px solid #000; padding: 10px; vertical-align: top; height: 160px; }
        .inner-table { width: 100%; font-size: 11px; }
        .inner-table td { padding-bottom: 3px; vertical-align: top; }
        .lbl { width: 90px; font-weight: bold; } 
        .sep { width: 10px; text-align: center; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        .items-table th { border: 1px solid #000; background-color: #f2f2f2; padding: 8px; text-align: center; font-weight: bold; }
        .items-table td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .summary-row td { border: 1px solid #000; padding: 8px; }
        .label-cell { background-color: #fff; font-weight: bold; text-align: right; }
        .value-cell { text-align: right; font-weight: bold; }
        .border-none { border: none !important; }

        .footer-layout { width: 100%; margin-top: 20px; page-break-inside: avoid; }
        .footer-left { width: 60%; vertical-align: top; padding-right: 20px; }
        .footer-right { width: 40%; vertical-align: top; text-align: center; padding-top: 20px; }
        
        .sign-company { font-size: 11px; margin-bottom: 10px; }
        .sign-img { display: block; margin: 10px auto; width: auto; height: auto; max-width: 250px; max-height: 120px; object-fit: contain; }
        .sign-name { font-weight: bold; text-decoration: underline; }
        .no-sign-box { height: 100px; line-height:100px; color:#ccc; border:1px dashed #ccc; margin:10px auto; width:150px; font-size: 10px; }

        @media print {
            .no-print { display: none; }
            [contenteditable="true"]:hover { background: none; outline: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
        <div style="margin-top:5px; color:red; font-size:11px;">
            * Mode: <strong><?= $inv_type ?></strong> (Currency: <?= $inv['currency'] ?>)<br>
            * Adjustment (DP, Fee, dll) akan muncul di atas Total.
        </div>
    </div>

    <table class="header-table">
        <tr>
            <td>
                <img src="../uploads/<?= $sets['company_logo'] ?>" class="logo" onerror="this.style.display='none'">
                <div class="company-addr"><?= nl2br(htmlspecialchars($sets['company_address_full'])) ?></div>
            </td>
            <td align="right" valign="top"><div class="doc-title">INVOICE</div></td>
        </tr>
    </table>

    <table class="info-wrapper">
        <tr>
            <td class="info-box border-right">
                <table class="inner-table">
                    <tr><td class="lbl">To</td><td class="sep">:</td><td><strong><?= htmlspecialchars($inv['company_name']) ?></strong></td></tr>
                    <tr><td class="lbl">Address</td><td class="sep">:</td><td><?= nl2br(htmlspecialchars($inv['c_address'])) ?></td></tr>
                    <tr><td class="lbl">Attention</td><td class="sep">:</td><td><?= htmlspecialchars($inv['pic_name']) ?> <br> <?= htmlspecialchars($inv['pic_phone']) ?></td></tr>
                </table>
            </td>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="lbl">Invoice Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td></tr>
                    <tr><td class="lbl">Due Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($inv['due_date'])) ?></td></tr>
                    <tr><td class="lbl">Invoice No</td><td class="sep">:</td><td><strong><?= $inv['invoice_no'] ?></strong></td></tr>
                    <tr><td class="lbl">PO. Reference</td><td class="sep">:</td><td><?= $inv['po_number_client'] ?></td></tr>
                    <tr><td class="lbl">Currency</td><td class="sep">:</td><td><?= $inv['currency'] ?></td></tr>
                    <tr><td colspan="3" style="height:5px"></td></tr>
                    <tr><td class="lbl">Contact Person</td><td class="sep">:</td><td><?= $inv['sales_name'] ?></td></tr>
                    <tr><td class="lbl">Email</td><td class="sep">:</td><td><?= $inv['sales_email'] ?></td></tr>
                    <tr><td class="lbl">Phone</td><td class="sep">:</td><td><?= $inv['sales_phone'] ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Description</th>
                <th width="8%">Qty</th>
                <th width="17%">Payment Method</th>
                <th width="15%">Unit Price (<?= $inv['currency'] ?>)</th>
                <th width="20%">Total (<?= $inv['currency'] ?>)</th>
            </tr>
        </thead>
        <tbody>
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
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                    <div contenteditable="true">
                        <?= htmlspecialchars($item['item_name']) ?>
                        <?php if(!empty($item['description']) && $item['description'] != 'Exclude Tax'): ?>
                            <br><small class="text-muted"><?= nl2br(htmlspecialchars($item['description'])) ?></small>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="text-center" contenteditable="true"><?= $qty ?></td> 
                <td class="text-center" contenteditable="true"><?= htmlspecialchars($payMethod) ?></td>
                <td class="text-right" contenteditable="true"><?= format_money($price, $is_international) ?></td>
                <td class="text-right" contenteditable="true"><?= format_money($lineTotal, $is_international) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php 
                // Kalkulasi Dasar (Subtotal + VAT saja)
                if ($is_international) {
                    $vatAmount = 0;
                    $grandTotal = round($grandTotal, 2); 
                } else {
                    $grandTotal = round($grandTotal, 0, PHP_ROUND_HALF_DOWN); 
                    $vatAmount = round($grandTotal * $tax_rate, 0, PHP_ROUND_HALF_DOWN); 
                }
                
                $totalInvoice = $grandTotal + $vatAmount;
                
                // MENGUBAH TOTAL INVOICE MENJADI TERBILANG
                $currency_text = $is_international ? ($inv['currency'] == 'USD' ? "US Dollars" : $inv['currency']) : "Rupiah";
                $amountInWords = ucwords(strtolower(getSpelledOutNumber($totalInvoice))) . " " . $currency_text;
            ?>
            
            <tr class="summary-row" id="row-subtotal">
                <td colspan="4" class="border-none"></td>
                <td class="label-cell" <?= $can_edit_note ?>>
                    <?php if($can_edit_note != ''): ?>
                        <span class="no-print" style="color:red; cursor:pointer; float:left; margin-right:5px; font-size:10px;" onclick="document.getElementById('row-subtotal').style.display='none'" title="Hapus Baris Sub Total">✖</span>
                    <?php endif; ?>
                    Sub Total
                </td>
                <td class="value-cell" <?= $can_edit_note ?>><?= format_money($grandTotal, $is_international) ?></td>
            </tr>
            
            <?php if(!$is_international): ?>
            <tr class="summary-row">
                <td colspan="4" class="border-none"></td>
                <td class="label-cell">VAT (11%)</td>
                <td class="value-cell" contenteditable="true"><?= format_money($vatAmount, $is_international) ?></td>
            </tr>
            <?php endif; ?>

            <?php foreach($adjData as $adj): ?>
            <tr class="summary-row">
                <td colspan="4" class="border-none"></td>
                <td class="label-cell text-muted" contenteditable="true"><?= htmlspecialchars($adj['label']) ?></td>
                <td class="value-cell text-muted" contenteditable="true"><?= format_money($adj['amount'], $is_international) ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="summary-row">
                <td colspan="4" class="border-none"></td>
                <td class="label-cell" <?= $can_edit_note ?>>Total</td>
                <td class="value-cell" contenteditable="true"><?= format_money($totalInvoice, $is_international) ?></td>
            </tr>

        </tbody>
    </table>

    <table class="footer-layout">
        <tr>
            <td class="footer-left">
                
                <div style="font-size: 11px; margin-bottom: 15px;">
                    <span style="font-weight: bold;">Amount in words :</span><br>
                    <div <?= $can_edit_note ?> style="font-style: italic; margin-top: 3px; line-height: 1.4; max-width: 90%;">
                        <?= htmlspecialchars($amountInWords) ?>
                    </div>
                </div>

                <div style="font-style: italic; font-size: 10px; margin-bottom: 20px;">
                    <strong>Note :</strong><br>
                    <?php if($is_international): ?>
                        <div <?= $can_edit_note ?> style="margin-bottom:5px; color:#000;">
                            <?= $special_note_usd ?>
                        </div>
                    <?php else: ?>
                        <div <?= $can_edit_note ?> style="margin-bottom:5px; color:#000;">
                            <?= nl2br(htmlspecialchars($final_note)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="font-size: 11px;">
                    <span style="font-weight: bold; margin-bottom: 2px; display: block;"><?= $payment_title ?></span>
                    <table style="width: 100%; font-size: 11px; line-height: 1.4; border-collapse: collapse;" <?= $can_edit_note ?>>
                        <?php foreach($payment_details as $label => $value): ?>
                        <tr>
                            <td style="width: 90px; vertical-align: top; padding-bottom: 2px;"><?= htmlspecialchars($label) ?></td>
                            <td style="width: 15px; vertical-align: top; text-align: center; padding-bottom: 2px;">:</td>
                            <td style="vertical-align: top; padding-bottom: 2px;"><?= htmlspecialchars($value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </td>

            <td class="footer-right">
                <div class="sign-company">PT. Linksfield Networks Indonesia</div>
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
                <?php if (!empty($signPath)): ?>
                    <img src="<?= $signPath ?>" class="sign-img">
                <?php else: ?>
                    <div class="no-sign-box"><span style="font-size:9px; color:red;">(Signature Not Found)</span></div>
                <?php endif; ?>
                <div class="sign-name" contenteditable="true"><?= htmlspecialchars($signerName) ?></div>
            </td>
        </tr>
    </table>
</body>
</html>