<?php
require_once '../../config/config.php';
requireLogin();

$conn = getConnection();

// Get filter values
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";

$params = [];
$types = "";

if ($category_id > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

if ($status === 'in_stock') {
    $sql .= " AND p.quantity > p.low_stock_threshold";
} elseif ($status === 'low_stock') {
    $sql .= " AND p.quantity > 0 AND p.quantity <= p.low_stock_threshold";
} elseif ($status === 'out_of_stock') {
    $sql .= " AND p.quantity <= 0";
}

$sql .= " ORDER BY p.name";

// Get products
$products = [];
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$stmt->close();

// Get all categories for filter
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $result->free();
}

// Calculate totals
$totalProducts = count($products);
$totalValue = 0;
$totalItems = 0;

foreach ($products as $product) {
    $totalValue += $product['price'] * $product['quantity'];
    $totalItems += $product['quantity'];
}

$conn->close();
?>

<?php include '../../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="mb-4"><i class="fas fa-clipboard-list me-2"></i>Inventory Report</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-success" onclick="exportTableToCSV('inventoryTable', 'inventory_report_<?php echo date('Y-m-d'); ?>.csv')">
            <i class="fas fa-file-export me-1"></i>Export to CSV
        </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="table-container">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="in_stock" <?php echo ($status == 'in_stock') ? 'selected' : ''; ?>>In Stock</option>
                        <option value="low_stock" <?php echo ($status == 'low_stock') ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out_of_stock" <?php echo ($status == 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="<?php echo SITE_URL; ?>/pages/reports/inventory.php" class="btn btn-secondary">Reset</a>
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
                    <i class="fas fa-tags"></i>
                </div>
                <h3><?php echo $totalProducts; ?></h3>
                <p class="mb-0">Total Products</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="dashboard-card bg-success text-white">
            <div class="card-body p-4">
                <div class="dashboard-icon">
                    <i class="fas fa-cubes"></i>
                </div>
                <h3><?php echo $totalItems; ?></h3>
                <p class="mb-0">Total Items</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="dashboard-card bg-info text-white">
            <div class="card-body p-4">
                <div class="dashboard-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h3><?php echo formatCurrency($totalValue); ?></h3>
                <p class="mb-0">Inventory Value</p>
            </div>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover datatable" id="inventoryTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td><?php echo sanitize($product['name']); ?></td>
                        <td><?php echo sanitize($product['category_name']); ?></td>
                        <td><?php echo formatCurrency($product['price']); ?></td>
                        <td><?php echo $product['quantity']; ?></td>
                        <td><?php echo formatCurrency($product['price'] * $product['quantity']); ?></td>
                        <td>
                            <?php if ($product['quantity'] <= 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($product['quantity'] <= $product['low_stock_threshold']): ?>
                                <span class="badge bg-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
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
    // Additional DataTable configuration if needed
});
</script> 