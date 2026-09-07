<?php

require_once __DIR__ . '/../core/BaseModel.php';

class PAR extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'property_acknowledgment_receipts';

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

    public static function getNextPARNo(): string
    {
        self::ensureDb();
        $stmt = self::$db->query("SELECT id FROM " . self::$tableName . " ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result ? ((int)$result['id'] + 1) : 1;
        $year = date('Y');
        return sprintf("PAR-%s-%04d", $year, $nextId);
    }

    public static function getByParNo(string $parNo)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE par_no = ?");
        $stmt->execute([$parNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function add(
        string $par_no,
        string $fund_cluster,
        int $accountable_officer,
        int $issued_by,
        string $issue_date,
        string $status,
        ?string $remarks
    ) {
        self::ensureDb();

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (par_no, fund_cluster, accountable_officer, issued_by, issue_date, status, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $par_no,
            $fund_cluster,
            $accountable_officer,
            $issued_by,
            $issue_date,
            $status,
            $remarks
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        string $par_no,
        string $fund_cluster,
        int $accountable_officer,
        int $issued_by,
        string $issue_date,
        string $status,
        ?string $remarks,
        int $id
    ): bool {
        self::ensureDb();

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET par_no = ?, fund_cluster = ?, accountable_officer = ?, issued_by = ?, issue_date = ?, status = ?, remarks = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $par_no,
            $fund_cluster,
            $accountable_officer,
            $issued_by,
            $issue_date,
            $status,
            $remarks,
            $id
        ]);
    }

    public static function updateStatus(string $status, int $id): bool
    {
        self::ensureDb();
        $stmt = self::$db->prepare("UPDATE " . self::$tableName . " SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}

class PAR_Items extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'par_items';

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

    public function getAll($par_id = null)
    {
        self::ensureDb();
        $sql = "SELECT * FROM " . self::$tableName;
        $params = [];

        if ($par_id !== null && $par_id !== '') {
            $sql .= " WHERE par_id = ?";
            $params[] = $par_id;
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByParAndProperty(int $par_id, string $property_no)
    {
        self::ensureDb();
        $stmt = self::$db->prepare(
            "SELECT * FROM " . self::$tableName . " WHERE par_id = ? AND property_no = ? LIMIT 1"
        );
        $stmt->execute([$par_id, $property_no]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function add(
        int $par_id,
        int $item_id,
        string $property_no,
        int $qty,
        float $unit_value,
        string $acquisition_date,
        ?string $description,
        ?string $remarks
    ) {
        self::ensureDb();

        $description = trim((string)$description);
        $description = $description === '' ? null : $description;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (par_id, item_id, property_no, qty, unit_value, acquisition_date, description, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $executed = $stmt->execute([
            $par_id,
            $item_id,
            $property_no,
            $qty,
            $unit_value,
            $acquisition_date,
            $description,
            $remarks
        ]);

        if (!$executed) {
            return false;
        }

        return (int) self::$db->lastInsertId();
    }

    public static function update(
        int $par_id,
        int $item_id,
        string $property_no,
        int $qty,
        float $unit_value,
        string $acquisition_date,
        ?string $description,
        ?string $remarks,
        int $id
    ): bool {
        self::ensureDb();

        $description = trim((string)$description);
        $description = $description === '' ? null : $description;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET par_id = ?, item_id = ?, property_no = ?, qty = ?, unit_value = ?, acquisition_date = ?, description = ?, remarks = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $par_id,
            $item_id,
            $property_no,
            $qty,
            $unit_value,
            $acquisition_date,
            $description,
            $remarks,
            $id
        ]);
    }
}
