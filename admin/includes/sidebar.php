<?php
// 1. Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Pastikan Koneksi Database Ada (Pencegahan Error)
if (!isset($conn)) {
    $db_path = __DIR__ . '/../../config/database.php'; 
    if (file_exists($db_path)) {
        require_once $db_path;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$role_name = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : 'standard';
$username = $_SESSION['username'] ?? 'User';
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// --- Refresh Division ID (Pastikan Session Sinkron dengan DB) ---
$user_division_id = isset($_SESSION['division_id']) ? intval($_SESSION['division_id']) : 0;
if ($user_division_id <= 0 && $user_id > 0 && isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT division_id FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $resDiv = $stmt->get_result();
        if ($rowDiv = $resDiv->fetch_assoc()) {
            $user_division_id = intval($rowDiv['division_id']);
            $_SESSION['division_id'] = $user_division_id;
        }
        $stmt->close();
    }
}

// --- HELPER FUNCTION: Active State ---
if (!function_exists('isChildActive')) {
    function isChildActive($children, $current) {
        foreach ($children as $c) {
            if ($c['url'] == $current) return true;
            if (strpos($c['url'], $current) !== false && $current != 'dashboard.php') return true;
            
            // Mapping Spesifik Page -> Menu
            $mappings = [
                'po_form.php' => 'po_list.php',
                'quotation_form.php' => 'quotation_list.php',
                'invoice_form.php' => 'invoice_list.php',
                'delivery_order_form.php' => 'delivery_order_list.php',
                'view_ticket.php' => 'tickets.php',
                'internal_create.php' => 'internal_tickets.php',
                'internal_view.php' => 'internal_tickets.php',
                'tsel_upload.php' => 'tsel_inject.php',
                'tsel_history.php' => 'tsel_inject.php',
                'tsel_dashboard.php' => 'tsel_dashboard.php', 
                'leave_form.php' => 'leave_list.php',
                'delivery_form.php' => 'delivery_list.php' 
            ];
            
            if (isset($mappings[$current]) && $mappings[$current] == $c['url']) return true;
        }
        return false;
    }
}

$sidebar_menu = [];
$debug_msg = "";

// =========================================================================
// LOGIKA 1: ADMIN HARDCODED BYPASS (PASTI FULL AKSES)
// =========================================================================
if ($role_name === 'admin') {
    $sidebar_menu['dashboard'] = ['menu_label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'bi bi-grid-fill', 'children' => []];
    $sidebar_menu['leave'] = ['menu_label' => 'Leave Request', 'url' => 'leave_list.php', 'icon' => 'bi bi-calendar-check-fill', 'children' => []];
    $sidebar_menu['delivery'] = ['menu_label' => 'Delivery', 'url' => 'delivery_list.php', 'icon' => 'bi bi-truck', 'children' => []];
    $sidebar_menu['helpdesk'] = ['menu_label' => 'Helpdesk', 'url' => '#', 'icon' => 'bi bi-ticket-detailed-fill', 'children' => [['menu_label' => 'External Tickets', 'url' => 'tickets.php'], ['menu_label' => 'Internal Tickets', 'url' => 'internal_tickets.php']]];
    $sidebar_menu['sales'] = ['menu_label' => 'Sales', 'url' => '#', 'icon' => 'bi bi-hand-thumbs-up-fill', 'children' => [['menu_label' => 'Dashboard Clients', 'url' => 'dashboard_clients.php'], ['menu_label' => 'Client List', 'url' => 'clients.php']]];
    $sidebar_menu['finance'] = ['menu_label' => 'Finance', 'url' => '#', 'icon' => 'bi bi-currency-dollar', 'children' => [['menu_label' => 'Vendor List', 'url' => 'vendor_list.php'], ['menu_label' => 'Purchase Orders', 'url' => 'po_list.php'], ['menu_label' => 'Quotations', 'url' => 'quotation_list.php'], ['menu_label' => 'PO From Client', 'url' => 'po_client_list.php'], ['menu_label' => 'Invoices', 'url' => 'invoice_list.php'], ['menu_label' => 'Payments', 'url' => 'payment_list.php'], ['menu_label' => 'Delivery Orders', 'url' => 'delivery_order_list.php']]];
    $sidebar_menu['admin'] = ['menu_label' => 'Administration', 'url' => '#', 'icon' => 'bi bi-gear-fill', 'children' => [['menu_label' => 'Manage Users', 'url' => 'manage_users.php'], ['menu_label' => 'Manage Divisions', 'url' => 'manage_divisions.php'], ['menu_label' => 'Manage Permissions', 'url' => 'manage_roles.php'], ['menu_label' => 'Settings', 'url' => 'settings.php']]];
    $sidebar_menu['ops'] = [
        'menu_label' => 'Telkomsel Ops', 
        'url' => '#', 
        'icon' => 'bi bi-broadcast-pin', 
        'children' => [
            ['menu_label' => 'Inject Dashboard', 'url' => 'tsel_dashboard.php'],
            ['menu_label' => 'Package List', 'url' => 'tsel_packages.php'], 
            ['menu_label' => 'Inject Data', 'url' => 'tsel_inject.php']
        ]
    ];
} 
// =========================================================================
// LOGIKA 2: USER STANDARD (LOAD DARI DATABASE)
// =========================================================================
else {
    if (isset($conn) && !$conn->connect_error) {
        if ($user_division_id > 0) {
            $allowed_ids = [];
            $resPerm = $conn->query("SELECT menu_id FROM division_permissions WHERE division_id = $user_division_id");
            if ($resPerm) {
                while($p = $resPerm->fetch_assoc()) {
                    $allowed_ids[] = intval($p['menu_id']);
                }
            }

            if (!empty($allowed_ids)) {
                $ids_str = implode(',', $allowed_ids);
                $sqlMenu = "SELECT * FROM menus 
                            WHERE id IN ($ids_str) 
                            OR menu_key IN (SELECT parent_menu FROM menus WHERE id IN ($ids_str)) 
                            ORDER BY sort_order ASC";
                
                $resMenu = $conn->query($sqlMenu);

                if ($resMenu && $resMenu->num_rows > 0) {
                    $temp_menus = [];
                    while ($row = $resMenu->fetch_assoc()) {
                        $temp_menus[$row['menu_key']] = $row;
                        if (!isset($temp_menus[$row['menu_key']]['children'])) {
                            $temp_menus[$row['menu_key']]['children'] = [];
                        }
                    }

                    foreach ($temp_menus as $key => $menu) {
                        if (empty($menu['parent_menu'])) {
                            if (!isset($sidebar_menu[$key])) {
                                $sidebar_menu[$key] = $menu;
                            }
                        } else {
                            $parentKey = $menu['parent_menu'];
                            if (isset($sidebar_menu[$parentKey])) {
                                $sidebar_menu[$parentKey]['children'][] = $menu;
                            } 
                            elseif (isset($temp_menus[$parentKey])) {
                                $sidebar_menu[$parentKey] = $temp_menus[$parentKey];
                                $sidebar_menu[$parentKey]['children'][] = $menu;
                            }
                        }
                    }
                } else {
                    $debug_msg = "Query Menu Gagal/Kosong.";
                }
            } else {
                $debug_msg = "Tidak ada permission ditemukan (Div ID: $user_division_id)";
            }
        } else {
            $debug_msg = "Division ID tidak valid (0).";
        }
    } else {
        $debug_msg = "Koneksi Database bermasalah.";
    }
}

// Variables untuk Styling Tailwind
$active_link_style = "bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 shadow-sm font-bold";
$inactive_link_style = "text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-white font-medium";
?>

<aside id="sidebar" class="group fixed left-0 top-0 z-[100] flex h-screen w-[280px] [&.is-collapsed]:w-[88px] flex-col overflow-y-hidden bg-white dark:bg-[#24303F] transition-all duration-300 ease-in-out lg:static lg:translate-x-0 -translate-x-full border-r border-slate-100 dark:border-slate-800 shrink-0 shadow-2xl lg:shadow-none font-sans">
    
    <div class="flex items-center justify-between lg:justify-start gap-3 px-6 group-[.is-collapsed]:px-0 group-[.is-collapsed]:justify-center pt-8 pb-6 lg:pt-10 lg:pb-8 transition-all duration-300 shrink-0">
        <a href="dashboard.php" class="flex items-center gap-3 overflow-hidden">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-500 text-white shadow-lg shadow-indigo-500/20 transition-transform hover:scale-105">
                <i class="ph-bold ph-headset text-2xl"></i>
            </div>
            <span class="text-xl font-black text-slate-800 dark:text-white tracking-tight group-[.is-collapsed]:opacity-0 group-[.is-collapsed]:hidden whitespace-nowrap transition-all duration-300">
                Helpdesk
            </span>
        </a>
        
        <button id="closeSidebarMobile" class="block lg:hidden text-slate-400 hover:text-red-500 transition-colors ml-auto p-1">
            <i class="ph-bold ph-x text-2xl"></i>
        </button>
    </div>

    <div class="flex flex-col overflow-y-auto no-scrollbar flex-1 py-4 group-[.is-collapsed]:items-center transition-all">
        <nav class="mt-2 w-full px-4 lg:px-6 group-[.is-collapsed]:px-3">
            <h3 class="mb-3 ml-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest group-[.is-collapsed]:hidden">Menu Utama</h3>
            
            <div class="hidden group-[.is-collapsed]:flex justify-center mb-4">
                 <i class="ph-fill ph-dots-three-outline text-xl text-slate-300 dark:text-slate-600"></i>
            </div>
            
            <ul class="flex flex-col gap-2">
                <?php if (!empty($sidebar_menu)): ?>
                    <?php foreach ($sidebar_menu as $key => $menu): ?>
                        
                        <?php if (empty($menu['children'])): ?>
                            <?php $isActive = ($current_page == $menu['url'] || (isset($mappings[$current_page]) && $mappings[$current_page] == $menu['url'])); ?>
                            <li>
                                <a href="<?= htmlspecialchars($menu['url']) ?>" class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?= $isActive ? $active_link_style : $inactive_link_style ?>" title="<?= htmlspecialchars($menu['menu_label']) ?>">
                                    <i class="<?= htmlspecialchars($menu['icon']) ?> text-xl shrink-0"></i>
                                    <span class="group-[.is-collapsed]:hidden whitespace-nowrap"><?= htmlspecialchars($menu['menu_label']) ?></span>
                                </a>
                            </li>
                        
                        <?php else: ?>
                            <?php $isActive = isChildActive($menu['children'], $current_page); ?>
                            <li>
                                <div class="submenu-toggle relative flex items-center justify-between w-full rounded-xl px-4 py-3 cursor-pointer transition-all <?= $isActive ? $active_link_style : $inactive_link_style ?>" title="<?= htmlspecialchars($menu['menu_label']) ?>">
                                    <div class="flex items-center gap-3">
                                        <i class="<?= htmlspecialchars($menu['icon']) ?> text-xl shrink-0"></i>
                                        <span class="group-[.is-collapsed]:hidden whitespace-nowrap"><?= htmlspecialchars($menu['menu_label']) ?></span>
                                    </div>
                                    <i class="ph-bold ph-caret-down text-sm group-[.is-collapsed]:hidden transition-transform duration-200 <?= $isActive ? 'rotate-180' : '' ?>"></i>
                                </div>
                                
                                <ul class="submenu flex flex-col gap-1 mt-1 group-[.is-collapsed]:hidden overflow-hidden transition-all duration-300 <?= $isActive ? 'max-h-[500px]' : 'max-h-0' ?>">
                                    <?php foreach ($menu['children'] as $child): 
                                        $isChildActive = isChildActive([$child], $current_page);
                                    ?>
                                        <li>
                                            <a href="<?= htmlspecialchars($child['url']) ?>" class="block py-2 pl-11 pr-4 text-sm rounded-lg transition-colors <?= $isChildActive ? 'text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50/50 dark:bg-indigo-500/10' : 'text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50' ?>">
                                                <?= htmlspecialchars($child['menu_label']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>

                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="p-4 bg-red-50 dark:bg-red-500/10 rounded-xl text-red-500">
                        <i class="ph-bold ph-warning-circle text-2xl mb-2 block"></i>
                        <span class="font-bold text-sm block">Akses Ditolak</span>
                        <span class="text-xs text-red-400 mt-1 block">
                            Role: <?= htmlspecialchars($role_name) ?><br>
                            Div ID: <?= htmlspecialchars($user_division_id) ?><br>
                            <?= !empty($debug_msg) ? "Msg: $debug_msg" : "" ?>
                        </span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</aside>


<div id="main" class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
    
    <header class="sticky top-0 z-40 flex w-full bg-white/80 backdrop-blur-md dark:bg-[#1A222C]/80 shadow-sm transition-all duration-300 border-b border-slate-100 dark:border-slate-800">
        <div class="flex flex-grow items-center justify-between px-4 py-4 md:px-6 2xl:px-11 h-20">
            
            <div class="flex items-center gap-4 sm:gap-6">
                <button id="sidebarToggle" class="z-50 block rounded-lg p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer transition-colors">
                     <i class="ph ph-list text-2xl"></i>
                </button>
            </div>

            <div class="flex items-center gap-3 2xsm:gap-6">
                <ul class="flex items-center gap-2">
                    <li>
                        <button id="darkModeToggle" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all">
                            <i class="ph ph-moon text-xl dark:hidden"></i>
                            <i class="ph ph-sun text-xl hidden dark:block"></i>
                        </button>
                    </li>
                </ul>

                <div class="relative">
                    <div id="profileBtn" class="flex items-center gap-3 cursor-pointer pl-4 border-l border-slate-100 dark:border-slate-700 transition-colors">
                        <span class="hidden text-right lg:block">
                            <span class="block text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($username) ?></span>
                            <span class="block text-xs font-medium text-slate-400"><?= ucfirst(htmlspecialchars($role_name)) ?></span>
                        </span>
                        <div class="h-11 w-11 rounded-full overflow-hidden border-2 border-white dark:border-slate-700 ring-2 ring-slate-100 dark:ring-slate-800 shadow-sm transition-all flex items-center justify-center bg-indigo-600 text-white font-bold text-lg">
                            <?= strtoupper(substr($username, 0, 1)) ?>
                        </div>
                        <i class="ph ph-caret-down text-slate-400 text-sm hidden lg:block"></i>
                    </div>

                    <div id="profileDropdown" class="hidden absolute right-0 mt-4 flex w-64 flex-col rounded-xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-lg z-50 overflow-hidden transition-all origin-top-right">
                        <div class="px-6 py-5">
                            <p class="text-sm font-bold text-slate-800 dark:text-white">Hello, <?= htmlspecialchars($username) ?>!</p>
                        </div>

                        <ul class="flex flex-col gap-1 px-4">
                            <li>
                                <a href="../admin/profile.php" class="flex items-center gap-3.5 rounded-lg px-2 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white transition-colors">
                                    <i class="ph ph-user text-xl"></i> My Profile
                                </a>
                            </li>
                        </ul>

                        <div class="px-4 my-2"><div class="border-t border-slate-100 dark:border-slate-700"></div></div>

                        <div class="px-4 pb-4">
                             <a href="../logout.php" class="flex items-center gap-3.5 rounded-lg px-2 py-2 text-sm font-bold text-red-500 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                                <i class="ph ph-sign-out text-xl"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

<script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mobileCloseBtn = document.getElementById('closeSidebarMobile');
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            const darkModeToggle = document.getElementById('darkModeToggle');
            const html = document.documentElement;
            
            // 1. Sinkronisasi Class Body (Legacy Bootstrap) saat Load
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('theme-dark');
            } else {
                document.body.classList.remove('theme-dark');
            }

            // 2. Mobile Sidebar Close
            if (mobileCloseBtn) {
                mobileCloseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.add('-translate-x-full');
                });
            }

            // 3. Global Event Listener (Sidebar & Dropdown)
            document.addEventListener('click', function(e) {
                // Sidebar Toggle
                const burgerBtn = e.target.closest('#sidebarToggle, .burger-btn');
                if (burgerBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (window.innerWidth < 1024) {
                        sidebar.classList.toggle('-translate-x-full');
                    } else {
                        sidebar.classList.toggle('is-collapsed');
                    }
                } else {
                    if (window.innerWidth < 1024 && sidebar && !sidebar.contains(e.target) && !sidebar.classList.contains('-translate-x-full')) {
                        sidebar.classList.add('-translate-x-full');
                    }
                }

                // Profile Dropdown
                if (profileBtn && profileBtn.contains(e.target)) {
                    profileDropdown.classList.toggle('hidden');
                } else if (profileDropdown && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                }
            });

            // 4. Submenu Toggle Logic (Animasi Accordion)
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const submenu = this.nextElementSibling;
                    const icon = this.querySelector('.ph-caret-down');
                    
                    if(submenu.classList.contains('max-h-0')) {
                        submenu.classList.remove('max-h-0');
                        submenu.classList.add('max-h-[500px]');
                        if(icon) icon.classList.add('rotate-180');
                    } else {
                        submenu.classList.add('max-h-0');
                        submenu.classList.remove('max-h-[500px]');
                        if(icon) icon.classList.remove('rotate-180');
                    }
                });
            });

            // 5. Resize Handling
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    sidebar.classList.remove('-translate-x-full');
                } else {
                    sidebar.classList.remove('is-collapsed');
                    if(!sidebar.classList.contains('-translate-x-full')) {
                         sidebar.classList.add('-translate-x-full');
                    }
                }
            });

            // 6. Dark Mode Toggle Lengkap (Tailwind + Legacy Bootstrap)
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Cek jika saat ini aktif di Dark Mode
                    if (html.classList.contains('dark') || html.getAttribute('data-bs-theme') === 'dark') {
                        // Switch ke Light Mode (Mereset Kedua Sistem)
                        html.classList.remove('dark');
                        html.setAttribute('data-bs-theme', 'light');
                        document.body.classList.remove('theme-dark');
                        localStorage.setItem('theme', 'light');
                    } else {
                        // Switch ke Dark Mode (Mengaktifkan Kedua Sistem)
                        html.classList.add('dark');
                        html.setAttribute('data-bs-theme', 'dark');
                        document.body.classList.add('theme-dark');
                        localStorage.setItem('theme', 'dark');
                    }
                });
            }
        });
    </script>

    <main class="p-4 md:p-6 2xl:p-10">