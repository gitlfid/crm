<?php
include '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$id = intval($_GET['id']);

// 1. AMBIL HEADER
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
if(!$do) die("DO not found");

// 2. AMBIL ITEM (LOGIKA ANTI-HILANG)
$itemsData = [];

// Cek tabel DO Items (Hasil Edit Manual)
$sqlDOItems = "SELECT item_name, unit as qty, charge_mode, description FROM delivery_order_items WHERE delivery_order_id = $id";
$resDOItems = $conn->query($sqlDOItems);

if ($resDOItems && $resDOItems->num_rows > 0) {
    // Jika ada data edit, pakai ini
    while($itm = $resDOItems->fetch_assoc()) {
        $itemsData[] = $itm;
    }
} else {
    // JIKA KOSONG, AMBIL DARI INVOICE (Data Asli/Lama)
    $inv_id = $do['invoice_id'];
    $quo_id = $do['quotation_id'];
    
    // Coba Invoice
    $sql_items = "SELECT item_name, qty, card_type as charge_mode, description FROM invoice_items WHERE invoice_id = '$inv_id'";
    $resItems = $conn->query($sql_items);
    
    // Coba Quotation jika Invoice kosong
    if ($resItems->num_rows == 0) {
        $sql_items = "SELECT item_name, qty, card_type as charge_mode, description FROM quotation_items WHERE quotation_id = '$quo_id'";
        $resItems = $conn->query($sql_items);
    }

    while($itm = $resItems->fetch_assoc()) {
        // Force fix tampilan jika data lama
        if(empty($itm['charge_mode']) || stripos($itm['charge_mode'], 'BBC') !== false) {
            $itm['charge_mode'] = 'Prepaid';
        }
        $itemsData[] = $itm;
    }
}

