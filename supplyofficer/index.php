<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([2]); // Supply Officer

require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/SystemSettings.model.php';
require_once __DIR__ . '/../repo/Supply_Receive.model.php';
require_once __DIR__ . '/../repo/Supply_Issuance.model.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/User.model.php';

$pageTitle = 'Supply Officer Dashboard';
$organizationName = SystemSettings::getSettings()['organization_name'] ?? 'SMS';

$receiptsCount = 0;
$receivedQtyTotal = 0;
$receivedQtyLast30Days = 0;
$issuedQtyLast30Days = 0;
$risPendingCount = 0;
$pendingRisAgedCount = 0;
$oldestPendingRisAgeDays = 0;
$issuanceOpenCount = 0;
$reorderAlertCount = 0;
$criticalReorderCount = 0;
$reorderLevelCount = 0;
$openRequestQty = 0;
$requestedQtyLast30Days = 0;
$issuedQtyAgainstRecentRequests = 0;
$fulfillmentRateLast30Days = 0.0;
$criticalReorders = [];
$pendingRisQueue = [];
$topOpenDemand = [];
$monthlyLabels = [];
$monthlyReceived = [];
$monthlyIssued = [];
$monthlyNetFlow = [];
$issuanceStatusCounts = [
    'Draft' => 0,
    'Partial Issued' => 0,
    'Issued' => 0,
    'Completed' => 0,
];
$risStatusCounts = [
    'Requested' => 0,
    'Approved' => 0,
    'Partial Issued' => 0,
    'Issued' => 0,
    'Completed' => 0,
];
$pendingStatuses = ['Requested', 'Approved', 'Partial Issued'];
$userNameById = [];
$departmentNameById = [];
$itemById = [];

