<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([2]); // Supply Officer
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/Supply_Issuance.model.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = "Supply Issuances";

$issuanceModel = new Supply_Issuance();
$issuanceList = $issuanceModel->getAll();

$issuanceItemsModel = new Supply_Issuance_Items();
$issuanceItems = $issuanceItemsModel->getAll();
$issuanceItemSummary = [];
foreach ($issuanceItems as $itemRow) {
    $issuanceId = (int)($itemRow['issuance_id'] ?? 0);
    if ($issuanceId === 0) {
        continue;
    }
    if (!isset($issuanceItemSummary[$issuanceId])) {
        $issuanceItemSummary[$issuanceId] = [
            'lines' => 0,
            'qty_issued' => 0
        ];
    }
    $issuanceItemSummary[$issuanceId]['lines'] += 1;
    $issuanceItemSummary[$issuanceId]['qty_issued'] += (int)($itemRow['qty_issued'] ?? 0);
}

$risModel = new RIS();
$risList = $risModel->getAll();

$risMap = [];
foreach ($risList as $ris) {
    $risMap[$ris['id']] = $ris;
}

$users = (new BaseModel('user_role_dept'))->getAll();
$userMap = [];
foreach ($users as $user) {
    $userMap[$user['id']] = $user;
}

$items = (new Item())->getAll();
$itemMap = [];
foreach ($items as $item) {
    $itemMap[$item['id']] = $item;
}

$statusOptions = ['Draft', 'Partial Issued', 'Issued', 'Completed'];

$newRsmiNo = Supply_Issuance::getNextRSMINo();
$today = date('Y-m-d');
$isSupplyOfficerView = true;
$currentUserId = (string)(getUser('id') ?? '');
$currentUserName = (string)(getUser('fullname') ?? 'Supply Officer');

?>
 
