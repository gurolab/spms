<?php

require_once __DIR__ . '/../core/BaseModel.php';

class RIS extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'requisition_slips';

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


    // Generate next RIS number in the format RIS-YYYY-XXXX
    public static function getNextRISNo()
    {
        self::ensureDb();
        $stmt = self::$db->query("SELECT id FROM ".self::$tableName." ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result ? ($result['id'] + 1) : 1;
        $year = date('Y');
        $risNo = sprintf("RIS-%s-%04d", $year, $nextId);
        return $risNo;
    }

    // ris_no, requisition_date, fund_cluster, division, responsibility_center_code, requested_by, purpose, remarks
    public static function addinitial($ris_no, $requisition_date, $fund_cluster, $division, $responsibility_center_code, $requested_by, $purpose, $remarks)
    {
        self::ensureDb();
        if ($division === '') {
            $division = null;
        }

        if ($responsibility_center_code === '') {
            $responsibility_center_code = null;
        }

        $remarks = trim((string)$remarks) === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "INSERT INTO ".self::$tableName." (ris_no, requisition_date, fund_cluster, division, responsibility_center_code, requested_by, status, purpose, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $ris_no,
            $requisition_date,
            $fund_cluster,
            $division,
            $responsibility_center_code,
            $requested_by,
            'Requested',
            $purpose,
            $remarks
        ]);
        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }
        return false;
    }

    // fund_cluster, division, responsibility_center_code, requisition_date, requested_by, purpose, approved_by, issued_by, received_by, status, remarks
    public static function add($fund_cluster, $division, $responsibility_center_code, $requisition_date, $requested_by, $purpose, $approved_by, $issued_by, $received_by, $status, $remarks)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("INSERT INTO ".self::$tableName." (fund_cluster, division, responsibility_center_code, requisition_date, requested_by, purpose, approved_by, issued_by, received_by, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$fund_cluster, $division, $responsibility_center_code, $requisition_date, $requested_by, $purpose, $approved_by, $issued_by, $received_by, $status, $remarks]);
        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }
        return false;
    }

    public static function updateStatus($status, $id)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("UPDATE ".self::$tableName." SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function update($fund_cluster, $responsibility_center_code, $approved_by, $issued_by, $received_by, $status, $remarks, $purpose, $id)
    {
        self::ensureDb();
        if (empty($fund_cluster)) {
            $fund_cluster = null;
        }
        if (empty($responsibility_center_code)) {
            $responsibility_center_code = null;
        }
        if (empty($approved_by)) {
            $approved_by = null;
        }
        if (empty($issued_by)) {
            $issued_by = null;
        }
        if (empty($received_by)) {
            $received_by = null;
        }
        $remarks = trim((string)$remarks) === '' ? null : $remarks;
        $purpose = trim((string)$purpose) === '' ? null : $purpose;

        $sql = "UPDATE ".self::$tableName." SET fund_cluster = ?, responsibility_center_code = ?, approved_by = ?, issued_by = ?, received_by = ?, status = ?, remarks = ?, purpose = ?";
        $fields = [$fund_cluster, $responsibility_center_code, $approved_by, $issued_by, $received_by, $status, $remarks, $purpose];

        $sql .= " WHERE id = ?";
        $fields[] = $id;

        $stmt = self::$db->prepare($sql);
        $res = $stmt->execute($fields);
        
        return $res;
    }


}


class RIS_Items extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'requisition_items';

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


    public function getAll($ris_id = null)
    {
        $sql = "SELECT * FROM ".self::$tableName;
        $params = [];

        if ($ris_id) {
            $sql .= " WHERE ris_id = ?";
            $params[] = $ris_id;
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    //ris_id, item_id, qty_requested, qty_issued, remarks
    public static function add($ris_id, $item_id, $qty_requested, $remarks)
    {
        self::ensureDb();
        $qty_issued = 0;
        $remarks = trim((string)$remarks);
        if ($remarks === '') {
            $remarks = '';
        }

        $stmt = self::$db->prepare("INSERT INTO ".self::$tableName." (ris_id, item_id, qty_requested, qty_issued, remarks) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ris_id, $item_id, $qty_requested, $qty_issued, $remarks]);
        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }
        return false;
    }

    public static function update($ris_id, $item_id, $qty_requested, $qty_issued, $remarks, $id)
    {
        $remarks = trim((string)$remarks);
        if ($remarks === '') {
            $remarks = '';
        }

        self::ensureDb();
        $stmt = self::$db->prepare("UPDATE ".self::$tableName." SET ris_id = ?, item_id = ?, qty_requested = ?, qty_issued = ?, remarks = ? WHERE id = ?");
        $stmt->execute([$ris_id, $item_id, $qty_requested, $qty_issued, $remarks, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function updateQtyIssued($qty_issued, $id)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("UPDATE ".self::$tableName." SET qty_issued = ? WHERE id = ?");
        $stmt->execute([$qty_issued, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function adjustQtyIssued($id, $deltaQty)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("UPDATE ".self::$tableName." SET qty_issued = GREATEST(0, qty_issued + ?) WHERE id = ?");
        $stmt->execute([$deltaQty, $id]);
        return $stmt->rowCount() > 0;
    }

    // public static function getById($id)
    // {
    //     $stmt = self::$db->prepare("SELECT * FROM ".self::$tableName." WHERE id = ?");
    //     $stmt->execute([$id]);
    //     return $stmt->fetch(PDO::FETCH_ASSOC);
    // }

    public static function getByRISAndItem($ris_id, $item_id)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("SELECT * FROM ".self::$tableName." WHERE ris_id = ? AND item_id = ?");
        $stmt->execute([$ris_id, $item_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
}
