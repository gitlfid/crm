<?php
$page_title = "Client List";
include 'includes/header.php';
include 'includes/sidebar.php';
// include '../config/functions.php'; // Sesuaikan jika ada

// Pastikan session role tersedia
$my_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

// --- 1. LOGIKA IMPORT CSV (EXCEL) ---
if (isset($_POST['import_clients'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        
        // Skip header
        fgetcsv($handle, 1000, ","); 
        
        $success = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $comp = $conn->real_escape_string($data[0] ?? '');
            $addr = $conn->real_escape_string($data[1] ?? '');
            $pic  = $conn->real_escape_string($data[2] ?? '');
            $phone= $conn->real_escape_string($data[3] ?? '');
            $sub  = $conn->real_escape_string($data[4] ?? 'Monthly');
            $stat = $conn->real_escape_string($data[5] ?? 'Trial');
            
            if(!empty($comp)) {
                $sql = "INSERT INTO clients (company_name, address, pic_name, pic_phone, subscription_type, status) 
                        VALUES ('$comp', '$addr', '$pic', '$phone', '$sub', '$stat')";
                try {
                    if($conn->query($sql)) $success++;
                } catch (Exception $e) {
                    // Skip duplicate or error row
                }
            }
        }
        fclose($handle);
        echo "<script>alert('Berhasil import $success data client!'); window.location='clients.php';</script>";
    } else {
        echo "<script>alert('Gagal upload file.');</script>";
    }
}

// --- 2. LOGIKA DELETE (ADMIN ONLY & ERROR HANDLING) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if ($my_role == 'admin') {
        $del_id = intval($_GET['id']);
        
        try {
            $sqlDel = "DELETE FROM clients WHERE id = $del_id";
            if ($conn->query($sqlDel)) {
                echo "<script>alert('Client berhasil dihapus!'); window.location='clients.php';</script>";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) {
                echo "<script>
                    alert('GAGAL MENGHAPUS: Client ini masih memiliki data transaksi (Quotation/Invoice) yang aktif. Silakan hapus data transaksinya terlebih dahulu.'); 
                    window.location='clients.php';
                </script>";
            } else {
                echo "<script>alert('Error Database: " . addslashes($e->getMessage()) . "'); window.location='clients.php';</script>";
            }
        }
    } else {
        echo "<script>alert('Akses Ditolak! Hanya Admin yang bisa menghapus.'); window.location='clients.php';</script>";
    }
    exit; 
}

// --- 3. AMBIL DATA SALES PERSON (Untuk Dropdown) ---
$sales_people = [];
$sqlSales = "SELECT u.id, u.username FROM users u 
             JOIN divisions d ON u.division_id = d.id 
             WHERE d.name = 'Business Development' OR d.code = 'BD'";
$resSales = $conn->query($sqlSales);
if($resSales) {
    while($row = $resSales->fetch_assoc()) { $sales_people[] = $row; }
}

