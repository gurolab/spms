<?php

require_once __DIR__.'/core/helper.php';
require_once __DIR__.'/repo/Account.model.php';
require_once __DIR__.'/core/csrf.php';
require_once __DIR__.'/repo/Role.model.php';
require_once __DIR__.'/repo/Department.model.php';


$pageTitle = 'Login';


if (isPost() && action() == 'login') {

    // Basic brute-force protection: throttle by IP and email
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
    $now = time();
    $windowSeconds = 900; // 15 minutes
    $maxAttempts = 10; // max attempts per window

    // Clean old attempts
    foreach ($_SESSION['login_attempts'] as $key => $attempts) {
        $_SESSION['login_attempts'][$key] = array_filter($attempts, function($ts) use ($now, $windowSeconds) {
            return ($now - $ts) <= $windowSeconds;
        });
    }

    $emailKey = 'email:' . ($_POST['email'] ?? '');
    $ipKey = 'ip:' . $clientIp;
    $attemptsEmail = $_SESSION['login_attempts'][$emailKey] ?? [];
    $attemptsIp = $_SESSION['login_attempts'][$ipKey] ?? [];
    if (count($attemptsEmail) >= $maxAttempts || count($attemptsIp) >= $maxAttempts) {
        writeAuditLog('auth.login.blocked', 'auth', null, [
            'email' => (string)($_POST['email'] ?? ''),
            'reason' => 'rate_limited',
        ]);
        flash('error', 'Too many login attempts. Please try again later.');
        redirect('login.php');
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        writeAuditLog('auth.login.failed', 'auth', null, [
            'email' => (string)($_POST['email'] ?? ''),
            'reason' => 'invalid_csrf',
        ]);
        flash('error', 'Invalid request. Please try again.');
        redirect('login.php');
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['pwd'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        writeAuditLog('auth.login.failed', 'auth', null, [
            'email' => $email,
            'reason' => 'invalid_email_format',
        ]);
        flash('error', 'Invalid email format!');
        redirect('login.php?email='.$email);
    }
    
    if (!isDomainAllowed($email)) {
        writeAuditLog('auth.login.failed', 'auth', null, [
            'email' => $email,
            'reason' => 'disallowed_domain',
        ]);
        flash('error', 'Email domain does not supported!');
        redirect('login.php?email='.$email);
    }

    new Account();
    $user = Account::login($email,$password);
 
    if ($user) {
        flash('success', 'Login successful!');
        session_regenerate_id(true);

        $initRole = new Role();
        $initDept = new Department();

        $deptRecord = $initDept->getById($user['departmentid']);
        $roleRecord = $initRole->getById($user['roleid']);

        $user['departmentname'] = $deptRecord['code'] ?? '';
        $user['department_fullname'] = $deptRecord['name'] ?? '';
        $user['rolename'] = $roleRecord['name'] ?? '';

        $_SESSION['user'] = $user;
        writeAuditLog('auth.login.success', 'user', $user['id'] ?? null, [
            'email' => $email,
            'roleid' => $user['roleid'] ?? null,
        ]);
        // Reset attempts on successful login
        unset($_SESSION['login_attempts']['email:'.$email]);
        unset($_SESSION['login_attempts']['ip:'.$clientIp]);
        redirect('index.php?email='.$email);
    } else {
        writeAuditLog('auth.login.failed', 'auth', null, [
            'email' => $email,
            'reason' => 'invalid_credentials',
        ]);
        flash('error', 'Invalid email or password!');
        // Record failed attempt
        $_SESSION['login_attempts'][$emailKey][] = $now;
        $_SESSION['login_attempts'][$ipKey][] = $now;
        redirect('login.php?email='.$email);
    }

}


?> 


<?php require_once __DIR__ . '/partials/head.php'; ?>
<?php require_once __DIR__ . '/partials/preload.php'; ?>



        <section class="auth d-flex">
            <div class="auth-left bg-main-50 flex-center p-24">
                <img src="<?=IMG_PATH?>/logo/cotsu.png" alt="" width="40%" height="40%">
            </div>
            <div class="auth-right py-40 px-24 flex-center flex-column">
                <div class="auth-right__inner mx-auto w-100">
                    <a href="#" class="auth-right__logo d-block d-md-none text-center">
                        <img src="<?=IMG_PATH?>/logo/cotsu.png" alt="" width="40%" height="40%" class="mx-auto">
                    </a>

                    <h2 class=" d-block d-md text-center mb-8">SUPPLY MANAGEMENT SYSTEM &#128075;</h2>
                    <p class="text-gray-600 text-15 mb-32">Please sign in to your account</p>

                    <form  method="post" action="login.php?action=login" class="form">
                        <?=csrf_input()?>
                        <div class="mb-24">
                            <label for="email" class="form-label mb-8 h6">Email</label>
                            <div class="position-relative">
                                <input type="email" class="form-control py-11 ps-40" id="email" placeholder="Type your username" name="email" value="<?=getData('email')?>" required>
                                <span class="position-absolute top-50 translate-middle-y ms-16 text-gray-600 d-flex"><i class="ph ph-user"></i></span>
                            </div>
                        </div>
                        <div class="mb-24">
                            <label for="current-password" class="form-label mb-8 h6">Current Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control py-11 ps-40" id="current-password" placeholder="Enter Current Password" name="pwd" required>
                                <span class="toggle-password position-absolute top-50 inset-inline-end-0 me-16 translate-middle-y ph ph-eye-slash" id="#current-password"></span>
                                <span class="position-absolute top-50 translate-middle-y ms-16 text-gray-600 d-flex"><i class="ph ph-lock"></i></span>
                            </div>
                        </div>
                        <div class="mb-32 flex-between flex-wrap gap-8">
                            <div class="form-check mb-0 flex-shrink-0">

                            </div>
                            <a href="forgotpwd.php" class="text-main-600 hover-text-decoration-underline text-15 fw-medium">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn btn-main rounded-pill w-100" name="login" value="login">Sign In</button>
                        <p class="mt-32 text-gray-600 text-center">Need an account?
                            <a href="register.php" class="text-main-600 hover-text-decoration-underline">Create an account</a>
                        </p>
                        
                    </form>
                </div>
            </div>
        </section>

    







<?php require_once __DIR__ . '/partials/scripts.php'; ?>

<?php require_once __DIR__ . '/partials/alert-scripts.php'; ?>
