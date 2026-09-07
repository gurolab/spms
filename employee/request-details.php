<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([6]); // Employee

require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = 'Request Details';
$currentUserId = (int)(getUser('id') ?? 0);

$headerEditableStatuses = ['Requested', 'Pending'];
$lineEditableStatuses = ['Requested'];
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
$supplyTypes = ['Supply', 'Material', 'Raw Material', 'Packaging', 'Finished Good'];

$selectedRequestId = (int)($_GET['ris_id'] ?? 0);
if ($selectedRequestId <= 0) {
    flash('danger', 'Request reference is required.');
    redirect('requisition.php');
}

$risModel = new RIS();
$risItemsModel = new RIS_Items();
$itemModel = new Item();

$selectedRequest = $risModel->getById($selectedRequestId);
if (!$selectedRequest || (int)($selectedRequest['requested_by'] ?? 0) !== $currentUserId) {
    flash('danger', 'Request not found or access is denied.');
    redirect('requisition.php');
}

$allItems = $itemModel->getAll();
$requestLines = $risItemsModel->getAll($selectedRequestId);

$itemsById = [];
$supplyItems = [];
$propertyItems = [];
foreach ($allItems as $item) {
    $itemId = (int)($item['id'] ?? 0);
    if ($itemId <= 0) {
        continue;
    }

    $itemsById[$itemId] = $item;
    $isSupply = ((int)($item['is_consumable'] ?? 1) === 1) || in_array((string)($item['type'] ?? ''), $supplyTypes, true);
    if ($isSupply) {
        $supplyItems[] = $item;
    } else {
        $propertyItems[] = $item;
    }
}

