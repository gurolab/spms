<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([6]); // Employee

require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

const EMPLOYEE_EDITABLE_STATUSES = ['Requested', 'Pending'];
const EMPLOYEE_LINE_EDITABLE_STATUSES = ['Requested'];

function employeeRequestRedirect(?int $risId = null): void
{
    $target = 'requisition.php';
    if ($risId !== null && $risId > 0) {
        $target = 'request-details.php?ris_id=' . $risId;
    }
    redirect($target);
}

function isEmployeeEditableStatus(string $status): bool
{
    return in_array($status, EMPLOYEE_EDITABLE_STATUSES, true);
}

function isEmployeeLineEditableStatus(string $status): bool
{
    return in_array($status, EMPLOYEE_LINE_EDITABLE_STATUSES, true);
}

function getOwnedRisOrRedirect(int $risId, int $userId): array
{
    $risModel = new RIS();
    $ris = $risModel->getById($risId);

    if (!$ris || (int)($ris['requested_by'] ?? 0) !== $userId) {
        flash('danger', 'Request not found or access is denied.');
        employeeRequestRedirect();
    }

    return $ris;
}

function trimString($value): string
{
    return trim((string)$value);
}

if (!isPost()) {
    employeeRequestRedirect();
}

$risIdFromRequest = isset($_POST['ris_id'])
    ? (int)$_POST['ris_id']
    : (isset($_POST['id']) ? (int)$_POST['id'] : null);
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    flash('danger', 'Invalid request token. Please try again.');
    employeeRequestRedirect($risIdFromRequest);
}

$currentUserId = (int)(getUser('id') ?? 0);
$currentDepartmentId = (int)(getUser('departmentid') ?? 0);

if ($currentUserId <= 0 || $currentDepartmentId <= 0) {
    flash('danger', 'Unable to identify your account context. Please sign in again.');
    redirect('../logout.php');
}

if (POSTACT('create_request')) {
    $requisitionDate = trimString($_POST['requisition_date'] ?? '');
    $fundCluster = trimString($_POST['fund_cluster'] ?? '');
    $responsibilityCenterCode = trimString($_POST['responsibility_center_code'] ?? '');
    $purpose = trimString($_POST['purpose'] ?? '');
    $remarks = trimString($_POST['remarks'] ?? '');

    $dateObj = DateTime::createFromFormat('Y-m-d', $requisitionDate);
    $isValidDate = $dateObj && $dateObj->format('Y-m-d') === $requisitionDate;

    if (!$isValidDate) {
        flash('danger', 'A valid requisition date is required.');
        employeeRequestRedirect();
    }
    if ($fundCluster === '') {
        flash('danger', 'Fund cluster is required.');
        employeeRequestRedirect();
    }
    if ($purpose === '') {
        flash('danger', 'Purpose is required.');
        employeeRequestRedirect();
    }
    if (!isValidLength($fundCluster, 1, 100)) {
        flash('danger', 'Fund cluster must be between 1 and 100 characters.');
        employeeRequestRedirect();
    }
    if ($responsibilityCenterCode !== '' && !isValidLength($responsibilityCenterCode, 1, 100)) {
        flash('danger', 'Responsibility center code must be at most 100 characters.');
        employeeRequestRedirect();
    }
    if (!isValidLength($purpose, 1, 1000)) {
        flash('danger', 'Purpose must be at most 1000 characters.');
        employeeRequestRedirect();
    }
    if ($remarks !== '' && !isValidLength($remarks, 1, 1000)) {
        flash('danger', 'Remarks must be at most 1000 characters.');
        employeeRequestRedirect();
    }

    $risNo = RIS::getNextRISNo();
    $newId = RIS::addinitial(
        $risNo,
        $requisitionDate,
        $fundCluster,
        $currentDepartmentId,
        $responsibilityCenterCode,
        $currentUserId,
        $purpose,
        $remarks
    );

    if ($newId) {
        writeAuditLog('employee.ris.create.success', 'requisition_slips', (int)$newId, [], [
            'ris_no' => $risNo,
            'fund_cluster' => $fundCluster,
            'department_id' => $currentDepartmentId,
            'purpose' => $purpose,
        ]);
        flash('success', 'Request created successfully. Add line items to complete your submission.');
        employeeRequestRedirect((int)$newId);
    }

    writeAuditLog('employee.ris.create.failed', 'requisition_slips', null, [], [
        'fund_cluster' => $fundCluster,
        'department_id' => $currentDepartmentId,
        'purpose' => $purpose,
    ]);
    flash('danger', 'Failed to create request. Please try again.');
    employeeRequestRedirect();
}

