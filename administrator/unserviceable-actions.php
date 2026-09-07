<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/UnserviceableProperty.model.php';

$reportModel = new UnserviceablePropertyReport();
$itemModel = new UnserviceablePropertyItem();

function logUnserviceableAction(string $action, string $entity, $entityId = null, array $metadata = [], array $details = []): void
{
    if (!isset($metadata['module'])) {
        $metadata['module'] = 'reports_compliance';
    }

    writeAuditLog($action, $entity, $entityId, $metadata, $details);
}

function parseOptionalFloat($value): ?float
{
    if ($value === null || trim((string)$value) === '') {
        return null;
    }
    return (float)$value;
}

if (POSTACT('add_report')) {
    $reportNo = trim($_POST['report_no'] ?? '');
    $reportDate = $_POST['report_date'] ?? '';
    $entityName = trim($_POST['entity_name'] ?? '');
    $office = trim($_POST['office'] ?? '');
    $fundCluster = trim($_POST['fund_cluster'] ?? '');
    $preparedBy = (int)($_POST['prepared_by'] ?? 0);
    $inspectedByInput = $_POST['inspected_by'] ?? '';
    $approvedByInput = $_POST['approved_by'] ?? '';
    $inspectedBy = $inspectedByInput === '' ? null : (int)$inspectedByInput;
    $approvedBy = $approvedByInput === '' ? null : (int)$approvedByInput;
    $remarks = $_POST['remarks'] ?? null;

    if ($reportNo === '' || $reportDate === '' || $entityName === '' || $office === '' || $fundCluster === '' || $preparedBy === 0) {
        flash('danger', 'Report No., Report Date, Entity Name, Office, Fund Cluster, and Prepared By are required.');
        redirect('unserviceable_reports.php');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
        flash('danger', 'Invalid report date format.');
        redirect('unserviceable_reports.php');
    }

    $duplicate = UnserviceablePropertyReport::getByReportNo($reportNo);
    if ($duplicate) {
        logUnserviceableAction('unserviceable.report.create.failed', 'unserviceable_reports', null, [
            'reason' => 'duplicate_report_no',
            'report_no' => $reportNo,
        ]);
        flash('danger', 'Another IIRUP already uses this report number.');
        redirect('unserviceable_reports.php');
    }

    $result = UnserviceablePropertyReport::add(
        $reportNo,
        $reportDate,
        $entityName,
        $office,
        $fundCluster,
        $preparedBy,
        $inspectedBy,
        $approvedBy,
        $remarks
    );

    if ($result) {
        logUnserviceableAction('unserviceable.report.create.success', 'unserviceable_reports', (int)$result, [
            'report_no' => $reportNo,
        ], [
            'report_no' => ['old' => null, 'new' => $reportNo],
            'report_date' => ['old' => null, 'new' => $reportDate],
            'entity_name' => ['old' => null, 'new' => $entityName],
            'office' => ['old' => null, 'new' => $office],
            'fund_cluster' => ['old' => null, 'new' => $fundCluster],
            'prepared_by' => ['old' => null, 'new' => $preparedBy],
            'inspected_by' => ['old' => null, 'new' => $inspectedBy],
            'approved_by' => ['old' => null, 'new' => $approvedBy],
            'remarks' => ['old' => null, 'new' => $remarks],
        ]);
        flash('success', 'IIRUP created successfully.');
    } else {
        logUnserviceableAction('unserviceable.report.create.failed', 'unserviceable_reports', null, [
            'reason' => 'database',
            'report_no' => $reportNo,
        ]);
        flash('danger', 'Failed to create IIRUP.');
    }
    redirect('unserviceable_reports.php');
}

