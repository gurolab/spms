<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/Department.model.php';

$pageTitle = "Departments";

$iDept = new Department();
$isSystemAdmin = ((int)(getUser('roleid') ?? 0) === ROLE_SYSTEM_ADMIN);
$requestedAction = isPost() ? action() : '';

if (($requestedAction === 'add' || $requestedAction === 'update') && !$isSystemAdmin) {
    writeAuditLog('department.modify.denied', 'department', null, [
        'reason' => 'forbidden_role',
        'action' => $requestedAction
    ]);
    flash('danger', 'Only System Admin can modify departments.');
    redirect('departments.php');
    exit;
}

if ($requestedAction === 'add') {
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));

    if ($code === '' || $name === '') {
        writeAuditLog('department.create.denied', 'department', null, [
            'reason' => 'required_fields_missing',
            'code' => $code,
            'name' => $name
        ]);
        flash('danger', 'Department code and name are required.');
        redirect('departments.php');
        exit;
    }

    if (!isValidLength($code, 2, 30)) {
        writeAuditLog('department.create.denied', 'department', null, [
            'reason' => 'invalid_code_length',
            'code' => $code
        ]);
        flash('danger', 'Department code must be between 2 and 30 characters.');
        redirect('departments.php');
        exit;
    }

    if (!isValidLength($name, 2, 150)) {
        writeAuditLog('department.create.denied', 'department', null, [
            'reason' => 'invalid_name_length',
            'name' => $name
        ]);
        flash('danger', 'Department name must be between 2 and 150 characters.');
        redirect('departments.php');
        exit;
    }

    if ($iDept->isCodeExists($code)) {
        writeAuditLog('department.create.denied', 'department', null, [
            'reason' => 'duplicate_code',
            'code' => $code
        ]);
        flash('danger', 'Department code already exists.');
        redirect('departments.php');
        exit;
    }

    if ($iDept->isNameExists($name)) {
        writeAuditLog('department.create.denied', 'department', null, [
            'reason' => 'duplicate_name',
            'name' => $name
        ]);
        flash('danger', 'Department name already exists.');
        redirect('departments.php');
        exit;
    }

    try {
        $newDepartmentId = $iDept->add($code, $name);
    } catch (Throwable $e) {
        writeAuditLog('department.create.failed', 'department', null, [
            'code' => $code,
            'name' => $name,
            'reason' => 'db_exception'
        ]);
        flash('danger', 'Failed to add department.');
        redirect('departments.php');
        exit;
    }

    if ($newDepartmentId) {
        writeAuditLog('department.create.success', 'department', (int)$newDepartmentId, [
            'code' => $code,
            'name' => $name
        ]);
        flash('success', 'Department added successfully.');
    } else {
        writeAuditLog('department.create.failed', 'department', null, [
            'code' => $code,
            'name' => $name
        ]);
        flash('danger', 'Failed to add department.');
    }

    redirect('departments.php');
    exit;
}

if ($requestedAction === 'update') {
    $departmentId = (int)($_POST['department_id'] ?? 0);
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));

    if ($departmentId <= 0) {
        writeAuditLog('department.update.denied', 'department', null, [
            'reason' => 'missing_department_id'
        ]);
        flash('danger', 'Department ID is required.');
        redirect('departments.php');
        exit;
    }

    $existingDepartment = $iDept->getById($departmentId);
    if (!$existingDepartment) {
        writeAuditLog('department.update.denied', 'department', $departmentId, [
            'reason' => 'department_not_found'
        ]);
        flash('danger', 'Department not found.');
        redirect('departments.php');
        exit;
    }

    if ($code === '' || $name === '') {
        writeAuditLog('department.update.denied', 'department', $departmentId, [
            'reason' => 'required_fields_missing',
            'code' => $code,
            'name' => $name
        ]);
        flash('danger', 'Department code and name are required.');
        redirect('departments.php');
        exit;
    }

    if (!isValidLength($code, 2, 30)) {
        writeAuditLog('department.update.denied', 'department', $departmentId, [
            'reason' => 'invalid_code_length',
            'code' => $code
        ]);
        flash('danger', 'Department code must be between 2 and 30 characters.');
        redirect('departments.php');
        exit;
    }

    if (!isValidLength($name, 2, 150)) {
        writeAuditLog('department.update.denied', 'department', $departmentId, [
            'reason' => 'invalid_name_length',
            'name' => $name
        ]);
        flash('danger', 'Department name must be between 2 and 150 characters.');
        redirect('departments.php');
        exit;
    }

    if ($iDept->isCodeExistsExceptId($code, $departmentId)) {
        writeAuditLog('department.update.denied', 'department', $departmentId, [
            'reason' => 'duplicate_code',
            'code' => $code
        ]);
        flash('danger', 'Department code already exists.');
        redirect('departments.php');
        exit;
    }

    if ($iDept->isNameExistsExceptId($name, $departmentId)) {
        writeAuditLog('department.update.denied', 'department', $departmentId, [
            'reason' => 'duplicate_name',
            'name' => $name
        ]);
        flash('danger', 'Department name already exists.');
        redirect('departments.php');
        exit;
    }

    $changes = [];
    if ($code !== (string)($existingDepartment['code'] ?? '')) {
        $changes['code'] = [
            'old' => $existingDepartment['code'] ?? null,
            'new' => $code
        ];
    }
    if ($name !== (string)($existingDepartment['name'] ?? '')) {
        $changes['name'] = [
            'old' => $existingDepartment['name'] ?? null,
            'new' => $name
        ];
    }

    try {
        $updated = $iDept->updateById($departmentId, $code, $name);
    } catch (Throwable $e) {
        writeAuditLog('department.update.failed', 'department', $departmentId, [
            'code' => $code,
            'name' => $name,
            'reason' => 'db_exception'
        ]);
        flash('danger', 'Failed to update department.');
        redirect('departments.php');
        exit;
    }

    if ($updated) {
        writeAuditLog('department.update.success', 'department', $departmentId, [
            'code' => $code,
            'name' => $name,
            'changes' => $changes
        ]);
        flash('success', 'Department updated successfully.');
    } else {
        writeAuditLog('department.update.failed', 'department', $departmentId, [
            'code' => $code,
            'name' => $name
        ]);
        flash('danger', 'Failed to update department.');
    }

    redirect('departments.php');
    exit;
}

