<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = "Request Supply Items";

$iRef1 = new RIS();
$RISList = $iRef1->getAll();

$activeRISId = isset($_GET['ris_id']) ? trim((string)$_GET['ris_id']) : '';
$selectedRIS = null;
if ($activeRISId !== '') {
    foreach ($RISList as $candidate) {
        if ((string)$candidate['id'] === $activeRISId) {
            $selectedRIS = $candidate;
            break;
        }
    }
}
$selectedStatus = $selectedRIS['status'] ?? null;
$canAddItems = !in_array($selectedStatus, ['Approved', 'Completed'], true);

$iRef2 = new Item();
$items = $iRef2->getAll();

$iModel1 = new RIS_Items();

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

                <select class="form-select select2" id="top_ris_id" name="top_ris_id" data-placeholder="Select Request No">
                    <option value="" disabled <?= !getData('ris_id') ? 'selected' : '' ?>>Select Request No</option>
                    <?php foreach ($RISList as $ris) : ?>
                    <option value="<?= $ris['id'] ?>" <?= (getData('ris_id') == $ris['id']) ? 'selected' : '' ?>>
                        <?= $ris['ris_no'] ?> - <?= DisplayDate($ris['requisition_date']) ?>
                    </option>
                    <?php endforeach; ?>

                </select>
                <?php if ($selectedRIS): ?>
                <a href="print/ris.php?id=<?= (int)$selectedRIS['id'] ?>"
                   target="_blank"
                   class="btn-action"
                   aria-label="Print RIS"
                   data-bs-toggle="tooltip"
                   title="Print RIS">
                    <i class="ph ph-printer"></i>
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Breadcrumb Right End -->
        </div>

        <?php if ($selectedRIS): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <h6 class="text-gray-500 text-uppercase mb-2">RIS No.</h6>
                        <p class="text-gray-900 fw-medium mb-0"><?= htmlspecialchars($selectedRIS['ris_no'] ?? '') ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-gray-500 text-uppercase mb-2">Status</h6>
                        <p class="text-gray-900 fw-medium mb-0"><?= htmlspecialchars($selectedRIS['status'] ?? '') ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-gray-500 text-uppercase mb-2">Requested Date</h6>
                        <p class="text-gray-900 fw-medium mb-0"><?= DisplayDate($selectedRIS['requisition_date'] ?? '') ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-gray-500 text-uppercase mb-2">Purpose</h6>
                        <p class="text-gray-900 fw-medium mb-0"><?= htmlspecialchars($selectedRIS['purpose'] ?? '') ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canAddItems): ?>
        <!-- Inline Add Supply Item Form -->
        <div class="card mb-4">
            <div class="card-body">

                <form id="inlineAddItemForm" action="ris-actions.php?action=addrisitem" method="post"
                    class="row g-3 align-items-end">
                    <input type="hidden" name="ris_id" value="<?= isset($_GET['ris_id']) ? $_GET['ris_id'] : '' ?>">
                    <div class="col-md-6">
                        <label for="inline_item_id" class="form-label mb-1">Item</label>
                        <select class="form-select select2" id="inline_item_id" name="item_id" data-placeholder="Select item" required>
                            <option value="" disabled selected>Select Item</option>
                            <?php foreach ($items as $item) : ?>
                            <option value="<?= $item['id'] ?>"><?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="inline_qty_requested" class="form-label mb-1">Qty Requested</label>
                        <input type="number" class="form-control" id="inline_qty_requested" name="qty_requested" min="1"
                            required>
                    </div>
                    <div class="col-md-3">
                        <label for="inline_remarks" class="form-label mb-1">Remarks</label>
                        <input type="text" class="form-control" id="inline_remarks" name="remarks">
                    </div>

                    
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>

                </form>


            </div>
        </div>
        <?php endif; ?>



        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Items</th>
                            <th class="h6 text-gray-300">Request QTY</th>
                            <th class="h6 text-gray-300">Issued Qty</th>
                            <th class="h6 text-gray-300">Remarks</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            if (isset($_GET['ris_id']) && !empty($_GET['ris_id'])) {
                                $list = $iModel1->getAll($_GET['ris_id']);
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
                            <td class="text-gray-900"><?= $r['qty_requested'] ?></td>
                            <td class="text-gray-900"><?= $r['qty_issued'] ?></td>
                            <td class="text-gray-900"><?= !empty($r['remarks']) ? htmlspecialchars($r['remarks']) : '--' ?></td>
                            <td class="text-gray-900">
                                <?php if ($canAddItems) : ?>
                                <form action="ris-actions.php?action=delrisitem" method="post" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                    <input type="hidden" name="ris_id" value="<?= $_GET['ris_id'] ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-danger py-3 px-3 text-sm">Remove</button>
                                </form>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white">Locked</span>
                                <?php endif; ?>
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
        language: {
            emptyTable: 'No items recorded yet.'
        },
        order: [[0, 'asc']]
    });

    // Listen for changes on the select element
    $('#top_ris_id').on('change', function() {
        var selectedId = $(this).val();
        if (selectedId) {
            // Reload the page with the selected ID as a query parameter
            window.location.href = 'ris_items.php?ris_id=' + selectedId;
        }
    });

});
</script>
