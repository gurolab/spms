<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/PAR.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = "PAR Assigned Items";

$parModel = new PAR();
$parList = $parModel->getAll();

$activeParId = isset($_GET['par_id']) ? trim((string)$_GET['par_id']) : '';
$selectedPar = null;
foreach ($parList as $candidate) {
    if ((string)$candidate['id'] === $activeParId) {
        $selectedPar = $candidate;
        break;
    }
}

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();
$accountable = $selectedPar ? SearchById($users, $selectedPar['accountable_officer'] ?? '') : null;
$issuedBy = $selectedPar ? SearchById($users, $selectedPar['issued_by'] ?? '') : null;

$itemsModel = new Item();
$items = $itemsModel->getAll();

$parItemsModel = new PAR_Items();
$itemList = $activeParId !== '' ? $parItemsModel->getAll($activeParId) : [];

$totalQty = 0;
$totalValue = 0.0;
foreach ($itemList as $row) {
    $qty = (int)($row['qty'] ?? 0);
    $unitValue = (float)($row['unit_value'] ?? 0);
    $totalQty += $qty;
    $totalValue += ($qty * $unitValue);
}

$status = $selectedPar['status'] ?? null;
$canModifyItems = $status === 'Active';
$statusBadgeMap = [
    'Active' => 'bg-success text-dark',
    'Returned' => 'bg-warning text-dark',
    'Transferred' => 'bg-info text-dark',
    'Disposed' => 'bg-danger'
];

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
                <select class="form-select select2" id="top_par_id" name="top_par_id" data-placeholder="Select PAR">
                    <option value="" disabled <?= $activeParId === '' ? 'selected' : '' ?>>Select PAR</option>
                    <?php foreach ($parList as $par) : ?>
                    <option value="<?= $par['id'] ?>" <?= $activeParId === (string)$par['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($par['par_no']) ?> - <?= DisplayDate($par['issue_date'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selectedPar): ?>
                <a href="print/par.php?id=<?= (int)$selectedPar['id'] ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="ph ph-printer me-1"></i> Print PAR
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selectedPar) : ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-4">
                    <div>
                        <h5 class="text-gray-900 mb-1"><?= htmlspecialchars($selectedPar['par_no']) ?></h5>
                        <p class="text-gray-500 mb-0">Fund Cluster: <span class="fw-semibold"><?= htmlspecialchars($selectedPar['fund_cluster']) ?></span></p>
                        <p class="text-gray-500 mb-0">Status:
                            <span class="badge <?= $statusBadgeMap[$status] ?? 'bg-secondary' ?>"><?= htmlspecialchars($status ?? 'N/A') ?></span>
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500 mb-0">Accountable Officer:
                            <span class="fw-semibold"><?= htmlspecialchars($accountable['fullname'] ?? 'Unassigned') ?></span>
                        </p>
                        <p class="text-gray-500 mb-0">Issued By:
                            <span class="fw-semibold"><?= htmlspecialchars($issuedBy['fullname'] ?? 'N/A') ?></span>
                        </p>
                        <p class="text-gray-500 mb-0">Issue Date:
                            <span class="fw-semibold"><?= DisplayDate($selectedPar['issue_date'] ?? '') ?></span>
                        </p>
                    </div>
                    <div class="text-end">
                        <p class="text-gray-500 mb-1">Items Summary</p>
                        <p class="text-gray-700 mb-0">Total Lines: <span class="fw-semibold"><?= count($itemList) ?></span></p>
                        <p class="text-gray-700 mb-0">Total Quantity: <span class="fw-semibold"><?= $totalQty ?></span></p>
                        <p class="text-gray-700 mb-0">Total Value: <span class="fw-semibold">PHP <?= number_format($totalValue, 2) ?></span></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($selectedPar && $canModifyItems) : ?>
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="mb-3 text-gray-900">Add Item to PAR</h6>
                <form id="inlineAddItemForm" action="par-actions.php?action=addparitem" method="post" class="row g-3 align-items-end">
                    <input type="hidden" name="par_id" value="<?= htmlspecialchars($activeParId) ?>">
                    <div class="col-md-3">
                        <label for="inline_property_no" class="form-label mb-1">Property Tag</label>
                        <input type="text" class="form-control" id="inline_property_no" name="property_no" placeholder="e.g. COTSU-2025-001" required>
                    </div>
                    <div class="col-md-3">
                        <label for="inline_item_id" class="form-label mb-1">Item</label>
                        <select class="form-select select2" id="inline_item_id" name="item_id" data-placeholder="Select item" required>
                            <option value="" disabled selected>Select Item</option>
                            <?php foreach ($items as $item) : ?>
                            <option value="<?= $item['id'] ?>"><?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label for="inline_qty" class="form-label mb-1">Qty</label>
                        <input type="number" min="1" class="form-control" id="inline_qty" name="qty" value="1" required>
                    </div>
                    <div class="col-md-2">
                        <label for="inline_unit_value" class="form-label mb-1">Unit Value</label>
                        <input type="number" min="0" step="0.01" class="form-control" id="inline_unit_value" name="unit_value" placeholder="0.00" required>
                    </div>
                    <div class="col-md-2">
                        <label for="inline_acquisition_date" class="form-label mb-1">Acquisition Date</label>
                        <input type="date" class="form-control" id="inline_acquisition_date" name="acquisition_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label for="inline_description" class="form-label mb-1">Description</label>
                        <input type="text" class="form-control" id="inline_description" name="description" placeholder="Optional additional details">
                    </div>
                    <div class="col-md-12">
                        <label for="inline_remarks" class="form-label mb-1">Remarks</label>
                        <input type="text" class="form-control" id="inline_remarks" name="remarks" placeholder="Optional remarks">
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($selectedPar && !$canModifyItems) : ?>
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="ph ph-warning-circle text-warning"></i>
            <div>Item modifications are disabled for PARs that are <?= htmlspecialchars($status) ?>.</div>
        </div>
        <?php endif; ?>

        <div class="card overflow-hidden p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="itemsTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Property Tag</th>
                            <th class="h6 text-gray-300">Item</th>
                            <th class="h6 text-gray-300">Quantity</th>
                            <th class="h6 text-gray-300">Unit Value</th>
                            <th class="h6 text-gray-300">Total Value</th>
                            <th class="h6 text-gray-300">Acquisition Date</th>
                            <th class="h6 text-gray-300">Description</th>
                            <th class="h6 text-gray-300">Remarks</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itemList as $row) : ?>
                        <?php
                            $item = SearchById($items, $row['item_id'] ?? '');
                            $lineQty = (int)($row['qty'] ?? 0);
                            $lineValue = (float)($row['unit_value'] ?? 0);
                        ?>
                        <tr>
                            <td class="text-gray-900 fw-medium"><?= htmlspecialchars($row['property_no']) ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= htmlspecialchars($item['description'] ?? 'N/A') ?></span>
                                    <?php if (!empty($item['code'])) : ?>
                                    <span class="text-gray-500 text-13"><?= htmlspecialchars($item['code']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= $lineQty ?></td>
                            <td class="text-gray-900">PHP <?= number_format($lineValue, 2) ?></td>
                            <td class="text-gray-900 fw-semibold">PHP <?= number_format($lineQty * $lineValue, 2) ?></td>
                            <td class="text-gray-900"><?= DisplayDate($row['acquisition_date'] ?? '') ?></td>
                            <td class="text-gray-900"><?= htmlspecialchars($row['description'] ?? '') ?></td>
                            <td class="text-gray-900"><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button"
                                        class="btn-action btn-action-primary edit-item-btn"
                                        aria-label="Edit item"
                                        data-bs-toggle="tooltip"
                                        title="Edit item"
                                        data-id="<?= $row['id'] ?>"
                                        data-property_no="<?= htmlspecialchars($row['property_no']) ?>"
                                        data-item_id="<?= $row['item_id'] ?>"
                                        data-qty="<?= $row['qty'] ?>"
                                        data-unit_value="<?= $row['unit_value'] ?>"
                                        data-acquisition_date="<?= $row['acquisition_date'] ?>"
                                        data-description="<?= htmlspecialchars($row['description'] ?? '', ENT_QUOTES) ?>"
                                        data-remarks="<?= htmlspecialchars($row['remarks'] ?? '', ENT_QUOTES) ?>"
                                        data-par_id="<?= $row['par_id'] ?>"
                                        <?= $canModifyItems ? '' : 'disabled' ?>>
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <form action="par-actions.php?action=delparitem" method="post"
                                        onsubmit="return confirm('Remove this item from the PAR?');">
                                        <input type="hidden" name="par_id" value="<?= htmlspecialchars($row['par_id']) ?>">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn-action btn-action-danger"
                                            aria-label="Remove item"
                                            data-bs-toggle="tooltip"
                                            title="Remove item"
                                            <?= $canModifyItems ? '' : 'disabled' ?>><i class="ph ph-trash"></i></button>
                                    </form>
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

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editItemModalLabel">Update PAR Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editItemForm" action="par-actions.php?action=updateparitem" method="post">
                    <input type="hidden" name="id" id="edit_item_id">
                    <input type="hidden" name="par_id" id="edit_item_par_id" value="<?= htmlspecialchars($activeParId) ?>">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_item_property_no" class="form-label">Property Tag</label>
                            <input type="text" class="form-control" id="edit_item_property_no" name="property_no" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_item_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="edit_item_item_id" name="item_id" data-placeholder="Select item" required>
                                <?php foreach ($items as $item) : ?>
                                <option value="<?= $item['id'] ?>"><?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_item_qty" class="form-label">Quantity</label>
                            <input type="number" min="1" class="form-control" id="edit_item_qty" name="qty" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_item_unit_value" class="form-label">Unit Value</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="edit_item_unit_value" name="unit_value" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_item_acquisition_date" class="form-label">Acquisition Date</label>
                            <input type="date" class="form-control" id="edit_item_acquisition_date" name="acquisition_date" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_item_description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="edit_item_description" name="description">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_item_remarks" class="form-label">Remarks</label>
                            <input type="text" class="form-control" id="edit_item_remarks" name="remarks">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updateItemBtn">Update</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function() {
    new DataTable('#itemsTable', {
        searching: false,
        lengthChange: false,
        info: false,
        paging: false,
        order: [[0, 'asc']],
        columnDefs: [{
            orderable: false,
            targets: [8]
        }]
    });

    $('#top_par_id').on('change', function() {
        const selectedId = $(this).val();
        if (selectedId) {
            window.location.href = 'par_items.php?par_id=' + selectedId;
        }
    });

    $(document).on('click', '.edit-item-btn', function() {
        const btn = $(this);
        $('#edit_item_id').val(btn.data('id'));
        $('#edit_item_par_id').val(btn.data('par_id') ?? $('#edit_item_par_id').val());
        $('#edit_item_property_no').val(btn.data('property_no'));
        $('#edit_item_item_id').val(btn.data('item_id'));
        $('#edit_item_qty').val(btn.data('qty'));
        $('#edit_item_unit_value').val(btn.data('unit_value'));
        $('#edit_item_acquisition_date').val(btn.data('acquisition_date'));
        $('#edit_item_description').val(btn.data('description'));
        $('#edit_item_remarks').val(btn.data('remarks'));
        $('#editItemModal').modal('show');
    });

    $('#updateItemBtn').on('click', function() {
        $('#editItemForm').submit();
    });
});
</script>

