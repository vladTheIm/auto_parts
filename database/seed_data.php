<?php
/**
 * SpareStack Auto Parts OS - Schema Auto-Migration & Seed Data Initializer
 */

class SeedData {
    public static function initialize(PDO $db, $driver) {
        if ($driver === 'sqlite') {
            self::initSqlite($db);
        } else {
            self::initMysql($db);
        }
    }

    private static function initSqlite(PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS branches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                location TEXT NOT NULL,
                phone TEXT,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                branch_id INTEGER DEFAULT 1,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'Cashier',
                is_online INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                slug TEXT NOT NULL UNIQUE,
                icon TEXT DEFAULT 'cog'
            );

            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                name TEXT NOT NULL,
                sku TEXT UNIQUE NOT NULL,
                barcode TEXT,
                oem_number TEXT,
                fits_vehicles TEXT,
                cost_price REAL NOT NULL DEFAULT 0.0,
                selling_price REAL NOT NULL DEFAULT 0.0,
                stock_quantity INTEGER NOT NULL DEFAULT 0,
                reorder_level INTEGER NOT NULL DEFAULT 10,
                image_url TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS branch_stock (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                branch_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 0,
                reorder_level INTEGER NOT NULL DEFAULT 10,
                UNIQUE(branch_id, product_id)
            );

