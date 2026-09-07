<?php

require_once __DIR__ . '/Model.php';


class BaseModel extends Model
{
    protected static $db = null;
    protected $newTable;

    public function __construct($tableName)
    {
        parent::__construct();
        $this->newTable = $tableName;
        self::$db = parent::Db();
    }
    
    public function getAll()
    {
        $stmt = self::$db->prepare("SELECT * FROM " . $this->newTable);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = self::$db->prepare("SELECT * FROM " . $this->newTable . " WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteById($id)
    {
        $stmt = self::$db->prepare("DELETE FROM " . $this->newTable . " WHERE id = ?");
        return $stmt->execute([$id]);
    }
}