if (POSTACT('update_report')) {
    $id = (int)($_POST['id'] ?? 0);
    $reportNo = trim($_POST['report_no'] ?? '');
    $reportDate = $_POST['report_date'] ?? '';
    $entityName = trim($_POST['entity_name'] ?? '');
    $office = trim($_POST['office'] ?? '');
    $fundCluster = trim($_POST['fund_cluster'] ?? '');
    $preparedBy = (int)($_POST['prepared_by'] ?? 0);
    $inspectedByInput = $_POST['inspected_by'] ?? '';
    $approvedByInput = $_POST['approved_by'] ?? '';
    $inspectedBy = $inspectedByInput === '' ? null : (int)$inspectedByInput;
    $approvedBy = $approvedByInput === '' ? null : (int)$approvedByInput;
    $remarks = $_POST['remarks'] ?? null;

    if ($id === 0) {
        flash('danger', 'IIRUP reference is required.');
        redirect('unserviceable_reports.php');
    }

    if ($reportNo === '' || $reportDate === '' || $entityName === '' || $office === '' || $fundCluster === '' || $preparedBy === 0) {
        flash('danger', 'Report No., Report Date, Entity Name, Office, Fund Cluster, and Prepared By are required.');
        redirect('unserviceable_reports.php');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
        flash('danger', 'Invalid report date format.');
        redirect('unserviceable_reports.php');
    }

    $existing = $reportModel->getById($id);
    if (!$existing) {
        logUnserviceableAction('unserviceable.report.update.failed', 'unserviceable_reports', $id, [
            'reason' => 'not_found',
        ]);
        flash('danger', 'IIRUP not found.');
        redirect('unserviceable_reports.php');
    }

    $duplicate = UnserviceablePropertyReport::getByReportNo($reportNo);
    if ($duplicate && (int)$duplicate['id'] !== $id) {
        logUnserviceableAction('unserviceable.report.update.failed', 'unserviceable_reports', $id, [
            'reason' => 'duplicate_report_no',
            'report_no' => $reportNo,
        ]);
        flash('danger', 'Another IIRUP already uses this report number.');
        redirect('unserviceable_reports.php');
    }

    $changes = [];
    foreach ([
        'report_no' => $reportNo,
        'report_date' => $reportDate,
        'entity_name' => $entityName,
        'office' => $office,
        'fund_cluster' => $fundCluster,
        'prepared_by' => $preparedBy,
        'inspected_by' => $inspectedBy,
        'approved_by' => $approvedBy,
        'remarks' => $remarks,
    ] as $field => $newValue) {
        $oldValue = $existing[$field] ?? null;
        if ((string)$oldValue !== (string)$newValue) {
            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }
    }

    $result = UnserviceablePropertyReport::update(
        $id,
        $reportNo,
        $reportDate,
        $entityName,
        $office,
        $fundCluster,
        $preparedBy,
        $inspectedBy,
        $approvedBy,
        $remarks
    );

    if ($result) {
        if (empty($changes)) {
            logUnserviceableAction('unserviceable.report.update.no_changes', 'unserviceable_reports', $id, [
                'report_no' => $reportNo,
            ]);
        } else {
            logUnserviceableAction('unserviceable.report.update.success', 'unserviceable_reports', $id, [
                'report_no' => $reportNo,
            ], $changes);
        }
        flash('success', 'IIRUP updated successfully.');
    } else {
        logUnserviceableAction('unserviceable.report.update.failed', 'unserviceable_reports', $id, [
            'reason' => 'database',
            'report_no' => $reportNo,
        ], $changes);
        flash('danger', 'Failed to update IIRUP.');
    }
    redirect('unserviceable_reports.php');
}

if (POSTACT('delete_report')) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === 0) {
        flash('danger', 'IIRUP reference is required.');
        redirect('unserviceable_reports.php');
    }

    $existing = $reportModel->getById($id);
    if (!$existing) {
        logUnserviceableAction('unserviceable.report.delete.failed', 'unserviceable_reports', $id, [
            'reason' => 'not_found',
        ]);
        flash('danger', 'IIRUP not found.');
        redirect('unserviceable_reports.php');
    }

    $result = UnserviceablePropertyReport::deleteReport($id);

    if ($result) {
        logUnserviceableAction('unserviceable.report.delete.success', 'unserviceable_reports', $id, [
            'report_no' => $existing['report_no'] ?? null,
        ]);
        flash('success', 'IIRUP and its items were removed.');
    } else {
        logUnserviceableAction('unserviceable.report.delete.failed', 'unserviceable_reports', $id, [
            'reason' => 'database',
            'report_no' => $existing['report_no'] ?? null,
        ]);
        flash('danger', 'Failed to delete IIRUP.');
    }
    redirect('unserviceable_reports.php');
}

