<?php

require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/SystemSettings.model.php';
require_once __DIR__ . '/../repo/PhysicalInventory.model.php';
require_once __DIR__ . '/../repo/PhysicalPpe.model.php';
require_once __DIR__ . '/../repo/UnserviceableProperty.model.php';
require_once __DIR__ . '/../repo/AuditLog.model.php';

guardRole([5]); // Auditor

function auditorFormatTimestamp(?string $timestamp): string
{
    if (!$timestamp) {
        return '-';
    }

    $ts = strtotime($timestamp);
    return $ts ? date('M d, Y h:i A', $ts) : $timestamp;
}

function auditorFormatActionLabel(string $action): string
{
    $action = trim($action);
    if ($action === '') {
        return 'Unknown Action';
    }

    return ucwords(str_replace(['.', '_'], ' ', $action));
}

function auditorActivitySummary(array $activity): string
{
    $summary = trim((string)($activity['audit_summary'] ?? ''));
    if ($summary !== '') {
        return $summary;
    }

    $metadata = json_decode((string)($activity['metadata'] ?? ''), true);
    if (!is_array($metadata) || empty($metadata)) {
        return 'No field-level changes captured.';
    }

    $parts = [];
    foreach (array_slice(array_keys($metadata), 0, 2) as $key) {
        $value = $metadata[$key];
        if (is_scalar($value)) {
            $parts[] = $key . ': ' . $value;
        }
    }

    return empty($parts) ? 'Metadata available.' : implode(' | ', $parts);
}

function auditorBuildScopeClause(array $scope, array &$params, string $alias = 'al', bool $hasWhere = false): string
{
    if (empty($scope)) {
        return '';
    }

    $clauses = [];

    if (!empty($scope['action_like']) && is_array($scope['action_like'])) {
        foreach ($scope['action_like'] as $pattern) {
            $clauses[] = $alias . '.action LIKE ?';
            $params[] = (string)$pattern;
        }
    }

    if (!empty($scope['entity_in']) && is_array($scope['entity_in'])) {
        $placeholders = implode(', ', array_fill(0, count($scope['entity_in']), '?'));
        $clauses[] = $alias . '.entity IN (' . $placeholders . ')';
        foreach ($scope['entity_in'] as $entity) {
            $params[] = (string)$entity;
        }
    }

    if (empty($clauses)) {
        return '';
    }

    return ($hasWhere ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $clauses) . ')';
}

$pageTitle = 'Auditor Dashboard';
$settings = SystemSettings::getSettings();
$organizationName = $settings['organization_name'] ?? 'SMS';
$userName = htmlspecialchars((string)(getUser('fullname') ?? 'Auditor'), ENT_QUOTES, 'UTF-8');
$roleDescription = 'Review compliance reports, prioritize unresolved sign-offs, and monitor scoped audit risk.';

$adminBase = rtrim(BASE_URL, '/') . '/administrator/';

$monitoringThresholds = [
    'activity_window_days' => 30,
    'filing_window_days' => 30,
    'high_risk_window_days' => 14,
    'signoff_overdue_days' => 21,
];

$activityWindowDays = max(1, (int)$monitoringThresholds['activity_window_days']);
$filingWindowDays = max(1, (int)$monitoringThresholds['filing_window_days']);
$highRiskWindowDays = max(1, (int)$monitoringThresholds['high_risk_window_days']);
$signoffOverdueDays = max(1, (int)$monitoringThresholds['signoff_overdue_days']);

$metrics = [
    'audit_events' => 0,
    'audit_events_window' => 0,
    'reports_window' => 0,
    'rpci_reports' => 0,
    'rpcppe_reports' => 0,
    'iirup_reports' => 0,
    'pending_signoff' => 0,
    'pending_signoff_over_threshold' => 0,
    'high_risk_events_window' => 0,
    'variance_lines_total' => 0,
];

$pendingBreakdown = [
    'RPCI' => 0,
    'RPCPPE' => 0,
    'IIRUP' => 0,
];

$varianceBreakdown = [
    'RPCI' => ['lines' => 0, 'abs_qty' => 0],
    'RPCPPE' => ['lines' => 0, 'abs_qty' => 0],
];

