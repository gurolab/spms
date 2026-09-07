<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/PropertyCard.model.php';

$allowedStatuses = PropertyCard::STATUSES;

function resolveOfficeOfficerSelection(int $officeOfficerId): array
{
    if ($officeOfficerId <= 0) {
        throw new RuntimeException('Office / Officer selection is required.');
    }

    $userModel = new BaseModel('user_role_dept');
    $user = $userModel->getById($officeOfficerId);
    if (!is_array($user)) {
        throw new RuntimeException('Selected Office / Officer account was not found.');
    }

    $roleId = (int)($user['roleid'] ?? 0);
    $status = strtolower(trim((string)($user['status'] ?? '')));
    if ($roleId !== 6 || $status !== 'active') {
        throw new RuntimeException('Only active permanent employees can receive property/equipment.');
    }

    $label = formatOfficerNameWithDeptCode($user, $officeOfficerId);

    return [
        'id' => $officeOfficerId,
        'label' => $label,
    ];
}

function resolveEligibleAccountableOfficer(int $officerId): ?int
{
    if ($officerId <= 0) {
        return null;
    }

    $userModel = new BaseModel('user_role_dept');
    $user = $userModel->getById($officerId);
    if (!is_array($user)) {
        throw new RuntimeException('Selected Accountable Officer account was not found.');
    }

    $roleId = (int)($user['roleid'] ?? 0);
    $status = strtolower(trim((string)($user['status'] ?? '')));
    if ($roleId !== 6 || $status !== 'active') {
        throw new RuntimeException('Only active permanent employees can receive property/equipment.');
    }

    return $officerId;
}

function updateCardStatusFromHistory(int $cardId): void
{
    $latest = PropertyCardTransaction::getLatestByCard($cardId);
    if ($latest && isset($latest['status'])) {
        PropertyCard::updateStatus($cardId, $latest['status']);
    }
}

function recalculateCardLedger(int $cardId): void
{
    $rows = PropertyCardTransaction::getAllByCardAscending($cardId);
    $cardModel = new PropertyCard();
    $card = $cardModel->getById($cardId);
    $unitCost = (float)($card['acquisition_cost'] ?? 0);
    $runningBalance = 0;

    foreach ($rows as $row) {
        $receiptQty = max(0, (int)($row['receipt_qty'] ?? 0));
        $issueQty = max(0, (int)($row['issue_qty'] ?? 0));
        $runningBalance += ($receiptQty - $issueQty);
        if ($runningBalance < 0) {
            $runningBalance = 0;
        }

        $rowAmount = (float)($row['amount'] ?? 0);
        if ($rowAmount <= 0 && $unitCost > 0) {
            $rowAmount = $runningBalance * $unitCost;
        }

        PropertyCardTransaction::updateLedgerSnapshot((int)$row['id'], $runningBalance, $rowAmount);
    }
}

