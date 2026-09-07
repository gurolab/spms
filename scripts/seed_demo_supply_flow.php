<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/Supply_Issuance.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/SupplyLedger.model.php';
require_once __DIR__ . '/../repo/AuditLog.model.php';

date_default_timezone_set('Asia/Manila');

const DEFAULT_SEED = 20260310;
const DEFAULT_TAG = 'DEMO-RIS-20260310';

function out(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function printUsage(): void
{
    out('Usage: php scripts/seed_demo_supply_flow.php [--execute] [--seed=20260310] [--tag=DEMO-RIS-20260310] [--department-limit=19]');
    out('');
    out('Flags:');
    out('  --execute            Apply changes to the database (required for write mode).');
    out('  --seed=INT           Deterministic random seed. Default: ' . DEFAULT_SEED);
    out('  --tag=STRING         Seed marker saved in audit metadata.');
    out('  --department-limit=N Optional cap on departments to seed (debug/demo use).');
    out('  --help               Show this help text.');
}

function randElement(array $values)
{
    return $values[array_rand($values)];
}

function clampDate(DateTimeImmutable $date, DateTimeImmutable $max): DateTimeImmutable
{
    if ($date > $max) {
        return $max;
    }
    return $date;
}

function purposeForDepartment(string $deptCode, string $deptName): string
{
    $deptLabel = $deptName !== '' ? $deptName : $deptCode;
    $deptCode = strtoupper(trim($deptCode));

    $purposeMap = [
        'ICTO' => 'Monthly support supplies for ICT operations, hardware servicing, and laboratory troubleshooting tasks.',
        'CLINIC' => 'Replenishment of sanitation and emergency-use consumables to sustain daily clinic operations.',
        'PO' => 'Operational supplies for procurement documentation, canvassing packets, and supplier coordination.',
        'SPMO' => 'Warehouse and frontline service supplies for receiving, recording, and issuing inventory transactions.',
        'ACCOUNTING' => 'Supplies for voucher routing, reconciliation folders, and accounting records maintenance.',
        'BUDGET' => 'Supplies for budget proposal compilation, monitoring reports, and supporting document filing.',
        'CASHIER' => 'Counter and records supplies for cashiering transactions and daily cash report preparation.',
        'REGISTRAR' => 'Enrollment and records processing supplies for registration forms and student document control.',
        'LIBRARY' => 'Circulation desk and records organization supplies for daily library transactions.',
        'RESEARCH' => 'Administrative and records supplies supporting research office documentation and reporting.',
        'VPAF' => 'Office supplies for policy paperwork, endorsements, and administration-finance coordination.',
        'VPAA' => 'Administrative supplies for academic affairs correspondence, scheduling, and records handling.',
        'OP' => 'Executive office supplies for routing, endorsements, and inter-office communications.',
    ];

    if (isset($purposeMap[$deptCode])) {
        return $purposeMap[$deptCode];
    }

    return 'Routine replenishment of office consumables to support daily operations of ' . $deptLabel . '.';
}

function departmentItemPool(string $deptCode): array
{
    $deptCode = strtoupper(trim($deptCode));

    $baseOffice = [1, 3, 6, 11, 13, 14, 16, 30];
    $printer = [2, 12];
    $electrical = [7, 8, 17, 18, 25];
    $medical = [9, 10, 19, 20];
    $construction = [26, 29];

    return match ($deptCode) {
        'ICTO' => array_values(array_unique(array_merge($baseOffice, $printer, $electrical))),
        'CLINIC' => array_values(array_unique(array_merge([1, 3, 11, 14], $medical))),
        'SPMO', 'PO', 'VPAF' => array_values(array_unique(array_merge($baseOffice, $printer, $construction, [25]))),
        'ACCOUNTING', 'CASHIER', 'BUDGET', 'REGISTRAR', 'LIBRARY', 'OP', 'VPAA' => array_values(array_unique(array_merge($baseOffice, $printer))),
        'RESEARCH' => array_values(array_unique(array_merge($baseOffice, $printer, [7, 17, 30]))),
        default => array_values(array_unique(array_merge($baseOffice, [7, 30]))),
    };
}

function chooseRequestQty(array $item, string $finalStatus, int $currentStock): int
{
    $unit = strtolower(trim((string)($item['unit'] ?? '')));

    [$baseMin, $baseMax] = match ($unit) {
        'ream', 'box', 'pack', 'bottle', 'roll', 'bag' => [2, 8],
        'dozen' => [1, 4],
        'piece' => [1, 5],
        'set', 'unit' => [1, 2],
        default => [1, 5],
    };

    if ($finalStatus === 'Partial Issued') {
        $baseMin = max(2, $baseMin);
    }

    $statusCapRatio = match ($finalStatus) {
        'Requested', 'Approved' => 0.08,
        'Partial Issued' => 0.10,
        default => 0.12,
    };

    $stockCap = (int)floor($currentStock * $statusCapRatio);
    $cap = max(1, min($currentStock, max($baseMin, $stockCap), $baseMax));
    $minQty = min($baseMin, $cap);

    $qty = mt_rand($minQty, $cap);

    if ($finalStatus === 'Partial Issued' && $qty < 2 && $currentStock >= 2) {
        $qty = 2;
    }

    return max(1, min($qty, $currentStock));
}

function buildStatusPool(int $departmentCount): array
{
    if ($departmentCount <= 0) {
        return [];
    }

    $requestedCount = 1;
    $approvedCount = max(1, (int)floor($departmentCount * 0.10));
    $nonIssuedCount = $requestedCount + $approvedCount;

    $maxNonIssued = max(1, (int)floor($departmentCount * 0.20));
    if ($nonIssuedCount > $maxNonIssued) {
        $approvedCount = max(1, $maxNonIssued - $requestedCount);
        $nonIssuedCount = $requestedCount + $approvedCount;
    }

    $issuedFlowCount = $departmentCount - $nonIssuedCount;
    if ($issuedFlowCount < 1) {
        $issuedFlowCount = 1;
        if ($approvedCount > 1) {
            $approvedCount--;
        } else {
            $requestedCount = 0;
        }
    }

    $partialCount = max(1, (int)round($issuedFlowCount * 0.35));
    $issuedCount = max(1, (int)round($issuedFlowCount * 0.30));
    $completedCount = $issuedFlowCount - $partialCount - $issuedCount;
    if ($completedCount < 1) {
        $completedCount = 1;
        if ($partialCount > $issuedCount && $partialCount > 1) {
            $partialCount--;
        } elseif ($issuedCount > 1) {
            $issuedCount--;
        }
    }

    $pool = [];
    $pool = array_merge($pool, array_fill(0, $requestedCount, 'Requested'));
    $pool = array_merge($pool, array_fill(0, $approvedCount, 'Approved'));
    $pool = array_merge($pool, array_fill(0, $partialCount, 'Partial Issued'));
    $pool = array_merge($pool, array_fill(0, $issuedCount, 'Issued'));
    $pool = array_merge($pool, array_fill(0, $completedCount, 'Completed'));

    if (count($pool) > $departmentCount) {
        $pool = array_slice($pool, 0, $departmentCount);
    } elseif (count($pool) < $departmentCount) {
        $pool = array_merge($pool, array_fill(0, $departmentCount - count($pool), 'Completed'));
    }

    shuffle($pool);
    return $pool;
}

function remarkForStatus(string $status, string $departmentCode, string $tag): string
{
    $statusRemarks = [
        'Requested' => [
            'Filed by department; pending supply office review and scheduling.',
            'Initial submission complete; awaiting approval queue.',
            'Department request logged for validation and allocation.',
        ],
        'Approved' => [
            'Validated by Supply Officer; issuance to follow next stock window.',
            'Approved for release once pick-list is finalized.',
            'Request passed compliance check and is queued for issuance.',
        ],
        'Partial Issued' => [
            'Priority quantities released; remaining balance to be scheduled.',
            'Partial fulfillment completed due staggered departmental release.',
            'Initial release issued; outstanding items retained for next cycle.',
        ],
        'Issued' => [
            'All requested quantities released; awaiting final completion tagging.',
            'Stocks issued in full and endorsed to department focal person.',
            'Issued quantities cleared by warehouse for department use.',
        ],
        'Completed' => [
            'Requested quantities fully released and transaction closed.',
            'Issuance cycle completed with no outstanding balance.',
            'Department release finalized and marked complete.',
        ],
    ];

    $line = randElement($statusRemarks[$status] ?? ['Operational transaction recorded.']);
    return '[' . $departmentCode . '] ' . $line;
}

function auditRecord(
    AuditLog $audit,
    int $actorId,
    string $action,
    string $entity,
    ?int $entityId,
    string $tag,
    array $metadata = [],
    array $details = []
): void {
    $metadata = array_merge([
        'seed_tag' => $tag,
        'source' => 'scripts/seed_demo_supply_flow.php',
        'ip' => '127.0.0.1',
        'user_agent' => 'cli-demo-seeder/1.0',
    ], $metadata);

    $audit->record($actorId, $action, $entity, $entityId, $metadata, $details);
}

function updateRisHeaderFields(
    RIS $risModel,
    int $risId,
    ?int $approvedBy,
    ?int $issuedBy,
    ?int $receivedBy,
    string $status,
    string $remarks
): void {
    $row = $risModel->getById($risId);
    if (!$row) {
        throw new RuntimeException('RIS record not found for update. ID=' . $risId);
    }

    $ok = RIS::update(
        (string)($row['fund_cluster'] ?? ''),
        (string)($row['responsibility_center_code'] ?? ''),
        $approvedBy,
        $issuedBy,
        $receivedBy,
        $status,
        $remarks,
        (string)($row['purpose'] ?? ''),
        $risId
    );

    if (!$ok) {
        throw new RuntimeException('Failed to update RIS header fields. ID=' . $risId);
    }
}

function syncIssuanceStatusFromRis(RIS_Items $risItemsModel, int $risId, int $issuanceId): array
{
    $risItems = $risItemsModel->getAll($risId);
    $totalRequested = 0;
    $totalIssued = 0;
    $totalOutstanding = 0;

    foreach ($risItems as $itemRow) {
        $req = (int)($itemRow['qty_requested'] ?? 0);
        $iss = (int)($itemRow['qty_issued'] ?? 0);
        $totalRequested += $req;
        $totalIssued += $iss;
        $totalOutstanding += max(0, $req - $iss);
    }

    if ($totalOutstanding === 0 && $totalRequested > 0) {
        RIS::updateStatus('Completed', $risId);
        Supply_Issuance::updateStatus('Completed', $issuanceId);
        $status = 'Completed';
    } elseif ($totalIssued > 0) {
        RIS::updateStatus('Partial Issued', $risId);
        Supply_Issuance::updateStatus('Partial Issued', $issuanceId);
        $status = 'Partial Issued';
    } else {
        RIS::updateStatus('Approved', $risId);
        Supply_Issuance::updateStatus('Draft', $issuanceId);
        $status = 'Approved';
    }

    return [
        'status' => $status,
        'total_requested' => $totalRequested,
        'total_issued' => $totalIssued,
        'total_outstanding' => $totalOutstanding,
    ];
}

function issueRisLine(
    array $issuance,
    array $risItem,
    int $qtyToIssue,
    string $remarks,
    int $supplyOfficerId,
    Item $itemRepo,
    RIS_Items $risItemsModel
): ?int {
    if ($qtyToIssue <= 0) {
        return null;
    }

    $issuanceId = (int)($issuance['id'] ?? 0);
    $risId = (int)($issuance['ris_id'] ?? 0);
    $risItemId = (int)($risItem['id'] ?? 0);
    $itemId = (int)($risItem['item_id'] ?? 0);
    $qtyRequested = (int)($risItem['qty_requested'] ?? 0);
    $alreadyIssued = (int)($risItem['qty_issued'] ?? 0);
    $remaining = max(0, $qtyRequested - $alreadyIssued);

    if ($remaining <= 0) {
        return null;
    }

    if ($qtyToIssue > $remaining) {
        $qtyToIssue = $remaining;
    }

    $item = $itemRepo->getById($itemId);
    if (!$item) {
        throw new RuntimeException('Cannot issue RIS item. Inventory item missing. item_id=' . $itemId);
    }

    $stockOnHand = (int)($item['stock_onhand'] ?? 0);
    if ($stockOnHand < $qtyToIssue) {
        throw new RuntimeException('Insufficient stock on hand for item_id=' . $itemId . '. available=' . $stockOnHand . ' requested=' . $qtyToIssue);
    }

    $existing = Supply_Issuance_Items::getByIssuanceAndRequisitionItem($issuanceId, $risItemId);
    if ($existing) {
        $newTotalIssued = (int)$existing['qty_issued'] + $qtyToIssue;
        $updated = Supply_Issuance_Items::updateQtyIssued($newTotalIssued, (int)$existing['id'], $remarks);
        if (!$updated) {
            throw new RuntimeException('Failed to update existing issuance line. id=' . (int)$existing['id']);
        }
        $issuanceItemId = (int)$existing['id'];
    } else {
        $insertId = Supply_Issuance_Items::add(
            $issuanceId,
            $risItemId,
            $itemId,
            $qtyRequested,
            $qtyToIssue,
            $remarks
        );
        if (!$insertId) {
            throw new RuntimeException('Failed to insert issuance line for requisition item id=' . $risItemId);
        }
        $issuanceItemId = (int)$insertId;
    }

    $itemRepo->reduceQty($itemId, $qtyToIssue);

    StockInventory::recordMovement(
        $itemId,
        0,
        $qtyToIssue,
        'Supply Issuance',
        $issuanceId,
        (string)($issuance['rsmi_no'] ?? ''),
        $remarks,
        (string)($issuance['issuance_date'] ?? date('Y-m-d'))
    );

    $itemCurrent = $itemRepo->getById($itemId);
    $unitCost = (float)($itemCurrent['unit_cost'] ?? 0);
    Supply_Ledger_Entry::recordMovement(
        $itemId,
        (string)($issuance['issuance_date'] ?? date('Y-m-d')),
        'Supply Issuance',
        $issuanceId,
        (string)($issuance['rsmi_no'] ?? ''),
        0,
        $qtyToIssue,
        $unitCost,
        $remarks,
        $supplyOfficerId
    );

    RIS_Items::adjustQtyIssued($risItemId, $qtyToIssue);
    syncIssuanceStatusFromRis($risItemsModel, $risId, $issuanceId);

    return $issuanceItemId;
}

function summarizeRequestTotals(RIS_Items $risItemsModel, int $risId): array
{
    $items = $risItemsModel->getAll($risId);
    $requested = 0;
    $issued = 0;
    foreach ($items as $row) {
        $requested += (int)($row['qty_requested'] ?? 0);
        $issued += (int)($row['qty_issued'] ?? 0);
    }
    return ['requested' => $requested, 'issued' => $issued];
}

$opts = getopt('', ['execute', 'seed::', 'tag::', 'department-limit::', 'help']);
if (isset($opts['help'])) {
    printUsage();
    exit(0);
}

$execute = isset($opts['execute']);
$seed = isset($opts['seed']) ? (int)$opts['seed'] : DEFAULT_SEED;
$tag = trim((string)($opts['tag'] ?? DEFAULT_TAG));
$departmentLimit = isset($opts['department-limit']) ? max(1, (int)$opts['department-limit']) : null;

if ($tag === '') {
    out('Error: --tag cannot be empty.');
    exit(1);
}

mt_srand($seed);

out('Demo supply flow seeder');
out('Seed: ' . $seed);
out('Tag : ' . $tag);
out('');

if (!$execute) {
    out('Dry mode only. No changes were written.');
    out('Re-run with --execute to apply the seed.');
    exit(0);
}

$pdo = Model::Db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$supplyOfficerStmt = $pdo->prepare(
    "SELECT id, fullname
     FROM user_role_dept
     WHERE roleid = 2
       AND status = 'Active'
     ORDER BY id ASC
     LIMIT 1"
);
$supplyOfficerStmt->execute();
$supplyOfficer = $supplyOfficerStmt->fetch(PDO::FETCH_ASSOC);
if (!$supplyOfficer) {
    out('Error: No active Supply Officer user found.');
    exit(1);
}
$supplyOfficerId = (int)$supplyOfficer['id'];

$existingSeedStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM activity_logs
     WHERE metadata LIKE ?"
);
$existingSeedStmt->execute(['%"seed_tag":"' . $tag . '"%']);
$existingSeedCount = (int)$existingSeedStmt->fetchColumn();
if ($existingSeedCount > 0) {
    out('Abort: Seed tag already exists in activity logs (' . $existingSeedCount . ' rows).');
    out('Use a new --tag value if you need another batch.');
    exit(1);
}

