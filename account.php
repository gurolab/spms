<?php


require_once __DIR__.'/../core/flash.php';

$title = 'Dashboard';

//page data
$title = 'Account';


//load data
$id = $user['id'];
$euser = getUserById($id);
$given = $euser['firstname'];
$sur = $euser['lastname'];
$program = $euser['program'];
$email = $euser['email'];
$role = $euser['role'];

//error and success messages

?>

<!DOCTYPE html>
<html lang="en">
<?php require_once 'partials/header.php'; ?>

<body class="nav-fixed">
    <?php require_once 'partials/topnav.php'; ?>

    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sidenav shadow-right sidenav-light">
                <?php require_once 'admin/users/sidemenu.php'; ?>
                <?php require_once 'partials/sidefooter.php'; ?>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <div class="page-header pb-10 page-header-dark bg-gradient-primary-to-secondary">
                    <div class="container-fluid">
                        <div class="page-header-content">
                            <h1 class="page-header-title">
                                <div class="page-header-icon"><i data-feather="user"></i></div>
                                <span><?=$title?></span>
                            </h1>
                            <div class="page-header-subtitle"></div>
                        </div>
                    </div>
                </div>

                <div class="container-fluid mt-n15">
                    <div class="row">
                        <div class="col-sm-12 col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <?php require_once 'inc/flashmessage.php'; ?>
                                    <form method="post" action="log/account.mvc.php?action=updateaccount&id=<?= $id ?>">
                                        <div class="form-row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="small mb-1" for="inputFirstName">Given Name</label>
                                                    <input class="form-control py-4" name="firstname" type="text"
                                                        placeholder="Enter given name" value="<?=$given?>" required />
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="small mb-1" for="inputLastName">Surname</label>
                                                    <input class="form-control py-4" name="lastname" type="text"
                                                        placeholder="Enter surname" value="<?=$sur?>" required />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="exampleFormControlSelect1">Program</label>
                                            <select class="form-control" id="exampleFormControlSelect1" name="program"
                                                required>
                                                <option value="">Select Program</option>
                                                <option value="MIT" <?= $program == 'MIT' ? 'selected' : '' ?>>MIT
                                                </option>
                                                <option value="MSIT" <?= $program == 'MSIT' ? 'selected' : '' ?>>MSIT
                                                </option>
                                                <option value="MSSW" <?= $program == 'MSSW' ? 'selected' : '' ?>>MSSW
                                                </option>
                                                <option value="MAPD" <?= $program == 'MAPD' ? 'selected' : '' ?>>MAPD
                                                </option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="small mb-1" for="inputEmailAddress">Email</label>
                                            <input class="form-control py-4" name="email" type="email"
                                                aria-describedby="emailHelp" placeholder="Enter email address"
                                                value="<?=$email?>" required readonly />
                                        </div>
                                        <div class="form-group">
                                            <label for="exampleFormControlSelect1">Role</label>
                                            <select class="form-control" id="exampleFormControlSelect2" name="role" required>
                                                <option value="">Select Role</option>
                                                <option value="admin" <?= $role == 'admin' ? 'selected' : '' ?>>Admin</option>
                                                <option value="adviser" <?= $role == 'adviser' ? 'selected' : '' ?>>Adviser</option>
                                                <option value="chairperson" <?= $role == 'chairperson' ? 'selected' : '' ?>>Chairperson</option>
                                                <option value="researcher" <?= $role == 'researcher' ? 'selected' : '' ?>>Researcher</option>
                                                <option value="student" <?  $role == 'student' ? 'selected' : '' ?>>Student</option>
                                            </select>
                                        </div>
                                        <div class="form-group mt-4 mb-0">
                                            <input type="submit" value="Update User"
                                                class="btn btn-primary btn-block" />
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <?php require_once 'inc/flashmessage.php'; ?>
                                    <form method="post"
                                        action="log/account.mvc.php?action=updatepassword&id=<?= $id ?>">
                                        <div class="form-row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="small mb-1" for="inputPassword">Password</label>
                                                    <input class="form-control py-4" name="pwd" type="password"
                                                        placeholder="Enter password" />
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="small mb-1" for="inputConfirmPassword">Confirm
                                                        Password</label>
                                                    <input class="form-control py-4" name="confirmpwd" type="password"
                                                        placeholder="Confirm password" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group mt-4 mb-0">
                                            <input type="submit" value="Update Password"
                                                class="btn btn-primary btn-block" />
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php require_once 'partials/footer.php'; ?>
        </div>
    </div>

    <?php require_once 'partials/scripts.php'; ?>
</body>

</html>