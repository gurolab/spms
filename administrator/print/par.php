<?php

require_once __DIR__ . '/../admin_guard.php';
require_once __DIR__ . '/../../core/helper.php';
require_once __DIR__ . '/../../core/pdf/TcpdfHelper.php';
require_once __DIR__ . '/../../repo/User.model.php';

$parId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($parId <= 0) {
    flash('danger', 'Invalid PAR reference.');
    redirect('../par.php');
}

$db = User::Db();

$headerSql = "
    SELECT
        par.*,
        accountable.fullname AS accountable_name,
        accountable.departmentname AS accountable_department,
        accountable.rolename AS accountable_role,
        issuer.fullname AS issued_name,
        issuer.rolename AS issued_role
    FROM property_acknowledgment_receipts par
    LEFT JOIN user_role_dept accountable ON accountable.id = par.accountable_officer
    LEFT JOIN user_role_dept issuer ON issuer.id = par.issued_by
    WHERE par.id = ?
    LIMIT 1
";

$headerStmt = $db->prepare($headerSql);
$headerStmt->execute([$parId]);
$header = $headerStmt->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    flash('danger', 'PAR record not found.');
    redirect('../par.php');
}

$itemsSql = "
    SELECT
        pi.*,
        it.code AS stock_no,
        it.description AS item_description,
        it.unit AS item_unit
    FROM par_items pi
    JOIN items it ON it.id = pi.item_id
    WHERE pi.par_id = ?
    ORDER BY it.code ASC, it.description ASC
";

$itemsStmt = $db->prepare($itemsSql);
$itemsStmt->execute([$parId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$settings = SystemSettings::getSettings();
$orgName = strtoupper($settings['organization_name'] ?? 'Cotabato State University');
$orgAddress = $settings['address'] ?? '';

$parNo = $header['par_no'] ?? '';
$issueDate = spms_pdf_format_date($header['issue_date'] ?? null);
$fundCluster = $header['fund_cluster'] ?? '';
$accountableName = strtoupper(trim((string)($header['accountable_name'] ?? '')));
$accountableDepartment = trim((string)($header['accountable_department'] ?? ''));
$issuedName = strtoupper(trim((string)($header['issued_name'] ?? '')));
$issuedRole = trim((string)($header['issued_role'] ?? ''));

$orgNameDisplay = spms_pdf_escape($orgName);
$orgAddressDisplay = spms_pdf_escape($orgAddress);
$parNoDisplay = spms_pdf_escape($parNo);
$issueDateDisplay = spms_pdf_escape($issueDate);
$fundClusterDisplay = spms_pdf_escape($fundCluster);
$accountableNameDisplay = spms_pdf_escape($accountableName);
$accountableDepartmentDisplay = spms_pdf_escape($accountableDepartment);
$issuedNameDisplay = spms_pdf_escape($issuedName);
$issuedRoleDisplay = spms_pdf_escape($issuedRole);

$pdf = spms_pdf_init('PAR - ' . $parNo, 'P', 'A4');

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
    .par-main th, .par-main td { line-height: 1.2; }
    .par-main th { padding: 3px 4px; }
    .par-main td { padding: 4px 4px; vertical-align: top; }
    .par-main .qty-col,
    .par-main .unit-col,
    .par-main .date-col { text-align: center; }
    .par-main .amount-col { text-align: right; }
    .par-main .prop-col { word-break: break-all; }
    .signature-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .signature-table td { padding: 10px 8px 6px 8px; line-height: 1.25; vertical-align: top; }
    .small { font-size: 8pt; }
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
    $rows = '<tr><td class="text-center" colspan="6">No items recorded for this PAR.</td></tr>';
} else {
    foreach ($items as $item) {
        $qty = (int)($item['qty'] ?? 0);
        $unitValue = (float)($item['unit_value'] ?? 0);
        $amount = $qty * $unitValue;
        $totalValue += $amount;
        $rows .= sprintf(
            '<tr>
                <td width="12%%" class="qty-col">%d</td>
                <td width="12%%" class="unit-col">%s</td>
                <td width="32%%" class="desc-col">%s</td>
                <td width="16%%" class="prop-col">%s</td>
                <td width="14%%" class="date-col">%s</td>
                <td width="14%%" class="amount-col">%s</td>
            </tr>',
            $qty,
            htmlspecialchars((string)($item['item_unit'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['item_description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['property_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            spms_pdf_escape(spms_pdf_format_date($item['acquisition_date'] ?? null, 'M d, Y')),
            number_format($amount, 2)
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
<div class="text-right small">Appendix 71</div>

<h2 class="text-center" style="margin: 10px 0 2px 0; font-size: 13pt;">PROPERTY ACKNOWLEDGMENT RECEIPT (PAR)</h2>
<br /><br />

<table width="100%" class="bordered meta-grid" cellspacing="0" cellpadding="0">
    <tr>
        <td width="18%" class="label">Entity Name:</td>
        <td width="32%">{$orgNameDisplay}</td>
        <td width="18%" class="label">PAR No.:</td>
        <td width="32%">{$parNoDisplay}</td>
    </tr>
    <tr>
        <td class="label">Fund Cluster:</td>
        <td>{$fundClusterDisplay}</td>
        <td class="label">Date:</td>
        <td>{$issueDateDisplay}</td>
    </tr>
</table>

<table width="100%" class="bordered par-main" cellspacing="0" cellpadding="0" style="margin-top:6px;">
    <thead>
        <tr class="text-center fw-bold">
            <th width="12%" class="qty-col">Quantity</th>
            <th width="12%" class="unit-col">Unit</th>
            <th width="32%" class="desc-col">Description</th>
            <th width="16%" class="prop-col">Property Number</th>
            <th width="14%" class="date-col">Date Acquired</th>
            <th width="14%" class="amount-col">Amount</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
        <tr>
            <td colspan="6" class="text-center">x-x-x-x Nothing Follows-x-x-x-x</td>
        </tr>
        <tr class="fw-bold">
            <td colspan="5" class="text-right">TOTAL</td>
            <td class="text-right">{$totalValueFormatted}</td>
        </tr>
    </tbody>
</table>
<br /><br />

<table width="100%" class="signature-table" style="margin-top:0;">
    <tr class="text-center fw-bold">
        <td width="50%">Received by:</td>
        <td width="50%">Issued by:</td>
    </tr>
    <tr>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
    </tr>
    <tr>
        <td class="sig-name">{$accountableNameDisplay}</td>
        <td class="sig-name">{$issuedNameDisplay}</td>
    </tr>
    <tr>
        <td class="sig-caption">Signature over Printed Name of End User</td>
        <td class="sig-caption">Signature over Printed Name of Supply and/or Property Custodian</td>
    </tr>
    <tr>
        <td class="sig-caption">Position/Office: {$accountableDepartmentDisplay}</td>
        <td class="sig-caption">Position/Office: {$issuedRoleDisplay}</td>
    </tr>
    <tr>
        <td class="sig-caption">&nbsp;</td>
        <td class="sig-caption">Date: {$issueDateDisplay}</td>
    </tr>
</table>
HTML;

spms_pdf_render($pdf, $html);
spms_pdf_output($pdf, spms_pdf_filename('PAR', $parNo));
