<?php
require_once __DIR__ . '/../core/auth_guard.php';
guardRole([2]); // Supply Officer

require_once __DIR__ . '/../repo/StockInventory.model.php';

$pageTitle = 'Inventory Reports';
$inventorySummary = [];
$reorderList = [];
$totalOnHand = 0;
$itemsTracked = 0;

try {
    $inventorySummary = StockInventory::getAllSummary();
    $reorderList = StockInventory::getReorderList();
    $itemsTracked = count($inventorySummary);

    foreach ($inventorySummary as $row) {
        $totalOnHand += (int)($row['current_balance'] ?? 0);
    }
} catch (Throwable $e) {
    error_log('supplyofficer inventory reports failed: ' . $e->getMessage());
}

$criticalRows = array_slice($reorderList, 0, 10);

require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/preload.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="index.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></span></li>
                </ul>
            </div>
            <div class="flex-align gap-8 flex-wrap">
                <a href="receive.php" class="btn btn-primary">Receive Supplies</a>
                <a href="issue_supplies.php" class="btn btn-outline-primary">Issue Supplies</a>
                <a href="ris.php" class="btn btn-outline-primary">Manage RIS</a>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Tracked Items</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($itemsTracked) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Current On Hand</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($totalOnHand) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Reorder Alerts</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format(count($reorderList)) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom border-gray-100">
                <div class="flex-between flex-wrap gap-8">
                    <h5 class="mb-0 text-gray-900">Critical Stock Watchlist</h5>
                    <span class="text-13 text-gray-500">Top <?= count($criticalRows) ?> results</span>
                </div>
            </div>
            <div class="card-body p-5 overflow-x-auto">
                <table id="criticalStockTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Code</th>
                            <th class="h6 text-gray-300">Description</th>
                            <th class="h6 text-gray-300">Category</th>
                            <th class="h6 text-gray-300">Current Balance</th>
                            <th class="h6 text-gray-300">Reorder Level</th>
                            <th class="h6 text-gray-300">Safety Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($criticalRows)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-gray-500">No critical inventory alerts.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($criticalRows as $row): ?>
                            <tr>
                                <td class="text-gray-900 fw-medium"><?= htmlspecialchars($row['code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($row['current_balance'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($row['reorder_level'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($row['safety_stock'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16 px-24 py-16">
            <p class="text-gray-300 text-13 fw-normal mb-0">&copy; <?= date('Y'); ?> SMS. All rights reserved.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function () {
    const tableHasData = $('#criticalStockTable tbody tr').find('td[colspan]').length === 0;
    if (tableHasData) {
        new DataTable('#criticalStockTable', {
            searching: true,
            lengthChange: false,
            info: true,
            paging: true,
            order: [[3, 'asc']]
        });
    }
});
</script>
