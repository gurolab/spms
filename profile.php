<?php

require_once __DIR__ . '/core/helper.php';
require_once __DIR__ . '/core/csrf.php';
require_once __DIR__ . '/core/BaseModel.php';

if (!isLoggedIn()) {
    flash('error', 'You must be logged in to view this page.');
    redirect('login.php');
}

$pageTitle = 'My Profile';
$userId = (int)(getUser('id') ?? 0);

$profileRepo = new BaseModel('user_role_dept');
$profile = $profileRepo->getById($userId);

$firstname = trim((string)($profile['firstname'] ?? getUser('firstname') ?? ''));
$lastname = trim((string)($profile['lastname'] ?? getUser('lastname') ?? ''));
$email = trim((string)($profile['email'] ?? getUser('email') ?? ''));
$roleName = trim((string)($profile['rolename'] ?? getUser('rolename') ?? ''));
$departmentCode = trim((string)($profile['departmentcode'] ?? getUser('departmentname') ?? ''));
$departmentName = trim((string)($profile['departmentname'] ?? getUser('department_fullname') ?? $departmentCode));
$status = trim((string)($profile['status'] ?? getUser('status') ?? ''));

$fullName = trim($firstname . ' ' . $lastname);
$initials = strtoupper(mb_substr($firstname, 0, 1, 'UTF-8') . mb_substr($lastname, 0, 1, 'UTF-8'));
$initials = $initials !== '' ? $initials : 'U';

$statusMap = [
    'Active' => 'badge bg-success text-white',
    'Pending' => 'badge bg-warning text-dark',
    'Inactive' => 'badge bg-secondary text-white',
    'Suspended' => 'badge bg-danger text-white',
];
$statusClass = $statusMap[$status] ?? 'badge bg-secondary text-white';

$dashboardRoutes = [
    1 => 'administrator/dashboard.php',
    2 => 'supplyofficer/index.php',
    3 => 'inventoryofficer/dashboard.php',
    4 => 'propertycustodian/dashboard.php',
    5 => 'auditor/dashboard.php',
    6 => 'employee/dashboard.php',
];

$sidebarRoutes = [
    1 => 'administrator/sidebar.php',
    2 => 'supplyofficer/sidebar.php',
    3 => 'inventoryofficer/sidebar.php',
    4 => 'propertycustodian/sidebar.php',
    5 => 'auditor/sidebar.php',
    6 => 'employee/sidebar.php',
];

$roleId = (int)(getUser('roleid') ?? 0);
$dashboardLink = $dashboardRoutes[$roleId] ?? null;
$sidebarPath = $sidebarRoutes[$roleId] ?? null;
$sidebarFile = $sidebarPath ? (__DIR__ . '/' . $sidebarPath) : null;
$showSidebar = $sidebarFile !== null && file_exists($sidebarFile);

?>

<?php require_once __DIR__ . '/partials/head.php'; ?>
<?php require_once __DIR__ . '/partials/preload.php'; ?>
<?php if (!$showSidebar): ?>
<style>
    .dashboard-main-wrapper {
        margin-left: 0 !important;
        width: 100%;
    }
