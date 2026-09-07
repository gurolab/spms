<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/PhysicalInventory.model.php';

$pageTitle = 'Report on Physical Count of Inventories';

$reportModel = new PhysicalInventoryReport();
$reports = $reportModel->getAllWithSummary();

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();

$defaultReportNo = PhysicalInventoryReport::getNextReportNo();
$defaultReportDate = date('Y-m-d');
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
                    <li><span class="text-main-600 fw-normal text-15">Reports &amp; Compliance</span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= $pageTitle ?></span></li>
                </ul>
            </div>

            <div class="flex-align gap-8 flex-wrap">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReportModal">
                    <i class="ph ph-plus-circle me-1"></i> New RPCI
                </button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="rcciTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Report No.</th>
                            <th class="h6 text-gray-300">Report Date</th>
                            <th class="h6 text-gray-300">Fund / Type</th>
                            <th class="h6 text-gray-300">Prepared By</th>
                            <th class="h6 text-gray-300">Verified By</th>
                            <th class="h6 text-gray-300">Location</th>
                            <th class="h6 text-gray-300 text-center">Lines</th>
                            <th class="h6 text-gray-300 text-center">System Qty</th>
                            <th class="h6 text-gray-300 text-center">Counted Qty</th>
                            <th class="h6 text-gray-300 text-center">Variance Qty</th>
                            <th class="h6 text-gray-300 text-center">Variance Value</th>
                            <th class="h6 text-gray-300 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report) : ?>
                            <?php
                                $prepared = SearchById($users, $report['prepared_by'] ?? '');
                                $verified = $report['verified_by'] ? SearchById($users, $report['verified_by']) : null;
                                $varianceQty = (int)($report['total_variance_qty'] ?? 0);
                                $varianceValue = (float)($report['total_variance_value'] ?? 0);
                                $varianceClass = $varianceQty === 0 ? 'badge bg-success text-dark' : ($varianceQty > 0 ? 'badge bg-warning text-dark' : 'badge bg-danger');
                            ?>
                            <tr>
                                <td class="text-gray-900 fw-semibold"><?= htmlspecialchars($report['report_no']) ?></td>
                                <td class="text-gray-900"><?= DisplayDate($report['report_date']) ?></td>
                                <td class="text-gray-900">
                                    <div class="d-flex flex-column">
                                        <span class="text-15 fw-medium"><?= htmlspecialchars($report['fund_cluster'] ?? 'General Fund') ?></span>
                                        <span class="text-gray-500 text-13">
                                            <?= htmlspecialchars($report['inventory_type'] ?? 'LOW/HIGH VALUE SEMI-EXPENDABLE PPE') ?>
                                        </span>
                                        <span class="text-gray-400 text-12">
                                            <?= htmlspecialchars($report['classification_code'] ?? 'CLASSIFICATION AND CODE') ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-gray-900">
                                    <div class="d-flex flex-column">
                                        <span class="text-15 fw-medium"><?= htmlspecialchars($prepared['fullname'] ?? 'Unknown') ?></span>
                                        <?php if (!empty($prepared['department_name'])) : ?>
                                            <span class="text-gray-500 text-13"><?= htmlspecialchars($prepared['department_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-gray-900">
                                    <?php if ($verified) : ?>
                                        <div class="d-flex flex-column">
                                            <span class="text-15 fw-medium"><?= htmlspecialchars($verified['fullname'] ?? '') ?></span>
                                            <?php if (!empty($verified['department_name'])) : ?>
                                                <span class="text-gray-500 text-13"><?= htmlspecialchars($verified['department_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-gray-900"><?= htmlspecialchars($report['location'] ?? '-') ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($report['total_items'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($report['total_system_qty'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($report['total_counted_qty'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center">
                                    <span class="<?= $varianceClass ?>"><?= $varianceQty ?></span>
                                </td>
                                <td class="text-gray-900 text-center"><?= number_format($varianceValue, 2) ?></td>
                                <td class="text-gray-900 text-center">
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <a href="physical_inventory_items.php?report_id=<?= (int)$report['id'] ?>"
                                           class="btn-action"
                                           aria-label="View items"
                                           data-bs-toggle="tooltip"
                                           title="View items">
                                            <i class="ph ph-list-bullets"></i>
                                        </a>
                                        <a href="print/rpci.php?id=<?= (int)$report['id'] ?>" target="_blank"
                                           class="btn-action"
                                           aria-label="Print RPCI"
                                           data-bs-toggle="tooltip"
                                           title="Print RPCI">
                                            <i class="ph ph-printer"></i>
                                        </a>
                                        <button type="button"
                                            class="btn-action btn-action-primary edit-report-btn"
                                            aria-label="Edit report"
                                            data-bs-toggle="tooltip"
                                            title="Edit report"
                                            data-id="<?= (int)$report['id'] ?>"
                                            data-report-no="<?= htmlspecialchars($report['report_no'], ENT_QUOTES) ?>"
                                            data-report-date="<?= htmlspecialchars($report['report_date'], ENT_QUOTES) ?>"
                                            data-fund-cluster="<?= htmlspecialchars($report['fund_cluster'] ?? '', ENT_QUOTES) ?>"
                                            data-inventory-type="<?= htmlspecialchars($report['inventory_type'] ?? '', ENT_QUOTES) ?>"
                                            data-classification-code="<?= htmlspecialchars($report['classification_code'] ?? '', ENT_QUOTES) ?>"
                                            data-prepared-by="<?= (int)$report['prepared_by'] ?>"
                                            data-verified-by="<?= $report['verified_by'] ? (int)$report['verified_by'] : '' ?>"
                                            data-location="<?= htmlspecialchars($report['location'] ?? '', ENT_QUOTES) ?>"
                                            data-remarks="<?= htmlspecialchars($report['remarks'] ?? '', ENT_QUOTES) ?>">
                                            <i class="ph ph-pencil-line"></i>
                                        </button>
                                        <button type="button"
                                            class="btn-action btn-action-danger delete-report-btn"
                                            aria-label="Delete report"
                                            data-bs-toggle="tooltip"
                                            title="Delete report"
                                            data-id="<?= (int)$report['id'] ?>"
                                            data-label="<?= htmlspecialchars($report['report_no'], ENT_QUOTES) ?>">
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

<!-- Add Report Modal -->
<div class="modal fade" id="addReportModal" tabindex="-1" aria-labelledby="addReportLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addReportLabel">New RPCI</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addReportForm" action="physical_inventory-actions.php?action=add_report" method="post">
                    <?= csrf_input() ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_report_no" class="form-label">Report No.</label>
                            <input type="text" class="form-control" id="add_report_no" name="report_no" value="<?= htmlspecialchars($defaultReportNo) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_report_date" class="form-label">Report Date</label>
                            <input type="date" class="form-control" id="add_report_date" name="report_date" value="<?= htmlspecialchars($defaultReportDate) ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="add_fund_cluster" class="form-label">Fund Cluster</label>
                            <input type="text" class="form-control" id="add_fund_cluster" name="fund_cluster" value="General Fund" required>
                        </div>
                        <div class="col-md-4">
                            <label for="add_inventory_type" class="form-label">Inventory Type</label>
                            <select class="form-select" id="add_inventory_type" name="inventory_type" required>
                                <option value="LOW VALUE SEMI-EXPENDABLE PPE">LOW VALUE SEMI-EXPENDABLE PPE</option>
                                <option value="HIGH VALUE SEMI-EXPENDABLE PPE">HIGH VALUE SEMI-EXPENDABLE PPE</option>
                                <option value="LOW/HIGH VALUE SEMI-EXPENDABLE PPE">LOW/HIGH VALUE SEMI-EXPENDABLE PPE</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="add_classification_code" class="form-label">Classification and Code</label>
                            <input type="text" class="form-control" id="add_classification_code" name="classification_code" value="CLASSIFICATION AND CODE" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_prepared_by" class="form-label">Prepared By</label>
                            <select class="form-select select2" id="add_prepared_by" name="prepared_by" data-placeholder="Select preparer" required>
                                <option value="" disabled selected>Select preparer</option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= (int)$user['id'] ?>" <?= $currentUserId === (int)$user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['fullname']) ?>
                                        <?= !empty($user['department_name']) ? ' - ' . htmlspecialchars($user['department_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_verified_by" class="form-label">Verified By</label>
                            <select class="form-select select2" id="add_verified_by" name="verified_by" data-placeholder="Select verifier (optional)">
                                <option value="">-- Optional --</option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= (int)$user['id'] ?>">
                                        <?= htmlspecialchars($user['fullname']) ?>
                                        <?= !empty($user['department_name']) ? ' - ' . htmlspecialchars($user['department_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_location" class="form-label">Location / Warehouse</label>
                            <input type="text" class="form-control" id="add_location" name="location" placeholder="e.g. Main Store Room">
                        </div>
                        <div class="col-md-6">
                            <label for="add_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="add_remarks" name="remarks" rows="3" placeholder="Additional notes (optional)"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveReportBtn">Save Report</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Report Modal -->
<div class="modal fade" id="editReportModal" tabindex="-1" aria-labelledby="editReportLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editReportLabel">Update RPCI</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editReportForm" action="physical_inventory-actions.php?action=update_report" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_report_no" class="form-label">Report No.</label>
                            <input type="text" class="form-control" id="edit_report_no" name="report_no" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_report_date" class="form-label">Report Date</label>
                            <input type="date" class="form-control" id="edit_report_date" name="report_date" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_fund_cluster" class="form-label">Fund Cluster</label>
                            <input type="text" class="form-control" id="edit_fund_cluster" name="fund_cluster" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_inventory_type" class="form-label">Inventory Type</label>
                            <select class="form-select" id="edit_inventory_type" name="inventory_type" required>
                                <option value="LOW VALUE SEMI-EXPENDABLE PPE">LOW VALUE SEMI-EXPENDABLE PPE</option>
                                <option value="HIGH VALUE SEMI-EXPENDABLE PPE">HIGH VALUE SEMI-EXPENDABLE PPE</option>
                                <option value="LOW/HIGH VALUE SEMI-EXPENDABLE PPE">LOW/HIGH VALUE SEMI-EXPENDABLE PPE</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_classification_code" class="form-label">Classification and Code</label>
                            <input type="text" class="form-control" id="edit_classification_code" name="classification_code" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_prepared_by" class="form-label">Prepared By</label>
                            <select class="form-select select2" id="edit_prepared_by" name="prepared_by" data-placeholder="Select preparer" required>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= (int)$user['id'] ?>">
                                        <?= htmlspecialchars($user['fullname']) ?>
                                        <?= !empty($user['department_name']) ? ' - ' . htmlspecialchars($user['department_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_verified_by" class="form-label">Verified By</label>
                            <select class="form-select select2" id="edit_verified_by" name="verified_by" data-placeholder="Select verifier (optional)">
                                <option value="">-- Optional --</option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= (int)$user['id'] ?>">
                                        <?= htmlspecialchars($user['fullname']) ?>
                                        <?= !empty($user['department_name']) ? ' - ' . htmlspecialchars($user['department_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_location" class="form-label">Location / Warehouse</label>
                            <input type="text" class="form-control" id="edit_location" name="location">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="edit_remarks" name="remarks" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateReportBtn">Update Report</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Report Modal -->
<div class="modal fade" id="deleteReportModal" tabindex="-1" aria-labelledby="deleteReportLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteReportLabel">Delete RPCI</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Removing <span id="deleteReportLabelSpan" class="fw-semibold"></span>. This will also remove the captured line items.</p>
                <form id="deleteReportForm" action="physical_inventory-actions.php?action=delete_report" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" id="delete_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteReportBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function() {
    new DataTable('#rcciTable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        order: [[1, 'desc']],
        columnDefs: [{
            orderable: false,
            targets: [5, 11]
        }]
    });

    $('[data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip(this);
    });

    $('#addReportModal .select2').select2({
        dropdownParent: $('#addReportModal')
    });

    $('#editReportModal .select2').select2({
        dropdownParent: $('#editReportModal')
    });

    $('#saveReportBtn').on('click', function() {
        $('#addReportForm').submit();
    });

    $('#updateReportBtn').on('click', function() {
        $('#editReportForm').submit();
    });

    $('#confirmDeleteReportBtn').on('click', function() {
        $('#deleteReportForm').submit();
    });

    $(document).on('click', '.edit-report-btn', function() {
        const btn = $(this);
        $('#edit_id').val(btn.data('id'));
        $('#edit_report_no').val(btn.data('report-no'));
        $('#edit_report_date').val(btn.data('report-date'));
        $('#edit_fund_cluster').val(btn.data('fund-cluster') || 'General Fund');
        $('#edit_inventory_type').val(btn.data('inventory-type') || 'LOW/HIGH VALUE SEMI-EXPENDABLE PPE');
        $('#edit_classification_code').val(btn.data('classification-code') || 'CLASSIFICATION AND CODE');
        $('#edit_prepared_by').val(btn.data('prepared-by')).trigger('change');
        $('#edit_verified_by').val(btn.data('verified-by') || '').trigger('change');
        $('#edit_location').val(btn.data('location'));
        $('#edit_remarks').val(btn.data('remarks'));
        $('#editReportModal').modal('show');
    });

    $(document).on('click', '.delete-report-btn', function() {
        const btn = $(this);
        $('#delete_id').val(btn.data('id'));
        $('#deleteReportLabelSpan').text(btn.data('label'));
        $('#deleteReportModal').modal('show');
    });
});
</script>

