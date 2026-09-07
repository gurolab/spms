<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/SystemSettings.model.php';
require_once __DIR__ . '/../repo/ICS.model.php';
require_once __DIR__ . '/../repo/PAR.model.php';
require_once __DIR__ . '/../repo/PropertyCard.model.php';
require_once __DIR__ . '/../repo/PropertyTransfer.model.php';

$pageTitle = 'Property Custodian Dashboard';
$organizationName = SystemSettings::getSettings()['organization_name'] ?? 'SMS';

$icsList = [];
$parList = [];
$propertyCards = [];
$transfers = [];
$users = [];

$activeIcsCount = 0;
$activeParCount = 0;
$propertyCardCount = 0;
$pendingTransferCount = 0;
$cardsForAttentionCount = 0;
$unassignedCardsCount = 0;
$staleCardsCount = 0;
$completedTransfersThisMonth = 0;
$pendingTransfersOverThresholdCount = 0;
$oldestPendingTransferDays = 0;
$activeIcsWithoutItemsCount = 0;
$activeParWithoutItemsCount = 0;
$totalAccountabilityValue = 0.0;

$monthlyLabels = [];
$icsMonthlySeries = [];
$parMonthlySeries = [];
$cardStatusLabels = PropertyCard::STATUSES;
$cardStatusSeries = array_fill(0, count($cardStatusLabels), 0);
$transferStatusLabels = PropertyTransfer::STATUSES;
$transferStatusSeries = array_fill(0, count($transferStatusLabels), 0);
$departmentLabels = [];
$departmentSeries = [];
$recentTransfers = [];
$transferStatusBadgeMap = [
    'Pending' => 'bg-warning text-dark',
    'Completed' => 'bg-success text-dark',
    'Cancelled' => 'bg-danger',
];

$monthTotals = [];
$monthCursor = new DateTime('first day of this month');
for ($i = 5; $i >= 0; $i--) {
    $monthObj = (clone $monthCursor)->modify("-{$i} months");
    $monthKey = $monthObj->format('Y-m');
    $monthTotals[$monthKey] = ['ics' => 0, 'par' => 0];
    $monthlyLabels[] = $monthObj->format('M Y');
}

$today = new DateTimeImmutable('today');
$todayTimestamp = $today->getTimestamp();
$currentMonthKey = $today->format('Y-m');
$staleThresholdDays = 90;
$pendingThresholdDays = 30;
$userById = [];
$departmentBuckets = [];

$normalizeDepartment = static function (?array $user): string {
    if (!is_array($user)) {
        return 'Unassigned';
    }

    $departmentCode = getDepartmentCodeFromUser($user);
    return $departmentCode !== '' ? $departmentCode : 'Unassigned';
};

$resolveOfficerLabel = static function (array $userById, int $userId): string {
    if ($userId <= 0) {
        return 'N/A';
    }

    $label = formatOfficerNameWithDeptCode($userById[$userId] ?? null, $userId);
    return $label !== '' ? $label : ('User #' . $userId);
};

