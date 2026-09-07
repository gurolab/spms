<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/PropertyCard.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = "Property Cards";

$cardModel = new PropertyCard();
$cardList = $cardModel->getAll();

$transactionModel = new PropertyCardTransaction();
$transactionSummary = [];
foreach ($transactionModel->getAll() as $row) {
    $cardId = (int)($row['property_card_id'] ?? 0);
    if ($cardId === 0) {
        continue;
    }
    if (!isset($transactionSummary[$cardId])) {
        $transactionSummary[$cardId] = [
            'count' => 0,
            'last_status' => null,
            'last_date' => null,
        ];
    }
    $transactionSummary[$cardId]['count'] += 1;
    if ($transactionSummary[$cardId]['last_date'] === null || $row['transaction_date'] >= $transactionSummary[$cardId]['last_date']) {
        $transactionSummary[$cardId]['last_date'] = $row['transaction_date'];
        $transactionSummary[$cardId]['last_status'] = $row['status'];
    }
}

$itemModel = new Item();
$items = $itemModel->getAll();

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();

$eligibleAccountabilityUsers = array_values(array_filter($users, static function ($user) {
    return (int)($user['roleid'] ?? 0) === 6
        && strtolower(trim((string)($user['status'] ?? ''))) === 'active';
}));
usort($eligibleAccountabilityUsers, static function ($a, $b) {
    return strcasecmp((string)($a['fullname'] ?? ''), (string)($b['fullname'] ?? ''));
});

function propertyCardOfficerLabel(?array $user): string
{
    return formatOfficerNameWithDeptCode($user);
}

$parModel = new BaseModel('property_acknowledgment_receipts');
$pars = $parModel->getAll();

$statusOptions = PropertyCard::STATUSES;
$statusBadges = [
    'Assigned' => 'bg-success text-dark',
    'For Repair' => 'bg-warning text-dark',
    'Unserviceable' => 'bg-danger',
    'Disposed' => 'bg-secondary text-dark'
];

