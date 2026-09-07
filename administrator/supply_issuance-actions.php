<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/Supply_Issuance.model.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/SupplyLedger.model.php';

function supplyIssuanceListUrl(): string
{
    if (isSupplyOfficer()) {
        return BASE_URL . 'supplyofficer/issue_supplies.php';
    }
    return BASE_URL . 'administrator/supply_issuance.php';
}

function supplyIssuanceItemsUrl($issuanceId = null): string
{
    $base = isSupplyOfficer()
        ? BASE_URL . 'supplyofficer/supply_issuance_items.php'
        : BASE_URL . 'administrator/supply_issuance_items.php';

    if ($issuanceId === null || $issuanceId === '') {
        return $base;
    }

    return $base . '?issuance_id=' . urlencode((string)$issuanceId);
}


if (POSTACT('add')) {
    $ris_id = $_POST['ris_id'] ?? '';
    $issuance_date = $_POST['issuance_date'] ?? '';
    $issued_by = isSupplyOfficer() ? (string)(getUser('id') ?? '') : ($_POST['issued_by'] ?? '');
    $received_by = $_POST['received_by'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (empty($ris_id) || empty($issuance_date) || empty($issued_by)) {
        flash('danger', 'RIS, issuance date, and issuer are required.');
        redirect(supplyIssuanceListUrl());
        exit;
    }

    $risModel = new RIS();
    $risData = $risModel->getById($ris_id);
    if (!$risData) {
        flash('danger', 'Selected RIS could not be found.');
        redirect(supplyIssuanceListUrl());
        exit;
    }

    $rsmi_no = Supply_Issuance::getNextRSMINo();
    $status = 'Draft';

    $issuanceId = Supply_Issuance::add($rsmi_no, $ris_id, $issuance_date, $issued_by, $received_by, $status, $remarks);

    if ($issuanceId) {
        flash('success', 'Issuance created successfully.');
    } else {
        flash('danger', 'Failed to create issuance.');
    }
    redirect(supplyIssuanceListUrl());
}

if (POSTACT('update')) {
    $id = $_POST['id'] ?? '';
    $issuance_date = $_POST['issuance_date'] ?? '';
    $issued_by = isSupplyOfficer() ? (string)(getUser('id') ?? '') : ($_POST['issued_by'] ?? '');
    $received_by = $_POST['received_by'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    $validStatuses = ['Draft', 'Partial Issued', 'Issued', 'Completed'];

    if (empty($id) || empty($issuance_date) || empty($issued_by) || !in_array($status, $validStatuses, true)) {
        flash('danger', 'Issuance id, date, issuer, and a valid status are required.');
        redirect(supplyIssuanceListUrl());
        exit;
    }

    $updateResult = Supply_Issuance::update($issuance_date, $issued_by, $received_by, $status, $remarks, $id);

    if ($updateResult) {
        flash('success', 'Issuance updated successfully.');
    } else {
        flash('danger', 'Failed to update issuance.');
    }
    redirect(supplyIssuanceListUrl());
}

if (POSTACT('del')) {
    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        flash('danger', 'Issuance reference required.');
        redirect(supplyIssuanceListUrl());
        exit;
    }

    $itemModel = new Supply_Issuance_Items();
    $items = $itemModel->getAll($id);
    if (!empty($items)) {
        flash('danger', 'Unable to delete issuance with recorded items. Remove items first.');
        redirect(supplyIssuanceListUrl());
        exit;
    }

    $issuanceModel = new Supply_Issuance();
    $deleted = $issuanceModel->deleteById($id);

    if ($deleted) {
        flash('success', 'Issuance deleted successfully.');
    } else {
        flash('danger', 'Failed to delete issuance.');
    }
    redirect(supplyIssuanceListUrl());
}

if (POSTACT('addissuanceitem')) {
    $issuance_id = $_POST['issuance_id'] ?? '';
    $requisition_item_id = $_POST['requisition_item_id'] ?? '';
    $qty_issued = $_POST['qty_issued'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (empty($issuance_id) || empty($requisition_item_id) || empty($qty_issued)) {
        flash('danger', 'Issuance, RIS item, and quantity are required.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    if (!is_numeric($qty_issued) || $qty_issued <= 0) {
        flash('danger', 'Quantity issued must be greater than zero.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    $issuanceModel = new Supply_Issuance();
    $issuance = $issuanceModel->getById($issuance_id);
    if (!$issuance) {
        flash('danger', 'Issuance record not found.');
        redirect(supplyIssuanceListUrl());
        exit;
    }

    $risModel = new RIS();
    $risData = $risModel->getById($issuance['ris_id']);
    if (!$risData) {
        flash('danger', 'Linked RIS could not be found.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    $risItemModel = new RIS_Items();
    $risItem = $risItemModel->getById($requisition_item_id);
    if (!$risItem || (int)$risItem['ris_id'] !== (int)$issuance['ris_id']) {
        flash('danger', 'Selected RIS item is invalid for this issuance.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    $remaining = (int)$risItem['qty_requested'] - (int)$risItem['qty_issued'];
    if ($remaining <= 0) {
        flash('danger', 'The selected item has already been fully issued.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    if ($qty_issued > $remaining) {
        flash('danger', 'Quantity exceeds the remaining balance for this item.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    $item_id = $risItem['item_id'];
    $qty_requested = $risItem['qty_requested'];

    $itemRepo = new Item();
    $itemDetails = $itemRepo->getById($item_id);
    if (!$itemDetails) {
        flash('danger', 'Inventory record for the selected item was not found.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    if ((int)$itemDetails['stock_onhand'] < (int)$qty_issued) {
        flash('danger', 'Insufficient stock on hand for this issuance.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    $existingIssuanceItem = Supply_Issuance_Items::getByIssuanceAndRequisitionItem($issuance_id, $requisition_item_id);
    $addResult = false;

    if ($existingIssuanceItem) {
        $newTotalIssued = (int)$existingIssuanceItem['qty_issued'] + (int)$qty_issued;
        $remarksToStore = null;

        $incomingRemarks = trim((string)$remarks);
        if ($incomingRemarks !== '') {
            $previousRemarks = trim((string)($existingIssuanceItem['remarks'] ?? ''));
            $remarksToStore = $previousRemarks !== ''
                ? $previousRemarks . ' | ' . $incomingRemarks
                : $incomingRemarks;
        }

        $addResult = Supply_Issuance_Items::updateQtyIssued($newTotalIssued, $existingIssuanceItem['id'], $remarksToStore);
    } else {
        $addResult = Supply_Issuance_Items::add($issuance_id, $requisition_item_id, $item_id, $qty_requested, $qty_issued, $remarks);
    }

    if ($addResult) {
        // adjust stock and RIS issued quantities
        $itemRepo->reduceQty($item_id, $qty_issued);
        StockInventory::recordMovement(
            (int)$item_id,
            0,
            (int)$qty_issued,
            'Supply Issuance',
            (int)$issuance_id,
            $issuance['rsmi_no'] ?? '',
            $remarks,
            $issuance['issuance_date'] ?? date('Y-m-d')
        );
        $itemDetailsCurrent = $itemRepo->getById($item_id);
        $issuanceUnitCost = (float)($itemDetailsCurrent['unit_cost'] ?? 0);
        Supply_Ledger_Entry::recordMovement(
            (int)$item_id,
            $issuance['issuance_date'] ?? date('Y-m-d'),
            'Supply Issuance',
            (int)$issuance_id,
            $issuance['rsmi_no'] ?? '',
            0,
            (int)$qty_issued,
            $issuanceUnitCost,
            $remarks,
            getUser('id')
        );
        $risItemModel::adjustQtyIssued($requisition_item_id, $qty_issued);

        // Update RIS status depending on remaining quantities
        $risItems = $risItemModel->getAll($issuance['ris_id']);
        $totalOutstanding = 0;
        $totalRequested = 0;
        $totalIssued = 0;

        foreach ($risItems as $itemRow) {
            $totalRequested += (int)$itemRow['qty_requested'];
            $totalIssued += (int)$itemRow['qty_issued'];
            $totalOutstanding += max(0, (int)$itemRow['qty_requested'] - (int)$itemRow['qty_issued']);
        }

        if ($totalOutstanding === 0 && $totalRequested > 0) {
            $risModel::updateStatus('Completed', $issuance['ris_id']);
            Supply_Issuance::updateStatus('Completed', $issuance_id);
        } else {
            if ($totalIssued > 0) {
                $risModel::updateStatus('Partial Issued', $issuance['ris_id']);
                Supply_Issuance::updateStatus('Partial Issued', $issuance_id);
            } else {
                $risModel::updateStatus('Approved', $issuance['ris_id']);
                Supply_Issuance::updateStatus('Draft', $issuance_id);
            }
        }

        flash('success', 'Issuance item recorded successfully.');
    } else {
        flash('danger', 'Failed to record issuance item.');
    }

    redirect(supplyIssuanceItemsUrl($issuance_id));
}

if (POSTACT('delissuanceitem')) {
    $id = $_POST['id'] ?? '';
    $issuance_id = $_POST['issuance_id'] ?? '';

    if (empty($id) || empty($issuance_id)) {
        flash('danger', 'Issuance item reference required.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    $itemModel = new Supply_Issuance_Items();
    $issuanceItem = $itemModel->getById($id);
    if (!$issuanceItem) {
        flash('danger', 'Issuance item not found.');
        redirect(supplyIssuanceItemsUrl($issuance_id));
        exit;
    }

    $issuanceModel = new Supply_Issuance();
    $issuance = $issuanceModel->getById($issuance_id);
    if (!$issuance) {
        flash('danger', 'Issuance record not found.');
        redirect(supplyIssuanceListUrl());
        exit;
    }

    $deleteResult = $itemModel->deleteById($id);

    if ($deleteResult) {
        $qty = (int)$issuanceItem['qty_issued'];
        $itemId = (int)$issuanceItem['item_id'];
        $requisitionItemId = (int)$issuanceItem['requisition_item_id'];

        // Return stock and adjust RIS
        $itemRepo = new Item();
        $itemRepo->addQty($itemId, $qty);
        StockInventory::recordMovement(
            (int)$itemId,
            (int)$qty,
            0,
            'Supply Issuance Reversal',
            (int)$issuance_id,
            $issuance['rsmi_no'] ?? '',
            $issuanceItem['remarks'] ?? '',
            $issuance['issuance_date'] ?? date('Y-m-d')
        );
        $itemDetails = $itemRepo->getById($itemId);
        $unitCost = (float)($itemDetails['unit_cost'] ?? 0);
        Supply_Ledger_Entry::recordMovement(
            (int)$itemId,
            $issuance['issuance_date'] ?? date('Y-m-d'),
            'Supply Issuance Reversal',
            (int)$issuance_id,
            $issuance['rsmi_no'] ?? '',
            (int)$qty,
            0,
            $unitCost,
            $issuanceItem['remarks'] ?? '',
            getUser('id')
        );
        RIS_Items::adjustQtyIssued($requisitionItemId, -$qty);

        // Re-evaluate RIS status
        $risModel = new RIS();
        $risItems = (new RIS_Items())->getAll($issuance['ris_id']);
        $totalOutstanding = 0;
        $totalRequested = 0;
        $totalIssued = 0;
        foreach ($risItems as $row) {
            $totalRequested += (int)$row['qty_requested'];
            $totalIssued += (int)$row['qty_issued'];
            $totalOutstanding += max(0, (int)$row['qty_requested'] - (int)$row['qty_issued']);
        }

        if ($totalOutstanding === 0 && $totalRequested > 0) {
            $risModel::updateStatus('Completed', $issuance['ris_id']);
            Supply_Issuance::updateStatus('Completed', $issuance_id);
        } elseif ($totalIssued === 0) {
            $risModel::updateStatus('Approved', $issuance['ris_id']);
            Supply_Issuance::updateStatus('Draft', $issuance_id);
        } else {
            $risModel::updateStatus('Partial Issued', $issuance['ris_id']);
            Supply_Issuance::updateStatus('Partial Issued', $issuance_id);
        }

        // Update issuance status based on remaining items
        $remainingItems = $itemModel->getAll($issuance_id);
        if (empty($remainingItems)) {
            Supply_Issuance::updateStatus('Draft', $issuance_id);
        }

        flash('success', 'Issuance item removed successfully.');
    } else {
        flash('danger', 'Failed to remove issuance item.');
    }

    redirect(supplyIssuanceItemsUrl($issuance_id));
}

?>