try {
    $icsList = (new ICS())->getAll();
    $parList = (new PAR())->getAll();
    $propertyCards = (new PropertyCard())->getAll();
    $transfers = (new PropertyTransfer())->getAll();
    $users = (new BaseModel('user_role_dept'))->getAll();
    $icsItems = (new ICS_Items())->getAll();
    $parItems = (new PAR_Items())->getAll();
    $cardTransactions = (new PropertyCardTransaction())->getAll();

    foreach ($users as $user) {
        $userId = (int)($user['id'] ?? 0);
        if ($userId > 0) {
            $userById[$userId] = $user;
        }
    }

    $icsItemCountByHeader = [];
    foreach ($icsItems as $item) {
        $icsId = (int)($item['ics_id'] ?? 0);
        if ($icsId <= 0) {
            continue;
        }

        $icsItemCountByHeader[$icsId] = ($icsItemCountByHeader[$icsId] ?? 0) + 1;
    }

    $parItemCountByHeader = [];
    foreach ($parItems as $item) {
        $parId = (int)($item['par_id'] ?? 0);
        if ($parId <= 0) {
            continue;
        }

        $parItemCountByHeader[$parId] = ($parItemCountByHeader[$parId] ?? 0) + 1;
    }

    $latestTxnDateByCard = [];
    foreach ($cardTransactions as $txn) {
        $cardId = (int)($txn['property_card_id'] ?? 0);
        $txnDate = trim((string)($txn['transaction_date'] ?? ''));
        if ($cardId <= 0 || $txnDate === '') {
            continue;
        }

        if (!isset($latestTxnDateByCard[$cardId]) || $txnDate > $latestTxnDateByCard[$cardId]) {
            $latestTxnDateByCard[$cardId] = $txnDate;
        }
    }

    foreach ($icsList as $ics) {
        $status = (string)($ics['status'] ?? '');
        if ($status === 'Active') {
            $activeIcsCount++;

            $icsId = (int)($ics['id'] ?? 0);
            if (($icsItemCountByHeader[$icsId] ?? 0) === 0) {
                $activeIcsWithoutItemsCount++;
            }

            $assignedToId = (int)($ics['assigned_to'] ?? 0);
            $deptCode = $normalizeDepartment($userById[$assignedToId] ?? null);
            $departmentBuckets[$deptCode] = ($departmentBuckets[$deptCode] ?? 0) + 1;
        }

        $issuedDate = trim((string)($ics['issued_date'] ?? ''));
        if ($issuedDate !== '') {
            $timestamp = strtotime($issuedDate);
            if ($timestamp !== false) {
                $monthKey = date('Y-m', $timestamp);
                if (isset($monthTotals[$monthKey])) {
                    $monthTotals[$monthKey]['ics']++;
                }
            }
        }
    }

    foreach ($parList as $par) {
        $status = (string)($par['status'] ?? '');
        if ($status === 'Active') {
            $activeParCount++;

            $parId = (int)($par['id'] ?? 0);
            if (($parItemCountByHeader[$parId] ?? 0) === 0) {
                $activeParWithoutItemsCount++;
            }

            $accountableOfficerId = (int)($par['accountable_officer'] ?? 0);
            $deptCode = $normalizeDepartment($userById[$accountableOfficerId] ?? null);
            $departmentBuckets[$deptCode] = ($departmentBuckets[$deptCode] ?? 0) + 1;
        }

        $issueDate = trim((string)($par['issue_date'] ?? ''));
        if ($issueDate !== '') {
            $timestamp = strtotime($issueDate);
            if ($timestamp !== false) {
                $monthKey = date('Y-m', $timestamp);
                if (isset($monthTotals[$monthKey])) {
                    $monthTotals[$monthKey]['par']++;
                }
            }
        }
    }

    $propertyCardCount = count($propertyCards);
    foreach ($propertyCards as $card) {
        $status = (string)($card['current_status'] ?? '');
        $statusIndex = array_search($status, $cardStatusLabels, true);
        if ($statusIndex !== false) {
            $cardStatusSeries[$statusIndex]++;
        }

        $cardId = (int)($card['id'] ?? 0);
        $accountableOfficerId = (int)($card['accountable_officer'] ?? 0);

        if ($status === 'For Repair' || $status === 'Unserviceable') {
            $cardsForAttentionCount++;
        }

        if ($status !== 'Disposed') {
            if ($accountableOfficerId <= 0) {
                $unassignedCardsCount++;
            }

            $totalAccountabilityValue += (float)($card['acquisition_cost'] ?? 0);
            $deptCode = $normalizeDepartment($userById[$accountableOfficerId] ?? null);
            $departmentBuckets[$deptCode] = ($departmentBuckets[$deptCode] ?? 0) + 1;

            $latestMovementDate = trim((string)($latestTxnDateByCard[$cardId] ?? ''));
            if ($latestMovementDate === '') {
                $latestMovementDate = trim((string)($card['acquisition_date'] ?? ''));
            }

            $latestMovementTimestamp = $latestMovementDate !== '' ? strtotime($latestMovementDate) : false;
            if ($latestMovementTimestamp === false) {
                $staleCardsCount++;
            } else {
                $daysSinceMovement = (int)floor(($todayTimestamp - $latestMovementTimestamp) / 86400);
                if ($daysSinceMovement > $staleThresholdDays) {
                    $staleCardsCount++;
                }
            }
        }
    }

    foreach ($transfers as $transfer) {
        $status = (string)($transfer['status'] ?? '');
        $transferDate = trim((string)($transfer['transfer_date'] ?? ''));
        $transferTimestamp = $transferDate !== '' ? strtotime($transferDate) : false;

        if ($status === 'Pending') {
            $pendingTransferCount++;

            if ($transferTimestamp !== false) {
                $ageDays = (int)floor(($todayTimestamp - $transferTimestamp) / 86400);
                if ($ageDays > $oldestPendingTransferDays) {
                    $oldestPendingTransferDays = $ageDays;
                }
                if ($ageDays > $pendingThresholdDays) {
                    $pendingTransfersOverThresholdCount++;
                }
            }
        }

        if ($status === 'Completed' && $transferTimestamp !== false) {
            if (date('Y-m', $transferTimestamp) === $currentMonthKey) {
                $completedTransfersThisMonth++;
            }
        }

        $statusIndex = array_search($status, $transferStatusLabels, true);
        if ($statusIndex !== false) {
            $transferStatusSeries[$statusIndex]++;
        }

        $fromOfficerId = (int)($transfer['from_officer'] ?? 0);
        $toOfficerId = (int)($transfer['to_officer'] ?? 0);

        $recentTransfers[] = [
            'id' => (int)($transfer['id'] ?? 0),
            'transfer_no' => (string)($transfer['transfer_no'] ?? ''),
            'transfer_date' => $transferDate,
            'property_tag' => (string)($transfer['property_tag'] ?? ''),
            'status' => $status,
            'status_class' => $transferStatusBadgeMap[$status] ?? 'bg-secondary',
            'from_label' => $resolveOfficerLabel($userById, $fromOfficerId),
            'to_label' => $resolveOfficerLabel($userById, $toOfficerId),
            'sort_ts' => $transferTimestamp !== false ? (int)$transferTimestamp : 0,
        ];
    }

    if (!empty($departmentBuckets)) {
        arsort($departmentBuckets);
        $departmentBuckets = array_slice($departmentBuckets, 0, 7, true);
        $departmentLabels = array_keys($departmentBuckets);
        $departmentSeries = array_values($departmentBuckets);
    }

    if (!empty($recentTransfers)) {
        usort($recentTransfers, static function (array $left, array $right): int {
            if ($left['sort_ts'] === $right['sort_ts']) {
                return ((int)$right['id']) <=> ((int)$left['id']);
            }

            return ((int)$right['sort_ts']) <=> ((int)$left['sort_ts']);
        });
        $recentTransfers = array_slice($recentTransfers, 0, 6);
    }
} catch (Throwable $e) {
    error_log('propertycustodian dashboard metrics failed: ' . $e->getMessage());
}

