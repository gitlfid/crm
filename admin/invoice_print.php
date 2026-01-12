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

// 2. AMBIL ITEM
$sql_inv_items = "SELECT * FROM invoice_items WHERE invoice_id = $id";
$check_items = $conn->query($sql_inv_items);

if ($check_items && $check_items->num_rows > 0) {
    $items = $check_items;
} else {
    $items = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = " . $inv['quotation_id']);
}

// 3. AMBIL SETTINGS
$sets = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];

// --- LOGIKA MATA UANG & PEMBULATAN ---
$is_usd = ($inv['currency'] == 'USD');
$tax_rate = $is_usd ? 0 : 0.11;

// Info Bank
if ($is_usd) {
    $payment_details = "Banking Nation : Indonesia\n" .
                       "Bank Name : PT. Bank Central Asia (BCA)\n" .
                       "Bank Address : Jl. M. H. Thamrin No. 1 Kec. Menteng, Kota Jakarta Pusat, DKI Jakarta\n" .
                       "SWIFT CODE : CENAIDJAXXX\n" .
                       "Acc No : 2060802761\n" .
                       "Acc Name : PT Linksfield Networks Indonesia\n" .
                       "Settlement Currency : USD";
} else {
    $payment_details = $sets['invoice_payment_info'] ?? '-';
}

