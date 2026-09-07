<?php
require_once __DIR__ . '/admin_guard.php';

$baseUrl = rtrim(BASE_URL, '/');
$adminBase = $baseUrl . '/administrator/';
$profileUrl = $baseUrl . '/profile.php';
$logoutUrl = $baseUrl . '/logout.php';
$roleId = (int) (getUser('roleid') ?? 0);

$dashboardRoutes = [
    ROLE_SYSTEM_ADMIN => $adminBase . 'dashboard.php',
    ROLE_SUPPLY_OFFICER => $baseUrl . '/supplyofficer/index.php',
    ROLE_INVENTORY_OFFICER => $baseUrl . '/inventoryofficer/dashboard.php',
    ROLE_PROPERTY_CUSTODIAN => $baseUrl . '/propertycustodian/dashboard.php',
    ROLE_AUDITOR => $baseUrl . '/auditor/dashboard.php',
    ROLE_EMPLOYEE => $baseUrl . '/employee/dashboard.php',
];
$dashboardUrl = $dashboardRoutes[$roleId] ?? ($baseUrl . '/index.php');
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$currentFile = basename($currentPath);

$accessMatrix = adminAccessMatrix();
$canAccess = static function (string $module) use ($accessMatrix, $roleId): bool {
    $allowedRoles = $accessMatrix[$module] ?? [ROLE_SYSTEM_ADMIN];
    return in_array($roleId, $allowedRoles, true);
};
$isActiveFile = static function (string $file, array $activeOn = []) use ($currentFile): bool {
    $targets = array_merge([$file], $activeOn);
    return in_array($currentFile, $targets, true);
};

$menuGroups = [
    [
        'icon' => 'ph-users',
        'label' => 'User Management',
        'items' => [
            ['file' => 'users.php', 'label' => 'Users'],
            ['file' => 'roles.php', 'label' => 'Roles'],
            ['file' => 'departments.php', 'label' => 'Departments'],
        ],
    ],
    [
        'icon' => 'ph-cube',
        'label' => 'Supply Operations',
        'items' => [
            ['file' => 'suppliers.php', 'label' => 'Suppliers'],
            ['file' => 'items.php', 'label' => 'Items'],
            ['file' => 'supply_receive.php', 'label' => 'Receive Supplies'],
            ['file' => 'supply_receive_items.php', 'label' => 'Received Items'],
            ['file' => 'ris.php', 'label' => 'Manage RIS'],
            ['file' => 'ris_items.php', 'label' => 'RIS Items'],
            ['file' => 'supply_issuance.php', 'label' => 'Issue Supplies', 'active_on' => ['supply_issuance_items.php']],
        ],
    ],
    [
        'icon' => 'ph-chart-line-up',
        'label' => 'Inventory Reports',
        'items' => [
            ['file' => 'inventory_balance.php', 'label' => 'Inventory Balance'],
            ['file' => 'reorder_report.php', 'label' => 'Reorder Report'],
            ['file' => 'supply_ledger.php', 'label' => 'Supply Ledger'],
        ],
    ],
    [
        'icon' => 'ph-briefcase',
        'label' => 'Property Management',
        'items' => [
            ['file' => 'ics.php', 'label' => 'ICS Headers'],
            ['file' => 'ics_items.php', 'label' => 'ICS Items'],
            ['file' => 'par.php', 'label' => 'PAR Headers'],
            ['file' => 'par_items.php', 'label' => 'PAR Items'],
            ['file' => 'property_cards.php', 'label' => 'Property Cards'],
            ['file' => 'property_card_transactions.php', 'label' => 'Card Movements'],
            ['file' => 'property_transfers.php', 'label' => 'Transfers'],
        ],
    ],
    [
        'icon' => 'ph-clipboard',
        'label' => 'Reports &amp; Compliance',
        'items' => [
            ['file' => 'physical_inventory_reports.php', 'label' => 'RPCI (Physical Count)', 'active_on' => ['physical_inventory_items.php']],
            ['file' => 'physical_ppe_reports.php', 'label' => 'RPCPPE (PPE Report)', 'active_on' => ['physical_ppe_items.php']],
            ['file' => 'unserviceable_reports.php', 'label' => 'IIRUP (Unserviceable)', 'active_on' => ['unserviceable_items.php']],
        ],
    ],
];
?>
<!-- ============================ Sidebar Start ============================ -->

