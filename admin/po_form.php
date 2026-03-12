<?php
$page_title = "Create New Purchase Order";
include 'includes/header.php';
include 'includes/sidebar.php';
// include '../config/functions.php'; // Aktifkan jika diperlukan

// Ambil data User yang sedang Login (Kontak, Email, Phone)
$user_id = $_SESSION['user_id'] ?? 0;
$user_info = $conn->query("SELECT email, phone FROM users WHERE id = $user_id")->fetch_assoc();

// Ambil list Vendors
$vendors = $conn->query("SELECT id, company_name FROM vendors ORDER BY company_name ASC");

// --- FUNGSI PENOMORAN PO OTOMATIS ---
function generatePoNumber($conn) {
    $year_month = date("Ym");
    $prefix = "POLF" . $year_month; // Contoh: POLF202512

    // Ambil nomor urut tertinggi untuk bulan ini (4 digit)
    $sql = "SELECT MAX(SUBSTRING(po_number, 11)) as last_num FROM purchase_orders WHERE po_number LIKE '{$prefix}%'";
    $result = $conn->query($sql)->fetch_assoc();
    $last_num = (int)$result['last_num'];
    
    $new_num = $last_num + 1;
    // Format: POLFYYYYMMNNNN -> POLF2025120001
    return $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
}

// Inisialisasi data PO
$po_data = [
    'id' => 0,
    'po_number' => generatePoNumber($conn),
    'vendor_id' => '',
    'po_date' => date('Y-m-d'),
    'total_amount' => 0.00,
    'status' => 'Draft',
];
$po_items = [];
$is_edit = false;

// Logic Edit Mode (jika ada ID di URL)
if (isset($_GET['id'])) {
    $po_id = intval($_GET['id']);
    $po_data_res = $conn->query("SELECT * FROM purchase_orders WHERE id=$po_id");
    if ($po_data_res && $po_data_res->num_rows > 0) {
        $po_data = $po_data_res->fetch_assoc();
        $is_edit = true;
        
        // Ambil Item PO
        $po_items_res = $conn->query("SELECT * FROM po_items WHERE po_id=$po_id");
        while($item = $po_items_res->fetch_assoc()) {
            $po_items[] = $item;
        }
        $page_title = "Edit Purchase Order " . $po_data['po_number'];
    }
}

// --- LOGIC SAVE/SUBMIT PO ---
if (isset($_POST['save_po'])) {
    $po_id = intval($_POST['po_id']);
    $vendor_id = intval($_POST['vendor_id']);
    $po_date = $conn->real_escape_string($_POST['po_date']);
    $total_amount = floatval(str_replace(',', '', $_POST['final_total_amount']));
    $status = $conn->real_escape_string($_POST['status']);
    
    // Data Item dari JS
    $items_json = json_decode($_POST['po_items_data'], true);

    // 1. Simpan Header PO
    if ($po_id == 0) {
        $po_number = generatePoNumber($conn);
        $sql_header = "INSERT INTO purchase_orders (po_number, vendor_id, created_by_user_id, po_date, total_amount, status) 
                       VALUES ('$po_number', $vendor_id, $user_id, '$po_date', $total_amount, '$status')";
        $conn->query($sql_header);
        $po_id = $conn->insert_id;
        $msg = "Purchase Order $po_number Berhasil Dibuat!";
    } else {
        $po_number = $conn->real_escape_string($_POST['po_number']);
        $sql_header = "UPDATE purchase_orders SET vendor_id=$vendor_id, po_date='$po_date', total_amount=$total_amount, status='$status' WHERE id=$po_id";
        $conn->query($sql_header);
        // Hapus item lama jika edit
        $conn->query("DELETE FROM po_items WHERE po_id=$po_id");
        $msg = "Purchase Order $po_number Berhasil Diupdate!";
    }

    // 2. Simpan Item PO
    if (!empty($items_json) && $po_id > 0) {
        foreach ($items_json as $item) {
            $desc = $conn->real_escape_string($item['description']);
            $platform = $conn->real_escape_string($item['platform']);
            $sub = $conn->real_escape_string($item['sub']);
            $qty = floatval($item['qty']);
            $unit_price = floatval($item['unit_price']);
            $currency = $conn->real_escape_string($item['currency']);
            $total = floatval($item['total']);
            $charge_mode = $conn->real_escape_string($item['charge_mode']);

            $sql_item = "INSERT INTO po_items (po_id, description, platform, sub, qty, unit_price, currency, total, charge_mode) 
                         VALUES ($po_id, '$desc', '$platform', '$sub', $qty, $unit_price, '$currency', $total, '$charge_mode')";
            $conn->query($sql_item);
        }
    }

    echo "<script>alert('$msg'); window.location='po_list.php';</script>";
}
?>

