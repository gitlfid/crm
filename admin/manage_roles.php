<?php
$page_title = "Manage Access Permissions";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- 1. PROSES SIMPAN PERMISSION ---
if (isset($_POST['save_permissions'])) {
    $divisionId = intval($_POST['division_id']);
    $selectedMenus = $_POST['menus'] ?? [];

    // Hapus permission lama
    $conn->query("DELETE FROM division_permissions WHERE division_id = $divisionId");

    // Insert permission baru
    if (!empty($selectedMenus)) {
        $stmt = $conn->prepare("INSERT INTO division_permissions (division_id, menu_id) VALUES (?, ?)");
        foreach ($selectedMenus as $menuId) {
            $stmt->bind_param("ii", $divisionId, $menuId);
            $stmt->execute();
        }
        $stmt->close();
    }
    
    echo "<script>alert('Permissions updated successfully!'); window.location='manage_roles.php?division_id=$divisionId';</script>";
}

// --- 2. AMBIL DATA DIVISI ---
$divisions = $conn->query("SELECT * FROM divisions ORDER BY id ASC");

// Divisi yang sedang diedit (Default: 1/IT jika tidak ada parameter)
$activeDivId = isset($_GET['division_id']) ? intval($_GET['division_id']) : 1; 

// Ambil Nama Divisi Aktif
$activeDivName = '';
$dNameQ = $conn->query("SELECT name FROM divisions WHERE id = $activeDivId");
if($dNameQ->num_rows > 0) {
    $activeDivName = $dNameQ->fetch_assoc()['name'];
}

// --- 3. AMBIL SEMUA MENU & STATUS PERMISSION ---
// Kita ambil SEMUA menu, lalu cek apakah menu tersebut ada di tabel permission untuk divisi aktif
$sqlMenus = "SELECT m.*, 
            (SELECT COUNT(*) FROM division_permissions dp WHERE dp.division_id = $activeDivId AND dp.menu_id = m.id) as is_permitted
            FROM menus m 
            ORDER BY m.sort_order ASC";
$menusRes = $conn->query($sqlMenus);

// --- 4. SUSUN MENU JADI TREE (PARENT -> CHILDREN) ---
$menuTree = [];
// Langkah A: Masukkan semua menu ke array asosiatif berdasarkan key
$allMenus = [];
if ($menusRes) {
    while($row = $menusRes->fetch_assoc()) {
        $row['children'] = []; // Siapkan tempat untuk anak
        $allMenus[$row['menu_key']] = $row;
    }
}

