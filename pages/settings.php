<?php
require_once '../config/config.php';
requireAdmin(); // Only administrators can access settings

$conn = getConnection();
$message = '';

// Process data reset requests
if (isset($_POST['reset_data']) && isAdmin()) {
    $reset_type = $_POST['reset_type'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    
    if ($confirmation !== 'CONFIRM') {
        $message = displayError('Please type CONFIRM to proceed with data reset.');
    } else {
        $conn->begin_transaction();
        
        try {
            switch ($reset_type) {
                case 'sales':
                    // Clear sales data
                    $conn->query("DELETE FROM sale_items");
                    $conn->query("DELETE FROM sales");
                    $message = displayAlert('All sales data has been reset successfully.');
                    break;
                    
                case 'inventory':
                    // Reset inventory quantities
                    $conn->query("UPDATE products SET quantity = 0");
                    $message = displayAlert('All inventory quantities have been reset to zero.');
                    break;
                    
                case 'all':
                    // Reset everything except users
                    $conn->query("DELETE FROM sale_items");
                    $conn->query("DELETE FROM sales");
                    $conn->query("DELETE FROM products");
                    $conn->query("DELETE FROM categories");
                    // Add default category
                    $conn->query("INSERT INTO categories (name) VALUES ('Default Category')");
                    $message = displayAlert('All system data has been reset successfully.');
                    break;
                    
                default:
                    $message = displayError('Invalid reset type selected.');
            }
            
            $conn->commit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = displayError('Error resetting data: ' . $e->getMessage());
        }
    }
}

$conn->close();
?>

<?php include '../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-4"><i class="fas fa-cog me-2"></i>System Settings</h2>
    </div>
</div>

<?php echo $message; ?>

<div class="row">
    <!-- Theme section -->
    <div class="col-lg-6 mb-4">
        <div class="table-container h-100">
            <h4 class="mb-4"><i class="fas fa-palette me-2"></i>Theme Settings</h4>
            
            <p>Current color palette:</p>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center">
                            <div style="width: 40px; height: 40px; background-color: #106a87;" class="rounded me-2"></div>
                            <div>
                                <strong>Primary</strong>
                                <div class="small text-muted">#106a87</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div style="width: 40px; height: 40px; background-color: #375f74;" class="rounded me-2"></div>
                            <div>
                                <strong>Primary Dark</strong>
                                <div class="small text-muted">#375f74</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div style="width: 40px; height: 40px; background-color: #e02842;" class="rounded me-2"></div>
                            <div>
                                <strong>Accent</strong>
                                <div class="small text-muted">#e02842</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center">
                            <div style="width: 40px; height: 40px; background-color: #f48c77;" class="rounded me-2"></div>
                            <div>
                                <strong>Accent Light</strong>
                                <div class="small text-muted">#f48c77</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div style="width: 40px; height: 40px; background-color: #d1aba0;" class="rounded me-2"></div>
                            <div>
                                <strong>Neutral</strong>
                                <div class="small text-muted">#d1aba0</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div style="width: 40px; height: 40px; background-color: #69a1b1;" class="rounded me-2"></div>
                            <div>
                                <strong>Secondary</strong>
                                <div class="small text-muted">#69a1b1</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <p>Preview of themed elements:</p>
            
            <div class="row mb-4">
                <div class="col-md-6 d-flex flex-column gap-2">
                    <button class="btn btn-primary">Primary Button</button>
                    <button class="btn btn-danger">Danger Button</button>
                    <button class="btn btn-success">Success Button</button>
                    <span class="badge bg-out-of-stock mb-1">Out of Stock</span>
                    <span class="badge bg-low-stock mb-1">Low Stock</span>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-primary">Primary Alert</div>
                    <div class="alert alert-danger">Danger Alert</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Data Reset Section -->
    <div class="col-lg-6 mb-4">
        <div class="table-container h-100">
            <h4 class="mb-4 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Reset Data</h4>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Warning:</strong> Resetting data is irreversible. Please back up your data before proceeding.
            </div>
            
            <form method="POST" action="" onsubmit="return confirm('Are you ABSOLUTELY sure you want to reset the selected data? This action CANNOT be undone!');">
                <div class="mb-3">
                    <label for="reset_type" class="form-label">Select Reset Type</label>
                    <select class="form-select" id="reset_type" name="reset_type" required>
                        <option value="" selected disabled>-- Select Reset Type --</option>
                        <option value="sales">Reset Sales Data Only</option>
                        <option value="inventory">Reset Inventory Quantities Only</option>
                        <option value="all">Reset All System Data (Except Users)</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="confirmation" class="form-label">Type "CONFIRM" to proceed</label>
                    <input type="text" class="form-control" id="confirmation" name="confirmation" required placeholder="CONFIRM">
                    <div class="form-text text-danger">This action cannot be undone!</div>
                </div>
                
                <div class="d-grid mt-4">
                    <button type="submit" name="reset_data" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i>Reset Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <!-- System Maintenance Section -->
    <div class="col-lg-6 mb-4">
        <div class="table-container h-100">
            <h4 class="mb-4"><i class="fas fa-sync-alt me-2"></i>System Maintenance</h4>
            
            <div class="mb-4">
                <p>Use the button below to run the data synchronization tool to ensure all sales data is correctly synchronized and consistent throughout the system.</p>
                
                <a href="<?php echo SITE_URL; ?>/admin_sync.php" class="btn btn-primary">
                    <i class="fas fa-sync me-1"></i>Synchronize Data
                </a>
            </div>
        </div>
    </div>
    
    <!-- System Information Section -->
    <div class="col-lg-6 mb-4">
        <div class="table-container h-100">
            <h4 class="mb-4"><i class="fas fa-info-circle me-2"></i>System Information</h4>
            
            <table class="table">
                <tr>
                    <td><strong>System Version:</strong></td>
                    <td>1.0.0</td>
                </tr>
                <tr>
                    <td><strong>PHP Version:</strong></td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <td><strong>Database:</strong></td>
                    <td>MySQL</td>
                </tr>
                <tr>
                    <td><strong>Server:</strong></td>
                    <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make the confirmation field only enabled when a reset type is selected
    const resetTypeSelect = document.getElementById('reset_type');
    const confirmationInput = document.getElementById('confirmation');
    
    if (resetTypeSelect && confirmationInput) {
        confirmationInput.disabled = true;
        
        resetTypeSelect.addEventListener('change', function() {
            confirmationInput.disabled = !this.value;
            if (this.value) {
                confirmationInput.focus();
            }
        });
    }
});
</script> 