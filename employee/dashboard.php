<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([6]); // Employee

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../repo/RIS.model.php';

$pageTitle = 'Employee Dashboard';
$userName = getUser('fullname') ? htmlspecialchars(getUser('fullname'), ENT_QUOTES, 'UTF-8') : 'Employee';
$departmentName = getUser('departmentname') ? htmlspecialchars(getUser('departmentname'), ENT_QUOTES, 'UTF-8') : 'your department';
$roleDescription = 'Submit supply/property requests and monitor issuance progress in one place.';

$currentUserId = (int)(getUser('id') ?? 0);
$pendingStatuses = ['Requested', 'Pending', 'Approved', 'Partial Issued', 'Issued'];

$requestCounts = [
    'total' => 0,
    'pending' => 0,
    'completed' => 0,
];
$activePropertyDocs = 0;
$assignedPropertyItems = 0;
$requestStatusCounts = [
    'Requested' => 0,
    'Pending' => 0,
    'Approved' => 0,
    'Partial Issued' => 0,
    'Issued' => 0,
    'Completed' => 0,
    'Rejected' => 0,
    'Cancelled' => 0,
];
$activePropertyByType = [
    'ICS' => 0,
    'PAR' => 0,
];
$monthlyRequested = [];
$monthlyCompleted = [];
$monthlyLabelsMap = [];
$monthCursor = new DateTime('first day of this month');
for ($i = 11; $i >= 0; $i--) {
    $monthObj = (clone $monthCursor)->modify("-{$i} months");
    $monthKey = $monthObj->format('Y-m');
    $monthlyRequested[$monthKey] = 0;
    $monthlyCompleted[$monthKey] = 0;
    $monthlyLabelsMap[$monthKey] = $monthObj->format('M Y');
}

try {
    $risModel = new RIS();
    $allRequests = $risModel->getAll();

    foreach ($allRequests as $row) {
        if ((int)($row['requested_by'] ?? 0) !== $currentUserId) {
            continue;
        }
        $requestCounts['total']++;
        $status = (string)($row['status'] ?? '');
        if (in_array($status, $pendingStatuses, true)) {
            $requestCounts['pending']++;
        }
        if ($status === 'Completed') {
            $requestCounts['completed']++;
        }
        if (isset($requestStatusCounts[$status])) {
            $requestStatusCounts[$status]++;
        }

        $requisitionDate = trim((string)($row['requisition_date'] ?? ''));
        if ($requisitionDate !== '') {
            $requestDate = DateTime::createFromFormat('Y-m-d', substr($requisitionDate, 0, 10));
            if ($requestDate instanceof DateTime) {
                $monthKey = $requestDate->format('Y-m');
                if (isset($monthlyRequested[$monthKey])) {
                    $monthlyRequested[$monthKey]++;
                    if ($status === 'Completed') {
                        $monthlyCompleted[$monthKey]++;
                    }
                }
            }
        }
    }

    $db = Model::Db();
    $docKeys = [];

    $icsStmt = $db->prepare("SELECT id FROM inventory_custodian_slips WHERE status = 'Active' AND (assigned_to = :uid OR received_by = :uid)");
    $icsStmt->execute(['uid' => $currentUserId]);
    foreach ($icsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
        $docKeys['ICS-' . (int)$doc['id']] = true;
        $activePropertyByType['ICS']++;
    }

    $parStmt = $db->prepare("SELECT id FROM property_acknowledgment_receipts WHERE status = 'Active' AND accountable_officer = :uid");
    $parStmt->execute(['uid' => $currentUserId]);
    foreach ($parStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
        $docKeys['PAR-' . (int)$doc['id']] = true;
        $activePropertyByType['PAR']++;
    }

    $activePropertyDocs = count($docKeys);

    $icsItemCountStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM inventory_custodian_slips h
         INNER JOIN ics_items ii ON ii.ics_id = h.id
         WHERE h.assigned_to = :uid OR h.received_by = :uid"
    );
    $icsItemCountStmt->execute(['uid' => $currentUserId]);
    $icsItemCount = (int)$icsItemCountStmt->fetchColumn();

    $parItemCountStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM property_acknowledgment_receipts h
         INNER JOIN par_items pi ON pi.par_id = h.id
         WHERE h.accountable_officer = :uid"
    );
    $parItemCountStmt->execute(['uid' => $currentUserId]);
    $parItemCount = (int)$parItemCountStmt->fetchColumn();

    $assignedPropertyItems = $icsItemCount + $parItemCount;
} catch (Throwable $e) {
    error_log('Employee dashboard metrics failed: ' . $e->getMessage());
}

$monthlyLabels = array_values($monthlyLabelsMap);
$monthlyRequestedSeries = array_values($monthlyRequested);
$monthlyCompletedSeries = array_values($monthlyCompleted);
$statusChartLabels = [];
$statusChartSeries = [];
foreach ($requestStatusCounts as $statusLabel => $count) {
    if ($count > 0) {
        $statusChartLabels[] = $statusLabel;
        $statusChartSeries[] = $count;
    }
}
if (empty($statusChartSeries)) {
    $statusChartLabels = ['No Requests'];
    $statusChartSeries = [1];
}