foreach ($monthTotals as $totals) {
    $icsMonthlySeries[] = (int)$totals['ics'];
    $parMonthlySeries[] = (int)$totals['par'];
}

if (empty($departmentLabels)) {
    $departmentLabels = ['No Department Data'];
    $departmentSeries = [0];
}

$openDocsWithoutItemsCount = $activeIcsWithoutItemsCount + $activeParWithoutItemsCount;
$watchlistAlertCount = $pendingTransfersOverThresholdCount + $staleCardsCount + $unassignedCardsCount + $openDocsWithoutItemsCount;

require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/preload.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div>
                <h4 class="mb-4 text-gray-900">Welcome, <?= htmlspecialchars(getUser('fullname') ?? 'Property Custodian', ENT_QUOTES, 'UTF-8') ?>!</h4>
                <p class="text-gray-500 mb-0 text-14"><?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?> property accountability workspace</p>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Active ICS</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($activeIcsCount) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Active PAR</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($activeParCount) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Total Property Cards</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($propertyCardCount) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Pending Transfers</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($pendingTransferCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Cards Requiring Attention</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($cardsForAttentionCount) ?></h3>
                        <p class="text-12 text-gray-500 mb-0 mt-8">For Repair + Unserviceable</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Unassigned Cards</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($unassignedCardsCount) ?></h3>
                        <p class="text-12 text-gray-500 mb-0 mt-8">Needs accountable officer</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Stale Card Movements</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($staleCardsCount) ?></h3>
                        <p class="text-12 text-gray-500 mb-0 mt-8">No movement in <?= (int)$staleThresholdDays ?>+ days</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-13 text-gray-500 mb-6">Completed Transfers (Month)</p>
                        <h3 class="mb-0 text-gray-900"><?= number_format($completedTransfersThisMonth) ?></h3>
                        <p class="text-12 text-gray-500 mb-0 mt-8"><?= htmlspecialchars($today->format('F Y'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Monthly ICS vs PAR</h5>
                    </div>
                    <div class="card-body">
                        <div id="icsParTrendChart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Property Card Status Mix</h5>
                    </div>
                    <div class="card-body">
                        <div id="cardStatusChart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Transfer Status Overview</h5>
                    </div>
                    <div class="card-body">
                        <div id="transferStatusChart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Active Accountability by Department</h5>
                    </div>
                    <div class="card-body">
                        <div id="departmentAccountabilityChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-8">
                            <h5 class="mb-0 text-gray-900">Recent Transfers</h5>
                            <a href="property_transfers.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentTransfers)): ?>
                            <div class="p-20">
                                <p class="text-gray-500 mb-0">No transfer records available yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="h6 text-gray-300">Transfer No.</th>
                                            <th class="h6 text-gray-300">Date</th>
                                            <th class="h6 text-gray-300">Property Tag</th>
                                            <th class="h6 text-gray-300">Movement</th>
                                            <th class="h6 text-gray-300">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentTransfers as $transfer): ?>
                                            <tr>
                                                <td class="text-gray-900 fw-semibold"><?= htmlspecialchars($transfer['transfer_no'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900"><?= htmlspecialchars(DisplayDate($transfer['transfer_date']), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900"><?= htmlspecialchars($transfer['property_tag'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900 text-13">
                                                    <span class="d-block"><?= htmlspecialchars($transfer['from_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="d-block text-gray-500">to <?= htmlspecialchars($transfer['to_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                                <td class="text-gray-900"><span class="badge <?= htmlspecialchars($transfer['status_class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($transfer['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Quick Access</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-12">
                            <div class="col-md-6">
                                <a href="ics.php" class="btn btn-primary w-100">Manage ICS</a>
                            </div>
                            <div class="col-md-6">
                                <a href="par.php" class="btn btn-primary w-100">Manage PAR</a>
                            </div>
                            <div class="col-md-6">
                                <a href="property_cards.php" class="btn btn-primary w-100">Manage Property Cards</a>
                            </div>
                            <div class="col-md-6">
                                <a href="property_transfers.php" class="btn btn-primary w-100">Manage Transfers</a>
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

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-8">
                            <h5 class="mb-0 text-gray-900">Decision Watchlist</h5>
                            <span class="badge bg-danger text-white"><?= number_format($watchlistAlertCount) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-12 text-gray-700 d-flex justify-content-between gap-8">
                                <span>Pending transfers over <?= (int)$pendingThresholdDays ?> days</span>
                                <span class="fw-semibold"><?= number_format($pendingTransfersOverThresholdCount) ?></span>
                            </li>
                            <li class="mb-12 text-gray-700 d-flex justify-content-between gap-8">
                                <span>Oldest pending transfer age</span>
                                <span class="fw-semibold"><?= number_format($oldestPendingTransferDays) ?> day(s)</span>
                            </li>
                            <li class="mb-12 text-gray-700 d-flex justify-content-between gap-8">
                                <span>Active docs without item lines</span>
                                <span class="fw-semibold"><?= number_format($openDocsWithoutItemsCount) ?></span>
                            </li>
                            <li class="mb-12 text-gray-700 d-flex justify-content-between gap-8">
                                <span>Unassigned property cards</span>
                                <span class="fw-semibold"><?= number_format($unassignedCardsCount) ?></span>
                            </li>
                            <li class="mb-0 text-gray-700 d-flex justify-content-between gap-8">
                                <span>Total active accountability value</span>
                                <span class="fw-semibold">PHP <?= number_format($totalAccountabilityValue, 2) ?></span>
                            </li>
                        </ul>
                        <div class="border-top border-gray-100 mt-16 pt-16">
                            <a href="property_transfers.php" class="btn btn-sm btn-outline-primary me-8 mb-8">Review Transfers</a>
                            <a href="property_cards.php" class="btn btn-sm btn-outline-primary mb-8">Review Cards</a>
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
    const icsMonthlySeries = <?= json_encode($icsMonthlySeries) ?>;
    const parMonthlySeries = <?= json_encode($parMonthlySeries) ?>;
    const cardStatusLabels = <?= json_encode($cardStatusLabels) ?>;
    const cardStatusSeries = <?= json_encode($cardStatusSeries) ?>;
    const transferStatusLabels = <?= json_encode($transferStatusLabels) ?>;
    const transferStatusSeries = <?= json_encode($transferStatusSeries) ?>;
    const departmentLabels = <?= json_encode($departmentLabels) ?>;
    const departmentSeries = <?= json_encode($departmentSeries) ?>;

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

    new ApexCharts(document.querySelector('#icsParTrendChart'), {
        ...chartDefaults,
        chart: { ...chartDefaults.chart, type: 'line', height: 280 },
        series: [
            { name: 'ICS', data: icsMonthlySeries },
            { name: 'PAR', data: parMonthlySeries }
        ],
        xaxis: { categories: monthlyLabels },
        yaxis: { min: 0, forceNiceScale: true },
        stroke: { curve: 'smooth', width: [3, 3] },
        dataLabels: { enabled: false },
        colors: ['#2563eb', '#f97316']
    }).render();

    new ApexCharts(document.querySelector('#cardStatusChart'), {
        ...chartDefaults,
        chart: { ...chartDefaults.chart, type: 'donut', height: 280 },
        series: cardStatusSeries,
        labels: cardStatusLabels,
        dataLabels: { enabled: true },
        legend: { position: 'bottom' },
        colors: ['#22c55e', '#f59e0b', '#ef4444', '#64748b']
    }).render();

    new ApexCharts(document.querySelector('#transferStatusChart'), {
        ...chartDefaults,
        chart: { ...chartDefaults.chart, type: 'bar', height: 280 },
        series: [{ name: 'Transfers', data: transferStatusSeries }],
        xaxis: { categories: transferStatusLabels },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
        dataLabels: { enabled: false },
        colors: ['#0ea5e9']
    }).render();

    new ApexCharts(document.querySelector('#departmentAccountabilityChart'), {
        ...chartDefaults,
        chart: { ...chartDefaults.chart, type: 'bar', height: 320 },
        series: [{ name: 'Accountability Records', data: departmentSeries }],
        xaxis: {
            categories: departmentLabels,
            labels: { rotate: -35 }
        },
        yaxis: { min: 0, forceNiceScale: true },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        colors: ['#14b8a6']
    }).render();
})();
</script>
