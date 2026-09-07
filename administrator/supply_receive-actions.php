<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/Supply_Receive.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/SupplyLedger.model.php';

function supplyReceiveListUrl(): string
{
    if (isSupplyOfficer()) {
        return BASE_URL . 'supplyofficer/receive.php';
    }
    return BASE_URL . 'administrator/supply_receive.php';
}

function supplyReceiveItemsUrl($receiptHeaderId = null): string
{
    $base = isSupplyOfficer()
        ? BASE_URL . 'supplyofficer/supply_receive_items.php'
        : BASE_URL . 'administrator/supply_receive_items.php';

    if ($receiptHeaderId === null || $receiptHeaderId === '') {
        return $base;
    }

    return $base . '?receipt_header_id=' . urlencode((string)$receiptHeaderId);
}

function logSupplyReceiveAction(string $action, string $entity, $entityId = null, array $metadata = [], array $details = []): void
{
    writeAuditLog($action, $entity, $entityId, $metadata, $details);
}

function syncSupplyReceiptReferences(int $receiptHeaderId, string $receiptNo): array
{
    if ($receiptHeaderId <= 0) {
        return ['ledger' => 0, 'stock' => 0];
    }

    $ledgerUpdated = Supply_Ledger_Entry::syncReferenceNoForSupplyReceipt($receiptHeaderId, $receiptNo);
    $stockUpdated = StockTransaction::syncReferenceNoForSupplyReceipt($receiptHeaderId, $receiptNo);

    return ['ledger' => $ledgerUpdated, 'stock' => $stockUpdated];
}

function normalizePrefixedDocumentNo(string $value, string $prefix): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/^' . preg_quote($prefix, '/') . '[-\s]*/i', '', $value);
    $value = trim((string)$value);

    if ($value === '') {
        return strtoupper($prefix);
    }

    return strtoupper($prefix . '-' . $value);
}

function normalizeReceiptNoByMode(string $receiptNo, string $mode): string
{
    $receiptNo = trim($receiptNo);
    if ($receiptNo === '') {
        return '';
    }

    $core = preg_replace('/^(DR|SI)[-\s]*/i', '', $receiptNo);
    $core = trim((string)$core);

    if ($core === '') {
        return strtoupper($mode);
    }

    return strtoupper($mode . '-' . $core);
}

/**
 * Validates DR/SI mode and normalizes doc numbers + receipt no prefix.
 * Returns [mode|null, error|null].
 */
function validateDeliveryModeAndReceipt(array &$payload): array
{
    $payload['dr_no'] = normalizePrefixedDocumentNo((string)($payload['dr_no'] ?? ''), 'DR');
    $payload['si_no'] = normalizePrefixedDocumentNo((string)($payload['si_no'] ?? ''), 'SI');

    $hasDr = $payload['dr_no'] !== '';
    $hasSi = $payload['si_no'] !== '';

    if ($hasDr && $hasSi) {
        return [null, 'Select only one delivery mode: DR or SI.'];
    }

    if (!$hasDr && !$hasSi) {
        return [null, 'Either DR No or SI No is required.'];
    }

    $mode = $hasDr ? 'DR' : 'SI';

    if ($mode === 'DR') {
        $payload['si_no'] = '';
    } else {
        $payload['dr_no'] = '';
    }

    $payload['receipt_no'] = normalizeReceiptNoByMode((string)($payload['receipt_no'] ?? ''), $mode);
    if ($payload['receipt_no'] === '') {
        return [null, 'Receipt No is required.'];
    }

    return [$mode, null];
}

