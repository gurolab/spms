<?php

require_once __DIR__ . '/../core/config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
$pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.tables
         WHERE table_schema = ?
           AND table_name = ?
         LIMIT 1"
    );
    $stmt->execute([DB_NAME, $table]);
    return (bool)$stmt->fetchColumn();
}

function columnsExist(PDO $db, string $table, array $columns): bool
{
    foreach ($columns as $column) {
        $stmt = $db->prepare(
            "SELECT 1
             FROM information_schema.columns
             WHERE table_schema = ?
               AND table_name = ?
               AND column_name = ?
             LIMIT 1"
        );
        $stmt->execute([DB_NAME, $table, $column]);
        if (!$stmt->fetchColumn()) {
            return false;
        }
    }

    return true;
}

function indexExists(PDO $db, string $table, string $indexName): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.statistics
         WHERE table_schema = ?
           AND table_name = ?
           AND index_name = ?
         LIMIT 1"
    );
    $stmt->execute([DB_NAME, $table, $indexName]);
    return (bool)$stmt->fetchColumn();
}

function constraintExists(PDO $db, string $table, string $constraintName): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.table_constraints
         WHERE table_schema = ?
           AND table_name = ?
           AND constraint_name = ?
         LIMIT 1"
    );
    $stmt->execute([DB_NAME, $table, $constraintName]);
    return (bool)$stmt->fetchColumn();
}

function hasUniqueDuplicates(PDO $db, string $table, array $columns): bool
{
    $groupBy = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns));
    $sql = "SELECT 1 FROM `{$table}` GROUP BY {$groupBy} HAVING COUNT(*) > 1 LIMIT 1";
    return (bool)$db->query($sql)->fetchColumn();
}

function foreignKeyHasOrphans(PDO $db, array $definition): bool
{
    $tableColumn = $definition['columns'][0];
    $refColumn = $definition['ref_columns'][0];
    $sql = sprintf(
        "SELECT 1
         FROM `%s` c
         LEFT JOIN `%s` p ON p.`%s` = c.`%s`
         WHERE c.`%s` IS NOT NULL
           AND c.`%s` > 0
           AND p.`%s` IS NULL
         LIMIT 1",
        $definition['table'],
        $definition['ref_table'],
        $refColumn,
        $tableColumn,
        $tableColumn,
        $tableColumn,
        $refColumn
    );

    return (bool)$db->query($sql)->fetchColumn();
}

function addIndex(PDO $db, array $definition): string
{
    $type = !empty($definition['unique']) ? 'UNIQUE INDEX' : 'INDEX';
    $columns = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $definition['columns']));
    $sql = sprintf(
        "ALTER TABLE `%s` ADD %s `%s` (%s)",
        $definition['table'],
        $type,
        $definition['name'],
        $columns
    );
    $db->exec($sql);
    return $sql;
}

function addForeignKey(PDO $db, array $definition): string
{
    $columns = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $definition['columns']));
    $refColumns = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $definition['ref_columns']));
    $sql = sprintf(
        "ALTER TABLE `%s`
         ADD CONSTRAINT `%s`
         FOREIGN KEY (%s)
         REFERENCES `%s` (%s)
         ON UPDATE %s
         ON DELETE %s",
        $definition['table'],
        $definition['name'],
        $columns,
        $definition['ref_table'],
        $refColumns,
        $definition['on_update'],
        $definition['on_delete']
    );
    $db->exec($sql);
    return $sql;
}

