<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/User.model.php';

$iUser = new User;
$postadd = isPost() && action() === 'add';
$postupdate = isPost() && action() === 'update';
$postdelete = isPost() && action() === 'del';

if ($postadd) {

    $firstname = $_POST['firstname'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $department = $_POST['departmentid'] ?? '';
    $role = $_POST['roleid'] ?? '';
    $email = $_POST['email'] ?? '';
    $status = $_POST['status'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmpassword'] ?? '';

    if ($iUser::isExist($email)) {
        writeAuditLog('user.create.denied', 'user', null, [
            'email' => $email,
            'reason' => 'duplicate_email'
        ]);
        flash('danger', 'Email already exists');
        redirect('users.php');
        exit;
    }

    if (!isDomainAllowed($email)) {
        writeAuditLog('user.create.denied', 'user', null, [
            'email' => $email,
            'reason' => 'invalid_domain'
        ]);
        flash('error', 'Invalid email domain. Please use a valid email address.');
        redirect('users.php');
        exit;
    }

    $allowedStatus = ['Active', 'Inactive', 'Pending', 'Suspended'];
    if (!in_array($status, $allowedStatus, true)) {
        writeAuditLog('user.create.denied', 'user', null, [
            'email' => $email,
            'status' => $status,
            'reason' => 'invalid_status'
        ]);
        flash('error', 'Invalid status value.');
        redirect('users.php');
        exit;
    }

    if ($password !== $confirmPassword) {
        writeAuditLog('user.create.denied', 'user', null, [
            'email' => $email,
            'reason' => 'password_mismatch'
        ]);
        flash('error', 'Password and confirm password do not match!');
        redirect('users.php');
        exit;
    }

    $res = $iUser::add($firstname, $lastname, $department, $role, $email, $password, $status);
    if ($res) {
        writeAuditLog('user.create.success', 'user', (int)$res, [
            'email' => $email,
            'roleid' => $role,
            'departmentid' => $department,
            'status' => $status
        ]);
        flash('success', 'User added successfully');
    } else {
        writeAuditLog('user.create.failed', 'user', null, [
            'email' => $email,
            'roleid' => $role,
            'departmentid' => $department,
            'status' => $status
        ]);
        flash('danger', 'Failed to add user');
    }
    redirect('users.php');
    exit;
}

if ($postupdate) {

    $userid = $_POST['userid'] ?? '';
    $firstname = $_POST['firstname'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['roleid'] ?? '';
    $department = $_POST['departmentid'] ?? '';
    $status = $_POST['status'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmpassword'] ?? '';

    if (empty($userid)) {
        writeAuditLog('user.update.denied', 'user', null, [
            'reason' => 'missing_user_id'
        ]);
        flash('danger', 'User ID is required for update');
        redirect('users.php');
        exit;
    }

    $currentUser = $iUser->getById($userid);
    if (!$currentUser) {
        writeAuditLog('user.update.denied', 'user', (int)$userid, [
            'reason' => 'user_not_found'
        ]);
        flash('danger', 'User not found');
        redirect('users.php');
        exit;
    }

    if (!isDomainAllowed($email)) {
        writeAuditLog('user.update.denied', 'user', (int)$userid, [
            'email' => $email,
            'reason' => 'invalid_domain'
        ]);
        flash('error', 'Invalid email domain. Please use a valid email address.');
        redirect('users.php');
        exit;
    }

    $existingUser = $iUser->getByEmail($email);
    if ($existingUser && (string)$existingUser['id'] !== (string)$userid) {
        writeAuditLog('user.update.denied', 'user', (int)$userid, [
            'email' => $email,
            'reason' => 'duplicate_email'
        ]);
        flash('danger', 'Email already exists');
        redirect('users.php');
        exit;
    }

    $allowedStatus = ['Active', 'Inactive', 'Pending', 'Suspended'];
    if (!in_array($status, $allowedStatus, true)) {
        writeAuditLog('user.update.denied', 'user', (int)$userid, [
            'status' => $status,
            'reason' => 'invalid_status'
        ]);
        flash('error', 'Invalid status value.');
        redirect('users.php');
        exit;
    }

    $passwordChanged = !empty($password);
    if (!empty($password)) {
        if ($password !== $confirmPassword) {
            writeAuditLog('user.update.denied', 'user', (int)$userid, [
                'reason' => 'password_mismatch'
            ]);
            flash('danger', 'Password and confirm password do not match');
            redirect('users.php');
            exit;
        }
    } else {
        $password = null;
    }

    $changes = [];
    if ($firstname !== ($currentUser['firstname'] ?? '')) {
        $changes['firstname'] = [
            'old' => $currentUser['firstname'] ?? null,
            'new' => $firstname
        ];
    }
    if ($lastname !== ($currentUser['lastname'] ?? '')) {
        $changes['lastname'] = [
            'old' => $currentUser['lastname'] ?? null,
            'new' => $lastname
        ];
    }
    if ($email !== ($currentUser['email'] ?? '')) {
        $changes['email'] = [
            'old' => $currentUser['email'] ?? null,
            'new' => $email
        ];
    }
    if ($role !== (string)($currentUser['roleid'] ?? '')) {
        $changes['roleid'] = [
            'old' => $currentUser['roleid'] ?? null,
            'new' => $role
        ];
    }
    if ($department !== (string)($currentUser['departmentid'] ?? '')) {
        $changes['departmentid'] = [
            'old' => $currentUser['departmentid'] ?? null,
            'new' => $department
        ];
    }
    if ($status !== ($currentUser['status'] ?? '')) {
        $changes['status'] = [
            'old' => $currentUser['status'] ?? null,
            'new' => $status
        ];
    }
    if ($passwordChanged) {
        $changes['password'] = [
            'old' => null,
            'new' => '[updated]'
        ];
    }

    $updatedFields = array_keys($changes);

    $res = User::update($firstname, $lastname, $department, $role, $email, $password, $status, $userid);

    if ($res) {
        writeAuditLog('user.update.success', 'user', (int)$userid, [
            'email' => $email,
            'roleid' => $role,
            'departmentid' => $department,
            'status' => $status,
            'updated_fields' => $updatedFields,
            'password_reset' => $passwordChanged,
            'changes' => $changes
        ]);
        flash('success', 'User updated successfully');
    } else {
        writeAuditLog('user.update.failed', 'user', (int)$userid, [
            'email' => $email,
            'roleid' => $role,
            'departmentid' => $department,
            'status' => $status,
            'updated_fields' => $updatedFields,
            'password_reset' => $passwordChanged,
            'changes' => $changes
        ]);
        flash('danger', 'Failed to update user');
    }

    redirect('users.php');
    exit;
}

if ($postdelete) {

    $userid = $_POST['userid'] ?? '';

    if (empty($userid)) {
        writeAuditLog('user.delete.denied', 'user', null, [
            'reason' => 'missing_user_id'
        ]);
        flash('danger', 'User ID is required for deletion');
        redirect('users.php');
        exit;
    }

    $targetUser = $iUser->getById($userid);
    if (!$targetUser) {
        writeAuditLog('user.delete.denied', 'user', (int)$userid, [
            'reason' => 'user_not_found'
        ]);
        flash('danger', 'User not found');
        redirect('users.php');
        exit;
    }

    $result = $iUser->deleteById($userid);

    if ($result) {
        writeAuditLog('user.delete.success', 'user', (int)$userid, [
            'email' => $targetUser['email'] ?? null,
            'status' => $targetUser['status'] ?? null,
            'departmentid' => $targetUser['departmentid'] ?? null
        ]);
        flash('success', 'User deleted successfully');
    } else {
        writeAuditLog('user.delete.failed', 'user', (int)$userid, [
            'email' => $targetUser['email'] ?? null,
            'status' => $targetUser['status'] ?? null,
            'departmentid' => $targetUser['departmentid'] ?? null
        ]);
        flash('danger', 'Failed to delete user');
    }
    redirect('users.php');
    exit;
}

?>