$defaultCardNo = PropertyCard::getNextCardNo();
$defaultStatus = 'Assigned';
$defaultAcquisitionDate = date('Y-m-d');
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
                    <i class="ph ph-plus-circle me-1"></i> Add Property Card
                </button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Card No.</th>
                            <th class="h6 text-gray-300">Property Tag</th>
                            <th class="h6 text-gray-300">Item</th>
                            <th class="h6 text-gray-300">Accountable Officer</th>
                            <th class="h6 text-gray-300">Current Status</th>
                            <th class="h6 text-gray-300">Location</th>
                            <th class="h6 text-gray-300">Acquired</th>
                            <th class="h6 text-gray-300">Transactions</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                                                                                <tbody>
                        <?php foreach ($cardList as $card): ?>
                        <?php
                            $item = SearchById($items, $card['item_id'] ?? '');
                            $accountable = SearchById($users, $card['accountable_officer'] ?? '');
                            $par = $card['par_id'] ? SearchById($pars, $card['par_id']) : null;
                            $summary = $transactionSummary[$card['id']] ?? ['count' => 0, 'last_status' => null, 'last_date' => null];
                            $statusClass = $statusBadges[$card['current_status']] ?? 'bg-secondary';
                        ?>
                        <tr>
                            <td class="text-gray-900 fw-semibold"><?= htmlspecialchars($card['card_no']) ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= htmlspecialchars($card['property_tag']) ?></span>
                                    <?php if ($par): ?>
                                    <span class="text-gray-500 text-13">PAR: <?= htmlspecialchars($par['par_no']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= htmlspecialchars($item['description'] ?? 'N/A') ?></span>
                                    <?php if (!empty($item['code'])): ?>
                                    <span class="text-gray-500 text-13"><?= htmlspecialchars($item['code']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= htmlspecialchars($accountable['fullname'] ?? 'Unassigned') ?></span>
                                    <?php if (!empty($accountable['department_name'])): ?>
                                    <span class="text-gray-500 text-13"><?= htmlspecialchars($accountable['department_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($card['current_status']) ?></span>
                            </td>
                            <td class="text-gray-900"><?= htmlspecialchars($card['location'] ?? '') ?></td>
                            <td class="text-gray-900"><?= DisplayDate($card['acquisition_date'] ?? '') ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-13 text-gray-500"><?= $summary['count'] ?> movement(s)</span>
                                    <?php if (!empty($summary['last_status'])): ?>
                                    <span class="text-13 text-gray-500">Last: <?= htmlspecialchars($summary['last_status']) ?> (<?= DisplayDate($summary['last_date']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap justify-content-center">
                                    <a href="property_card_transactions.php?card_id=<?= $card['id'] ?>"
                                       class="btn-action"
                                       aria-label="View history"
                                       data-bs-toggle="tooltip"
                                       title="View history">
                                        <i class="ph ph-list-bullets"></i>
                                    </a>
                                    <a href="../administrator/print/property_card.php?id=<?= $card['id'] ?>"
                                       target="_blank"
                                       class="btn-action"
                                       aria-label="Print property card"
                                       data-bs-toggle="tooltip"
                                       title="Print property card">
                                        <i class="ph ph-printer"></i>
                                    </a>
                                    <button type="button"
                                        class="btn-action btn-action-primary edit-btn"
                                        aria-label="Edit card"
                                        data-bs-toggle="tooltip"
                                        title="Edit card"
                                        data-id="<?= $card['id'] ?>"
                                        data-card_no="<?= htmlspecialchars($card['card_no'], ENT_QUOTES) ?>"
                                        data-par_id="<?= htmlspecialchars($card['par_id'] ?? '', ENT_QUOTES) ?>"
                                        data-item_id="<?= htmlspecialchars($card['item_id'], ENT_QUOTES) ?>"
                                        data-property_tag="<?= htmlspecialchars($card['property_tag'], ENT_QUOTES) ?>"
                                        data-accountable_officer="<?= htmlspecialchars($card['accountable_officer'] ?? '', ENT_QUOTES) ?>"
                                        data-location="<?= htmlspecialchars($card['location'] ?? '', ENT_QUOTES) ?>"
                                        data-acquisition_date="<?= htmlspecialchars($card['acquisition_date'] ?? '', ENT_QUOTES) ?>"
                                        data-acquisition_cost="<?= htmlspecialchars($card['acquisition_cost'] ?? '', ENT_QUOTES) ?>"
                                        data-current_status="<?= htmlspecialchars($card['current_status'], ENT_QUOTES) ?>"
                                        data-remarks="<?= htmlspecialchars((string)($card['remarks'] ?? ''), ENT_QUOTES) ?>">
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-action-danger delete-btn"
                                        aria-label="Delete card"
                                        data-bs-toggle="tooltip"
                                        title="Delete card"
                                        data-id="<?= $card['id'] ?>">
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
<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Add Property Card</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addForm" action="property_card-actions.php?action=add" method="post">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="add_card_no" class="form-label">Card No.</label>
                            <input type="text" class="form-control" id="add_card_no" name="card_no" value="<?= htmlspecialchars($defaultCardNo) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="add_property_tag" class="form-label">Property Tag</label>
                            <input type="text" class="form-control" id="add_property_tag" name="property_tag" required>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <label for="add_par_id" class="form-label">Linked PAR</label>
                            <select class="form-select select2" id="add_par_id" name="par_id" data-placeholder="Link PAR" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($pars as $par): ?>
                                <option value="<?= $par['id'] ?>"><?= htmlspecialchars($par['par_no']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="add_accountable_officer" class="form-label">Accountable Officer</label>
                            <select class="form-select select2" id="add_accountable_officer" name="accountable_officer" data-placeholder="Assign officer" data-allow-clear>
                                <option value="" <?= $currentUserId === 0 ? 'selected' : '' ?>>Unassigned</option>
                                <?php foreach ($eligibleAccountabilityUsers as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $currentUserId === (int)$user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(propertyCardOfficerLabel($user)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="add_location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="add_location" name="location" placeholder="Office / Room">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="add_acquisition_date" class="form-label">Acquisition Date</label>
                            <input type="date" class="form-control" id="add_acquisition_date" name="acquisition_date" value="<?= $defaultAcquisitionDate ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="add_acquisition_cost" class="form-label">Acquisition Cost</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="add_acquisition_cost" name="acquisition_cost" placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label for="add_current_status" class="form-label">Status</label>
                            <select class="form-select" id="add_current_status" name="current_status" required>
                                <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= $status ?>" <?= $status === $defaultStatus ? 'selected' : '' ?>><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="add_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="add_remarks" name="remarks" rows="3" placeholder="Notes or additional details"></textarea>
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
                <h5 class="modal-title" id="editModalLabel">Edit Property Card</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" action="property_card-actions.php?action=update" method="post">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_card_no" class="form-label">Card No.</label>
                            <input type="text" class="form-control" id="edit_card_no" name="card_no" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_property_tag" class="form-label">Property Tag</label>
                            <input type="text" class="form-control" id="edit_property_tag" name="property_tag" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="edit_item_id" name="item_id" data-placeholder="Select item" required>
                                <?php foreach ($items as $item): ?>
                                <option value="<?= $item['id'] ?>"><?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_par_id" class="form-label">Linked PAR</label>
                            <select class="form-select select2" id="edit_par_id" name="par_id" data-placeholder="Link PAR" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($pars as $par): ?>
                                <option value="<?= $par['id'] ?>"><?= htmlspecialchars($par['par_no']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_accountable_officer" class="form-label">Accountable Officer</label>
                            <select class="form-select select2" id="edit_accountable_officer" name="accountable_officer" data-placeholder="Assign officer" data-allow-clear>
                                <option value="">Unassigned</option>
                                <?php foreach ($eligibleAccountabilityUsers as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars(propertyCardOfficerLabel($user)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="edit_location" name="location">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_acquisition_date" class="form-label">Acquisition Date</label>
                            <input type="date" class="form-control" id="edit_acquisition_date" name="acquisition_date">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_acquisition_cost" class="form-label">Acquisition Cost</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="edit_acquisition_cost" name="acquisition_cost">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_current_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_current_status" name="current_status" required>
                                <?php foreach ($statusOptions as $status): ?>
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
                <p>Are you sure you want to delete this property card? <br> This action cannot be undone.</p>
                <form id="deleteForm" action="property_card-actions.php?action=delete" method="post">
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
        order: [[0, 'asc']],
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
        const btn = $(this);
        $('#edit_id').val(btn.data('id'));
        $('#edit_card_no').val(btn.data('card_no') ?? '');
        $('#edit_property_tag').val(btn.data('property_tag') ?? '').trigger('change');
        $('#edit_item_id').val(btn.data('item_id') ?? '').trigger('change');
        $('#edit_par_id').val(btn.data('par_id') ?? '').trigger('change');
        $('#edit_accountable_officer').val(btn.data('accountable_officer') ?? '').trigger('change');
        $('#edit_location').val(btn.data('location') ?? '');
        $('#edit_acquisition_date').val(btn.data('acquisition_date') ?? '');
        $('#edit_acquisition_cost').val(btn.data('acquisition_cost') ?? '');
        $('#edit_current_status').val(btn.data('current_status') ?? 'Assigned').trigger('change');
        const remarks = btn.data('remarks');
        $('#edit_remarks').val(remarks !== undefined ? remarks : '');
        $('#editModal').modal('show');
    });

    $(document).on('click', '.delete-btn', function() {
        const cardId = $(this).data('id');
        $('#delete_id').val(cardId);
        $('#deleteModal').modal('show');
    });
});
</script>





