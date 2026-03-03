<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. INIT FILTER VARIABLES ---
$search    = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client  = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';
$f_status  = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';

// Bangun Query WHERE Dasar (Hanya PO Received atau Invoiced)
$where = "q.status IN ('po_received', 'invoiced')";

// Filter Text (PO No, Quote No)
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (q.po_number_client LIKE '%$safe_search%' OR q.quotation_no LIKE '%$safe_search%')";
}

// Filter Client
if (!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND q.client_id = $safe_client";
}

// Filter Status
if (!empty($f_status)) {
    $safe_status = $conn->real_escape_string($f_status);
    $where .= " AND q.status = '$safe_status'";
}

// --- 3. LOGIKA EXPORT EXCEL (CSV) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    $sqlEx = "SELECT q.*, c.company_name, c.pic_name, u.username 
              FROM quotations q 
              JOIN clients c ON q.client_id = c.id 
              JOIN users u ON q.created_by_user_id = u.id 
              WHERE $where 
              ORDER BY q.created_at DESC";
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=PO_Client_Data_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, array('PO Number', 'Quote Ref', 'Date', 'Client', 'PIC', 'Total Amount', 'Currency', 'Status', 'Sales Person', 'PO File'));
    
    while($row = $resEx->fetch_assoc()) {
        $qId = $row['id'];
        // Hitung Total Amount PO
        $total = $conn->query("SELECT SUM(qty * unit_price) as t FROM quotation_items WHERE quotation_id = $qId")->fetch_assoc()['t'];
        
        fputcsv($output, array(
            $row['po_number_client'],
            $row['quotation_no'],
            $row['quotation_date'],
            $row['company_name'],
            $row['pic_name'],
            $total,
            $row['currency'],
            $row['status'],
            $row['username'],
            $row['po_file_client'] ? 'Uploaded' : 'Pending'
        ));
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "PO From Client";
include 'includes/header.php';
include 'includes/sidebar.php';

// Ambil List Client untuk Filter
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// LOGIKA CANCEL PO
if (isset($_GET['cancel_id'])) {
    $q_id = intval($_GET['cancel_id']);
    $conn->query("UPDATE quotations SET status='cancel' WHERE id=$q_id");
    echo "<script>alert('PO dan Quotation berhasil dibatalkan!'); window.location='po_client_list.php';</script>";
}

// LOGIKA PROCESS TO INVOICE
if (isset($_GET['process_invoice_id'])) {
    $q_id = intval($_GET['process_invoice_id']);
    echo "<script>window.location='invoice_form.php?source_id=$q_id';</script>";
}

// LOGIKA UPLOAD PO DOC (BARU)
if (isset($_POST['upload_po_doc'])) {
    $q_id = intval($_POST['quotation_id']);
    $uploadDir = __DIR__ . '/../uploads/';
    
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['po_file']) && $_FILES['po_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['po_file']['name'], PATHINFO_EXTENSION));
        // Validasi tipe file
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $fileName = 'PO_' . time() . '_' . $q_id . '.' . $ext;
            if (move_uploaded_file($_FILES['po_file']['tmp_name'], $uploadDir . $fileName)) {
                $sqlUp = "UPDATE quotations SET po_file_client = '$fileName' WHERE id = $q_id";
                if ($conn->query($sqlUp)) {
                    echo "<script>alert('Dokumen PO berhasil diupload!'); window.location='po_client_list.php';</script>";
                } else {
                    echo "<script>alert('Gagal update database.');</script>";
                }
            } else {
                echo "<script>alert('Gagal memindahkan file ke server.');</script>";
            }
        } else {
            echo "<script>alert('Format file tidak didukung. Harap gunakan PDF, JPG, atau PNG.');</script>";
        }
    } else {
        echo "<script>alert('Silakan pilih file terlebih dahulu.');</script>";
    }
}

// QUERY DATA TAMPILAN
$sql = "SELECT q.*, c.company_name, u.username 
        FROM quotations q 
        JOIN clients c ON q.client_id = c.id 
        JOIN users u ON q.created_by_user_id = u.id 
        WHERE $where
        ORDER BY q.created_at DESC";
