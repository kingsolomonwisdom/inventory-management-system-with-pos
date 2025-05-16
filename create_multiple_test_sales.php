<?php
// Test Data Generator Tool
require_once 'config/config.php';

// Only allow access when logged in
requireLogin();

// Only admin users can run this script
if (!isAdmin()) {
    die("Access denied. Only administrators can create test data.");
}

$action = isset($_GET['action']) ? $_GET['action'] : 'form';
$message = '';

if ($action === 'generate' && isset($_POST['submit'])) {
    try {
        $conn = getConnection();
        $conn->begin_transaction();
        
        $days = isset($_POST['days']) ? (int)$_POST['days'] : 7;
        $products = isset($_POST['products']) ? (bool)$_POST['products'] : false;
        
        // Create products and categories if needed or requested
        if ($products) {
            createTestProducts($conn);
        }
        
        // Create sales for the specified number of days
        $salesCreated = createTestSales($conn, $days);
        
        // Commit all changes
        $conn->commit();
        
        $message = "<div class='alert alert-success'>
            <strong>Success!</strong> Created " . count($salesCreated) . " test sales across $days days.
        </div>";
        
        $conn->close();
    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_errno === 0) {
            $conn->rollback();
            $conn->close();
        }
        $message = "<div class='alert alert-danger'>
            <strong>Error:</strong> " . $e->getMessage() . "
        </div>";
    }
}

// Output header
include 'includes/templates/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-database me-2"></i>Test Data Generator</h4>
                </div>
                <div class="card-body">
                    <?php echo $message; ?>
                    
                    <?php if ($action === 'form'): ?>
                        <p>Use this tool to generate test data for the system. This will create products and sales records to help with testing.</p>
                        
                        <form method="post" action="?action=generate" class="mb-4">
                            <div class="mb-3">
                                <label for="days" class="form-label">Number of days to generate sales for:</label>
                                <input type="number" class="form-control" id="days" name="days" min="1" max="30" value="7">
                                <div class="form-text">Creates 1-3 sales per day for the past X days</div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="products" name="products" value="1">
                                <label class="form-check-label" for="products">Create test products if none exist</label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="submit" class="btn btn-primary">Generate Test Data</button>
                                <a href="index.php" class="btn btn-secondary">Return to Dashboard</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <a href="test_data.php" class="btn btn-primary">Generate More Data</a>
                            <a href="index.php" class="btn btn-secondary">Return to Dashboard</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/templates/footer.php';

// Function to create test products if needed
function createTestProducts($conn) {
    // Check if we have any products
    $result = $conn->query("SELECT COUNT(*) as count FROM products");
    $productCount = $result->fetch_assoc()['count'];
    
    if ($productCount == 0) {
        // Create a test category if none exists
        $result = $conn->query("SELECT id FROM categories LIMIT 1");
        if ($result->num_rows == 0) {
            $conn->query("INSERT INTO categories (name) VALUES ('Test Category')");
            $categoryId = $conn->insert_id;
        } else {
            $categoryId = $result->fetch_assoc()['id'];
        }
        
        // Create multiple test products
        $products = [
            ['Test Product 1', 'This is test product 1', 100, 19.99],
            ['Test Product 2', 'This is test product 2', 50, 29.99],
            ['Test Product 3', 'This is test product 3', 200, 9.99]
        ];
        
        foreach ($products as $product) {
            $sql = "INSERT INTO products (name, description, category_id, quantity, price, low_stock_threshold) 
                    VALUES (?, ?, ?, ?, ?, 10)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssidi", $product[0], $product[1], $categoryId, $product[2], $product[3]);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Function to create test sales for different dates
function createTestSales($conn, $days = 7) {
    // Get the current user's ID
    $userId = $_SESSION['user_id'];
    
    // Get product IDs
    $productIds = [];
    $result = $conn->query("SELECT id, name, price FROM products LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        $productIds[] = $row;
    }
    
    if (empty($productIds)) {
        throw new Exception("No products found to create sales. Please create products first.");
    }
    
    // Create sales for the last X days with different amounts
    $salesCreated = [];
    for ($i = ($days - 1); $i >= 0; $i--) {
        // Create 1-3 sales for each day
        $salesPerDay = rand(1, 3);
        
        for ($j = 0; $j < $salesPerDay; $j++) {
            $date = date('Y-m-d H:i:s', strtotime("-$i days"));
            $invoiceNumber = 'TEST-' . date('Ymd', strtotime("-$i days")) . '-' . ($j + 1);
            $customerName = "Test Customer " . ($j + 1);
            
            // Random amount between $50 and $500
            $totalAmount = rand(5000, 50000) / 100;
            
            // Insert the sale with specific date
            $sql = "INSERT INTO sales (invoice_number, customer_name, user_id, total_amount, created_at) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisd", $invoiceNumber, $customerName, $userId, $totalAmount, $date);
            $stmt->execute();
            $saleId = $conn->insert_id;
            $stmt->close();
            
            // Add 1-3 sale items
            $itemCount = rand(1, 3);
            $actualTotal = 0;
            
            for ($k = 0; $k < $itemCount; $k++) {
                // Pick a random product
                $product = $productIds[array_rand($productIds)];
                $quantity = rand(1, 5);
                $price = $product['price'];
                $itemTotal = $quantity * $price;
                $actualTotal += $itemTotal;
                
                $sql = "INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisdd", $saleId, $product['id'], $product['name'], $quantity, $price);
                $stmt->execute();
                $stmt->close();
                
                // Update inventory
                $sql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $quantity, $product['id']);
                $stmt->execute();
                $stmt->close();
            }
            
            // Update the sale to match the actual total from items
            $sql = "UPDATE sales SET total_amount = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $actualTotal, $saleId);
            $stmt->execute();
            $stmt->close();
            
            $salesCreated[] = [
                'id' => $saleId,
                'date' => $date,
                'amount' => $actualTotal
            ];
        }
    }
    
    return $salesCreated;
}
?> 