<?php

require_once __DIR__ . '/../admin_guard.php';
require_once __DIR__ . '/../../core/helper.php';
require_once __DIR__ . '/../../core/pdf/TcpdfHelper.php';
require_once __DIR__ . '/../../repo/User.model.php';
require_once __DIR__ . '/../../repo/PhysicalInventory.model.php';

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reportId <= 0) {
    flash('danger', 'Invalid RPCI reference.');
    redirect('../physical_inventory_reports.php');
}

$db = User::Db();

$headerSql = "
    SELECT
        pir.*,
        preparer.fullname AS prepared_name,
        preparer.rolename AS prepared_role,
        verifier.fullname AS verified_name,
        verifier.rolename AS verified_role
    FROM physical_inventory_reports pir
    LEFT JOIN user_role_dept preparer ON preparer.id = pir.prepared_by
    LEFT JOIN user_role_dept verifier ON verifier.id = pir.verified_by
    WHERE pir.id = ?
    LIMIT 1
";

$headerStmt = $db->prepare($headerSql);
$headerStmt->execute([$reportId]);
$header = $headerStmt->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    flash('danger', 'RPCI record not found.');
    redirect('../physical_inventory_reports.php');
}

$items = PhysicalInventoryItem::getAllByReport($reportId);

$settings = SystemSettings::getSettings();
$orgName = strtoupper($settings['organization_name'] ?? 'Cotabato State University');
$orgAddress = $settings['address'] ?? '';

$reportNo = $header['report_no'] ?? '';
$reportDate = spms_pdf_format_date($header['report_date'] ?? null);
$location = trim((string)($header['location'] ?? ''));
$fundCluster = trim((string)($header['fund_cluster'] ?? ''));
$fundCluster = $fundCluster !== '' ? $fundCluster : 'General Fund';
$remarks = trim((string)($header['remarks'] ?? ''));
$preparedName = strtoupper(trim((string)($header['prepared_name'] ?? '')));
$verifiedName = strtoupper(trim((string)($header['verified_name'] ?? '')));
$inventoryType = strtoupper(trim((string)($header['inventory_type'] ?? '')));
$inventoryType = $inventoryType !== '' ? $inventoryType : 'LOW/HIGH VALUE SEMI-EXPENDABLE PPE';
$classificationCode = trim((string)($header['classification_code'] ?? ''));
$classificationCode = $classificationCode !== '' ? $classificationCode : 'CLASSIFICATION AND CODE';

$orgNameDisplay = spms_pdf_escape($orgName);
$orgAddressDisplay = spms_pdf_escape($orgAddress);
$reportNoDisplay = spms_pdf_escape($reportNo);
$reportDateDisplay = spms_pdf_escape($reportDate);
$locationDisplay = spms_pdf_escape($location);
$fundClusterDisplay = spms_pdf_escape($fundCluster);
$remarksDisplay = spms_pdf_escape($remarks);
$preparedNameDisplay = spms_pdf_escape($preparedName);
$verifiedNameDisplay = spms_pdf_escape($verifiedName);
$inventoryTypeDisplay = spms_pdf_escape($inventoryType);
$classificationCodeDisplay = spms_pdf_escape($classificationCode);

$pdf = spms_pdf_init('RPCI - ' . $reportNo, 'L', 'A4');

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
    .rpci-main th, .rpci-main td { line-height: 1.2; }
    .rpci-main th { padding: 3px 4px; }
    .rpci-main td { padding: 4px 4px; vertical-align: top; }
    .rpci-main .unit-col,
    .rpci-main .bal-col,
    .rpci-main .hand-col,
    .rpci-main .varqty-col { text-align: center; }
    .rpci-main .value-col,
    .rpci-main .varval-col { text-align: right; }
    .rpci-main .remarks-col { word-break: break-all; }
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

$totalVarianceQty = 0;
$totalVarianceValue = 0;

