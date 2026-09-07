<?php

require_once __DIR__ . '/../core/BaseModel.php';

class User extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'users';
    private static $hasStatusColumn = null; // cached detection

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
    }

    private static function hasStatusColumn()
    {
        if (self::$hasStatusColumn !== null) {
            return self::$hasStatusColumn;
        }
        try {
            $stmt = self::$db->query("DESCRIBE " . self::$tableName);
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            self::$hasStatusColumn = in_array('status', $columns, true);
        } catch (Throwable $e) {
            self::$hasStatusColumn = false;
        }
        return self::$hasStatusColumn;
    }


    public static function add($firstname, $lastname, $department, $role, $email, $password, $status)
    {
        $password = password_hash($password, PASSWORD_DEFAULT);
        if (self::hasStatusColumn()) {
            $stmt = self::$db->prepare("INSERT INTO ".self::$tableName." (firstname, lastname, departmentid, roleid, email, password, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$firstname, $lastname, $department,  $role, $email, $password, $status]);
        } else {
            $stmt = self::$db->prepare("INSERT INTO ".self::$tableName." (firstname, lastname, departmentid, roleid, email, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$firstname, $lastname, $department,  $role, $email, $password]);
        }
        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }
        return false;
    }

    public static function update($firstname, $lastname, $department, $role, $email, $password, $status, $id)
    {
        $updateHasStatus = self::hasStatusColumn();
        $sql = "UPDATE ".self::$tableName." SET firstname = ?, lastname = ?, roleid = ?, departmentid = ?, email = ?";
        $fields = [$firstname, $lastname, $role, $department, $email];

        if ($updateHasStatus) {
            $sql .= ", status = ?";
            $fields[] = $status;
        }

        if(!empty($password)) {
            $password = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $fields[] = $password;
        }
        
        $sql .= " WHERE id = ?";
        $fields[] = $id;

        $stmt = self::$db->prepare($sql);
        $result = $stmt->execute($fields);
        
        return $result; // Returns true on success, false on failure
    }

    

    public static function isExist($email)
    {
        $stmt = self::$db->prepare("SELECT * FROM ".self::$tableName." WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount() > 0;
    }

    public static function getByEmail($email)
    {
        $stmt = self::$db->prepare("SELECT * FROM ".self::$tableName." WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    
}



