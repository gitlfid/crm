<?php
include '../config/database.php';
include '../config/functions.php'; // Pastikan load functions untuk helper angka jika ada
session_start();

if (!isset($_GET['id'])) die("Invoice ID Not Found");
$id = intval($_GET['id']);

// 1. AMBIL HEADER INVOICE
$sql = "SELECT i.*, 
               c.company_name, c.address, c.pic_name, c.pic_phone, c.pic_email,
               q.po_number_client, q.currency,
               u.username as creator_name
        FROM invoices i
        JOIN quotations q ON i.quotation_id = q.id
        JOIN clients c ON q.client_id = c.id
        LEFT JOIN users u ON i.created_by_user_id = u.id
        WHERE i.id = $id";

$inv = $conn->query($sql)->fetch_assoc();
if (!$inv) die("Invoice Not Found");

// 2. AMBIL ITEM (LOGIKA PRIORITAS: INVOICE ITEMS > QUOTATION ITEMS)
$items = [];
// Cek tabel invoice_items (Data yang sudah diedit user)
$sqlItems = "SELECT item_name, qty, unit_price, description, card_type FROM invoice_items WHERE invoice_id = $id";
$resItems = $conn->query($sqlItems);

if ($resItems->num_rows > 0) {
    while($row = $resItems->fetch_assoc()) {
        $items[] = $row;
    }
} else {
    // Fallback: Ambil dari Quotation jika invoice item belum ter-generate
    $q_id = $inv['quotation_id'];
    $sqlQ = "SELECT item_name, qty, unit_price, description, card_type FROM quotation_items WHERE quotation_id = $q_id";
    $resQ = $conn->query($sqlQ);
    while($row = $resQ->fetch_assoc()) {
        $items[] = $row;
    }
}

// Ambil Setting Perusahaan (Logo, Alamat Sendiri)
$sets = [];
$resS = $conn->query("SELECT * FROM settings");
while($row = $resS->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];

