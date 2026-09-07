<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/PhysicalInventory.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$reportId = (int)($_GET['report_id'] ?? 0);
if ($reportId <= 0) {
    flash('danger', 'RPCI reference is required.');
    redirect('physical_inventory_reports.php');
}

$reportModel = new PhysicalInventoryReport();
$report = $reportModel->getById($reportId);
if (!$report) {
    flash('danger', 'RPCI not found.');
    redirect('physical_inventory_reports.php');
}

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();
$prepared = SearchById($users, $report['prepared_by'] ?? '');
$verified = $report['verified_by'] ? SearchById($users, $report['verified_by']) : null;

$itemRepo = new Item();
$inventoryItems = $itemRepo->getAll();

$itemModel = new PhysicalInventoryItem();
$lineItems = $itemModel->getAllByReport($reportId);

$totals = [
    'lines' => count($lineItems),
    'system_qty' => 0,
    'counted_qty' => 0,
    'variance_qty' => 0,
    'variance_value' => 0.0
];

foreach ($lineItems as $line) {
    $systemQty = (int)($line['system_qty'] ?? 0);
    $countedQty = (int)($line['counted_qty'] ?? 0);
    $varianceQty = (int)($line['variance_qty'] ?? ($countedQty - $systemQty));
    $varianceValue = (float)($line['variance_value'] ?? (($countedQty - $systemQty) * (float)($line['unit_cost'] ?? 0)));

    $totals['system_qty'] += $systemQty;
    $totals['counted_qty'] += $countedQty;
    $totals['variance_qty'] += $varianceQty;
    $totals['variance_value'] += $varianceValue;
}

