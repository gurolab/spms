<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/PAR.model.php';

$pageTitle = "Property Acknowledgment Receipts (PAR)";

$parModel = new PAR();
$parList = $parModel->getAll();

$parItemsModel = new PAR_Items();
$parItemSummary = [];
foreach ($parItemsModel->getAll() as $parItemRow) {
    $parId = (int)($parItemRow['par_id'] ?? 0);
    if ($parId === 0) {
        continue;
    }

    if (!isset($parItemSummary[$parId])) {
        $parItemSummary[$parId] = [
            'lines' => 0,
            'qty' => 0,
            'total_value' => 0.0
        ];
    }

    $lineQty = (int)($parItemRow['qty'] ?? 0);
    $lineValue = (float)($parItemRow['unit_value'] ?? 0);

    $parItemSummary[$parId]['lines'] += 1;
    $parItemSummary[$parId]['qty'] += $lineQty;
    $parItemSummary[$parId]['total_value'] += ($lineQty * $lineValue);
}

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();
$eligibleAccountableUsers = array_values(array_filter($users, static function ($user) {
    return (int)($user['roleid'] ?? 0) === 6
        && strtolower(trim((string)($user['status'] ?? ''))) === 'active';
}));
usort($eligibleAccountableUsers, static function ($a, $b) {
    return strcasecmp((string)($a['fullname'] ?? ''), (string)($b['fullname'] ?? ''));
});

function parOfficerLabel(?array $user): string
{
    return formatOfficerNameWithDeptCode($user);
}

$statusOptions = ['Active', 'Returned', 'Transferred', 'Disposed'];
$statusBadgeMap = [
    'Active' => 'bg-success text-dark',
    'Returned' => 'bg-warning text-dark',
    'Transferred' => 'bg-info text-dark',
    'Disposed' => 'bg-danger'
];