$indexes = [
    ['table' => 'stock_cards', 'name' => 'ux_stock_cards_item_id', 'columns' => ['item_id'], 'unique' => true],
    ['table' => 'stock_cards', 'name' => 'ux_stock_cards_card_no', 'columns' => ['card_no'], 'unique' => true],
    ['table' => 'stock_inventory', 'name' => 'ux_stock_inventory_item_id', 'columns' => ['item_id'], 'unique' => true],
    ['table' => 'stock_transactions', 'name' => 'idx_stock_transactions_item_id', 'columns' => ['item_id'], 'unique' => false],
    ['table' => 'stock_transactions', 'name' => 'idx_stock_transactions_stock_card_id', 'columns' => ['stock_card_id'], 'unique' => false],
    ['table' => 'stock_transactions', 'name' => 'idx_stock_transactions_reference', 'columns' => ['reference_type', 'reference_id'], 'unique' => false],
    ['table' => 'stock_transactions', 'name' => 'idx_stock_transactions_transaction_date', 'columns' => ['transaction_date'], 'unique' => false],
    ['table' => 'supply_ledger_cards', 'name' => 'ux_supply_ledger_cards_item_id', 'columns' => ['item_id'], 'unique' => true],
    ['table' => 'supply_ledger_cards', 'name' => 'ux_supply_ledger_cards_ledger_no', 'columns' => ['ledger_no'], 'unique' => true],
    ['table' => 'supply_ledger_entries', 'name' => 'idx_supply_ledger_entries_ledger_id', 'columns' => ['ledger_id'], 'unique' => false],
    ['table' => 'supply_ledger_entries', 'name' => 'idx_supply_ledger_entries_reference', 'columns' => ['reference_type', 'reference_id'], 'unique' => false],
    ['table' => 'supply_ledger_entries', 'name' => 'idx_supply_ledger_entries_entry_date', 'columns' => ['entry_date'], 'unique' => false],
    ['table' => 'requisition_items', 'name' => 'idx_requisition_items_ris_id', 'columns' => ['ris_id'], 'unique' => false],
    ['table' => 'requisition_items', 'name' => 'idx_requisition_items_item_id', 'columns' => ['item_id'], 'unique' => false],
    ['table' => 'requisition_slips', 'name' => 'idx_requisition_slips_status', 'columns' => ['status'], 'unique' => false],
    ['table' => 'requisition_slips', 'name' => 'idx_requisition_slips_division', 'columns' => ['division'], 'unique' => false],
    ['table' => 'requisition_slips', 'name' => 'idx_requisition_slips_requested_by', 'columns' => ['requested_by'], 'unique' => false],
    ['table' => 'supply_receipt_items', 'name' => 'idx_supply_receipt_items_receipt_header_id', 'columns' => ['receipt_header_id'], 'unique' => false],
    ['table' => 'supply_receipt_items', 'name' => 'idx_supply_receipt_items_item_id', 'columns' => ['item_id'], 'unique' => false],
    ['table' => 'supply_receipts', 'name' => 'idx_supply_receipts_supplier_id', 'columns' => ['supplier_id'], 'unique' => false],
    ['table' => 'supply_receipts', 'name' => 'idx_supply_receipts_receipt_date', 'columns' => ['receipt_date'], 'unique' => false],
    ['table' => 'supply_issuance_items', 'name' => 'idx_supply_issuance_items_issuance_id', 'columns' => ['issuance_id'], 'unique' => false],
    ['table' => 'supply_issuance_items', 'name' => 'idx_supply_issuance_items_requisition_item_id', 'columns' => ['requisition_item_id'], 'unique' => false],
    ['table' => 'supply_issuance_items', 'name' => 'idx_supply_issuance_items_item_id', 'columns' => ['item_id'], 'unique' => false],
    ['table' => 'supply_issuances', 'name' => 'idx_supply_issuances_ris_id', 'columns' => ['ris_id'], 'unique' => false],
    ['table' => 'supply_issuances', 'name' => 'idx_supply_issuances_status', 'columns' => ['status'], 'unique' => false],
    ['table' => 'supply_issuances', 'name' => 'idx_supply_issuances_issuance_date', 'columns' => ['issuance_date'], 'unique' => false],
];

