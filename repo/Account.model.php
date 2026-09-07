<?php

require_once __DIR__ . '/../core/BaseModel.php';

class Account extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'users';

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
    }

    public static function login($email, $password)
    {
        $stmt = self::$db->prepare("SELECT * FROM ".self::$tableName." WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            $user['password'] = null;
            return $user;
        }
        return false;
    }

    public static function register($firstname, $lastname, $departmentId, $roleId, $email, $password)
    {
        $password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = self::$db->prepare("INSERT INTO ".self::$tableName." (firstname, lastname, departmentid, roleid, email, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$firstname, $lastname, $departmentId,  $roleId, $email, $password]);
        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }
        return false;
    }

    public static function updateBasicInfo(int $id, string $firstname, string $lastname, string $email): bool
    {
        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " SET firstname = ?, lastname = ?, email = ? WHERE id = ?"
        );
        return $stmt->execute([$firstname, $lastname, $email, $id]);
    }

    public static function getProfile()
    {
        $stmt = self::$db->prepare("SELECT * FROM ".self::$tableName." WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function updateProfile($data)
    {
        self::ensureDb();

        $accountId = (int)($_SESSION['user_id'] ?? 0);
        if ($accountId <= 0) {
            return 0;
        }

        $current = self::findById($accountId);
        if (!$current) {
            return 0;
        }

        $firstname = trim((string)($data['firstname'] ?? $data['username'] ?? ($current['firstname'] ?? '')));
        $lastname = trim((string)($data['lastname'] ?? ($current['lastname'] ?? '')));
        $email = trim((string)($data['email'] ?? ($current['email'] ?? '')));

        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " SET firstname = ?, lastname = ?, email = ? WHERE id = ?"
        );
        $stmt->execute([$firstname, $lastname, $email, $accountId]);
        return $stmt->rowCount();
    }

    public static function resetPassword($email, $token) {
        $stmt = self::$db->prepare("SELECT * FROM ".self::$tableName." WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public static function getByEmail($email)
    {
        $stmt = self::$db->prepare("SELECT * FROM ".self::$tableName." WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function verifyPassword(int $accountId, string $password): bool
    {
        $record = self::findById($accountId);
        if (!$record || !isset($record['password'])) {
            return false;
        }
        return password_verify($password, $record['password']);
    }

    public static function updatePassword($pwd, $accountId){
        $password = password_hash($pwd, PASSWORD_DEFAULT);
        $stmt = self::$db->prepare("UPDATE ".self::$tableName." SET password = ? WHERE id = ?");
        $stmt->execute([$password, $accountId]);
        return $stmt->rowCount() > 0;
    }

    protected static function ensureDb(): void
    {
        if (self::$db === null) {
            self::$db = parent::Db();
        }
    }

    public static function findById(int $id)
    {
        self::ensureDb();
        $stmt = self::$db->prepare("SELECT * FROM " . self::$tableName . " WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