if (POSTACT('add_item')) {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unitCost = (float)($_POST['unit_cost'] ?? 0);
    $appraisedValue = (float)($_POST['appraised_value'] ?? 0);
    $disposalMethod = trim($_POST['disposal_method'] ?? '');
    $propertyNo = $_POST['property_no'] ?? null;
    $dateAcquired = $_POST['date_acquired'] ?? null;
    $conditionNotes = $_POST['condition_notes'] ?? null;
    $totalCost = parseOptionalFloat($_POST['total_cost'] ?? null);
    $accumulatedDepreciation = parseOptionalFloat($_POST['accumulated_depreciation'] ?? null);
    $accumulatedImpairmentLoss = parseOptionalFloat($_POST['accumulated_impairment_loss'] ?? null);
    $carryingAmount = parseOptionalFloat($_POST['carrying_amount'] ?? null);
    $disposalSale = isset($_POST['disposal_sale']);
    $disposalTransfer = isset($_POST['disposal_transfer']);
    $disposalDestruction = isset($_POST['disposal_destruction']);
    $disposalOther = $_POST['disposal_other'] ?? null;
    $disposalTotal = parseOptionalFloat($_POST['disposal_total'] ?? null);
    $orNo = $_POST['or_no'] ?? null;
    $salesAmount = parseOptionalFloat($_POST['sales_amount'] ?? null);
    $remarks = $_POST['remarks'] ?? null;

    if ($reportId === 0) {
        flash('danger', 'IIRUP reference is required.');
        redirect('unserviceable_reports.php');
    }

    $report = $reportModel->getById($reportId);
    if (!$report) {
        flash('danger', 'IIRUP not found.');
        redirect('unserviceable_reports.php');
    }

    if ($itemId === 0) {
        flash('danger', 'Item selection is required.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    if ($quantity < 0) {
        flash('danger', 'Quantity cannot be negative.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    if ($unitCost < 0 || $appraisedValue < 0) {
        flash('danger', 'Costs cannot be negative.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    foreach ([
        'Total Cost' => $totalCost,
        'Accumulated Depreciation' => $accumulatedDepreciation,
        'Accumulated Impairment Loss' => $accumulatedImpairmentLoss,
        'Carrying Amount' => $carryingAmount,
        'Disposal Total' => $disposalTotal,
        'Sales Amount' => $salesAmount,
    ] as $label => $candidate) {
        if ($candidate !== null && $candidate < 0) {
            flash('danger', $label . ' cannot be negative.');
            redirect('unserviceable_items.php?report_id=' . $reportId);
        }
    }

    if ($dateAcquired !== null && trim((string)$dateAcquired) !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dateAcquired)) {
        flash('danger', 'Invalid date acquired format.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    if ($disposalMethod === '') {
        flash('danger', 'Recommended disposal method is required.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    $existingLine = UnserviceablePropertyItem::getByReportAndItem($reportId, $itemId);
    if ($existingLine) {
        logUnserviceableAction('unserviceable.item.create.failed', 'unserviceable_items', null, [
            'reason' => 'duplicate_item',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ]);
        flash('danger', 'This item is already listed in the selected IIRUP.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    $result = UnserviceablePropertyItem::add(
        $reportId,
        $itemId,
        $quantity,
        $unitCost,
        $appraisedValue,
        $disposalMethod,
        $propertyNo,
        $conditionNotes,
        $remarks,
        $dateAcquired,
        $totalCost,
        $accumulatedDepreciation,
        $accumulatedImpairmentLoss,
        $carryingAmount,
        $disposalSale,
        $disposalTransfer,
        $disposalDestruction,
        $disposalOther,
        $disposalTotal,
        $orNo,
        $salesAmount
    );

    if ($result) {
        logUnserviceableAction('unserviceable.item.create.success', 'unserviceable_items', (int)$result, [
            'report_id' => $reportId,
            'item_id' => $itemId,
        ], [
            'report_id' => ['old' => null, 'new' => $reportId],
            'item_id' => ['old' => null, 'new' => $itemId],
            'quantity' => ['old' => null, 'new' => $quantity],
            'unit_cost' => ['old' => null, 'new' => $unitCost],
            'appraised_value' => ['old' => null, 'new' => $appraisedValue],
            'disposal_method' => ['old' => null, 'new' => $disposalMethod],
            'property_no' => ['old' => null, 'new' => $propertyNo],
            'date_acquired' => ['old' => null, 'new' => $dateAcquired],
            'total_cost' => ['old' => null, 'new' => $totalCost],
            'accumulated_depreciation' => ['old' => null, 'new' => $accumulatedDepreciation],
            'accumulated_impairment_loss' => ['old' => null, 'new' => $accumulatedImpairmentLoss],
            'carrying_amount' => ['old' => null, 'new' => $carryingAmount],
            'disposal_sale' => ['old' => null, 'new' => $disposalSale ? 1 : 0],
            'disposal_transfer' => ['old' => null, 'new' => $disposalTransfer ? 1 : 0],
            'disposal_destruction' => ['old' => null, 'new' => $disposalDestruction ? 1 : 0],
            'disposal_other' => ['old' => null, 'new' => $disposalOther],
            'disposal_total' => ['old' => null, 'new' => $disposalTotal],
            'or_no' => ['old' => null, 'new' => $orNo],
            'sales_amount' => ['old' => null, 'new' => $salesAmount],
            'condition_notes' => ['old' => null, 'new' => $conditionNotes],
            'remarks' => ['old' => null, 'new' => $remarks],
        ]);
        flash('success', 'Unserviceable item added successfully.');
    } else {
        logUnserviceableAction('unserviceable.item.create.failed', 'unserviceable_items', null, [
            'reason' => 'database',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ]);
        flash('danger', 'Failed to add unserviceable item.');
    }
    redirect('unserviceable_items.php?report_id=' . $reportId);
}

if (POSTACT('update_item')) {
    $id = (int)($_POST['id'] ?? 0);
    $reportId = (int)($_POST['report_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unitCost = (float)($_POST['unit_cost'] ?? 0);
    $appraisedValue = (float)($_POST['appraised_value'] ?? 0);
    $disposalMethod = trim($_POST['disposal_method'] ?? '');
    $propertyNo = $_POST['property_no'] ?? null;
    $dateAcquired = $_POST['date_acquired'] ?? null;
    $conditionNotes = $_POST['condition_notes'] ?? null;
    $totalCost = parseOptionalFloat($_POST['total_cost'] ?? null);
    $accumulatedDepreciation = parseOptionalFloat($_POST['accumulated_depreciation'] ?? null);
    $accumulatedImpairmentLoss = parseOptionalFloat($_POST['accumulated_impairment_loss'] ?? null);
    $carryingAmount = parseOptionalFloat($_POST['carrying_amount'] ?? null);
    $disposalSale = isset($_POST['disposal_sale']);
    $disposalTransfer = isset($_POST['disposal_transfer']);
    $disposalDestruction = isset($_POST['disposal_destruction']);
    $disposalOther = $_POST['disposal_other'] ?? null;
    $disposalTotal = parseOptionalFloat($_POST['disposal_total'] ?? null);
    $orNo = $_POST['or_no'] ?? null;
    $salesAmount = parseOptionalFloat($_POST['sales_amount'] ?? null);
    $remarks = $_POST['remarks'] ?? null;

    if ($id === 0 || $reportId === 0) {
        flash('danger', 'Line reference is required.');
        redirect('unserviceable_reports.php');
    }

    $report = $reportModel->getById($reportId);
    if (!$report) {
        flash('danger', 'IIRUP not found.');
        redirect('unserviceable_reports.php');
    }

    $existingLine = $itemModel->getById($id);
    if (!$existingLine) {
        logUnserviceableAction('unserviceable.item.update.failed', 'unserviceable_items', $id, [
            'reason' => 'not_found',
            'report_id' => $reportId,
        ]);
        flash('danger', 'Unserviceable line item not found.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    if ($itemId === 0) {
        flash('danger', 'Item selection is required.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    if ($quantity < 0) {
        flash('danger', 'Quantity cannot be negative.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    if ($unitCost < 0 || $appraisedValue < 0) {
        flash('danger', 'Costs cannot be negative.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    foreach ([
        'Total Cost' => $totalCost,
        'Accumulated Depreciation' => $accumulatedDepreciation,
        'Accumulated Impairment Loss' => $accumulatedImpairmentLoss,
        'Carrying Amount' => $carryingAmount,
        'Disposal Total' => $disposalTotal,
        'Sales Amount' => $salesAmount,
    ] as $label => $candidate) {
        if ($candidate !== null && $candidate < 0) {
            flash('danger', $label . ' cannot be negative.');
            redirect('unserviceable_items.php?report_id=' . $reportId);
        }
    }

    if ($dateAcquired !== null && trim((string)$dateAcquired) !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dateAcquired)) {
        flash('danger', 'Invalid date acquired format.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    if ($disposalMethod === '') {
        flash('danger', 'Recommended disposal method is required.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    $duplicate = UnserviceablePropertyItem::getByReportAndItem($reportId, $itemId);
    if ($duplicate && (int)$duplicate['id'] !== $id) {
        logUnserviceableAction('unserviceable.item.update.failed', 'unserviceable_items', $id, [
            'reason' => 'duplicate_item',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ]);
        flash('danger', 'Another line already uses this item in the IIRUP.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    $changes = [];
    foreach ([
        'item_id' => $itemId,
        'quantity' => $quantity,
        'unit_cost' => $unitCost,
        'appraised_value' => $appraisedValue,
        'disposal_method' => $disposalMethod,
        'property_no' => $propertyNo,
        'date_acquired' => $dateAcquired,
        'total_cost' => $totalCost,
        'accumulated_depreciation' => $accumulatedDepreciation,
        'accumulated_impairment_loss' => $accumulatedImpairmentLoss,
        'carrying_amount' => $carryingAmount,
        'disposal_sale' => $disposalSale ? 1 : 0,
        'disposal_transfer' => $disposalTransfer ? 1 : 0,
        'disposal_destruction' => $disposalDestruction ? 1 : 0,
        'disposal_other' => $disposalOther,
        'disposal_total' => $disposalTotal,
        'or_no' => $orNo,
        'sales_amount' => $salesAmount,
        'condition_notes' => $conditionNotes,
        'remarks' => $remarks,
    ] as $field => $newValue) {
        $oldValue = $existingLine[$field] ?? null;
        if ((string)$oldValue !== (string)$newValue) {
            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }
    }

    $result = UnserviceablePropertyItem::update(
        $id,
        $reportId,
        $itemId,
        $quantity,
        $unitCost,
        $appraisedValue,
        $disposalMethod,
        $propertyNo,
        $conditionNotes,
        $remarks,
        $dateAcquired,
        $totalCost,
        $accumulatedDepreciation,
        $accumulatedImpairmentLoss,
        $carryingAmount,
        $disposalSale,
        $disposalTransfer,
        $disposalDestruction,
        $disposalOther,
        $disposalTotal,
        $orNo,
        $salesAmount
    );

    if ($result) {
        if (empty($changes)) {
            logUnserviceableAction('unserviceable.item.update.no_changes', 'unserviceable_items', $id, [
                'report_id' => $reportId,
                'item_id' => $itemId,
            ]);
        } else {
            logUnserviceableAction('unserviceable.item.update.success', 'unserviceable_items', $id, [
                'report_id' => $reportId,
                'item_id' => $itemId,
            ], $changes);
        }
        flash('success', 'Unserviceable item updated successfully.');
    } else {
        logUnserviceableAction('unserviceable.item.update.failed', 'unserviceable_items', $id, [
            'reason' => 'database',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ], $changes);
        flash('danger', 'Failed to update unserviceable item.');
    }
    redirect('unserviceable_items.php?report_id=' . $reportId);
}

if (POSTACT('delete_item')) {
    $id = (int)($_POST['id'] ?? 0);
    $reportId = (int)($_POST['report_id'] ?? 0);

    if ($id === 0 || $reportId === 0) {
        flash('danger', 'Line reference is required.');
        redirect('unserviceable_reports.php');
    }

    $report = $reportModel->getById($reportId);
    if (!$report) {
        flash('danger', 'IIRUP not found.');
        redirect('unserviceable_reports.php');
    }

    $existingLine = $itemModel->getById($id);
    if (!$existingLine) {
        logUnserviceableAction('unserviceable.item.delete.failed', 'unserviceable_items', $id, [
            'reason' => 'not_found',
            'report_id' => $reportId,
        ]);
        flash('danger', 'Unserviceable line item not found.');
        redirect('unserviceable_items.php?report_id=' . $reportId);
    }

    $result = $itemModel->deleteById($id);

    if ($result) {
        logUnserviceableAction('unserviceable.item.delete.success', 'unserviceable_items', $id, [
            'report_id' => $reportId,
            'item_id' => $existingLine['item_id'] ?? null,
        ]);
        flash('success', 'Unserviceable item removed.');
    } else {
        logUnserviceableAction('unserviceable.item.delete.failed', 'unserviceable_items', $id, [
            'reason' => 'database',
            'report_id' => $reportId,
            'item_id' => $existingLine['item_id'] ?? null,
        ]);
        flash('danger', 'Failed to delete unserviceable item.');
    }
    redirect('unserviceable_items.php?report_id=' . $reportId);
}

redirect('unserviceable_reports.php');