<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<!--Content-->
<div class="dashboard-main-wrapper">

    <div class="dashboard-body">

        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="index.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= $pageTitle ?></span></li>
                </ul>
            </div>

            <div class="flex-align gap-8 flex-wrap">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ph ph-plus-circle me-1"></i> Add Issuance
                </button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">RSMI No</th>
                            <th class="h6 text-gray-300">RIS No</th>
                            <th class="h6 text-gray-300">Issuance Date</th>
                            <th class="h6 text-gray-300">Issued By</th>
                            <th class="h6 text-gray-300">Received By</th>
                            <th class="h6 text-gray-300">Items Issued</th>
                            <th class="h6 text-gray-300">Status</th>
                            <th class="h6 text-gray-300">Remarks</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($issuanceList as $issuance): ?>
                            <?php
                                $risInfo = $risMap[$issuance['ris_id']] ?? null;
                                $issuedBy = $userMap[$issuance['issued_by']] ?? null;
                                $receivedBy = $userMap[$issuance['received_by']] ?? null;
                                $summary = $issuanceItemSummary[$issuance['id']] ?? ['lines' => 0, 'qty_issued' => 0];
                                $linesLabel = $summary['lines'] === 1 ? 'item' : 'items';
                                $itemsDisplay = $summary['lines'] > 0
                                    ? sprintf('%d %s / %d qty', $summary['lines'], $linesLabel, $summary['qty_issued'])
                                    : 'â€”';
                                $statusClass = match ($issuance['status']) {
                                    'Draft' => 'bg-warning text-dark',
                                    'Partial Issued' => 'bg-info text-dark',
                                    'Issued' => 'bg-primary',
                                    'Completed' => 'bg-success',
                                    default => 'bg-secondary'
                                };
                            ?>
                            <tr>
                                <td class="text-gray-900"><?= htmlspecialchars($issuance['rsmi_no']) ?></td>
                                <td class="text-gray-900">
                                    <?php if ($risInfo): ?>
                                        <div class="d-flex flex-column">
                                            <span class="text-15 fw-medium"><?= htmlspecialchars($risInfo['ris_no']) ?></span>
                                            <span class="text-gray-500 text-13"><?= DisplayDate($risInfo['requisition_date']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        â€”
                                    <?php endif; ?>
                                </td>
                                <td class="text-gray-900"><?= DisplayDate($issuance['issuance_date']) ?></td>
                                <td class="text-gray-900"><?= $issuedBy['fullname'] ?? '' ?></td>
                                <td class="text-gray-900"><?= $receivedBy['fullname'] ?? '' ?></td>
                                <td class="text-gray-900"><?= $itemsDisplay ?></td>
                                <td class="text-gray-900">
                                    <span class="badge <?= $statusClass ?>"><?= $issuance['status'] ?></span>
                                </td>
                                <td class="text-gray-900"><?= !empty($issuance['remarks']) ? htmlspecialchars($issuance['remarks']) : 'â€”' ?></td>
                                <td class="text-gray-900">
                                    <div class="d-flex gap-2 flex-wrap justify-content-center">
                                        <a href="supply_issuance_items.php?issuance_id=<?= $issuance['id'] ?>"
                                           class="btn-action"
                                           aria-label="View issuance items"
                                           data-bs-toggle="tooltip"
                                           title="View items">
                                            <i class="ph ph-list-bullets"></i>
                                        </a>
                                        <a href="../administrator/print/rsmi.php?id=<?= $issuance['id'] ?>" target="_blank"
                                           class="btn-action"
                                           aria-label="Print RSMI"
                                           data-bs-toggle="tooltip"
                                           title="Print RSMI">
                                            <i class="ph ph-printer"></i>
                                        </a>
                                        <button type="button"
                                                class="btn-action btn-action-primary edit-btn"
                                                aria-label="Edit issuance"
                                                data-bs-toggle="tooltip"
                                                title="Edit issuance"
                                                data-id="<?= $issuance['id'] ?>">
                                            <i class="ph ph-pencil-line"></i>
                                        </button>
                                        <button type="button"
                                                class="btn-action btn-action-danger delete-btn"
                                                aria-label="Delete issuance"
                                                data-bs-toggle="tooltip"
                                                title="Delete issuance"
                                                data-id="<?= $issuance['id'] ?>">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16">
            <p class="text-gray-300 text-13 fw-normal"> &copy; Copyright COTSU 2025, All Right Reserverd</p>
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>

</div>

<!-- Add Issuance Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Create Issuance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addForm" action="../administrator/supply_issuance-actions.php?action=add" method="post">
                    <input type="hidden" name="rsmi_no" value="<?= $newRsmiNo ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="rsmi_no" class="form-label">RSMI No</label>
                            <input type="text" class="form-control" id="rsmi_no" value="<?= $newRsmiNo ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="issuance_date" class="form-label">Issuance Date</label>
                            <input type="date" class="form-control" id="issuance_date" name="issuance_date" value="<?= $today ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ris_id" class="form-label">Reference RIS</label>
                            <select class="form-select select2" id="ris_id" name="ris_id" data-placeholder="Select RIS" required>
                                <option value=""></option>
                                <?php foreach ($risList as $ris): ?>
                                    <option value="<?= $ris['id'] ?>"><?= $ris['ris_no'] ?> - <?= DisplayDate($ris['requisition_date']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="issued_by" class="form-label">Issued By</label>
                            <select class="form-select select2" id="issued_by" name="issued_by" data-placeholder="Select issuer" required>
                                <option value=""></option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= ($currentUserId == $user['id']) ? 'selected' : '' ?>>
                                        <?= $user['fullname'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="received_by" class="form-label">Received By</label>
                            <select class="form-select select2" id="received_by" name="received_by" data-placeholder="Select receiver" data-allow-clear>
                                <option value=""></option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="remarks" class="form-label">Remarks (Optional)</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Issuance Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Issuance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" action="../administrator/supply_issuance-actions.php?action=update" method="post">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_rsmi_no" class="form-label">RSMI No</label>
                            <input type="text" class="form-control" id="edit_rsmi_no" disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_issuance_date" class="form-label">Issuance Date</label>
                            <input type="date" class="form-control" id="edit_issuance_date" name="issuance_date" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_ris_reference" class="form-label">Reference RIS</label>
                            <input type="text" class="form-control" id="edit_ris_reference" disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select select2" id="edit_status" name="status" data-placeholder="Select status" required>
                                <?php foreach ($statusOptions as $status): ?>
                                    <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_issued_by" class="form-label">Issued By</label>
                            <select class="form-select select2" id="edit_issued_by" name="issued_by" data-placeholder="Select issuer" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_received_by" class="form-label">Received By</label>
                            <select class="form-select select2" id="edit_received_by" name="received_by" data-placeholder="Select receiver" data-allow-clear>
                                <option value="">Not Set</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="edit_remarks" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateBtn">Update</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Deleting this issuance will remove all related data. This action cannot be undone.</p>
                <form id="deleteForm" action="../administrator/supply_issuance-actions.php?action=del" method="post">
                    <input type="hidden" name="id" id="delete_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function () {
    new DataTable('#dtable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        "columnDefs": [{
            "orderable": false,
            "targets": [8]
        }]
    });

    $('#saveBtn').on('click', function () {
        $('#addForm').submit();
    });

    $('#updateBtn').on('click', function () {
        $('#editForm').submit();
    });

    const isSupplyOfficerView = <?= $isSupplyOfficerView ? 'true' : 'false' ?>;
    const supplyOfficerUserId = <?= json_encode($currentUserId) ?>;
    const supplyOfficerUserName = <?= json_encode($currentUserName) ?>;

    function lockSingleUserSelect(selector) {
        if (!isSupplyOfficerView || !supplyOfficerUserId) {
            return;
        }

        const $select = $(selector);
        if (!$select.length) {
            return;
        }

        const originalName = $select.attr('name');
        if (originalName) {
            const marker = selector.replace(/[^a-zA-Z0-9_-]/g, '_');
            let $hidden = $select.siblings('input[type="hidden"][data-lock-target="' + marker + '"]');
            if (!$hidden.length) {
                $hidden = $('<input>', {
                    type: 'hidden',
                    name: originalName,
                    value: supplyOfficerUserId,
                    'data-lock-target': marker
                });
                $select.after($hidden);
            } else {
                $hidden.val(supplyOfficerUserId);
            }
            $select.removeAttr('name');
        }

        if (!$select.find('option[value="' + supplyOfficerUserId + '"]').length) {
            $select.append(new Option(supplyOfficerUserName, supplyOfficerUserId, true, true));
        }

        $select.val(supplyOfficerUserId).trigger('change');
        $select.prop('disabled', true);
    }

    lockSingleUserSelect('#issued_by');
    lockSingleUserSelect('#edit_issued_by');

    $(document).on('click', '.edit-btn', function () {
        const issuanceId = $(this).data('id');
        $.getJSON(baseUrl + "/api/admin.php", { action: 'getIssuanceById', id: issuanceId }, function (res) {
            if (res.error) {
                showAlert('Error', res.error, 'error');
                return;
            }

            $('#edit_id').val(res.id);
            $('#edit_rsmi_no').val(res.rsmi_no);
            $('#edit_issuance_date').val(res.issuance_date);
            $('#edit_ris_reference').val(res.ris_no);
            $('#edit_status').val(res.status).trigger('change');
            lockSingleUserSelect('#edit_issued_by');
            $('#edit_received_by').val(res.received_by).trigger('change');
            $('#edit_remarks').val(res.remarks);

            $('#editModal').modal('show');
        });
    });

    $(document).on('click', '.delete-btn', function () {
        const issuanceId = $(this).data('id');
        $('#delete_id').val(issuanceId);
        $('#deleteModal').modal('show');
    });

    $('#confirmDeleteBtn').on('click', function () {
        $('#deleteForm').submit();
    });
});
</script>



