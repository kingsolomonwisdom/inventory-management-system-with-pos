<?php
require_once '../config/config.php';
requireLogin();

$conn = getConnection();
$message = '';

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Handle image upload
function uploadImage($file) {
    $fileName = basename($file['name']);
    $targetFilePath = UPLOAD_DIR . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    
    // Check if file is an image
    $allowedTypes = array('jpg', 'jpeg', 'png', 'gif');
    if (!in_array(strtolower($fileType), $allowedTypes)) {
        return array('success' => false, 'message' => 'Only JPG, JPEG, PNG, and GIF files are allowed.');
    }
    
    // Generate unique filename
    $fileName = uniqid() . '.' . $fileType;
    $targetFilePath = UPLOAD_DIR . $fileName;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        return array('success' => true, 'filename' => $fileName);
    } else {
        return array('success' => false, 'message' => 'There was an error uploading your file.');
    }
}

// Add new product
if (isset($_POST['add_product'])) {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $barcode = sanitize($_POST['barcode']);
    $image = NULL;
    
    // Generate SKU if empty
    if (empty($barcode)) {
        $barcode = generateSKU();
    }
    
    if (empty($name) || empty($category_id) || $price <= 0) {
        $_SESSION['message'] = displayError('Name, category, and price are required');
    } else {
        // Check if barcode already exists
        $stmt = $conn->prepare("SELECT id FROM products WHERE barcode = ? AND id != ?");
        $dummy_id = 0; // For new products
        $stmt->bind_param("si", $barcode, $dummy_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['message'] = displayError('A product with this barcode/SKU already exists. Please use a different barcode.');
            $stmt->close();
        } else {
            $stmt->close();
            
            // Upload image if provided
            if (!empty($_FILES['image']['name'])) {
                $upload = uploadImage($_FILES['image']);
                if ($upload['success']) {
                    $image = $upload['filename'];
                } else {
                    $_SESSION['message'] = displayError($upload['message']);
                    $image = NULL;
                }
            }
            
            // Calculate automatic low stock threshold based on quantity
            $low_stock_threshold = calculateLowStockThreshold($quantity);
            
            // Insert product
            $stmt = $conn->prepare("INSERT INTO products (name, description, category_id, quantity, price, barcode, low_stock_threshold, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiddsds", $name, $description, $category_id, $quantity, $price, $barcode, $low_stock_threshold, $image);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = displayAlert('Product added successfully');
            } else {
                $_SESSION['message'] = displayError('Error adding product: ' . $conn->error);
            }
            
            $stmt->close();
        }
    }
    
    // Redirect to GET request
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Delete product
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $force = isset($_GET['force']) && $_GET['force'] == 1;
    
    // Check if the product is used in any sales records
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sale_items WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $has_sales = ($row['count'] > 0);
    $stmt->close();
    
    if ($has_sales && !$force) {
        // Product has sales records and not forcing deletion - show warning
        $_SESSION['message'] = displayError('This product has sales records. <a href="?delete=' . $id . '&force=1" class="alert-link" onclick="return confirm(\'WARNING: Deleting this product will keep orphaned sales records in the database. This could cause reporting issues. Are you absolutely sure you want to continue?\')">Click here</a> to force delete or consider disabling it instead.');
    } else {
        // Either no sales records or forcing deletion - proceed with deletion
        
        // Get product image to delete
        $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['image'] && file_exists(UPLOAD_DIR . $row['image'])) {
                unlink(UPLOAD_DIR . $row['image']);
            }
        }
        $stmt->close();
        
        // Delete product
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($has_sales && $force) {
                $_SESSION['message'] = displayAlert('Product force deleted successfully. Note that orphaned sales records remain in the database.');
            } else {
                $_SESSION['message'] = displayAlert('Product deleted successfully');
            }
        } else {
            $_SESSION['message'] = displayError('Error deleting product: ' . $conn->error);
        }
        
        $stmt->close();
    }
    
    // Redirect to GET request (without delete parameter)
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Edit product
if (isset($_POST['edit_product'])) {
    $id = (int)$_POST['id'];
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $barcode = sanitize($_POST['barcode']);
    
    // Generate SKU if empty
    if (empty($barcode)) {
        $barcode = generateSKU();
    }
    
    if (empty($name) || empty($category_id) || $price <= 0) {
        $_SESSION['message'] = displayError('Name, category, and price are required');
    } else {
        // Check if barcode already exists for a different product
        $stmt = $conn->prepare("SELECT id FROM products WHERE barcode = ? AND id != ?");
        $stmt->bind_param("si", $barcode, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['message'] = displayError('A product with this barcode/SKU already exists. Please use a different barcode.');
            $stmt->close();
        } else {
            $stmt->close();
            
            // Get current product data
            $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();
            
            $image = $product['image'];
            
            // Upload new image if provided
            if (!empty($_FILES['image']['name'])) {
                $upload = uploadImage($_FILES['image']);
                if ($upload['success']) {
                    // Delete old image if exists
                    if ($image && file_exists(UPLOAD_DIR . $image)) {
                        unlink(UPLOAD_DIR . $image);
                    }
                    $image = $upload['filename'];
                } else {
                    $_SESSION['message'] = displayError($upload['message']);
                }
            }
            
            // Calculate automatic low stock threshold based on quantity
            $low_stock_threshold = calculateLowStockThreshold($quantity);
            
            // Update product
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, category_id = ?, quantity = ?, price = ?, barcode = ?, low_stock_threshold = ?, image = ? WHERE id = ?");
            $stmt->bind_param("ssiddsisi", $name, $description, $category_id, $quantity, $price, $barcode, $low_stock_threshold, $image, $id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = displayAlert('Product updated successfully');
            } else {
                $_SESSION['message'] = displayError('Error updating product: ' . $conn->error);
            }
            
            $stmt->close();
        }
    }
    
    // Redirect to GET request
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Toggle product status
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $current_status = $row['status'];
    $stmt->close();
    
    // Toggle status
    $new_status = ($current_status == 'active') ? 'disabled' : 'active';
    
    $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    
    if ($stmt->execute()) {
        $status_text = ($new_status == 'active') ? 'enabled' : 'disabled';
        $_SESSION['message'] = displayAlert("Product {$status_text} successfully");
    } else {
        $_SESSION['message'] = displayError('Error updating product status: ' . $conn->error);
    }
    
    $stmt->close();
    
    // Redirect to GET request
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Display any message stored in session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it
}

