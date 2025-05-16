<?php
require_once 'config/config.php';
requireLogin();

// Get total products count
$conn = getConnection();
$totalProducts = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM products");
if ($result) {
    $totalProducts = $result->fetch_assoc()['count'];
}

// Get low stock products count
$lowStockProducts = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= low_stock_threshold");
if ($result) {
    $lowStockProducts = $result->fetch_assoc()['count'];
}

// Get categories count
$totalCategories = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM categories");
if ($result) {
    $totalCategories = $result->fetch_assoc()['count'];
}

// Get total users
$totalUsers = 0;
if (isAdmin()) {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $totalUsers = $result->fetch_assoc()['count'];
    }
}

// Get total sales amount
$totalSales = 0;
$result = $conn->query("SELECT SUM(total_amount) as total FROM sales");
if ($result) {
    $row = $result->fetch_assoc();
    if ($row['total'] !== null) {
        $totalSales = $row['total'];
    }
}

// Get today's sales
$todaySales = 0;
$result = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at) = CURDATE()");
if ($result) {
    $row = $result->fetch_assoc();
    if ($row['total'] !== null) {
        $todaySales = $row['total'];
    }
}

// Get recent sales
$recentSales = [];
$result = $conn->query("SELECT s.id, s.invoice_number, s.customer_name, s.total_amount, s.created_at, u.username 
                        FROM sales s 
                        JOIN users u ON s.user_id = u.id 
                        ORDER BY s.created_at DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentSales[] = $row;
    }
}

// Get low stock products
$lowStockItems = [];
$result = $conn->query("SELECT id, name, quantity, low_stock_threshold 
                        FROM products 
                        WHERE quantity <= low_stock_threshold 
                        ORDER BY quantity ASC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lowStockItems[] = $row;
    }
}

// Get sales data for chart (last 7 days)
$salesData = [];
$salesLabels = [];
$salesAmounts = [];

$result = $conn->query("SELECT DATE(created_at) as date, SUM(total_amount) as total 
                        FROM sales 
                        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
                        GROUP BY DATE(created_at) 
                        ORDER BY DATE(created_at)");
if ($result) {
    // Create array with all dates in last 7 days
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $salesLabels[] = date('M d', strtotime($date));
        $salesData[$date] = 0;
    }
    
    // Fill in actual data
    while ($row = $result->fetch_assoc()) {
        $salesData[$row['date']] = $row['total'];
    }
    
    // Convert to array for chart
    $salesAmounts = array_values($salesData);
}

$conn->close();
?>

<?php include 'includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="dashboard-card bg-primary text-white h-100 py-4 px-4">
            <div class="dashboard-card-header">
                <h5>Total Sales</h5>
                <div class="dashboard-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="mt-2">
                <h3><?php echo formatCurrency($totalSales); ?></h3>
                <div class="text-white-50">Lifetime sales</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="dashboard-card bg-success text-white h-100 py-4 px-4">
            <div class="dashboard-card-header">
                <h5>Today's Sales</h5>
                <div class="dashboard-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="mt-2">
                <h3><?php echo formatCurrency($todaySales); ?></h3>
                <div class="text-white-50">Today's revenue</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="dashboard-card bg-info text-white h-100 py-4 px-4">
            <div class="dashboard-card-header">
                <h5>Total Products</h5>
                <div class="dashboard-icon">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            <div class="mt-2">
                <h3><?php echo $totalProducts; ?></h3>
                <div class="text-white-50"><?php echo $totalCategories; ?> categories</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="dashboard-card bg-warning text-white h-100 py-4 px-4">
            <div class="dashboard-card-header">
                <h5>Low Stock Alert</h5>
                <div class="dashboard-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="mt-2">
                <h3><?php echo $lowStockProducts; ?></h3>
                <div class="text-white-50">Items below threshold</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="table-container h-100">
            <h4 class="mb-4"><i class="fas fa-chart-line me-2"></i>Sales Trend (Last 7 Days)</h4>
            <div class="chart-container">
                <canvas id="salesChart" class="chart-canvas"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <div class="table-container h-100">
            <h4 class="mb-4"><i class="fas fa-exclamation-circle me-2"></i>Low Stock Items</h4>
            <?php if (count($lowStockItems) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockItems as $item): ?>
                                <tr>
                                    <td><?php echo sanitize($item['name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>
                                        <?php if ($item['quantity'] == 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="<?php echo SITE_URL; ?>/pages/reports/low_stock.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>All items are well stocked!
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="table-container">
            <h4 class="mb-4"><i class="fas fa-receipt me-2"></i>Recent Sales</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Staff</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentSales) > 0): ?>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><?php echo sanitize($sale['invoice_number']); ?></td>
                                    <td><?php echo sanitize($sale['customer_name'] ?: 'Walk-in Customer'); ?></td>
                                    <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                    <td><?php echo formatDateTime($sale['created_at']); ?></td>
                                    <td><?php echo sanitize($sale['username']); ?></td>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/pages/view_sale.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No sales recorded yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <a href="<?php echo SITE_URL; ?>/pages/sales.php" class="btn btn-sm btn-outline-primary">View All Sales</a>
                <a href="<?php echo SITE_URL; ?>/pages/pos.php" class="btn btn-sm btn-success ms-2">
                    <i class="fas fa-plus-circle me-1"></i>New Sale
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales chart
    const salesCtx = document.getElementById('salesChart');
    
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($salesLabels); ?>,
                datasets: [{
                    label: 'Sales Amount',
                    data: <?php echo json_encode($salesAmounts); ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$ ' + context.raw.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$ ' + value;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>