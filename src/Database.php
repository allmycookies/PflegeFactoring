<?php

namespace Src;

use PDO;
use PDOException;

class Database {
    private static $pdo = null;

    public static function connect() {
        if (self::$pdo === null) {
            $dbFile = dirname(__DIR__) . '/database.sqlite';
            try {
                self::$pdo = new PDO('sqlite:' . $dbFile);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::setup();
            } catch (PDOException $e) {
                die("Database Connection Error: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    private static function setup() {
        $pdo = self::$pdo;
        $pdo->exec("CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS entries (id INTEGER PRIMARY KEY AUTOINCREMENT, data TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS saved_filters (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, filter_config TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS entry_history (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER, message TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(entry_id) REFERENCES entries(id) ON DELETE CASCADE)");
    }
}
