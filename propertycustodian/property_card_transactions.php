<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/PropertyCard.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/PAR.model.php';
require_once __DIR__ . '/../repo/Supply_Issuance.model.php';

$pageTitle = "Property Card History";

$cardModel = new PropertyCard();
$cardList = $cardModel->getAll();

$activeCardId = isset($_GET['card_id']) ? trim((string)$_GET['card_id']) : '';
$selectedCard = null;
foreach ($cardList as $card) {
    if ((string)$card['id'] === $activeCardId) {
        $selectedCard = $card;
        break;
    }
}

$itemModel = new Item();
$items = $itemModel->getAll();

$userModel = new BaseModel('user_role_dept');
$users = $userModel->getAll();

$eligibleOfficeOfficers = array_values(array_filter($users, static function ($user) {
    return (int)($user['roleid'] ?? 0) === 6
        && strtolower(trim((string)($user['status'] ?? ''))) === 'active';
}));
usort($eligibleOfficeOfficers, static function ($a, $b) {
    return strcasecmp((string)($a['fullname'] ?? ''), (string)($b['fullname'] ?? ''));
});

$eligibleOfficeOfficerIds = array_map(static fn($user) => (int)($user['id'] ?? 0), $eligibleOfficeOfficers);

function propertyCardOfficeOfficerLabel(?array $user): string
{
    return formatOfficerNameWithDeptCode($user);
}

$risModel = new RIS();
$risList = $risModel->getAll();
$parModel = new PAR();
$parList = $parModel->getAll();
$issuanceModel = new Supply_Issuance();
$issuanceList = $issuanceModel->getAll();

$risRefs = [];
foreach ($risList as $ris) {
    $ref = trim((string)($ris['ris_no'] ?? ''));
    if ($ref !== '') {
        $risRefs[] = $ref;
    }
}
$parRefs = [];
foreach ($parList as $par) {
    $ref = trim((string)($par['par_no'] ?? ''));
    if ($ref !== '') {
        $parRefs[] = $ref;
    }
}
$risRefs = array_values(array_unique($risRefs));
$parRefs = array_values(array_unique($parRefs));
sort($parRefs);
$rsmiRefs = [];
foreach ($issuanceList as $issuance) {
    $ref = trim((string)($issuance['rsmi_no'] ?? ''));
    if ($ref !== '') {
        $rsmiRefs[] = $ref;
    }
}
$rsmiRefs = array_values(array_unique($rsmiRefs));
sort($risRefs);
sort($parRefs);
sort($rsmiRefs);

$transactionModel = new PropertyCardTransaction();
$transactionList = $activeCardId !== '' ? $transactionModel->getAll($activeCardId) : [];

$statusOptions = PropertyCard::STATUSES;
$statusBadges = [
    'Assigned' => 'bg-success text-dark',
    'For Repair' => 'bg-warning text-dark',
    'Unserviceable' => 'bg-danger',
    'Disposed' => 'bg-secondary text-dark'
];

$itemForCard = $selectedCard ? SearchById($items, $selectedCard['item_id'] ?? '') : null;
$accountable = $selectedCard ? SearchById($users, $selectedCard['accountable_officer'] ?? '') : null;
$lastTransaction = !empty($transactionList) ? $transactionList[0] : null;
$defaultOfficeOfficerId = $selectedCard ? (int)($selectedCard['accountable_officer'] ?? 0) : 0;
if (!in_array($defaultOfficeOfficerId, $eligibleOfficeOfficerIds, true)) {
    $defaultOfficeOfficerId = 0;
}


