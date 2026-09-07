<?php

require_once __DIR__ . '/../admin_guard.php';
require_once __DIR__ . '/../../core/helper.php';
require_once __DIR__ . '/../../core/pdf/TcpdfHelper.php';
require_once __DIR__ . '/../../repo/User.model.php';

$issuanceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($issuanceId <= 0) {
    flash('danger', 'Invalid RSMI reference.');
    redirect('../supply_issuance.php');
}

$db = User::Db();

$headerSql = "
    SELECT
        si.*,
        ris.ris_no,
        ris.purpose,
        ris.fund_cluster,
        ris.responsibility_center_code,
        ris.requisition_date,
        dept.name AS division_name,
        dept.code AS division_code,
        req.fullname AS requested_name,
        issuer.fullname AS issued_name,
        issuer.rolename AS issued_role,
        receiver.fullname AS received_name,
        receiver.rolename AS received_role
    FROM supply_issuances si
    LEFT JOIN requisition_slips ris ON ris.id = si.ris_id
    LEFT JOIN departments dept ON dept.id = ris.division
    LEFT JOIN user_role_dept req ON req.id = ris.requested_by
    LEFT JOIN user_role_dept issuer ON issuer.id = si.issued_by
    LEFT JOIN user_role_dept receiver ON receiver.id = si.received_by
    WHERE si.id = ?
    LIMIT 1
";

$headerStmt = $db->prepare($headerSql);
$headerStmt->execute([$issuanceId]);
$header = $headerStmt->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    flash('danger', 'RSMI record not found.');
    redirect('../supply_issuance.php');
}

$itemSql = "
    SELECT
        sii.*,
        it.code AS stock_no,
        it.description,
        it.unit,
        it.unit_cost,
        ri.qty_requested
    FROM supply_issuance_items sii
    JOIN items it ON it.id = sii.item_id
    LEFT JOIN requisition_items ri ON ri.id = sii.requisition_item_id
    WHERE sii.issuance_id = ?
    ORDER BY it.code ASC, it.description ASC
";

