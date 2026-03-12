<?php
// =========================================
// 1. INITIALIZATION & CONFIG
// =========================================
ini_set('display_errors', 0); // Matikan display error di production agar UI tidak rusak
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Konfigurasi & Fungsi DULUAN
require_once '../config/database.php'; 
// include '../config/functions.php'; // Sesuaikan jika ada fungsi eksternal

// --- PERMISSION CHECK ---
$can_access = false;
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $can_access = true;
} else {
    $my_div = isset($_SESSION['division_id']) ? intval($_SESSION['division_id']) : 0;
    
    if ($my_div === 0 && isset($_SESSION['user_id'])) {
        $uID = intval($_SESSION['user_id']);
        $qDiv = $conn->query("SELECT division_id FROM users WHERE id = $uID");
        if ($qDiv && $qDiv->num_rows > 0) {
            $my_div = intval($qDiv->fetch_assoc()['division_id']);
            $_SESSION['division_id'] = $my_div;
        }
    }
    
    $page_url = basename($_SERVER['PHP_SELF']); 
    $sqlPerm = "SELECT dp.menu_id FROM division_permissions dp 
                JOIN menus m ON dp.menu_id = m.id 
                WHERE dp.division_id = $my_div AND m.url LIKE '%$page_url'";
    $chk = $conn->query($sqlPerm);
    if ($chk && $chk->num_rows > 0) $can_access = true;
}

if (!$can_access) {
    echo "<script>alert('Akses Ditolak! Anda tidak memiliki izin.'); window.location='dashboard.php';</script>";
    exit;
}

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
    
    echo "<script>alert('Akses menu berhasil diperbarui!'); window.location='manage_roles.php?division_id=$divisionId';</script>";
}

// --- 2. AMBIL DATA DIVISI ---
$divisions = $conn->query("SELECT * FROM divisions ORDER BY name ASC");

// Divisi yang sedang diedit (Default: 1 jika tidak ada parameter)
$activeDivId = isset($_GET['division_id']) ? intval($_GET['division_id']) : 1; 

// Ambil Nama & Kode Divisi Aktif
$activeDivName = '';
$activeDivCode = '';
$dNameQ = $conn->query("SELECT name, code FROM divisions WHERE id = $activeDivId");
if($dNameQ->num_rows > 0) {
    $rowD = $dNameQ->fetch_assoc();
    $activeDivName = $rowD['name'];
    $activeDivCode = $rowD['code'];
}

// --- 3. AMBIL SEMUA MENU & STATUS PERMISSION ---
$sqlMenus = "SELECT m.*, 
            (SELECT COUNT(*) FROM division_permissions dp WHERE dp.division_id = $activeDivId AND dp.menu_id = m.id) as is_permitted
            FROM menus m 
            ORDER BY m.sort_order ASC";
$menusRes = $conn->query($sqlMenus);

// --- 4. SUSUN MENU JADI TREE (PARENT -> CHILDREN) ---
$menuTree = [];
$allMenus = [];
if ($menusRes) {
    while($row = $menusRes->fetch_assoc()) {
        $row['children'] = []; 
        $allMenus[$row['menu_key']] = $row;
    }
}

foreach ($allMenus as $key => &$menu) {
    if (!empty($menu['parent_menu'])) {
        if (isset($allMenus[$menu['parent_menu']])) {
            $allMenus[$menu['parent_menu']]['children'][] = $menu;
        }
    } else {
        $menuTree[] = &$menu;
    }
}
unset($menu);

