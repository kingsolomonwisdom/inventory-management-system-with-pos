<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventory_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === FALSE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db(DB_NAME);

// Create tables if they don't exist
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
        `product_id` int(11) NOT NULL,
        `quantity` int(11) NOT NULL,
        `price` decimal(10,2) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `sale_id` (`sale_id`),
        KEY `product_id` (`product_id`),
        CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
        CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

// Execute each table creation query
foreach ($tables as $table) {
    if ($conn->query($table) === FALSE) {
        die("Error creating table: " . $conn->error);
    }
}

// Add default admin user if none exists
$check_admin = "SELECT * FROM users WHERE username = 'admin'";
$result = $conn->query($check_admin);

if ($result->num_rows == 0) {
    $hashed_password = password_hash('admin', PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO users (username, password, role) VALUES ('admin', '$hashed_password', 'admin')";
    
    if ($conn->query($insert_admin) === FALSE) {
        die("Error creating admin user: " . $conn->error);
    }
}

// Close connection
$conn->close();

// Function to get database connection
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}
?> 