$foreignKeys = [
    ['table' => 'requisition_items', 'name' => 'fk_requisition_items_ris', 'columns' => ['ris_id'], 'ref_table' => 'requisition_slips', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'requisition_items', 'name' => 'fk_requisition_items_item', 'columns' => ['item_id'], 'ref_table' => 'items', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'supply_receipt_items', 'name' => 'fk_supply_receipt_items_header', 'columns' => ['receipt_header_id'], 'ref_table' => 'supply_receipts', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'supply_receipt_items', 'name' => 'fk_supply_receipt_items_item', 'columns' => ['item_id'], 'ref_table' => 'items', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'supply_issuance_items', 'name' => 'fk_supply_issuance_items_header', 'columns' => ['issuance_id'], 'ref_table' => 'supply_issuances', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'supply_issuance_items', 'name' => 'fk_supply_issuance_items_ris_item', 'columns' => ['requisition_item_id'], 'ref_table' => 'requisition_items', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'supply_issuance_items', 'name' => 'fk_supply_issuance_items_item', 'columns' => ['item_id'], 'ref_table' => 'items', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'stock_inventory', 'name' => 'fk_stock_inventory_item', 'columns' => ['item_id'], 'ref_table' => 'items', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'stock_cards', 'name' => 'fk_stock_cards_item', 'columns' => ['item_id'], 'ref_table' => 'items', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'stock_transactions', 'name' => 'fk_stock_transactions_stock_card', 'columns' => ['stock_card_id'], 'ref_table' => 'stock_cards', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'stock_transactions', 'name' => 'fk_stock_transactions_item', 'columns' => ['item_id'], 'ref_table' => 'items', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'supply_ledger_cards', 'name' => 'fk_supply_ledger_cards_item', 'columns' => ['item_id'], 'ref_table' => 'items', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
    ['table' => 'supply_ledger_entries', 'name' => 'fk_supply_ledger_entries_ledger', 'columns' => ['ledger_id'], 'ref_table' => 'supply_ledger_cards', 'ref_columns' => ['id'], 'on_update' => 'CASCADE', 'on_delete' => 'RESTRICT'],
];

$applied = [];
$skipped = [];

foreach ($indexes as $definition) {
    if (!tableExists($pdo, $definition['table'])) {
        $skipped[] = 'skip index ' . $definition['name'] . ': table missing';
        continue;
    }

    if (!columnsExist($pdo, $definition['table'], $definition['columns'])) {
        $skipped[] = 'skip index ' . $definition['name'] . ': column missing';
        continue;
    }

    if (indexExists($pdo, $definition['table'], $definition['name'])) {
        $skipped[] = 'skip index ' . $definition['name'] . ': already exists';
        continue;
    }

    if (!empty($definition['unique']) && hasUniqueDuplicates($pdo, $definition['table'], $definition['columns'])) {
        $skipped[] = 'skip index ' . $definition['name'] . ': duplicate values present';
        continue;
    }

    $applied[] = addIndex($pdo, $definition);
}

foreach ($foreignKeys as $definition) {
    if (!tableExists($pdo, $definition['table']) || !tableExists($pdo, $definition['ref_table'])) {
        $skipped[] = 'skip fk ' . $definition['name'] . ': table missing';
        continue;
    }

    if (!columnsExist($pdo, $definition['table'], $definition['columns']) || !columnsExist($pdo, $definition['ref_table'], $definition['ref_columns'])) {
        $skipped[] = 'skip fk ' . $definition['name'] . ': column missing';
        continue;
    }

    if (constraintExists($pdo, $definition['table'], $definition['name'])) {
        $skipped[] = 'skip fk ' . $definition['name'] . ': already exists';
        continue;
    }

    if (foreignKeyHasOrphans($pdo, $definition)) {
        $skipped[] = 'skip fk ' . $definition['name'] . ': orphan rows present';
        continue;
    }

    $applied[] = addForeignKey($pdo, $definition);
}

echo 'Applied:' . PHP_EOL;
foreach ($applied as $statement) {
    echo $statement . PHP_EOL;
}

echo PHP_EOL . 'Skipped:' . PHP_EOL;
foreach ($skipped as $message) {
    echo $message . PHP_EOL;
}
