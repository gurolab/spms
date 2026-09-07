<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/UnserviceableProperty.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$reportId = (int)($_GET['report_id'] ?? 0);
if ($reportId <= 0) {
    flash('danger', 'IIRUP reference is required.');
    redirect('unserviceable_reports.php');
}

$reportModel = new UnserviceablePropertyReport();
$report = $reportModel->getById($reportId);
if (!$report) {
    flash('danger', 'IIRUP not found.');
    redirect('unserviceable_reports.php');
}

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();
$prepared = SearchById($users, $report['prepared_by'] ?? '');
$inspected = $report['inspected_by'] ? SearchById($users, $report['inspected_by']) : null;
$approved = $report['approved_by'] ? SearchById($users, $report['approved_by']) : null;

$itemRepo = new Item();
$itemsMaster = $itemRepo->getAll();

$itemModel = new UnserviceablePropertyItem();
$lineItems = $itemModel->getAllByReport($reportId);

$disposalOptions = [
    'Sale at Public Auction',
    'Transfer to Other Government Agency',
    'Donation',
    'Disposal Through Destruction',
    'Recycle / For Parts',
    'Return to Supplier',
    'Other'
];

$totals = [
    'lines' => count($lineItems),
    'quantity' => 0,
    'appraised_value' => 0.0
];

foreach ($lineItems as $line) {
    $totals['quantity'] += (int)($line['quantity'] ?? 0);
    $totals['appraised_value'] += (float)($line['appraised_value'] ?? 0);
}

