<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([2]); // Supply Officer
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/Supply_Issuance.model.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = "Issuance Items";

$issuance_id = isset($_GET['issuance_id']) ? trim((string)$_GET['issuance_id']) : '';
$issuanceModel = new Supply_Issuance();
$issuance = $issuance_id !== '' ? $issuanceModel->getById($issuance_id) : null;

if (!$issuance) {
    flash('danger', 'Issuance record not found.');
    redirect('issue_supplies.php');
    exit;
}

$risModel = new RIS();
$ris = $risModel->getById($issuance['ris_id']);

$risItemsModel = new RIS_Items();
$risItems = $risItemsModel->getAll($issuance['ris_id']);
$risItemMap = [];
foreach ($risItems as $entry) {
    $risItemMap[$entry['id']] = $entry;
}

$itemsRepo = new Item();
$items = $itemsRepo->getAll();
$itemMap = [];
foreach ($items as $item) {
    $itemMap[$item['id']] = $item;
}

$issuanceItemsModel = new Supply_Issuance_Items();
$issuanceItems = $issuanceItemsModel->getAll($issuance_id);

$canAddItems = $issuance['status'] !== 'Completed';

function computeOutstanding(array $risItem): int
{
    return max(0, (int)$risItem['qty_requested'] - (int)$risItem['qty_issued']);
}

?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">

        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="issue_supplies.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Issuances</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= htmlspecialchars($issuance['rsmi_no']) ?></span></li>
                </ul>
            </div>

            <div class="flex-align gap-8 flex-wrap">
                <a href="../administrator/print/rsmi.php?id=<?= (int)$issuance['id'] ?>" target="_blank"
                   class="btn-action"
                   aria-label="Print RSMI"
                   data-bs-toggle="tooltip"
                   title="Print RSMI">
                    <i class="ph ph-printer"></i>
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <h6 class="text-gray-500 text-uppercase mb-2">RSMI No.</h6>
                        <p class="text-gray-900 fw-medium mb-0"><?= htmlspecialchars($issuance['rsmi_no']) ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-gray-500 text-uppercase mb-2">RIS Reference</h6>
                        <p class="text-gray-900 fw-medium mb-0">
                            <?= $ris ? htmlspecialchars($ris['ris_no']) : '--' ?>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-gray-500 text-uppercase mb-2">Issuance Date</h6>
                        <p class="text-gray-900 fw-medium mb-0"><?= DisplayDate($issuance['issuance_date']) ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-gray-500 text-uppercase mb-2">Status</h6>
                        <p class="text-gray-900 fw-medium mb-0"><?= htmlspecialchars($issuance['status']) ?></p>
                    </div>
                </div>
            </div>
        </div>



        <?php if ($canAddItems): ?>
        <div class="card mb-4">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">Add Issued Item</h5>
            </div>
            <div class="card-body">
                <form action="../administrator/supply_issuance-actions.php?action=addissuanceitem" method="post" class="row g-3 align-items-end">
                    <input type="hidden" name="issuance_id" value="<?= $issuance_id ?>">
                    <div class="col-md-4">
                        <label for="requisition_item_id" class="form-label mb-1">RIS Item</label>
                        <select class="form-select" id="requisition_item_id" name="requisition_item_id" required>
                            <option value="" disabled selected>Select item from RIS</option>
                            <?php foreach ($risItems as $risItem): ?>
                                <?php 
                                    $itemInfo = $itemMap[$risItem['item_id']] ?? null;
                                    $outstanding = computeOutstanding($risItem);
                                    if ($outstanding <= 0) {
                                        continue;
                                    }
                                ?>
                                <option value="<?= $risItem['id'] ?>">
                                    <?= $itemInfo ? htmlspecialchars(formatItemSelectLabel($itemInfo), ENT_QUOTES, 'UTF-8') : 'N/A - Unknown Item - N/A' ?>
                                    (Outstanding <?= $outstanding ?> of <?= $risItem['qty_requested'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="qty_issued" class="form-label mb-1">Quantity</label>
                        <input type="number" class="form-control" id="qty_issued" name="qty_issued" min="1" required>
                    </div>
                    <div class="col-md-4">
                        <label for="remarks" class="form-label mb-1">Remarks (Optional)</label>
                        <input type="text" class="form-control" id="remarks" name="remarks">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add Item</button>
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
                            <th class="h6 text-gray-300">Item</th>
                            <th class="h6 text-gray-300">RIS Qty</th>
                            <th class="h6 text-gray-300">Issued Qty</th>
                            <th class="h6 text-gray-300">Remaining</th>
                            <th class="h6 text-gray-300">Remarks</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($issuanceItems)): ?>
                            <?php foreach ($issuanceItems as $row): ?>
                                <?php
                                    $itemInfo = $itemMap[$row['item_id']] ?? null;
                                    $risItem = $risItemMap[$row['requisition_item_id']] ?? null;
                                    $remaining = $risItem ? computeOutstanding($risItem) : 0;
                                ?>
                                <tr>
                                    <td class="text-gray-900">
                                        <div class="d-flex flex-column">
                                            <span class="text-15 fw-medium"><?= $itemInfo ? $itemInfo['description'] : 'Unknown Item' ?></span>
                                            <span class="text-gray-500 text-13"><?= $itemInfo ? $itemInfo['code'] : '' ?></span>
                                        </div>
                                    </td>
                                    <td class="text-gray-900"><?= $row['qty_requested'] ?></td>
                                    <td class="text-gray-900"><?= $row['qty_issued'] ?></td>
                                    <td class="text-gray-900"><?= $remaining ?></td>
                                    <td class="text-gray-900"><?= !empty($row['remarks']) ? htmlspecialchars($row['remarks']) : '--' ?></td>
                                    <td class="text-gray-900">
                                        <form action="../administrator/supply_issuance-actions.php?action=delissuanceitem" method="post" onsubmit="return confirm('Remove this issued item?');">
                                            <input type="hidden" name="issuance_id" value="<?= $issuance_id ?>">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
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
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function () {
    $('#dtable').DataTable({
        language: {
            emptyTable: 'No issued items yet.'
        }
    });

    const traverseSelect = $('#requisition_item_id');
    if (traverseSelect.length) {
        if (!traverseSelect.find('option[value=""]').length) {
            traverseSelect.prepend('<option value=""></option>');
        }
        traverseSelect.attr('data-placeholder', 'Select item from RIS').addClass('select2');
    }
    if (typeof initSelect2 === 'function') {
        initSelect2(document);
    }

});
</script>




