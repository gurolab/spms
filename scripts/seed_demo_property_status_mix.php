<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../repo/PAR.model.php';
require_once __DIR__ . '/../repo/PropertyCard.model.php';
require_once __DIR__ . '/../repo/PropertyTransfer.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/SupplyLedger.model.php';
require_once __DIR__ . '/../repo/AuditLog.model.php';

date_default_timezone_set('Asia/Manila');

const DEFAULT_SEED = 20260310;
const DEFAULT_TAG = 'DEMO-PROP-MIX-20260310';

function out(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function printUsage(): void
{
    out('Usage: php scripts/seed_demo_property_status_mix.php [--execute] [--seed=20260310] [--tag=DEMO-PROP-MIX-20260310]');
    out('       [--add-pending=1] [--add-cancelled=1]');
}

function withSeedTag(string $text, string $tag): string
{
    return trim($text);
}

function clampDate(DateTimeImmutable $date, DateTimeImmutable $max): DateTimeImmutable
{
    return $date > $max ? $max : $date;
}

function tableHasSeedTag(PDO $db, string $table, string $column, string $needle): bool
{
    $stmt = $db->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` LIKE ? LIMIT 1");
    $stmt->execute(['%' . $needle . '%']);
    return (bool)$stmt->fetchColumn();
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
        'source' => 'scripts/seed_demo_property_status_mix.php',
        'ip' => '127.0.0.1',
        'user_agent' => 'cli-demo-property-status-mix/1.0',
    ], $metadata);

    $audit->record($actorId, $action, $entity, $entityId, $metadata, $details);
}

function getRoleUser(PDO $db, int $roleId): ?array
{
    $stmt = $db->prepare(
        "SELECT * FROM user_role_dept
         WHERE roleid = ?
           AND status = 'Active'
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmt->execute([$roleId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function getActiveEmployees(PDO $db): array
{
    $stmt = $db->query(
        "SELECT * FROM user_role_dept
         WHERE roleid = 6
           AND status = 'Active'
         ORDER BY departmentid ASC, id ASC"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function propertyTagExists(PDO $db, string $propertyTag): bool
{
    $checks = [
        ['par_items', 'property_no'],
        ['property_cards', 'property_tag'],
        ['property_transfers', 'property_tag'],
        ['ics_items', 'property_no'],
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

function pickDifferentEmployee(array $employees, int $excludeId): ?array
{
    $pool = array_values(array_filter($employees, static function (array $emp) use ($excludeId): bool {
        return (int)($emp['id'] ?? 0) !== $excludeId;
    }));
    if (empty($pool)) {
        return null;
    }
    return $pool[array_rand($pool)];
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

$opts = getopt('', ['execute', 'seed::', 'tag::', 'add-pending::', 'add-cancelled::', 'help']);
if (isset($opts['help'])) {
    printUsage();
    exit(0);
}

$execute = isset($opts['execute']);
$seed = isset($opts['seed']) ? (int)$opts['seed'] : DEFAULT_SEED;
$tag = trim((string)($opts['tag'] ?? DEFAULT_TAG));
$addPending = isset($opts['add-pending']) ? max(0, (int)$opts['add-pending']) : 1;
$addCancelled = isset($opts['add-cancelled']) ? max(0, (int)$opts['add-cancelled']) : 1;

mt_srand($seed);

out('Demo property status mix seeder');
out('Seed: ' . $seed);
out('Tag : ' . $tag);
out('Pending transfers to add  : ' . $addPending);
out('Cancelled transfers to add: ' . $addCancelled);
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

$propertyCustodian = getRoleUser($db, 4);
if (!$propertyCustodian) {
    out('Error: Active Property Custodian account is required.');
    exit(1);
}
$propertyCustodianId = (int)$propertyCustodian['id'];
$systemAdmin = getRoleUser($db, 1);
$systemAdminId = $systemAdmin ? (int)$systemAdmin['id'] : $propertyCustodianId;

$employees = getActiveEmployees($db);
if (count($employees) < 2) {
    out('Error: At least two active employees are required.');
    exit(1);
}

$itemRepo = new Item();
$items = $itemRepo->getAll();
$assetPool = array_values(array_filter($items, static function (array $item): bool {
    return (int)($item['is_consumable'] ?? 1) === 0 && (int)($item['stock_onhand'] ?? 0) > 0;
}));
usort($assetPool, static fn(array $a, array $b): int => (int)$b['stock_onhand'] <=> (int)$a['stock_onhand']);
if (empty($assetPool)) {
    out('Error: No available non-consumable items with stock on hand.');
    exit(1);
}

$audit = new AuditLog();
$today = new DateTimeImmutable(date('Y-m-d'));
$baseDate = new DateTimeImmutable('2026-03-01');
$propertyTagCounter = 7000;
$memoCounter = 8000;

$summary = [
    'par_headers' => 0,
    'par_items' => 0,
    'cards_created' => 0,
    'card_txns' => 0,
    'transfers_added' => 0,
    'special_status_cards' => [
        'For Repair' => 0,
        'Unserviceable' => 0,
        'Disposed' => 0,
    ],
];

$specialStatuses = ['For Repair', 'Unserviceable', 'Disposed'];

try {
    $db->beginTransaction();

    foreach ($specialStatuses as $idx => $status) {
        $employee = $employees[$idx % count($employees)];
        $employeeId = (int)$employee['id'];
        $departmentCode = strtoupper(trim((string)($employee['departmentcode'] ?? 'EMP')));

        $issueDateObj = clampDate($baseDate->modify('+' . $idx . ' day'), $today);
        $issueDate = $issueDateObj->format('Y-m-d');
        $parNo = PAR::getNextPARNo();
        $fundCluster = 'FC-' . $departmentCode . '-STATUS-MIX';
        $parRemarks = withSeedTag('Property issuance for department accountability.', $tag);

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
            throw new RuntimeException('Failed to create PAR for status mix.');
        }
        $parId = (int)$parId;
        $summary['par_headers']++;

        auditRecord(
            $audit,
            $propertyCustodianId,
            'par.create.success',
            'par',
            $parId,
            $tag,
            ['par_no' => $parNo, 'accountable_officer' => $employeeId]
        );

        $item = $assetPool[array_rand($assetPool)];
        $itemId = (int)$item['id'];
        $itemCurrent = $itemRepo->getById($itemId);
        if (!$itemCurrent || (int)$itemCurrent['stock_onhand'] < 1) {
            throw new RuntimeException('Insufficient stock for item id ' . $itemId . ' during status mix seeding.');
        }
        $unitValue = (float)($itemCurrent['unit_cost'] ?? 0);
        if ($unitValue <= 0) {
            $unitValue = 1000;
        }
        $propertyTag = generateUniquePropertyTag($db, 'MIX-' . $departmentCode, $propertyTagCounter);
        $description = trim((string)($itemCurrent['description'] ?? 'Property Item')) . ' (Department asset)';
        $parItemRemarks = withSeedTag('Issued under ' . $parNo . '.', $tag);

        $parItemId = PAR_Items::add(
            $parId,
            $itemId,
            $propertyTag,
            1,
            $unitValue,
            $issueDate,
            $description,
            $parItemRemarks
        );
        if (!$parItemId) {
            throw new RuntimeException('Failed to add PAR item for status mix.');
        }
        $summary['par_items']++;

        $itemRepo->reduceQty($itemId, 1);
        StockInventory::recordMovement(
            $itemId,
            0,
            1,
            'PAR Issuance',
            $parId,
            $parNo,
            $parItemRemarks,
            $issueDate
        );
        Supply_Ledger_Entry::recordMovement(
            $itemId,
            $issueDate,
            'PAR Issuance',
            $parId,
            $parNo,
            0,
            1,
            $unitValue,
            $parItemRemarks,
            $propertyCustodianId
        );

        auditRecord(
            $audit,
            $propertyCustodianId,
            'par.item.add.success',
            'par_items',
            (int)$parItemId,
            $tag,
            ['par_id' => $parId, 'item_id' => $itemId, 'property_no' => $propertyTag]
        );

        $cardNo = PropertyCard::getNextCardNo();
        $cardRemarks = withSeedTag('Property card created for accountability tracking.', $tag);
        $location = trim((string)($employee['departmentname'] ?? $departmentCode));
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
            throw new RuntimeException('Failed to create property card for status mix.');
        }
        $cardId = (int)$cardId;
        $summary['cards_created']++;

        auditRecord(
            $audit,
            $propertyCustodianId,
            'property_card.create.success',
            'property_cards',
            $cardId,
            $tag,
            ['card_no' => $cardNo, 'property_tag' => $propertyTag]
        );

        $officeLabel = formatOfficerNameWithDeptCode($employee, $employeeId);
        $initialTxn = PropertyCardTransaction::add(
            $cardId,
            $issueDate,
            'Assigned',
            $parNo,
            withSeedTag('Initial accountability posting.', $tag),
            $propertyCustodianId,
            1,
            0,
            $officeLabel,
            $employeeId,
            null,
            $unitValue
        );
        if (!$initialTxn) {
            throw new RuntimeException('Failed to create initial property card transaction.');
        }
        $summary['card_txns']++;

        auditRecord(
            $audit,
            $propertyCustodianId,
            'property_card.transaction.add.success',
            'property_card_transactions',
            (int)$initialTxn,
            $tag,
            ['property_card_id' => $cardId, 'status' => 'Assigned', 'reference_no' => $parNo]
        );

        $memoCounter++;
        $statusRef = 'STATUS-' . $memoCounter;
        $statusDateObj = clampDate($issueDateObj->modify('+2 day'), $today);
        if ($statusDateObj <= $issueDateObj) {
            $statusDateObj = $issueDateObj;
        }
        $statusDate = $statusDateObj->format('Y-m-d');

        $receiptQty = 1;
        $issueQty = 1;
        $amount = $unitValue;
        if ($status === 'Unserviceable' || $status === 'Disposed') {
            $receiptQty = 0;
            $issueQty = 1;
            $amount = 0.0;
        }

        $statusTxn = PropertyCardTransaction::add(
            $cardId,
            $statusDate,
            $status,
            $statusRef,
            withSeedTag('Status update to ' . $status . '.', $tag),
            $propertyCustodianId,
            $receiptQty,
            $issueQty,
            $officeLabel,
            $employeeId,
            null,
            $amount
        );
        if (!$statusTxn) {
            throw new RuntimeException('Failed to create property card status transaction for ' . $status . '.');
        }
        $summary['card_txns']++;
        recalculateCardLedger($cardId);
        PropertyCard::updateStatus($cardId, $status);
        $summary['special_status_cards'][$status]++;

        auditRecord(
            $audit,
            $propertyCustodianId,
            'property_card.transaction.add.success',
            'property_card_transactions',
            (int)$statusTxn,
            $tag,
            ['property_card_id' => $cardId, 'status' => $status, 'reference_no' => $statusRef],
            [
                'receipt_qty' => ['old' => null, 'new' => (string)$receiptQty],
                'issue_qty' => ['old' => null, 'new' => (string)$issueQty],
            ]
        );

        auditRecord(
            $audit,
            $propertyCustodianId,
            'property_card.status.update.success',
            'property_cards',
            $cardId,
            $tag,
            ['new_status' => $status, 'reference_no' => $statusRef]
        );
    }

    $cardStatusStmt = $db->query(
        "SELECT current_status, COUNT(*) c
         FROM property_cards
         GROUP BY current_status"
    );
    $cardCounts = [];
    $totalCards = 0;
    foreach ($cardStatusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (string)$row['current_status'];
        $count = (int)$row['c'];
        $cardCounts[$status] = $count;
        $totalCards += $count;
    }
    $assignedCards = (int)($cardCounts['Assigned'] ?? 0);
    $assignedRatio = $totalCards > 0 ? ($assignedCards / $totalCards) : 0.0;
    if ($assignedRatio < 0.70) {
        throw new RuntimeException('Assigned card ratio would fall below 70%.');
    }

    $transferStatusStmt = $db->query(
        "SELECT status, COUNT(*) c
         FROM property_transfers
         GROUP BY status"
    );
    $transferCounts = ['Completed' => 0, 'Pending' => 0, 'Cancelled' => 0];
    foreach ($transferStatusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $transferCounts[(string)$row['status']] = (int)$row['c'];
    }

    $completedBefore = (int)$transferCounts['Completed'];
    $nonCompletedBefore = (int)$transferCounts['Pending'] + (int)$transferCounts['Cancelled'];
    $nonCompletedToAdd = $addPending + $addCancelled;
    $completedRatioAfter = $completedBefore / max(1, $completedBefore + $nonCompletedBefore + $nonCompletedToAdd);
    if ($completedRatioAfter < 0.70) {
        throw new RuntimeException(
            'Adding requested pending/cancelled transfers would reduce Completed ratio below 70%.'
        );
    }

    $cardsStmt = $db->query(
        "SELECT c.*, u.departmentid, u.departmentname
         FROM property_cards c
         LEFT JOIN user_role_dept u ON u.id = c.accountable_officer
         ORDER BY c.id ASC"
    );
    $cards = $cardsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cards)) {
        throw new RuntimeException('No property cards available for transfer seeding.');
    }

    $statusesToAdd = array_merge(
        array_fill(0, $addPending, 'Pending'),
        array_fill(0, $addCancelled, 'Cancelled')
    );
    foreach ($statusesToAdd as $status) {
        $card = $cards[array_rand($cards)];
        $propertyTag = trim((string)($card['property_tag'] ?? ''));
        $itemId = (int)($card['item_id'] ?? 0);
        $fromOfficer = (int)($card['accountable_officer'] ?? 0);
        if ($propertyTag === '' || $itemId <= 0 || $fromOfficer <= 0) {
            continue;
        }

        $toOfficer = pickDifferentEmployee($employees, $fromOfficer);
        if (!$toOfficer) {
            continue;
        }
        $toOfficerId = (int)$toOfficer['id'];
        $fromDepartment = (int)($card['departmentid'] ?? 0);
        $toDepartment = (int)($toOfficer['departmentid'] ?? 0);

        $acqDateRaw = trim((string)($card['acquisition_date'] ?? ''));
        $acqDateObj = $acqDateRaw !== '' ? new DateTimeImmutable($acqDateRaw) : $baseDate;
        $transferDateObj = clampDate($acqDateObj->modify('+' . mt_rand(5, 18) . ' day'), $today);
        if ($transferDateObj < $acqDateObj) {
            $transferDateObj = $acqDateObj;
        }
        $transferDate = $transferDateObj->format('Y-m-d');
        $transferNo = PropertyTransfer::getNextTransferNo();
        $reason = withSeedTag('Transfer request recorded with status ' . $status . '.', $tag);

        $transferId = PropertyTransfer::add(
            $transferNo,
            $transferDate,
            $propertyTag,
            $itemId,
            $fromOfficer,
            $toOfficerId,
            $fromDepartment > 0 ? $fromDepartment : null,
            $toDepartment > 0 ? $toDepartment : null,
            'Personnel',
            $reason,
            $propertyCustodianId,
            $systemAdminId,
            $toOfficerId,
            $status
        );
        if (!$transferId) {
            throw new RuntimeException('Failed to create transfer with status ' . $status . '.');
        }
        $summary['transfers_added']++;

        auditRecord(
            $audit,
            $propertyCustodianId,
            'property_transfer.create.success',
            'property_transfers',
            (int)$transferId,
            $tag,
            [
                'transfer_no' => $transferNo,
                'status' => $status,
                'property_tag' => $propertyTag,
                'from_officer' => $fromOfficer,
                'to_officer' => $toOfficerId,
            ]
        );
    }

    $ratioStmt = $db->query(
        "SELECT
            SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) completed_count,
            COUNT(*) total_count
         FROM property_transfers"
    );
    $ratioRow = $ratioStmt->fetch(PDO::FETCH_ASSOC);
    $completedCount = (int)($ratioRow['completed_count'] ?? 0);
    $totalTransfers = (int)($ratioRow['total_count'] ?? 0);
    $completedRatio = $totalTransfers > 0 ? ($completedCount / $totalTransfers) : 1.0;
    if ($completedRatio < 0.70) {
        throw new RuntimeException('Completed transfer ratio dropped below 70% after seeding.');
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    out('Error: ' . $e->getMessage());
    exit(1);
}

$cardDist = [];
$stmt = $db->query("SELECT current_status, COUNT(*) c FROM property_cards GROUP BY current_status ORDER BY current_status");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cardDist[(string)$row['current_status']] = (int)$row['c'];
}
$assigned = (int)($cardDist['Assigned'] ?? 0);
$totalCards = array_sum($cardDist);
$assignedRatioPct = $totalCards > 0 ? (($assigned / $totalCards) * 100) : 0;

$transferDist = [];
$stmt = $db->query("SELECT status, COUNT(*) c FROM property_transfers GROUP BY status ORDER BY status");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $transferDist[(string)$row['status']] = (int)$row['c'];
}
$completed = (int)($transferDist['Completed'] ?? 0);
$totalTransfers = array_sum($transferDist);
$completedRatioPct = $totalTransfers > 0 ? (($completed / $totalTransfers) * 100) : 0;

out('Seed applied successfully.');
out('');
out('Added records:');
out('  PAR headers              : ' . $summary['par_headers']);
out('  PAR items                : ' . $summary['par_items']);
out('  Property cards           : ' . $summary['cards_created']);
out('  Property card txns       : ' . $summary['card_txns']);
out('  Transfers (non-completed): ' . $summary['transfers_added']);
out('');
out('Special card statuses added:');
foreach ($summary['special_status_cards'] as $status => $count) {
    out('  - ' . str_pad($status, 13, ' ', STR_PAD_RIGHT) . ': ' . $count);
}
out('');
out('Current property card status distribution:');
foreach ($cardDist as $status => $count) {
    out('  - ' . str_pad($status, 13, ' ', STR_PAD_RIGHT) . ': ' . $count);
}
out('  Assigned ratio: ' . number_format($assignedRatioPct, 2) . '%');
out('');
out('Current transfer status distribution:');
foreach ($transferDist as $status => $count) {
    out('  - ' . str_pad($status, 9, ' ', STR_PAD_RIGHT) . ': ' . $count);
}
out('  Completed ratio: ' . number_format($completedRatioPct, 2) . '%');
out('');
out('Audit validation hint: filter activity logs metadata by seed_tag = ' . $tag);
