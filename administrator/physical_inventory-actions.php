<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/PhysicalInventory.model.php';

$reportModel = new PhysicalInventoryReport();
$itemModel = new PhysicalInventoryItem();

function logPhysicalInventoryAction(string $action, string $entity, $entityId = null, array $metadata = [], array $details = []): void
{
    if (!isset($metadata['module'])) {
        $metadata['module'] = 'reports_compliance';
    }

    writeAuditLog($action, $entity, $entityId, $metadata, $details);
}

if (POSTACT('add_report')) {
    $reportNo = trim($_POST['report_no'] ?? '');
    $reportDate = $_POST['report_date'] ?? '';
    $fundCluster = trim($_POST['fund_cluster'] ?? '');
    $inventoryType = trim($_POST['inventory_type'] ?? '');
    $classificationCode = trim($_POST['classification_code'] ?? '');
    $preparedBy = (int)($_POST['prepared_by'] ?? 0);
    $verifiedByInput = $_POST['verified_by'] ?? '';
    $verifiedBy = $verifiedByInput === '' ? null : (int)$verifiedByInput;
    $location = $_POST['location'] ?? null;
    $remarks = $_POST['remarks'] ?? null;

    if ($reportNo === '' || $reportDate === '' || $fundCluster === '' || $inventoryType === '' || $classificationCode === '' || $preparedBy === 0) {
        flash('danger', 'Report No., Report Date, Fund Cluster, Inventory Type, Classification/Code, and Prepared By are required.');
        redirect('physical_inventory_reports.php');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
        flash('danger', 'Invalid report date format.');
        redirect('physical_inventory_reports.php');
    }

    $duplicate = PhysicalInventoryReport::getByReportNo($reportNo);
    if ($duplicate) {
        logPhysicalInventoryAction('physical_inventory.report.create.failed', 'physical_inventory_reports', null, [
            'reason' => 'duplicate_report_no',
            'report_no' => $reportNo,
        ]);
        flash('danger', 'Another RPCI already uses this report number.');
        redirect('physical_inventory_reports.php');
    }

    $result = PhysicalInventoryReport::add(
        $reportNo,
        $reportDate,
        $fundCluster,
        $inventoryType,
        $classificationCode,
        $preparedBy,
        $verifiedBy,
        $location,
        $remarks
    );

    if ($result) {
        logPhysicalInventoryAction('physical_inventory.report.create.success', 'physical_inventory_reports', (int)$result, [
            'report_no' => $reportNo,
        ], [
            'report_no' => ['old' => null, 'new' => $reportNo],
            'report_date' => ['old' => null, 'new' => $reportDate],
            'fund_cluster' => ['old' => null, 'new' => $fundCluster],
            'inventory_type' => ['old' => null, 'new' => $inventoryType],
            'classification_code' => ['old' => null, 'new' => $classificationCode],
            'prepared_by' => ['old' => null, 'new' => $preparedBy],
            'verified_by' => ['old' => null, 'new' => $verifiedBy],
            'location' => ['old' => null, 'new' => $location],
            'remarks' => ['old' => null, 'new' => $remarks],
        ]);
        flash('success', 'RPCI created successfully.');
    } else {
        logPhysicalInventoryAction('physical_inventory.report.create.failed', 'physical_inventory_reports', null, [
            'reason' => 'database',
            'report_no' => $reportNo,
        ]);
        flash('danger', 'Failed to create RPCI.');
    }
    redirect('physical_inventory_reports.php');
}