// Tentukan Simbol Mata Uang
$currency = $inv['currency']; 
$symbol = ($currency == 'IDR') ? 'Rp' : '$';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= $inv['invoice_no'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 11px; 
            margin: 0; padding: 0; 
            color: #000 !important; /* Force Hitam */
            -webkit-print-color-adjust: exact !important; 
        }
        
        @page { size: A4; margin: 0.5cm; }
        
        /* Container Print */
        .wrapper { width: 95%; margin: 0 auto; padding-top: 10px; }

        /* Header Layout */
        .header-table { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .my-addr { font-size: 10px; color: #333; max-width: 400px; }
        .doc-title { text-align: right; font-size: 24px; font-weight: bold; text-transform: uppercase; }

        /* Info Customer & Invoice Data */
        .info-table { width: 100%; margin-bottom: 20px; border: 1px solid #000; border-collapse: collapse; }
        .info-table td { padding: 5px 8px; vertical-align: top; border: 1px solid #ccc; }
        .lbl { font-weight: bold; width: 100px; }
        
        /* Tabel Item */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; border: 1px solid #000; }
        .items-table th { 
            border: 1px solid #000; 
            background-color: #f0f0f0 !important; /* Abu muda */
            color: #000 !important; 
            padding: 8px; font-weight: bold; text-align: center; 
        }
        .items-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }

        /* Footer / TTD */
        .footer-box { margin-top: 30px; text-align: right; page-break-inside: avoid; }
        .sign-area { height: 100px; display: flex; align-items: flex-end; justify-content: flex-end; }
        .sign-img { max-height: 80px; object-fit: contain; }
        .sign-line { border-top: 1px solid #000; display: inline-block; width: 200px; text-align: center; padding-top: 5px; font-weight: bold; }

        @media print { .no-print { display: none; } }
        .no-print { text-align: center; padding: 10px; background: #eee; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" style="padding:5px 15px; background:blue; color:white; border:none; cursor:pointer;">🖨️ Print Invoice</button>
    </div>

    <div class="wrapper">
        <table class="header-table">
            <tr>
                <td valign="top">
                    <img src="../uploads/<?= $sets['company_logo'] ?>" class="logo" onerror="this.style.display='none'">
                    <div class="my-addr">
                        <strong><?= $sets['company_name'] ?></strong><br>
                        <?= nl2br($sets['company_address_full']) ?>
                    </div>
                </td>
                <td align="right" valign="top">
                    <div class="doc-title">INVOICE</div>
                </td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <td width="50%" style="border-right: 2px solid #000;">
                    <div style="margin-bottom: 5px;"><span class="lbl">To:</span></div>
                    <strong><?= htmlspecialchars($inv['company_name']) ?></strong><br>
                    <?= nl2br(htmlspecialchars($inv['address'])) ?><br>
                    <br>
                    Attn: <?= htmlspecialchars($inv['pic_name']) ?>
                </td>
                <td width="50%">
                    <table width="100%">
                        <tr><td class="lbl" style="border:none;">Invoice Date</td><td style="border:none;">: <?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td></tr>
                        <tr><td class="lbl" style="border:none;">Due Date</td><td style="border:none;">: <?= date('d/m/Y', strtotime($inv['due_date'])) ?></td></tr>
                        <tr><td class="lbl" style="border:none;">Invoice No</td><td style="border:none;">: <strong><?= $inv['invoice_no'] ?></strong></td></tr>
                        <tr><td class="lbl" style="border:none;">PO. Reference</td><td style="border:none;">: <?= $inv['po_number_client'] ?></td></tr>
                        <tr><td class="lbl" style="border:none;">Currency</td><td style="border:none;">: <?= $inv['currency'] ?></td></tr>
                        <tr><td class="lbl" style="border:none;">Contact</td><td style="border:none;">: <?= $sets['contact_person'] ?? 'Niawati' ?></td></tr>
                        <tr><td class="lbl" style="border:none;">Email</td><td style="border:none;">: <?= $sets['contact_email'] ?? 'nia@linksfield.net' ?></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="35%">Description</th>
                    <th width="10%">Qty</th>
                    <th width="15%">Payment Method</th>
                    <th width="15%">Unit Price (<?= $currency ?>)</th>
                    <th width="20%">Total (<?= $currency ?>)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; 
                $subTotal = 0;
                
                foreach($items as $item): 
                    $qty   = floatval($item['qty']);
                    $price = floatval($item['unit_price']);
                    $total = $qty * $price;
                    $subTotal += $total;
                    
                    // Payment Method (Card Type atau Custom dari kolom)
                    $payMethod = !empty($item['card_type']) ? $item['card_type'] : $inv['payment_method']; 
                    if(empty($payMethod)) $payMethod = 'Prepaid';
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>
                        <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                        <?php if(!empty($item['description'])): ?>
                            <br><small class="text-muted"><?= nl2br(htmlspecialchars($item['description'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= $qty ?></td> <td class="text-center"><?= htmlspecialchars($payMethod) ?></td>
                    <td class="text-right"><?= number_format($price, ($currency=='IDR'?0:2)) ?></td>
                    <td class="text-right"><?= number_format($total, ($currency=='IDR'?0:2)) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php 
                    $vat = $subTotal * 0.11; // PPN 11%
                    $grandTotal = $subTotal + $vat;
                ?>
                <tr>
                    <td colspan="4" style="border:none;"></td>
                    <td class="text-right text-bold" style="background:#f9f9f9;">Sub Total</td>
                    <td class="text-right text-bold"><?= number_format($subTotal, ($currency=='IDR'?0:2)) ?></td>
                </tr>
                <tr>
                    <td colspan="4" style="border:none;"></td>
                    <td class="text-right text-bold" style="background:#f9f9f9;">VAT (11%)</td>
                    <td class="text-right text-bold"><?= number_format($vat, ($currency=='IDR'?0:2)) ?></td>
                </tr>
                <tr>
                    <td colspan="4" style="border:none;"></td>
                    <td class="text-right text-bold" style="background:#f9f9f9; border-top:2px solid #000;">Total</td>
                    <td class="text-right text-bold" style="border-top:2px solid #000;"><?= number_format($grandTotal, ($currency=='IDR'?0:2)) ?></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 20px; font-size: 10px; font-style: italic;">
            <strong>Note :</strong><br>
            Please note that the payer is responsible for any bank charges incurred in preparing bank transfers.
        </div>

        <div style="margin-top: 10px; font-size: 10px;">
            <strong>Payment Method (IDR)</strong><br>
            Acc Name : <?= $sets['bank_account_name'] ?? 'PT. LINKSFIELD NETWORKS INDONESIA' ?><br>
            Acc No : <?= $sets['bank_account_no'] ?? '123-456-7890' ?><br>
            Bank : <?= $sets['bank_name'] ?? 'BCA' ?>
        </div>

        <div class="footer-box">
            <div style="margin-bottom: 50px;">
                PT. Linksfield Networks Indonesia
            </div>
            
            <div class="sign-area">
                <?php 
                    $baseDir = dirname(__DIR__);
                    // Cari TTD Creator Invoice
                    $creatorID = $inv['created_by_user_id'];
                    $signPath = '';
                    
                    if(!empty($creatorID)) {
                        $files = glob($baseDir . '/uploads/signatures/SIG_*_' . $creatorID . '_*.png');
                        if ($files && count($files) > 0) $signPath = '../uploads/signatures/' . basename($files[0]);
                    }
                    if (empty($signPath) && file_exists($baseDir . '/assets/images/signature.png')) {
                        $signPath = '../assets/images/signature.png';
                    }
                ?>
                <?php if(!empty($signPath)): ?>
                    <img src="<?= $signPath ?>" class="sign-img">
                <?php endif; ?>
            </div>
            
            <div class="sign-line">
                ( Niawati )
            </div>
        </div>
    </div>

</body>
</html>