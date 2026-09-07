<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';

$pageTitle = 'Inventory Balance';

$inventory = StockInventory::getAllSummary();

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
                    <li><span class="text-main-600 fw-normal text-15"><?= $pageTitle ?></span></li>
                </ul>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="inventoryTable" class="table table-striped table-auto-fit">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Code</th>
                            <th class="h6 text-gray-300">Description</th>
                            <th class="h6 text-gray-300">Category</th>
                            <th class="h6 text-gray-300">Unit</th>
                            <th class="h6 text-gray-300">On Hand</th>
                            <th class="h6 text-gray-300">Current Balance</th>
                            <th class="h6 text-gray-300">Safety Stock</th>
                            <th class="h6 text-gray-300">Reorder Level</th>
                            <th class="h6 text-gray-300">Reorder Qty</th>
                            <th class="h6 text-gray-300">Last Restocked</th>
                            <th class="h6 text-gray-300">Last Issued</th>
                            <th class="h6 text-gray-300">Status</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $row): ?>
                            <?php
                                $current = (int)$row['current_balance'];
                                $reorderLevel = (int)$row['reorder_level'];
                                $safetyStock = (int)$row['safety_stock'];
                                if ($safetyStock > 0 && $current <= $safetyStock) {
                                    $statusBadge = '<span class="badge bg-danger">Critical</span>';
                                } elseif ($reorderLevel > 0 && $current <= $reorderLevel) {
                                    $statusBadge = '<span class="badge bg-warning text-dark">Reorder</span>';
                                } else {
                                    $statusBadge = '<span class="badge bg-success">Healthy</span>';
                                }
                            ?>
                            <tr>
                                <td class="text-gray-900 fw-medium"><?= htmlspecialchars($row['code']) ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($row['description']) ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($row['category']) ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars($row['unit']) ?></td>
                                <td class="text-gray-900 text-center"><?= (int)$row['stock_onhand'] ?></td>
                                <td class="text-gray-900 text-center"><?= $current ?></td>
                                <td class="text-gray-900 text-center"><?= $safetyStock ?></td>
                                <td class="text-gray-900 text-center"><?= $reorderLevel ?></td>
                                <td class="text-gray-900 text-center"><?= (int)$row['reorder_quantity'] ?></td>
                                <td class="text-gray-900 text-center"><?= $row['last_restocked_at'] ? DisplayDate($row['last_restocked_at']) : '—' ?></td>
                                <td class="text-gray-900 text-center"><?= $row['last_issued_at'] ? DisplayDate($row['last_issued_at']) : '—' ?></td>
                                <td><?= $statusBadge ?></td>
                                <td class="text-center">
                                    <button class="btn-action btn-action-primary edit-level-btn"
                                        type="button"
                                        aria-label="Adjust levels"
                                        data-bs-toggle="tooltip"
                                        title="Adjust levels"
                                        data-item-id="<?= $row['item_id'] ?>"
                                        data-item-code="<?= htmlspecialchars($row['code']) ?>"
                                        data-item-desc="<?= htmlspecialchars($row['description']) ?>"
                                        data-reorder-level="<?= $reorderLevel ?>"
                                        data-reorder-qty="<?= (int)$row['reorder_quantity'] ?>"
                                        data-safety-stock="<?= $safetyStock ?>">
                                        <i class="ph ph-sliders"></i>
                                    </button>
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

<div class="modal fade" id="editLevelModal" tabindex="-1" aria-labelledby="editLevelLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLevelLabel">Adjust Reorder Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="stock_inventory-actions.php?action=updateLevels" method="post" id="levelForm">
                    <input type="hidden" name="item_id" id="modal_item_id">
                    <input type="hidden" name="action" value="updateLevels">
                    <input type="hidden" name="redirect" value="inventory_balance.php">
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <input type="text" class="form-control" id="modal_item_info" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="modal_reorder_level" class="form-label">Reorder Level</label>
                        <input type="number" class="form-control" id="modal_reorder_level" name="reorder_level" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_reorder_qty" class="form-label">Reorder Quantity</label>
                        <input type="number" class="form-control" id="modal_reorder_qty" name="reorder_quantity" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_safety_stock" class="form-label">Safety Stock</label>
                        <input type="number" class="form-control" id="modal_safety_stock" name="safety_stock" min="0" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveLevelBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function() {
    new DataTable('#inventoryTable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        autoWidth: false,
        order: [[0, 'asc']]
    });

    $(document).on('click', '.edit-level-btn', function() {
        const itemId = $(this).data('item-id');
        const info = $(this).data('item-code') + ' - ' + $(this).data('item-desc');
        $('#modal_item_id').val(itemId);
        $('#modal_item_info').val(info);
        $('#modal_reorder_level').val($(this).data('reorder-level'));
        $('#modal_reorder_qty').val($(this).data('reorder-qty'));
        $('#modal_safety_stock').val($(this).data('safety-stock'));
        $('#editLevelModal').modal('show');
    });

    $('#saveLevelBtn').on('click', function() {
        $('#levelForm').submit();
    });
});
</script>
