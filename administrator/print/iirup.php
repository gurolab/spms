<?php

require_once __DIR__ . '/../admin_guard.php';
require_once __DIR__ . '/../../core/helper.php';
require_once __DIR__ . '/../../core/pdf/TcpdfHelper.php';
require_once __DIR__ . '/../../repo/User.model.php';
require_once __DIR__ . '/../../repo/UnserviceableProperty.model.php';

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reportId <= 0) {
    flash('danger', 'Invalid IIRUP reference.');
    redirect('../unserviceable_reports.php');
}

try {
    UnserviceablePropertyReport::ensureSchema();
} catch (Throwable $e) {
    error_log('IIRUP print schema bootstrap failed: ' . $e->getMessage());
    flash('danger', 'Unable to prepare IIRUP data schema.');
    redirect('../unserviceable_reports.php');
}

$db = User::Db();

$headerSql = "
    SELECT
        upr.*,
        preparer.fullname AS prepared_name,
        preparer.rolename AS prepared_role,
        inspector.fullname AS inspected_name,
        inspector.rolename AS inspected_role,
        approver.fullname AS approved_name,
        approver.rolename AS approved_role
    FROM unserviceable_property_reports upr
    LEFT JOIN user_role_dept preparer ON preparer.id = upr.prepared_by
    LEFT JOIN user_role_dept inspector ON inspector.id = upr.inspected_by
    LEFT JOIN user_role_dept approver ON approver.id = upr.approved_by
    WHERE upr.id = ?
    LIMIT 1
";

$headerStmt = $db->prepare($headerSql);
$headerStmt->execute([$reportId]);
$header = $headerStmt->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    flash('danger', 'IIRUP record not found.');
    redirect('../unserviceable_reports.php');
}

$items = UnserviceablePropertyItem::getAllByReport($reportId);

$settings = SystemSettings::getSettings();
$orgName = strtoupper($settings['organization_name'] ?? 'Cotabato State University');
$orgAddress = $settings['address'] ?? '';

$reportNo = $header['report_no'] ?? '';
$reportDate = spms_pdf_format_date($header['report_date'] ?? null);
$entityName = trim((string)($header['entity_name'] ?? ''));
$office = trim((string)($header['office'] ?? ''));
$fundCluster = trim((string)($header['fund_cluster'] ?? ''));
$fundCluster = $fundCluster !== '' ? $fundCluster : 'General Fund';
$remarks = trim((string)($header['remarks'] ?? ''));
$preparedName = strtoupper(trim((string)($header['prepared_name'] ?? '')));
$preparedRole = trim((string)($header['prepared_role'] ?? ''));
$inspectedName = strtoupper(trim((string)($header['inspected_name'] ?? '')));
$approvedName = strtoupper(trim((string)($header['approved_name'] ?? '')));

$orgNameDisplay = spms_pdf_escape($orgName);
$orgAddressDisplay = spms_pdf_escape($orgAddress);
$reportNoDisplay = spms_pdf_escape($reportNo);
$reportDateDisplay = spms_pdf_escape($reportDate);
$entityNameDisplay = spms_pdf_escape($entityName);
$officeDisplay = spms_pdf_escape($office);
$fundClusterDisplay = spms_pdf_escape($fundCluster);
$remarksDisplay = spms_pdf_escape($remarks);
$preparedNameDisplay = spms_pdf_escape($preparedName);
$preparedRoleDisplay = spms_pdf_escape($preparedRole);
$inspectedNameDisplay = spms_pdf_escape($inspectedName);
$approvedNameDisplay = spms_pdf_escape($approvedName);

$pdf = spms_pdf_init('IIRUP - ' . $reportNo, 'L', 'A4');

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
    .iirup-main th, .iirup-main td { line-height: 1.18; }
    .iirup-main th { padding: 3px 4px; }
    .iirup-main td { padding: 4px 4px; vertical-align: top; }
    .iirup-main .qty-col,
    .iirup-main .sale-col,
    .iirup-main .transfer-col,
    .iirup-main .destruct-col { text-align: center; }
    .iirup-main .unitcost-col,
    .iirup-main .totalcost-col,
    .iirup-main .accdep-col,
    .iirup-main .accimp-col,
    .iirup-main .carrying-col,
    .iirup-main .disptotal-col,
    .iirup-main .appraised-col,
    .iirup-main .amount-col { text-align: right; }
    .iirup-main .part-col,
    .iirup-main .prop-col,
    .iirup-main .remarks-col,
    .iirup-main .dispother-col,
    .iirup-main .or-col { word-break: break-all; }
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

$totalQuantity = 0;
$totalCostValue = 0.0;
$totalCarryingValue = 0.0;
$totalAppraisedValue = 0.0;
$totalDisposalValue = 0.0;
$totalSalesValue = 0.0;

