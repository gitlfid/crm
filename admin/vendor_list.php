<?php
$page_title = "Vendor List";
include 'includes/header.php';
include 'includes/sidebar.php';
// include '../config/functions.php'; // Aktifkan jika diperlukan

// --- 1. LOGIKA SIMPAN / EDIT VENDOR ---
if (isset($_POST['save_vendor'])) {
    $is_edit = !empty($_POST['vendor_id']);
    $id = $is_edit ? intval($_POST['vendor_id']) : 0;

    $company_name = $conn->real_escape_string(trim($_POST['company_name']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $pic_name = $conn->real_escape_string(trim($_POST['pic_name']));

    if ($is_edit) {
        $sql = "UPDATE vendors SET company_name='$company_name', address='$address', pic_name='$pic_name' WHERE id=$id";
        $message = "Data Vendor Berhasil Diperbarui!";
    } else {
        $sql = "INSERT INTO vendors (company_name, address, pic_name) VALUES ('$company_name', '$address', '$pic_name')";
        $message = "Vendor Baru Berhasil Ditambahkan!";
    }

    if ($conn->query($sql)) {
        echo "<script>alert('$message'); window.location='vendor_list.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// --- 2. LOGIKA HAPUS VENDOR ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM vendors WHERE id=$id";
    if ($conn->query($sql)) {
        echo "<script>alert('Vendor Berhasil Dihapus Secara Permanen!'); window.location='vendor_list.php';</script>";
    } else {
        echo "<script>alert('Gagal Menghapus: Pastikan tidak ada Purchase Order / Data Transaksi yang terhubung dengan vendor ini.'); window.location='vendor_list.php';</script>";
    }
}

// --- 3. LOGIKA FILTER PENCARIAN ---
$search_keyword = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where_clause = "1=1";

if (!empty($search_keyword)) {
    $where_clause .= " AND (company_name LIKE '%$search_keyword%' OR pic_name LIKE '%$search_keyword%' OR address LIKE '%$search_keyword%')";
}

// --- 4. QUERY UTAMA ---
$vendors = $conn->query("SELECT * FROM vendors WHERE $where_clause ORDER BY id DESC");

// --- 5. QUERY STATISTIK (Dinamis berdasarkan filter) ---
$stat_total = $conn->query("SELECT COUNT(*) FROM vendors WHERE $where_clause")->fetch_row()[0];
$stat_with_pic = $conn->query("SELECT COUNT(*) FROM vendors WHERE $where_clause AND pic_name != '' AND pic_name IS NOT NULL")->fetch_row()[0];
$stat_no_pic = $stat_total - $stat_with_pic;
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

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-storefront"></i>
                </div>
                Vendor Directory
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Kelola daftar rekanan dan perusahaan pemasok/vendor terdaftar.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='vendor_list.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh / Reset Filter">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
            <button onclick="openCustomModal('add')" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-plus text-xl relative z-10"></i> 
                <span class="relative z-10">Add New Vendor</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-buildings"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Vendors</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stat_total) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-user-check"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Data Lengkap (PIC)</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stat_with_pic) ?></h4>
            </div>
        </div>

        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 flex items-center justify-center text-3xl shrink-0"><i class="ph-fill ph-warning-circle"></i></div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Tanpa PIC</p>
                <h4 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($stat_no_pic) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-2 transition-colors duration-300">
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-grow group">
                <i class="ph-bold ph-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                <input type="text" name="search" class="w-full pl-12 pr-4 py-3.5 bg-transparent border-none text-sm font-medium focus:ring-0 outline-none dark:text-white placeholder-slate-400" placeholder="Cari Nama Perusahaan, PIC, atau Alamat..." value="<?= htmlspecialchars($search_keyword) ?>">
            </div>
            <button type="submit" class="bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white font-bold py-3.5 px-8 rounded-2xl transition-all shadow-sm active:scale-95 whitespace-nowrap flex items-center justify-center gap-2">
                <i class="ph-bold ph-funnel"></i> Cari Vendor
            </button>
        </form>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors duration-300">
        <div class="overflow-x-auto modern-scrollbar w-full">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 dark:bg-slate-800/30">
                    <tr>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap min-w-[250px]">Informasi Perusahaan</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Contact Person (PIC)</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-xs font-black text-slate-400 uppercase tracking-wider min-w-[300px]">Alamat Detail</th>
                        <th class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 text-center text-xs font-black text-slate-400 uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if ($vendors && $vendors->num_rows > 0): ?>
                        <?php while($row = $vendors->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-slate-200 to-slate-100 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 font-black text-lg uppercase shrink-0 shadow-inner border border-slate-200/50 dark:border-slate-600/50">
                                        <?= strtoupper(substr($row['company_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                            <?= htmlspecialchars($row['company_name']) ?>
                                        </div>
                                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-widest border border-slate-200 dark:border-slate-700">
                                            <i class="ph-fill ph-tag"></i> VND-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <?php if(!empty($row['pic_name'])): ?>
                                    <div class="inline-flex items-center gap-2.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1A222C] shadow-sm">
                                        <div class="w-6 h-6 rounded-full overflow-hidden shrink-0 bg-slate-100 ring-2 ring-white dark:ring-slate-800">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['pic_name']) ?>&background=random&color=fff&size=64" alt="Avatar" class="w-full h-full object-cover">
                                        </div>
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                            <?= htmlspecialchars($row['pic_name']) ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/30 text-slate-400 dark:text-slate-500">
                                        <i class="ph-bold ph-user-minus text-xs"></i>
                                        <span class="text-[10px] font-bold uppercase tracking-widest">Belum Ditentukan</span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <?php if(!empty($row['address'])): ?>
                                    <div class="flex items-start gap-2">
                                        <div class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-500 shrink-0 mt-0.5">
                                            <i class="ph-fill ph-map-pin text-sm"></i>
                                        </div>
                                        <span class="text-xs font-medium text-slate-600 dark:text-slate-400 leading-relaxed">
                                            <?= nl2br(htmlspecialchars($row['address'])) ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs italic text-slate-400 opacity-70">Alamat tidak tersedia</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-5 align-middle text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php $vendorJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                    <button onclick='openCustomModal("edit", <?= $vendorJson ?>)' class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-indigo-500 text-slate-500 hover:text-white dark:bg-slate-800 dark:hover:bg-indigo-500 dark:text-slate-400 dark:hover:text-white transition-all shadow-sm active:scale-95 flex items-center justify-center" title="Edit Vendor">
                                        <i class="ph-bold ph-pencil-simple text-base"></i>
                                    </button>
                                    <a href="vendor_list.php?delete=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin menghapus vendor <?= htmlspecialchars($row['company_name'], ENT_QUOTES) ?> secara permanen?');" class="w-9 h-9 rounded-xl bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white dark:bg-rose-500/10 dark:hover:bg-rose-600 dark:text-rose-400 dark:hover:text-white transition-all shadow-sm active:scale-95 flex items-center justify-center" title="Hapus Vendor">
                                        <i class="ph-bold ph-trash text-base"></i>
                                    </a>
                                </div>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-storefront text-5xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg mb-1">Data Tidak Ditemukan</h4>
                                    <p class="text-sm font-medium">Belum ada vendor terdaftar atau tidak sesuai dengan filter pencarian.</p>
                                    <?php if(!empty($search_keyword)): ?>
                                        <a href="vendor_list.php" class="mt-5 inline-flex items-center gap-2 bg-slate-800 dark:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors shadow-sm">
                                            <i class="ph-bold ph-arrows-counter-clockwise"></i> Reset Pencarian
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($vendors && $vendors->num_rows > 0): ?>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-center">
            <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest bg-white dark:bg-[#1A222C] px-5 py-2 rounded-full border border-slate-100 dark:border-slate-700 shadow-sm">
                Menampilkan Total <span class="text-indigo-600 dark:text-indigo-400 font-black mx-1"><?= $vendors->num_rows ?></span> Vendor
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="vendorModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeCustomModal()"></div>
    
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-xl transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        
        <div class="px-8 py-5 border-b border-indigo-500/20 bg-indigo-600 text-white flex justify-between items-center" id="modalHeader">
            <h3 class="text-lg font-black flex items-center gap-2 tracking-wide" id="modalTitle">
                </h3>
            <button type="button" onclick="closeCustomModal()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                <i class="ph-bold ph-x text-lg"></i>
            </button>
        </div>
        
        <form method="POST">
            <div class="p-8 space-y-5">
                <input type="hidden" name="vendor_id" id="vendor_id">
                
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Nama Perusahaan <span class="text-rose-500">*</span></label>
                    <div class="relative group">
                        <i class="ph-bold ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                        <input type="text" name="company_name" id="company_name" class="w-full pl-12 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="PT / CV Nama Vendor..." required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Nama PIC (Opsional)</label>
                    <div class="relative group">
                        <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                        <input type="text" name="pic_name" id="pic_name" class="w-full pl-12 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Nama lengkap Contact Person...">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Alamat Detail (Opsional)</label>
                    <textarea name="address" id="address" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all resize-none placeholder-slate-400 shadow-inner" rows="3" placeholder="Jalan, Kota, Kode Pos..."></textarea>
                </div>
            </div>
            
            <div class="px-8 py-5 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3">
                <button type="button" onclick="closeCustomModal()" class="px-6 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-sm shadow-sm">
                    Batal
                </button>
                <button type="submit" name="save_vendor" class="px-6 py-2.5 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 transition-all flex items-center gap-2 text-sm active:scale-95">
                    <i class="ph-bold ph-floppy-disk text-lg"></i> Simpan Data
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- CUSTOM MODAL HANDLERS (Tailwind Vanilla JS) ---
    function openCustomModal(mode, data = null) {
        const modal = document.getElementById('vendorModal');
        const box = modal.querySelector('.modal-box');
        const header = document.getElementById('modalHeader');
        
        // Reset or Fill Form
        if (mode === 'add') {
            document.getElementById('vendor_id').value = '';
            document.getElementById('company_name').value = '';
            document.getElementById('pic_name').value = '';
            document.getElementById('address').value = '';
            document.getElementById('modalTitle').innerHTML = '<i class="ph-fill ph-storefront text-2xl"></i> Add New Vendor';
            
            // Set Header Color
            header.className = "px-8 py-5 border-b border-indigo-500/20 bg-indigo-600 text-white flex justify-between items-center";
        } else if (mode === 'edit' && data) {
            document.getElementById('vendor_id').value = data.id;
            document.getElementById('company_name').value = data.company_name;
            document.getElementById('pic_name').value = data.pic_name;
            document.getElementById('address').value = data.address;
            document.getElementById('modalTitle').innerHTML = '<i class="ph-fill ph-pencil-simple text-2xl"></i> Edit Vendor Data';
            
            // Set Header Color
            header.className = "px-8 py-5 border-b border-amber-500/20 bg-amber-500 text-white flex justify-between items-center";
        }

        // Show Animate
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
        }, 10);
    }

    function closeCustomModal() {
        const modal = document.getElementById('vendorModal');
        const box = modal.querySelector('.modal-box');
        
        // Hide Animate
        modal.classList.add('opacity-0');
        box.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
</script>

<?php include 'includes/footer.php'; ?>