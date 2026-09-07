<?php
require_once __DIR__ . '/admin_guard.php';

$baseUrl = rtrim(BASE_URL, '/');
$moduleBase = $baseUrl . '/propertycustodian/';

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
    .dashboard-main-wrapper .text-gray-300,
    .dashboard-main-wrapper .text-gray-400 {
        color: #475569 !important;
    }

    .dashboard-main-wrapper .table thead th,
    .dashboard-main-wrapper .h6.text-gray-300 {
        color: #334155 !important;
    }

    .dashboard-main-wrapper .btn-action {
        background-color: #ffffff !important;
        color: #334155 !important;
        border: 1px solid #cbd5e1 !important;
    }

    .dashboard-main-wrapper .btn-action-primary {
        color: #1d4ed8 !important;
        border-color: #93c5fd !important;
    }

    .dashboard-main-wrapper .btn-action-danger {
        color: #b91c1c !important;
        border-color: #fecaca !important;
    }
</style>
<aside class="sidebar">
    <button type="button" class="sidebar-close-btn text-gray-500 hover-text-white hover-bg-main-600 text-md w-24 h-24 border border-gray-100 hover-border-main-600 d-xl-none d-flex flex-center rounded-circle position-absolute"><i class="ph ph-x"></i></button>

    <a href="<?= $moduleBase ?>dashboard.php" class="sidebar__logo text-center p-5 pt-5 position-sticky inset-block-start-0 bg-white w-100 z-1">
        <img src="<?= IMG_PATH ?>/logo/cotsu.png" alt="Logo" class="sidebar__brand-logo">
    </a>

    <div class="sidebar-menu-wrapper overflow-y-auto scroll-sm">
        <div class="p-5 pt-5">
            <ul class="sidebar-menu">
                <li class="sidebar-menu__item<?= $isActive(['dashboard.php', 'index.php']) ?>">
                    <a href="<?= $moduleBase ?>dashboard.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-squares-four"></i></span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-menu__item has-dropdown<?= $isActive(['ics.php', 'ics_items.php', 'par.php', 'par_items.php', 'property_cards.php', 'property_card_transactions.php', 'property_transfers.php']) ?>">
                    <a href="javascript:void(0)" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-briefcase"></i></span>
                        <span class="text">Property Management</span>
                    </a>
                    <ul class="sidebar-submenu">
                        <li class="sidebar-submenu__item">
                            <a href="<?= $moduleBase ?>ics.php" class="sidebar-submenu__link<?= $isActive('ics.php') ?>">ICS Headers</a>
                        </li>
                        <li class="sidebar-submenu__item">
                            <a href="<?= $moduleBase ?>ics_items.php" class="sidebar-submenu__link<?= $isActive('ics_items.php') ?>">ICS Items</a>
                        </li>
                        <li class="sidebar-submenu__item">
                            <a href="<?= $moduleBase ?>par.php" class="sidebar-submenu__link<?= $isActive('par.php') ?>">PAR Headers</a>
                        </li>
                        <li class="sidebar-submenu__item">
                            <a href="<?= $moduleBase ?>par_items.php" class="sidebar-submenu__link<?= $isActive('par_items.php') ?>">PAR Items</a>
                        </li>
                        <li class="sidebar-submenu__item">
                            <a href="<?= $moduleBase ?>property_cards.php" class="sidebar-submenu__link<?= $isActive('property_cards.php') ?>">Property Cards</a>
                        </li>
                        <li class="sidebar-submenu__item">
                            <a href="<?= $moduleBase ?>property_card_transactions.php" class="sidebar-submenu__link<?= $isActive('property_card_transactions.php') ?>">Card Movements</a>
                        </li>
                        <li class="sidebar-submenu__item">
                            <a href="<?= $moduleBase ?>property_transfers.php" class="sidebar-submenu__link<?= $isActive('property_transfers.php') ?>">Transfers</a>
                        </li>
                    </ul>
                </li>

                <li class="sidebar-menu__item">
                    <span class="text-gray-300 text-sm px-20 pt-20 fw-semibold border-top border-gray-100 d-block text-uppercase">System</span>
                </li>

                <li class="sidebar-menu__item<?= $isActive('audit.php') ?>">
                    <a href="<?= $moduleBase ?>audit.php" class="sidebar-menu__link">
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