$rows = '';
if (empty($items)) {
    $rows = '<tr><td class="text-center" colspan="18">No unserviceable line items for this report.</td></tr>';
} else {
    foreach ($items as $item) {
        $dateAcquired = trim((string)($item['date_acquired'] ?? ''));
        $quantity = (int)($item['quantity'] ?? 0);
        $unitCost = (float)($item['unit_cost'] ?? 0);
        $totalCost = (float)($item['total_cost'] ?? ($quantity * $unitCost));
        $accumulatedDep = (float)($item['accumulated_depreciation'] ?? 0);
        $accumulatedImpairment = (float)($item['accumulated_impairment_loss'] ?? 0);
        $carryingAmount = (float)($item['carrying_amount'] ?? ($totalCost - $accumulatedDep - $accumulatedImpairment));
        $appraisedValue = (float)($item['appraised_value'] ?? 0);
        $disposalTotal = (float)($item['disposal_total'] ?? 0);
        $salesAmount = (float)($item['sales_amount'] ?? 0);

        $disposalSale = (int)($item['disposal_sale'] ?? 0) === 1 ? '/' : '';
        $disposalTransfer = (int)($item['disposal_transfer'] ?? 0) === 1 ? '/' : '';
        $disposalDestruction = (int)($item['disposal_destruction'] ?? 0) === 1 ? '/' : '';
        $disposalOther = trim((string)($item['disposal_other'] ?? ''));
        if ($disposalOther === '' && trim((string)($item['disposal_method'] ?? '')) !== '') {
            $disposalOther = trim((string)$item['disposal_method']);
        }
        if ($disposalTotal <= 0 && ($disposalSale !== '' || $disposalTransfer !== '' || $disposalDestruction !== '' || $disposalOther !== '')) {
            $disposalTotal = $appraisedValue;
        }
        $lineRemarks = trim((string)($item['remarks'] ?? ''));
        if ($lineRemarks === '') {
            $lineRemarks = trim((string)($item['condition_notes'] ?? ''));
        }

        $totalQuantity += $quantity;
        $totalCostValue += $totalCost;
        $totalCarryingValue += $carryingAmount;
        $totalAppraisedValue += $appraisedValue;
        $totalDisposalValue += $disposalTotal;
        $totalSalesValue += $salesAmount;

        $rows .= sprintf(
            '<tr>
                <td width="7%%" class="date-col text-center">%s</td>
                <td width="16%%" class="part-col">%s</td>
                <td width="7%%" class="prop-col">%s</td>
                <td width="4%%" class="qty-col">%d</td>
                <td width="6%%" class="unitcost-col">%s</td>
                <td width="6%%" class="totalcost-col">%s</td>
                <td width="6%%" class="accdep-col">%s</td>
                <td width="6%%" class="accimp-col">%s</td>
                <td width="6%%" class="carrying-col">%s</td>
                <td width="6%%" class="remarks-col">%s</td>
                <td width="3%%" class="sale-col">%s</td>
                <td width="3%%" class="transfer-col">%s</td>
                <td width="3%%" class="destruct-col">%s</td>
                <td width="5%%" class="dispother-col">%s</td>
                <td width="5%%" class="disptotal-col">%s</td>
                <td width="5%%" class="appraised-col">%s</td>
                <td width="3%%" class="or-col">%s</td>
                <td width="3%%" class="amount-col">%s</td>
            </tr>',
            spms_pdf_escape(spms_pdf_format_date($dateAcquired, 'M d, Y', '-')),
            htmlspecialchars(trim((string)($item['item_code'] ?? '') . ' ' . (string)($item['item_description'] ?? '')), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['property_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            $quantity,
            number_format($unitCost, 2),
            number_format($totalCost, 2),
            number_format($accumulatedDep, 2),
            number_format($accumulatedImpairment, 2),
            number_format($carryingAmount, 2),
            htmlspecialchars($lineRemarks, ENT_QUOTES, 'UTF-8'),
            $disposalSale,
            $disposalTransfer,
            $disposalDestruction,
            htmlspecialchars($disposalOther, ENT_QUOTES, 'UTF-8'),
            number_format($disposalTotal, 2),
            number_format($appraisedValue, 2),
            htmlspecialchars((string)($item['or_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            number_format($salesAmount, 2)
        );
    }
}

$totalCostFormatted = number_format($totalCostValue, 2);
$totalCarryingFormatted = number_format($totalCarryingValue, 2);
$totalAppraisedFormatted = number_format($totalAppraisedValue, 2);
$totalDisposalFormatted = number_format($totalDisposalValue, 2);
$totalSalesFormatted = number_format($totalSalesValue, 2);

$html = <<<HTML
{$styles}
<div class="text-center gov-header">
    <div class="gov-line">REPUBLIC OF THE PHILIPPINES</div>
    <div class="org-line">{$orgNameDisplay}</div>
    <div class="org-address">{$orgAddressDisplay}</div>
</div>
<div class="text-right small">Appendix 74</div>

<h2 class="text-center" style="margin: 10px 0 2px 0; font-size: 13pt;">INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE PROPERTY (IIRUP)</h2>
<div class="text-center small">As of {$reportDateDisplay}</div>
<br /><br />

<table width="100%" class="bordered meta-grid" cellspacing="0" cellpadding="0">
    <tr>
        <td width="16%" class="label">Report No.:</td>
        <td width="24%">{$reportNoDisplay}</td>
        <td width="16%" class="label">Report Date:</td>
        <td width="22%">{$reportDateDisplay}</td>
        <td width="10%" class="label">Entity:</td>
        <td width="12%">{$entityNameDisplay}</td>
    </tr>
    <tr>
        <td class="label">Office:</td>
        <td colspan="3">{$officeDisplay}</td>
        <td class="label">Fund Cluster:</td>
        <td>{$fundClusterDisplay}</td>
    </tr>
</table>

<table width="100%" class="bordered meta-grid" cellspacing="0" cellpadding="0" style="margin-top:6px;">
    <tr>
        <td width="33%" class="text-center">{$preparedNameDisplay}</td>
        <td width="33%" class="text-center">{$preparedRoleDisplay}</td>
        <td width="34%" class="text-center">{$officeDisplay}</td>
    </tr>
</table>
<table width="100%" class="meta" style="margin-top:2px;">
    <tr class="small text-center">
        <td>(Name of Accountable Officer)</td>
        <td>(Designation)</td>
        <td>(Station)</td>
    </tr>
</table>

<table width="100%" class="bordered iirup-main" cellspacing="0" cellpadding="0" style="margin-top:6px;">
    <thead>
        <tr class="text-center fw-bold">
            <th width="7%" rowspan="2">Date Acquired</th>
            <th width="16%" rowspan="2">Particulars / Articles</th>
            <th width="7%" rowspan="2">Property No.</th>
            <th width="4%" rowspan="2">Qty</th>
            <th width="6%" rowspan="2">Unit Cost</th>
            <th width="6%" rowspan="2">Total Cost</th>
            <th width="6%" rowspan="2">Accumulated Depreciation</th>
            <th width="6%" rowspan="2">Accumulated Impairment Losses</th>
            <th width="6%" rowspan="2">Carrying Amount</th>
            <th width="6%" rowspan="2">Remarks</th>
            <th width="19%" colspan="5">DISPOSAL</th>
            <th width="5%" rowspan="2">Appraised Value</th>
            <th width="6%" colspan="2">RECORD OF SALES</th>
        </tr>
        <tr class="text-center fw-bold">
            <th width="3%">Sale</th>
            <th width="3%">Transfer</th>
            <th width="3%">Destruction</th>
            <th width="5%">Others (Specify)</th>
            <th width="5%">Total</th>
            <th width="3%">OR No.</th>
            <th width="3%">Amount</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
        <tr class="fw-bold">
            <td colspan="3" class="text-right">Totals</td>
            <td class="text-center">{$totalQuantity}</td>
            <td></td>
            <td class="text-right">{$totalCostFormatted}</td>
            <td></td>
            <td></td>
            <td class="text-right">{$totalCarryingFormatted}</td>
            <td></td>
            <td colspan="4"></td>
            <td class="text-right">{$totalDisposalFormatted}</td>
            <td class="text-right">{$totalAppraisedFormatted}</td>
            <td></td>
            <td class="text-right">{$totalSalesFormatted}</td>
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

<div style="margin-top:8px; font-size:8.5pt;">
    <p style="margin:0 0 6px 0;">I HEREBY request inspection and disposition, pursuant to Section 79 of PD 1445, of the property enumerated above.</p>
    <p style="margin:0 0 6px 0;">I CERTIFY that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgment, the best for the public interest.</p>
    <p style="margin:0 0 2px 0;">I CERTIFY that I have witnessed the disposition of the articles enumerated on this report this ____day of _____________, _____.</p>
</div>

<table width="100%" class="signature-table" style="margin-top:12px;">
    <tr class="text-center fw-bold">
        <td width="25%">Requested by</td>
        <td width="25%">Inspection Officer</td>
        <td width="25%">Approved by</td>
        <td width="25%">Witness</td>
    </tr>
    <tr>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
    </tr>
    <tr>
        <td class="sig-name">{$preparedNameDisplay}</td>
        <td class="sig-name">{$inspectedNameDisplay}</td>
        <td class="sig-name">{$approvedNameDisplay}</td>
        <td class="sig-name">_______________________________</td>
    </tr>
    <tr>
        <td class="sig-caption">Signature over Printed Name of Accountable Officer</td>
        <td class="sig-caption">Signature over Printed Name of Inspection Officer</td>
        <td class="sig-caption">Signature over Printed Name of Authorized Official</td>
        <td class="sig-caption">Signature over Printed Name of Witness</td>
    </tr>
</table>
HTML;

spms_pdf_render($pdf, $html);

writeAuditLog('iirup.print.view', 'unserviceable_reports', $reportId, [
    'module' => 'reports_compliance',
    'report_no' => $reportNo,
]);
spms_pdf_output($pdf, spms_pdf_filename('IIRUP', $reportNo));