if (POSTACT('add')) {
    // receipt_no, pr_no, po_no, dr_no, si_no, iar_no, supplier_id, receipt_date, received_by, inspected_by, notes
    $payload = [
        'receipt_no' => trim((string)($_POST['receipt_no'] ?? '')),
        'pr_no' => trim((string)($_POST['pr_no'] ?? '')),
        'po_no' => trim((string)($_POST['po_no'] ?? '')),
        'dr_no' => trim((string)($_POST['dr_no'] ?? '')),
        'si_no' => trim((string)($_POST['si_no'] ?? '')),
        'iar_no' => trim((string)($_POST['iar_no'] ?? '')),
        'supplier_id' => (int)($_POST['supplier_id'] ?? 0),
        'receipt_date' => trim((string)($_POST['receipt_date'] ?? '')),
        'received_by' => (int)(isSupplyOfficer() ? (string)(getUser('id') ?? '') : ($_POST['received_by'] ?? 0)),
        'inspected_by' => (int)($_POST['inspected_by'] ?? 0),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ];

    if ($payload['inspected_by'] <= 0) {
        $payload['inspected_by'] = $payload['received_by'];
    }

    [$mode, $modeError] = validateDeliveryModeAndReceipt($payload);
    if ($modeError !== null) {
        logSupplyReceiveAction('supply.receive.header.create.failed', 'supply_receipts', null, [
            'reason' => 'validation',
            'message' => $modeError,
        ]);
        flash('danger', $modeError);
        redirect(supplyReceiveListUrl());
        exit;
    }

    if (
        $payload['receipt_no'] === ''
        || $payload['supplier_id'] <= 0
        || $payload['receipt_date'] === ''
        || $payload['received_by'] <= 0
    ) {
        logSupplyReceiveAction('supply.receive.header.create.failed', 'supply_receipts', null, [
            'reason' => 'validation',
            'message' => 'Required fields missing.',
        ]);
        flash('danger', 'Receipt No, Supplier, Receipt Date, and Received By are required.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    if (
        strlen($payload['receipt_no']) > 250
        || strlen($payload['pr_no']) > 100
        || strlen($payload['po_no']) > 100
        || strlen($payload['dr_no']) > 100
        || strlen($payload['si_no']) > 100
        || strlen($payload['iar_no']) > 100
    ) {
        logSupplyReceiveAction('supply.receive.header.create.failed', 'supply_receipts', null, [
            'reason' => 'validation',
            'message' => 'Field length violation.',
        ]);
        flash('danger', 'Receipt/PR/PO/DR/SI/IAR values exceed allowed length.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    $headerModel = new Supply_Receive_Header();
    $res = $headerModel::add(
        $payload['receipt_no'],
        $payload['pr_no'],
        $payload['po_no'],
        $payload['dr_no'],
        $payload['si_no'],
        $payload['iar_no'],
        $payload['supplier_id'],
        $payload['receipt_date'],
        $payload['received_by'],
        $payload['inspected_by'],
        $payload['notes']
    );

    if ($res) {
        logSupplyReceiveAction('supply.receive.header.create.success', 'supply_receipts', (int)$res, [
            'delivery_mode' => $mode,
        ], [
            'receipt_no' => ['old' => null, 'new' => $payload['receipt_no']],
            'pr_no' => ['old' => null, 'new' => $payload['pr_no']],
            'po_no' => ['old' => null, 'new' => $payload['po_no']],
            'dr_no' => ['old' => null, 'new' => $payload['dr_no']],
            'si_no' => ['old' => null, 'new' => $payload['si_no']],
            'iar_no' => ['old' => null, 'new' => $payload['iar_no']],
            'supplier_id' => ['old' => null, 'new' => $payload['supplier_id']],
            'receipt_date' => ['old' => null, 'new' => $payload['receipt_date']],
            'received_by' => ['old' => null, 'new' => $payload['received_by']],
            'inspected_by' => ['old' => null, 'new' => $payload['inspected_by']],
            'notes' => ['old' => null, 'new' => $payload['notes']],
        ]);
        flash('success', 'Supply receipt added successfully');
    } else {
        logSupplyReceiveAction('supply.receive.header.create.failed', 'supply_receipts', null, [
            'reason' => 'database',
            'delivery_mode' => $mode,
        ]);
        flash('danger', 'Failed to add supply receipt');
    }

    redirect(supplyReceiveListUrl());
    exit;
}

if (POSTACT('update')) {
    // receipt_no, pr_no, po_no, dr_no, si_no, iar_no, supplier_id, receipt_date, received_by, inspected_by, notes
    $id = (int)($_POST['id'] ?? 0);
    $payload = [
        'receipt_no' => trim((string)($_POST['receipt_no'] ?? '')),
        'pr_no' => trim((string)($_POST['pr_no'] ?? '')),
        'po_no' => trim((string)($_POST['po_no'] ?? '')),
        'dr_no' => trim((string)($_POST['dr_no'] ?? '')),
        'si_no' => trim((string)($_POST['si_no'] ?? '')),
        'iar_no' => trim((string)($_POST['iar_no'] ?? '')),
        'supplier_id' => (int)($_POST['supplier_id'] ?? 0),
        'receipt_date' => trim((string)($_POST['receipt_date'] ?? '')),
        'received_by' => (int)(isSupplyOfficer() ? (string)(getUser('id') ?? '') : ($_POST['received_by'] ?? 0)),
        'inspected_by' => (int)($_POST['inspected_by'] ?? 0),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ];

    if ($payload['inspected_by'] <= 0) {
        $payload['inspected_by'] = $payload['received_by'];
    }

    if ($id <= 0) {
        logSupplyReceiveAction('supply.receive.header.update.failed', 'supply_receipts', null, [
            'reason' => 'validation',
            'message' => 'Missing receipt header id.',
        ]);
        flash('danger', 'Receipt ID is required.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    $headerModel = new Supply_Receive_Header();
    $existing = $headerModel->getById($id);
    if (!$existing) {
        logSupplyReceiveAction('supply.receive.header.update.failed', 'supply_receipts', $id, [
            'reason' => 'not_found',
        ]);
        flash('danger', 'Supply receipt not found.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    [$mode, $modeError] = validateDeliveryModeAndReceipt($payload);
    if ($modeError !== null) {
        logSupplyReceiveAction('supply.receive.header.update.failed', 'supply_receipts', $id, [
            'reason' => 'validation',
            'message' => $modeError,
        ]);
        flash('danger', $modeError);
        redirect(supplyReceiveListUrl());
        exit;
    }

    if (
        $payload['receipt_no'] === ''
        || $payload['supplier_id'] <= 0
        || $payload['receipt_date'] === ''
        || $payload['received_by'] <= 0
    ) {
        logSupplyReceiveAction('supply.receive.header.update.failed', 'supply_receipts', $id, [
            'reason' => 'validation',
            'message' => 'Required fields missing.',
        ]);
        flash('danger', 'Receipt No, Supplier, Receipt Date, and Received By are required.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    if (
        strlen($payload['receipt_no']) > 250
        || strlen($payload['pr_no']) > 100
        || strlen($payload['po_no']) > 100
        || strlen($payload['dr_no']) > 100
        || strlen($payload['si_no']) > 100
        || strlen($payload['iar_no']) > 100
    ) {
        logSupplyReceiveAction('supply.receive.header.update.failed', 'supply_receipts', $id, [
            'reason' => 'validation',
            'message' => 'Field length violation.',
        ]);
        flash('danger', 'Receipt/PR/PO/DR/SI/IAR values exceed allowed length.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    $changes = [];
    foreach (['receipt_no', 'pr_no', 'po_no', 'dr_no', 'si_no', 'iar_no', 'supplier_id', 'receipt_date', 'received_by', 'inspected_by', 'notes'] as $field) {
        $oldValue = (string)($existing[$field] ?? '');
        $newValue = (string)($payload[$field] ?? '');
        if ($oldValue !== $newValue) {
            $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
        }
    }

    if (empty($changes)) {
        $syncedRows = syncSupplyReceiptReferences($id, $payload['receipt_no']);
        if ($syncedRows['ledger'] > 0 || $syncedRows['stock'] > 0) {
            logSupplyReceiveAction('supply.receive.reference.sync', 'supply_receipts', $id, [
                'trigger' => 'update_no_changes',
                'ledger_rows_updated' => $syncedRows['ledger'],
                'stock_rows_updated' => $syncedRows['stock'],
            ]);
        }

        logSupplyReceiveAction('supply.receive.header.update.no_changes', 'supply_receipts', $id, [
            'delivery_mode' => $mode,
        ]);
        flash('info', 'No changes detected.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    $res = $headerModel::update(
        $payload['receipt_no'],
        $payload['pr_no'],
        $payload['po_no'],
        $payload['dr_no'],
        $payload['si_no'],
        $payload['iar_no'],
        $payload['supplier_id'],
        $payload['receipt_date'],
        $payload['received_by'],
        $payload['inspected_by'],
        $payload['notes'],
        $id
    );

    if ($res) {
        $syncedRows = syncSupplyReceiptReferences($id, $payload['receipt_no']);
        if ($syncedRows['ledger'] > 0 || $syncedRows['stock'] > 0) {
            logSupplyReceiveAction('supply.receive.reference.sync', 'supply_receipts', $id, [
                'trigger' => 'update_success',
                'ledger_rows_updated' => $syncedRows['ledger'],
                'stock_rows_updated' => $syncedRows['stock'],
            ]);
        }

        logSupplyReceiveAction('supply.receive.header.update.success', 'supply_receipts', $id, [
            'delivery_mode' => $mode,
        ], $changes);
        flash('success', 'Supply receipt updated successfully');
    } else {
        logSupplyReceiveAction('supply.receive.header.update.failed', 'supply_receipts', $id, [
            'reason' => 'database',
            'delivery_mode' => $mode,
        ], $changes);
        flash('danger', 'Failed to update supply receipt');
    }

    redirect(supplyReceiveListUrl());
    exit;
}

if (POSTACT('del')) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        logSupplyReceiveAction('supply.receive.header.delete.failed', 'supply_receipts', null, [
            'reason' => 'validation',
            'message' => 'Missing receipt header id.',
        ]);
        flash('danger', 'Receipt ID is required for deletion.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    $headerModel = new Supply_Receive_Header();
    $lineModel = new Supply_Receive_Items();
    $existing = $headerModel->getById($id);

    if (!$existing) {
        logSupplyReceiveAction('supply.receive.header.delete.failed', 'supply_receipts', $id, [
            'reason' => 'not_found',
        ]);
        flash('danger', 'Supply receipt not found.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    $lineCount = count($lineModel->getAll($id));
    if ($lineCount > 0) {
        logSupplyReceiveAction('supply.receive.header.delete.failed', 'supply_receipts', $id, [
            'reason' => 'has_items',
            'line_count' => $lineCount,
        ]);
        flash('danger', 'Remove all received line items before deleting this receipt.');
        redirect(supplyReceiveListUrl());
        exit;
    }

    $res = $headerModel->deleteById($id);
    if ($res) {
        logSupplyReceiveAction('supply.receive.header.delete.success', 'supply_receipts', $id, [], [
            'receipt_no' => ['old' => (string)($existing['receipt_no'] ?? ''), 'new' => null],
            'supplier_id' => ['old' => (string)($existing['supplier_id'] ?? ''), 'new' => null],
            'receipt_date' => ['old' => (string)($existing['receipt_date'] ?? ''), 'new' => null],
        ]);
        flash('success', 'Receipt deleted successfully');
    } else {
        logSupplyReceiveAction('supply.receive.header.delete.failed', 'supply_receipts', $id, [
            'reason' => 'database',
        ]);
        flash('danger', 'Failed to delete receipt');
    }

    redirect(supplyReceiveListUrl());
    exit;
}

if (POSTACT('addreceiveitem')) {
    // receipt_header_id, item_id, qty_received, unit_cost, batch_no, expiry_date, remarks
    $receiptHeaderId = (int)($_POST['receipt_header_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $qtyReceived = (int)($_POST['qty_received'] ?? 0);
    $unitCost = (float)($_POST['unit_cost'] ?? 0);
    $batchNo = trim((string)($_POST['batch_no'] ?? ''));
    $expiryDate = trim((string)($_POST['expiry_date'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));

    if ($receiptHeaderId <= 0) {
        logSupplyReceiveAction('supply.receive.item.create.failed', 'supply_receipt_items', null, [
            'reason' => 'validation',
            'message' => 'Missing receipt header id.',
        ]);
        flash('error', 'Receipt No is required');
        redirect(supplyReceiveItemsUrl());
        exit;
    }

    if ($itemId <= 0 || $qtyReceived <= 0 || $unitCost <= 0 || $batchNo === '') {
        logSupplyReceiveAction('supply.receive.item.create.failed', 'supply_receipt_items', null, [
            'reason' => 'validation',
            'message' => 'Required line item fields are missing or invalid.',
            'receipt_header_id' => $receiptHeaderId,
        ]);
        flash('error', 'Item, Quantity Received, Unit Cost, and Batch No are required');
        redirect(supplyReceiveItemsUrl($receiptHeaderId));
        exit;
    }

    if (strlen($batchNo) > 10) {
        logSupplyReceiveAction('supply.receive.item.create.failed', 'supply_receipt_items', null, [
            'reason' => 'validation',
            'message' => 'Batch No exceeds 10 characters.',
            'receipt_header_id' => $receiptHeaderId,
        ]);
        flash('error', 'Batch No must be 10 characters or less');
        redirect(supplyReceiveItemsUrl($receiptHeaderId));
        exit;
    }

    $headerModel = new Supply_Receive_Header();
    $receiptHeader = $headerModel->getById($receiptHeaderId);
    if (!$receiptHeader) {
        logSupplyReceiveAction('supply.receive.item.create.failed', 'supply_receipt_items', null, [
            'reason' => 'not_found',
            'receipt_header_id' => $receiptHeaderId,
        ]);
        flash('error', 'Receipt header not found');
        redirect(supplyReceiveItemsUrl());
        exit;
    }

    $lineModel = new Supply_Receive_Items();
    $res = $lineModel->add($receiptHeaderId, $itemId, $qtyReceived, $unitCost, $batchNo, $expiryDate, $remarks);

    if ($res) {
        $itemModel = new Item();
        $itemModel->addQty($itemId, $qtyReceived);

        $headerDate = $receiptHeader['receipt_date'] ?? date('Y-m-d');
        $referenceNo = $receiptHeader['receipt_no'] ?? '';

        StockInventory::recordMovement(
            $itemId,
            $qtyReceived,
            0,
            'Supply Receipt',
            $receiptHeaderId,
            $referenceNo,
            $remarks,
            $headerDate
        );

        Supply_Ledger_Entry::recordMovement(
            $itemId,
            $headerDate,
            'Supply Receipt',
            $receiptHeaderId,
            $referenceNo,
            $qtyReceived,
            0,
            $unitCost,
            $remarks,
            (int)(getUser('id') ?? 0)
        );

        logSupplyReceiveAction('supply.receive.item.create.success', 'supply_receipt_items', (int)$res, [
            'receipt_header_id' => $receiptHeaderId,
            'reference_no' => $referenceNo,
        ], [
            'item_id' => ['old' => null, 'new' => $itemId],
            'qty_received' => ['old' => null, 'new' => $qtyReceived],
            'unit_cost' => ['old' => null, 'new' => $unitCost],
            'batch_no' => ['old' => null, 'new' => $batchNo],
            'expiry_date' => ['old' => null, 'new' => $expiryDate],
            'remarks' => ['old' => null, 'new' => $remarks],
        ]);

        flash('success', 'Item added successfully');
    } else {
        logSupplyReceiveAction('supply.receive.item.create.failed', 'supply_receipt_items', null, [
            'reason' => 'database',
            'receipt_header_id' => $receiptHeaderId,
            'item_id' => $itemId,
        ]);
        flash('error', 'Failed to add item');
    }

    redirect(supplyReceiveItemsUrl($receiptHeaderId));
    exit;
}

if (POSTACT('delreceiveitem')) {
    $id = (int)($_POST['id'] ?? 0);
    $receiptHeaderId = (int)($_POST['receipt_header_id'] ?? 0);

    if ($id <= 0) {
        logSupplyReceiveAction('supply.receive.item.delete.failed', 'supply_receipt_items', null, [
            'reason' => 'validation',
            'message' => 'Missing line item id.',
        ]);
        flash('error', 'Line item ID is required');
        redirect(supplyReceiveItemsUrl());
        exit;
    }

    if ($receiptHeaderId <= 0) {
        logSupplyReceiveAction('supply.receive.item.delete.failed', 'supply_receipt_items', $id, [
            'reason' => 'validation',
            'message' => 'Missing receipt header id.',
        ]);
        flash('error', 'Receipt Header ID is required');
        redirect(supplyReceiveItemsUrl());
        exit;
    }

    $lineModel = new Supply_Receive_Items();
    $itemDetails = $lineModel->getById($id);
    if (!$itemDetails) {
        logSupplyReceiveAction('supply.receive.item.delete.failed', 'supply_receipt_items', $id, [
            'reason' => 'not_found',
            'receipt_header_id' => $receiptHeaderId,
        ]);
        flash('error', 'Received item not found');
        redirect(supplyReceiveItemsUrl($receiptHeaderId));
        exit;
    }

    $res = $lineModel->deleteById($id);

    if ($res) {
        $itemModel = new Item();
        $itemModel->reduceQty((int)$itemDetails['item_id'], (int)$itemDetails['qty_received']);

        $headerModel = new Supply_Receive_Header();
        $receiptHeader = $headerModel->getById($receiptHeaderId);
        $headerDate = $receiptHeader['receipt_date'] ?? date('Y-m-d');
        $referenceNo = $receiptHeader['receipt_no'] ?? '';

        StockInventory::recordMovement(
            (int)$itemDetails['item_id'],
            0,
            (int)$itemDetails['qty_received'],
            'Supply Receipt Reversal',
            $receiptHeaderId,
            $referenceNo,
            $itemDetails['remarks'] ?? '',
            $headerDate
        );

        Supply_Ledger_Entry::recordMovement(
            (int)$itemDetails['item_id'],
            $headerDate,
            'Supply Receipt Reversal',
            $receiptHeaderId,
            $referenceNo,
            0,
            (int)$itemDetails['qty_received'],
            (float)($itemDetails['unit_cost'] ?? 0),
            $itemDetails['remarks'] ?? '',
            (int)(getUser('id') ?? 0)
        );

        logSupplyReceiveAction('supply.receive.item.delete.success', 'supply_receipt_items', $id, [
            'receipt_header_id' => $receiptHeaderId,
            'reference_no' => $referenceNo,
        ], [
            'item_id' => ['old' => (string)($itemDetails['item_id'] ?? ''), 'new' => null],
            'qty_received' => ['old' => (string)($itemDetails['qty_received'] ?? ''), 'new' => null],
            'unit_cost' => ['old' => (string)($itemDetails['unit_cost'] ?? ''), 'new' => null],
            'batch_no' => ['old' => (string)($itemDetails['batch_no'] ?? ''), 'new' => null],
            'expiry_date' => ['old' => (string)($itemDetails['expiry_date'] ?? ''), 'new' => null],
            'remarks' => ['old' => (string)($itemDetails['remarks'] ?? ''), 'new' => null],
        ]);

        flash('success', 'Item deleted successfully');
    } else {
        logSupplyReceiveAction('supply.receive.item.delete.failed', 'supply_receipt_items', $id, [
            'reason' => 'database',
            'receipt_header_id' => $receiptHeaderId,
        ]);
        flash('error', 'Failed to delete item');
    }

    redirect(supplyReceiveItemsUrl($receiptHeaderId));
    exit;
}

flash('danger', 'Unsupported receive supply action.');
redirect(supplyReceiveListUrl());
exit;
