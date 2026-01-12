<?php
include '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$id = intval($_GET['id']);

// 1. AMBIL DATA HEADER (DO -> Payment -> Invoice -> Sales User)
$sql = "SELECT d.*, 
               c.company_name, c.address, c.pic_name, c.pic_phone,
               u.id as sender_id, u.username as sender_name, u.signature_file as sender_sign,
               p.invoice_id, i.quotation_id
        FROM delivery_orders d
        LEFT JOIN payments p ON d.payment_id = p.id
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN quotations q ON i.quotation_id = q.id
        LEFT JOIN clients c ON q.client_id = c.id
        LEFT JOIN users u ON i.created_by_user_id = u.id 
        WHERE d.id = $id";

$do = $conn->query($sql)->fetch_assoc();
if(!$do) die("DO not found (ID: $id)");

// 2. AMBIL ITEM (LOGIKA PERBAIKAN: AMBIL DARI INVOICE ITEMS)
$inv_id = $do['invoice_id'];
$quo_id = $do['quotation_id'];

// Coba ambil dari Invoice Items dulu
$sql_items = "SELECT item_name, qty, card_type, description FROM invoice_items WHERE invoice_id = '$inv_id'";
$items = $conn->query($sql_items);

// Jika kosong, ambil dari Quotation Items (Fallback)
if ($items->num_rows == 0) {
    $sql_items = "SELECT item_name, qty, card_type, description FROM quotation_items WHERE quotation_id = '$quo_id'";
    $items = $conn->query($sql_items);
}

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
        
        /* SETTING HALAMAN AGAR URL HILANG (Tapi tetap wajib uncheck 'Headers and footers' di browser) */
        @page { 
            margin: 1cm; /* Margin standar */
            size: A4; 
        }

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

        /* --- FOOTER LAYOUT --- */
        .footer-table { width: 100%; margin-top: 30px; page-break-inside: avoid; border-collapse: collapse; }
        
        /* [FIX] Align Bottom agar Nama Sejajar */
        .footer-col { vertical-align: bottom; padding: 10px; }
        
        .remarks-col { vertical-align: top; width: 34%; font-size: 10px; border-right: 1px solid #eee; padding-right: 15px; }
        .sender-col { width: 33%; text-align: center; }
        .recipient-col { width: 33%; text-align: center; }

        /* Styling Tanda Tangan */
        .sign-title { font-weight: bold; margin-bottom: 10px; text-decoration: underline; font-size: 11px; display: block; }
        
        /* [FIX] AREA TANDA TANGAN PROPORSIONAL */
        .sign-area { 
            height: 110px; /* Tinggi area tetap */
            width: 100%;
            display: flex;
            align-items: flex-end; /* Gambar menempel di bawah */
            justify-content: center;
            margin-bottom: 5px;
        }

        /* [FIX] GAMBAR TIDAK GEPENG */
        .sign-img { 
            max-width: 100%;   
            max-height: 100px; /* Batas tinggi gambar */
            width: auto;       /* Lebar otomatis menyesuaikan rasio */
            height: auto;      /* Tinggi otomatis menyesuaikan rasio */
            object-fit: contain; /* KUNCI: Menjaga proporsi gambar */
            display: block;
        }

        .sign-name { font-weight: bold; text-decoration: underline; font-size: 11px; margin-top: 5px; display: block;}
        .no-sign-text { color: #ccc; font-size: 9px; margin-bottom: 40px; display: block;}
        
        /* Garis Tanda Tangan Manual */
        .sign-line { 
            border-bottom: 1px solid #000; 
            width: 80%; 
            margin: 0 auto;
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
        <div style="margin-top: 5px; color: red; font-size: 10px;">
            * Untuk menghilangkan URL di bawah, Hapus Centang <b>"Headers and footers"</b> di menu Print.
        </div>
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
                <th width="35%">Item Name</th>
                <th width="10%">Unit</th>
                <th width="20%">Charge Mode</th>
                <th width="30%">Description</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; $totalUnit = 0;
            // LOOP ITEM DARI INVOICE/QUOTATION
            while($item = $items->fetch_assoc()): 
                $qty = floatval($item['qty']);
                $totalUnit += $qty;
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td class="text-left"><?= htmlspecialchars($item['item_name'] ?? '') ?></td>
                <td><?= $qty ?></td>
                <td><?= htmlspecialchars($item['card_type'] ?? '') ?></td>
                <td class="text-left"><?= nl2br(htmlspecialchars($item['description'] ?? '')) ?></td>
            </tr>
            <?php endwhile; ?>
            <tr>
                <td colspan="2" class="text-right" style="font-weight:bold;">Total Unit</td>
                <td style="font-weight:bold;"><?= $totalUnit ?></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td class="footer-col remarks-col">
                <strong>Remarks :</strong>
                <ul style="padding-left: 15px; margin-top: 5px;">
                    <li>Please sign and stamp this delivery order</li>
                    <li>Please send it via email and whatsapp to the number above</li>
                    <li>Barang yang sudah dibeli tidak dapat dikembalikan</li>
                </ul>
            </td>

            <td class="footer-col sender-col">
                <div class="sign-title">Sender</div>
                
                <div class="sign-area">
                    <?php 
                        $signFile = trim($do['sender_sign'] ?? ''); 
                        $userId   = $do['sender_id'] ?? 0;
                        $signPath = '';
                        $baseDir = dirname(__DIR__); 

                        // Logika Auto-Search (Sama dengan Invoice)
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

            <td class="footer-col recipient-col">
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