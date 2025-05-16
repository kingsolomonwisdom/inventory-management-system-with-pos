<?php
// Set timezone explicitly for PHP
date_default_timezone_set('UTC');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventory_system');

// Improved function to get database connection with better error handling
function getConnection() {
    try {
        // Create connection
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            error_log("Database Connection Error: " . $conn->connect_error);
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset for proper encoding
        if (!$conn->set_charset("utf8mb4")) {
            error_log("Error setting charset utf8mb4: " . $conn->error);
        }
        
        // Set timezone in MySQL to match PHP
        $timezone = date_default_timezone_get();
        $conn->query("SET time_zone = '+00:00'");
        
        // Check for and add missing columns (for upgrading existing databases)
        checkAndUpdateTableStructure($conn);
        
        return $conn;
    } catch (Exception $e) {
        // Log the error but don't expose details to the user
        error_log("Database Error: " . $e->getMessage());
        die("A database connection error occurred. Please try again later or contact support.");
    }
}

// Initial setup - create database if it doesn't exist
function initializeDatabase() {
    try {
        // Connect without specifying database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            error_log("Connection Error: " . $conn->connect_error);
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Create database if not exists
        $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
        if ($conn->query($sql) === FALSE) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        // Select the database
        $conn->select_db(DB_NAME);
        
        // Create tables
        createTables($conn);
        
        // Add default admin user if none exists
        createDefaultAdmin($conn);
        
        $conn->close();
        return true;
    } catch (Exception $e) {
        error_log("Database Initialization Error: " . $e->getMessage());
        return false;
    }
}

// Function to create tables
function createTables($conn) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        "CREATE TABLE IF NOT EXISTS `categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        "CREATE TABLE IF NOT EXISTS `products` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `description` text,
            `category_id` int(11) NOT NULL,
            `quantity` int(11) NOT NULL DEFAULT '0',
            `price` decimal(10,2) NOT NULL,
            `image` varchar(255) DEFAULT NULL,
            `barcode` varchar(50) DEFAULT NULL,
            `low_stock_threshold` int(11) DEFAULT '10',
            `status` enum('active','disabled') NOT NULL DEFAULT 'active',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `category_id` (`category_id`),
            CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        "CREATE TABLE IF NOT EXISTS `sales` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `invoice_number` varchar(50) NOT NULL,
            `customer_name` varchar(100) DEFAULT NULL,
            `user_id` int(11) NOT NULL,
            `total_amount` decimal(10,2) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        "CREATE TABLE IF NOT EXISTS `sale_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `sale_id` int(11) NOT NULL,
            `product_id` int(11) NULL,
            `product_name` varchar(100) NOT NULL,
            `quantity` int(11) NOT NULL,
            `price` decimal(10,2) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `sale_id` (`sale_id`),
            KEY `product_id` (`product_id`),
            CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
            CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($tables as $table) {
        if ($conn->query($table) === FALSE) {
            throw new Exception("Error creating table: " . $conn->error);
        }
    }
}

// Function to create default admin user
function createDefaultAdmin($conn) {
    $check_admin = "SELECT * FROM users WHERE username = 'admin'";
    $result = $conn->query($check_admin);
    
    if ($result && $result->num_rows == 0) {
        $hashed_password = password_hash('admin', PASSWORD_DEFAULT);
        $insert_admin = "INSERT INTO users (username, password, role) VALUES ('admin', '$hashed_password', 'admin')";
        
        if ($conn->query($insert_admin) === FALSE) {
            throw new Exception("Error creating admin user: " . $conn->error);
        }
    }
}

// Function to check and update table structure
function checkAndUpdateTableStructure($conn) {
    try {
        // Check if status column exists in products table
        $result = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
        if ($result && $result->num_rows == 0) {
            // Add status column if it doesn't exist
            $sql = "ALTER TABLE products ADD COLUMN `status` enum('active','disabled') NOT NULL DEFAULT 'active' AFTER `low_stock_threshold`";
            $conn->query($sql);
        }
        
        // Check if product_name column exists in sale_items table
        $result = $conn->query("SHOW COLUMNS FROM sale_items LIKE 'product_name'");
        if ($result && $result->num_rows == 0) {
            // First disable foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS=0");
            
            // Add product_name column
            $sql = "ALTER TABLE sale_items ADD COLUMN `product_name` varchar(100) NOT NULL AFTER `product_id`";
            $conn->query($sql);
            
            // Update product_name from products table for existing records
            $sql = "UPDATE sale_items si JOIN products p ON si.product_id = p.id SET si.product_name = p.name";
            $conn->query($sql);
            
            // Modify product_id to allow NULL
            $sql = "ALTER TABLE sale_items MODIFY COLUMN `product_id` int(11) NULL";
            $conn->query($sql);
            
            // Drop the existing foreign key constraint
            $result = $conn->query("SHOW CREATE TABLE sale_items");
            if ($result && $row = $result->fetch_assoc()) {
                $createTable = $row['Create Table'];
                if (preg_match('/CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY/', $createTable)) {
                    $sql = "ALTER TABLE sale_items DROP FOREIGN KEY `sale_items_ibfk_2`";
                    $conn->query($sql);
                }
            }
            
            // Add back the foreign key with ON DELETE SET NULL
            $sql = "ALTER TABLE sale_items ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL";
            $conn->query($sql);
            
            // Re-enable foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS=1");
        }
    } catch (Exception $e) {
        error_log("Table structure update error: " . $e->getMessage());
    }
}

// Initialize database on first load
initializeDatabase();
?> 