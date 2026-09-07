<?php

require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/Item.model.php';

class PhysicalPpeReport extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'physical_ppe_reports';
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

    public static function ensureSchema(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        self::ensureDb();

        $reportSql = "
            CREATE TABLE IF NOT EXISTS physical_ppe_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_no VARCHAR(50) NOT NULL UNIQUE,
                report_date DATE NOT NULL,
                fund_cluster VARCHAR(50) NOT NULL,
                station VARCHAR(150) NOT NULL,
                prepared_by INT NOT NULL,
                verified_by INT NULL,
                remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $itemsSql = "
            CREATE TABLE IF NOT EXISTS physical_ppe_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_id INT NOT NULL,
                item_id INT NOT NULL,
                property_no VARCHAR(100) NULL,
                asset_tag VARCHAR(100) NULL,
                property_card_qty INT NOT NULL DEFAULT 0,
                physical_qty INT NOT NULL DEFAULT 0,
                cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_ppereport FOREIGN KEY (report_id) REFERENCES physical_ppe_reports(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        try {
            self::$db->exec($reportSql);
            self::$db->exec($itemsSql);
        } catch (Throwable $e) {
            error_log('PhysicalPpeReport::ensureSchema failed: ' . $e->getMessage());
        }

        self::$schemaInitialized = true;
    }

    public static function getNextReportNo(): string
    {
        self::ensureSchema();

        $stmt = self::$db->query("SELECT id FROM " . self::$tableName . " ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result ? ((int)$result['id'] + 1) : 1;
        $year = date('Y');
        return sprintf('RPCPPE-%s-%04d', $year, $nextId);
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
        string $fundCluster,
        string $station,
        int $preparedBy,
        ?int $verifiedBy,
        ?string $remarks
    ) {
        self::ensureSchema();

        $fundCluster = trim($fundCluster);
        $station = trim($station);

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . "
                (report_no, report_date, fund_cluster, station, prepared_by, verified_by, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $reportNo,
            $reportDate,
            $fundCluster,
            $station,
            $preparedBy,
            $verifiedBy,
            $remarks
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        int $id,
        string $reportNo,
        string $reportDate,
        string $fundCluster,
        string $station,
        int $preparedBy,
        ?int $verifiedBy,
        ?string $remarks
    ): bool {
        self::ensureSchema();

        $fundCluster = trim($fundCluster);
        $station = trim($station);

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET report_no = ?, report_date = ?, fund_cluster = ?, station = ?, prepared_by = ?, verified_by = ?, remarks = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $reportNo,
            $reportDate,
            $fundCluster,
            $station,
            $preparedBy,
            $verifiedBy,
            $remarks,
            $id
        ]);
    }

    public static function deleteReport(int $id): bool
    {
        self::ensureSchema();

        $itemModel = new PhysicalPpeItem();
        $itemModel->deleteByReport($id);

        $stmt = self::$db->prepare("DELETE FROM " . self::$tableName . " WHERE id = ?");
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
                r.station,
                r.prepared_by,
                r.verified_by,
                r.remarks,
                r.created_at,
                r.updated_at,
                COUNT(i.id) AS total_items,
                COALESCE(SUM(i.property_card_qty), 0) AS total_property_qty,
                COALESCE(SUM(i.physical_qty), 0) AS total_physical_qty,
                COALESCE(SUM(i.physical_qty - i.property_card_qty), 0) AS total_variance_qty,
                COALESCE(SUM(i.cost), 0) AS total_cost
            FROM " . self::$tableName . " r
            LEFT JOIN physical_ppe_items i ON i.report_id = r.id
            GROUP BY
                r.id,
                r.report_no,
                r.report_date,
                r.fund_cluster,
                r.station,
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

class PhysicalPpeItem extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'physical_ppe_items';

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
        PhysicalPpeReport::ensureSchema();
    }

    protected static function ensureDb(): void
    {
        if (self::$db === null) {
            self::$db = parent::Db();
        }
        PhysicalPpeReport::ensureSchema();
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
                (i.physical_qty - i.property_card_qty) AS variance_qty
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
        int $propertyCardQty,
        int $physicalQty,
        float $cost,
        ?string $propertyNo,
        ?string $assetTag,
        ?string $remarks
    ) {
        self::ensureDb();

        $propertyNo = trim((string)$propertyNo);
        $propertyNo = $propertyNo === '' ? null : $propertyNo;

        $assetTag = trim((string)$assetTag);
        $assetTag = $assetTag === '' ? null : $assetTag;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . "
                (report_id, item_id, property_no, asset_tag, property_card_qty, physical_qty, cost, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $reportId,
            $itemId,
            $propertyNo,
            $assetTag,
            $propertyCardQty,
            $physicalQty,
            $cost,
            $remarks
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        int $id,
        int $reportId,
        int $itemId,
        int $propertyCardQty,
        int $physicalQty,
        float $cost,
        ?string $propertyNo,
        ?string $assetTag,
        ?string $remarks
    ): bool {
        self::ensureDb();

        $propertyNo = trim((string)$propertyNo);
        $propertyNo = $propertyNo === '' ? null : $propertyNo;

        $assetTag = trim((string)$assetTag);
        $assetTag = $assetTag === '' ? null : $assetTag;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET report_id = ?, item_id = ?, property_no = ?, asset_tag = ?, property_card_qty = ?, physical_qty = ?, cost = ?, remarks = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $reportId,
            $itemId,
            $propertyNo,
            $assetTag,
            $propertyCardQty,
            $physicalQty,
            $cost,
            $remarks,
            $id
        ]);
    }
}