// Get all products
$products = [];
$result = $conn->query("SELECT p.*, c.name as category_name FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        ORDER BY p.status ASC, p.name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
}

// Get all categories for dropdown
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $result->free();
}

$conn->close();

// Function to generate unique SKU on server side
function generateSKU() {
    $conn = getConnection();
    $unique = false;
    $sku = '';
    
    while (!$unique) {
        // Generate prefix (3 uppercase letters)
        $prefix = '';
        for ($i = 0; $i < 3; $i++) {
            $prefix .= chr(rand(65, 90)); // ASCII codes for A-Z
        }
        
        // Generate number (6 digits)
        $number = mt_rand(100000, 999999);
        
        // Combine prefix and number
        $sku = $prefix . '-' . $number;
        
        // Check if SKU already exists
        $stmt = $conn->prepare("SELECT id FROM products WHERE barcode = ?");
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $unique = true;
        }
        
        $stmt->close();
    }
    
    return $sku;
}

// Function to calculate low stock threshold based on quantity
function calculateLowStockThreshold($quantity) {
    // Basic logic: 15% of current quantity, with a minimum of 5 and maximum of 100
    $threshold = max(5, min(100, ceil($quantity * 0.15)));
    
    // Adjust threshold based on quantity ranges for more appropriate values
    if ($quantity > 1000) {
        // For very high stock items, use a smaller percentage
        $threshold = ceil($quantity * 0.10);
    } elseif ($quantity > 500) {
        // For high stock items
        $threshold = ceil($quantity * 0.12);
    } elseif ($quantity < 50) {
        // For low stock items, use a higher percentage
        $threshold = max(3, ceil($quantity * 0.25));
    }
    
    return $threshold;
}
?>