if (POSTACT('update_report')) {
    $id = (int)($_POST['id'] ?? 0);
    $reportNo = trim($_POST['report_no'] ?? '');
    $reportDate = $_POST['report_date'] ?? '';
    $fundCluster = trim($_POST['fund_cluster'] ?? '');
    $inventoryType = trim($_POST['inventory_type'] ?? '');
    $classificationCode = trim($_POST['classification_code'] ?? '');
    $preparedBy = (int)($_POST['prepared_by'] ?? 0);
    $verifiedByInput = $_POST['verified_by'] ?? '';
    $verifiedBy = $verifiedByInput === '' ? null : (int)$verifiedByInput;
    $location = $_POST['location'] ?? null;
    $remarks = $_POST['remarks'] ?? null;

    if ($id === 0) {
        flash('danger', 'RPCI reference is required.');
        redirect('physical_inventory_reports.php');
    }

    if ($reportNo === '' || $reportDate === '' || $fundCluster === '' || $inventoryType === '' || $classificationCode === '' || $preparedBy === 0) {
        flash('danger', 'Report No., Report Date, Fund Cluster, Inventory Type, Classification/Code, and Prepared By are required.');
        redirect('physical_inventory_reports.php');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
        flash('danger', 'Invalid report date format.');
        redirect('physical_inventory_reports.php');
    }

    $existing = $reportModel->getById($id);
    if (!$existing) {
        logPhysicalInventoryAction('physical_inventory.report.update.failed', 'physical_inventory_reports', $id, [
            'reason' => 'not_found',
        ]);
        flash('danger', 'RPCI not found.');
        redirect('physical_inventory_reports.php');
    }

    $duplicate = PhysicalInventoryReport::getByReportNo($reportNo);
    if ($duplicate && (int)$duplicate['id'] !== $id) {
        logPhysicalInventoryAction('physical_inventory.report.update.failed', 'physical_inventory_reports', $id, [
            'reason' => 'duplicate_report_no',
            'report_no' => $reportNo,
        ]);
        flash('danger', 'Another RPCI already uses this report number.');
        redirect('physical_inventory_reports.php');
    }

    $changes = [];
    foreach ([
        'report_no' => $reportNo,
        'report_date' => $reportDate,
        'fund_cluster' => $fundCluster,
        'inventory_type' => $inventoryType,
        'classification_code' => $classificationCode,
        'prepared_by' => $preparedBy,
        'verified_by' => $verifiedBy,
        'location' => $location,
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

    $result = PhysicalInventoryReport::update(
        $id,
        $reportNo,
        $reportDate,
        $fundCluster,
        $inventoryType,
        $classificationCode,
        $preparedBy,
        $verifiedBy,
        $location,
        $remarks
    );

    if ($result) {
        if (empty($changes)) {
            logPhysicalInventoryAction('physical_inventory.report.update.no_changes', 'physical_inventory_reports', $id, [
                'report_no' => $reportNo,
            ]);
        } else {
            logPhysicalInventoryAction('physical_inventory.report.update.success', 'physical_inventory_reports', $id, [
                'report_no' => $reportNo,
            ], $changes);
        }
        flash('success', 'RPCI updated successfully.');
    } else {
        logPhysicalInventoryAction('physical_inventory.report.update.failed', 'physical_inventory_reports', $id, [
            'reason' => 'database',
            'report_no' => $reportNo,
        ], $changes);
        flash('danger', 'Failed to update RPCI.');
    }
    redirect('physical_inventory_reports.php');
}

if (POSTACT('delete_report')) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === 0) {
        flash('danger', 'RPCI reference is required.');
        redirect('physical_inventory_reports.php');
    }

    $existing = $reportModel->getById($id);
    if (!$existing) {
        logPhysicalInventoryAction('physical_inventory.report.delete.failed', 'physical_inventory_reports', $id, [
            'reason' => 'not_found',
        ]);
        flash('danger', 'RPCI not found.');
        redirect('physical_inventory_reports.php');
    }

    $result = PhysicalInventoryReport::deleteHeader($id);

    if ($result) {
        logPhysicalInventoryAction('physical_inventory.report.delete.success', 'physical_inventory_reports', $id, [
            'report_no' => $existing['report_no'] ?? null,
        ]);
        flash('success', 'RPCI and its items were removed.');
    } else {
        logPhysicalInventoryAction('physical_inventory.report.delete.failed', 'physical_inventory_reports', $id, [
            'reason' => 'database',
            'report_no' => $existing['report_no'] ?? null,
        ]);
        flash('danger', 'Failed to delete RPCI.');
    }
    redirect('physical_inventory_reports.php');
}

