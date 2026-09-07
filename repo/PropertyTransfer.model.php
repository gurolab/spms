<?php

require_once __DIR__ . '/../core/BaseModel.php';

class PropertyTransfer extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'property_transfers';
    public const STATUSES = ['Pending','Completed','Cancelled'];
    public const TYPES = ['Department','Personnel'];

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

    public static function getNextTransferNo(): string
    {
        self::ensureDb();
        $stmt = self::$db->query("SELECT id FROM " . self::$tableName . " ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result ? ((int)$result['id'] + 1) : 1;
        $year = date('Y');
        return sprintf('PT-%s-%04d', $year, $nextId);
    }

    public static function getByTransferNo(string $transferNo)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE transfer_no = ?");
        $stmt->execute([$transferNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function add(
        string $transfer_no,
        string $transfer_date,
        string $property_tag,
        int $item_id,
        ?int $from_officer,
        ?int $to_officer,
        ?int $from_department,
        ?int $to_department,
        string $transfer_type,
        ?string $reason,
        ?int $prepared_by,
        ?int $noted_by,
        ?int $acknowledged_by,
        string $status
    ) {
        self::ensureDb();

        $reason = trim((string)$reason);
        $reason = $reason === '' ? null : $reason;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (transfer_no, transfer_date, property_tag, item_id, from_officer, to_officer, from_department, to_department, transfer_type, reason, prepared_by, noted_by, acknowledged_by, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $transfer_no,
            $transfer_date,
            $property_tag,
            $item_id,
            $from_officer ?: null,
            $to_officer ?: null,
            $from_department ?: null,
            $to_department ?: null,
            $transfer_type,
            $reason,
            $prepared_by ?: null,
            $noted_by ?: null,
            $acknowledged_by ?: null,
            $status
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        string $transfer_no,
        string $transfer_date,
        string $property_tag,
        int $item_id,
        ?int $from_officer,
        ?int $to_officer,
        ?int $from_department,
        ?int $to_department,
        string $transfer_type,
        ?string $reason,
        ?int $prepared_by,
        ?int $noted_by,
        ?int $acknowledged_by,
        string $status,
        int $id
    ): bool {
        self::ensureDb();

        $reason = trim((string)$reason);
        $reason = $reason === '' ? null : $reason;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET transfer_no = ?, transfer_date = ?, property_tag = ?, item_id = ?, from_officer = ?, to_officer = ?, from_department = ?, to_department = ?, transfer_type = ?, reason = ?, prepared_by = ?, noted_by = ?, acknowledged_by = ?, status = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $transfer_no,
            $transfer_date,
            $property_tag,
            $item_id,
            $from_officer ?: null,
            $to_officer ?: null,
            $from_department ?: null,
            $to_department ?: null,
            $transfer_type,
            $reason,
            $prepared_by ?: null,
            $noted_by ?: null,
            $acknowledged_by ?: null,
            $status,
            $id
        ]);
    }

    public static function updateStatus(int $id, string $status): bool
    {
        self::ensureDb();
        $stmt = self::$db->prepare("UPDATE " . self::$tableName . " SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public static function getByPropertyTag(string $propertyTag)
    {
        self::ensureDb();
        $stmt = self::$db->prepare(
            "SELECT * FROM " . self::$tableName . " WHERE property_tag = ? ORDER BY transfer_date DESC, id DESC"
        );
        $stmt->execute([$propertyTag]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
