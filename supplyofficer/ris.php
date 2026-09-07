<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([2]); // Supply Officer
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/Department.model.php';

$pageTitle = "Request for Supply";


$iModel1 = new RIS();
$list = $iModel1->getAll();

$iItemsModel = new RIS_Items();
$risItemSummary = [];
foreach ($iItemsModel->getAll() as $risItemRow) {
    $risId = (int)($risItemRow['ris_id'] ?? 0);
    if ($risId === 0) {
        continue;
    }

    if (!isset($risItemSummary[$risId])) {
        $risItemSummary[$risId] = [
            'lines' => 0,
            'qty_requested' => 0,
            'qty_issued' => 0
        ];
    }

    $risItemSummary[$risId]['lines'] += 1;
    $risItemSummary[$risId]['qty_requested'] += (int)($risItemRow['qty_requested'] ?? 0);
    $risItemSummary[$risId]['qty_issued'] += (int)($risItemRow['qty_issued'] ?? 0);
}

$iRef1 = new BaseModel('departments');
$Departments = $iRef1->getAll();

$iRef2 = new BaseModel('user_role_dept');
$users = $iRef2->getAll();

$statusOptions = ['Requested', 'Approved', 'Partial Issued', 'Issued', 'Completed'];
$statusBadgeMap = [
    'Requested' => 'bg-info text-dark',
    'Approved' => 'bg-success',
    'Partial Issued' => 'bg-warning text-dark',
    'Issued' => 'bg-primary',
    'Completed' => 'bg-secondary'
];

$ris_no = $iModel1->getNextRISNo();
$requisition_date = date('Y-m-d');
$division = getUser('departmentid') ?? '';
$current_userid = getUser('id') ?? '';
$current_status = 'Requested';

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
                    <i class="ph ph-plus-circle me-1"></i> Add New Request
                </button>

            </div>
            <!-- Breadcrumb Right End -->
        </div>


        <div class="card overflow-hidden" class="p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">RIS No</th>
                            <th class="h6 text-gray-300">Purpose</th>
                            <th class="h6 text-gray-300">Items</th>
                            <th class="h6 text-gray-300">Requested By</th>
                            <th class="h6 text-gray-300">Approved By</th>
                            <th class="h6 text-gray-300">Issued By</th>
                            <th class="h6 text-gray-300">Received By</th>
                            <th class="h6 text-gray-300">Status</th>
                            <th class="h6 text-gray-300">Remarks</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r) : ?>
                        <?php
                            $requested = SearchById($users, $r['requested_by']);
                            $approved = SearchById($users, $r['approved_by']);
                            $issued = SearchById($users, $r['issued_by']);
                            $received = SearchById($users, $r['received_by']);
                            $divisionInfo = SearchById($Departments, $r['division']);
                            $summary = $risItemSummary[$r['id']] ?? ['lines' => 0, 'qty_requested' => 0, 'qty_issued' => 0];

                            $requestedName = $requested['fullname'] ?? '';
                            $approvedName = $approved['fullname'] ?? '';
                            $issuedName = $issued['fullname'] ?? '';
                            $receivedName = $received['fullname'] ?? '';
                            $divisionCode = $divisionInfo['code'] ?? '--';
                            $requisitionDateLabel = DisplayDate($r['requisition_date']);

                            $fundCluster = trim((string)($r['fund_cluster'] ?? '')) === '' ? '--' : $r['fund_cluster'];
                            $responsibilityCode = trim((string)($r['responsibility_center_code'] ?? '')) === '' ? '--' : $r['responsibility_center_code'];
                            $statusClass = $statusBadgeMap[$r['status']] ?? 'bg-light text-dark';
                            $remarksDisplay = trim((string)($r['remarks'] ?? '')) === '' ? '--' : $r['remarks'];
                        ?>
                        <tr>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= $r['ris_no'] ?></span>
                                    <span class="text-gray-500 text-13"><?= $fundCluster ?></span>
                                    <span class="text-gray-500 text-13"><?= $responsibilityCode ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= $r['purpose'] ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <?php if ($summary['lines'] > 0) : ?>
                                        <?php $itemLabel = $summary['lines'] === 1 ? 'item' : 'items'; ?>
                                        <span class="text-15 fw-medium"><?= $summary['lines'] ?> <?= $itemLabel ?></span>
                                        <span class="text-gray-500 text-13">
                                            Requested: <?= $summary['qty_requested'] ?> | Issued: <?= $summary['qty_issued'] ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="text-gray-500 text-13">No items yet</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= $requestedName ?></span>
                                    <span class="text-gray-500 text-13"><?= $divisionCode ?></span>
                                    <span class="text-gray-500 text-13"><?= $requisitionDateLabel ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= $approvedName ?></td>
                            <td class="text-gray-900"><?= $issuedName ?></td>
                            <td class="text-gray-900"><?= $receivedName ?></td>
                            <td class="text-gray-900">
                                <span class="badge <?= $statusClass ?>"><?= $r['status'] ?></span>
                            </td>
                            <td class="text-gray-900"><?= $remarksDisplay ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap justify-content-center">
                                    <a href="ris_items.php?ris_id=<?= $r['id'] ?>"
                                       class="btn-action"
                                       aria-label="View items"
                                       data-bs-toggle="tooltip"
                                       title="View items">
                                        <i class="ph ph-list-bullets"></i>
                                    </a>
                                    <a href="../administrator/print/ris.php?id=<?= $r['id'] ?>" target="_blank"
                                       class="btn-action"
                                       aria-label="Print RIS"
                                       data-bs-toggle="tooltip"
                                       title="Print RIS">
                                        <i class="ph ph-printer"></i>
                                    </a>
                                    <button class="btn-action btn-action-primary edit-btn"
                                            type="button"
                                            aria-label="Edit request"
                                            data-bs-toggle="tooltip"
                                            title="Edit request"
                                            data-id="<?= $r['id'] ?>">
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <button class="btn-action btn-action-danger delete-btn"
                                            type="button"
                                            aria-label="Delete request"
                                            data-bs-toggle="tooltip"
                                            title="Delete request"
                                            data-id="<?= $r['id'] ?>">
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