$requestStatus = (string)($selectedRequest['status'] ?? 'Requested');
$canEditHeader = in_array($requestStatus, $headerEditableStatuses, true);
$canEditLines = in_array($requestStatus, $lineEditableStatuses, true);

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
                        <li><a href="requisition.php" class="text-gray-600 fw-normal text-15 hover-text-main-600">Request Supplies and Property</a></li>
                        <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                        <li><span class="text-main-600 fw-normal text-15">Selected Request</span></li>
                    </ul>
                </div>
                <div class="flex-align gap-8 flex-wrap">
                    <a href="requisition.php" class="btn btn-primary">Back to My Requests</a>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-24">
                <div class="card-body p-4">
                    <h1 class="h4 mb-8">Selected Request: [<?= htmlspecialchars((string)($selectedRequest['ris_no'] ?? '')) ?>]</h1>
                    <p class="text-gray-600 mb-0">
                        <span class="badge <?= $statusBadgeMap[$requestStatus] ?? 'bg-secondary' ?>"><?= htmlspecialchars($requestStatus) ?></span>
                        | Requisition Date: <?= DisplayDate((string)($selectedRequest['requisition_date'] ?? '')) ?>
                    </p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-24">
                <div class="card-body p-4">
                    <h2 class="h5 mb-16">Request Header</h2>
                    <?php if ($canEditHeader): ?>
                    <form action="request-actions.php?action=update_request" method="post" class="row g-3">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" value="<?= (int)$selectedRequest['id'] ?>">
                        <div class="col-12 col-md-6"><label class="form-label mb-1">Fund Cluster</label><input type="text" class="form-control" name="fund_cluster" value="<?= htmlspecialchars((string)($selectedRequest['fund_cluster'] ?? '')) ?>" required></div>
                        <div class="col-12 col-md-6"><label class="form-label mb-1">Responsibility Center Code</label><input type="text" class="form-control" name="responsibility_center_code" value="<?= htmlspecialchars((string)($selectedRequest['responsibility_center_code'] ?? '')) ?>"></div>
                        <div class="col-12"><label class="form-label mb-1">Purpose</label><textarea class="form-control" name="purpose" rows="2" required><?= htmlspecialchars((string)($selectedRequest['purpose'] ?? '')) ?></textarea></div>
                        <div class="col-12"><label class="form-label mb-1">Remarks</label><textarea class="form-control" name="remarks" rows="2"><?= htmlspecialchars((string)($selectedRequest['remarks'] ?? '')) ?></textarea></div>
                        <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary">Update Header</button></div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-warning mb-0">This request header can no longer be edited.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-24">
                <div class="card-body p-4">
                    <h2 class="h5 mb-16">Request Line Items</h2>

                    <?php if ($canEditLines): ?>
                    <form action="request-actions.php?action=add_item" method="post" class="row g-3 align-items-end mb-16">
                        <?= csrf_input() ?>
                        <input type="hidden" name="ris_id" value="<?= (int)$selectedRequest['id'] ?>">
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Item (Supply or Property)</label>
                            <select class="form-select select2" name="item_id" data-placeholder="Select item" required>
                                <option value="" disabled selected>Select item</option>
                                <?php if (!empty($supplyItems)): ?><optgroup label="Supply and Consumable"><?php foreach ($supplyItems as $item): ?><option value="<?= (int)$item['id'] ?>"><?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                                <?php if (!empty($propertyItems)): ?><optgroup label="Property and Non-Consumable"><?php foreach ($propertyItems as $item): ?><option value="<?= (int)$item['id'] ?>"><?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2"><label class="form-label mb-1">Qty</label><input type="number" class="form-control" name="qty_requested" min="1" required></div>
                        <div class="col-12 col-md-3"><label class="form-label mb-1">Remarks</label><input type="text" class="form-control" name="remarks"></div>
                        <div class="col-12 col-md-1 d-grid"><button type="submit" class="btn btn-primary">Add</button></div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-warning mb-16">You can only add or change line items while request status is Requested.</div>
                    <?php endif; ?>

                    <div class="overflow-x-auto">
                        <table id="requestItemsTable" class="table table-striped align-middle employee-table-fix">
                            <thead><tr><th>Item</th><th>Class</th><th>Qty Req</th><th>Qty Iss</th><th>Line Status</th><th>Remarks</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($requestLines as $line): ?>
                                <?php
                                    $lineItem = $itemsById[(int)$line['item_id']] ?? null;
                                    $qtyReq = (int)($line['qty_requested'] ?? 0);
                                    $qtyIss = (int)($line['qty_issued'] ?? 0);
                                    $lineIsSupply = $lineItem ? ((((int)($lineItem['is_consumable'] ?? 1) === 1) || in_array((string)($lineItem['type'] ?? ''), $supplyTypes, true))) : true;
                                    $lineStatus = 'Requested';
                                    $lineStatusClass = 'bg-info text-dark';
                                    if ($qtyIss > 0 && $qtyIss < $qtyReq) {
                                        $lineStatus = 'Partial Issued';
                                        $lineStatusClass = 'bg-primary';
                                    } elseif ($qtyIss >= $qtyReq && $qtyReq > 0) {
                                        $lineStatus = 'Issued';
                                        $lineStatusClass = 'bg-success';
                                    }
                                ?>
                                <tr>
                                    <td><?= $lineItem ? htmlspecialchars((string)$lineItem['description']) . '<br><small>' . htmlspecialchars((string)$lineItem['code']) . '</small>' : '<span class="text-danger">Item missing</span>' ?></td>
                                    <td><span class="badge <?= $lineIsSupply ? 'bg-info text-dark' : 'bg-dark' ?>"><?= $lineIsSupply ? 'Supply' : 'Property' ?></span></td>
                                    <td><?= $qtyReq ?></td>
                                    <td><?= $qtyIss ?></td>
                                    <td><span class="badge <?= $lineStatusClass ?>"><?= $lineStatus ?></span></td>
                                    <td><?= trim((string)($line['remarks'] ?? '')) !== '' ? htmlspecialchars((string)$line['remarks']) : '--' ?></td>
                                    <td>
                                        <?php if ($canEditLines && $qtyIss === 0): ?>
                                        <form action="request-actions.php?action=update_item" method="post" class="d-flex gap-1 mb-1">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="id" value="<?= (int)$line['id'] ?>">
                                            <input type="hidden" name="ris_id" value="<?= (int)$selectedRequest['id'] ?>">
                                            <input type="number" min="1" class="form-control form-control-sm" style="max-width:95px" name="qty_requested" value="<?= $qtyReq ?>" required>
                                            <input type="text" class="form-control form-control-sm" style="max-width:180px" name="remarks" value="<?= htmlspecialchars((string)($line['remarks'] ?? '')) ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                        <form action="request-actions.php?action=remove_item" method="post">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="id" value="<?= (int)$line['id'] ?>">
                                            <input type="hidden" name="ris_id" value="<?= (int)$selectedRequest['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this item line?')">Remove</button>
                                        </form>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Locked</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
.select2-container--default .select2-selection--single .select2-selection__rendered,
.select2-container--default .select2-results__option {
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
    new DataTable('#requestItemsTable', {
        searching: false,
        lengthChange: false,
        info: false,
        paging: false,
        ordering: false,
        language: { emptyTable: 'No items added yet for this request.' }
    });
});
</script>
