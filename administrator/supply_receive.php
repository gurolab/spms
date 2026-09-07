<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/Supply_Receive.model.php';
require_once __DIR__ . '/../repo/User.model.php';
require_once __DIR__ . '/../repo/Supplier.model.php';
require_once __DIR__ . '/../repo/Item.model.php';

$pageTitle = "Receive Supplies";

$iRef1 = new Supplier();
$suppliers = $iRef1->getAll();

$iRef2 = new BaseModel('user_role_dept');
$users = $iRef2->getAll();
$isSupplyOfficerView = isSupplyOfficer();
$currentUserId = (string)(getUser('id') ?? '');
$currentUserName = (string)(getUser('fullname') ?? 'Supply Officer');

$iModel1 = new Supply_Receive_Header();
$list = $iModel1->getAll();

$iRef3 = new Item();
$items = $iRef3->getAll();

$iItemsModel = new Supply_Receive_Items();
$receiptItemSummary = [];
foreach ($iItemsModel->getAll() as $receiveItem) {
    $receiptId = (int)($receiveItem['receipt_header_id'] ?? 0);
    if ($receiptId === 0) {
        continue;
    }

    if (!isset($receiptItemSummary[$receiptId])) {
        $receiptItemSummary[$receiptId] = [
            'lines' => 0,
            'qty' => 0,
            'total_cost' => 0.0
        ];
    }

    $lineQty = (int)($receiveItem['qty_received'] ?? 0);
    $lineCost = (float)($receiveItem['unit_cost'] ?? 0);

    $receiptItemSummary[$receiptId]['lines'] += 1;
    $receiptItemSummary[$receiptId]['qty'] += $lineQty;
    $receiptItemSummary[$receiptId]['total_cost'] += ($lineCost * $lineQty);
}

?>




<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>


<!--Content-->
<div class="dashboard-main-wrapper">



    <div class="dashboard-body">

        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <!-- Breadcrumb Start -->
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="index.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li> <span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span> </li>
                    <li><span class="text-main-600 fw-normal text-15"><?=$pageTitle?></span></li>
                </ul>
            </div>
            <!-- Breadcrumb End -->

            <!-- Breadcrumb Right Start -->
            <div class="flex-align gap-8 flex-wrap">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ph ph-plus-circle me-1"></i> Add New Receipt
                </button>
                
            </div>
            <!-- Breadcrumb Right End -->
        </div>


        <div class="card overflow-hidden">
            <div class="card-body p-5 overflow-x-auto">
                <table id="dtable" class="table table-striped table-auto-fit">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Receipt No</th>
                            <th class="h6 text-gray-300">Date</th>
                            <th class="h6 text-gray-300">Supplier</th>
                            <th class="h6 text-gray-300">Items Received</th>
                            <th class="h6 text-gray-300">Avg Unit Cost</th>
                            <th class="h6 text-gray-300">Received By</th>
                            <th class="h6 text-gray-300">Inspected By</th>
                            <th class="h6 text-gray-300">PR/PO/DR/SI/IAR</th>
                            <th class="h6 text-gray-300">Notes</th>
                            <th class="h6 text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r) : ?>
                        <tr>
                            <?php
                                $summary = $receiptItemSummary[$r['id']] ?? ['lines' => 0, 'qty' => 0, 'total_cost' => 0];
                                $itemsLabel = $summary['lines'] === 1 ? 'item' : 'items';
                                $itemsDisplay = $summary['qty'] > 0
                                    ? sprintf('%d qty / %d %s', $summary['qty'], $summary['lines'], $itemsLabel)
                                    : '—';
                                $avgUnitCostDisplay = $summary['qty'] > 0
                                    ? number_format($summary['total_cost'] / max(1, $summary['qty']), 2)
                                    : '0.00';
                            ?>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?= htmlspecialchars((string)$r['receipt_no']) ?></span>
                                    <span class="text-gray-500 text-12">DR: <?= htmlspecialchars(trim((string)($r['dr_no'] ?? '')) !== '' ? (string)$r['dr_no'] : '--') ?></span>
                                    <span class="text-gray-500 text-12">SI: <?= htmlspecialchars(trim((string)($r['si_no'] ?? '')) !== '' ? (string)$r['si_no'] : '--') ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= DisplayDate($r['receipt_date']); ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column">
                                    <span class="text-15 fw-medium"><?=SearchById($suppliers,$r['supplier_id'])['name']; ?></span>
                                    <span class="text-gray-500 text-13"><?= SearchById($suppliers,$r['supplier_id'])['contact_person'];  ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= $itemsDisplay ?></td>
                            <td class="text-gray-900"><?= $avgUnitCostDisplay ?></td>
                            
                            <td class="text-gray-900"><?= SearchById($users,$r['received_by'])['fullname']; ?></td>
                            <td class="text-gray-900"><?= SearchById($users,$r['inspected_by'])['fullname']; ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex flex-column text-12">
                                    <span><span class="text-gray-500">PR:</span> <?= htmlspecialchars(trim((string)($r['pr_no'] ?? '')) !== '' ? (string)$r['pr_no'] : '--') ?></span>
                                    <span><span class="text-gray-500">PO:</span> <?= htmlspecialchars(trim((string)($r['po_no'] ?? '')) !== '' ? (string)$r['po_no'] : '--') ?></span>
                                    <span><span class="text-gray-500">DR:</span> <?= htmlspecialchars(trim((string)($r['dr_no'] ?? '')) !== '' ? (string)$r['dr_no'] : '--') ?></span>
                                    <span><span class="text-gray-500">SI:</span> <?= htmlspecialchars(trim((string)($r['si_no'] ?? '')) !== '' ? (string)$r['si_no'] : '--') ?></span>
                                    <span><span class="text-gray-500">IAR:</span> <?= htmlspecialchars(trim((string)($r['iar_no'] ?? '')) !== '' ? (string)$r['iar_no'] : '--') ?></span>
                                </div>
                            </td>
                            <td class="text-gray-900"><?= $r['notes'] ?></td>
                            <td class="text-gray-900">
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="supply_receive_items.php?receipt_header_id=<?= $r['id'] ?>"
                                        class="btn-action"
                                        aria-label="View items"
                                        data-bs-toggle="tooltip"
                                        title="View items">
                                        <i class="ph ph-list-bullets"></i>
                                    </a>
                                    <button class="btn-action btn-action-primary edit-btn"
                                        type="button"
                                        aria-label="Edit receipt"
                                        data-bs-toggle="tooltip"
                                        title="Edit receipt"
                                        data-id="<?= $r['id'] ?>">
                                        <i class="ph ph-pencil-line"></i>
                                    </button>
                                    <button class="btn-action btn-action-danger delete-btn"
                                        type="button"
                                        aria-label="Delete receipt"
                                        data-bs-toggle="tooltip"
                                        title="Delete receipt"
                                        data-id="<?= $r['id'] ?>">
                                        <i class="ph ph-trash"></i>
                                    </button>
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
            <div class="flex-align flex-wrap gap-16">
            </div>
        </div>
    </div>




