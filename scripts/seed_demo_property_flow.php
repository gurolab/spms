<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../repo/ICS.model.php';
require_once __DIR__ . '/../repo/PAR.model.php';
require_once __DIR__ . '/../repo/PropertyCard.model.php';
require_once __DIR__ . '/../repo/PropertyTransfer.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/SupplyLedger.model.php';
require_once __DIR__ . '/../repo/AuditLog.model.php';

date_default_timezone_set('Asia/Manila');

const DEFAULT_SEED = 20260310;
const DEFAULT_TAG = 'DEMO-PROP-20260310';
const DEFAULT_ICS_COUNT = 10;
const DEFAULT_PAR_COUNT = 8;
const DEFAULT_TRANSFER_COUNT = 4;
const DEFAULT_REPAIR_COUNT = 2;

function out(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function printUsage(): void
{
    out('Usage: php scripts/seed_demo_property_flow.php [--execute] [--seed=20260310] [--tag=DEMO-PROP-20260310]');
    out('       [--ics-count=10] [--par-count=8] [--transfer-count=4] [--repair-count=2]');
    out('');
    out('Flags:');
    out('  --execute              Apply changes to the database.');
    out('  --seed=INT             Deterministic random seed. Default: ' . DEFAULT_SEED);
    out('  --tag=STRING           Seed marker for audit metadata.');
    out('  --ics-count=N          Number of ICS headers to create.');
    out('  --par-count=N          Number of PAR headers/cards to create.');
    out('  --transfer-count=N     Number of completed transfers to auto-apply.');
    out('  --repair-count=N       Number of cards to add repair/return movement.');
    out('  --help                 Show this help text.');
}

function clampDate(DateTimeImmutable $date, DateTimeImmutable $max): DateTimeImmutable
{
    if ($date > $max) {
        return $max;
    }
    return $date;
}

function withSeedTag(string $text, string $tag): string
{
    return trim($text);
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
        'source' => 'scripts/seed_demo_property_flow.php',
        'ip' => '127.0.0.1',
        'user_agent' => 'cli-demo-property-seeder/1.0',
    ], $metadata);

    $audit->record($actorId, $action, $entity, $entityId, $metadata, $details);
}

function tableHasSeedTag(PDO $db, string $table, string $column, string $tagNeedle): bool
{
    $sql = sprintf("SELECT 1 FROM `%s` WHERE `%s` LIKE ? LIMIT 1", $table, $column);
    $stmt = $db->prepare($sql);
    $stmt->execute(['%' . $tagNeedle . '%']);
    return (bool)$stmt->fetchColumn();
}

