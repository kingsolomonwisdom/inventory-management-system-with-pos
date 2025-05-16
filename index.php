<?php
require_once 'config/config.php';
requireLogin();

// Debug - Print any MySQL connection errors
$conn = getConnection();
if ($conn->connect_errno) {
    echo "Failed to connect to MySQL: " . $conn->connect_error;
    exit();
}

// Verify database connection by checking tables
$result = $conn->query("SHOW TABLES");
if (!$result) {
    echo "Error checking tables: " . $conn->error;
    exit();
}

// Get total products count
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

// Debug - Log database schema version
error_log("Checking sales data in dashboard");

// Get total sales amount
$totalSales = 0;
$result = $conn->query("SELECT SUM(total_amount) as total FROM sales");
if ($result && $row = $result->fetch_assoc()) {
    $totalSales = $row['total'] ?: 0;
}
error_log("Total sales amount: " . $totalSales);

// Get today's sales
$todaySales = 0;
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $todaySales = $row['total'] ?: 0;
}
$stmt->close();
error_log("Today's sales amount: " . $todaySales);

// Get recent sales
$recentSales = [];
$stmt = $conn->prepare("SELECT s.id, s.invoice_number, s.customer_name, s.total_amount, s.created_at, u.username 
                      FROM sales s 
                      JOIN users u ON s.user_id = u.id 
                      ORDER BY s.created_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentSales[] = $row;
    }
}
$stmt->close();

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

// Create array with all dates in last 7 days
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $salesLabels[] = date('M d', strtotime($date));
    $salesData[$date] = 0;
}

// Debug log the date range for chart
$startDate = date('Y-m-d', strtotime("-6 days"));
$endDate = date('Y-m-d');
error_log("Chart date range: $startDate to $endDate");

// Get chart data with explicit date conversion to account for timezone differences
$sql = "SELECT DATE(created_at) as date, SUM(total_amount) as total 
      FROM sales 
      WHERE DATE(created_at) BETWEEN ? AND ?
      GROUP BY DATE(created_at) 
      ORDER BY DATE(created_at)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// Fill in actual data
if ($result) {
    error_log("Found " . $result->num_rows . " days with sales for the chart");
    while ($row = $result->fetch_assoc()) {
        error_log("Chart data: " . $row['date'] . " = " . $row['total']);
        $salesData[$row['date']] = (float)$row['total'];
    }
} else {
    error_log("Chart query error: " . $conn->error);
}
$stmt->close();

// Convert to array for chart
$salesAmounts = array_values($salesData);
error_log("Chart data points: " . implode(", ", $salesAmounts));

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
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="salesChart"></canvas>
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
                <?php if (count($recentSales) > 0): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Staff</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><?php echo sanitize($sale['invoice_number']); ?></td>
                                    <td><?php echo sanitize($sale['customer_name'] ?: 'Walk-in Customer'); ?></td>
                                    <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                    <td><?php echo formatDateTime($sale['created_at']); ?></td>
                                    <td><?php echo sanitize($sale['username']); ?></td>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/pages/view_sale.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No recent sales found.
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <a href="<?php echo SITE_URL; ?>/pages/sales.php" class="btn btn-sm btn-outline-primary">View All Sales</a>
                <?php if (count($recentSales) == 0): ?>
                    <a href="<?php echo SITE_URL; ?>/pages/pos.php" class="btn btn-sm btn-primary ms-2">Create New Sale</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales Chart with improved rendering and data handling
    var ctx = document.getElementById('salesChart').getContext('2d');
    var chartLabels = <?php echo json_encode($salesLabels); ?>;
    var chartData = <?php echo json_encode($salesAmounts); ?>;
    
    console.log("Chart labels:", chartLabels);
    console.log("Chart data:", chartData);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Sales',
                data: chartData,
                backgroundColor: 'rgba(37, 120, 152, 0.2)',
                borderColor: 'rgba(37, 120, 152, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(37, 120, 152, 1)',
                pointRadius: 4,
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$' + Number(context.parsed.y).toFixed(2);
                        }
                    }
                },
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>