</div>

<!--Content-->

<!-- Modal -->

<!-- Add User Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addForm" action="supply_receive-actions.php?action=add" method="post">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="receipt_no" class="form-label">Receipt No</label>
                                <input type="text" class="form-control" id="receipt_no" name="receipt_no" placeholder="DR-XXXXX or SI-XXXXX" required>
                            </div>
                            <div class="col-md-6">
                                <label for="receipt_date" class="form-label">Receipt Date</label>
                                <input type="date" class="form-control" id="receipt_date" name="receipt_date" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="pr_no" class="form-label">PR No</label>
                                <input type="text" class="form-control" id="pr_no" name="pr_no" maxlength="100" placeholder="Optional">
                            </div>
                            <div class="col-md-4">
                                <label for="po_no" class="form-label">PO No</label>
                                <input type="text" class="form-control" id="po_no" name="po_no" maxlength="100" placeholder="Optional">
                            </div>
                            <div class="col-md-4">
                                <label for="dr_no" class="form-label">DR No</label>
                                <input type="text" class="form-control" id="dr_no" name="dr_no" maxlength="100" placeholder="Use for DR mode">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="si_no" class="form-label">SI No</label>
                                <input type="text" class="form-control" id="si_no" name="si_no" maxlength="100" placeholder="Use for SI mode">
                            </div>
                            <div class="col-md-6">
                                <label for="iar_no" class="form-label">IAR No</label>
                                <input type="text" class="form-control" id="iar_no" name="iar_no" maxlength="100" placeholder="Optional">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <small class="text-gray-500">Fill either DR No or SI No only. Receipt No will follow the same prefix.</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="supplier_id" class="form-label">Supplier</label>
                            <select class="form-select select2" id="supplier_id" name="supplier_id" data-placeholder="Select supplier" required>
                                    <option value="" disabled selected>Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier) : ?>
                                        <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="received_by" class="form-label">Received By</label>
                            <select class="form-select select2" id="received_by" name="received_by" data-placeholder="Select user" data-allow-clear>
                                    <option value="" disabled selected>Select User</option>
                                    <?php foreach ($users as $user) : ?>
                                        <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="inspected_by" class="form-label">Inspected By</label>
                            <select class="form-select select2" id="inspected_by" name="inspected_by" data-placeholder="Select inspector" data-allow-clear>
                                    <option value="" disabled selected>Select User</option>
                                    <?php foreach ($users as $user) : ?>
                                        <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Supply Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" action="supply_receive-actions.php?action=update" method="post">
                    

                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_receipt_no" class="form-label">Receipt No</label>
                            <input type="text" class="form-control" id="edit_receipt_no" name="receipt_no" placeholder="DR-XXXXX or SI-XXXXX" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_receipt_date" class="form-label">Receipt Date</label>
                            <input type="date" class="form-control" id="edit_receipt_date" name="receipt_date" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_pr_no" class="form-label">PR No</label>
                            <input type="text" class="form-control" id="edit_pr_no" name="pr_no" maxlength="100" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_po_no" class="form-label">PO No</label>
                            <input type="text" class="form-control" id="edit_po_no" name="po_no" maxlength="100" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_dr_no" class="form-label">DR No</label>
                            <input type="text" class="form-control" id="edit_dr_no" name="dr_no" maxlength="100" placeholder="Use for DR mode">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_si_no" class="form-label">SI No</label>
                            <input type="text" class="form-control" id="edit_si_no" name="si_no" maxlength="100" placeholder="Use for SI mode">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_iar_no" class="form-label">IAR No</label>
                            <input type="text" class="form-control" id="edit_iar_no" name="iar_no" maxlength="100" placeholder="Optional">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <small class="text-gray-500">Fill either DR No or SI No only. Receipt No will follow the same prefix.</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_supplier_id" class="form-label">Supplier</label>
                            <select class="form-select select2" id="edit_supplier_id" name="supplier_id" data-placeholder="Select supplier" required>
                                <option value="" disabled selected>Select Supplier</option>
                                <?php foreach ($suppliers as $supplier) : ?>
                                    <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_received_by" class="form-label">Received By</label>
                            <select class="form-select select2" id="edit_received_by" name="received_by" data-placeholder="Select user" data-allow-clear>
                                <option value="" disabled selected>Select User</option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_inspected_by" class="form-label">Inspected By</label>
                            <select class="form-select select2" id="edit_inspected_by" name="inspected_by" data-placeholder="Select inspector" data-allow-clear>
                                <option value="" disabled selected>Select User</option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= $user['id'] ?>"><?= $user['fullname'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="id" id="edit_id">

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateBtn">Update</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this data? <br> This action cannot be undone.</p>
                <form id="deleteForm" action="supply_receive-actions.php?action=del" method="post">
                    <input type="hidden" name="id" id="delete_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>




