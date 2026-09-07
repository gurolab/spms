<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/User.model.php';
require_once __DIR__ . '/../repo/SystemSettings.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/AuditLog.model.php';

$pageTitle = 'Administrator Dashboard';

$settings = SystemSettings::getSettings();
$organizationName = $settings['organization_name'] ?? 'SMS';
$fiscalYearStart = $settings['fiscal_year_start'] ?? null;
$fiscalYearEnd = $settings['fiscal_year_end'] ?? null;

$fiscalYearLabel = '';
if ($fiscalYearStart && $fiscalYearEnd) {
    $fiscalYearLabel = (new DateTime($fiscalYearStart))->format('M d, Y')
        . ' - '
        . (new DateTime($fiscalYearEnd))->format('M d, Y');
}

$issuedTrendStart = null;
$issuedTrendEnd = null;
if ($fiscalYearStart && $fiscalYearEnd) {
    try {
        $candidateStart = new DateTime($fiscalYearStart);
        $candidateEnd = new DateTime($fiscalYearEnd);
        if ($candidateStart <= $candidateEnd) {
            $issuedTrendStart = $candidateStart;
            $issuedTrendEnd = $candidateEnd;
        }
    } catch (Throwable $e) {
        // Fall back to current calendar year if fiscal year settings are invalid.
    }
}

if ($issuedTrendStart === null || $issuedTrendEnd === null) {
    $currentYear = (new DateTime('today'))->format('Y');
    $issuedTrendStart = new DateTime($currentYear . '-01-01');
    $issuedTrendEnd = new DateTime($currentYear . '-12-31');
}

$issuedTrendRangeStartDate = $issuedTrendStart->format('Y-m-d');
$issuedTrendRangeEndDate = $issuedTrendEnd->format('Y-m-d');
$issuedTrendMonthPeriods = [];
$issuedTrendQuarterPeriods = [];
$issuedMonthToQuarterKey = [];
$issuedQuarterMeta = [];

$issuedMonthCursor = (clone $issuedTrendStart)->modify('first day of this month');
$issuedMonthLimit = (clone $issuedTrendEnd)->modify('first day of this month');
$issuedMonthOffset = 0;
while ($issuedMonthCursor <= $issuedMonthLimit) {
    $monthKey = $issuedMonthCursor->format('Y-m');
    $issuedTrendMonthPeriods[] = [
        'key' => $monthKey,
        'label' => $issuedMonthCursor->format('M Y'),
    ];

    $quarterIndex = intdiv($issuedMonthOffset, 3) + 1;
    $quarterKey = 'Q' . $quarterIndex;
    $issuedMonthToQuarterKey[$monthKey] = $quarterKey;

    if (!isset($issuedQuarterMeta[$quarterKey])) {
        $issuedQuarterMeta[$quarterKey] = [
            'index' => $quarterIndex,
            'start' => clone $issuedMonthCursor,
            'end' => clone $issuedMonthCursor,
        ];
    } else {
        $issuedQuarterMeta[$quarterKey]['end'] = clone $issuedMonthCursor;
    }

    $issuedMonthOffset++;
    $issuedMonthCursor->modify('+1 month');
}

foreach ($issuedQuarterMeta as $quarterKey => $meta) {
    $issuedTrendQuarterPeriods[] = [
        'key' => $quarterKey,
        'label' => sprintf(
            'Q%d (%s - %s)',
            (int)$meta['index'],
            $meta['start']->format('M Y'),
            $meta['end']->format('M Y')
        ),
    ];
}

$issuedTrendPayload = [
    'month' => [
        'periods' => $issuedTrendMonthPeriods,
        'defaultPeriodKey' => '',
        'seriesByPeriod' => [],
    ],
    'quarter' => [
        'periods' => $issuedTrendQuarterPeriods,
        'defaultPeriodKey' => '',
        'seriesByPeriod' => [],
    ],
];

$defaultMonthPeriodKey = '';
$defaultQuarterPeriodKey = '';
$todayMonthKey = (new DateTime('today'))->format('Y-m');

if (isset($issuedMonthToQuarterKey[$todayMonthKey])) {
    $defaultMonthPeriodKey = $todayMonthKey;
    $defaultQuarterPeriodKey = $issuedMonthToQuarterKey[$todayMonthKey];
} else {
    if (!empty($issuedTrendMonthPeriods)) {
        $defaultMonthPeriodKey = (string)$issuedTrendMonthPeriods[count($issuedTrendMonthPeriods) - 1]['key'];
    }
    if (!empty($issuedTrendQuarterPeriods)) {
        $defaultQuarterPeriodKey = (string)$issuedTrendQuarterPeriods[count($issuedTrendQuarterPeriods) - 1]['key'];
    }
}

$issuedTrendPayload['month']['defaultPeriodKey'] = $defaultMonthPeriodKey;
$issuedTrendPayload['quarter']['defaultPeriodKey'] = $defaultQuarterPeriodKey;

$formatIssuedCode = static function (string $itemCode): string {
    $itemCode = trim($itemCode);
    if ($itemCode === '') {
        return 'N/A';
    }

    $segments = explode('-', $itemCode);
    $tail = trim((string)end($segments));
    return $tail !== '' ? $tail : $itemCode;
};

$formatAuditActionLabel = static function (string $action): string {
    $action = trim($action);
    if ($action === '') {
        return 'Unknown Action';
    }

    return ucwords(str_replace(['.', '_'], ' ', $action));
};

$formatTimestampLabel = static function (?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return 'N/A';
    }

    try {
        return (new DateTime($raw))->format('M d, Y h:i A');
    } catch (Throwable $e) {
        return $raw;
    }
};

$buildTopItemSeries = static function (array $codeTotals, callable $codeFormatter): array {
    if (empty($codeTotals)) {
        return [
            'labels' => ['No Data'],
            'values' => [0],
            'codes' => [''],
        ];
    }

    $rows = [];
    foreach ($codeTotals as $code => $qty) {
        $rows[] = [
            'code' => (string)$code,
            'qty' => (int)$qty,
        ];
    }

    usort($rows, static function (array $left, array $right): int {
        if ($left['qty'] === $right['qty']) {
            return strcmp($left['code'], $right['code']);
        }
        return $right['qty'] <=> $left['qty'];
    });

    $rows = array_slice($rows, 0, 10);

    $labels = [];
    $values = [];
    $codes = [];
    foreach ($rows as $row) {
        $codes[] = $row['code'];
        $labels[] = $codeFormatter($row['code']);
        $values[] = $row['qty'];
    }

    return [
        'labels' => $labels,
        'values' => $values,
        'codes' => $codes,
    ];
};

