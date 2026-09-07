<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';

$pageTitle = 'Reorder Report';

$items = StockInventory::getReorderList();

?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="index.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= $pageTitle ?></span></li>
                </ul>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="reorderTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Code</th>
                            <th class="h6 text-gray-300">Description</th>
                            <th class="h6 text-gray-300">Category</th>
                            <th class="h6 text-gray-300">Current Balance</th>
                            <th class="h6 text-gray-300">Safety Stock</th>
                            <th class="h6 text-gray-300">Reorder Level</th>
                            <th class="h6 text-gray-300">Suggested Order</th>
                            <th class="h6 text-gray-300">Last Restocked</th>
                            <th class="h6 text-gray-300">Last Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row): ?>
                            <?php
                                $current = (int)$row['current_balance'];
                                $reorderLevel = (int)$row['reorder_level'];
                                $safetyStock = (int)$row['safety_stock'];
                                $reorderQty = (int)$row['reorder_quantity'];
                                $recommended = max($reorderQty, ($reorderLevel > 0 ? ($reorderLevel - $current) : 0));
                                if ($recommended < $reorderQty) {
                                    $recommended = $reorderQty;
                                }
                            ?>
                            <tr>
                                <td class="text-gray-900 fw-medium"><?= htmlspecialchars($row['code']) ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($row['description']) ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($row['category']) ?></td>
                                <td class="text-gray-900 text-center"><?= $current ?></td>
                                <td class="text-gray-900 text-center"><?= $safetyStock ?></td>
                                <td class="text-gray-900 text-center"><?= $reorderLevel ?></td>
                                <td class="text-gray-900 text-center"><?= $recommended ?></td>
                                <td class="text-gray-900 text-center"><?= $row['last_restocked_at'] ? DisplayDate($row['last_restocked_at']) : '—' ?></td>
                                <td class="text-gray-900 text-center"><?= $row['last_issued_at'] ? DisplayDate($row['last_issued_at']) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16">
            <p class="text-gray-300 text-13 fw-normal">&copy; Copyright COTSU 2025, All Right Reserverd</p>
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function() {
    new DataTable('#reorderTable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        order: [[3, 'asc']]
    });
});
</script>
