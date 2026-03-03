<?php
$page_title = "Vendor List";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Logic untuk Simpan/Edit Vendor
if (isset($_POST['save_vendor'])) {
    $is_edit = !empty($_POST['vendor_id']);
    $id = $is_edit ? intval($_POST['vendor_id']) : 0;

    $company_name = $conn->real_escape_string($_POST['company_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $pic_name = $conn->real_escape_string($_POST['pic_name']);

    if ($is_edit) {
        $sql = "UPDATE vendors SET company_name='$company_name', address='$address', pic_name='$pic_name' WHERE id=$id";
        $message = "Vendor Berhasil Diupdate!";
    } else {
        $sql = "INSERT INTO vendors (company_name, address, pic_name) VALUES ('$company_name', '$address', '$pic_name')";
        $message = "Vendor Berhasil Ditambahkan!";
    }

    if ($conn->query($sql)) {
        echo "<script>alert('$message'); window.location='vendor_list.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// Logic untuk Hapus Vendor
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM vendors WHERE id=$id";
    if ($conn->query($sql)) {
        echo "<script>alert('Vendor Berhasil Dihapus!'); window.location='vendor_list.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "\\nPastikan tidak ada Purchase Order yang terhubung.'); window.location='vendor_list.php';</script>";
    }
}

$vendors = $conn->query("SELECT * FROM vendors ORDER BY company_name ASC");
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    .custom-scrollbar::-webkit-scrollbar { height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1400px] mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 animate-fade-in-up">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Vendor Directory</h1>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Daftar rekanan dan perusahaan pemasok/vendor terdaftar.</p>
        </div>
        <button onclick="openCustomModal('add')" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 active:scale-95 whitespace-nowrap">
            <i class="ph-bold ph-plus text-lg"></i> Add New Vendor
        </button>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="overflow-x-auto custom-scrollbar w-full">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase tracking-widest text-slate-500 dark:text-slate-400 font-black">
                    <tr>
                        <th class="px-6 py-4 whitespace-nowrap">Company Information</th>
                        <th class="px-6 py-4 whitespace-nowrap">PIC (Contact Person)</th>
                        <th class="px-6 py-4 whitespace-nowrap">Address Detail</th>
                        <th class="px-6 py-4 text-center whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                    <?php if ($vendors && $vendors->num_rows > 0): ?>
                        <?php while($row = $vendors->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/80 transition-colors group">
                            
                            <td class="px-6 py-4 align-top">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-black text-sm shrink-0 border border-indigo-100 dark:border-indigo-500/20">
                                        <?= strtoupper(substr($row['company_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-0.5">
                                            <?= htmlspecialchars($row['company_name']) ?>
                                        </div>
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1 uppercase tracking-wider">
                                            <i class="ph-fill ph-buildings"></i> Vendor ID: #<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <?php if(!empty($row['pic_name'])): ?>
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
                                        <div class="w-5 h-5 rounded-full overflow-hidden shrink-0 bg-slate-100 dark:bg-slate-700">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['pic_name']) ?>&background=random&size=32" alt="Avatar" class="w-full h-full object-cover">
                                        </div>
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                            <?= htmlspecialchars($row['pic_name']) ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[10px] italic text-slate-400 dark:text-slate-500">- Not Provided -</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 align-top text-slate-600 dark:text-slate-400 text-[11px] leading-relaxed max-w-xs whitespace-normal">
                                <?php if(!empty($row['address'])): ?>
                                    <div class="flex items-start gap-1.5">
                                        <i class="ph-fill ph-map-pin text-slate-400 mt-0.5"></i>
                                        <span><?= nl2br(htmlspecialchars($row['address'])) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="italic opacity-60">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 align-top text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php $vendorJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                    <button onclick='openCustomModal("edit", <?= $vendorJson ?>)' class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-50 text-slate-600 hover:text-amber-600 dark:bg-slate-700 dark:hover:bg-amber-500/20 dark:text-slate-300 dark:hover:text-amber-400 transition-all shadow-sm active:scale-95 flex items-center justify-center" title="Edit Vendor">
                                        <i class="ph-bold ph-pencil-simple text-sm"></i>
                                    </button>
                                    <a href="vendor_list.php?delete=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin menghapus vendor <?= htmlspecialchars($row['company_name'], ENT_QUOTES) ?>?');" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 dark:bg-slate-700 dark:hover:bg-rose-500/20 dark:text-slate-300 dark:hover:text-rose-400 transition-all shadow-sm active:scale-95 flex items-center justify-center" title="Delete Vendor">
                                        <i class="ph-bold ph-trash text-sm"></i>
                                    </a>
                                </div>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3 border border-slate-100 dark:border-slate-700">
                                        <i class="ph-fill ph-buildings text-3xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mb-0.5">Belum Ada Vendor</h4>
                                    <p class="text-[11px] font-medium">Klik tombol 'Add New Vendor' untuk mulai menambahkan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($vendors && $vendors->num_rows > 0): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Total <?= $vendors->num_rows ?> vendor terdaftar.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="vendorModal" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg transform scale-95 opacity-0 transition-all duration-300 modal-box shadow-2xl border border-slate-100 dark:border-slate-700 flex flex-col overflow-hidden">
        
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="text-sm font-extrabold text-slate-800 dark:text-white flex items-center gap-2 tracking-wide" id="modalTitle">
                </h3>
            <button type="button" onclick="closeCustomModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 hover:bg-slate-300 text-slate-500 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-slate-600 transition-colors">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        
        <form method="POST">
            <div class="p-6 space-y-4">
                <input type="hidden" name="vendor_id" id="vendor_id">
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nama Perusahaan <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <i class="ph-fill ph-buildings absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="company_name" id="company_name" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all font-bold" placeholder="PT / CV Nama Perusahaan" required>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">PIC (Contact Person)</label>
                    <div class="relative">
                        <i class="ph-fill ph-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="pic_name" id="pic_name" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all font-medium" placeholder="Nama Penanggung Jawab">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Alamat Lengkap</label>
                    <textarea name="address" id="address" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all resize-none font-medium" rows="3" placeholder="Alamat detail vendor..."></textarea>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <button type="button" onclick="closeCustomModal()" class="px-6 py-2.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors text-xs">
                    Cancel
                </button>
                <button type="submit" name="save_vendor" class="px-6 py-2.5 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-500/30 transition-all flex items-center gap-2 text-xs active:scale-95">
                    <i class="ph-bold ph-floppy-disk text-sm"></i> Save Data
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- CUSTOM MODAL HANDLERS ---
    function openCustomModal(mode, data = null) {
        const modal = document.getElementById('vendorModal');
        const box = modal.querySelector('.modal-box');
        
        // Reset or Fill Form
        if (mode === 'add') {
            document.getElementById('vendor_id').value = '';
            document.getElementById('company_name').value = '';
            document.getElementById('pic_name').value = '';
            document.getElementById('address').value = '';
            document.getElementById('modalTitle').innerHTML = '<i class="ph-fill ph-buildings text-indigo-500 text-lg"></i> Add New Vendor';
        } else if (mode === 'edit' && data) {
            document.getElementById('vendor_id').value = data.id;
            document.getElementById('company_name').value = data.company_name;
            document.getElementById('pic_name').value = data.pic_name;
            document.getElementById('address').value = data.address;
            document.getElementById('modalTitle').innerHTML = '<i class="ph-fill ph-pencil-simple text-amber-500 text-lg"></i> Edit Vendor Data';
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