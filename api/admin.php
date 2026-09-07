<?php

require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../repo/User.model.php';
require_once __DIR__ . '/../repo/Role.model.php';
require_once __DIR__ . '/../repo/Department.model.php';
require_once __DIR__ . '/../repo/Supplier.model.php';
require_once __DIR__ . '/../repo/Item.model.php';
require_once __DIR__ . '/../repo/Supply_Receive.model.php';
require_once __DIR__ . '/../repo/StockInventory.model.php';
require_once __DIR__ . '/../repo/SupplyLedger.model.php';
require_once __DIR__ . '/../repo/Supply_Issuance.model.php';
require_once __DIR__ . '/../repo/RIS.model.php';
require_once __DIR__ . '/../repo/ICS.model.php';
require_once __DIR__ . '/../repo/PAR.model.php';


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

/**
 * Enforce API action access by role ID.
 */
function apiGuard(array $allowedRoleIds): void
{
    $roleId = (int)(getUser('roleid') ?? 0);
    if (!in_array($roleId, $allowedRoleIds, true)) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

$actionName = (string)action();

if ($actionName !== '') {
    $actionRoleMatrix = [
        // User/system lookups
        'getAllDepartments' => [1, 2, 3, 4, 5],
        'getAllRoles' => [1],
        'getAllUsers' => [1],
        'getUserById' => [1],

        // Supply officer scope
        'getSupplierById' => [1, 2],
        'getSupplyReceiveHeaderById' => [1, 2],
        'addReceiveItem' => [1, 2],
        'delReceiveItem' => [1, 2],
        'getAllReceiveItems' => [1, 2],
        'getAllRIS' => [1, 2],
        'getRISById' => [1, 2],
        'getIssuanceById' => [1, 2],

        // Shared module lookups
        'getItemById' => [1, 2, 3, 4, 5],
        'getICSById' => [1, 4],
        'getPARById' => [1, 4],
    ];

    $allowedRoles = $actionRoleMatrix[$actionName] ?? [1];
    apiGuard($allowedRoles);
}


if(GETACT('getAllDepartments')) {
    $iDepartment = new Department;
    $departments = $iDepartment->getAll();
    echo json_encode($departments);
    exit;
}

if(GETACT('getAllRoles')) {
    $iRole = new Role;
    $roles = $iRole->getAll();
    echo json_encode($roles);
    exit;
}

if(GETACT('getAllUsers')) {
    $iBase = new BaseModel("user_role_dept");
    $users = $iBase->getAll();
    echo json_encode($users);
    exit;
}

if(GETACT('getUserById')) {
    
    $userId = $_GET['id'] ?? '';
    if(empty($userId)) {
        echo json_encode(['error' => 'User ID is required']);
        exit;
    }

    $iUser = new User;
    $user = $iUser->getById($userId);
    if(!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    echo json_encode($user);
    exit;
}

if(GETACT('getSupplierById')) {
    
    $supplierId = $_GET['id'] ?? '';
    if(empty($supplierId)) {
        echo json_encode(['error' => 'Supplier ID is required']);
        exit;
    }

    $iSupplier = new Supplier;
    $supplier = $iSupplier->getById($supplierId);
    if(!$supplier) {
        echo json_encode(['error' => 'Supplier not found']);
        exit;
    }
    echo json_encode($supplier);
    exit;
}

if(GETACT('getItemById')) {
    
    $itemId = $_GET['id'] ?? '';
    if(empty($itemId)) {
        echo json_encode(['error' => 'Item ID is required']);
        exit;
    }

    $iItem = new Item;
    $item = $iItem->getById($itemId);
    if(!$item) {
        echo json_encode(['error' => 'Item not found']);
        exit;
    }
    echo json_encode($item);
    exit;
}

if(GETACT('getSupplyReceiveHeaderById')) {
    
    $supplyReceiveId = $_GET['id'] ?? '';
    if(empty($supplyReceiveId)) {
        echo json_encode(['error' => 'Supply Receive ID is required']);
        exit;
    }

    $iSupplyReceive = new Supply_Receive_Header;
    $supplyReceive = $iSupplyReceive->getById($supplyReceiveId);
    if(!$supplyReceive) {
        echo json_encode(['error' => 'Supply Receive not found']);
        exit;
    }
    echo json_encode($supplyReceive);
    exit;
}

if(GETACT('getIssuanceById')) {
    $issuanceId = $_GET['id'] ?? '';
    if (empty($issuanceId)) {
        echo json_encode(['error' => 'Issuance ID is required']);
        exit;
    }

    $issuanceModel = new Supply_Issuance();
    $issuance = $issuanceModel->getById($issuanceId);
    if (!$issuance) {
        echo json_encode(['error' => 'Issuance not found']);
        exit;
    }

    $risModel = new RIS();
    $ris = $risModel->getById($issuance['ris_id']);
    if ($ris) {
        $issuance['ris_no'] = $ris['ris_no'];
    } else {
        $issuance['ris_no'] = '';
    }

    echo json_encode($issuance);
    exit;
}

if(GETACT('getICSById')) {
    
    $icsId = $_GET['id'] ?? '';
    if (empty($icsId)) {
        echo json_encode(['error' => 'ICS ID is required']);
        exit;
    }

    $icsModel = new ICS();
    $ics = $icsModel->getById($icsId);
    if (!$ics) {
        echo json_encode(['error' => 'ICS not found']);
        exit;
    }

    echo json_encode($ics);
    exit;
}

if(GETACT('getPARById')) {
    
    $parId = $_GET['id'] ?? '';
    if (empty($parId)) {
        echo json_encode(['error' => 'PAR ID is required']);
        exit;
    }

    $parModel = new PAR();
    $par = $parModel->getById($parId);
    if (!$par) {
        echo json_encode(['error' => 'PAR not found']);
        exit;
    }

    echo json_encode($par);
    exit;
}

if(POSTACT('addReceiveItem')) {
    //receipt_header_id, item_id, qty_received, unit_cost, batch_no, expiry_date, remarks
    $receiptHeaderId = $_POST['receipt_header_id'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $qtyReceived = $_POST['qty_received'] ?? '';
    $unitCost = $_POST['unit_cost'] ?? '';
    $batchNo = $_POST['batch_no'] ?? '';
    $expiryDate = $_POST['expiry_date'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if(empty($receiptHeaderId) || empty($itemId) || empty($qtyReceived) || empty($unitCost) || empty($batchNo)) {
        echo json_encode(['error' => 'Receipt Header ID, Item ID, Quantity Received, Unit Cost, Batch No are required']);
        exit;
    }

    if(!is_numeric($qtyReceived) || $qtyReceived <= 0) {
        echo json_encode(['error' => 'Quantity Received must be greater than zero']);
        exit;
    }

    if(!is_numeric($unitCost) || $unitCost <= 0) {
        echo json_encode(['error' => 'Unit Cost must be greater than zero']);
        exit;
    }

    $headerModel = new Supply_Receive_Header();
    $receiptHeader = $headerModel->getById($receiptHeaderId);
    if (!$receiptHeader) {
        echo json_encode(['error' => 'Receipt header not found']);
        exit;
    }

    $iSupplyReceiveItems = new Supply_Receive_Items;
    $res = $iSupplyReceiveItems->add($receiptHeaderId, $itemId, $qtyReceived, $unitCost, $batchNo, $expiryDate, $remarks);
    if ($res) {
        $iItem = new Item;
        $iItem->addQty((int)$itemId, (int)$qtyReceived);

        $headerDate = $receiptHeader['receipt_date'] ?? date('Y-m-d');
        $referenceNo = $receiptHeader['receipt_no'] ?? '';

        StockInventory::recordMovement(
            (int)$itemId,
            (int)$qtyReceived,
            0,
            'Supply Receipt',
            (int)$receiptHeaderId,
            $referenceNo,
            $remarks,
            $headerDate
        );

        Supply_Ledger_Entry::recordMovement(
            (int)$itemId,
            $headerDate,
            'Supply Receipt',
            (int)$receiptHeaderId,
            $referenceNo,
            (int)$qtyReceived,
            0,
            (float)$unitCost,
            $remarks,
            (int)(getUser('id') ?? 0)
        );

        writeAuditLog('supply.receive.item.create.success', 'supply_receipt_items', (int)$res, [
            'channel' => 'api',
            'receipt_header_id' => (int)$receiptHeaderId,
            'reference_no' => $referenceNo,
        ], [
            'item_id' => ['old' => null, 'new' => $itemId],
            'qty_received' => ['old' => null, 'new' => $qtyReceived],
            'unit_cost' => ['old' => null, 'new' => $unitCost],
            'batch_no' => ['old' => null, 'new' => $batchNo],
        ]);

        echo json_encode(['success' => 'Item added successfully']);
    } else {
        writeAuditLog('supply.receive.item.create.failed', 'supply_receipt_items', null, [
            'channel' => 'api',
            'reason' => 'database',
            'receipt_header_id' => (int)$receiptHeaderId,
            'item_id' => (int)$itemId,
        ]);
        echo json_encode(['error' => 'Failed to add item']);
    }
    exit;
}

if(POSTACT('delReceiveItem')) {
    
    $Id = $_POST['id'] ?? '';
    
    if(empty($Id)) {
        echo json_encode(['error' => 'Item ID is required']);
        exit;
    }

    $receiptHeaderId = (int)($_POST['receipt_header_id'] ?? 0);

    $iSupplyReceiveItems = new Supply_Receive_Items;
    $itemDetails = $iSupplyReceiveItems->getById($Id);
    if (!$itemDetails) {
        echo json_encode(['error' => 'Receive item not found']);
        exit;
    }

    if ($receiptHeaderId <= 0) {
        $receiptHeaderId = (int)($itemDetails['receipt_header_id'] ?? 0);
    }

    $res = $iSupplyReceiveItems->deleteById($Id);

    if($res) {
        $iItem = new Item;
        $iItem->reduceQty((int)$itemDetails['item_id'], (int)$itemDetails['qty_received']);

        $headerModel = new Supply_Receive_Header();
        $receiptHeader = $headerModel->getById($receiptHeaderId);
        $headerDate = $receiptHeader['receipt_date'] ?? date('Y-m-d');
        $referenceNo = $receiptHeader['receipt_no'] ?? '';

        StockInventory::recordMovement(
            (int)$itemDetails['item_id'],
            0,
            (int)$itemDetails['qty_received'],
            'Supply Receipt Reversal',
            (int)$receiptHeaderId,
            $referenceNo,
            $itemDetails['remarks'] ?? '',
            $headerDate
        );

        Supply_Ledger_Entry::recordMovement(
            (int)$itemDetails['item_id'],
            $headerDate,
            'Supply Receipt Reversal',
            (int)$receiptHeaderId,
            $referenceNo,
            0,
            (int)$itemDetails['qty_received'],
            (float)($itemDetails['unit_cost'] ?? 0),
            $itemDetails['remarks'] ?? '',
            (int)(getUser('id') ?? 0)
        );

        writeAuditLog('supply.receive.item.delete.success', 'supply_receipt_items', (int)$Id, [
            'channel' => 'api',
            'receipt_header_id' => (int)$receiptHeaderId,
            'reference_no' => $referenceNo,
        ], [
            'item_id' => ['old' => $itemDetails['item_id'] ?? null, 'new' => null],
            'qty_received' => ['old' => $itemDetails['qty_received'] ?? null, 'new' => null],
            'unit_cost' => ['old' => $itemDetails['unit_cost'] ?? null, 'new' => null],
            'batch_no' => ['old' => $itemDetails['batch_no'] ?? null, 'new' => null],
        ]);

        echo json_encode(['success' => 'Item deleted successfully']);
    } else {
        writeAuditLog('supply.receive.item.delete.failed', 'supply_receipt_items', (int)$Id, [
            'channel' => 'api',
            'reason' => 'database',
            'receipt_header_id' => (int)$receiptHeaderId,
        ]);
        echo json_encode(['error' => 'Failed to delete item']);
    }
    exit;
}

if(GETACT('getAllReceiveItems')) {
    $receipt_header_id = $_GET['receipt_header_id'] ?? '';

    if(empty($receipt_header_id)) {
        echo json_encode(['error' => 'Receipt Header ID is required']);
        exit;
    }

    $iRef1 = new BaseModel("items");
    $Items = $iRef1->getAll();

    $iReceiveItems = new Supply_Receive_Items;
    $receiveItems = $iReceiveItems->getAll($receipt_header_id);

    foreach($receiveItems as &$r) {
        $ritem = SearchById($Items, $r['item_id']);
        $r['item_code'] = $ritem['code'] ?? '';
        $r['item_description'] = $ritem['description'] ?? '';

        if(isset($r['expiry_date']) && $r['expiry_date'] != null) {
            $r['expiry_date'] = DisplayDate($r['expiry_date']);
        } else {
            $r['expiry_date'] = '';
        }

        if($r['remarks'] == null) {
            $r['remarks'] = '';
        }

        if(isset($r['unit_cost'])) {
            $r['unit_cost'] = number_format((float)$r['unit_cost'], 2, '.', '');
        }
    }
    unset($r); // break reference

    echo json_encode($receiveItems);
    exit;
}

if(GETACT('getAllRIS')) {
    
    $iRIS = new RIS;
    $risList = $iRIS->getAll();
    
    // foreach($risList as &$r) {
    //     $r['requisition_date'] = DisplayDate($r['requisition_date']);
    // }
    unset($r); // break reference

    echo json_encode($risList);
    exit;
}


if(GETACT('getRISById')) {
    
    $ris_id = $_GET['id'] ?? '';

    if(empty($ris_id)) {
        echo json_encode(['error' => 'RIS No is required']);
        exit;
    }
    
    $iRIS = new RIS;
    $list = $iRIS->getById($ris_id);
    if(!$list) {
        echo json_encode(['error' => 'RIS not found']);
        exit;
    }
    
    echo json_encode($list);
    exit;
}
