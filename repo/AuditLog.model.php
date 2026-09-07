<?php

require_once __DIR__ . '/../core/BaseModel.php';

class AuditLog extends BaseModel
{
    protected static $db = null;
    protected static $activityTable = 'activity_logs';
    protected static $auditTable = 'audits';
    protected static $detailTable = 'audit_details';
    protected static $roleScopes = [
        1 => [ // System Admin (exclude external auditor scope)
            'exclude_action_like' => [
                'physical_inventory.%',
                'physical_ppe.%',
                'unserviceable.%',
                'rpci.%',
                'rpcppe.%',
                'iirup.%',
            ],
            'exclude_entity_in' => [
                'physical_inventory_reports',
                'physical_inventory_items',
                'physical_ppe_reports',
                'physical_ppe_items',
                'unserviceable_reports',
                'unserviceable_items',
            ],
        ],
        2 => [ // Supply Officer
            'action_like' => [
                'supplier.%',
                'supply.%',
                'ris.%',
                'employee.ris%',
            ],
            'entity_in' => [
                'supplier',
                'supply_receipts',
                'supply_receipt_items',
                'supply_issuance',
                'supply_issuance_items',
                'requisition_slips',
                'requisition_items',
            ],
        ],
        3 => [ // Inventory Officer
            'action_like' => [
                'item.%',
                'inventory.%',
                'stock.%',
                'supply_ledger.%',
                'reorder.%',
                'reconciliation.%',
            ],
            'entity_in' => [
                'item',
                'items',
                'stock_inventory',
                'supply_ledger',
                'inventory_balance',
                'reorder_report',
                'inventory_reconciliation',
                'stock_cards',
            ],
        ],
        4 => [ // Property Custodian
            'action_like' => [
                'ics.%',
                'par.%',
                'property_card.%',
                'property_transfer.%',
                'property.%',
            ],
            'entity_in' => [
                'ics',
                'ics_items',
                'par',
                'par_items',
                'property_cards',
                'property_card_transactions',
                'property_transfers',
                'property_acknowledgment_receipts',
            ],
        ],
        5 => [ // Auditor
            'action_like' => [
                'physical_inventory.%',
                'physical_ppe.%',
                'unserviceable.%',
                'rpci.%',
                'rpcppe.%',
                'iirup.%',
            ],
            'entity_in' => [
                'physical_inventory_reports',
                'physical_inventory_items',
                'physical_ppe_reports',
                'physical_ppe_items',
                'unserviceable_reports',
                'unserviceable_items',
            ],
        ],
        6 => [ // Employee (future-facing)
            'action_like' => [
                'employee.%',
                'profile.%',
            ],
            'entity_in' => [
                'requisition_slips',
                'requisition_items',
            ],
        ],
    ];

    public function __construct()
    {
        parent::__construct(self::$activityTable);
        self::$db = parent::Db();
    }

    protected static function ensureDb(): void
    {
        if (self::$db === null) {
            self::$db = parent::Db();
        }
    }

    public static function scopeForRole(int $roleId): array
    {
        return self::$roleScopes[$roleId] ?? ['action_like' => ['__no_match__']];
    }

    protected static function buildScopeSql(array $scope, array &$params, bool $hasWhere = false): string
    {
        if (empty($scope)) {
            return '';
        }

        $includeClauses = [];
        $excludeClauses = [];

        if (!empty($scope['action_like']) && is_array($scope['action_like'])) {
            foreach ($scope['action_like'] as $pattern) {
                $includeClauses[] = 'al.action LIKE ?';
                $params[] = (string)$pattern;
            }
        }

        if (!empty($scope['entity_in']) && is_array($scope['entity_in'])) {
            $placeholders = implode(', ', array_fill(0, count($scope['entity_in']), '?'));
            $includeClauses[] = 'al.entity IN (' . $placeholders . ')';
            foreach ($scope['entity_in'] as $entity) {
                $params[] = (string)$entity;
            }
        }

        if (!empty($scope['exclude_action_like']) && is_array($scope['exclude_action_like'])) {
            foreach ($scope['exclude_action_like'] as $pattern) {
                $excludeClauses[] = 'al.action NOT LIKE ?';
                $params[] = (string)$pattern;
            }
        }

        if (!empty($scope['exclude_entity_in']) && is_array($scope['exclude_entity_in'])) {
            $placeholders = implode(', ', array_fill(0, count($scope['exclude_entity_in']), '?'));
            $excludeClauses[] = 'al.entity NOT IN (' . $placeholders . ')';
            foreach ($scope['exclude_entity_in'] as $entity) {
                $params[] = (string)$entity;
            }
        }

        $scopeGroups = [];
        if (!empty($includeClauses)) {
            $scopeGroups[] = '(' . implode(' OR ', $includeClauses) . ')';
        }
        if (!empty($excludeClauses)) {
            $scopeGroups[] = '(' . implode(' AND ', $excludeClauses) . ')';
        }

        if (empty($scopeGroups)) {
            return '';
        }

        return ($hasWhere ? ' AND ' : ' WHERE ') . implode(' AND ', $scopeGroups);
    }

