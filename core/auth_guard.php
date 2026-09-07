<?php

require_once __DIR__.'/helper.php';

// Basic auth guard: require login
if (!isLoggedIn()) {
	flash('error', 'You must be logged in to access this page.');
	redirect(BASE_URL.'login.php');
}

// Optional role guard helper usage example:
// guardRole([1,2]) to allow Admin and Supply Officer
function guardRole(array $allowedRoleIds) {
	if (!isLoggedIn()) {
		redirect(BASE_URL.'login.php');
	}
	$roleId = $_SESSION['user']['roleid'] ?? null;
	if (!in_array($roleId, $allowedRoleIds, true)) {
		redirect(BASE_URL.'unathorized.php');
	}
}


