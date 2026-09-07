<?php

require_once __DIR__ . '/../core/BaseModel.php';

class Supplier extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'suppliers';

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
    }


    public static function add($name, $contact_person, $contact_number, $email, $address, $tin)
    {
        $stmt = self::$db->prepare("INSERT INTO ".self::$tableName." (name, contact_person, contact_number, email, address, tin) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $contact_person, $contact_number, $email, $address, $tin]);
        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }
        return false;
    }

    public static function update($name, $contact_person, $contact_number, $email, $address, $tin, $id)
    {
        $sql = "UPDATE ".self::$tableName." SET name = ?, contact_person = ?, contact_number = ?, email = ?, address = ?, tin = ?";
        $fields = [$name, $contact_person, $contact_number, $email, $address, $tin];

        $sql .= " WHERE id = ?";
        $fields[] = $id;

        $stmt = self::$db->prepare($sql);
        $res = $stmt->execute($fields);
        
        return $res;
    }


    
}