$employeeStmt = $pdo->query(
    "SELECT
        u.id,
        u.fullname,
        u.departmentid,
        u.departmentcode,
        u.departmentname
     FROM user_role_dept u
     WHERE u.roleid = 6
       AND u.status = 'Active'
       AND u.departmentid > 0
     ORDER BY u.departmentcode ASC, u.id ASC"
);
$employeeRows = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($employeeRows)) {
    out('Error: No active employee records found.');
    exit(1);
}

$employeesByDepartment = [];
foreach ($employeeRows as $row) {
    $deptId = (int)$row['departmentid'];
    if (!isset($employeesByDepartment[$deptId])) {
        $employeesByDepartment[$deptId] = [];
    }
    $employeesByDepartment[$deptId][] = $row;
}

$selectedDepartments = [];
ksort($employeesByDepartment);
foreach ($employeesByDepartment as $deptId => $group) {
    $pick = $group[array_rand($group)];
    $selectedDepartments[] = $pick;
}

usort($selectedDepartments, static function (array $a, array $b): int {
    return strcmp((string)$a['departmentcode'], (string)$b['departmentcode']);
});

if ($departmentLimit !== null && count($selectedDepartments) > $departmentLimit) {
    $selectedDepartments = array_slice($selectedDepartments, 0, $departmentLimit);
}

