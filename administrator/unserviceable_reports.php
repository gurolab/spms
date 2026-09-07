<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/UnserviceableProperty.model.php';

$pageTitle = 'Inventory & Inspection Report of Unserviceable Property';

$reportModel = new UnserviceablePropertyReport();
$reports = $reportModel->getAllWithSummary();

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();

$defaultReportNo = UnserviceablePropertyReport::getNextReportNo();
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
                    <i class="ph ph-plus-circle me-1"></i> New IIRUP
                </button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="iirupTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Report No.</th>
                            <th class="h6 text-gray-300">Date</th>
                            <th class="h6 text-gray-300">Entity / Office</th>
                            <th class="h6 text-gray-300">Fund Cluster</th>
                            <th class="h6 text-gray-300">Prepared By</th>
                            <th class="h6 text-gray-300">Inspected By</th>
                            <th class="h6 text-gray-300">Approved By</th>
                            <th class="h6 text-gray-300 text-center">Lines</th>
                            <th class="h6 text-gray-300 text-center">Total Qty</th>
                            <th class="h6 text-gray-300 text-center">Appraised Value</th>
                            <th class="h6 text-gray-300 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report) : ?>
                            <?php
                                $prepared = SearchById($users, $report['prepared_by'] ?? '');
                                $inspected = $report['inspected_by'] ? SearchById($users, $report['inspected_by']) : null;
                                $approved = $report['approved_by'] ? SearchById($users, $report['approved_by']) : null;
                            ?>
                            <tr>
                                <td class="text-gray-900 fw-semibold"><?= htmlspecialchars($report['report_no']) ?></td>
                                <td class="text-gray-900"><?= DisplayDate($report['report_date']) ?></td>
                                <td class="text-gray-900">
                                    <div class="d-flex flex-column">
                                        <span class="text-15 fw-medium"><?= htmlspecialchars($report['entity_name']) ?></span>
                                        <span class="text-gray-500 text-13"><?= htmlspecialchars($report['office']) ?></span>
                                    </div>
                                </td>
                                <td class="text-gray-900"><?= htmlspecialchars($report['fund_cluster'] ?? '-') ?></td>
                                <td class="text-gray-900">
                                    <div class="d-flex flex-column">
                                        <span class="text-15 fw-medium"><?= htmlspecialchars($prepared['fullname'] ?? 'Unknown') ?></span>
                                        <?php if (!empty($prepared['department_name'])) : ?>
                                            <span class="text-gray-500 text-13"><?= htmlspecialchars($prepared['department_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-gray-900">
                                    <?php if ($inspected) : ?>
                                        <div class="d-flex flex-column">
                                            <span class="text-15 fw-medium"><?= htmlspecialchars($inspected['fullname']) ?></span>
                                            <?php if (!empty($inspected['department_name'])) : ?>
                                                <span class="text-gray-500 text-13"><?= htmlspecialchars($inspected['department_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-gray-900">
                                    <?php if ($approved) : ?>
                                        <div class="d-flex flex-column">
                                            <span class="text-15 fw-medium"><?= htmlspecialchars($approved['fullname']) ?></span>
                                            <?php if (!empty($approved['department_name'])) : ?>
                                                <span class="text-gray-500 text-13"><?= htmlspecialchars($approved['department_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-gray-900 text-center"><?= (int)($report['total_items'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center"><?= (int)($report['total_quantity'] ?? 0) ?></td>
                                <td class="text-gray-900 text-center"><?= number_format((float)($report['total_appraised_value'] ?? 0), 2) ?></td>
                                <td class="text-gray-900 text-center">
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <a href="unserviceable_items.php?report_id=<?= (int)$report['id'] ?>"
                                           class="btn-action"
                                           aria-label="View items"
                                           data-bs-toggle="tooltip"
                                           title="View items">
                                            <i class="ph ph-list-bullets"></i>
                                        </a>
                                        <a href="print/iirup.php?id=<?= (int)$report['id'] ?>" target="_blank"
                                           class="btn-action"
                                           aria-label="Print IIRUP"
                                           data-bs-toggle="tooltip"
                                           title="Print IIRUP">
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
                                            data-entity="<?= htmlspecialchars($report['entity_name'], ENT_QUOTES) ?>"
                                            data-office="<?= htmlspecialchars($report['office'], ENT_QUOTES) ?>"
                                            data-fund-cluster="<?= htmlspecialchars($report['fund_cluster'] ?? '', ENT_QUOTES) ?>"
                                            data-prepared-by="<?= (int)$report['prepared_by'] ?>"
                                            data-inspected-by="<?= $report['inspected_by'] ? (int)$report['inspected_by'] : '' ?>"
                                            data-approved-by="<?= $report['approved_by'] ? (int)$report['approved_by'] : '' ?>"
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
                <h5 class="modal-title" id="addReportLabel">New IIRUP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addReportForm" action="unserviceable-actions.php?action=add_report" method="post">
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
                            <label for="add_entity_name" class="form-label">Entity Name</label>
                            <input type="text" class="form-control" id="add_entity_name" name="entity_name" placeholder="e.g. COTSU" required>
                        </div>
                        <div class="col-md-4">
                            <label for="add_office" class="form-label">Office / Division</label>
                            <input type="text" class="form-control" id="add_office" name="office" placeholder="e.g. Supply and Property Section" required>
                        </div>
                        <div class="col-md-4">
                            <label for="add_fund_cluster" class="form-label">Fund Cluster</label>
                            <input type="text" class="form-control" id="add_fund_cluster" name="fund_cluster" value="General Fund" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <label for="add_inspected_by" class="form-label">Inspected By</label>
                            <select class="form-select select2" id="add_inspected_by" name="inspected_by" data-placeholder="Select inspector (optional)">
                                <option value="">-- Optional --</option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= (int)$user['id'] ?>">
                                        <?= htmlspecialchars($user['fullname']) ?>
                                        <?= !empty($user['department_name']) ? ' - ' . htmlspecialchars($user['department_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="add_approved_by" class="form-label">Approved By</label>
                            <select class="form-select select2" id="add_approved_by" name="approved_by" data-placeholder="Select approver (optional)">
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
                        <div class="col-12">
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
                <h5 class="modal-title" id="editReportLabel">Update IIRUP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editReportForm" action="unserviceable-actions.php?action=update_report" method="post">
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
                            <label for="edit_entity_name" class="form-label">Entity Name</label>
                            <input type="text" class="form-control" id="edit_entity_name" name="entity_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_office" class="form-label">Office / Division</label>
                            <input type="text" class="form-control" id="edit_office" name="office" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_fund_cluster" class="form-label">Fund Cluster</label>
                            <input type="text" class="form-control" id="edit_fund_cluster" name="fund_cluster" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <label for="edit_inspected_by" class="form-label">Inspected By</label>
                            <select class="form-select select2" id="edit_inspected_by" name="inspected_by" data-placeholder="Select inspector (optional)">
                                <option value="">-- Optional --</option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= (int)$user['id'] ?>">
                                        <?= htmlspecialchars($user['fullname']) ?>
                                        <?= !empty($user['department_name']) ? ' - ' . htmlspecialchars($user['department_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_approved_by" class="form-label">Approved By</label>
                            <select class="form-select select2" id="edit_approved_by" name="approved_by" data-placeholder="Select approver (optional)">
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
                        <div class="col-12">
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
                <h5 class="modal-title" id="deleteReportLabel">Delete IIRUP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Removing <span id="deleteReportLabelSpan" class="fw-semibold"></span>. This will also remove the tagged unserviceable items.</p>
                <form id="deleteReportForm" action="unserviceable-actions.php?action=delete_report" method="post">
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
    new DataTable('#iirupTable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        order: [[1, 'desc']],
        columnDefs: [{
            orderable: false,
            targets: [2, 4, 5, 6, 10]
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
        $('#edit_entity_name').val(btn.data('entity'));
        $('#edit_office').val(btn.data('office'));
        $('#edit_fund_cluster').val(btn.data('fund-cluster') || 'General Fund');
        $('#edit_prepared_by').val(btn.data('prepared-by')).trigger('change');
        $('#edit_inspected_by').val(btn.data('inspected-by') || '').trigger('change');
        $('#edit_approved_by').val(btn.data('approved-by') || '').trigger('change');
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

