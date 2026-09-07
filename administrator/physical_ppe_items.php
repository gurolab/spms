<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/PhysicalPpe.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$reportId = (int)($_GET['report_id'] ?? 0);
if ($reportId <= 0) {
    flash('danger', 'RPCPPE reference is required.');
    redirect('physical_ppe_reports.php');
}

$reportModel = new PhysicalPpeReport();
$report = $reportModel->getById($reportId);
if (!$report) {
    flash('danger', 'RPCPPE not found.');
    redirect('physical_ppe_reports.php');
}

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();
$prepared = SearchById($users, $report['prepared_by'] ?? '');
$verified = $report['verified_by'] ? SearchById($users, $report['verified_by']) : null;

$itemRepo = new Item();
$itemsMaster = $itemRepo->getAll();

$itemModel = new PhysicalPpeItem();
$lineItems = $itemModel->getAllByReport($reportId);

$totals = [
    'lines' => count($lineItems),
    'property_qty' => 0,
    'physical_qty' => 0,
    'variance_qty' => 0,
    'total_cost' => 0.0
];

foreach ($lineItems as $line) {
    $propertyQty = (int)($line['property_card_qty'] ?? 0);
    $physicalQty = (int)($line['physical_qty'] ?? 0);
    $varianceQty = (int)($line['variance_qty'] ?? ($physicalQty - $propertyQty));
    $cost = (float)($line['cost'] ?? 0);

    $totals['property_qty'] += $propertyQty;
    $totals['physical_qty'] += $physicalQty;
    $totals['variance_qty'] += $varianceQty;
    $totals['total_cost'] += $cost;
}

