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

// 3. [BARU] AMBIL ADJUSTMENTS (Multiple Rows)
$adjData = [];
// Cek tabel exist dulu jaga-jaga
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

// --- LOGIKA TIPE INVOICE (DOMESTIC / INTERNATIONAL) ---
$inv_type = isset($inv['invoice_type']) ? $inv['invoice_type'] : 'Domestic'; 
$is_international = ($inv_type == 'International');

// A. SETTING PAJAK
$tax_rate = $is_international ? 0 : 0.11;

// B. SETTING PAYMENT DETAILS & NOTE
if ($is_international) {
    // USD
    $payment_title = "Payment Method (USD)";
    $special_note_usd = "Please note that the payer is responsible for any bank charges incurred in preparing bank transfers.";
    
    $payment_details = "Banking Nation : Indonesia\n" .
                       "Bank Name : PT. Bank Central Asia (BCA)\n" .
                       "Bank Address : Jl. M. H. Thamrin No. 1 Kec. Menteng, Kota Jakarta Pusat, DKI Jakarta\n" .
                       "SWIFT CODE : CENAIDJAXXX\n" .
                       "Acc No : 2060802761\n" .
                       "Acc Name : PT Linksfield Networks Indonesia\n" .
                       "Settlement Currency : USD";
} else {
    // IDR
    $payment_title = "Payment Method (IDR)";
    
    // Note Default dari Database
    $final_note = isset($sets['invoice_note_default']) ? $sets['invoice_note_default'] : '';
    
    $special_note_usd = ""; 
    
    $payment_details = "Acc Name : PT. LINKSFIELD NETWORKS INDONESIA\n" .
                       "Bank Name : BCA (Bank Central Asia)\n" .
                       "Acc No : 2060752705\n" .
                       "SWIFT CODE : CENAIDJA\n" .
                       "Bank Address : Jl. M. H. Thamrin No. 1 Kec. Menteng";
}

// FORMAT ANGKA
function format_money($num, $is_intl) {
    if ($is_intl) {
        return number_format((float)$num, 2, '.', ','); 
    } else {
        return number_format((float)$num, 0, ',', '.');
    }
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
            * Adjustment (DP, Fee, dll) akan muncul otomatis jika sudah diinput.
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
                    <tr><td class="lbl">Attn.</td><td class="sep">:</td><td><?= htmlspecialchars($inv['pic_name']) ?> <br> <?= htmlspecialchars($inv['pic_phone']) ?></td></tr>
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
                    <tr><td class="lbl">Contact</td><td class="sep">:</td><td><?= $inv['sales_name'] ?></td></tr>
                    <tr><td class="lbl">Email</td><td class="sep">:</td><td><?= $inv['sales_email'] ?></td></tr>
                    <tr><td class="lbl">Tel</td><td class="sep">:</td><td><?= $inv['sales_phone'] ?></td></tr>
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
                // PERHITUNGAN TOTAL DENGAN ADJUSTMENT
                if ($is_international) {
                    $vatAmount = 0;
                    $grandTotal = round($grandTotal, 2); 
                } else {
                    $grandTotal = round($grandTotal, 0, PHP_ROUND_HALF_DOWN); 
                    $vatAmount = round($grandTotal * $tax_rate, 0, PHP_ROUND_HALF_DOWN); 
                }
                
                $totalAll = $grandTotal + $vatAmount;
                
                // Tambahkan Adjustment ke Kalkulasi Total
                $totalAdj = 0;
                foreach ($adjData as $adj) {
                    $totalAdj += floatval($adj['amount']);
                }
                $totalAll += $totalAdj;
            ?>
            
            <tr class="summary-row">
                <td colspan="4" class="border-none"></td>
                <td class="label-cell">Sub Total</td>
                <td class="value-cell" contenteditable="true"><?= format_money($grandTotal, $is_international) ?></td>
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
                <td class="label-cell" contenteditable="true"><?= htmlspecialchars($adj['label']) ?></td>
                <td class="value-cell" contenteditable="true"><?= format_money($adj['amount'], $is_international) ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="summary-row">
                <td colspan="4" class="border-none"></td>
                <td class="label-cell">Total</td>
                <td class="value-cell" contenteditable="true"><?= format_money($totalAll, $is_international) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="footer-layout">
        <tr>
            <td class="footer-left">
                <div style="font-style: italic; font-size: 10px; margin-bottom: 20px;">
                    <strong>Note :</strong><br>
                    <?php if($is_international): ?>
                        <div style="margin-bottom:5px; color:#000;">
                            <?= $special_note_usd ?>
                        </div>
                    <?php else: ?>
                        <div contenteditable="true" style="margin-bottom:5px; color:#000;">
                            <?= nl2br(htmlspecialchars($final_note)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="font-size: 11px;">
                    <span style="font-weight: bold; margin-bottom: 5px; display: block;"><?= $payment_title ?></span>
                    <div style="line-height: 1.5; white-space: pre-line;" contenteditable="true">
                        <?= htmlspecialchars($payment_details) ?>
                    </div>
                </div>
            </td>

            <td class="footer-right">
                <div class="sign-company">PT. Linksfield Networks Indonesia</div>
                
                <?php 
                    // LOGIKA SIGNATURE (Tetap dipertahankan)
                    $signPath = '';
                    $signerName = 'Niawati'; 
                    $baseDir = dirname(__DIR__);

                    $sqlNia = "SELECT id, username, signature_file FROM users WHERE username LIKE '%Niawati%' OR email LIKE '%nia@%' LIMIT 1";
                    $resNia = $conn->query($sqlNia);
                    $nia = $resNia->fetch_assoc();

                    if ($nia) {
                        $signerName = $nia['username'];
                        $niaId = $nia['id'];
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