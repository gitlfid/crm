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
                        // Transformasi dinamis dari Bootstrap Icon ke Phosphor Icon
                        $icon = str_replace(['bi bi-', 'bi-'], ['ph-', 'ph-'], $row['icon']);
                        $icon = str_replace('-fill', '', $icon); 
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

// Diadopsi dari sidebar-sample.php
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
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-500 text-white shadow-lg shadow-indigo-500/20 transition-transform hover:scale-105">
                <i class="ph-bold ph-lifebuoy text-2xl"></i>
            </div>
            <span class="text-xl font-black text-slate-800 dark:text-white tracking-tight group-[.is-collapsed]:opacity-0 group-[.is-collapsed]:hidden whitespace-nowrap transition-all duration-300">
                Helpdesk<span class="text-indigo-600 dark:text-indigo-400">.</span>
            </span>
        </a>
        <button id="closeSidebarMobile" class="block lg:hidden text-slate-400 hover:text-red-500 transition-colors ml-auto p-1">
            <i class="ph-bold ph-x text-2xl"></i>
        </button>
    </div>

    <div class="flex flex-col overflow-y-auto no-scrollbar flex-1 py-4 group-[.is-collapsed]:items-center transition-all">
        <nav class="mt-2 w-full px-4 lg:px-6 group-[.is-collapsed]:px-3">
            
            <div class="mb-6">
                <h3 class="mb-3 ml-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest group-[.is-collapsed]:hidden">Menu Utama</h3>
                
                <div class="hidden group-[.is-collapsed]:flex justify-center mb-4">
                     <i class="ph-fill ph-dots-three-outline text-xl text-slate-300 dark:text-slate-600"></i>
                </div>
                
                <ul class="flex flex-col gap-2">
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
                                    <a href="<?= htmlspecialchars($menu['url']) ?>" class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?= $link_class ?>" title="<?= htmlspecialchars($menu['menu_label']) ?>">
                                        <i class="<?= $icon_class ?> text-xl shrink-0"></i>
                                        <span class="group-[.is-collapsed]:hidden whitespace-nowrap"><?= htmlspecialchars($menu['menu_label']) ?></span>
                                    </a>
                                </li>
                            
                            <?php else: ?>
                                <?php $isActiveGroup = isChildActive($menu['children'], $current_page); ?>
                                
                                <li>
                                    <button class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?= $isActiveGroup ? 'bg-slate-50 dark:bg-slate-800/50' : '' ?> <?= $inactive_link_style ?>" aria-expanded="<?= $isActiveGroup ? 'true' : 'false' ?>" onclick="toggleSubmenu(this)" title="<?= htmlspecialchars($menu['menu_label']) ?>">
                                        <i class="<?= $icon_class ?> text-xl shrink-0 <?= $isActiveGroup ? 'text-indigo-600 dark:text-indigo-400' : '' ?>"></i>
                                        <span class="group-[.is-collapsed]:hidden whitespace-nowrap flex-1 text-left <?= $isActiveGroup ? 'font-bold text-slate-800 dark:text-white' : '' ?>"><?= htmlspecialchars($menu['menu_label']) ?></span>
                                        <i class="ph-bold ph-caret-down shrink-0 transition-transform duration-200 group-[.is-collapsed]:hidden <?= $isActiveGroup ? 'rotate-180 text-indigo-600 dark:text-indigo-400' : '' ?>"></i>
                                    </button>
                                    
                                    <ul class="mt-1 flex flex-col gap-1 pl-11 pr-2 overflow-hidden transition-all duration-300 ease-in-out group-[.is-collapsed]:hidden <?= $isActiveGroup ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0' ?>">
                                        <?php foreach ($menu['children'] as $child): 
                                            $isChildActive = isChildActive([$child], $current_page);
                                        ?>
                                            <li>
                                                <a href="<?= htmlspecialchars($child['url']) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors <?= $isChildActive ? 'text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50/50 dark:bg-indigo-500/10' : 'text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50' ?>">
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
                        <li class="px-4 py-3 mt-2 bg-red-50 dark:bg-red-900/10 rounded-xl border border-red-100 dark:border-red-900/30 group-[.is-collapsed]:hidden">
                            <div class="flex items-start gap-3 text-red-600 dark:text-red-400">
                                <i class="ph-fill ph-warning-circle text-xl mt-0.5"></i>
                                <div>
                                    <h6 class="font-bold text-sm mb-1">Akses Terbatas</h6>
                                    <p class="text-xs opacity-80 leading-relaxed">Hubungi admin untuk mendapatkan akses menu sistem.</p>
                                </div>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
        </nav>
    </div>
</aside>

<script>
    // 1. Fungsi Toggle Accordion Menu (Submenu)
    function toggleSubmenu(button) {
        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        const submenu = button.nextElementSibling;
        const caret = button.querySelector('.ph-caret-down');
        const icon = button.querySelector('i:first-child');
        
        const sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('is-collapsed') && window.innerWidth >= 1024) return;

        if (isExpanded) {
            button.setAttribute('aria-expanded', 'false');
            submenu.style.maxHeight = '0px';
            submenu.style.opacity = '0';
            caret.style.transform = 'rotate(0deg)';
            button.classList.remove('bg-slate-50', 'dark:bg-slate-800/50');
            button.querySelector('span').classList.remove('font-bold', 'text-slate-800', 'dark:text-white');
            caret.classList.remove('text-indigo-600', 'dark:text-indigo-400');
            icon.classList.remove('text-indigo-600', 'dark:text-indigo-400');
        } else {
            button.setAttribute('aria-expanded', 'true');
            submenu.style.maxHeight = submenu.scrollHeight + 'px';
            submenu.style.opacity = '1';
            caret.style.transform = 'rotate(180deg)';
            button.classList.add('bg-slate-50', 'dark:bg-slate-800/50');
            button.querySelector('span').classList.add('font-bold', 'text-slate-800', 'dark:text-white');
            caret.classList.add('text-indigo-600', 'dark:text-indigo-400');
            icon.classList.add('text-indigo-600', 'dark:text-indigo-400');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mobileCloseBtn = document.getElementById('closeSidebarMobile');
        
        // Atur state awal untuk menu yang sedang aktif
        document.querySelectorAll('button[aria-expanded="true"]').forEach(btn => {
            const submenu = btn.nextElementSibling;
            if(submenu) {
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
            }
        });

        // 2. Menutup sidebar menggunakan tombol "X" di mode Mobile
        if (mobileCloseBtn) {
            mobileCloseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.add('-translate-x-full');
            });
        }

        // 3. Global Event Listener untuk tombol Burger di Header (Dari sidebar-sample.php)
        document.addEventListener('click', function(e) {
            const burgerBtn = e.target.closest('.ph-list, .ph-list-bold, .ph-list-dash, .ph-text-align-justify, #sidebarToggle, .burger-btn');
            
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
        });

        // 4. Mereset state Sidebar ketika ukuran layar ditarik/berubah (Resize)
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
    });
</script>