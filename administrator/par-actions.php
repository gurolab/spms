<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/PAR.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/SupplyLedger.model.php';

$allowedStatuses = ['Active', 'Returned', 'Transferred', 'Disposed'];

function resolveEligibleAccountableOfficer(int $userId): array
{
    if ($userId <= 0) {
        throw new RuntimeException('Accountable Officer is required.');
    }

    $userModel = new BaseModel('user_role_dept');
    $user = $userModel->getById($userId);
    if (!is_array($user)) {
        throw new RuntimeException('Selected Accountable Officer account was not found.');
    }

    $roleId = (int)($user['roleid'] ?? 0);
    $status = strtolower(trim((string)($user['status'] ?? '')));
    if ($roleId !== 6 || $status !== 'active') {
        throw new RuntimeException('Only active permanent employees can receive property/equipment.');
    }

    return $user;
}

if (POSTACT('add')) {
    $par_no = trim($_POST['par_no'] ?? '');
    $fund_cluster = trim($_POST['fund_cluster'] ?? '');
    $accountable_officer = (int)($_POST['accountable_officer'] ?? 0);
    $issued_by = (int)($_POST['issued_by'] ?? 0);
    $issue_date = $_POST['issue_date'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $remarks = $_POST['remarks'] ?? '';

    if ($par_no === '' || $fund_cluster === '' || $accountable_officer === 0 || $issued_by === 0 || $issue_date === '') {
        flash('danger', 'PAR No., Fund Cluster, Accountable Officer, Issued By, and Issue Date are required.');
        redirect('par.php');
    }

    if (!in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('par.php');
    }

    try {
        resolveEligibleAccountableOfficer($accountable_officer);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('par.php');
    }

    $existing = PAR::getByParNo($par_no);
    if ($existing) {
        flash('danger', 'A PAR with the same number already exists.');
        redirect('par.php');
    }

    $result = PAR::add($par_no, $fund_cluster, $accountable_officer, $issued_by, $issue_date, $status, $remarks);

    if ($result) {
        flash('success', 'Property acknowledgment receipt created successfully.');
    } else {
        flash('danger', 'Failed to create property acknowledgment receipt.');
    }
    redirect('par.php');
}

if (POSTACT('update')) {
    $id = (int)($_POST['id'] ?? 0);
    $par_no = trim($_POST['par_no'] ?? '');
    $fund_cluster = trim($_POST['fund_cluster'] ?? '');
    $accountable_officer = (int)($_POST['accountable_officer'] ?? 0);
    $issued_by = (int)($_POST['issued_by'] ?? 0);
    $issue_date = $_POST['issue_date'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $remarks = $_POST['remarks'] ?? '';

    if ($id === 0) {
        flash('danger', 'PAR reference is required.');
        redirect('par.php');
    }

    if ($par_no === '' || $fund_cluster === '' || $accountable_officer === 0 || $issued_by === 0 || $issue_date === '') {
        flash('danger', 'PAR No., Fund Cluster, Accountable Officer, Issued By, and Issue Date are required.');
        redirect('par.php');
    }

    if (!in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('par.php');
    }

    try {
        resolveEligibleAccountableOfficer($accountable_officer);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('par.php');
    }

    $existing = PAR::getByParNo($par_no);
    if ($existing && (int)$existing['id'] !== $id) {
        flash('danger', 'Another PAR already uses this number.');
        redirect('par.php');
    }

    $result = PAR::update($par_no, $fund_cluster, $accountable_officer, $issued_by, $issue_date, $status, $remarks, $id);

    if ($result) {
        flash('success', 'Property acknowledgment receipt updated successfully.');
    } else {
        flash('danger', 'Failed to update property acknowledgment receipt.');
    }
    redirect('par.php');
}

if (POSTACT('del')) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id === 0) {
        flash('danger', 'PAR reference is required for deletion.');
        redirect('par.php');
    }

    $itemsModel = new PAR_Items();
    $linkedItems = $itemsModel->getAll($id);
    if (!empty($linkedItems)) {
        flash('danger', 'Remove all items attached to this PAR before deleting it.');
        redirect('par.php');
    }

    $model = new PAR();
    $result = $model->deleteById($id);

    if ($result) {
        flash('success', 'Property acknowledgment receipt deleted successfully.');
    } else {
        flash('danger', 'Failed to delete property acknowledgment receipt.');
    }
    redirect('par.php');
}

