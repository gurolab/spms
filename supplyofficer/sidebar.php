<?php
require_once __DIR__ . '/../core/auth_guard.php';
guardRole([2]); // Supply Officer

$baseUrl = rtrim(BASE_URL, '/');
$supplyBase = $baseUrl . '/supplyofficer/';
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$activeFile = basename($currentPath);

$isActive = static function ($file) use ($activeFile): string {
    if (is_array($file)) {
        return in_array($activeFile, $file, true) ? ' activePage' : '';
    }

    return $activeFile === $file ? ' activePage' : '';
};
?>
<style>
    .dashboard-main-wrapper .btn-outline-primary {
        color: #1d4ed8 !important;
        border-color: #1d4ed8 !important;
    }

    .dashboard-main-wrapper .btn-outline-secondary {
        color: #334155 !important;
        border-color: #94a3b8 !important;
    }

    .dashboard-main-wrapper .text-gray-200,
    .dashboard-main-wrapper .text-gray-300 {
        color: #475569 !important;
    }
</style>
<!-- ============================ Sidebar Start ============================ -->
<aside class="sidebar">
    <button type="button" class="sidebar-close-btn text-gray-500 hover-text-white hover-bg-main-600 text-md w-24 h-24 border border-gray-100 hover-border-main-600 d-xl-none d-flex flex-center rounded-circle position-absolute">
        <i class="ph ph-x"></i>
    </button>

    <a href="<?= $supplyBase ?>index.php" class="sidebar__logo text-center p-5 pt-5 position-sticky inset-block-start-0 bg-white w-100 z-1">
        <img src="<?= IMG_PATH ?>/logo/cotsu.png" alt="Logo" class="sidebar__brand-logo">
    </a>

    <div class="sidebar-menu-wrapper overflow-y-auto scroll-sm">
        <div class="p-5 pt-5">
            <ul class="sidebar-menu">
                <li class="sidebar-menu__item<?= $isActive(['index.php']) ?>">
                    <a href="<?= $supplyBase ?>index.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-squares-four"></i></span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-menu__item has-dropdown<?= $isActive(['receive.php', 'issue_supplies.php', 'ris.php']) ?>">
                    <a href="javascript:void(0)" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-cube"></i></span>
                        <span class="text">Supply Operations</span>
                    </a>
                    <ul class="sidebar-submenu">
                        <li class="sidebar-submenu__item<?= $isActive('receive.php') ?>">
                            <a href="<?= $supplyBase ?>receive.php" class="sidebar-submenu__link">Receive Supplies</a>
                        </li>
                        <li class="sidebar-submenu__item<?= $isActive('issue_supplies.php') ?>">
                            <a href="<?= $supplyBase ?>issue_supplies.php" class="sidebar-submenu__link">Issue Supplies</a>
                        </li>
                        <li class="sidebar-submenu__item<?= $isActive('ris.php') ?>">
                            <a href="<?= $supplyBase ?>ris.php" class="sidebar-submenu__link">Manage RIS</a>
                        </li>
                    </ul>
                </li>

                <li class="sidebar-menu__item has-dropdown<?= $isActive('inventory_reports.php') ?>">
                    <a href="javascript:void(0)" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-chart-line-up"></i></span>
                        <span class="text">Inventory Reports</span>
                    </a>
                    <ul class="sidebar-submenu">
                        <li class="sidebar-submenu__item<?= $isActive('inventory_reports.php') ?>">
                            <a href="<?= $supplyBase ?>inventory_reports.php" class="sidebar-submenu__link">Overview</a>
                        </li>
                    </ul>
                </li>

                <li class="sidebar-menu__item">
                    <span class="text-gray-300 text-sm px-20 pt-20 fw-semibold border-top border-gray-100 d-block text-uppercase">System</span>
                </li>
                <li class="sidebar-menu__item<?= $isActive('audit.php') ?>">
                    <a href="<?= $supplyBase ?>audit.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-activity"></i></span>
                        <span class="text">Audit Logs</span>
                    </a>
                </li>
                <li class="sidebar-menu__item<?= $isActive('profile.php') ?>">
                    <a href="<?= $baseUrl ?>/profile.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-user-circle"></i></span>
                        <span class="text">My Profile</span>
                    </a>
                </li>
                <li class="sidebar-menu__item<?= $isActive('logout.php') ?>">
                    <a href="<?= $baseUrl ?>/logout.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-sign-out"></i></span>
                        <span class="text">Sign Out</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>
<!-- ============================ Sidebar End ============================ -->
