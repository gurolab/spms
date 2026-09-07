<?php

require_once __DIR__ . '/../core/BaseModel.php';

class PropertyCard extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'property_cards';
    public const STATUSES = ['Assigned', 'For Repair', 'Unserviceable', 'Disposed'];

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

    public static function getNextCardNo(): string
    {
        self::ensureDb();
        $stmt = self::$db->query("SELECT id FROM " . self::$tableName . " ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result ? ((int)$result['id'] + 1) : 1;
        $year = date('Y');
        return sprintf("PC-%s-%04d", $year, $nextId);
    }

    public static function getByCardNo(string $cardNo)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE card_no = ?");
        $stmt->execute([$cardNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getByPropertyTag(string $propertyTag)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE property_tag = ?");
        $stmt->execute([$propertyTag]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function add(
        string $card_no,
        ?int $par_id,
        int $item_id,
        string $property_tag,
        ?int $accountable_officer,
        ?string $location,
        ?string $acquisition_date,
        ?float $acquisition_cost,
        string $current_status,
        ?string $remarks
    ) {
        self::ensureDb();

        $location = trim((string)$location);
        $location = $location === '' ? null : $location;
        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (card_no, par_id, item_id, property_tag, accountable_officer, location, acquisition_date, acquisition_cost, current_status, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $card_no,
            $par_id ?: null,
            $item_id,
            $property_tag,
            $accountable_officer ?: null,
            $location,
            $acquisition_date ?: null,
            $acquisition_cost ?? null,
            $current_status,
            $remarks
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        string $card_no,
        ?int $par_id,
        int $item_id,
        string $property_tag,
        ?int $accountable_officer,
        ?string $location,
        ?string $acquisition_date,
        ?float $acquisition_cost,
        string $current_status,
        ?string $remarks,
        int $id
    ): bool {
        self::ensureDb();

        $location = trim((string)$location);
        $location = $location === '' ? null : $location;
        $remarks = trim((string)$remarks);
        $remarks = $remarks === '' ? null : $remarks;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET card_no = ?, par_id = ?, item_id = ?, property_tag = ?, accountable_officer = ?, location = ?, acquisition_date = ?, acquisition_cost = ?, current_status = ?, remarks = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $card_no,
            $par_id ?: null,
            $item_id,
            $property_tag,
            $accountable_officer ?: null,
            $location,
            $acquisition_date ?: null,
            $acquisition_cost ?? null,
            $current_status,
            $remarks,
            $id
        ]);
    }

    public static function updateStatus(int $id, string $status): bool
    {
        self::ensureDb();
        $stmt = self::$db->prepare("UPDATE " . self::$tableName . " SET current_status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}

class PropertyCardTransaction extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'property_card_transactions';
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
            error_log('PropertyCardTransaction::ensureColumn failed for ' . $column . ': ' . $e->getMessage());
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

        self::ensureColumn('receipt_qty', 'receipt_qty INT NOT NULL DEFAULT 0 AFTER performed_by');
        self::ensureColumn('issue_qty', 'issue_qty INT NOT NULL DEFAULT 0 AFTER receipt_qty');
        self::ensureColumn('office_officer', 'office_officer VARCHAR(255) NULL AFTER issue_qty');
        self::ensureColumn('office_officer_id', 'office_officer_id INT NULL AFTER office_officer');
        self::ensureColumn('balance_qty', 'balance_qty INT NOT NULL DEFAULT 0 AFTER office_officer_id');
        self::ensureColumn('amount', 'amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER balance_qty');

        self::$schemaInitialized = true;
    }

    public function getAll($property_card_id = null)
    {
        self::ensureDb();
        $params = [];
        $condition = "";

        if ($property_card_id !== null && $property_card_id !== '') {
            $condition = " WHERE property_card_id = ?";
            $params[] = $property_card_id;
        }

        $stmt = self::$db->prepare(
            "SELECT * FROM " . self::$tableName . $condition . " ORDER BY transaction_date DESC, id DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllByCardAscending(int $property_card_id): array
    {
        self::ensureDb();
        $stmt = self::$db->prepare(
            "SELECT * FROM " . self::$tableName . " WHERE property_card_id = ? ORDER BY transaction_date ASC, id ASC"
        );
        $stmt->execute([$property_card_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getLatestByCard(int $property_card_id)
    {
        self::ensureDb();
        $stmt = self::$db->prepare(
            "SELECT * FROM " . self::$tableName . " WHERE property_card_id = ? ORDER BY transaction_date DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$property_card_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function add(
        int $property_card_id,
        string $transaction_date,
        string $status,
        ?string $reference_no,
        ?string $notes,
        ?int $performed_by,
        int $receipt_qty = 0,
        int $issue_qty = 0,
        ?string $office_officer = null,
        ?int $office_officer_id = null,
        ?int $balance_qty = null,
        ?float $amount = null
    ) {
        self::ensureDb();

        $reference_no = trim((string)$reference_no);
        $reference_no = $reference_no === '' ? null : $reference_no;

        $notes = trim((string)$notes);
        $notes = $notes === '' ? null : $notes;
        $office_officer = trim((string)$office_officer);
        $office_officer = $office_officer === '' ? null : $office_officer;
        $office_officer_id = $office_officer_id !== null && $office_officer_id > 0 ? (int)$office_officer_id : null;

        $receipt_qty = max(0, (int)$receipt_qty);
        $issue_qty = max(0, (int)$issue_qty);
        $balance_qty = $balance_qty !== null ? (int)$balance_qty : 0;
        $amount = $amount !== null ? (float)$amount : 0.0;

        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (property_card_id, transaction_date, status, reference_no, notes, performed_by, receipt_qty, issue_qty, office_officer, office_officer_id, balance_qty, amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $property_card_id,
            $transaction_date,
            $status,
            $reference_no,
            $notes,
            $performed_by ?: null,
            $receipt_qty,
            $issue_qty,
            $office_officer,
            $office_officer_id,
            $balance_qty,
            $amount
        ]);

        return $stmt->rowCount() > 0 ? self::$db->lastInsertId() : false;
    }

    public static function update(
        int $id,
        int $property_card_id,
        string $transaction_date,
        string $status,
        ?string $reference_no,
        ?string $notes,
        ?int $performed_by,
        int $receipt_qty = 0,
        int $issue_qty = 0,
        ?string $office_officer = null,
        ?int $office_officer_id = null,
        ?int $balance_qty = null,
        ?float $amount = null
    ): bool {
        self::ensureDb();

        $reference_no = trim((string)$reference_no);
        $reference_no = $reference_no === '' ? null : $reference_no;

        $notes = trim((string)$notes);
        $notes = $notes === '' ? null : $notes;
        $office_officer = trim((string)$office_officer);
        $office_officer = $office_officer === '' ? null : $office_officer;
        $office_officer_id = $office_officer_id !== null && $office_officer_id > 0 ? (int)$office_officer_id : null;

        $receipt_qty = max(0, (int)$receipt_qty);
        $issue_qty = max(0, (int)$issue_qty);
        $balance_qty = $balance_qty !== null ? (int)$balance_qty : 0;
        $amount = $amount !== null ? (float)$amount : 0.0;

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . "
             SET property_card_id = ?, transaction_date = ?, status = ?, reference_no = ?, notes = ?, performed_by = ?, receipt_qty = ?, issue_qty = ?, office_officer = ?, office_officer_id = ?, balance_qty = ?, amount = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $property_card_id,
            $transaction_date,
            $status,
            $reference_no,
            $notes,
            $performed_by ?: null,
            $receipt_qty,
            $issue_qty,
            $office_officer,
            $office_officer_id,
            $balance_qty,
            $amount,
            $id
        ]);
    }

    public static function updateLedgerSnapshot(int $id, int $balance_qty, float $amount): bool
    {
        self::ensureDb();
        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " SET balance_qty = ?, amount = ? WHERE id = ?"
        );
        return $stmt->execute([(int)$balance_qty, (float)$amount, $id]);
    }
}
