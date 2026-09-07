<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/Model.php';

const MARKER_PATTERN = '/\s*\[DEMO-SEED:[^\]]+\]/i';

function out(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function printUsage(): void
{
    out('Usage: php scripts/cleanup_demo_seed_notes.php [--execute]');
    out('');
    out('Flags:');
    out('  --execute   Apply cleanup updates to the database.');
    out('  --help      Show this help text.');
}

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function getColumnMap(PDO $db, string $table): array
{
    $stmt = $db->query('SHOW COLUMNS FROM `' . $table . '`');
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $name = trim((string)($column['Field'] ?? ''));
        if ($name !== '') {
            $columns[$name] = $column;
        }
    }
    return $columns;
}

function getPrimaryKeyColumn(array $columnMap): ?string
{
    foreach ($columnMap as $name => $meta) {
        if ((string)($meta['Key'] ?? '') === 'PRI') {
            return $name;
        }
    }
    return null;
}

function cleanDemoSeedMarker(string $text): string
{
    $cleaned = (string)preg_replace(MARKER_PATTERN, '', $text);
    $cleaned = (string)preg_replace('/\s{2,}/', ' ', $cleaned);
    return trim($cleaned);
}

$opts = getopt('', ['execute', 'help']);
if (isset($opts['help'])) {
    printUsage();
    exit(0);
}

$execute = isset($opts['execute']);

out('Demo-seed marker cleanup utility');
out($execute ? 'Mode: EXECUTE' : 'Mode: DRY RUN');
out('');

$targets = [
    ['inventory_custodian_slips', 'remarks'],
    ['ics_items', 'remarks'],
    ['property_acknowledgment_receipts', 'remarks'],
    ['par_items', 'remarks'],
    ['property_cards', 'remarks'],
    ['property_card_transactions', 'notes'],
    ['property_transfers', 'reason'],
    ['requisition_slips', 'remarks'],
    ['requisition_items', 'remarks'],
    ['supply_issuances', 'remarks'],
    ['supply_issuance_items', 'remarks'],
    ['stock_transactions', 'remarks'],
    ['supply_ledger_entries', 'remarks'],
];

$db = Model::Db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$summary = [];
$totalCandidates = 0;
$totalUpdated = 0;

try {
    if ($execute) {
        $db->beginTransaction();
    }

    foreach ($targets as [$table, $column]) {
        if (!tableExists($db, $table)) {
            $summary[] = [$table, $column, 'skipped (table missing)', 0, 0];
            continue;
        }

        $columnMap = getColumnMap($db, $table);
        if (!isset($columnMap[$column])) {
            $summary[] = [$table, $column, 'skipped (column missing)', 0, 0];
            continue;
        }

        $pkColumn = getPrimaryKeyColumn($columnMap);
        if ($pkColumn === null) {
            $summary[] = [$table, $column, 'skipped (no primary key)', 0, 0];
            continue;
        }

        $scanStmt = $db->prepare(
            'SELECT `' . $pkColumn . '` AS row_id, `' . $column . '` AS text_value
             FROM `' . $table . '`
             WHERE `' . $column . '` LIKE ?'
        );
        $scanStmt->execute(['%[DEMO-SEED:%']);

        $candidateCount = 0;
        $updatedCount = 0;

        $updateStmt = $db->prepare(
            'UPDATE `' . $table . '`
             SET `' . $column . '` = ?
             WHERE `' . $pkColumn . '` = ?'
        );

        while ($row = $scanStmt->fetch(PDO::FETCH_ASSOC)) {
            $candidateCount++;
            $rowId = $row['row_id'] ?? null;
            $original = (string)($row['text_value'] ?? '');
            $cleaned = cleanDemoSeedMarker($original);

            if ($cleaned === $original) {
                continue;
            }

            if ($execute) {
                $updateStmt->execute([$cleaned, $rowId]);
            }
            $updatedCount++;
        }

        $totalCandidates += $candidateCount;
        $totalUpdated += $updatedCount;
        $summary[] = [$table, $column, 'ok', $candidateCount, $updatedCount];
    }

    if ($execute) {
        $db->commit();
    }
} catch (Throwable $e) {
    if ($execute && $db->inTransaction()) {
        $db->rollBack();
    }

    out('Error: ' . $e->getMessage());
    exit(1);
}

out('Results:');
foreach ($summary as [$table, $column, $status, $candidates, $updated]) {
    out('  - ' . $table . '.' . $column . ' => ' . $status . '; candidates=' . $candidates . '; updated=' . $updated);
}
out('');
out('Totals:');
out('  Candidate rows: ' . $totalCandidates);
out('  Rows cleaned  : ' . $totalUpdated);
out('');

if (!$execute) {
    out('Dry run complete. Re-run with --execute to apply the cleanup.');
} else {
    out('Cleanup applied successfully.');
}