<style>
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modern-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .modern-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .modern-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .modern-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-emerald-500/30">
                    <i class="ph-fill ph-shopping-cart"></i>
                </div>
                <?= $is_edit ? 'Edit Purchase Order' : 'Create New PO' ?>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Lengkapi formulir di bawah ini untuk menerbitkan dokumen Purchase Order.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="po_list.php" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-[#24303F] text-slate-600 dark:text-slate-300 font-bold py-3 px-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all hover:bg-slate-50 dark:hover:bg-slate-800 active:scale-95 whitespace-nowrap">
                <i class="ph-bold ph-arrow-left text-lg"></i> Back to List
            </a>
        </div>
    </div>

    <form method="POST" class="space-y-6">
        <input type="hidden" name="po_id" value="<?= $po_data['id'] ?>">
        <input type="hidden" name="po_number" value="<?= $po_data['po_number'] ?>">
        <input type="hidden" name="po_items_data" id="po_items_data">
        <input type="hidden" name="final_total_amount" id="final_total_amount">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800">
                <h3 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 mb-5 flex items-center gap-2">
                    <i class="ph-fill ph-file-text text-emerald-500 text-lg"></i> PO Information
                </h3>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PO Number</label>
                        <div class="relative group">
                            <i class="ph-bold ph-hash absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="text" class="w-full pl-11 pr-4 py-3 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-emerald-600 dark:text-emerald-400 outline-none cursor-not-allowed shadow-inner uppercase tracking-wider" value="<?= $po_data['po_number'] ?>" readonly>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Date of Issue <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-calendar-blank absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input type="date" name="po_date" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" value="<?= $po_data['po_date'] ?>" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Status Dokumen</label>
                        <div class="relative group">
                            <i class="ph-bold ph-list-dashes absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <select name="status" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="Draft" <?= $po_data['status'] == 'Draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="Submitted" <?= $po_data['status'] == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                <option value="Approved" <?= $po_data['status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="Rejected" <?= $po_data['status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#24303F] p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800">
                <h3 class="text-sm font-black text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 mb-5 flex items-center gap-2">
                    <i class="ph-fill ph-buildings text-teal-500 text-lg"></i> Vendor Details
                </h3>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Pilih Vendor <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <i class="ph-bold ph-storefront absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <select name="vendor_id" id="vendor_id" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-teal-500/50 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner" required>
                                <option value="">-- Pilih Vendor Tujuan --</option>
                                <?php if($vendors->num_rows > 0) { $vendors->data_seek(0); while($v = $vendors->fetch_assoc()): ?>
                                    <option value="<?= $v['id'] ?>" <?= $po_data['vendor_id'] == $v['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="bg-teal-50 dark:bg-teal-500/10 border border-teal-100 dark:border-teal-500/20 rounded-xl p-4 mt-2">
                        <p class="text-[10px] font-black text-teal-600 dark:text-teal-400 uppercase tracking-widest mb-2 flex items-center gap-1.5"><i class="ph-fill ph-user-circle text-sm"></i> PIC (Created By)</p>
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-500 dark:text-slate-400">Name</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($_SESSION['username'] ?? 'N/A') ?></span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-500 dark:text-slate-400">Email</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300"><?= htmlspecialchars($user_info['email'] ?? 'N/A') ?></span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-500 dark:text-slate-400">Phone</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300"><?= htmlspecialchars($user_info['phone'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30 flex justify-between items-center">
                <h3 class="text-sm font-black text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="ph-fill ph-package text-emerald-500 text-xl"></i> Purchase Order Items
                </h3>
                <button type="button" onclick="openItemModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20 dark:text-emerald-400 font-bold rounded-xl text-xs transition-colors border border-emerald-200 dark:border-emerald-500/20 shadow-sm active:scale-95">
                    <i class="ph-bold ph-plus"></i> Add Item
                </button>
            </div>

            <div class="overflow-x-auto modern-scrollbar w-full">
                <table class="w-full text-left border-collapse table-auto min-w-[900px]" id="itemsTable">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[5%] text-center">#</th>
                            <th class="px-4 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[35%]">Description / Platform / Sub</th>
                            <th class="px-4 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[15%] text-center">Charge Mode</th>
                            <th class="px-4 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[10%] text-center">Qty</th>
                            <th class="px-4 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[15%] text-right">Unit Price</th>
                            <th class="px-4 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[15%] text-right">Line Total</th>
                            <th class="px-4 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[5%] text-center">Act</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50 text-[11px]">
                        </tbody>
                </table>
                
                <div id="noItemMessage" class="py-12 text-center" style="display:none;">
                    <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                        <div class="w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700 shadow-inner">
                            <i class="ph-fill ph-package text-3xl text-slate-300 dark:text-slate-600"></i>
                        </div>
                        <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mb-0.5">Item Kosong</h4>
                        <p class="text-[11px] font-medium">Silakan klik tombol "Add Item" untuk menambahkan barang.</p>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30 flex justify-end">
                <div class="flex items-center gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-6 py-4 rounded-2xl shadow-sm">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Grand Total</span>
                    <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight" id="grandTotalDisplay">IDR 0.00</span>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-40 bg-white/90 dark:bg-[#1A222C]/90 backdrop-blur-md p-4 sm:p-6 rounded-3xl shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.1)] border border-slate-200 dark:border-slate-700 flex flex-col-reverse sm:flex-row justify-end items-center gap-3">
            <a href="po_list.php" class="w-full sm:w-auto px-8 py-3.5 rounded-2xl font-bold text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors text-center border border-transparent dark:border-slate-600">
                Cancel
            </a>
            <button type="submit" name="save_po" class="w-full sm:w-auto px-10 py-3.5 rounded-2xl font-bold text-sm text-white bg-emerald-500 hover:bg-emerald-600 shadow-lg shadow-emerald-500/30 transition-all flex items-center justify-center gap-2 active:scale-95">
                <i class="ph-bold ph-floppy-disk text-xl"></i> Save Purchase Order
            </button>
        </div>
    </form>
</div>

<div id="itemModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeItemModal()"></div>
    
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-2xl transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col max-h-[90vh] overflow-hidden">
        
        <div class="px-6 py-5 border-b border-emerald-500/20 bg-emerald-600 dark:bg-emerald-700 text-white flex justify-between items-center shrink-0">
            <h3 class="text-base font-black flex items-center gap-2 tracking-wide"><i class="ph-bold ph-package text-xl"></i> Add / Edit Item</h3>
            <button type="button" onclick="closeItemModal()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                <i class="ph-bold ph-x text-lg"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto modern-scrollbar">
            <input type="hidden" id="itemIndex" value="-1">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Description <span class="text-rose-500">*</span></label>
                    <textarea id="item_description" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner resize-none" rows="2" required placeholder="Nama item / Deskripsi layanan..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Platform</label>
                        <input type="text" id="item_platform" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="Tsel / XA / dll...">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Sub</label>
                        <input type="text" id="item_sub" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" placeholder="Csp / Corporate...">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Qty <span class="text-rose-500">*</span></label>
                        <input type="number" id="item_qty" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-black text-center focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" min="0" step="0.01" value="1" required oninput="calculateTotal()">
                    </div>
                    
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Unit Price <span class="text-rose-500">*</span></label>
                        <div class="flex">
                            <select id="item_currency" class="bg-slate-100 dark:bg-slate-800 border border-r-0 border-slate-200 dark:border-slate-700 rounded-l-xl text-xs font-bold px-3 focus:outline-none dark:text-white" onchange="calculateTotal()">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                            </select>
                            <input type="number" id="item_unit_price" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-r-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all shadow-inner" min="0" step="0.01" required oninput="calculateTotal()" placeholder="0.00">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Charge Mode</label>
                    <select id="item_charge_mode_select" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white appearance-none cursor-pointer shadow-inner mb-2" onchange="toggleChargeModeInput(this.value)">
                        <option value="">-- Select Mode --</option>
                        <option value="TBD">TBD</option>
                        <option value="Per Service">Per Service</option>
                        <option value="Per Project">Per Project</option>
                        <option value="Per Month">Per Month</option>
                        <option value="Other">Other (Specify manually)</option>
                    </select>
                    <input type="text" id="item_charge_mode_manual" class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-emerald-500/50 outline-none dark:text-white transition-all hidden shadow-inner" placeholder="Ketik Charge Mode...">
                </div>

                <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-2xl p-4 mt-2">
                    <label class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-1 text-center">Calculated Line Total</label>
                    <input type="text" id="item_total_display" class="w-full bg-transparent text-center text-xl font-black text-emerald-700 dark:text-emerald-400 outline-none border-none pointer-events-none" readonly value="IDR 0.00">
                    <input type="hidden" id="item_total">
                </div>

            </div>
        </div>
        
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3 shrink-0">
            <button type="button" onclick="closeItemModal()" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-sm shadow-sm">Batal</button>
            <button type="button" onclick="saveItem()" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-md shadow-emerald-500/30 flex items-center justify-center gap-2 active:scale-95">
                <i class="ph-bold ph-check text-lg"></i> Simpan Item
            </button>
        </div>
    </div>
</div>

<script>
    let poItems = <?= json_encode($po_items) ?>;
    const defaultCurrency = 'IDR';
    const currencyFormat = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: defaultCurrency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    document.addEventListener('DOMContentLoaded', function() {
        if (poItems.length > 0) {
            renderItems();
        } else {
            document.getElementById('noItemMessage').style.display = 'block';
        }
        updateFinalTotal();
    });

    // --- MODAL HANDLERS ---
    function openItemModal(index = -1) {
        const modal = document.getElementById('itemModal');
        const box = modal.querySelector('.modal-box');
        
        // Reset Form
        document.getElementById('itemIndex').value = index;
        document.getElementById('item_description').value = '';
        document.getElementById('item_platform').value = '';
        document.getElementById('item_sub').value = '';
        document.getElementById('item_qty').value = 1;
        document.getElementById('item_unit_price').value = '';
        document.getElementById('item_currency').value = defaultCurrency;
        document.getElementById('item_charge_mode_select').value = '';
        document.getElementById('item_charge_mode_manual').style.display = 'none';
        
        calculateTotal();

        // Jika Edit Mode
        if (index > -1) {
            const item = poItems[index];
            document.getElementById('item_description').value = item.description;
            document.getElementById('item_platform').value = item.platform;
            document.getElementById('item_sub').value = item.sub;
            document.getElementById('item_qty').value = item.qty;
            document.getElementById('item_unit_price').value = item.unit_price;
            document.getElementById('item_currency').value = item.currency;
            
            const chargeModeSelect = document.getElementById('item_charge_mode_select');
            const chargeModeManual = document.getElementById('item_charge_mode_manual');
            
            let isFound = false;
            for(let i=0; i<chargeModeSelect.options.length; i++) {
                if(chargeModeSelect.options[i].value === item.charge_mode) {
                    isFound = true;
                    break;
                }
            }

            if (isFound) {
                chargeModeSelect.value = item.charge_mode;
                chargeModeManual.style.display = 'none';
            } else {
                chargeModeSelect.value = 'Other';
                chargeModeManual.value = item.charge_mode;
                chargeModeManual.style.display = 'block';
            }
            calculateTotal(); 
        }

        // Show Animate
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
        }, 10);
    }

    function closeItemModal() {
        const modal = document.getElementById('itemModal');
        const box = modal.querySelector('.modal-box');
        modal.classList.add('opacity-0');
        box.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // --- Kalkulasi ---
    function calculateTotal() {
        const qty = parseFloat(document.getElementById('item_qty').value) || 0;
        const price = parseFloat(document.getElementById('item_unit_price').value) || 0;
        const currency = document.getElementById('item_currency').value;
        const total = qty * price;
        
        document.getElementById('item_total').value = total.toFixed(2);
        
        let formatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        document.getElementById('item_total_display').value = formatter.format(total);
    }

    function toggleChargeModeInput(value) {
        const manualInput = document.getElementById('item_charge_mode_manual');
        if (value === 'Other') {
            manualInput.style.display = 'block';
            manualInput.value = '';
            manualInput.classList.remove('hidden');
        } else {
            manualInput.style.display = 'none';
            manualInput.classList.add('hidden');
        }
    }

    function saveItem() {
        const index = parseInt(document.getElementById('itemIndex').value);
        const description = document.getElementById('item_description').value.trim();
        
        if (!description || parseFloat(document.getElementById('item_qty').value) <= 0 || parseFloat(document.getElementById('item_unit_price').value) <= 0) {
            alert('Deskripsi, Qty, dan Unit Price harus diisi dengan nilai yang valid.');
            return;
        }

        let chargeMode = document.getElementById('item_charge_mode_select').value;
        if (chargeMode === 'Other') {
            chargeMode = document.getElementById('item_charge_mode_manual').value.trim();
        } else if (chargeMode === '') {
            chargeMode = 'N/A';
        }
        
        const newItem = {
            description: description,
            platform: document.getElementById('item_platform').value,
            sub: document.getElementById('item_sub').value,
            qty: parseFloat(document.getElementById('item_qty').value).toFixed(2),
            unit_price: parseFloat(document.getElementById('item_unit_price').value).toFixed(2),
            currency: document.getElementById('item_currency').value,
            total: parseFloat(document.getElementById('item_total').value).toFixed(2),
            charge_mode: chargeMode,
        };

        if (index > -1) {
            poItems[index] = newItem; 
        } else {
            poItems.push(newItem);
        }
        
        renderItems();
        closeItemModal();
    }

    function deleteItem(index) {
        if (confirm('Yakin ingin menghapus item ini?')) {
            poItems.splice(index, 1);
            renderItems();
        }
    }

    function renderItems() {
        const tbody = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
        tbody.innerHTML = '';
        
        if (poItems.length === 0) {
            document.getElementById('noItemMessage').style.display = 'block';
            document.getElementById('itemsTable').style.display = 'none';
        } else {
            document.getElementById('noItemMessage').style.display = 'none';
            document.getElementById('itemsTable').style.display = 'table';
            
            poItems.forEach((item, index) => {
                let formatter = new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: item.currency,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });

                const row = tbody.insertRow();
                row.className = "hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors group";
                row.innerHTML = `
                    <td class="px-4 py-4 text-center font-medium text-slate-500 align-top">${index + 1}</td>
                    <td class="px-4 py-4 align-top">
                        <div class="font-bold text-slate-800 dark:text-slate-200">${item.description}</div>
                        <div class="text-[9px] text-slate-500 dark:text-slate-400 mt-1 uppercase tracking-widest font-bold flex gap-2">
                            <span>PLAT: ${item.platform || '-'}</span> 
                            <span>SUB: ${item.sub || '-'}</span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-center align-top">
                        <span class="inline-block px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 text-[9px] font-black uppercase tracking-widest">
                            ${item.charge_mode}
                        </span>
                    </td>
                    <td class="px-4 py-4 text-center font-bold text-slate-800 dark:text-white align-top">${item.qty}</td>
                    <td class="px-4 py-4 text-right font-medium text-slate-600 dark:text-slate-300 align-top">${formatter.format(item.unit_price)}</td>
                    <td class="px-4 py-4 text-right font-black text-slate-800 dark:text-emerald-400 align-top">${formatter.format(item.total)}</td>
                    <td class="px-4 py-4 text-center align-top">
                        <div class="flex items-center justify-center gap-1.5 opacity-50 group-hover:opacity-100 transition-opacity">
                            <button type="button" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 hover:bg-emerald-500 hover:text-white dark:bg-slate-700 dark:text-slate-400 transition-colors flex items-center justify-center" onclick="openItemModal(${index})"><i class="ph-bold ph-pencil-simple"></i></button>
                            <button type="button" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white dark:bg-rose-500/10 dark:text-rose-400 transition-colors flex items-center justify-center" onclick="deleteItem(${index})"><i class="ph-bold ph-trash"></i></button>
                        </div>
                    </td>
                `;
            });
        }
        updateFinalTotal();
        
        // Update hidden JSON input for PHP submission
        document.getElementById('po_items_data').value = JSON.stringify(poItems);
    }
    
    function updateFinalTotal() {
        let totalIDR = 0;
        let totalUSD = 0;

        poItems.forEach(item => {
            if (item.currency === 'IDR') {
                totalIDR += parseFloat(item.total);
            } else if (item.currency === 'USD') {
                totalUSD += parseFloat(item.total);
            }
        });

        let displayHTML = '';
        if (totalIDR > 0) {
            displayHTML += currencyFormat.format(totalIDR);
        }
        if (totalUSD > 0) {
            if (displayHTML) displayHTML += ' + ';
            displayHTML += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(totalUSD);
        }
        
        if (!displayHTML) displayHTML = 'IDR 0.00';

        document.getElementById('grandTotalDisplay').innerHTML = displayHTML;
        
        // Set IDR total to be saved in the database
        document.getElementById('final_total_amount').value = totalIDR.toFixed(2); 
    }
</script>

<?php include 'includes/footer.php'; ?>