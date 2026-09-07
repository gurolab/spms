<?php
require_once __DIR__ . '/../core/auth_guard.php';
guardRole([5]); // Auditor

$baseUrl = rtrim(BASE_URL, '/');
$auditorBase = $baseUrl . '/auditor/';
$adminBase = $baseUrl . '/administrator/';
$profileUrl = $baseUrl . '/profile.php';
$logoutUrl = $baseUrl . '/logout.php';

$currentPath = trim(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/');
$activePath = strtolower($currentPath);

$isActive = static function (array $targets) use ($activePath): string {
    foreach ($targets as $target) {
        if (str_ends_with($activePath, strtolower(trim($target, '/')))) {
            return ' activePage';
        }
    }
    return '';
};
?>
<aside class="sidebar">
    <button type="button" class="sidebar-close-btn text-gray-500 hover-text-white hover-bg-main-600 text-md w-24 h-24 border border-gray-100 hover-border-main-600 d-xl-none d-flex flex-center rounded-circle position-absolute"><i class="ph ph-x"></i></button>

    <a href="<?= $auditorBase ?>dashboard.php" class="sidebar__logo text-center p-5 pt-5 position-sticky inset-block-start-0 bg-white w-100 z-1">
        <img src="<?= IMG_PATH ?>/logo/cotsu.png" alt="Logo" class="sidebar__brand-logo">
    </a>

    <div class="sidebar-menu-wrapper overflow-y-auto scroll-sm">
        <div class="p-5 pt-5">
            <ul class="sidebar-menu">
                <li class="sidebar-menu__item<?= $isActive(['auditor/dashboard.php']) ?>">
                    <a href="<?= $auditorBase ?>dashboard.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-squares-four"></i></span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-menu__item<?= $isActive(['administrator/physical_inventory_reports.php', 'administrator/physical_inventory_items.php']) ?>">
                    <a href="<?= $adminBase ?>physical_inventory_reports.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-clipboard-text"></i></span>
                        <span class="text">RPCI Reports</span>
                    </a>
                </li>

                <li class="sidebar-menu__item<?= $isActive(['administrator/physical_ppe_reports.php', 'administrator/physical_ppe_items.php']) ?>">
                    <a href="<?= $adminBase ?>physical_ppe_reports.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-archive"></i></span>
                        <span class="text">RPCPPE Reports</span>
                    </a>
                </li>

                <li class="sidebar-menu__item<?= $isActive(['administrator/unserviceable_reports.php', 'administrator/unserviceable_items.php']) ?>">
                    <a href="<?= $adminBase ?>unserviceable_reports.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-warning-octagon"></i></span>
                        <span class="text">IIRUP Reports</span>
                    </a>
                </li>

                <li class="sidebar-menu__item<?= $isActive(['administrator/audit_logs.php']) ?>">
                    <a href="<?= $adminBase ?>audit_logs.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-activity"></i></span>
                        <span class="text">Audit Logs</span>
                    </a>
                </li>

                <li class="sidebar-menu__item">
                    <span class="text-gray-300 text-sm px-20 pt-20 fw-semibold border-top border-gray-100 d-block text-uppercase">Account</span>
                </li>

                <li class="sidebar-menu__item<?= $isActive(['profile.php']) ?>">
                    <a href="<?= $profileUrl ?>" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-user-circle"></i></span>
                        <span class="text">My Profile</span>
                    </a>
                </li>

                <li class="sidebar-menu__item<?= $isActive(['logout.php']) ?>">
                    <a href="<?= $logoutUrl ?>" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-sign-out"></i></span>
                        <span class="text">Sign Out</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>
