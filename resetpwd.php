<?php

require_once __DIR__.'/core/helper.php';
require_once __DIR__.'/repo/User.model.php';
require_once __DIR__.'/repo/Account.model.php';
require_once __DIR__.'/core/csrf.php';

// Handle reset request submission (step 1)
if (isPost() && action() == 'reset') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request. Please try again.');
        redirect('forgotpwd.php');
    }

    $email = trim((string)($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Invalid email format. Please enter a valid email address.');
        redirect('forgotpwd.php?email=' . rawurlencode($email));
    }

    if (!isDomainAllowed($email)) {
        flash('error', 'Invalid email domain. Please use your Gmail or COTSU email address.');
        redirect('forgotpwd.php?email=' . rawurlencode($email));
    }

    $init = new User;
    $user = $init::getByEmail($email);

    if (!$user) {
        flash('error', 'Email is not yet registered. Please check your email address.');
        redirect('forgotpwd.php?email=' . rawurlencode($email));
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['password_reset'] = $_SESSION['password_reset'] ?? [];
    $_SESSION['password_reset'][$email] = [
        'token'   => $token,
        'expires' => time() + 1800, // 30 minutes
    ];

    flash('info', 'Password reset link has been generated. Please check your email.');
    redirect('resetpwd.php?action=newp&email=' . rawurlencode($email) . '&token=' . $token);
}

// Validate token before showing the new password form (step 2 GET)
if (!isPost() && action() == 'newp') {
    $emailParam = (string)(getData('email') ?? '');
    $tokenParam = (string)(getData('token') ?? '');

    $resetRecord = $_SESSION['password_reset'][$emailParam] ?? null;
    $isExpired = isset($resetRecord['expires']) && $resetRecord['expires'] < time();

    if (
        $emailParam === '' ||
        $tokenParam === '' ||
        !$resetRecord ||
        $isExpired ||
        !hash_equals((string)$resetRecord['token'], $tokenParam)
    ) {
        if ($isExpired) {
            unset($_SESSION['password_reset'][$emailParam]);
        }
        flash('error', 'The password reset link is invalid or has expired.');
        redirect('forgotpwd.php');
    }
}

// Handle password update submission (step 2 POST)
if (isPost() && action() == 'newp') {
    $email = trim((string)($_POST['email'] ?? ''));
    $token = (string)($_POST['token'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmpassword = (string)($_POST['confirmpassword'] ?? '');

    $redirectParams = [
        'action' => 'newp',
        'email'  => $email !== '' ? $email : null,
        'token'  => $token !== '' ? $token : null,
    ];
    $redirectParams = array_filter($redirectParams, fn ($value) => $value !== null);
    $redirectTarget = 'resetpwd.php?' . http_build_query($redirectParams, '', '&', PHP_QUERY_RFC3986);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request. Please try again.');
        redirect($redirectTarget);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Invalid email format. Please enter a valid email address.');
        redirect($redirectTarget);
    }

    if (!isDomainAllowed($email)) {
        flash('error', 'Invalid email domain. Please use your Gmail or COTSU email address.');
        redirect($redirectTarget);
    }

    $resetRecord = $_SESSION['password_reset'][$email] ?? null;
    if (!$resetRecord) {
        flash('error', 'No password reset request was found for this email. Please start again.');
        redirect('forgotpwd.php?email=' . rawurlencode($email));
    }

    if (($resetRecord['expires'] ?? 0) < time()) {
        unset($_SESSION['password_reset'][$email]);
        flash('error', 'The password reset link has expired. Please request a new one.');
        redirect('forgotpwd.php?email=' . rawurlencode($email));
    }

    if (!hash_equals((string)$resetRecord['token'], $token)) {
        flash('error', 'Invalid token. Please try again.');
        redirect('forgotpwd.php?email=' . rawurlencode($email));
    }

    if (isValidLength($password, 6, 30) == false) {
        flash('error', 'Password must be between 6 and 30 characters.');
        redirect($redirectTarget);
    }

    if ($password !== $confirmpassword) {
        flash('error', 'Passwords do not match. Please try again.');
        redirect($redirectTarget);
    }

    new Account;
    $account = Account::getByEmail($email);

    if (!$account) {
        flash('error', 'Email not found. Please check your email address.');
        redirect('forgotpwd.php?email=' . rawurlencode($email));
    }

    $success = Account::updatePassword($password, $account['id']);
    if (!$success) {
        flash('error', 'Failed to update password. Please try again later.');
        redirect($redirectTarget);
    }

    unset($_SESSION['password_reset'][$email]);

    flash('success', 'Password reset successfully. You can now login with your new password.');
    redirect('login.php?email=' . rawurlencode($email));
}

$pageTitle = "Reset Password";

?>



<?php require_once __DIR__ . '/partials/head.php'; ?>
<?php require_once __DIR__ . '/partials/preload.php'; ?>

    <body class="bg-primary">
        <div id="layoutAuthentication">
            <div id="layoutAuthentication_content">
                <main>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-5">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header justify-content-center"><h3 class="font-weight-light my-4">Password Recovery</h3></div>
                                    <div class="card-body">
                                        <div class="small mb-3 text-muted"></div>
                                        <form method="post" action="?action=newp">
                                            <?=csrf_input()?>
                                            <div class="form-group">
                                                <label class="small mb-1" for="inputEmailAddress">New Password</label>
                                                <input class="form-control py-4" id="inputEmailAddress" type="password" aria-describedby="emailHelp" placeholder="Enter new password" name="password" required/>
                                            </div>
                                            <div class="form-group">
                                                <label class="small mb-1" for="inputEmailAddress">New Password</label>
                                                <input class="form-control py-4" id="inputEmailAddress" type="password" aria-describedby="emailHelp" placeholder="Enter new password" name="confirmpassword" required/>
                                            </div>
                                            <div class="form-group d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <input type="hidden" name="token" value="<?=htmlspecialchars((string)getData('token'), ENT_QUOTES, 'UTF-8');?>">
                                                <input type="hidden" name="email" value="<?=htmlspecialchars((string)getData('email'), ENT_QUOTES, 'UTF-8');?>">
                                                <a class="small" href="login.php">Return to login</a>
                                                <button class="btn btn-primary" type="submit" name="reset">Confirm Password</button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="card-footer text-center">
                                        <div class="small"><a href="register.php">Need an account? Sign up!</a></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>


<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<?php require_once __DIR__ . '/partials/alert-scripts.php'; ?>
