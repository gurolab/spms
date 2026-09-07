<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';

$pageTitle = 'Stock Cards';
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
                    <li><a href="dashboard.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></span></li>
                </ul>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="stockCardsTable" class="table table-striped table-auto-fit">
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
                        <?php if (empty($inventory)): ?>
                            <tr>
                                <td colspan="13" class="text-center text-gray-500">No stock card records available yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($inventory as $row): ?>
                            <?php
                                $current = (int)$row['current_balance'];
                                $reorderLevel = (int)$row['reorder_level'];
                                $safetyStock = (int)$row['safety_stock'];
                                $needsReorder = ($reorderLevel > 0 && $current <= $reorderLevel) || ($safetyStock > 0 && $current <= $safetyStock);
                                $statusBadge = $needsReorder ? '<span class="badge bg-danger">Reorder</span>' : '<span class="badge bg-success">Healthy</span>';
                            ?>
                            <tr>
                                <td class="text-gray-900 fw-medium"><?= htmlspecialchars((string)$row['code'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars((string)$row['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900"><?= htmlspecialchars((string)$row['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-gray-900 text-center"><?= (int)$row['stock_onhand'] ?></td>
                                <td class="text-gray-900 text-center"><?= $current ?></td>
                                <td class="text-gray-900 text-center"><?= $safetyStock ?></td>
                                <td class="text-gray-900 text-center"><?= $reorderLevel ?></td>
                                <td class="text-gray-900 text-center"><?= (int)$row['reorder_quantity'] ?></td>
                                <td class="text-gray-900 text-center"><?= $row['last_restocked_at'] ? DisplayDate((string)$row['last_restocked_at']) : '-' ?></td>
                                <td class="text-gray-900 text-center"><?= $row['last_issued_at'] ? DisplayDate((string)$row['last_issued_at']) : '-' ?></td>
                                <td><?= $statusBadge ?></td>
                                <td class="text-center">
                                    <div class="d-flex gap-2 flex-wrap justify-content-center">
                                        <a class="btn-action"
                                           href="stockcard.php?item_id=<?= (int)$row['item_id'] ?>"
                                           aria-label="Open stock card"
                                           data-bs-toggle="tooltip"
                                           title="Open stock card">
                                            <i class="ph ph-identification-card"></i>
                                        </a>
                                        <button class="btn-action btn-action-primary edit-level-btn"
                                            type="button"
                                            aria-label="Adjust levels"
                                            data-bs-toggle="tooltip"
                                            title="Adjust levels"
                                            data-item-id="<?= (int)$row['item_id'] ?>"
                                            data-item-code="<?= htmlspecialchars((string)$row['code'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-item-desc="<?= htmlspecialchars((string)$row['description'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-reorder-level="<?= $reorderLevel ?>"
                                            data-reorder-qty="<?= (int)$row['reorder_quantity'] ?>"
                                            data-safety-stock="<?= $safetyStock ?>">
                                            <i class="ph ph-sliders"></i>
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
            <p class="text-gray-300 text-13 fw-normal">&copy; Copyright COTSU <?= date('Y') ?>, All Rights Reserved</p>
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
                    <?= csrf_input(); ?>
                    <input type="hidden" name="item_id" id="modal_item_id">
                    <input type="hidden" name="action" value="updateLevels">
                    <input type="hidden" name="redirect" value="stock_cards.php">
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
$(document).ready(function () {
    const hasDataRows = $('#stockCardsTable tbody tr').find('td[colspan]').length === 0;
    if (hasDataRows) {
        new DataTable('#stockCardsTable', {
            searching: true,
            lengthChange: false,
            info: true,
            paging: true,
            autoWidth: false,
            order: [[0, 'asc']]
        });
    }

    $(document).on('click', '.edit-level-btn', function () {
        const itemId = $(this).data('item-id');
        const info = $(this).data('item-code') + ' - ' + $(this).data('item-desc');
        $('#modal_item_id').val(itemId);
        $('#modal_item_info').val(info);
        $('#modal_reorder_level').val($(this).data('reorder-level'));
        $('#modal_reorder_qty').val($(this).data('reorder-qty'));
        $('#modal_safety_stock').val($(this).data('safety-stock'));
        $('#editLevelModal').modal('show');
    });

    $('#saveLevelBtn').on('click', function () {
        $('#levelForm').submit();
    });
});
</script>