$res = $conn->query($sql);
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 animate-fade-in-up">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Client Purchase Orders</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Daftar PO yang diterima dari klien dan siap diproses menjadi Invoice.</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/80 rounded-t-2xl" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-base"></i> Filter Data & Export
            </h3>
            <i id="filterIcon" class="ph-bold ph-caret-up text-slate-400 transition-transform duration-300"></i>
        </div>
        
        <div id="filterBody" class="p-5 block transition-all duration-300">
            <form method="GET" id="filterForm">
                <div class="flex flex-col xl:flex-row gap-4 xl:items-end">
                    
                    <div class="w-full xl:w-64 shrink-0">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">No PO / No Quote</label>
                        <div class="relative">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="search" class="w-full pl-8 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[11px] font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400" placeholder="e.g. PO-..." value="<?= htmlspecialchars($search ?? '') ?>">
                        </div>
                    </div>

                    <div class="w-full xl:w-64 shrink-0">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Perusahaan Klien</label>
                        <div class="relative">
                            <select name="client_id" class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[11px] font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">- Semua Client -</option>
                                <?php if($clients->num_rows > 0) { $clients->data_seek(0); while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="w-full xl:w-48 shrink-0 flex-1">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Status Dokumen</label>
                        <div class="relative">
                            <select name="status" class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[11px] font-bold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer">
                                <option value="">- Semua Status -</option>
                                <option value="po_received" <?= $f_status=='po_received'?'selected':'' ?>>Pending Invoice</option>
                                <option value="invoiced" <?= $f_status=='invoiced'?'selected':'' ?>>Invoiced</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="w-full xl:w-auto flex gap-2 shrink-0">
                        <button type="submit" class="flex-1 xl:flex-none bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white font-bold py-2 px-4 rounded-xl transition-colors text-[11px] shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                            <i class="ph-bold ph-funnel text-sm"></i> Filter
                        </button>
                        
                        <button type="submit" formmethod="POST" name="export_excel" class="flex-1 xl:flex-none bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 px-4 rounded-xl transition-colors text-[11px] shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                            <i class="ph-bold ph-file-csv text-sm"></i> Export
                        </button>

                        <?php if(!empty($search) || !empty($f_client) || !empty($f_status)): ?>
                            <a href="po_client_list.php" class="flex-none bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold py-2 px-3 rounded-xl transition-colors text-[11px] text-center border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-sm"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="overflow-x-auto w-full pb-20">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                    <tr>
                        <th class="px-5 py-3.5 whitespace-nowrap w-[20%]">PO Details</th>
                        <th class="px-5 py-3.5 w-[30%]">Client Info</th>
                        <th class="px-5 py-3.5 text-center whitespace-nowrap w-[15%]">Document</th>
                        <th class="px-5 py-3.5 text-center whitespace-nowrap w-[15%]">Status</th>
                        <th class="px-5 py-3.5 text-center whitespace-nowrap w-[10%]">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px]">
                    <?php if($res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-5 py-3 align-middle whitespace-nowrap">
                                <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-[12px] mb-0.5">
                                    <?= htmlspecialchars($row['po_number_client'] ?? 'N/A') ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">
                                    Ref: <span class="font-mono bg-slate-100 dark:bg-slate-700 px-1 rounded"><?= htmlspecialchars($row['quotation_no'] ?? '') ?></span>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 leading-snug truncate max-w-[250px]" title="<?= htmlspecialchars($row['company_name'] ?? '') ?>">
                                    <?= htmlspecialchars($row['company_name'] ?? '') ?>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1 font-medium whitespace-nowrap">
                                    <i class="ph-fill ph-user-circle"></i> <?= htmlspecialchars($row['username'] ?? '') ?>
                                </div>
                            </td>

                            <td class="px-5 py-3 align-middle text-center whitespace-nowrap">
                                <?php if($row['po_file_client']): ?>
                                    <a href="../uploads/<?= $row['po_file_client'] ?>" target="_blank" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 dark:bg-slate-700 dark:hover:bg-indigo-500/20 dark:text-slate-300 dark:hover:text-indigo-400 transition-colors border border-slate-200 dark:border-slate-600 font-bold text-[10px] tracking-wide">
                                        <i class="ph-bold ph-file-pdf text-sm"></i> View Doc
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-400 dark:text-slate-500 italic text-[10px]">- Not Uploaded -</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-3 align-middle text-center whitespace-nowrap">
                                <?php if($row['status'] == 'po_received'): ?>
                                    <span class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg border border-amber-200 bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 text-[9px] font-black uppercase tracking-widest w-32">
                                        <i class="ph-fill ph-hourglass-high text-[11px]"></i> Pending Inv
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 text-[9px] font-black uppercase tracking-widest w-32">
                                        <i class="ph-fill ph-receipt text-[11px]"></i> Invoiced
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-3 align-middle text-center whitespace-nowrap relative">
                                <button onclick="toggleActionMenu(<?= $row['id'] ?>)" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-all shadow-sm active:scale-95 focus:outline-none">
                                    <i class="ph-bold ph-dots-three-vertical text-base"></i>
                                </button>

                                <div id="action-menu-<?= $row['id'] ?>" class="hidden absolute right-10 top-2 w-48 bg-white dark:bg-[#24303F] rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden text-left origin-top-right">
                                    <div class="py-1">
                                        <a href="quotation_print.php?id=<?= $row['id'] ?>" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-eye text-sm text-slate-400"></i> View Quote
                                        </a>

                                        <button onclick="openUploadModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['po_number_client'] ?? '') ?>')" class="w-full text-left flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <i class="ph-bold ph-cloud-arrow-up text-sm text-slate-400"></i> <?= $row['po_file_client'] ? 'Update PO Doc' : 'Upload PO Doc' ?>
                                        </button>

                                        <?php if($row['status'] == 'po_received'): ?>
                                            <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                            
                                            <a href="?process_invoice_id=<?= $row['id'] ?>" class="flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-receipt text-sm"></i> Process to Invoice
                                            </a>
                                            
                                            <a href="?cancel_id=<?= $row['id'] ?>" onclick="return confirm('PERINGATAN: Membatalkan PO ini juga akan membatalkan Quotation. Lanjutkan?')" class="flex items-center gap-2.5 px-4 py-2 text-[11px] font-bold text-rose-600 dark:text-rose-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <i class="ph-bold ph-x-circle text-sm"></i> Cancel PO
                                            </a>
                                        <?php else: ?>
                                            <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                            <span class="block px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 italic">
                                                <i class="ph-bold ph-check-circle mr-1"></i> Already Invoiced
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-14 h-14 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-file-text text-2xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-[13px] mb-0.5">Tidak Ada Data</h4>
                                    <p class="text-[11px] font-medium">Purchase Order Client tidak ditemukan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($res->num_rows > 0): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Menampilkan total <?= $res->num_rows ?> data PO.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="uploadPOModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700 flex flex-col">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-4 border-b border-indigo-500 bg-indigo-600 rounded-t-2xl flex justify-between items-center text-white">
                <h3 class="text-sm font-bold flex items-center gap-2"><i class="ph-bold ph-cloud-arrow-up text-lg"></i> Upload PO Document</h3>
                <button type="button" onclick="closeModal('uploadPOModal')" class="text-white/70 hover:text-white"><i class="ph-bold ph-x text-lg"></i></button>
            </div>
            
            <div class="p-6">
                <input type="hidden" name="quotation_id" id="modal_q_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Client PO Number</label>
                        <input type="text" id="modal_po_no" class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 outline-none" readonly>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Select File (PDF / Image) <span class="text-rose-500">*</span></label>
                        <input type="file" name="po_file" class="w-full block text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-widest file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 dark:file:bg-indigo-500/10 dark:file:text-indigo-400 dark:hover:file:bg-indigo-500/20 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-xl" accept=".pdf,.jpg,.jpeg,.png" required>
                        <p class="text-[10px] text-slate-400 mt-1.5 italic"><i class="ph-fill ph-info"></i> Maksimal ukuran file 5MB.</p>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex bg-slate-50/50 dark:bg-slate-800/50 rounded-b-2xl justify-end gap-2">
                <button type="button" onclick="closeModal('uploadPOModal')" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-xs">Batal</button>
                <button type="submit" name="upload_po_doc" class="px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-500/30 flex items-center justify-center gap-2">
                    <i class="ph-bold ph-upload-simple text-sm"></i> Upload File
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- FILTER TOGGLE ---
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('filterToggleBtn');
        const body = document.getElementById('filterBody');
        const icon = document.getElementById('filterIcon');

        if(btn && body && icon) {
            btn.addEventListener('click', () => {
                body.classList.toggle('hidden');
                if (body.classList.contains('hidden')) {
                    icon.classList.replace('ph-caret-up', 'ph-caret-down');
                } else {
                    icon.classList.replace('ph-caret-down', 'ph-caret-up');
                }
            });
        }
    });

    // --- MODAL HANDLERS ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
        }, 10);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.add('opacity-0');
        box.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function openUploadModal(id, poNo) {
        document.getElementById('modal_q_id').value = id;
        document.getElementById('modal_po_no').value = poNo;
        openModal('uploadPOModal');
    }

    // --- ACTION MENU DROPDOWN LOGIC ---
    let currentOpenDropdown = null;
    function toggleActionMenu(id) {
        const menu = document.getElementById('action-menu-' + id);
        if (currentOpenDropdown && currentOpenDropdown !== menu) {
            currentOpenDropdown.classList.add('hidden');
        }
        menu.classList.toggle('hidden');
        currentOpenDropdown = menu.classList.contains('hidden') ? null : menu;
    }
    
    document.addEventListener('click', (e) => {
        if (currentOpenDropdown && !e.target.closest('td.relative')) {
            currentOpenDropdown.classList.add('hidden');
            currentOpenDropdown = null;
        }
    });
</script>

<?php include 'includes/footer.php'; ?>