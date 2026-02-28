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
$email = $_SESSION['email'] ?? 'user@example.com';
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
    $sidebar_menu['dashboard'] = ['menu_label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'ph-squares-four', 'children' => []];
    $sidebar_menu['leave'] = ['menu_label' => 'Leave Request', 'url' => 'leave_list.php', 'icon' => 'ph-calendar-check', 'children' => []];
    $sidebar_menu['delivery'] = ['menu_label' => 'Delivery', 'url' => 'delivery_list.php', 'icon' => 'ph-truck', 'children' => []];
    
    $sidebar_menu['helpdesk'] = ['menu_label' => 'Helpdesk', 'url' => '#', 'icon' => 'ph-headset', 'children' => [
        ['menu_label' => 'External Tickets', 'url' => 'tickets.php'], 
        ['menu_label' => 'Internal Tickets', 'url' => 'internal_tickets.php']
    ]];
    
    $sidebar_menu['sales'] = ['menu_label' => 'Sales', 'url' => '#', 'icon' => 'ph-handshake', 'children' => [
        ['menu_label' => 'Dashboard Clients', 'url' => 'dashboard_clients.php'], 
        ['menu_label' => 'Client List', 'url' => 'clients.php']
    ]];
    
    $sidebar_menu['finance'] = ['menu_label' => 'Finance', 'url' => '#', 'icon' => 'ph-currency-circle-dollar', 'children' => [
        ['menu_label' => 'Vendor List', 'url' => 'vendor_list.php'], 
        ['menu_label' => 'Purchase Orders', 'url' => 'po_list.php'], 
        ['menu_label' => 'Quotations', 'url' => 'quotation_list.php'], 
        ['menu_label' => 'PO From Client', 'url' => 'po_client_list.php'], 
        ['menu_label' => 'Invoices', 'url' => 'invoice_list.php'], 
        ['menu_label' => 'Payments', 'url' => 'payment_list.php'], 
        ['menu_label' => 'Delivery Orders', 'url' => 'delivery_order_list.php']
    ]];
    
    $sidebar_menu['admin'] = ['menu_label' => 'Administration', 'url' => '#', 'icon' => 'ph-gear', 'children' => [
        ['menu_label' => 'Manage Users', 'url' => 'manage_users.php'], 
        ['menu_label' => 'Manage Divisions', 'url' => 'manage_divisions.php'], 
        ['menu_label' => 'Manage Permissions', 'url' => 'manage_roles.php'], 
        ['menu_label' => 'Settings', 'url' => 'settings.php']
    ]];
    
    $sidebar_menu['ops'] = [
        'menu_label' => 'Telkomsel Ops', 
        'url' => '#', 
        'icon' => 'ph-broadcast', 
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
                        // Mengganti icon bawaan (bi) ke phosphor (ph)
                        $icon = str_replace(['bi bi-', 'bi-'], ['ph ', 'ph-'], $row['icon']);
                        $row['icon'] = strpos($icon, 'ph-') === false ? 'ph-folder' : $icon;
                        
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

// Style Tailwind untuk state menu
$active_link_style = "bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 shadow-sm font-bold";
$inactive_link_style = "text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-white font-medium";

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
?>

<aside id="sidebar" class="group fixed left-0 top-0 z-[100] flex h-screen w-[280px] [&.is-collapsed]:w-[88px] flex-col overflow-y-hidden bg-white dark:bg-[#24303F] transition-all duration-300 ease-in-out lg:static lg:translate-x-0 -translate-x-full border-r border-slate-100 dark:border-slate-800 shrink-0 shadow-2xl lg:shadow-none font-sans">
    
    <div class="flex items-center justify-between lg:justify-start gap-3 px-6 group-[.is-collapsed]:px-0 group-[.is-collapsed]:justify-center pt-8 pb-6 lg:pt-10 lg:pb-8 transition-all duration-300 shrink-0">
        <a href="dashboard.php" class="flex items-center gap-3 overflow-hidden">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/20">
                <i class="ph-fill ph-lifebuoy text-xl"></i>
            </div>
            <span class="text-xl font-extrabold tracking-tight text-slate-800 dark:text-white group-[.is-collapsed]:hidden whitespace-nowrap">
                Helpdesk<span class="text-indigo-600 dark:text-indigo-400">.</span>
            </span>
        </a>
        <button id="closeSidebarMobile" class="lg:hidden text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
            <i class="ph ph-x text-2xl"></i>
        </button>
    </div>

    <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear px-4 group-[.is-collapsed]:px-2 pb-4">
        <h3 class="mb-4 ml-2 text-xs font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 group-[.is-collapsed]:hidden mt-2">
            Menu Utama
        </h3>

        <nav class="mt-1 flex-grow">
            <ul class="mb-6 flex flex-col gap-1.5">
                <?php if (!empty($sidebar_menu)): ?>
                    <?php foreach ($sidebar_menu as $key => $menu): ?>
                        
                        <?php 
                            $icon_class = str_replace(['bi bi-', 'bi-'], ['ph ', 'ph-'], $menu['icon']);
                            if(strpos($icon_class, 'ph ') === false) { $icon_class = 'ph ' . $icon_class; }
                        ?>

                        <?php if (empty($menu['children'])): ?>
                            <?php 
                                $is_active = ($current_page == $menu['url'] || (isset($mappings[$current_page]) && $mappings[$current_page] == $menu['url']));
                                $link_class = $is_active ? $active_link_style : $inactive_link_style;
                            ?>
                            <li>
                                <a href="<?= htmlspecialchars($menu['url']) ?>" class="group/link relative flex items-center gap-3 rounded-xl px-3 py-2.5 transition-all duration-200 <?= $link_class ?>" title="<?= htmlspecialchars($menu['menu_label']) ?>">
                                    <i class="<?= $icon_class ?> text-xl shrink-0 group-[.is-collapsed]:mx-auto <?= $is_active ? 'ph-fill' : '' ?>"></i>
                                    <span class="group-[.is-collapsed]:hidden truncate"><?= htmlspecialchars($menu['menu_label']) ?></span>
                                    
                                    <div class="absolute left-full ml-4 hidden group-hover/link:group-[.is-collapsed]:block bg-slate-800 text-white text-xs px-2.5 py-1.5 rounded-lg whitespace-nowrap z-50">
                                        <?= htmlspecialchars($menu['menu_label']) ?>
                                    </div>
                                </a>
                            </li>
                        
                        <?php else: ?>
                            <?php $isActiveGroup = isChildActive($menu['children'], $current_page); ?>
                            
                            <li>
                                <button class="group/btn relative flex w-full items-center justify-between rounded-xl px-3 py-2.5 transition-all duration-200 <?= $isActiveGroup ? 'bg-slate-50 dark:bg-slate-800/50' : '' ?> <?= $inactive_link_style ?>" aria-expanded="<?= $isActiveGroup ? 'true' : 'false' ?>" onclick="toggleSubmenu(this)" title="<?= htmlspecialchars($menu['menu_label']) ?>">
                                    <div class="flex items-center gap-3 overflow-hidden">
                                        <i class="<?= $icon_class ?> text-xl shrink-0 group-[.is-collapsed]:mx-auto <?= $isActiveGroup ? 'text-indigo-600 dark:text-indigo-400 ph-fill' : '' ?>"></i>
                                        <span class="group-[.is-collapsed]:hidden truncate <?= $isActiveGroup ? 'font-bold text-slate-800 dark:text-white' : '' ?>"><?= htmlspecialchars($menu['menu_label']) ?></span>
                                    </div>
                                    <i class="ph ph-caret-down shrink-0 transition-transform duration-200 group-[.is-collapsed]:hidden <?= $isActiveGroup ? 'rotate-180' : '' ?>"></i>
                                    
                                    <div class="absolute left-full ml-4 hidden group-hover/btn:group-[.is-collapsed]:block bg-slate-800 text-white text-xs px-2.5 py-1.5 rounded-lg whitespace-nowrap z-50">
                                        <?= htmlspecialchars($menu['menu_label']) ?>
                                    </div>
                                </button>
                                
                                <ul class="mt-1 flex flex-col gap-1 pl-9 pr-2 overflow-hidden transition-all duration-300 ease-in-out group-[.is-collapsed]:hidden <?= $isActiveGroup ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0' ?>">
                                    <?php foreach ($menu['children'] as $child): 
                                        $isChildActive = isChildActive([$child], $current_page);
                                    ?>
                                        <li>
                                            <a href="<?= htmlspecialchars($child['url']) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors <?= $isChildActive ? 'text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50/50 dark:bg-indigo-500/5' : 'text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800/50' ?>">
                                                <div class="w-1.5 h-1.5 rounded-full <?= $isChildActive ? 'bg-indigo-600 dark:bg-indigo-400' : 'bg-slate-300 dark:bg-slate-600' ?>"></div>
                                                <span class="truncate"><?= htmlspecialchars($child['menu_label']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                
                <?php else: ?>
                    <li class="px-3 py-4 mt-2 bg-red-50 dark:bg-red-900/10 rounded-xl border border-red-100 dark:border-red-900/30 group-[.is-collapsed]:hidden">
                        <div class="flex items-start gap-3 text-red-600 dark:text-red-400">
                            <i class="ph-fill ph-warning-circle text-xl mt-0.5"></i>
                            <div>
                                <h6 class="font-bold text-sm mb-1">Akses Terbatas</h6>
                                <p class="text-xs opacity-80 leading-relaxed">Hubungi admin untuk mendapatkan akses menu.</p>
                            </div>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</aside>

<script>
    function toggleSubmenu(button) {
        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        const submenu = button.nextElementSibling;
        const caret = button.querySelector('.ph-caret-down');
        
        const sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('is-collapsed') && window.innerWidth >= 1024) return;

        if (isExpanded) {
            button.setAttribute('aria-expanded', 'false');
            submenu.style.maxHeight = '0px';
            submenu.style.opacity = '0';
            caret.style.transform = 'rotate(0deg)';
            button.classList.remove('bg-slate-50', 'dark:bg-slate-800/50');
        } else {
            button.setAttribute('aria-expanded', 'true');
            submenu.style.maxHeight = submenu.scrollHeight + 'px';
            submenu.style.opacity = '1';
            caret.style.transform = 'rotate(180deg)';
            button.classList.add('bg-slate-50', 'dark:bg-slate-800/50');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('button[aria-expanded="true"]').forEach(btn => {
            const submenu = btn.nextElementSibling;
            if(submenu) submenu.style.maxHeight = submenu.scrollHeight + 'px';
        });
    });
</script>


<div id="main-content" class="flex flex-col flex-1 w-full h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 relative transition-colors duration-300">
    
    <header class="sticky top-0 z-40 flex w-full bg-white/80 backdrop-blur-md dark:bg-[#1A222C]/80 shadow-soft transition-all duration-300 border-b border-slate-100 dark:border-slate-800">
        <div class="flex flex-grow items-center justify-between px-4 py-4 md:px-6 2xl:px-11 h-20">
            
            <div class="flex items-center gap-4 sm:gap-6">
                <button id="sidebarToggle" class="z-50 block rounded-lg p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer transition-colors" title="Toggle Sidebar">
                     <i class="ph ph-list text-2xl"></i>
                </button>
            </div>

            <div class="flex items-center gap-3 2xsm:gap-6">
                
                <ul class="flex items-center gap-2">
                     <li>
                        <button id="darkModeToggle" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all" title="Ubah Tema">
                            <i class="ph ph-moon text-xl dark:hidden"></i>
                            <i class="ph ph-sun text-xl hidden dark:block text-amber-400"></i>
                        </button>
                    </li>
                </ul>

                <div class="relative">
                    <div id="profileBtn" class="flex items-center gap-3 cursor-pointer pl-4 border-l border-slate-100 dark:border-slate-700 transition-colors group">
                        <span class="hidden text-right lg:block">
                            <span class="block text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($username) ?></span>
                            <span class="block text-xs font-medium text-slate-400"><?= ucfirst(htmlspecialchars($role_name)) ?></span>
                        </span>
                        
                        <div class="h-11 w-11 rounded-full overflow-hidden border-2 border-white dark:border-slate-700 ring-2 ring-slate-100 dark:ring-slate-800 shadow-sm transition-all group-hover:ring-indigo-100">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($username) ?>&background=random" alt="User" class="object-cover w-full h-full">
                        </div>
                        <i class="ph ph-caret-down text-slate-400 text-sm hidden lg:block transition-transform duration-200"></i>
                    </div>

                    <div id="profileDropdown" class="hidden absolute right-0 mt-4 flex w-64 flex-col rounded-xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-soft-lg z-50 overflow-hidden transition-all origin-top-right">
                        
                        <div class="px-6 py-5 bg-slate-50 dark:bg-slate-800/50">
                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($username) ?></p>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5"><?= htmlspecialchars($email) ?></p>
                        </div>

                        <ul class="flex flex-col gap-1 px-4 py-2">
                            <li>
                                <a href="profile.php" class="flex items-center gap-3.5 rounded-lg px-2 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                    <i class="ph ph-user text-xl"></i>
                                    Edit Profile
                                </a>
                            </li>
                            <li>
                                <a href="settings.php" class="flex items-center gap-3.5 rounded-lg px-2 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                    <i class="ph ph-gear text-xl"></i>
                                    Account Settings
                                </a>
                            </li>
                        </ul>

                        <div class="px-4 my-1">
                             <div class="border-t border-slate-100 dark:border-slate-700"></div>
                        </div>

                        <div class="px-4 pb-4 pt-1">
                             <a href="../logout.php" class="flex items-center gap-3.5 rounded-lg px-2 py-2 text-sm font-bold text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                                <i class="ph ph-sign-out text-xl"></i>
                                Sign out
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </header>
    
    <main class="flex-1 overflow-x-hidden overflow-y-auto">