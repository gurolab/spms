<?php

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/SystemSettings.model.php';

$pageTitle = 'System Settings';
$settings = SystemSettings::getSettings();

$logoPath = $settings['logo_path'] ?? null;
$logoUrl = $logoPath
    ? BASE_URL . ltrim($logoPath, '/')
    : IMG_PATH . '/logo/cotsu.png';

function fieldValue(array $settings, string $key): string
{
    $value = $settings[$key] ?? '';
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="index.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= htmlspecialchars($pageTitle) ?></span></li>
                </ul>
            </div>
        </div>

        <div class="row g-24">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Organization Profile</h5>
                        <p class="text-gray-200 text-13 mb-0">Update the basic information displayed across the system.</p>
                    </div>
                    <div class="card-body">
                        <form action="system_settings-actions.php" method="post" class="row g-16">
                            <input type="hidden" name="action" value="update_general">

                            <div class="col-12">
                                <label for="organization_name" class="form-label">Organization Name</label>
                                <input type="text" class="form-control" id="organization_name" name="organization_name"
                                    value="<?= fieldValue($settings, 'organization_name') ?>" required>
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3" placeholder="Building, Street, City"><?= fieldValue($settings, 'address') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email"
                                    value="<?= fieldValue($settings, 'contact_email') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number"
                                    value="<?= fieldValue($settings, 'contact_number') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="fiscal_year_start" class="form-label">Fiscal Year Start</label>
                                <input type="date" class="form-control" id="fiscal_year_start" name="fiscal_year_start"
                                    value="<?= fieldValue($settings, 'fiscal_year_start') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="fiscal_year_end" class="form-label">Fiscal Year End</label>
                                <input type="date" class="form-control" id="fiscal_year_end" name="fiscal_year_end"
                                    value="<?= fieldValue($settings, 'fiscal_year_end') ?>">
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ph ph-floppy-disk me-1"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header border-bottom border-gray-100">
                        <h5 class="mb-0 text-gray-900">Branding</h5>
                        <p class="text-gray-200 text-13 mb-0">Upload the official logo shown in headers, documents, and reports.</p>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-24">
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Organization Logo" class="rounded shadow-sm" style="max-width: 200px; max-height: 160px;">
                            <p class="text-gray-200 text-13 mt-12 mb-0">Current Logo</p>
                        </div>
                        <form action="system_settings-actions.php" method="post" enctype="multipart/form-data" class="row g-16">
                            <input type="hidden" name="action" value="upload_logo">
                            <div class="col-12">
                                <label for="logo" class="form-label">Logo Image</label>
                                <input class="form-control" type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.svg">
                                <small class="text-gray-200 d-block mt-6">Recommended: PNG/SVG, max 2MB.</small>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="ph ph-upload-simple me-1"></i> Upload Logo
                                </button>
                            </div>
                        </form>
                        <?php if ($logoPath): ?>
                            <form action="system_settings-actions.php" method="post" class="mt-16">
                                <input type="hidden" name="action" value="remove_logo">
                                <button type="submit" class="btn btn-link text-danger p-0">
                                    <i class="ph ph-trash me-1"></i> Remove current logo
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16">
            <p class="text-gray-300 text-13 fw-normal">&copy; Copyright Cotabato State University <?= date('Y') ?>, All Rights Reserved</p>
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/scripts.php'; ?>

