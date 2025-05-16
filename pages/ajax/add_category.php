<?php
require_once '../../config/config.php';
requireLogin();

// Set content type to JSON
header('Content-Type: application/json');

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate and sanitize input
if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
    echo json_encode(['success' => false, 'message' => 'Category name is required']);
    exit;
}

$name = sanitize($_POST['name']);

// Check if category already exists
$conn = getConnection();
$stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
$stmt->bind_param("s", $name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'A category with this name already exists']);
    $stmt->close();
    $conn->close();
    exit;
}

// Insert new category
$stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
$stmt->bind_param("s", $name);

if ($stmt->execute()) {
    $category_id = $conn->insert_id;
    echo json_encode(['success' => true, 'id' => $category_id, 'name' => $name]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error adding category: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?> 