$pageTitle = 'Unserviceable Property Items';

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
                    <li><a href="unserviceable_reports.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Reports &amp; Compliance</a></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= $pageTitle ?></span></li>
                </ul>
            </div>

            <div class="flex-align gap-8 flex-wrap">
                <a href="unserviceable_reports.php" class="btn btn-outline-secondary">
                    <i class="ph ph-arrow-left me-1"></i> Back to IIRUP
                </a>
                <a href="print/iirup.php?id=<?= (int)$report['id'] ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="ph ph-printer me-1"></i> Print IIRUP
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
                            <span class="text-gray-500 text-uppercase text-12">Entity</span>
                            <p class="mb-0 text-16 fw-semibold"><?= htmlspecialchars($report['entity_name']) ?></p>
                            <span class="text-gray-500 text-13"><?= htmlspecialchars($report['office']) ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Total Lines</span>
                            <p class="mb-0 text-16 fw-semibold"><?= $totals['lines'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Prepared By</span>
                            <p class="mb-0 text-16 fw-semibold"><?= htmlspecialchars($prepared['fullname'] ?? 'Unknown') ?></p>
                            <?php if (!empty($prepared['department_name'])) : ?>
                                <span class="text-gray-500 text-13"><?= htmlspecialchars($prepared['department_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Inspected By</span>
                            <?php if ($inspected) : ?>
                                <p class="mb-0 text-16 fw-semibold"><?= htmlspecialchars($inspected['fullname']) ?></p>
                                <?php if (!empty($inspected['department_name'])) : ?>
                                    <span class="text-gray-500 text-13"><?= htmlspecialchars($inspected['department_name']) ?></span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="badge bg-secondary">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Approved By</span>
                            <?php if ($approved) : ?>
                                <p class="mb-0 text-16 fw-semibold"><?= htmlspecialchars($approved['fullname']) ?></p>
                                <?php if (!empty($approved['department_name'])) : ?>
                                    <span class="text-gray-500 text-13"><?= htmlspecialchars($approved['department_name']) ?></span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="badge bg-secondary">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Total Quantity</span>
                            <p class="mb-0 text-16 fw-semibold"><?= $totals['quantity'] ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <span class="text-gray-500 text-uppercase text-12">Total Appraised Value</span>
                            <p class="mb-0 text-16 fw-semibold"><?= number_format($totals['appraised_value'], 2) ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
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
                <table id="iirupItemsTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Item</th>
                            <th class="h6 text-gray-300 text-center">Date Acquired</th>
                            <th class="h6 text-gray-300">Property No.</th>
                            <th class="h6 text-gray-300 text-center">Quantity</th>
                            <th class="h6 text-gray-300 text-center">Unit Cost</th>
                            <th class="h6 text-gray-300 text-center">Total Cost</th>
                            <th class="h6 text-gray-300 text-center">Accumulated Depreciation</th>
                            <th class="h6 text-gray-300 text-center">Accumulated Impairment</th>
                            <th class="h6 text-gray-300 text-center">Carrying Amount</th>
                            <th class="h6 text-gray-300 text-center">Appraised Value</th>
                            <th class="h6 text-gray-300">Disposal</th>
                            <th class="h6 text-gray-300">OR No.</th>
                            <th class="h6 text-gray-300 text-center">Sales Amount</th>
                            <th class="h6 text-gray-300">Remarks</th>
                            <th class="h6 text-gray-300 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lineItems as $line) : ?>
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
                                <td class="text-gray-900 text-center">
                                    <?= !empty($line['date_acquired']) ? DisplayDate($line['date_acquired']) : '-' ?>
                                </td>
                                <td class="text-gray-900"><?= htmlspecialchars($line['property_no'] ?? '-') ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($line['quantity'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['unit_cost'] ?? 0), 2) ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['total_cost'] ?? 0), 2) ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['accumulated_depreciation'] ?? 0), 2) ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['accumulated_impairment_loss'] ?? 0), 2) ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['carrying_amount'] ?? 0), 2) ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['appraised_value'] ?? 0), 2) ?></td>
                                <td class="text-gray-900">
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($line['disposal_method'] ?? '') ?></span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php if ((int)($line['disposal_sale'] ?? 0) === 1) : ?><span class="badge bg-success">Sale</span><?php endif; ?>
                                            <?php if ((int)($line['disposal_transfer'] ?? 0) === 1) : ?><span class="badge bg-primary">Transfer</span><?php endif; ?>
                                            <?php if ((int)($line['disposal_destruction'] ?? 0) === 1) : ?><span class="badge bg-danger">Destruction</span><?php endif; ?>
                                            <?php if (!empty($line['disposal_other'])) : ?><span class="badge bg-secondary"><?= htmlspecialchars($line['disposal_other']) ?></span><?php endif; ?>
                                        </div>
                                        <span class="text-gray-500 text-12">Total: <?= number_format((float)($line['disposal_total'] ?? 0), 2) ?></span>
                                    </div>
                                </td>
                                <td class="text-gray-900"><?= htmlspecialchars($line['or_no'] ?? '-') ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($line['sales_amount'] ?? 0), 2) ?></td>
                                <td class="text-gray-900">
                                    <div class="d-flex flex-column">
                                        <span><?= htmlspecialchars($line['condition_notes'] ?? '') ?></span>
                                        <?php if (!empty($line['remarks'])) : ?>
                                            <span class="text-gray-500 text-12"><?= htmlspecialchars($line['remarks']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-gray-900 text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-primary edit-item-btn"
                                            data-id="<?= (int)$line['id'] ?>"
                                            data-item-id="<?= (int)$line['item_id'] ?>"
                                            data-property-no="<?= htmlspecialchars($line['property_no'] ?? '', ENT_QUOTES) ?>"
                                            data-date-acquired="<?= htmlspecialchars($line['date_acquired'] ?? '', ENT_QUOTES) ?>"
                                            data-quantity="<?= (int)$line['quantity'] ?>"
                                            data-unit-cost="<?= number_format((float)($line['unit_cost'] ?? 0), 2, '.', '') ?>"
                                            data-total-cost="<?= number_format((float)($line['total_cost'] ?? 0), 2, '.', '') ?>"
                                            data-accumulated-depreciation="<?= number_format((float)($line['accumulated_depreciation'] ?? 0), 2, '.', '') ?>"
                                            data-accumulated-impairment-loss="<?= number_format((float)($line['accumulated_impairment_loss'] ?? 0), 2, '.', '') ?>"
                                            data-carrying-amount="<?= number_format((float)($line['carrying_amount'] ?? 0), 2, '.', '') ?>"
                                            data-appraised-value="<?= number_format((float)($line['appraised_value'] ?? 0), 2, '.', '') ?>"
                                            data-condition-notes="<?= htmlspecialchars($line['condition_notes'] ?? '', ENT_QUOTES) ?>"
                                            data-disposal-method="<?= htmlspecialchars($line['disposal_method'] ?? '', ENT_QUOTES) ?>"
                                            data-disposal-sale="<?= (int)($line['disposal_sale'] ?? 0) ?>"
                                            data-disposal-transfer="<?= (int)($line['disposal_transfer'] ?? 0) ?>"
                                            data-disposal-destruction="<?= (int)($line['disposal_destruction'] ?? 0) ?>"
                                            data-disposal-other="<?= htmlspecialchars($line['disposal_other'] ?? '', ENT_QUOTES) ?>"
                                            data-disposal-total="<?= number_format((float)($line['disposal_total'] ?? 0), 2, '.', '') ?>"
                                            data-or-no="<?= htmlspecialchars($line['or_no'] ?? '', ENT_QUOTES) ?>"
                                            data-sales-amount="<?= number_format((float)($line['sales_amount'] ?? 0), 2, '.', '') ?>"
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
                <h5 class="modal-title" id="addItemLabel">Add Unserviceable Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addItemForm" action="unserviceable-actions.php?action=add_item" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="add_item_id" name="item_id" data-placeholder="Select item" required>
                                <option value="" disabled selected>Select item</option>
                                <?php foreach ($itemsMaster as $item) : ?>
                                    <option value="<?= (int)$item['id'] ?>"
                                        data-code="<?= htmlspecialchars($item['code'], ENT_QUOTES) ?>">
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
                            <label for="add_date_acquired" class="form-label">Date Acquired</label>
                            <input type="date" class="form-control" id="add_date_acquired" name="date_acquired">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="add_quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="add_quantity" name="quantity" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="add_unit_cost" class="form-label">Unit Cost</label>
                            <input type="number" class="form-control" id="add_unit_cost" name="unit_cost" min="0" step="0.01" value="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label for="add_total_cost" class="form-label">Total Cost</label>
                            <input type="number" class="form-control" id="add_total_cost" name="total_cost" min="0" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label for="add_appraised_value" class="form-label">Appraised Value</label>
                            <input type="number" class="form-control" id="add_appraised_value" name="appraised_value" min="0" step="0.01" value="0.00" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="add_accumulated_depreciation" class="form-label">Accumulated Depreciation</label>
                            <input type="number" class="form-control" id="add_accumulated_depreciation" name="accumulated_depreciation" min="0" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label for="add_accumulated_impairment_loss" class="form-label">Accumulated Impairment Loss</label>
                            <input type="number" class="form-control" id="add_accumulated_impairment_loss" name="accumulated_impairment_loss" min="0" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label for="add_carrying_amount" class="form-label">Carrying Amount</label>
                            <input type="number" class="form-control" id="add_carrying_amount" name="carrying_amount" min="0" step="0.01" value="0.00">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_disposal_method" class="form-label">Recommended Disposal Method</label>
                            <select class="form-select" id="add_disposal_method" name="disposal_method" required>
                                <option value="" disabled selected>Select method</option>
                                <?php foreach ($disposalOptions as $method) : ?>
                                    <option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_disposal_other" class="form-label">Disposal Other (Specify)</label>
                            <input type="text" class="form-control" id="add_disposal_other" name="disposal_other" placeholder="Optional">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_disposal_sale" name="disposal_sale">
                                    <label class="form-check-label" for="add_disposal_sale">Sale</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_disposal_transfer" name="disposal_transfer">
                                    <label class="form-check-label" for="add_disposal_transfer">Transfer</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_disposal_destruction" name="disposal_destruction">
                                    <label class="form-check-label" for="add_disposal_destruction">Destruction</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="add_disposal_total" class="form-label">Disposal Total</label>
                            <input type="number" class="form-control" id="add_disposal_total" name="disposal_total" min="0" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label for="add_or_no" class="form-label">OR No.</label>
                            <input type="text" class="form-control" id="add_or_no" name="or_no" placeholder="Optional">
                        </div>
                        <div class="col-md-3">
                            <label for="add_sales_amount" class="form-label">Sales Amount</label>
                            <input type="number" class="form-control" id="add_sales_amount" name="sales_amount" min="0" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label for="add_condition_notes" class="form-label">Condition Notes</label>
                            <input type="text" class="form-control" id="add_condition_notes" name="condition_notes" placeholder="Optional">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="add_item_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="add_item_remarks" name="remarks" rows="3" placeholder="Additional notes (optional)"></textarea>
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
                <h5 class="modal-title" id="editItemLabel">Update Unserviceable Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editItemForm" action="unserviceable-actions.php?action=update_item" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" id="edit_line_id">
                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_item_id" class="form-label">Item</label>
                            <select class="form-select select2" id="edit_item_id" name="item_id" data-placeholder="Select item" required>
                                <?php foreach ($itemsMaster as $item) : ?>
                                    <option value="<?= (int)$item['id'] ?>">
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
                            <label for="edit_date_acquired" class="form-label">Date Acquired</label>
                            <input type="date" class="form-control" id="edit_date_acquired" name="date_acquired">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="edit_quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_unit_cost" class="form-label">Unit Cost</label>
                            <input type="number" class="form-control" id="edit_unit_cost" name="unit_cost" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_total_cost" class="form-label">Total Cost</label>
                            <input type="number" class="form-control" id="edit_total_cost" name="total_cost" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label for="edit_appraised_value" class="form-label">Appraised Value</label>
                            <input type="number" class="form-control" id="edit_appraised_value" name="appraised_value" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_accumulated_depreciation" class="form-label">Accumulated Depreciation</label>
                            <input type="number" class="form-control" id="edit_accumulated_depreciation" name="accumulated_depreciation" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_accumulated_impairment_loss" class="form-label">Accumulated Impairment Loss</label>
                            <input type="number" class="form-control" id="edit_accumulated_impairment_loss" name="accumulated_impairment_loss" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_carrying_amount" class="form-label">Carrying Amount</label>
                            <input type="number" class="form-control" id="edit_carrying_amount" name="carrying_amount" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_disposal_method" class="form-label">Recommended Disposal Method</label>
                            <select class="form-select" id="edit_disposal_method" name="disposal_method" required>
                                <option value="">Select method</option>
                                <?php foreach ($disposalOptions as $method) : ?>
                                    <option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_disposal_other" class="form-label">Disposal Other (Specify)</label>
                            <input type="text" class="form-control" id="edit_disposal_other" name="disposal_other">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_disposal_sale" name="disposal_sale">
                                    <label class="form-check-label" for="edit_disposal_sale">Sale</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_disposal_transfer" name="disposal_transfer">
                                    <label class="form-check-label" for="edit_disposal_transfer">Transfer</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_disposal_destruction" name="disposal_destruction">
                                    <label class="form-check-label" for="edit_disposal_destruction">Destruction</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="edit_disposal_total" class="form-label">Disposal Total</label>
                            <input type="number" class="form-control" id="edit_disposal_total" name="disposal_total" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label for="edit_or_no" class="form-label">OR No.</label>
                            <input type="text" class="form-control" id="edit_or_no" name="or_no">
                        </div>
                        <div class="col-md-3">
                            <label for="edit_sales_amount" class="form-label">Sales Amount</label>
                            <input type="number" class="form-control" id="edit_sales_amount" name="sales_amount" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label for="edit_condition_notes" class="form-label">Condition Notes</label>
                            <input type="text" class="form-control" id="edit_condition_notes" name="condition_notes">
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
                <h5 class="modal-title" id="deleteItemLabel">Remove Unserviceable Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Remove <span id="deleteItemLabelSpan" class="fw-semibold"></span> from this IIRUP?</p>
                <form id="deleteItemForm" action="unserviceable-actions.php?action=delete_item" method="post">
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
function recalcIirupFinancials(prefix) {
    const qty = parseFloat($('#' + prefix + '_quantity').val()) || 0;
    const unitCost = parseFloat($('#' + prefix + '_unit_cost').val()) || 0;
    const dep = parseFloat($('#' + prefix + '_accumulated_depreciation').val()) || 0;
    const impairment = parseFloat($('#' + prefix + '_accumulated_impairment_loss').val()) || 0;

    const totalCost = qty * unitCost;
    const carrying = totalCost - dep - impairment;

    $('#' + prefix + '_total_cost').val(totalCost.toFixed(2));
    $('#' + prefix + '_carrying_amount').val(Math.max(carrying, 0).toFixed(2));
}

$(document).ready(function() {
    new DataTable('#iirupItemsTable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        order: [[0, 'asc']],
        columnDefs: [{
            orderable: false,
            targets: [10, 13, 14]
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

    $(document).on('click', '.edit-item-btn', function() {
        const btn = $(this);
        $('#edit_line_id').val(btn.data('id'));
        $('#edit_item_id').val(btn.data('item-id')).trigger('change');
        $('#edit_property_no').val(btn.data('property-no'));
        $('#edit_date_acquired').val(btn.data('date-acquired'));
        $('#edit_quantity').val(btn.data('quantity'));
        $('#edit_unit_cost').val(btn.data('unit-cost'));
        $('#edit_total_cost').val(btn.data('total-cost'));
        $('#edit_accumulated_depreciation').val(btn.data('accumulated-depreciation'));
        $('#edit_accumulated_impairment_loss').val(btn.data('accumulated-impairment-loss'));
        $('#edit_carrying_amount').val(btn.data('carrying-amount'));
        $('#edit_appraised_value').val(btn.data('appraised-value'));
        $('#edit_condition_notes').val(btn.data('condition-notes'));
        $('#edit_disposal_method').val(btn.data('disposal-method'));
        $('#edit_disposal_sale').prop('checked', parseInt(btn.data('disposal-sale'), 10) === 1);
        $('#edit_disposal_transfer').prop('checked', parseInt(btn.data('disposal-transfer'), 10) === 1);
        $('#edit_disposal_destruction').prop('checked', parseInt(btn.data('disposal-destruction'), 10) === 1);
        $('#edit_disposal_other').val(btn.data('disposal-other'));
        $('#edit_disposal_total').val(btn.data('disposal-total'));
        $('#edit_or_no').val(btn.data('or-no'));
        $('#edit_sales_amount').val(btn.data('sales-amount'));
        $('#edit_item_remarks').val(btn.data('remarks'));
        $('#editItemModal').modal('show');
    });

    $('#add_quantity, #add_unit_cost, #add_accumulated_depreciation, #add_accumulated_impairment_loss').on('input', function () {
        recalcIirupFinancials('add');
    });

    $('#edit_quantity, #edit_unit_cost, #edit_accumulated_depreciation, #edit_accumulated_impairment_loss').on('input', function () {
        recalcIirupFinancials('edit');
    });

    $(document).on('click', '.delete-item-btn', function() {
        const btn = $(this);
        $('#delete_line_id').val(btn.data('id'));
        $('#deleteItemLabelSpan').text(btn.data('label'));
        $('#deleteItemModal').modal('show');
    });
});
</script>

