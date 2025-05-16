<?php
require_once '../config/config.php';
requireLogin();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'Product ID is required']);
    exit;
}

$id = (int)$_GET['id'];
$conn = getConnection();

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Return product data as JSON
echo json_encode($product);
exit;
?> 