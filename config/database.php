<?php
/**
 * Torque Auto Parts OS - Database Configuration & Connection
 * Supports MySQL (XAMPP default) with automatic SQLite fallback for zero-config startup.
 */

class Database {
    private static $pdo = null;
    private static $driver = null;

    public static function getConnection() {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        // 1. Try MySQL Connection (Standard XAMPP credentials; overridable via env vars for production)
        $mysqlHost = self::env('TORQUE_DB_HOST', '127.0.0.1');
        $mysqlPort = self::env('TORQUE_DB_PORT', '3306');
        $mysqlDb   = self::env('TORQUE_DB_NAME', 'torque_autoparts');
        $mysqlUser = self::env('TORQUE_DB_USER', 'root');
        $mysqlPass = self::env('TORQUE_DB_PASS', '');

        try {
            // First attempt to connect to the specific database
            $dsn = "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDb};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            self::$pdo = new PDO($dsn, $mysqlUser, $mysqlPass, $options);
            self::$driver = 'mysql';
            self::checkAndMigrate();
            return self::$pdo;
        } catch (PDOException $e) {
            // If database doesn't exist yet on MySQL, try creating it
            try {
                $rootDsn = "mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4";
                $rootPdo = new PDO($rootDsn, $mysqlUser, $mysqlPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$mysqlDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                self::$pdo = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDb};charset=utf8mb4", $mysqlUser, $mysqlPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                self::$driver = 'mysql';
                self::checkAndMigrate();
                return self::$pdo;
            } catch (Exception $ex) {
                // MySQL is not running or credentials failed; fallback to SQLite
            }
        }

        // 2. Fallback: SQLite (Zero configuration, stored in database/torque.sqlite)
        try {
            $dbDir = __DIR__ . '/../database';
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            $sqlitePath = $dbDir . '/torque.sqlite';
            self::$pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            self::$driver = 'sqlite';
            self::checkAndMigrate();
            return self::$pdo;
        } catch (Exception $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }

    public static function getDriver() {
        return self::$driver;
    }

    private static function env($key, $default = null) {
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }
        return $default;
    }

    private static function checkAndMigrate() {
        require_once __DIR__ . '/../database/seed_data.php';
        SeedData::initialize(self::$pdo, self::$driver);
    }
}