$rows = '';
if (empty($items)) {
    $rows = '<tr><td class="text-center" colspan="10">No inventory line items for this report.</td></tr>';
} else {
    foreach ($items as $item) {
        $systemQty = (int)($item['system_qty'] ?? 0);
        $countedQty = (int)($item['counted_qty'] ?? 0);
        $varianceQty = (int)($item['variance_qty'] ?? 0);
        $varianceValue = (float)($item['variance_value'] ?? 0);

        $totalVarianceQty += $varianceQty;
        $totalVarianceValue += $varianceValue;

        $rows .= sprintf(
            '<tr>
                <td width="10%%" class="article-col">%s</td>
                <td width="18%%" class="desc-col">%s</td>
                <td width="12%%" class="stock-col">%s</td>
                <td width="8%%" class="unit-col">%s</td>
                <td width="8%%" class="value-col">%s</td>
                <td width="10%%" class="bal-col">%d</td>
                <td width="10%%" class="hand-col">%d</td>
                <td width="8%%" class="varqty-col">%d</td>
                <td width="8%%" class="varval-col">%s</td>
                <td width="8%%" class="remarks-col">%s</td>
            </tr>',
            htmlspecialchars((string)($item['item_code'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['item_description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['item_code'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)($item['item_unit'] ?? ''), ENT_QUOTES, 'UTF-8'),
            number_format((float)($item['unit_cost'] ?? 0), 2),
            $systemQty,
            $countedQty,
            $varianceQty,
            number_format($varianceValue, 2),
            htmlspecialchars((string)($item['remarks'] ?? ''), ENT_QUOTES, 'UTF-8')
        );
    }
}

$totalVarianceQtyFormatted = number_format($totalVarianceQty);
$totalVarianceFormatted = number_format($totalVarianceValue, 2);

$html = <<<HTML
{$styles}
<div class="text-center gov-header">
    <div class="gov-line">REPUBLIC OF THE PHILIPPINES</div>
    <div class="org-line">{$orgNameDisplay}</div>
    <div class="org-address">{$orgAddressDisplay}</div>
</div>
<div class="text-right small">Appendix 66</div>

<h2 class="text-center" style="margin: 10px 0 2px 0; font-size: 13pt;">REPORT ON THE PHYSICAL COUNT OF INVENTORIES ({$inventoryTypeDisplay})</h2>
<div class="text-center small">({$classificationCodeDisplay})</div>
<div class="text-center small">(Type of Inventory Item)</div>
<div class="text-center small">As of {$reportDateDisplay}</div>
<br /><br />

<table width="100%" class="bordered meta-grid" cellspacing="0" cellpadding="0">
    <tr>
        <td width="16%" class="label">Report No.:</td>
        <td width="24%">{$reportNoDisplay}</td>
        <td width="16%" class="label">Fund Cluster:</td>
        <td width="44%">{$fundClusterDisplay}</td>
    </tr>
    <tr>
        <td class="label">Location:</td>
        <td colspan="3">{$locationDisplay}</td>
    </tr>
    <tr>
        <td class="label">Prepared by:</td>
        <td colspan="3">{$preparedNameDisplay}</td>
    </tr>
</table>

<table width="100%" class="bordered rpci-main" cellspacing="0" cellpadding="0" style="margin-top:6px;">
    <thead>
        <tr class="text-center fw-bold">
            <th width="10%" class="article-col">Article</th>
            <th width="18%" class="desc-col">Description</th>
            <th width="12%" class="stock-col">Stock Number</th>
            <th width="8%" class="unit-col">Unit of Measure</th>
            <th width="8%" class="value-col">Unit Value</th>
            <th width="10%" class="bal-col">Balance Per Card (Qty)</th>
            <th width="10%" class="hand-col">On Hand Per Count (Qty)</th>
            <th width="8%" class="varqty-col">Shortage/Overage (Qty)</th>
            <th width="8%" class="varval-col">Shortage/Overage (Value)</th>
            <th width="8%" class="remarks-col">Remarks (Accountable Officer)</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
        <tr class="fw-bold">
            <td colspan="7" class="text-right">Totals</td>
            <td class="text-center">{$totalVarianceQtyFormatted}</td>
            <td class="text-right">{$totalVarianceFormatted}</td>
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
        <td width="33.33%">Certified Correct by:</td>
        <td width="33.33%">Approved by:</td>
        <td width="33.33%">Verified by:</td>
    </tr>
    <tr>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
        <td class="text-center"><div class="signature-space"></div></td>
    </tr>
    <tr>
        <td class="sig-name">{$preparedNameDisplay}</td>
        <td class="sig-name">_______________________________</td>
        <td class="sig-name">{$verifiedNameDisplay}</td>
    </tr>
    <tr>
        <td class="sig-caption">Inventory Committee Chairman</td>
        <td class="sig-caption">Signature over Printed Name of Head of Agency/Entity or Authorized Representative</td>
        <td class="sig-caption">Signature over Printed Name of COA Representative</td>
    </tr>
</table>
HTML;

spms_pdf_render($pdf, $html);

writeAuditLog('rpci.print.view', 'physical_inventory_reports', $reportId, [
    'module' => 'reports_compliance',
    'report_no' => $reportNo,
]);
spms_pdf_output($pdf, spms_pdf_filename('RPCI', $reportNo));