$itemsStmt = $db->prepare($itemSql);
$itemsStmt->execute([$issuanceId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$settings = SystemSettings::getSettings();
$orgName = strtoupper($settings['organization_name'] ?? 'Cotabato State University');
$orgAddress = $settings['address'] ?? '';

$rsmiNo = $header['rsmi_no'] ?? '';
$issuanceDate = $header['issuance_date'] ? date('F d, Y', strtotime($header['issuance_date'])) : '';
$fundCluster = $header['fund_cluster'] ?? '';
$divisionName = $header['division_name'] ?? '';
$divisionCode = $header['division_code'] ?? '';
$rcCode = $header['responsibility_center_code'] ?? '';
$purpose = trim((string)($header['purpose'] ?? ''));

$requestedName = strtoupper(trim((string)($header['requested_name'] ?? '')));
$issuedName = strtoupper(trim((string)($header['issued_name'] ?? '')));
$receivedName = strtoupper(trim((string)($header['received_name'] ?? '')));

$orgNameDisplay = htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8');
$orgAddressDisplay = htmlspecialchars((string)$orgAddress, ENT_QUOTES, 'UTF-8');
$rsmiNoDisplay = htmlspecialchars((string)$rsmiNo, ENT_QUOTES, 'UTF-8');
$issuanceDateDisplay = htmlspecialchars((string)$issuanceDate, ENT_QUOTES, 'UTF-8');
$fundClusterDisplay = htmlspecialchars((string)$fundCluster, ENT_QUOTES, 'UTF-8');
$divisionNameDisplay = htmlspecialchars((string)$divisionName, ENT_QUOTES, 'UTF-8');
$risNoDisplay = htmlspecialchars((string)($header['ris_no'] ?? ''), ENT_QUOTES, 'UTF-8');
$issuedNameDisplay = htmlspecialchars($issuedName, ENT_QUOTES, 'UTF-8');
$receivedNameDisplay = htmlspecialchars($receivedName, ENT_QUOTES, 'UTF-8');

$pdf = spms_pdf_init('RSMI - ' . $rsmiNo, 'L', 'A4');

$styles = <<<CSS
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #111; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .fw-bold { font-weight: bold; }
    .bordered { width: 100%; border: 1px solid #111; border-collapse: collapse; table-layout: fixed; }
    .bordered td, .bordered th { border: 1px solid #111; padding: 5px 7px; line-height: 1.28; }
    .bordered td { vertical-align: middle; }
    .bordered th { vertical-align: middle; }
    .meta { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .meta td { padding: 4px 6px; line-height: 1.25; vertical-align: middle; }
    .signature-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .signature-table td { padding: 12px 8px 6px 8px; line-height: 1.25; vertical-align: top; }
    .small { font-size: 8pt; }
    .uppercase { text-transform: uppercase; }
    .header-box { border: 1px solid #111; padding: 3px 5px; min-height: 20px; line-height: 1.25; word-wrap: break-word; }
    .gov-header { line-height: 0.9; margin: 0; padding: 0; }
    .gov-header div { margin: 0; padding: 0; }
    .gov-line { font-size: 8pt; margin-bottom: -1px; }
    .org-line { font-size: 13pt; font-weight: bold; color: #800000; margin-top: -1px; margin-bottom: -2px; }
    .org-address { font-size: 8pt; margin-top: -1px; }
    .report-title { margin: 12px 0 18px 0; font-size: 13pt; }
    .meta-grid td { vertical-align: middle; padding: 6px 8px; }
    .meta-grid .label { font-weight: bold; }
    .section-head th { font-weight: bold; text-align: center; vertical-align: middle; padding-top: 4px; padding-bottom: 4px; }
    .section-head .accounting-head { font-size: 8.5pt; line-height: 1.15; }
    .main-head th { padding-top: 4px; padding-bottom: 4px; line-height: 1.2; }
    .recap-table td, .recap-table th { padding: 4px 5px; }
    .signature-space { height: 36px; }
    .main-table .data-row td { padding-top: 6px; padding-bottom: 6px; }
    .sig-name { text-align: center; font-weight: bold; }
    .sig-caption { text-align: center; }
</style>
CSS;

$totalAmount = 0;
$totalFormatted = '0.00';
$rows = '';
$recap = [];
if (empty($items)) {
    $rows = '<tr><td class="text-center" colspan="8">No issuance items recorded.</td></tr>';
} else {
    foreach ($items as $item) {
        $qtyIssued = (int)($item['qty_issued'] ?? 0);
        $unitCost = (float)($item['unit_cost'] ?? 0);
        $amount = $qtyIssued * $unitCost;
        $totalAmount += $amount;

        $stockNo = trim((string)($item['stock_no'] ?? ''));
        if (!isset($recap[$stockNo])) {
            $recap[$stockNo] = [
                'qty' => 0,
                'unit_cost' => $unitCost,
                'total' => 0.0,
            ];
        }
        $recap[$stockNo]['qty'] += $qtyIssued;
        $recap[$stockNo]['unit_cost'] = $unitCost;
        $recap[$stockNo]['total'] += $amount;

        $rows .= sprintf(
            '<tr class="data-row">
                <td class="text-center">%s</td>
                <td class="text-center">%s</td>
                <td class="text-center">%s</td>
                <td>%s</td>
                <td class="text-center">%s</td>
                <td class="text-center">%d</td>
                <td class="text-right">%s</td>
                <td class="text-right">%s</td>
            </tr>',
            htmlspecialchars((string)($header['ris_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($rcCode, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['stock_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['unit'] ?? ''), ENT_QUOTES, 'UTF-8'),
            $qtyIssued,
            number_format($unitCost, 2),
            number_format($amount, 2)
        );
    }
    $totalFormatted = number_format($totalAmount, 2);
}

$recapRows = '';
if (empty($recap)) {
    $recapRows = '<tr><td colspan="5" class="text-center">No recapitulation data.</td></tr>';
} else {
    foreach ($recap as $stockNo => $data) {
        $recapRows .= sprintf(
            '<tr>
                <td width="30%%">%s</td>
                <td width="15%%" class="text-center">%d</td>
                <td width="20%%" class="text-right">%s</td>
                <td width="20%%" class="text-right">%s</td>
                <td width="15%%"></td>
            </tr>',
            htmlspecialchars((string)$stockNo, ENT_QUOTES, 'UTF-8'),
            (int)$data['qty'],
            number_format((float)$data['unit_cost'], 2),
            number_format((float)$data['total'], 2)
        );
    }
}

$html = <<<HTML
{$styles}
<div class="text-center gov-header">
    <div class="gov-line">REPUBLIC OF THE PHILIPPINES</div>
    <div class="org-line uppercase">{$orgNameDisplay}</div>
    <div class="org-address">{$orgAddressDisplay}</div>
</div>
<div class="text-right small">Appendix 64</div>

<h2 class="text-center report-title">REPORT OF SUPPLIES AND MATERIALS ISSUED (RSMI)</h2>

<table width="100%" class="bordered meta-grid" cellspacing="0" cellpadding="0">
    <tr>
        <td width="15%" class="label">Entity Name:</td>
        <td width="35%">{$orgNameDisplay}</td>
        <td width="15%" class="label">Fund Cluster:</td>
        <td width="35%">{$fundClusterDisplay}</td>
    </tr>
    <tr>
        <td class="label">Serial No.:</td>
        <td>{$rsmiNoDisplay}</td>
        <td class="label">RIS No.:</td>
        <td>{$risNoDisplay}</td>
    </tr>
    <tr>
        <td class="label">Date:</td>
        <td>{$issuanceDateDisplay}</td>
        <td class="label">Division:</td>
        <td>{$divisionNameDisplay}</td>
    </tr>
</table>

<table width="100%" class="bordered main-table" cellspacing="0" cellpadding="0" style="margin-top:6px;">
    <colgroup>
        <col style="width:10%;">
        <col style="width:14%;">
        <col style="width:10%;">
        <col style="width:26%;">
        <col style="width:7%;">
        <col style="width:8%;">
        <col style="width:12%;">
        <col style="width:13%;">
    </colgroup>
    <thead>
        <tr class="section-head">
            <th colspan="6">To be filled up by the Supply and/or Property Division/Unit</th>
            <th colspan="2" class="accounting-head">To be filled up by the Accounting<br />Division/Unit</th>
        </tr>
        <tr class="text-center fw-bold main-head">
            <th>RIS No.</th>
            <th>Responsibility Center Code</th>
            <th>Stock No.</th>
            <th>Item</th>
            <th>Unit</th>
            <th>Quantity Issued</th>
            <th>Unit Cost</th>
            <th>Amount</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
        <tr class="fw-bold">
            <td colspan="7" class="text-right">Grand Total</td>
            <td class="text-right">{$totalFormatted}</td>
        </tr>
    </tbody>
</table>

<table width="100%" class="meta" style="margin-top:8px;">
    <tr>
        <td width="100%" class="fw-bold">Recapitulation:</td>
    </tr>
</table>

<table width="100%" class="bordered recap-table" cellspacing="0" cellpadding="0">
    <thead>
        <tr class="text-center fw-bold">
            <th width="30%">Stock No.</th>
            <th width="15%">Quantity</th>
            <th width="20%">Unit Cost</th>
            <th width="20%">Total Cost</th>
            <th width="15%">UACS Object Code</th>
        </tr>
    </thead>
    <tbody>
        {$recapRows}
    </tbody>
</table>

<table width="100%" class="meta" style="margin-top:8px;">
    <tr>
        <td width="75%"></td>
        <td width="25%" class="fw-bold">Posted by:</td>
    </tr>
</table>

<table width="100%" class="meta" style="margin-top:8px;">
    <tr>
        <td width="100%">I hereby certify to the correctness of the above information.</td>
    </tr>
</table>

<table width="100%" class="signature-table" style="margin-top:10px;">
    <tr>
        <td width="45%" class="text-center"><div class="signature-space"></div></td>
        <td width="35%" class="text-center"><div class="signature-space"></div></td>
        <td width="20%" class="text-center"><div class="signature-space"></div></td>
    </tr>
    <tr>
        <td class="text-center fw-bold uppercase">{$issuedNameDisplay}</td>
        <td class="text-center fw-bold uppercase">{$receivedNameDisplay}</td>
        <td class="text-center">&nbsp;</td>
    </tr>
    <tr>
        <td class="text-center small">Signature over Printed Name of Supply and/or Property Custodian</td>
        <td class="text-center small">Signature over Printed Name of Designated Accounting Staff</td>
        <td class="text-center small">Date</td>
    </tr>
</table>
HTML;

spms_pdf_render($pdf, $html);

if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output('RSMI-' . preg_replace('/[^A-Za-z0-9\-]/', '', $rsmiNo) . '.pdf', 'I');
exit;
