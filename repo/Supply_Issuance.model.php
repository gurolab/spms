<?php

require_once __DIR__ . '/../core/BaseModel.php';

class Supply_Issuance extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'supply_issuances';

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

    public static function getNextRSMINo(): string
    {
        self::ensureDb();
        $stmt = self::$db->query("SELECT id FROM " . self::$tableName . " ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result ? ($result['id'] + 1) : 1;
        $year = date('Y');
        return sprintf("RSMI-%s-%04d", $year, $nextId);
    }

    public static function add($rsmi_no, $ris_id, $issuance_date, $issued_by, $received_by, $status, $remarks)
    {
        self::ensureDb();
        if ($received_by === '') {
            $received_by = null;
        }

        $status = $status ?: 'Draft';
        $remarks = trim((string)$remarks) === '' ? null : $remarks;

        $stmt = self::$db->prepare("INSERT INTO " . self::$tableName . " (rsmi_no, ris_id, issuance_date, issued_by, received_by, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $executed = $stmt->execute([$rsmi_no, $ris_id, $issuance_date, $issued_by, $received_by, $status, $remarks]);

        if ($executed) {
            return self::$db->lastInsertId();
        }

        return false;
    }

    public static function update($issuance_date, $issued_by, $received_by, $status, $remarks, $id)
    {
        self::ensureDb();
        if ($received_by === '') {
            $received_by = null;
        }

        $remarks = trim((string)$remarks) === '' ? null : $remarks;

        $sql = "UPDATE " . self::$tableName . " SET issuance_date = ?, issued_by = ?, received_by = ?, status = ?, remarks = ? WHERE id = ?";
        $stmt = self::$db->prepare($sql);

        return $stmt->execute([$issuance_date, $issued_by, $received_by, $status, $remarks, $id]);
    }

    public static function getByRIS($ris_id)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE ris_id = ?");
        $stmt->execute([$ris_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function updateStatus($status, $id)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("UPDATE " . self::$tableName . " SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}

class Supply_Issuance_Items extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'supply_issuance_items';

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

    public function getAll($issuance_id = null)
    {
        self::ensureDb();
        $sql = "SELECT * FROM " . self::$tableName;
        $params = [];

        if ($issuance_id) {
            $sql .= " WHERE issuance_id = ?";
            $params[] = $issuance_id;
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function add($issuance_id, $requisition_item_id, $item_id, $qty_requested, $qty_issued, $remarks)
    {
        self::ensureDb();
        $remarks = trim((string)$remarks) === '' ? null : $remarks;

        $stmt = self::$db->prepare("INSERT INTO " . self::$tableName . " (issuance_id, requisition_item_id, item_id, qty_requested, qty_issued, remarks) VALUES (?, ?, ?, ?, ?, ?)");
        $executed = $stmt->execute([$issuance_id, $requisition_item_id, $item_id, $qty_requested, $qty_issued, $remarks]);

        if ($executed) {
            return self::$db->lastInsertId();
        }

        return false;
    }

    public static function updateQtyIssued($qty_issued, $id, $remarks = null)
    {
        self::ensureDb();
        $remarks = trim((string)$remarks) === '' ? null : $remarks;

        $stmt = self::$db->prepare("UPDATE " . self::$tableName . " SET qty_issued = ?, remarks = COALESCE(?, remarks) WHERE id = ?");
        return $stmt->execute([$qty_issued, $remarks, $id]);
    }

    public static function getByIssuanceAndRequisitionItem($issuance_id, $requisition_item_id)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE issuance_id = ? AND requisition_item_id = ? LIMIT 1");
        $stmt->execute([$issuance_id, $requisition_item_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
