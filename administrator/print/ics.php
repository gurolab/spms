<?php

require_once __DIR__ . '/../admin_guard.php';
require_once __DIR__ . '/../../core/helper.php';
require_once __DIR__ . '/../../core/pdf/TcpdfHelper.php';
require_once __DIR__ . '/../../repo/User.model.php';

$icsId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($icsId <= 0) {
    flash('danger', 'Invalid ICS reference.');
    redirect('../ics.php');
}

$db = User::Db();

$headerSql = "
    SELECT
        ics.*,
        assignee.fullname AS assigned_name,
        assignee.departmentname AS assigned_department,
        assignee.rolename AS assigned_role,
        issuer.fullname AS issued_name,
        issuer.rolename AS issued_role
    FROM inventory_custodian_slips ics
    LEFT JOIN user_role_dept assignee ON assignee.id = ics.assigned_to
    LEFT JOIN user_role_dept issuer ON issuer.id = ics.issued_by
    WHERE ics.id = ?
    LIMIT 1
";

$headerStmt = $db->prepare($headerSql);
$headerStmt->execute([$icsId]);
$header = $headerStmt->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    flash('danger', 'ICS record not found.');
    redirect('../ics.php');
}

$itemsSql = "
    SELECT
        i.*,
        it.code AS stock_no,
        it.description,
        it.unit
    FROM ics_items i
    JOIN items it ON it.id = i.item_id
    WHERE i.ics_id = ?
    ORDER BY it.code ASC, it.description ASC
";

