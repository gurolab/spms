<?php

require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/Item.model.php';

class StockCard extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'stock_cards';

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

    public static function ensureForItem(int $itemId): int
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

        $cardNo = sprintf('SC-%05d', $itemId);
        $stmt = self::$db->prepare("INSERT INTO " . self::$tableName . " (item_id, card_no, reorder_point, max_level, notes) VALUES (?, ?, 0, 0, NULL)");
        $stmt->execute([$itemId, $cardNo]);

        return (int)self::$db->lastInsertId();
    }
}