$pageTitle = 'RPCPPE Line Items';

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
                    <li><a href="physical_ppe_reports.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Reports &amp; Compliance</a></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= $pageTitle ?></span></li>
                </ul>
            </div>

            <div class="flex-align gap-8 flex-wrap">
                <a href="physical_ppe_reports.php" class="btn btn-outline-secondary">
                    <i class="ph ph-arrow-left me-1"></i> Back to RPCPPE
                </a>
                <a href="print/rpcppe.php?id=<?= (int)$report['id'] ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="ph ph-printer me-1"></i> Print RPCPPE
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="ph ph-plus-circle me-1"></i> Add Line
                </button>
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
                            <span class="text-gray-500 text-uppercase text-12">Report Date</span>
                            <p class="mb-0 text-18 fw-semibold"><?= DisplayDate($report['report_date']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Fund Cluster</span>
                            <p class="mb-0 text-16 fw-semibold"><?= htmlspecialchars($report['fund_cluster']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Station</span>
                            <p class="mb-0 text-16 fw-semibold"><?= htmlspecialchars($report['station']) ?></p>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
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
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Lines</span>
                            <p class="mb-0 text-16 fw-semibold"><?= $totals['lines'] ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Total Cost</span>
                            <p class="mb-0 text-16 fw-semibold"><?= number_format($totals['total_cost'], 2) ?></p>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Property Card Qty</span>
                            <p class="mb-0 text-16 fw-semibold"><?= $totals['property_qty'] ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Physical Qty</span>
                            <p class="mb-0 text-16 fw-semibold"><?= $totals['physical_qty'] ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Variance Qty</span>
                            <?php
                                $varianceClass = $totals['variance_qty'] === 0 ? 'bg-success text-dark' : ($totals['variance_qty'] > 0 ? 'bg-warning text-dark' : 'bg-danger');
                            ?>
                            <span class="badge <?= $varianceClass ?> text-16"><?= $totals['variance_qty'] ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
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
                <table id="rpcppeItemsTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Item</th>
                            <th class="h6 text-gray-300">Property No.</th>
                            <th class="h6 text-gray-300">Asset Tag</th>
                            <th class="h6 text-gray-300 text-center">On Record Qty</th>
                            <th class="h6 text-gray-300 text-center">Physical Qty</th>
                            <th class="h6 text-gray-300 text-center">Variance Qty</th>
                            <th class="h6 text-gray-300 text-center">Cost</th>
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
                                <td class="text-gray-900"><?= htmlspecialchars($line['property_no'] ?? '-') ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($line['asset_tag'] ?? '-') ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($line['property_card_qty'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($line['physical_qty'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center">
                                    <span class="<?= $varianceClass ?>"><?= $varianceQty ?></span>
                                </td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['cost'] ?? 0), 2) ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($line['remarks'] ?? '') ?></td>
                                <td class="text-gray-900 text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-primary edit-item-btn"
                                            data-id="<?= (int)$line['id'] ?>"
                                            data-item-id="<?= (int)$line['item_id'] ?>"
                                            data-property-no="<?= htmlspecialchars($line['property_no'] ?? '', ENT_QUOTES) ?>"
                                            data-asset-tag="<?= htmlspecialchars($line['asset_tag'] ?? '', ENT_QUOTES) ?>"
                                            data-property-qty="<?= (int)$line['property_card_qty'] ?>"
                                            data-physical-qty="<?= (int)$line['physical_qty'] ?>"
                                            data-cost="<?= number_format((float)($line['cost'] ?? 0), 2, '.', '') ?>"
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
                <h5 class="modal-title" id="addItemLabel">Add PPE Line</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addItemForm" action="physical_ppe-actions.php?action=add_item" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="add_item_id" name="item_id" data-placeholder="Select item" required>
                                <option value="" disabled selected>Select item</option>
                                <?php foreach ($itemsMaster as $item) : ?>
                                    <option value="<?= (int)$item['id'] ?>"
                                        data-code="<?= htmlspecialchars($item['code'], ENT_QUOTES) ?>"
                                        data-unit="<?= htmlspecialchars($item['unit'], ENT_QUOTES) ?>">
                                        <?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="add_property_no" class="form-label">Property No.</label>
                            <input type="text" class="form-control" id="add_property_no" name="property_no" placeholder="Optional">
                        </div>
                        <div class="col-md-3">
                            <label for="add_asset_tag" class="form-label">Asset Tag</label>
                            <input type="text" class="form-control" id="add_asset_tag" name="asset_tag" placeholder="Optional">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="add_property_qty" class="form-label">On Record Qty</label>
                            <input type="number" class="form-control" id="add_property_qty" name="property_card_qty" min="0" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="add_physical_qty" class="form-label">Physical Qty</label>
                            <input type="number" class="form-control" id="add_physical_qty" name="physical_qty" min="0" value="0" required>
                            <small class="text-gray-500 d-block">Variance: <span id="add_variance_preview">0</span></small>
                        </div>
                        <div class="col-md-4">
                            <label for="add_cost" class="form-label">Cost</label>
                            <input type="number" class="form-control" id="add_cost" name="cost" min="0" step="0.01" value="0.00" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="add_item_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="add_item_remarks" name="remarks" rows="3" placeholder="Condition, variance explanation, etc. (optional)"></textarea>
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
                <h5 class="modal-title" id="editItemLabel">Update PPE Line</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editItemForm" action="physical_ppe-actions.php?action=update_item" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" id="edit_line_id">
                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="edit_item_id" name="item_id" data-placeholder="Select item" required>
                                <?php foreach ($itemsMaster as $item) : ?>
                                    <option value="<?= (int)$item['id'] ?>"
                                        data-code="<?= htmlspecialchars($item['code'], ENT_QUOTES) ?>"
                                        data-unit="<?= htmlspecialchars($item['unit'], ENT_QUOTES) ?>">
                                        <?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_property_no" class="form-label">Property No.</label>
                            <input type="text" class="form-control" id="edit_property_no" name="property_no">
                        </div>
                        <div class="col-md-3">
                            <label for="edit_asset_tag" class="form-label">Asset Tag</label>
                            <input type="text" class="form-control" id="edit_asset_tag" name="asset_tag">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_property_qty" class="form-label">On Record Qty</label>
                            <input type="number" class="form-control" id="edit_property_qty" name="property_card_qty" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_physical_qty" class="form-label">Physical Qty</label>
                            <input type="number" class="form-control" id="edit_physical_qty" name="physical_qty" min="0" required>
                            <small class="text-gray-500 d-block">Variance: <span id="edit_variance_preview">0</span></small>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_cost" class="form-label">Cost</label>
                            <input type="number" class="form-control" id="edit_cost" name="cost" min="0" step="0.01" required>
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
                <h5 class="modal-title" id="deleteItemLabel">Remove PPE Line</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Remove <span id="deleteItemLabelSpan" class="fw-semibold"></span> from this RPCPPE?</p>
                <form id="deleteItemForm" action="physical_ppe-actions.php?action=delete_item" method="post">
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
    const propertyQty = parseInt($('#' + prefix + '_property_qty').val(), 10) || 0;
    const physicalQty = parseInt($('#' + prefix + '_physical_qty').val(), 10) || 0;
    $('#' + prefix + '_variance_preview').text(physicalQty - propertyQty);
}

$(document).ready(function() {
    new DataTable('#rpcppeItemsTable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        order: [[0, 'asc']],
        columnDefs: [{
            orderable: false,
            targets: [1, 2, 7, 8]
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

    $('#add_property_qty').on('input', function() {
        updateVariancePreview('add');
    });

    $('#add_physical_qty').on('input', function() {
        updateVariancePreview('add');
    });

    $('#edit_property_qty').on('input', function() {
        updateVariancePreview('edit');
    });

    $('#edit_physical_qty').on('input', function() {
        updateVariancePreview('edit');
    });

    $(document).on('click', '.edit-item-btn', function() {
        const btn = $(this);
        $('#edit_line_id').val(btn.data('id'));
        $('#edit_item_id').val(btn.data('item-id')).trigger('change');
        $('#edit_property_no').val(btn.data('property-no'));
        $('#edit_asset_tag').val(btn.data('asset-tag'));
        $('#edit_property_qty').val(btn.data('property-qty'));
        $('#edit_physical_qty').val(btn.data('physical-qty'));
        $('#edit_cost').val(btn.data('cost'));
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

    $('#addItemModal').on('shown.bs.modal', function() {
        updateVariancePreview('add');
    });
});
</script>

