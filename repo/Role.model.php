<?php

require_once __DIR__ . '/../core/BaseModel.php';

class Role extends BaseModel
{
    protected static $db = null;
    protected static $tableName = 'roles';

    public function __construct()
    {
        parent::__construct(self::$tableName);
        self::$db = parent::Db();
    }


    
}



