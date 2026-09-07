<?php

require_once __DIR__ . '/../admin_guard.php';
require_once __DIR__ . '/../../core/helper.php';
require_once __DIR__ . '/../../core/pdf/TcpdfHelper.php';
require_once __DIR__ . '/../../repo/User.model.php';
require_once __DIR__ . '/../../repo/PhysicalPpe.model.php';

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reportId <= 0) {
    flash('danger', 'Invalid RPCPPE reference.');
    redirect('../physical_ppe_reports.php');
}

$db = User::Db();

$headerSql = "
    SELECT
        ppr.*,
        preparer.fullname AS prepared_name,
        preparer.rolename AS prepared_role,
        verifier.fullname AS verified_name,
        verifier.rolename AS verified_role
    FROM physical_ppe_reports ppr
    LEFT JOIN user_role_dept preparer ON preparer.id = ppr.prepared_by
    LEFT JOIN user_role_dept verifier ON verifier.id = ppr.verified_by
    WHERE ppr.id = ?
    LIMIT 1
";

$headerStmt = $db->prepare($headerSql);
$headerStmt->execute([$reportId]);
$header = $headerStmt->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    flash('danger', 'RPCPPE record not found.');
    redirect('../physical_ppe_reports.php');
}

$items = PhysicalPpeItem::getAllByReport($reportId);

$settings = SystemSettings::getSettings();
$orgName = strtoupper($settings['organization_name'] ?? 'Cotabato State University');
$orgAddress = $settings['address'] ?? '';

$reportNo = $header['report_no'] ?? '';
$reportDate = spms_pdf_format_date($header['report_date'] ?? null);
$fundCluster = trim((string)($header['fund_cluster'] ?? ''));
$station = trim((string)($header['station'] ?? ''));
$remarks = trim((string)($header['remarks'] ?? ''));
$preparedName = strtoupper(trim((string)($header['prepared_name'] ?? '')));
$verifiedName = strtoupper(trim((string)($header['verified_name'] ?? '')));

$orgNameDisplay = spms_pdf_escape($orgName);
$orgAddressDisplay = spms_pdf_escape($orgAddress);
$reportNoDisplay = spms_pdf_escape($reportNo);
$reportDateDisplay = spms_pdf_escape($reportDate);
$fundClusterDisplay = spms_pdf_escape($fundCluster);
$stationDisplay = spms_pdf_escape($station);
$remarksDisplay = spms_pdf_escape($remarks);
$preparedNameDisplay = spms_pdf_escape($preparedName);
$verifiedNameDisplay = spms_pdf_escape($verifiedName);

$pdf = spms_pdf_init('RPCPPE - ' . $reportNo, 'L', 'A4');

