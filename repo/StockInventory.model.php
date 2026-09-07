<?php

require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/Item.model.php';
require_once __DIR__ . '/StockCard.model.php';
require_once __DIR__ . '/StockTransaction.model.php';

class StockInventory extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'stock_inventory';

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

    protected static function getItemRepo(): Item
    {
        return new Item();
    }

    protected static function getValidItem(int $itemId): ?array
    {
        if ($itemId <= 0) {
            return null;
        }

        $item = self::getItemRepo()->getById($itemId);
        return is_array($item) ? $item : null;
    }

    public static function ensureForItem(int $itemId): array
    {
        self::ensureDb();

        if ($itemId <= 0) {
            return [];
        }

        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE item_id = ? LIMIT 1");
        $stmt->execute([$itemId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record) {
            return $record;
        }

        $item = self::getValidItem($itemId);
        if (!$item) {
            return [];
        }
        $currentBalance = (int)($item['stock_onhand'] ?? 0);

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (item_id, reorder_level, reorder_quantity, safety_stock, current_balance) 
             VALUES (?, 0, 0, 0, ?)"
        );
        $stmt->execute([$itemId, $currentBalance]);

        return self::getByItemId($itemId);
    }

    public static function getByItemId(int $itemId): array
    {
        self::ensureDb();

        if ($itemId <= 0) {
            return [];
        }

        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE item_id = ? LIMIT 1");
        $stmt->execute([$itemId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record) {
            return $record;
        }
        return self::ensureForItem($itemId);
    }

    public static function updateLevels(int $itemId, int $reorderLevel, int $reorderQty, int $safetyStock): bool
    {
        self::ensureDb();
        if (empty(self::ensureForItem($itemId))) {
            return false;
        }

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " 
             SET reorder_level = ?, reorder_quantity = ?, safety_stock = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE item_id = ?"
        );
        return $stmt->execute([$reorderLevel, $reorderQty, $safetyStock, $itemId]);
    }

    public static function recordMovement(
        int $itemId,
        int $qtyIn,
        int $qtyOut,
        string $referenceType,
        ?int $referenceId,
        ?string $referenceNo,
        ?string $remarks = null,
        ?string $eventDate = null
    ): void {
        self::ensureDb();
        if (empty(self::ensureForItem($itemId))) {
            return;
        }

        $item = self::getValidItem($itemId);
        if (!$item) {
            return;
        }
        $currentBalance = (int)($item['stock_onhand'] ?? 0);

        $eventDate = $eventDate ?: date('Y-m-d');
        $eventDateTime = $eventDate . ' ' . date('H:i:s');

        $setParts = ['current_balance = ?'];
        $params = [$currentBalance];

        if ($qtyIn > 0) {
            $setParts[] = 'last_restocked_at = ?';
            $params[] = $eventDateTime;
        }

        if ($qtyOut > 0) {
            $setParts[] = 'last_issued_at = ?';
            $params[] = $eventDateTime;
        }

        $setParts[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $itemId;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " SET " . implode(', ', $setParts) . " WHERE item_id = ?"
        );
        $stmt->execute($params);

        $stockCardId = StockCard::ensureForItem($itemId);
        if ($stockCardId <= 0) {
            return;
        }

        StockTransaction::add(
            $stockCardId,
            $itemId,
            $eventDate,
            $referenceType,
            $referenceId,
            $referenceNo,
            $qtyIn,
            $qtyOut,
            $currentBalance,
            $remarks
        );
    }

    public static function refreshFromItem(int $itemId): void
    {
        self::ensureDb();
        if (empty(self::ensureForItem($itemId))) {
            return;
        }

        $item = self::getValidItem($itemId);
        if (!$item) {
            return;
        }
        $currentBalance = (int)($item['stock_onhand'] ?? 0);

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " 
             SET current_balance = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE item_id = ?"
        );
        $stmt->execute([$currentBalance, $itemId]);
    }

    public static function refreshAllFromItems(): void
    {
        self::ensureDb();
        $itemRepo = self::getItemRepo();
        $items = $itemRepo->getAll();

        foreach ($items as $item) {
            $record = self::ensureForItem((int)$item['id']);
            $currentBalance = (int)$item['stock_onhand'];

            $stmt = self::$db->prepare(
                "UPDATE " . self::$tableName . " 
                 SET current_balance = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE item_id = ?"
            );
            $stmt->execute([$currentBalance, $item['id']]);
        }
    }

    public static function getAllSummary(): array
    {
        self::refreshAllFromItems();
        $stmt = self::$db->query(
            "SELECT si.*, i.code, i.description, i.category, i.unit, i.type, i.stock_onhand, i.unit_cost
             FROM " . self::$tableName . " si 
             JOIN items i ON si.item_id = i.id 
             ORDER BY i.code ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getReorderList(): array
    {
        self::refreshAllFromItems();
        $stmt = self::$db->query(
            "SELECT si.*, i.code, i.description, i.category, i.unit, i.type, i.stock_onhand, i.unit_cost
             FROM " . self::$tableName . " si 
             JOIN items i ON si.item_id = i.id 
             WHERE 
                (si.reorder_level > 0 AND si.current_balance <= si.reorder_level)
                OR (si.safety_stock > 0 AND si.current_balance <= si.safety_stock)
             ORDER BY si.current_balance ASC, i.code ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
