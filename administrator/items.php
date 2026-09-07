<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = "Items";

$iModel = new Item();
$list = $iModel->getAll();

$typeOptions = [ 'Supply', 'Equipment', 'Raw Material', 'Finished Good', 'Packaging', 'Property', 'Semi-expendable' ];

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
                    <i class="ph ph-plus-circle me-1"></i> Add Item
                </button>
            </div>
            <!-- Breadcrumb Right End -->
        </div>


        <div class="card overflow-hidden" class="p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Code</th>
                            <th class="h6 text-gray-300">Category</th>
                            <th class="h6 text-gray-300">Unit</th>
                            <th class="h6 text-gray-300">Cost</th>
                            <th class="h6 text-gray-300">On Hand</th>
                            <th class="h6 text-gray-300">Type</th>
                            <th class="h6 text-gray-300">Consumable</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r) : ?>
                        <tr>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= $r['code'] ?></span>
                                    <span class="text-gray-500 text-13"><?= $r['description'] ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= $r['category'] ?></td>
                            <td class="text-gray-900"><?= $r['unit'] ?></td>
                            <td class="text-gray-900"><?= $r['unit_cost'] ?></td>
                            <td class="text-gray-900"><?= $r['stock_onhand'] ?></td>
                            <td class="text-gray-900"><?= $r['type'] ?></td>
                            <td class="text-gray-900">
                                <?php if ($r['is_consumable']): ?>
                                <span class="badge bg-success">Yes</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-action btn-action-primary edit-btn"
                                        type="button"
                                        aria-label="Edit item"
                                        data-bs-toggle="tooltip"
                                        title="Edit item"
                                        data-id="<?= $r['id'] ?>">
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <button class="btn-action btn-action-danger delete-btn"
                                        type="button"
                                        aria-label="Delete item"
                                        data-bs-toggle="tooltip"
                                        title="Delete item"
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
                <h5 class="modal-title" id="addUserModalLabel">Add New Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addForm" action="item-actions.php?action=add" method="post">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="code" class="form-label">Item Code</label>
                            <input type="text" class="form-control" id="code" name="code" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="category" name="category" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="unit" class="form-label">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit" required>
                        </div>
                        <div class="col-md-6">
                            <label for="unit_cost" class="form-label">Unit Cost</label>
                            <input type="number" step="0.01" class="form-control" id="unit_cost" name="unit_cost"
                                required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="stock_onhand" class="form-label">Stock On Hand</label>
                            <input type="number" class="form-control" id="stock_onhand" name="stock_onhand" required>
                        </div>
                        <div class="col-md-6">
                            <label for="type" class="form-label">Type</label>
                        <select class="form-select select2" id="type" name="type" data-placeholder="Select item type" required>
                                <option value="" disabled selected>Select Type</option>
                                <?php foreach ($typeOptions as $option): ?>
                                <option value="<?= $option ?>"><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_consumable" name="is_consumable"
                                    value="1">
                                <label class="form-check-label" for="is_consumable">
                                    Is Consumable
                                </label>
                            </div>
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

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" action="item-actions.php?action=update" method="post">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="edit_description" name="description" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="code" class="form-label">Item Code</label>
                            <input type="text" class="form-control" id="edit_code" name="code" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="edit_category" name="category" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="unit" class="form-label">Unit</label>
                            <input type="text" class="form-control" id="edit_unit" name="unit" required>
                        </div>
                        <div class="col-md-6">
                            <label for="unit_cost" class="form-label">Unit Cost</label>
                            <input type="number" step="0.01" class="form-control" id="edit_unit_cost" name="unit_cost" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="stock_onhand" class="form-label">Stock On Hand</label>
                            <input type="number" class="form-control" id="edit_stock_onhand" name="stock_onhand" required>
                        </div>
                        <div class="col-md-6">
                            <label for="type" class="form-label">Type</label>
                        <select class="form-select select2" id="edit_type" name="type" data-placeholder="Select item type" required>
                                <option value="" disabled>Select Type</option>
                                <?php foreach ($typeOptions as $option): ?>
                                <option value="<?= $option ?>"><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_consumable" name="is_consumable" value="1">
                                <label class="form-check-label" for="edit_is_consumable">
                                    Is Consumable
                                </label>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="itemid" id="edit_itemid">

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
                <form id="deleteForm" action="item-actions.php?action=del" method="post">
                    <input type="hidden" name="itemid" id="delete_id">
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
            "targets": [0, 5]
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

    // Edit user button click - USE EVENT DELEGATION
    $(document).on('click', '.edit-btn', function() {
        const Id = $(this).data('id');

        $.getJSON(baseUrl+"/api/admin.php", {
            action: 'getItemById',
            id: Id
        }, function(res) {
            if (res.error) {
                alert(res.error);
                return;
            }

            // Populate the edit form with user data
            $('#edit_itemid').val(res.id);
            $('#edit_description').val(res.description);
            $('#edit_code').val(res.code);
            $('#edit_category').val(res.category);
            $('#edit_unit').val(res.unit);
            $('#edit_unit_cost').val(res.unit_cost);
            $('#edit_stock_onhand').val(res.stock_onhand);
            $('#edit_type').val(res.type).trigger('change');
            $('#edit_is_consumable').prop('checked', res.is_consumable == 1);

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