<aside class="sidebar">
    <!-- sidebar close btn -->
     <button type="button" class="sidebar-close-btn text-gray-500 hover-text-white hover-bg-main-600 text-md w-24 h-24 border border-gray-100 hover-border-main-600 d-xl-none d-flex flex-center rounded-circle position-absolute"><i class="ph ph-x"></i></button>
    <!-- sidebar close btn -->
    
    <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="sidebar__logo text-center p-5 pt-5 position-sticky inset-block-start-0 bg-white w-100 z-1">
        <img src="<?=IMG_PATH?>/logo/cotsu.png" alt="Logo" class="sidebar__brand-logo">
    </a>

    <div class="sidebar-menu-wrapper overflow-y-auto scroll-sm">
        <div class="p-5 pt-5">
            <ul class="sidebar-menu">
                <li class="sidebar-menu__item<?= $isActiveFile('dashboard.php', ['index.php']) ? ' activePage' : '' ?>">
                    <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="sidebar-menu__link">
                        <span class="icon"><i class="ph  ph-squares-four"></i></span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>
                <?php foreach ($menuGroups as $group): ?>
                    <?php
                        $visibleItems = [];
                        foreach ($group['items'] as $item) {
                            if (!$canAccess($item['file'])) {
                                continue;
                            }
                            $activeOn = $item['active_on'] ?? [];
                            $item['is_active'] = $isActiveFile($item['file'], $activeOn);
                            $visibleItems[] = $item;
                        }

                        $groupIsActive = false;
                        foreach ($visibleItems as $item) {
                            if (!empty($item['is_active'])) {
                                $groupIsActive = true;
                                break;
                            }
                        }
                    ?>
                    <?php if (!empty($visibleItems)): ?>
                        <li class="sidebar-menu__item has-dropdown<?= $groupIsActive ? ' activePage' : '' ?>">
                            <a href="javascript:void(0)" class="sidebar-menu__link">
                                <span class="icon"><i class="ph <?= $group['icon'] ?>"></i></span>
                                <span class="text"><?= $group['label'] ?></span>
                            </a>
                            <ul class="sidebar-submenu">
                                <?php foreach ($visibleItems as $item): ?>
                                    <li class="sidebar-submenu__item<?= !empty($item['is_active']) ? ' activePage' : '' ?>">
                                        <a href="<?= $adminBase . $item['file'] ?>" class="sidebar-submenu__link"><?= $item['label'] ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>

                <li class="sidebar-menu__item">
                    <span class="text-gray-300 text-sm px-20 pt-20 fw-semibold border-top border-gray-100 d-block text-uppercase">System</span>
                </li>
                <?php if ($canAccess('system_settings.php')): ?>
                    <li class="sidebar-menu__item<?= $isActiveFile('system_settings.php') ? ' activePage' : '' ?>">
                        <a href="<?= $adminBase ?>system_settings.php" class="sidebar-menu__link">
                            <span class="icon"><i class="ph ph-sliders"></i></span>
                            <span class="text">System Settings</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($canAccess('audit_logs.php')): ?>
                    <li class="sidebar-menu__item<?= $isActiveFile('audit_logs.php') ? ' activePage' : '' ?>">
                        <a href="<?= $adminBase ?>audit_logs.php" class="sidebar-menu__link">
                            <span class="icon"><i class="ph ph-activity"></i></span>
                            <span class="text">Audit Logs</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="sidebar-menu__item<?= $isActiveFile('profile.php') ? ' activePage' : '' ?>">
                    <a href="<?= $profileUrl ?>" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-user-circle"></i></span>
                        <span class="text">My Profile</span>
                    </a>
                </li>
                <li class="sidebar-menu__item<?= $isActiveFile('logout.php') ? ' activePage' : '' ?>">
                    <a href="<?= $logoutUrl ?>" class="sidebar-menu__link">
                        <span class="icon"><i class="ph ph-sign-out"></i></span>
                        <span class="text">Sign Out</span>
                    </a>
                </li>
                
            </ul>
        </div>

    </div>

</aside>    
<!-- ============================ Sidebar End  ============================ -->
