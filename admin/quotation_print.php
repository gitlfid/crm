<?php
include '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$id = intval($_GET['id']);

// 1. AMBIL DATA HEADER QUOTATION
$sql = "SELECT q.*, 
               c.company_name, c.address as c_address, c.pic_name, c.pic_phone,
               u.username as sales_name, u.email as sales_email, u.phone as sales_phone, 
               u.signature_file as sales_sign 
        FROM quotations q
        JOIN clients c ON q.client_id = c.id
        JOIN users u ON q.created_by_user_id = u.id
        WHERE q.id = $id";
$quo = $conn->query($sql)->fetch_assoc();
if(!$quo) die("Quotation not found");

// 2. AMBIL ITEM QUOTATION
$itemsData = [];
$resQ = $conn->query("SELECT item_name, qty, unit_price, card_type, description FROM quotation_items WHERE quotation_id = $id");
while($row = $resQ->fetch_assoc()) { $itemsData[] = $row; }

// 3. AMBIL SETTINGS APLIKASI (Logo, Alamat Perusahaan)
$sets = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];

// ========================================================================
// 4. LOGIKA PERMISSION EDIT NOTE (HANYA ADMIN & DIVISI FINANCE)
// ========================================================================
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

// Berikan hak 'contenteditable' JIKA user adalah Admin ATAU anggota divisi Finance
$can_edit_note = ($user_role_session === 'admin' || $is_finance) ? 'contenteditable="true"' : '';


// 5. SETTING CURRENCY & FORMATTING
$is_intl = ($quo['currency'] !== 'IDR');
function format_money($num, $is_intl) {
    if ($is_intl) {
        return number_format((float)$num, 2, '.', ','); 
    } else {
        return number_format((float)$num, 0, ',', '.');
    }
}

// 6. DEFAULT REMARKS
$remarks = !empty($quo['remarks']) ? $quo['remarks'] : "- Please required the number quotation if open the PO\n- Please send the NPWP Company if open the PO";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation <?= $quo['quotation_no'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; color: #000; -webkit-print-color-adjust: exact; }
        
        /* Set Kertas Landscape Sesuai Standar Quotation */
        @page { margin: 1cm; size: A4 landscape; }

        .no-print { background: #f8f9fa; padding: 10px; text-align: center; border-bottom: 1px solid #ddd; }
        .btn-print { background: #0d6efd; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        
        /* Efek kuning saat area bisa diedit di-hover */
        [contenteditable="true"]:hover { background-color: #fffdd0; outline: 1px dashed #999; cursor: text; }

        .header-table { width: 100%; margin-bottom: 15px; }
        .logo { max-height: 50px; margin-bottom: 5px; }
        .company-addr { font-size: 10px; color: #333; max-width: 350px; line-height: 1.3; }
        .doc-title { text-align: right; font-size: 24px; font-weight: bold; text-transform: uppercase; vertical-align: bottom; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #000; }
        .info-table td { border: 1px solid #000; padding: 8px; vertical-align: top; width: 50%; }
        
        .inner-info { width: 100%; font-size: 11px; border-collapse: collapse; }
        .inner-info td { padding: 2px 0; vertical-align: top; }
        .lbl { width: 70px; font-weight: bold; }
        .sep { width: 15px; text-align: center; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 11px; border: 1px solid #000; }
        .items-table th { border: 1px solid #000; background-color: #f2f2f2; padding: 8px; text-align: center; font-weight: bold; }
        .items-table td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .footer-table { width: 100%; margin-top: 20px; page-break-inside: avoid; }
        .footer-table td { vertical-align: top; }
        .footer-left { width: 60%; }
        .footer-right { width: 40%; text-align: center; padding-top: 20px; }

        .remarks-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; display: inline-block; }
        .sign-company { font-size: 11px; margin-bottom: 10px; }
        .sign-img { display: block; margin: 10px auto; max-width: 150px; max-height: 80px; object-fit: contain; }
        .sign-name { font-weight: bold; text-decoration: underline; }
        .no-sign-box { height: 60px; line-height:60px; color:#ccc; border:1px dashed #ccc; margin:10px auto; width:100px; font-size: 10px; }

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
            * Klik area teks (seperti Remarks, Harga, Dll) untuk edit manual sebelum print.<br>
            <span style="color:#666;">(Hak Edit diberikan otomatis khusus Admin & Divisi Finance)</span>
        </div>
    </div>

    <table class="header-table">
        <tr>
            <td>
                <img src="../uploads/<?= $sets['company_logo'] ?>" class="logo" onerror="this.style.display='none'">
                <div class="company-addr"><?= nl2br(htmlspecialchars($sets['company_address_full'])) ?></div>
            </td>
            <td align="right" valign="bottom"><div class="doc-title">QUOTATION</div></td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td>
                <table class="inner-info">
                    <tr><td class="lbl">To</td><td class="sep">:</td><td><strong><?= htmlspecialchars($quo['company_name']) ?></strong></td></tr>
                    <tr><td class="lbl">Address</td><td class="sep">:</td><td <?= $can_edit_note ?>><?= nl2br(htmlspecialchars($quo['c_address'])) ?></td></tr>
                    <tr><td class="lbl">Attn.</td><td class="sep">:</td><td <?= $can_edit_note ?>><?= htmlspecialchars($quo['pic_name']) ?> <?= !empty($quo['pic_phone']) ? "(".htmlspecialchars($quo['pic_phone']).")" : "" ?></td></tr>
                </table>
            </td>
            <td>
                <table class="inner-info">
                    <tr><td class="lbl">Quotation</td><td class="sep">:</td><td><strong><?= $quo['quotation_no'] ?></strong></td></tr>
                    <tr><td class="lbl">Date</td><td class="sep">:</td><td <?= $can_edit_note ?>><?= date('d/m/Y', strtotime($quo['quotation_date'])) ?></td></tr>
                    <tr><td class="lbl">Currency</td><td class="sep">:</td><td><?= $quo['currency'] ?></td></tr>
                    <tr><td class="lbl">Contact</td><td class="sep">:</td><td <?= $can_edit_note ?>><?= htmlspecialchars($quo['sales_name']) ?></td></tr>
                    <tr><td class="lbl">Email</td><td class="sep">:</td><td <?= $can_edit_note ?>><?= htmlspecialchars($quo['sales_email']) ?></td></tr>
                    <tr><td class="lbl">Tel</td><td class="sep">:</td><td <?= $can_edit_note ?>><?= htmlspecialchars($quo['sales_phone']) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="30%">Item</th>
                <th width="10%">Qty</th>
                <th width="15%">Unit Price (<?= $quo['currency'] ?>)</th>
                <th width="25%">Description</th>
                <th width="15%">Charge Mode</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach($itemsData as $item): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td <?= $can_edit_note ?>><?= htmlspecialchars($item['item_name']) ?></td>
                <td class="text-center" <?= $can_edit_note ?>><?= floatval($item['qty']) ?></td> 
                <td class="text-right" <?= $can_edit_note ?>><?= format_money($item['unit_price'], $is_intl) ?></td>
                <td <?= $can_edit_note ?>><?= htmlspecialchars($item['description']) ?></td>
                <td class="text-center" <?= $can_edit_note ?>><?= htmlspecialchars($item['card_type']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td class="footer-left">
                <div class="remarks-title">REMARKS :</div>
                <div <?= $can_edit_note ?> style="line-height: 1.5; white-space: pre-wrap;"><?= htmlspecialchars($remarks) ?></div>
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
                <div class="sign-name" <?= $can_edit_note ?>><?= htmlspecialchars($signerName) ?></div>
            </td>
        </tr>
    </table>
</body>
</html>