<!-- Modal -->


<?php require_once __DIR__ . '/../partials/scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>

<script>
$(document).ready(function() {
    // Existing DataTable code
    new DataTable('#dtable', {
        searching: true,
        lengthChange: false,
        info: true,
        paging: true,
        autoWidth: false,
        "columnDefs": [{
            "orderable": false,
            "targets": [0, 4]
        }]
    });

    $('#exportOptions').on('change', function() {
        var format = $(this).val();
        if (format) {
            window.location.href = `export.php?format=${format}`;
        }
    });

    const isSupplyOfficerView = <?= $isSupplyOfficerView ? 'true' : 'false' ?>;
    const supplyOfficerUserId = <?= json_encode($currentUserId) ?>;
    const supplyOfficerUserName = <?= json_encode($currentUserName) ?>;

    function lockSingleUserSelect(selector) {
        if (!isSupplyOfficerView || !supplyOfficerUserId) {
            return;
        }

        const $select = $(selector);
        if (!$select.length) {
            return;
        }

        const originalName = $select.attr('name');
        if (originalName) {
            const marker = selector.replace(/[^a-zA-Z0-9_-]/g, '_');
            let $hidden = $select.siblings('input[type="hidden"][data-lock-target="' + marker + '"]');
            if (!$hidden.length) {
                $hidden = $('<input>', {
                    type: 'hidden',
                    name: originalName,
                    value: supplyOfficerUserId,
                    'data-lock-target': marker
                });
                $select.after($hidden);
            } else {
                $hidden.val(supplyOfficerUserId);
            }
            $select.removeAttr('name');
        }

        if (!$select.find('option[value="' + supplyOfficerUserId + '"]').length) {
            $select.append(new Option(supplyOfficerUserName, supplyOfficerUserId, true, true));
        }

        $select.val(supplyOfficerUserId).trigger('change');
        $select.prop('disabled', true);
    }

    lockSingleUserSelect('#received_by');
    lockSingleUserSelect('#edit_received_by');

    function applyReceiptPrefix($receiptInput, mode) {
        const currentValue = ($receiptInput.val() || '').trim();
        if (!currentValue || !mode) {
            return;
        }

        const core = currentValue.replace(/^(DR|SI)[-\\s]*/i, '').trim();
        $receiptInput.val(mode + (core ? '-' + core : ''));
    }

    function setupDeliveryMode(drSelector, siSelector, receiptSelector) {
        const $dr = $(drSelector);
        const $si = $(siSelector);
        const $receipt = $(receiptSelector);

        if (!$dr.length || !$si.length || !$receipt.length) {
            return;
        }

        function enforce(source) {
            const drVal = ($dr.val() || '').trim();
            const siVal = ($si.val() || '').trim();

            if (source === 'dr' && drVal !== '') {
                $si.val('');
            } else if (source === 'si' && siVal !== '') {
                $dr.val('');
            }

            const hasDr = ($dr.val() || '').trim() !== '';
            const hasSi = ($si.val() || '').trim() !== '';

            if (hasDr) {
                $dr.prop('disabled', false);
                $si.prop('disabled', true);
                $receipt.attr('placeholder', 'DR-XXXXX');
                applyReceiptPrefix($receipt, 'DR');
            } else if (hasSi) {
                $si.prop('disabled', false);
                $dr.prop('disabled', true);
                $receipt.attr('placeholder', 'SI-XXXXX');
                applyReceiptPrefix($receipt, 'SI');
            } else {
                $dr.prop('disabled', false);
                $si.prop('disabled', false);
                $receipt.attr('placeholder', 'DR-XXXXX or SI-XXXXX');
            }
        }

        $dr.on('input change', function() { enforce('dr'); });
        $si.on('input change', function() { enforce('si'); });
        $receipt.on('blur', function() { enforce('receipt'); });

        enforce('init');
    }

    setupDeliveryMode('#dr_no', '#si_no', '#receipt_no');
    setupDeliveryMode('#edit_dr_no', '#edit_si_no', '#edit_receipt_no');

    // Save new user
    $('#saveBtn').click(function() {
        $('#addForm').submit();
    });

    // Edit user button click - USE EVENT DELEGATION
    $(document).on('click', '.edit-btn', function() {
        const Id = $(this).data('id');

        $.getJSON(baseUrl+"/api/admin.php", {
            action: 'getSupplyReceiveHeaderById',
            id: Id
        }, function(res) {
            if (res.error) {
                alert(res.error);
                return;
            }

            // Populate the edit form with user data
            $('#edit_id').val(res.id);
            $('#edit_receipt_no').val(res.receipt_no);
            $('#edit_receipt_date').val(res.receipt_date);
            $('#edit_pr_no').val(res.pr_no ?? '');
            $('#edit_po_no').val(res.po_no ?? '');
            $('#edit_dr_no').val(res.dr_no ?? '');
            $('#edit_si_no').val(res.si_no ?? '');
            $('#edit_iar_no').val(res.iar_no ?? '');
            $('#edit_supplier_id').val(res.supplier_id).trigger('change');
            if (isSupplyOfficerView) {
                lockSingleUserSelect('#edit_received_by');
            } else {
                $('#edit_received_by').val(res.received_by).trigger('change');
            }
            $('#edit_inspected_by').val(res.inspected_by).trigger('change');
            $('#edit_notes').val(res.notes);

            if ((res.si_no ?? '').trim() !== '') {
                $('#edit_si_no').trigger('change');
            } else {
                $('#edit_dr_no').trigger('change');
            }

            $('#editModal').modal('show');
        });
    });

    // Update user
    $('#updateBtn').click(function() {
        $('#editForm').submit();
    });

    // Delete user button click - USE EVENT DELEGATION
    $(document).on('click', '.delete-btn', function() {
        const Id = $(this).data('id');
        $('#delete_id').val(Id);
        $('#deleteModal').modal('show');
    });

    // Confirm delete
    $('#confirmDeleteBtn').click(function() {
        $('#deleteForm').submit();
    });
});
</script>