$dashboardRoleId = (int)(getUser('roleid') ?? 0);
$dashboardAccessMatrix = adminAccessMatrix();
$canAccessDashboardModule = static function (string $module) use ($dashboardAccessMatrix, $dashboardRoleId): bool {
    $allowedRoles = $dashboardAccessMatrix[$module] ?? [ROLE_SYSTEM_ADMIN];
    return in_array($dashboardRoleId, $allowedRoles, true);
};
$canAccessAuditLogs = $canAccessDashboardModule('audit_logs.php');
$consumptionColumnClass = $canAccessAuditLogs ? 'col-lg-6' : 'col-lg-12';
$highRiskWhereSql = "(al.action LIKE '%.failed%' OR al.action LIKE '%.denied%' OR al.action LIKE '%.delete.%' OR al.action LIKE '%.cancel.%' OR al.action = 'auth.login.failed')";
$dashboardAuditScope = AuditLog::scopeForRole($dashboardRoleId);
$buildDashboardAuditScopeClause = static function (array $scope, array &$params, bool $hasWhere = false): string {
    if (empty($scope)) {
        return '';
    }

    $includeClauses = [];
    $excludeClauses = [];

    if (!empty($scope['action_like']) && is_array($scope['action_like'])) {
        foreach ($scope['action_like'] as $pattern) {
            $includeClauses[] = 'al.action LIKE ?';
            $params[] = (string)$pattern;
        }
    }

    if (!empty($scope['entity_in']) && is_array($scope['entity_in'])) {
        $placeholders = implode(', ', array_fill(0, count($scope['entity_in']), '?'));
        $includeClauses[] = 'al.entity IN (' . $placeholders . ')';
        foreach ($scope['entity_in'] as $entity) {
            $params[] = (string)$entity;
        }
    }

    if (!empty($scope['exclude_action_like']) && is_array($scope['exclude_action_like'])) {
        foreach ($scope['exclude_action_like'] as $pattern) {
            $excludeClauses[] = 'al.action NOT LIKE ?';
            $params[] = (string)$pattern;
        }
    }

    if (!empty($scope['exclude_entity_in']) && is_array($scope['exclude_entity_in'])) {
        $placeholders = implode(', ', array_fill(0, count($scope['exclude_entity_in']), '?'));
        $excludeClauses[] = 'al.entity NOT IN (' . $placeholders . ')';
        foreach ($scope['exclude_entity_in'] as $entity) {
            $params[] = (string)$entity;
        }
    }

    $scopeGroups = [];
    if (!empty($includeClauses)) {
        $scopeGroups[] = '(' . implode(' OR ', $includeClauses) . ')';
    }
    if (!empty($excludeClauses)) {
        $scopeGroups[] = '(' . implode(' AND ', $excludeClauses) . ')';
    }

    if (empty($scopeGroups)) {
        return '';
    }

    return ($hasWhere ? ' AND ' : ' WHERE ') . implode(' AND ', $scopeGroups);
};

$metrics = [
    'users_total' => 0,
    'users_active' => 0,
    'ris_pending' => 0,
    'ris_overdue_7_days' => 0,
    'inventory_alerts' => 0,
    'open_accountabilities' => 0,
    'transfer_overdue_14_days' => 0,
    'audit_30_days' => 0,
    'high_risk_events_14_days' => 0,
];

$receivedMonthLabels = [];
$receivedMonthSeries = [];
$receivedMonthIndex = [];
foreach ($issuedTrendMonthPeriods as $period) {
    $periodKey = (string)$period['key'];
    $receivedMonthIndex[$periodKey] = count($receivedMonthLabels);
    $receivedMonthLabels[] = (string)$period['label'];
    $receivedMonthSeries[] = 0;
}

$receivedQuarterLabels = [];
$receivedQuarterSeries = [];
$receivedQuarterIndex = [];
foreach ($issuedTrendQuarterPeriods as $period) {
    $periodKey = (string)$period['key'];
    $receivedQuarterIndex[$periodKey] = count($receivedQuarterLabels);
    $receivedQuarterLabels[] = (string)$period['label'];
    $receivedQuarterSeries[] = 0;
}

$receivedTrendPayload = [
    'month' => [
        'categories' => $receivedMonthLabels,
        'values' => $receivedMonthSeries,
    ],
    'quarter' => [
        'categories' => $receivedQuarterLabels,
        'values' => $receivedQuarterSeries,
    ],
    'defaultMode' => 'month',
];

$issuedVolumeMonthSeries = array_fill(0, count($receivedMonthLabels), 0);
$issuedVolumeQuarterSeries = array_fill(0, count($receivedQuarterLabels), 0);

$supplyFlowTrendPayload = [
    'month' => [
        'categories' => $receivedMonthLabels,
        'received' => $receivedMonthSeries,
        'issued' => $issuedVolumeMonthSeries,
        'net' => array_fill(0, count($receivedMonthLabels), 0),
    ],
    'quarter' => [
        'categories' => $receivedQuarterLabels,
        'received' => $receivedQuarterSeries,
        'issued' => $issuedVolumeQuarterSeries,
        'net' => array_fill(0, count($receivedQuarterLabels), 0),
    ],
    'defaultMode' => 'month',
];

$risStatusBuckets = [
    'Requested' => 0,
    'Approved' => 0,
    'Partial Issued' => 0,
    'Issued' => 0,
    'Completed' => 0,
];
$risOtherCount = 0;

$inventoryHealthBuckets = [
    'Healthy' => 0,
    'Reorder' => 0,
    'Critical' => 0,
];

$propertyLabels = ['ICS', 'PAR', 'Transfers'];
$propertyOpenSeries = [0, 0, 0];
$propertyClosedSeries = [0, 0, 0];

$departmentDemandLabels = [];
$departmentDemandSeries = [];

$reorderAlerts = [];
$alertsToDisplay = [];
$workflowAgingRows = [];
$pendingRisAgingRows = [];
$riskEventRows = [];
$departmentTopConsumptionRows = [];

