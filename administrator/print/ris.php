<?php

require_once __DIR__ . '/../admin_guard.php';
require_once __DIR__ . '/../../core/helper.php';
require_once __DIR__ . '/../../core/pdf/TcpdfHelper.php';
require_once __DIR__ . '/../../repo/RIS.model.php';
require_once __DIR__ . '/../../repo/User.model.php';

$risId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($risId <= 0) {
    flash('danger', 'Invalid RIS reference.');
    redirect('../ris.php');
}

$db = User::Db();

$headerStmt = $db->prepare(
    "SELECT
        rs.*,
        dept.name AS division_name,
        dept.code AS division_code,
        req.fullname AS requested_name,
        req.rolename AS requested_role,
        approver.fullname AS approved_name,
        approver.rolename AS approved_role,
        issuer.fullname AS issued_name,
        issuer.rolename AS issued_role,
        receiver.fullname AS received_name,
        receiver.rolename AS received_role
     FROM requisition_slips rs
     LEFT JOIN departments dept ON dept.id = rs.division
     LEFT JOIN user_role_dept req ON req.id = rs.requested_by
     LEFT JOIN user_role_dept approver ON approver.id = rs.approved_by
     LEFT JOIN user_role_dept issuer ON issuer.id = rs.issued_by
     LEFT JOIN user_role_dept receiver ON receiver.id = rs.received_by
     WHERE rs.id = ? LIMIT 1"
);

$headerStmt->execute([$risId]);
$header = $headerStmt->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    flash('danger', 'RIS record not found.');
    redirect('../ris.php');
}

