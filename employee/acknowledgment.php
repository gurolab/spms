<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([6]); // Employee

require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = 'Request and Property Status';
$currentUserId = (int)(getUser('id') ?? 0);

$pendingStatuses = ['Requested', 'Pending', 'Approved', 'Partial Issued', 'Issued'];
$statusBadgeMap = [
    'Requested' => 'bg-info text-dark',
    'Pending' => 'bg-warning text-dark',
    'Approved' => 'bg-success',
    'Partial Issued' => 'bg-primary',
    'Issued' => 'bg-primary',
    'Completed' => 'bg-secondary',
    'Rejected' => 'bg-danger',
    'Cancelled' => 'bg-danger',
    'Active' => 'bg-success',
    'Returned' => 'bg-warning text-dark',
    'Transferred' => 'bg-info text-dark',
    'Disposed' => 'bg-danger',
];

$risModel = new RIS();
$risItemsModel = new RIS_Items();
$myRequests = array_values(array_filter($risModel->getAll(), static function ($row) use ($currentUserId) {
    return (int)($row['requested_by'] ?? 0) === $currentUserId;
}));
usort($myRequests, static function ($a, $b) {
    return (int)$b['id'] <=> (int)$a['id'];
});

$requestItemSummary = [];
foreach ($risItemsModel->getAll() as $line) {
    $risId = (int)($line['ris_id'] ?? 0);
    if ($risId <= 0) {
        continue;
    }
    if (!isset($requestItemSummary[$risId])) {
        $requestItemSummary[$risId] = ['lines' => 0, 'qty_requested' => 0, 'qty_issued' => 0];
    }
    $requestItemSummary[$risId]['lines'] += 1;
    $requestItemSummary[$risId]['qty_requested'] += (int)($line['qty_requested'] ?? 0);
    $requestItemSummary[$risId]['qty_issued'] += (int)($line['qty_issued'] ?? 0);
}

$pendingRequests = 0;
$completedRequests = 0;
foreach ($myRequests as $row) {
    $status = (string)($row['status'] ?? '');
    if (in_array($status, $pendingStatuses, true)) {
        $pendingRequests++;
    }
    if ($status === 'Completed') {
        $completedRequests++;
    }
}

$propertyLines = [];
$activePropertyDocs = 0;
try {
    $db = Model::Db();

    $icsStmt = $db->prepare(
        "SELECT
            'ICS' AS document_type,
            h.id AS document_id,
            h.ics_no AS document_no,
            h.issued_date AS document_date,
            h.status AS document_status,
            h.remarks AS document_remarks,
            ii.property_no AS property_no,
            ii.qty AS qty,
            ii.unit_value AS unit_value,
            i.code AS item_code,
            i.description AS item_description
         FROM inventory_custodian_slips h
         INNER JOIN ics_items ii ON ii.ics_id = h.id
         LEFT JOIN items i ON i.id = ii.item_id
         WHERE h.assigned_to = :uid OR h.received_by = :uid"
    );
    $icsStmt->execute(['uid' => $currentUserId]);
    $icsRows = $icsStmt->fetchAll(PDO::FETCH_ASSOC);

    $parStmt = $db->prepare(
        "SELECT
            'PAR' AS document_type,
            h.id AS document_id,
            h.par_no AS document_no,
            h.issue_date AS document_date,
            h.status AS document_status,
            h.remarks AS document_remarks,
            pi.property_no AS property_no,
            pi.qty AS qty,
            pi.unit_value AS unit_value,
            i.code AS item_code,
            i.description AS item_description
         FROM property_acknowledgment_receipts h
         INNER JOIN par_items pi ON pi.par_id = h.id
         LEFT JOIN items i ON i.id = pi.item_id
         WHERE h.accountable_officer = :uid"
    );
    $parStmt->execute(['uid' => $currentUserId]);
    $parRows = $parStmt->fetchAll(PDO::FETCH_ASSOC);

    $propertyLines = array_merge($icsRows, $parRows);
    usort($propertyLines, static function ($a, $b) {
        $dateCmp = strcmp((string)($b['document_date'] ?? ''), (string)($a['document_date'] ?? ''));
        if ($dateCmp !== 0) {
            return $dateCmp;
        }
        return ((int)($b['document_id'] ?? 0)) <=> ((int)($a['document_id'] ?? 0));
    });

    $activeDocKeys = [];
    foreach ($propertyLines as $line) {
        if ((string)($line['document_status'] ?? '') !== 'Active') {
            continue;
        }
        $activeDocKeys[(string)($line['document_type'] ?? '') . '-' . (string)($line['document_id'] ?? '')] = true;
    }
    $activePropertyDocs = count($activeDocKeys);
} catch (Throwable $e) {
    error_log('Employee acknowledgment query failed: ' . $e->getMessage());
}

