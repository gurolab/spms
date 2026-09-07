<?php

require_once __DIR__.'/core/helper.php';
require_once __DIR__.'/repo/User.model.php';
require_once __DIR__.'/repo/Account.model.php';
require_once __DIR__.'/repo/Department.model.php';
require_once __DIR__.'/repo/Role.model.php';
require_once __DIR__.'/core/csrf.php';
 

$pageTitle = 'Register';

$departmentModel = new Department();
$departments = $departmentModel->getAll();

$roleModel = new Role();
$roles = $roleModel->getAll();

$oldInputs = [
    'firstname' => (string) (getData('firstname') ?? ''),
    'lastname' => (string) (getData('lastname') ?? ''),
    'email' => (string) (getData('email') ?? ''),
    'departmentid' => (string) (getData('departmentid') ?? ''),
];


if(isPost() && action() == 'register') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request. Please try again.');
        redirect('register.php');
    }

    $firstname = trim((string) ($_POST['firstname'] ?? ''));
    $lastname = trim((string) ($_POST['lastname'] ?? ''));
    $departmentId = $_POST['departmentid'] ?? '';
    $departmentId = is_string($departmentId) ? trim($departmentId) : '';
    $roleId = 6;
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = $_POST['pwd'] ?? '';
    $confirmPassword = $_POST['confirmpwd'] ?? '';

    $formData = [
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'departmentid' => $departmentId,
        'roleid' => $roleId
    ];

    $queryString = http_build_query($formData, '', '&', PHP_QUERY_RFC3986);
    $redirectUrl = 'register.php' . ($queryString ? '?' . $queryString : '');


    if ($firstname === '' || $lastname === '' || $departmentId === '' || $email === '' || $password === '' || $confirmPassword === '') {
        flash('error', 'Please fill in all required fields.');
        redirect($redirectUrl);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Invalid email format. Please double-check your email address.');
        redirect($redirectUrl);
    }

    if(!isDomainAllowed($email)) {
        flash('error', 'Invalid email domain. Please use an allowed email address.');
        redirect($redirectUrl);
    }

    if (!ctype_digit($departmentId)) {
        flash('error', 'Please select a valid department.');
        redirect($redirectUrl);
    }

    $departmentId = (int) $departmentId;
    $departmentRecord = $departmentModel->getById($departmentId);
    if (!$departmentRecord) {
        flash('error', 'Selected department could not be found.');
        redirect($redirectUrl);
    }

    if($password !== $confirmPassword)
    {
        flash('error', 'Password and confirm password do not match.');
        redirect($redirectUrl);
    }

    new User();
    new Account();

    if(User::isExist($email)) {
        flash('error', 'Email already registered. Please use a different email address.');
        redirect($redirectUrl);
    }

    $userId = Account::register($firstname, $lastname, $departmentId, $roleId, $email, $password);
    
    if ($userId) {
        flash('success', 'Registration successful! You can now login.');
        redirect('login.php?email=' . rawurlencode($email));
    } else {
        flash('error', 'Something went wrong while creating your account. Please try again.');
        redirect($redirectUrl);
    }
}else{
    flash('warning', 'Please fill in the form to register a new account.');
    //redirect('register.php?email='.$email);
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

                <form action="?action=register" method="post" class="form">
                    <?=csrf_input()?>

                    <div class="mb-24">
                        <label for="firstname" class="form-label mb-8 h6">First Name</label>
                        <div class="position-relative">
                            <input type="text" class="form-control py-11 ps-40" id="firstname" placeholder="Type your first name" value="<?=htmlspecialchars($oldInputs['firstname'], ENT_QUOTES, 'UTF-8');?>" name="firstname" >
                            <span class="position-absolute top-50 translate-middle-y ms-16 text-gray-600 d-flex"><i class="ph ph-user"></i></span>
                        </div>
                    </div>
                    <div class="mb-24">
                        <label for="lastname" class="form-label mb-8 h6">Last Name</label>
                        <div class="position-relative"> 
                            <input type="text" class="form-control py-11 ps-40" id="lastname" placeholder="Type your last name" value="<?=htmlspecialchars($oldInputs['lastname'], ENT_QUOTES, 'UTF-8');?>" name="lastname" >
                            <span class="position-absolute top-50 translate-middle-y ms-16 text-gray-600 d-flex"><i class="ph ph-user"></i></span>
                        </div>
                    </div>
                    <div class="mb-24">
                        <label for="departmentid" class="form-label mb-8 h6">Department</label>
                        <div class="position-relative">
                            <select class="form-control py-11 ps-40" id="departmentid" name="departmentid" <?=(!empty($departments) && $canSubmit) ? '' : 'disabled'; ?>>
                                <option value="">Select your department</option>
                                <?php foreach ($departments as $department): 
                                    $departmentValue = (string) $department['id'];
                                    $selected = $oldInputs['departmentid'] !== '' && $oldInputs['departmentid'] === $departmentValue ? 'selected' : '';
                                ?>
                                    <option value="<?=$departmentValue?>" <?=$selected?>><?=htmlspecialchars($department['code'] . ' - ' . $department['name'], ENT_QUOTES, 'UTF-8');?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="position-absolute top-50 translate-middle-y ms-16 text-gray-600 d-flex"><i class="ph ph-buildings"></i></span>
                        </div>
                        <?php if (empty($departments)): ?>
                            <small class="text-danger fst-italic d-block mt-8">No departments are configured yet. Please contact the administrator.</small>
                        <?php endif; ?>
                    </div>
                    <div class="mb-24">
                        <label for="email" class="form-label mb-8 h6">Email </label>
                        <div class="position-relative">
                            <input type="email" class="form-control py-11 ps-40" id="email" placeholder="Type your email address" value="<?=htmlspecialchars($oldInputs['email'], ENT_QUOTES, 'UTF-8');?>" name="email" >
                            <span class="position-absolute top-50 translate-middle-y ms-16 text-gray-600 d-flex"><i class="ph ph-envelope"></i></span>
                        </div>
                    </div>
                    <div class="mb-24">
                        <label for="current-password" class="form-label mb-8 h6">Password</label>
                        <div class="position-relative">
                            <input type="password" class="form-control py-11 ps-40" id="current-password" placeholder="Enter a password" name="pwd" >
                            <span class="toggle-password position-absolute top-50 inset-inline-end-0 me-16 translate-middle-y ph ph-eye-slash" id="#current-password"></span>
                            <span class="position-absolute top-50 translate-middle-y ms-16 text-gray-600 d-flex"><i class="ph ph-lock"></i></span>
                        </div>
                    </div>
                    <div class="mb-24">
                        <label for="confirm-password" class="form-label mb-8 h6">Confirm Password</label>
                        <div class="position-relative">
                            <input type="password" class="form-control py-11 ps-40" id="confirm-password" placeholder="Enter confirm password" name="confirmpwd" >
                            <span class="toggle-password position-absolute top-50 inset-inline-end-0 me-16 translate-middle-y ph ph-eye-slash" id="#confirm-password"></span>
                            <span class="position-absolute top-50 translate-middle-y ms-16 text-gray-600 d-flex"><i class="ph ph-lock"></i></span>
                        </div>
                    </div>
                    <div class="mb-32 flex-between flex-wrap gap-8">
                        <a href="forgotpwd.php" class="text-main-600 hover-text-decoration-underline text-15 fw-medium">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-main rounded-pill w-100" name="register" >Sign Up</button>
                    <p class="mt-32 text-gray-600 text-center">Already have an account?
                        <a href="login.php" class="text-main-600 hover-text-decoration-underline"> Log In</a>
                    </p>
                    
                </form>
            </div>
        </div>
    </section>





<?php require_once __DIR__ . '/partials/scripts.php'; ?>
<?php require_once __DIR__ . '/partials/alert-scripts.php'; ?>
