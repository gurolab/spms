<?php

require_once __DIR__ . '/../admin_guard.php';
require_once __DIR__ . '/../../core/helper.php';
require_once __DIR__ . '/../../core/pdf/TcpdfHelper.php';
require_once __DIR__ . '/../../repo/User.model.php';
require_once __DIR__ . '/../../repo/PropertyCard.model.php';

$cardId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($cardId <= 0) {
    flash('danger', 'Invalid Property Card reference.');
    redirect('../property_cards.php');
}

$db = User::Db();
$schemaGuard = new PropertyCardTransaction();

$headerSql = "
    SELECT
        pc.*,
        it.code AS stock_no,
        it.description AS item_description,
        it.unit AS item_unit,
        par.par_no,
        par.fund_cluster,
        acc.fullname AS accountable_name,
        acc.departmentcode AS accountable_department_code,
        acc.departmentname AS accountable_department
    FROM property_cards pc
    JOIN items it ON it.id = pc.item_id
    LEFT JOIN property_acknowledgment_receipts par ON par.id = pc.par_id
    LEFT JOIN user_role_dept acc ON acc.id = pc.accountable_officer
    WHERE pc.id = ?
    LIMIT 1
";

$headerStmt = $db->prepare($headerSql);
$headerStmt->execute([$cardId]);
$header = $headerStmt->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    flash('danger', 'Property Card record not found.');
    redirect('../property_cards.php');
}

$txSql = "
    SELECT
        t.*,
        u.fullname AS performed_name,
        oo.fullname AS office_officer_name,
        oo.departmentcode AS office_officer_department_code,
        oo.departmentname AS office_officer_department
    FROM property_card_transactions t
    LEFT JOIN user_role_dept u ON u.id = t.performed_by
    LEFT JOIN user_role_dept oo ON oo.id = t.office_officer_id
    WHERE t.property_card_id = ?
    ORDER BY t.transaction_date ASC, t.id ASC