<?php include '../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="mb-4"><i class="fas fa-boxes me-2"></i>Inventory Management</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="fas fa-plus-circle me-1"></i>Add New Product
        </button>
        <button type="button" class="btn btn-success ms-2" onclick="exportTableToCSV('inventoryTable', 'inventory_export_<?php echo date('Y-m-d'); ?>.csv')">
            <i class="fas fa-file-export me-1"></i>Export to CSV
        </button>
    </div>
</div>

<?php echo $message; ?>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover datatable" id="inventoryTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th data-export="false">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td>
                            <?php if ($product['image']): ?>
                                <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $product['image']; ?>" class="img-thumbnail" width="50" height="50" alt="<?php echo sanitize($product['name']); ?>">
                            <?php else: ?>
                                <div class="bg-light text-center py-2 px-3 rounded">
                                    <i class="fas fa-image text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo sanitize($product['name']); ?>
                            <?php if (isset($product['status']) && $product['status'] == 'disabled'): ?>
                                <span class="badge bg-secondary">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo sanitize($product['category_name']); ?></td>
                        <td><?php echo formatCurrency($product['price']); ?></td>
                        <td><?php echo $product['quantity']; ?></td>
                        <td>
                            <?php if ($product['quantity'] <= 0): ?>
                                <span class="badge bg-out-of-stock">Out of Stock</span>
                            <?php elseif ($product['quantity'] <= $product['low_stock_threshold']): ?>
                                <span class="badge bg-low-stock">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info view-btn" 
                                    data-id="<?php echo $product['id']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#viewProductModal">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-primary edit-btn" 
                                    data-id="<?php echo $product['id']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#editProductModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if (isset($product['status'])): ?>
                                <a href="?toggle_status=<?php echo $product['id']; ?>" class="btn btn-sm <?php echo ($product['status'] == 'active') ? 'btn-warning' : 'btn-success'; ?>" 
                                   title="<?php echo ($product['status'] == 'active') ? 'Disable' : 'Enable'; ?> this product">
                                    <i class="fas <?php echo ($product['status'] == 'active') ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                </a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this product?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Product Name*</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category*</label>
                                <select class="form-select select2" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php if (empty($categories)): ?>
                                        <option value="new_category">+ Add New Category</option>
                                    <?php else: ?>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo sanitize($category['name']); ?></option>
                                        <?php endforeach; ?>
                                        <option value="new_category">+ Add New Category</option>
                                    <?php endif; ?>
                                </select>
                                <div id="new_category_container" class="mt-2 d-none">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="new_category_name" placeholder="Enter new category name">
                                        <button class="btn btn-success" type="button" id="add_new_category_btn">Add</button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Price*</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="0">
                                <small class="form-text text-muted">Low stock levels are automatically calculated based on quantity.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="barcode" class="form-label">Barcode/SKU</label>
                                <input type="text" class="form-control" id="barcode" name="barcode">
                                <small class="form-text text-muted">A unique barcode will be generated automatically if left empty.</small>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Product Image</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <div id="imagePreview" class="mt-2"></div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="editProductForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Product Name*</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_category_id" class="form-label">Category*</label>
                                <select class="form-select select2" id="edit_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php if (empty($categories)): ?>
                                        <option value="new_category">+ Add New Category</option>
                                    <?php else: ?>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo sanitize($category['name']); ?></option>
                                        <?php endforeach; ?>
                                        <option value="new_category">+ Add New Category</option>
                                    <?php endif; ?>
                                </select>
                                <div id="edit_new_category_container" class="mt-2 d-none">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="edit_new_category_name" placeholder="Enter new category name">
                                        <button class="btn btn-success" type="button" id="edit_add_new_category_btn">Add</button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_price" class="form-label">Price*</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0">
                                <small class="form-text text-muted">Low stock levels are automatically calculated based on quantity.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_barcode" class="form-label">Barcode/SKU</label>
                                <input type="text" class="form-control" id="edit_barcode" name="barcode">
                            </div>
                            <div class="mb-3">
                                <label for="edit_image" class="form-label">Product Image</label>
                                <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                                <div id="edit_imagePreview" class="mt-2"></div>
                                <div class="form-text">Leave blank to keep current image</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_product" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Product Modal -->