</style>
<?php endif; ?>

    <?php if ($showSidebar): ?>
        <?php require_once $sidebarFile; ?>
    <?php endif; ?>

    <div class="dashboard-main-wrapper">
        <div class="dashboard-body">
            <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
                <div class="breadcrumb mb-24">
                    <ul class="flex-align gap-4">
                        <li><a href="<?= htmlspecialchars($dashboardLink ?? 'index.php', ENT_QUOTES, 'UTF-8'); ?>" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                        <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                        <li><span class="text-main-600 fw-normal text-15"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></span></li>
                    </ul>
                </div>
                <?php if ($dashboardLink): ?>
                    <a href="<?= htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary">
                        <i class="ph ph-arrow-left me-1"></i> Back to Dashboard
                    </a>
                <?php endif; ?>
            </div>

            <div class="row g-24">
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-center mb-24">
                                <div class="mx-auto mb-12 rounded-circle bg-main-50 text-main-600 flex-center fw-semibold text-24" style="width: 88px; height: 88px;">
                                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <h4 class="text-gray-900 mb-4"><?= htmlspecialchars($fullName !== '' ? $fullName : 'Account', ENT_QUOTES, 'UTF-8'); ?></h4>
                                <?php if ($roleName !== ''): ?>
                                    <span class="badge bg-info text-dark mb-8"><?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <p class="text-gray-600 text-14 mb-0"><?= htmlspecialchars($departmentName, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>

                            <div class="list-group list-group-flush">
                                <div class="list-group-item px-0 py-12 d-flex justify-content-between align-items-center">
                                    <span class="text-gray-500 text-13">User ID</span>
                                    <span class="text-gray-900 text-14">#<?= htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="list-group-item px-0 py-12">
                                    <span class="text-gray-500 text-13 d-block mb-4">Email</span>
                                    <span class="text-gray-900 text-14 d-flex align-items-center gap-6">
                                        <i class="ph ph-at"></i>
                                        <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <div class="list-group-item px-0 py-12 d-flex justify-content-between align-items-center">
                                    <span class="text-gray-500 text-13">Status</span>
                                    <span class="<?= $statusClass; ?>"><?= htmlspecialchars($status !== '' ? $status : 'Unknown', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card mb-24">
                        <div class="card-header border-bottom">
                            <div class="flex-between flex-wrap gap-8">
                                <div>
                                    <h5 class="mb-0 text-gray-900">Personal Information</h5>
                                    <p class="text-gray-500 text-13 mb-0">Update your name and contact email.</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="profile-actions.php" method="post" class="row g-16">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="update_profile">

                                <div class="col-md-6">
                                    <label for="firstname" class="form-label text-14 text-gray-500">First Name</label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" value="<?= htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastname" class="form-label text-14 text-gray-500">Last Name</label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" value="<?= htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="col-md-12">
                                    <label for="email" class="form-label text-14 text-gray-500">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <small class="text-gray-500 text-12 d-block mt-6">Use your official institution email to ensure system notifications reach you.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-14 text-gray-500">Department</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($departmentName, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-14 text-gray-500">Role</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ph ph-floppy-disk me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header border-bottom">
                            <div class="flex-between flex-wrap gap-8">
                                <div>
                                    <h5 class="mb-0 text-gray-900">Account Security</h5>
                                    <p class="text-gray-500 text-13 mb-0">Change your password regularly to keep your account secure.</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="profile-actions.php" method="post" class="row g-16">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="update_password">

                                <div class="col-12">
                                    <label for="current_password" class="form-label text-14 text-gray-500">Current Password</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control pe-5" id="current_password" name="current_password" required>
                                        <button type="button" class="btn btn-link text-gray-500 position-absolute top-50 translate-middle-y end-0 me-6 px-0" data-password-toggle="current_password">
                                            <i class="ph ph-eye-slash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="new_password" class="form-label text-14 text-gray-500">New Password</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control pe-5" id="new_password" name="new_password" required>
                                        <button type="button" class="btn btn-link text-gray-500 position-absolute top-50 translate-middle-y end-0 me-6 px-0" data-password-toggle="new_password">
                                            <i class="ph ph-eye-slash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label text-14 text-gray-500">Confirm Password</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control pe-5" id="confirm_password" name="confirm_password" required>
                                        <button type="button" class="btn btn-link text-gray-500 position-absolute top-50 translate-middle-y end-0 me-6 px-0" data-password-toggle="confirm_password">
                                            <i class="ph ph-eye-slash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <small class="text-gray-500 text-12">Password must contain 6-30 characters. Avoid reusing passwords from other services.</small>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ph ph-lock-key-open me-1"></i> Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-footer">
            <div class="flex-between flex-wrap gap-16">
                <p class="text-gray-300 text-13 fw-normal"> &copy; Copyright COTSU 2025, All Right Reserverd</p>
                <div class="flex-align flex-wrap gap-16"></div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            document.querySelectorAll('[data-password-toggle]').forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-password-toggle');
                    const target = document.getElementById(targetId);
                    if (!target) {
                        return;
                    }
                    const icon = this.querySelector('i');
                    const isHidden = target.type === 'password';
                    target.type = isHidden ? 'text' : 'password';
                    if (icon) {
                        icon.classList.toggle('ph-eye');
                        icon.classList.toggle('ph-eye-slash');
                    }
                });
            });
        })();
    </script>
    <?php require_once __DIR__ . '/partials/alert-scripts.php'; ?>
    <?php require_once __DIR__ . '/partials/scripts.php'; ?>
