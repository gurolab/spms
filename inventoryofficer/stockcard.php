<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/StockCard.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/StockTransaction.model.php';
require_once __DIR__ . '/../repo/SystemSettings.model.php';

function stockCardBuildInClause(array $ids, string $prefix): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0)));
    if (empty($ids)) {
        return ['', []];
    }

    $params = [];
    $placeholders = [];
    foreach ($ids as $index => $value) {
        $key = ':' . $prefix . $index;
        $placeholders[] = $key;
        $params[$key] = $value;
    }

    return [implode(', ', $placeholders), $params];
}

function stockCardReferenceOfficeMap(array $transactions): array
{
    $db = BaseModel::Db();
    $receiptIds = [];
    $issuanceIds = [];
    $parIds = [];

    foreach ($transactions as $tx) {
        $referenceId = (int)($tx['reference_id'] ?? 0);
        if ($referenceId <= 0) {
            continue;
        }

        $type = strtolower(trim((string)($tx['reference_type'] ?? '')));
        if ($type === '') {
            continue;
        }

        if (str_contains($type, 'supply receipt')) {
            $receiptIds[] = $referenceId;
            continue;
        }

        if (str_contains($type, 'supply issuance')) {
            $issuanceIds[] = $referenceId;
            continue;
        }

        if (str_contains($type, 'par issuance')) {
            $parIds[] = $referenceId;
        }
    }

    $officeByReference = [];

    [$receiptIn, $receiptParams] = stockCardBuildInClause($receiptIds, 'sr');
    if ($receiptIn !== '') {
        $sql = "
            SELECT
                sr.id,
                COALESCE(NULLIF(TRIM(s.name), ''), NULLIF(TRIM(sr.receipt_no), ''), '') AS label
            FROM supply_receipts sr
            LEFT JOIN suppliers s ON s.id = sr.supplier_id
            WHERE sr.id IN ($receiptIn)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($receiptParams);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $officeByReference['receipt-' . (int)$row['id']] = (string)$row['label'];
        }
    }

    [$issuanceIn, $issuanceParams] = stockCardBuildInClause($issuanceIds, 'si');
    if ($issuanceIn !== '') {
        $sql = "
            SELECT
                si.id,
                COALESCE(
                    NULLIF(TRIM(dept.code), ''),
                    NULLIF(TRIM(dept.name), ''),
                    NULLIF(TRIM(req.departmentname), ''),
                    NULLIF(TRIM(req.fullname), ''),
                    ''
                ) AS label
            FROM supply_issuances si
            LEFT JOIN requisition_slips rs ON rs.id = si.ris_id
            LEFT JOIN departments dept ON dept.id = rs.division
            LEFT JOIN user_role_dept req ON req.id = rs.requested_by
            WHERE si.id IN ($issuanceIn)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($issuanceParams);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $officeByReference['issuance-' . (int)$row['id']] = (string)$row['label'];
        }
    }

    [$parIn, $parParams] = stockCardBuildInClause($parIds, 'par');
    if ($parIn !== '') {
        $sql = "
            SELECT
                par.id,
                COALESCE(
                    NULLIF(TRIM(acc.departmentname), ''),
                    NULLIF(TRIM(acc.fullname), ''),
                    ''
                ) AS label
            FROM property_acknowledgment_receipts par
            LEFT JOIN user_role_dept acc ON acc.id = par.accountable_officer
            WHERE par.id IN ($parIn)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($parParams);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $officeByReference['par-' . (int)$row['id']] = (string)$row['label'];
        }
    }

    return $officeByReference;
}

function stockCardResolveOffice(array $tx, array $officeMap): string
{
    $referenceId = (int)($tx['reference_id'] ?? 0);
    if ($referenceId <= 0) {
        return '-';
    }

    $type = strtolower(trim((string)($tx['reference_type'] ?? '')));
    if (str_contains($type, 'supply receipt')) {
        return $officeMap['receipt-' . $referenceId] ?? '-';
    }

    if (str_contains($type, 'supply issuance')) {
        return $officeMap['issuance-' . $referenceId] ?? '-';
    }

    if (str_contains($type, 'par issuance')) {
        return $officeMap['par-' . $referenceId] ?? '-';
    }

    return '-';
}

$pageTitle = 'Stock Card';
$itemRepo = new Item();
$items = $itemRepo->getAll();

$settings = SystemSettings::getSettings();
$organizationName = strtoupper(trim((string)($settings['organization_name'] ?? 'Cotabato State University')));
$organizationAddress = trim((string)($settings['address'] ?? ''));
$fundClusterLabel = 'General Fund';

$selectedItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$selectedItem = null;
$stockCard = null;
$inventoryState = null;
$transactions = [];
$renderRows = [];