<!-- Modal -->

<!-- Add User Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <form id="addForm" action="../administrator/ris-actions.php?action=initadd" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ris_no" class="form-label">RIS No.</label>
                            <input type="text" class="form-control" id="ris_no" name="ris_no" value="<?=$ris_no;?>" readonly required>
                        </div>
                        <div class="col-md-6">
                            <label for="requisition_date" class="form-label">Requisition Date</label>
                            <input type="date" class="form-control" id="requisition_date" name="requisition_date"
                                value="<?=$requisition_date;?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="fund_cluster" class="form-label">Fund Cluster</label>
                            <input type="text" class="form-control" id="fund_cluster" name="fund_cluster" placeholder="e.g. 01-Regular Agency Fund" required>
                        </div>
                        <div class="col-md-6">
                            <label for="responsibility_center_code" class="form-label">Responsibility Center Code</label>
                            <input type="text" class="form-control" id="responsibility_center_code" name="responsibility_center_code" placeholder="Optional">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="division" class="form-label">Division / Department</label>
                            <select class="form-select select2" id="division" name="division" data-placeholder="Select division" required>
                                <option value="" <?= empty($division) ? 'selected' : '' ?>>Select Division</option>
                                <?php foreach ($Departments as $dept) : ?>
                                <option value="<?= $dept['id'] ?>" <?= ($division == $dept['id']) ? 'selected' : '' ?>>
                                    <?= $dept['code'] ?> - <?= $dept['name'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="requested_by" class="form-label">Requested By</label>
                            <select class="form-select select2" id="requested_by" name="requested_by" data-placeholder="Select requester" required>
                                <option value="" <?= empty($current_userid) ? 'selected' : '' ?>>Select Requestor</option>
                                <?php foreach ($users as $user) : ?>
                                <option value="<?= $user['id'] ?>"
                                    <?= ($current_userid == $user['id']) ? 'selected' : '' ?>>
                                    <?= $user['fullname'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="purpose" class="form-label">Purpose</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="remarks" class="form-label">Remarks (Optional)</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Add instructions for approvers or issuers"></textarea>
                        </div>
                    </div>

                    <input type="hidden" name="status" value="<?= $current_status; ?>">
                </form>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveBtn">Submit</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Update Request : </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" action="../administrator/ris-actions.php?action=update" method="post">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_ris_no_display" class="form-label">RIS No.</label>
                            <input type="text" class="form-control" id="edit_ris_no_display" readonly>
                            <input type="hidden" name="ris_no" id="edit_ris_no">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_requisition_date" class="form-label">Requisition Date</label>
                            <input type="date" class="form-control" id="edit_requisition_date" name="requisition_date" readonly>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_fund_cluster" class="form-label">Fund Cluster</label>
                            <input type="text" class="form-control" id="edit_fund_cluster" name="fund_cluster" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_responsibility_center_code" class="form-label">Responsibility Center
                                Code</label>
                            <input type="text" class="form-control" id="edit_responsibility_center_code"
                                name="responsibility_center_code">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_approved_by" class="form-label">Approved By</label>
                            <select class="form-select select2" id="edit_approved_by" name="approved_by" data-placeholder="Select approver" data-allow-clear>
                                <option value=""></option>
                                <?php foreach ($users as $user) : ?>
                                <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_issued_by" class="form-label">Issued By</label>
                            <select class="form-select select2" id="edit_issued_by" name="issued_by" data-placeholder="Select issuer" data-allow-clear>
                                <option value=""></option>
                                <?php foreach ($users as $user) : ?>
                                <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select select2" id="edit_status" name="status" data-placeholder="Select status">
                                <option value=""></option>
                                <?php foreach ($statusOptions as $status) : ?>
                                <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_received_by" class="form-label">Received By</label>
                            <select class="form-select select2" id="edit_received_by" name="received_by" data-placeholder="Select receiver" data-allow-clear>
                                <option value=""></option>
                                <?php foreach ($users as $user) : ?>
                                <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="edit_purpose" class="form-label">Purpose</label>
                            <textarea class="form-control" id="edit_purpose" name="purpose" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_remarks" class="form-label">Remarks (Optional)</label>
                            <textarea class="form-control" id="edit_remarks" name="remarks" rows="2" placeholder="Tracking notes, partial issuance details, etc."></textarea>
                        </div>
                    </div>

                    <input type="hidden" name="id" id="edit_id" >
                    <input type="hidden" name="division" id="edit_division" value="<?= $division; ?>">
                    <input type="hidden" name="requested_by" id="edit_requested_by" value="<?= $current_userid; ?>">
                    
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateBtn">Update</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this data? <br> This action cannot be undone.</p>
                <form id="deleteForm" action="../administrator/ris-actions.php?action=del" method="post">
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




<!-- Modal -->


<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function() {
    // Existing DataTable code
    new DataTable('#dtable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        "columnDefs": [{
            "orderable": false,
            "targets": [0, 4]
        }]
    });

    $('#exportOptions').on('change', function() {
        var format = $(this).val();
        if (format) {
            window.location.href = `export.php?format=${format}`;
        }
    });

    // Save new user
    $('#saveBtn').click(function() {
        $('#addForm').submit();
    });

    $("#dtable").on("click", ".edit-btn", function() {
        //showAlert("Edit User", "This feature is under development.", "info");
    });

    // Edit user button click - USE EVENT DELEGATION
    $(document).on('click', '.edit-btn', function() {
        const Id = $(this).data('id');

        $.getJSON(baseUrl+"/api/admin.php", {
            action: 'getRISById',
            id: Id
        }, function(res) {
            if (res.error) {
                showAlert("Error", res.error);
                return;
            }

            // Populate edit form fields with response data
            $('#editModalLabel').text(`Edit Request: ${res.ris_no}`);
            $('#editModalLabel').text(`Edit Request: ${res.ris_no}`);
            $('#edit_id').val(res.id);
            $('#edit_ris_no_display').val(res.ris_no);
            $('#edit_ris_no').val(res.ris_no);
            $('#edit_requisition_date').val(res.requisition_date);
            $('#edit_division').val(res.division);
            $('#edit_requested_by').val(res.requested_by);

            $('#edit_fund_cluster').val(res.fund_cluster);
            $('#edit_responsibility_center_code').val(res.responsibility_center_code);
            $('#edit_approved_by').val(res.approved_by).trigger('change');
            $('#edit_issued_by').val(res.issued_by).trigger('change');
            $('#edit_received_by').val(res.received_by).trigger('change');
            $('#edit_status').val(res.status ?? 'Requested').trigger('change');
            $('#edit_purpose').val(res.purpose);
            $('#edit_remarks').val(res.remarks);

            $('#editModal').modal('show');
        });
    });

    // Update user
    $('#updateBtn').click(function() {
        $('#editForm').submit();
    });

    // Delete user button click - USE EVENT DELEGATION
    $(document).on('click', '.delete-btn', function() {
        const Id = $(this).data('id');
        $('#delete_id').val(Id);
        $('#deleteModal').modal('show');
    });

    // Confirm delete
    $('#confirmDeleteBtn').click(function() {
        $('#deleteForm').submit();
    });
});
</script>






