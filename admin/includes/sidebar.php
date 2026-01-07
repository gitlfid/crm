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
    $sidebar_menu['ops'] = ['menu_label' => 'Telkomsel Ops', 'url' => '#', 'icon' => 'bi bi-broadcast-pin', 'children' => [['menu_label' => 'Package List', 'url' => 'tsel_packages.php'], ['menu_label' => 'Inject Data', 'url' => 'tsel_inject.php']]];
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
?>

<div id="sidebar" class="active">
    <div class="sidebar-wrapper active d-flex flex-column" style="height: 100vh;">
        <div class="sidebar-header position-relative">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo">
                    <a href="dashboard.php"><h4 class="m-0">Helpdesk</h4></a>
                </div>
                <div class="sidebar-toggler x">
                    <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
                </div>
            </div>
        </div>
        
        <div class="sidebar-menu flex-grow-1">
            <ul class="menu">
                <li class="sidebar-title">Menu Utama</li>

                <?php if (!empty($sidebar_menu)): ?>
                    <?php foreach ($sidebar_menu as $key => $menu): ?>
                        
                        <?php if (empty($menu['children'])): ?>
                            <li class="sidebar-item <?= ($current_page == $menu['url'] || (isset($mappings[$current_page]) && $mappings[$current_page] == $menu['url'])) ? 'active' : '' ?>">
                                <a href="<?= htmlspecialchars($menu['url']) ?>" class='sidebar-link'>
                                    <i class="<?= htmlspecialchars($menu['icon']) ?>"></i>
                                    <span><?= htmlspecialchars($menu['menu_label']) ?></span>
                                </a>
                            </li>
                        
                        <?php else: ?>
                            <?php $isActive = isChildActive($menu['children'], $current_page); ?>
                            <li class="sidebar-item has-sub <?= $isActive ? 'active' : '' ?>">
                                <a href="#" class='sidebar-link'>
                                    <i class="<?= htmlspecialchars($menu['icon']) ?>"></i>
                                    <span><?= htmlspecialchars($menu['menu_label']) ?></span>
                                </a>
                                <ul class="submenu <?= $isActive ? 'active' : '' ?>">
                                    <?php foreach ($menu['children'] as $child): 
                                        $isChildActive = isChildActive([$child], $current_page);
                                    ?>
                                        <li class="submenu-item <?= $isChildActive ? 'active' : '' ?>">
                                            <a href="<?= htmlspecialchars($child['url']) ?>">
                                                <?= htmlspecialchars($child['menu_label']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>

                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="sidebar-item text-danger">
                        <div class="p-3">
                            <i class="bi bi-exclamation-triangle fs-4 mb-2 d-block"></i>
                            <span class="fw-bold">Akses Ditolak</span><br>
                            <small>
                                Role: <?= htmlspecialchars($role_name) ?><br>
                                Div ID: <?= htmlspecialchars($user_division_id) ?><br>
                                <?= !empty($debug_msg) ? "Msg: $debug_msg" : "" ?>
                            </small>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<div id="main">
    <header class="mb-3">
        <nav class="navbar navbar-expand navbar-light navbar-top">
            <div class="container-fluid px-0">
                
                <a href="#" class="burger-btn d-block" onclick="toggleSidebar(event)">
                    <i class="bi bi-justify fs-3"></i>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0"></ul>
                    
                    <div class="d-flex align-items-center gap-4">
                        
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-sun fs-6 text-secondary"></i>
                            <div class="form-check form-switch mb-0" style="min-height: auto;">
                                <input class="form-check-input" type="checkbox" id="darkmode-toggle" style="cursor: pointer; width: 3em; height: 1.5em;">
                            </div>
                            <i class="bi bi-moon fs-6 text-secondary"></i>
                        </div>

                        <div class="dropdown">
                            <a href="#" data-bs-toggle="dropdown" aria-expanded="false" class="d-block text-decoration-none">
                                <div class="user-menu d-flex align-items-center">
                                    <div class="user-name text-end me-3 d-none d-md-block">
                                        <h6 class="mb-0 text-gray-600"><?= htmlspecialchars($username) ?></h6>
                                        <p class="mb-0 text-sm text-gray-600">
                                            <?= ucfirst($role_name) ?> 
                                        </p>
                                    </div>
                                    <div class="user-img">
                                        <div class="avatar avatar-md bg-primary">
                                            <span class="avatar-content text-white"><?= strtoupper(substr($username, 0, 1)) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="dropdownMenuButton" style="min-width: 12rem;">
                                <li><h6 class="dropdown-header text-muted">Hello, <?= htmlspecialchars($username) ?>!</h6></li>
                                <li><a class="dropdown-item" href="../admin/profile.php"><i class="icon-mid bi bi-person me-2"></i> My Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="icon-mid bi bi-box-arrow-left me-2"></i> Logout</a></li>
                            </ul>
                        </div>

                    </div>
                </div>

            </div>
        </nav>
    </header>

<script>
    // FUNGSI TOGGLE SIDEBAR (SMOOTH)
    function toggleSidebar(e) {
        if(e) e.preventDefault();
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        sidebar.classList.toggle('active');
        document.body.classList.toggle('sidebar-toggled'); // Optional helper class
    }

    // FUNGSI TEMA DENGAN SWITCH
    document.addEventListener('DOMContentLoaded', () => {
        const toggleSwitch = document.getElementById('darkmode-toggle');
        const savedTheme = localStorage.getItem('theme');
        
        // 1. Set Initial State
        if (savedTheme === 'dark') {
            document.body.classList.add('theme-dark');
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            if(toggleSwitch) toggleSwitch.checked = true;
        } else {
            // Default Light
            document.body.classList.remove('theme-dark');
            document.documentElement.setAttribute('data-bs-theme', 'light');
            if(toggleSwitch) toggleSwitch.checked = false;
        }

        // 2. Event Listener Change
        if(toggleSwitch) {
            toggleSwitch.addEventListener('change', function() {
                if (this.checked) {
                    // Switch ke Dark
                    document.body.classList.add('theme-dark');
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    // Switch ke Light
                    document.body.classList.remove('theme-dark');
                    document.documentElement.setAttribute('data-bs-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            });
        }
    });
</script>