$chartPayload = [
    'monthlyLabels' => $monthlyLabels,
    'monthlyRequested' => $monthlyRequestedSeries,
    'monthlyCompleted' => $monthlyCompletedSeries,
    'statusLabels' => $statusChartLabels,
    'statusSeries' => $statusChartSeries,
    'propertyLabels' => ['ICS', 'PAR'],
    'propertySeries' => [(int)$activePropertyByType['ICS'], (int)$activePropertyByType['PAR']],
    'hasRequests' => $requestCounts['total'] > 0,
];

require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/preload.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body p-24">
        <div class="container-fluid">
                <div class="card border-0 shadow-sm mb-24">
                    <div class="card-body p-4">
                        <h1 class="h4 mb-8"><?=$pageTitle;?></h1>
                        <p class="text-gray-600 mb-0"><?=$roleDescription;?></p>
                    </div>
                </div>

            <div class="row gy-3 mb-24">
                <div class="col-12 col-md-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h6 text-uppercase text-gray-500 mb-8">Total Requests</h3>
                            <p class="h3 mb-0"><?= (int)$requestCounts['total'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h6 text-uppercase text-gray-500 mb-8">Pending / In Progress</h3>
                            <p class="h3 mb-0"><?= (int)$requestCounts['pending'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h6 text-uppercase text-gray-500 mb-8">Completed Requests</h3>
                            <p class="h3 mb-0"><?= (int)$requestCounts['completed'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h6 text-uppercase text-gray-500 mb-8">Active Property Docs</h3>
                            <p class="h3 mb-0"><?= (int)$activePropertyDocs ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h6 text-uppercase text-gray-500 mb-8">Assigned Property Items</h3>
                            <p class="h3 mb-0"><?= (int)$assignedPropertyItems ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row gy-3 mb-24">
                <div class="col-12 col-xl-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-8 mb-8">
                                <h2 class="h5 mb-0">Request Trend</h2>
                                <div class="d-flex align-items-center gap-8">
                                    <span class="text-gray-500 text-13">Monthly Requested vs Completed</span>
                                    <select id="trendRangeFilter" class="form-select form-select-sm trend-range-select" aria-label="Trend range filter">
                                        <option value="6" selected>6 Months</option>
                                        <option value="12">12 Months</option>
                                    </select>
                                </div>
                            </div>
                            <div id="requestTrendChart" class="employee-chart"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-8">Current Request Status Mix</h2>
                            <p class="text-gray-500 text-13 mb-0">Distribution of your RIS statuses.</p>
                            <div id="statusMixChart" class="employee-chart-sm"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row gy-3 mb-24">
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-8">Active Property Docs by Type</h2>
                            <p class="text-gray-500 text-13 mb-0">Active accountability documents currently assigned to you.</p>
                            <div id="propertyDocsChart" class="employee-chart-sm"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h6 text-uppercase text-gray-500 mb-12">Status Legend</h3>
                            <p class="text-gray-600 mb-0">
                                Requested/Pending: waiting for review.<br>
                                Approved/Partial Issued/Issued: being released by supply/property team.<br>
                                Completed: fully served and closed.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row gy-3">
                <div class="col-12 col-xl-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-8">Welcome, <?=$userName;?>!</h2>
                            <p class="text-gray-600 mb-16">
                                You're logged in under <?=$departmentName;?>. You can now submit your own supply/property requests
                                and track both request progress and assigned property records from your account.
                            </p>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-8"><i class="ph ph-paper-plane-tilt text-main-600 me-8"></i>Create new RIS requests for both supplies and property items.</li>
                                <li class="mb-8"><i class="ph ph-list-checks text-main-600 me-8"></i>Review issuance progress and completion status anytime.</li>
                                <li class="mb-0"><i class="ph ph-shield-check text-main-600 me-8"></i>Check assigned property/equipment records and current accountability status.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            <h3 class="h6 text-uppercase text-gray-500 mb-12">Request Tools</h3>
                            <a href="requisition.php" class="btn employee-dashboard-btn employee-dashboard-btn-primary mb-12">Request Supplies / Property</a>
                            <a href="acknowledgment.php" class="btn employee-dashboard-btn employee-dashboard-btn-primary mb-12">View Request Status</a>
                            <a href="assigned-property.php" class="btn employee-dashboard-btn employee-dashboard-btn-primary mb-12">View Assigned Property / Equipment</a>
                            <a href="../index.php" class="btn employee-dashboard-btn employee-dashboard-btn-secondary mb-12">Back to Role Router</a>
                            <a href="../logout.php" class="btn employee-dashboard-btn employee-dashboard-btn-danger">Sign Out</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16 px-24 py-16">
            <p class="text-gray-300 text-13 fw-normal mb-0">&copy; <?=date('Y');?> SMS. All rights reserved.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<style>
.employee-dashboard-btn {
    color: #ffffff !important;
    border: 1px solid transparent !important;
}

.employee-dashboard-btn-primary {
    background-color: #487fff !important;
    border-color: #487fff !important;
}

.employee-dashboard-btn-primary:hover {
    background-color: #345fd1 !important;
    border-color: #345fd1 !important;
}

.employee-dashboard-btn-secondary {
    background-color: #6b7280 !important;
    border-color: #6b7280 !important;
}

.employee-dashboard-btn-secondary:hover {
    background-color: #4b5563 !important;
    border-color: #4b5563 !important;
}

.employee-dashboard-btn-danger {
    background-color: #ea5455 !important;
    border-color: #ea5455 !important;
}

.employee-dashboard-btn-danger:hover {
    background-color: #c24041 !important;
    border-color: #c24041 !important;
}

.employee-chart {
    min-height: 320px;
}

.employee-chart-sm {
    min-height: 300px;
}

.trend-range-select {
    min-width: 118px;
    color: #1f2937 !important;
    background-color: #ffffff !important;
}
</style>

<script>
const employeeDashboardCharts = <?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

$(function () {
    if (typeof ApexCharts === 'undefined') {
        return;
    }

    const getTrendWindow = function (months) {
        const labels = employeeDashboardCharts.monthlyLabels.slice(-months);
        const requested = employeeDashboardCharts.monthlyRequested.slice(-months);
        const completed = employeeDashboardCharts.monthlyCompleted.slice(-months);
        return { labels, requested, completed };
    };

    const trendElement = document.querySelector('#requestTrendChart');
    if (trendElement) {
        const defaultWindow = getTrendWindow(6);
        const trendOptions = {
            chart: { type: 'area', height: 320, toolbar: { show: false } },
            series: [
                { name: 'Requested', data: defaultWindow.requested },
                { name: 'Completed', data: defaultWindow.completed }
            ],
            xaxis: { categories: defaultWindow.labels },
            stroke: { curve: 'smooth', width: [3, 3] },
            dataLabels: { enabled: false },
            fill: {
                type: 'gradient',
                gradient: { opacityFrom: 0.35, opacityTo: 0.05 }
            },
            colors: ['#487fff', '#16a34a'],
            legend: { position: 'top', horizontalAlign: 'left' },
            yaxis: {
                min: 0,
                forceNiceScale: true,
                labels: {
                    formatter: function (value) {
                        return Math.round(value).toString();
                    }
                }
            },
            tooltip: { shared: true, intersect: false }
        };
        const trendChart = new ApexCharts(trendElement, trendOptions);
        trendChart.render();

        const trendFilter = document.querySelector('#trendRangeFilter');
        if (trendFilter) {
            trendFilter.addEventListener('change', function () {
                const selectedMonths = parseInt(this.value, 10) === 12 ? 12 : 6;
                const windowData = getTrendWindow(selectedMonths);
                trendChart.updateOptions({
                    xaxis: { categories: windowData.labels }
                });
                trendChart.updateSeries([
                    { name: 'Requested', data: windowData.requested },
                    { name: 'Completed', data: windowData.completed }
                ]);
            });
        }
    }

    const statusElement = document.querySelector('#statusMixChart');
    if (statusElement) {
        const statusPlaceholder = !employeeDashboardCharts.hasRequests;
        const statusOptions = {
            chart: { type: 'donut', height: 300 },
            series: employeeDashboardCharts.statusSeries,
            labels: employeeDashboardCharts.statusLabels,
            colors: statusPlaceholder
                ? ['#94a3b8']
                : ['#06b6d4', '#f59e0b', '#10b981', '#3b82f6', '#6366f1', '#22c55e', '#ef4444', '#991b1b'],
            legend: { position: 'bottom' },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: function () {
                                    return employeeDashboardCharts.hasRequests
                                        ? employeeDashboardCharts.statusSeries.reduce((sum, val) => sum + val, 0).toString()
                                        : '0';
                                }
                            }
                        }
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return employeeDashboardCharts.hasRequests ? value + ' request(s)' : 'No requests yet';
                    }
                }
            }
        };
        new ApexCharts(statusElement, statusOptions).render();
    }

    const propertyElement = document.querySelector('#propertyDocsChart');
    if (propertyElement) {
        const propertyOptions = {
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            series: [{ name: 'Active Docs', data: employeeDashboardCharts.propertySeries }],
            xaxis: { categories: employeeDashboardCharts.propertyLabels },
            colors: ['#0ea5a4'],
            dataLabels: { enabled: true },
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: '48%'
                }
            },
            yaxis: {
                min: 0,
                forceNiceScale: true,
                labels: {
                    formatter: function (value) {
                        return Math.round(value).toString();
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return value + ' document(s)';
                    }
                }
            }
        };
        new ApexCharts(propertyElement, propertyOptions).render();
    }
});
</script>
