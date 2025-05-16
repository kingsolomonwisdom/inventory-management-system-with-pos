<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once 'database.php';

// Site configuration
define('SITE_NAME', 'Inventory Management System');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/inventory-management-system');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/inventory-management-system/uploads/');
define('EXPORT_DIR', $_SERVER['DOCUMENT_ROOT'] . '/inventory-management-system/exports/');

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function isStaff() {
    return isLoggedIn() && $_SESSION['role'] === 'staff';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php?error=unauthorized');
        exit;
    }
}

// Helper functions
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999));
}

function displayAlert($message, $type = 'success') {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

function displayError($error) {
    return displayAlert($error, 'danger');
}

function redirectTo($location) {
    header("Location: $location");
    exit;
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $user;
}

// Format functions
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}
?> 