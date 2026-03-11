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
if ($res) {
    while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];
}

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
$tax_rate = $is_intl ? 0 : 0.11;

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation #<?= $quo['quotation_no'] ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact; 
            background-color: #f1f5f9; 
            color: #1e293b; /* slate-800 */
        }
        
        /* Set Print to fit 1 page */
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
                height: 100vh !important; /* Paksa 1 halaman */
                display: flex;
                flex-direction: column;
            }
            
            /* Hindari tabel dan elemen patah ke halaman berikutnya */
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
            ::-webkit-scrollbar { display: none; }
        }

        /* Editable Hover Effects (Interactive Screen Mode) */
        [contenteditable="true"] { transition: all 0.2s; outline: none; }
        [contenteditable="true"]:hover { background-color: #fef08a; box-shadow: 0 0 0 4px #fef08a; border-radius: 2px; cursor: text; }
        [contenteditable="true"]:focus { background-color: #fef08a; box-shadow: 0 0 0 4px #fef08a; border-radius: 2px; cursor: text; border-bottom: 2px dashed #eab308; }
    </style>
</head>
<body class="py-8 print:py-0 text-[11px]">

    <div class="no-print fixed bottom-8 right-8 flex flex-col items-end gap-3 z-50">
        <div class="bg-white px-5 py-4 rounded-2xl shadow-xl border border-slate-200 max-w-sm text-right animate-bounce">
            <p class="text-xs font-bold text-indigo-600 mb-1 flex items-center justify-end gap-1.5"><i class="ph-fill ph-info"></i> Edit Mode Active</p>
            <p class="text-[10px] text-slate-500 mb-2">Klik pada area teks (Alamat, Item, Harga, Remarks) untuk melakukan <strong>edit manual</strong> sebelum dicetak.</p>
            <div class="inline-flex gap-2 text-[9px] font-bold uppercase tracking-widest bg-slate-100 px-2 py-1 rounded text-slate-500">
                <i class="ph-bold ph-globe"></i> Currency: <?= $quo['currency'] ?>
            </div>
        </div>
        <button onclick="window.print()" class="group flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 px-8 rounded-full shadow-lg shadow-indigo-600/40 transition-all hover:scale-105 active:scale-95">
            <i class="ph-bold ph-printer text-2xl group-hover:animate-pulse"></i> 
            <span class="text-lg">Print Document</span>
        </button>
    </div>

    <div class="print-container bg-white w-full max-w-[210mm] min-h-[297mm] mx-auto p-10 shadow-2xl rounded flex flex-col">
        
        <div class="flex justify-between items-center border-b-[3px] border-slate-800 pb-4 mb-5 shrink-0">
            <div class="w-1/3">
                <img src="../uploads/<?= $sets['company_logo'] ?? 'default-logo.png' ?>" class="max-h-12 object-contain" onerror="this.style.display='none'">
            </div>
            <div class="w-1/3 text-center">
                <h1 class="text-3xl font-black tracking-tight text-slate-900 uppercase">Quotation</h1>
            </div>
            <div class="w-1/3 text-right">
                <div class="text-[9px] text-slate-600 leading-snug font-medium text-right ml-auto max-w-[200px]">
                    <?= nl2br(htmlspecialchars($sets['company_address_full'] ?? '')) ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-5 shrink-0">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <h3 class="text-[9px] font-black uppercase tracking-widest text-indigo-600 mb-2 flex items-center gap-1.5"><i class="ph-fill ph-buildings text-sm"></i> Prepared For</h3>
                <div class="font-black text-slate-800 text-xs mb-1" <?= $can_edit_note ?>><?= htmlspecialchars($quo['company_name']) ?></div>
                <div class="text-[10px] text-slate-600 leading-snug mb-2" <?= $can_edit_note ?>><?= nl2br(htmlspecialchars($quo['c_address'])) ?></div>
                
                <div class="border-t border-slate-200 pt-2">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Attention:</p>
                    <p class="text-[10px] font-bold text-slate-800 leading-tight" <?= $can_edit_note ?>><?= htmlspecialchars($quo['pic_name']) ?></p>
                    <p class="text-[10px] text-slate-500 font-medium" <?= $can_edit_note ?>><?= htmlspecialchars($quo['pic_phone']) ?></p>
                </div>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <h3 class="text-[9px] font-black uppercase tracking-widest text-indigo-600 mb-2 flex items-center gap-1.5"><i class="ph-fill ph-file-text text-sm"></i> Document Details</h3>
                
                <table class="w-full text-[10px]">
                    <tbody>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium w-24">Quote No</td>
                            <td class="py-0.5 font-bold text-slate-800 font-mono">#<?= $quo['quotation_no'] ?></td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium">Date</td>
                            <td class="py-0.5 font-bold text-slate-800" <?= $can_edit_note ?>><?= date('F d, Y', strtotime($quo['quotation_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium pb-1.5">Currency</td>
                            <td class="py-0.5 font-bold text-slate-800 border-b border-slate-200 pb-1.5">
                                <span class="bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded text-[9px] font-black tracking-widest"><?= $quo['currency'] ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium pt-1.5">Contact Person</td>
                            <td class="py-0.5 font-bold text-slate-800 pt-1.5" <?= $can_edit_note ?>><?= htmlspecialchars($quo['sales_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium">Email</td>
                            <td class="py-0.5 font-medium text-slate-800" <?= $can_edit_note ?>><?= htmlspecialchars($quo['sales_email']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-0.5 text-slate-500 font-medium">Phone</td>
                            <td class="py-0.5 font-medium text-slate-800" <?= $can_edit_note ?>><?= htmlspecialchars($quo['sales_phone']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="border border-slate-800 rounded-lg overflow-hidden mb-4 shrink-0">
            <table class="w-full text-left text-[10px]">
                <thead class="bg-slate-800 text-white font-bold uppercase tracking-wider text-[9px]">
                    <tr>
                        <th class="py-2 px-3 text-center w-[5%]">No</th>
                        <th class="py-2 px-3 w-[40%]">Description / Item</th>
                        <th class="py-2 px-3 text-center w-[8%]">Qty</th>
                        <th class="py-2 px-3 text-center w-[15%]">Charge Mode</th>
                        <th class="py-2 px-3 text-right w-[15%]">Unit Price</th>
                        <th class="py-2 px-3 text-right w-[17%]">Line Total</th>
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
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-2 px-3 text-center font-medium text-slate-500 align-top"><?= $no++ ?></td>
                        <td class="py-2 px-3 align-top">
                            <div class="font-bold text-slate-800" <?= $can_edit_note ?>><?= htmlspecialchars($item['item_name']) ?></div>
                            <?php if(!empty($item['description']) && $item['description'] != 'Exclude Tax'): ?>
                                <div class="text-[9px] text-slate-500 mt-0.5 leading-snug" <?= $can_edit_note ?>><?= nl2br(htmlspecialchars($item['description'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-3 text-center font-bold text-slate-800 align-top" <?= $can_edit_note ?>><?= $qty ?></td> 
                        <td class="py-2 px-3 text-center align-top">
                            <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-widest border border-slate-200" <?= $can_edit_note ?>>
                                <?= htmlspecialchars($item['card_type']) ?>
                            </span>
                        </td>
                        <td class="py-2 px-3 text-right font-medium text-slate-700 align-top" <?= $can_edit_note ?>><?= format_money($price, $is_intl) ?></td>
                        <td class="py-2 px-3 text-right font-bold text-slate-800 align-top" <?= $can_edit_note ?>><?= format_money($lineTotal, $is_intl) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr>
                        <td colspan="6" class="py-3"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php 
            if ($is_intl) {
                $vatAmount = 0;
                $grandTotal = round($grandTotal, 2); 
            } else {
                $grandTotal = round($grandTotal, 0, PHP_ROUND_HALF_DOWN); 
                $vatAmount = round($grandTotal * $tax_rate, 0, PHP_ROUND_HALF_DOWN); 
            }
            $totalQuotation = $grandTotal + $vatAmount;
        ?>
        <div class="flex justify-end mb-4 shrink-0 avoid-break mt-auto">
            <div class="w-1/2 rounded-xl bg-slate-50 border border-slate-200 p-3">
                <table class="w-full text-[10px]">
                    <tbody>
                        <tr>
                            <td class="py-1 text-slate-500 font-bold uppercase tracking-widest text-[9px]">Sub Total</td>
                            <td class="py-1 text-right font-bold text-slate-800" <?= $can_edit_note ?>><?= format_money($grandTotal, $is_intl) ?></td>
                        </tr>
                        <?php if(!$is_intl): ?>
                        <tr>
                            <td class="py-1 text-slate-500 font-bold uppercase tracking-widest text-[9px] border-b border-slate-200 pb-2">VAT (11%)</td>
                            <td class="py-1 text-right font-bold text-slate-800 border-b border-slate-200 pb-2" <?= $can_edit_note ?>><?= format_money($vatAmount, $is_intl) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="py-2 pt-2 text-indigo-600 font-black uppercase tracking-widest text-[11px]">Total (<?= $quo['currency'] ?>)</td>
                            <td class="py-2 pt-2 text-right font-black text-indigo-600 text-sm" <?= $can_edit_note ?>><?= format_money($totalQuotation, $is_intl) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6 shrink-0 avoid-break">
            
            <div class="col-span-8">
                <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-3 h-full">
                    <h4 class="text-[9px] font-black text-indigo-600 uppercase tracking-widest mb-1.5 flex items-center gap-1.5"><i class="ph-fill ph-info text-sm"></i> Remarks & Notes</h4>
                    <div class="text-[9px] text-slate-700 leading-relaxed whitespace-pre-wrap font-medium" <?= $can_edit_note ?>><?= htmlspecialchars($remarks) ?></div>
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
                
                <div class="inline-block border-b-2 border-slate-800 pb-1 px-4 mb-0.5 font-bold text-[11px]" <?= $can_edit_note ?>>
                    <?= htmlspecialchars($signerName) ?>
                </div>
                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mt-0.5">Authorized Signature</p>
            </div>
            
        </div>

    </div>

</body>
</html>