// --- 4. LOGIKA SIMPAN (ADD / EDIT) ---
if (isset($_POST['save_client'])) {
    $is_edit = !empty($_POST['client_id']);
    $id = $is_edit ? intval($_POST['client_id']) : 0;

    $comp = $conn->real_escape_string($_POST['company_name'] ?? '');
    $addr = $conn->real_escape_string($_POST['address'] ?? '');
    $pic  = $conn->real_escape_string($_POST['pic_name'] ?? '');
    $phone= $conn->real_escape_string($_POST['pic_phone'] ?? '');
    
    $sub_type = $conn->real_escape_string($_POST['subscription_type'] ?? 'Monthly');
    $status   = $conn->real_escape_string($_POST['status'] ?? 'Trial');
    
    $sales_id = !empty($_POST['sales_person_id']) ? intval($_POST['sales_person_id']) : "NULL";

    // Upload Logic
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $nda_sql = "";
    if (isset($_FILES['nda_file']) && $_FILES['nda_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['nda_file']['name'], PATHINFO_EXTENSION));
        $newName = 'NDA_' . time() . '_' . rand(100,999) . '.' . $ext;
        if (move_uploaded_file($_FILES['nda_file']['tmp_name'], $uploadDir . $newName)) {
            $nda_sql = $is_edit ? ", nda_file='$newName'" : $newName;
        }
    } else { $nda_sql = $is_edit ? "" : "NULL"; }

    $contract_sql = "";
    if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['contract_file']['name'], PATHINFO_EXTENSION));
        $newName = 'CONT_' . time() . '_' . rand(100,999) . '.' . $ext;
        if (move_uploaded_file($_FILES['contract_file']['tmp_name'], $uploadDir . $newName)) {
            $contract_sql = $is_edit ? ", contract_file='$newName'" : $newName;
        }
    } else { $contract_sql = $is_edit ? "" : "NULL"; }

    try {
        if ($is_edit) {
            $sql = "UPDATE clients SET 
                    company_name='$comp', address='$addr', pic_name='$pic', pic_phone='$phone',
                    subscription_type='$sub_type', status='$status', sales_person_id=$sales_id
                    $nda_sql $contract_sql
                    WHERE id=$id";
        } else {
            $nda_val = ($nda_sql == "NULL") ? "NULL" : "'$nda_sql'";
            $cont_val = ($contract_sql == "NULL") ? "NULL" : "'$contract_sql'";
            $sql = "INSERT INTO clients (company_name, address, pic_name, pic_phone, subscription_type, status, sales_person_id, nda_file, contract_file) 
                    VALUES ('$comp', '$addr', '$pic', '$phone', '$sub_type', '$status', $sales_id, $nda_val, $cont_val)";
        }

        if ($conn->query($sql)) {
            echo "<script>alert('Data Client Berhasil Disimpan!'); window.location='clients.php';</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('Error Database: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// --- 5. FILTER DATA ---
$search  = isset($_GET['search']) ? $_GET['search'] : '';
$f_sub   = isset($_GET['subscription']) ? $_GET['subscription'] : '';
$f_stat  = isset($_GET['status']) ? $_GET['status'] : '';
$f_sales = isset($_GET['sales']) ? $_GET['sales'] : '';

$where = "1=1";
if(!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (company_name LIKE '%$safe_search%' OR pic_name LIKE '%$safe_search%')";
}
if(!empty($f_sub)) {
    $safe_sub = $conn->real_escape_string($f_sub);
    $where .= " AND subscription_type = '$safe_sub'";
}
if(!empty($f_stat)) {
    $safe_stat = $conn->real_escape_string($f_stat);
    $where .= " AND status = '$safe_stat'";
}
if(!empty($f_sales)) {
    $safe_sales = intval($f_sales);
    $where .= " AND sales_person_id = $safe_sales";
}

// --- 6. QUERY DATA UTAMA ---
$sqlClients = "SELECT c.*, u.username as sales_name 
               FROM clients c 
               LEFT JOIN users u ON c.sales_person_id = u.id 
               WHERE $where
               ORDER BY c.created_at DESC";
$clients = $conn->query($sqlClients);
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
                <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 flex items-center justify-center text-xl shadow-inner">
                    <i class="ph-bold ph-users-three"></i>
                </div>
                Client List
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Database pelanggan dan manajemen dokumen legalitas (NDA & Kontrak).</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button onclick="openImportModal()" class="inline-flex items-center justify-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20 dark:text-emerald-400 font-bold py-2.5 px-5 rounded-xl border border-emerald-200 dark:border-emerald-500/20 transition-all active:scale-95 shadow-sm">
                <i class="ph-bold ph-file-csv text-lg"></i> Import Excel
            </button>
            <button onclick="openClientModal()" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-plus text-lg relative z-10"></i> 
                <span class="relative z-10">Add Client</span>
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-t-3xl transition-colors" id="filterToggleBtn">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="ph-bold ph-funnel text-indigo-500 text-lg"></i> Filter Data Client
            </h3>
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 transition-transform">
                <i id="filterIcon" class="ph-bold ph-caret-up transition-transform duration-300"></i>
            </div>
        </div>
        
        <div id="filterBody" class="p-6 block transition-all duration-300">
            <form method="GET">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5 items-end">
                    
                    <div class="lg:col-span-4">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Pencarian</label>
                        <div class="relative group">
                            <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Nama Perusahaan / PIC..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Langganan</label>
                        <div class="relative group">
                            <select name="subscription" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua</option>
                                <option value="Daily" <?= $f_sub=='Daily'?'selected':'' ?>>Daily</option>
                                <option value="Monthly" <?= $f_sub=='Monthly'?'selected':'' ?>>Monthly</option>
                                <option value="Yearly" <?= $f_sub=='Yearly'?'selected':'' ?>>Yearly</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Status</label>
                        <div class="relative group">
                            <select name="status" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Status</option>
                                <option value="Trial" <?= $f_stat=='Trial'?'selected':'' ?>>Trial</option>
                                <option value="Subscribe" <?= $f_stat=='Subscribe'?'selected':'' ?>>Subscribe</option>
                                <option value="Unsubscribe" <?= $f_stat=='Unsubscribe'?'selected':'' ?>>Unsubscribe</option>
                                <option value="Cancel" <?= $f_stat=='Cancel'?'selected':'' ?>>Cancel</option>
                                <option value="Hold" <?= $f_stat=='Hold'?'selected':'' ?>>Hold</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Sales (BD)</label>
                        <div class="relative group">
                            <select name="sales" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white appearance-none outline-none transition-all cursor-pointer shadow-inner">
                                <option value="">Semua Sales</option>
                                <?php foreach($sales_people as $sp): ?>
                                    <option value="<?= $sp['id'] ?>" <?= $f_sales==$sp['id']?'selected':'' ?>>
                                        <?= htmlspecialchars($sp['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="lg:col-span-2 flex gap-3 h-[42px]">
                        <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-sm active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-bold ph-funnel"></i> Filter
                        </button>
                        <?php if(!empty($search) || !empty($f_sub) || !empty($f_stat) || !empty($f_sales)): ?>
                            <a href="clients.php" class="flex-none w-[42px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold rounded-xl transition-all border border-rose-100 dark:border-rose-500/20 active:scale-95 flex items-center justify-center" title="Reset Filters">
                                <i class="ph-bold ph-arrows-counter-clockwise text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors duration-300">
        <div class="overflow-x-auto modern-scrollbar w-full">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider min-w-[250px]">Company Info</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Subscription</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Sales Person</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Docs</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if ($clients && $clients->num_rows > 0): ?>
                        <?php while($row = $clients->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle">
                                <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-1.5 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                    <?= htmlspecialchars($row['company_name']) ?>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    <span class="flex items-center gap-1.5"><i class="ph-bold ph-user text-slate-400"></i> <?= htmlspecialchars($row['pic_name']) ?></span>
                                    <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                                    <span class="flex items-center gap-1.5"><i class="ph-bold ph-phone text-slate-400"></i> <?= htmlspecialchars($row['pic_phone']) ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 text-xs font-bold border border-slate-200 dark:border-slate-600">
                                    <?= htmlspecialchars($row['subscription_type']) ?>
                                </span>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <?php 
                                    $st = $row['status'];
                                    $stStyle = 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300';
                                    if($st == 'Subscribe') $stStyle = 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20';
                                    elseif($st == 'Trial') $stStyle = 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-400 dark:border-sky-500/20';
                                    elseif($st == 'Hold')  $stStyle = 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20';
                                    elseif($st == 'Cancel' || $st == 'Unsubscribe') $stStyle = 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20';
                                ?>
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg border text-[10px] font-black uppercase tracking-widest <?= $stStyle ?>">
                                    <?= htmlspecialchars($st) ?>
                                </span>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <?php if($row['sales_name']): ?>
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
                                        <div class="w-5 h-5 rounded-full overflow-hidden shrink-0 bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-[10px]">
                                            <?= strtoupper(substr($row['sales_name'], 0, 1)) ?>
                                        </div>
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($row['sales_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs italic text-slate-400">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-5 align-middle text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <?php if($row['nda_file']): ?>
                                        <a href="../uploads/<?= $row['nda_file'] ?>" target="_blank" title="Lihat NDA" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20 flex items-center justify-center transition-colors">
                                            <i class="ph-fill ph-file-lock text-lg"></i>
                                        </a>
                                    <?php else: ?>
                                        <div class="w-8 h-8 flex items-center justify-center text-slate-300 dark:text-slate-600" title="NDA Tidak Ada">
                                            <i class="ph-bold ph-file-dashed text-lg"></i>
                                        </div>
                                    <?php endif; ?>

                                    <?php if($row['contract_file']): ?>
                                        <a href="../uploads/<?= $row['contract_file'] ?>" target="_blank" title="Lihat Kontrak" class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-400 dark:hover:bg-indigo-500/20 flex items-center justify-center transition-colors">
                                            <i class="ph-fill ph-file-text text-lg"></i>
                                        </a>
                                    <?php else: ?>
                                        <div class="w-8 h-8 flex items-center justify-center text-slate-300 dark:text-slate-600" title="Kontrak Tidak Ada">
                                            <i class="ph-bold ph-file-dashed text-lg"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick='editClient(<?= json_encode($row) ?>)' class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-indigo-600 hover:text-white dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-indigo-600 transition-colors flex items-center justify-center shadow-sm" title="Edit">
                                        <i class="ph-bold ph-pencil-simple"></i>
                                    </button>
                                    
                                    <?php if ($my_role == 'admin'): ?>
                                    <a href="?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin menghapus? Data tidak bisa dikembalikan.')" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-600 dark:hover:text-white transition-colors flex items-center justify-center shadow-sm" title="Hapus">
                                        <i class="ph-bold ph-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-users-three text-4xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Tidak Ada Data Client</h4>
                                    <p class="text-sm font-medium">Belum ada data atau tidak ditemukan dengan filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($clients && $clients->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-center">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white dark:bg-[#1A222C] px-4 py-1.5 rounded-full border border-slate-100 dark:border-slate-700 shadow-sm">
                Total Data: <span class="text-indigo-600 dark:text-indigo-400 ml-1"><?= $clients->num_rows ?> Client</span>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="clientModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeClientModal()"></div>
    
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh] overflow-hidden transform transition-all scale-100">
        <form method="POST" enctype="multipart/form-data" class="flex flex-col h-full">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center shrink-0">
                <h3 class="text-lg font-black text-slate-800 dark:text-white" id="modalTitle">Add New Client</h3>
                <button type="button" onclick="closeClientModal()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-200/50 hover:bg-rose-100 text-slate-500 hover:text-rose-600 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-rose-500/20 dark:hover:text-rose-400 transition-colors">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto modern-scrollbar flex-1">
                <input type="hidden" name="client_id" id="client_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Company Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="company_name" id="company_name" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">PIC Name</label>
                        <input type="text" name="pic_name" id="pic_name" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">PIC Phone</label>
                        <input type="text" name="pic_phone" id="pic_phone" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Address</label>
                        <textarea name="address" id="address" rows="2" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all"></textarea>
                    </div>

                    <div class="md:col-span-2"><hr class="border-slate-200 dark:border-slate-700"></div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Subscription Type</label>
                        <div class="relative">
                            <select name="subscription_type" id="subscription_type" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all">
                                <option value="Daily">Daily</option>
                                <option value="Monthly" selected>Monthly</option>
                                <option value="Yearly">Yearly</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Client Status</label>
                        <div class="relative">
                            <select name="status" id="status" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all">
                                <option value="Trial">Trial</option>
                                <option value="Subscribe">Subscribe</option>
                                <option value="Unsubscribe">Unsubscribe</option>
                                <option value="Cancel">Cancel</option>
                                <option value="Hold">Hold</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Sales Person (BD)</label>
                        <div class="relative">
                            <select name="sales_person_id" id="sales_person_id" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 dark:text-white appearance-none outline-none transition-all">
                                <option value="">-- Select Sales --</option>
                                <?php foreach($sales_people as $sp): ?>
                                    <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="md:col-span-2"><hr class="border-slate-200 dark:border-slate-700"></div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Upload NDA (Opsional)</label>
                        <input type="file" name="nda_file" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-500/10 dark:file:text-indigo-400 hover:file:bg-indigo-100 transition-all cursor-pointer">
                        <div id="nda_status" class="mt-2 text-xs font-bold"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Upload Contract (Opsional)</label>
                        <input type="file" name="contract_file" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-500/10 dark:file:text-indigo-400 hover:file:bg-indigo-100 transition-all cursor-pointer">
                        <div id="contract_status" class="mt-2 text-xs font-bold"></div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3 shrink-0">
                <button type="button" onclick="closeClientModal()" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="save_client" class="px-6 py-2.5 rounded-xl font-bold text-sm bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition-all active:scale-95">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div id="importModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeImportModal()"></div>
    
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100">
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-emerald-500 text-white flex justify-between items-center">
                <h3 class="text-lg font-black flex items-center gap-2"><i class="ph-bold ph-file-csv text-xl"></i> Import Clients</h3>
                <button type="button" onclick="closeImportModal()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>

            <div class="p-6">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Pilih File CSV <span class="text-rose-500">*</span></label>
                <input type="file" name="csv_file" accept=".csv" required class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-600 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 dark:file:bg-emerald-500/10 dark:file:text-emerald-400 hover:file:bg-emerald-100 transition-all cursor-pointer">
                
                <div class="mt-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-xs text-amber-700 dark:text-amber-400">
                    <strong class="block mb-1 font-bold"><i class="ph-fill ph-warning-circle"></i> Format Urutan Kolom CSV:</strong>
                    Company Name, Address, PIC Name, PIC Phone, Subscription (Monthly/Yearly), Status (Trial/Subscribe)
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3">
                <button type="button" onclick="closeImportModal()" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</button>
                <button type="submit" name="import_clients" class="px-6 py-2.5 rounded-xl font-bold text-sm bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-all active:scale-95">Mulai Import</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- Logika Collapse Filter ---
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('filterToggleBtn');
        const body = document.getElementById('filterBody');
        const icon = document.getElementById('filterIcon');

        if(btn && body && icon) {
            btn.addEventListener('click', () => {
                if (body.classList.contains('hidden')) {
                    body.classList.remove('hidden');
                    setTimeout(() => body.style.opacity = '1', 10); 
                    icon.classList.replace('ph-caret-down', 'ph-caret-up');
                } else {
                    body.classList.add('hidden');
                    body.style.opacity = '0';
                    icon.classList.replace('ph-caret-up', 'ph-caret-down');
                }
            });
        }
    });

    // --- Logika Modals Vanilla JS (Tailwind) ---
    const clientModal = document.getElementById('clientModal');
    const importModal = document.getElementById('importModal');

    function openClientModal() {
        document.getElementById('client_id').value = '';
        document.getElementById('company_name').value = '';
        document.getElementById('pic_name').value = '';
        document.getElementById('pic_phone').value = '';
        document.getElementById('address').value = '';
        document.getElementById('subscription_type').value = 'Monthly';
        document.getElementById('status').value = 'Trial';
        document.getElementById('sales_person_id').value = '';
        document.getElementById('nda_status').innerHTML = '';
        document.getElementById('contract_status').innerHTML = '';
        document.getElementById('modalTitle').innerText = "Add New Client";
        
        clientModal.classList.remove('hidden');
    }

    function closeClientModal() {
        clientModal.classList.add('hidden');
    }

    function editClient(data) {
        document.getElementById('client_id').value = data.id;
        document.getElementById('company_name').value = data.company_name;
        document.getElementById('pic_name').value = data.pic_name;
        document.getElementById('pic_phone').value = data.pic_phone;
        document.getElementById('address').value = data.address;
        document.getElementById('subscription_type').value = data.subscription_type;
        document.getElementById('status').value = data.status;
        document.getElementById('sales_person_id').value = data.sales_person_id;

        const successHTML = '<span class="text-emerald-600 dark:text-emerald-400"><i class="ph-fill ph-check-circle"></i> File Tersedia</span>';
        const missingHTML = '<span class="text-slate-400"><i class="ph-bold ph-file-dashed"></i> Belum ada file</span>';

        document.getElementById('nda_status').innerHTML = data.nda_file ? successHTML : missingHTML;
        document.getElementById('contract_status').innerHTML = data.contract_file ? successHTML : missingHTML;

        document.getElementById('modalTitle').innerText = "Edit Client Data";
        
        clientModal.classList.remove('hidden');
    }

    function openImportModal() {
        importModal.classList.remove('hidden');
    }

    function closeImportModal() {
        importModal.classList.add('hidden');
    }
</script>

<?php include 'includes/footer.php'; ?>