$list = $iDept->getAll();

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
                <?php if ($isSystemAdmin) : ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                        <i class="ph ph-plus-circle me-1"></i> Add Department
                    </button>
                <?php endif; ?>
            </div>
            <!-- Breadcrumb Right End -->
        </div>

        <div class="card overflow-hidden" class="p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Code</th>
                            <th class="h6 text-gray-300">Name</th>
                            <?php if ($isSystemAdmin) : ?>
                                <th class="h6 text-gray-300">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r) : ?>
                        <tr>
                            <td class="text-gray-900"><?= htmlspecialchars($r['code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-gray-900"><?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <?php if ($isSystemAdmin) : ?>
                                <td class="text-gray-900">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button"
                                            class="btn-action btn-action-primary edit-department-btn"
                                            aria-label="Edit department"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editDepartmentModal"
                                            title="Edit department"
                                            data-id="<?= (int)($r['id'] ?? 0) ?>"
                                            data-code="<?= htmlspecialchars($r['code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-name="<?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="ph ph-pencil-line"></i>
                                        </button>
                                    </div>
                                </td>
                            <?php endif; ?>
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

<?php if ($isSystemAdmin) : ?>
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDepartmentModalLabel">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addDepartmentForm" action="departments.php?action=add" method="post">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label for="add_department_code" class="form-label">Department Code</label>
                        <input type="text" class="form-control text-uppercase" id="add_department_code" name="code" maxlength="30" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_department_name" class="form-label">Department Name</label>
                        <input type="text" class="form-control" id="add_department_name" name="name" maxlength="150" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveDepartmentBtn">Save Department</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isSystemAdmin) : ?>
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editDepartmentModalLabel">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editDepartmentForm" action="departments.php?action=update" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="department_id" id="edit_department_id">
                    <div class="mb-3">
                        <label for="edit_department_code" class="form-label">Department Code</label>
                        <input type="text" class="form-control text-uppercase" id="edit_department_code" name="code" maxlength="30" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_department_name" class="form-label">Department Name</label>
                        <input type="text" class="form-control" id="edit_department_name" name="name" maxlength="150" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateDepartmentBtn">Update Department</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>



<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
    $(document).ready(function () {
        const hasActionColumn = <?= $isSystemAdmin ? 'true' : 'false' ?>;
        const columnDefs = hasActionColumn ? [{ orderable: false, targets: [2] }] : [];

        new DataTable('#dtable', {
            searching: true,
            lengthChange: false,
            info: true,
            paging: true,
            columnDefs: columnDefs
        });

        $('#exportOptions').on('change', function () {
            var format = $(this).val();
            if (format) {
                window.location.href = `export.php?format=${format}`;
            }
        });

        const saveDepartmentBtn = document.getElementById('saveDepartmentBtn');
        if (saveDepartmentBtn) {
            saveDepartmentBtn.addEventListener('click', function () {
                document.getElementById('addDepartmentForm').submit();
            });
        }

        const departmentCodeInput = document.getElementById('add_department_code');
        if (departmentCodeInput) {
            departmentCodeInput.addEventListener('input', function () {
                this.value = this.value.toUpperCase();
            });
        }

        const updateDepartmentBtn = document.getElementById('updateDepartmentBtn');
        if (updateDepartmentBtn) {
            updateDepartmentBtn.addEventListener('click', function () {
                document.getElementById('editDepartmentForm').submit();
            });
        }

        const editDepartmentCodeInput = document.getElementById('edit_department_code');
        if (editDepartmentCodeInput) {
            editDepartmentCodeInput.addEventListener('input', function () {
                this.value = this.value.toUpperCase();
            });
        }

        document.querySelectorAll('.edit-department-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('edit_department_id').value = this.dataset.id || '';
                document.getElementById('edit_department_code').value = (this.dataset.code || '').toUpperCase();
                document.getElementById('edit_department_name').value = this.dataset.name || '';
            });
        });

    });
</script>
