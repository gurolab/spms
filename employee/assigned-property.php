<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([6]); // Employee

require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/Model.php';

$pageTitle = 'Assigned Property and Equipment';
$currentUserId = (int)(getUser('id') ?? 0);

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
    'Assigned' => 'bg-success',
    'For Repair' => 'bg-warning text-dark',
    'Unserviceable' => 'bg-danger',
    'Disposed' => 'bg-secondary',
    'Returned' => 'bg-warning text-dark',
    'Transferred' => 'bg-info text-dark',
];

$assignedItems = [];
$summary = [
    'total' => 0,
    'active' => 0,
    'for_repair' => 0,
    'closed' => 0,
];

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
            ii.date_acquired AS acquisition_date,
            ii.remarks AS item_remarks,
            i.code AS item_code,
            i.description AS item_description,
            pc.card_no AS card_no,
            pc.current_status AS card_status,
            pc.location AS location
         FROM inventory_custodian_slips h
         INNER JOIN ics_items ii ON ii.ics_id = h.id
         LEFT JOIN items i ON i.id = ii.item_id
         LEFT JOIN property_cards pc ON pc.property_tag = ii.property_no
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
            pi.acquisition_date AS acquisition_date,
            pi.remarks AS item_remarks,
            i.code AS item_code,
            i.description AS item_description,
            pc.card_no AS card_no,
            pc.current_status AS card_status,
            pc.location AS location
         FROM property_acknowledgment_receipts h
         INNER JOIN par_items pi ON pi.par_id = h.id
         LEFT JOIN items i ON i.id = pi.item_id
         LEFT JOIN property_cards pc ON pc.par_id = h.id AND pc.property_tag = pi.property_no
         WHERE h.accountable_officer = :uid"
    );
    $parStmt->execute(['uid' => $currentUserId]);
    $parRows = $parStmt->fetchAll(PDO::FETCH_ASSOC);

    $assignedItems = array_merge($icsRows, $parRows);

    foreach ($assignedItems as &$line) {
        $cardStatus = trim((string)($line['card_status'] ?? ''));
        $docStatus = trim((string)($line['document_status'] ?? ''));
        $line['current_status'] = $cardStatus !== '' ? $cardStatus : $docStatus;
    }
    unset($line);

    usort($assignedItems, static function ($a, $b) {
        $dateCmp = strcmp((string)($b['document_date'] ?? ''), (string)($a['document_date'] ?? ''));
        if ($dateCmp !== 0) {
            return $dateCmp;
        }
        return ((int)($b['document_id'] ?? 0)) <=> ((int)($a['document_id'] ?? 0));
    });

    $summary['total'] = count($assignedItems);

    foreach ($assignedItems as $line) {
        $status = strtolower(trim((string)($line['current_status'] ?? '')));
        if ($status === '' || $status === 'n/a') {
            continue;
        }
        if (in_array($status, ['active', 'assigned', 'issued', 'approved', 'partial issued'], true)) {
            $summary['active']++;
            continue;
        }
        if ($status === 'for repair') {
            $summary['for_repair']++;
            continue;
        }
        if (in_array($status, ['unserviceable', 'disposed', 'returned', 'transferred', 'completed', 'cancelled'], true)) {
            $summary['closed']++;
        }
    }
} catch (Throwable $e) {
    error_log('Employee assigned property query failed: ' . $e->getMessage());
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
                    <a href="acknowledgment.php" class="btn btn-primary">View Request Status</a>
                    <a href="../logout.php" class="btn btn-danger">Sign Out</a>
                </div>
            </div>

            <div class="row g-3 mb-24">
                <div class="col-12 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">Total Assigned Lines</p><h2 class="h3 mb-0"><?= (int)$summary['total'] ?></h2></div></div></div>
                <div class="col-12 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">Active / Assigned</p><h2 class="h3 mb-0"><?= (int)$summary['active'] ?></h2></div></div></div>
                <div class="col-12 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">For Repair</p><h2 class="h3 mb-0"><?= (int)$summary['for_repair'] ?></h2></div></div></div>
                <div class="col-12 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">Closed / Returned</p><h2 class="h3 mb-0"><?= (int)$summary['closed'] ?></h2></div></div></div>
            </div>

            <div class="card border-0 shadow-sm mb-24">
                <div class="card-body p-4 overflow-x-auto">
                    <h2 class="h5 mb-16">My Assigned Property / Equipment</h2>
                    <?php if (count($assignedItems) === 0): ?>
                    <div class="alert alert-info mb-0">
                        No property or equipment assignment records were found for your account yet.
                    </div>
                    <?php else: ?>
                    <table id="assignedPropertyTable" class="table table-striped align-middle employee-table-fix">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Issued Date</th>
                                <th>Item</th>
                                <th>Property / Tag No.</th>
                                <th>Qty</th>
                                <th>Unit Value</th>
                                <th>Current Status</th>
                                <th>Document Status</th>
                                <th>Location</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedItems as $line): ?>
                            <?php
                                $currentStatus = trim((string)($line['current_status'] ?? ''));
                                $docStatus = trim((string)($line['document_status'] ?? ''));
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
                                $notes = [];
                                $itemRemarks = trim((string)($line['item_remarks'] ?? ''));
                                $docRemarks = trim((string)($line['document_remarks'] ?? ''));
                                if ($itemRemarks !== '') {
                                    $notes[] = 'Item: ' . $itemRemarks;
                                }
                                if ($docRemarks !== '') {
                                    $notes[] = 'Document: ' . $docRemarks;
                                }
                            ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars($docLabel) ?>
                                    <?php if (trim((string)($line['card_no'] ?? '')) !== ''): ?>
                                    <br><small>Card: <?= htmlspecialchars((string)$line['card_no']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= DisplayDate((string)($line['document_date'] ?? '')) ?></td>
                                <td>
                                    <?= trim((string)($line['item_description'] ?? '')) !== '' ? htmlspecialchars((string)$line['item_description']) : '--' ?>
                                    <?php if (trim((string)($line['item_code'] ?? '')) !== ''): ?>
                                    <br><small><?= htmlspecialchars((string)$line['item_code']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= trim((string)($line['property_no'] ?? '')) !== '' ? htmlspecialchars((string)$line['property_no']) : '--' ?></td>
                                <td><?= (int)($line['qty'] ?? 0) ?></td>
                                <td><?= isset($line['unit_value']) ? 'PHP ' . number_format((float)$line['unit_value'], 2) : '--' ?></td>
                                <td><span class="badge <?= $statusBadgeMap[$currentStatus] ?? 'bg-secondary' ?>"><?= htmlspecialchars($currentStatus !== '' ? $currentStatus : 'N/A') ?></span></td>
                                <td><span class="badge <?= $statusBadgeMap[$docStatus] ?? 'bg-secondary' ?>"><?= htmlspecialchars($docStatus !== '' ? $docStatus : 'N/A') ?></span></td>
                                <td><?= trim((string)($line['location'] ?? '')) !== '' ? htmlspecialchars((string)$line['location']) : '--' ?></td>
                                <td>
                                    <?php if (count($notes) === 0): ?>
                                    --
                                    <?php else: ?>
                                    <?= htmlspecialchars(implode(' | ', $notes)) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
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
    const table = document.querySelector('#assignedPropertyTable');
    if (!table) {
        return;
    }

    new DataTable('#assignedPropertyTable', {
        searching: true,
        lengthChange: false,
        pageLength: 10,
        info: true,
        paging: true,
        order: [[1, 'desc']]
    });
});
</script>
