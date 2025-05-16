<?php
require_once '../../config/config.php';
requireLogin();

$conn = getConnection();

// Get low stock products
$products = [];
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.quantity <= p.low_stock_threshold 
        ORDER BY p.quantity ASC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
}

$conn->close();
?>

<?php include '../../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="mb-4"><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Report</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-success" onclick="exportTableToCSV('lowStockTable', 'low_stock_report_<?php echo date('Y-m-d'); ?>.csv')">
            <i class="fas fa-file-export me-1"></i>Export to CSV
        </button>
    </div>
</div>

<div class="table-container">
    <?php if (count($products) > 0): ?>
        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong><?php echo count($products); ?> products</strong> are below their minimum stock threshold.
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover datatable" id="lowStockTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Current Quantity</th>
                        <th>Threshold</th>
                        <th>Status</th>
                        <th data-export="false">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td><?php echo sanitize($product['name']); ?></td>
                            <td><?php echo sanitize($product['category_name']); ?></td>
                            <td><?php echo $product['quantity']; ?></td>
                            <td><?php echo $product['low_stock_threshold']; ?></td>
                            <td>
                                <?php if ($product['quantity'] <= 0): ?>
                                    <span class="badge bg-danger">Out of Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Low Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary edit-stock-btn" 
                                        data-id="<?php echo $product['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>"
                                        data-quantity="<?php echo $product['quantity']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#updateStockModal">
                                    <i class="fas fa-edit"></i> Update Stock
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            Good news! All products are adequately stocked.
        </div>
    <?php endif; ?>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo SITE_URL; ?>/pages/update_stock.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="product_id" name="product_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="product_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_quantity" class="form-label">Current Quantity</label>
                        <input type="number" class="form-control" id="current_quantity" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_quantity" class="form-label">New Quantity</label>
                        <input type="number" class="form-control" id="new_quantity" name="new_quantity" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set update stock modal values
    const editStockBtns = document.querySelectorAll('.edit-stock-btn');
    editStockBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const quantity = this.dataset.quantity;
            
            document.getElementById('product_id').value = id;
            document.getElementById('product_name').value = name;
            document.getElementById('current_quantity').value = quantity;
            document.getElementById('new_quantity').value = quantity;
            document.getElementById('new_quantity').min = 0;
            document.getElementById('new_quantity').focus();
        });
    });
});
</script> 