$pageTitle = 'RPCI Line Items';

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
                    <li><a href="physical_inventory_reports.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Reports &amp; Compliance</a></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= $pageTitle ?></span></li>
                </ul>
            </div>

            <div class="flex-align gap-8 flex-wrap">
                <a href="physical_inventory_reports.php" class="btn btn-outline-secondary">
                    <i class="ph ph-arrow-left me-1"></i> Back to RPCI
                </a>
                <a href="print/rpci.php?id=<?= (int)$report['id'] ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="ph ph-printer me-1"></i> Print RPCI
                </a>
                <?php if ($report['status'] ?? '' !== 'Completed'): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="ph ph-plus-circle me-1"></i> Add Line
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-24">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Report No.</span>
                            <p class="mb-0 text-18 fw-semibold"><?= htmlspecialchars($report['report_no']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Date</span>
                            <p class="mb-0 text-18 fw-semibold"><?= DisplayDate($report['report_date']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Prepared By</span>
                            <p class="mb-0 text-16 fw-semibold"><?= htmlspecialchars($prepared['fullname'] ?? 'Unknown') ?></p>
                            <?php if (!empty($prepared['department_name'])) : ?>
                                <span class="text-gray-500 text-13"><?= htmlspecialchars($prepared['department_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Verified By</span>
                            <?php if ($verified) : ?>
                                <p class="mb-0 text-16 fw-semibold"><?= htmlspecialchars($verified['fullname']) ?></p>
                                <?php if (!empty($verified['department_name'])) : ?>
                                    <span class="text-gray-500 text-13"><?= htmlspecialchars($verified['department_name']) ?></span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="badge bg-secondary">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Location</span>
                            <p class="mb-0 text-16 fw-semibold"><?= htmlspecialchars($report['location'] ?? '-') ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Lines</span>
                            <p class="mb-0 text-16 fw-semibold"><?= $totals['lines'] ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">System Qty</span>
                            <p class="mb-0 text-16 fw-semibold"><?= $totals['system_qty'] ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Counted Qty</span>
                            <p class="mb-0 text-16 fw-semibold"><?= $totals['counted_qty'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Variance Qty</span>
                            <?php
                                $varianceBadge = $totals['variance_qty'] === 0 ? 'bg-success text-dark' : ($totals['variance_qty'] > 0 ? 'bg-warning text-dark' : 'bg-danger');
                            ?>
                            <span class="badge <?= $varianceBadge ?> text-16"><?= $totals['variance_qty'] ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Variance Value</span>
                            <p class="mb-0 text-16 fw-semibold">PHP <?= number_format($totals['variance_value'], 2) ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Remarks</span>
                            <p class="mb-0 text-16"><?= htmlspecialchars($report['remarks'] ?? 'None') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="rcciItemsTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Item</th>
                            <th class="h6 text-gray-300">Location</th>
                            <th class="h6 text-gray-300 text-center">System Qty</th>
                            <th class="h6 text-gray-300 text-center">Counted Qty</th>
                            <th class="h6 text-gray-300 text-center">Variance Qty</th>
                            <th class="h6 text-gray-300 text-center">Unit Cost</th>
                            <th class="h6 text-gray-300 text-center">Book Value</th>
                            <th class="h6 text-gray-300 text-center">Counted Value</th>
                            <th class="h6 text-gray-300">Remarks</th>
                            <th class="h6 text-gray-300 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lineItems as $line) : ?>
                            <?php
                                $varianceQty = (int)($line['variance_qty'] ?? 0);
                                $varianceClass = $varianceQty === 0 ? 'badge bg-success text-dark' : ($varianceQty > 0 ? 'badge bg-warning text-dark' : 'badge bg-danger');
                            ?>
                            <tr>
                                <td class="text-gray-900">
                                    <div class="d-flex flex-column">
                                        <span class="text-15 fw-medium"><?= htmlspecialchars($line['item_code'] ?? '') ?></span>
                                        <span class="text-gray-500 text-13"><?= htmlspecialchars($line['item_description'] ?? '') ?></span>
                                        <?php if (!empty($line['item_unit'])) : ?>
                                            <span class="text-gray-400 text-12">Unit: <?= htmlspecialchars($line['item_unit']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-gray-900"><?= htmlspecialchars($line['location'] ?? '-') ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($line['system_qty'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($line['counted_qty'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center">
                                    <span class="<?= $varianceClass ?>"><?= $varianceQty ?></span>
                                </td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['unit_cost'] ?? 0), 2) ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['book_value'] ?? 0), 2) ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['counted_value'] ?? 0), 2) ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($line['remarks'] ?? '') ?></td>
                                <td class="text-gray-900 text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-primary edit-item-btn"
                                            data-id="<?= (int)$line['id'] ?>"
                                            data-item-id="<?= (int)$line['item_id'] ?>"
                                            data-location="<?= htmlspecialchars($line['location'] ?? '', ENT_QUOTES) ?>"
                                            data-system-qty="<?= (int)$line['system_qty'] ?>"
                                            data-counted-qty="<?= (int)$line['counted_qty'] ?>"
                                            data-unit-cost="<?= number_format((float)($line['unit_cost'] ?? 0), 2, '.', '') ?>"
                                            data-remarks="<?= htmlspecialchars($line['remarks'] ?? '', ENT_QUOTES) ?>">
                                            <i class="ph ph-pencil-line me-1"></i> Edit
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-danger delete-item-btn"
                                            data-id="<?= (int)$line['id'] ?>"
                                            data-label="<?= htmlspecialchars($line['item_code'] ?? '', ENT_QUOTES) ?>">
                                            <i class="ph ph-trash me-1"></i> Delete
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

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemLabel">Add Line Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addItemForm" action="physical_inventory-actions.php?action=add_item" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="add_item_id" name="item_id" data-placeholder="Select item" required>
                                <option value="" disabled selected>Select item</option>
                                <?php foreach ($inventoryItems as $item) : ?>
                                    <option value="<?= (int)$item['id'] ?>"
                                        data-code="<?= htmlspecialchars($item['code'], ENT_QUOTES) ?>"
                                        data-stock="<?= (int)$item['stock_onhand'] ?>"
                                        data-cost="<?= number_format((float)$item['unit_cost'], 2, '.', '') ?>">
                                        <?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_location_line" class="form-label">Location / Shelf</label>
                            <input type="text" class="form-control" id="add_location_line" name="location" placeholder="Optional specific location">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="add_system_qty" class="form-label">System Qty</label>
                            <input type="number" class="form-control" id="add_system_qty" name="system_qty" min="0" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="add_counted_qty" class="form-label">Counted Qty</label>
                            <input type="number" class="form-control" id="add_counted_qty" name="counted_qty" min="0" value="0" required>
                            <small class="text-gray-500 d-block">Variance: <span id="add_variance_preview">0</span></small>
                        </div>
                        <div class="col-md-4">
                            <label for="add_unit_cost" class="form-label">Unit Cost</label>
                            <input type="number" class="form-control" id="add_unit_cost" name="unit_cost" min="0" step="0.01" value="0.00" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="add_item_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="add_item_remarks" name="remarks" rows="3" placeholder="Notes about discrepancies or condition (optional)"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveItemBtn">Add Line</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editItemLabel">Update Line Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editItemForm" action="physical_inventory-actions.php?action=update_item" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" id="edit_line_id">
                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="edit_item_id" name="item_id" data-placeholder="Select item" required>
                                <?php foreach ($inventoryItems as $item) : ?>
                                    <option value="<?= (int)$item['id'] ?>"
                                        data-code="<?= htmlspecialchars($item['code'], ENT_QUOTES) ?>"
                                        data-stock="<?= (int)$item['stock_onhand'] ?>"
                                        data-cost="<?= number_format((float)$item['unit_cost'], 2, '.', '') ?>">
                                        <?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_location_line" class="form-label">Location / Shelf</label>
                            <input type="text" class="form-control" id="edit_location_line" name="location">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_system_qty" class="form-label">System Qty</label>
                            <input type="number" class="form-control" id="edit_system_qty" name="system_qty" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_counted_qty" class="form-label">Counted Qty</label>
                            <input type="number" class="form-control" id="edit_counted_qty" name="counted_qty" min="0" required>
                            <small class="text-gray-500 d-block">Variance: <span id="edit_variance_preview">0</span></small>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_unit_cost" class="form-label">Unit Cost</label>
                            <input type="number" class="form-control" id="edit_unit_cost" name="unit_cost" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_item_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="edit_item_remarks" name="remarks" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateItemBtn">Update Line</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Item Modal -->
