<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/SupplyLedger.model.php';

$iModel = new Item();

$postadd = isPost() && action() === 'add';
$postupdate = isPost() && action() === 'update';
$postdelete = isPost() && action() === 'del';

function itemDependencyLabelMap(): array
{
    return [
        'requisition_items' => 'RIS items',
        'supply_receipt_items' => 'received supply lines',
        'supply_issuance_items' => 'issuance lines',
        'ics_items' => 'ICS items',
        'par_items' => 'PAR items',
        'physical_inventory_items' => 'RPCI items',
        'physical_ppe_items' => 'RPCPPE items',
        'unserviceable_property_items' => 'IIRUP items',
        'property_cards' => 'property cards',
        'property_transfers' => 'property transfers',
        'stock_transactions' => 'stock transactions',
        'supply_ledger_entries' => 'supply ledger entries',
    ];
}

if($postadd) { 
    // code, description, category, unit, unit_cost, stock_onhand, type, is_consumable
    $code = $_POST['code'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $unit_cost = $_POST['unit_cost'] ?? '';
    $stock_onhand = $_POST['stock_onhand'] ?? '';
    $type = $_POST['type'] ?? '';
    $is_consumable = isset($_POST['is_consumable']) ? 1 : 0;
    if(empty($code) || empty($description) || empty($category) || empty($unit) || empty($unit_cost) || empty($stock_onhand) || empty($type)) {
        flash('danger', 'All fields are required');
        redirect('items.php');
        exit;
    }

    $res = $iModel::add($code, $description, $category, $unit, $unit_cost, $stock_onhand, $type, $is_consumable);
    if ($res) {
        $itemId = (int)$res;
        StockInventory::ensureForItem($itemId);
        StockInventory::refreshFromItem($itemId);
        Supply_Ledger_Card::ensureForItem($itemId, getUser('id'));
        flash('success', 'Item added successfully');
    } else {
        flash('danger', 'Failed to add item');
    }
    redirect('items.php');
}

if($postupdate) {
    // itemid, code, description, category, unit, unit_cost, stock_onhand, type, is_consumable
    $itemid = $_POST['itemid'] ?? '';
    $code = $_POST['code'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $unit_cost = $_POST['unit_cost'] ?? '';
    $stock_onhand = $_POST['stock_onhand'] ?? '';
    $type = $_POST['type'] ?? '';
    $is_consumable = isset($_POST['is_consumable']) ? 1 : 0;
    if(empty($itemid)) {
        flash('danger', 'Item ID is required for update');
        redirect('items.php');
        exit;
    }
    if (!Item::existsById((int)$itemid)) {
        flash('danger', 'Selected item was not found.');
        redirect('items.php');
        exit;
    }
    if(empty($code) || empty($description) || empty($category) || empty($unit) || empty($unit_cost) || empty($stock_onhand) || empty($type)) {
        flash('danger', 'All fields are required');
        redirect('items.php');
        exit;
    }
    $res = $iModel::update($code, $description, $category, $unit, $unit_cost, $stock_onhand, $type, $is_consumable, $itemid);
    if ($res) {
        $itemId = (int)$itemid;
        StockInventory::ensureForItem($itemId);
        StockInventory::refreshFromItem($itemId);
        Supply_Ledger_Card::ensureForItem($itemId, getUser('id'));
        flash('success', 'Item updated successfully');
    } else {
        flash('danger', 'Failed to update item');
    }
    redirect('items.php');
}

if($postdelete) {

    // itemid,
    $itemid = (int)($_POST['itemid'] ?? 0);
    
    if($itemid <= 0) {
        flash('danger', 'Item ID is required for deletion');
        redirect('items.php');
        exit;
    }

    if (!Item::existsById($itemid)) {
        flash('danger', 'Selected item was not found.');
        redirect('items.php');
        exit;
    }

    $dependencyCounts = Item::getDeletionDependencyCounts($itemid);
    $blockingCounts = [];
    foreach (itemDependencyLabelMap() as $table => $label) {
        $count = (int)($dependencyCounts[$table] ?? 0);
        if ($count > 0) {
            $blockingCounts[] = $label;
        }
    }

    if (!empty($blockingCounts)) {
        flash('danger', 'Item cannot be deleted because it is referenced by: ' . implode(', ', $blockingCounts) . '.');
        redirect('items.php');
        exit;
    }

    try {
        $res = Item::deleteWithSupportRecords($itemid);
    } catch (Throwable $e) {
        flash('danger', 'Failed to delete item. ' . $e->getMessage());
        redirect('items.php');
        exit;
    }

    if ($res) {
        flash('success', 'Item deleted successfully.');
    } else {
        flash('danger', 'Failed to delete item.');
    }
    redirect('items.php');
}






?>
