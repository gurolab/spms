<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/User.model.php';
require_once __DIR__ . '/../repo/Role.model.php';
require_once __DIR__ . '/../repo/Department.model.php';

$pageTitle = "Roles";

$iRole = new Role(); 
$list = $iRole->getAll(); 

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

            </div>
            <!-- Breadcrumb Right End -->
        </div>

        <div class="card overflow-hidden" class="p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="roletable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r) : ?>
                        <tr>
                            <td class="text-gray-900"><?= $r['name'] ?></td>
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




<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
    $(document).ready(function () {
        // Existing DataTable code
        new DataTable('#roletable', {
            searching: true,
            lengthChange: false,
            info: true,
            paging: true,
            "columnDefs": [
                { "orderable": false, "targets": [0, 0] }
            ]
        });

        $('#exportOptions').on('change', function () {
            var format = $(this).val();
            if (format) {
                window.location.href = `export.php?format=${format}`;
            }
        });


    });
</script>
