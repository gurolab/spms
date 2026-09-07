<?php

require_once __DIR__ . '/../core/BaseModel.php';

class Department extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'departments';

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
    }

    public function add($code, $name)
    {
        $stmt = self::$db->prepare(
            "INSERT INTO " . self::$tableName . " (code, name) VALUES (?, ?)"
        );
        $stmt->execute([$code, $name]);

        if ($stmt->rowCount() > 0) {
            return self::$db->lastInsertId();
        }

        return false;
    }

    public function isCodeExists($code)
    {
        $stmt = self::$db->prepare(
            "SELECT id FROM " . self::$tableName . " WHERE LOWER(code) = LOWER(?) LIMIT 1"
        );
        $stmt->execute([$code]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function isNameExists($name)
    {
        $stmt = self::$db->prepare(
            "SELECT id FROM " . self::$tableName . " WHERE LOWER(name) = LOWER(?) LIMIT 1"
        );
        $stmt->execute([$name]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function isCodeExistsExceptId($code, $id)
    {
        $stmt = self::$db->prepare(
            "SELECT id FROM " . self::$tableName . " WHERE LOWER(code) = LOWER(?) AND id <> ? LIMIT 1"
        );
        $stmt->execute([$code, $id]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function isNameExistsExceptId($name, $id)
    {
        $stmt = self::$db->prepare(
            "SELECT id FROM " . self::$tableName . " WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1"
        );
        $stmt->execute([$name, $id]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateById($id, $code, $name)
    {
        $stmt = self::$db->prepare(
            "UPDATE " . self::$tableName . " SET code = ?, name = ? WHERE id = ?"
        );
        return $stmt->execute([$code, $name, $id]);
    }



}

