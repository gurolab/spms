<?php

require_once __DIR__ . '/../core/BaseModel.php';

class Supply_Receive_Header extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'supply_receipts';
    protected static $schemaInitialized = false;

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
        self::ensureSchema();
    }

    protected static function ensureDb(): void
    {
        if (self::$db === null) {
            self::$db = parent::Db();
        }
        self::ensureSchema();
    }

    protected static function columnExists(string $column): bool
    {
        $stmt = self::$db->query(
            "SHOW COLUMNS FROM " . self::$tableName . " LIKE " . self::$db->quote($column)
        );
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected static function ensureColumn(string $column, string $definition): void
    {
        try {
            if (!self::columnExists($column)) {
                self::$db->exec("ALTER TABLE " . self::$tableName . " ADD COLUMN " . $definition);
            }
        } catch (Throwable $e) {
            error_log('Supply_Receive_Header::ensureColumn failed for ' . $column . ': ' . $e->getMessage());
        }
    }

    protected static function ensureSchema(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        if (self::$db === null) {
            self::$db = parent::Db();
        }

        self::ensureColumn('pr_no', 'pr_no VARCHAR(100) NULL AFTER receipt_no');
        self::ensureColumn('po_no', 'po_no VARCHAR(100) NULL AFTER pr_no');
        self::ensureColumn('dr_no', 'dr_no VARCHAR(100) NULL AFTER po_no');
        self::ensureColumn('si_no', 'si_no VARCHAR(100) NULL AFTER dr_no');
        self::ensureColumn('iar_no', 'iar_no VARCHAR(100) NULL AFTER si_no');
        self::$schemaInitialized = true;
    }

    //receipt_no, supplier_id, receipt_date, received_by, inspected_by, notes

    public static function add($receipt_no, $pr_no, $po_no, $dr_no, $si_no, $iar_no, $supplier_id, $receipt_date, $received_by, $inspected_by, $notes)
    {
        self::ensureDb();

        $pr_no = trim((string)$pr_no);
        $po_no = trim((string)$po_no);
        $dr_no = trim((string)$dr_no);
        $si_no = trim((string)$si_no);
        $iar_no = trim((string)$iar_no);

        $pr_no = $pr_no === '' ? null : $pr_no;
        $po_no = $po_no === '' ? null : $po_no;
        $dr_no = $dr_no === '' ? null : $dr_no;
        $si_no = $si_no === '' ? null : $si_no;
        $iar_no = $iar_no === '' ? null : $iar_no;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (receipt_no, pr_no, po_no, dr_no, si_no, iar_no, supplier_id, receipt_date, received_by, inspected_by, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$receipt_no, $pr_no, $po_no, $dr_no, $si_no, $iar_no, $supplier_id, $receipt_date, $received_by, $inspected_by, $notes]);
        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }
        return false;
    }
    

    public static function update($receipt_no, $pr_no, $po_no, $dr_no, $si_no, $iar_no, $supplier_id, $receipt_date, $received_by, $inspected_by, $notes, $id)
    {
        self::ensureDb();

        $pr_no = trim((string)$pr_no);
        $po_no = trim((string)$po_no);
        $dr_no = trim((string)$dr_no);
        $si_no = trim((string)$si_no);
        $iar_no = trim((string)$iar_no);

        $pr_no = $pr_no === '' ? null : $pr_no;
        $po_no = $po_no === '' ? null : $po_no;
        $dr_no = $dr_no === '' ? null : $dr_no;
        $si_no = $si_no === '' ? null : $si_no;
        $iar_no = $iar_no === '' ? null : $iar_no;

        $sql = "UPDATE " . self::$tableName . " SET receipt_no = ?, pr_no = ?, po_no = ?, dr_no = ?, si_no = ?, iar_no = ?, supplier_id = ?, receipt_date = ?, received_by = ?, inspected_by = ?, notes = ?";
        $fields = [$receipt_no, $pr_no, $po_no, $dr_no, $si_no, $iar_no, $supplier_id, $receipt_date, $received_by, $inspected_by, $notes];

        $sql .= " WHERE id = ?";
        $fields[] = $id;

        $stmt = self::$db->prepare($sql);
        $res = $stmt->execute($fields);
        
        return $res;
    }


}


class Supply_Receive_Items extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'supply_receipt_items';

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
    }


    public function getAll($receipt_header_id = null)
    {
        $sql = "SELECT * FROM ".self::$tableName;
        $params = [];

        if ($receipt_header_id) {
            $sql .= " WHERE receipt_header_id = ?";
            $params[] = $receipt_header_id;
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    //receipt_header_id, item_id, qty_received, unit_cost, batch_no, expiry_date, remarks

    public static function add($receipt_header_id, $item_id, $qty_received, $unit_cost, $batch_no, $expiry_date, $remarks)
    {
        if($expiry_date == '') {
            $expiry_date = null;
        }

        if($remarks == '') {
            $remarks = null; 
        }

        if($unit_cost === '' || $unit_cost === null) {
            $unit_cost = 0;
        }

        $unit_cost = number_format((float)$unit_cost, 2, '.', '');

        $stmt = self::$db->prepare("INSERT INTO ".self::$tableName." (receipt_header_id, item_id, qty_received, unit_cost, batch_no, expiry_date, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$receipt_header_id, $item_id, $qty_received, $unit_cost, $batch_no, $expiry_date, $remarks]);
        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }
        return false;
    }

    public static function update($receipt_header_id, $item_id, $qty_received, $unit_cost, $batch_no, $expiry_date, $remarks, $id)
    {
        if($expiry_date == '') {
            $expiry_date = null;
        }

        if($remarks == '') {
            $remarks = null; 
        }

        if($unit_cost === '' || $unit_cost === null) {
            $unit_cost = 0;
        }

        $unit_cost = number_format((float)$unit_cost, 2, '.', '');

        $sql = "UPDATE ".self::$tableName." SET receipt_header_id = ?, item_id = ?, qty_received = ?, unit_cost = ?, batch_no = ?, expiry_date = ?, remarks = ?";
        $fields = [$receipt_header_id, $item_id, $qty_received, $unit_cost, $batch_no, $expiry_date, $remarks];

        $sql .= " WHERE id = ?";
        $fields[] = $id;

        $stmt = self::$db->prepare($sql);
        $res = $stmt->execute($fields);
        
        return $res;
    }
    
}