$defaultParNo = $parModel->getNextPARNo();
$defaultIssueDate = date('Y-m-d');
$currentUserId = (int)(getUser('id') ?? 0);

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
                    <i class="ph ph-plus-circle me-1"></i> Add PAR
                </button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">PAR No.</th>
                            <th class="h6 text-gray-300">Fund Cluster</th>
                            <th class="h6 text-gray-300">Accountable Officer</th>
                            <th class="h6 text-gray-300">Issued By</th>
                            <th class="h6 text-gray-300">Issue Date</th>
                            <th class="h6 text-gray-300">Status</th>
                            <th class="h6 text-gray-300">Items Summary</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parList as $row) : ?>
                        <?php
                            $accountable = SearchById($users, $row['accountable_officer'] ?? '');
                            $issued = SearchById($users, $row['issued_by'] ?? '');
                            $summary = $parItemSummary[$row['id']] ?? ['lines' => 0, 'qty' => 0, 'total_value' => 0.0];
                            $statusClass = $statusBadgeMap[$row['status']] ?? 'bg-secondary';
                            ?>
                        <tr>
                            <td class="text-gray-900 fw-semibold"><?= htmlspecialchars($row['par_no']) ?></td>
                            <td class="text-gray-900"><?= htmlspecialchars($row['fund_cluster']) ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span
                                        class="text-15 fw-medium"><?= htmlspecialchars($accountable['fullname'] ?? 'Unassigned') ?></span>
                                    <?php if (!empty($accountable['department_name'])) : ?>
                                    <span
                                        class="text-gray-500 text-13"><?= htmlspecialchars($accountable['department_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span
                                        class="text-15 fw-medium"><?= htmlspecialchars($issued['fullname'] ?? 'N/A') ?></span>
                                    <?php if (!empty($issued['department_name'])) : ?>
                                    <span
                                        class="text-gray-500 text-13"><?= htmlspecialchars($issued['department_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= DisplayDate($row['issue_date'] ?? '') ?></td>
                            <td class="text-gray-900">
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span>
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
                                    <a href="par_items.php?par_id=<?= $row['id'] ?>" class="btn-action"
                                        aria-label="View items" data-bs-toggle="tooltip" title="View items">
                                        <i class="ph ph-list-bullets"></i>
                                    </a>
                                    <a href="print/par.php?id=<?= $row['id'] ?>" target="_blank" class="btn-action"
                                        aria-label="Print PAR" data-bs-toggle="tooltip" title="Print PAR">
                                        <i class="ph ph-printer"></i>
                                    </a>
                                    <button type="button" class="btn-action btn-action-primary edit-btn"
                                        aria-label="Edit PAR" data-bs-toggle="tooltip" title="Edit PAR"
                                        data-id="<?= $row['id'] ?>"
                                        data-par-no="<?= htmlspecialchars($row['par_no'], ENT_QUOTES) ?>"
                                        data-fund-cluster="<?= htmlspecialchars($row['fund_cluster'], ENT_QUOTES) ?>"
                                        data-accountable-officer="<?= htmlspecialchars($row['accountable_officer'], ENT_QUOTES) ?>"
                                        data-issued-by="<?= htmlspecialchars($row['issued_by'], ENT_QUOTES) ?>"
                                        data-issue-date="<?= htmlspecialchars($row['issue_date'], ENT_QUOTES) ?>"
                                        data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                                        data-remarks="<?= htmlspecialchars((string)($row['remarks'] ?? ''), ENT_QUOTES) ?>">
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-action-danger delete-btn"
                                        aria-label="Delete PAR" data-bs-toggle="tooltip" title="Delete PAR"
                                        data-id="<?= $row['id'] ?>">
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
            <p class="text-gray-300 text-13 fw-normal">&copy; Copyright COTSU 2025, All Right Reserverd</p>
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Add Property Acknowledgment Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addForm" action="par-actions.php?action=add" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_par_no" class="form-label">PAR No.</label>
                            <input type="text" class="form-control" id="add_par_no" name="par_no"
                                value="<?= htmlspecialchars($defaultParNo) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_fund_cluster" class="form-label">Fund Cluster</label>
                            <input type="text" class="form-control" id="add_fund_cluster" name="fund_cluster" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_accountable_officer" class="form-label">Accountable Officer</label>
                            <select class="form-select select2" id="add_accountable_officer" name="accountable_officer"
                                data-placeholder="Select accountable officer" required>
                                <option value="" disabled selected>Select User</option>
                                <?php foreach ($eligibleAccountableUsers as $user) : ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars(parOfficerLabel($user)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_issued_by" class="form-label">Issued By</label>
                            <select class="form-select select2" id="add_issued_by" name="issued_by"
                                data-placeholder="Select issuing officer" required>
                                <option value="" disabled <?= $currentUserId === 0 ? 'selected' : '' ?>>Select User
                                </option>
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
                            <label for="add_issue_date" class="form-label">Issue Date</label>
                            <input type="date" class="form-control" id="add_issue_date" name="issue_date"
                                value="<?= $defaultIssueDate ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-select" id="add_status" name="status" required>
                                <?php foreach ($statusOptions as $status) : ?>
                                <option value="<?= $status ?>" <?= $status === 'Active' ? 'selected' : '' ?>>
                                    <?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="add_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="add_remarks" name="remarks" rows="3"
                                placeholder="Optional notes"></textarea>
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
                <h5 class="modal-title" id="editModalLabel">Edit Property Acknowledgment Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" action="par-actions.php?action=update" method="post">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_par_no" class="form-label">PAR No.</label>
                            <input type="text" class="form-control" id="edit_par_no" name="par_no" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_fund_cluster" class="form-label">Fund Cluster</label>
                            <input type="text" class="form-control" id="edit_fund_cluster" name="fund_cluster" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_accountable_officer" class="form-label">Accountable Officer</label>
                            <select class="form-select select2" id="edit_accountable_officer" name="accountable_officer"
                                data-placeholder="Select accountable officer" required>
                                <?php foreach ($eligibleAccountableUsers as $user) : ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars(parOfficerLabel($user)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_issued_by" class="form-label">Issued By</label>
                            <select class="form-select select2" id="edit_issued_by" name="issued_by"
                                data-placeholder="Select issuing officer" required>
                                <?php foreach ($users as $user) : ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars(formatOfficerNameWithDeptCode($user)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_issue_date" class="form-label">Issue Date</label>
                            <input type="date" class="form-control" id="edit_issue_date" name="issue_date" required>
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
                <p>Are you sure you want to delete this PAR? <br> This action cannot be undone.</p>
                <form id="deleteForm" action="par-actions.php?action=del" method="post">
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
    new DataTable('#dtable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        order: [
            [0, 'desc']
        ],
        columnDefs: [{
            orderable: false,
            targets: [7]
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
        const btn = $(this);

        $('#edit_id').val(btn.data('id'));
        $('#edit_par_no').val(btn.data('parNo') ?? '');
        $('#edit_fund_cluster').val(btn.data('fundCluster') ?? '');
        $('#edit_accountable_officer').val(btn.data('accountableOfficer') ?? '').trigger('change');
        $('#edit_issued_by').val(btn.data('issuedBy') ?? '').trigger('change');
        $('#edit_issue_date').val(btn.data('issueDate') ?? '');
        $('#edit_status').val(btn.data('status') ?? 'Active').trigger('change');
        const remarks = btn.data('remarks');
        $('#edit_remarks').val(remarks !== undefined ? remarks : '');

        $('#editModal').modal('show');
    });

    $(document).on('click', '.delete-btn', function() {
        const parId = $(this).data('id');
        $('#delete_id').val(parId);
        $('#deleteModal').modal('show');
    });
});
</script>
