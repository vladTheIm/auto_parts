<?php
/**
 * SpareStack Auto Parts OS - Database Configuration & Connection
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
        self::migrateManualItems();
        require_once __DIR__ . '/../database/seed_data.php';
        SeedData::initialize(self::$pdo, self::$driver);
    }

    /**
     * Idempotent migration: allow sale_items.product_id to be NULL so the POS
     * can record manual sales (no catalog product, seller-entered price).
     * MySQL FK constraints already permit NULL values, so we only relax the NOT NULL flag.
     */
    private static function migrateManualItems() {
        try {
            if (self::$driver === 'sqlite') {
                // SQLite cannot ALTER a column; rebuild the table without NOT NULL.
                $st = self::$pdo->query("SELECT 'a' FROM pragma_table_info('sale_items') WHERE name='product_id' AND notnull=0 LIMIT 1");
                if ($st->fetchColumn() === false) {
                    self::$pdo->exec("BEGIN");
                    self::$pdo->exec("CREATE TABLE sale_items_new (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        sale_id INTEGER NOT NULL,
                        product_id INTEGER,
                        product_name TEXT NOT NULL,
                        sku TEXT NOT NULL,
                        unit_price REAL NOT NULL,
                        cost_price REAL NOT NULL,
                        quantity INTEGER NOT NULL,
                        total_price REAL NOT NULL
                    )");
                    self::$pdo->exec("INSERT INTO sale_items_new SELECT id, sale_id, product_id, product_name, sku, unit_price, cost_price, quantity, total_price FROM sale_items");
                    self::$pdo->exec("DROP TABLE sale_items");
                    self::$pdo->exec("ALTER TABLE sale_items_new RENAME TO sale_items");
                    self::$pdo->exec("COMMIT");
                }
            } else {
                // MySQL: relax the NOT NULL constraint only if still required (FK already allows NULL).
                $st = self::$pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'product_id'");
                $nullable = $st->fetchColumn();
                if (strtoupper((string)$nullable) !== 'YES') {
                    self::$pdo->exec("ALTER TABLE sale_items MODIFY COLUMN product_id INT NULL");
                }
            }
        } catch (Exception $e) {
            // Non-fatal migration wrapper: if ALTER fails (e.g. column already nullable), ignore.
        }
    }
}
