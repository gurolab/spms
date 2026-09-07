<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/AuditLog.model.php';

header('Content-Type: application/json');

$roleId = (int)(getUser('roleid') ?? 0);
$auditScope = AuditLog::scopeForRole($roleId);

function formatLogTimestamp(?string $timestamp): string
{
    if (!$timestamp) {
        return '-';
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
    if (!$dt) {
        $dt = new DateTime($timestamp);
    }

    return $dt ? $dt->format('M d, Y h:i A') : $timestamp;
}

function metadataPreview(?string $metadataJson): string
{
    if (!$metadataJson) {
        return '';
    }

    $metadata = json_decode($metadataJson, true);
    if (!is_array($metadata) || empty($metadata)) {
        return '';
    }

    $keys = array_keys($metadata);
    $previewKeys = array_slice($keys, 0, 3);
    $fragments = [];
    foreach ($previewKeys as $key) {
        $value = $metadata[$key];
        if (is_scalar($value)) {
            $fragments[] = $key . ': ' . $value;
        } elseif (is_array($value)) {
            $fragments[] = $key . ': [object]';
        } else {
            $fragments[] = $key . ': ...';
        }
    }

    return implode(', ', $fragments);
}

function encodeMetadataAttr(array $metadata): string
{
    try {
        $payload = json_encode(
            $metadata,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
    } catch (Throwable $e) {
        $payload = '{}';
    }

    return htmlspecialchars((string)$payload, ENT_QUOTES, 'UTF-8');
}

if (GETACT('list')) {
    $draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 0;
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
    if ($length === -1) {
        $length = 100;
    }
    $length = max(10, min($length, 100));
    $searchValue = trim((string)($_GET['search']['value'] ?? ''));
    $orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

    $columnMap = [
        0 => 'al.created_at',
        1 => 'u.firstname',
        2 => 'al.action',
        3 => 'al.entity',
        4 => 'al.entity_id',
        5 => 'a.summary',
        6 => 'al.ip_address',
    ];
    $orderBy = $columnMap[$orderColumnIndex] ?? 'al.created_at';

    try {
        $totalCount = AuditLog::countActivities($auditScope);
        $filteredCount = $searchValue === '' ? $totalCount : AuditLog::countActivitiesFiltered($searchValue, $auditScope);
        $rows = AuditLog::fetchActivitiesDataTable($start, $length, $searchValue, $orderBy, $orderDir, $auditScope);
        $data = [];

        foreach ($rows as $row) {
            $fullName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
            $displayUser = $fullName !== '' ? $fullName : 'System';
            $summary = trim((string)($row['audit_summary'] ?? ''));
            if ($summary === '') {
                $summary = metadataPreview($row['metadata'] ?? null);
            }

            $metadata = [];
            if (!empty($row['metadata'])) {
                $decoded = json_decode((string)$row['metadata'], true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $metadataAttr = encodeMetadataAttr($metadata);
            $hasTrail = !empty($row['audit_summary']);
            $options = '<div class="flex-center gap-8">'
                . '<button type="button" class="btn btn-sm btn-outline-secondary btn-view-metadata" data-bs-toggle="tooltip" data-bs-title="View metadata" data-metadata="' . $metadataAttr . '"><i class="ph ph-info"></i></button>'
                . '<button type="button" class="btn btn-sm btn-outline-primary btn-view-trail" data-bs-toggle="tooltip" data-bs-title="View audit trail" data-activity-id="' . (int)$row['id'] . '"' . ($hasTrail ? '' : ' disabled') . '><i class="ph ph-shuffle"></i></button>'
                . '</div>';

            $data[] = [
                'timestamp' => htmlspecialchars(formatLogTimestamp($row['created_at'] ?? null), ENT_QUOTES, 'UTF-8'),
                'user' => htmlspecialchars($displayUser, ENT_QUOTES, 'UTF-8'),
                'action' => '<code>' . htmlspecialchars((string)($row['action'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code>',
                'entity' => htmlspecialchars((string)($row['entity'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'entity_id' => $row['entity_id'] !== null ? (string)(int)$row['entity_id'] : '-',
                'summary' => htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'),
                'ip_address' => htmlspecialchars((string)($row['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                'options' => $options,
            ];
        }

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $filteredCount,
            'data' => $data,
        ]);
    } catch (Throwable $e) {
        error_log('audit_logs list fetch failed: ' . $e->getMessage());
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Failed to load audit logs.',
        ]);
    }

    exit;
}

if (!GETACT('trail')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unsupported action.',
    ]);
    exit;
}

$activityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($activityId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid activity reference.',
    ]);
    exit;
}

try {
    $trail = AuditLog::fetchAuditTrailScoped($activityId, $auditScope);
    if ($trail === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Activity is not available in your audit scope.',
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'trail' => $trail,
    ]);
} catch (Throwable $e) {
    error_log('audit_logs trail fetch failed: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load audit trail.',
    ]);
}