";
$txStmt = $db->prepare($txSql);
$txStmt->execute([$cardId]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

$settings = SystemSettings::getSettings();
$orgName = strtoupper($settings['organization_name'] ?? 'Cotabato State University');
$orgAddress = $settings['address'] ?? '';

$fundCluster = trim((string)($header['fund_cluster'] ?? ''));
$cardNo = trim((string)($header['card_no'] ?? ''));
$stockNo = trim((string)($header['stock_no'] ?? ''));
$itemDescription = trim((string)($header['item_description'] ?? ''));
$propertyNumber = trim((string)($header['property_tag'] ?? ''));
$accountableOfficer = formatOfficerNameWithDeptCode([
    'fullname' => (string)($header['accountable_name'] ?? ''),
    'departmentcode' => (string)($header['accountable_department_code'] ?? ''),
    'departmentname' => (string)($header['accountable_department'] ?? ''),
]);
$acquisitionCost = (float)($header['acquisition_cost'] ?? 0);

$orgNameDisplay = spms_pdf_escape($orgName);
$orgAddressDisplay = spms_pdf_escape($orgAddress);
$fundClusterDisplay = spms_pdf_escape($fundCluster);
$cardNoDisplay = spms_pdf_escape($cardNo);
$stockNoDisplay = spms_pdf_escape($stockNo);
$itemDescriptionDisplay = spms_pdf_escape($itemDescription);
$propertyNumberDisplay = spms_pdf_escape($propertyNumber);
$accountableOfficerDisplay = spms_pdf_escape($accountableOfficer);

$pdf = spms_pdf_init('PROPERTY CARD - ' . $cardNo, 'P', 'A4');

$styles = <<<CSS
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #111; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .fw-bold { font-weight: bold; }
    .small { font-size: 8pt; }
    .meta { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .meta td { padding: 3px 5px; line-height: 1.25; vertical-align: middle; }
    .bordered { width: 100%; border: 1px solid #111; border-collapse: collapse; table-layout: fixed; }
    .bordered td, .bordered th { border: 1px solid #111; padding: 4px 5px; line-height: 1.25; }
    .bordered td { vertical-align: middle; }
    .bordered th { vertical-align: middle; }
    .header-box { border: 1px solid #111; padding: 4px 6px; min-height: 20px; line-height: 1.25; word-wrap: break-word; }
    .gov-header { line-height: 0.9; margin: 0; padding: 0; }
    .gov-header div { margin: 0; padding: 0; }
    .gov-line { font-size: 8pt; margin-bottom: -1px; }
    .org-line { font-size: 13pt; font-weight: bold; color: #800000; margin-top: -1px; margin-bottom: -2px; }
    .org-address { font-size: 8pt; margin-top: -1px; }
</style>
CSS;

$rows = '';
$lineCount = 0;
$runningBalance = 0;

if (empty($transactions)) {
    $rows = '<tr><td class="text-center" colspan="8">No recorded property card movements.</td></tr>';
} else {
    foreach ($transactions as $row) {
        $lineCount++;
        $receiptQty = max(0, (int)($row['receipt_qty'] ?? 0));
        $issueQty = max(0, (int)($row['issue_qty'] ?? 0));
        $runningBalance += ($receiptQty - $issueQty);
        if ($runningBalance < 0) {
            $runningBalance = 0;
        }

        $storedBalance = (int)($row['balance_qty'] ?? 0);
        $balanceQty = $storedBalance > 0 ? $storedBalance : $runningBalance;

        $amount = (float)($row['amount'] ?? 0);
        if ($amount <= 0 && $acquisitionCost > 0) {
            $amount = $balanceQty * $acquisitionCost;
        }

        $officeOfficer = trim((string)($row['office_officer'] ?? ''));
        $officeOfficerUser = trim((string)($row['office_officer_name'] ?? ''));
        $officeOfficerDepartmentCode = trim((string)($row['office_officer_department_code'] ?? ''));
        $officeOfficerDepartment = trim((string)($row['office_officer_department'] ?? ''));
        if ($officeOfficerUser !== '') {
            $officeOfficer = formatOfficerNameWithDeptCode([
                'fullname' => $officeOfficerUser,
                'departmentcode' => $officeOfficerDepartmentCode,
                'departmentname' => $officeOfficerDepartment,
            ]);
        } elseif (strpos($officeOfficer, ' / ') !== false) {
            [$legacyOffice, $legacyOfficer] = array_map('trim', explode(' / ', $officeOfficer, 2));
            $legacyOfficeCode = getDepartmentCodeFromUser(['departmentname' => $legacyOffice]);
            if ($legacyOfficer !== '') {
                $officeOfficer = $legacyOfficer;
                if ($legacyOfficeCode !== '') {
                    $officeOfficer .= ' - ' . $legacyOfficeCode;
                }
            }
        }
        if ($officeOfficer === '') {
            $officeOfficer = trim((string)($row['performed_name'] ?? ''));
        }

        $remarks = trim((string)($row['notes'] ?? ''));
        $status = trim((string)($row['status'] ?? ''));
        if ($status !== '') {
            $remarks = $remarks === '' ? $status : ($remarks . ' (' . $status . ')');
        }

        $rows .= sprintf(
            '<tr>
                <td class="text-center">%s</td>
                <td>%s</td>
                <td class="text-center">%d</td>
                <td class="text-center">%d</td>
                <td>%s</td>
                <td class="text-center">%d</td>
                <td class="text-right">%s</td>
                <td>%s</td>
            </tr>',
            spms_pdf_escape(spms_pdf_format_date($row['transaction_date'] ?? null, 'M d, Y')),
            htmlspecialchars((string)($row['reference_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            $receiptQty,
            $issueQty,
            htmlspecialchars($officeOfficer, ENT_QUOTES, 'UTF-8'),
            $balanceQty,
            number_format($amount, 2),
            htmlspecialchars($remarks, ENT_QUOTES, 'UTF-8')
        );
    }
}

$html = <<<HTML
{$styles}
<div class="text-center gov-header">
    <div class="gov-line">REPUBLIC OF THE PHILIPPINES</div>
    <div class="org-line">{$orgNameDisplay}</div>
    <div class="org-address">{$orgAddressDisplay}</div>
</div>

<table width="100%" class="meta" style="margin-top:4px;">
    <tr>
        <td width="80%"></td>
        <td width="20%" class="text-right">Appendix 69</td>
    </tr>
</table>

<h2 class="text-center" style="margin: 2px 0 6px 0; font-size: 13pt;">PROPERTY CARD</h2>

<table width="100%" class="meta">
    <tr>
        <td width="17%" class="fw-bold">Entity Name:</td>
        <td width="33%" class="header-box">{$orgNameDisplay}</td>
        <td width="17%" class="fw-bold">Fund Cluster:</td>
        <td width="33%" class="header-box">{$fundClusterDisplay}</td>
    </tr>
    <tr>
        <td class="fw-bold">Property, Plant and Equipment:</td>
        <td class="header-box">{$itemDescriptionDisplay}</td>
        <td class="fw-bold">Property Number:</td>
        <td class="header-box">{$propertyNumberDisplay}</td>
    </tr>
    <tr>
        <td class="fw-bold">Description:</td>
        <td class="header-box">{$itemDescriptionDisplay}</td>
        <td class="fw-bold">Stock No.:</td>
        <td class="header-box">{$stockNoDisplay}</td>
    </tr>
    <tr>
        <td class="fw-bold">Card No.:</td>
        <td class="header-box">{$cardNoDisplay}</td>
        <td class="fw-bold">Office/Officer:</td>
        <td class="header-box">{$accountableOfficerDisplay}</td>
    </tr>
</table>

<table width="100%" class="bordered" cellspacing="0" cellpadding="0" style="margin-top:6px;">
    <thead>
        <tr class="text-center fw-bold">
            <th width="12%">Date</th>
            <th width="16%">Reference / PAR No.</th>
            <th width="10%">Receipt Qty</th>
            <th width="15%">Issue/Transfer/Disposal Qty</th>
            <th width="17%">Office / Officer</th>
            <th width="10%">Balance Qty</th>
            <th width="10%">Amount</th>
            <th width="10%">Remarks</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
HTML;

spms_pdf_render($pdf, $html);

writeAuditLog('property_card.print.view', 'property_cards', $cardId, [
    'module' => 'property_cards',
    'card_no' => $cardNo,
    'movement_lines' => $lineCount,
]);
spms_pdf_output($pdf, spms_pdf_filename('PROPERTY-CARD', $cardNo));
