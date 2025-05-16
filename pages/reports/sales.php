<?php
require_once '../../config/config.php';
requireLogin();

$conn = getConnection();

// Get date range filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // Start of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
$staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

// Get sales data for the time period
$sales = [];
$sql = "SELECT s.id, s.invoice_number, s.customer_name, s.total_amount, s.created_at, u.username, u.id as user_id
        FROM sales s 
        JOIN users u ON s.user_id = u.id 
        WHERE DATE(s.created_at) BETWEEN ? AND ?";
        
$params = [$startDate, $endDate];
$types = "ss";

if ($staff_id > 0) {
    $sql .= " AND s.user_id = ?";
    $params[] = $staff_id;
    $types .= "i";
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
}
$stmt->close();

// Get sales data for chart - group by day
$salesByDay = [];
$sql = "SELECT DATE(created_at) as sale_date, SUM(total_amount) as total 
        FROM sales 
        WHERE DATE(created_at) BETWEEN ? AND ?";
        
$params = [$startDate, $endDate];
$types = "ss";

if ($staff_id > 0) {
    $sql .= " AND user_id = ?";
    $params[] = $staff_id;
    $types .= "i";
}

$sql .= " GROUP BY DATE(created_at) ORDER BY DATE(created_at)";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$chartDates = [];
$chartAmounts = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $chartDates[] = date('M d', strtotime($row['sale_date']));
        $chartAmounts[] = $row['total'];
        $salesByDay[$row['sale_date']] = $row['total'];
    }
}
$stmt->close();

// Get top selling products
$topProducts = [];
$sql = "SELECT p.id, p.name, SUM(si.quantity) as total_quantity, SUM(si.quantity * si.price) as total_revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?";
        
$params = [$startDate, $endDate];
$types = "ss";

if ($staff_id > 0) {
    $sql .= " AND s.user_id = ?";
    $params[] = $staff_id;
    $types .= "i";
}

$sql .= " GROUP BY p.id
        ORDER BY total_quantity DESC
        LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topProducts[] = $row;
    }
}
$stmt->close();

// Calculate totals
$totalSales = 0;
$saleCount = count($sales);

foreach ($sales as $sale) {
    $totalSales += $sale['total_amount'];
}

$averageSale = $saleCount > 0 ? $totalSales / $saleCount : 0;

// Get all staff for filter
$staff = [];
$result = $conn->query("SELECT id, username FROM users ORDER BY username");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    $result->free();
}

$conn->close();
?>

<?php include '../../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i>Sales Report</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-success" onclick="exportTableToCSV('salesTable', 'sales_report_<?php echo date('Y-m-d'); ?>.csv')">
            <i class="fas fa-file-export me-1"></i>Export to CSV
        </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="table-container">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-3">
                    <label for="staff_id" class="form-label">Staff</label>
                    <select class="form-select" id="staff_id" name="staff_id">
                        <option value="0">All Staff</option>
                        <?php foreach ($staff as $person): ?>
                            <option value="<?php echo $person['id']; ?>" <?php echo ($staff_id == $person['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize($person['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="<?php echo SITE_URL; ?>/pages/reports/sales.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-4">
        <div class="dashboard-card bg-primary text-white">
            <div class="card-body p-4">
                <div class="dashboard-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h3><?php echo formatCurrency($totalSales); ?></h3>
                <p class="mb-0">Total Sales</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="dashboard-card bg-success text-white">
            <div class="card-body p-4">
                <div class="dashboard-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <h3><?php echo $saleCount; ?></h3>
                <p class="mb-0">Number of Sales</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="dashboard-card bg-info text-white">
            <div class="card-body p-4">
                <div class="dashboard-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <h3><?php echo formatCurrency($averageSale); ?></h3>
                <p class="mb-0">Average Sale</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="table-container">
            <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Sales Trend</h4>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <div class="table-container">
            <h4 class="mb-4"><i class="fas fa-trophy me-2"></i>Top Selling Products</h4>
            <?php if (count($topProducts) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td><?php echo sanitize($product['name']); ?></td>
                                    <td><?php echo $product['total_quantity']; ?></td>
                                    <td><?php echo formatCurrency($product['total_revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No sales data available for this period.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="table-container">
    <h4 class="mb-4"><i class="fas fa-list me-2"></i>Sales List</h4>
    
    <div class="table-responsive">
        <table class="table table-hover datatable" id="salesTable">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Staff</th>
                    <th>Amount</th>
                    <th data-export="false">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><?php echo sanitize($sale['invoice_number']); ?></td>
                        <td><?php echo formatDateTime($sale['created_at']); ?></td>
                        <td><?php echo sanitize($sale['customer_name'] ?: 'Walk-in Customer'); ?></td>
                        <td><?php echo sanitize($sale['username']); ?></td>
                        <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                        <td>
                            <a href="<?php echo SITE_URL; ?>/pages/view_sale.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/templates/footer.php'; ?>

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
    
    // Sales chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartDates); ?>,
                datasets: [{
                    label: 'Sales Amount',
                    data: <?php echo json_encode($chartAmounts); ?>,
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