?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body p-24">
        <div class="container-fluid">
            <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
                <div class="breadcrumb mb-0">
                    <ul class="flex-align gap-4">
                        <li><a href="dashboard.php" class="text-gray-600 fw-normal text-15 hover-text-main-600">Dashboard</a></li>
                        <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                        <li><span class="text-main-600 fw-normal text-15"><?= $pageTitle ?></span></li>
                    </ul>
                </div>
                <div class="flex-align gap-8 flex-wrap">
                    <a href="requisition.php" class="btn btn-primary">New Request</a>
                    <a href="assigned-property.php" class="btn btn-primary">Assigned Property</a>
                    <a href="../logout.php" class="btn btn-danger">Sign Out</a>
                </div>
            </div>

            <div class="row g-3 mb-24">
                <div class="col-12 col-md-4"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">Pending Requests</p><h2 class="h3 mb-0"><?= $pendingRequests ?></h2></div></div></div>
                <div class="col-12 col-md-4"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">Completed Requests</p><h2 class="h3 mb-0"><?= $completedRequests ?></h2></div></div></div>
                <div class="col-12 col-md-4"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">Active Property Docs</p><h2 class="h3 mb-0"><?= $activePropertyDocs ?></h2></div></div></div>
            </div>

            <div class="card border-0 shadow-sm mb-24">
                <div class="card-body p-4 overflow-x-auto">
                    <h2 class="h5 mb-16">Supply and Property Request Status</h2>
                    <table id="requestStatusTable" class="table table-striped align-middle employee-table-fix">
                        <thead><tr><th>RIS No.</th><th>Date</th><th>Purpose</th><th>Items</th><th>Status</th><th>Remarks</th></tr></thead>
                        <tbody>
                            <?php foreach ($myRequests as $row): ?>
                            <?php
                                $summary = $requestItemSummary[(int)$row['id']] ?? ['lines' => 0, 'qty_requested' => 0, 'qty_issued' => 0];
                                $status = (string)($row['status'] ?? 'Requested');
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars((string)($row['ris_no'] ?? '')) ?></td>
                                <td><?= DisplayDate((string)($row['requisition_date'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($row['purpose'] ?? '')) ?></td>
                                <td><?= (int)$summary['lines'] ?> line(s)<br><small>Req <?= (int)$summary['qty_requested'] ?> | Iss <?= (int)$summary['qty_issued'] ?></small></td>
                                <td><span class="badge <?= $statusBadgeMap[$status] ?? 'bg-secondary' ?>"><?= htmlspecialchars($status) ?></span></td>
                                <td><?= trim((string)($row['remarks'] ?? '')) !== '' ? htmlspecialchars((string)$row['remarks']) : '--' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-24">
                <div class="card-body p-4 overflow-x-auto">
                    <h2 class="h5 mb-16">My Issued Property (ICS/PAR)</h2>
                    <p class="text-gray-500 text-13 mb-16">
                        Need more details? Open
                        <a href="assigned-property.php" class="fw-semibold">Assigned Property</a>
                        for card status and location.
                    </p>
                    <table id="propertyStatusTable" class="table table-striped align-middle employee-table-fix">
                        <thead><tr><th>Document</th><th>Date</th><th>Status</th><th>Item</th><th>Property No.</th><th>Qty</th><th>Unit Value</th><th>Remarks</th></tr></thead>
                        <tbody>
                            <?php foreach ($propertyLines as $line): ?>
                            <?php
                                $docStatus = (string)($line['document_status'] ?? '');
                                $docType = trim((string)($line['document_type'] ?? ''));
                                $docNo = trim((string)($line['document_no'] ?? ''));
                                $docPrefix = $docType !== '' ? $docType . '-' : '';
                                if ($docNo !== '') {
                                    $docLabel = ($docPrefix !== '' && stripos($docNo, $docPrefix) === 0)
                                        ? $docNo
                                        : ($docType !== '' ? ($docType . ' - ' . $docNo) : $docNo);
                                } else {
                                    $docLabel = $docType !== '' ? $docType : '--';
                                }
                            ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars($docLabel) ?>
                                </td>
                                <td><?= DisplayDate((string)($line['document_date'] ?? '')) ?></td>
                                <td><span class="badge <?= $statusBadgeMap[$docStatus] ?? 'bg-secondary' ?>"><?= htmlspecialchars($docStatus !== '' ? $docStatus : 'N/A') ?></span></td>
                                <td>
                                    <?= trim((string)($line['item_description'] ?? '')) !== '' ? htmlspecialchars((string)$line['item_description']) : '--' ?>
                                    <?php if (trim((string)($line['item_code'] ?? '')) !== ''): ?>
                                    <br><small><?= htmlspecialchars((string)$line['item_code']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= trim((string)($line['property_no'] ?? '')) !== '' ? htmlspecialchars((string)$line['property_no']) : '--' ?></td>
                                <td><?= (int)($line['qty'] ?? 0) ?></td>
                                <td><?= isset($line['unit_value']) ? 'PHP ' . number_format((float)$line['unit_value'], 2) : '--' ?></td>
                                <td><?= trim((string)($line['document_remarks'] ?? '')) !== '' ? htmlspecialchars((string)$line['document_remarks']) : '--' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16 px-24 py-16">
            <p class="text-gray-300 text-13 fw-normal mb-0">&copy; <?= date('Y') ?> SMS. All rights reserved.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<style>
.employee-table-fix {
    color: #1f2937 !important;
}

.employee-table-fix th,
.employee-table-fix td,
.employee-table-fix small,
.employee-table-fix a:not(.btn) {
    color: #1f2937 !important;
}

.employee-table-fix .btn {
    color: #ffffff !important;
}

.employee-table-fix.table-striped > tbody > tr:nth-of-type(odd) > * {
    --bs-table-color-type: #1f2937 !important;
    --bs-table-bg-type: #f8fafc !important;
    color: #1f2937 !important;
}

.employee-table-fix.table-striped > tbody > tr:nth-of-type(even) > * {
    color: #1f2937 !important;
}
</style>

<script>
$(function () {
    new DataTable('#requestStatusTable', {
        searching: true,
        lengthChange: false,
        pageLength: 8,
        info: true,
        paging: true,
        order: [[1, 'desc']]
    });

    new DataTable('#propertyStatusTable', {
        searching: true,
        lengthChange: false,
        pageLength: 8,
        info: true,
        paging: true,
        order: [[1, 'desc']]
    });
});
</script>
