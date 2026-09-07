<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/SupplyLedger.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/SystemSettings.model.php';

$pageTitle = 'Supply Ledger Card (SLC)';

$itemRepo = new Item();
$items = $itemRepo->getAll();

$settings = SystemSettings::getSettings();
$organizationName = strtoupper(trim((string)($settings['organization_name'] ?? 'Cotabato State University')));
$organizationAddress = trim((string)($settings['address'] ?? ''));
$fundClusterLabel = 'General Fund';

$selectedItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$ledgerCard = null;
$ledgerEntries = [];
$selectedItem = null;
$inventoryState = null;
$renderRows = [];

if ($selectedItemId > 0) {
    $selectedItem = $itemRepo->getById($selectedItemId);
    if ($selectedItem) {
        $ledgerCard = Supply_Ledger_Card::getByItem($selectedItemId);
        $ledgerEntries = Supply_Ledger_Entry::getEntriesByItem($selectedItemId);
        $inventoryState = StockInventory::getByItemId($selectedItemId);

        $previousIssueDate = null;

        foreach ($ledgerEntries as $entry) {
            $entryDateRaw = trim((string)($entry['entry_date'] ?? ''));
            $entryTimestamp = strtotime($entryDateRaw ?: '');
            $qtyIn = (int)($entry['qty_in'] ?? 0);
            $qtyOut = (int)($entry['qty_out'] ?? 0);
            $unitCost = (float)($entry['unit_cost'] ?? 0);
            $balanceQty = (int)($entry['balance_qty'] ?? 0);

            $daysToConsume = '-';
            if ($qtyOut > 0 && $entryTimestamp !== false) {
                if ($previousIssueDate !== null) {
                    $previousTs = strtotime($previousIssueDate);
                    if ($previousTs !== false) {
                        $daysToConsume = (string)max(0, (int)floor(($entryTimestamp - $previousTs) / 86400));
                    }
                } else {
                    $daysToConsume = '0';
                }
                $previousIssueDate = date('Y-m-d', $entryTimestamp);
            }

            $referenceNo = trim((string)($entry['reference_no'] ?? ''));
            $referenceType = trim((string)($entry['reference_type'] ?? ''));
            $reference = $referenceNo !== '' ? $referenceNo : ($referenceType !== '' ? $referenceType : '-');

            $renderRows[] = [
                'date' => $entryDateRaw,
                'reference' => $reference,
                'receipt_qty' => $qtyIn,
                'receipt_unit_cost' => $unitCost,
                'receipt_total' => $qtyIn * $unitCost,
                'issue_qty' => $qtyOut,
                'issue_unit_cost' => $unitCost,
                'issue_total' => $qtyOut * $unitCost,
                'balance_qty' => $balanceQty,
                'balance_unit_cost' => $unitCost,
                'balance_total' => $balanceQty * $unitCost,
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
.slc-table,
.slc-table th,
.slc-table td {
    border: 1px solid #111;
}

.slc-table {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
}

.slc-table th,
.slc-table td {
    padding: 6px 8px;
    line-height: 1.25;
    vertical-align: middle;
}

.slc-table thead th {
    text-align: center;
    vertical-align: middle;
    white-space: normal;
}

.slc-table thead tr:first-child th[colspan] {
    text-align: center !important;
}

.slc-table thead th.days-consume {
    line-height: 1.15;
    font-size: 8.5px;
    word-break: break-word;
    white-space: normal !important;
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
    margin-top: 0;
}

.print-title {
    margin: 0;
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

    #ledgerTable_filter,
    #ledgerTable_filter *,
    #ledgerTable_wrapper .dataTables_filter,
    #ledgerTable_wrapper .dataTables_filter *,
    #ledgerTable_wrapper .dt-search,
    #ledgerTable_wrapper .dt-search *,
    #ledgerTable_wrapper .dt-layout-row:not(.dt-layout-table) {
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

    #ledgerTable,
    #ledgerTable_wrapper,
    #ledgerTable_wrapper .dt-layout-table {
        width: 100% !important;
    }

    .print-sheet .card-body {
        padding: 0 !important;
    }

    .print-sheet {
        margin-top: -2px !important;
    }

    .print-sheet .print-gov-header-block {
        margin-top: 0 !important;
        margin-bottom: 8px !important;
    }

    .print-sheet .print-title-block {
        margin-bottom: 8px !important;
    }

    .print-sheet .details-table {
        margin-bottom: 8px !important;
    }

    .print-sheet .details-table td,
    .print-sheet .details-table th {
        font-size: 11px;
        line-height: 1.2;
    }

    .slc-table {
        font-size: 9px;
    }

    .slc-table th,
    .slc-table td {
        padding: 4px 5px;
    }

    .slc-table tbody td:nth-child(n+3) {
        white-space: nowrap;
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
                    <li><span class="text-main-600 fw-normal text-15"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></span></li>
                </ul>
            </div>
        </div>

        <div class="card mb-4 print-hide">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label for="item_id" class="form-label">Item</label>
                        <select name="item_id" id="item_id" class="form-select select2" data-placeholder="Select item" required>
                            <option value="" disabled <?= $selectedItemId === 0 ? 'selected' : '' ?>>Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= (int)$item['id'] ?>" <?= ($selectedItemId === (int)$item['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(formatItemSelectLabel($item), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">View Ledger</button>
                    </div>
                    <?php if ($selectedItem): ?>
                        <div class="col-md-4 text-md-end text-start">
                            <button type="button" class="btn btn-outline-secondary" onclick="printLedger()">Print SLC</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($selectedItem && $ledgerCard): ?>
            <div class="card print-sheet">
                <div class="card-body p-4">
                    <div class="text-center mb-2 print-gov-header print-gov-header-block">
                        <div class="gov-line">REPUBLIC OF THE PHILIPPINES</div>
                        <div class="org-line"><?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($organizationAddress !== ''): ?>
                            <div class="org-address"><?= htmlspecialchars($organizationAddress, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center mb-3 print-title-block">
                        <h5 class="mb-0 print-title">SUPPLIES LEDGER CARD</h5>
                    </div>

                    <table class="w-100 mb-3 details-table">
                        <tr>
                            <td width="18%" class="fw-bold">Entity Name:</td>
                            <td width="32%"><?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?></td>
                            <td width="18%" class="fw-bold">Fund Cluster:</td>
                            <td width="32%"><?= htmlspecialchars($fundClusterLabel, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Item:</td>
                            <td><?= htmlspecialchars((string)($selectedItem['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="fw-bold">Item Code:</td>
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
                            <td class="fw-bold">Ledger No.:</td>
                            <td><?= htmlspecialchars((string)($ledgerCard['ledger_no'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    </table>

                    <table id="ledgerTable" class="slc-table">
                        <thead>
                            <tr class="text-center fw-bold">
                                <th width="7%" rowspan="2">Date</th>
                                <th width="10%" rowspan="2">Reference</th>
                                <th width="23%" colspan="3">Receipt</th>
                                <th width="23%" colspan="3">Issue</th>
                                <th width="23%" colspan="3">Balance</th>
                                <th width="14%" rowspan="2" class="days-consume">No. of Days<br>to Consume</th>
                            </tr>
                            <tr class="text-center fw-bold">
                                <th width="7%">Qty.</th>
                                <th width="8%">Unit Cost</th>
                                <th width="8%">Total Cost</th>
                                <th width="7%">Qty.</th>
                                <th width="8%">Unit Cost</th>
                                <th width="8%">Total Cost</th>
                                <th width="7%">Qty.</th>
                                <th width="8%">Unit Cost</th>
                                <th width="8%">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($renderRows)): ?>
                                <tr>
                                    <td colspan="12" class="text-center text-gray-500">No ledger entries yet.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($renderRows as $row): ?>
                                <tr>
                                    <td class="text-center"><?= $row['date'] !== '' ? DisplayDate($row['date']) : '-' ?></td>
                                    <td><?= htmlspecialchars($row['reference'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center"><?= $row['receipt_qty'] ?></td>
                                    <td class="text-right"><?= number_format((float)$row['receipt_unit_cost'], 2) ?></td>
                                    <td class="text-right"><?= number_format((float)$row['receipt_total'], 2) ?></td>
                                    <td class="text-center"><?= $row['issue_qty'] ?></td>
                                    <td class="text-right"><?= number_format((float)$row['issue_unit_cost'], 2) ?></td>
                                    <td class="text-right"><?= number_format((float)$row['issue_total'], 2) ?></td>
                                    <td class="text-center"><?= $row['balance_qty'] ?></td>
                                    <td class="text-right"><?= number_format((float)$row['balance_unit_cost'], 2) ?></td>
                                    <td class="text-right"><?= number_format((float)$row['balance_total'], 2) ?></td>
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
    const $table = $('#ledgerTable');
    const hasRows = $table.length && $table.find('tbody td[colspan]').length === 0;
    let ledgerDataTable = null;
    let printState = null;

    const expandForPrint = () => {
        if (!ledgerDataTable) {
            return;
        }
        if (printState !== null) {
            return;
        }
        printState = {
            pageLen: ledgerDataTable.page.len(),
            page: ledgerDataTable.page(),
            search: ledgerDataTable.search(),
        };
        ledgerDataTable.search('').page.len(-1).draw(false);
        ledgerDataTable.columns.adjust();
    };

    const restoreAfterPrint = () => {
        if (!ledgerDataTable || printState === null) {
            return;
        }
        ledgerDataTable.search(printState.search).page.len(printState.pageLen).draw(false);
        ledgerDataTable.page(printState.page).draw('page');
        ledgerDataTable.columns.adjust();
        printState = null;
    };

    if (hasRows) {
        ledgerDataTable = new DataTable('#ledgerTable', {
            searching: true,
            lengthChange: false,
            info: true,
            paging: true,
            ordering: false,
            autoWidth: false,
            columnDefs: [
                { targets: 0, width: '7%' },
                { targets: 1, width: '10%' },
                { targets: 2, width: '7%' },
                { targets: 3, width: '8%' },
                { targets: 4, width: '8%' },
                { targets: 5, width: '7%' },
                { targets: 6, width: '8%' },
                { targets: 7, width: '8%' },
                { targets: 8, width: '7%' },
                { targets: 9, width: '8%' },
                { targets: 10, width: '8%' },
                { targets: 11, width: '14%' }
            ]
        });
    }

    window.printLedger = function () {
        expandForPrint();
        setTimeout(function () {
            window.print();
        }, 120);
    };

    window.addEventListener('beforeprint', expandForPrint);
    window.addEventListener('afterprint', restoreAfterPrint);
});
</script>
