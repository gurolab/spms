<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/PropertyTransfer.model.php';
require_once __DIR__ . '/../repo/PropertyCard.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/PAR.model.php';

$allowedStatuses = PropertyTransfer::STATUSES;
$allowedTypes = PropertyTransfer::TYPES;

function resolveItemIdForTag(string $propertyTag): ?int
{
    $cardModel = new PropertyCard();
    foreach ($cardModel->getAll() as $card) {
        if (isset($card['property_tag']) && $card['property_tag'] === $propertyTag) {
            return (int)$card['item_id'];
        }
    }
    return null;
}

function fetchTransferById(int $id): ?array
{
    $model = new PropertyTransfer();
    $row = $model->getById($id);
    return is_array($row) ? $row : null;
}

function fetchDepartmentNameById(int $departmentId): ?string
{
    if ($departmentId <= 0) {
        return null;
    }

    $model = new BaseModel('departments');
    $row = $model->getById($departmentId);
    if (!is_array($row)) {
        return null;
    }

    $name = trim((string)($row['name'] ?? ''));
    return $name !== '' ? $name : null;
}

function fetchOfficeOfficerLabelById(int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }

    $model = new BaseModel('user_role_dept');
    $user = $model->getById($userId);
    if (!is_array($user)) {
        return null;
    }

    $label = formatOfficerNameWithDeptCode($user, $userId);
    return $label !== '' ? $label : null;
}

function resolveEligibleRecipientOfficer(int $userId, bool $required = false): ?array
{
    if ($userId <= 0) {
        if ($required) {
            throw new RuntimeException('To Officer is required.');
        }
        return null;
    }

    $model = new BaseModel('user_role_dept');
    $user = $model->getById($userId);
    if (!is_array($user)) {
        throw new RuntimeException('Selected To Officer account was not found.');
    }

    $roleId = (int)($user['roleid'] ?? 0);
    $status = strtolower(trim((string)($user['status'] ?? '')));
    if ($roleId !== 6 || $status !== 'active') {
        throw new RuntimeException('Only active permanent employees can receive property/equipment.');
    }

    return $user;
}