try {
    $receipts = (new Supply_Receive_Header())->getAll();
    $receiptItems = (new Supply_Receive_Items())->getAll();
    $risList = (new RIS())->getAll();
    $issuanceList = (new Supply_Issuance())->getAll();
    $issuanceItems = (new Supply_Issuance_Items())->getAll();
    $risItems = (new RIS_Items())->getAll();
    $items = (new Item())->getAll();
    $users = (new User())->getAll();
    $departments = (new BaseModel('departments'))->getAll();
    $criticalReorders = StockInventory::getReorderList();

    $receiptsCount = count($receipts);

    foreach ($users as $user) {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $fullName = trim((string)($user['fullname'] ?? ''));
        if ($fullName === '') {
            $fullName = trim(
                (string)($user['firstname'] ?? '') . ' ' . (string)($user['lastname'] ?? '')
            );
        }
        if ($fullName === '') {
            $fullName = 'User #' . $userId;
        }

        $userNameById[$userId] = $fullName;
    }

    foreach ($departments as $department) {
        $departmentId = (int)($department['id'] ?? 0);
        if ($departmentId <= 0) {
            continue;
        }

        $code = trim((string)($department['code'] ?? ''));
        $name = trim((string)($department['name'] ?? ''));
        $departmentLabel = $code !== '' && $name !== ''
            ? $code . ' - ' . $name
            : ($code !== '' ? $code : ($name !== '' ? $name : ('Department #' . $departmentId)));

        $departmentNameById[$departmentId] = $departmentLabel;
    }

    foreach ($items as $item) {
        $itemId = (int)($item['id'] ?? 0);
        if ($itemId <= 0) {
            continue;
        }
        $itemById[$itemId] = $item;
    }

    $monthsBack = 5;
    $receiptMonthTotals = [];
    $issuedMonthTotals = [];
    $monthStart = new DateTime('first day of this month');
    for ($i = $monthsBack; $i >= 0; $i--) {
        $month = (clone $monthStart)->modify("-{$i} months");
        $key = $month->format('Y-m');
        $monthlyLabels[] = $month->format('M Y');
        $receiptMonthTotals[$key] = 0;
        $issuedMonthTotals[$key] = 0;
    }

    $last30DaysStart = (new DateTime('today'))->modify('-29 days')->setTime(0, 0, 0);
    $last30DaysStartTs = $last30DaysStart->getTimestamp();
    $todayTs = (new DateTime('today'))->getTimestamp();

    $receiptDateById = [];
    foreach ($receipts as $receipt) {
        $receiptDateById[(int)($receipt['id'] ?? 0)] = (string)($receipt['receipt_date'] ?? '');
    }

    foreach ($receiptItems as $entry) {
        $receiptHeaderId = (int)($entry['receipt_header_id'] ?? 0);
        $qtyReceived = (int)($entry['qty_received'] ?? 0);
        if ($receiptHeaderId <= 0 || $qtyReceived <= 0 || empty($receiptDateById[$receiptHeaderId])) {
            continue;
        }

        $timestamp = strtotime($receiptDateById[$receiptHeaderId]);
        if ($timestamp === false) {
            continue;
        }

        $receivedQtyTotal += $qtyReceived;

        if ($timestamp >= $last30DaysStartTs) {
            $receivedQtyLast30Days += $qtyReceived;
        }

        $monthKey = date('Y-m', $timestamp);
        if (isset($receiptMonthTotals[$monthKey])) {
            $receiptMonthTotals[$monthKey] += $qtyReceived;
        }
    }

    $issuanceDateById = [];
    foreach ($issuanceList as $issuance) {
        if (in_array($issuance['status'] ?? '', ['Draft', 'Partial Issued', 'Issued'], true)) {
            $issuanceOpenCount++;
        }

        $issuanceStatus = trim((string)($issuance['status'] ?? ''));
        if ($issuanceStatus === '') {
            $issuanceStatus = 'Unknown';
        }
        if (!isset($issuanceStatusCounts[$issuanceStatus])) {
            $issuanceStatusCounts[$issuanceStatus] = 0;
        }
        $issuanceStatusCounts[$issuanceStatus]++;

        $issuanceDateById[(int)($issuance['id'] ?? 0)] = (string)($issuance['issuance_date'] ?? '');
    }

    foreach ($risList as $ris) {
        $risStatus = trim((string)($ris['status'] ?? ''));
        if ($risStatus === '') {
            $risStatus = 'Unknown';
        }
        if (!isset($risStatusCounts[$risStatus])) {
            $risStatusCounts[$risStatus] = 0;
        }
        $risStatusCounts[$risStatus]++;
    }

    foreach ($issuanceItems as $entry) {
        $issuanceId = (int)($entry['issuance_id'] ?? 0);
        $qtyIssued = (int)($entry['qty_issued'] ?? 0);
        if ($issuanceId <= 0 || $qtyIssued <= 0 || empty($issuanceDateById[$issuanceId])) {
            continue;
        }

        $timestamp = strtotime($issuanceDateById[$issuanceId]);
        if ($timestamp === false) {
            continue;
        }

        if ($timestamp >= $last30DaysStartTs) {
            $issuedQtyLast30Days += $qtyIssued;
        }

        $monthKey = date('Y-m', $timestamp);
        if (isset($issuedMonthTotals[$monthKey])) {
            $issuedMonthTotals[$monthKey] += $qtyIssued;
        }
    }

    $recentRisIds = [];
    foreach ($risList as $ris) {
        $risId = (int)($ris['id'] ?? 0);
        if ($risId <= 0) {
            continue;
        }

        $requisitionDateRaw = (string)($ris['requisition_date'] ?? '');
        $timestamp = strtotime($requisitionDateRaw);
        if ($timestamp === false) {
            continue;
        }
        if ($timestamp >= $last30DaysStartTs) {
            $recentRisIds[$risId] = true;
        }
    }

    $outstandingByRis = [];
    $outstandingByItem = [];
    foreach ($risItems as $line) {
        $risId = (int)($line['ris_id'] ?? 0);
        $itemId = (int)($line['item_id'] ?? 0);
        $qtyRequested = max(0, (int)($line['qty_requested'] ?? 0));
        $qtyIssued = max(0, (int)($line['qty_issued'] ?? 0));
        if ($qtyIssued > $qtyRequested) {
            $qtyIssued = $qtyRequested;
        }

        if (isset($recentRisIds[$risId])) {
            $requestedQtyLast30Days += $qtyRequested;
            $issuedQtyAgainstRecentRequests += $qtyIssued;
        }

        $outstandingQty = max(0, $qtyRequested - $qtyIssued);
        if ($outstandingQty <= 0) {
            continue;
        }

        $openRequestQty += $outstandingQty;
        $outstandingByRis[$risId] = ($outstandingByRis[$risId] ?? 0) + $outstandingQty;
        $outstandingByItem[$itemId] = ($outstandingByItem[$itemId] ?? 0) + $outstandingQty;
    }

    foreach ($risList as $ris) {
        $status = (string)($ris['status'] ?? '');
        if (!in_array($status, $pendingStatuses, true)) {
            continue;
        }

        $risPendingCount++;

        $risId = (int)($ris['id'] ?? 0);
        $requisitionDate = (string)($ris['requisition_date'] ?? '');
        $timestamp = strtotime($requisitionDate);
        $ageDays = 0;
        if ($timestamp !== false) {
            $ageDays = (int)floor(($todayTs - $timestamp) / 86400);
            if ($ageDays < 0) {
                $ageDays = 0;
            }
        }

        if ($ageDays >= 7) {
            $pendingRisAgedCount++;
        }
        $oldestPendingRisAgeDays = max($oldestPendingRisAgeDays, $ageDays);

        $divisionValue = $ris['division'] ?? '';
        $divisionId = (int)$divisionValue;
        $departmentLabel = 'Unassigned';
        if ($divisionId > 0 && isset($departmentNameById[$divisionId])) {
            $departmentLabel = $departmentNameById[$divisionId];
        } elseif (trim((string)$divisionValue) !== '') {
            $departmentLabel = (string)$divisionValue;
        }

        $requestedBy = (int)($ris['requested_by'] ?? 0);
        $requesterLabel = $userNameById[$requestedBy] ?? 'Unknown';

        $pendingRisQueue[] = [
            'id' => $risId,
            'ris_no' => (string)($ris['ris_no'] ?? ('RIS #' . $risId)),
            'status' => $status,
            'requisition_date' => $requisitionDate,
            'age_days' => $ageDays,
            'requester' => $requesterLabel,
            'department' => $departmentLabel,
            'outstanding_qty' => (int)($outstandingByRis[$risId] ?? 0),
        ];
    }

    usort($pendingRisQueue, static function (array $left, array $right): int {
        if ($left['age_days'] === $right['age_days']) {
            return strcmp((string)$left['requisition_date'], (string)$right['requisition_date']);
        }
        return $right['age_days'] <=> $left['age_days'];
    });
    $pendingRisQueue = array_slice($pendingRisQueue, 0, 8);

    foreach ($outstandingByItem as $itemId => $qty) {
        if ($qty <= 0) {
            continue;
        }

        $item = $itemById[(int)$itemId] ?? [];
        $topOpenDemand[] = [
            'code' => (string)($item['code'] ?? ('ITEM-' . $itemId)),
            'description' => (string)($item['description'] ?? 'Unknown item'),
            'category' => (string)($item['category'] ?? ''),
            'outstanding_qty' => (int)$qty,
            'stock_onhand' => (int)($item['stock_onhand'] ?? 0),
        ];
    }

    usort($topOpenDemand, static function (array $left, array $right): int {
        if ($left['outstanding_qty'] === $right['outstanding_qty']) {
            return strcmp((string)$left['code'], (string)$right['code']);
        }
        return $right['outstanding_qty'] <=> $left['outstanding_qty'];
    });
    $topOpenDemand = array_slice($topOpenDemand, 0, 8);

    $monthlyReceived = array_values($receiptMonthTotals);
    $monthlyIssued = array_values($issuedMonthTotals);
    $monthlyNetFlow = array_map(
        static function (int $received, int $issued): int {
            return $received - $issued;
        },
        $monthlyReceived,
        $monthlyIssued
    );

    $reorderAlertCount = count($criticalReorders);
    foreach ($criticalReorders as $row) {
        $currentBalance = (int)($row['current_balance'] ?? 0);
        $safetyStock = (int)($row['safety_stock'] ?? 0);
        $reorderLevel = (int)($row['reorder_level'] ?? 0);
        if ($safetyStock > 0 && $currentBalance <= $safetyStock) {
            $criticalReorderCount++;
        } elseif ($reorderLevel > 0 && $currentBalance <= $reorderLevel) {
            $reorderLevelCount++;
        }
    }
    $criticalReorders = array_slice($criticalReorders, 0, 6);

    if ($requestedQtyLast30Days > 0) {
        $fulfillmentRateLast30Days = round(
            ($issuedQtyAgainstRecentRequests / $requestedQtyLast30Days) * 100,
            1
        );
    }
} catch (Throwable $e) {
    error_log('supplyofficer dashboard metrics failed: ' . $e->getMessage());
}

