<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/Supplier.model.php';

$pageTitle = "Suppliers";

$iSupplier = new Supplier();
$list = $iSupplier->getAll();


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
                    <i class="ph ph-plus-circle me-1"></i> Add Supplier
                </button>
            </div>
            <!-- Breadcrumb Right End -->
        </div>


        <div class="card overflow-hidden" class="p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr> 
                            <th class="h6 text-gray-300">Name</th>
                            <th class="h6 text-gray-300">Number</th>
                            <th class="h6 text-gray-300">Email</th>
                            <th class="h6 text-gray-300">TIN</th>
                            <th class="h6 text-gray-300">Address</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r) : ?>
                        <tr>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= $r['contact_person'] ?></span>
                                    <span class="text-gray-500 text-13"><?= $r['name'] ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= $r['contact_number'] ?></td>
                            <td class="text-gray-900"><?= $r['email'] ?></td>
                            <td class="text-gray-900"><?= $r['tin'] ?></td>
                            <td class="text-gray-900"><?= $r['address'] ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-action btn-action-primary edit-btn"
                                        type="button"
                                        aria-label="Edit supplier"
                                        data-bs-toggle="tooltip"
                                        title="Edit supplier"
                                        data-id="<?= $r['id'] ?>">
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <button class="btn-action btn-action-danger delete-btn"
                                        type="button"
                                        aria-label="Delete supplier"
                                        data-bs-toggle="tooltip"
                                        title="Delete supplier"
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
                <h5 class="modal-title" id="addUserModalLabel">Add New Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addForm" action="supplier-actions.php?action=add" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="company" class="form-label">Company</label>
                            <input type="text" class="form-control" id="company" name="company" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="contactperson" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contactperson" name="contactperson" required>
                        </div>
                        <div class="col-md-6">
                            <label for="contactnumber" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contactnumber" name="contactnumber" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="tin" class="form-label">TIN</label>
                            <input type="text" class="form-control" id="tin" name="tin" required>
                        </div>
                        <div class="col-md-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
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
                <h5 class="modal-title" id="editModalLabel">Edit Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" action="supplier-actions.php?action=update" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="company" class="form-label">Company</label>
                            <input type="text" class="form-control" id="edit_company" name="company" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="contactperson" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="edit_contactperson" name="contactperson" required>
                        </div>
                        <div class="col-md-6">
                            <label for="contactnumber" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="edit_contactnumber" name="contactnumber" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="tin" class="form-label">TIN</label>
                            <input type="text" class="form-control" id="edit_tin" name="tin" required>
                        </div>
                        <div class="col-md-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2" required></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="supplierid" id="edit_supplierid">

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
                <form id="deleteForm" action="supplier-actions.php?action=del" method="post">
                    <input type="hidden" name="supplierid" id="delete_id" >
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
    $(document).ready(function () {
        // Existing DataTable code
        new DataTable('#dtable', {
            searching: true,
            lengthChange: false,
            info: true,
            paging: true,
            "columnDefs": [
                { "orderable": false, "targets": [0, 5] }
            ]
        });

        $('#exportOptions').on('change', function () {
            var format = $(this).val();
            if (format) {
                window.location.href = `export.php?format=${format}`;
            }
        });

        // Save new user
        $('#saveBtn').click(function() {
            $('#addForm').submit();
        });
        
        // Edit user button click
        $(document).on('click', '.edit-btn', function() {
            const Id = $(this).data('id');
            
            $.getJSON(baseUrl+"/api/admin.php", { action: 'getSupplierById', id: Id }, function (res) {
                if (res.error) {
                    alert(res.error);
                    return;
                }
                
                // Populate the edit form with user data
                $('#edit_supplierid').val(res.id);
                $('#edit_company').val(res.name);
                $('#edit_contactperson').val(res.contact_person);
                $('#edit_contactnumber').val(res.contact_number);
                $('#edit_email').val(res.email);
                $('#edit_tin').val(res.tin);
                $('#edit_address').val(res.address);
                
                $('#editModal').modal('show');
            });

        });
        
        // Update user
        $('#updateBtn').click(function() {
            $('#editForm').submit();
        });
        
        // Delete user button click
        $(document).on('click', '.delete-btn', function() {
            const userId = $(this).data('id');
            $('#delete_id').val(userId);
            $('#deleteModal').modal('show');
        });
        
        // Confirm delete
        $('#confirmDeleteBtn').click(function() {
            $('#deleteForm').submit();
        });
    });
</script>
