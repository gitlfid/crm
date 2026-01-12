<?php
include '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$id = intval($_GET['id']);

// 1. AMBIL DATA HEADER
$sql = "SELECT d.*, 
               c.company_name, c.address, c.pic_name, c.pic_phone,
               u.id as sender_id, u.username as sender_name, u.signature_file as sender_sign 
        FROM delivery_orders d
        LEFT JOIN payments p ON d.payment_id = p.id
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN quotations q ON i.quotation_id = q.id
        LEFT JOIN clients c ON q.client_id = c.id
        LEFT JOIN users u ON i.created_by_user_id = u.id 
        WHERE d.id = $id";

$do = $conn->query($sql)->fetch_assoc();
if(!$do) die("DO not found (ID: $id)");

$items = $conn->query("SELECT * FROM delivery_order_items WHERE delivery_order_id = $id");

$sets = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Order <?= $do['do_number'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; -webkit-print-color-adjust: exact; }
        @page { margin: 1.5cm; size: A4; }

        /* HEADER */
        .header-table { width: 100%; margin-bottom: 30px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .company-addr { font-size: 10px; color: #333; max-width: 300px; line-height: 1.3; }
        .doc-title { text-align: right; font-size: 20px; font-weight: bold; text-transform: uppercase; padding-top: 20px; }

        /* INFO BOXES */
        .info-wrapper { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
        .info-box { width: 48%; border: 1px solid #000; padding: 10px; vertical-align: top; height: 120px; }
        .info-spacer { width: 4%; }
        .inner-table { width: 100%; font-size: 11px; }
        .inner-table td { padding-bottom: 3px; vertical-align: top; }
        .lbl { width: 80px; font-weight: bold; } .sep { width: 10px; text-align: center; }

        /* ITEMS TABLE */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        .items-table th { 
            border: 1px solid #000; 
            background-color: #ff6b6b; /* Warna Header DO */
            color: white;
            padding: 8px; 
            text-align: center; 
            font-weight: bold;
        }
        .items-table td { border: 1px solid #000; padding: 8px; vertical-align: middle; text-align: center; }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }

        /* --- FOOTER LAYOUT (PROPORSIONAL FIX) --- */
        .footer-table { width: 100%; margin-top: 30px; page-break-inside: avoid; border-collapse: collapse; }
        .footer-col { vertical-align: top; padding: 10px; }
        
        /* Pembagian Kolom yang Seimbang */
        .remarks-box { width: 34%; font-size: 10px; border-right: 1px solid #eee; }
        .sender-box { width: 33%; text-align: center; }
        .recipient-box { width: 33%; text-align: center; }

        /* Styling Tanda Tangan */
        .sign-title { font-weight: bold; margin-bottom: 5px; text-decoration: underline; font-size: 11px; }
        
        /* Area Gambar/Kosong dengan Tinggi Tetap agar Nama Sejajar */
        .sign-area { 
            height: 100px; /* Tinggi Kunci untuk kesejajaran */
            width: 100%;
            display: flex;
            align-items: flex-end; /* Memaksa isi ada di bawah */
            justify-content: center;
            margin-bottom: 5px;
        }

        .sign-img { 
            display: block; margin: 0 auto; 
            max-width: 150px; 
            max-height: 90px; /* Agar tidak melebihi sign-area */
            object-fit: contain; 
            position: relative; top: 10px; /* Sedikit turun agar rapi */
        }

        .sign-name { font-weight: bold; text-decoration: underline; font-size: 11px; margin-top: 5px; }
        .no-sign-text { line-height: 100px; color: #ccc; font-size: 9px; }
        
        /* Garis Tanda Tangan Manual */
        .sign-line { 
            border-bottom: 1px solid #000; 
            width: 80%; 
            margin: 80px auto 5px auto; /* Margin atas menyesuaikan area kosong */
        }

        /* HIDE PRINT BUTTON */
        @media print { .no-print { display: none; } }
        .no-print { text-align: center; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #ccc; margin-bottom: 20px; }
        .btn-print { padding: 5px 15px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print">🖨️ Print / Save PDF</button>
    </div>

    <table class="header-table">
        <tr>
            <td>
                <img src="../uploads/<?= $sets['company_logo'] ?>" class="logo" onerror="this.style.display='none'">
                <div class="company-addr"><?= nl2br(htmlspecialchars($sets['company_address_full'] ?? '')) ?></div>
            </td>
            <td align="right" valign="top"><div class="doc-title">DELIVERY ORDER</div></td>
        </tr>
    </table>

    <table class="info-wrapper">
        <tr>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="lbl">To</td><td class="sep">:</td><td><strong><?= htmlspecialchars($do['company_name'] ?? 'Unknown') ?></strong></td></tr>
                    <tr><td class="lbl">Address</td><td class="sep">:</td><td><?= nl2br(htmlspecialchars($do['address'] ?? '-')) ?></td></tr>
                    <tr><td class="lbl">Attn.</td><td class="sep">:</td><td><?= htmlspecialchars($do['pic_name'] ?? '-') ?></td></tr>
                </table>
            </td>
            <td class="info-spacer"></td>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="lbl">Delivery Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($do['do_date'])) ?></td></tr>
                    <tr><td class="lbl">Delivery No</td><td class="sep">:</td><td><strong><?= $do['do_number'] ?></strong></td></tr>
                    <tr><td class="lbl">Contact</td><td class="sep">:</td><td><?= htmlspecialchars($do['pic_name'] ?? '-') ?></td></tr>
                    <tr><td class="lbl">Tel</td><td class="sep">:</td><td><?= htmlspecialchars($do['pic_phone'] ?? '-') ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="25%">Item</th>
                <th width="15%">Content</th>
                <th width="10%">Unit</th>
                <th width="15%">Charge Mode</th>
                <th width="30%">Description</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; $totalUnit = 0;
            while($item = $items->fetch_assoc()): 
                $totalUnit += $item['unit'];
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td class="text-left"><?= htmlspecialchars($item['item_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['content'] ?? '') ?></td>
                <td><?= $item['unit'] ?></td>
                <td><?= htmlspecialchars($item['charge_mode'] ?? '') ?></td>
                <td class="text-left"><?= nl2br(htmlspecialchars($item['description'] ?? '')) ?></td>
            </tr>
            <?php endwhile; ?>
            <tr>
                <td colspan="3" class="text-right" style="font-weight:bold;">Total Unit</td>
                <td style="font-weight:bold;"><?= $totalUnit ?></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td class="footer-col remarks-box">
                <strong>Remarks :</strong>
                <ul style="padding-left: 15px; margin-top: 5px;">
                    <li>Please sign and stamp this delivery order</li>
                    <li>Please send it via email and whatsapp to the number above</li>
                    <li>Barang yang sudah dibeli tidak dapat dikembalikan</li>
                </ul>
            </td>

            <td class="footer-col sender-box">
                <div class="sign-title">Sender</div>
                
                <div class="sign-area">
                    <?php 
                        $signFile = trim($do['sender_sign'] ?? ''); 
                        $userId   = $do['sender_id'] ?? 0;
                        $signPath = '';
                        $baseDir = dirname(__DIR__); 

                        // Logika Auto-Search
                        if (!empty($signFile) && file_exists($baseDir . '/uploads/signatures/' . $signFile)) {
                            $signPath = '../uploads/signatures/' . $signFile;
                        }
                        elseif (!empty($userId)) {
                            $files = glob($baseDir . '/uploads/signatures/SIG_*_' . $userId . '_*.png');
                            if ($files && count($files) > 0) $signPath = '../uploads/signatures/' . basename($files[0]);
                        }

                        if (empty($signPath) && file_exists($baseDir . '/assets/images/signature.png')) {
                            $signPath = '../assets/images/signature.png';
                        }
                    ?>

                    <?php if (!empty($signPath)): ?>
                        <img src="<?= $signPath ?>" class="sign-img">
                    <?php else: ?>
                        <span class="no-sign-text">(No Signature)</span>
                    <?php endif; ?>
                </div>

                <div class="sign-name"><?= htmlspecialchars($do['sender_name'] ?? 'Niawati') ?></div>
            </td>

            <td class="footer-col recipient-box">
                <div class="sign-title">Recipient</div>
                
                <div class="sign-area">
                    <div class="sign-line"></div>
                </div>

                <div class="sign-name"><?= htmlspecialchars($do['pic_name'] ?? 'Client') ?></div>
            </td>
        </tr>
    </table>

</body>
</html>