<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/SystemSettings.model.php';

$action = action();
$settings = SystemSettings::getSettings();
$actorId = getUser('id') ?? null;

if ($action === 'update_general' && isPost()) {
    $organizationName = trim($_POST['organization_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $fiscalStart = trim($_POST['fiscal_year_start'] ?? '');
    $fiscalEnd = trim($_POST['fiscal_year_end'] ?? '');

    if ($organizationName === '') {
        flash('danger', 'Organization name is required.');
        redirect('system_settings.php');
    }

    if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Please enter a valid contact email address.');
        redirect('system_settings.php');
    }

    $fiscalStartDate = null;
    $fiscalEndDate = null;

    try {
        if ($fiscalStart !== '') {
            $fiscalStartDate = (new DateTime($fiscalStart))->format('Y-m-d');
        }
        if ($fiscalEnd !== '') {
            $fiscalEndDate = (new DateTime($fiscalEnd))->format('Y-m-d');
        }
    } catch (Throwable $e) {
        flash('danger', 'Invalid fiscal year dates provided.');
        redirect('system_settings.php');
    }

    if ($fiscalStartDate && $fiscalEndDate) {
        if ($fiscalStartDate > $fiscalEndDate) {
            flash('danger', 'Fiscal year end must be after the start date.');
            redirect('system_settings.php');
        }
    }

    $payload = [
        'organization_name' => $organizationName,
        'address' => $address,
        'contact_email' => $contactEmail,
        'contact_number' => $contactNumber,
        'fiscal_year_start' => $fiscalStartDate,
        'fiscal_year_end' => $fiscalEndDate,
    ];

    $changes = [];
    foreach ($payload as $field => $value) {
        $previous = $settings[$field] ?? null;
        if ($previous === '') {
            $previous = null;
        }
        if ($value === '') {
            $value = null;
        }
        if ($previous != $value) {
            $changes[$field] = [
                'old' => $previous,
                'new' => $value,
            ];
        }
    }

    $result = SystemSettings::save($payload, $actorId);

    if ($result) {
        if (!empty($changes)) {
            writeAuditLog('system_settings.update', 'system_settings', (int)($settings['id'] ?? 1), [
                'changes' => $changes,
            ]);
        }
        flash('success', 'Settings updated successfully.');
    } else {
        flash('danger', 'Failed to update settings.');
    }

    redirect('system_settings.php');
}

if ($action === 'upload_logo' && isPost()) {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'Please select a logo to upload.');
        redirect('system_settings.php');
    }

    $file = $_FILES['logo'];

    if ((int)$file['size'] > 2097152) { // 2MB
        flash('danger', 'Logo file is too large. Maximum size is 2MB.');
        redirect('system_settings.php');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMime = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/svg+xml' => 'svg',
    ];

    if (!$mimeType || !array_key_exists($mimeType, $allowedMime)) {
        flash('danger', 'Unsupported logo format. Please upload a PNG, JPG, or SVG file.');
        redirect('system_settings.php');
    }

    $extension = $allowedMime[$mimeType];
    $uploadDir = dirname(__DIR__) . '/assets/uploads/settings';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        flash('danger', 'Failed to create upload directory.');
        redirect('system_settings.php');
    }

    $fileName = 'logo-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        flash('danger', 'Unable to move uploaded file.');
        redirect('system_settings.php');
    }

    $relativePath = 'assets/uploads/settings/' . $fileName;

    $oldLogo = $settings['logo_path'] ?? null;

    $result = SystemSettings::save(['logo_path' => $relativePath], $actorId);

    if ($result) {
        if ($oldLogo && strpos($oldLogo, 'assets/uploads/settings/') === 0) {
            $oldFile = dirname(__DIR__) . '/' . $oldLogo;
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        writeAuditLog('system_settings.logo.update', 'system_settings', (int)($settings['id'] ?? 1), [
            'changes' => [
                'logo_path' => [
                    'old' => $oldLogo,
                    'new' => $relativePath,
                ],
            ],
        ]);
        flash('success', 'Logo updated successfully.');
    } else {
        @unlink($destination);
        flash('danger', 'Failed to update logo.');
    }

    redirect('system_settings.php');
}

if ($action === 'remove_logo' && isPost()) {
    $oldLogo = $settings['logo_path'] ?? null;
    if ($oldLogo && strpos($oldLogo, 'assets/uploads/settings/') === 0) {
        $oldFile = dirname(__DIR__) . '/' . $oldLogo;
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    $result = SystemSettings::save(['logo_path' => null], $actorId);

    if ($result) {
        writeAuditLog('system_settings.logo.remove', 'system_settings', (int)($settings['id'] ?? 1), [
            'changes' => [
                'logo_path' => [
                    'old' => $oldLogo,
                    'new' => null,
                ],
            ],
        ]);
        flash('success', 'Logo removed successfully.');
    } else {
        flash('danger', 'Failed to remove logo.');
    }

    redirect('system_settings.php');
}

flash('danger', 'Unsupported action requested.');
redirect('system_settings.php');

