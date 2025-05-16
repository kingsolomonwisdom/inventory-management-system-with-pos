<?php
require_once '../config/config.php';
requireLogin();

$conn = getConnection();
$message = '';
$receiptData = null;

// Process checkout
if (isset($_POST['checkout'])) {
    $cart_items = json_decode($_POST['cart_items'], true);
    $customer_name = sanitize($_POST['customer_name']);
    
    if (empty($cart_items)) {
        $message = displayError('Cart is empty. Please add items before checkout.');
    } else {
        // Validate stock levels before processing
        $stockError = false;
        $errorMessage = '';
        
        foreach ($cart_items as $item) {
            $product_id = $item['id'];
            $quantity = $item['quantity'];
            
            // Check current stock
            $stmt = $conn->prepare("SELECT name, quantity FROM products WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                if ($quantity > $row['quantity']) {
                    $stockError = true;
                    $errorMessage .= "Not enough stock for '" . $row['name'] . "'. Available: " . $row['quantity'] . ", Requested: " . $quantity . "<br>";
                }
            }
            $stmt->close();
        }
        
        if ($stockError) {
            $message = displayError('Stock level error: <br>' . $errorMessage);
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Generate invoice number
                $invoice_number = generateInvoiceNumber();
                
                // Calculate total
                $total_amount = 0;
                foreach ($cart_items as $item) {
                    $total_amount += $item['price'] * $item['quantity'];
                }
                
                // Insert sale
                $stmt = $conn->prepare("INSERT INTO sales (invoice_number, customer_name, user_id, total_amount) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssid", $invoice_number, $customer_name, $_SESSION['user_id'], $total_amount);
                $stmt->execute();
                $sale_id = $conn->insert_id;
                $stmt->close();
                
                // Insert sale items and update inventory
                foreach ($cart_items as $item) {
                    $product_id = $item['id'];
                    $product_name = $item['name'];
                    $quantity = $item['quantity'];
                    $price = $item['price'];
                    
                    // Insert sale item
                    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iisid", $sale_id, $product_id, $product_name, $quantity, $price);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Update inventory
                    $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                    $stmt->bind_param("ii", $quantity, $product_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Get sale data for receipt
                $receiptData = [
                    'invoice_number' => $invoice_number,
                    'date' => date('Y-m-d H:i:s'),
                    'customer_name' => $customer_name ?: 'Walk-in Customer',
                    'items' => $cart_items,
                    'total' => $total_amount,
                    'cashier' => $_SESSION['username']
                ];
                
                $message = displayAlert('Sale completed successfully!');
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $message = displayError('Error processing sale: ' . $e->getMessage());
            }
        }
    }
}

// Get all products for POS
$products = [];
$result = $conn->query("SELECT p.id, p.name, p.price, p.quantity, p.image, c.name as category_name, 
                        c.id as category_id 
                        FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE p.quantity > 0 AND p.status = 'active'
                        ORDER BY p.name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
}

// Get categories for filter
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $result->free();
}

$conn->close();
?>