$latestReports = [
    'rpci' => null,
    'rpcppe' => null,
    'iirup' => null,
];

$reportTrendPayload = [
    'labels' => [],
    'rpci' => [],
    'rpcppe' => [],
    'iirup' => [],
];

$oldestPendingReports = [];
$highRiskActivities = [];
$recentActivities = [];

try {
    // Ensure report and audit tables exist before metrics queries.
    new PhysicalInventoryReport();
    new PhysicalPpeReport();
    new UnserviceablePropertyReport();
    new AuditLog();

    $db = BaseModel::Db();

    $scalar = static function (PDO $dbConn, string $sql, array $params = []): int {
        $stmt = $dbConn->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    };

    $metrics['rpci_reports'] = $scalar($db, 'SELECT COUNT(*) FROM physical_inventory_reports');
    $metrics['rpcppe_reports'] = $scalar($db, 'SELECT COUNT(*) FROM physical_ppe_reports');
    $metrics['iirup_reports'] = $scalar($db, 'SELECT COUNT(*) FROM unserviceable_property_reports');
    $metrics['reports_window'] = $scalar(
        $db,
        "
            SELECT
                (SELECT COUNT(*) FROM physical_inventory_reports WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL {$filingWindowDays} DAY))
                + (SELECT COUNT(*) FROM physical_ppe_reports WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL {$filingWindowDays} DAY))
                + (SELECT COUNT(*) FROM unserviceable_property_reports WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL {$filingWindowDays} DAY))
        "
    );

    $pendingBreakdown['RPCI'] = $scalar($db, 'SELECT COUNT(*) FROM physical_inventory_reports WHERE verified_by IS NULL');
    $pendingBreakdown['RPCPPE'] = $scalar($db, 'SELECT COUNT(*) FROM physical_ppe_reports WHERE verified_by IS NULL');
    $pendingBreakdown['IIRUP'] = $scalar(
        $db,
        'SELECT COUNT(*) FROM unserviceable_property_reports WHERE inspected_by IS NULL OR approved_by IS NULL'
    );
    $metrics['pending_signoff'] = array_sum($pendingBreakdown);
    $metrics['pending_signoff_over_threshold'] = $scalar(
        $db,
        "
            SELECT
                (SELECT COUNT(*) FROM physical_inventory_reports WHERE verified_by IS NULL AND report_date <= DATE_SUB(CURDATE(), INTERVAL {$signoffOverdueDays} DAY))
                + (SELECT COUNT(*) FROM physical_ppe_reports WHERE verified_by IS NULL AND report_date <= DATE_SUB(CURDATE(), INTERVAL {$signoffOverdueDays} DAY))
                + (SELECT COUNT(*) FROM unserviceable_property_reports WHERE (inspected_by IS NULL OR approved_by IS NULL) AND report_date <= DATE_SUB(CURDATE(), INTERVAL {$signoffOverdueDays} DAY))
        "
    );

    $varianceBreakdown['RPCI']['lines'] = $scalar(
        $db,
        'SELECT COUNT(*) FROM physical_inventory_items WHERE counted_qty <> system_qty'
    );
    $varianceBreakdown['RPCI']['abs_qty'] = $scalar(
        $db,
        'SELECT COALESCE(SUM(ABS(counted_qty - system_qty)), 0) FROM physical_inventory_items'
    );
    $varianceBreakdown['RPCPPE']['lines'] = $scalar(
        $db,
        'SELECT COUNT(*) FROM physical_ppe_items WHERE physical_qty <> property_card_qty'
    );
    $varianceBreakdown['RPCPPE']['abs_qty'] = $scalar(
        $db,
        'SELECT COALESCE(SUM(ABS(physical_qty - property_card_qty)), 0) FROM physical_ppe_items'
    );
    $metrics['variance_lines_total'] = $varianceBreakdown['RPCI']['lines'] + $varianceBreakdown['RPCPPE']['lines'];

    $latestReports['rpci'] = $db
        ->query('SELECT report_no, report_date FROM physical_inventory_reports ORDER BY report_date DESC, id DESC LIMIT 1')
        ->fetch(PDO::FETCH_ASSOC);
    $latestReports['rpcppe'] = $db
        ->query('SELECT report_no, report_date FROM physical_ppe_reports ORDER BY report_date DESC, id DESC LIMIT 1')
        ->fetch(PDO::FETCH_ASSOC);
    $latestReports['iirup'] = $db
        ->query('SELECT report_no, report_date FROM unserviceable_property_reports ORDER BY report_date DESC, id DESC LIMIT 1')
        ->fetch(PDO::FETCH_ASSOC);

    $scope = AuditLog::scopeForRole((int)(getUser('roleid') ?? 0));
    $metrics['audit_events'] = AuditLog::countActivities($scope);
    $recentActivities = AuditLog::fetchActivitiesPage(1, 8, $scope);

    $auditWindowParams = [];
    $auditWindowScopeSql = auditorBuildScopeClause($scope, $auditWindowParams, 'al', true);
    $auditWindowSql = 'SELECT COUNT(*) FROM activity_logs al WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL ' . $activityWindowDays . ' DAY)' . $auditWindowScopeSql;
    $auditWindowStmt = $db->prepare($auditWindowSql);
    $auditWindowStmt->execute($auditWindowParams);
    $metrics['audit_events_window'] = (int)$auditWindowStmt->fetchColumn();

    $highRiskWhereSql = "(al.action LIKE '%.failed%' OR al.action LIKE '%.denied%' OR al.action LIKE '%.delete.%' OR al.action LIKE '%.cancel.%')";

    $highRiskCountParams = [];
    $highRiskCountScopeSql = auditorBuildScopeClause($scope, $highRiskCountParams, 'al', true);
    $highRiskCountSql = 'SELECT COUNT(*) FROM activity_logs al WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL ' . $highRiskWindowDays . ' DAY) AND ' . $highRiskWhereSql . $highRiskCountScopeSql;
    $highRiskCountStmt = $db->prepare($highRiskCountSql);
    $highRiskCountStmt->execute($highRiskCountParams);
    $metrics['high_risk_events_window'] = (int)$highRiskCountStmt->fetchColumn();

    $highRiskParams = [];
    $highRiskScopeSql = auditorBuildScopeClause($scope, $highRiskParams, 'al', true);
    $highRiskSql = "
        SELECT
            al.created_at,
            al.action,
            al.entity,
            TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, ''))) AS actor_name
        FROM activity_logs al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL {$highRiskWindowDays} DAY)
          AND {$highRiskWhereSql}
          {$highRiskScopeSql}
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT 8
    ";
    $highRiskStmt = $db->prepare($highRiskSql);
    $highRiskStmt->execute($highRiskParams);
    $highRiskActivities = $highRiskStmt->fetchAll(PDO::FETCH_ASSOC);

    $oldestPendingSql = "
        SELECT
            pending_rows.report_type,
            pending_rows.report_no,
            pending_rows.report_date,
            pending_rows.pending_fields,
            pending_rows.age_days,
            pending_rows.href
        FROM (
            SELECT
                'RPCI' AS report_type,
                report_no,
                report_date,
                'Verified By' AS pending_fields,
                DATEDIFF(CURDATE(), report_date) AS age_days,
                'physical_inventory_reports.php' AS href
            FROM physical_inventory_reports
            WHERE verified_by IS NULL

            UNION ALL

            SELECT
                'RPCPPE' AS report_type,
                report_no,
                report_date,
                'Verified By' AS pending_fields,
                DATEDIFF(CURDATE(), report_date) AS age_days,
                'physical_ppe_reports.php' AS href
            FROM physical_ppe_reports
            WHERE verified_by IS NULL

            UNION ALL

            SELECT
                'IIRUP' AS report_type,
                report_no,
                report_date,
                TRIM(BOTH ', ' FROM CONCAT(
                    CASE WHEN inspected_by IS NULL THEN 'Inspected By' ELSE '' END,
                    CASE WHEN inspected_by IS NULL AND approved_by IS NULL THEN ', ' ELSE '' END,
                    CASE WHEN approved_by IS NULL THEN 'Approved By' ELSE '' END
                )) AS pending_fields,
                DATEDIFF(CURDATE(), report_date) AS age_days,
                'unserviceable_reports.php' AS href
            FROM unserviceable_property_reports
            WHERE inspected_by IS NULL OR approved_by IS NULL
        ) AS pending_rows
        ORDER BY pending_rows.age_days DESC, pending_rows.report_date ASC, pending_rows.report_no ASC
        LIMIT 8
    ";
    $oldestPendingReports = $db->query($oldestPendingSql)->fetchAll(PDO::FETCH_ASSOC);

    // Build a 6-month filing trend for report oversight.
    $trendStart = (new DateTime('first day of this month'))->modify('-5 months');
    $trendEnd = new DateTime('first day of this month');
    $trendCursor = clone $trendStart;
    $trendIndex = [];
    while ($trendCursor <= $trendEnd) {
        $monthKey = $trendCursor->format('Y-m');
        $trendIndex[$monthKey] = count($reportTrendPayload['labels']);
        $reportTrendPayload['labels'][] = $trendCursor->format('M Y');
        $reportTrendPayload['rpci'][] = 0;
        $reportTrendPayload['rpcppe'][] = 0;
        $reportTrendPayload['iirup'][] = 0;
        $trendCursor->modify('+1 month');
    }

    $trendStartDate = $trendStart->format('Y-m-01');
    $loadTrendSeries = static function (
        PDO $dbConn,
        string $tableName,
        string $seriesKey,
        string $startDate,
        array $indexMap,
        array &$payload
    ): void {
        $stmt = $dbConn->prepare(
            "SELECT DATE_FORMAT(report_date, '%Y-%m') AS month_key, COUNT(*) AS report_count
             FROM {$tableName}
             WHERE report_date >= ?
             GROUP BY month_key"
        );
        $stmt->execute([$startDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $monthKey = (string)($row['month_key'] ?? '');
            if (!isset($indexMap[$monthKey])) {
                continue;
            }
            $payload[$seriesKey][$indexMap[$monthKey]] = (int)($row['report_count'] ?? 0);
        }
    };

    $loadTrendSeries($db, 'physical_inventory_reports', 'rpci', $trendStartDate, $trendIndex, $reportTrendPayload);
    $loadTrendSeries($db, 'physical_ppe_reports', 'rpcppe', $trendStartDate, $trendIndex, $reportTrendPayload);
    $loadTrendSeries($db, 'unserviceable_property_reports', 'iirup', $trendStartDate, $trendIndex, $reportTrendPayload);
} catch (Throwable $e) {
    error_log('auditor dashboard metrics failed: ' . $e->getMessage());
}