if (POSTACT('add_item')) {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $systemQty = (int)($_POST['system_qty'] ?? 0);
    $countedQty = (int)($_POST['counted_qty'] ?? 0);
    $unitCost = (float)($_POST['unit_cost'] ?? 0);
    $location = $_POST['location'] ?? null;
    $remarks = $_POST['remarks'] ?? null;

    if ($reportId === 0) {
        flash('danger', 'RPCI reference is required.');
        redirect('physical_inventory_reports.php');
    }

    $report = $reportModel->getById($reportId);
    if (!$report) {
        flash('danger', 'RPCI not found.');
        redirect('physical_inventory_reports.php');
    }

    if ($itemId === 0) {
        flash('danger', 'Item selection is required.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    if ($systemQty < 0 || $countedQty < 0) {
        flash('danger', 'Quantities cannot be negative.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    if ($unitCost < 0) {
        flash('danger', 'Unit cost cannot be negative.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    $existingLine = PhysicalInventoryItem::getByReportAndItem($reportId, $itemId);
    if ($existingLine) {
        logPhysicalInventoryAction('physical_inventory.item.create.failed', 'physical_inventory_items', null, [
            'reason' => 'duplicate_item',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ]);
        flash('danger', 'This item is already listed in the selected RPCI.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    $result = PhysicalInventoryItem::add(
        $reportId,
        $itemId,
        $systemQty,
        $countedQty,
        $unitCost,
        $location,
        $remarks
    );

    if ($result) {
        logPhysicalInventoryAction('physical_inventory.item.create.success', 'physical_inventory_items', (int)$result, [
            'report_id' => $reportId,
            'item_id' => $itemId,
        ], [
            'report_id' => ['old' => null, 'new' => $reportId],
            'item_id' => ['old' => null, 'new' => $itemId],
            'system_qty' => ['old' => null, 'new' => $systemQty],
            'counted_qty' => ['old' => null, 'new' => $countedQty],
            'unit_cost' => ['old' => null, 'new' => $unitCost],
            'location' => ['old' => null, 'new' => $location],
            'remarks' => ['old' => null, 'new' => $remarks],
        ]);
        flash('success', 'Line item added successfully.');
    } else {
        logPhysicalInventoryAction('physical_inventory.item.create.failed', 'physical_inventory_items', null, [
            'reason' => 'database',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ]);
        flash('danger', 'Failed to add line item.');
    }
    redirect('physical_inventory_items.php?report_id=' . $reportId);
}

if (POSTACT('update_item')) {
    $id = (int)($_POST['id'] ?? 0);
    $reportId = (int)($_POST['report_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $systemQty = (int)($_POST['system_qty'] ?? 0);
    $countedQty = (int)($_POST['counted_qty'] ?? 0);
    $unitCost = (float)($_POST['unit_cost'] ?? 0);
    $location = $_POST['location'] ?? null;
    $remarks = $_POST['remarks'] ?? null;

    if ($id === 0 || $reportId === 0) {
        flash('danger', 'Line reference is required.');
        redirect('physical_inventory_reports.php');
    }

    $report = $reportModel->getById($reportId);
    if (!$report) {
        flash('danger', 'RPCI not found.');
        redirect('physical_inventory_reports.php');
    }

    $existingLine = $itemModel->getById($id);
    if (!$existingLine) {
        logPhysicalInventoryAction('physical_inventory.item.update.failed', 'physical_inventory_items', $id, [
            'reason' => 'not_found',
            'report_id' => $reportId,
        ]);
        flash('danger', 'Line item not found.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    if ($itemId === 0) {
        flash('danger', 'Item selection is required.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    if ($systemQty < 0 || $countedQty < 0) {
        flash('danger', 'Quantities cannot be negative.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    if ($unitCost < 0) {
        flash('danger', 'Unit cost cannot be negative.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    $duplicate = PhysicalInventoryItem::getByReportAndItem($reportId, $itemId);
    if ($duplicate && (int)$duplicate['id'] !== $id) {
        logPhysicalInventoryAction('physical_inventory.item.update.failed', 'physical_inventory_items', $id, [
            'reason' => 'duplicate_item',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ]);
        flash('danger', 'Another line already uses this item in the RPCI.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    $changes = [];
    foreach ([
        'item_id' => $itemId,
        'system_qty' => $systemQty,
        'counted_qty' => $countedQty,
        'unit_cost' => $unitCost,
        'location' => $location,
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

    $result = PhysicalInventoryItem::update(
        $id,
        $reportId,
        $itemId,
        $systemQty,
        $countedQty,
        $unitCost,
        $location,
        $remarks
    );

    if ($result) {
        if (empty($changes)) {
            logPhysicalInventoryAction('physical_inventory.item.update.no_changes', 'physical_inventory_items', $id, [
                'report_id' => $reportId,
                'item_id' => $itemId,
            ]);
        } else {
            logPhysicalInventoryAction('physical_inventory.item.update.success', 'physical_inventory_items', $id, [
                'report_id' => $reportId,
                'item_id' => $itemId,
            ], $changes);
        }
        flash('success', 'Line item updated successfully.');
    } else {
        logPhysicalInventoryAction('physical_inventory.item.update.failed', 'physical_inventory_items', $id, [
            'reason' => 'database',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ], $changes);
        flash('danger', 'Failed to update line item.');
    }
    redirect('physical_inventory_items.php?report_id=' . $reportId);
}

if (POSTACT('delete_item')) {
    $id = (int)($_POST['id'] ?? 0);
    $reportId = (int)($_POST['report_id'] ?? 0);

    if ($id === 0 || $reportId === 0) {
        flash('danger', 'Line reference is required.');
        redirect('physical_inventory_reports.php');
    }

    $report = $reportModel->getById($reportId);
    if (!$report) {
        flash('danger', 'RPCI not found.');
        redirect('physical_inventory_reports.php');
    }

    $existingLine = $itemModel->getById($id);
    if (!$existingLine) {
        logPhysicalInventoryAction('physical_inventory.item.delete.failed', 'physical_inventory_items', $id, [
            'reason' => 'not_found',
            'report_id' => $reportId,
        ]);
        flash('danger', 'Line item not found.');
        redirect('physical_inventory_items.php?report_id=' . $reportId);
    }

    $result = $itemModel->deleteById($id);

    if ($result) {
        logPhysicalInventoryAction('physical_inventory.item.delete.success', 'physical_inventory_items', $id, [
            'report_id' => $reportId,
            'item_id' => $existingLine['item_id'] ?? null,
        ]);
        flash('success', 'Line item removed.');
    } else {
        logPhysicalInventoryAction('physical_inventory.item.delete.failed', 'physical_inventory_items', $id, [
            'reason' => 'database',
            'report_id' => $reportId,
            'item_id' => $existingLine['item_id'] ?? null,
        ]);
        flash('danger', 'Failed to delete line item.');
    }
    redirect('physical_inventory_items.php?report_id=' . $reportId);
}

redirect('physical_inventory_reports.php');