$sets = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DO <?= $do['do_number'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; 
            color: #000 !important; /* Paksa Hitam */
            -webkit-print-color-adjust: exact !important; /* Paksa Warna Background */
        }
        
        @page { size: A4; margin: 0.5cm; }
        
        /* WRAPPER TENGAH & LEBAR 95% AGAR TIDAK KEPOTONG */
        .wrapper { width: 95%; margin: 0 auto; padding-top: 10px; }

        .header-table { width: 100%; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .company-addr { font-size: 10px; color: #000; max-width: 350px; line-height: 1.3; }
        .doc-title { text-align: right; font-size: 20px; font-weight: bold; text-transform: uppercase; padding-top: 20px; color: #000; }

        .info-wrapper { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
        .info-box { width: 48%; border: 1px solid #000; padding: 10px; vertical-align: top; height: 120px; }
        .inner-table { width: 100%; font-size: 11px; }
        .inner-table td { padding-bottom: 3px; vertical-align: top; }
        .lbl { width: 80px; font-weight: bold; } .sep { width: 10px; text-align: center; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; border: 1px solid #000; }
        
        /* WARNA HEADER TABEL */
        .items-table th { 
            border: 1px solid #000; 
            background-color: #ff6b6b !important; 
            color: white !important; 
            padding: 8px; font-weight: bold; text-align: center; 
        }
        .items-table td { border: 1px solid #000; padding: 8px; vertical-align: middle; text-align: center; color: #000; }
        .text-left { text-align: left !important; }

        .footer-table { width: 100%; margin-top: 30px; border-collapse: collapse; page-break-inside: avoid; }
        .footer-col { vertical-align: bottom; padding: 10px; }
        .remarks-col { width: 34%; font-size: 10px; border-right: 1px solid #eee; padding-right: 15px; vertical-align: top; }
        .sender-col, .recipient-col { width: 33%; text-align: center; }

        .sign-area { height: 130px; display: flex; align-items: flex-end; justify-content: center; width: 100%; }
        .sign-img { max-height: 120px; max-width: 100%; object-fit: contain; }
        .sign-name { font-weight: bold; text-decoration: underline; margin-top: 5px; font-size: 11px; }
        .sign-line { border-bottom: 1px solid #000; width: 80%; margin: 0 auto; }
        
        @media print { .no-print { display: none; } }
        .no-print { text-align: center; padding: 10px; background: #eee; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" style="padding:5px 15px; background:blue; color:white; border:none; border-radius:4px; cursor:pointer;">🖨️ Print / PDF</button>
        <div style="font-size:10px; color:red; margin-top:5px;">* Pastikan "Background graphics" dicentang di menu print.</div>
    </div>

    <div class="wrapper">
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
                        <tr><td class="lbl">To</td><td class="sep">:</td><td><strong><?= htmlspecialchars($do['company_name'] ?? '') ?></strong></td></tr>
                        <tr><td class="lbl">Address</td><td class="sep">:</td><td><?= nl2br(htmlspecialchars($do['address'] ?? '')) ?></td></tr>
                        <tr><td class="lbl">Attn.</td><td class="sep">:</td><td><?= htmlspecialchars($do['pic_name'] ?? '') ?></td></tr>
                    </table>
                </td>
                <td style="width:4%"></td>
                <td class="info-box">
                    <table class="inner-table">
                        <tr><td class="lbl">Delivery Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($do['do_date'])) ?></td></tr>
                        <tr><td class="lbl">Delivery No</td><td class="sep">:</td><td><strong><?= $do['do_number'] ?></strong></td></tr>
                        <tr><td class="lbl">Contact</td><td class="sep">:</td><td><?= htmlspecialchars($do['pic_name'] ?? '') ?></td></tr>
                        <tr><td class="lbl">Tel</td><td class="sep">:</td><td><?= htmlspecialchars($do['pic_phone'] ?? '') ?></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">No</th><th width="35%">Item</th><th width="10%">Unit</th><th width="20%">Charge Mode</th><th width="30%">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no=1; 
                // --- LOGIKA TOTAL UNIT PINTAR (Permintaan: Jangan dijumlah jika ada Fee) ---
                $maxQty = 0; // Mencari nilai tertinggi
                
                foreach($itemsData as $itm) {
                    $qty = floatval($itm['qty']);
                    if($qty > $maxQty) $maxQty = $qty;
                }
                // Total Unit = Angka Terbesar yang ditemukan (Misal: 10 Kartu + 10 Fee = Total 10)
                $finalTotal = $maxQty;

                // Tampilkan Baris Item
                foreach($itemsData as $itm): 
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td class="text-left"><?= htmlspecialchars($itm['item_name']) ?></td>
                    <td><?= floatval($itm['qty']) ?></td>
                    <td><?= htmlspecialchars($itm['charge_mode']) ?></td>
                    <td class="text-left"><?= htmlspecialchars($itm['description']) ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($itemsData)): ?>
                    <tr><td colspan="5" style="padding:15px; color:red;">Item data is currently empty/not saved. Please Edit & Save.</td></tr>
                <?php endif; ?>

                <tr>
                    <td colspan="2" class="text-right" style="font-weight:bold;">Total Unit</td>
                    <td style="font-weight:bold;"><?= $finalTotal ?></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <table class="footer-table">
            <tr>
                <td class="footer-col remarks-col">
                    <strong>Remarks :</strong>
                    <ul style="padding-left:15px; margin-top:5px;">
                        <li>Please sign and stamp this delivery order</li>
                        <li>Please send it via email and whatsapp</li>
                        <li>Barang yang sudah dibeli tidak dapat dikembalikan</li>
                    </ul>
                </td>
                <td class="footer-col sender-col">
                    <div class="sign-title">Sender</div>
                    <div class="sign-area">
                        <?php 
                            $signFile = $do['sender_sign']; $src = '';
                            $baseDir = dirname(__DIR__);
                            
                            // Auto Search Signature
                            if(!empty($signFile) && file_exists($baseDir."/uploads/signatures/$signFile")) {
                                $src="../uploads/signatures/$signFile";
                            } elseif(!empty($do['sender_id'])) {
                                $files = glob($baseDir."/uploads/signatures/SIG_*_".$do['sender_id']."_*.png");
                                if($files) $src = "../uploads/signatures/" . basename($files[0]);
                            }
                            if(!$src && file_exists($baseDir."/assets/images/signature.png")) {
                                $src="../assets/images/signature.png";
                            }
                        ?>
                        <?php if($src): ?><img src="<?= $src ?>" class="sign-img"><?php endif; ?>
                    </div>
                    <div class="sign-name"><?= htmlspecialchars($do['sender_name']) ?></div>
                </td>
                <td class="footer-col recipient-col">
                    <div class="sign-title">Recipient</div>
                    <div class="sign-area"><div class="sign-line"></div></div>
                    <div class="sign-name"><?= htmlspecialchars($do['pic_name']) ?></div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>