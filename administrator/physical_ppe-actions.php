<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/PhysicalPpe.model.php';

$reportModel = new PhysicalPpeReport();
$itemModel = new PhysicalPpeItem();

function logPhysicalPpeAction(string $action, string $entity, $entityId = null, array $metadata = [], array $details = []): void
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
    $station = trim($_POST['station'] ?? '');
    $preparedBy = (int)($_POST['prepared_by'] ?? 0);
    $verifiedByInput = $_POST['verified_by'] ?? '';
    $verifiedBy = $verifiedByInput === '' ? null : (int)$verifiedByInput;
    $remarks = $_POST['remarks'] ?? null;

    if ($reportNo === '' || $reportDate === '' || $fundCluster === '' || $station === '' || $preparedBy === 0) {
        flash('danger', 'Report No., Report Date, Fund Cluster, Station, and Prepared By are required.');
        redirect('physical_ppe_reports.php');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
        flash('danger', 'Invalid report date format.');
        redirect('physical_ppe_reports.php');
    }

    $duplicate = PhysicalPpeReport::getByReportNo($reportNo);
    if ($duplicate) {
        logPhysicalPpeAction('physical_ppe.report.create.failed', 'physical_ppe_reports', null, [
            'reason' => 'duplicate_report_no',
            'report_no' => $reportNo,
        ]);
        flash('danger', 'Another RPCPPE already uses this report number.');
        redirect('physical_ppe_reports.php');
    }

    $result = PhysicalPpeReport::add(
        $reportNo,
        $reportDate,
        $fundCluster,
        $station,
        $preparedBy,
        $verifiedBy,
        $remarks
    );

    if ($result) {
        logPhysicalPpeAction('physical_ppe.report.create.success', 'physical_ppe_reports', (int)$result, [
            'report_no' => $reportNo,
        ], [
            'report_no' => ['old' => null, 'new' => $reportNo],
            'report_date' => ['old' => null, 'new' => $reportDate],
            'fund_cluster' => ['old' => null, 'new' => $fundCluster],
            'station' => ['old' => null, 'new' => $station],
            'prepared_by' => ['old' => null, 'new' => $preparedBy],
            'verified_by' => ['old' => null, 'new' => $verifiedBy],
            'remarks' => ['old' => null, 'new' => $remarks],
        ]);
        flash('success', 'RPCPPE created successfully.');
    } else {
        logPhysicalPpeAction('physical_ppe.report.create.failed', 'physical_ppe_reports', null, [
            'reason' => 'database',
            'report_no' => $reportNo,
        ]);
        flash('danger', 'Failed to create RPCPPE.');
    }
    redirect('physical_ppe_reports.php');
}

if (POSTACT('update_report')) {
    $id = (int)($_POST['id'] ?? 0);
    $reportNo = trim($_POST['report_no'] ?? '');
    $reportDate = $_POST['report_date'] ?? '';
    $fundCluster = trim($_POST['fund_cluster'] ?? '');
    $station = trim($_POST['station'] ?? '');
    $preparedBy = (int)($_POST['prepared_by'] ?? 0);
    $verifiedByInput = $_POST['verified_by'] ?? '';
    $verifiedBy = $verifiedByInput === '' ? null : (int)$verifiedByInput;
    $remarks = $_POST['remarks'] ?? null;

    if ($id === 0) {
        flash('danger', 'RPCPPE reference is required.');
        redirect('physical_ppe_reports.php');
    }

    if ($reportNo === '' || $reportDate === '' || $fundCluster === '' || $station === '' || $preparedBy === 0) {
        flash('danger', 'Report No., Report Date, Fund Cluster, Station, and Prepared By are required.');
        redirect('physical_ppe_reports.php');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
        flash('danger', 'Invalid report date format.');
        redirect('physical_ppe_reports.php');
    }

    $existing = $reportModel->getById($id);
    if (!$existing) {
        logPhysicalPpeAction('physical_ppe.report.update.failed', 'physical_ppe_reports', $id, [
            'reason' => 'not_found',
        ]);
        flash('danger', 'RPCPPE not found.');
        redirect('physical_ppe_reports.php');
    }

    $duplicate = PhysicalPpeReport::getByReportNo($reportNo);
    if ($duplicate && (int)$duplicate['id'] !== $id) {
        logPhysicalPpeAction('physical_ppe.report.update.failed', 'physical_ppe_reports', $id, [
            'reason' => 'duplicate_report_no',
            'report_no' => $reportNo,
        ]);
        flash('danger', 'Another RPCPPE already uses this report number.');
        redirect('physical_ppe_reports.php');
    }

    $changes = [];
    foreach ([
        'report_no' => $reportNo,
        'report_date' => $reportDate,
        'fund_cluster' => $fundCluster,
        'station' => $station,
        'prepared_by' => $preparedBy,
        'verified_by' => $verifiedBy,
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

    $result = PhysicalPpeReport::update(
        $id,
        $reportNo,
        $reportDate,
        $fundCluster,
        $station,
        $preparedBy,
        $verifiedBy,
        $remarks
    );

    if ($result) {
        if (empty($changes)) {
            logPhysicalPpeAction('physical_ppe.report.update.no_changes', 'physical_ppe_reports', $id, [
                'report_no' => $reportNo,
            ]);
        } else {
            logPhysicalPpeAction('physical_ppe.report.update.success', 'physical_ppe_reports', $id, [
                'report_no' => $reportNo,
            ], $changes);
        }
        flash('success', 'RPCPPE updated successfully.');
    } else {
        logPhysicalPpeAction('physical_ppe.report.update.failed', 'physical_ppe_reports', $id, [
            'reason' => 'database',
            'report_no' => $reportNo,
        ], $changes);
        flash('danger', 'Failed to update RPCPPE.');
    }
    redirect('physical_ppe_reports.php');
}

if (POSTACT('delete_report')) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id === 0) {
        flash('danger', 'RPCPPE reference is required.');
        redirect('physical_ppe_reports.php');
    }

    $existing = $reportModel->getById($id);
    if (!$existing) {
        logPhysicalPpeAction('physical_ppe.report.delete.failed', 'physical_ppe_reports', $id, [
            'reason' => 'not_found',
        ]);
        flash('danger', 'RPCPPE not found.');
        redirect('physical_ppe_reports.php');
    }

    $result = PhysicalPpeReport::deleteReport($id);

    if ($result) {
        logPhysicalPpeAction('physical_ppe.report.delete.success', 'physical_ppe_reports', $id, [
            'report_no' => $existing['report_no'] ?? null,
        ]);
        flash('success', 'RPCPPE and its items were removed.');
    } else {
        logPhysicalPpeAction('physical_ppe.report.delete.failed', 'physical_ppe_reports', $id, [
            'reason' => 'database',
            'report_no' => $existing['report_no'] ?? null,
        ]);
        flash('danger', 'Failed to delete RPCPPE.');
    }
    redirect('physical_ppe_reports.php');
}

