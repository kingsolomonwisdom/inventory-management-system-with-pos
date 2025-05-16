<?php
require_once '../config/config.php';
requireAdmin();

$conn = getConnection();
$message = '';

// Add new category
if (isset($_POST['add_category'])) {
    $name = sanitize($_POST['name']);
    
    if (empty($name)) {
        $message = displayError('Category name is required');
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        
        if ($stmt->execute()) {
            $message = displayAlert('Category added successfully');
        } else {
            $message = displayError('Error adding category: ' . $conn->error);
        }
        
        $stmt->close();
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if category is being used
    $check = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    $check->close();
    
    if ($row['count'] > 0) {
        $message = displayError('Cannot delete category: It is being used by ' . $row['count'] . ' product(s)');
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = displayAlert('Category deleted successfully');
        } else {
            $message = displayError('Error deleting category: ' . $conn->error);
        }
        
        $stmt->close();
    }
}

// Edit category
if (isset($_POST['edit_category'])) {
    $id = (int)$_POST['id'];
    $name = sanitize($_POST['name']);
    
    if (empty($name)) {
        $message = displayError('Category name is required');
    } else {
        $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        
        if ($stmt->execute()) {
            $message = displayAlert('Category updated successfully');
        } else {
            $message = displayError('Error updating category: ' . $conn->error);
        }
        
        $stmt->close();
    }
}

// Get all categories
$categories = [];
$result = $conn->query("SELECT * FROM categories ORDER BY name");
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
    <div class="col-md-6">
        <h2 class="mb-4"><i class="fas fa-tags me-2"></i>Categories</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus-circle me-1"></i>Add New Category
        </button>
    </div>
</div>

<?php echo $message; ?>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo $category['id']; ?></td>
                        <td><?php echo sanitize($category['name']); ?></td>
                        <td><?php echo formatDate($category['created_at']); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary edit-btn" 
                                    data-id="<?php echo $category['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>"
                                    data-bs-toggle="modal" data-bs-target="#editCategoryModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this category?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set edit modal values
    const editBtns = document.querySelectorAll('.edit-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
        });
    });
});
</script> 