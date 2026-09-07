<?php

require_once __DIR__ . '/../core/BaseModel.php';

class SystemSettings extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'system_settings';

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

    protected static function ensureRecord(): array
    {
        self::ensureDb();
        $stmt = self::$db->query(
            "SELECT * FROM " . self::$tableName . " ORDER BY id ASC LIMIT 1"
        );
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record) {
            return $record;
        }

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (organization_name)
             VALUES ('Cotabato State University')"
        );
        $stmt->execute();

        return self::ensureRecord();
    }

    public static function getSettings(): array
    {
        return self::ensureRecord();
    }

    public static function save(array $data, ?int $userId = null): bool
    {
        $record = self::ensureRecord();
        $allowedFields = [
            'organization_name',
            'logo_path',
            'address',
            'contact_email',
            'contact_number',
            'fiscal_year_start',
            'fiscal_year_end'
        ];

        $setParts = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if ($value === '') {
                    $value = null;
                }
                $setParts[] = $field . ' = ?';
                $params[] = $value;
            }
        }

        if (empty($setParts)) {
            return true;
        }

        $setParts[] = 'updated_by = ?';
        $params[] = $userId;
        $params[] = $record['id'];

        $sql = "UPDATE " . self::$tableName . " SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = self::$db->prepare($sql);
        return $stmt->execute($params);
    }
}