$itemsStmt = $db->prepare(
    "SELECT
        ri.*,
        it.code AS stock_no,
        it.description,
        it.unit
     FROM requisition_items ri
     JOIN items it ON it.id = ri.item_id
     WHERE ri.ris_id = ?
     ORDER BY it.code ASC, it.description ASC"
);
$itemsStmt->execute([$risId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$settings = SystemSettings::getSettings();
$orgName = strtoupper($settings['organization_name'] ?? 'Cotabato State University');
$orgAddress = $settings['address'] ?? '';

$risNo = $header['ris_no'] ?? '';
$fundCluster = $header['fund_cluster'] ?? '';
$division = $header['division_name'] ?? '';
$divisionCode = $header['division_code'] ?? '';
$responsibilityCode = $header['responsibility_center_code'] ?? '';
$requisitionDate = $header['requisition_date'] ? date('F d, Y', strtotime($header['requisition_date'])) : '';
$purpose = trim((string)($header['purpose'] ?? ''));
$remarks = trim((string)($header['remarks'] ?? ''));

$requestedName = strtoupper(trim((string)($header['requested_name'] ?? '')));
$approvedName = strtoupper(trim((string)($header['approved_name'] ?? '')));
$issuedName = strtoupper(trim((string)($header['issued_name'] ?? '')));
$receivedName = strtoupper(trim((string)($header['received_name'] ?? '')));

$pdf = spms_pdf_init('RIS - ' . $risNo, 'P', 'A4');

$styles = <<<CSS
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #111; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .fw-bold { font-weight: bold; }
    .mt-2 { margin-top: 6px; }
    .mb-1 { margin-bottom: 4px; }
    .mb-2 { margin-bottom: 8px; }
    .mb-3 { margin-bottom: 12px; }
    .bordered { width: 100%; border: 1px solid #111; border-collapse: collapse; table-layout: fixed; }
    .bordered td, .bordered th { border: 1px solid #111; padding: 4px 6px; line-height: 1.25; }
    .bordered td { vertical-align: middle; }
    .bordered th { vertical-align: middle; }
    .metadata { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .metadata td { padding: 4px 6px; line-height: 1.25; vertical-align: middle; }
    .signature-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .signature-table td { padding: 10px 8px 4px 8px; line-height: 1.25; vertical-align: top; }
    .small { font-size: 8pt; }
    .uppercase { text-transform: uppercase; }
    .purpose { border: 1px solid #111; min-height: 40px; padding: 6px; }
    .remarks { border: 1px solid #111; min-height: 32px; padding: 6px; }
    .header-box { border: 1px solid #111; padding: 4px 6px; min-height: 20px; line-height: 1.25; word-wrap: break-word; }
    .gov-header { line-height: 0.9; margin: 0; padding: 0; }
    .gov-header div { margin: 0; padding: 0; }
    .gov-line { font-size: 8pt; margin-bottom: -1px; }
    .org-line { font-size: 13pt; font-weight: bold; color: #800000; margin-top: -1px; margin-bottom: -2px; }
    .org-address { font-size: 8pt; margin-top: -1px; }
    .signature-space { height: 34px; }
    .sig-name { text-align: center; font-weight: bold; text-transform: uppercase; }
    .sig-caption { text-align: center; font-size: 8pt; }
    .ris-table .head-top th { padding-top: 4px; padding-bottom: 3px; line-height: 1.2; }
    .ris-table .head-sub th { padding-top: 3px; padding-bottom: 3px; line-height: 1.15; }
    .ris-table .data-row td { padding-top: 5px; padding-bottom: 5px; }
</style>
CSS;

$itemsRows = '';
if (empty($items)) {
    $itemsRows .= '<tr><td class="text-center" colspan="8">No items recorded.</td></tr>';
} else {
    foreach ($items as $row) {
        $requestedQty = (int)($row['qty_requested'] ?? 0);
        $issuedQty = (int)($row['qty_issued'] ?? 0);

        $isAvailable = $requestedQty > 0
            ? $issuedQty >= $requestedQty
            : $issuedQty > 0;

        $yesMark = $isAvailable ? '&#10003;' : '';
        $noMark = $isAvailable ? '' : '&#10003;';

        $itemsRows .= sprintf(
            '<tr class="data-row">
                <td width="15%%">%s</td>
                <td width="8%%" class="text-center">%s</td>
                <td width="31%%">%s</td>
                <td width="10%%" class="text-center">%d</td>
                <td width="7%%" class="text-center">%s</td>
                <td width="7%%" class="text-center">%s</td>
                <td width="10%%" class="text-center">%d</td>
                <td width="12%%">%s</td>
            </tr>',
            htmlspecialchars((string)($row['stock_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($row['unit'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            $requestedQty,
            $yesMark,
            $noMark,
            $issuedQty,
            htmlspecialchars((string)($row['remarks'] ?? ''), ENT_QUOTES, 'UTF-8')
        );
    }
}

$html = <<<HTML
{$styles}
<div class="text-center gov-header">
    <div class="gov-line">REPUBLIC OF THE PHILIPPINES</div>
    <div class="org-line">{$orgName}</div>
    <div class="org-address">{$orgAddress}</div>
</div>
<div class="text-right small">Appendix 63</div>

<h2 class="text-center" style="margin: 10px 0 6px 0; font-size: 13pt;">REQUISITION AND ISSUE SLIP (RIS)</h2>

<table width="100%" class="metadata">
    <tr>
        <td width="20%" class="fw-bold">Entity Name:</td>
        <td width="40%" class="header-box uppercase">{$orgName}</td>
        <td width="15%" class="fw-bold">Fund Cluster:</td>
        <td width="25%" class="header-box">{$fundCluster}</td>
    </tr>
    <tr>
        <td class="fw-bold">Division:</td>
        <td class="header-box">{$division}</td>
        <td class="fw-bold">Responsibility Center Code:</td>
        <td class="header-box">{$responsibilityCode}</td>
    </tr>
    <tr>
        <td class="fw-bold">Office/Section:</td>
        <td class="header-box">{$divisionCode}</td>
        <td class="fw-bold">RIS No.:</td>
        <td class="header-box">{$risNo}</td>
    </tr>
    <tr>
        <td class="fw-bold">Requisition Date:</td>
        <td class="header-box">{$requisitionDate}</td>
        <td class="fw-bold">Status:</td>
        <td class="header-box">{$header['status']}</td>
    </tr>
</table>

<div class="mt-2 mb-1 fw-bold">Purpose:</div>
<div class="purpose">{$purpose}</div>

<table width="100%" class="bordered mt-2 ris-table" cellspacing="0" cellpadding="0">
    <thead>
        <tr class="text-center fw-bold head-top">
            <th width="15%" rowspan="2">Stock No.</th>
            <th width="8%" rowspan="2">Unit</th>
            <th width="31%" rowspan="2">Description</th>
            <th width="10%">Requisition</th>
            <th width="14%" colspan="2">Stock Available?</th>
            <th width="10%">Issue</th>
            <th width="12%" rowspan="2">Remarks</th>
        </tr>
        <tr class="text-center fw-bold head-sub">
            <th width="10%">Quantity</th>
            <th width="7%">Yes</th>
            <th width="7%">No</th>
            <th width="10%">Quantity</th>
        </tr>
    </thead>
    <tbody>
        {$itemsRows}
    </tbody>
</table>

<div class="mt-2 mb-1 fw-bold">Remarks:</div>
<div class="remarks">{$remarks}</div>

<table width="100%" class="signature-table mt-3">
    <tr class="text-center fw-bold">
        <td width="25%">Requested by:</td>
        <td width="25%">Approved by:</td>
        <td width="25%">Issued by:</td>
        <td width="25%">Received by:</td>
    </tr>
    <tr>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
    </tr>
    <tr>
        <td class="sig-name">{$requestedName}</td>
        <td class="sig-name">{$approvedName}</td>
        <td class="sig-name">{$issuedName}</td>
        <td class="sig-name">{$receivedName}</td>
    </tr>
    <tr>
        <td class="sig-caption">Signature over Printed Name</td>
        <td class="sig-caption">Signature over Printed Name</td>
        <td class="sig-caption">Signature over Printed Name</td>
        <td class="sig-caption">Signature over Printed Name</td>
    </tr>
</table>
HTML;

spms_pdf_render($pdf, $html);

ob_end_clean();
$pdf->Output('RIS-' . preg_replace('/[^A-Za-z0-9\-]/', '', $risNo) . '.pdf', 'I');
exit;