<div class="modal fade" id="deleteItemModal" tabindex="-1" aria-labelledby="deleteItemLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteItemLabel">Remove Line Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Remove <span id="deleteItemLabelSpan" class="fw-semibold"></span> from this RPCI?</p>
                <form id="deleteItemForm" action="physical_inventory-actions.php?action=delete_item" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" id="delete_line_id">
                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteItemBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
function updateVariancePreview(prefix) {
    const systemQty = parseInt($('#' + prefix + '_system_qty').val(), 10) || 0;
    const countedQty = parseInt($('#' + prefix + '_counted_qty').val(), 10) || 0;
    const variance = countedQty - systemQty;
    $('#' + prefix + '_variance_preview').text(variance);
}

function applyItemDefaults(prefix) {
    const select = $('#' + prefix + '_item_id');
    const option = select.find('option:selected');
    if (!option.length) {
        return;
    }
    const stock = option.data('stock');
    const cost = option.data('cost');
    const systemInput = $('#' + prefix + '_system_qty');
    const unitCostInput = $('#' + prefix + '_unit_cost');

    if (!systemInput.data('manual')) {
        systemInput.val(stock !== undefined ? stock : 0);
    }
    if (!unitCostInput.data('manual')) {
        const parsedCost = cost !== undefined ? Number(cost) : 0;
        unitCostInput.val(parsedCost.toFixed(2));
    }
    updateVariancePreview(prefix);
}

$(document).ready(function() {
    new DataTable('#rcciItemsTable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        order: [[0, 'asc']],
        columnDefs: [{
            orderable: false,
            targets: [1, 8, 9]
        }]
    });

    $('#addItemModal .select2').select2({
        dropdownParent: $('#addItemModal')
    });

    $('#editItemModal .select2').select2({
        dropdownParent: $('#editItemModal')
    });

    $('#saveItemBtn').on('click', function() {
        $('#addItemForm').submit();
    });

    $('#updateItemBtn').on('click', function() {
        $('#editItemForm').submit();
    });

    $('#confirmDeleteItemBtn').on('click', function() {
        $('#deleteItemForm').submit();
    });

    $('#add_item_id').on('change', function() {
        $('#add_system_qty').data('manual', false);
        $('#add_unit_cost').data('manual', false);
        applyItemDefaults('add');
    });

    $('#edit_item_id').on('change', function() {
        applyItemDefaults('edit');
    });

    $('#add_system_qty').on('input', function() {
        $(this).data('manual', true);
        updateVariancePreview('add');
    });

    $('#add_counted_qty').on('input', function() {
        updateVariancePreview('add');
    });

    $('#add_unit_cost').on('input', function() {
        $(this).data('manual', true);
    });

    $('#edit_system_qty').on('input', function() {
        updateVariancePreview('edit');
    });

    $('#edit_counted_qty').on('input', function() {
        updateVariancePreview('edit');
    });

    $('#edit_unit_cost').on('input', function() {
        $(this).data('manual', true);
    });

    $(document).on('click', '.edit-item-btn', function() {
        const btn = $(this);
        $('#edit_line_id').val(btn.data('id'));
        $('#edit_item_id').val(btn.data('item-id')).trigger('change');
        $('#edit_location_line').val(btn.data('location'));
        $('#edit_system_qty').val(btn.data('system-qty')).data('manual', true);
        $('#edit_counted_qty').val(btn.data('counted-qty'));
        $('#edit_unit_cost').val(btn.data('unit-cost')).data('manual', true);
        $('#edit_item_remarks').val(btn.data('remarks'));
        updateVariancePreview('edit');
        $('#editItemModal').modal('show');
    });

    $(document).on('click', '.delete-item-btn', function() {
        const btn = $(this);
        $('#delete_line_id').val(btn.data('id'));
        $('#deleteItemLabelSpan').text(btn.data('label'));
        $('#deleteItemModal').modal('show');
    });

    // Initialize defaults when the add modal opens
    $('#addItemModal').on('shown.bs.modal', function() {
        applyItemDefaults('add');
        updateVariancePreview('add');
    });
});
</script>