if (POSTACT('add')) {
    $card_no = trim($_POST['card_no'] ?? '');
    $par_id = (int)($_POST['par_id'] ?? 0);
    $item_id = (int)($_POST['item_id'] ?? 0);
    $property_tag = trim($_POST['property_tag'] ?? '');
    $accountable_officer = (int)($_POST['accountable_officer'] ?? 0);
    $location = $_POST['location'] ?? '';
    $acquisition_date = $_POST['acquisition_date'] ?? '';
    $acquisition_cost = $_POST['acquisition_cost'] ?? '';
    $current_status = $_POST['current_status'] ?? 'Assigned';
    $remarks = $_POST['remarks'] ?? '';

    if ($card_no === '' || $item_id === 0 || $property_tag === '') {
        flash('danger', 'Card No, Item, and Property Tag are required.');
        redirect('property_cards.php');
    }

    if (!in_array($current_status, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('property_cards.php');
    }

    if (PropertyCard::getByCardNo($card_no)) {
        flash('danger', 'Card number already exists.');
        redirect('property_cards.php');
    }

    if (PropertyCard::getByPropertyTag($property_tag)) {
        flash('danger', 'Property tag already has a card.');
        redirect('property_cards.php');
    }

    try {
        $accountable_officer = resolveEligibleAccountableOfficer($accountable_officer) ?? 0;
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('property_cards.php');
    }

    $result = PropertyCard::add(
        $card_no,
        $par_id ?: null,
        $item_id,
        $property_tag,
        $accountable_officer ?: null,
        $location,
        $acquisition_date ?: null,
        $acquisition_cost !== '' ? (float)$acquisition_cost : null,
        $current_status,
        $remarks
    );

    if ($result) {
        flash('success', 'Property card created successfully.');
    } else {
        flash('danger', 'Failed to create property card.');
    }
    redirect('property_cards.php');
}

if (POSTACT('update')) {
    $id = (int)($_POST['id'] ?? 0);
    $card_no = trim($_POST['card_no'] ?? '');
    $par_id = (int)($_POST['par_id'] ?? 0);
    $item_id = (int)($_POST['item_id'] ?? 0);
    $property_tag = trim($_POST['property_tag'] ?? '');
    $accountable_officer = (int)($_POST['accountable_officer'] ?? 0);
    $location = $_POST['location'] ?? '';
    $acquisition_date = $_POST['acquisition_date'] ?? '';
    $acquisition_cost = $_POST['acquisition_cost'] ?? '';
    $current_status = $_POST['current_status'] ?? 'Assigned';
    $remarks = $_POST['remarks'] ?? '';

    if ($id === 0) {
        flash('danger', 'Property card reference is required.');
        redirect('property_cards.php');
    }

    if ($card_no === '' || $item_id === 0 || $property_tag === '') {
        flash('danger', 'Card No, Item, and Property Tag are required.');
        redirect('property_cards.php');
    }

    if (!in_array($current_status, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('property_cards.php');
    }

    $existing = PropertyCard::getByCardNo($card_no);
    if ($existing && (int)$existing['id'] !== $id) {
        flash('danger', 'Another card already uses this number.');
        redirect('property_cards.php');
    }

    $existingTag = PropertyCard::getByPropertyTag($property_tag);
    if ($existingTag && (int)$existingTag['id'] !== $id) {
        flash('danger', 'Another card already uses this property tag.');
        redirect('property_cards.php');
    }

    try {
        $accountable_officer = resolveEligibleAccountableOfficer($accountable_officer) ?? 0;
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('property_cards.php');
    }

    $result = PropertyCard::update(
        $card_no,
        $par_id ?: null,
        $item_id,
        $property_tag,
        $accountable_officer ?: null,
        $location,
        $acquisition_date ?: null,
        $acquisition_cost !== '' ? (float)$acquisition_cost : null,
        $current_status,
        $remarks,
        $id
    );

    if ($result) {
        flash('success', 'Property card updated successfully.');
    } else {
        flash('danger', 'Failed to update property card.');
    }
    redirect('property_cards.php');
}

if (POSTACT('delete')) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id === 0) {
        flash('danger', 'Property card reference is required for deletion.');
        redirect('property_cards.php');
    }

    $txnModel = new PropertyCardTransaction();
    $transactions = $txnModel->getAll($id);
    if (!empty($transactions)) {
        flash('danger', 'Remove all transactions before deleting this card.');
        redirect('property_cards.php');
    }

    $model = new PropertyCard();
    $result = $model->deleteById($id);

    if ($result) {
        flash('success', 'Property card deleted successfully.');
    } else {
        flash('danger', 'Failed to delete property card.');
    }
    redirect('property_cards.php');
}

