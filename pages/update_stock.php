<?php
require_once '../config/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . '/pages/inventory.php');
    exit;
}

if (!isset($_POST['product_id']) || !isset($_POST['new_quantity'])) {
    header('Location: ' . SITE_URL . '/pages/inventory.php?error=Missing+required+fields');
    exit;
}

$product_id = (int)$_POST['product_id'];
$new_quantity = (int)$_POST['new_quantity'];

if ($new_quantity < 0) {
    header('Location: ' . SITE_URL . '/pages/inventory.php?error=Quantity+cannot+be+negative');
    exit;
}

$conn = getConnection();

$stmt = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
$stmt->bind_param("ii", $new_quantity, $product_id);

if ($stmt->execute()) {
    header('Location: ' . SITE_URL . '/pages/inventory.php?success=Stock+updated+successfully');
} else {
    header('Location: ' . SITE_URL . '/pages/inventory.php?error=Error+updating+stock:+' . $conn->error);
}

$stmt->close();
$conn->close();
exit;
?> 