$departmentCount = count($selectedDepartments);
if ($departmentCount === 0) {
    out('Error: No departments selected for seeding.');
    exit(1);
}

$statusPool = buildStatusPool($departmentCount);

$itemRepo = new Item();
$risModel = new RIS();
$risItemsModel = new RIS_Items();
$audit = new AuditLog();

$allItemsRaw = $itemRepo->getAll();
$itemsById = [];
$stockMap = [];
$fallbackItemIds = [];
foreach ($allItemsRaw as $itemRow) {
    $itemId = (int)($itemRow['id'] ?? 0);
    if ($itemId <= 0) {
        continue;
    }
    $itemsById[$itemId] = $itemRow;
    $stockMap[$itemId] = (int)($itemRow['stock_onhand'] ?? 0);

    $isConsumable = (int)($itemRow['is_consumable'] ?? 0) === 1;
    if ($isConsumable && $stockMap[$itemId] >= 10) {
        $fallbackItemIds[] = $itemId;
    }
}

if (empty($fallbackItemIds)) {
    out('Error: No fallback consumable items with sufficient stock were found.');
    exit(1);
}

$summary = [
    'departments' => $departmentCount,
    'requests_created' => 0,
    'request_lines_created' => 0,
    'issuances_created' => 0,
    'issuance_lines_created' => 0,
    'total_requested_qty' => 0,
    'total_issued_qty' => 0,
    'status_counts' => [
        'Requested' => 0,
        'Approved' => 0,
        'Partial Issued' => 0,
        'Issued' => 0,
        'Completed' => 0,
    ],
    'sample_ris' => [],
    'sample_rsmi' => [],
];

