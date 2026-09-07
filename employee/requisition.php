<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([6]); // Employee

require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../repo/RIS.model.php';

$pageTitle = 'Request Supplies and Property';
$currentUserId = (int)(getUser('id') ?? 0);
$currentDepartmentCode = trim((string)(getUser('departmentname') ?? ''));

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
];

$risModel = new RIS();
$risItemsModel = new RIS_Items();

$allRequests = $risModel->getAll();
$allRequestItems = $risItemsModel->getAll();

$myRequests = array_values(array_filter($allRequests, static function ($row) use ($currentUserId) {
    return (int)($row['requested_by'] ?? 0) === $currentUserId;
}));
usort($myRequests, static function ($a, $b) {
    return (int)$b['id'] <=> (int)$a['id'];
});

$requestItemSummary = [];
foreach ($allRequestItems as $line) {
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

$pendingCount = 0;
$completedCount = 0;
foreach ($myRequests as $row) {
    $status = (string)($row['status'] ?? '');
    if ($status === 'Completed') {
        $completedCount++;
    }
    if (in_array($status, $pendingStatuses, true)) {
        $pendingCount++;
    }
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
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRequestModal">Create New Request</button>
                    <a href="acknowledgment.php" class="btn btn-primary">View Status</a>
                    <a href="../logout.php" class="btn btn-danger">Sign Out</a>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-24">
                <div class="card-body p-4">
                    <h1 class="h4 mb-8"><?= $pageTitle ?></h1>
                    <p class="text-gray-600 mb-0">
                        Department: <span class="fw-semibold"><?= htmlspecialchars($currentDepartmentCode !== '' ? $currentDepartmentCode : 'N/A') ?></span>
                    </p>
                </div>
            </div>

            <div class="row g-3 mb-24">
                <div class="col-12 col-md-4"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">Total Requests</p><h2 class="h3 mb-0"><?= count($myRequests) ?></h2></div></div></div>
                <div class="col-12 col-md-4"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">Pending / In Progress</p><h2 class="h3 mb-0"><?= $pendingCount ?></h2></div></div></div>
                <div class="col-12 col-md-4"><div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase text-gray-500 text-12 mb-8">Completed</p><h2 class="h3 mb-0"><?= $completedCount ?></h2></div></div></div>
            </div>

            <div class="card border-0 shadow-sm mb-24">
                <div class="card-body p-4 overflow-x-auto">
                    <h2 class="h5 mb-16">My Requests</h2>
                    <table id="requestsTable" class="table table-striped align-middle employee-table-fix">
                        <thead><tr><th>RIS No.</th><th>Date</th><th>Purpose</th><th>Items</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($myRequests as $row): ?>
                            <?php
                                $requestId = (int)$row['id'];
                                $summary = $requestItemSummary[$requestId] ?? ['lines' => 0, 'qty_requested' => 0, 'qty_issued' => 0];
                                $status = (string)($row['status'] ?? 'Requested');
                                $canDeleteRow = in_array($status, ['Requested', 'Pending'], true);
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars((string)($row['ris_no'] ?? '')) ?></td>
                                <td><?= DisplayDate((string)($row['requisition_date'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($row['purpose'] ?? '')) ?></td>
                                <td><?= (int)$summary['lines'] ?> line(s)<br><small>Req <?= (int)$summary['qty_requested'] ?> | Iss <?= (int)$summary['qty_issued'] ?></small></td>
                                <td><span class="badge <?= $statusBadgeMap[$status] ?? 'bg-secondary' ?>"><?= htmlspecialchars($status) ?></span></td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="request-details.php?ris_id=<?= $requestId ?>" class="btn btn-sm btn-primary">Open</a>
                                        <?php if ($canDeleteRow): ?>
                                        <form action="request-actions.php?action=delete_request" method="post" class="d-inline">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="id" value="<?= $requestId ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this request?')">Delete</button>
                                        </form>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Locked</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
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

<div class="modal fade" id="createRequestModal" tabindex="-1" aria-labelledby="createRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="createRequestModalLabel">Create New Request</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="request-actions.php?action=create_request" method="post">
                <?= csrf_input() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">Requisition Date</label>
                            <input type="date" class="form-control" name="requisition_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">Fund Cluster</label>
                            <input type="text" class="form-control" name="fund_cluster" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">Responsibility Center Code</label>
                            <input type="text" class="form-control" name="responsibility_center_code">
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">Purpose</label>
                            <textarea class="form-control" name="purpose" rows="2" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<style>
#createRequestModal .modal-content,
#createRequestModal .modal-title,
#createRequestModal .form-label,
#createRequestModal .form-control,
#createRequestModal textarea {
    color: #1f2937 !important;
}

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
    new DataTable('#requestsTable', {
        searching: true,
        lengthChange: false,
        pageLength: 8,
        info: true,
        paging: true,
        order: [[1, 'desc']],
        columnDefs: [{ orderable: false, targets: [5] }],
        language: { emptyTable: 'No requests found. Use Create New Request to start one.' }
    });
});
</script>