$reportTotals = [
    'RPCI' => $metrics['rpci_reports'],
    'RPCPPE' => $metrics['rpcppe_reports'],
    'IIRUP' => $metrics['iirup_reports'],
];

require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/preload.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div>
                <h4 class="mb-4 text-gray-900"><?= $pageTitle ?></h4>
                <p class="text-gray-500 mb-0 text-14">
                    <?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?> | <?= $roleDescription ?>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-8">
                <span class="badge bg-warning text-dark text-13">Pending Sign-off: <?= number_format($metrics['pending_signoff']) ?></span>
                <span class="badge <?= $metrics['pending_signoff_over_threshold'] > 0 ? 'bg-danger' : 'bg-success' ?> text-13">
                    Pending &gt; <?= number_format($signoffOverdueDays) ?> Days: <?= number_format($metrics['pending_signoff_over_threshold']) ?>
                </span>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Audit Events (Scoped)</p>
                        <h4 class="mb-0 text-gray-900"><?= number_format($metrics['audit_events']) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Audit Events (<?= number_format($activityWindowDays) ?>d)</p>
                        <h4 class="mb-0 text-info-700"><?= number_format($metrics['audit_events_window']) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Reports Filed (<?= number_format($filingWindowDays) ?>d)</p>
                        <h4 class="mb-0 text-main-700"><?= number_format($metrics['reports_window']) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Pending Sign-off</p>
                        <h4 class="mb-0 <?= $metrics['pending_signoff'] > 0 ? 'text-warning-700' : 'text-success-700' ?>">
                            <?= number_format($metrics['pending_signoff']) ?>
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Pending &gt; <?= number_format($signoffOverdueDays) ?> Days</p>
                        <h4 class="mb-0 <?= $metrics['pending_signoff_over_threshold'] > 0 ? 'text-danger-700' : 'text-success-700' ?>">
                            <?= number_format($metrics['pending_signoff_over_threshold']) ?>
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">High-Risk Events (<?= number_format($highRiskWindowDays) ?>d)</p>
                        <h4 class="mb-0 <?= $metrics['high_risk_events_window'] > 0 ? 'text-danger-700' : 'text-success-700' ?>">
                            <?= number_format($metrics['high_risk_events_window']) ?>
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Quick Access</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-gray-500 mb-12">Hello, <?= $userName ?>.</p>
                        <div class="row g-12 mb-12">
                            <div class="col-12"><a href="<?= $adminBase ?>audit_logs.php" class="btn btn-primary w-100">Audit Logs</a></div>
                            <div class="col-12"><a href="<?= $adminBase ?>physical_inventory_reports.php" class="btn btn-primary w-100">RPCI Reports</a></div>
                            <div class="col-12"><a href="<?= $adminBase ?>physical_ppe_reports.php" class="btn btn-primary w-100">RPCPPE Reports</a></div>
                            <div class="col-12"><a href="<?= $adminBase ?>unserviceable_reports.php" class="btn btn-primary w-100">IIRUP Reports</a></div>
                            <div class="col-12"><a href="../profile.php" class="btn btn-secondary w-100">My Profile</a></div>
                            <div class="col-12"><a href="../logout.php" class="btn btn-outline-danger w-100">Sign Out</a></div>
                        </div>

                        <div class="border-top border-gray-100 pt-12 mb-12">
                            <p class="text-13 text-gray-500 mb-8">Latest Reports</p>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-8 text-13">
                                    <span class="fw-semibold">RPCI:</span>
                                    <?= !empty($latestReports['rpci']['report_no']) ? htmlspecialchars($latestReports['rpci']['report_no'], ENT_QUOTES, 'UTF-8') . ' (' . DisplayDate($latestReports['rpci']['report_date']) . ')' : 'No records' ?>
                                </li>
                                <li class="mb-8 text-13">
                                    <span class="fw-semibold">RPCPPE:</span>
                                    <?= !empty($latestReports['rpcppe']['report_no']) ? htmlspecialchars($latestReports['rpcppe']['report_no'], ENT_QUOTES, 'UTF-8') . ' (' . DisplayDate($latestReports['rpcppe']['report_date']) . ')' : 'No records' ?>
                                </li>
                                <li class="text-13">
                                    <span class="fw-semibold">IIRUP:</span>
                                    <?= !empty($latestReports['iirup']['report_no']) ? htmlspecialchars($latestReports['iirup']['report_no'], ENT_QUOTES, 'UTF-8') . ' (' . DisplayDate($latestReports['iirup']['report_date']) . ')' : 'No records' ?>
                                </li>
                            </ul>
                        </div>

                        <div class="border-top border-gray-100 pt-12">
                            <p class="text-13 text-gray-500 mb-8">Pending by Report Type</p>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($pendingBreakdown as $type => $count): ?>
                                    <li class="d-flex align-items-center justify-content-between mb-8 text-13">
                                        <span class="text-gray-900"><?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="badge <?= $count > 0 ? 'bg-warning text-dark' : 'bg-success' ?>"><?= number_format((int)$count) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="border-top border-gray-100 pt-12 mt-12">
                            <p class="text-13 text-gray-500 mb-8">Monitoring Thresholds</p>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-8 text-13 text-gray-900">Sign-off aging alert: more than <?= number_format($signoffOverdueDays) ?> days</li>
                                <li class="mb-8 text-13 text-gray-900">High-risk activity review window: last <?= number_format($highRiskWindowDays) ?> days</li>
                                <li class="mb-8 text-13 text-gray-900">Audit activity trend window: last <?= number_format($activityWindowDays) ?> days</li>
                                <li class="text-13 text-gray-900">Report filing trend window: last <?= number_format($filingWindowDays) ?> days</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Report Filing Trend (Last 6 Months)</h5>
                        <p class="text-gray-500 text-12 mb-0">Monthly submissions across RPCI, RPCPPE, and IIRUP.</p>
                    </div>
                    <div class="card-body">
                        <div id="auditorReportTrendChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Compliance Snapshot</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th class="text-13 text-gray-300">Type</th>
                                        <th class="text-13 text-gray-300 text-center">Total</th>
                                        <th class="text-13 text-gray-300 text-center">Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportTotals as $type => $total): ?>
                                        <?php $pendingCount = (int)($pendingBreakdown[$type] ?? 0); ?>
                                        <tr>
                                            <td class="text-gray-900 fw-medium text-13"><?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-center text-gray-900"><?= number_format((int)$total) ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $pendingCount > 0 ? 'bg-warning text-dark' : 'bg-success' ?>">
                                                    <?= number_format($pendingCount) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-20 py-16 border-top border-gray-100">
                            <p class="text-13 text-gray-500 mb-8">Variance Findings</p>
                            <div class="d-flex justify-content-between text-13 mb-6">
                                <span class="text-gray-900">RPCI mismatch lines</span>
                                <span class="fw-semibold text-gray-900"><?= number_format((int)$varianceBreakdown['RPCI']['lines']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-13 mb-6">
                                <span class="text-gray-900">RPCPPE mismatch lines</span>
                                <span class="fw-semibold text-gray-900"><?= number_format((int)$varianceBreakdown['RPCPPE']['lines']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-13 mb-6">
                                <span class="text-gray-900">RPCI absolute qty difference</span>
                                <span class="fw-semibold text-gray-900"><?= number_format((int)$varianceBreakdown['RPCI']['abs_qty']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-13">
                                <span class="text-gray-900">RPCPPE absolute qty difference</span>
                                <span class="fw-semibold text-gray-900"><?= number_format((int)$varianceBreakdown['RPCPPE']['abs_qty']) ?></span>
                            </div>
                            <div class="mt-10">
                                <span class="badge <?= $metrics['variance_lines_total'] > 0 ? 'bg-warning text-dark' : 'bg-success' ?>">
                                    Total mismatch lines: <?= number_format($metrics['variance_lines_total']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <div>
                                <h5 class="mb-0 text-gray-900">Oldest Pending Sign-off</h5>
                                <p class="text-gray-500 text-12 mb-0">Prioritize records waiting longest; red badge indicates more than <?= number_format($signoffOverdueDays) ?> days.</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($oldestPendingReports)): ?>
                            <div class="p-20">
                                <p class="text-gray-500 mb-0">No pending sign-off records.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th class="text-13 text-gray-300">Type</th>
                                            <th class="text-13 text-gray-300">Report No.</th>
                                            <th class="text-13 text-gray-300">Date</th>
                                            <th class="text-13 text-gray-300">Missing Sign-off</th>
                                            <th class="text-13 text-gray-300 text-center">Age</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($oldestPendingReports as $row): ?>
                                            <?php $ageDays = (int)($row['age_days'] ?? 0); ?>
                                            <tr>
                                                <td class="text-gray-900 text-13 fw-medium"><?= htmlspecialchars((string)($row['report_type'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-13">
                                                    <a href="<?= $adminBase . htmlspecialchars((string)($row['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="text-main-600">
                                                        <?= htmlspecialchars((string)($row['report_no'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                </td>
                                                <td class="text-gray-900 text-13"><?= DisplayDate((string)($row['report_date'] ?? '')) ?></td>
                                                <td class="text-gray-900 text-13"><?= htmlspecialchars((string)($row['pending_fields'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?= $ageDays >= $signoffOverdueDays ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= number_format($ageDays) ?>d</span>
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
        </div>

        <div class="row g-24">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <div>
                                <h5 class="mb-0 text-gray-900">High-Risk Scoped Events (<?= number_format($highRiskWindowDays) ?>d)</h5>
                                <p class="text-gray-500 text-12 mb-0">Failed, denied, delete, and cancel events requiring review.</p>
                            </div>
                            <a href="<?= $adminBase ?>audit_logs.php" class="btn btn-sm btn-outline-primary">Open Audit Logs</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($highRiskActivities)): ?>
                            <div class="p-20">
                                <p class="text-gray-500 mb-0">No high-risk scoped events in the last <?= number_format($highRiskWindowDays) ?> days.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th class="text-13 text-gray-300">Timestamp</th>
                                            <th class="text-13 text-gray-300">Action</th>
                                            <th class="text-13 text-gray-300">Entity</th>
                                            <th class="text-13 text-gray-300">Actor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($highRiskActivities as $event): ?>
                                            <?php $actorName = trim((string)($event['actor_name'] ?? '')) !== '' ? (string)$event['actor_name'] : 'System'; ?>
                                            <tr>
                                                <td class="text-gray-900 text-13"><?= htmlspecialchars(auditorFormatTimestamp($event['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900 text-13"><?= htmlspecialchars(auditorFormatActionLabel((string)($event['action'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900 text-13"><?= htmlspecialchars((string)($event['entity'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900 text-13"><?= htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Recent Scoped Activity</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentActivities)): ?>
                            <div class="p-24">
                                <p class="text-gray-500 mb-0">No scoped audit events found yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="h6 text-gray-300">Date/Time</th>
                                            <th class="h6 text-gray-300">User</th>
                                            <th class="h6 text-gray-300">Action</th>
                                            <th class="h6 text-gray-300">Entity</th>
                                            <th class="h6 text-gray-300">Summary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <?php
                                            $fullName = trim((string)($activity['firstname'] ?? '') . ' ' . (string)($activity['lastname'] ?? ''));
                                            $displayUser = $fullName !== '' ? $fullName : 'System';
                                            ?>
                                            <tr>
                                                <td class="text-gray-900"><?= htmlspecialchars(auditorFormatTimestamp($activity['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900"><?= htmlspecialchars($displayUser, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900"><code><?= htmlspecialchars((string)($activity['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                                                <td class="text-gray-900"><?= htmlspecialchars((string)($activity['entity'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-gray-900"><?= htmlspecialchars(auditorActivitySummary($activity), ENT_QUOTES, 'UTF-8') ?></td>
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
    </div>
    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16 px-24 py-16">
            <p class="text-gray-300 text-13 fw-normal mb-0">&copy; <?= date('Y'); ?> <?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/scripts.php'; ?>

<script>
    (function () {
        if (typeof ApexCharts === 'undefined') {
            return;
        }

        const trendData = <?= json_encode($reportTrendPayload) ?>;
        const chartEl = document.querySelector('#auditorReportTrendChart');
        if (!chartEl) {
            return;
        }

        const labels = Array.isArray(trendData.labels) ? trendData.labels : [];
        const rpciSeries = Array.isArray(trendData.rpci) ? trendData.rpci : [];
        const rpcppeSeries = Array.isArray(trendData.rpcppe) ? trendData.rpcppe : [];
        const iirupSeries = Array.isArray(trendData.iirup) ? trendData.iirup : [];

        const chart = new ApexCharts(chartEl, {
            chart: {
                type: 'bar',
                height: 320,
                stacked: true,
                toolbar: { show: false }
            },
            series: [
                { name: 'RPCI', data: rpciSeries },
                { name: 'RPCPPE', data: rpcppeSeries },
                { name: 'IIRUP', data: iirupSeries }
            ],
            colors: ['#0d6efd', '#20c997', '#f59e0b'],
            xaxis: {
                categories: labels,
                labels: { rotate: -30 }
            },
            yaxis: {
                title: { text: 'Reports' },
                labels: {
                    formatter: function (value) {
                        return Math.round(value).toLocaleString();
                    }
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '52%'
                }
            },
            dataLabels: { enabled: false },
            stroke: {
                show: true,
                width: 1,
                colors: ['transparent']
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left'
            },
            grid: {
                borderColor: '#e2e8f0',
                strokeDashArray: 4
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return Number(value).toLocaleString() + ' reports';
                    }
                }
            }
        });

        chart.render();
    })();
</script>
