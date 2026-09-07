<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';

$pageTitle = 'Inventory Reconciliation';

$summaryItems = StockInventory::getAllSummary();
$reconciliationRows = StockInventory::getReorderList();

$totalTracked = count($summaryItems);
$reconcileCount = count($reconciliationRows);
$healthyCount = max(0, $totalTracked - $reconcileCount);
$totalShortfall = 0;

foreach ($reconciliationRows as $row) {
    $current = (int)($row['current_balance'] ?? 0);
    $reorderLevel = (int)($row['reorder_level'] ?? 0);
    $reorderQty = (int)($row['reorder_quantity'] ?? 0);

    $required = max($reorderQty, max(0, $reorderLevel - $current));
    $totalShortfall += $required;
}

?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="dashboard.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></span></li>
                </ul>
            </div>
            <div class="flex-align gap-8 flex-wrap">
                <a href="stock_cards.php" class="btn btn-outline-primary">Adjust Reorder Levels</a>
                <a href="slc.php" class="btn btn-outline-secondary">Open SLC</a>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Tracked Items</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($totalTracked) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Needs Reconciliation</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($reconcileCount) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Suggested Reorder Qty</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($totalShortfall) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header border-bottom border-gray-100">
                <div class="flex-between flex-wrap gap-8">
                    <div>
                        <h5 class="mb-0 text-gray-900">Items Requiring Action</h5>
                        <p class="mb-0 text-gray-500 text-13">Review low balance items and confirm reorder settings and physical counts.</p>
                    </div>
                    <span class="badge bg-warning text-dark"><?= number_format($reconcileCount) ?> flagged</span>
                </div>
            </div>
            <div class="card-body p-5 overflow-x-auto">
                <table id="reconciliationTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Code</th>
                            <th class="h6 text-gray-300">Description</th>
                            <th class="h6 text-gray-300">Category</th>
                            <th class="h6 text-gray-300">Current Balance</th>
                            <th class="h6 text-gray-300">Safety Stock</th>
                            <th class="h6 text-gray-300">Reorder Level</th>
                            <th class="h6 text-gray-300">Reorder Qty</th>
                            <th class="h6 text-gray-300">Suggested Order</th>
                            <th class="h6 text-gray-300">Last Restocked</th>
                            <th class="h6 text-gray-300">Last Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reconciliationRows)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-gray-500">No reconciliation alerts at the moment.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($reconciliationRows as $row): ?>
                            <?php
                                $current = (int)$row['current_balance'];
                                $reorderLevel = (int)$row['reorder_level'];
                                $reorderQty = (int)$row['reorder_quantity'];
                                $suggestedOrder = max($reorderQty, max(0, $reorderLevel - $current));
                            ?>
                            <tr>
                                <td class="text-gray-900 fw-medium"><?= htmlspecialchars((string)$row['code'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars((string)$row['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900 text-center"><?= $current ?></td>
                                <td class="text-gray-900 text-center"><?= (int)$row['safety_stock'] ?></td>
                                <td class="text-gray-900 text-center"><?= $reorderLevel ?></td>
                                <td class="text-gray-900 text-center"><?= $reorderQty ?></td>
                                <td class="text-gray-900 text-center"><?= $suggestedOrder ?></td>
                                <td class="text-gray-900 text-center"><?= !empty($row['last_restocked_at']) ? DisplayDate((string)$row['last_restocked_at']) : '-' ?></td>
                                <td class="text-gray-900 text-center"><?= !empty($row['last_issued_at']) ? DisplayDate((string)$row['last_issued_at']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16">
            <p class="text-gray-300 text-13 fw-normal">&copy; Copyright COTSU <?= date('Y') ?>, All Rights Reserved</p>
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function () {
    const hasDataRows = $('#reconciliationTable tbody tr').find('td[colspan]').length === 0;
    if (hasDataRows) {
        new DataTable('#reconciliationTable', {
            searching: true,
            lengthChange: false,
            info: true,
            paging: true,
            order: [[3, 'asc']]
        });
    }
});
</script>