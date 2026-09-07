<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/ICS.model.php';

$allowedValueTypes = ['Low', 'High'];
$allowedStatuses = ['Active', 'Returned', 'Transferred', 'Disposed'];

function resolveEligibleAssignedOfficer(int $userId): array
{
    if ($userId <= 0) {
        throw new RuntimeException('Assigned To is required.');
    }

    $userModel = new BaseModel('user_role_dept');
    $user = $userModel->getById($userId);
    if (!is_array($user)) {
        throw new RuntimeException('Selected Assigned To account was not found.');
    }

    $roleId = (int)($user['roleid'] ?? 0);
    $status = strtolower(trim((string)($user['status'] ?? '')));
    if ($roleId !== 6 || $status !== 'active') {
        throw new RuntimeException('Only active permanent employees can receive property/equipment.');
    }

    return $user;
}

if (POSTACT('add')) {
    $ics_no = trim($_POST['ics_no'] ?? '');
    $property_no = trim($_POST['property_no'] ?? '');
    $value_type = $_POST['value_type'] ?? 'Low';
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $issued_by = (int)($_POST['issued_by'] ?? 0);
    $issued_date = $_POST['issued_date'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $remarks = $_POST['remarks'] ?? '';

    if ($ics_no === '' || $property_no === '' || $assigned_to === 0 || $issued_by === 0 || $issued_date === '') {
        flash('danger', 'ICS No., Property No., Assigned To, Issued By, and Issued Date are required.');
        redirect('ics.php');
    }

    if (!in_array($value_type, $allowedValueTypes, true)) {
        flash('danger', 'Invalid value type selected.');
        redirect('ics.php');
    }

    if (!in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('ics.php');
    }

    try {
        resolveEligibleAssignedOfficer($assigned_to);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('ics.php');
    }

    $existing = ICS::getByICSNo($ics_no);
    if ($existing) {
        flash('danger', 'An ICS with the same number already exists.');
        redirect('ics.php');
    }

    $result = ICS::add(
        $ics_no,
        $property_no,
        $value_type,
        $assigned_to,
        $issued_by,
        $issued_date,
        $status,
        $remarks
    );

    if ($result) {
        flash('success', 'Inventory Custodian Slip created successfully.');
    } else {
        flash('danger', 'Failed to create Inventory Custodian Slip.');
    }
    redirect('ics.php');
}

if (POSTACT('update')) {
    $id = (int)($_POST['id'] ?? 0);
    $ics_no = trim($_POST['ics_no'] ?? '');
    $property_no = trim($_POST['property_no'] ?? '');
    $value_type = $_POST['value_type'] ?? 'Low';
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $issued_by = (int)($_POST['issued_by'] ?? 0);
    $issued_date = $_POST['issued_date'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $remarks = $_POST['remarks'] ?? '';

    if ($id === 0) {
        flash('danger', 'ICS reference is required.');
        redirect('ics.php');
    }

    if ($ics_no === '' || $property_no === '' || $assigned_to === 0 || $issued_by === 0 || $issued_date === '') {
        flash('danger', 'ICS No., Property No., Assigned To, Issued By, and Issued Date are required.');
        redirect('ics.php');
    }

    if (!in_array($value_type, $allowedValueTypes, true)) {
        flash('danger', 'Invalid value type selected.');
        redirect('ics.php');
    }

    if (!in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('ics.php');
    }

    try {
        resolveEligibleAssignedOfficer($assigned_to);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('ics.php');
    }

    $existing = ICS::getByICSNo($ics_no);
    if ($existing && (int)$existing['id'] !== $id) {
        flash('danger', 'Another ICS already uses this number.');
        redirect('ics.php');
    }

    $result = ICS::update(
        $ics_no,
        $property_no,
        $value_type,
        $assigned_to,
        $issued_by,
        $issued_date,
        $status,
        $remarks,
        $id
    );

    if ($result) {
        flash('success', 'Inventory Custodian Slip updated successfully.');
    } else {
        flash('danger', 'Failed to update Inventory Custodian Slip.');
    }
    redirect('ics.php');
}

if (POSTACT('del')) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id === 0) {
        flash('danger', 'ICS reference is required for deletion.');
        redirect('ics.php');
    }

    $itemsModel = new ICS_Items();
    $linkedItems = $itemsModel->getAll($id);
    if (!empty($linkedItems)) {
        flash('danger', 'Remove all items attached to this ICS before deleting it.');
        redirect('ics.php');
    }

    $model = new ICS();
    $result = $model->deleteById($id);

    if ($result) {
        flash('success', 'Inventory Custodian Slip deleted successfully.');
    } else {
        flash('danger', 'Failed to delete Inventory Custodian Slip.');
    }
    redirect('ics.php');
}

