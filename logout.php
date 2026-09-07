<?php
require_once __DIR__ . '/core/helper.php';

// Ensure session is started by config.php
// We'll avoid using session flash since session will be destroyed.

// Clear and destroy session safely
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
$logoutUserId = $_SESSION['user']['id'] ?? null;
$logoutEmail = $_SESSION['user']['email'] ?? null;
if ($logoutUserId !== null || $logoutEmail !== null) {
	writeAuditLog('auth.logout', 'user', $logoutUserId, [
		'email' => $logoutEmail,
	]);
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Redirect with a one-time query flag for client-side toast
header('Location: login.php?logout=1');
exit;
