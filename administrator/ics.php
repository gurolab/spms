<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/ICS.model.php';

$pageTitle = "Inventory Custodian Slips";

$icsModel = new ICS();
$icsList = $icsModel->getAll();

$icsItemsModel = new ICS_Items();
$icsItemSummary = [];
foreach ($icsItemsModel->getAll() as $icsItemRow) {
    $icsId = (int)($icsItemRow['ics_id'] ?? 0);
    if ($icsId === 0) {
        continue;
    }

    if (!isset($icsItemSummary[$icsId])) {
        $icsItemSummary[$icsId] = [
            'lines' => 0,
            'qty' => 0,
            'total_value' => 0.0
        ];
    }

    $lineQty = (int)($icsItemRow['qty'] ?? 0);
    $lineValue = (float)($icsItemRow['unit_value'] ?? 0);

    $icsItemSummary[$icsId]['lines'] += 1;
    $icsItemSummary[$icsId]['qty'] += $lineQty;
    $icsItemSummary[$icsId]['total_value'] += ($lineQty * $lineValue);
}

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();
$eligibleAssignedUsers = array_values(array_filter($users, static function ($user) {
    return (int)($user['roleid'] ?? 0) === 6
        && strtolower(trim((string)($user['status'] ?? ''))) === 'active';
}));
usort($eligibleAssignedUsers, static function ($a, $b) {
    return strcasecmp((string)($a['fullname'] ?? ''), (string)($b['fullname'] ?? ''));
});

function icsOfficerLabel(?array $user): string
{
    return formatOfficerNameWithDeptCode($user);
}

$valueTypeOptions = ['Low', 'High'];
$statusOptions = ['Active', 'Returned', 'Transferred', 'Disposed'];
$statusBadgeMap = [
    'Active' => 'bg-success text-dark',
    'Returned' => 'bg-warning text-dark',
    'Transferred' => 'bg-info text-dark',
    'Disposed' => 'bg-danger'
];

$defaultIcsNo = $icsModel->getNextICSNo();
$defaultIssuedDate = date('Y-m-d');
$currentUserId = (int)(getUser('id') ?? 0);

?>



