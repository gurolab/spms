<?php

require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/csrf.php';

// Role IDs
const ROLE_SYSTEM_ADMIN = 1;
const ROLE_SUPPLY_OFFICER = 2;
const ROLE_INVENTORY_OFFICER = 3;
const ROLE_PROPERTY_CUSTODIAN = 4;
const ROLE_AUDITOR = 5;
const ROLE_EMPLOYEE = 6;

/**
 * Resolve the current script path relative to the administrator directory.
 */
function adminRelativeScriptPath(): string
{
	$scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
	$adminRoot = realpath(__DIR__);

	if (!$scriptFile || !$adminRoot) {
		return '';
	}

	$scriptReal = realpath($scriptFile) ?: $scriptFile;
	$adminPrefix = rtrim(str_replace('\\', '/', $adminRoot), '/') . '/';
	$scriptNormalized = str_replace('\\', '/', $scriptReal);

	if (strpos($scriptNormalized, $adminPrefix) !== 0) {
		return '';
	}

	return substr($scriptNormalized, strlen($adminPrefix));
}

/**
 * Access matrix by module file (relative to administrator/).
 * Modules can be strictly role-owned (for example, auditor compliance pages).
 */
function adminAccessMatrix(): array
{
	$admin = ROLE_SYSTEM_ADMIN;
	$supply = ROLE_SUPPLY_OFFICER;
	$inventory = ROLE_INVENTORY_OFFICER;
	$custodian = ROLE_PROPERTY_CUSTODIAN;
	$auditor = ROLE_AUDITOR;

	return [
		// System administration
		'admin_guard.php' => [$admin],
		'dashboard.php' => [$admin],
		'users.php' => [$admin],
		'user-actions.php' => [$admin],
		'roles.php' => [$admin],
		'departments.php' => [$admin, $supply, $inventory, $custodian, $auditor],
		'system_settings.php' => [$admin],
		'system_settings-actions.php' => [$admin],
		'sidebar.php' => [$admin],

		// Audit trails (scoped per role in AuditLog::scopeForRole).
		'audit_logs.php' => [$admin, $supply, $auditor],
		'audit_logs-actions.php' => [$admin, $supply, $auditor],

		// Supplier maintenance
		'suppliers.php' => [$admin, $supply],
		'supplier-actions.php' => [$admin, $supply],

		// Supply operations
		'supply_receive.php' => [$admin, $supply],
		'supply_receive_items.php' => [$admin, $supply],
		'supply_receive-actions.php' => [$admin, $supply],
		'supply_issuance.php' => [$admin, $supply],
		'supply_issuance_items.php' => [$admin, $supply],
		'supply_issuance-actions.php' => [$admin, $supply],
		'ris.php' => [$admin, $supply],
		'ris_items.php' => [$admin, $supply],
		'ris-actions.php' => [$admin, $supply],
		'print/ris.php' => [$admin, $supply],
		'print/rsmi.php' => [$admin, $supply],

		// Inventory officer scope
		'items.php' => [$admin, $supply, $inventory],
		'item-actions.php' => [$admin, $supply, $inventory],
		'inventory_balance.php' => [$admin, $supply, $inventory],
		'stock_inventory-actions.php' => [$admin, $supply, $inventory],
		'supply_ledger.php' => [$admin, $supply, $inventory],
		'reorder_report.php' => [$admin, $supply, $inventory],

		// Property custodian scope
		'ics.php' => [$admin, $custodian],
		'ics_items.php' => [$admin, $custodian],
		'ics-actions.php' => [$admin, $custodian],
		'par.php' => [$admin, $custodian],
		'par_items.php' => [$admin, $custodian],
		'par-actions.php' => [$admin, $custodian],
		'property_cards.php' => [$admin, $custodian],
		'property_card_transactions.php' => [$admin, $custodian],
		'property_card-actions.php' => [$admin, $custodian],
		'print/property_card.php' => [$admin, $custodian],
		'property_transfers.php' => [$admin, $custodian],
		'property_transfer-actions.php' => [$admin, $custodian],
		'print/ics.php' => [$admin, $custodian],
		'print/par.php' => [$admin, $custodian],

		// Auditor scope
		'physical_inventory_reports.php' => [$auditor],
		'physical_inventory_items.php' => [$auditor],
		'physical_inventory-actions.php' => [$auditor],
		'physical_ppe_reports.php' => [$auditor],
		'physical_ppe_items.php' => [$auditor],
		'physical_ppe-actions.php' => [$auditor],
		'unserviceable_reports.php' => [$auditor],
		'unserviceable_items.php' => [$auditor],
		'unserviceable-actions.php' => [$auditor],
		'print/rpci.php' => [$auditor],
		'print/rpcppe.php' => [$auditor],
		'print/iirup.php' => [$auditor],
	];
}

$currentScript = adminRelativeScriptPath();
$matrix = adminAccessMatrix();
$allowedRoles = $matrix[$currentScript] ?? [ROLE_SYSTEM_ADMIN];
guardRole($allowedRoles);

if (isPost() && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
	flash('danger', 'Invalid request. Please try again.');
	$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
	redirect($redirect);
}
