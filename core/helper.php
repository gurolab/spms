<?php

require_once __DIR__.'/config.php';
 

function isPost(){
    return $_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST);
}

function isGet(){
    return $_SERVER['REQUEST_METHOD'] == 'GET' && !empty($_GET);
}

function getData($param){
    return isset($_GET[$param]) ? $_GET[$param] : null;
}

function action(){
    if (isPost() && isset($_POST['action'])) {
        return $_POST['action'];
    }
    return isset($_GET['action']) ? $_GET['action'] : '';
}

function POSTACT($actionName){
    return isPost() && action() == $actionName ? true : false;
}

function GETACT($actionName){
    return isGet() && action() == $actionName ? true : false;
}


function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}


function isDomainAllowed($email){
    $allowed = defined('ALLOWED_EMAIL_DOMAINS') ? ALLOWED_EMAIL_DOMAINS : ['gmail.com','cotsu.edu.ph'];
    $parts = explode('@', (string)$email);
    if (count($parts) < 2) {
        return false;
    }
    $domain = strtolower($parts[1]);
    return in_array($domain, $allowed, true);
}

function isPDF(){
    $allowed = ['application/pdf'];
    return in_array($_FILES['file']['type'], $allowed);
}

function redirect($url) {
    header('Location: ' . $url);
    exit();
}


function show($params = []) {
    echo '<pre>';
    print_r($params);
    echo '</pre>';
}


function getUser($param=null){
    if (!isLoggedIn()) {
        return null; // or handle the case when user is not logged in
    }
 
    if (empty($param)) {
        return $_SESSION['user'];
    }

    if($param =='fullname'){
        return $_SESSION['user']['firstname'] . ' ' . $_SESSION['user']['lastname'];
    }

    return isset($_SESSION['user'][$param]) ? $_SESSION['user'][$param] : null;
}

function isAdmin(){
    return isset($_SESSION['user']) && $_SESSION['user']['roleid'] == 1;
}

function isSupplyOfficer(){
    return isset($_SESSION['user']) && $_SESSION['user']['roleid'] == 2;
}

function isInventoryOfficer(){
    return isset($_SESSION['user']) && $_SESSION['user']['roleid'] == 3;
}

function isPropertyCustodian(){
    return isset($_SESSION['user']) && $_SESSION['user']['roleid'] == 4;
}

function isAuditor(){
    return isset($_SESSION['user']) && $_SESSION['user']['roleid'] == 5;
}

function isEmployee(){
    return isset($_SESSION['user']) && $_SESSION['user']['roleid'] == 6;
}

function isValidLength($string, $min, $max) {
    return strlen($string) >= $min && strlen($string) <= $max;
}

// function getRoles(){
//     return ['Administrator', 'Inventory Officer', 'chairperson', 'researcher', 'student', 'faculty'];
// }

function flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}



function getModelField($modelData, $fieldName) {
    if (is_array($modelData)) {
        return isset($modelData[$fieldName]) ? $modelData[$fieldName] : null;
    } elseif (is_object($modelData)) {
        return isset($modelData->$fieldName) ? $modelData->$fieldName : null;
    }
    return null;
}

function SearchById($collection, $id, $idField = 'id') {
    if (!is_array($collection)) {
        return null;
    }
    
    foreach ($collection as $item) {
        if (isset($item[$idField]) && $item[$idField] == $id) {
            return $item;
        }
    }
    
    return null;
}

function formatItemSelectLabel($item): string
{
    if (!is_array($item)) {
        return 'N/A - Unknown Item - N/A';
    }

    $code = trim((string)($item['code'] ?? ''));
    $description = trim((string)($item['description'] ?? ''));
    $unit = trim((string)($item['unit'] ?? ''));

    return sprintf(
        '%s - %s - %s',
        $code !== '' ? $code : 'N/A',
        $description !== '' ? $description : 'Unknown Item',
        $unit !== '' ? $unit : 'N/A'
    );
}

function getDepartmentCodeFromUser(?array $user): string
{
    if (!is_array($user)) {
        return '';
    }

    $directCode = trim((string)(
        $user['departmentcode']
        ?? $user['department_code']
        ?? $user['dept_code']
        ?? $user['departmentCode']
        ?? ''
    ));
    if ($directCode !== '') {
        return strtoupper($directCode);
    }

    $departmentName = trim((string)($user['departmentname'] ?? ($user['department_name'] ?? '')));
    if ($departmentName === '') {
        return '';
    }

    preg_match_all('/[A-Za-z0-9]+/', $departmentName, $matches);
    if (empty($matches[0])) {
        return '';
    }

    $parts = $matches[0];
    if (count($parts) === 1) {
        return strtoupper((string)$parts[0]);
    }

    $code = '';
    foreach ($parts as $part) {
        $code .= strtoupper((string)$part[0]);
    }

    return $code;
}

function formatOfficerNameWithDeptCode(?array $user, ?int $fallbackId = null): string
{
    if (!is_array($user)) {
        return $fallbackId !== null && $fallbackId > 0 ? ('User #' . $fallbackId) : '';
    }

    $fullname = trim((string)($user['fullname'] ?? ''));
    if ($fullname === '') {
        $firstname = trim((string)($user['firstname'] ?? ''));
        $lastname = trim((string)($user['lastname'] ?? ''));
        $fullname = trim($firstname . ' ' . $lastname);
    }
    if ($fullname === '') {
        $resolvedId = (int)($user['id'] ?? 0);
        if ($resolvedId > 0) {
            $fullname = 'User #' . $resolvedId;
        } elseif ($fallbackId !== null && $fallbackId > 0) {
            $fullname = 'User #' . $fallbackId;
        }
    }

    $departmentCode = getDepartmentCodeFromUser($user);
    if ($fullname !== '' && $departmentCode !== '') {
        return $fullname . ' - ' . $departmentCode;
    }

    return $fullname;
}



function DisplayDate($date) {
    if (empty($date)) {
        return '';
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    
    return date('F j, Y', $timestamp);
}

function writeAuditLog(string $action, string $entity, $entityId = null, array $metadata = [], array $details = []): void
{
    static $auditLogger = null;

    if (!array_key_exists('ip', $metadata)) {
        $metadata['ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
    }

    if (!array_key_exists('actor_email', $metadata) && isset($_SESSION['user']['email'])) {
        $metadata['actor_email'] = $_SESSION['user']['email'];
    }

    if (!array_key_exists('user_agent', $metadata)) {
        $metadata['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    $actorId = $_SESSION['user']['id'] ?? null;

    try {
        if ($auditLogger === null) {
            require_once __DIR__ . '/../repo/AuditLog.model.php';
            $auditLogger = new AuditLog();
        }

        $auditLogger->record($actorId, $action, $entity, $entityId, $metadata, $details);
    } catch (Throwable $e) {
        error_log('writeAuditLog failed: ' . $e->getMessage());
    }
}