if (POSTACT('add_item')) {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $propertyCardQty = (int)($_POST['property_card_qty'] ?? 0);
    $physicalQty = (int)($_POST['physical_qty'] ?? 0);
    $cost = (float)($_POST['cost'] ?? 0);
    $propertyNo = $_POST['property_no'] ?? null;
    $assetTag = $_POST['asset_tag'] ?? null;
    $remarks = $_POST['remarks'] ?? null;

    if ($reportId === 0) {
        flash('danger', 'RPCPPE reference is required.');
        redirect('physical_ppe_reports.php');
    }

    $report = $reportModel->getById($reportId);
    if (!$report) {
        flash('danger', 'RPCPPE not found.');
        redirect('physical_ppe_reports.php');
    }

    if ($itemId === 0) {
        flash('danger', 'Item selection is required.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    if ($propertyCardQty < 0 || $physicalQty < 0) {
        flash('danger', 'Quantities cannot be negative.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    if ($cost < 0) {
        flash('danger', 'Cost cannot be negative.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    $existingLine = PhysicalPpeItem::getByReportAndItem($reportId, $itemId);
    if ($existingLine) {
        logPhysicalPpeAction('physical_ppe.item.create.failed', 'physical_ppe_items', null, [
            'reason' => 'duplicate_item',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ]);
        flash('danger', 'This item is already listed in the selected RPCPPE.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    $result = PhysicalPpeItem::add(
        $reportId,
        $itemId,
        $propertyCardQty,
        $physicalQty,
        $cost,
        $propertyNo,
        $assetTag,
        $remarks
    );

    if ($result) {
        logPhysicalPpeAction('physical_ppe.item.create.success', 'physical_ppe_items', (int)$result, [
            'report_id' => $reportId,
            'item_id' => $itemId,
        ], [
            'report_id' => ['old' => null, 'new' => $reportId],
            'item_id' => ['old' => null, 'new' => $itemId],
            'property_card_qty' => ['old' => null, 'new' => $propertyCardQty],
            'physical_qty' => ['old' => null, 'new' => $physicalQty],
            'cost' => ['old' => null, 'new' => $cost],
            'property_no' => ['old' => null, 'new' => $propertyNo],
            'asset_tag' => ['old' => null, 'new' => $assetTag],
            'remarks' => ['old' => null, 'new' => $remarks],
        ]);
        flash('success', 'PPE line added successfully.');
    } else {
        logPhysicalPpeAction('physical_ppe.item.create.failed', 'physical_ppe_items', null, [
            'reason' => 'database',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ]);
        flash('danger', 'Failed to add PPE line.');
    }
    redirect('physical_ppe_items.php?report_id=' . $reportId);
}

if (POSTACT('update_item')) {
    $id = (int)($_POST['id'] ?? 0);
    $reportId = (int)($_POST['report_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $propertyCardQty = (int)($_POST['property_card_qty'] ?? 0);
    $physicalQty = (int)($_POST['physical_qty'] ?? 0);
    $cost = (float)($_POST['cost'] ?? 0);
    $propertyNo = $_POST['property_no'] ?? null;
    $assetTag = $_POST['asset_tag'] ?? null;
    $remarks = $_POST['remarks'] ?? null;

    if ($id === 0 || $reportId === 0) {
        flash('danger', 'Line reference is required.');
        redirect('physical_ppe_reports.php');
    }

    $report = $reportModel->getById($reportId);
    if (!$report) {
        flash('danger', 'RPCPPE not found.');
        redirect('physical_ppe_reports.php');
    }

    $existingLine = $itemModel->getById($id);
    if (!$existingLine) {
        logPhysicalPpeAction('physical_ppe.item.update.failed', 'physical_ppe_items', $id, [
            'reason' => 'not_found',
            'report_id' => $reportId,
        ]);
        flash('danger', 'PPE line not found.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    if ($itemId === 0) {
        flash('danger', 'Item selection is required.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    if ($propertyCardQty < 0 || $physicalQty < 0) {
        flash('danger', 'Quantities cannot be negative.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    if ($cost < 0) {
        flash('danger', 'Cost cannot be negative.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    $duplicate = PhysicalPpeItem::getByReportAndItem($reportId, $itemId);
    if ($duplicate && (int)$duplicate['id'] !== $id) {
        logPhysicalPpeAction('physical_ppe.item.update.failed', 'physical_ppe_items', $id, [
            'reason' => 'duplicate_item',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ]);
        flash('danger', 'Another line already uses this item in the RPCPPE.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    $changes = [];
    foreach ([
        'item_id' => $itemId,
        'property_card_qty' => $propertyCardQty,
        'physical_qty' => $physicalQty,
        'cost' => $cost,
        'property_no' => $propertyNo,
        'asset_tag' => $assetTag,
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

    $result = PhysicalPpeItem::update(
        $id,
        $reportId,
        $itemId,
        $propertyCardQty,
        $physicalQty,
        $cost,
        $propertyNo,
        $assetTag,
        $remarks
    );

    if ($result) {
        if (empty($changes)) {
            logPhysicalPpeAction('physical_ppe.item.update.no_changes', 'physical_ppe_items', $id, [
                'report_id' => $reportId,
                'item_id' => $itemId,
            ]);
        } else {
            logPhysicalPpeAction('physical_ppe.item.update.success', 'physical_ppe_items', $id, [
                'report_id' => $reportId,
                'item_id' => $itemId,
            ], $changes);
        }
        flash('success', 'PPE line updated successfully.');
    } else {
        logPhysicalPpeAction('physical_ppe.item.update.failed', 'physical_ppe_items', $id, [
            'reason' => 'database',
            'report_id' => $reportId,
            'item_id' => $itemId,
        ], $changes);
        flash('danger', 'Failed to update PPE line.');
    }
    redirect('physical_ppe_items.php?report_id=' . $reportId);
}

if (POSTACT('delete_item')) {
    $id = (int)($_POST['id'] ?? 0);
    $reportId = (int)($_POST['report_id'] ?? 0);

    if ($id === 0 || $reportId === 0) {
        flash('danger', 'Line reference is required.');
        redirect('physical_ppe_reports.php');
    }

    $report = $reportModel->getById($reportId);
    if (!$report) {
        flash('danger', 'RPCPPE not found.');
        redirect('physical_ppe_reports.php');
    }

    $existingLine = $itemModel->getById($id);
    if (!$existingLine) {
        logPhysicalPpeAction('physical_ppe.item.delete.failed', 'physical_ppe_items', $id, [
            'reason' => 'not_found',
            'report_id' => $reportId,
        ]);
        flash('danger', 'PPE line not found.');
        redirect('physical_ppe_items.php?report_id=' . $reportId);
    }

    $result = $itemModel->deleteById($id);

    if ($result) {
        logPhysicalPpeAction('physical_ppe.item.delete.success', 'physical_ppe_items', $id, [
            'report_id' => $reportId,
            'item_id' => $existingLine['item_id'] ?? null,
        ]);
        flash('success', 'PPE line removed.');
    } else {
        logPhysicalPpeAction('physical_ppe.item.delete.failed', 'physical_ppe_items', $id, [
            'reason' => 'database',
            'report_id' => $reportId,
            'item_id' => $existingLine['item_id'] ?? null,
        ]);
        flash('danger', 'Failed to delete PPE line.');
    }
    redirect('physical_ppe_items.php?report_id=' . $reportId);
}

redirect('physical_ppe_reports.php');
