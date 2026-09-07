<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([2]); // Supply Officer
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/Supply_Receive.model.php';
require_once __DIR__ . '/../repo/User.model.php';
require_once __DIR__ . '/../repo/Supplier.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = "Supply Receive Items";

$iRef1 = new Supply_Receive_Header();
$supply_receive_header = $iRef1->getAll();

$iRef2 = new Item();
$items = $iRef2->getAll();

$iModel1 = new Supply_Receive_Items();

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

                <select class="form-select select2" id="top_receipt_header_id" name="receipt_header_id" data-placeholder="Select receipt">
                    <option value="" disabled <?= !getData('receipt_header_id') ? 'selected' : '' ?>>Select Receipt No</option>
                    <?php foreach ($supply_receive_header as $header) : ?>
                    <option value="<?= $header['id'] ?>" <?= (getData('receipt_header_id') == $header['id']) ? 'selected' : '' ?>>
                        <?= $header['receipt_no'] ?> - <?= DisplayDate($header['receipt_date']) ?>
                    </option>
                    <?php endforeach; ?>

                </select>
            </div>
            
            <!-- Breadcrumb Right End -->
        </div>

        <!-- instead of modal use in line above this table to add items -->
        <!-- Inline Add Supply Item Form -->
        <div class="card mb-4">
            <div class="card-body">

                <form id="inlineAddItemForm" action="../administrator/supply_receive-actions.php?action=addreceiveitem" method="post"
                    class="row g-3 align-items-end"><input type="hidden" name="receipt_header_id"
                        value="<?= isset($_GET['receipt_header_id']) ? $_GET['receipt_header_id'] : '' ?>">
                    <div class="col-md-4">
                        <label for="inline_item_id" class="form-label mb-1">Item</label>
                        <select class="form-select select2" id="inline_item_id" name="item_id" data-placeholder="Select item" required>
                            <option value="" disabled selected>Select Item</option>
                            <?php foreach ($items as $item) : ?>
                            <option value="<?= $item['id'] ?>"><?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label for="inline_qty_received" class="form-label mb-1">Qty Received</label>
                        <input type="number" class="form-control" id="inline_qty_received" name="qty_received" min="1"
                            required>
                    </div>
                    <div class="col-md-2">
                        <label for="inline_unit_cost" class="form-label mb-1">Unit Cost</label>
                        <input type="number" class="form-control" id="inline_unit_cost" name="unit_cost" min="0" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="col-md-1">
                        <label for="inline_batch_no" class="form-label mb-1">Batch No</label>
                        <input type="number" class="form-control" id="inline_batch_no" name="batch_no">
                    </div>
                    <div class="col-md-1">
                        <label for="inline_expiry_date" class="form-label mb-1">Expiry Date</label>
                        <input type="date" class="form-control" id="inline_expiry_date" name="expiry_date">
                    </div>
                    <div class="col-md-1">
                        <label for="inline_remarks" class="form-label mb-1">Remarks</label>
                        <input type="text" class="form-control" id="inline_remarks" name="remarks">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </form>
            </div>
        </div>



        <div class="card overflow-hidden p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Items</th>
                            <th class="h6 text-gray-300">Receive QTY</th>
                            <th class="h6 text-gray-300">Unit Cost</th>
                            <th class="h6 text-gray-300">Batch No</th>
                            <th class="h6 text-gray-300">Expiry Date</th>
                            <th class="h6 text-gray-300">Remarks</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            if (isset($_GET['receipt_header_id']) && !empty($_GET['receipt_header_id'])) {
                                $list = $iModel1->getAll($_GET['receipt_header_id']);
                            } else {
                                $list = [];
                            }
                        ?>
                        <?php if (!empty($list)) : ?>
                        <?php foreach ($list as $r) : ?>
                        <tr>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?=SearchById($items,$r['item_id'])['description']; ?></span>
                                    <span class="text-gray-500 text-13"><?= SearchById($items,$r['item_id'])['code'];  ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= $r['qty_received'] ?></td>
                            <td class="text-gray-900"><?= number_format((float)($r['unit_cost'] ?? 0), 2) ?></td>
                            <td class="text-gray-900"><?= $r['batch_no'] ?></td>
                            <td class="text-gray-900"><?= DisplayDate($r['expiry_date']) ?></td>
                            <td class="text-gray-900"><?= $r['remarks'] ?></td>
                            <td class="text-gray-900">
                                <form action="../administrator/supply_receive-actions.php?action=delreceiveitem" method="post" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                    <input type="hidden" name="receipt_header_id" value="<?= $_GET['receipt_header_id'] ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit"
                                            class="btn-action btn-action-danger"
                                            aria-label="Remove item"
                                            data-bs-toggle="tooltip"
                                            title="Remove item">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
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



<!-- Modal -->


<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>


<script>
$(document).ready(function() {

    var table = $('#dtable').DataTable({
        searching: false,
        lengthChange: false,
        info: false,
        paging: false,
        "columnDefs": [{
            "orderable": true
        }]
    });

    // Listen for changes on the select element
    $('#top_receipt_header_id').on('change', function() {
        var selectedId = $(this).val();
        if (selectedId) {
            // Reload the page with the selected ID as a query parameter
            window.location.href = 'supply_receive_items.php?receipt_header_id=' + selectedId;
        }
    });

});
</script>