// Fungsi Format (Sama dengan Quotation)
function smart_format($num, $curr) {
    $clean_num = strval($num);
    $decimals = 0;
    if (strpos($clean_num, '.') !== false) {
        $decimals = strlen(substr(strrchr($clean_num, "."), 1));
    }
    if ($curr == 'IDR') {
        return number_format((float)$clean_num, $decimals, ',', '.');
    } else {
        return number_format((float)$clean_num, $decimals, '.', ',');
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

        .watermark-container { position: fixed; top: 42%; left: 50%; transform: translate(-50%, -50%); width: 80%; z-index: -1000; opacity: 0.08; pointer-events: none; }
        .watermark-img { width: 100%; height: auto; }

        .header-table { width: 100%; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .doc-title { text-align: right; font-size: 24px; font-weight: bold; text-transform: uppercase; padding-top: 20px; }

        .info-wrapper { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; border: 1px solid #000; }
        .info-box { width: 48%; border: 1px solid #000; padding: 10px; vertical-align: top; height: 150px; }
        .inner-table { width: 100%; font-size: 11px; }
        .inner-table td { padding-bottom: 3px; vertical-align: top; }
        .lbl { width: 90px; font-weight: bold; } .sep { width: 10px; text-align: center; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        .items-table th { border: 1px solid #000; background-color: #f2f2f2; padding: 8px; text-align: center; font-weight: bold; }
        .items-table td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
        .text-right { text-align: right; } .text-center { text-align: center; }

        .summary-row td { border: 1px solid #000; padding: 8px; }
        .label-cell { background-color: #fff; font-weight: bold; text-align: right; }
        .value-cell { text-align: right; font-weight: bold; }
        .border-none { border: none !important; }

        .footer-layout { width: 100%; margin-top: 20px; page-break-inside: avoid; }
        .footer-left { width: 60%; vertical-align: top; padding-right: 20px; }
        .footer-right { width: 40%; vertical-align: top; text-align: center; padding-top: 20px; }

        /* SIGNATURE STYLE (Sama Persis dengan Quotation) */
        .sign-company { font-size: 11px; font-weight: normal; margin-bottom: 10px; }
        .sign-img { 
            display: block; margin: 10px auto; 
            width: auto; height: auto; 
            max-width: 250px; max-height: 120px; /* Proporsional */
            object-fit: contain; 
        }
        .sign-name { font-weight: bold; text-decoration: underline; }
        .no-sign-box { height: 100px; line-height: 100px; color: #ccc; border: 1px dashed #ccc; margin: 10px auto; width: 180px; font-size: 10px; }

        @media print { .no-print { display: none; } [contenteditable="true"]:hover { background: none; outline: none; } }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
        <div style="margin-top:5px; color:red; font-size:11px;">* Tips: Klik angka di tabel untuk mengedit nominal secara manual sebelum dicetak.</div>
    </div>

    <div class="watermark-container"><img src="../uploads/<?= $sets['company_watermark'] ?>" class="watermark-img" onerror="this.style.display='none'"></div>

    <table class="header-table">
        <tr>
            <td>
                <img src="../uploads/<?= $sets['company_logo'] ?>" class="logo">
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
                    <tr><td class="lbl">Attn.</td><td class="sep">:</td><td><?= htmlspecialchars($inv['pic_name']) ?> (<?= htmlspecialchars($inv['pic_phone']) ?>)</td></tr>
                </table>
            </td>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="lbl">Invoice Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td></tr>
                    <tr><td class="lbl">Due Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($inv['due_date'])) ?></td></tr>
                    <tr><td class="lbl">Invoice Number</td><td class="sep">:</td><td><strong><?= $inv['invoice_no'] ?></strong></td></tr>
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
                <th width="5%">No</th><th width="35%">Description</th><th width="8%">Qty</th>
                <th width="17%">Method</th><th width="15%">Unit Price (<?= $inv['currency'] ?>)</th>
                <th width="20%">Total (<?= $inv['currency'] ?>)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no=1; $grandTotal=0;
            while($item = $items->fetch_assoc()): 
                $lineTotal = floatval($item['qty']) * floatval($item['unit_price']);
                $grandTotal += $lineTotal;
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                    <?= htmlspecialchars($item['item_name']) ?> 
                    <?php if(!empty($item['description'])): ?><br><small class="text-muted"><?= nl2br(htmlspecialchars($item['description'])) ?></small><?php endif; ?>
                </td>
                <td class="text-center" contenteditable="true"><?= smart_format($item['qty'], $inv['currency']) ?></td>
                <td class="text-center" contenteditable="true"><?= $inv['payment_method'] ?></td>
                <td class="text-right" contenteditable="true"><?= smart_format($item['unit_price'], $inv['currency']) ?></td>
                <td class="text-right" contenteditable="true"><?= smart_format($lineTotal, $inv['currency']) ?></td>
            </tr>
            <?php endwhile; ?>
            
            <?php 
                // Rounding Konsisten 0.5 Ke Bawah (IDR)
                if (!$is_usd) {
                    $grandTotal = round($grandTotal, 0, PHP_ROUND_HALF_DOWN);
                    $vatAmount = round($grandTotal * $tax_rate, 0, PHP_ROUND_HALF_DOWN); 
                } else {
                    $grandTotal = round($grandTotal, 2);
                    $vatAmount = round($grandTotal * $tax_rate, 2);
                }
                $totalAll = $grandTotal + $vatAmount;
            ?>
            
            <tr class="summary-row">
                <td colspan="4" class="border-none"></td><td class="label-cell">Sub Total</td>
                <td class="value-cell" contenteditable="true"><?= smart_format($grandTotal, $inv['currency']) ?></td>
            </tr>
            <?php if(!$is_usd): ?>
            <tr class="summary-row">
                <td colspan="4" class="border-none"></td><td class="label-cell">VAT (11%)</td>
                <td class="value-cell" contenteditable="true"><?= smart_format($vatAmount, $inv['currency']) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="summary-row">
                <td colspan="4" class="border-none"></td><td class="label-cell">Total</td>
                <td class="value-cell" contenteditable="true"><?= smart_format($totalAll, $inv['currency']) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="footer-layout">
        <tr>
            <td class="footer-left">
                <div style="font-style: italic; font-size: 10px; margin-bottom: 20px;">
                    <strong>Note :</strong><br><?= nl2br(htmlspecialchars($sets['invoice_note_default'] ?? '')) ?>
                </div>
                <div style="font-size: 11px;">
                    <span style="font-weight: bold; margin-bottom: 5px; display: block;">Payment Method (<?= $inv['currency'] ?>)</span>
                    <div style="line-height: 1.5; white-space: pre-line;"><?= htmlspecialchars($payment_details) ?></div>
                </div>
            </td>

            <td class="footer-right">
                <div class="sign-company">PT. Linksfield Networks Indonesia</div>
                
                <?php 
                    // LOGIKA TANDA TANGAN (AUTO FALLBACK)
                    // Kita siapkan 3 kemungkinan path
                    $signFile = trim($inv['sales_sign']);
                    $srcSig   = "../uploads/signatures/" . $signFile;
                    $srcUp    = "../uploads/" . $signFile;
                    $srcDef   = "../assets/images/signature.png";
                    
                    // Kita gunakan srcSig sebagai default awal.
                    // Jika browser gagal load, dia akan pindah ke srcUp, lalu ke srcDef.
                ?>

                <?php if (!empty($signFile)): ?>
                    <img src="<?= $srcSig ?>" class="sign-img" 
                         onerror="
                            // Jika gagal, coba folder uploads biasa
                            if (this.src.indexOf('signatures') > -1) { 
                                this.src = '<?= $srcUp ?>'; 
                            } 
                            // Jika folder uploads juga gagal, pakai default signature
                            else if (this.src.indexOf('uploads') > -1) { 
                                this.src = '<?= $srcDef ?>'; 
                            }
                            // Jika default pun gagal, sembunyikan gambar
                            else { 
                                this.style.display = 'none'; 
                            }
                         ">
                <?php else: ?>
                    <img src="<?= $srcDef ?>" class="sign-img" onerror="this.style.display='none'">
                <?php endif; ?>

                <div class="sign-name" contenteditable="true"><?= htmlspecialchars($inv['sales_name']) ?></div>
            </td>
        </tr>
    </table>

</body>
</html>