if ($selectedItemId > 0) {
    $selectedItem = $itemRepo->getById($selectedItemId);
    if ($selectedItem) {
        $stockCardId = StockCard::ensureForItem($selectedItemId);
        $stockCard = (new BaseModel('stock_cards'))->getById($stockCardId);
        $inventoryState = StockInventory::getByItemId($selectedItemId);
        $transactions = StockTransaction::getAllByItem($selectedItemId);

        $officeMap = stockCardReferenceOfficeMap($transactions);
        $previousIssueDate = null;

        foreach ($transactions as $tx) {
            $transactionDateRaw = trim((string)($tx['transaction_date'] ?? ''));
            $transactionTimestamp = strtotime($transactionDateRaw ?: '');
            $qtyOut = (int)($tx['qty_out'] ?? 0);
            $daysToConsume = '-';

            if ($qtyOut > 0 && $transactionTimestamp !== false) {
                if ($previousIssueDate !== null) {
                    $previousTs = strtotime($previousIssueDate);
                    if ($previousTs !== false) {
                        $daysToConsume = (string)max(0, (int)floor(($transactionTimestamp - $previousTs) / 86400));
                    }
                } else {
                    $daysToConsume = '0';
                }
                $previousIssueDate = date('Y-m-d', $transactionTimestamp);
            }

            $referenceNo = trim((string)($tx['reference_no'] ?? ''));
            $referenceType = trim((string)($tx['reference_type'] ?? ''));

            $renderRows[] = [
                'date' => $transactionDateRaw,
                'reference' => $referenceNo !== '' ? $referenceNo : ($referenceType !== '' ? $referenceType : '-'),
                'receipt_qty' => (int)($tx['qty_in'] ?? 0),
                'issue_qty' => $qtyOut,
                'office' => stockCardResolveOffice($tx, $officeMap),
                'balance_qty' => (int)($tx['balance_qty'] ?? 0),
                'days_to_consume' => $daysToConsume,
            ];
        }
    }
}

?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<style>
.stock-card-table,
.stock-card-table th,
.stock-card-table td {
    border: 1px solid #111;
}

.stock-card-table {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
}

.stock-card-table th,
.stock-card-table td {
    padding: 6px 8px;
    line-height: 1.25;
    vertical-align: middle;
}

.print-gov-header {
    line-height: 0.9;
    margin: 0;
    padding: 0;
}

.print-gov-header div {
    margin: 0;
    padding: 0;
}

.print-gov-header .gov-line {
    font-size: 8pt;
    margin-bottom: -1px;
}

.print-gov-header .org-line {
    font-size: 13pt;
    font-weight: 700;
    color: #800000;
    margin-top: -1px;
    margin-bottom: -2px;
}