$itemsStmt = $db->prepare($itemsSql);
$itemsStmt->execute([$icsId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$settings = SystemSettings::getSettings();
$orgName = strtoupper($settings['organization_name'] ?? 'Cotabato State University');
$orgAddress = $settings['address'] ?? '';

$icsNo = $header['ics_no'] ?? '';
$propertyNo = $header['property_no'] ?? '';
$issuedDate = spms_pdf_format_date($header['issued_date'] ?? null);
$status = strtoupper($header['status'] ?? '');
$valueType = strtoupper(trim((string)($header['value_type'] ?? '')));
$valueTypeLabel = $valueType === 'HIGH'
    ? 'HIGH VALUE SEMI-EXPENDABLE PROPERTY'
    : 'LOW VALUE SEMI-EXPENDABLE PROPERTY';
$fundCluster = trim((string)($header['fund_cluster'] ?? ''));
$remarks = trim((string)($header['remarks'] ?? ''));
$assignedName = strtoupper(trim((string)($header['assigned_name'] ?? '')));
$assignedDepartment = trim((string)($header['assigned_department'] ?? ''));
$issuedName = strtoupper(trim((string)($header['issued_name'] ?? '')));

$orgNameDisplay = spms_pdf_escape($orgName);
$orgAddressDisplay = spms_pdf_escape($orgAddress);
$icsNoDisplay = spms_pdf_escape($icsNo);
$propertyNoDisplay = spms_pdf_escape($propertyNo);
$issuedDateDisplay = spms_pdf_escape($issuedDate);
$statusDisplay = spms_pdf_escape($status);
$valueTypeLabelDisplay = spms_pdf_escape($valueTypeLabel);
$fundClusterDisplay = spms_pdf_escape($fundCluster);
$remarksDisplay = spms_pdf_escape($remarks);
$assignedNameDisplay = spms_pdf_escape($assignedName);
$assignedDepartmentDisplay = spms_pdf_escape($assignedDepartment);
$issuedNameDisplay = spms_pdf_escape($issuedName);

$pdf = spms_pdf_init('ICS - ' . $icsNo, 'P', 'A4');

$styles = <<<CSS
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #111; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .fw-bold { font-weight: bold; }
    .bordered { width: 100%; border: 1px solid #111; border-collapse: collapse; table-layout: fixed; }
    .bordered td, .bordered th { border: 1px solid #111; padding: 4px 6px; line-height: 1.25; }
    .bordered td { vertical-align: middle; }
    .bordered th { vertical-align: middle; }
    .meta { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .meta td { padding: 4px 6px; line-height: 1.25; vertical-align: middle; }
    .meta-grid td { padding: 4px 6px; line-height: 1.25; vertical-align: middle; }
    .meta-grid .label { font-weight: bold; }
    .ics-main th, .ics-main td { line-height: 1.2; }
    .ics-main th { padding: 3px 4px; }
    .ics-main td { padding: 4px 4px; vertical-align: top; }
    .ics-main .qty-col,
    .ics-main .unit-col,
    .ics-main .life-col { text-align: center; }
    .ics-main .unit-cost-col,
    .ics-main .total-cost-col { text-align: right; }
    .ics-main .inv-col,
    .ics-main .remarks-col { word-break: break-all; }
    .remarks-grid td { vertical-align: top; }
    .small { font-size: 8pt; }
    .signature-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .signature-table td { padding: 10px 8px 6px 8px; line-height: 1.25; vertical-align: top; }
    .header-box { border: 1px solid #111; padding: 4px 6px; min-height: 20px; line-height: 1.25; word-wrap: break-word; }
    .gov-header { line-height: 0.9; margin: 0; padding: 0; }
    .gov-header div { margin: 0; padding: 0; }
    .gov-line { font-size: 8pt; margin-bottom: -1px; }
    .org-line { font-size: 13pt; font-weight: bold; color: #800000; margin-top: -1px; margin-bottom: -2px; }
    .org-address { font-size: 8pt; margin-top: -1px; }
    .signature-space { height: 34px; }
    .sig-name { text-align: center; font-weight: bold; text-transform: uppercase; }
    .sig-caption { text-align: center; font-size: 8pt; }
</style>
CSS;

$totalValue = 0;
$rows = '';
if (empty($items)) {
    $rows = '<tr><td class="text-center" colspan="8">No items encoded for this ICS.</td></tr>';
} else {
    foreach ($items as $item) {
        $qty = (int)($item['qty'] ?? 0);
        $unitValue = (float)($item['unit_value'] ?? 0);
        $amount = $qty * $unitValue;
        $estimatedUsefulLife = trim((string)($item['estimated_useful_life'] ?? ''));
        if ($estimatedUsefulLife === '') {
            $estimatedUsefulLife = '-';
        }
        $totalValue += $amount;
        $rows .= sprintf(
            '<tr>
                <td width="10%%" class="qty-col">%d</td>
                <td width="9%%" class="unit-col">%s</td>
                <td width="12%%" class="unit-cost-col">%s</td>
                <td width="12%%" class="total-cost-col">%s</td>
                <td width="24%%" class="desc-col">%s</td>
                <td width="14%%" class="inv-col">%s</td>
                <td width="9%%" class="life-col">%s</td>
                <td width="10%%" class="remarks-col">%s</td>
            </tr>',
            $qty,
            htmlspecialchars((string)($item['unit'] ?? ''), ENT_QUOTES, 'UTF-8'),
            number_format($unitValue, 2),
            number_format($amount, 2),
            htmlspecialchars((string)($item['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['property_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($estimatedUsefulLife, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['remarks'] ?? ''), ENT_QUOTES, 'UTF-8')
        );
    }
}

$totalValueFormatted = number_format($totalValue, 2);

$html = <<<HTML
{$styles}
<div class="text-center gov-header">
    <div class="gov-line">REPUBLIC OF THE PHILIPPINES</div>
    <div class="org-line">{$orgNameDisplay}</div>
    <div class="org-address">{$orgAddressDisplay}</div>
</div>

<h2 class="text-center" style="margin: 10px 0 2px 0; font-size: 13pt;">INVENTORY CUSTODIAN SLIP</h2>
<div class="text-center small fw-bold">({$valueTypeLabelDisplay})</div>
<br /><br />

<table width="100%" class="bordered meta-grid" cellspacing="0" cellpadding="0">
    <tr>
        <td width="20%" class="label">Entity Name:</td>
        <td width="30%">{$orgNameDisplay}</td>
        <td width="20%" class="label">Fund Cluster:</td>
        <td width="30%">{$fundClusterDisplay}</td>
    </tr>
    <tr>
        <td class="label">ICS No.:</td>
        <td>{$icsNoDisplay}</td>
        <td class="label">Control No.:</td>
        <td>{$propertyNoDisplay}</td>
    </tr>
    <tr>
        <td class="label">Issued Date:</td>
        <td>{$issuedDateDisplay}</td>
        <td class="label">Status:</td>
        <td>{$statusDisplay}</td>
    </tr>
</table>

<table width="100%" class="bordered ics-main" cellspacing="0" cellpadding="0" style="margin-top:6px;">
    <thead>
        <tr class="text-center fw-bold">
            <th width="10%" class="qty-col">Quantity</th>
            <th width="9%" class="unit-col">Unit</th>
            <th width="12%" class="unit-cost-col">Unit Cost</th>
            <th width="12%" class="total-cost-col">Total Cost</th>
            <th width="24%" class="desc-col">Description</th>
            <th width="14%" class="inv-col">Inventory Item No.</th>
            <th width="9%" class="life-col">Estimated Useful Life</th>
            <th width="10%" class="remarks-col">Remarks</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
        <tr class="fw-bold">
            <td colspan="3" class="text-right">TOTAL</td>
            <td class="text-right">{$totalValueFormatted}</td>
            <td colspan="4"></td>
        </tr>
    </tbody>
</table>

<table width="100%" class="bordered remarks-grid" cellspacing="0" cellpadding="0" style="margin-top:14px;">
    <tr>
        <td width="18%" class="fw-bold">General Remarks:</td>
        <td width="82%">{$remarksDisplay}</td>
    </tr>
</table>
<br /><br />

<table width="100%" class="signature-table" style="margin-top:0;">
    <tr class="text-center fw-bold">
        <td width="50%">Received by:</td>
        <td width="50%">Received From:</td>
    </tr>
    <tr>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
    </tr>
    <tr>
        <td class="sig-name">{$assignedNameDisplay}</td>
        <td class="sig-name">{$issuedNameDisplay}</td>
    </tr>
    <tr>
        <td class="sig-caption">Signature over Printed Name</td>
        <td class="sig-caption">Signature over Printed Name</td>
    </tr>
    <tr>
        <td class="sig-caption">{$assignedDepartmentDisplay}</td>
        <td class="sig-caption">&nbsp;</td>
    </tr>
    <tr>
        <td class="sig-caption">Date: {$issuedDateDisplay}</td>
        <td class="sig-caption">Date: {$issuedDateDisplay}</td>
    </tr>
</table>
HTML;

spms_pdf_render($pdf, $html);
spms_pdf_output($pdf, spms_pdf_filename('ICS', $icsNo));