    protected static function encodeMetadata(array $metadata): string
    {
        if (empty($metadata)) {
            return '{}';
        }

        try {
            return json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $e) {
            error_log('AuditLog metadata encode failed: ' . $e->getMessage());
            return '{}';
        }
    }

    protected static function normaliseChanges(array $details): array
    {
        $normalised = [];
        foreach ($details as $field => $change) {
            if (is_array($change)) {
                $old = $change['old'] ?? null;
                $new = $change['new'] ?? null;
            } else {
                // Treat non-array change as value replacement
                $old = null;
                $new = $change;
            }

            $normalised[$field] = [
                'old' => self::stringifyValue($old),
                'new' => self::stringifyValue($new),
            ];
        }

        return $normalised;
    }

    protected static function stringifyValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            return '[unserializable]';
        }
    }

    public function record(
        $actorId,
        string $action,
        string $entity,
        $entityId = null,
        array $metadata = [],
        array $details = []
    ): bool {
        self::ensureDb();

        $ipAddress = $metadata['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $metadata['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

        // Do not duplicate environmental data inside metadata payload
        unset($metadata['ip'], $metadata['user_agent']);

        $metadataJson = self::encodeMetadata($metadata);

        try {
            $stmt = self::$db->prepare(
                "INSERT INTO " . self::$activityTable . "
                 (user_id, action, entity, entity_id, metadata, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            $stmt->execute([$actorId, $action, $entity, $entityId, $metadataJson, $ipAddress, $userAgent]);
            $activityId = (int)self::$db->lastInsertId();

            $changeSet = $details;
            if (empty($changeSet) && isset($metadata['changes']) && is_array($metadata['changes'])) {
                $changeSet = $metadata['changes'];
            }

            if (!empty($changeSet)) {
                $normalised = self::normaliseChanges($changeSet);
                $summary = count($normalised) . ' field' . (count($normalised) === 1 ? '' : 's') . ' updated';

                $auditStmt = self::$db->prepare(
                    "INSERT INTO " . self::$auditTable . "
                     (activity_id, user_id, entity, entity_id, action, summary, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())"
                );
                $auditStmt->execute([$activityId, $actorId, $entity, $entityId, $action, $summary]);
                $auditId = (int)self::$db->lastInsertId();

                $detailStmt = self::$db->prepare(
                    "INSERT INTO " . self::$detailTable . " (audit_id, field_name, old_value, new_value)
                     VALUES (?, ?, ?, ?)"
                );

                foreach ($normalised as $field => $change) {
                    $detailStmt->execute([$auditId, (string)$field, $change['old'], $change['new']]);
                }
            }

            return true;
        } catch (Throwable $e) {
            error_log('AuditLog insert failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function fetchRecentActivities(int $limit = 50): array
    {
        return self::fetchActivitiesPage(1, $limit);
    }

    public static function countActivities(array $scope = []): int
    {
        self::ensureDb();
        $params = [];
        $scopeSql = self::buildScopeSql($scope, $params);
        $stmt = self::$db->prepare(
            "SELECT COUNT(*) FROM " . self::$activityTable . " al" . $scopeSql
        );
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    protected static function buildActivitySearchSql(string $search, array &$params): string
    {
        $search = trim($search);
        if ($search === '') {
            return '';
        }

        $like = '%' . $search . '%';
        $params[] = $like; // action
        $params[] = $like; // entity
        $params[] = $like; // entity id
        $params[] = $like; // summary
        $params[] = $like; // ip
        $params[] = $like; // firstname
        $params[] = $like; // lastname
        $params[] = $like; // full name
        $params[] = $like; // timestamp

        return " WHERE (
            al.action LIKE ?
            OR al.entity LIKE ?
            OR CAST(al.entity_id AS CHAR) LIKE ?
            OR COALESCE(a.summary, '') LIKE ?
            OR COALESCE(al.ip_address, '') LIKE ?
            OR COALESCE(u.firstname, '') LIKE ?
            OR COALESCE(u.lastname, '') LIKE ?
            OR CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) LIKE ?
            OR CAST(al.created_at AS CHAR) LIKE ?
        )";
    }

    public static function countActivitiesFiltered(string $search = '', array $scope = []): int
    {
        self::ensureDb();
        $params = [];
        $whereSql = self::buildActivitySearchSql($search, $params);
        $whereSql .= self::buildScopeSql($scope, $params, $whereSql !== '');

        $stmt = self::$db->prepare(
            "SELECT COUNT(*) 
             FROM " . self::$activityTable . " al
             LEFT JOIN " . self::$auditTable . " a ON a.activity_id = al.id
             LEFT JOIN users u ON al.user_id = u.id" . $whereSql
        );
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function fetchActivitiesDataTable(
        int $start = 0,
        int $length = 10,
        string $search = '',
        string $orderBy = 'al.created_at',
        string $orderDir = 'DESC',
        array $scope = []
    ): array {
        self::ensureDb();
        $start = max(0, $start);
        $length = max(1, min($length, 200));
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $allowedOrderColumns = [
            'al.created_at',
            'u.firstname',
            'al.action',
            'al.entity',
            'al.entity_id',
            'a.summary',
            'al.ip_address',
        ];
        if (!in_array($orderBy, $allowedOrderColumns, true)) {
            $orderBy = 'al.created_at';
        }

        $params = [];
        $whereSql = self::buildActivitySearchSql($search, $params);
        $whereSql .= self::buildScopeSql($scope, $params, $whereSql !== '');

        $stmt = self::$db->prepare(
            "SELECT al.*, u.firstname, u.lastname, a.summary AS audit_summary
             FROM " . self::$activityTable . " al
             LEFT JOIN " . self::$auditTable . " a ON a.activity_id = al.id
             LEFT JOIN users u ON al.user_id = u.id" .
             $whereSql .
             " ORDER BY " . $orderBy . " " . $orderDir . ", al.id DESC
             LIMIT " . $length . " OFFSET " . $start
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchActivitiesPage(int $page = 1, int $perPage = 25, array $scope = []): array
    {
        self::ensureDb();
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 5000));
        $offset = ($page - 1) * $perPage;
        $params = [];
        $scopeSql = self::buildScopeSql($scope, $params);

        $stmt = self::$db->prepare(
            "SELECT al.*, u.firstname, u.lastname, a.summary AS audit_summary
             FROM " . self::$activityTable . " al
             LEFT JOIN " . self::$auditTable . " a ON a.activity_id = al.id
             LEFT JOIN users u ON al.user_id = u.id
             " . $scopeSql . "
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT " . $perPage . " OFFSET " . $offset
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected static function canAccessActivity(int $activityId, array $scope = []): bool
    {
        self::ensureDb();
        $params = [$activityId];
        $scopeSql = self::buildScopeSql($scope, $params, true);
        $stmt = self::$db->prepare(
            "SELECT al.id
             FROM " . self::$activityTable . " al
             WHERE al.id = ?" . $scopeSql . "
             LIMIT 1"
        );
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public static function fetchAuditTrail(int $activityId): array
    {
        self::ensureDb();
        $stmt = self::$db->prepare(
            "SELECT ad.field_name, ad.old_value, ad.new_value
             FROM " . self::$auditTable . " a
             JOIN " . self::$detailTable . " ad ON ad.audit_id = a.id
             WHERE a.activity_id = ?
             ORDER BY ad.field_name ASC"
        );
        $stmt->execute([$activityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchAuditTrailScoped(int $activityId, array $scope = []): ?array
    {
        if (!self::canAccessActivity($activityId, $scope)) {
            return null;
        }

        return self::fetchAuditTrail($activityId);
    }
}