            CREATE TABLE IF NOT EXISTS stock_movements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                branch_id INTEGER NOT NULL,
                user_id INTEGER,
                change_qty INTEGER NOT NULL,
                previous_qty INTEGER NOT NULL,
                new_qty INTEGER NOT NULL,
                reason TEXT NOT NULL,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT NOT NULL,
                workshop_name TEXT,
                credit_balance REAL DEFAULT 0.0,
                credit_limit REAL DEFAULT 2000.0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                contact_person TEXT,
                phone TEXT,
                email TEXT,
                address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS sales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_number TEXT UNIQUE NOT NULL,
                branch_id INTEGER NOT NULL,
                user_id INTEGER,
                customer_id INTEGER,
                subtotal REAL NOT NULL,
                vat_amount REAL NOT NULL,
                discount_amount REAL DEFAULT 0.0,
                grand_total REAL NOT NULL,
                payment_method TEXT NOT NULL,
                payment_ref TEXT,
                status TEXT DEFAULT 'Completed',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS sale_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sale_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                product_name TEXT NOT NULL,
                sku TEXT NOT NULL,
                unit_price REAL NOT NULL,
                cost_price REAL NOT NULL,
                quantity INTEGER NOT NULL,
                total_price REAL NOT NULL
            );

            CREATE TABLE IF NOT EXISTS purchase_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                po_number TEXT UNIQUE NOT NULL,
                supplier_id INTEGER NOT NULL,
                branch_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                unit_cost REAL NOT NULL,
                total_cost REAL NOT NULL,
                status TEXT NOT NULL DEFAULT 'Ordered',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                received_at DATETIME
            );

            CREATE TABLE IF NOT EXISTS shifts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                branch_id INTEGER NOT NULL,
                opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                closed_at DATETIME,
                opening_float REAL NOT NULL DEFAULT 300.0,
                closing_cash_counted REAL,
                expected_cash REAL,
                cash_variance REAL,
                status TEXT NOT NULL DEFAULT 'Open'
            );

            CREATE TABLE IF NOT EXISTS settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT NOT NULL
            );
        ");

        self::seedCoreData($db);
    }

    private static function initMysql(PDO $db) {
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        $db->exec($sql);
        self::seedCoreData($db);
    }

    private static function seedCoreData(PDO $db) {
        // Check if data already exists
        $stmt = $db->query("SELECT COUNT(*) FROM branches");
        if ($stmt->fetchColumn() > 0) {
            return; // Already seeded
        }

        // 1. Branches
        $db->exec("
            INSERT INTO branches (id, name, location, phone, is_active) VALUES
            (1, 'Kumasi Main', 'Harper Road, Adum, Kumasi', '+233 32 202 4491', 1),
            (2, 'Accra Spintex', 'Spintex Road, Near Papaye, Accra', '+233 30 281 9920', 1),
            (3, 'Takoradi Depot', 'Harbour Commercial Area, Takoradi', '+233 31 204 8831', 1);
        ");

        // 2. Users (Default password: password123)
        $pwd = password_hash('password123', PASSWORD_BCRYPT);
        $db->exec("
            INSERT INTO users (branch_id, name, email, password_hash, role, is_online) VALUES
            (1, 'Efua Asante', 'efua@asanteautoparts.com', '{$pwd}', 'Owner', 1),
            (1, 'Kojo Mensah', 'kojo@asanteautoparts.com', '{$pwd}', 'Manager', 1),
            (1, 'Ama Boateng', 'ama@asanteautoparts.com', '{$pwd}', 'Cashier', 0),
            (2, 'Yaw Owusu', 'yaw@asanteautoparts.com', '{$pwd}', 'Manager', 1),
            (2, 'Linda Frimpong', 'linda@asanteautoparts.com', '{$pwd}', 'Cashier', 1),
            (3, 'Kwabena Sarpong', 'kwabena@asanteautoparts.com', '{$pwd}', 'Manager', 0);
        ");

        // 3. Categories
        $db->exec("
            INSERT INTO categories (name, slug, icon) VALUES
            ('Brakes & Friction', 'brakes', 'disc'),
            ('Filters & Intake', 'filters', 'air-vent'),
            ('Electrical & Batteries', 'batteries', 'zap'),
            ('Suspension & Steering', 'suspension', 'activity'),
            ('Ignition & Spark', 'ignition', 'flame'),
            ('Engine & Cooling', 'engine', 'cpu'),
            ('Fluids & Lubricants', 'fluids', 'droplet');
        ");

        // 4. Products & Fitments
        $db->exec("
            INSERT INTO products (category_id, name, sku, barcode, oem_number, fits_vehicles, cost_price, selling_price, stock_quantity, reorder_level, image_url) VALUES
            (1, 'Ceramic Brake Pad Set (Front)', 'BP-4471', '078945612301', '04465-02220', 'Toyota Corolla (2010-2019), Matrix, Auris 1.8L', 120.00, 185.00, 42, 10, NULL),
            (2, 'Spin-On Engine Oil Filter', 'OF-1029', '078945612302', '90915-YZZE1', 'Universal Japanese (Toyota, Nissan, Honda 1.3L-2.4L)', 22.00, 45.00, 6, 12, NULL),
            (3, 'Car Battery 12V 65Ah Heavy Duty', 'BT-6520', '078945612303', '56530-SMF', 'Hyundai Elantra, Kia Forte, Toyota RAV4, Honda CR-V', 550.00, 780.00, 18, 5, NULL),
            (4, 'Gas Shock Absorber (Rear Pair)', 'SA-3312', '078945612304', '55310-2H000', 'Hyundai Elantra (2007-2016), Kia Cerato', 210.00, 320.00, 27, 8, NULL),
            (5, 'Iridium Spark Plug (Set of 4)', 'SP-2201', '078945612305', 'IK20-5304', 'Universal Petrol 4-Cyl Engines (Toyota, Honda, Nissan)', 55.00, 96.00, 33, 10, NULL),
            (2, 'Engine Air Filter Element', 'AF-1187', '078945612306', '16546-ED000', 'Nissan Almera, Tiida, Latio 1.5L-1.8L', 18.00, 38.00, 5, 10, NULL),
            (6, 'Aluminum Radiator Assembly', 'RD-9021', '078945612307', '19010-R40-A51', 'Honda Accord (2008-2013) 2.4L Automatic', 420.00, 650.00, 9, 3, NULL),
            (7, 'Full Synthetic Engine Oil 5W-30 (4L)', 'FL-5304', '078945612308', 'SYN-5W30-4L', 'Universal High Performance Gasoline & Diesel', 180.00, 280.00, 24, 6, NULL);
        ");

        // 5. Branch Stock allocation
        $db->exec("
            INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level) VALUES
            (1, 1, 42, 10), (1, 2, 6, 12), (1, 3, 18, 5), (1, 4, 27, 8), (1, 5, 33, 10), (1, 6, 5, 10), (1, 7, 9, 3), (1, 8, 24, 6),
            (2, 1, 25, 10), (2, 2, 40, 12), (2, 3, 12, 5), (2, 4, 15, 8), (2, 5, 20, 10), (2, 6, 18, 10), (2, 7, 4, 3), (2, 8, 30, 6),
            (3, 1, 14, 10), (3, 2, 20, 12), (3, 3, 8, 5), (3, 4, 10, 8), (3, 5, 15, 10), (3, 6, 12, 10), (3, 7, 2, 3), (3, 8, 15, 6);
        ");

        // 6. Customers (Mechanics & Workshops)
        $db->exec("
            INSERT INTO customers (name, phone, workshop_name, credit_balance, credit_limit) VALUES
            ('Master Kojo Owusu', '+233 24 412 8890', 'Kojo Master Mechanic & AC Services', 420.00, 3000.00),
            ('Uncle Dan Mechanics', '+233 20 891 2234', 'Speedway Auto Garage Adum', 0.00, 2500.00),
            ('Chief Auto Diagnostics', '+233 27 554 9901', 'Chief Diagnostics & Tuneup Center', 850.00, 5000.00);
        ");

        // 7. Suppliers
        $db->exec("
            INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
            ('Accra Auto Parts Distributors Ltd', 'Alhaji Rashid', '+233 30 223 9901', 'sales@accraautoparts.com', 'Abossey Okai, Accra'),
            ('Tema Motor Spares Wholesale', 'Michael Boateng', '+233 30 330 4412', 'orders@temamotor.com', 'Heavy Industrial Area, Tema'),
            ('Kumasi Battery & Electrical Hub', 'Sister Akua', '+233 32 204 1189', 'akua@kumasibatteries.gh', 'Suame Magazine, Kumasi');
        ");

        // 8. Purchase Orders
        $db->exec("
            INSERT INTO purchase_orders (po_number, supplier_id, branch_id, product_id, quantity, unit_cost, total_cost, status, created_at) VALUES
            ('PO-2026-001', 1, 1, 2, 50, 22.00, 1100.00, 'Ordered', '2026-08-24 10:30:00'),
            ('PO-2026-002', 3, 1, 3, 15, 550.00, 8250.00, 'Received', '2026-08-23 14:15:00');
        ");

        // 9. Initial Shifts
        $db->exec("
            INSERT INTO shifts (user_id, branch_id, opened_at, opening_float, status) VALUES
            (1, 1, '2026-08-25 08:00:00', 300.00, 'Open');
        ");

        // 10. Default Settings
        $db->exec("
            INSERT INTO settings (setting_key, setting_value) VALUES
            ('dealership_name', 'SpareStack Auto Parts OS'),
            ('dealership_tagline', 'Original Genuine & OEM Parts'),
            ('phone', '+233 32 202 4491'),
            ('email', 'sales@sparestack.com'),
            ('address', 'Plot 14 Harper Road, Adum, Kumasi'),
            ('currency_symbol', 'GHS'),
            ('currency_name', 'Ghanaian Cedi'),
            ('vat_rate', '15.00'),
            ('receipt_footer', 'Thank you for your patronage. Valid receipt required for returns within 7 days. Electrical parts non-refundable.');
        ");
    }
}
