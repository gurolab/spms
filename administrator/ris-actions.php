<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/RIS.model.php';

function risListUrl(): string
{
    if (isSupplyOfficer()) {
        return BASE_URL . 'supplyofficer/ris.php';
    }
    return BASE_URL . 'administrator/ris.php';
}

function risItemsUrl($risId = null): string
{
    $base = isSupplyOfficer()
        ? BASE_URL . 'supplyofficer/ris_items.php'
        : BASE_URL . 'administrator/ris_items.php';

    if ($risId === null || $risId === '') {
        return $base;
    }

    return $base . '?ris_id=' . urlencode((string)$risId);
}


if(POSTACT('initadd')) { 
    // ris_no, requisition_date, fund_cluster, division, responsibility_center_code, requested_by, purpose, remarks
    $ris_no = trim($_POST['ris_no'] ?? '');
    $requisition_date = $_POST['requisition_date'] ?? '';
    $division = $_POST['division'] ?? '';
    $requested_by = $_POST['requested_by'] ?? '';
    $fund_cluster = trim($_POST['fund_cluster'] ?? '');
    $responsibility_center_code = trim($_POST['responsibility_center_code'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if($ris_no === '' || $requisition_date === '' || $division === '' || $requested_by === '' || $fund_cluster === '' || $purpose === '') {
        flash('danger', 'RIS No, Requisition Date, Department, Fund Cluster, Requested By, and Purpose are required.');
        redirect(risListUrl());
        exit;
    }

    $iModel = new RIS();
    $res = $iModel::addinitial($ris_no, $requisition_date, $fund_cluster, $division, $responsibility_center_code, $requested_by, $purpose, $remarks);

    if ($res) {
        flash('success', 'Request submitted successfully');
    } else {
        flash('danger', 'Failed to submitted Request');
    }
    redirect(risListUrl());
}

if(POSTACT('update')) {

    // fund_cluster, responsibility_center_code, approved_by, issued_by, received_by, status, remarks, purpose
    $fund_cluster = trim($_POST['fund_cluster'] ?? '');
    $responsibility_center_code = trim($_POST['responsibility_center_code'] ?? '');
    $approved_by = $_POST['approved_by'] ?? '';
    $issued_by = $_POST['issued_by'] ?? '';
    $received_by = $_POST['received_by'] ?? '';
    $status = $_POST['status'] ?? 'Requested';
    $remarks = trim($_POST['remarks'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $id = $_POST['id'] ?? '';

    $allowedStatuses = ['Requested','Approved','Partial Issued','Issued','Completed'];
    if(!in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect(risListUrl());
        exit;
    }

    if(empty($id)) {
        flash('danger', 'RIS reference is required.');
        redirect(risListUrl());
        exit;
    }

    $iModel = new RIS();
    $res = $iModel::update($fund_cluster, $responsibility_center_code, $approved_by, $issued_by, $received_by, $status, $remarks, $purpose, $id);
    
    if ($res) {
        flash('success', 'Request updated successfully');
    } else {
        flash('danger', 'Failed to update Request');
    }
    redirect(risListUrl());
}

if(POSTACT('del')) {

    $id = $_POST['id'] ?? '';
    
    if(empty($id)) {
        flash('danger', 'ID is required for deletion');
        redirect(risListUrl());
        exit;
    }

    //check if has items in ris_items that has qty_issued > 0
    $iModelItems = new RIS_Items();
    $items = $iModelItems->getAll($id);
    foreach($items as $item) {
        if(is_numeric($item['qty_issued']) && $item['qty_issued'] > 0) {
            flash('danger', 'Cannot delete Request that has issued items, Pls follow the SOP for returning items.');
            redirect(risListUrl());
        }
    }

    $iModel = new RIS();
    $res = $iModel->deleteById($id);
    
    if ($res) {
        flash('success', 'Request deleted successfully');
    } else {
        flash('danger', 'Failed to delete Request');
    }
    redirect(risListUrl());

}

if(POSTACT('addrisitem')) {

    //ris_id, item_id, qty_requested, qty_issued, remarks
    $ris_id = $_POST['ris_id'] ?? '';
    $item_id = $_POST['item_id'] ?? '';
    $qty_requested = $_POST['qty_requested'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if(empty($ris_id) || empty($item_id) || empty($qty_requested)) {
        flash('danger', 'All fields are required');
        redirect(risItemsUrl($ris_id));
    }

    if(!is_numeric($qty_requested) || $qty_requested <= 0) {
        flash('danger', 'Quantity requested must be greater than 0 and quantity issued cannot be negative');
        redirect(risItemsUrl($ris_id));
        exit;
    }

    $iModel = new RIS_Items();
    $existingItem = $iModel->getByRISAndItem($ris_id, $item_id);

    if ($existingItem) {
        $newQtyRequested = (int)$existingItem['qty_requested'] + (int)$qty_requested;
        $remarksToStore = trim((string)$remarks);
        if ($remarksToStore !== '') {
            $prevRemarks = trim((string)($existingItem['remarks'] ?? ''));
            $remarksToStore = $prevRemarks !== '' ? $prevRemarks . ' | ' . $remarksToStore : $remarksToStore;
        } else {
            $remarksToStore = $existingItem['remarks'] ?? '';
        }

        $res = $iModel::update($existingItem['ris_id'], $existingItem['item_id'], $newQtyRequested, $existingItem['qty_issued'], $remarksToStore, $existingItem['id']);
    } else {
        $res = $iModel::add($ris_id, $item_id, $qty_requested, $remarks);
    }

    if ($res) {
        flash('success', 'Item added successfully');
    } else {
        flash('danger', 'Failed to add item');
    }
    redirect(risItemsUrl($ris_id));
}

if(POSTACT('delrisitem')) {

    //ris_id, item_id, qty_requested, remarks
    $id = $_POST['id'] ?? '';
    $ris_id = $_POST['ris_id'] ?? '';
    
    if(empty($id) ) {
        flash('error', 'ID is required');
        redirect(risItemsUrl());
    }

    if(empty($ris_id)) {
        flash('error', 'RIS No is required');
        redirect(risItemsUrl());
    }

    $iModel = new RIS_Items;
    $itemDetails = $iModel->getById($id);
    
    if(!$itemDetails) {
        flash('error', 'Item not found');
        redirect(risItemsUrl($ris_id));
        exit;
    }

    if(is_numeric($itemDetails['qty_issued']) && $itemDetails['qty_issued'] > 0) {
        flash('error', 'Cannot delete item that has been issued, Pls follow the SOP for returning items.');
        redirect(risItemsUrl($ris_id));
    }

    $res = $iModel->deleteById($id);
    
    if($res) {
        flash('success', 'Item deleted successfully');
    } else {
        flash('error', 'Failed to delete item');
    }
    redirect(risItemsUrl($ris_id));
    
}

if(POSTACT('updateissueditem')) {

    //ris_id, qty_requested,  id
    $ris_id = $_POST['ris_id'] ?? '';
    $qty_issued = $_POST['qty_issued'] ?? '';
    $id = $_POST['id'] ?? '';

    if(empty($ris_id) || empty($qty_issued) || empty($id)) {
        flash('danger', 'All fields are required');
        redirect(risItemsUrl($ris_id));
    }

    if(!is_numeric($qty_issued) || $qty_issued < 1) {
        flash('danger', 'Quantity issued must be greater than 0');
        redirect(risItemsUrl($ris_id));
        exit;
    }

    $iModel = new RIS_Items();
    $res = $iModel::updateQtyIssued($qty_issued, $id);
    if ($res) {
        flash('success', 'Item issued successfully');
    } else {
        flash('danger', 'Failed to issue item');
    }
    redirect(risItemsUrl($ris_id));
}


?>
