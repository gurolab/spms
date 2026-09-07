<?php

require_once __DIR__ . '/config.php'; // Use absolute path

class Model {
    private static $db = null;

    protected function __construct() {
        self::initDbConnection();
    }

    protected static function initDbConnection() {
        if (self::$db === null) {
            self::$db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS, DB_OPTIONS);
        }
        return self::$db;
    }

    public static function Db() {
        return self::initDbConnection();
    }

    public function getCount($table) {
        $db = self::Db();
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$table}");
        $stmt->execute();
        return $stmt->fetchColumn();
    }



}