.print-gov-header .org-address {
    font-size: 8pt;
    margin-top: -1px;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 5mm;
    }

    body {
        background: #fff;
    }

    html,
    body {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .sidebar,
    .dashboard-footer,
    .breadcrumb-with-buttons,
    .print-hide,
    .select2-container,
    .btn,
    .dataTables_length,
    .dataTables_filter,
    .dataTables_info,
    .dataTables_paginate,
    .dt-length,
    .dt-search,
    .dt-info,
    .dt-paging {
        display: none !important;
    }

    #stockCardTable_filter,
    #stockCardTable_filter *,
    #stockCardTable_wrapper .dataTables_filter,
    #stockCardTable_wrapper .dataTables_filter *,
    #stockCardTable_wrapper .dt-search,
    #stockCardTable_wrapper .dt-search *,
    #stockCardTable_wrapper .dt-layout-row:not(.dt-layout-table) {
        display: none !important;
    }

    .dashboard-main-wrapper,
    .dashboard-body {
        margin: 0;
        padding: 0;
    }

    .dashboard-main-wrapper {
        margin-inline-start: 0 !important;
        width: 100% !important;
        max-width: none !important;
        min-height: auto !important;
    }

    .dashboard-body {
        padding-inline-start: 0 !important;
        padding-inline-end: 0 !important;
        padding-block-start: 0 !important;
        padding-block-end: 0 !important;
        width: 100% !important;
    }

    .card {
        border: none;
        box-shadow: none;
        margin: 0 !important;
    }

    #stockCardTable,
    #stockCardTable_wrapper,
    #stockCardTable_wrapper .dt-layout-table {
        width: 100% !important;
    }

    .print-sheet .card-body {
        padding: 0 !important;
    }

    .stock-card-table {
        font-size: 9px;
    }

    .stock-card-table th,
    .stock-card-table td {
        padding: 4px 5px;
    }
}
</style>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="dashboard.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><a href="stock_cards.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Stock Cards</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></span></li>
                </ul>
            </div>
        </div>

        <div class="card mb-4 print-hide">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-7">
                        <label for="item_id" class="form-label">Item</label>
                        <select name="item_id" id="item_id" class="form-select select2" data-placeholder="Select item" required>
                            <option value="" disabled <?= $selectedItemId === 0 ? 'selected' : '' ?>>Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= (int)$item['id'] ?>" <?= $selectedItemId === (int)$item['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Open Card</button>
                    </div>
                    <?php if ($selectedItem): ?>
                        <div class="col-md-3 text-md-end text-start">
                            <button type="button" class="btn btn-outline-secondary" onclick="printStockCard()">Print Stock Card</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($selectedItem): ?>
            <div class="card print-sheet">
                <div class="card-body p-4">
                    <div class="text-end small mb-1">Appendix 58</div>
                    <div class="text-center mb-2 print-gov-header">
                        <div class="gov-line">REPUBLIC OF THE PHILIPPINES</div>
                        <div class="org-line"><?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($organizationAddress !== ''): ?>
                            <div class="org-address"><?= htmlspecialchars($organizationAddress, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center mb-3">
                        <h5 class="mb-0">STOCK CARD</h5>
                    </div>

                    <table class="w-100 mb-3">
                        <tr>
                            <td width="18%" class="fw-bold">Entity Name:</td>
                            <td width="32%"><?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?></td>
                            <td width="18%" class="fw-bold">Fund Cluster:</td>
                            <td width="32%"><?= htmlspecialchars($fundClusterLabel, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Item:</td>
                            <td><?= htmlspecialchars((string)($selectedItem['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="fw-bold">Stock No.:</td>
                            <td><?= htmlspecialchars((string)($selectedItem['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Description:</td>
                            <td><?= htmlspecialchars((string)($selectedItem['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="fw-bold">Re-order Point:</td>
                            <td><?= (int)($inventoryState['reorder_level'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Unit of Measurement:</td>
                            <td><?= htmlspecialchars((string)($selectedItem['unit'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="fw-bold">Card No.:</td>
                            <td><?= htmlspecialchars((string)($stockCard['card_no'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    </table>

                    <table id="stockCardTable" class="stock-card-table">
                        <thead>
                            <tr class="text-center fw-bold">
                                <th width="12%">Date</th>
                                <th width="16%">Reference</th>
                                <th width="12%">Receipt Qty</th>
                                <th width="12%">Issue Qty</th>
                                <th width="18%">Office</th>
                                <th width="12%">Balance Qty</th>
                                <th width="18%">No. of Days to Consume</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($renderRows)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-gray-500">No stock movements recorded for this item.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($renderRows as $row): ?>
                                <tr>
                                    <td class="text-center"><?= $row['date'] !== '' ? DisplayDate($row['date']) : '-' ?></td>
                                    <td><?= htmlspecialchars($row['reference'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center"><?= $row['receipt_qty'] ?></td>
                                    <td class="text-center"><?= $row['issue_qty'] ?></td>
                                    <td><?= htmlspecialchars($row['office'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center"><?= $row['balance_qty'] ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['days_to_consume'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16">
            <p class="text-gray-300 text-13 fw-normal">&copy; Copyright COTSU <?= date('Y') ?>, All Rights Reserved</p>
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function () {
    const $table = $('#stockCardTable');
    const hasRows = $table.length && $table.find('tbody td[colspan]').length === 0;
    let stockCardDataTable = null;
    let printState = null;

    const expandForPrint = () => {
        if (!stockCardDataTable) {
            return;
        }
        if (printState !== null) {
            return;
        }
        printState = {
            pageLen: stockCardDataTable.page.len(),
            page: stockCardDataTable.page(),
            search: stockCardDataTable.search(),
        };
        stockCardDataTable.search('').page.len(-1).draw(false);
        stockCardDataTable.columns.adjust();
    };

    const restoreAfterPrint = () => {
        if (!stockCardDataTable || printState === null) {
            return;
        }
        stockCardDataTable.search(printState.search).page.len(printState.pageLen).draw(false);
        stockCardDataTable.page(printState.page).draw('page');
        stockCardDataTable.columns.adjust();
        printState = null;
    };

    if (hasRows) {
        stockCardDataTable = new DataTable('#stockCardTable', {
            searching: true,
            lengthChange: false,
            info: true,
            paging: true,
            order: [[0, 'asc']]
        });
    }

    window.printStockCard = function () {
        expandForPrint();
        setTimeout(function () {
            window.print();
        }, 120);
    };

    window.addEventListener('beforeprint', expandForPrint);
    window.addEventListener('afterprint', restoreAfterPrint);
});
</script>