if (POSTACT('addparitem')) {
    $par_id = (int)($_POST['par_id'] ?? 0);
    $property_no = trim($_POST['property_no'] ?? '');
    $item_id = (int)($_POST['item_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    $unit_value = (float)($_POST['unit_value'] ?? 0);
    $acquisition_date = $_POST['acquisition_date'] ?? '';
    $description = $_POST['description'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if ($par_id === 0) {
        flash('danger', 'PAR reference is missing. Please reopen the PAR and try again.');
        redirect('par_items.php');
    }
    if ($property_no === '') {
        flash('danger', 'Property tag is required.');
        redirect('par_items.php?par_id=' . $par_id);
    }
    if ($item_id === 0) {
        flash('danger', 'Please select an item to assign.');
        redirect('par_items.php?par_id=' . $par_id);
    }
    if ($qty <= 0) {
        flash('danger', 'Quantity must be greater than zero.');
        redirect('par_items.php?par_id=' . $par_id);
    }
    if ($unit_value < 0) {
        flash('danger', 'Unit value cannot be negative.');
        redirect('par_items.php?par_id=' . $par_id);
    }
    if ($acquisition_date === '') {
        flash('danger', 'Acquisition date is required.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $parModelCheck = new PAR();
    $parHeader = $parModelCheck->getById($par_id);
    if (!$parHeader) {
        flash('danger', 'PAR reference not found.');
        redirect('par_items.php');
    }

    if ($parHeader['status'] !== 'Active') {
        flash('danger', 'Only active PARs can accept new items.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $itemsModel = new PAR_Items();
    $existingProperty = $itemsModel->getByParAndProperty($par_id, $property_no);
    if ($existingProperty) {
        flash('danger', 'This property tag is already listed under the selected PAR.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $itemRepo = new Item();
    $itemDetails = $itemRepo->getById($item_id);
    if (!$itemDetails) {
        flash('danger', 'Inventory record for the selected item was not found.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    if ((int)$itemDetails['stock_onhand'] < $qty) {
        flash('danger', 'Insufficient stock on hand for this issuance.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    try {
        $result = PAR_Items::add($par_id, $item_id, $property_no, $qty, $unit_value, $acquisition_date, $description, $remarks);
    } catch (Throwable $e) {
        flash('danger', 'Failed to attach item to PAR. ' . $e->getMessage());
        redirect('par_items.php?par_id=' . $par_id);
    }

    if ($result !== false) {
        $issueDate = $parHeader['issue_date'] ?? date('Y-m-d');
        $referenceNo = $parHeader['par_no'] ?? '';
        $itemRepo->reduceQty($item_id, $qty);
        StockInventory::recordMovement(
            (int)$item_id,
            0,
            (int)$qty,
            'PAR Issuance',
            (int)$par_id,
            $referenceNo,
            $remarks,
            $issueDate
        );
        Supply_Ledger_Entry::recordMovement(
            (int)$item_id,
            $issueDate,
            'PAR Issuance',
            (int)$par_id,
            $referenceNo,
            0,
            (int)$qty,
            (float)$unit_value,
            $remarks,
            getUser('id')
        );
        flash('success', 'Item added to PAR successfully.');
    } else {
        flash('danger', 'Failed to attach item to PAR. Please try again.');
    }
    redirect('par_items.php?par_id=' . $par_id);
}

if (POSTACT('updateparitem')) {
    $id = (int)($_POST['id'] ?? 0);
    $par_id = (int)($_POST['par_id'] ?? 0);
    $property_no = trim($_POST['property_no'] ?? '');
    $item_id = (int)($_POST['item_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    $unit_value = (float)($_POST['unit_value'] ?? 0);
    $acquisition_date = $_POST['acquisition_date'] ?? '';
    $description = $_POST['description'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if ($id === 0 || $par_id === 0) {
        flash('danger', 'Item reference is required.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    if ($property_no === '') {
        flash('danger', 'Property tag is required.');
        redirect('par_items.php?par_id=' . $par_id);
    }
    if ($item_id === 0) {
        flash('danger', 'Please select an item to assign.');
        redirect('par_items.php?par_id=' . $par_id);
    }
    if ($qty <= 0) {
        flash('danger', 'Quantity must be greater than zero.');
        redirect('par_items.php?par_id=' . $par_id);
    }
    if ($unit_value < 0) {
        flash('danger', 'Unit value cannot be negative.');
        redirect('par_items.php?par_id=' . $par_id);
    }
    if ($acquisition_date === '') {
        flash('danger', 'Acquisition date is required.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $parModelCheck = new PAR();
    $parHeader = $parModelCheck->getById($par_id);
    if (!$parHeader) {
        flash('danger', 'PAR reference not found.');
        redirect('par_items.php');
    }

    if ($parHeader['status'] !== 'Active') {
        flash('danger', 'Only active PARs can be modified.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $itemsModel = new PAR_Items();
    $existingProperty = $itemsModel->getByParAndProperty($par_id, $property_no);
    if ($existingProperty && (int)$existingProperty['id'] !== $id) {
        flash('danger', 'Another item already uses this property tag.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $existingItem = $itemsModel->getById($id);
    if (!$existingItem) {
        flash('danger', 'PAR item not found.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $itemRepo = new Item();
    $issueDate = $parHeader['issue_date'] ?? date('Y-m-d');
    $referenceNo = $parHeader['par_no'] ?? '';

    $previousItemId = (int)($existingItem['item_id'] ?? 0);
    $previousQty = (int)($existingItem['qty'] ?? 0);
    $previousUnitValue = (float)($existingItem['unit_value'] ?? 0);
    $unitCostChanged = abs($previousUnitValue - $unit_value) > 0.0001;

    if ($previousItemId === $item_id) {
        $deltaQty = $qty - $previousQty;
        if ($deltaQty > 0) {
            $itemDetails = $itemRepo->getById($item_id);
            if (!$itemDetails) {
                flash('danger', 'Inventory record for the selected item was not found.');
                redirect('par_items.php?par_id=' . $par_id);
            }
            if ((int)$itemDetails['stock_onhand'] < $deltaQty) {
                flash('danger', 'Insufficient stock on hand for this update.');
                redirect('par_items.php?par_id=' . $par_id);
            }
        }
    } else {
        $newItemDetails = $itemRepo->getById($item_id);
        if (!$newItemDetails) {
            flash('danger', 'Inventory record for the selected item was not found.');
            redirect('par_items.php?par_id=' . $par_id);
        }
        if ((int)$newItemDetails['stock_onhand'] < $qty) {
            flash('danger', 'Insufficient stock on hand for the new item.');
            redirect('par_items.php?par_id=' . $par_id);
        }
    }

    try {
        $result = PAR_Items::update($par_id, $item_id, $property_no, $qty, $unit_value, $acquisition_date, $description, $remarks, $id);
    } catch (Throwable $e) {
        flash('danger', 'Failed to update PAR item. ' . $e->getMessage());
        redirect('par_items.php?par_id=' . $par_id);
    }

    if ($result) {
        if ($previousItemId === $item_id) {
            $deltaQty = $qty - $previousQty;
            if ($deltaQty > 0) {
                $itemRepo->reduceQty($item_id, $deltaQty);
                StockInventory::recordMovement(
                    (int)$item_id,
                    0,
                    (int)$deltaQty,
                    'PAR Issuance Adjustment',
                    (int)$par_id,
                    $referenceNo,
                    $remarks,
                    $issueDate
                );
                Supply_Ledger_Entry::recordMovement(
                    (int)$item_id,
                    $issueDate,
                    'PAR Issuance Adjustment',
                    (int)$par_id,
                    $referenceNo,
                    0,
                    (int)$deltaQty,
                    (float)$unit_value,
                    $remarks,
                    getUser('id')
                );
            } elseif ($deltaQty < 0) {
                $deltaQty = abs($deltaQty);
                $itemRepo->addQty($item_id, $deltaQty);
                StockInventory::recordMovement(
                    (int)$item_id,
                    (int)$deltaQty,
                    0,
                    'PAR Issuance Adjustment',
                    (int)$par_id,
                    $referenceNo,
                    $remarks,
                    $issueDate
                );
                Supply_Ledger_Entry::recordMovement(
                    (int)$item_id,
                    $issueDate,
                    'PAR Issuance Adjustment',
                    (int)$par_id,
                    $referenceNo,
                    (int)$deltaQty,
                    0,
                    (float)$unit_value,
                    $remarks,
                    getUser('id')
                );
            } elseif ($unitCostChanged) {
                $adjustRemarks = trim((string)$remarks);
                if ($adjustRemarks === '') {
                    $adjustRemarks = sprintf('Unit cost updated from %.2f to %.2f.', $previousUnitValue, $unit_value);
                }
                Supply_Ledger_Entry::recordMovement(
                    (int)$item_id,
                    $issueDate,
                    'PAR Unit Cost Update',
                    (int)$par_id,
                    $referenceNo,
                    0,
                    0,
                    (float)$unit_value,
                    $adjustRemarks,
                    getUser('id')
                );
            }
        } else {
            if ($previousItemId > 0 && $previousQty > 0) {
                $itemRepo->addQty($previousItemId, $previousQty);
                StockInventory::recordMovement(
                    (int)$previousItemId,
                    (int)$previousQty,
                    0,
                    'PAR Issuance Reversal',
                    (int)$par_id,
                    $referenceNo,
                    $existingItem['remarks'] ?? '',
                    $issueDate
                );
                Supply_Ledger_Entry::recordMovement(
                    (int)$previousItemId,
                    $issueDate,
                    'PAR Issuance Reversal',
                    (int)$par_id,
                    $referenceNo,
                    (int)$previousQty,
                    0,
                    (float)$previousUnitValue,
                    $existingItem['remarks'] ?? '',
                    getUser('id')
                );
            }

            $itemRepo->reduceQty($item_id, $qty);
            StockInventory::recordMovement(
                (int)$item_id,
                0,
                (int)$qty,
                'PAR Issuance',
                (int)$par_id,
                $referenceNo,
                $remarks,
                $issueDate
            );
            Supply_Ledger_Entry::recordMovement(
                (int)$item_id,
                $issueDate,
                'PAR Issuance',
                (int)$par_id,
                $referenceNo,
                0,
                (int)$qty,
                (float)$unit_value,
                $remarks,
                getUser('id')
            );
        }
        flash('success', 'PAR item updated successfully.');
    } else {
        flash('danger', 'Failed to update PAR item. Please try again.');
    }
    redirect('par_items.php?par_id=' . $par_id);
}

if (POSTACT('delparitem')) {
    $id = (int)($_POST['id'] ?? 0);
    $par_id = (int)($_POST['par_id'] ?? 0);

    if ($id === 0 || $par_id === 0) {
        flash('danger', 'Item reference is required for removal.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $parModelCheck = new PAR();
    $parHeader = $parModelCheck->getById($par_id);
    if (!$parHeader) {
        flash('danger', 'PAR reference not found.');
        redirect('par_items.php');
    }

    if ($parHeader['status'] !== 'Active') {
        flash('danger', 'Only active PARs can be modified.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $itemsModel = new PAR_Items();
    $existingItem = $itemsModel->getById($id);
    if (!$existingItem) {
        flash('danger', 'PAR item not found.');
        redirect('par_items.php?par_id=' . $par_id);
    }

    $result = $itemsModel->deleteById($id);

    if ($result) {
        $itemId = (int)($existingItem['item_id'] ?? 0);
        $qty = (int)($existingItem['qty'] ?? 0);
        if ($itemId > 0 && $qty > 0) {
            $issueDate = $parHeader['issue_date'] ?? date('Y-m-d');
            $referenceNo = $parHeader['par_no'] ?? '';
            $itemRepo = new Item();
            $itemRepo->addQty($itemId, $qty);
            StockInventory::recordMovement(
                (int)$itemId,
                (int)$qty,
                0,
                'PAR Issuance Reversal',
                (int)$par_id,
                $referenceNo,
                $existingItem['remarks'] ?? '',
                $issueDate
            );
            Supply_Ledger_Entry::recordMovement(
                (int)$itemId,
                $issueDate,
                'PAR Issuance Reversal',
                (int)$par_id,
                $referenceNo,
                (int)$qty,
                0,
                (float)($existingItem['unit_value'] ?? 0),
                $existingItem['remarks'] ?? '',
                getUser('id')
            );
        }
        flash('success', 'Item removed from PAR successfully.');
    } else {
        flash('danger', 'Failed to remove item from PAR.');
    }
    redirect('par_items.php?par_id=' . $par_id);
}

redirect('par.php');