<div class="modal fade" id="viewProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Product Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4" id="view_image_container">
                            <img src="" id="view_image" class="img-fluid rounded" alt="Product Image">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h4 id="view_name" class="mb-3"></h4>
                        <p><strong>Category:</strong> <span id="view_category"></span></p>
                        <p><strong>Price:</strong> <span id="view_price"></span></p>
                        <p><strong>Quantity:</strong> <span id="view_quantity"></span></p>
                        <p><strong>Barcode/SKU:</strong> <span id="view_barcode"></span></p>
                        <p><strong>Low Stock Alert:</strong> <span id="view_threshold"></span> units</p>
                        <p><strong>Status:</strong> <span id="view_status"></span></p>
                    </div>
                </div>
                <div class="mt-4">
                    <h5>Description</h5>
                    <p id="view_description"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary edit-from-view">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File preview for add form
    const imageInput = document.getElementById('image');
    const previewContainer = document.getElementById('imagePreview');
    
    if (imageInput) {
        imageInput.addEventListener('change', function() {
            previewImage(this, previewContainer);
        });
    }
    
    // Generate random SKU/barcode when Add Product modal is opened
    const addProductModal = document.getElementById('addProductModal');
    const barcodeInput = document.getElementById('barcode');
    
    if (addProductModal) {
        addProductModal.addEventListener('show.bs.modal', function() {
            if (barcodeInput && !barcodeInput.value) {
                barcodeInput.value = generateClientSKU();
            }
        });
    }
    
    // File preview for edit form
    const editImageInput = document.getElementById('edit_image');
    const editPreviewContainer = document.getElementById('edit_imagePreview');
    
    if (editImageInput) {
        editImageInput.addEventListener('change', function() {
            previewImage(this, editPreviewContainer);
        });
    }
    
    function previewImage(input, container) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                container.innerHTML = `<img src="${e.target.result}" class="img-fluid img-thumbnail" style="max-height: 150px;">`;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // View product details
    const viewBtns = document.querySelectorAll('.view-btn');
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.id;
            
            // Find product data in the table
            const row = this.closest('tr');
            const name = row.cells[2].textContent;
            const category = row.cells[3].textContent;
            const price = row.cells[4].textContent;
            const quantity = row.cells[5].textContent;
            
            // Get product image
            const imageElement = row.cells[1].querySelector('img');
            const imageSrc = imageElement ? imageElement.src : '';
            
            // Get detailed data via AJAX
            fetch(`<?php echo SITE_URL; ?>/pages/get_product.php?id=${productId}`)
                .then(response => response.json())
                .then(product => {
                    document.getElementById('view_name').textContent = name;
                    document.getElementById('view_category').textContent = category;
                    document.getElementById('view_price').textContent = price;
                    document.getElementById('view_quantity').textContent = quantity;
                    document.getElementById('view_barcode').textContent = product.barcode || 'N/A';
                    
                    // Calculate and display the threshold dynamically
                    const qty = parseInt(quantity);
                    let threshold = calculateLowStockThreshold(qty);
                    document.getElementById('view_threshold').textContent = threshold;
                    
                    document.getElementById('view_description').textContent = product.description || 'No description available';
                    
                    // Set status
                    const statusElement = document.getElementById('view_status');
                    if (parseInt(quantity) <= 0) {
                        statusElement.innerHTML = '<span class="badge bg-danger">Out of Stock</span>';
                    } else if (parseInt(quantity) <= threshold) {
                        statusElement.innerHTML = '<span class="badge bg-warning">Low Stock</span>';
                    } else {
                        statusElement.innerHTML = '<span class="badge bg-success">In Stock</span>';
                    }
                    
                    // Set image
                    const imageContainer = document.getElementById('view_image_container');
                    if (imageSrc) {
                        imageContainer.innerHTML = `<img src="${imageSrc}" class="img-fluid rounded" alt="Product Image">`;
                    } else {
                        imageContainer.innerHTML = `<div class="bg-light text-center py-5 rounded">
                                                    <i class="fas fa-image fa-4x text-muted"></i>
                                                    <p class="mt-3 text-muted">No image available</p>
                                                </div>`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching product details:', error);
                });
        });
    });
    
    // Edit product
    const editBtns = document.querySelectorAll('.edit-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.id;
            
            // Get product data via AJAX
            fetch(`<?php echo SITE_URL; ?>/pages/get_product.php?id=${productId}`)
                .then(response => response.json())
                .then(product => {
                    document.getElementById('edit_id').value = product.id;
                    document.getElementById('edit_name').value = product.name;
                    document.getElementById('edit_category_id').value = product.category_id;
                    document.getElementById('edit_price').value = product.price;
                    document.getElementById('edit_quantity').value = product.quantity;
                    document.getElementById('edit_barcode').value = product.barcode || '';
                    document.getElementById('edit_description').value = product.description || '';
                    
                    // Show current image preview
                    const imagePreview = document.getElementById('edit_imagePreview');
                    if (product.image) {
                        imagePreview.innerHTML = `<img src="<?php echo SITE_URL; ?>/uploads/${product.image}" class="img-fluid img-thumbnail" style="max-height: 150px;">`;
                    } else {
                        imagePreview.innerHTML = '';
                    }
                    
                    // Refresh Select2
                    $('#edit_category_id').trigger('change');
                })
                .catch(error => {
                    console.error('Error fetching product data:', error);
                });
        });
    });
    
    // Edit from view
    const editFromViewBtn = document.querySelector('.edit-from-view');
    if (editFromViewBtn) {
        editFromViewBtn.addEventListener('click', function() {
            $('#viewProductModal').modal('hide');
            $('#editProductModal').modal('show');
        });
    }
    
    // Initialize Select2 with custom template
    $('.select2').each(function() {
        $(this).select2({
            theme: 'bootstrap-5',
            dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : document.body,
            width: '100%'
        });
    });
    
    // Handle new category in add form
    const categorySelect = document.getElementById('category_id');
    const newCategoryContainer = document.getElementById('new_category_container');
    const newCategoryInput = document.getElementById('new_category_name');
    const addNewCategoryBtn = document.getElementById('add_new_category_btn');
    
    if (categorySelect) {
        $(categorySelect).on('change', function() {
            if (this.value === 'new_category') {
                newCategoryContainer.classList.remove('d-none');
                setTimeout(function() {
                    newCategoryInput.focus();
                }, 100);
            } else {
                newCategoryContainer.classList.add('d-none');
            }
        });
    }
    
    if (addNewCategoryBtn) {
        addNewCategoryBtn.addEventListener('click', addNewCategory);
    }
    
    function addNewCategory() {
        const categoryName = newCategoryInput.value.trim();
        if (categoryName) {
            // Send AJAX request to add new category
            fetch('<?php echo SITE_URL; ?>/pages/ajax/add_category.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'name=' + encodeURIComponent(categoryName)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new option to select
                    const newOption = new Option(categoryName, data.id);
                    
                    // Remove the "new_category" option temporarily
                    const newCategoryOption = Array.from(categorySelect.options)
                        .find(option => option.value === 'new_category');
                    if (newCategoryOption) {
                        categorySelect.removeChild(newCategoryOption);
                    }
                    
                    // Add new category option
                    categorySelect.appendChild(newOption);
                    
                    // Re-add the "new_category" option at the end
                    if (newCategoryOption) {
                        categorySelect.appendChild(newCategoryOption);
                    }
                    
                    // Refresh Select2
                    $(categorySelect).val(data.id).trigger('change');
                    
                    // Hide new category input
                    newCategoryContainer.classList.add('d-none');
                    
                    // Clear input
                    newCategoryInput.value = '';
                    
                    // Show success message
                    alert('Category "' + categoryName + '" added successfully');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error adding category:', error);
                alert('An error occurred while adding the category');
            });
        } else {
            alert('Please enter a category name');
            newCategoryInput.focus();
        }
    }
    
    // Allow pressing Enter in the category name input to add the category
    if (newCategoryInput) {
        newCategoryInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addNewCategory();
            }
        });
    }
    
    // Handle new category in edit form
    const editCategorySelect = document.getElementById('edit_category_id');
    const editNewCategoryContainer = document.getElementById('edit_new_category_container');
    const editNewCategoryInput = document.getElementById('edit_new_category_name');
    const editAddNewCategoryBtn = document.getElementById('edit_add_new_category_btn');
    
    if (editCategorySelect) {
        $(editCategorySelect).on('change', function() {
            if (this.value === 'new_category') {
                editNewCategoryContainer.classList.remove('d-none');
                setTimeout(function() {
                    editNewCategoryInput.focus();
                }, 100);
            } else {
                editNewCategoryContainer.classList.add('d-none');
            }
        });
    }
    
    if (editAddNewCategoryBtn) {
        editAddNewCategoryBtn.addEventListener('click', addEditNewCategory);
    }
    
    function addEditNewCategory() {
        const categoryName = editNewCategoryInput.value.trim();
        if (categoryName) {
            // Send AJAX request to add new category
            fetch('<?php echo SITE_URL; ?>/pages/ajax/add_category.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'name=' + encodeURIComponent(categoryName)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new option to select
                    const newOption = new Option(categoryName, data.id);
                    
                    // Remove the "new_category" option temporarily
                    const newCategoryOption = Array.from(editCategorySelect.options)
                        .find(option => option.value === 'new_category');
                    if (newCategoryOption) {
                        editCategorySelect.removeChild(newCategoryOption);
                    }
                    
                    // Add new category option
                    editCategorySelect.appendChild(newOption);
                    
                    // Re-add the "new_category" option at the end
                    if (newCategoryOption) {
                        editCategorySelect.appendChild(newCategoryOption);
                    }
                    
                    // Refresh Select2
                    $(editCategorySelect).val(data.id).trigger('change');
                    
                    // Hide new category input
                    editNewCategoryContainer.classList.add('d-none');
                    
                    // Clear input
                    editNewCategoryInput.value = '';
                    
                    // Show success message
                    alert('Category "' + categoryName + '" added successfully');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error adding category:', error);
                alert('An error occurred while adding the category');
            });
        } else {
            alert('Please enter a category name');
            editNewCategoryInput.focus();
        }
    }
    
    // Allow pressing Enter in the edit category name input to add the category
    if (editNewCategoryInput) {
        editNewCategoryInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addEditNewCategory();
            }
        });
    }
    
    // Function to generate a random SKU/barcode on client side
    function generateClientSKU() {
        // Generate random prefix (3 uppercase letters)
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        let prefix = '';
        for (let i = 0; i < 3; i++) {
            prefix += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        // Generate random number (6 digits)
        const number = Math.floor(100000 + Math.random() * 900000);
        
        // Combine prefix and number with a hyphen
        return `${prefix}-${number}`;
    }
    
    // Client-side function to match the PHP version for displaying threshold
    function calculateLowStockThreshold(quantity) {
        // Basic logic: 15% of current quantity, with a minimum of 5 and maximum of 100
        let threshold = Math.max(5, Math.min(100, Math.ceil(quantity * 0.15)));
        
        // Adjust threshold based on quantity ranges for more appropriate values
        if (quantity > 1000) {
            // For very high stock items, use a smaller percentage
            threshold = Math.ceil(quantity * 0.10);
        } else if (quantity > 500) {
            // For high stock items
            threshold = Math.ceil(quantity * 0.12);
        } else if (quantity < 50) {
            // For low stock items, use a higher percentage
            threshold = Math.max(3, Math.ceil(quantity * 0.25));
        }
        
        return threshold;
    }
});
</script> 