<?php

require_once __DIR__ . '/../core/BaseModel.php';

class Item extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'items';

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

    //code, description, category, unit, unit_cost, stock_onhand, type, is_consumable

    public static function add($code, $description, $category, $unit, $unit_cost, $stock_onhand, $type, $is_consumable)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("INSERT INTO ".self::$tableName." (code, description, category, unit, unit_cost, stock_onhand, type, is_consumable) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $description, $category, $unit, $unit_cost, $stock_onhand, $type, $is_consumable]);
        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }
        return false;
    }

    public static function update($code, $description, $category, $unit, $unit_cost, $stock_onhand, $type, $is_consumable, $id)
    {
        self::ensureDb();
        $sql = "UPDATE ".self::$tableName." SET code = ?, description = ?, category = ?, unit = ?, unit_cost = ?, stock_onhand = ?, type = ?, is_consumable = ?";
        $fields = [$code, $description, $category, $unit, $unit_cost, $stock_onhand, $type, $is_consumable];

        $sql .= " WHERE id = ?";
        $fields[] = $id;

        $stmt = self::$db->prepare($sql);
        $res = $stmt->execute($fields);
        
        return $res;
    }


    public static function addQty($id, $qty = 0)
    {
        self::ensureDb();
        $sql = "UPDATE ".self::$tableName." SET stock_onhand = stock_onhand + ? WHERE id = ?";
        $stmt = self::$db->prepare($sql);
        $res = $stmt->execute([$qty, $id]);
        
        return $res;
    }

    public static function reduceQty($id, $qty = 0)
    {
        self::ensureDb();
        $sql = "UPDATE ".self::$tableName." SET stock_onhand = stock_onhand - ? WHERE id = ?";
        $stmt = self::$db->prepare($sql);
        $res = $stmt->execute([$qty, $id]);
        
        return $res;
    }

    public static function existsById(int $id): bool
    {
        self::ensureDb();

        if ($id <= 0) {
            return false;
        }

        $stmt = self::$db->prepare("SELECT 1 FROM " . self::$tableName . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        return (bool)$stmt->fetchColumn();
    }

    public static function getDeletionDependencyCounts(int $itemId): array
    {
        self::ensureDb();

        if ($itemId <= 0) {
            return [];
        }

        $counts = [];
        $directTables = [
            'requisition_items',
            'supply_receipt_items',
            'supply_issuance_items',
            'ics_items',
            'par_items',
            'physical_inventory_items',
            'physical_ppe_items',
            'unserviceable_property_items',
            'property_cards',
            'property_transfers',
            'stock_cards',
            'stock_inventory',
            'stock_transactions',
            'supply_ledger_cards',
        ];

        foreach ($directTables as $table) {
            $stmt = self::$db->prepare("SELECT COUNT(*) FROM " . $table . " WHERE item_id = ?");
            $stmt->execute([$itemId]);
            $counts[$table] = (int)$stmt->fetchColumn();
        }

        $ledgerStmt = self::$db->prepare(
            "SELECT COUNT(*)
             FROM supply_ledger_entries e
             INNER JOIN supply_ledger_cards c ON c.id = e.ledger_id
             WHERE c.item_id = ?"
        );
        $ledgerStmt->execute([$itemId]);
        $counts['supply_ledger_entries'] = (int)$ledgerStmt->fetchColumn();

        return $counts;
    }

    public static function deleteWithSupportRecords(int $itemId): bool
    {
        self::ensureDb();

        if ($itemId <= 0) {
            return false;
        }

        self::$db->beginTransaction();

        try {
            $deleteLedgerEntries = self::$db->prepare(
                "DELETE e
                 FROM supply_ledger_entries e
                 INNER JOIN supply_ledger_cards c ON c.id = e.ledger_id
                 WHERE c.item_id = ?"
            );
            $deleteLedgerEntries->execute([$itemId]);

            $deleteStockTransactions = self::$db->prepare(
                "DELETE FROM stock_transactions WHERE item_id = ?"
            );
            $deleteStockTransactions->execute([$itemId]);

            $deleteStockInventory = self::$db->prepare(
                "DELETE FROM stock_inventory WHERE item_id = ?"
            );
            $deleteStockInventory->execute([$itemId]);

            $deleteStockCards = self::$db->prepare(
                "DELETE FROM stock_cards WHERE item_id = ?"
            );
            $deleteStockCards->execute([$itemId]);

            $deleteLedgerCards = self::$db->prepare(
                "DELETE FROM supply_ledger_cards WHERE item_id = ?"
            );
            $deleteLedgerCards->execute([$itemId]);

            $deleteItem = self::$db->prepare(
                "DELETE FROM " . self::$tableName . " WHERE id = ?"
            );
            $deleteItem->execute([$itemId]);

            if ($deleteItem->rowCount() <= 0) {
                self::$db->rollBack();
                return false;
            }

            self::$db->commit();
            return true;
        } catch (Throwable $e) {
            if (self::$db->inTransaction()) {
                self::$db->rollBack();
            }
            throw $e;
        }
    }


    
}


