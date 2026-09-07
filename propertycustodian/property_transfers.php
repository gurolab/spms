<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/PropertyTransfer.model.php';
require_once __DIR__ . '/../repo/PropertyCard.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = "Property Transfers";

$transferModel = new PropertyTransfer();
$transferList = $transferModel->getAll();

$cardModel = new PropertyCard();
$cards = $cardModel->getAll();

$itemModel = new Item();
$items = $itemModel->getAll();

$departmentModel = new BaseModel('departments');
$departments = $departmentModel->getAll();

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();
$eligibleRecipientUsers = array_values(array_filter($users, static function ($user) {
    return (int)($user['roleid'] ?? 0) === 6
        && strtolower(trim((string)($user['status'] ?? ''))) === 'active';
}));
usort($eligibleRecipientUsers, static function ($a, $b) {
    return strcasecmp((string)($a['fullname'] ?? ''), (string)($b['fullname'] ?? ''));
});

function transferOfficerLabel(?array $user): string
{
    return formatOfficerNameWithDeptCode($user);
}

function transferDepartmentCodeLabel(?array $department): string
{
    if (!is_array($department)) {
        return '';
    }

    $departmentCode = trim((string)(
        $department['code']
        ?? $department['departmentcode']
        ?? $department['department_code']
        ?? ''
    ));

    return strtoupper($departmentCode);
}

$statusOptions = PropertyTransfer::STATUSES;
$typeOptions = PropertyTransfer::TYPES;

$defaultTransferNo = PropertyTransfer::getNextTransferNo();
$defaultDate = date('Y-m-d');
$currentUserId = (int)(getUser('id') ?? 0);