<?php include '../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-lg-8">
        <h2 class="mb-4"><i class="fas fa-cash-register me-2"></i>Point of Sale</h2>
    </div>
    <div class="col-lg-4 text-lg-end">
        <div class="input-group">
            <input type="text" class="form-control" id="searchProduct" placeholder="Search products...">
            <select class="form-select" id="categoryFilter" style="max-width: 150px;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>"><?php echo sanitize($category['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<?php echo $message; ?>

<!-- Main POS Container -->
<div class="pos-container">
    <!-- Products Section -->
    <div class="col-lg-8 mb-4">
        <div class="table-container h-100 p-0">
            <div class="p-3 border-bottom">
                <div class="product-grid" id="productsContainer">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card" 
                            data-id="<?php echo $product['id']; ?>" 
                            data-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" 
                            data-price="<?php echo $product['price']; ?>" 
                            data-category="<?php echo $product['category_name']; ?>" 
                            data-category-id="<?php echo $product['category_id']; ?>"
                            data-maxstock="<?php echo $product['quantity']; ?>">
                            <?php if ($product['image']): ?>
                                <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $product['image']; ?>" class="product-card-img w-100" alt="<?php echo sanitize($product['name']); ?>">
                            <?php else: ?>
                                <div class="product-card-img w-100 bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-box fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-card-body">
                                <div class="product-card-title"><?php echo sanitize($product['name']); ?></div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="product-card-price"><?php echo formatCurrency($product['price']); ?></div>
                                    <small class="text-muted"><?php echo $product['quantity']; ?> in stock</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cart Section -->
    <div class="col-lg-4 mb-4">
        <div class="cart-container">
            <h4 class="mb-3"><i class="fas fa-shopping-cart me-2"></i>Current Sale</h4>
            
            <div class="cart-items" id="cart-items">
                <!-- Cart items will be inserted here via JavaScript -->
            </div>
            
            <div class="cart-summary">
                <div class="cart-total mb-3">
                    <span>Total:</span>
                    <span id="cart-total">$0.00</span>
                </div>
                
                <form id="checkout-form" method="POST" action="">
                    <input type="hidden" id="cart-items-input" name="cart_items" value="">
                    
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Customer Name (Optional)</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Walk-in Customer">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-danger" onclick="clearCart()">
                            <i class="fas fa-trash-alt me-1"></i>Clear Cart
                        </button>
                        <button type="submit" name="checkout" id="checkout-btn" class="btn btn-success" disabled>
                            <i class="fas fa-check-circle me-1"></i>Complete Sale
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="receipt">
                    <div class="receipt-header">
                        <h5 class="mb-1"><?php echo SITE_NAME; ?></h5>
                        <p class="mb-1">Invoice #: <span id="receipt-invoice"></span></p>
                        <p class="mb-1">Date: <span id="receipt-date"></span></p>
                        <p class="mb-1">Customer: <span id="receipt-customer"></span></p>
                        <p class="mb-0">Cashier: <span id="receipt-cashier"></span></p>
                    </div>
                    
                    <div class="receipt-items">
                        <div id="receipt-items-container"></div>
                    </div>
                    
                    <div class="receipt-total mb-3">
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <span id="receipt-total"></span>
                        </div>
                    </div>
                    
                    <div class="receipt-footer">
                        <p class="mb-1">Thank you for your purchase!</p>
                        <p class="mb-0">Please come again</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="print-receipt">
                    <i class="fas fa-print me-1"></i>Print Receipt
                </button>
                <a href="<?php echo SITE_URL; ?>/pages/pos.php" class="btn btn-success">
                    <i class="fas fa-plus-circle me-1"></i>New Sale
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show receipt modal if transaction was completed
    <?php if ($receiptData): ?>
    const receiptData = <?php echo json_encode($receiptData); ?>;
    showReceipt(receiptData);
    clearCart();
    <?php endif; ?>
    
    // Update cart on page load
    updateCartDisplay();
    
    // Product search
    const searchInput = document.getElementById('searchProduct');
    const categoryFilter = document.getElementById('categoryFilter');
    const products = document.querySelectorAll('.product-card');
    
    searchInput.addEventListener('input', filterProducts);
    categoryFilter.addEventListener('change', filterProducts);
    
    function filterProducts() {
        const searchValue = searchInput.value.toLowerCase();
        const categoryValue = categoryFilter.value;
        
        products.forEach(product => {
            const productName = product.dataset.name.toLowerCase();
            const productCategory = product.dataset.categoryId;
            
            const matchesSearch = productName.includes(searchValue);
            const matchesCategory = categoryValue === '' || productCategory === categoryValue;
            
            product.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
        });
    }
    
    // Show receipt function
    function showReceipt(data) {
        document.getElementById('receipt-invoice').textContent = data.invoice_number;
        document.getElementById('receipt-date').textContent = new Date(data.date).toLocaleString();
        document.getElementById('receipt-customer').textContent = data.customer_name;
        document.getElementById('receipt-cashier').textContent = data.cashier;
        document.getElementById('receipt-total').textContent = formatCurrency(data.total);
        
        // Add receipt items
        const itemsContainer = document.getElementById('receipt-items-container');
        itemsContainer.innerHTML = '';
        
        data.items.forEach(item => {
            const itemTotal = item.price * item.quantity;
            const itemElement = document.createElement('div');
            itemElement.className = 'receipt-item';
            itemElement.innerHTML = `
                <div>
                    <div>${item.name} (${item.quantity} x $${item.price.toFixed(2)})</div>
                </div>
                <div>$${itemTotal.toFixed(2)}</div>
            `;
            itemsContainer.appendChild(itemElement);
        });
        
        // Show modal
        const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
        receiptModal.show();
    }
});
</script> 