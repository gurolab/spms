<?php

require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/Item.model.php';

class PhysicalInventoryReport extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'physical_inventory_reports';
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
            error_log('PhysicalInventoryReport::ensureColumn failed for ' . $column . ': ' . $e->getMessage());
        }
    }

    public static function ensureSchema(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        self::ensureDb();

        $reportSql = "
            CREATE TABLE IF NOT EXISTS physical_inventory_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_no VARCHAR(50) NOT NULL UNIQUE,
                report_date DATE NOT NULL,
                fund_cluster VARCHAR(80) NULL,
                inventory_type VARCHAR(120) NULL,
                classification_code VARCHAR(120) NULL,
                location VARCHAR(150) NULL,
                prepared_by INT NOT NULL,
                verified_by INT NULL,
                remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $itemsSql = "
            CREATE TABLE IF NOT EXISTS physical_inventory_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_id INT NOT NULL,
                item_id INT NOT NULL,
                location VARCHAR(150) NULL,
                system_qty INT NOT NULL DEFAULT 0,
                counted_qty INT NOT NULL DEFAULT 0,
                unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_physical_inventory_report
                    FOREIGN KEY (report_id)
                    REFERENCES physical_inventory_reports(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        try {
            self::$db->exec($reportSql);
            self::$db->exec($itemsSql);

            // Backward-compatible upgrades for RPCI Appendix 66 fields.
            self::ensureColumn('fund_cluster', 'fund_cluster VARCHAR(80) NULL AFTER report_date');
            self::ensureColumn('inventory_type', 'inventory_type VARCHAR(120) NULL AFTER fund_cluster');
            self::ensureColumn('classification_code', 'classification_code VARCHAR(120) NULL AFTER inventory_type');
        } catch (Throwable $e) {
            error_log('PhysicalInventoryReport::ensureSchema failed: ' . $e->getMessage());
        }

        self::$schemaInitialized = true;
    }

    public static function getNextReportNo(): string
    {
        self::ensureSchema();

        $stmt = self::$db->query(
            "SELECT id FROM " . self::$tableName . " ORDER BY id DESC LIMIT 1"
        );
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result ? ((int)$result['id'] + 1) : 1;
        $year = date('Y');

        return sprintf('RPCI-%s-%04d', $year, $nextId);
    }

    public static function getByReportNo(string $reportNo)
    {
        self::ensureSchema();

        $stmt = self::$db->prepare(
            "SELECT * FROM " . self::$tableName . " WHERE report_no = ? LIMIT 1"
        );
        $stmt->execute([$reportNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function add(
        string $reportNo,
        string $reportDate,
        ?string $fundCluster,
        ?string $inventoryType,
        ?string $classificationCode,
        int $preparedBy,
        ?int $verifiedBy,
        ?string $location,
        ?string $remarks
    ) {
        self::ensureSchema();

        $fundCluster = trim((string)$fundCluster);
        $fundCluster = $fundCluster === '' ? null : $fundCluster;

        $inventoryType = trim((string)$inventoryType);
        $inventoryType = $inventoryType === '' ? null : $inventoryType;

        $classificationCode = trim((string)$classificationCode);
        $classificationCode = $classificationCode === '' ? null : $classificationCode;

        $location = trim((string)$location);
        $location = $location === '' ? null : $location;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (report_no, report_date, fund_cluster, inventory_type, classification_code, prepared_by, verified_by, location, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $reportNo,
            $reportDate,
            $fundCluster,
            $inventoryType,
            $classificationCode,
            $preparedBy,
            $verifiedBy,
            $location,
            $remarks
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        int $id,
        string $reportNo,
        string $reportDate,
        ?string $fundCluster,
        ?string $inventoryType,
        ?string $classificationCode,
        int $preparedBy,
        ?int $verifiedBy,
        ?string $location,
        ?string $remarks
    ): bool {
        self::ensureSchema();

        $fundCluster = trim((string)$fundCluster);
        $fundCluster = $fundCluster === '' ? null : $fundCluster;

        $inventoryType = trim((string)$inventoryType);
        $inventoryType = $inventoryType === '' ? null : $inventoryType;

        $classificationCode = trim((string)$classificationCode);
        $classificationCode = $classificationCode === '' ? null : $classificationCode;

        $location = trim((string)$location);
        $location = $location === '' ? null : $location;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET report_no = ?, report_date = ?, fund_cluster = ?, inventory_type = ?, classification_code = ?, prepared_by = ?, verified_by = ?, location = ?, remarks = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $reportNo,
            $reportDate,
            $fundCluster,
            $inventoryType,
            $classificationCode,
            $preparedBy,
            $verifiedBy,
            $location,
            $remarks,
            $id
        ]);
    }

    public static function deleteHeader(int $id): bool
    {
        self::ensureSchema();

        $itemModel = new PhysicalInventoryItem();
        $itemModel->deleteByReport($id);

        $stmt = self::$db->prepare(
            "DELETE FROM " . self::$tableName . " WHERE id = ?"
        );

        return $stmt->execute([$id]);
    }

    public static function getAllWithSummary(): array
    {
        self::ensureSchema();

        $sql = "
            SELECT
                r.id,
                r.report_no,
                r.report_date,
                r.fund_cluster,
                r.inventory_type,
                r.classification_code,
                r.location,
                r.prepared_by,
                r.verified_by,
                r.remarks,
                r.created_at,
                r.updated_at,
                COUNT(i.id) AS total_items,
                COALESCE(SUM(i.system_qty), 0) AS total_system_qty,
                COALESCE(SUM(i.counted_qty), 0) AS total_counted_qty,
                COALESCE(SUM(i.counted_qty - i.system_qty), 0) AS total_variance_qty,
                COALESCE(SUM((i.counted_qty - i.system_qty) * i.unit_cost), 0) AS total_variance_value
            FROM " . self::$tableName . " r
            LEFT JOIN physical_inventory_items i ON i.report_id = r.id
            GROUP BY
                r.id,
                r.report_no,
                r.report_date,
                r.fund_cluster,
                r.inventory_type,
                r.classification_code,
                r.location,
                r.prepared_by,
                r.verified_by,
                r.remarks,
                r.created_at,
                r.updated_at
            ORDER BY r.report_date DESC, r.report_no DESC
        ";

        $stmt = self::$db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class PhysicalInventoryItem extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'physical_inventory_items';

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
        PhysicalInventoryReport::ensureSchema();
    }

    protected static function ensureDb(): void
    {
        if (self::$db === null) {
            self::$db = parent::Db();
        }
        PhysicalInventoryReport::ensureSchema();
    }

    public static function getAllByReport(int $reportId): array
    {
        self::ensureDb();

        $stmt = self::$db->prepare(
            "SELECT
                i.*,
                items.code AS item_code,
                items.description AS item_description,
                items.unit AS item_unit,
                (i.counted_qty - i.system_qty) AS variance_qty,
                (i.unit_cost * i.system_qty) AS book_value,
                (i.unit_cost * i.counted_qty) AS counted_value,
                ((i.counted_qty - i.system_qty) * i.unit_cost) AS variance_value
             FROM " . self::$tableName . " i
             JOIN items ON items.id = i.item_id
             WHERE i.report_id = ?
             ORDER BY items.code ASC"
        );

        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByReport(int $reportId): void
    {
        $stmt = self::$db->prepare(
            "DELETE FROM " . self::$tableName . " WHERE report_id = ?"
        );
        $stmt->execute([$reportId]);
    }

    public static function getByReportAndItem(int $reportId, int $itemId)
    {
        self::ensureDb();

        $stmt = self::$db->prepare(
            "SELECT * FROM " . self::$tableName . " WHERE report_id = ? AND item_id = ? LIMIT 1"
        );
        $stmt->execute([$reportId, $itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function add(
        int $reportId,
        int $itemId,
        int $systemQty,
        int $countedQty,
        float $unitCost,
        ?string $location,
        ?string $remarks
    ) {
        self::ensureDb();

        $location = trim((string)$location);
        $location = $location === '' ? null : $location;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (report_id, item_id, system_qty, counted_qty, unit_cost, location, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $reportId,
            $itemId,
            $systemQty,
            $countedQty,
            $unitCost,
            $location,
            $remarks
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        int $id,
        int $reportId,
        int $itemId,
        int $systemQty,
        int $countedQty,
        float $unitCost,
        ?string $location,
        ?string $remarks
    ): bool {
        self::ensureDb();

        $location = trim((string)$location);
        $location = $location === '' ? null : $location;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET report_id = ?, item_id = ?, system_qty = ?, counted_qty = ?, unit_cost = ?, location = ?, remarks = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $reportId,
            $itemId,
            $systemQty,
            $countedQty,
            $unitCost,
            $location,
            $remarks,
            $id
        ]);
    }
}