if (POSTACT('addtxn')) {
    $property_card_id = (int)($_POST['property_card_id'] ?? 0);
    $transaction_date = $_POST['transaction_date'] ?? '';
    $status = $_POST['status'] ?? '';
    $reference_no = $_POST['reference_no'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $performed_by = (int)($_POST['performed_by'] ?? 0);
    $receipt_qty = (int)($_POST['receipt_qty'] ?? 0);
    $issue_qty = (int)($_POST['issue_qty'] ?? 0);
    $office_officer_id = (int)($_POST['office_officer_id'] ?? 0);
    $amount_raw = trim((string)($_POST['amount'] ?? ''));
    $amount = $amount_raw === '' ? null : (float)$amount_raw;

    if ($property_card_id === 0 || $transaction_date === '' || $status === '') {
        flash('danger', 'Property card, transaction date, and status are required.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    if (!in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    if ($receipt_qty < 0 || $issue_qty < 0) {
        flash('danger', 'Receipt and issue quantities cannot be negative.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    if ($receipt_qty === 0 && $issue_qty === 0) {
        flash('danger', 'Provide either receipt quantity or issue/transfer/disposal quantity.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    if ($amount !== null && $amount < 0) {
        flash('danger', 'Amount cannot be negative.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    try {
        $officeOfficer = resolveOfficeOfficerSelection($office_officer_id);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    $result = PropertyCardTransaction::add(
        $property_card_id,
        $transaction_date,
        $status,
        $reference_no,
        $notes,
        $performed_by ?: null,
        $receipt_qty,
        $issue_qty,
        $officeOfficer['label'],
        $officeOfficer['id'],
        null,
        $amount
    );

    if ($result) {
        recalculateCardLedger($property_card_id);
        PropertyCard::updateStatus($property_card_id, $status);
        flash('success', 'Transaction added successfully.');
    } else {
        flash('danger', 'Failed to add transaction.');
    }
    redirect('property_card_transactions.php?card_id=' . $property_card_id);
}

if (POSTACT('updatetxn')) {
    $id = (int)($_POST['id'] ?? 0);
    $property_card_id = (int)($_POST['property_card_id'] ?? 0);
    $transaction_date = $_POST['transaction_date'] ?? '';
    $status = $_POST['status'] ?? '';
    $reference_no = $_POST['reference_no'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $performed_by = (int)($_POST['performed_by'] ?? 0);
    $receipt_qty = (int)($_POST['receipt_qty'] ?? 0);
    $issue_qty = (int)($_POST['issue_qty'] ?? 0);
    $office_officer_id = (int)($_POST['office_officer_id'] ?? 0);
    $amount_raw = trim((string)($_POST['amount'] ?? ''));
    $amount = $amount_raw === '' ? null : (float)$amount_raw;

    if ($id === 0 || $property_card_id === 0) {
        flash('danger', 'Transaction reference is required.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    if ($transaction_date === '' || $status === '') {
        flash('danger', 'Transaction date and status are required.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    if (!in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    if ($receipt_qty < 0 || $issue_qty < 0) {
        flash('danger', 'Receipt and issue quantities cannot be negative.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    if ($receipt_qty === 0 && $issue_qty === 0) {
        flash('danger', 'Provide either receipt quantity or issue/transfer/disposal quantity.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    if ($amount !== null && $amount < 0) {
        flash('danger', 'Amount cannot be negative.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    try {
        $officeOfficer = resolveOfficeOfficerSelection($office_officer_id);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    $result = PropertyCardTransaction::update(
        $id,
        $property_card_id,
        $transaction_date,
        $status,
        $reference_no,
        $notes,
        $performed_by ?: null,
        $receipt_qty,
        $issue_qty,
        $officeOfficer['label'],
        $officeOfficer['id'],
        null,
        $amount
    );

    if ($result) {
        recalculateCardLedger($property_card_id);
        updateCardStatusFromHistory($property_card_id);
        flash('success', 'Transaction updated successfully.');
    } else {
        flash('danger', 'Failed to update transaction.');
    }
    redirect('property_card_transactions.php?card_id=' . $property_card_id);
}

if (POSTACT('deltxn')) {
    $id = (int)($_POST['id'] ?? 0);
    $property_card_id = (int)($_POST['property_card_id'] ?? 0);

    if ($id === 0 || $property_card_id === 0) {
        flash('danger', 'Transaction reference is required for deletion.');
        redirect('property_card_transactions.php?card_id=' . $property_card_id);
    }

    $model = new PropertyCardTransaction();
    $result = $model->deleteById($id);

    if ($result) {
        recalculateCardLedger($property_card_id);
        updateCardStatusFromHistory($property_card_id);
        flash('success', 'Transaction deleted successfully.');
    } else {
        flash('danger', 'Failed to delete transaction.');
    }
    redirect('property_card_transactions.php?card_id=' . $property_card_id);
}

redirect('property_cards.php');
