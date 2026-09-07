<?php

require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/Item.model.php';

class UnserviceablePropertyReport extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'unserviceable_property_reports';
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

    protected static function reportColumnExists(string $column): bool
    {
        $stmt = self::$db->query(
            "SHOW COLUMNS FROM unserviceable_property_reports LIKE " . self::$db->quote($column)
        );
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected static function ensureReportColumn(string $column, string $definition): void
    {
        try {
            if (!self::reportColumnExists($column)) {
                self::$db->exec("ALTER TABLE unserviceable_property_reports ADD COLUMN " . $definition);
            }
        } catch (Throwable $e) {
            error_log('UnserviceablePropertyReport::ensureReportColumn failed for ' . $column . ': ' . $e->getMessage());
        }
    }

    protected static function itemColumnExists(string $column): bool
    {
        $stmt = self::$db->query(
            "SHOW COLUMNS FROM unserviceable_property_items LIKE " . self::$db->quote($column)
        );
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected static function ensureItemColumn(string $column, string $definition): void
    {
        try {
            if (!self::itemColumnExists($column)) {
                self::$db->exec("ALTER TABLE unserviceable_property_items ADD COLUMN " . $definition);
            }
        } catch (Throwable $e) {
            error_log('UnserviceablePropertyReport::ensureItemColumn failed for ' . $column . ': ' . $e->getMessage());
        }
    }

    public static function ensureSchema(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        self::ensureDb();

        $reportSql = "
            CREATE TABLE IF NOT EXISTS unserviceable_property_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_no VARCHAR(50) NOT NULL UNIQUE,
                report_date DATE NOT NULL,
                entity_name VARCHAR(150) NOT NULL,
                office VARCHAR(150) NOT NULL,
                fund_cluster VARCHAR(80) NULL,
                prepared_by INT NOT NULL,
                inspected_by INT NULL,
                approved_by INT NULL,
                remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $itemsSql = "
            CREATE TABLE IF NOT EXISTS unserviceable_property_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_id INT NOT NULL,
                item_id INT NOT NULL,
                property_no VARCHAR(100) NULL,
                quantity INT NOT NULL DEFAULT 0,
                unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                appraised_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                condition_notes TEXT NULL,
                disposal_method VARCHAR(100) NOT NULL,
                remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_unserviceable_report
                    FOREIGN KEY (report_id)
                    REFERENCES unserviceable_property_reports(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        try {
            self::$db->exec($reportSql);
            self::$db->exec($itemsSql);

            // Backward-compatible upgrades for IIRUP header fields.
            self::ensureReportColumn('fund_cluster', 'fund_cluster VARCHAR(80) NULL AFTER office');

            // Backward-compatible upgrades for richer Appendix 74 fields.
            self::ensureItemColumn('date_acquired', 'date_acquired DATE NULL AFTER property_no');
            self::ensureItemColumn('total_cost', 'total_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER unit_cost');
            self::ensureItemColumn('accumulated_depreciation', 'accumulated_depreciation DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER total_cost');
            self::ensureItemColumn('accumulated_impairment_loss', 'accumulated_impairment_loss DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER accumulated_depreciation');
            self::ensureItemColumn('carrying_amount', 'carrying_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER accumulated_impairment_loss');
            self::ensureItemColumn('disposal_sale', 'disposal_sale TINYINT(1) NOT NULL DEFAULT 0 AFTER disposal_method');
            self::ensureItemColumn('disposal_transfer', 'disposal_transfer TINYINT(1) NOT NULL DEFAULT 0 AFTER disposal_sale');
            self::ensureItemColumn('disposal_destruction', 'disposal_destruction TINYINT(1) NOT NULL DEFAULT 0 AFTER disposal_transfer');
            self::ensureItemColumn('disposal_other', 'disposal_other VARCHAR(120) NULL AFTER disposal_destruction');
            self::ensureItemColumn('disposal_total', 'disposal_total DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER disposal_other');
            self::ensureItemColumn('or_no', 'or_no VARCHAR(60) NULL AFTER disposal_total');
            self::ensureItemColumn('sales_amount', 'sales_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER or_no');
        } catch (Throwable $e) {
            error_log('UnserviceablePropertyReport::ensureSchema failed: ' . $e->getMessage());
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

        return sprintf('IIRUP-%s-%04d', $year, $nextId);
    }

    public static function getByReportNo(string $reportNo)
    {
        self::ensureSchema();

        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE report_no = ? LIMIT 1");
        $stmt->execute([$reportNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function add(
        string $reportNo,
        string $reportDate,
        string $entityName,
        string $office,
        ?string $fundCluster,
        int $preparedBy,
        ?int $inspectedBy,
        ?int $approvedBy,
        ?string $remarks
    ) {
        self::ensureSchema();

        $entityName = trim($entityName);
        $office = trim($office);
        $fundCluster = trim((string)$fundCluster);
        $fundCluster = $fundCluster === '' ? null : $fundCluster;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $columns = ['report_no', 'report_date', 'entity_name', 'office'];
        $values = [
            $reportNo,
            $reportDate,
            $entityName,
            $office,
        ];

        if (self::reportColumnExists('fund_cluster')) {
            $columns[] = 'fund_cluster';
            $values[] = $fundCluster;
        }

        $columns = array_merge($columns, ['prepared_by', 'inspected_by', 'approved_by', 'remarks']);
        $values = array_merge($values, [
            $preparedBy,
            $inspectedBy,
            $approvedBy,
            $remarks
        ]);

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (" . implode(', ', $columns) . ")
             VALUES (" . $placeholders . ")"
        );

        $stmt->execute($values);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        int $id,
        string $reportNo,
        string $reportDate,
        string $entityName,
        string $office,
        ?string $fundCluster,
        int $preparedBy,
        ?int $inspectedBy,
        ?int $approvedBy,
        ?string $remarks
    ): bool {
        self::ensureSchema();

        $entityName = trim($entityName);
        $office = trim($office);
        $fundCluster = trim((string)$fundCluster);
        $fundCluster = $fundCluster === '' ? null : $fundCluster;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $setParts = [
            'report_no = ?',
            'report_date = ?',
            'entity_name = ?',
            'office = ?',
        ];
        $values = [
            $reportNo,
            $reportDate,
            $entityName,
            $office,
        ];

        if (self::reportColumnExists('fund_cluster')) {
            $setParts[] = 'fund_cluster = ?';
            $values[] = $fundCluster;
        }

        $setParts = array_merge($setParts, ['prepared_by = ?', 'inspected_by = ?', 'approved_by = ?', 'remarks = ?']);
        $values = array_merge($values, [
            $preparedBy,
            $inspectedBy,
            $approvedBy,
            $remarks,
            $id
        ]);

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET " . implode(', ', $setParts) . "
             WHERE id = ?"
        );

        return $stmt->execute($values);
    }

    public static function deleteReport(int $id): bool
    {
        self::ensureSchema();

        $itemModel = new UnserviceablePropertyItem();
        $itemModel->deleteByReport($id);

        $stmt = self::$db->prepare("DELETE FROM " . self::$tableName . " WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getAllWithSummary(): array
    {
        self::ensureSchema();

        $hasFundCluster = self::reportColumnExists('fund_cluster');
        $fundClusterSelect = $hasFundCluster ? 'r.fund_cluster' : 'NULL AS fund_cluster';
        $fundClusterGroupBy = $hasFundCluster ? "r.fund_cluster,\n                " : '';

        $sql = "
            SELECT
                r.id,
                r.report_no,
                r.report_date,
                r.entity_name,
                r.office,
                {$fundClusterSelect},
                r.prepared_by,
                r.inspected_by,
                r.approved_by,
                r.remarks,
                r.created_at,
                r.updated_at,
                COUNT(i.id) AS total_items,
                COALESCE(SUM(i.quantity), 0) AS total_quantity,
                COALESCE(SUM(i.appraised_value), 0) AS total_appraised_value
            FROM " . self::$tableName . " r
            LEFT JOIN unserviceable_property_items i ON i.report_id = r.id
            GROUP BY
                r.id,
                r.report_no,
                r.report_date,
                r.entity_name,
                r.office,
                {$fundClusterGroupBy}r.prepared_by,
                r.inspected_by,
                r.approved_by,
                r.remarks,
                r.created_at,
                r.updated_at
            ORDER BY r.report_date DESC, r.report_no DESC
        ";

        $stmt = self::$db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class UnserviceablePropertyItem extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'unserviceable_property_items';

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
        UnserviceablePropertyReport::ensureSchema();
    }

    protected static function ensureDb(): void
    {
        if (self::$db === null) {
            self::$db = parent::Db();
        }
        UnserviceablePropertyReport::ensureSchema();
    }

    public static function getAllByReport(int $reportId): array
    {
        self::ensureDb();

        $stmt = self::$db->prepare(
            "SELECT
                i.*,
                items.code AS item_code,
                items.description AS item_description,
                items.unit AS item_unit
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
        $stmt = self::$db->prepare("DELETE FROM " . self::$tableName . " WHERE report_id = ?");
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
        int $quantity,
        float $unitCost,
        float $appraisedValue,
        string $disposalMethod,
        ?string $propertyNo,
        ?string $conditionNotes,
        ?string $remarks,
        ?string $dateAcquired = null,
        ?float $totalCost = null,
        ?float $accumulatedDepreciation = null,
        ?float $accumulatedImpairmentLoss = null,
        ?float $carryingAmount = null,
        bool $disposalSale = false,
        bool $disposalTransfer = false,
        bool $disposalDestruction = false,
        ?string $disposalOther = null,
        ?float $disposalTotal = null,
        ?string $orNo = null,
        ?float $salesAmount = null
    ) {
        self::ensureDb();

        $propertyNo = trim((string)$propertyNo);
        $propertyNo = $propertyNo === '' ? null : $propertyNo;

        $conditionNotes = trim((string)$conditionNotes);
        $conditionNotes = $conditionNotes === '' ? null : $conditionNotes;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $dateAcquired = trim((string)$dateAcquired);
        $dateAcquired = $dateAcquired === '' ? null : $dateAcquired;
        $disposalOther = trim((string)$disposalOther);
        $disposalOther = $disposalOther === '' ? null : $disposalOther;
        $orNo = trim((string)$orNo);
        $orNo = $orNo === '' ? null : $orNo;

        $computedTotalCost = $totalCost !== null ? (float)$totalCost : ((float)$quantity * (float)$unitCost);
        $computedDepreciation = $accumulatedDepreciation !== null ? (float)$accumulatedDepreciation : 0.0;
        $computedImpairment = $accumulatedImpairmentLoss !== null ? (float)$accumulatedImpairmentLoss : 0.0;
        $computedCarrying = $carryingAmount !== null ? (float)$carryingAmount : ($computedTotalCost - $computedDepreciation - $computedImpairment);
        $computedDisposalTotal = $disposalTotal !== null ? (float)$disposalTotal : (float)$appraisedValue;
        $computedSalesAmount = $salesAmount !== null ? (float)$salesAmount : 0.0;

        $disposalMethod = trim($disposalMethod);

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . "
                (report_id, item_id, property_no, date_acquired, quantity, unit_cost, total_cost, accumulated_depreciation, accumulated_impairment_loss, carrying_amount, appraised_value, condition_notes, disposal_method, disposal_sale, disposal_transfer, disposal_destruction, disposal_other, disposal_total, or_no, sales_amount, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $reportId,
            $itemId,
            $propertyNo,
            $dateAcquired,
            $quantity,
            $unitCost,
            $computedTotalCost,
            $computedDepreciation,
            $computedImpairment,
            $computedCarrying,
            $appraisedValue,
            $conditionNotes,
            $disposalMethod,
            $disposalSale ? 1 : 0,
            $disposalTransfer ? 1 : 0,
            $disposalDestruction ? 1 : 0,
            $disposalOther,
            $computedDisposalTotal,
            $orNo,
            $computedSalesAmount,
            $remarks
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        int $id,
        int $reportId,
        int $itemId,
        int $quantity,
        float $unitCost,
        float $appraisedValue,
        string $disposalMethod,
        ?string $propertyNo,
        ?string $conditionNotes,
        ?string $remarks,
        ?string $dateAcquired = null,
        ?float $totalCost = null,
        ?float $accumulatedDepreciation = null,
        ?float $accumulatedImpairmentLoss = null,
        ?float $carryingAmount = null,
        bool $disposalSale = false,
        bool $disposalTransfer = false,
        bool $disposalDestruction = false,
        ?string $disposalOther = null,
        ?float $disposalTotal = null,
        ?string $orNo = null,
        ?float $salesAmount = null
    ): bool {
        self::ensureDb();

        $propertyNo = trim((string)$propertyNo);
        $propertyNo = $propertyNo === '' ? null : $propertyNo;

        $conditionNotes = trim((string)$conditionNotes);
        $conditionNotes = $conditionNotes === '' ? null : $conditionNotes;

        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $dateAcquired = trim((string)$dateAcquired);
        $dateAcquired = $dateAcquired === '' ? null : $dateAcquired;
        $disposalOther = trim((string)$disposalOther);
        $disposalOther = $disposalOther === '' ? null : $disposalOther;
        $orNo = trim((string)$orNo);
        $orNo = $orNo === '' ? null : $orNo;

        $computedTotalCost = $totalCost !== null ? (float)$totalCost : ((float)$quantity * (float)$unitCost);
        $computedDepreciation = $accumulatedDepreciation !== null ? (float)$accumulatedDepreciation : 0.0;
        $computedImpairment = $accumulatedImpairmentLoss !== null ? (float)$accumulatedImpairmentLoss : 0.0;
        $computedCarrying = $carryingAmount !== null ? (float)$carryingAmount : ($computedTotalCost - $computedDepreciation - $computedImpairment);
        $computedDisposalTotal = $disposalTotal !== null ? (float)$disposalTotal : (float)$appraisedValue;
        $computedSalesAmount = $salesAmount !== null ? (float)$salesAmount : 0.0;

        $disposalMethod = trim($disposalMethod);

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET report_id = ?, item_id = ?, property_no = ?, date_acquired = ?, quantity = ?, unit_cost = ?, total_cost = ?, accumulated_depreciation = ?, accumulated_impairment_loss = ?, carrying_amount = ?, appraised_value = ?, condition_notes = ?, disposal_method = ?, disposal_sale = ?, disposal_transfer = ?, disposal_destruction = ?, disposal_other = ?, disposal_total = ?, or_no = ?, sales_amount = ?, remarks = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $reportId,
            $itemId,
            $propertyNo,
            $dateAcquired,
            $quantity,
            $unitCost,
            $computedTotalCost,
            $computedDepreciation,
            $computedImpairment,
            $computedCarrying,
            $appraisedValue,
            $conditionNotes,
            $disposalMethod,
            $disposalSale ? 1 : 0,
            $disposalTransfer ? 1 : 0,
            $disposalDestruction ? 1 : 0,
            $disposalOther,
            $computedDisposalTotal,
            $orNo,
            $computedSalesAmount,
            $remarks,
            $id
        ]);
    }
}
