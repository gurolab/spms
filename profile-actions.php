<?php

require_once __DIR__ . '/core/helper.php';
require_once __DIR__ . '/core/csrf.php';
require_once __DIR__ . '/core/BaseModel.php';
require_once __DIR__ . '/repo/Account.model.php';
require_once __DIR__ . '/repo/User.model.php';

if (!isLoggedIn()) {
    flash('error', 'You must be logged in to manage your profile.');
    redirect('login.php');
}

if (!isPost()) {
    flash('error', 'Unsupported request method.');
    redirect('profile.php');
}

$action = action();

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid request token. Please try again.');
    redirect('profile.php');
}

$userId = (int)(getUser('id') ?? 0);
if ($userId <= 0) {
    flash('error', 'Unable to determine the current user.');
    redirect('login.php');
}

new Account();

if ($action === 'update_profile') {
    $firstname = trim((string)($_POST['firstname'] ?? ''));
    $lastname = trim((string)($_POST['lastname'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));

    if (!isValidLength($firstname, 2, 150)) {
        flash('danger', 'First name must be between 2 and 150 characters.');
        redirect('profile.php');
    }

    if (!isValidLength($lastname, 2, 150)) {
        flash('danger', 'Last name must be between 2 and 150 characters.');
        redirect('profile.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Please provide a valid email address.');
        redirect('profile.php');
    }

    if (!isDomainAllowed($email)) {
        flash('danger', 'Please use an approved email domain.');
        redirect('profile.php');
    }

    $currentAccount = Account::findById($userId);
    if (!$currentAccount) {
        flash('danger', 'Account was not found. Please contact support.');
        redirect('profile.php');
    }

    $changes = [];
    if ($firstname !== ($currentAccount['firstname'] ?? '')) {
        $changes['firstname'] = [
            'old' => $currentAccount['firstname'] ?? null,
            'new' => $firstname,
        ];
    }
    if ($lastname !== ($currentAccount['lastname'] ?? '')) {
        $changes['lastname'] = [
            'old' => $currentAccount['lastname'] ?? null,
            'new' => $lastname,
        ];
    }
    if (strcasecmp($email, (string)($currentAccount['email'] ?? '')) !== 0) {
        $changes['email'] = [
            'old' => $currentAccount['email'] ?? null,
            'new' => $email,
        ];
    }

    if (empty($changes)) {
        flash('info', 'No changes detected.');
        redirect('profile.php');
    }

    $existing = Account::getByEmail($email);
    if ($existing && (int)$existing['id'] !== $userId) {
        flash('danger', 'Another user already uses this email address.');
        redirect('profile.php');
    }

    $updated = Account::updateBasicInfo($userId, $firstname, $lastname, $email);

    if ($updated) {
        $viewRepo = new BaseModel('user_role_dept');
        $refreshed = $viewRepo->getById($userId);

        $_SESSION['user']['firstname'] = $firstname;
        $_SESSION['user']['lastname'] = $lastname;
        $_SESSION['user']['email'] = $email;

        if ($refreshed) {
            $_SESSION['user']['roleid'] = $refreshed['roleid'] ?? $_SESSION['user']['roleid'] ?? null;
            $_SESSION['user']['departmentid'] = $refreshed['departmentid'] ?? $_SESSION['user']['departmentid'] ?? null;
            $_SESSION['user']['rolename'] = $refreshed['rolename'] ?? $_SESSION['user']['rolename'] ?? null;
            $_SESSION['user']['departmentname'] = $refreshed['departmentcode'] ?? $_SESSION['user']['departmentname'] ?? null;
            $_SESSION['user']['department_fullname'] = $refreshed['departmentname'] ?? $_SESSION['user']['department_fullname'] ?? null;
            $_SESSION['user']['status'] = $refreshed['status'] ?? $_SESSION['user']['status'] ?? null;
        }

        writeAuditLog('profile.update.success', 'user', $userId, ['context' => 'self_service'], $changes);
        flash('success', 'Profile updated successfully.');
    } else {
        writeAuditLog('profile.update.failed', 'user', $userId, ['context' => 'self_service'], $changes);
        flash('danger', 'Failed to update profile. Please try again later.');
    }

    redirect('profile.php');
}

if ($action === 'update_password') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        flash('danger', 'All password fields are required.');
        redirect('profile.php');
    }

    if (!isValidLength($newPassword, 6, 30)) {
        flash('danger', 'New password must be between 6 and 30 characters.');
        redirect('profile.php');
    }

    if ($newPassword !== $confirmPassword) {
        flash('danger', 'New password and confirmation do not match.');
        redirect('profile.php');
    }

    if (!Account::verifyPassword($userId, $currentPassword)) {
        writeAuditLog('profile.password.invalid_current', 'user', $userId, ['context' => 'self_service']);
        flash('danger', 'Current password is incorrect.');
        redirect('profile.php');
    }

    if (Account::verifyPassword($userId, $newPassword)) {
        flash('danger', 'New password must be different from the current password.');
        redirect('profile.php');
    }

    $updated = Account::updatePassword($newPassword, $userId);

    if ($updated) {
        writeAuditLog('profile.password.change', 'user', $userId, [
            'context' => 'self_service',
            'password_changed' => true,
        ]);
        flash('success', 'Password updated successfully.');
    } else {
        writeAuditLog('profile.password.change_failed', 'user', $userId, ['context' => 'self_service']);
        flash('danger', 'Failed to update password. Please try again later.');
    }

    redirect('profile.php');
}

flash('error', 'Unsupported profile action.');
redirect('profile.php');
