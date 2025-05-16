<?php
require_once '../config/config.php';
requireLogin();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ' . SITE_URL . '/pages/sales.php?error=Invalid+sale+ID');
    exit;
}

$sale_id = (int)$_GET['id'];
$conn = getConnection();

// Get sale data
$sale = null;
$stmt = $conn->prepare("SELECT s.*, u.username FROM sales s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . SITE_URL . '/pages/sales.php?error=Sale+not+found');
    exit;
}

$sale = $result->fetch_assoc();
$stmt->close();

// Get sale items
$items = [];
$stmt = $conn->prepare("SELECT si.*, 
                        CASE 
                            WHEN p.id IS NULL THEN si.product_name 
                            ELSE p.name 
                        END as name,
                        p.image 
                        FROM sale_items si 
                        LEFT JOIN products p ON si.product_id = p.id 
                        WHERE si.sale_id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

$conn->close();
?>

<?php include '../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="mb-4"><i class="fas fa-receipt me-2"></i>Sale Details</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="<?php echo SITE_URL; ?>/pages/sales.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Sales
        </a>
        <button type="button" class="btn btn-primary ms-2" id="print-receipt">
            <i class="fas fa-print me-1"></i>Print Receipt
        </button>
    </div>
</div>

<div class="row">
    <!-- Sale Details -->
    <div class="col-lg-4 mb-4">
        <div class="table-container h-100">
            <h4 class="mb-4"><i class="fas fa-info-circle me-2"></i>Sale Information</h4>
            
            <div class="mb-4">
                <p class="mb-2"><strong>Invoice Number:</strong> <?php echo sanitize($sale['invoice_number']); ?></p>
                <p class="mb-2"><strong>Date:</strong> <?php echo formatDateTime($sale['created_at']); ?></p>
                <p class="mb-2"><strong>Customer:</strong> <?php echo sanitize($sale['customer_name'] ?: 'Walk-in Customer'); ?></p>
                <p class="mb-2"><strong>Staff:</strong> <?php echo sanitize($sale['username']); ?></p>
                <p class="mb-2"><strong>Total Amount:</strong> <span class="text-primary fw-bold"><?php echo formatCurrency($sale['total_amount']); ?></span></p>
            </div>
            
            <?php if (isAdmin()): ?>
                <div class="mt-4">
                    <a href="<?php echo SITE_URL; ?>/pages/sales.php?delete=<?php echo $sale_id; ?>" 
                       class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to delete this sale? This will restore items to inventory.')">
                        <i class="fas fa-trash me-1"></i>Delete Sale
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sale Items -->
    <div class="col-lg-8 mb-4">
        <div class="table-container h-100">
            <h4 class="mb-4"><i class="fas fa-shopping-basket me-2"></i>Sale Items</h4>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="d-flex align-items-center">
                                    <?php if ($item['image']): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image']; ?>" class="img-thumbnail me-2" width="40" height="40" alt="<?php echo sanitize($item['name']); ?>">
                                    <?php else: ?>
                                        <div class="bg-light text-center me-2 rounded" style="width: 40px; height: 40px; line-height: 40px;">
                                            <i class="fas fa-box text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <?php echo sanitize($item['name']); ?>
                                </td>
                                <td><?php echo formatCurrency($item['price']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo formatCurrency($item['price'] * $item['quantity']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total:</th>
                            <th><?php echo formatCurrency($sale['total_amount']); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Receipt for printing -->
<div class="receipt print-only">
    <div class="receipt-header">
        <h4 class="mb-1"><?php echo SITE_NAME; ?></h4>
        <p class="mb-1">Invoice #: <?php echo sanitize($sale['invoice_number']); ?></p>
        <p class="mb-1">Date: <?php echo formatDateTime($sale['created_at']); ?></p>
        <p class="mb-1">Customer: <?php echo sanitize($sale['customer_name'] ?: 'Walk-in Customer'); ?></p>
        <p class="mb-0">Cashier: <?php echo sanitize($sale['username']); ?></p>
    </div>
    
    <div class="receipt-items">
        <?php foreach ($items as $item): ?>
            <div class="receipt-item">
                <div><?php echo sanitize($item['name']); ?> (<?php echo $item['quantity']; ?> x <?php echo formatCurrency($item['price']); ?>)</div>
                <div><?php echo formatCurrency($item['price'] * $item['quantity']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="receipt-total">
        <div class="d-flex justify-content-between">
            <strong>Total:</strong>
            <span><?php echo formatCurrency($sale['total_amount']); ?></span>
        </div>
    </div>
    
    <div class="receipt-footer">
        <p class="mb-1">Thank you for your purchase!</p>
        <p class="mb-0">Please come again</p>
    </div>
</div>

<?php include '../includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Print receipt
    const printReceiptBtn = document.getElementById('print-receipt');
    if (printReceiptBtn) {
        printReceiptBtn.addEventListener('click', function() {
            window.print();
        });
    }
});
</script> 