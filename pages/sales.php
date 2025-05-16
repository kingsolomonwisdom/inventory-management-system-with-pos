<?php
require_once '../config/config.php';
requireLogin();

$conn = getConnection();
$message = '';

// Delete sale
if (isset($_GET['delete']) && isAdmin()) {
    $id = (int)$_GET['delete'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get sale items to restore inventory
        $stmt = $conn->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $saleItems = [];
        while ($row = $result->fetch_assoc()) {
            $saleItems[] = $row;
        }
        $stmt->close();
        
        // Restore inventory
        foreach ($saleItems as $item) {
            $stmt = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
            $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Delete sale items
        $stmt = $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Delete sale
        $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $message = displayAlert('Sale deleted successfully and inventory restored');
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = displayError('Error deleting sale: ' . $e->getMessage());
    }
}

// Get date range filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // Start of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Get sales with date filter
$sales = [];
$sql = "SELECT s.id, s.invoice_number, s.customer_name, s.total_amount, s.created_at, u.username 
        FROM sales s 
        JOIN users u ON s.user_id = u.id 
        WHERE DATE(s.created_at) BETWEEN ? AND ? 
        ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
}
$stmt->close();

// Calculate total sales amount
$totalSales = 0;
foreach ($sales as $sale) {
    $totalSales += $sale['total_amount'];
}

$conn->close();
?>

<?php include '../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i>Sales History</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="<?php echo SITE_URL; ?>/pages/pos.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-1"></i>New Sale
        </a>
        <button type="button" class="btn btn-success ms-2" onclick="exportTableToCSV('salesTable', 'sales_report_<?php echo date('Y-m-d'); ?>.csv')">
            <i class="fas fa-file-export me-1"></i>Export to CSV
        </button>
    </div>
</div>

<?php echo $message; ?>

<div class="table-container">
    <div class="row mb-4">
        <div class="col-md-6">
            <form method="GET" action="" class="row g-3">
                <div class="col-sm-5">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-sm-5">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-sm-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
        <div class="col-md-6 text-md-end d-flex align-items-end justify-content-md-end">
            <h5 class="mb-0">Total Sales: <span class="text-primary"><?php echo formatCurrency($totalSales); ?></span></h5>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover datatable" id="salesTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Staff</th>
                    <th data-export="false">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><?php echo $sale['id']; ?></td>
                        <td><?php echo sanitize($sale['invoice_number']); ?></td>
                        <td><?php echo sanitize($sale['customer_name'] ?: 'Walk-in Customer'); ?></td>
                        <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                        <td><?php echo formatDateTime($sale['created_at']); ?></td>
                        <td><?php echo sanitize($sale['username']); ?></td>
                        <td>
                            <a href="<?php echo SITE_URL; ?>/pages/view_sale.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if (isAdmin()): ?>
                                <a href="?delete=<?php echo $sale['id']; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this sale? This will restore items to inventory.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validate date range
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    endDateInput.addEventListener('change', function() {
        if (startDateInput.value > endDateInput.value) {
            alert('End date cannot be earlier than start date');
            endDateInput.value = startDateInput.value;
        }
    });
    
    startDateInput.addEventListener('change', function() {
        if (startDateInput.value > endDateInput.value) {
            endDateInput.value = startDateInput.value;
        }
    });
});
</script> 