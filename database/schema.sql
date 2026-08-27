-- ==========================================================
-- Torque Auto Parts OS - Relational Database Schema
-- Compatible with MySQL 5.7+ / 8.0+ / MariaDB / SQLite
-- ==========================================================

CREATE TABLE IF NOT EXISTS `branches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('Owner', 'Manager', 'Cashier') NOT NULL DEFAULT 'Cashier',
    `is_online` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT 'cog'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT DEFAULT NULL,
    `name` VARCHAR(200) NOT NULL,
    `sku` VARCHAR(100) UNIQUE NOT NULL,
    `barcode` VARCHAR(100) DEFAULT NULL,
    `oem_number` VARCHAR(100) DEFAULT NULL,
    `fits_vehicles` TEXT DEFAULT NULL,
    `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `reorder_level` INT NOT NULL DEFAULT 10,
    `image_url` LONGTEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `branch_stock` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `reorder_level` INT NOT NULL DEFAULT 10,
    UNIQUE KEY `branch_prod_unique` (`branch_id`, `product_id`),
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `change_qty` INT NOT NULL,
    `previous_qty` INT NOT NULL,
    `new_qty` INT NOT NULL,
    `reason` VARCHAR(100) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `workshop_name` VARCHAR(150) DEFAULT NULL,
    `credit_balance` DECIMAL(10,2) DEFAULT 0.00,
    `credit_limit` DECIMAL(10,2) DEFAULT 2000.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) UNIQUE NOT NULL,
    `branch_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `customer_id` INT DEFAULT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `vat_amount` DECIMAL(10,2) NOT NULL,
    `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
    `grand_total` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('Cash', 'Card', 'MoMo', 'Transfer', 'Credit') NOT NULL,
    `payment_ref` VARCHAR(100) DEFAULT NULL,
    `status` VARCHAR(30) DEFAULT 'Completed',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `sku` VARCHAR(100) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `cost_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_number` VARCHAR(50) UNIQUE NOT NULL,
    `supplier_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_cost` DECIMAL(10,2) NOT NULL,
    `total_cost` DECIMAL(10,2) NOT NULL,
    `status` ENUM('Draft', 'Ordered', 'Received', 'Cancelled') NOT NULL DEFAULT 'Ordered',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `received_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `opened_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `closed_at` DATETIME DEFAULT NULL,
    `opening_float` DECIMAL(10,2) NOT NULL DEFAULT 300.00,
    `closing_cash_counted` DECIMAL(10,2) DEFAULT NULL,
    `expected_cash` DECIMAL(10,2) DEFAULT NULL,
    `cash_variance` DECIMAL(10,2) DEFAULT NULL,
    `status` ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
