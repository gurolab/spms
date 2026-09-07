<?php

require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/StockInventory.model.php';

class StockTransaction extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'stock_transactions';
    protected static $insertColumns = null;
    protected static $resolvedColumns = null;

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
    }

    protected static function ensureDb(): void
    {
        if (self::$db === null) {
            self::$db = parent::Db();
        }
    }

    protected static function resolveInsertColumns(): array
    {
        self::ensureDb();

        if (is_array(self::$insertColumns)) {
            return self::$insertColumns;
        }

        $existing = self::resolveColumnMap();

        $supported = [
            'stock_card_id',
            'item_id',
            'transaction_date',
            'reference_type',
            'reference_id',
            'reference_no',
            'qty_in',
            'qty_out',
            'balance_qty',
            'remarks',
            // Legacy schema columns that are still required in some deployments.
            'transact_date',
            'reference_document_type',
            'reference_document_no',
            'receipt_qty',
            'issue_qty',
        ];

        $columns = [];
        foreach ($supported as $column) {
            if (isset($existing[$column])) {
                $columns[] = $column;
            }
        }

        self::$insertColumns = $columns;
        return self::$insertColumns;
    }

    protected static function resolveColumnMap(): array
    {
        self::ensureDb();

        if (is_array(self::$resolvedColumns)) {
            return self::$resolvedColumns;
        }

        $stmt = self::$db->query("SHOW COLUMNS FROM `" . self::$tableName . "`");
        $existing = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $field = (string)($column['Field'] ?? '');
            if ($field !== '') {
                $existing[$field] = true;
            }
        }

        self::$resolvedColumns = $existing;
        return self::$resolvedColumns;
    }

    protected static function pickColumn(string $primary, string $legacy): string
    {
        $columns = self::resolveColumnMap();
        if (isset($columns[$primary])) {
            return $primary;
        }

        return $legacy;
    }

    protected static function recalculateBalancesForItem(int $itemId, int $currentBalance): void
    {
        $dateColumn = self::pickColumn('transaction_date', 'transact_date');
        $qtyInColumn = self::pickColumn('qty_in', 'receipt_qty');
        $qtyOutColumn = self::pickColumn('qty_out', 'issue_qty');

        $stmt = self::$db->prepare(
            "SELECT
                id,
                " . $qtyInColumn . " AS qty_in,
                " . $qtyOutColumn . " AS qty_out
             FROM " . self::$tableName . "
             WHERE item_id = ?
             ORDER BY " . $dateColumn . " ASC, id ASC"
        );
        $stmt->execute([$itemId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($entries)) {
            return;
        }

        $netMovement = 0;
        foreach ($entries as $entry) {
            $netMovement += (int)($entry['qty_in'] ?? 0) - (int)($entry['qty_out'] ?? 0);
        }

        $runningBalance = $currentBalance - $netMovement;
        $updateStmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " SET balance_qty = ? WHERE id = ?"
        );

        foreach ($entries as $entry) {
            $runningBalance += (int)($entry['qty_in'] ?? 0) - (int)($entry['qty_out'] ?? 0);
            $updateStmt->execute([$runningBalance, (int)$entry['id']]);
        }
    }

    public static function rebuildBalancesForItem(int $itemId): void
    {
        self::ensureDb();
        if ($itemId <= 0) {
            return;
        }

        StockInventory::refreshFromItem($itemId);
        $inventory = StockInventory::getByItemId($itemId);
        $currentBalance = (int)($inventory['current_balance'] ?? 0);

        self::recalculateBalancesForItem($itemId, $currentBalance);
    }

    public static function rebuildAllBalances(): void
    {
        self::ensureDb();
        $stmt = self::$db->query(
            "SELECT DISTINCT item_id
             FROM " . self::$tableName . "
             WHERE item_id IS NOT NULL AND item_id > 0
             ORDER BY item_id ASC"
        );
        $itemIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($itemIds as $itemId) {
            self::rebuildBalancesForItem((int)$itemId);
        }
    }

    public static function add(
        int $stockCardId,
        int $itemId,
        string $transactionDate,
        string $referenceType,
        ?int $referenceId,
        ?string $referenceNo,
        int $qtyIn,
        int $qtyOut,
        int $balanceQty,
        ?string $remarks = null
    ): bool {
        self::ensureDb();

        if ($stockCardId <= 0 || $itemId <= 0) {
            return false;
        }

        $cleanRemarks = trim((string)$remarks);
        if ($cleanRemarks === '') {
            $cleanRemarks = null;
        }

        $cleanReferenceNo = trim((string)$referenceNo);
        $legacyReferenceNo = $cleanReferenceNo === '' ? 'N/A' : $cleanReferenceNo;

        $data = [
            'stock_card_id' => $stockCardId,
            'item_id' => $itemId,
            'transaction_date' => $transactionDate,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_no' => $cleanReferenceNo === '' ? null : $cleanReferenceNo,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'balance_qty' => $balanceQty,
            'remarks' => $cleanRemarks,
            // Legacy mirrors
            'transact_date' => $transactionDate,
            'reference_document_type' => $referenceType,
            'reference_document_no' => $legacyReferenceNo,
            'receipt_qty' => $qtyIn,
            'issue_qty' => $qtyOut,
        ];

        $columns = self::resolveInsertColumns();
        if (empty($columns)) {
            return false;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (" . implode(', ', $columns) . ")
             VALUES (" . $placeholders . ")"
        );

        $values = [];
        foreach ($columns as $column) {
            $values[] = $data[$column] ?? null;
        }

        $inserted = $stmt->execute($values);

        if ($inserted && $itemId > 0) {
            StockInventory::refreshFromItem($itemId);
            $inventory = StockInventory::getByItemId($itemId);
            $currentBalance = (int)($inventory['current_balance'] ?? $balanceQty);
            self::recalculateBalancesForItem($itemId, $currentBalance);
        }

        return $inserted;
    }

    public static function syncReferenceNoForSupplyReceipt(int $receiptHeaderId, string $receiptNo): int
    {
        self::ensureDb();

        if ($receiptHeaderId <= 0) {
            return 0;
        }

        $columns = self::resolveColumnMap();
        $cleanReceiptNo = trim($receiptNo);
        $legacyReceiptNo = $cleanReceiptNo === '' ? 'N/A' : $cleanReceiptNo;

        $setParts = [];
        $setParams = [];
        $changeParts = [];
        $changeParams = [];

        if (isset($columns['reference_no'])) {
            $setParts[] = "reference_no = ?";
            $setParams[] = $cleanReceiptNo === '' ? null : $cleanReceiptNo;
            $changeParts[] = "COALESCE(reference_no, '') <> ?";
            $changeParams[] = $cleanReceiptNo;
        }

        if (isset($columns['reference_document_no'])) {
            $setParts[] = "reference_document_no = ?";
            $setParams[] = $legacyReceiptNo;
            $changeParts[] = "COALESCE(reference_document_no, '') <> ?";
            $changeParams[] = $legacyReceiptNo;
        }

        if (empty($setParts)) {
            return 0;
        }

        $typeParts = [];
        if (isset($columns['reference_type'])) {
            $typeParts[] = "reference_type IN ('Supply Receipt', 'Supply Receipt Reversal')";
        }
        if (isset($columns['reference_document_type'])) {
            $typeParts[] = "reference_document_type IN ('Supply Receipt', 'Supply Receipt Reversal')";
        }

        if (empty($typeParts)) {
            return 0;
        }

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET " . implode(', ', $setParts) . "
             WHERE reference_id = ?
               AND (" . implode(' OR ', $typeParts) . ")
               AND (" . implode(' OR ', $changeParts) . ")"
        );

        $stmt->execute(array_merge($setParams, [$receiptHeaderId], $changeParams));
        return (int)$stmt->rowCount();
    }

    public static function syncAllSupplyReceiptReferenceNos(): int
    {
        self::ensureDb();

        $columns = self::resolveColumnMap();
        $setParts = [];
        $changeParts = [];

        if (isset($columns['reference_no'])) {
            $setParts[] = "t.reference_no = NULLIF(TRIM(COALESCE(r.receipt_no, '')), '')";
            $changeParts[] = "COALESCE(t.reference_no, '') <> COALESCE(r.receipt_no, '')";
        }

        if (isset($columns['reference_document_no'])) {
            $setParts[] = "t.reference_document_no = CASE WHEN TRIM(COALESCE(r.receipt_no, '')) = '' THEN 'N/A' ELSE r.receipt_no END";
            $changeParts[] = "COALESCE(t.reference_document_no, '') <> CASE WHEN TRIM(COALESCE(r.receipt_no, '')) = '' THEN 'N/A' ELSE r.receipt_no END";
        }

        if (empty($setParts)) {
            return 0;
        }

        $typeParts = [];
        if (isset($columns['reference_type'])) {
            $typeParts[] = "t.reference_type IN ('Supply Receipt', 'Supply Receipt Reversal')";
        }
        if (isset($columns['reference_document_type'])) {
            $typeParts[] = "t.reference_document_type IN ('Supply Receipt', 'Supply Receipt Reversal')";
        }

        if (empty($typeParts)) {
            return 0;
        }

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " t
             INNER JOIN supply_receipts r ON r.id = t.reference_id
             SET " . implode(', ', $setParts) . "
             WHERE (" . implode(' OR ', $typeParts) . ")
               AND (" . implode(' OR ', $changeParts) . ")"
        );
        $stmt->execute();

        return (int)$stmt->rowCount();
    }

    public static function getRecentByItem(int $itemId, int $limit = 10): array
    {
        self::ensureDb();

        if ($itemId <= 0) {
            return [];
        }

        $dateColumn = self::pickColumn('transaction_date', 'transact_date');
        $referenceTypeColumn = self::pickColumn('reference_type', 'reference_document_type');
        $referenceNoColumn = self::pickColumn('reference_no', 'reference_document_no');
        $qtyInColumn = self::pickColumn('qty_in', 'receipt_qty');
        $qtyOutColumn = self::pickColumn('qty_out', 'issue_qty');

        $stmt = self::$db->prepare(
            "SELECT
                id,
                item_id,
                stock_card_id,
                " . $dateColumn . " AS transaction_date,
                " . $referenceTypeColumn . " AS reference_type,
                reference_id,
                " . $referenceNoColumn . " AS reference_no,
                " . $qtyInColumn . " AS qty_in,
                " . $qtyOutColumn . " AS qty_out,
                balance_qty,
                remarks
             FROM " . self::$tableName . "
             WHERE item_id = ?
             ORDER BY " . $dateColumn . " DESC, id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $itemId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllByItem(int $itemId): array
    {
        self::ensureDb();

        if ($itemId <= 0) {
            return [];
        }

        self::rebuildBalancesForItem($itemId);
        $dateColumn = self::pickColumn('transaction_date', 'transact_date');
        $referenceTypeColumn = self::pickColumn('reference_type', 'reference_document_type');
        $referenceNoColumn = self::pickColumn('reference_no', 'reference_document_no');
        $qtyInColumn = self::pickColumn('qty_in', 'receipt_qty');
        $qtyOutColumn = self::pickColumn('qty_out', 'issue_qty');

        $stmt = self::$db->prepare(
            "SELECT
                id,
                item_id,
                stock_card_id,
                " . $dateColumn . " AS transaction_date,
                " . $referenceTypeColumn . " AS reference_type,
                reference_id,
                " . $referenceNoColumn . " AS reference_no,
                " . $qtyInColumn . " AS qty_in,
                " . $qtyOutColumn . " AS qty_out,
                balance_qty,
                remarks
             FROM " . self::$tableName . "
             WHERE item_id = ?
             ORDER BY " . $dateColumn . " ASC, id ASC"
        );
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