// Langkah B: Susun Parent-Child
foreach ($allMenus as $key => &$menu) {
    if (!empty($menu['parent_menu'])) {
        // Jika punya parent, masukkan diri sendiri ke array children milik parent
        if (isset($allMenus[$menu['parent_menu']])) {
            $allMenus[$menu['parent_menu']]['children'][] = $menu;
        }
    } else {
        // Jika tidak punya parent, masukkan ke root tree
        $menuTree[] = &$menu;
    }
}
unset($menu); // Bersihkan reference
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
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-shield-check"></i>
                </div>
                Access Permissions
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Atur dan kelola hak akses menu sidebar (Role Management) untuk setiap Divisi.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='manage_roles.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <div class="lg:col-span-4 xl:col-span-3">
            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col sticky top-24">
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 flex items-center justify-center">
                        <i class="ph-bold ph-buildings text-lg"></i>
                    </div>
                    <h3 class="font-black text-slate-800 dark:text-white text-sm uppercase tracking-widest">Pilih Divisi</h3>
                </div>
                
                <div class="p-4 flex flex-col gap-2 max-h-[600px] overflow-y-auto modern-scrollbar">
                    <?php if($divisions && $divisions->num_rows > 0): ?>
                        <?php while($d = $divisions->fetch_assoc()): 
                            $isActive = ($activeDivId == $d['id']);
                            $baseClass = "flex items-center justify-between px-4 py-3.5 rounded-2xl transition-all cursor-pointer group ";
                            $activeClass = $isActive 
                                ? "bg-indigo-50 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400 font-black border border-indigo-200 dark:border-indigo-500/30 shadow-sm" 
                                : "text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-transparent font-semibold";
                        ?>
                            <a href="?division_id=<?= $d['id'] ?>" class="<?= $baseClass . $activeClass ?>">
                                <div class="flex items-center gap-3">
                                    <i class="ph-fill ph-folder <?= $isActive ? 'text-indigo-500' : 'text-slate-400 group-hover:text-indigo-400' ?> text-lg transition-colors"></i>
                                    <span class="text-sm"><?= htmlspecialchars($d['name']) ?></span>
                                </div>
                                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md <?= $isActive ? 'bg-indigo-100 dark:bg-indigo-500/30' : 'bg-slate-100 dark:bg-slate-800' ?>">
                                    <?= htmlspecialchars($d['code']) ?>
                                </span>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center p-4 text-slate-400 text-xs font-medium">Belum ada divisi.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-8 xl:col-span-9">
            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col min-h-[500px]">
                
                <form id="permForm" method="POST" class="flex flex-col h-full m-0">
                    <input type="hidden" name="division_id" value="<?= $activeDivId ?>">
                    
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
                        <div>
                            <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1">Access Control For</p>
                            <h3 class="font-black text-slate-800 dark:text-white text-lg flex items-center gap-2">
                                <i class="ph-fill ph-shield-check text-emerald-500"></i>
                                <?= htmlspecialchars($activeDivName) ?>
                            </h3>
                        </div>
                        
                        <button type="submit" name="save_permissions" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-indigo-500/30 transition-all transform active:scale-95 whitespace-nowrap">
                            <i class="ph-bold ph-floppy-disk text-lg"></i> Simpan Akses
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto modern-scrollbar flex-grow pb-10">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50/80 dark:bg-slate-900/50">
                                <tr>
                                    <th class="px-8 py-4 border-b border-slate-200 dark:border-slate-700 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[80%]">Menu Name / Navigation</th>
                                    <th class="px-8 py-4 border-b border-slate-200 dark:border-slate-700 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-[20%]">Allow Access</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                                <?php if(empty($menuTree)): ?>
                                    <tr>
                                        <td colspan="2" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                                <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                                    <i class="ph-fill ph-warning-circle text-4xl text-slate-300 dark:text-slate-600"></i>
                                                </div>
                                                <h4 class="font-black text-slate-700 dark:text-slate-200 text-base mb-1">Menu Tidak Ditemukan</h4>
                                                <p class="text-sm font-medium">Belum ada data menu di dalam database.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($menuTree as $parent): ?>
                                        <tr class="bg-slate-50/50 dark:bg-slate-800/20 hover:bg-slate-100 dark:hover:bg-slate-800/50 transition-colors group">
                                            <td class="px-8 py-4 align-middle">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-lg bg-white dark:bg-[#1A222C] border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 shadow-sm group-hover:text-indigo-500 transition-colors">
                                                        <i class="<?= htmlspecialchars($parent['icon']) ?> text-base"></i>
                                                    </div>
                                                    <span class="font-bold text-slate-800 dark:text-slate-200 text-sm tracking-wide">
                                                        <?= htmlspecialchars($parent['menu_label']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-8 py-4 align-middle text-center border-l border-slate-100 dark:border-slate-800/50">
                                                <div class="flex justify-center items-center h-full">
                                                    <input type="checkbox" name="menus[]" value="<?= $parent['id'] ?>" data-key="<?= $parent['menu_key'] ?>" class="w-5 h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 cursor-pointer transition-all parent-check accent-indigo-600 shadow-inner" <?= ($parent['is_permitted'] > 0) ? 'checked' : '' ?>>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <?php if(!empty($parent['children'])): ?>
                                            <?php foreach($parent['children'] as $child): ?>
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                                <td class="px-8 py-3.5 align-middle pl-16">
                                                    <div class="flex items-center gap-3">
                                                        <i class="ph-bold ph-arrow-elbow-down-right text-slate-300 dark:text-slate-600 text-lg"></i>
                                                        <span class="font-medium text-slate-600 dark:text-slate-400 text-sm">
                                                            <?= htmlspecialchars($child['menu_label']) ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-8 py-3.5 align-middle text-center border-l border-slate-100 dark:border-slate-800/50">
                                                    <div class="flex justify-center items-center h-full">
                                                        <input type="checkbox" name="menus[]" value="<?= $child['id'] ?>" class="w-4.5 h-4.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 cursor-pointer transition-all child-check child-<?= $parent['menu_key'] ?> accent-indigo-500" <?= ($child['is_permitted'] > 0) ? 'checked' : '' ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

            </div>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const parentChecks = document.querySelectorAll('.parent-check');
        
        parentChecks.forEach(parent => {
            parent.addEventListener('change', function() {
                const key = this.getAttribute('data-key');
                const children = document.querySelectorAll('.child-' + key);
                
                // Jika Parent dicentang -> Anak ikut dicentang
                // Jika Parent di-uncheck -> Anak ikut di-uncheck
                children.forEach(child => {
                    child.checked = this.checked;
                });
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>