?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

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
                    <i class="ph ph-plus-circle me-1"></i> Record Transfer
                </button>
            </div>
        </div>
        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Transfer No.</th>
                            <th class="h6 text-gray-300">Date</th>
                            <th class="h6 text-gray-300">Property Tag</th>
                            <th class="h6 text-gray-300">From</th>
                            <th class="h6 text-gray-300">To</th>
                            <th class="h6 text-gray-300">Status</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transferList as $row): ?>
                        <?php
                            $fromOfficer = SearchById($users, $row['from_officer'] ?? '');
                            $toOfficer = SearchById($users, $row['to_officer'] ?? '');
                            $fromDept = SearchById($departments, $row['from_department'] ?? '');
                            $toDept = SearchById($departments, $row['to_department'] ?? '');
                        ?>
                        <tr>
                            <td class="text-gray-900 fw-semibold"><?= htmlspecialchars($row['transfer_no']) ?></td>
                            <td class="text-gray-900"><?= DisplayDate($row['transfer_date'] ?? '') ?></td>
                            <td class="text-gray-900"><?= htmlspecialchars($row['property_tag']) ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-13 text-gray-700 fw-medium"><?= htmlspecialchars($fromOfficer['fullname'] ?? 'N/A') ?></span>
                                    <span class="text-gray-500 text-12"><?= htmlspecialchars(transferDepartmentCodeLabel($fromDept)) ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-13 text-gray-700 fw-medium"><?= htmlspecialchars($toOfficer['fullname'] ?? 'N/A') ?></span>
                                    <span class="text-gray-500 text-12"><?= htmlspecialchars(transferDepartmentCodeLabel($toDept)) ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <span class="badge <?= $row['status'] === 'Completed' ? 'bg-success text-dark' : ($row['status'] === 'Cancelled' ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= htmlspecialchars($row['status']) ?></span>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap justify-content-center">
                                    <button type="button" class="btn-action btn-action-primary edit-btn"
                                        aria-label="Edit transfer"
                                        data-bs-toggle="tooltip"
                                        title="Edit transfer"
                                        data-id="<?= $row['id'] ?>"
                                        data-transfer_no="<?= htmlspecialchars($row['transfer_no'], ENT_QUOTES) ?>"
                                        data-transfer_date="<?= htmlspecialchars($row['transfer_date'], ENT_QUOTES) ?>"
                                        data-property_tag="<?= htmlspecialchars($row['property_tag'], ENT_QUOTES) ?>"
                                        data-item_id="<?= htmlspecialchars($row['item_id'], ENT_QUOTES) ?>"
                                        data-from_officer="<?= htmlspecialchars($row['from_officer'] ?? '', ENT_QUOTES) ?>"
                                        data-to_officer="<?= htmlspecialchars($row['to_officer'] ?? '', ENT_QUOTES) ?>"
                                        data-from_department="<?= htmlspecialchars($row['from_department'] ?? '', ENT_QUOTES) ?>"
                                        data-to_department="<?= htmlspecialchars($row['to_department'] ?? '', ENT_QUOTES) ?>"
                                        data-transfer_type="<?= htmlspecialchars($row['transfer_type'], ENT_QUOTES) ?>"
                                        data-reason="<?= htmlspecialchars((string)($row['reason'] ?? ''), ENT_QUOTES) ?>"
                                        data-prepared_by="<?= htmlspecialchars($row['prepared_by'] ?? '', ENT_QUOTES) ?>"
                                        data-noted_by="<?= htmlspecialchars($row['noted_by'] ?? '', ENT_QUOTES) ?>"
                                        data-acknowledged_by="<?= htmlspecialchars($row['acknowledged_by'] ?? '', ENT_QUOTES) ?>"
                                        data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>">
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn-action status-btn"
                                        aria-label="Update status"
                                        data-bs-toggle="tooltip"
                                        title="Update status"
                                        data-id="<?= $row['id'] ?>"
                                        data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>">
                                        <i class="ph ph-arrows-left-right"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-action-danger delete-btn"
                                        aria-label="Delete transfer"
                                        data-bs-toggle="tooltip"
                                        title="Delete transfer"
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
            <p class="text-gray-300 text-13 fw-normal"> &copy; Copyright COTSU 2025, All Right Reserverd</p>
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>
</div>
<!-- Add Transfer Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Record Property Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addForm" action="property_transfer-actions.php?action=add" method="post">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="add_transfer_no" class="form-label">Transfer No.</label>
                            <input type="text" class="form-control" id="add_transfer_no" name="transfer_no" value="<?= htmlspecialchars($defaultTransferNo) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="add_transfer_date" class="form-label">Transfer Date</label>
                            <input type="date" class="form-control" id="add_transfer_date" name="transfer_date" value="<?= $defaultDate ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="add_transfer_type" class="form-label">Transfer Type</label>
                            <select class="form-select select2" id="add_transfer_type" name="transfer_type" data-placeholder="Select type" required>
                                <?php foreach ($typeOptions as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_property_tag" class="form-label">Property Tag</label>
                            <select class="form-select select2" id="add_property_tag" name="property_tag" data-placeholder="Select property tag" required>
                                <option value="" disabled selected>Select Tag</option>
                                <?php foreach ($cards as $card): ?>
                                <?php
                                    $accountableUser = SearchById($users, $card['accountable_officer'] ?? '');
                                    $accountableDeptId = $accountableUser['departmentid'] ?? '';
                                ?>
                                <option value="<?= htmlspecialchars($card['property_tag']) ?>"
                                    data-item-id="<?= htmlspecialchars($card['item_id']) ?>"
                                    data-accountable-officer="<?= htmlspecialchars($card['accountable_officer'] ?? '') ?>"
                                    data-department-id="<?= htmlspecialchars($accountableDeptId) ?>">
                                    <?= htmlspecialchars($card['property_tag']) ?> - <?= htmlspecialchars($card['card_no']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="add_item_id" name="item_id" data-placeholder="Select item" required>
                                <option value="" disabled selected>Select Item</option>
                                <?php foreach ($items as $item): ?>
                                <option value="<?= $item['id'] ?>"><?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_from_officer" class="form-label">From Officer</label>
                            <select class="form-select select2" id="add_from_officer" name="from_officer" data-placeholder="Select officer" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_to_officer" class="form-label">To Officer</label>
                            <select class="form-select select2" id="add_to_officer" name="to_officer" data-placeholder="Select officer" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($eligibleRecipientUsers as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars(transferOfficerLabel($user)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_from_department" class="form-label">From Department</label>
                            <select class="form-select select2" id="add_from_department" name="from_department" data-placeholder="Select department" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_to_department" class="form-label">To Department</label>
                            <select class="form-select select2" id="add_to_department" name="to_department" data-placeholder="Select department" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="add_prepared_by" class="form-label">Prepared By</label>
                            <select class="form-select select2" id="add_prepared_by" name="prepared_by" data-placeholder="Select preparer" data-allow-clear>
                                <option value="" <?= $currentUserId === 0 ? 'selected' : '' ?>>Optional</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $currentUserId === (int)$user['id'] ? 'selected' : '' ?>><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="add_noted_by" class="form-label">Noted By</label>
                            <select class="form-select select2" id="add_noted_by" name="noted_by" data-placeholder="Select reviewer" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="add_acknowledged_by" class="form-label">Acknowledged By</label>
                            <select class="form-select select2" id="add_acknowledged_by" name="acknowledged_by" data-placeholder="Select approver" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-select select2" id="add_status" name="status" data-placeholder="Select status" required>
                                <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= $status ?>" <?= $status === 'Pending' ? 'selected' : '' ?>><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_reason" class="form-label">Reason / Notes</label>
                            <textarea class="form-control" id="add_reason" name="reason" rows="1" placeholder="Optional notes"></textarea>
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
<!-- Edit Transfer Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Update Property Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" action="property_transfer-actions.php?action=update" method="post">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_transfer_no" class="form-label">Transfer No.</label>
                            <input type="text" class="form-control" id="edit_transfer_no" name="transfer_no" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_transfer_date" class="form-label">Transfer Date</label>
                            <input type="date" class="form-control" id="edit_transfer_date" name="transfer_date" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_transfer_type" class="form-label">Transfer Type</label>
                            <select class="form-select select2" id="edit_transfer_type" name="transfer_type" data-placeholder="Select type" required>
                                <?php foreach ($typeOptions as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_property_tag" class="form-label">Property Tag</label>
                            <select class="form-select select2" id="edit_property_tag" name="property_tag" data-placeholder="Select property tag" required>
                                <?php foreach ($cards as $card): ?>
                                <?php
                                    $accountableUser = SearchById($users, $card['accountable_officer'] ?? '');
                                    $accountableDeptId = $accountableUser['departmentid'] ?? '';
                                ?>
                                <option value="<?= htmlspecialchars($card['property_tag']) ?>"
                                    data-item-id="<?= htmlspecialchars($card['item_id']) ?>"
                                    data-accountable-officer="<?= htmlspecialchars($card['accountable_officer'] ?? '') ?>"
                                    data-department-id="<?= htmlspecialchars($accountableDeptId) ?>">
                                    <?= htmlspecialchars($card['property_tag']) ?> - <?= htmlspecialchars($card['card_no']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="edit_item_id" name="item_id" data-placeholder="Select item" required>
                                <?php foreach ($items as $item): ?>
                                <option value="<?= $item['id'] ?>"><?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_from_officer" class="form-label">From Officer</label>
                            <select class="form-select select2" id="edit_from_officer" name="from_officer" data-placeholder="Select officer" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_to_officer" class="form-label">To Officer</label>
                            <select class="form-select select2" id="edit_to_officer" name="to_officer" data-placeholder="Select officer" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($eligibleRecipientUsers as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars(transferOfficerLabel($user)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_from_department" class="form-label">From Department</label>
                            <select class="form-select select2" id="edit_from_department" name="from_department" data-placeholder="Select department" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_to_department" class="form-label">To Department</label>
                            <select class="form-select select2" id="edit_to_department" name="to_department" data-placeholder="Select department" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_prepared_by" class="form-label">Prepared By</label>
                            <select class="form-select select2" id="edit_prepared_by" name="prepared_by" data-placeholder="Select preparer" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_noted_by" class="form-label">Noted By</label>
                            <select class="form-select select2" id="edit_noted_by" name="noted_by" data-placeholder="Select reviewer" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_acknowledged_by" class="form-label">Acknowledged By</label>
                            <select class="form-select select2" id="edit_acknowledged_by" name="acknowledged_by" data-placeholder="Select approver" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select select2" id="edit_status" name="status" data-placeholder="Select status" required>
                                <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_reason" class="form-label">Reason / Notes</label>
                            <textarea class="form-control" id="edit_reason" name="reason" rows="1"></textarea>
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

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">Update Transfer Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm" action="property_transfer-actions.php?action=updatestatus" method="post">
                    <input type="hidden" name="id" id="status_id">
                    <div class="mb-3">
                        <label for="status_status" class="form-label">Status</label>
                        <select class="form-select select2" id="status_status" name="status" data-placeholder="Select status" required>
                            <?php foreach ($statusOptions as $status): ?>
                            <option value="<?= $status ?>"><?= $status ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="statusUpdateBtn">Update Status</button>
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
                <p>Are you sure you want to delete this transfer record? <br> This action cannot be undone.</p>
                <form id="deleteForm" action="property_transfer-actions.php?action=delete" method="post">
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
    const currentUserId = <?= (int)$currentUserId ?>;

    $('#addModal').on('shown.bs.modal', function() {
        if (currentUserId <= 0) {
            return;
        }
        const $preparedBy = $('#add_prepared_by');
        if ($preparedBy.find(`option[value="${currentUserId}"]`).length) {
            $preparedBy.val(String(currentUserId)).trigger('change');
        }
    });

    function syncCardDetails($tagSelect, $itemSelect, $officerSelect, $deptSelect) {
        $tagSelect.on('change', function() {
            const $selected = $(this).find('option:selected');
            const itemId = $selected.data('item-id');
            const officerId = $selected.data('accountable-officer');
            const deptId = $selected.data('department-id');

            if (itemId) {
                $itemSelect.val(itemId).trigger('change');
            } else {
                $itemSelect.val('').trigger('change');
            }

            if (officerId) {
                $officerSelect.val(officerId).trigger('change');
            } else {
                $officerSelect.val('').trigger('change');
            }

            if (deptId) {
                $deptSelect.val(deptId).trigger('change');
            } else {
                $deptSelect.val('').trigger('change');
            }
        });
    }

    syncCardDetails($('#add_property_tag'), $('#add_item_id'), $('#add_from_officer'), $('#add_from_department'));
    syncCardDetails($('#edit_property_tag'), $('#edit_item_id'), $('#edit_from_officer'), $('#edit_from_department'));

    new DataTable('#dtable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        order: [[1, 'desc']],
        columnDefs: [{ orderable: false, targets: [6] }]
    });

    $('#saveBtn').on('click', function() {
        $('#addForm').submit();
    });

    $('#updateBtn').on('click', function() {
        $('#editForm').submit();
    });

    $('#statusUpdateBtn').on('click', function() {
        $('#statusForm').submit();
    });

    $('#confirmDeleteBtn').on('click', function() {
        $('#deleteForm').submit();
    });

    $(document).on('click', '.edit-btn', function() {
        const btn = $(this);
        $('#edit_id').val(btn.data('id'));
        $('#edit_transfer_no').val(btn.data('transfer_no') ?? '');
        $('#edit_transfer_date').val(btn.data('transfer_date') ?? '');
        $('#edit_transfer_type').val(btn.data('transfer_type') ?? 'Department').trigger('change');
        $('#edit_property_tag').val(btn.data('property_tag') ?? '').trigger('change');
        if (btn.data('item_id')) {
            $('#edit_item_id').val(btn.data('item_id')).trigger('change');
        }
        $('#edit_from_officer').val(btn.data('from_officer') ?? '').trigger('change');
        $('#edit_to_officer').val(btn.data('to_officer') ?? '').trigger('change');
        $('#edit_from_department').val(btn.data('from_department') ?? '').trigger('change');
        $('#edit_to_department').val(btn.data('to_department') ?? '').trigger('change');
        $('#edit_prepared_by').val(btn.data('prepared_by') ?? '').trigger('change');
        $('#edit_noted_by').val(btn.data('noted_by') ?? '').trigger('change');
        $('#edit_acknowledged_by').val(btn.data('acknowledged_by') ?? '').trigger('change');
        $('#edit_status').val(btn.data('status') ?? 'Pending').trigger('change');
        const reason = btn.data('reason');
        $('#edit_reason').val(reason !== undefined ? reason : '');
        $('#editModal').modal('show');
    });

    $(document).on('click', '.status-btn', function() {
        const btn = $(this);
        $('#status_id').val(btn.data('id'));
        $('#status_status').val(btn.data('status') ?? 'Pending').trigger('change');
        $('#statusModal').modal('show');
    });

    $(document).on('click', '.delete-btn', function() {
        $('#delete_id').val($(this).data('id'));
        $('#deleteModal').modal('show');
    });
});
</script>