if (POSTACT('update_request')) {
    $risId = (int)($_POST['id'] ?? 0);
    $fundCluster = trimString($_POST['fund_cluster'] ?? '');
    $responsibilityCenterCode = trimString($_POST['responsibility_center_code'] ?? '');
    $purpose = trimString($_POST['purpose'] ?? '');
    $remarks = trimString($_POST['remarks'] ?? '');

    if ($risId <= 0) {
        flash('danger', 'Request reference is required.');
        employeeRequestRedirect();
    }

    $ris = getOwnedRisOrRedirect($risId, $currentUserId);
    if (!isEmployeeEditableStatus((string)($ris['status'] ?? ''))) {
        flash('danger', 'This request is already being processed and can no longer be edited.');
        employeeRequestRedirect($risId);
    }

    if ($fundCluster === '' || $purpose === '') {
        flash('danger', 'Fund cluster and purpose are required.');
        employeeRequestRedirect($risId);
    }
    if (!isValidLength($fundCluster, 1, 100)) {
        flash('danger', 'Fund cluster must be between 1 and 100 characters.');
        employeeRequestRedirect($risId);
    }
    if ($responsibilityCenterCode !== '' && !isValidLength($responsibilityCenterCode, 1, 100)) {
        flash('danger', 'Responsibility center code must be at most 100 characters.');
        employeeRequestRedirect($risId);
    }
    if (!isValidLength($purpose, 1, 1000)) {
        flash('danger', 'Purpose must be at most 1000 characters.');
        employeeRequestRedirect($risId);
    }
    if ($remarks !== '' && !isValidLength($remarks, 1, 1000)) {
        flash('danger', 'Remarks must be at most 1000 characters.');
        employeeRequestRedirect($risId);
    }

    $res = RIS::update(
        $fundCluster,
        $responsibilityCenterCode,
        $ris['approved_by'] ?? null,
        $ris['issued_by'] ?? null,
        $ris['received_by'] ?? null,
        (string)($ris['status'] ?? 'Requested'),
        $remarks,
        $purpose,
        $risId
    );

    if ($res) {
        writeAuditLog('employee.ris.update.success', 'requisition_slips', $risId, [], [
            'fund_cluster' => $fundCluster,
            'purpose' => $purpose,
        ]);
        flash('success', 'Request updated successfully.');
    } else {
        writeAuditLog('employee.ris.update.failed', 'requisition_slips', $risId);
        flash('danger', 'No changes were saved. Please try again.');
    }
    employeeRequestRedirect($risId);
}

if (POSTACT('delete_request')) {
    $risId = (int)($_POST['id'] ?? 0);
    if ($risId <= 0) {
        flash('danger', 'Request reference is required.');
        employeeRequestRedirect();
    }

    $ris = getOwnedRisOrRedirect($risId, $currentUserId);
    if (!isEmployeeEditableStatus((string)($ris['status'] ?? ''))) {
        flash('danger', 'This request can no longer be deleted.');
        employeeRequestRedirect($risId);
    }

    $itemsModel = new RIS_Items();
    $items = $itemsModel->getAll($risId);
    foreach ($items as $item) {
        if ((int)($item['qty_issued'] ?? 0) > 0) {
            flash('danger', 'Cannot delete a request that already has issued quantities.');
            employeeRequestRedirect($risId);
        }
    }

    foreach ($items as $item) {
        $itemsModel->deleteById((int)$item['id']);
    }

    $risModel = new RIS();
    $res = $risModel->deleteById($risId);
    if ($res) {
        writeAuditLog('employee.ris.delete.success', 'requisition_slips', $risId, [], [
            'ris_no' => $ris['ris_no'] ?? null,
        ]);
        flash('success', 'Request deleted successfully.');
    } else {
        writeAuditLog('employee.ris.delete.failed', 'requisition_slips', $risId);
        flash('danger', 'Failed to delete request.');
    }
    employeeRequestRedirect();
}