?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">

        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="index.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><a href="property_cards.php" class="text-gray-200 fw-normal text-15 hover-text-main-600"><?= $pageTitle ?></a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15">Transactions</span></li>
                </ul>
            </div>
            <div class="flex-align gap-8 flex-wrap">
                <select class="form-select select2" id="top_card_id" name="top_card_id" data-placeholder="Select property card">
                    <option value="" disabled <?= $activeCardId === '' ? 'selected' : '' ?>>Select Property Card</option>
                    <?php foreach ($cardList as $card): ?>
                    <option value="<?= $card['id'] ?>" <?= $activeCardId === (string)$card['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($card['card_no']) ?> - <?= htmlspecialchars($card['property_tag']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selectedCard): ?>
                <a href="../administrator/print/property_card.php?id=<?= (int)$selectedCard['id'] ?>"
                   target="_blank"
                   class="btn-action"
                   aria-label="Print Property Card"
                   data-bs-toggle="tooltip"
                   title="Print Property Card">
                    <i class="ph ph-printer"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selectedCard): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-4">
                    <div>
                        <h5 class="text-gray-900 mb-1"><?= htmlspecialchars($selectedCard['card_no']) ?></h5>
                        <p class="text-gray-500 mb-0">Property Tag: <span class="fw-semibold"><?= htmlspecialchars($selectedCard['property_tag']) ?></span></p>
                        <p class="text-gray-500 mb-0">Current Status:
                            <span class="badge <?= $statusBadges[$selectedCard['current_status']] ?? 'bg-secondary' ?>">
                                <?= htmlspecialchars($selectedCard['current_status']) ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500 mb-0">Item:
                            <span class="fw-semibold"><?= htmlspecialchars($itemForCard['description'] ?? 'N/A') ?></span>
                        </p>
                        <p class="text-gray-500 mb-0">Accountable Officer:
                            <span class="fw-semibold"><?= htmlspecialchars($accountable['fullname'] ?? 'Unassigned') ?></span>
                        </p>
                        <p class="text-gray-500 mb-0">Location:
                            <span class="fw-semibold"><?= htmlspecialchars($selectedCard['location'] ?? '') ?></span>
                        </p>
                    </div>
                    <div class="text-end">
                        <p class="text-gray-500 mb-1">Movement Summary</p>
                        <p class="text-gray-700 mb-0">Total Transactions: <span class="fw-semibold"><?= count($transactionList) ?></span></p>
                        <?php if ($lastTransaction): ?>
                        <p class="text-gray-700 mb-0">Latest: <span class="fw-semibold"><?= htmlspecialchars($lastTransaction['status']) ?></span></p>
                        <p class="text-gray-500 mb-0"><?= DisplayDate($lastTransaction['transaction_date']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($selectedCard): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="mb-3 text-gray-900">Add Transaction</h6>
                <form id="addTxnForm" action="property_card-actions.php?action=addtxn" method="post" class="row g-3 align-items-end">
                    <input type="hidden" name="property_card_id" value="<?= htmlspecialchars($activeCardId) ?>">
                    <div class="col-md-3">
                        <label for="txn_transaction_date" class="form-label mb-1">Transaction Date</label>
                        <input type="date" class="form-control" id="txn_transaction_date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="txn_status" class="form-label mb-1">Status</label>
                        <select class="form-select select2" id="txn_status" name="status" data-placeholder="Select status" required>
                            <option value="" disabled selected>Select Status</option>
                            <?php foreach ($statusOptions as $status): ?>
                            <option value="<?= $status ?>"><?= $status ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="txn_reference_source" class="form-label mb-1">Reference Source</label>
                        <select class="form-select select2" id="txn_reference_source" data-placeholder="Select source">
                            <?php if (!empty($risRefs)): ?>
                            <option value="RIS" selected>RIS (Request for Supply)</option>
                            <?php endif; ?>
                            <?php if (!empty($parRefs)): ?>
                            <option value="PAR" <?= empty($risRefs) ? 'selected' : '' ?>>PAR Header</option>
                            <?php endif; ?>
                            <?php if (!empty($rsmiRefs)): ?>
                            <option value="RSMI" <?= empty($risRefs) && empty($parRefs) ? 'selected' : '' ?>>RSMI</option>
                            <?php endif; ?>
                            <option value="Memo" <?= empty($risRefs) && empty($parRefs) && empty($rsmiRefs) ? 'selected' : '' ?>>Other / Memo</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="txn_performed_by" class="form-label mb-1">Performed By</label>
                        <select class="form-select select2" id="txn_performed_by" name="performed_by" data-placeholder="Performed by" data-allow-clear>
                            <option value="">Optional</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="txn_reference_no_select" class="form-label mb-1">Reference No.</label>
                        <select class="form-select" id="txn_reference_no_select" name="reference_no" data-placeholder="Select reference">
                            <option value="" selected disabled>Select reference</option>
                        </select>
                        <input type="text" class="form-control mt-2 d-none" id="txn_reference_no_memo" name="reference_no" placeholder="Enter memo reference" disabled>
                    </div>
                    <div class="col-md-2">
                        <label for="txn_receipt_qty" class="form-label mb-1">Receipt Qty</label>
                        <input type="number" min="0" class="form-control" id="txn_receipt_qty" name="receipt_qty" value="0" required>
                    </div>
                    <div class="col-md-2">
                        <label for="txn_issue_qty" class="form-label mb-1">Issue/Transfer/Disposal Qty</label>
                        <input type="number" min="0" class="form-control" id="txn_issue_qty" name="issue_qty" value="0" required>
                    </div>
                    <div class="col-md-2">
                        <label for="txn_amount" class="form-label mb-1">Amount</label>
                        <input type="number" min="0" step="0.01" class="form-control" id="txn_amount" name="amount" placeholder="Auto if blank">
                    </div>
                    <div class="col-md-6">
                        <label for="txn_office_officer_id" class="form-label mb-1">Office / Officer</label>
                        <select class="form-select select2" id="txn_office_officer_id" name="office_officer_id" data-placeholder="Select officer" required>
                            <option value="" disabled <?= $defaultOfficeOfficerId === 0 ? 'selected' : '' ?>>Select Active Permanent Employee</option>
                            <?php foreach ($eligibleOfficeOfficers as $officer): ?>
                            <?php $optionId = (int)($officer['id'] ?? 0); ?>
                            <option value="<?= $optionId ?>" <?= $defaultOfficeOfficerId === $optionId ? 'selected' : '' ?>>
                                <?= htmlspecialchars(propertyCardOfficeOfficerLabel($officer)) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="txn_notes" class="form-label mb-1">Notes</label>
                        <input type="text" class="form-control" id="txn_notes" name="notes" placeholder="Details or remarks">
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">Add Transaction</button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif (!empty($cardList)): ?>
        <div class="alert alert-info">Select a property card to view and record its history.</div>
        <?php endif; ?>

        <div class="card overflow-hidden p-5">
            <div class="card-body p-5 overflow-x-auto">
                <table id="txnTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Date</th>
                            <th class="h6 text-gray-300">Reference</th>
                            <th class="h6 text-gray-300 text-center">Receipt Qty</th>
                            <th class="h6 text-gray-300 text-center">Issue/Transfer/Disposal Qty</th>
                            <th class="h6 text-gray-300">Office / Officer</th>
                            <th class="h6 text-gray-300 text-center">Balance Qty</th>
                            <th class="h6 text-gray-300 text-center">Amount</th>
                            <th class="h6 text-gray-300">Status</th>
                            <th class="h6 text-gray-300">Notes</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactionList as $txn): ?>
                        <?php
                            $performer = SearchById($users, $txn['performed_by'] ?? '');
                            $receiptQty = (int)($txn['receipt_qty'] ?? 0);
                            $issueQty = (int)($txn['issue_qty'] ?? 0);
                            $balanceQty = (int)($txn['balance_qty'] ?? 0);
                            $amountValue = (float)($txn['amount'] ?? 0);
                            $officeOfficerId = (int)($txn['office_officer_id'] ?? 0);
                            $officeOfficerUser = $officeOfficerId > 0 ? SearchById($users, $officeOfficerId) : null;
                            $officeOfficer = $officeOfficerUser ? propertyCardOfficeOfficerLabel($officeOfficerUser) : trim((string)($txn['office_officer'] ?? ''));
                            if (!$officeOfficerUser && strpos($officeOfficer, ' / ') !== false) {
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
                                $officeOfficer = $performer ? formatOfficerNameWithDeptCode($performer) : 'N/A';
                            }
                        ?>
                        <tr>
                            <td class="text-gray-900"><?= DisplayDate($txn['transaction_date']) ?></td>
                            <td class="text-gray-900"><?= htmlspecialchars($txn['reference_no'] ?? '') ?></td>
                            <td class="text-gray-900 text-center"><?= $receiptQty ?></td>
                            <td class="text-gray-900 text-center"><?= $issueQty ?></td>
                            <td class="text-gray-900"><?= htmlspecialchars($officeOfficer) ?></td>
                            <td class="text-gray-900 text-center"><?= $balanceQty ?></td>
                            <td class="text-gray-900 text-center"><?= number_format($amountValue, 2) ?></td>
                            <td class="text-gray-900">
                                <span class="badge <?= $statusBadges[$txn['status']] ?? 'bg-secondary' ?>">
                                    <?= htmlspecialchars($txn['status']) ?>
                                </span>
                            </td>
                            <td class="text-gray-900"><?= htmlspecialchars($txn['notes'] ?? '') ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap justify-content-center">
                                    <button
                                        type="button"
                                        class="btn-action btn-action-primary edit-txn-btn"
                                        aria-label="Edit transaction"
                                        data-bs-toggle="tooltip"
                                        title="Edit transaction"
                                        data-id="<?= $txn['id'] ?>"
                                        data-property_card_id="<?= $txn['property_card_id'] ?>"
                                        data-transaction_date="<?= $txn['transaction_date'] ?>"
                                        data-status="<?= htmlspecialchars($txn['status'], ENT_QUOTES) ?>"
                                        data-reference_no="<?= htmlspecialchars($txn['reference_no'] ?? '', ENT_QUOTES) ?>"
                                        data-receipt_qty="<?= $receiptQty ?>"
                                        data-issue_qty="<?= $issueQty ?>"
                                        data-office_officer_id="<?= htmlspecialchars((string)$officeOfficerId, ENT_QUOTES) ?>"
                                        data-office_officer="<?= htmlspecialchars($officeOfficer, ENT_QUOTES) ?>"
                                        data-amount="<?= number_format($amountValue, 2, '.', '') ?>"
                                        data-notes="<?= htmlspecialchars($txn['notes'] ?? '', ENT_QUOTES) ?>"
                                        data-performed_by="<?= htmlspecialchars($txn['performed_by'] ?? '', ENT_QUOTES) ?>"
                                    >
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <form action="property_card-actions.php?action=deltxn" method="post" onsubmit="return confirm('Remove this transaction?');">
                                        <input type="hidden" name="property_card_id" value="<?= htmlspecialchars($activeCardId) ?>">
                                        <input type="hidden" name="id" value="<?= $txn['id'] ?>">
                                        <button type="submit" class="btn-action btn-action-danger"
                                            aria-label="Remove transaction"
                                            data-bs-toggle="tooltip"
                                            title="Remove transaction">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16">
            <p class="text-gray-300 text-13 fw-normal"> &copy; Copyright COTSU 2025, All Right Reserverd</p>
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTxnModal" tabindex="-1" aria-labelledby="editTxnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTxnModalLabel">Update Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editTxnForm" action="property_card-actions.php?action=updatetxn" method="post">
                    <input type="hidden" name="id" id="edit_txn_id">
                    <input type="hidden" name="property_card_id" id="edit_txn_card_id">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_txn_date" class="form-label">Transaction Date</label>
                            <input type="date" class="form-control" id="edit_txn_date" name="transaction_date" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_txn_status" class="form-label">Status</label>
                            <select class="form-select select2" id="edit_txn_status" name="status" data-placeholder="Select status" required>
                                <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_txn_reference_source" class="form-label">Reference Source</label>
                            <select class="form-select select2" id="edit_txn_reference_source" data-placeholder="Select source">
                                <?php if (!empty($risRefs)): ?>
                                <option value="RIS">RIS (Request for Supply)</option>
                                <?php endif; ?>
                                <?php if (!empty($parRefs)): ?>
                                <option value="PAR">PAR Header</option>
                                <?php endif; ?>
                                <?php if (!empty($rsmiRefs)): ?>
                                <option value="RSMI">RSMI</option>
                                <?php endif; ?>
                                <option value="Memo">Other / Memo</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_txn_performed_by" class="form-label">Performed By</label>
                            <select class="form-select select2" id="edit_txn_performed_by" name="performed_by" data-placeholder="Performed by" data-allow-clear>
                                <option value="">Optional</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_txn_reference_no_select" class="form-label">Reference No.</label>
                            <select class="form-select" id="edit_txn_reference_no_select" name="reference_no" data-placeholder="Select reference">
                                <option value="" selected disabled>Select reference</option>
                            </select>
                            <input type="text" class="form-control mt-2 d-none" id="edit_txn_reference_no_memo" name="reference_no" placeholder="Enter memo reference" disabled>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_txn_receipt_qty" class="form-label">Receipt Qty</label>
                            <input type="number" min="0" class="form-control" id="edit_txn_receipt_qty" name="receipt_qty" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_txn_issue_qty" class="form-label">Issue/Transfer/Disposal Qty</label>
                            <input type="number" min="0" class="form-control" id="edit_txn_issue_qty" name="issue_qty" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_txn_amount" class="form-label">Amount</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="edit_txn_amount" name="amount">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_txn_office_officer_id" class="form-label">Office / Officer</label>
                            <select class="form-select select2" id="edit_txn_office_officer_id" name="office_officer_id" data-placeholder="Select officer" required>
                                <option value="" disabled selected>Select Active Permanent Employee</option>
                                <?php foreach ($eligibleOfficeOfficers as $officer): ?>
                                <?php $optionId = (int)($officer['id'] ?? 0); ?>
                                <option value="<?= $optionId ?>"><?= htmlspecialchars(propertyCardOfficeOfficerLabel($officer)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_txn_notes" class="form-label">Notes</label>
                            <input type="text" class="form-control" id="edit_txn_notes" name="notes">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updateTxnBtn">Update</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function() {
    const referenceData = {
        RIS: <?= json_encode($risRefs) ?>,
        PAR: <?= json_encode($parRefs) ?>,
        RSMI: <?= json_encode($rsmiRefs) ?>
    };

    function setMemoMode($select, $memoInput, enabled) {
        if (enabled) {
            $select.addClass('d-none').prop('disabled', true);
            $memoInput.removeClass('d-none').prop('disabled', false);
        } else {
            $select.removeClass('d-none').prop('disabled', false);
            $memoInput.addClass('d-none').prop('disabled', true).val('');
        }
    }

    function populateReferenceSelect($select, source, selectedValue) {
        const options = referenceData[source] || [];
        let html = '<option value="" disabled selected>Select reference</option>';
        options.forEach((ref) => {
            const selected = ref === selectedValue ? ' selected' : '';
            html += `<option value="${ref}"${selected}>${ref}</option>`;
        });
        $select.html(html);
        $select.trigger('change');
    }

    function initReferencePicker(sourceSelector, selectSelector, memoSelector, initialValue) {
        const $source = $(sourceSelector);
        const $select = $(selectSelector);
        const $memo = $(memoSelector);

        function resolveInitialSource(value) {
            if (!value) {
                return $source.val()
                    || (referenceData.RIS && referenceData.RIS.length
                        ? 'RIS'
                        : referenceData.PAR && referenceData.PAR.length
                            ? 'PAR'
                            : referenceData.RSMI && referenceData.RSMI.length
                                ? 'RSMI'
                                : 'Memo');
            }
            if (referenceData.RIS && referenceData.RIS.includes(value)) {
                return 'RIS';
            }
            if (referenceData.PAR && referenceData.PAR.includes(value)) {
                return 'PAR';
            }
            if (referenceData.RSMI && referenceData.RSMI.includes(value)) {
                return 'RSMI';
            }
            return 'Memo';
        }

        function sync(source, value) {
            if (source === 'Memo') {
                setMemoMode($select, $memo, true);
                $memo.val(value || '');
            } else {
                setMemoMode($select, $memo, false);
                populateReferenceSelect($select, source, value);
            }
        }

        const initialSource = resolveInitialSource(initialValue);
        $source.val(initialSource).trigger('change');
        sync(initialSource, initialValue);

        $source.off('change.reference').on('change.reference', function() {
            sync($(this).val(), '');
        });
    }
    new DataTable('#txnTable', {
        searching: false,
        lengthChange: false,
        info: false,
        paging: false,
        order: [[0, 'desc']],
        columnDefs: [{
            orderable: false,
            targets: [9]
        }]
    });

    $('#top_card_id').on('change', function() {
        const selectedId = $(this).val();
        if (selectedId) {
            window.location.href = 'property_card_transactions.php?card_id=' + selectedId;
        }
    });

    initReferencePicker('#txn_reference_source', '#txn_reference_no_select', '#txn_reference_no_memo', '');

    $(document).on('click', '.edit-txn-btn', function() {
        const btn = $(this);
        $('#edit_txn_id').val(btn.data('id'));
        $('#edit_txn_card_id').val(btn.data('property_card_id'));
        $('#edit_txn_date').val(btn.data('transaction_date'));
        $('#edit_txn_status').val(btn.data('status')).trigger('change');
        $('#edit_txn_performed_by').val(btn.data('performed_by')).trigger('change');
        $('#edit_txn_receipt_qty').val(btn.data('receipt_qty') ?? 0);
        $('#edit_txn_issue_qty').val(btn.data('issue_qty') ?? 0);
        $('#edit_txn_office_officer_id').val(btn.data('office_officer_id') ?? '').trigger('change');
        $('#edit_txn_amount').val(btn.data('amount') ?? '');
        $('#edit_txn_notes').val(btn.data('notes'));
        initReferencePicker('#edit_txn_reference_source', '#edit_txn_reference_no_select', '#edit_txn_reference_no_memo', btn.data('reference_no'));
        $('#editTxnModal').modal('show');
    });

    $('#updateTxnBtn').on('click', function() {
        $('#editTxnForm').submit();
    });
});
</script>