$formatShortDate = static function (?string $date): string {
    $date = trim((string)$date);
    if ($date === '') {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '-';
    }

    return date('M d, Y', $timestamp);
};

require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/preload.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div>
                <h4 class="mb-4 text-gray-900">Welcome, <?= htmlspecialchars(getUser('fullname') ?? 'Supply Officer', ENT_QUOTES, 'UTF-8') ?>!</h4>
                <p class="text-gray-500 mb-0 text-14">
                    <?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?> supply operations workspace
                </p>
            </div>
            <div>
                <span class="badge bg-primary text-white text-13">Decision Snapshot: Open Qty <?= number_format($openRequestQty) ?></span>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Receipts Logged</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($receiptsCount) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Pending RIS</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($risPendingCount) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Open Issuances</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($issuanceOpenCount) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Reorder Alerts</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($reorderAlertCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Total Qty Received</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($receivedQtyTotal) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Open Request Qty</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($openRequestQty) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Pending RIS &gt; 7 Days</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($pendingRisAgedCount) ?></h3>
                        <p class="text-12 text-gray-500 mb-0 mt-6">Oldest pending: <?= number_format($oldestPendingRisAgeDays) ?> day(s)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">30-Day Fulfillment</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($fulfillmentRateLast30Days, 1) ?>%</h3>
                        <p class="text-12 text-gray-500 mb-0 mt-6">
                            Req <?= number_format($requestedQtyLast30Days) ?> | Issued <?= number_format($issuedQtyAgainstRecentRequests) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">6-Month Supply Flow</h5>
                    </div>
                    <div class="card-body">
                        <div id="supplyFlowChart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Issuance Status Mix</h5>
                    </div>
                    <div class="card-body">
                        <div id="issuanceStatusChart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">RIS Status Pipeline</h5>
                    </div>
                    <div class="card-body">
                        <div id="risStatusChart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <h5 class="mb-0 text-gray-900">Pending RIS Aging Queue</h5>
                            <span class="text-13 text-gray-500">Oldest pending requests first</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pendingRisQueue)): ?>
                            <div class="p-24">
                                <p class="text-gray-500 mb-0">No pending RIS records needing attention.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="h6 text-gray-300">RIS</th>
                                            <th class="h6 text-gray-300 text-center">Age</th>
                                            <th class="h6 text-gray-300 text-center">Outstanding Qty</th>
                                            <th class="h6 text-gray-300">Requester</th>
                                            <th class="h6 text-gray-300">Department</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingRisQueue as $row): ?>
                                            <tr>
                                                <td class="text-gray-900">
                                                    <p class="mb-2 fw-medium"><?= htmlspecialchars((string)$row['ris_no'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="mb-0 text-12 text-gray-500"><?= htmlspecialchars($formatShortDate((string)$row['requisition_date']), ENT_QUOTES, 'UTF-8') ?></p>
                                                </td>
                                                <td class="text-center text-gray-900">
                                                    <span class="badge <?= ((int)$row['age_days'] >= 7) ? 'bg-danger text-white' : 'bg-warning text-dark' ?>">
                                                        <?= number_format((int)$row['age_days']) ?>d
                                                    </span>
                                                </td>
                                                <td class="text-center text-gray-900"><?= number_format((int)$row['outstanding_qty']) ?></td>
                                                <td class="text-gray-900"><?= htmlspecialchars((string)$row['requester'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900">
                                                    <p class="mb-2"><?= htmlspecialchars((string)$row['department'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="mb-0 text-12 text-gray-500"><?= htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8') ?></p>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <h5 class="mb-0 text-gray-900">Reorder Alerts</h5>
                            <span class="badge bg-danger text-white"><?= $reorderAlertCount ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-12">
                            Critical: <?= number_format($criticalReorderCount) ?> |
                            Reorder Level: <?= number_format($reorderLevelCount) ?>
                        </p>
                        <?php if (empty($criticalReorders)): ?>
                            <p class="text-gray-500 mb-0">No critical stock alerts right now.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($criticalReorders as $alert): ?>
                                    <?php
                                        $currentBalance = (int)($alert['current_balance'] ?? 0);
                                        $safetyStock = (int)($alert['safety_stock'] ?? 0);
                                        $isCritical = $safetyStock > 0 && $currentBalance <= $safetyStock;
                                    ?>
                                    <li class="list-group-item px-0 py-10">
                                        <div class="d-flex justify-content-between align-items-start gap-8">
                                            <div>
                                                <p class="mb-2 text-gray-900 fw-medium"><?= htmlspecialchars($alert['code'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="mb-0 text-12 text-gray-500"><?= htmlspecialchars($alert['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge <?= $isCritical ? 'bg-danger text-white' : 'bg-warning text-dark' ?>">
                                                    <?= $isCritical ? 'Critical' : 'Reorder' ?>
                                                </span>
                                                <p class="mb-0 text-12 text-gray-500 mt-4">On hand: <?= number_format($currentBalance) ?></p>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="inventory_reports.php" class="btn btn-sm btn-outline-primary mt-12">View Full Report</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <h5 class="mb-0 text-gray-900">Top Outstanding Item Demand</h5>
                            <span class="text-13 text-gray-500">Most requested but not yet fully issued</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($topOpenDemand)): ?>
                            <div class="p-24">
                                <p class="text-gray-500 mb-0">No outstanding item demand right now.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="h6 text-gray-300">Item</th>
                                            <th class="h6 text-gray-300">Description</th>
                                            <th class="h6 text-gray-300 text-center">Outstanding Qty</th>
                                            <th class="h6 text-gray-300 text-center">On Hand</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topOpenDemand as $row): ?>
                                            <tr>
                                                <td class="text-gray-900 fw-medium"><?= htmlspecialchars((string)$row['code'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900">
                                                    <p class="mb-2"><?= htmlspecialchars((string)$row['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="mb-0 text-12 text-gray-500"><?= htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8') ?></p>
                                                </td>
                                                <td class="text-center text-gray-900"><?= number_format((int)$row['outstanding_qty']) ?></td>
                                                <td class="text-center text-gray-900"><?= number_format((int)$row['stock_onhand']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Quick Access</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-12">
                            <div class="col-12">
                                <a href="receive.php" class="btn btn-primary w-100">Receive Supplies</a>
                            </div>
                            <div class="col-12">
                                <a href="issue_supplies.php" class="btn btn-primary w-100">Issue Supplies</a>
                            </div>
                            <div class="col-12">
                                <a href="ris.php" class="btn btn-primary w-100">Manage RIS</a>
                            </div>
                            <div class="col-12">
                                <a href="inventory_reports.php" class="btn btn-outline-primary w-100">Inventory Reports</a>
                            </div>
                            <div class="col-12">
                                <a href="audit.php" class="btn btn-secondary w-100">Audit Logs</a>
                            </div>
                            <div class="col-12">
                                <a href="../profile.php" class="btn btn-secondary w-100">My Profile</a>
                            </div>
                        </div>
                        <div class="border-top border-gray-100 pt-12 mt-12">
                            <p class="text-12 text-gray-500 mb-4">Last 30 Days</p>
                            <p class="mb-0 text-gray-900">Received <?= number_format($receivedQtyLast30Days) ?> | Issued <?= number_format($issuedQtyLast30Days) ?></p>
                        </div>
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

        const monthlyLabels = <?= json_encode($monthlyLabels) ?>;
        const monthlyReceived = <?= json_encode($monthlyReceived) ?>;
        const monthlyIssued = <?= json_encode($monthlyIssued) ?>;
        const monthlyNetFlow = <?= json_encode($monthlyNetFlow) ?>;
        const issuanceLabels = <?= json_encode(array_keys($issuanceStatusCounts)) ?>;
        const issuanceSeries = <?= json_encode(array_values($issuanceStatusCounts)) ?>;
        const risLabels = <?= json_encode(array_keys($risStatusCounts)) ?>;
        const risSeries = <?= json_encode(array_values($risStatusCounts)) ?>;

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

        new ApexCharts(document.querySelector('#supplyFlowChart'), {
            ...chartDefaults,
            chart: { ...chartDefaults.chart, type: 'line', height: 280 },
            series: [
                { name: 'Received Qty', type: 'column', data: monthlyReceived },
                { name: 'Issued Qty', type: 'column', data: monthlyIssued },
                { name: 'Net Flow', type: 'line', data: monthlyNetFlow }
            ],
            xaxis: { categories: monthlyLabels },
            yaxis: { forceNiceScale: true },
            dataLabels: { enabled: false },
            plotOptions: {
                bar: {
                    columnWidth: '45%',
                    borderRadius: 4
                }
            },
            stroke: { curve: 'smooth', width: [0, 0, 3] },
            colors: ['#22c55e', '#f97316', '#2563eb'],
            legend: { position: 'top', horizontalAlign: 'left' },
            tooltip: { y: { formatter: (value) => value + ' units' } }
        }).render();

        new ApexCharts(document.querySelector('#issuanceStatusChart'), {
            ...chartDefaults,
            chart: { ...chartDefaults.chart, type: 'donut', height: 280 },
            labels: issuanceLabels,
            series: issuanceSeries,
            dataLabels: { enabled: true },
            legend: { position: 'bottom' },
            colors: ['#f59e0b', '#0ea5e9', '#6366f1', '#16a34a', '#64748b']
        }).render();

        new ApexCharts(document.querySelector('#risStatusChart'), {
            ...chartDefaults,
            chart: { ...chartDefaults.chart, type: 'bar', height: 280 },
            series: [{ name: 'Requests', data: risSeries }],
            xaxis: { categories: risLabels },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            dataLabels: { enabled: false },
            colors: ['#14b8a6']
        }).render();
    })();
</script>
