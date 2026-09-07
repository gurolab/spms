<?php

require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/StockInventory.model.php';
require_once __DIR__ . '/Item.model.php';

class Supply_Ledger_Card extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'supply_ledger_cards';

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

    public static function ensureForItem(int $itemId, ?int $createdBy = null): int
    {
        self::ensureDb();

        if (!Item::existsById($itemId)) {
            return 0;
        }

        $stmt = self::$db->prepare("SELECT id FROM " . self::$tableName . " WHERE item_id = ? LIMIT 1");
        $stmt->execute([$itemId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing && isset($existing['id'])) {
            return (int)$existing['id'];
        }

        $ledgerNo = sprintf('SLC-%05d', $itemId);
        $stmt = self::$db->prepare("INSERT INTO " . self::$tableName . " (item_id, ledger_no, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$itemId, $ledgerNo, $createdBy]);

        return (int)self::$db->lastInsertId();
    }

    public static function getByItem(int $itemId)
    {
        self::ensureDb();

        if ($itemId <= 0) {
            return false;
        }

        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE item_id = ? LIMIT 1");
        $stmt->execute([$itemId]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($card) {
            return $card;
        }
        $cardId = self::ensureForItem($itemId, null);
        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE id = ? LIMIT 1");
        $stmt->execute([$cardId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

class Supply_Ledger_Entry extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'supply_ledger_entries';

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

    protected static function recalculateBalancesForLedger(int $ledgerId, int $currentBalance): void
    {
        if ($ledgerId <= 0) {
            return;
        }

        $stmt = self::$db->prepare(
            "SELECT id, qty_in, qty_out
             FROM " . self::$tableName . "
             WHERE ledger_id = ?
             ORDER BY entry_date ASC, id ASC"
        );
        $stmt->execute([$ledgerId]);
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

        $ledgerId = Supply_Ledger_Card::ensureForItem($itemId, null);
        if ($ledgerId <= 0) {
            return;
        }

        StockInventory::refreshFromItem($itemId);
        $inventory = StockInventory::getByItemId($itemId);
        $currentBalance = (int)($inventory['current_balance'] ?? 0);

        self::recalculateBalancesForLedger($ledgerId, $currentBalance);
    }

    public static function rebuildAllBalances(): void
    {
        self::ensureDb();
        $stmt = self::$db->query("SELECT item_id FROM supply_ledger_cards WHERE item_id > 0 ORDER BY item_id ASC");
        $itemIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($itemIds as $itemId) {
            self::rebuildBalancesForItem((int)$itemId);
        }
    }

    public static function recordMovement(
        int $itemId,
        string $entryDate,
        string $referenceType,
        int $referenceId,
        string $referenceNo,
        int $qtyIn,
        int $qtyOut,
        float $unitCost,
        ?string $remarks = null,
        ?int $createdBy = null
    ): bool {
        self::ensureDb();

        if ($itemId <= 0) {
            return false;
        }

        $ledgerId = Supply_Ledger_Card::ensureForItem($itemId, $createdBy);
        if ($ledgerId <= 0) {
            return false;
        }

        StockInventory::refreshFromItem($itemId);
        $inventory = StockInventory::getByItemId($itemId);
        if (empty($inventory)) {
            return false;
        }
        $balance = (int)($inventory['current_balance'] ?? 0);

        $cleanRemarks = trim((string)$remarks);
        if ($cleanRemarks === '') {
            $cleanRemarks = null;
        }

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (ledger_id, entry_date, reference_type, reference_id, reference_no, qty_in, qty_out, balance_qty, unit_cost, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $inserted = $stmt->execute([
            $ledgerId,
            $entryDate,
            $referenceType,
            $referenceId,
            $referenceNo,
            $qtyIn,
            $qtyOut,
            $balance,
            $unitCost,
            $cleanRemarks
        ]);

        if ($inserted) {
            self::recalculateBalancesForLedger($ledgerId, $balance);
        }

        return $inserted;
    }

    public static function syncReferenceNoForSupplyReceipt(int $receiptHeaderId, string $receiptNo): int
    {
        self::ensureDb();

        if ($receiptHeaderId <= 0) {
            return 0;
        }

        $cleanReceiptNo = trim($receiptNo);

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET reference_no = ?
             WHERE reference_id = ?
               AND reference_type IN ('Supply Receipt', 'Supply Receipt Reversal')
               AND COALESCE(reference_no, '') <> ?"
        );
        $stmt->execute([$cleanReceiptNo, $receiptHeaderId, $cleanReceiptNo]);

        return (int)$stmt->rowCount();
    }

    public static function syncAllSupplyReceiptReferenceNos(): int
    {
        self::ensureDb();

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " e
             INNER JOIN supply_receipts r ON r.id = e.reference_id
             SET e.reference_no = r.receipt_no
             WHERE e.reference_type IN ('Supply Receipt', 'Supply Receipt Reversal')
               AND COALESCE(e.reference_no, '') <> COALESCE(r.receipt_no, '')"
        );
        $stmt->execute();

        return (int)$stmt->rowCount();
    }

    public static function getEntriesByItem(int $itemId): array
    {
        self::ensureDb();

        if ($itemId <= 0) {
            return [];
        }

        self::rebuildBalancesForItem($itemId);
        $ledgerId = Supply_Ledger_Card::ensureForItem($itemId, null);
        if ($ledgerId <= 0) {
            return [];
        }

        $stmt = self::$db->prepare(
            "SELECT e.* FROM " . self::$tableName . " e WHERE e.ledger_id = ? ORDER BY e.entry_date ASC, e.id ASC"
        );
        $stmt->execute([$ledgerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