if (POSTACT('addicsitem')) {
    $ics_id = (int)($_POST['ics_id'] ?? 0);
    $property_no = trim($_POST['property_no'] ?? '');
    $estimated_useful_life = trim($_POST['estimated_useful_life'] ?? '');
    $item_id = (int)($_POST['item_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    $unit_value = (float)($_POST['unit_value'] ?? 0);
    $date_acquired = $_POST['date_acquired'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if ($ics_id === 0 || $property_no === '' || $item_id === 0 || $qty <= 0 || $unit_value < 0 || $date_acquired === '') {
        flash('danger', 'All required fields must be completed to add an item.');
        redirect('ics_items.php?ics_id=' . $ics_id);
    }

    $icsModelCheck = new ICS();
    $icsHeader = $icsModelCheck->getById($ics_id);
    if (!$icsHeader) {
        flash('danger', 'ICS reference not found.');
        redirect('ics_items.php');
    }

    if ($icsHeader['status'] !== 'Active') {
        flash('danger', 'Only active slips can accept new items.');
        redirect('ics_items.php?ics_id=' . $ics_id);
    }

    $itemsModel = new ICS_Items();
    $existingProperty = $itemsModel->getByICSAndProperty($ics_id, $property_no);
    if ($existingProperty) {
        flash('danger', 'This property number is already listed under the selected ICS.');
        redirect('ics_items.php?ics_id=' . $ics_id);
    }

    $result = ICS_Items::add(
        $ics_id,
        $item_id,
        $property_no,
        $qty,
        $unit_value,
        $date_acquired,
        $remarks,
        $estimated_useful_life
    );

    if ($result) {
        flash('success', 'Item added to ICS successfully.');
    } else {
        flash('danger', 'Failed to attach item to ICS.');
    }
    redirect('ics_items.php?ics_id=' . $ics_id);
}

if (POSTACT('updateicsitem')) {
    $id = (int)($_POST['id'] ?? 0);
    $ics_id = (int)($_POST['ics_id'] ?? 0);
    $property_no = trim($_POST['property_no'] ?? '');
    $estimated_useful_life = trim($_POST['estimated_useful_life'] ?? '');
    $item_id = (int)($_POST['item_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    $unit_value = (float)($_POST['unit_value'] ?? 0);
    $date_acquired = $_POST['date_acquired'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if ($id === 0 || $ics_id === 0) {
        flash('danger', 'Item reference is required.');
        redirect('ics_items.php?ics_id=' . $ics_id);
    }

    if ($property_no === '' || $item_id === 0 || $qty <= 0 || $unit_value < 0 || $date_acquired === '') {
        flash('danger', 'All required fields must be completed to update an item.');
        redirect('ics_items.php?ics_id=' . $ics_id);
    }

    $icsModelCheck = new ICS();
    $icsHeader = $icsModelCheck->getById($ics_id);
    if (!$icsHeader) {
        flash('danger', 'ICS reference not found.');
        redirect('ics_items.php');
    }

    if ($icsHeader['status'] !== 'Active') {
        flash('danger', 'Only active slips can be modified.');
        redirect('ics_items.php?ics_id=' . $ics_id);
    }

    $itemsModel = new ICS_Items();
    $existingProperty = $itemsModel->getByICSAndProperty($ics_id, $property_no);
    if ($existingProperty && (int)$existingProperty['id'] !== $id) {
        flash('danger', 'Another item already uses this property number.');
        redirect('ics_items.php?ics_id=' . $ics_id);
    }

    $result = ICS_Items::update(
        $ics_id,
        $item_id,
        $property_no,
        $qty,
        $unit_value,
        $date_acquired,
        $remarks,
        $id,
        $estimated_useful_life
    );

    if ($result) {
        flash('success', 'ICS item updated successfully.');
    } else {
        flash('danger', 'Failed to update ICS item.');
    }
    redirect('ics_items.php?ics_id=' . $ics_id);
}

if (POSTACT('delicsitem')) {
    $id = (int)($_POST['id'] ?? 0);
    $ics_id = (int)($_POST['ics_id'] ?? 0);

    if ($id === 0 || $ics_id === 0) {
        flash('danger', 'Item reference is required for removal.');
        redirect('ics_items.php?ics_id=' . $ics_id);
    }

    $icsModelCheck = new ICS();
    $icsHeader = $icsModelCheck->getById($ics_id);
    if (!$icsHeader) {
        flash('danger', 'ICS reference not found.');
        redirect('ics_items.php');
    }

    if ($icsHeader['status'] !== 'Active') {
        flash('danger', 'Only active slips can be modified.');
        redirect('ics_items.php?ics_id=' . $ics_id);
    }

    $itemsModel = new ICS_Items();
    $result = $itemsModel->deleteById($id);

    if ($result) {
        flash('success', 'Item removed from ICS successfully.');
    } else {
        flash('danger', 'Failed to remove item from ICS.');
    }
    redirect('ics_items.php?ics_id=' . $ics_id);
}

redirect('ics.php');