if (POSTACT('add_item')) {
    $risId = (int)($_POST['ris_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $qtyRequested = (int)($_POST['qty_requested'] ?? 0);
    $remarks = trimString($_POST['remarks'] ?? '');

    if ($risId <= 0 || $itemId <= 0 || $qtyRequested <= 0) {
        flash('danger', 'Request, item, and quantity are required.');
        employeeRequestRedirect($risId > 0 ? $risId : null);
    }
    if ($remarks !== '' && !isValidLength($remarks, 1, 500)) {
        flash('danger', 'Remarks must be at most 500 characters.');
        employeeRequestRedirect($risId);
    }

    $ris = getOwnedRisOrRedirect($risId, $currentUserId);
    if (!isEmployeeLineEditableStatus((string)($ris['status'] ?? ''))) {
        flash('danger', 'This request can no longer be modified.');
        employeeRequestRedirect($risId);
    }

    $itemModel = new Item();
    $item = $itemModel->getById($itemId);
    if (!$item) {
        flash('danger', 'Selected item was not found.');
        employeeRequestRedirect($risId);
    }

    $itemsModel = new RIS_Items();
    $existingItem = $itemsModel->getByRISAndItem($risId, $itemId);
    $entityId = null;
    if ($existingItem) {
        $newQtyRequested = (int)$existingItem['qty_requested'] + $qtyRequested;
        $mergedRemarks = $remarks;
        $previousRemarks = trimString($existingItem['remarks'] ?? '');
        if ($previousRemarks !== '' && $mergedRemarks !== '') {
            $mergedRemarks = $previousRemarks . ' | ' . $mergedRemarks;
        } elseif ($previousRemarks !== '') {
            $mergedRemarks = $previousRemarks;
        }

        $res = RIS_Items::update(
            (int)$existingItem['ris_id'],
            (int)$existingItem['item_id'],
            $newQtyRequested,
            (int)$existingItem['qty_issued'],
            $mergedRemarks,
            (int)$existingItem['id']
        );
        $entityId = (int)$existingItem['id'];
    } else {
        $res = RIS_Items::add($risId, $itemId, $qtyRequested, $remarks);
        $entityId = is_numeric($res) ? (int)$res : null;
    }

    if ($res) {
        writeAuditLog('employee.ris_item.add.success', 'requisition_items', $entityId, [], [
            'ris_id' => $risId,
            'item_id' => $itemId,
            'qty_requested' => $qtyRequested,
        ]);
        flash('success', 'Item added to request.');
    } else {
        writeAuditLog('employee.ris_item.add.failed', 'requisition_items', null, [], [
            'ris_id' => $risId,
            'item_id' => $itemId,
            'qty_requested' => $qtyRequested,
        ]);
        flash('danger', 'Failed to add item to request.');
    }
    employeeRequestRedirect($risId);
}

if (POSTACT('update_item')) {
    $itemLineId = (int)($_POST['id'] ?? 0);
    $risId = (int)($_POST['ris_id'] ?? 0);
    $qtyRequested = (int)($_POST['qty_requested'] ?? 0);
    $remarks = trimString($_POST['remarks'] ?? '');

    if ($itemLineId <= 0 || $risId <= 0 || $qtyRequested <= 0) {
        flash('danger', 'Valid item, request, and quantity are required.');
        employeeRequestRedirect($risId > 0 ? $risId : null);
    }
    if ($remarks !== '' && !isValidLength($remarks, 1, 500)) {
        flash('danger', 'Remarks must be at most 500 characters.');
        employeeRequestRedirect($risId);
    }

    $ris = getOwnedRisOrRedirect($risId, $currentUserId);
    if (!isEmployeeLineEditableStatus((string)($ris['status'] ?? ''))) {
        flash('danger', 'This request can no longer be modified.');
        employeeRequestRedirect($risId);
    }

    $itemsModel = new RIS_Items();
    $existingLine = $itemsModel->getById($itemLineId);
    if (!$existingLine || (int)($existingLine['ris_id'] ?? 0) !== $risId) {
        flash('danger', 'Requested item line was not found.');
        employeeRequestRedirect($risId);
    }

    $qtyIssued = (int)($existingLine['qty_issued'] ?? 0);
    if ($qtyIssued > 0) {
        flash('danger', 'Issued item lines can no longer be edited.');
        employeeRequestRedirect($risId);
    }

    $res = RIS_Items::update(
        (int)$existingLine['ris_id'],
        (int)$existingLine['item_id'],
        $qtyRequested,
        $qtyIssued,
        $remarks,
        $itemLineId
    );

    if ($res) {
        writeAuditLog('employee.ris_item.update.success', 'requisition_items', $itemLineId, [], [
            'ris_id' => $risId,
            'qty_requested' => $qtyRequested,
        ]);
        flash('success', 'Item line updated successfully.');
    } else {
        writeAuditLog('employee.ris_item.update.failed', 'requisition_items', $itemLineId);
        flash('danger', 'Failed to update item line.');
    }
    employeeRequestRedirect($risId);
}

if (POSTACT('remove_item')) {
    $itemLineId = (int)($_POST['id'] ?? 0);
    $risId = (int)($_POST['ris_id'] ?? 0);

    if ($itemLineId <= 0 || $risId <= 0) {
        flash('danger', 'Valid item and request references are required.');
        employeeRequestRedirect($risId > 0 ? $risId : null);
    }

    $ris = getOwnedRisOrRedirect($risId, $currentUserId);
    if (!isEmployeeLineEditableStatus((string)($ris['status'] ?? ''))) {
        flash('danger', 'This request can no longer be modified.');
        employeeRequestRedirect($risId);
    }

    $itemsModel = new RIS_Items();
    $existingLine = $itemsModel->getById($itemLineId);
    if (!$existingLine || (int)($existingLine['ris_id'] ?? 0) !== $risId) {
        flash('danger', 'Requested item line was not found.');
        employeeRequestRedirect($risId);
    }
    if ((int)($existingLine['qty_issued'] ?? 0) > 0) {
        flash('danger', 'Issued item lines cannot be removed.');
        employeeRequestRedirect($risId);
    }

    $res = $itemsModel->deleteById($itemLineId);
    if ($res) {
        writeAuditLog('employee.ris_item.delete.success', 'requisition_items', $itemLineId, [], [
            'ris_id' => $risId,
        ]);
        flash('success', 'Item line removed successfully.');
    } else {
        writeAuditLog('employee.ris_item.delete.failed', 'requisition_items', $itemLineId);
        flash('danger', 'Failed to remove item line.');
    }
    employeeRequestRedirect($risId);
}

flash('danger', 'Invalid request action.');
employeeRequestRedirect($risIdFromRequest);
