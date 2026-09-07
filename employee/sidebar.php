<?php
require_once __DIR__ . '/../core/auth_guard.php';
guardRole([6]); // Employee

$baseUrl = rtrim(BASE_URL, '/');
$employeeBase = $baseUrl . '/employee/';
$profileUrl = $baseUrl . '/profile.php';
$logoutUrl = $baseUrl . '/logout.php';

$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$activeFile = basename($currentPath);

$isActive = static function ($file) use ($activeFile): string {
    if (is_array($file)) {
        return in_array($activeFile, $file, true) ? ' activePage' : '';
    }
    return $activeFile === $file ? ' activePage' : '';
};
?>
<aside class="sidebar">
    <button type="button" class="sidebar-close-btn text-gray-500 hover-text-white hover-bg-main-600 text-md w-24 h-24 border border-gray-100 hover-border-main-600 d-xl-none d-flex flex-center rounded-circle position-absolute"><i class="ph ph-x"></i></button>

    <a href="<?= $employeeBase ?>dashboard.php" class="sidebar__logo text-center p-5 pt-5 position-sticky inset-block-start-0 bg-white w-100 z-1">
        <img src="<?= IMG_PATH ?>/logo/cotsu.png" alt="Logo" class="sidebar__brand-logo">
    </a>

    <div class="sidebar-menu-wrapper overflow-y-auto scroll-sm">
        <div class="p-5 pt-5">
            <ul class="sidebar-menu">
                <li class="sidebar-menu__item<?= $isActive('dashboard.php') ?>">
                    <a href="<?= $employeeBase ?>dashboard.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-squares-four"></i></span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-menu__item<?= $isActive(['requisition.php', 'request-details.php']) ?>">
                    <a href="<?= $employeeBase ?>requisition.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-paper-plane-tilt"></i></span>
                        <span class="text">Request Supplies / Property</span>
                    </a>
                </li>

                <li class="sidebar-menu__item<?= $isActive('acknowledgment.php') ?>">
                    <a href="<?= $employeeBase ?>acknowledgment.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-list-checks"></i></span>
                        <span class="text">View Request Status</span>
                    </a>
                </li>

                <li class="sidebar-menu__item<?= $isActive('assigned-property.php') ?>">
                    <a href="<?= $employeeBase ?>assigned-property.php" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-briefcase"></i></span>
                        <span class="text">Assigned Property</span>
                    </a>
                </li>

                <li class="sidebar-menu__item">
                    <span class="text-gray-300 text-sm px-20 pt-20 fw-semibold border-top border-gray-100 d-block text-uppercase">Account</span>
                </li>

                <li class="sidebar-menu__item<?= $isActive('profile.php') ?>">
                    <a href="<?= $profileUrl ?>" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-user-circle"></i></span>
                        <span class="text">My Profile</span>
                    </a>
                </li>

                <li class="sidebar-menu__item<?= $isActive('logout.php') ?>">
                    <a href="<?= $logoutUrl ?>" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-sign-out"></i></span>
                        <span class="text">Sign Out</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>
