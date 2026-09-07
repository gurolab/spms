<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/SystemSettings.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../core/BaseModel.php';

$pageTitle = 'Inventory Officer Dashboard';
$organizationName = SystemSettings::getSettings()['organization_name'] ?? 'SMS';

$inventorySummary = [];
$reorderList = [];
$watchlistRows = [];

$trackedItems = 0;
$totalOnHand = 0;
$totalInventoryValue = 0.0;
$reorderAlertCount = 0;
$criticalCount = 0;
$healthyCount = 0;
$suggestedReorderQty = 0;
$suggestedReorderValue = 0.0;
$noThresholdCount = 0;
$dormantCount = 0;
$dormantValue = 0.0;

$movementWindowInQty = 0;
$movementWindowOutQty = 0;
$movementWindowInValue = 0.0;
$movementWindowOutValue = 0.0;

$monthLabels = [];
$monthlyQtyIn = [];
$monthlyQtyOut = [];
$statusLabels = ['Healthy', 'Reorder', 'Critical'];
$statusSeries = [0, 0, 0];
$categoryLabels = [];
$categorySeries = [];

$dormantThresholdTs = strtotime('-90 days');
$movementWindowStartTs = strtotime('-30 days');

try {
    $inventorySummary = StockInventory::getAllSummary();
    $reorderList = StockInventory::getReorderList();

    $trackedItems = count($inventorySummary);
    $reorderAlertCount = count($reorderList);

    foreach ($inventorySummary as $row) {
        $current = (int)($row['current_balance'] ?? 0);
        $safetyStock = (int)($row['safety_stock'] ?? 0);
        $reorderLevel = (int)($row['reorder_level'] ?? 0);
        $unitCost = (float)($row['unit_cost'] ?? 0);

        $totalOnHand += $current;
        $totalInventoryValue += ($current * $unitCost);

        if ($reorderLevel <= 0 && $safetyStock <= 0) {
            $noThresholdCount++;
        }

        if ($safetyStock > 0 && $current <= $safetyStock) {
            $criticalCount++;
            $statusSeries[2]++;
        } elseif ($reorderLevel > 0 && $current <= $reorderLevel) {
            $statusSeries[1]++;
        } else {
            $healthyCount++;
            $statusSeries[0]++;
        }

        if ($current > 0) {
            $lastIssuedRaw = trim((string)($row['last_issued_at'] ?? ''));
            $lastIssuedTs = $lastIssuedRaw !== '' ? strtotime($lastIssuedRaw) : false;
            if ($lastIssuedTs === false || $lastIssuedTs < $dormantThresholdTs) {
                $dormantCount++;
                $dormantValue += ($current * $unitCost);
            }
        }
    }

    $reorderCategoryTotals = [];
    foreach ($reorderList as $row) {
        $current = (int)($row['current_balance'] ?? 0);
        $safetyStock = (int)($row['safety_stock'] ?? 0);
        $reorderLevel = (int)($row['reorder_level'] ?? 0);
        $reorderQty = (int)($row['reorder_quantity'] ?? 0);
        $unitCost = (float)($row['unit_cost'] ?? 0);

        $targetLevel = max($reorderLevel, $safetyStock);
        $requiredQty = max($reorderQty, max(0, $targetLevel - $current));
        $estimatedValue = $requiredQty * $unitCost;

        $suggestedReorderQty += $requiredQty;
        $suggestedReorderValue += $estimatedValue;

        $category = trim((string)($row['category'] ?? 'Uncategorized'));
        if ($category === '') {
            $category = 'Uncategorized';
        }
        $reorderCategoryTotals[$category] = ($reorderCategoryTotals[$category] ?? 0) + $requiredQty;

        $lastIssuedRaw = trim((string)($row['last_issued_at'] ?? ''));
        $lastIssuedTs = $lastIssuedRaw !== '' ? strtotime($lastIssuedRaw) : false;
        $daysSinceIssue = $lastIssuedTs !== false
            ? max(0, (int)floor((time() - $lastIssuedTs) / 86400))
            : null;

        $severity = ($safetyStock > 0 && $current <= $safetyStock) ? 'Critical' : 'Reorder';

        $watchlistRows[] = [
            'code' => (string)($row['code'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'current_balance' => $current,
            'required_qty' => $requiredQty,
            'estimated_value' => $estimatedValue,
            'days_since_issue' => $daysSinceIssue,
            'severity' => $severity,
        ];
    }

    usort($watchlistRows, static function (array $left, array $right): int {
        $rank = ['Critical' => 0, 'Reorder' => 1];
        $leftRank = $rank[$left['severity']] ?? 9;
        $rightRank = $rank[$right['severity']] ?? 9;

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        if ($left['required_qty'] !== $right['required_qty']) {
            return $right['required_qty'] <=> $left['required_qty'];
        }

        if ($left['estimated_value'] !== $right['estimated_value']) {
            return $right['estimated_value'] <=> $left['estimated_value'];
        }

        return strcmp((string)$left['code'], (string)$right['code']);
    });

    arsort($reorderCategoryTotals);
    $reorderCategoryTotals = array_slice($reorderCategoryTotals, 0, 6, true);
    $categoryLabels = array_keys($reorderCategoryTotals);
    $categorySeries = array_values($reorderCategoryTotals);

    $monthsBack = 5;
    $monthStart = new DateTime('first day of this month');
    $monthBucketsIn = [];
    $monthBucketsOut = [];

    for ($i = $monthsBack; $i >= 0; $i--) {
        $month = (clone $monthStart)->modify("-{$i} months");
        $key = $month->format('Y-m');
        $monthLabels[] = $month->format('M Y');
        $monthBucketsIn[$key] = 0;
        $monthBucketsOut[$key] = 0;
    }

    $ledgerEntries = (new BaseModel('supply_ledger_entries'))->getAll();
    foreach ($ledgerEntries as $entry) {
        $entryDate = (string)($entry['entry_date'] ?? '');
        $timestamp = strtotime($entryDate);
        if ($timestamp === false) {
            continue;
        }

        $qtyIn = (int)($entry['qty_in'] ?? 0);
        $qtyOut = (int)($entry['qty_out'] ?? 0);
        $unitCost = (float)($entry['unit_cost'] ?? 0);

        $monthKey = date('Y-m', $timestamp);
        if (isset($monthBucketsIn[$monthKey])) {
            $monthBucketsIn[$monthKey] += $qtyIn;
            $monthBucketsOut[$monthKey] += $qtyOut;
        }

        if ($timestamp >= $movementWindowStartTs) {
            $movementWindowInQty += $qtyIn;
            $movementWindowOutQty += $qtyOut;
            $movementWindowInValue += ($qtyIn * $unitCost);
            $movementWindowOutValue += ($qtyOut * $unitCost);
        }
    }

    $monthlyQtyIn = array_values($monthBucketsIn);
    $monthlyQtyOut = array_values($monthBucketsOut);
} catch (Throwable $e) {
    error_log('inventoryofficer dashboard metrics failed: ' . $e->getMessage());
}

if (empty($categoryLabels)) {
    $categoryLabels = ['No Reorder Exposure'];
    $categorySeries = [0];
}

$netMovementQty = $movementWindowInQty - $movementWindowOutQty;
$netMovementValue = $movementWindowInValue - $movementWindowOutValue;
$watchlistRows = array_slice($watchlistRows, 0, 6);

require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/preload.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div>
                <h4 class="mb-4 text-gray-900">Welcome, <?= htmlspecialchars((string)(getUser('fullname') ?? 'Inventory Officer'), ENT_QUOTES, 'UTF-8') ?>!</h4>
                <p class="text-gray-500 mb-0 text-14">
                    <?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?> inventory monitoring, reconciliation, and decision support workspace
                </p>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Tracked Items</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($trackedItems) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Total On Hand</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($totalOnHand) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Inventory Value</p>
                        <h3 class="mb-0 text-gray-900">PHP <?= number_format($totalInventoryValue, 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Reorder Alerts</p>
                        <h3 class="mb-0 text-danger-700"><?= number_format($reorderAlertCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Critical Items</p>
                        <h3 class="mb-0 text-danger-700"><?= number_format($criticalCount) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Suggested Reorder Qty</p>
                        <h3 class="mb-0 text-warning-700"><?= number_format($suggestedReorderQty) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Suggested Reorder Value</p>
                        <h3 class="mb-0 text-warning-700">PHP <?= number_format($suggestedReorderValue, 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Dormant Stock (&gt;90d)</p>
                        <h3 class="mb-4 text-gray-900"><?= number_format($dormantCount) ?></h3>
                        <p class="mb-0 text-12 text-gray-500">PHP <?= number_format($dormantValue, 2) ?> value at risk</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <h5 class="mb-0 text-gray-900">6-Month Movement</h5>
                            <span class="badge <?= $netMovementQty >= 0 ? 'bg-success' : 'bg-danger' ?> text-white">
                                30d Net <?= $netMovementQty >= 0 ? '+' : '' ?><?= number_format($netMovementQty) ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-12">
                            30d In: <?= number_format($movementWindowInQty) ?> | Out: <?= number_format($movementWindowOutQty) ?>
                            <span class="ms-8">(PHP <?= number_format($netMovementValue, 2) ?> net)</span>
                        </p>
                        <div id="inventoryTrendChart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Stock Health Mix</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-12"><?= number_format($noThresholdCount) ?> items have no reorder/safety thresholds</p>
                        <div id="statusMixChart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Top Reorder Exposure</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-12">Top categories by suggested reorder quantity</p>
                        <div id="categoryBalanceChart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Quick Access</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-12">
                            <div class="col-md-6">
                                <a href="stock_cards.php" class="btn btn-primary w-100">Stock Cards</a>
                            </div>
                            <div class="col-md-6">
                                <a href="slc.php" class="btn btn-primary w-100">Supply Ledger Card (SLC)</a>
                            </div>
                            <div class="col-md-6">
                                <a href="inventory_reconciliation.php" class="btn btn-primary w-100">Inventory Reconciliation</a>
                            </div>
                            <div class="col-md-6">
                                <a href="audit.php" class="btn btn-secondary w-100">Audit Logs</a>
                            </div>
                            <div class="col-md-6">
                                <a href="../profile.php" class="btn btn-secondary w-100">My Profile</a>
                            </div>
                            <div class="col-md-6">
                                <a href="../logout.php" class="btn btn-outline-danger w-100">Sign Out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <div>
                                <h5 class="mb-0 text-gray-900">Priority Reorder Watchlist</h5>
                                <p class="mb-0 text-12 text-gray-500">
                                    Need <?= number_format($suggestedReorderQty) ?> units | PHP <?= number_format($suggestedReorderValue, 2) ?> projected
                                </p>
                            </div>
                            <span class="badge bg-danger text-white"><?= $reorderAlertCount ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($watchlistRows)): ?>
                            <p class="text-gray-500 mb-0">No inventory reconciliation alerts right now.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-gray-500 text-11">Item</th>
                                            <th class="text-gray-500 text-11 text-center">Status</th>
                                            <th class="text-gray-500 text-11 text-center">Bal</th>
                                            <th class="text-gray-500 text-11 text-center">Need</th>
                                            <th class="text-gray-500 text-11 text-end">Est. Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($watchlistRows as $alert): ?>
                                            <?php $isCritical = ($alert['severity'] === 'Critical'); ?>
                                            <tr>
                                                <td>
                                                    <p class="mb-2 text-12 text-gray-900 fw-medium"><?= htmlspecialchars($alert['code'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="mb-2 text-11 text-gray-500"><?= htmlspecialchars($alert['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php if ($alert['days_since_issue'] === null): ?>
                                                        <p class="mb-0 text-11 text-danger-600">No issue activity logged</p>
                                                    <?php else: ?>
                                                        <p class="mb-0 text-11 text-gray-500">Last issue <?= number_format((int)$alert['days_since_issue']) ?>d ago</p>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $isCritical ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                                        <?= htmlspecialchars($alert['severity'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td class="text-center text-12"><?= number_format((int)$alert['current_balance']) ?></td>
                                                <td class="text-center text-12 fw-semibold"><?= number_format((int)$alert['required_qty']) ?></td>
                                                <td class="text-end text-12">PHP <?= number_format((float)$alert['estimated_value'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="inventory_reconciliation.php" class="btn btn-sm btn-outline-primary mt-12">View Full List</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16 px-24 py-16">
            <p class="text-gray-300 text-13 fw-normal mb-0">&copy; <?= date('Y'); ?> <?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>
<script>
(function () {
    if (typeof ApexCharts === 'undefined') {
        return;
    }

    const monthLabels = <?= json_encode($monthLabels) ?>;
    const monthlyQtyIn = <?= json_encode($monthlyQtyIn) ?>;
    const monthlyQtyOut = <?= json_encode($monthlyQtyOut) ?>;
    const statusLabels = <?= json_encode($statusLabels) ?>;
    const statusSeries = <?= json_encode($statusSeries) ?>;
    const categoryLabels = <?= json_encode($categoryLabels) ?>;
    const categorySeries = <?= json_encode($categorySeries) ?>;

    const chartDefaults = {
        chart: {
            toolbar: { show: false },
            foreColor: '#475569'
        },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4
        }
    };

    new ApexCharts(document.querySelector('#inventoryTrendChart'), {
        ...chartDefaults,
        chart: { ...chartDefaults.chart, type: 'line', height: 280 },
        series: [
            { name: 'Qty In', data: monthlyQtyIn },
            { name: 'Qty Out', data: monthlyQtyOut }
        ],
        xaxis: { categories: monthLabels },
        yaxis: { min: 0, forceNiceScale: true },
        dataLabels: { enabled: false },
        stroke: { width: 3, curve: 'smooth' },
        colors: ['#0d6efd', '#ef4444']
    }).render();

    new ApexCharts(document.querySelector('#statusMixChart'), {
        ...chartDefaults,
        chart: { ...chartDefaults.chart, type: 'donut', height: 280 },
        labels: statusLabels,
        series: statusSeries,
        dataLabels: { enabled: true },
        legend: { position: 'bottom' },
        colors: ['#16a34a', '#f59e0b', '#dc2626']
    }).render();

    new ApexCharts(document.querySelector('#categoryBalanceChart'), {
        ...chartDefaults,
        chart: { ...chartDefaults.chart, type: 'bar', height: 280 },
        series: [{ name: 'Suggested Reorder Qty', data: categorySeries }],
        xaxis: { categories: categoryLabels },
        dataLabels: { enabled: false },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        colors: ['#0ea5e9']
    }).render();
})();
</script>
