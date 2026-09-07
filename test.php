<?php

require_once __DIR__ . '/core/helper.php';
require_once __DIR__ . '/repo/Account.model.php';
require_once __DIR__ . '/repo/User.model.php';
require_once __DIR__ . '/repo/Role.model.php';
require_once __DIR__ . '/repo/Department.model.php';

// new Account;
// new User;
// $initDept = new Department();
// $initRole = new Role();
// echo show(getUser());
// show(getUser('id'));
echo password_hash('a',PASSWORD_DEFAULT);