$styles = <<<CSS
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #111; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .fw-bold { font-weight: bold; }
    .bordered { width: 100%; border: 1px solid #111; border-collapse: collapse; table-layout: fixed; }
    .bordered td, .bordered th { border: 1px solid #111; padding: 4px 6px; line-height: 1.25; }
    .bordered td { vertical-align: middle; }
    .bordered th { vertical-align: middle; }
    .meta { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .meta td { padding: 4px 6px; line-height: 1.25; vertical-align: middle; }
    .meta-grid td { padding: 4px 6px; line-height: 1.25; vertical-align: middle; }
    .meta-grid .label { font-weight: bold; }
    .rpcppe-main th, .rpcppe-main td { line-height: 1.2; }
    .rpcppe-main th { padding: 3px 4px; }
    .rpcppe-main td { padding: 4px 4px; vertical-align: top; }
    .rpcppe-main .no-col,
    .rpcppe-main .unit-col,
    .rpcppe-main .qtycard-col,
    .rpcppe-main .qtycount-col,
    .rpcppe-main .varqty-col { text-align: center; }
    .rpcppe-main .unitval-col,
    .rpcppe-main .varval-col { text-align: right; }
    .rpcppe-main .prop-col,
    .rpcppe-main .acc-col { word-break: break-all; }
    .remarks-grid td { vertical-align: top; }
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

$totalRecordQty = 0;
$totalPhysicalQty = 0;
$totalVarianceQty = 0;
$totalVarianceValue = 0.0;

$rows = '';
if (empty($items)) {
    $rows = '<tr><td class="text-center" colspan="11">No PPE line items for this report.</td></tr>';
} else {
    $line = 1;
    foreach ($items as $item) {
        $recordQty = (int)($item['property_card_qty'] ?? 0);
        $physicalQty = (int)($item['physical_qty'] ?? 0);
        $varianceQty = (int)($item['variance_qty'] ?? ($physicalQty - $recordQty));
        $unitValue = (float)($item['cost'] ?? 0);
        $varianceValue = $varianceQty * $unitValue;
        $accountablePerson = trim((string)($item['remarks'] ?? ''));

        $totalRecordQty += $recordQty;
        $totalPhysicalQty += $physicalQty;
        $totalVarianceQty += $varianceQty;
        $totalVarianceValue += $varianceValue;

        $rows .= sprintf(
            '<tr>
                <td width="4%%" class="no-col">%d</td>
                <td width="9%%" class="article-col">%s</td>
                <td width="19%%" class="desc-col">%s</td>
                <td width="8%%" class="unit-col">%s</td>
                <td width="11%%" class="prop-col">%s</td>
                <td width="8%%" class="unitval-col">%s</td>
                <td width="8%%" class="qtycard-col">%d</td>
                <td width="8%%" class="qtycount-col">%d</td>
                <td width="8%%" class="varqty-col">%d</td>
                <td width="8%%" class="varval-col">%s</td>
                <td width="9%%" class="acc-col">%s</td>
            </tr>',
            $line++,
            htmlspecialchars((string)($item['item_code'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['item_description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['item_unit'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['property_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            number_format($unitValue, 2),
            $recordQty,
            $physicalQty,
            $varianceQty,
            number_format($varianceValue, 2),
            htmlspecialchars($accountablePerson, ENT_QUOTES, 'UTF-8')
        );
    }
}

$totalVarianceValueFormatted = number_format($totalVarianceValue, 2);

$html = <<<HTML
{$styles}
<div class="text-center gov-header">
    <div class="gov-line">REPUBLIC OF THE PHILIPPINES</div>
    <div class="org-line">{$orgNameDisplay}</div>
    <div class="org-address">{$orgAddressDisplay}</div>
</div>
<div class="text-right small">Appendix 73</div>

<h2 class="text-center" style="margin: 10px 0 2px 0; font-size: 13pt;">REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT (RPCPPE)</h2>
<div class="text-center small">(CLASSIFICATION AND CODE)</div>
<div class="text-center small">(Type of Property, Plant and Equipment)</div>
<div class="text-center small">As of {$reportDateDisplay}</div>
<br /><br />

<table width="100%" class="bordered meta-grid" cellspacing="0" cellpadding="0">
    <tr>
        <td width="16%" class="label">Report No.:</td>
        <td width="24%">{$reportNoDisplay}</td>
        <td width="16%" class="label">Report Date:</td>
        <td width="20%">{$reportDateDisplay}</td>
        <td width="12%" class="label">Fund Cluster:</td>
        <td width="12%">{$fundClusterDisplay}</td>
    </tr>
    <tr>
        <td class="label">Station:</td>
        <td colspan="3">{$stationDisplay}</td>
        <td class="label">Prepared by:</td>
        <td>{$preparedNameDisplay}</td>
    </tr>
</table>

<table width="100%" class="bordered rpcppe-main" cellspacing="0" cellpadding="0" style="margin-top:6px;">
    <thead>
        <tr class="text-center fw-bold">
            <th width="4%" class="no-col">No.</th>
            <th width="9%" class="article-col">Article</th>
            <th width="19%" class="desc-col">Description</th>
            <th width="8%" class="unit-col">Unit of Measure</th>
            <th width="11%" class="prop-col">Property Number</th>
            <th width="8%" class="unitval-col">Unit Value</th>
            <th width="8%" class="qtycard-col">Quantity per Property Card</th>
            <th width="8%" class="qtycount-col">Quantity per Physical Count</th>
            <th width="8%" class="varqty-col">Shortage/Overage (Qty)</th>
            <th width="8%" class="varval-col">Shortage/Overage (Value)</th>
            <th width="9%" class="acc-col">Accountable Person</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
        <tr class="fw-bold">
            <td colspan="5" class="text-right">Totals</td>
            <td></td>
            <td class="text-center">{$totalRecordQty}</td>
            <td class="text-center">{$totalPhysicalQty}</td>
            <td class="text-center">{$totalVarianceQty}</td>
            <td class="text-right">{$totalVarianceValueFormatted}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table width="100%" class="bordered remarks-grid" cellspacing="0" cellpadding="0" style="margin-top:14px;">
    <tr>
        <td width="18%" class="fw-bold">Report Remarks:</td>
        <td width="82%">{$remarksDisplay}</td>
    </tr>
</table>
<br /><br />

<table width="100%" class="signature-table" style="margin-top:0;">
    <tr class="text-center fw-bold">
        <td width="50%">Prepared by</td>
        <td width="50%">Verified and Found Correct by</td>
    </tr>
    <tr>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
    </tr>
    <tr>
        <td class="sig-name">{$preparedNameDisplay}</td>
        <td class="sig-name">{$verifiedNameDisplay}</td>
    </tr>
    <tr>
        <td class="sig-caption">Signature over Printed Name / Position</td>
        <td class="sig-caption">Signature over Printed Name / Position</td>
    </tr>
</table>
HTML;

spms_pdf_render($pdf, $html);

writeAuditLog('rpcppe.print.view', 'physical_ppe_reports', $reportId, [
    'module' => 'reports_compliance',
    'report_no' => $reportNo,
]);
spms_pdf_output($pdf, spms_pdf_filename('RPCPPE', $reportNo));