$today = new DateTimeImmutable(date('Y-m-d'));
$startDate = new DateTimeImmutable('2026-02-18');

try {
    $pdo->beginTransaction();

    foreach ($selectedDepartments as $index => $deptEmployee) {
        $employeeId = (int)$deptEmployee['id'];
        $employeeName = (string)$deptEmployee['fullname'];
        $departmentId = (int)$deptEmployee['departmentid'];
        $departmentCode = strtoupper(trim((string)$deptEmployee['departmentcode']));
        $departmentName = trim((string)$deptEmployee['departmentname']);
        $finalStatus = $statusPool[$index] ?? 'Completed';

        $requisitionDate = clampDate($startDate->modify('+' . $index . ' day'), $today);
        $requisitionDateText = $requisitionDate->format('Y-m-d');

        $fundCluster = 'FC-' . $departmentCode . '-2026';
        $responsibilityCenterCode = 'RC-' . $departmentCode;
        $purpose = purposeForDepartment($departmentCode, $departmentName);
        $requestRemarks = remarkForStatus($finalStatus, $departmentCode, $tag);
        $risNo = RIS::getNextRISNo();

        $risId = RIS::addinitial(
            $risNo,
            $requisitionDateText,
            $fundCluster,
            $departmentId,
            $responsibilityCenterCode,
            $employeeId,
            $purpose,
            $requestRemarks
        );

        if (!$risId) {
            throw new RuntimeException('Failed to create RIS for department ' . $departmentCode);
        }

        $risId = (int)$risId;
        $summary['requests_created']++;
        $summary['status_counts'][$finalStatus] = ($summary['status_counts'][$finalStatus] ?? 0) + 1;
        if (count($summary['sample_ris']) < 8) {
            $summary['sample_ris'][] = $risNo;
        }

        auditRecord(
            $audit,
            $employeeId,
            'employee.ris.create.success',
            'requisition_slips',
            $risId,
            $tag,
            [
                'department_id' => $departmentId,
                'department_code' => $departmentCode,
                'ris_no' => $risNo,
            ],
            [
                'status' => ['old' => null, 'new' => 'Requested'],
                'purpose' => ['old' => null, 'new' => $purpose],
            ]
        );

        $lineTarget = in_array($finalStatus, ['Requested', 'Approved'], true) ? 2 : 4;
        $poolIds = departmentItemPool($departmentCode);
        $poolIds = array_values(array_filter($poolIds, static function (int $itemId) use ($itemsById): bool {
            return isset($itemsById[$itemId]);
        }));

        if (count($poolIds) < $lineTarget) {
            $poolIds = array_values(array_unique(array_merge($poolIds, $fallbackItemIds)));
        }

        shuffle($poolIds);
        $selectedItemIds = array_slice($poolIds, 0, $lineTarget);
        if (empty($selectedItemIds)) {
            throw new RuntimeException('No available item pool for department ' . $departmentCode);
        }

        $requestLines = [];
        foreach ($selectedItemIds as $lineIdx => $itemId) {
            $item = $itemsById[$itemId];
            $currentStock = (int)($stockMap[$itemId] ?? 0);
            if ($currentStock <= 0) {
                continue;
            }

            $qtyRequested = chooseRequestQty($item, $finalStatus, $currentStock);
            if ($finalStatus === 'Partial Issued' && $lineIdx === 0 && $qtyRequested < 2 && $currentStock >= 2) {
                $qtyRequested = 2;
            }

            $lineRemark = 'Department request line for ' . $departmentCode . '.';
            $lineId = RIS_Items::add($risId, $itemId, $qtyRequested, $lineRemark);
            if (!$lineId) {
                throw new RuntimeException('Failed to create RIS line for RIS ID=' . $risId);
            }

            $lineId = (int)$lineId;
            $summary['request_lines_created']++;
            $summary['total_requested_qty'] += $qtyRequested;

            auditRecord(
                $audit,
                $employeeId,
                'employee.ris_item.add.success',
                'requisition_items',
                $lineId,
                $tag,
                [
                    'ris_id' => $risId,
                    'item_id' => $itemId,
                ],
                [
                    'qty_requested' => ['old' => null, 'new' => (string)$qtyRequested],
                ]
            );

            $requestLines[] = [
                'id' => $lineId,
                'item_id' => $itemId,
                'qty_requested' => $qtyRequested,
            ];
        }

        if (empty($requestLines)) {
            throw new RuntimeException('No request lines created for RIS ID=' . $risId);
        }

        if ($finalStatus === 'Requested') {
            continue;
        }

        $approvalRemark = remarkForStatus('Approved', $departmentCode, $tag);
        updateRisHeaderFields(
            $risModel,
            $risId,
            $supplyOfficerId,
            null,
            null,
            'Approved',
            $approvalRemark
        );

        auditRecord(
            $audit,
            $supplyOfficerId,
            'ris.status.update.success',
            'requisition_slips',
            $risId,
            $tag,
            [
                'department_code' => $departmentCode,
                'status' => 'Approved',
            ]
        );

        if ($finalStatus === 'Approved') {
            continue;
        }

        $issuanceDate = clampDate(
            $requisitionDate->modify('+' . mt_rand(1, 3) . ' day'),
            $today
        );
        $issuanceDateText = $issuanceDate->format('Y-m-d');
        $issuanceRemark = remarkForStatus($finalStatus, $departmentCode, $tag);
        $rsmiNo = Supply_Issuance::getNextRSMINo();

        $issuanceId = Supply_Issuance::add(
            $rsmiNo,
            $risId,
            $issuanceDateText,
            $supplyOfficerId,
            $employeeId,
            'Draft',
            $issuanceRemark
        );
        if (!$issuanceId) {
            throw new RuntimeException('Failed to create issuance header for RIS ID=' . $risId);
        }

        $issuanceId = (int)$issuanceId;
        $summary['issuances_created']++;
        if (count($summary['sample_rsmi']) < 8) {
            $summary['sample_rsmi'][] = $rsmiNo;
        }

        auditRecord(
            $audit,
            $supplyOfficerId,
            'supply.issuance.create.success',
            'supply_issuances',
            $issuanceId,
            $tag,
            [
                'ris_id' => $risId,
                'rsmi_no' => $rsmiNo,
                'issued_to' => $employeeId,
            ]
        );

        $issuanceHeader = (new Supply_Issuance())->getById($issuanceId);
        if (!$issuanceHeader) {
            throw new RuntimeException('Issuance header fetch failed. ID=' . $issuanceId);
        }

        $risItemsForIssue = $risItemsModel->getAll($risId);
        $lineCount = count($risItemsForIssue);
        $issuedAnything = false;

        foreach ($risItemsForIssue as $lineIndex => $risLine) {
            $requestedQty = (int)($risLine['qty_requested'] ?? 0);
            if ($requestedQty <= 0) {
                continue;
            }

            if ($finalStatus === 'Partial Issued') {
                // Keep a small outstanding balance on at least one line.
                if ($lineIndex === 0 && $requestedQty >= 2) {
                    $qtyToIssue = $requestedQty - 1;
                } elseif ($lineCount === 1 && $requestedQty >= 2) {
                    $qtyToIssue = $requestedQty - 1;
                } else {
                    $qtyToIssue = $requestedQty;
                }
            } else {
                $qtyToIssue = $requestedQty;
            }

            if ($qtyToIssue <= 0) {
                continue;
            }

            $issuanceLineId = issueRisLine(
                $issuanceHeader,
                $risLine,
                $qtyToIssue,
                $issuanceRemark,
                $supplyOfficerId,
                $itemRepo,
                $risItemsModel
            );

            if ($issuanceLineId !== null) {
                $summary['issuance_lines_created']++;
                $summary['total_issued_qty'] += $qtyToIssue;
                $itemId = (int)($risLine['item_id'] ?? 0);
                if ($itemId > 0) {
                    $stockMap[$itemId] = max(0, ((int)($stockMap[$itemId] ?? 0)) - $qtyToIssue);
                }

                auditRecord(
                    $audit,
                    $supplyOfficerId,
                    'supply.issuance.item.create.success',
                    'supply_issuance_items',
                    $issuanceLineId,
                    $tag,
                    [
                        'issuance_id' => $issuanceId,
                        'ris_id' => $risId,
                        'requisition_item_id' => (int)$risLine['id'],
                    ],
                    [
                        'qty_issued' => ['old' => null, 'new' => (string)$qtyToIssue],
                    ]
                );
                $issuedAnything = true;
            }
        }

        if (!$issuedAnything) {
            throw new RuntimeException('Issuance created but no lines were issued. issuance_id=' . $issuanceId);
        }

        $finalIssuanceStatus = $finalStatus === 'Completed'
            ? 'Completed'
            : ($finalStatus === 'Issued' ? 'Issued' : 'Partial Issued');

        $finalReceivedBy = in_array($finalStatus, ['Issued', 'Completed'], true) ? $employeeId : null;
        $finalIssuedBy = $supplyOfficerId;
        $finalRemarks = remarkForStatus($finalStatus, $departmentCode, $tag);

        updateRisHeaderFields(
            $risModel,
            $risId,
            $supplyOfficerId,
            $finalIssuedBy,
            $finalReceivedBy,
            $finalStatus,
            $finalRemarks
        );

        $updatedIssuance = Supply_Issuance::update(
            $issuanceDateText,
            $supplyOfficerId,
            $employeeId,
            $finalIssuanceStatus,
            $finalRemarks,
            $issuanceId
        );
        if (!$updatedIssuance) {
            throw new RuntimeException('Failed to finalize issuance header. ID=' . $issuanceId);
        }

        auditRecord(
            $audit,
            $supplyOfficerId,
            'supply.issuance.update.success',
            'supply_issuances',
            $issuanceId,
            $tag,
            [
                'status' => $finalIssuanceStatus,
                'department_code' => $departmentCode,
            ]
        );
        auditRecord(
            $audit,
            $supplyOfficerId,
            'ris.status.update.success',
            'requisition_slips',
            $risId,
            $tag,
            [
                'status' => $finalStatus,
                'department_code' => $departmentCode,
            ]
        );

        $totals = summarizeRequestTotals($risItemsModel, $risId);
        if ($totals['issued'] > $totals['requested']) {
            throw new RuntimeException('Issued quantity exceeded requested quantity for RIS ID=' . $risId);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    out('Error: ' . $e->getMessage());
    exit(1);
}

$ratio = $summary['total_requested_qty'] > 0
    ? ($summary['total_issued_qty'] / $summary['total_requested_qty']) * 100
    : 0;

out('Seed applied successfully.');
out('');
out('Summary:');
out('  Departments covered    : ' . $summary['departments']);
out('  RIS headers created    : ' . $summary['requests_created']);
out('  RIS lines created      : ' . $summary['request_lines_created']);
out('  Issuance headers       : ' . $summary['issuances_created']);
out('  Issuance lines         : ' . $summary['issuance_lines_created']);
out('  Requested qty (total)  : ' . $summary['total_requested_qty']);
out('  Issued qty (total)     : ' . $summary['total_issued_qty']);
out('  Issued vs requested    : ' . number_format($ratio, 2) . '%');
out('');
out('Final status distribution:');
foreach ($summary['status_counts'] as $status => $count) {
    out('  - ' . str_pad($status, 14, ' ', STR_PAD_RIGHT) . ': ' . $count);
}

if (!empty($summary['sample_ris'])) {
    out('');
    out('Sample RIS numbers: ' . implode(', ', $summary['sample_ris']));
}

if (!empty($summary['sample_rsmi'])) {
    out('Sample RSMI numbers: ' . implode(', ', $summary['sample_rsmi']));
}

out('');
out('Audit validation hint: filter `activity_logs.metadata` by seed tag `' . $tag . '`.');