try {
    $db = User::Db();

    $scalar = static function (PDO $dbConn, string $sql, array $params = []): int {
        $stmt = $dbConn->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    };

    $metrics['users_total'] = $scalar($db, 'SELECT COUNT(*) FROM users');
    $metrics['users_active'] = $scalar($db, "SELECT COUNT(*) FROM users WHERE LOWER(COALESCE(status, '')) = 'active'");

    $pendingRisStatuses = ['Requested', 'Approved', 'Partial Issued'];
    $pendingRisStatusPlaceholders = implode(', ', array_fill(0, count($pendingRisStatuses), '?'));
    $pendingRisSql = 'SELECT COUNT(*) FROM requisition_slips WHERE status IN (' . $pendingRisStatusPlaceholders . ')';
    $metrics['ris_pending'] = $scalar($db, $pendingRisSql, $pendingRisStatuses);
    $metrics['ris_overdue_7_days'] = $scalar(
        $db,
        'SELECT COUNT(*) FROM requisition_slips WHERE status IN (' . $pendingRisStatusPlaceholders . ') AND requisition_date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
        $pendingRisStatuses
    );
    $metrics['transfer_overdue_14_days'] = $scalar(
        $db,
        "SELECT COUNT(*) FROM property_transfers WHERE status = 'Pending' AND transfer_date <= DATE_SUB(CURDATE(), INTERVAL 14 DAY)"
    );

    if ($canAccessAuditLogs) {
        $auditCountParams = [];
        $auditCountScopeSql = $buildDashboardAuditScopeClause($dashboardAuditScope, $auditCountParams, true);
        $auditCountStmt = $db->prepare(
            'SELECT COUNT(*) FROM activity_logs al WHERE al.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)' . $auditCountScopeSql
        );
        $auditCountStmt->execute($auditCountParams);
        $metrics['audit_30_days'] = (int)$auditCountStmt->fetchColumn();

        $highRiskCountParams = [];
        $highRiskCountScopeSql = $buildDashboardAuditScopeClause($dashboardAuditScope, $highRiskCountParams, true);
        $highRiskCountStmt = $db->prepare(
            'SELECT COUNT(*) FROM activity_logs al WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND ' . $highRiskWhereSql . $highRiskCountScopeSql
        );
        $highRiskCountStmt->execute($highRiskCountParams);
        $metrics['high_risk_events_14_days'] = (int)$highRiskCountStmt->fetchColumn();
    }

    $issuedMonthCodeTotals = [];
    $issuanceStmt = $db->prepare(
        "SELECT
            DATE_FORMAT(si.issuance_date, '%Y-%m') AS month_key,
            COALESCE(NULLIF(TRIM(i.code), ''), 'N/A') AS item_code,
            SUM(sii.qty_issued) AS qty_total
         FROM supply_issuance_items sii
         INNER JOIN supply_issuances si ON si.id = sii.issuance_id
         INNER JOIN items i ON i.id = sii.item_id
         WHERE si.issuance_date BETWEEN ? AND ?
         GROUP BY month_key, item_code
         ORDER BY month_key ASC, qty_total DESC"
    );
    $issuanceStmt->execute([$issuedTrendRangeStartDate, $issuedTrendRangeEndDate]);
    foreach ($issuanceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $monthKey = (string)($row['month_key'] ?? '');
        if (!isset($issuedMonthToQuarterKey[$monthKey])) {
            continue;
        }

        $qtyTotal = (int)($row['qty_total'] ?? 0);
        if (isset($receivedMonthIndex[$monthKey])) {
            $issuedVolumeMonthSeries[$receivedMonthIndex[$monthKey]] += $qtyTotal;
        }

        $quarterKey = $issuedMonthToQuarterKey[$monthKey] ?? null;
        if ($quarterKey !== null && isset($receivedQuarterIndex[$quarterKey])) {
            $issuedVolumeQuarterSeries[$receivedQuarterIndex[$quarterKey]] += $qtyTotal;
        }

        $itemCode = trim((string)($row['item_code'] ?? ''));
        if ($itemCode === '') {
            $itemCode = 'N/A';
        }

        if (!isset($issuedMonthCodeTotals[$monthKey])) {
            $issuedMonthCodeTotals[$monthKey] = [];
        }
        if (!isset($issuedMonthCodeTotals[$monthKey][$itemCode])) {
            $issuedMonthCodeTotals[$monthKey][$itemCode] = 0;
        }
        $issuedMonthCodeTotals[$monthKey][$itemCode] += $qtyTotal;
    }

    $issuedQuarterCodeTotals = [];
    foreach ($issuedMonthCodeTotals as $monthKey => $codeTotals) {
        $quarterKey = $issuedMonthToQuarterKey[$monthKey] ?? null;
        if ($quarterKey === null) {
            continue;
        }

        if (!isset($issuedQuarterCodeTotals[$quarterKey])) {
            $issuedQuarterCodeTotals[$quarterKey] = [];
        }

        foreach ($codeTotals as $itemCode => $qtyTotal) {
            if (!isset($issuedQuarterCodeTotals[$quarterKey][$itemCode])) {
                $issuedQuarterCodeTotals[$quarterKey][$itemCode] = 0;
            }
            $issuedQuarterCodeTotals[$quarterKey][$itemCode] += (int)$qtyTotal;
        }
    }

    foreach ($issuedTrendMonthPeriods as $period) {
        $periodKey = (string)$period['key'];
        $issuedTrendPayload['month']['seriesByPeriod'][$periodKey] = $buildTopItemSeries(
            $issuedMonthCodeTotals[$periodKey] ?? [],
            $formatIssuedCode
        );
    }

    foreach ($issuedTrendQuarterPeriods as $period) {
        $periodKey = (string)$period['key'];
        $issuedTrendPayload['quarter']['seriesByPeriod'][$periodKey] = $buildTopItemSeries(
            $issuedQuarterCodeTotals[$periodKey] ?? [],
            $formatIssuedCode
        );
    }

    $receivingStmt = $db->prepare(
        "SELECT DATE_FORMAT(sr.receipt_date, '%Y-%m') AS month_key, SUM(sri.qty_received) AS qty_total
         FROM supply_receipt_items sri
         INNER JOIN supply_receipts sr ON sr.id = sri.receipt_header_id
         WHERE sr.receipt_date BETWEEN ? AND ?
         GROUP BY month_key
         ORDER BY month_key ASC"
    );
    $receivingStmt->execute([$issuedTrendRangeStartDate, $issuedTrendRangeEndDate]);
    foreach ($receivingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $monthKey = (string)($row['month_key'] ?? '');
        if (!isset($receivedMonthIndex[$monthKey])) {
            continue;
        }

        $qtyTotal = (int)($row['qty_total'] ?? 0);
        $monthPosition = $receivedMonthIndex[$monthKey];
        $receivedMonthSeries[$monthPosition] += $qtyTotal;

        $quarterKey = $issuedMonthToQuarterKey[$monthKey] ?? null;
        if ($quarterKey !== null && isset($receivedQuarterIndex[$quarterKey])) {
            $quarterPosition = $receivedQuarterIndex[$quarterKey];
            $receivedQuarterSeries[$quarterPosition] += $qtyTotal;
        }
    }

    $receivedTrendPayload['month']['values'] = $receivedMonthSeries;
    $receivedTrendPayload['quarter']['values'] = $receivedQuarterSeries;

    $supplyFlowTrendPayload['month']['received'] = $receivedMonthSeries;
    $supplyFlowTrendPayload['month']['issued'] = $issuedVolumeMonthSeries;
    $supplyFlowTrendPayload['month']['net'] = array_map(
        static function (int $received, int $issued): int {
            return $received - $issued;
        },
        $receivedMonthSeries,
        $issuedVolumeMonthSeries
    );

    $supplyFlowTrendPayload['quarter']['received'] = $receivedQuarterSeries;
    $supplyFlowTrendPayload['quarter']['issued'] = $issuedVolumeQuarterSeries;
    $supplyFlowTrendPayload['quarter']['net'] = array_map(
        static function (int $received, int $issued): int {
            return $received - $issued;
        },
        $receivedQuarterSeries,
        $issuedVolumeQuarterSeries
    );

    $risStatusStmt = $db->query(
        'SELECT status, COUNT(*) AS total_count FROM requisition_slips GROUP BY status'
    );
    foreach ($risStatusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (string)($row['status'] ?? 'Unknown');
        $count = (int)($row['total_count'] ?? 0);
        if (array_key_exists($status, $risStatusBuckets)) {
            $risStatusBuckets[$status] = $count;
        } else {
            $risOtherCount += $count;
        }
    }
    if ($risOtherCount > 0) {
        $risStatusBuckets['Other'] = $risOtherCount;
    }

    $reorderAlerts = StockInventory::getReorderList();
    $inventorySummary = StockInventory::getAllSummary();
    foreach ($inventorySummary as $row) {
        $currentBalance = (int)($row['current_balance'] ?? 0);
        $safetyStock = (int)($row['safety_stock'] ?? 0);
        $reorderLevel = (int)($row['reorder_level'] ?? 0);

        if ($safetyStock > 0 && $currentBalance <= $safetyStock) {
            $inventoryHealthBuckets['Critical']++;
        } elseif ($reorderLevel > 0 && $currentBalance <= $reorderLevel) {
            $inventoryHealthBuckets['Reorder']++;
        } else {
            $inventoryHealthBuckets['Healthy']++;
        }
    }
    $metrics['inventory_alerts'] = count($reorderAlerts);

    $toStatusMap = static function (PDO $dbConn, string $table): array {
        $result = [];
        $stmt = $dbConn->query("SELECT status, COUNT(*) AS total_count FROM {$table} GROUP BY status");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(string)($row['status'] ?? '')] = (int)($row['total_count'] ?? 0);
        }
        return $result;
    };

    $icsStatusCounts = $toStatusMap($db, 'inventory_custodian_slips');
    $parStatusCounts = $toStatusMap($db, 'property_acknowledgment_receipts');
    $transferStatusCounts = $toStatusMap($db, 'property_transfers');

    $icsOpen = (int)($icsStatusCounts['Active'] ?? 0);
    $icsClosed = array_sum($icsStatusCounts) - $icsOpen;

    $parOpen = (int)($parStatusCounts['Active'] ?? 0);
    $parClosed = array_sum($parStatusCounts) - $parOpen;

    $transferOpen = (int)($transferStatusCounts['Pending'] ?? 0);
    $transferClosed = array_sum($transferStatusCounts) - $transferOpen;

    $propertyOpenSeries = [$icsOpen, $parOpen, $transferOpen];
    $propertyClosedSeries = [$icsClosed, $parClosed, $transferClosed];
    $metrics['open_accountabilities'] = $icsOpen + $parOpen + $transferOpen;

    $departmentStmt = $db->query(
        "SELECT
            COALESCE(NULLIF(TRIM(d.code), ''), NULLIF(TRIM(d.name), ''), 'Unassigned') AS dept_label,
            COUNT(*) AS request_count
         FROM requisition_slips rs
         LEFT JOIN departments d ON d.id = rs.division
         GROUP BY dept_label
         ORDER BY request_count DESC
         LIMIT 7"
    );
    foreach ($departmentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $departmentDemandLabels[] = (string)$row['dept_label'];
        $departmentDemandSeries[] = (int)$row['request_count'];
    }

    $workflowAgingQueries = [
        [
            'label' => 'RIS Pending',
            'status_label' => 'Over 7d',
            'threshold_days' => 7,
            'href' => 'ris.php',
            'sql' => "SELECT
                        COUNT(*) AS open_count,
                        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), requisition_date) >= 7 THEN 1 ELSE 0 END), 0) AS over_target_count,
                        COALESCE(MAX(DATEDIFF(CURDATE(), requisition_date)), 0) AS oldest_days,
                        COALESCE(ROUND(AVG(DATEDIFF(CURDATE(), requisition_date))), 0) AS avg_days
                    FROM requisition_slips
                    WHERE status IN (" . $pendingRisStatusPlaceholders . ")",
            'params' => $pendingRisStatuses,
        ],
        [
            'label' => 'Transfers Pending',
            'status_label' => 'Over 14d',
            'threshold_days' => 14,
            'href' => 'property_transfers.php',
            'sql' => "SELECT
                        COUNT(*) AS open_count,
                        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), transfer_date) >= 14 THEN 1 ELSE 0 END), 0) AS over_target_count,
                        COALESCE(MAX(DATEDIFF(CURDATE(), transfer_date)), 0) AS oldest_days,
                        COALESCE(ROUND(AVG(DATEDIFF(CURDATE(), transfer_date))), 0) AS avg_days
                    FROM property_transfers
                    WHERE status = 'Pending'",
            'params' => [],
        ],
        [
            'label' => 'ICS Active',
            'status_label' => 'Over 180d',
            'threshold_days' => 180,
            'href' => 'ics.php',
            'sql' => "SELECT
                        COUNT(*) AS open_count,
                        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), issued_date) >= 180 THEN 1 ELSE 0 END), 0) AS over_target_count,
                        COALESCE(MAX(DATEDIFF(CURDATE(), issued_date)), 0) AS oldest_days,
                        COALESCE(ROUND(AVG(DATEDIFF(CURDATE(), issued_date))), 0) AS avg_days
                    FROM inventory_custodian_slips
                    WHERE status = 'Active'",
            'params' => [],
        ],
        [
            'label' => 'PAR Active',
            'status_label' => 'Over 180d',
            'threshold_days' => 180,
            'href' => 'par.php',
            'sql' => "SELECT
                        COUNT(*) AS open_count,
                        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), issue_date) >= 180 THEN 1 ELSE 0 END), 0) AS over_target_count,
                        COALESCE(MAX(DATEDIFF(CURDATE(), issue_date)), 0) AS oldest_days,
                        COALESCE(ROUND(AVG(DATEDIFF(CURDATE(), issue_date))), 0) AS avg_days
                    FROM property_acknowledgment_receipts
                    WHERE status = 'Active'",
            'params' => [],
        ],
    ];

    foreach ($workflowAgingQueries as $workflowQuery) {
        $stmt = $db->prepare($workflowQuery['sql']);
        $stmt->execute($workflowQuery['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $workflowAgingRows[] = [
            'label' => (string)$workflowQuery['label'],
            'status_label' => (string)$workflowQuery['status_label'],
            'threshold_days' => (int)$workflowQuery['threshold_days'],
            'href' => (string)$workflowQuery['href'],
            'open_count' => (int)($row['open_count'] ?? 0),
            'over_target_count' => (int)($row['over_target_count'] ?? 0),
            'oldest_days' => (int)($row['oldest_days'] ?? 0),
            'avg_days' => (int)($row['avg_days'] ?? 0),
        ];
    }

    $pendingRisAgingStmt = $db->prepare(
        "SELECT
            rs.ris_no,
            rs.status,
            rs.requisition_date,
            COALESCE(NULLIF(TRIM(d.code), ''), NULLIF(TRIM(d.name), ''), 'Unassigned') AS dept_label,
            COALESCE(DATEDIFF(CURDATE(), rs.requisition_date), 0) AS age_days
         FROM requisition_slips rs
         LEFT JOIN departments d ON d.id = rs.division
         WHERE rs.status IN (" . $pendingRisStatusPlaceholders . ")
         ORDER BY age_days DESC, rs.requisition_date ASC
         LIMIT 6"
    );
    $pendingRisAgingStmt->execute($pendingRisStatuses);
    $pendingRisAgingRows = $pendingRisAgingStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($canAccessAuditLogs) {
        $highRiskParams = [];
        $highRiskScopeSql = $buildDashboardAuditScopeClause($dashboardAuditScope, $highRiskParams, true);
        $riskEventStmt = $db->prepare(
            "SELECT
                al.created_at,
                al.action,
                al.entity,
                TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, ''))) AS actor_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
               AND {$highRiskWhereSql}
               {$highRiskScopeSql}
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT 8"
        );
        $riskEventStmt->execute($highRiskParams);
        foreach ($riskEventStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $riskEventRows[] = [
                'created_at_label' => $formatTimestampLabel((string)($row['created_at'] ?? '')),
                'action' => $formatAuditActionLabel((string)($row['action'] ?? '')),
                'entity' => trim((string)($row['entity'] ?? '')) !== '' ? (string)$row['entity'] : 'N/A',
                'actor_name' => trim((string)($row['actor_name'] ?? '')) !== '' ? (string)$row['actor_name'] : 'System',
            ];
        }
    }

    $departmentConsumptionTotals = [];
    $departmentConsumptionTopItems = [];
    $departmentConsumptionStmt = $db->prepare(
        "SELECT
            COALESCE(NULLIF(TRIM(d.code), ''), NULLIF(TRIM(d.name), ''), 'Unassigned') AS dept_label,
            COALESCE(NULLIF(TRIM(i.code), ''), CONCAT('ITEM-', i.id), 'N/A') AS item_code,
            COALESCE(NULLIF(TRIM(i.description), ''), 'No Description') AS item_description,
            SUM(sii.qty_issued) AS qty_issued_total
         FROM supply_issuance_items sii
         INNER JOIN supply_issuances si ON si.id = sii.issuance_id
         INNER JOIN requisition_slips rs ON rs.id = si.ris_id
         LEFT JOIN departments d ON d.id = rs.division
         INNER JOIN items i ON i.id = sii.item_id
         WHERE si.issuance_date BETWEEN ? AND ?
         GROUP BY dept_label, item_code, item_description
         ORDER BY dept_label ASC, qty_issued_total DESC, item_code ASC"
    );
    $departmentConsumptionStmt->execute([$issuedTrendRangeStartDate, $issuedTrendRangeEndDate]);
    foreach ($departmentConsumptionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $departmentLabel = (string)($row['dept_label'] ?? 'Unassigned');
        $issuedQty = (int)($row['qty_issued_total'] ?? 0);

        if (!isset($departmentConsumptionTotals[$departmentLabel])) {
            $departmentConsumptionTotals[$departmentLabel] = 0;
        }
        $departmentConsumptionTotals[$departmentLabel] += $issuedQty;

        if (!isset($departmentConsumptionTopItems[$departmentLabel])) {
            $departmentConsumptionTopItems[$departmentLabel] = [];
        }
        if (count($departmentConsumptionTopItems[$departmentLabel]) >= 3) {
            continue;
        }

        $departmentConsumptionTopItems[$departmentLabel][] = [
            'code' => (string)($row['item_code'] ?? 'N/A'),
            'description' => (string)($row['item_description'] ?? 'No Description'),
            'qty_issued_total' => $issuedQty,
        ];
    }

    if (!empty($departmentConsumptionTopItems)) {
        $departmentLabels = array_keys($departmentConsumptionTopItems);
        usort($departmentLabels, static function (string $left, string $right) use ($departmentConsumptionTotals): int {
            $leftTotal = (int)($departmentConsumptionTotals[$left] ?? 0);
            $rightTotal = (int)($departmentConsumptionTotals[$right] ?? 0);

            if ($leftTotal === $rightTotal) {
                return strcmp($left, $right);
            }
            return $rightTotal <=> $leftTotal;
        });

        foreach ($departmentLabels as $departmentLabel) {
            $departmentTopConsumptionRows[] = [
                'department_label' => $departmentLabel,
                'total_issued' => (int)($departmentConsumptionTotals[$departmentLabel] ?? 0),
                'items' => $departmentConsumptionTopItems[$departmentLabel],
            ];
        }

        $departmentTopConsumptionRows = array_slice($departmentTopConsumptionRows, 0, 8);
    }
} catch (Throwable $e) {
    error_log('administrator dashboard metrics failed: ' . $e->getMessage());
}