<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<!--Content-->
<div class="dashboard-main-wrapper">



    <div class="dashboard-body">

        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <!-- Breadcrumb Start -->
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="index.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li> <span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span> </li>
                    <li><span class="text-main-600 fw-normal text-15"><?=$pageTitle?></span></li>
                </ul>
            </div>
            <!-- Breadcrumb End -->

            <!-- Breadcrumb Right Start -->
            <div class="flex-align gap-8 flex-wrap">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ph ph-plus-circle me-1"></i> Add ICS
                </button>
            </div>
            <!-- Breadcrumb Right End -->
        </div>


        <div class="card overflow-hidden" class="p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">ICS No.</th>
                            <th class="h6 text-gray-300">Control No.</th>
                            <th class="h6 text-gray-300">Value Type</th>
                            <th class="h6 text-gray-300">Assigned To</th>
                            <th class="h6 text-gray-300">Issued By</th>
                            <th class="h6 text-gray-300">Issued Date</th>
                            <th class="h6 text-gray-300">Status</th>
                            <th class="h6 text-gray-300">Items Summary</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($icsList as $slip) : ?>
                        <?php
                            $assigned = SearchById($users, $slip['assigned_to'] ?? '');
                            $issued = SearchById($users, $slip['issued_by'] ?? '');
                            $summary = $icsItemSummary[$slip['id']] ?? ['lines' => 0, 'qty' => 0, 'total_value' => 0.0];
                            $statusClass = $statusBadgeMap[$slip['status']] ?? 'bg-secondary';
                        ?>
                        <tr>
                            <td class="text-gray-900 fw-semibold"><?= htmlspecialchars($slip['ics_no']) ?></td>
                            <td class="text-gray-900"><?= htmlspecialchars($slip['property_no']) ?></td>
                            <td class="text-gray-900">
                                <span class="badge <?= $slip['value_type'] === 'High' ? 'bg-primary' : 'bg-info text-dark' ?>">
                                    <?= htmlspecialchars($slip['value_type']) ?>
                                </span>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= htmlspecialchars($assigned['fullname'] ?? 'Unassigned') ?></span>
                                    <?php if (!empty($assigned['department_name'])) : ?>
                                    <span class="text-gray-500 text-13"><?= htmlspecialchars($assigned['department_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= htmlspecialchars($issued['fullname'] ?? 'N/A') ?></span>
                                    <?php if (!empty($issued['department_name'])) : ?>
                                    <span class="text-gray-500 text-13"><?= htmlspecialchars($issued['department_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= DisplayDate($slip['issued_date']) ?></td>
                            <td class="text-gray-900">
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($slip['status']) ?></span>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-13 text-gray-500"><?= $summary['lines'] ?> line(s)</span>
                                    <span class="text-13 text-gray-500"><?= $summary['qty'] ?> unit(s)</span>
                                    <span class="text-13 text-gray-700 fw-semibold">
                                    PHP <?= number_format($summary['total_value'], 2) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap justify-content-center">
                                    <a href="ics_items.php?ics_id=<?= $slip['id'] ?>"
                                        class="btn-action"
                                        aria-label="View items"
                                        data-bs-toggle="tooltip"
                                        title="View items">
                                        <i class="ph ph-list-bullets"></i>
                                    </a>
                                    <a href="print/ics.php?id=<?= $slip['id'] ?>" target="_blank"
                                        class="btn-action"
                                        aria-label="Print ICS"
                                        data-bs-toggle="tooltip"
                                        title="Print ICS">
                                        <i class="ph ph-printer"></i>
                                    </a>
                                    <button type="button" class="btn-action btn-action-primary edit-btn"
                                        aria-label="Edit ICS"
                                        data-bs-toggle="tooltip"
                                        title="Edit ICS"
                                        data-id="<?= $slip['id'] ?>">
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-action-danger delete-btn"
                                        aria-label="Delete ICS"
                                        data-bs-toggle="tooltip"
                                        title="Delete ICS"
                                        data-id="<?= $slip['id'] ?>">
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
            <div class="flex-align flex-wrap gap-16">
            </div>
        </div>
    </div>



</div>

<!--Content-->

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Add Inventory Custodian Slip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addForm" action="ics-actions.php?action=add" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_ics_no" class="form-label">ICS No.</label>
                            <input type="text" class="form-control" id="add_ics_no" name="ics_no"
                                value="<?= htmlspecialchars($defaultIcsNo) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_property_no" class="form-label">Control No.</label>
                            <input type="text" class="form-control" id="add_property_no" name="property_no" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_value_type" class="form-label">Value Type</label>
                            <select class="form-select" id="add_value_type" name="value_type" required>
                                <?php foreach ($valueTypeOptions as $option) : ?>
                                <option value="<?= $option ?>"><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-select" id="add_status" name="status" required>
                                <?php foreach ($statusOptions as $status) : ?>
                                <option value="<?= $status ?>" <?= $status === 'Active' ? 'selected' : '' ?>><?= $status ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_assigned_to" class="form-label">Assigned To</label>
                            <select class="form-select select2" id="add_assigned_to" name="assigned_to" data-placeholder="Select user" required>
                                <option value="" disabled selected>Select User</option>
                                <?php foreach ($eligibleAssignedUsers as $user) : ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars(icsOfficerLabel($user)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_issued_by" class="form-label">Issued By</label>
                            <select class="form-select select2" id="add_issued_by" name="issued_by" data-placeholder="Select issuing officer" required>
                                <option value="" disabled <?= $currentUserId === 0 ? 'selected' : '' ?>>Select User</option>
                                <?php foreach ($users as $user) : ?>
                                <option value="<?= $user['id'] ?>"
                                    <?= $currentUserId === (int)$user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(formatOfficerNameWithDeptCode($user)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_issued_date" class="form-label">Issued Date</label>
                            <input type="date" class="form-control" id="add_issued_date" name="issued_date"
                                value="<?= $defaultIssuedDate ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="add_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="add_remarks" name="remarks" rows="3"
                                placeholder="Optional notes about this assignment"></textarea>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Inventory Custodian Slip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" action="ics-actions.php?action=update" method="post">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_ics_no" class="form-label">ICS No.</label>
                            <input type="text" class="form-control" id="edit_ics_no" name="ics_no" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_property_no" class="form-label">Control No.</label>
                            <input type="text" class="form-control" id="edit_property_no" name="property_no" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_value_type" class="form-label">Value Type</label>
                            <select class="form-select" id="edit_value_type" name="value_type" required>
                                <?php foreach ($valueTypeOptions as $option) : ?>
                                <option value="<?= $option ?>"><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <?php foreach ($statusOptions as $status) : ?>
                                <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_assigned_to" class="form-label">Assigned To</label>
                            <select class="form-select select2" id="edit_assigned_to" name="assigned_to" data-placeholder="Select user" required>
                                <?php foreach ($eligibleAssignedUsers as $user) : ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars(icsOfficerLabel($user)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_issued_by" class="form-label">Issued By</label>
                            <select class="form-select select2" id="edit_issued_by" name="issued_by" data-placeholder="Select issuing officer" required>
                                <?php foreach ($users as $user) : ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars(formatOfficerNameWithDeptCode($user)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_issued_date" class="form-label">Issued Date</label>
                            <input type="date" class="form-control" id="edit_issued_date" name="issued_date" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="edit_remarks" name="remarks" rows="3"></textarea>
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
                <p>Are you sure you want to delete this ICS? <br> This action cannot be undone.</p>
                <form id="deleteForm" action="ics-actions.php?action=del" method="post">
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
$(document).ready(function() {
    const dataTable = new DataTable('#dtable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        order: [[0, 'desc']],
        columnDefs: [{
            orderable: false,
            targets: [8]
        }]
    });

    $('#saveBtn').on('click', function() {
        $('#addForm').submit();
    });

    $('#updateBtn').on('click', function() {
        $('#editForm').submit();
    });

    $('#confirmDeleteBtn').on('click', function() {
        $('#deleteForm').submit();
    });

    $(document).on('click', '.edit-btn', function() {
        const slipId = $(this).data('id');
        $.getJSON(baseUrl + "/api/admin.php", {
            action: 'getICSById',
            id: slipId
        }, function(res) {
            if (res.error) {
                showAlert("Error", res.error, "danger");
                return;
            }

            $('#edit_id').val(res.id);
            $('#edit_ics_no').val(res.ics_no);
            $('#edit_property_no').val(res.property_no);
            $('#edit_value_type').val(res.value_type);
            $('#edit_status').val(res.status);
            $('#edit_assigned_to').val(res.assigned_to).trigger('change');
            $('#edit_issued_by').val(res.issued_by).trigger('change');
            $('#edit_issued_date').val(res.issued_date);
            $('#edit_remarks').val(res.remarks);
            $('#editModal').modal('show');
        }).fail(function() {
            showAlert("Error", "Unable to load slip details. Please try again.", "danger");
        });
    });

    $(document).on('click', '.delete-btn', function() {
        const slipId = $(this).data('id');
        $('#delete_id').val(slipId);
        $('#deleteModal').modal('show');
    });
});
</script>


