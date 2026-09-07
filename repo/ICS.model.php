<?php

require_once __DIR__ . '/../core/BaseModel.php';

class ICS extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'inventory_custodian_slips';
    protected static $tableColumns = null;

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

    protected static function getTableColumns(): array
    {
        self::ensureDb();

        if (is_array(self::$tableColumns)) {
            return self::$tableColumns;
        }

        $stmt = self::$db->query("SHOW COLUMNS FROM `" . self::$tableName . "`");
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $field = (string)($column['Field'] ?? '');
            if ($field !== '') {
                $columns[$field] = true;
            }
        }

        self::$tableColumns = $columns;
        return self::$tableColumns;
    }

    protected static function hasColumn(string $column): bool
    {
        $columns = self::getTableColumns();
        return isset($columns[$column]);
    }

    public static function getNextICSNo(): string
    {
        self::ensureDb();
        $stmt = self::$db->query("SELECT id FROM " . self::$tableName . " ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result ? ((int)$result['id'] + 1) : 1;
        $year = date('Y');
        return sprintf("ICS-%s-%04d", $year, $nextId);
    }

    public static function getByICSNo(string $icsNo)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE ics_no = ?");
        $stmt->execute([$icsNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function add(
        string $ics_no,
        string $property_no,
        string $value_type,
        int $assigned_to,
        int $issued_by,
        string $issued_date,
        string $status,
        ?string $remarks
    ) {
        self::ensureDb();

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $columns = ['ics_no', 'property_no', 'value_type', 'assigned_to', 'issued_by', 'issued_date', 'status', 'remarks'];
        $values = [
            $ics_no,
            $property_no,
            $value_type,
            $assigned_to,
            $issued_by,
            $issued_date,
            $status,
            $remarks,
        ];

        // Backward-compatible schema handling: some DBs require extra legacy fields.
        if (self::hasColumn('fund_cluster')) {
            $columns[] = 'fund_cluster';
            $values[] = 'General Fund';
        }
        if (self::hasColumn('received_by')) {
            $columns[] = 'received_by';
            $values[] = $assigned_to;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (" . implode(', ', $columns) . ")
             VALUES (" . $placeholders . ")"
        );

        $stmt->execute($values);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        string $ics_no,
        string $property_no,
        string $value_type,
        int $assigned_to,
        int $issued_by,
        string $issued_date,
        string $status,
        ?string $remarks,
        int $id
    ): bool {
        self::ensureDb();

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $setParts = [
            'ics_no = ?',
            'property_no = ?',
            'value_type = ?',
            'assigned_to = ?',
            'issued_by = ?',
            'issued_date = ?',
            'status = ?',
            'remarks = ?',
        ];
        $values = [
            $ics_no,
            $property_no,
            $value_type,
            $assigned_to,
            $issued_by,
            $issued_date,
            $status,
            $remarks,
        ];

        if (self::hasColumn('received_by')) {
            $setParts[] = 'received_by = ?';
            $values[] = $assigned_to;
        }

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET " . implode(', ', $setParts) . "
             WHERE id = ?"
        );

        $values[] = $id;
        return $stmt->execute($values);
    }

    public static function updateStatus(string $status, int $id): bool
    {
        self::ensureDb();
        $stmt = self::$db->prepare("UPDATE " . self::$tableName . " SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}


class ICS_Items extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'ics_items';
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
            error_log('ICS_Items::ensureColumn failed for ' . $column . ': ' . $e->getMessage());
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

        // Appendix 72/73 parity: keep useful life separately from free-form remarks.
        self::ensureColumn(
            'estimated_useful_life',
            "estimated_useful_life VARCHAR(80) NULL AFTER property_no"
        );

        self::$schemaInitialized = true;
    }

    public function getAll($ics_id = null)
    {
        self::ensureDb();
        $sql = "SELECT * FROM " . self::$tableName;
        $params = [];

        if ($ics_id !== null && $ics_id !== '') {
            $sql .= " WHERE ics_id = ?";
            $params[] = $ics_id;
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function add(
        int $ics_id,
        int $item_id,
        string $property_no,
        int $qty,
        float $unit_value,
        string $date_acquired,
        ?string $remarks,
        ?string $estimated_useful_life = null
    ) {
        self::ensureDb();

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;
        $estimated_useful_life = trim((string)$estimated_useful_life);
        $estimated_useful_life = $estimated_useful_life === '' ? null : $estimated_useful_life;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (ics_id, item_id, property_no, estimated_useful_life, qty, unit_value, date_acquired, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $ics_id,
            $item_id,
            $property_no,
            $estimated_useful_life,
            $qty,
            $unit_value,
            $date_acquired,
            $remarks
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        int $ics_id,
        int $item_id,
        string $property_no,
        int $qty,
        float $unit_value,
        string $date_acquired,
        ?string $remarks,
        int $id,
        ?string $estimated_useful_life = null
    ): bool {
        self::ensureDb();

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;
        $estimated_useful_life = trim((string)$estimated_useful_life);
        $estimated_useful_life = $estimated_useful_life === '' ? null : $estimated_useful_life;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET ics_id = ?, item_id = ?, property_no = ?, estimated_useful_life = ?, qty = ?, unit_value = ?, date_acquired = ?, remarks = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $ics_id,
            $item_id,
            $property_no,
            $estimated_useful_life,
            $qty,
            $unit_value,
            $date_acquired,
            $remarks,
            $id
        ]);
    }

    public static function getByICSAndProperty(int $ics_id, string $property_no)
    {
        self::ensureDb();
        $stmt = self::$db->prepare(
            "SELECT * FROM " . self::$tableName . " WHERE ics_id = ? AND property_no = ? LIMIT 1"
        );
        $stmt->execute([$ics_id, $property_no]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
