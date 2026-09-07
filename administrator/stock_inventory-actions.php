<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$action = action();

if (isPost() && $action === 'updateLevels') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $reorderLevel = (int)($_POST['reorder_level'] ?? 0);
    $reorderQty = (int)($_POST['reorder_quantity'] ?? 0);
    $safetyStock = (int)($_POST['safety_stock'] ?? 0);
    $redirect = $_POST['redirect'] ?? 'inventory_balance.php';

    if ($itemId <= 0) {
        flash('danger', 'Item reference is required.');
        redirect($redirect);
        exit;
    }

    if (!Item::existsById($itemId)) {
        flash('danger', 'Selected item was not found.');
        redirect($redirect);
        exit;
    }

    $updated = StockInventory::updateLevels($itemId, max(0, $reorderLevel), max(0, $reorderQty), max(0, $safetyStock));
    StockInventory::refreshFromItem($itemId);

    if ($updated) {
        flash('success', 'Reorder settings updated.');
    } else {
        flash('danger', 'Unable to update reorder settings.');
    }
    redirect($redirect);
    exit;
}

flash('danger', 'Unsupported action.');
redirect('inventory_balance.php');

?>