$alertsToDisplay = array_slice($reorderAlerts, 0, 6);

if (empty($departmentDemandLabels)) {
    $departmentDemandLabels = ['No Data'];
    $departmentDemandSeries = [0];
}

$quickLinks = [
    ['label' => 'Users', 'icon' => 'ph-users', 'href' => 'users.php'],
    ['label' => 'Suppliers', 'icon' => 'ph-truck', 'href' => 'suppliers.php'],
    ['label' => 'RIS', 'icon' => 'ph-note-pencil', 'href' => 'ris.php'],
    ['label' => 'Inventory Balance', 'icon' => 'ph-archive-box', 'href' => 'inventory_balance.php'],
    ['label' => 'Property Transfers', 'icon' => 'ph-arrows-left-right', 'href' => 'property_transfers.php'],
];
if ($canAccessAuditLogs) {
    $quickLinks[] = ['label' => 'Audit Logs', 'icon' => 'ph-activity', 'href' => 'audit_logs.php'];
}

?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div>
                <h4 class="mb-4 text-gray-900">
                    Welcome back, <?= htmlspecialchars((string)(getUser('fullname') ?? 'Administrator'), ENT_QUOTES, 'UTF-8') ?>!
                </h4>
                <p class="text-gray-500 mb-0 text-14">
                    <?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?> centralized administration and oversight dashboard
                    <?php if ($fiscalYearLabel !== ''): ?>
                        <span class="text-gray-500"> &middot; Fiscal Year <?= htmlspecialchars($fiscalYearLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Registered Users</p>
                        <h4 class="mb-0 text-gray-900"><?= number_format($metrics['users_total']) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Active Accounts</p>
                        <h4 class="mb-0 text-success-700"><?= number_format($metrics['users_active']) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Pending RIS</p>
                        <h4 class="mb-0 text-warning-700"><?= number_format($metrics['ris_pending']) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Inventory Alerts</p>
                        <h4 class="mb-0 text-danger-700"><?= number_format($metrics['inventory_alerts']) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Open Accountabilities</p>
                        <h4 class="mb-0 text-main-700"><?= number_format($metrics['open_accountabilities']) ?></h4>
                    </div>
                </div>
            </div>
            <?php if ($canAccessAuditLogs): ?>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-12 text-gray-500 mb-6">Audit Events (30d)</p>
                            <h4 class="mb-0 text-info-700"><?= number_format($metrics['audit_30_days']) ?></h4>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">RIS Beyond 7 Days</p>
                        <h4 class="mb-6 <?= $metrics['ris_overdue_7_days'] > 0 ? 'text-danger-700' : 'text-success-700' ?>">
                            <?= number_format($metrics['ris_overdue_7_days']) ?>
                        </h4>
                        <p class="text-gray-500 text-12 mb-12">Pending requisitions requiring escalation follow-up.</p>
                        <a href="ris.php" class="btn btn-sm btn-outline-primary">Review RIS Queue</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-12 text-gray-500 mb-6">Transfers Beyond 14 Days</p>
                        <h4 class="mb-6 <?= $metrics['transfer_overdue_14_days'] > 0 ? 'text-danger-700' : 'text-success-700' ?>">
                            <?= number_format($metrics['transfer_overdue_14_days']) ?>
                        </h4>
                        <p class="text-gray-500 text-12 mb-12">Pending transfers that may delay accountability updates.</p>
                        <a href="property_transfers.php" class="btn btn-sm btn-outline-primary">Review Transfers</a>
                    </div>
                </div>
            </div>
            <?php if ($canAccessAuditLogs): ?>
                <div class="col-lg-4 col-md-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-12 text-gray-500 mb-6">High-Risk Events (14d)</p>
                            <h4 class="mb-6 <?= $metrics['high_risk_events_14_days'] > 0 ? 'text-warning-700' : 'text-success-700' ?>">
                                <?= number_format($metrics['high_risk_events_14_days']) ?>
                            </h4>
                            <p class="text-gray-500 text-12 mb-12">Failed, denied, delete, and cancel actions for review.</p>
                            <a href="audit_logs.php" class="btn btn-sm btn-outline-primary">Inspect Audit Logs</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <div>
                                <h5 class="mb-0 text-gray-900">Issued Supplies Trend</h5>
                                <p id="issuedTrendSubtext" class="text-gray-500 text-12 mb-0">Top 10 item codes in current fiscal year</p>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-8">
                                <select id="issuedTrendMode" class="form-select form-select-sm" style="min-width: 130px;">
                                    <option value="month">By Month</option>
                                    <option value="quarter">By Quarter</option>
                                </select>
                                <select id="issuedTrendPeriod" class="form-select form-select-sm" style="min-width: 200px;"></select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="issuedTrendChart" style="min-height: 290px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <div>
                                <h5 class="mb-0 text-gray-900">Received Supplies Trend</h5>
                                <p id="receivedTrendSubtext" class="text-gray-500 text-12 mb-0">Total received quantities in current fiscal year (By Month)</p>
                            </div>
                            <select id="receivedTrendMode" class="form-select form-select-sm" style="min-width: 130px;">
                                <option value="month">By Month</option>
                                <option value="quarter">By Quarter</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="receivedTrendChart" style="min-height: 290px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <div>
                                <h5 class="mb-0 text-gray-900">Supply Flow Balance</h5>
                                <p id="supplyFlowSubtext" class="text-gray-500 text-12 mb-0">Received vs issued quantities in current fiscal year (By Month)</p>
                            </div>
                            <select id="supplyFlowMode" class="form-select form-select-sm" style="min-width: 130px;">
                                <option value="month">By Month</option>
                                <option value="quarter">By Quarter</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="supplyFlowChart" style="min-height: 290px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">RIS Status Mix</h5>
                        <p class="text-gray-500 text-12 mb-0">Distribution of requisitions by processing status.</p>
                    </div>
                    <div class="card-body">
                        <div id="risStatusChart" style="min-height: 260px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Inventory Health</h5>
                        <p class="text-gray-500 text-12 mb-0">Items grouped as healthy, reorder, or critical stock.</p>
                    </div>
                    <div class="card-body">
                        <div id="inventoryHealthChart" style="min-height: 260px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Property Accountability</h5>
                        <p class="text-gray-500 text-12 mb-0">Open versus closed records for ICS, PAR, and transfers.</p>
                    </div>
                    <div class="card-body">
                        <div id="propertyStatusChart" style="min-height: 260px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Department Demand</h5>
                        <p class="text-gray-500 text-12 mb-0">Top departments by number of submitted RIS requests.</p>
                    </div>
                    <div class="card-body">
                        <div id="departmentDemandChart" style="min-height: 260px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-24 mb-24">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <div>
                                <h5 class="mb-0 text-gray-900">Workflow Aging Monitor</h5>
                                <p class="text-gray-500 text-12 mb-0">Open workload and items beyond target cycle time</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($workflowAgingRows)): ?>
                            <div class="p-20">
                                <p class="text-gray-500 mb-0">No workflow aging data available.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th class="text-13 text-gray-300">Workflow</th>
                                            <th class="text-13 text-gray-300 text-center">Open</th>
                                            <th class="text-13 text-gray-300 text-center">Beyond Target</th>
                                            <th class="text-13 text-gray-300 text-center">Oldest</th>
                                            <th class="text-13 text-gray-300 text-center">Avg Age</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($workflowAgingRows as $workflow): ?>
                                            <?php
                                            $openCount = (int)($workflow['open_count'] ?? 0);
                                            $overTargetCount = (int)($workflow['over_target_count'] ?? 0);
                                            $oldestDays = (int)($workflow['oldest_days'] ?? 0);
                                            $avgDays = (int)($workflow['avg_days'] ?? 0);
                                            $statusClass = $overTargetCount > 0 ? 'bg-danger' : ($openCount > 0 ? 'bg-warning text-dark' : 'bg-success');
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-gray-900 fw-medium text-13"><?= htmlspecialchars((string)($workflow['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                        <a href="<?= htmlspecialchars((string)($workflow['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="text-12 text-main-600">Open module</a>
                                                    </div>
                                                </td>
                                                <td class="text-center fw-semibold"><?= number_format($openCount) ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?= $statusClass ?>">
                                                        <?= number_format($overTargetCount) ?> <?= htmlspecialchars((string)($workflow['status_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td class="text-center text-gray-900"><?= number_format($oldestDays) ?>d</td>
                                                <td class="text-center text-gray-900"><?= number_format($avgDays) ?>d</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <div>
                                <h5 class="mb-0 text-gray-900">Oldest Pending RIS</h5>
                                <p class="text-gray-500 text-12 mb-0">Queue visibility for delayed requisitions</p>
                            </div>
                            <a href="ris.php" class="btn btn-sm btn-outline-primary">Open RIS</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pendingRisAgingRows)): ?>
                            <div class="p-20">
                                <p class="text-gray-500 mb-0">No pending RIS records.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th class="text-13 text-gray-300">RIS No.</th>
                                            <th class="text-13 text-gray-300">Dept</th>
                                            <th class="text-13 text-gray-300 text-center">Age</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingRisAgingRows as $row): ?>
                                            <?php $ageDays = (int)($row['age_days'] ?? 0); ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-gray-900 fw-medium text-13"><?= htmlspecialchars((string)($row['ris_no'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="text-gray-500 text-12"><?= htmlspecialchars((string)($row['status'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?> &middot; <?= DisplayDate((string)($row['requisition_date'] ?? '')) ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-gray-900 text-13"><?= htmlspecialchars((string)($row['dept_label'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?= $ageDays >= 7 ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= number_format($ageDays) ?>d</span>
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

        <div class="row g-24 mb-24">
            <?php if ($canAccessAuditLogs): ?>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header border-bottom border-gray-100">
                            <div class="flex-between flex-wrap gap-8">
                                <div>
                                    <h5 class="mb-0 text-gray-900">Recent High-Risk Events</h5>
                                    <p class="text-gray-500 text-12 mb-0">Last 14 days of failed, denied, delete, and cancel actions</p>
                                </div>
                                <a href="audit_logs.php" class="btn btn-sm btn-outline-primary">Open Audit Logs</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($riskEventRows)): ?>
                                <div class="p-20">
                                    <p class="text-gray-500 mb-0">No high-risk events recorded in the last 14 days.</p>
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
                                            <?php foreach ($riskEventRows as $event): ?>
                                                <tr>
                                                    <td class="text-gray-900 text-13"><?= htmlspecialchars((string)($event['created_at_label'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-gray-900 text-13"><?= htmlspecialchars((string)($event['action'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-gray-900 text-13"><?= htmlspecialchars((string)($event['entity'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-gray-900 text-13"><?= htmlspecialchars((string)($event['actor_name'] ?? 'System'), ENT_QUOTES, 'UTF-8') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="<?= $consumptionColumnClass ?>">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <div>
                                <h5 class="mb-0 text-gray-900">Top Consumed Items by Department</h5>
                                <p class="text-gray-500 text-12 mb-0">Top 3 issued items per department in the current fiscal period</p>
                            </div>
                            <a href="supply_issuance.php" class="btn btn-sm btn-outline-primary">Open Issuances</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($departmentTopConsumptionRows)): ?>
                            <div class="p-20">
                                <p class="text-gray-500 mb-0">No issued item consumption data available for departments.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th class="text-13 text-gray-300">Department</th>
                                            <th class="text-13 text-gray-300">Top 3 Items</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departmentTopConsumptionRows as $departmentRow): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-gray-900 fw-medium text-13"><?= htmlspecialchars((string)($departmentRow['department_label'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="text-gray-500 text-12">Total issued: <?= number_format((int)($departmentRow['total_issued'] ?? 0)) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php foreach ((array)($departmentRow['items'] ?? []) as $index => $itemRow): ?>
                                                        <div class="d-flex align-items-start justify-content-between gap-8">
                                                            <div class="text-13 text-gray-900">
                                                                <?= (int)$index + 1 ?>.
                                                                <?= htmlspecialchars((string)($itemRow['code'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?>
                                                                <span class="text-gray-500">
                                                                    - <?= htmlspecialchars((string)($itemRow['description'] ?? 'No Description'), ENT_QUOTES, 'UTF-8') ?>
                                                                </span>
                                                            </div>
                                                            <span class="text-13 fw-semibold text-gray-900"><?= number_format((int)($itemRow['qty_issued_total'] ?? 0)) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
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
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <div class="flex-between flex-wrap gap-8">
                            <h5 class="mb-0 text-gray-900">Low Stock Watchlist</h5>
                            <a href="reorder_report.php" class="btn btn-sm btn-outline-primary">Open Reorder Report</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($alertsToDisplay)): ?>
                            <div class="p-20">
                                <p class="text-gray-500 mb-0">No low-stock items detected based on reorder and safety thresholds.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-13 text-gray-300">Item</th>
                                            <th class="text-13 text-gray-300 text-center">Current</th>
                                            <th class="text-13 text-gray-300 text-center">Reorder</th>
                                            <th class="text-13 text-gray-300 text-center">Safety</th>
                                            <th class="text-13 text-gray-300 text-center">Unit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alertsToDisplay as $alert): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-gray-900 fw-medium text-13"><?= htmlspecialchars((string)($alert['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="text-gray-500 text-12"><?= htmlspecialchars((string)($alert['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-center text-danger fw-semibold"><?= (int)($alert['current_balance'] ?? 0) ?></td>
                                                <td class="text-center"><?= (int)($alert['reorder_level'] ?? 0) ?></td>
                                                <td class="text-center"><?= (int)($alert['safety_stock'] ?? 0) ?></td>
                                                <td class="text-center"><?= htmlspecialchars((string)($alert['unit'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
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
                        <h5 class="mb-0 text-gray-900">Module Shortcuts</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-12">
                            <?php foreach ($quickLinks as $entry): ?>
                                <div class="col-12">
                                    <a href="<?= htmlspecialchars($entry['href'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-8">
                                        <i class="ph <?= htmlspecialchars($entry['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                        <span><?= htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16">
            <p class="text-gray-300 text-13 fw-normal">&copy; <?= date('Y') ?> <?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
            <div class="flex-align flex-wrap gap-16"></div>
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

        const issuedTrendData = <?= json_encode($issuedTrendPayload) ?>;
        const receivedTrendData = <?= json_encode($receivedTrendPayload) ?>;
        const supplyFlowTrendData = <?= json_encode($supplyFlowTrendPayload) ?>;

        const risStatusLabels = <?= json_encode(array_keys($risStatusBuckets)) ?>;
        const risStatusSeries = <?= json_encode(array_values($risStatusBuckets)) ?>;

        const inventoryHealthLabels = <?= json_encode(array_keys($inventoryHealthBuckets)) ?>;
        const inventoryHealthSeries = <?= json_encode(array_values($inventoryHealthBuckets)) ?>;

        const propertyLabels = <?= json_encode($propertyLabels) ?>;
        const propertyOpenSeries = <?= json_encode($propertyOpenSeries) ?>;
        const propertyClosedSeries = <?= json_encode($propertyClosedSeries) ?>;

        const deptLabels = <?= json_encode($departmentDemandLabels) ?>;
        const deptSeries = <?= json_encode($departmentDemandSeries) ?>;

        const commonGrid = { borderColor: '#e2e8f0', strokeDashArray: 4 };

        const renderChart = (selector, options) => {
            const el = document.querySelector(selector);
            if (!el) {
                return;
            }
            const chart = new ApexCharts(el, options);
            chart.render();
        };

        const issuedModeSelect = document.getElementById('issuedTrendMode');
        const issuedPeriodSelect = document.getElementById('issuedTrendPeriod');
        const issuedSubtitle = document.getElementById('issuedTrendSubtext');
        const fallbackIssuedSeries = { labels: ['No Data'], values: [0], codes: [''] };
        let issuedTrendChart = null;

        const readIssuedModeData = (mode) => {
            if (!issuedTrendData || typeof issuedTrendData !== 'object') {
                return { periods: [], defaultPeriodKey: '', seriesByPeriod: {} };
            }
            return issuedTrendData[mode] || { periods: [], defaultPeriodKey: '', seriesByPeriod: {} };
        };

        const populateIssuedPeriodOptions = () => {
            if (!issuedModeSelect || !issuedPeriodSelect) {
                return;
            }

            const mode = issuedModeSelect.value === 'quarter' ? 'quarter' : 'month';
            const modeData = readIssuedModeData(mode);
            const periods = Array.isArray(modeData.periods) ? modeData.periods : [];

            issuedPeriodSelect.innerHTML = '';

            if (periods.length === 0) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Current Fiscal Year';
                issuedPeriodSelect.appendChild(option);
                return;
            }

            periods.forEach((period) => {
                const option = document.createElement('option');
                option.value = period.key;
                option.textContent = period.label;
                issuedPeriodSelect.appendChild(option);
            });

            const hasDefault = periods.some((period) => period.key === modeData.defaultPeriodKey);
            if (hasDefault) {
                issuedPeriodSelect.value = modeData.defaultPeriodKey;
            } else {
                issuedPeriodSelect.value = periods[periods.length - 1].key;
            }
        };

        const readIssuedSelection = () => {
            const mode = issuedModeSelect && issuedModeSelect.value === 'quarter' ? 'quarter' : 'month';
            const modeData = readIssuedModeData(mode);
            const periods = Array.isArray(modeData.periods) ? modeData.periods : [];

            const periodKey = issuedPeriodSelect ? issuedPeriodSelect.value : '';
            const periodMeta = periods.find((period) => period.key === periodKey) || null;
            const payload = modeData.seriesByPeriod && modeData.seriesByPeriod[periodKey]
                ? modeData.seriesByPeriod[periodKey]
                : fallbackIssuedSeries;

            const labels = Array.isArray(payload.labels) && payload.labels.length > 0
                ? payload.labels
                : fallbackIssuedSeries.labels;
            const values = Array.isArray(payload.values) && payload.values.length > 0
                ? payload.values
                : fallbackIssuedSeries.values;

            return {
                mode,
                periodLabel: periodMeta ? periodMeta.label : '',
                labels,
                values
            };
        };

        const renderIssuedTrendChart = () => {
            const chartElement = document.querySelector('#issuedTrendChart');
            if (!chartElement) {
                return;
            }

            const selected = readIssuedSelection();
            const viewLabel = selected.mode === 'quarter' ? 'By Quarter' : 'By Month';

            if (issuedSubtitle) {
                issuedSubtitle.textContent = selected.periodLabel !== ''
                    ? `Top 10 item codes (${viewLabel}: ${selected.periodLabel})`
                    : `Top 10 item codes (${viewLabel})`;
            }

            const options = {
                chart: { type: 'bar', height: 290, toolbar: { show: false }, foreColor: '#475569' },
                series: [{ name: 'Issued Qty', data: selected.values }],
                xaxis: {
                    categories: selected.labels,
                    labels: { rotate: -35 }
                },
                yaxis: { min: 0, forceNiceScale: true },
                dataLabels: { enabled: false },
                plotOptions: { bar: { borderRadius: 6, columnWidth: '58%' } },
                colors: ['#2563eb'],
                grid: commonGrid
            };

            if (issuedTrendChart === null) {
                issuedTrendChart = new ApexCharts(chartElement, options);
                issuedTrendChart.render();
                return;
            }

            issuedTrendChart.updateOptions({
                xaxis: options.xaxis,
                yaxis: options.yaxis,
                plotOptions: options.plotOptions,
                colors: options.colors,
                grid: options.grid
            }, false, true);
            issuedTrendChart.updateSeries(options.series, true);
        };

        if (issuedModeSelect && issuedPeriodSelect) {
            populateIssuedPeriodOptions();

            issuedModeSelect.addEventListener('change', () => {
                populateIssuedPeriodOptions();
                renderIssuedTrendChart();
            });
            issuedPeriodSelect.addEventListener('change', renderIssuedTrendChart);
        }

        renderIssuedTrendChart();

        const receivedModeSelect = document.getElementById('receivedTrendMode');
        const receivedSubtitle = document.getElementById('receivedTrendSubtext');
        const fallbackReceivedSeries = { categories: ['No Data'], values: [0] };
        let receivedTrendChart = null;

        const readReceivedModeData = (mode) => {
            if (!receivedTrendData || typeof receivedTrendData !== 'object') {
                return fallbackReceivedSeries;
            }
            return receivedTrendData[mode] || fallbackReceivedSeries;
        };

        const readReceivedSelection = () => {
            const mode = receivedModeSelect && receivedModeSelect.value === 'quarter' ? 'quarter' : 'month';
            const payload = readReceivedModeData(mode);

            const categories = Array.isArray(payload.categories) && payload.categories.length > 0
                ? payload.categories
                : fallbackReceivedSeries.categories;
            const values = Array.isArray(payload.values) && payload.values.length > 0
                ? payload.values
                : fallbackReceivedSeries.values;

            return { mode, categories, values };
        };

        const renderReceivedTrendChart = () => {
            const chartElement = document.querySelector('#receivedTrendChart');
            if (!chartElement) {
                return;
            }

            const selected = readReceivedSelection();
            const viewLabel = selected.mode === 'quarter' ? 'By Quarter' : 'By Month';

            if (receivedSubtitle) {
                receivedSubtitle.textContent = `Total received quantities in current fiscal year (${viewLabel})`;
            }

            const options = {
                chart: { type: 'bar', height: 290, toolbar: { show: false }, foreColor: '#475569' },
                series: [{ name: 'Received Qty', data: selected.values }],
                xaxis: { categories: selected.categories, labels: { rotate: -35 } },
                yaxis: { min: 0, forceNiceScale: true },
                dataLabels: { enabled: false },
                plotOptions: { bar: { borderRadius: 6, columnWidth: '52%' } },
                colors: ['#0ea5e9'],
                grid: commonGrid
            };

            if (receivedTrendChart === null) {
                receivedTrendChart = new ApexCharts(chartElement, options);
                receivedTrendChart.render();
                return;
            }

            receivedTrendChart.updateOptions({
                xaxis: options.xaxis,
                yaxis: options.yaxis,
                plotOptions: options.plotOptions,
                colors: options.colors,
                grid: options.grid
            }, false, true);
            receivedTrendChart.updateSeries(options.series, true);
        };

        if (receivedModeSelect) {
            receivedModeSelect.value = receivedTrendData && receivedTrendData.defaultMode === 'quarter' ? 'quarter' : 'month';
            receivedModeSelect.addEventListener('change', renderReceivedTrendChart);
        }

        renderReceivedTrendChart();

        const supplyFlowModeSelect = document.getElementById('supplyFlowMode');
        const supplyFlowSubtitle = document.getElementById('supplyFlowSubtext');
        const fallbackSupplyFlow = {
            categories: ['No Data'],
            received: [0],
            issued: [0],
            net: [0]
        };
        let supplyFlowChart = null;

        const readSupplyFlowModeData = (mode) => {
            if (!supplyFlowTrendData || typeof supplyFlowTrendData !== 'object') {
                return fallbackSupplyFlow;
            }
            return supplyFlowTrendData[mode] || fallbackSupplyFlow;
        };

        const readSupplyFlowSelection = () => {
            const mode = supplyFlowModeSelect && supplyFlowModeSelect.value === 'quarter' ? 'quarter' : 'month';
            const payload = readSupplyFlowModeData(mode);

            const categories = Array.isArray(payload.categories) && payload.categories.length > 0
                ? payload.categories
                : fallbackSupplyFlow.categories;
            const received = Array.isArray(payload.received) && payload.received.length > 0
                ? payload.received
                : fallbackSupplyFlow.received;
            const issued = Array.isArray(payload.issued) && payload.issued.length > 0
                ? payload.issued
                : fallbackSupplyFlow.issued;
            const net = Array.isArray(payload.net) && payload.net.length > 0
                ? payload.net
                : fallbackSupplyFlow.net;

            return { mode, categories, received, issued, net };
        };

        const renderSupplyFlowChart = () => {
            const chartElement = document.querySelector('#supplyFlowChart');
            if (!chartElement) {
                return;
            }

            const selected = readSupplyFlowSelection();
            const viewLabel = selected.mode === 'quarter' ? 'By Quarter' : 'By Month';

            if (supplyFlowSubtitle) {
                supplyFlowSubtitle.textContent = `Received vs issued quantities in current fiscal year (${viewLabel})`;
            }

            const options = {
                chart: { type: 'line', height: 290, toolbar: { show: false }, foreColor: '#475569' },
                series: [
                    { name: 'Received Qty', type: 'column', data: selected.received },
                    { name: 'Issued Qty', type: 'column', data: selected.issued },
                    { name: 'Net Flow', type: 'line', data: selected.net }
                ],
                xaxis: { categories: selected.categories, labels: { rotate: -35 } },
                yaxis: { min: 0, forceNiceScale: true },
                dataLabels: { enabled: false },
                stroke: { width: [0, 0, 3], curve: 'straight' },
                markers: { size: [0, 0, 4] },
                plotOptions: { bar: { borderRadius: 5, columnWidth: '50%' } },
                colors: ['#0ea5e9', '#2563eb', '#f59e0b'],
                tooltip: { shared: true, intersect: false },
                legend: { position: 'top', horizontalAlign: 'left' },
                grid: commonGrid
            };

            if (supplyFlowChart === null) {
                supplyFlowChart = new ApexCharts(chartElement, options);
                supplyFlowChart.render();
                return;
            }

            supplyFlowChart.updateOptions({
                xaxis: options.xaxis,
                yaxis: options.yaxis,
                stroke: options.stroke,
                markers: options.markers,
                plotOptions: options.plotOptions,
                colors: options.colors,
                tooltip: options.tooltip,
                legend: options.legend,
                grid: options.grid
            }, false, true);
            supplyFlowChart.updateSeries(options.series, true);
        };

        if (supplyFlowModeSelect) {
            supplyFlowModeSelect.value = supplyFlowTrendData && supplyFlowTrendData.defaultMode === 'quarter' ? 'quarter' : 'month';
            supplyFlowModeSelect.addEventListener('change', renderSupplyFlowChart);
        }

        renderSupplyFlowChart();

        renderChart('#risStatusChart', {
            chart: { type: 'donut', height: 260, foreColor: '#475569' },
            labels: risStatusLabels,
            series: risStatusSeries,
            legend: { position: 'bottom' },
            colors: ['#0ea5e9', '#16a34a', '#f59e0b', '#2563eb', '#64748b', '#ef4444']
        });

        renderChart('#inventoryHealthChart', {
            chart: { type: 'donut', height: 260, foreColor: '#475569' },
            labels: inventoryHealthLabels,
            series: inventoryHealthSeries,
            legend: { position: 'bottom' },
            colors: ['#16a34a', '#f59e0b', '#dc2626']
        });

        renderChart('#propertyStatusChart', {
            chart: { type: 'bar', stacked: true, height: 260, toolbar: { show: false }, foreColor: '#475569' },
            series: [
                { name: 'Open', data: propertyOpenSeries },
                { name: 'Closed/Transferred', data: propertyClosedSeries }
            ],
            xaxis: { categories: propertyLabels },
            yaxis: { min: 0, forceNiceScale: true },
            dataLabels: { enabled: false },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '48%' } },
            colors: ['#f59e0b', '#2563eb'],
            grid: commonGrid
        });

        renderChart('#departmentDemandChart', {
            chart: { type: 'bar', height: 260, toolbar: { show: false }, foreColor: '#475569' },
            series: [{ name: 'Requests', data: deptSeries }],
            xaxis: { categories: deptLabels },
            yaxis: { min: 0, forceNiceScale: true },
            dataLabels: { enabled: false },
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '58%' } },
            colors: ['#7c3aed'],
            grid: commonGrid
        });
    })();
</script>
