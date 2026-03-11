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
        
        body { font-family: 'Inter', sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #f1f5f9; }
        
        @page { size: A4 portrait; margin: 0; }
        
        /* Print Overrides */
        @media print {
            body { background-color: #ffffff; }
            .no-print { display: none !important; }
            .print-container { box-shadow: none !important; margin: 0 !important; max-width: 100% !important; border-radius: 0 !important; padding: 1.5cm !important; }
            [contenteditable="true"]:hover { background: transparent !important; box-shadow: none !important; outline: none !important; }
            ::-webkit-scrollbar { display: none; }
        }

        /* Editable Hover Effects (Hanya tampil di layar) */
        [contenteditable="true"] { transition: all 0.2s; outline: none; }
        [contenteditable="true"]:hover { background-color: #fef08a; box-shadow: 0 0 0 4px #fef08a; border-radius: 2px; cursor: text; }
        [contenteditable="true"]:focus { background-color: #fef08a; box-shadow: 0 0 0 4px #fef08a; border-radius: 2px; cursor: text; border-bottom: 2px dashed #eab308; }
    </style>
</head>
<body class="text-slate-800 antialiased py-8 print:py-0">

    <div class="no-print fixed bottom-8 right-8 flex flex-col items-end gap-3 z-50">
        <div class="bg-white px-5 py-3 rounded-2xl shadow-xl border border-slate-200 max-w-sm text-right animate-bounce">
            <p class="text-xs font-bold text-rose-500 mb-1"><i class="ph-fill ph-info"></i> Edit Mode Active</p>
            <p class="text-[10px] text-slate-500">Klik pada area teks (Alamat, Item, Harga, Remarks) untuk melakukan <strong class="text-slate-800">edit manual</strong> sebelum dokumen dicetak.</p>
        </div>
        <button onclick="window.print()" class="group flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 px-8 rounded-full shadow-lg shadow-indigo-600/40 transition-all hover:scale-105 active:scale-95">
            <i class="ph-bold ph-printer text-2xl group-hover:animate-pulse"></i> 
            <span class="text-lg">Print Document</span>
        </button>
    </div>

    <div class="print-container bg-white w-full max-w-[210mm] mx-auto p-[1.5cm] min-h-[297mm] shadow-2xl rounded-sm relative overflow-hidden">
        
        <div class="flex justify-between items-start border-b-[3px] border-slate-800 pb-6 mb-8">
            <div class="flex items-center gap-5">
                <img src="../uploads/<?= $sets['company_logo'] ?? 'default-logo.png' ?>" class="h-16 object-contain" onerror="this.style.display='none'">
                <div class="max-w-[250px]">
                    <div class="text-[10px] text-slate-600 leading-relaxed font-medium">
                        <?= nl2br(htmlspecialchars($sets['company_address_full'] ?? '')) ?>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <h1 class="text-4xl font-black tracking-tight text-indigo-600 uppercase mb-1">Quotation</h1>
                <p class="text-sm font-bold text-slate-500 tracking-widest font-mono">#<?= $quo['quotation_no'] ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-8">
            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3 flex items-center gap-1.5"><i class="ph-fill ph-buildings text-indigo-500 text-sm"></i> Prepared For</h3>
                <div class="font-black text-slate-800 text-sm mb-1.5" <?= $can_edit_note ?>><?= htmlspecialchars($quo['company_name']) ?></div>
                <div class="text-[11px] text-slate-600 leading-relaxed mb-3" <?= $can_edit_note ?>><?= nl2br(htmlspecialchars($quo['c_address'])) ?></div>
                
                <div class="border-t border-slate-200 pt-3">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Attention:</p>
                    <p class="text-[11px] font-bold text-slate-800" <?= $can_edit_note ?>><?= htmlspecialchars($quo['pic_name']) ?></p>
                    <p class="text-[10px] text-slate-500 font-medium" <?= $can_edit_note ?>><?= htmlspecialchars($quo['pic_phone']) ?></p>
                </div>
            </div>

            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3 flex items-center gap-1.5"><i class="ph-fill ph-file-text text-indigo-500 text-sm"></i> Document Details</h3>
                
                <table class="w-full text-[11px]">
                    <tbody>
                        <tr>
                            <td class="py-1 text-slate-500 font-medium w-32">Date</td>
                            <td class="py-1 font-bold text-slate-800" <?= $can_edit_note ?>><?= date('F d, Y', strtotime($quo['quotation_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 text-slate-500 font-medium">Currency</td>
                            <td class="py-1 font-bold text-slate-800 border-b border-slate-200 pb-2"><span class="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded text-[10px] font-black tracking-widest"><?= $quo['currency'] ?></span></td>
                        </tr>
                        <tr><td colspan="2" class="h-2"></td></tr>
                        <tr>
                            <td class="py-1 text-slate-500 font-medium pt-2">Contact Person</td>
                            <td class="py-1 font-bold text-slate-800 pt-2" <?= $can_edit_note ?>><?= htmlspecialchars($quo['sales_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 text-slate-500 font-medium">Email</td>
                            <td class="py-1 font-medium text-slate-800" <?= $can_edit_note ?>><?= htmlspecialchars($quo['sales_email']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 text-slate-500 font-medium">Phone</td>
                            <td class="py-1 font-medium text-slate-800" <?= $can_edit_note ?>><?= htmlspecialchars($quo['sales_phone']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="border border-slate-800 rounded-xl overflow-hidden mb-6">
            <table class="w-full text-left text-[11px]">
                <thead class="bg-slate-800 text-white font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 text-center w-[5%]">No</th>
                        <th class="py-3 px-4 w-[35%]">Description / Item</th>
                        <th class="py-3 px-4 text-center w-[10%]">Qty</th>
                        <th class="py-3 px-4 text-center w-[15%]">Charge Mode</th>
                        <th class="py-3 px-4 text-right w-[15%]">Unit Price</th>
                        <th class="py-3 px-4 text-right w-[20%]">Line Total</th>
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
                        <td class="py-3 px-4 text-center font-medium text-slate-500"><?= $no++ ?></td>
                        <td class="py-3 px-4">
                            <div class="font-bold text-slate-800" <?= $can_edit_note ?>><?= htmlspecialchars($item['item_name']) ?></div>
                            <?php if(!empty($item['description']) && $item['description'] != 'Exclude Tax'): ?>
                                <div class="text-[10px] text-slate-500 mt-0.5 leading-snug" <?= $can_edit_note ?>><?= nl2br(htmlspecialchars($item['description'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-slate-800" <?= $can_edit_note ?>><?= $qty ?></td> 
                        <td class="py-3 px-4 text-center">
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-widest border border-slate-200" <?= $can_edit_note ?>>
                                <?= htmlspecialchars($item['card_type']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right font-medium text-slate-700" <?= $can_edit_note ?>><?= format_money($price, $is_intl) ?></td>
                        <td class="py-3 px-4 text-right font-bold text-slate-800" <?= $can_edit_note ?>><?= format_money($lineTotal, $is_intl) ?></td>
                    </tr>
                    <?php endforeach; ?>
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
        <div class="flex justify-end mb-10">
            <div class="w-1/2 rounded-2xl bg-slate-50 border border-slate-200 p-4">
                <table class="w-full text-[11px]">
                    <tbody>
                        <tr>
                            <td class="py-1.5 text-slate-500 font-bold uppercase tracking-widest text-[10px]">Sub Total</td>
                            <td class="py-1.5 text-right font-bold text-slate-800" <?= $can_edit_note ?>><?= format_money($grandTotal, $is_intl) ?></td>
                        </tr>
                        <?php if(!$is_intl): ?>
                        <tr>
                            <td class="py-1.5 text-slate-500 font-bold uppercase tracking-widest text-[10px] border-b border-slate-200 pb-3">VAT (11%)</td>
                            <td class="py-1.5 text-right font-bold text-slate-800 border-b border-slate-200 pb-3" <?= $can_edit_note ?>><?= format_money($vatAmount, $is_intl) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="py-2 pt-3 text-indigo-600 font-black uppercase tracking-widest text-xs">Total (<?= $quo['currency'] ?>)</td>
                            <td class="py-2 pt-3 text-right font-black text-indigo-600 text-sm" <?= $can_edit_note ?>><?= format_money($totalQuotation, $is_intl) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-5 gap-8 mt-auto page-break-inside-avoid">
            
            <div class="col-span-3">
                <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-4 h-full">
                    <h4 class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-2 flex items-center gap-1.5"><i class="ph-fill ph-info"></i> Remarks & Notes</h4>
                    <div class="text-[10px] text-slate-600 leading-relaxed whitespace-pre-wrap font-medium" <?= $can_edit_note ?>><?= htmlspecialchars($remarks) ?></div>
                </div>
            </div>

            <div class="col-span-2 text-center flex flex-col justify-end">
                <p class="text-[11px] font-bold text-slate-800 mb-2">PT. Linksfield Networks Indonesia</p>
                
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
                
                <div class="h-24 flex items-center justify-center my-2">
                    <?php if (!empty($signPath)): ?>
                        <img src="<?= $signPath ?>" class="max-h-full max-w-[200px] object-contain">
                    <?php else: ?>
                        <div class="w-40 h-20 border-2 border-dashed border-slate-300 rounded-xl flex items-center justify-center text-[10px] font-bold text-slate-400">
                            (Signature Missing)
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="inline-block border-b border-slate-800 pb-1 px-4 mb-1 font-bold text-[11px]" <?= $can_edit_note ?>>
                    <?= htmlspecialchars($signerName) ?>
                </div>
                <p class="text-[10px] text-slate-500 font-medium">Authorized Signature</p>
            </div>
            
        </div>

    </div>

    <script>
        window.onload = function() {
            // Optional: Hapus komentar di bawah jika ingin otomatis print saat halaman terbuka
            // window.print();
        };
    </script>
</body>
</html>