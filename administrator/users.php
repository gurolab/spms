<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/User.model.php';
require_once __DIR__ . '/../repo/Role.model.php';
require_once __DIR__ . '/../repo/Department.model.php';

$pageTitle = "Users";


$iBaseUser = new BaseModel("user_role_dept");
$list = $iBaseUser->getAll();
$iUser = new User;
$iRole = new Role;
$iDepartment = new Department;
$roles = $iRole->getAll();
$departments = $iDepartment->getAll();
$statusOptions = [
    'Active' => 'Active',
    'Inactive' => 'Inactive',
    'Pending' => 'Pending',
    'Suspended' => 'Suspended'
];


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
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="ph ph-plus-circle me-1"></i> Add User
                </button>
            </div>
            <!-- Breadcrumb Right End -->
        </div>


        <div class="card overflow-hidden" class="p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="usertable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Full Name</th>
                            <th class="h6 text-gray-300">Role</th>
                            <th class="h6 text-gray-300">Department</th>
                            <th class="h6 text-gray-300">Status</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r) : ?>
                        <tr>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= $r['fullname'] ?></span>
                                    <span class="text-gray-500 text-13"><?= $r['email'] ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= $r['rolename'] ?></td>
                            <td class="text-gray-900"><?= $r['departmentcode'] ?></td>
                            <td class="text-gray-900">
                                <span class="badge bg-success text-white"><?= $r['status'] ?></span>
                            </td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-action btn-action-primary edit-user-btn"
                                        type="button"
                                        aria-label="Edit user"
                                        data-bs-toggle="tooltip"
                                        title="Edit user"
                                        data-id="<?= $r['id'] ?>">
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <button class="btn-action btn-action-danger delete-user-btn"
                                        type="button"
                                        aria-label="Delete user"
                                        data-bs-toggle="tooltip"
                                        title="Delete user"
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
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" action="user-actions.php?action=add" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstname" name="firstname" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastname" name="lastname" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-control" id="role" name="roleid" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo $role['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-control" id="department" name="departmentid" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>"><?php echo $department['code']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmpassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmpassword" name="confirmpassword" required>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveUserBtn">Save User</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" action="user-actions.php?action=update" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="edit_firstname" name="firstname" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="edit_lastname" name="lastname" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-control" id="edit_role" name="roleid" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo $role['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-control" id="edit_department" name="departmentid" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>"><?php echo $department['code']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                        <div class="col-md-6">
                            <label for="confirmpassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="edit_confirmpassword" name="confirmpassword">
                        </div>
                    </div>
                    <input type="hidden" name="userid" id="edit_userid">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateUserBtn">Update User</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user? <br> This action cannot be undone.</p>
                <form id="deleteUserForm" action="user-actions.php?action=del" method="post">
                    <input type="hidden" name="userid" id="delete_userid" >
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete User</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>


<script>
    $(document).ready(function () {
        
        

        new DataTable('#usertable', {
            searching: true,
            lengthChange: false,
            info: true,
            paging: true,
            "columnDefs": [
                { "orderable": false, "targets": [0, 4] }
            ]
        });

        $('#exportOptions').on('change', function () {
            var format = $(this).val();
            if (format) {
                window.location.href = `export.php?format=${format}`;
            }
        });

        // Fetch roles and departments for dropdowns
        // $.ajax({
        //     url: 'fetch-dropdown-data.php',
        //     type: 'GET',
        //     dataType: 'json',
        //     success: function(data) {
        //         // Populate role dropdowns
        //         if (data.roles) {
        //             let roleOptions = '<option value="">Select Role</option>';
        //             data.roles.forEach(role => {
        //                 roleOptions += `<option value="${role.id}">${role.name}</option>`;
        //             });
        //             $('#role, #edit_role').html(roleOptions);
        //         }
                
        //         // Populate department dropdowns
        //         if (data.departments) {
        //             let deptOptions = '<option value="">Select Department</option>';
        //             data.departments.forEach(dept => {
        //                 deptOptions += `<option value="${dept.id}">${dept.code} - ${dept.name}</option>`;
        //             });
        //             $('#department, #edit_department').html(deptOptions);
        //         }
        //     }
        // });

        // Save new user
        $('#saveUserBtn').click(function() {
            $('#addUserForm').submit();
        });
        // Edit user button click
        $(document).on('click', '.edit-user-btn', function() {
            const userId = $(this).data('id');
            
            $.getJSON(baseUrl+"/api/admin.php", { action: 'getUserById', id: userId }, function (res) {
                if (res.error) {
                    alert(res.error);
                    return;
                }
                
                // Populate the edit form with user data
                $('#edit_userid').val(res.id);
                $('#edit_firstname').val(res.firstname);
                $('#edit_lastname').val(res.lastname);
                $('#edit_email').val(res.email);
                $('#edit_role').val(res.roleid);
                $('#edit_department').val(res.departmentid);
                $('#edit_status').val(res.status);
                
                $('#editUserModal').modal('show');
            });

            // $.post('https://cotsu.studies.ph/api/admin.php', { action: 'getUserById', id: userId }, function(res) {
            //     if (res.error) {
            //         alert(res.error);
            //         return;
            //     }
                
            //     // Populate the edit form with user data
            //     $('#edit_userid').val(res.id);
            //     $('#edit_firstname').val(res.firstname);
            //     $('#edit_lastname').val(res.lastname);
            //     $('#edit_email').val(res.email);
            //     $('#edit_role').val(res.roleid);
            //     $('#edit_department').val(res.departmentid);
            //     $('#edit_status').val(res.status);
                
            //     $('#editUserModal').modal('show');
            // }, 'json');
        });
        
        // Update user
        $('#updateUserBtn').click(function() {
            $('#editUserForm').submit();
        });
        
        // Delete user button click
        $(document).on('click', '.delete-user-btn', function() {
            const userId = $(this).data('id');
            $('#delete_userid').val(userId);
            $('#deleteUserModal').modal('show');
        });
        
        // Confirm delete
        $('#confirmDeleteBtn').click(function() {
            $('#deleteUserForm').submit();
        });
    });
</script>
