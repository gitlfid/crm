<?php
include '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$id = intval($_GET['id']);

$sql = "SELECT q.*, 
               c.company_name, c.address as c_address, c.pic_name, c.pic_phone,
               u.username as sales_name, u.email as sales_email, u.phone as sales_phone, 
               u.signature_file as sales_sign 
        FROM quotations q
        JOIN clients c ON q.client_id = c.id
        JOIN users u ON q.created_by_user_id = u.id
        WHERE q.id = $id";

$quot = $conn->query($sql)->fetch_assoc();
if(!$quot) die("Quotation not found");

$items = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $id");

$sets = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];

// --- DETEKSI BASE URL (Agar Gambar Muncul) ---
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// Naik satu level dari /admin/ ke root project
$baseUrl = $protocol . "://" . $host . dirname(dirname($_SERVER['PHP_SELF'])); 

// --- FORMAT PINTAR ---
function smart_format($num, $curr = 'IDR') {
    $val = floatval($num);
    if ($curr == 'IDR') {
        return number_format($val, 0, ',', '.');
    } else {
        return number_format($val, 2, '.', ',');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation <?= $quot['quotation_no'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; color: #000; -webkit-print-color-adjust: exact; }
        @page { margin: 1.5cm; size: A4; }
        
        /* HEADER & UTILS */
        .no-print { background: #f8f9fa; padding: 10px; text-align: center; border-bottom: 1px solid #ddd; }
        .btn-print { background: #0d6efd; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        [contenteditable="true"]:hover { background-color: #fffdd0; outline: 1px dashed #999; cursor: text; }

        /* LAYOUT */
        .watermark-container { position: fixed; top: 42%; left: 50%; transform: translate(-50%, -50%); width: 80%; z-index: -1000; opacity: 0.08; pointer-events: none; }
        .watermark-img { width: 100%; height: auto; }
        
        .header-table { width: 100%; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .company-addr { font-size: 10px; color: #333; max-width: 300px; line-height: 1.3; }
        .doc-title { text-align: right; font-size: 24px; font-weight: bold; text-transform: uppercase; padding-top: 20px; }
        
        .info-wrapper { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; border: 1px solid #000; }
        .info-box { width: 50%; padding: 10px; vertical-align: top; }
        .border-right { border-right: 1px solid #000; }
        .inner-table { width: 100%; font-size: 11px; }
        .inner-table td { padding-bottom: 3px; vertical-align: top; }
        .lbl { width: 80px; font-weight: bold; } 
        .sep { width: 10px; text-align: center; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        .items-table th { border: 1px solid #000; background-color: #f2f2f2; padding: 8px; text-align: center; font-weight: bold; }
        .items-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .remark-box { margin-top: 15px; font-size: 10px; line-height: 1.4; border-top: 1px solid #eee; padding-top: 10px; }
        .remark-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; display: block; }
        
        /* SIGNATURE STYLE (UPDATED) */
        .sign-table { width: 100%; margin-top: 40px; page-break-inside: avoid; }
        .sign-cell { text-align: center; vertical-align: bottom; }
        .sign-img { 
            display: block; margin: 10px auto; 
            width: auto; height: auto; 
            max-width: 250px; max-height: 120px; 
            object-fit: contain; 
        }
        .sign-name { font-weight: bold; text-decoration: underline; }
        .no-sign-box { height: 100px; line-height: 100px; color: #ccc; border: 1px dashed #ccc; margin: 10px auto; width: 180px; font-size: 10px; }
        
        @media print { 
            .no-print { display: none; }
            [contenteditable="true"]:hover { background: none; outline: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
        <div style="margin-top:5px; color:red; font-size:11px;">* Klik angka untuk edit manual sebelum print.</div>
    </div>

    <div class="watermark-container">
        <img src="<?= $baseUrl ?>/uploads/<?= $sets['company_watermark'] ?>" class="watermark-img" onerror="this.style.display='none'">
    </div>

    <table class="header-table">
        <tr>
            <td>
                <img src="<?= $baseUrl ?>/uploads/<?= $sets['company_logo'] ?>" class="logo" onerror="this.style.display='none'">
                <div class="company-addr"><?= nl2br(htmlspecialchars($sets['company_address_full'])) ?></div>
            </td>
            <td align="right" valign="top"><div class="doc-title">QUOTATION</div></td>
        </tr>
    </table>

    <table class="info-wrapper">
        <tr>
            <td class="info-box border-right">
                <table class="inner-table">
                    <tr><td class="lbl">To</td><td class="sep">:</td><td><strong><?= htmlspecialchars($quot['company_name']) ?></strong></td></tr>
                    <tr><td class="lbl">Address</td><td class="sep">:</td><td><?= nl2br(htmlspecialchars($quot['c_address'])) ?></td></tr>
                    <tr><td class="lbl">Attn.</td><td class="sep">:</td><td><?= htmlspecialchars($quot['pic_name']) ?> (<?= htmlspecialchars($quot['pic_phone']) ?>)</td></tr>
                </table>
            </td>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="lbl">Quotation</td><td class="sep">:</td><td><strong><?= $quot['quotation_no'] ?></strong></td></tr>
                    <tr><td class="lbl">Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($quot['quotation_date'])) ?></td></tr>
                    <tr><td class="lbl">Currency</td><td class="sep">:</td><td><?= $quot['currency'] ?></td></tr>
                    <tr><td colspan="3" style="height:5px"></td></tr>
                    <tr><td class="lbl">Contact</td><td class="sep">:</td><td><?= $quot['sales_name'] ?></td></tr>
                    <tr><td class="lbl">Email</td><td class="sep">:</td><td><?= $quot['sales_email'] ?></td></tr>
                    <tr><td class="lbl">Tel</td><td class="sep">:</td><td><?= $quot['sales_phone'] ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Item</th>
                <th width="8%">Qty</th>
                <th width="15%">Unit Price (<?= $quot['currency'] ?>)</th>
                <th width="20%">Description</th>
                <th width="17%">Charge Mode</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; while($item = $items->fetch_assoc()): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                    <div contenteditable="true"><?= htmlspecialchars($item['item_name']) ?></div>
                </td>
                <td class="text-center" contenteditable="true"><?= smart_format($item['qty'], $quot['currency']) ?></td>
                <td class="text-right" contenteditable="true"><?= smart_format($item['unit_price'], $quot['currency']) ?></td>
                <td><div contenteditable="true"><?= htmlspecialchars($item['description']) ?></div></td>
                
                <td class="text-center" contenteditable="true"><?= htmlspecialchars($item['card_type']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="remark-box">
        <span class="remark-title">REMARKS :</span>
        <div contenteditable="true">
        <?php 
        if (!empty($quot['remarks'])) {
            echo nl2br(htmlspecialchars($quot['remarks']));
        } else {
            echo "- Please required the number quotation if open the PO<br>";
            echo "- Please send the NPWP Company if open the PO";
        }
        ?>
        </div>
    </div>

    <table class="sign-table">
        <tr>
            <td width="60%"></td>
            <td width="40%" class="sign-cell">
                <div style="margin-bottom: 10px;">PT. Linksfield Networks Indonesia</div>
                
                <?php 
                    $signFile = trim($quot['sales_sign']);
                    $mainSignUrl = $baseUrl . "/uploads/signatures/" . $signFile;
                    $backupSignUrl = $baseUrl . "/uploads/" . $signFile;
                    $defaultSignUrl = $baseUrl . "/assets/images/signature.png";
                ?>

                <?php if(!empty($signFile)): ?>
                    <img src="<?= $mainSignUrl ?>" class="sign-img" 
                         onerror="
                            if (this.src == '<?= $mainSignUrl ?>') { this.src = '<?= $backupSignUrl ?>'; }
                            else if (this.src == '<?= $backupSignUrl ?>') { this.src = '<?= $defaultSignUrl ?>'; }
                            else { this.style.display='none'; document.getElementById('no-sign-box').style.display='block'; }
                         ">
                    <div id="no-sign-box" class="no-sign-box" style="display:none;">(No Signature Found)</div>
                <?php else: ?>
                    <img src="<?= $defaultSignUrl ?>" class="sign-img" onerror="this.style.display='none';">
                <?php endif; ?>

                <div class="sign-name" contenteditable="true"><?= htmlspecialchars($quot['sales_name']) ?></div>
            </td>
        </tr>
    </table>

</body>
</html>