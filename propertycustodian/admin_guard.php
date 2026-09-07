<?php

require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/csrf.php';

const ROLE_SYSTEM_ADMIN = 1;
const ROLE_PROPERTY_CUSTODIAN = 4;

guardRole([ROLE_SYSTEM_ADMIN, ROLE_PROPERTY_CUSTODIAN]);

if (isPost() && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    flash('danger', 'Invalid request. Please try again.');
    $redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    redirect($redirect);
}