$page_title = "Manage Roles & Permissions";
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modern-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .modern-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .modern-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .modern-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
    
    /* Custom Toggle Switch Styling */
    .toggle-checkbox:checked {
        right: 0;
        border-color: #4f46e5; /* indigo-600 */
    }
    .toggle-checkbox:checked + .toggle-label {
        background-color: #4f46e5; /* indigo-600 */
    }
    .dark .toggle-checkbox:checked + .toggle-label {
        background-color: #6366f1; /* indigo-500 */
    }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-fingerprint"></i>
                </div>
                Role Permissions
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Desain akses menu interaktif. Nyalakan toggle untuk memberikan hak akses pada Divisi/Departemen.</p>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <button onclick="window.location.href='manage_roles.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh Halaman">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <div class="lg:col-span-4 xl:col-span-3 flex flex-col gap-4">
            
            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col h-[calc(100vh-160px)] sticky top-24">
                
                <div class="p-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
                    <h3 class="font-black text-slate-800 dark:text-white text-sm uppercase tracking-widest mb-4 flex items-center gap-2">
                        <i class="ph-fill ph-buildings text-indigo-500 text-lg"></i> Direktori Divisi
                    </h3>
                    
                    <div class="relative">
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="searchDiv" onkeyup="filterDivisions()" placeholder="Cari divisi..." class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500/50 outline-none dark:text-white transition-all shadow-inner">
                    </div>
                </div>
                
                <div class="p-3 flex flex-col gap-1.5 overflow-y-auto modern-scrollbar flex-grow" id="divisionList">
                    <?php if($divisions && $divisions->num_rows > 0): ?>
                        <?php $divisions->data_seek(0); while($d = $divisions->fetch_assoc()): 
                            $isActive = ($activeDivId == $d['id']);
                            $baseClass = "div-item flex flex-col px-4 py-3 rounded-2xl transition-all cursor-pointer group border ";
                            $activeClass = $isActive 
                                ? "bg-indigo-50 dark:bg-indigo-500/10 border-indigo-200 dark:border-indigo-500/20 shadow-sm" 
                                : "bg-transparent border-transparent hover:bg-slate-50 dark:hover:bg-slate-800/50";
                        ?>
                            <a href="?division_id=<?= $d['id'] ?>" class="<?= $baseClass . $activeClass ?>">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-sm div-name <?= $isActive ? 'text-indigo-700 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300' ?>">
                                        <?= htmlspecialchars($d['name']) ?>
                                    </span>
                                    <i class="ph-bold ph-caret-right <?= $isActive ? 'text-indigo-500 opacity-100' : 'text-slate-300 dark:text-slate-600 opacity-0 group-hover:opacity-100' ?> transition-opacity"></i>
                                </div>
                                <span class="text-[9px] font-black uppercase tracking-widest <?= $isActive ? 'text-indigo-500' : 'text-slate-400' ?>">
                                    <i class="ph-fill ph-tag mr-1"></i><?= htmlspecialchars($d['code']) ?>
                                </span>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center p-6 text-slate-400 text-xs font-medium">
                            <i class="ph-fill ph-warning-circle text-3xl mb-2"></i><br>Belum ada divisi.
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <div class="lg:col-span-8 xl:col-span-9 flex flex-col gap-6">
            
            <div class="bg-indigo-600 rounded-3xl p-6 sm:p-8 shadow-lg shadow-indigo-500/30 relative overflow-hidden text-white flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="absolute -right-10 -top-10 opacity-10 pointer-events-none">
                    <i class="ph-fill ph-shield-check text-[150px]"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-indigo-200 text-xs font-bold uppercase tracking-widest mb-1">Mengatur Akses Untuk</p>
                    <h2 class="text-3xl font-black flex items-center gap-3">
                        <?= htmlspecialchars($activeDivName) ?>
                        <span class="text-[10px] bg-indigo-500/50 border border-indigo-400/50 px-2.5 py-1 rounded-lg tracking-widest uppercase align-middle">
                            <?= htmlspecialchars($activeDivCode) ?>
                        </span>
                    </h2>
                </div>
                <div class="relative z-10 flex gap-2 w-full sm:w-auto">
                    <button type="button" onclick="toggleAll(true)" class="flex-1 sm:flex-none px-4 py-2 bg-white/10 hover:bg-white/20 rounded-xl text-xs font-bold backdrop-blur-sm transition-colors border border-white/20">Check All</button>
                    <button type="button" onclick="toggleAll(false)" class="flex-1 sm:flex-none px-4 py-2 bg-black/10 hover:bg-black/20 rounded-xl text-xs font-bold backdrop-blur-sm transition-colors border border-black/20">Uncheck All</button>
                </div>
            </div>

            <form id="permForm" method="POST" class="flex-grow flex flex-col relative pb-24">
                <input type="hidden" name="division_id" value="<?= $activeDivId ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 gap-5">
                    <?php if(empty($menuTree)): ?>
                        <div class="col-span-full bg-white dark:bg-[#24303F] rounded-3xl p-10 text-center border border-slate-200 dark:border-slate-800 shadow-sm">
                            <i class="ph-fill ph-warning-circle text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
                            <h4 class="text-lg font-black text-slate-700 dark:text-slate-200 mb-1">Menu Kosong</h4>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada menu yang dikonfigurasi dalam sistem.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($menuTree as $parent): 
                            $isParentChecked = ($parent['is_permitted'] > 0);
                        ?>
                            <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col overflow-hidden hover:shadow-md transition-shadow">
                                
                                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/30 flex justify-between items-center">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-indigo-500 shadow-sm">
                                            <i class="<?= htmlspecialchars($parent['icon']) ?> text-xl"></i>
                                        </div>
                                        <span class="font-black text-slate-800 dark:text-slate-200 text-sm">
                                            <?= htmlspecialchars($parent['menu_label']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="relative inline-block w-12 mr-2 align-middle select-none transition duration-200 ease-in">
                                        <input type="checkbox" name="menus[]" value="<?= $parent['id'] ?>" data-key="<?= $parent['menu_key'] ?>" id="toggle_<?= $parent['id'] ?>" class="toggle-checkbox parent-check absolute block w-6 h-6 rounded-full bg-white border-4 border-slate-200 dark:border-slate-600 appearance-none cursor-pointer z-10 transition-transform duration-300 ease-in-out focus:outline-none" <?= $isParentChecked ? 'checked' : '' ?>/>
                                        <label for="toggle_<?= $parent['id'] ?>" class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-200 dark:bg-slate-700 cursor-pointer transition-colors duration-300 ease-in-out"></label>
                                    </div>
                                </div>

                                <div class="p-5 flex-grow bg-white dark:bg-[#24303F]">
                                    <?php if(!empty($parent['children'])): ?>
                                        <div class="space-y-3">
                                            <?php foreach($parent['children'] as $child): 
                                                $isChildChecked = ($child['is_permitted'] > 0);
                                            ?>
                                                <div class="flex justify-between items-center p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors border border-transparent hover:border-slate-100 dark:hover:border-slate-700/50">
                                                    <div class="flex items-center gap-3">
                                                        <i class="ph-bold ph-arrow-elbow-down-right text-slate-300 dark:text-slate-600 text-lg ml-2"></i>
                                                        <span class="font-bold text-slate-600 dark:text-slate-400 text-xs">
                                                            <?= htmlspecialchars($child['menu_label']) ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="relative inline-block w-10 mr-2 align-middle select-none transition duration-200 ease-in">
                                                        <input type="checkbox" name="menus[]" value="<?= $child['id'] ?>" data-parent="<?= $parent['menu_key'] ?>" id="toggle_<?= $child['id'] ?>" class="toggle-checkbox child-check child-<?= $parent['menu_key'] ?> absolute block w-5 h-5 rounded-full bg-white border-4 border-slate-200 dark:border-slate-600 appearance-none cursor-pointer z-10 transition-transform duration-300 ease-in-out focus:outline-none" <?= $isChildChecked ? 'checked' : '' ?>/>
                                                        <label for="toggle_<?= $child['id'] ?>" class="toggle-label block overflow-hidden h-5 rounded-full bg-slate-200 dark:bg-slate-700 cursor-pointer transition-colors duration-300 ease-in-out"></label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="h-full flex items-center justify-center">
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 italic">No Sub-menus</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="fixed bottom-8 right-8 z-40">
                    <button type="submit" name="save_permissions" class="group flex items-center justify-center gap-3 bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-500 text-white font-black py-4 px-8 rounded-full shadow-2xl shadow-indigo-900/30 dark:shadow-indigo-500/40 transition-all hover:scale-105 active:scale-95 border border-slate-700 dark:border-indigo-400">
                        <i class="ph-bold ph-floppy-disk text-2xl group-hover:animate-bounce"></i>
                        <span class="text-sm tracking-widest uppercase">Simpan Konfigurasi</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    // --- LOGIKA LIVE SEARCH DIVISI ---
    function filterDivisions() {
        let input = document.getElementById('searchDiv').value.toLowerCase();
        let items = document.querySelectorAll('.div-item');
        
        items.forEach(item => {
            let name = item.querySelector('.div-name').innerText.toLowerCase();
            if(name.includes(input)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // --- LOGIKA TOGGLE PARENT & CHILD ---
    document.addEventListener('DOMContentLoaded', function() {
        const parentChecks = document.querySelectorAll('.parent-check');
        const childChecks = document.querySelectorAll('.child-check');
        
        // Jika Parent di klik -> Semua Child ikut
        parentChecks.forEach(parent => {
            parent.addEventListener('change', function() {
                const key = this.getAttribute('data-key');
                const children = document.querySelectorAll('.child-' + key);
                children.forEach(child => {
                    child.checked = this.checked;
                });
            });
        });

        // Jika Child di klik (dicentang) -> Pastikan Parent Otomatis Dicentang
        // Karena anak menu tidak bisa diakses jika induk menu mati
        childChecks.forEach(child => {
            child.addEventListener('change', function() {
                if(this.checked) {
                    const parentKey = this.getAttribute('data-parent');
                    const parent = document.querySelector(`.parent-check[data-key="${parentKey}"]`);
                    if(parent) {
                        parent.checked = true;
                    }
                }
            });
        });
    });

    // --- LOGIKA TOMBOL CHECK ALL / UNCHECK ALL ---
    function toggleAll(status) {
        const allToggles = document.querySelectorAll('.toggle-checkbox');
        allToggles.forEach(toggle => {
            toggle.checked = status;
        });
    }
</script>

<?php include 'includes/footer.php'; ?>