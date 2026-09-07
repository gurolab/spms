<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/Supplier.model.php';

$supplierModel = new Supplier();

function validateSupplierPayload(array $payload): array
{
    $name = trim((string)($payload['company'] ?? ''));
    $contactPerson = trim((string)($payload['contactperson'] ?? ''));
    $contactNumberRaw = trim((string)($payload['contactnumber'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $address = trim((string)($payload['address'] ?? ''));
    $tin = trim((string)($payload['tin'] ?? ''));

    if ($name === '' || $contactPerson === '' || $contactNumberRaw === '' || $email === '' || $address === '' || $tin === '') {
        return [null, 'All fields are required.'];
    }

    if (mb_strlen($name) > 250) {
        return [null, 'Company name must be 250 characters or less.'];
    }

    if (mb_strlen($contactPerson) > 150) {
        return [null, 'Contact person must be 150 characters or less.'];
    }

    $contactNumber = preg_replace('/\D+/', '', $contactNumberRaw);
    if ($contactNumber === '' || strlen($contactNumber) > 11) {
        return [null, 'Contact number must contain up to 11 digits.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        return [null, 'Please provide a valid email address (max 150 characters).'];
    }

    if (mb_strlen($address) > 250) {
        return [null, 'Address must be 250 characters or less.'];
    }

    if (mb_strlen($tin) > 20) {
        return [null, 'TIN must be 20 characters or less.'];
    }

    return [[
        'name' => $name,
        'contact_person' => $contactPerson,
        'contact_number' => $contactNumber,
        'email' => $email,
        'address' => $address,
        'tin' => $tin,
    ], null];
}

if (isPost() && action() === 'add') {
    [$input, $validationError] = validateSupplierPayload($_POST);
    if ($validationError !== null) {
        writeAuditLog('supplier.add.failed', 'supplier', null, [
            'module' => 'administrator',
            'reason' => 'validation',
            'message' => $validationError,
        ]);
        flash('danger', $validationError);
        redirect('suppliers.php');
    }

    try {
        $newId = Supplier::add(
            $input['name'],
            $input['contact_person'],
            $input['contact_number'],
            $input['email'],
            $input['address'],
            $input['tin']
        );

        if ($newId) {
            writeAuditLog('supplier.add.success', 'supplier', (int)$newId, [
                'module' => 'administrator',
            ], [
                'name' => ['old' => null, 'new' => $input['name']],
                'contact_person' => ['old' => null, 'new' => $input['contact_person']],
                'contact_number' => ['old' => null, 'new' => $input['contact_number']],
                'email' => ['old' => null, 'new' => $input['email']],
                'address' => ['old' => null, 'new' => $input['address']],
                'tin' => ['old' => null, 'new' => $input['tin']],
            ]);
            flash('success', 'Supplier added successfully.');
        } else {
            writeAuditLog('supplier.add.failed', 'supplier', null, [
                'module' => 'administrator',
                'reason' => 'database',
            ]);
            flash('danger', 'Failed to add supplier.');
        }
    } catch (Throwable $e) {
        error_log('supplier add failed: ' . $e->getMessage());
        writeAuditLog('supplier.add.failed', 'supplier', null, [
            'module' => 'administrator',
            'reason' => 'exception',
        ]);
        flash('danger', 'Failed to add supplier. Please verify field lengths and try again.');
    }

    redirect('suppliers.php');
}

if (isPost() && action() === 'update') {
    $supplierId = (int)($_POST['supplierid'] ?? 0);
    if ($supplierId <= 0) {
        flash('danger', 'Supplier ID is required for update.');
        redirect('suppliers.php');
    }

    $existing = $supplierModel->getById($supplierId);
    if (!$existing) {
        writeAuditLog('supplier.update.failed', 'supplier', $supplierId, [
            'module' => 'administrator',
            'reason' => 'not_found',
        ]);
        flash('danger', 'Supplier not found.');
        redirect('suppliers.php');
    }

    [$input, $validationError] = validateSupplierPayload($_POST);
    if ($validationError !== null) {
        writeAuditLog('supplier.update.failed', 'supplier', $supplierId, [
            'module' => 'administrator',
            'reason' => 'validation',
            'message' => $validationError,
        ]);
        flash('danger', $validationError);
        redirect('suppliers.php');
    }

    $changes = [];
    foreach (['name', 'contact_person', 'contact_number', 'email', 'address', 'tin'] as $field) {
        $oldValue = (string)($existing[$field] ?? '');
        $newValue = (string)($input[$field] ?? '');
        if ($oldValue !== $newValue) {
            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }
    }

    if (empty($changes)) {
        writeAuditLog('supplier.update.no_changes', 'supplier', $supplierId, [
            'module' => 'administrator',
        ]);
        flash('info', 'No changes detected.');
        redirect('suppliers.php');
    }

    try {
        $result = $supplierModel->update(
            $input['name'],
            $input['contact_person'],
            $input['contact_number'],
            $input['email'],
            $input['address'],
            $input['tin'],
            $supplierId
        );

        if ($result) {
            writeAuditLog('supplier.update.success', 'supplier', $supplierId, [
                'module' => 'administrator',
            ], $changes);
            flash('success', 'Supplier updated successfully.');
        } else {
            writeAuditLog('supplier.update.failed', 'supplier', $supplierId, [
                'module' => 'administrator',
                'reason' => 'database',
            ], $changes);
            flash('danger', 'Failed to update supplier.');
        }
    } catch (Throwable $e) {
        error_log('supplier update failed: ' . $e->getMessage());
        writeAuditLog('supplier.update.failed', 'supplier', $supplierId, [
            'module' => 'administrator',
            'reason' => 'exception',
        ], $changes);
        flash('danger', 'Failed to update supplier. Please verify field lengths and try again.');
    }

    redirect('suppliers.php');
}

if (isPost() && action() === 'del') {
    $supplierId = (int)($_POST['supplierid'] ?? 0);

    if ($supplierId <= 0) {
        flash('danger', 'Supplier ID is required for deletion.');
        redirect('suppliers.php');
    }

    $existing = $supplierModel->getById($supplierId);

    try {
        $result = $supplierModel->deleteById($supplierId);

        if ($result) {
            writeAuditLog('supplier.delete.success', 'supplier', $supplierId, [
                'module' => 'administrator',
                'deleted_name' => $existing['name'] ?? null,
            ]);
            flash('success', 'Supplier deleted successfully.');
        } else {
            writeAuditLog('supplier.delete.failed', 'supplier', $supplierId, [
                'module' => 'administrator',
                'reason' => 'database',
            ]);
            flash('danger', 'Failed to delete supplier.');
        }
    } catch (Throwable $e) {
        error_log('supplier delete failed: ' . $e->getMessage());
        writeAuditLog('supplier.delete.failed', 'supplier', $supplierId, [
            'module' => 'administrator',
            'reason' => 'exception',
        ]);
        flash('danger', 'Failed to delete supplier.');
    }

    redirect('suppliers.php');
}

flash('danger', 'Unsupported supplier action.');
redirect('suppliers.php');