function fetchSourceParItem(PDO $db, string $propertyTag, ?int $preferredParId = null): ?array
{
    if ($preferredParId !== null && $preferredParId > 0) {
        $stmt = $db->prepare(
            "SELECT
                pi.id AS par_item_id,
                pi.par_id,
                pi.item_id,
                pi.property_no,
                pi.qty,
                pi.unit_value,
                pi.acquisition_date,
                pi.description,
                pi.remarks AS item_remarks,
                ph.fund_cluster,
                ph.issued_by,
                ph.remarks AS par_remarks
             FROM par_items pi
             INNER JOIN property_acknowledgment_receipts ph ON ph.id = pi.par_id
             WHERE pi.par_id = ? AND pi.property_no = ?
             LIMIT 1"
        );
        $stmt->execute([$preferredParId, $propertyTag]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    $stmt = $db->prepare(
        "SELECT
            pi.id AS par_item_id,
            pi.par_id,
            pi.item_id,
            pi.property_no,
            pi.qty,
            pi.unit_value,
            pi.acquisition_date,
            pi.description,
            pi.remarks AS item_remarks,
            ph.fund_cluster,
            ph.issued_by,
            ph.remarks AS par_remarks
         FROM par_items pi
         INNER JOIN property_acknowledgment_receipts ph ON ph.id = pi.par_id
         WHERE pi.property_no = ?
         ORDER BY ph.issue_date DESC, pi.id DESC
         LIMIT 1"
    );
    $stmt->execute([$propertyTag]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fetchTargetParByOfficerAndProperty(PDO $db, int $officerId, string $propertyTag): ?array
{
    if ($officerId <= 0 || $propertyTag === '') {
        return null;
    }

    $stmt = $db->prepare(
        "SELECT ph.*
         FROM property_acknowledgment_receipts ph
         INNER JOIN par_items pi ON pi.par_id = ph.id
         WHERE ph.accountable_officer = ? AND ph.status = 'Active' AND pi.property_no = ?
         ORDER BY ph.issue_date DESC, ph.id DESC
         LIMIT 1"
    );
    $stmt->execute([$officerId, $propertyTag]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }

    $stmt = $db->prepare(
        "SELECT ph.*
         FROM property_acknowledgment_receipts ph
         INNER JOIN par_items pi ON pi.par_id = ph.id
         WHERE ph.accountable_officer = ? AND pi.property_no = ?
         ORDER BY ph.issue_date DESC, ph.id DESC
         LIMIT 1"
    );
    $stmt->execute([$officerId, $propertyTag]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function countParItems(PDO $db, int $parId): int
{
    if ($parId <= 0) {
        return 0;
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM par_items WHERE par_id = ?");
    $stmt->execute([$parId]);
    return (int)$stmt->fetchColumn();
}

function cardMovementExists(PDO $db, int $cardId, string $referenceNo): bool
{
    if ($cardId <= 0 || $referenceNo === '') {
        return false;
    }

    $stmt = $db->prepare(
        "SELECT id FROM property_card_transactions WHERE property_card_id = ? AND reference_no = ? LIMIT 1"
    );
    $stmt->execute([$cardId, $referenceNo]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function recalculateTransferCardLedger(int $cardId): void
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

function autoApplyCompletedTransfer(int $transferId): array
{
    $transfer = fetchTransferById($transferId);
    if (!$transfer) {
        return ['ok' => false, 'message' => 'Transfer reference not found for automation.'];
    }

    $transferNo = trim((string)($transfer['transfer_no'] ?? ''));
    $propertyTag = trim((string)($transfer['property_tag'] ?? ''));
    $transferDate = trim((string)($transfer['transfer_date'] ?? ''));
    $toOfficer = (int)($transfer['to_officer'] ?? 0);
    $fromOfficer = (int)($transfer['from_officer'] ?? 0);
    $toDepartment = (int)($transfer['to_department'] ?? 0);

    if ($transferNo === '' || $propertyTag === '') {
        return ['ok' => false, 'message' => 'Transfer number and property tag are required for automation.'];
    }

    if ($toOfficer <= 0) {
        return ['ok' => false, 'message' => 'To Officer is required to auto-generate accountability records.'];
    }

    try {
        resolveEligibleRecipientOfficer($toOfficer, true);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }

    if ($transferDate === '') {
        $transferDate = date('Y-m-d');
    }

    $card = PropertyCard::getByPropertyTag($propertyTag);
    if (!$card) {
        return ['ok' => false, 'message' => 'Property card not found for the selected property tag.'];
    }

    $db = Model::Db();

    try {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
        }

        $sourceParId = isset($card['par_id']) ? (int)$card['par_id'] : 0;
        $sourceItem = fetchSourceParItem($db, $propertyTag, $sourceParId > 0 ? $sourceParId : null);
        $targetPar = fetchTargetParByOfficerAndProperty($db, $toOfficer, $propertyTag);

        if (!$targetPar) {
            $sourceFundCluster = trim((string)($sourceItem['fund_cluster'] ?? ''));
            if ($sourceFundCluster === '') {
                $sourceFundCluster = 'N/A';
            }

            $issuedBy = (int)($transfer['prepared_by'] ?? 0);
            if ($issuedBy <= 0) {
                $issuedBy = (int)(getUser('id') ?? 0);
            }
            if ($issuedBy <= 0) {
                $issuedBy = (int)($sourceItem['issued_by'] ?? 0);
            }
            if ($issuedBy <= 0) {
                $issuedBy = $toOfficer;
            }

            $newParRemarks = 'Auto-created from transfer ' . $transferNo . '.';
            $newParId = PAR::add(
                PAR::getNextPARNo(),
                $sourceFundCluster,
                $toOfficer,
                $issuedBy,
                $transferDate,
                'Active',
                $newParRemarks
            );

            if (!$newParId) {
                throw new RuntimeException('Unable to create auto-generated PAR.');
            }

            $parModel = new PAR();
            $targetPar = $parModel->getById((int)$newParId);
        }

        if (!$targetPar || !isset($targetPar['id'])) {
            throw new RuntimeException('Unable to resolve target PAR for transfer automation.');
        }

        $targetParId = (int)$targetPar['id'];
        $movementQty = 1;

        if ($sourceItem) {
            $sourceParId = (int)($sourceItem['par_id'] ?? 0);
            $sourceParItemId = (int)($sourceItem['par_item_id'] ?? 0);
            $sourceItemId = (int)($sourceItem['item_id'] ?? 0);
            $sourceQty = max(1, (int)($sourceItem['qty'] ?? 1));
            $sourceUnitValue = (float)($sourceItem['unit_value'] ?? ($card['acquisition_cost'] ?? 0));
            $sourceAcqDate = trim((string)($sourceItem['acquisition_date'] ?? ''));
            if ($sourceAcqDate === '') {
                $sourceAcqDate = $transferDate;
            }
            $sourceDescription = isset($sourceItem['description']) ? (string)$sourceItem['description'] : null;
            $sourceRemarks = isset($sourceItem['item_remarks']) ? (string)$sourceItem['item_remarks'] : null;

            $movementQty = $sourceQty;

            if ($sourceParItemId > 0 && $sourceParId !== $targetParId) {
                $targetParItem = PAR_Items::getByParAndProperty($targetParId, $propertyTag);
                if ($targetParItem) {
                    $targetUpdated = PAR_Items::update(
                        $targetParId,
                        $sourceItemId,
                        $propertyTag,
                        $sourceQty,
                        $sourceUnitValue,
                        $sourceAcqDate,
                        $sourceDescription,
                        $sourceRemarks,
                        (int)$targetParItem['id']
                    );

                    if (!$targetUpdated) {
                        throw new RuntimeException('Unable to sync existing target PAR item for transfer.');
                    }

                    $sourceParItemsModel = new PAR_Items();
                    if (!$sourceParItemsModel->deleteById($sourceParItemId)) {
                        throw new RuntimeException('Unable to remove source PAR item after transfer sync.');
                    }
                } else {
                    $updated = PAR_Items::update(
                        $targetParId,
                        $sourceItemId,
                        $propertyTag,
                        $sourceQty,
                        $sourceUnitValue,
                        $sourceAcqDate,
                        $sourceDescription,
                        $sourceRemarks,
                        $sourceParItemId
                    );

                    if (!$updated) {
                        throw new RuntimeException('Unable to move property item to the target PAR.');
                    }
                }

                $sourceItemCount = $sourceParId > 0 ? countParItems($db, $sourceParId) : 0;
                if ($sourceParId > 0 && $sourceItemCount === 0) {
                    $sourceRemarksText = trim((string)$sourceRemarks);
                    $historySuffix = 'Transferred/relieved via ' . $transferNo . '.';
                    $historyRemarks = $sourceRemarksText !== ''
                        ? ($sourceRemarksText . ' | ' . $historySuffix)
                        : $historySuffix;

                    $snapshotAdded = PAR_Items::add(
                        $sourceParId,
                        $sourceItemId,
                        $propertyTag,
                        $sourceQty,
                        $sourceUnitValue,
                        $sourceAcqDate,
                        $sourceDescription,
                        $historyRemarks
                    );

                    if (!$snapshotAdded) {
                        throw new RuntimeException('Unable to create source PAR history snapshot.');
                    }

                    if (!PAR::updateStatus('Transferred', $sourceParId)) {
                        throw new RuntimeException('Unable to update source PAR status.');
                    }
                }
            }
        } else {
            $targetParItem = PAR_Items::getByParAndProperty($targetParId, $propertyTag);
            if ($targetParItem) {
                $movementQty = max(1, (int)($targetParItem['qty'] ?? 1));
            } else {
                $itemId = (int)($card['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = (int)($transfer['item_id'] ?? 0);
                }
                if ($itemId <= 0) {
                    throw new RuntimeException('Unable to determine item id for auto-generated PAR item.');
                }

                $itemModel = new Item();
                $itemInfo = $itemModel->getById($itemId);
                $itemDescription = is_array($itemInfo) ? (string)($itemInfo['description'] ?? '') : '';
                $acquisitionDate = trim((string)($card['acquisition_date'] ?? ''));
                if ($acquisitionDate === '') {
                    $acquisitionDate = $transferDate;
                }

                $addedParItem = PAR_Items::add(
                    $targetParId,
                    $itemId,
                    $propertyTag,
                    1,
                    (float)($card['acquisition_cost'] ?? 0),
                    $acquisitionDate,
                    $itemDescription,
                    'Auto-generated from transfer ' . $transferNo . '.'
                );

                if (!$addedParItem) {
                    throw new RuntimeException('Unable to create auto-generated PAR item.');
                }
            }
        }

        $location = isset($card['location']) ? (string)$card['location'] : null;
        $departmentName = fetchDepartmentNameById($toDepartment);
        if ($departmentName !== null) {
            $location = $departmentName;
        }

        $cardStatus = trim((string)($card['current_status'] ?? 'Assigned'));
        if (!in_array($cardStatus, PropertyCard::STATUSES, true)) {
            $cardStatus = 'Assigned';
        }

        $cardUpdated = PropertyCard::update(
            (string)$card['card_no'],
            $targetParId,
            (int)$card['item_id'],
            $propertyTag,
            $toOfficer,
            $location,
            !empty($card['acquisition_date']) ? (string)$card['acquisition_date'] : null,
            isset($card['acquisition_cost']) && $card['acquisition_cost'] !== '' ? (float)$card['acquisition_cost'] : null,
            $cardStatus,
            isset($card['remarks']) ? (string)$card['remarks'] : null,
            (int)$card['id']
        );

        if (!$cardUpdated) {
            throw new RuntimeException('Unable to update property card accountability.');
        }

        if (!cardMovementExists($db, (int)$card['id'], $transferNo)) {
            $movementNotes = 'Auto transfer posting from officer #' . ($fromOfficer > 0 ? $fromOfficer : 0)
                . ' to officer #' . $toOfficer . '.';
            $reason = trim((string)($transfer['reason'] ?? ''));
            if ($reason !== '') {
                $movementNotes = $reason . ' | ' . $movementNotes;
            }

            $movementAmount = null;
            if (isset($card['acquisition_cost']) && $card['acquisition_cost'] !== '') {
                $movementAmount = (float)$card['acquisition_cost'] * max(1, $movementQty);
            }
            $officeOfficerLabel = fetchOfficeOfficerLabelById($toOfficer) ?? 'Transfer';

            $movementId = PropertyCardTransaction::add(
                (int)$card['id'],
                $transferDate,
                $cardStatus,
                $transferNo,
                $movementNotes,
                (int)(getUser('id') ?? 0) ?: null,
                max(1, $movementQty),
                max(1, $movementQty),
                $officeOfficerLabel,
                $toOfficer > 0 ? $toOfficer : null,
                null,
                $movementAmount
            );

            if (!$movementId) {
                throw new RuntimeException('Unable to add property card movement for transfer.');
            }

            recalculateTransferCardLedger((int)$card['id']);
        }

        if ($db->inTransaction()) {
            $db->commit();
        }

        writeAuditLog('property_transfer.auto_apply.success', 'property_transfers', $transferId, [], [
            'transfer_no' => $transferNo,
            'property_tag' => $propertyTag,
            'to_officer' => $toOfficer,
            'target_par_id' => $targetParId,
            'property_card_id' => (int)$card['id'],
        ]);

        return ['ok' => true, 'message' => 'Transfer automation applied successfully.'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        writeAuditLog('property_transfer.auto_apply.failed', 'property_transfers', $transferId, [], [
            'error' => $e->getMessage(),
            'transfer_no' => $transferNo,
            'property_tag' => $propertyTag,
        ]);

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

if (POSTACT('add')) {
    $transfer_no = trim($_POST['transfer_no'] ?? '');
    $transfer_date = $_POST['transfer_date'] ?? '';
    $property_tag = trim($_POST['property_tag'] ?? '');
    $item_id = (int)($_POST['item_id'] ?? 0);
    $from_officer = (int)($_POST['from_officer'] ?? 0);
    $to_officer = (int)($_POST['to_officer'] ?? 0);
    $from_department = (int)($_POST['from_department'] ?? 0);
    $to_department = (int)($_POST['to_department'] ?? 0);
    $transfer_type = $_POST['transfer_type'] ?? 'Department';
    $reason = $_POST['reason'] ?? '';
    $prepared_by = (int)($_POST['prepared_by'] ?? 0);
    $noted_by = (int)($_POST['noted_by'] ?? 0);
    $acknowledged_by = (int)($_POST['acknowledged_by'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';

    if ($transfer_no === '' || $transfer_date === '' || $property_tag === '') {
        flash('danger', 'Transfer No, Date, and Property Tag are required.');
        redirect('property_transfers.php');
    }

    if (!in_array($transfer_type, $allowedTypes, true)) {
        flash('danger', 'Invalid transfer type selected.');
        redirect('property_transfers.php');
    }

    if (!in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Invalid transfer status selected.');
        redirect('property_transfers.php');
    }

    if ($status === 'Completed' && $to_officer === 0) {
        flash('danger', 'To Officer is required when marking a transfer as Completed.');
        redirect('property_transfers.php');
    }

    try {
        resolveEligibleRecipientOfficer($to_officer);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('property_transfers.php');
    }

    if (PropertyTransfer::getByTransferNo($transfer_no)) {
        flash('danger', 'Transfer number already exists.');
        redirect('property_transfers.php');
    }

    if ($item_id === 0) {
        $resolvedItem = resolveItemIdForTag($property_tag);
        if ($resolvedItem !== null) {
            $item_id = $resolvedItem;
        }
    }

    if ($item_id === 0) {
        flash('danger', 'Unable to determine the item associated with the selected property tag.');
        redirect('property_transfers.php');
    }

    $result = PropertyTransfer::add(
        $transfer_no,
        $transfer_date,
        $property_tag,
        $item_id,
        $from_officer ?: null,
        $to_officer ?: null,
        $from_department ?: null,
        $to_department ?: null,
        $transfer_type,
        $reason,
        $prepared_by ?: null,
        $noted_by ?: null,
        $acknowledged_by ?: null,
        $status
    );

    if ($result) {
        if ($status === 'Completed') {
            $auto = autoApplyCompletedTransfer((int)$result);
            if ($auto['ok']) {
                flash('success', 'Property transfer logged and accountability records were auto-updated.');
            } else {
                flash('danger', 'Property transfer logged, but auto-update failed: ' . $auto['message']);
            }
        } else {
            flash('success', 'Property transfer logged successfully.');
        }
    } else {
        flash('danger', 'Failed to log property transfer.');
    }
    redirect('property_transfers.php');
}

if (POSTACT('update')) {
    $id = (int)($_POST['id'] ?? 0);
    $transfer_no = trim($_POST['transfer_no'] ?? '');
    $transfer_date = $_POST['transfer_date'] ?? '';
    $property_tag = trim($_POST['property_tag'] ?? '');
    $item_id = (int)($_POST['item_id'] ?? 0);
    $from_officer = (int)($_POST['from_officer'] ?? 0);
    $to_officer = (int)($_POST['to_officer'] ?? 0);
    $from_department = (int)($_POST['from_department'] ?? 0);
    $to_department = (int)($_POST['to_department'] ?? 0);
    $transfer_type = $_POST['transfer_type'] ?? 'Department';
    $reason = $_POST['reason'] ?? '';
    $prepared_by = (int)($_POST['prepared_by'] ?? 0);
    $noted_by = (int)($_POST['noted_by'] ?? 0);
    $acknowledged_by = (int)($_POST['acknowledged_by'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';

    if ($id === 0) {
        flash('danger', 'Transfer reference is required.');
        redirect('property_transfers.php');
    }

    if ($transfer_no === '' || $transfer_date === '' || $property_tag === '') {
        flash('danger', 'Transfer No, Date, and Property Tag are required.');
        redirect('property_transfers.php');
    }

    if (!in_array($transfer_type, $allowedTypes, true)) {
        flash('danger', 'Invalid transfer type selected.');
        redirect('property_transfers.php');
    }

    if (!in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Invalid transfer status selected.');
        redirect('property_transfers.php');
    }

    if ($status === 'Completed' && $to_officer === 0) {
        flash('danger', 'To Officer is required when marking a transfer as Completed.');
        redirect('property_transfers.php');
    }

    try {
        resolveEligibleRecipientOfficer($to_officer);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('property_transfers.php');
    }

    $existing = PropertyTransfer::getByTransferNo($transfer_no);
    if ($existing && (int)$existing['id'] !== $id) {
        flash('danger', 'Another record already uses this transfer number.');
        redirect('property_transfers.php');
    }

    if ($item_id === 0) {
        $resolvedItem = resolveItemIdForTag($property_tag);
        if ($resolvedItem !== null) {
            $item_id = $resolvedItem;
        }
    }

    if ($item_id === 0) {
        flash('danger', 'Unable to determine the item associated with the selected property tag.');
        redirect('property_transfers.php');
    }

    $result = PropertyTransfer::update(
        $transfer_no,
        $transfer_date,
        $property_tag,
        $item_id,
        $from_officer ?: null,
        $to_officer ?: null,
        $from_department ?: null,
        $to_department ?: null,
        $transfer_type,
        $reason,
        $prepared_by ?: null,
        $noted_by ?: null,
        $acknowledged_by ?: null,
        $status,
        $id
    );

    if ($result) {
        if ($status === 'Completed') {
            $auto = autoApplyCompletedTransfer($id);
            if ($auto['ok']) {
                flash('success', 'Property transfer updated and accountability records were auto-updated.');
            } else {
                flash('danger', 'Property transfer updated, but auto-update failed: ' . $auto['message']);
            }
        } else {
            flash('success', 'Property transfer updated successfully.');
        }
    } else {
        flash('danger', 'Failed to update property transfer.');
    }
    redirect('property_transfers.php');
}

if (POSTACT('delete')) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id === 0) {
        flash('danger', 'Transfer reference is required for deletion.');
        redirect('property_transfers.php');
    }

    $model = new PropertyTransfer();
    $result = $model->deleteById($id);

    if ($result) {
        flash('success', 'Property transfer removed successfully.');
    } else {
        flash('danger', 'Failed to delete property transfer.');
    }
    redirect('property_transfers.php');
}

if (POSTACT('updatestatus')) {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';

    if ($id === 0 || !in_array($status, $allowedStatuses, true)) {
        flash('danger', 'Valid transfer reference and status are required.');
        redirect('property_transfers.php');
    }

    $transfer = fetchTransferById($id);
    if (!$transfer) {
        flash('danger', 'Transfer reference not found.');
        redirect('property_transfers.php');
    }

    if ($status === 'Completed' && (int)($transfer['to_officer'] ?? 0) === 0) {
        flash('danger', 'To Officer is required before setting this transfer to Completed.');
        redirect('property_transfers.php');
    }

    if ($status === 'Completed') {
        try {
            resolveEligibleRecipientOfficer((int)($transfer['to_officer'] ?? 0), true);
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
            redirect('property_transfers.php');
        }
    }

    $result = PropertyTransfer::updateStatus($id, $status);

    if ($result) {
        if ($status === 'Completed') {
            $auto = autoApplyCompletedTransfer($id);
            if ($auto['ok']) {
                flash('success', 'Transfer status updated and accountability records were auto-updated.');
            } else {
                flash('danger', 'Transfer status updated, but auto-update failed: ' . $auto['message']);
            }
        } else {
            flash('success', 'Transfer status updated successfully.');
        }
    } else {
        flash('danger', 'Failed to update transfer status.');
    }
    redirect('property_transfers.php');
}

redirect('property_transfers.php');