function getRoleUser(PDO $db, int $roleId, string $status = 'Active'): ?array
{
    $stmt = $db->prepare(
        "SELECT *
         FROM user_role_dept
         WHERE roleid = ?
           AND status = ?
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmt->execute([$roleId, $status]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function getActiveEmployees(PDO $db): array
{
    $stmt = $db->prepare(
        "SELECT *
         FROM user_role_dept
         WHERE roleid = 6
           AND status = 'Active'
         ORDER BY departmentid ASC, id ASC"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pickEmployeesByDepartment(array $employees, int $count): array
{
    if ($count <= 0 || empty($employees)) {
        return [];
    }

    $byDept = [];
    foreach ($employees as $emp) {
        $deptId = (int)($emp['departmentid'] ?? 0);
        if (!isset($byDept[$deptId])) {
            $byDept[$deptId] = [];
        }
        $byDept[$deptId][] = $emp;
    }

    $picked = [];
    foreach ($byDept as $group) {
        $picked[] = $group[array_rand($group)];
        if (count($picked) >= $count) {
            return array_slice($picked, 0, $count);
        }
    }

    while (count($picked) < $count) {
        $picked[] = $employees[array_rand($employees)];
    }

    return $picked;
}

function pickDifferentEmployee(array $employees, int $excludeId): ?array
{
    $candidates = array_values(array_filter($employees, static function (array $emp) use ($excludeId): bool {
        return (int)($emp['id'] ?? 0) !== $excludeId;
    }));
    if (empty($candidates)) {
        return null;
    }
    return $candidates[array_rand($candidates)];
}

function propertyTagExists(PDO $db, string $propertyTag): bool
{
    $checks = [
        ['par_items', 'property_no'],
        ['ics_items', 'property_no'],
        ['property_cards', 'property_tag'],
        ['property_transfers', 'property_tag'],
    ];

    foreach ($checks as [$table, $column]) {
        $stmt = $db->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
        $stmt->execute([$propertyTag]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    }

    return false;
}

function controlNoExists(PDO $db, string $controlNo): bool
{
    $stmt = $db->prepare("SELECT 1 FROM inventory_custodian_slips WHERE property_no = ? LIMIT 1");
    $stmt->execute([$controlNo]);
    return (bool)$stmt->fetchColumn();
}

function generateUniquePropertyTag(PDO $db, string $prefix, int &$counter): string
{
    while (true) {
        $counter++;
        $tag = strtoupper($prefix) . '-2026-' . str_pad((string)$counter, 4, '0', STR_PAD_LEFT);
        if (!propertyTagExists($db, $tag)) {
            return $tag;
        }
    }
}

function generateUniqueControlNo(PDO $db, string $prefix, int &$counter): string
{
    while (true) {
        $counter++;
        $controlNo = strtoupper($prefix) . '-CTRL-' . str_pad((string)$counter, 4, '0', STR_PAD_LEFT);
        if (!controlNoExists($db, $controlNo)) {
            return $controlNo;
        }
    }
}

function buildItemPools(array $items): array
{
    $icsPool = [];
    $parPool = [];
    $stockMap = [];

    foreach ($items as $item) {
        $itemId = (int)($item['id'] ?? 0);
        if ($itemId <= 0) {
            continue;
        }

        $stock = (int)($item['stock_onhand'] ?? 0);
        $stockMap[$itemId] = $stock;
        $isConsumable = (int)($item['is_consumable'] ?? 0) === 1;
        $unitCost = (float)($item['unit_cost'] ?? 0);
        $type = strtolower(trim((string)($item['type'] ?? '')));

        if (!$isConsumable && $stock > 0) {
            $parPool[] = $item;
        }

        if (
            !$isConsumable
            && $stock > 0
            && (
                $unitCost <= 5000
                || in_array($type, ['semi-expendable', 'property'], true)
            )
        ) {
            $icsPool[] = $item;
        }
    }

    if (empty($icsPool)) {
        $icsPool = $parPool;
    }

    usort($icsPool, static fn(array $a, array $b): int => (int)$b['stock_onhand'] <=> (int)$a['stock_onhand']);
    usort($parPool, static fn(array $a, array $b): int => (int)$b['stock_onhand'] <=> (int)$a['stock_onhand']);

    return [$icsPool, $parPool, $stockMap];
}

function pickParItem(array $parPool, array &$stockMap): ?array
{
    $candidates = array_values(array_filter($parPool, static function (array $item) use ($stockMap): bool {
        $itemId = (int)($item['id'] ?? 0);
        return $itemId > 0 && ((int)($stockMap[$itemId] ?? 0) > 0);
    }));
    if (empty($candidates)) {
        return null;
    }

    $item = $candidates[array_rand($candidates)];
    $itemId = (int)$item['id'];
    $stockMap[$itemId] = max(0, (int)$stockMap[$itemId] - 1);
    return $item;
}

function recalculateCardLedger(int $cardId): void
{
    $rows = PropertyCardTransaction::getAllByCardAscending($cardId);
    $card = (new PropertyCard())->getById($cardId);
    $unitCost = (float)($card['acquisition_cost'] ?? 0);
    $running = 0;

    foreach ($rows as $row) {
        $receiptQty = max(0, (int)($row['receipt_qty'] ?? 0));
        $issueQty = max(0, (int)($row['issue_qty'] ?? 0));
        $running += ($receiptQty - $issueQty);
        if ($running < 0) {
            $running = 0;
        }

        $amount = (float)($row['amount'] ?? 0);
        if ($amount <= 0 && $unitCost > 0) {
            $amount = $running * $unitCost;
        }

        PropertyCardTransaction::updateLedgerSnapshot((int)$row['id'], $running, $amount);
    }
}

function departmentNameById(PDO $db, int $departmentId): string
{
    if ($departmentId <= 0) {
        return '';
    }
    $stmt = $db->prepare("SELECT name FROM departments WHERE id = ? LIMIT 1");
    $stmt->execute([$departmentId]);
    return trim((string)$stmt->fetchColumn());
}

function countParItems(PDO $db, int $parId): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM par_items WHERE par_id = ?");
    $stmt->execute([$parId]);
    return (int)$stmt->fetchColumn();
}

function appendRemark(string $base, string $extra): string
{
    $base = trim($base);
    $extra = trim($extra);
    if ($base === '') {
        return $extra;
    }
    if ($extra === '') {
        return $base;
    }
    return $base . ' | ' . $extra;
}

$opts = getopt('', [
    'execute',
    'seed::',
    'tag::',
    'ics-count::',
    'par-count::',
    'transfer-count::',
    'repair-count::',
    'help',
]);

if (isset($opts['help'])) {
    printUsage();
    exit(0);
}

$execute = isset($opts['execute']);
$seed = isset($opts['seed']) ? (int)$opts['seed'] : DEFAULT_SEED;
$tag = trim((string)($opts['tag'] ?? DEFAULT_TAG));
$icsCount = isset($opts['ics-count']) ? max(0, (int)$opts['ics-count']) : DEFAULT_ICS_COUNT;
$parCount = isset($opts['par-count']) ? max(0, (int)$opts['par-count']) : DEFAULT_PAR_COUNT;
$transferCount = isset($opts['transfer-count']) ? max(0, (int)$opts['transfer-count']) : DEFAULT_TRANSFER_COUNT;
$repairCount = isset($opts['repair-count']) ? max(0, (int)$opts['repair-count']) : DEFAULT_REPAIR_COUNT;

mt_srand($seed);

out('Demo property flow seeder');
out('Seed : ' . $seed);
out('Tag  : ' . $tag);
out('ICS  : ' . $icsCount);
out('PAR  : ' . $parCount);
out('Xfer : ' . $transferCount);
out('Repair tx cards: ' . $repairCount);
out('');

if (!$execute) {
    out('Dry mode only. No changes were written.');
    out('Re-run with --execute to apply.');
    exit(0);
}

$db = Model::Db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (tableHasSeedTag($db, 'activity_logs', 'metadata', '"seed_tag":"' . $tag . '"')) {
    out('Abort: seed tag already exists in activity_logs.metadata');
    exit(1);
}

$propertyCustodian = getRoleUser($db, 4, 'Active');
if (!$propertyCustodian) {
    out('Error: Active Property Custodian account is required.');
    exit(1);
}
$propertyCustodianId = (int)$propertyCustodian['id'];

$systemAdmin = getRoleUser($db, 1, 'Active');
$systemAdminId = $systemAdmin ? (int)$systemAdmin['id'] : $propertyCustodianId;

$activeEmployees = getActiveEmployees($db);
if (count($activeEmployees) < 2) {
    out('Error: At least two active employees are required.');
    exit(1);
}

$allItems = (new Item())->getAll();
[$icsPool, $parPool, $stockMap] = buildItemPools($allItems);

if ($icsCount > 0 && empty($icsPool)) {
    out('Error: No eligible non-consumable items available for ICS seeding.');
    exit(1);
}
if ($parCount > 0 && empty($parPool)) {
    out('Error: No eligible non-consumable items available for PAR seeding.');
    exit(1);
}

$audit = new AuditLog();
$itemRepo = new Item();
$today = new DateTimeImmutable(date('Y-m-d'));
$start = new DateTimeImmutable('2026-02-20');

$summary = [
    'ics_headers' => 0,
    'ics_items' => 0,
    'par_headers' => 0,
    'par_items' => 0,
    'property_cards' => 0,
    'card_transactions' => 0,
    'transfers' => 0,
    'auto_target_par' => 0,
    'status' => [
        'ics_active' => 0,
        'par_active' => 0,
        'par_transferred' => 0,
        'card_assigned' => 0,
        'card_for_repair' => 0,
    ],
    'sample' => [
        'ics' => [],
        'par' => [],
        'card' => [],
        'transfer' => [],
    ],
];

$icsControlCounter = 1000;
$icsPropertyCounter = 2000;
$parPropertyCounter = 3000;
$memoCounter = 4000;

$seededParCards = [];
$transferredCardIds = [];

try {
    $db->beginTransaction();

    $icsAssignees = pickEmployeesByDepartment($activeEmployees, $icsCount);
    foreach ($icsAssignees as $idx => $employee) {
        $departmentCode = strtoupper(trim((string)($employee['departmentcode'] ?? 'EMP')));
        $employeeId = (int)$employee['id'];
        $issuedDate = clampDate($start->modify('+' . $idx . ' day'), $today)->format('Y-m-d');
        $icsNo = ICS::getNextICSNo();
        $controlNo = generateUniqueControlNo($db, 'ICS-' . $departmentCode, $icsControlCounter);
        $remarks = withSeedTag('ICS issuance for low-value accountable equipment.', $tag);

        $icsId = ICS::add(
            $icsNo,
            $controlNo,
            'Low',
            $employeeId,
            $propertyCustodianId,
            $issuedDate,
            'Active',
            $remarks
        );
        if (!$icsId) {
            throw new RuntimeException('Failed to create ICS header.');
        }
        $icsId = (int)$icsId;
        $summary['ics_headers']++;
        $summary['status']['ics_active']++;
        if (count($summary['sample']['ics']) < 6) {
            $summary['sample']['ics'][] = $icsNo;
        }

        auditRecord(
            $audit,
            $propertyCustodianId,
            'ics.create.success',
            'ics',
            $icsId,
            $tag,
            [
                'ics_no' => $icsNo,
                'assigned_to' => $employeeId,
                'department_code' => $departmentCode,
            ]
        );

        $lineCount = mt_rand(1, 2);
        for ($line = 0; $line < $lineCount; $line++) {
            $item = $icsPool[array_rand($icsPool)];
            $itemId = (int)($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $qty = mt_rand(1, 2);
            $unitValue = (float)($item['unit_cost'] ?? 0);
            if ($unitValue <= 0) {
                $unitValue = 100;
            }

            $itemPropertyNo = generateUniquePropertyTag($db, 'ICS-' . $departmentCode, $icsPropertyCounter);
            $itemRemarks = withSeedTag('Issued under ' . $icsNo . ' for departmental use.', $tag);
            $itemDate = $issuedDate;
            $usefulLife = mt_rand(2, 7) . ' years';

            $icsItemId = ICS_Items::add(
                $icsId,
                $itemId,
                $itemPropertyNo,
                $qty,
                $unitValue,
                $itemDate,
                $itemRemarks,
                $usefulLife
            );
            if (!$icsItemId) {
                throw new RuntimeException('Failed to add ICS line item.');
            }

            $summary['ics_items']++;
            auditRecord(
                $audit,
                $propertyCustodianId,
                'ics.item.add.success',
                'ics_items',
                (int)$icsItemId,
                $tag,
                [
                    'ics_id' => $icsId,
                    'item_id' => $itemId,
                    'property_no' => $itemPropertyNo,
                ],
                [
                    'qty' => ['old' => null, 'new' => (string)$qty],
                    'unit_value' => ['old' => null, 'new' => (string)$unitValue],
                ]
            );
        }
    }

    $parAssignees = pickEmployeesByDepartment($activeEmployees, $parCount);
    foreach ($parAssignees as $idx => $employee) {
        $departmentCode = strtoupper(trim((string)($employee['departmentcode'] ?? 'EMP')));
        $departmentName = trim((string)($employee['departmentname'] ?? ''));
        $employeeId = (int)$employee['id'];
        $issueDate = clampDate($start->modify('+' . ($icsCount + $idx + 1) . ' day'), $today)->format('Y-m-d');
        $fundCluster = 'FC-' . $departmentCode . '-PROP';
        $parNo = PAR::getNextPARNo();
        $parRemarks = withSeedTag('Initial property accountability issuance for office operations.', $tag);

        $parId = PAR::add(
            $parNo,
            $fundCluster,
            $employeeId,
            $propertyCustodianId,
            $issueDate,
            'Active',
            $parRemarks
        );
        if (!$parId) {
            throw new RuntimeException('Failed to create PAR header.');
        }
        $parId = (int)$parId;
        $summary['par_headers']++;
        $summary['status']['par_active']++;
        if (count($summary['sample']['par']) < 6) {
            $summary['sample']['par'][] = $parNo;
        }

        auditRecord(
            $audit,
            $propertyCustodianId,
            'par.create.success',
            'par',
            $parId,
            $tag,
            [
                'par_no' => $parNo,
                'accountable_officer' => $employeeId,
                'department_code' => $departmentCode,
            ]
        );

        $parItem = pickParItem($parPool, $stockMap);
        if (!$parItem) {
            throw new RuntimeException('No stock available for PAR item seeding.');
        }
        $itemId = (int)$parItem['id'];
        $unitValue = (float)($parItem['unit_cost'] ?? 0);
        if ($unitValue <= 0) {
            $unitValue = 1000;
        }
        $propertyTag = generateUniquePropertyTag($db, 'PROP-' . $departmentCode, $parPropertyCounter);
        $description = trim((string)($parItem['description'] ?? 'Property Item')) . ' (Assigned office asset)';
        $itemRemarks = withSeedTag('Issued via ' . $parNo . '.', $tag);
        $qty = 1;

        $parItemId = PAR_Items::add(
            $parId,
            $itemId,
            $propertyTag,
            $qty,
            $unitValue,
            $issueDate,
            $description,
            $itemRemarks
        );
        if (!$parItemId) {
            throw new RuntimeException('Failed to add PAR item.');
        }
        $parItemId = (int)$parItemId;
        $summary['par_items']++;

        $itemRepo->reduceQty($itemId, $qty);
        StockInventory::recordMovement(
            $itemId,
            0,
            $qty,
            'PAR Issuance',
            $parId,
            $parNo,
            $itemRemarks,
            $issueDate
        );
        Supply_Ledger_Entry::recordMovement(
            $itemId,
            $issueDate,
            'PAR Issuance',
            $parId,
            $parNo,
            0,
            $qty,
            $unitValue,
            $itemRemarks,
            $propertyCustodianId
        );

        auditRecord(
            $audit,
            $propertyCustodianId,
            'par.item.add.success',
            'par_items',
            $parItemId,
            $tag,
            [
                'par_id' => $parId,
                'par_no' => $parNo,
                'item_id' => $itemId,
                'property_no' => $propertyTag,
            ],
            [
                'qty' => ['old' => null, 'new' => (string)$qty],
                'unit_value' => ['old' => null, 'new' => (string)$unitValue],
            ]
        );

        $cardNo = PropertyCard::getNextCardNo();
        $cardRemarks = withSeedTag('Initial card registration for ' . $propertyTag . '.', $tag);
        $location = $departmentName !== '' ? $departmentName : $departmentCode;
        $cardId = PropertyCard::add(
            $cardNo,
            $parId,
            $itemId,
            $propertyTag,
            $employeeId,
            $location,
            $issueDate,
            $unitValue,
            'Assigned',
            $cardRemarks
        );
        if (!$cardId) {
            throw new RuntimeException('Failed to create property card.');
        }
        $cardId = (int)$cardId;
        $summary['property_cards']++;
        $summary['status']['card_assigned']++;
        if (count($summary['sample']['card']) < 6) {
            $summary['sample']['card'][] = $cardNo;
        }

        auditRecord(
            $audit,
            $propertyCustodianId,
            'property_card.create.success',
            'property_cards',
            $cardId,
            $tag,
            [
                'card_no' => $cardNo,
                'property_tag' => $propertyTag,
                'par_id' => $parId,
            ]
        );

        $officeLabel = formatOfficerNameWithDeptCode($employee, $employeeId);
        $txnNotes = withSeedTag('Initial accountability posting under ' . $parNo . '.', $tag);
        $txnId = PropertyCardTransaction::add(
            $cardId,
            $issueDate,
            'Assigned',
            $parNo,
            $txnNotes,
            $propertyCustodianId,
            1,
            0,
            $officeLabel,
            $employeeId,
            null,
            $unitValue
        );
        if (!$txnId) {
            throw new RuntimeException('Failed to add initial property card transaction.');
        }
        $summary['card_transactions']++;
        recalculateCardLedger($cardId);
        PropertyCard::updateStatus($cardId, 'Assigned');

        auditRecord(
            $audit,
            $propertyCustodianId,
            'property_card.transaction.add.success',
            'property_card_transactions',
            (int)$txnId,
            $tag,
            [
                'property_card_id' => $cardId,
                'status' => 'Assigned',
                'reference_no' => $parNo,
            ],
            [
                'receipt_qty' => ['old' => null, 'new' => '1'],
                'issue_qty' => ['old' => null, 'new' => '0'],
            ]
        );

        $seededParCards[] = [
            'par_id' => $parId,
            'par_no' => $parNo,
            'fund_cluster' => $fundCluster,
            'par_item_id' => $parItemId,
            'item_id' => $itemId,
            'property_tag' => $propertyTag,
            'qty' => $qty,
            'unit_value' => $unitValue,
            'acquisition_date' => $issueDate,
            'description' => $description,
            'remarks' => $itemRemarks,
            'accountable_officer' => $employeeId,
            'accountable_user' => $employee,
            'card_id' => $cardId,
            'card_no' => $cardNo,
            'location' => $location,
            'issue_date' => $issueDate,
        ];
    }

    if (!empty($seededParCards) && $transferCount > 0) {
        $transferCandidates = $seededParCards;
        shuffle($transferCandidates);
        $transferCandidates = array_slice($transferCandidates, 0, min($transferCount, count($transferCandidates)));

        foreach ($transferCandidates as $candidate) {
            $fromOfficerId = (int)$candidate['accountable_officer'];
            $toOfficer = pickDifferentEmployee($activeEmployees, $fromOfficerId);
            if (!$toOfficer) {
                continue;
            }

            $issueDateObj = new DateTimeImmutable((string)$candidate['issue_date']);
            $transferDateObj = clampDate($issueDateObj->modify('+' . mt_rand(3, 9) . ' day'), $today);
            if ($transferDateObj <= $issueDateObj) {
                $nextDay = $issueDateObj->modify('+1 day');
                if ($nextDay > $today) {
                    continue;
                }
                $transferDateObj = $nextDay;
            }
            $transferDate = $transferDateObj->format('Y-m-d');
            $toOfficerId = (int)$toOfficer['id'];
            $fromDepartment = (int)($candidate['accountable_user']['departmentid'] ?? 0);
            $toDepartment = (int)($toOfficer['departmentid'] ?? 0);
            $transferNo = PropertyTransfer::getNextTransferNo();
            $reason = withSeedTag('Department-endorsed accountability transfer for operational reassignment.', $tag);

            $transferId = PropertyTransfer::add(
                $transferNo,
                $transferDate,
                (string)$candidate['property_tag'],
                (int)$candidate['item_id'],
                $fromOfficerId,
                $toOfficerId,
                $fromDepartment > 0 ? $fromDepartment : null,
                $toDepartment > 0 ? $toDepartment : null,
                'Personnel',
                $reason,
                $propertyCustodianId,
                $systemAdminId,
                $toOfficerId,
                'Completed'
            );
            if (!$transferId) {
                throw new RuntimeException('Failed to create property transfer.');
            }
            $transferId = (int)$transferId;
            $summary['transfers']++;
            if (count($summary['sample']['transfer']) < 6) {
                $summary['sample']['transfer'][] = $transferNo;
            }

            auditRecord(
                $audit,
                $propertyCustodianId,
                'property_transfer.create.success',
                'property_transfers',
                $transferId,
                $tag,
                [
                    'transfer_no' => $transferNo,
                    'property_tag' => $candidate['property_tag'],
                    'from_officer' => $fromOfficerId,
                    'to_officer' => $toOfficerId,
                ]
            );

            $targetParRemarks = withSeedTag('Auto-created from completed transfer ' . $transferNo . '.', $tag);
            $targetParNo = PAR::getNextPARNo();
            $targetParId = PAR::add(
                $targetParNo,
                (string)$candidate['fund_cluster'],
                $toOfficerId,
                $propertyCustodianId,
                $transferDate,
                'Active',
                $targetParRemarks
            );
            if (!$targetParId) {
                throw new RuntimeException('Failed to auto-create target PAR during transfer.');
            }
            $targetParId = (int)$targetParId;
            $summary['par_headers']++;
            $summary['auto_target_par']++;
            $summary['status']['par_active']++;

            auditRecord(
                $audit,
                $propertyCustodianId,
                'par.create.success',
                'par',
                $targetParId,
                $tag,
                [
                    'par_no' => $targetParNo,
                    'auto_generated' => true,
                    'from_transfer' => $transferNo,
                ]
            );

            $moved = PAR_Items::update(
                $targetParId,
                (int)$candidate['item_id'],
                (string)$candidate['property_tag'],
                (int)$candidate['qty'],
                (float)$candidate['unit_value'],
                (string)$candidate['acquisition_date'],
                (string)$candidate['description'],
                (string)$candidate['remarks'],
                (int)$candidate['par_item_id']
            );
            if (!$moved) {
                throw new RuntimeException('Failed to move PAR item to target PAR.');
            }

            $sourceParId = (int)$candidate['par_id'];
            if (countParItems($db, $sourceParId) === 0) {
                $snapshotRemarks = appendRemark((string)$candidate['remarks'], 'Transferred/relieved via ' . $transferNo . ' (history snapshot).');
                $snapshotId = PAR_Items::add(
                    $sourceParId,
                    (int)$candidate['item_id'],
                    (string)$candidate['property_tag'],
                    (int)$candidate['qty'],
                    (float)$candidate['unit_value'],
                    (string)$candidate['acquisition_date'],
                    (string)$candidate['description'],
                    $snapshotRemarks
                );
                if (!$snapshotId) {
                    throw new RuntimeException('Failed to create source PAR history snapshot.');
                }

                $sourceStatusOk = PAR::updateStatus('Transferred', $sourceParId);
                if (!$sourceStatusOk) {
                    throw new RuntimeException('Failed to mark source PAR as Transferred.');
                }
                $summary['status']['par_transferred']++;
                $summary['status']['par_active'] = max(0, $summary['status']['par_active'] - 1);

                auditRecord(
                    $audit,
                    $propertyCustodianId,
                    'par.status.update.success',
                    'par',
                    $sourceParId,
                    $tag,
                    [
                        'new_status' => 'Transferred',
                        'reference_transfer' => $transferNo,
                    ]
                );
            }

            $cardId = (int)$candidate['card_id'];
            $card = (new PropertyCard())->getById($cardId);
            if (!$card) {
                throw new RuntimeException('Property card not found during transfer auto-apply.');
            }
            $newLocation = departmentNameById($db, $toDepartment);
            if ($newLocation === '') {
                $newLocation = trim((string)($toOfficer['departmentname'] ?? ''));
            }
            $newCardRemarks = appendRemark((string)($card['remarks'] ?? ''), 'Transferred via ' . $transferNo . '.');
            $cardUpdated = PropertyCard::update(
                (string)$card['card_no'],
                $targetParId,
                (int)$card['item_id'],
                (string)$card['property_tag'],
                $toOfficerId,
                $newLocation,
                !empty($card['acquisition_date']) ? (string)$card['acquisition_date'] : null,
                isset($card['acquisition_cost']) ? (float)$card['acquisition_cost'] : null,
                'Assigned',
                withSeedTag($newCardRemarks, $tag),
                $cardId
            );
            if (!$cardUpdated) {
                throw new RuntimeException('Failed to update property card accountability.');
            }
            auditRecord(
                $audit,
                $propertyCustodianId,
                'property_card.update.success',
                'property_cards',
                $cardId,
                $tag,
                [
                    'transfer_no' => $transferNo,
                    'to_officer' => $toOfficerId,
                    'target_par_id' => $targetParId,
                ]
            );

            $movementQty = max(1, (int)$candidate['qty']);
            $officeLabel = formatOfficerNameWithDeptCode($toOfficer, $toOfficerId);
            $movementNotes = withSeedTag('Auto transfer posting from officer #' . $fromOfficerId . ' to officer #' . $toOfficerId . '.', $tag);
            $movementAmount = (float)$candidate['unit_value'] * $movementQty;

            $transferTxnId = PropertyCardTransaction::add(
                $cardId,
                $transferDate,
                'Assigned',
                $transferNo,
                $movementNotes,
                $propertyCustodianId,
                $movementQty,
                $movementQty,
                $officeLabel,
                $toOfficerId,
                null,
                $movementAmount
            );
            if (!$transferTxnId) {
                throw new RuntimeException('Failed to add transfer property card movement.');
            }
            $summary['card_transactions']++;
            recalculateCardLedger($cardId);
            PropertyCard::updateStatus($cardId, 'Assigned');

            auditRecord(
                $audit,
                $propertyCustodianId,
                'property_card.transaction.add.success',
                'property_card_transactions',
                (int)$transferTxnId,
                $tag,
                [
                    'property_card_id' => $cardId,
                    'reference_no' => $transferNo,
                    'status' => 'Assigned',
                ],
                [
                    'receipt_qty' => ['old' => null, 'new' => (string)$movementQty],
                    'issue_qty' => ['old' => null, 'new' => (string)$movementQty],
                ]
            );

            auditRecord(
                $audit,
                $propertyCustodianId,
                'property_transfer.auto_apply.success',
                'property_transfers',
                $transferId,
                $tag,
                [
                    'transfer_no' => $transferNo,
                    'property_tag' => $candidate['property_tag'],
                    'target_par_id' => $targetParId,
                    'property_card_id' => $cardId,
                ]
            );

            $transferredCardIds[$cardId] = true;
        }
    }

    if (!empty($seededParCards) && $repairCount > 0) {
        $repairCandidates = array_values(array_filter($seededParCards, static function (array $row) use ($transferredCardIds): bool {
            return !isset($transferredCardIds[(int)$row['card_id']]);
        }));
        shuffle($repairCandidates);
        $repairCandidates = array_slice($repairCandidates, 0, min($repairCount, count($repairCandidates)));

        foreach ($repairCandidates as $candidate) {
            $cardId = (int)$candidate['card_id'];
            $card = (new PropertyCard())->getById($cardId);
            if (!$card) {
                continue;
            }
            $accountableOfficerId = (int)($card['accountable_officer'] ?? 0);
            $accountableOfficer = null;
            foreach ($activeEmployees as $emp) {
                if ((int)$emp['id'] === $accountableOfficerId) {
                    $accountableOfficer = $emp;
                    break;
                }
            }
            if (!$accountableOfficer) {
                continue;
            }

            $issueDateObj = new DateTimeImmutable((string)$candidate['issue_date']);
            $repairDateObj = clampDate($issueDateObj->modify('+' . mt_rand(2, 5) . ' day'), $today);
            if ($repairDateObj <= $issueDateObj) {
                continue;
            }
            $returnDateObj = clampDate($repairDateObj->modify('+' . mt_rand(1, 2) . ' day'), $today);
            if ($returnDateObj < $repairDateObj) {
                $returnDateObj = $repairDateObj;
            }

            $memoCounter++;
            $repairRef = 'MR-2026-' . str_pad((string)$memoCounter, 4, '0', STR_PAD_LEFT);
            $repairNotes = withSeedTag('Sent for repair and returned to serviceable state.', $tag);
            $officeLabel = formatOfficerNameWithDeptCode($accountableOfficer, $accountableOfficerId);
            $amount = (float)($card['acquisition_cost'] ?? 0);

            $repairTxnId = PropertyCardTransaction::add(
                $cardId,
                $repairDateObj->format('Y-m-d'),
                'For Repair',
                $repairRef,
                $repairNotes,
                $propertyCustodianId,
                1,
                1,
                $officeLabel,
                $accountableOfficerId,
                null,
                $amount
            );
            if ($repairTxnId) {
                $summary['card_transactions']++;
                $summary['status']['card_for_repair']++;
                recalculateCardLedger($cardId);
                PropertyCard::updateStatus($cardId, 'For Repair');
                auditRecord(
                    $audit,
                    $propertyCustodianId,
                    'property_card.transaction.add.success',
                    'property_card_transactions',
                    (int)$repairTxnId,
                    $tag,
                    [
                        'property_card_id' => $cardId,
                        'reference_no' => $repairRef,
                        'status' => 'For Repair',
                    ]
                );
            }

            $returnTxnId = PropertyCardTransaction::add(
                $cardId,
                $returnDateObj->format('Y-m-d'),
                'Assigned',
                $repairRef . '-RTN',
                withSeedTag('Returned from repair and re-assigned to office.', $tag),
                $propertyCustodianId,
                1,
                1,
                $officeLabel,
                $accountableOfficerId,
                null,
                $amount
            );
            if ($returnTxnId) {
                $summary['card_transactions']++;
                recalculateCardLedger($cardId);
                PropertyCard::updateStatus($cardId, 'Assigned');
                auditRecord(
                    $audit,
                    $propertyCustodianId,
                    'property_card.transaction.add.success',
                    'property_card_transactions',
                    (int)$returnTxnId,
                    $tag,
                    [
                        'property_card_id' => $cardId,
                        'reference_no' => $repairRef . '-RTN',
                        'status' => 'Assigned',
                    ]
                );
            }
        }
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    out('Error: ' . $e->getMessage());
    exit(1);
}

out('Seed applied successfully.');
out('');
out('Summary:');
out('  ICS headers               : ' . $summary['ics_headers']);
out('  ICS items                 : ' . $summary['ics_items']);
out('  PAR headers               : ' . $summary['par_headers']);
out('  PAR items                 : ' . $summary['par_items']);
out('  Property cards            : ' . $summary['property_cards']);
out('  Property card txns        : ' . $summary['card_transactions']);
out('  Transfers (Completed)     : ' . $summary['transfers']);
out('  Auto-created target PAR   : ' . $summary['auto_target_par']);
out('');
out('Status notes:');
out('  ICS Active                : ' . $summary['status']['ics_active']);
out('  PAR Active                : ' . $summary['status']['par_active']);
out('  PAR Transferred           : ' . $summary['status']['par_transferred']);
out('  Repair movement cards     : ' . $summary['status']['card_for_repair']);
out('');
if (!empty($summary['sample']['ics'])) {
    out('Sample ICS: ' . implode(', ', $summary['sample']['ics']));
}
if (!empty($summary['sample']['par'])) {
    out('Sample PAR: ' . implode(', ', $summary['sample']['par']));
}
if (!empty($summary['sample']['card'])) {
    out('Sample Cards: ' . implode(', ', $summary['sample']['card']));
}
if (!empty($summary['sample']['transfer'])) {
    out('Sample Transfers: ' . implode(', ', $summary['sample']['transfer']));
}
out('');
out('Audit validation hint: filter activity logs metadata by seed_tag = ' . $tag);
