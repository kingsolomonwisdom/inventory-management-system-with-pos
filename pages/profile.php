<?php
require_once '../config/config.php';
requireLogin();

$conn = getConnection();
$message = '';
$user_id = $_SESSION['user_id'];

// Get user data
$user = null;
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    header('Location: ' . SITE_URL . '/logout.php');
    exit;
}
$stmt->close();

// Update profile
if (isset($_POST['update_profile'])) {
    $username = sanitize($_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($username)) {
        $_SESSION['message'] = displayError('Username is required');
    } else {
        // Check if username exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['message'] = displayError('Username already exists');
        } else {
            // If changing password
            if (!empty($new_password)) {
                // Verify current password
                if (!password_verify($current_password, $user['password'])) {
                    $_SESSION['message'] = displayError('Current password is incorrect');
                } elseif ($new_password !== $confirm_password) {
                    $_SESSION['message'] = displayError('New passwords do not match');
                } else {
                    // Update username and password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $username, $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['username'] = $username;
                        $_SESSION['message'] = displayAlert('Profile updated successfully');
                    } else {
                        $_SESSION['message'] = displayError('Error updating profile: ' . $conn->error);
                    }
                }
            } else {
                // Update username only
                $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->bind_param("si", $username, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['username'] = $username;
                    $_SESSION['message'] = displayAlert('Profile updated successfully');
                } else {
                    $_SESSION['message'] = displayError('Error updating profile: ' . $conn->error);
                }
            }
        }
        
        $stmt->close();
    }
    
    // Redirect to GET request
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Display any message stored in session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it
}

// Get user data (after possible update)
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get user stats
$total_sales = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total_amount) as total FROM sales WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_sales_count = $row['count'] ? $row['count'] : 0;
    $total_sales_amount = $row['total'] ? $row['total'] : 0;
}
$stmt->close();

$conn->close();
?>

<?php include '../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="mb-4"><i class="fas fa-user-circle me-2"></i>My Profile</h2>
    </div>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="table-container h-100">
            <div class="text-center mb-4">
                <div class="bg-light mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                    <i class="fas fa-user fa-3x text-primary"></i>
                </div>
                <h4><?php echo sanitize($user['username']); ?></h4>
                <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'success'; ?>"><?php echo ucfirst($user['role']); ?></span>
                <p class="text-muted mt-2">Member since <?php echo formatDate($user['created_at']); ?></p>
            </div>
            
            <hr>
            
            <div class="row text-center">
                <div class="col-6">
                    <h5 class="mb-0"><?php echo $total_sales_count; ?></h5>
                    <p class="text-muted">Total Sales</p>
                </div>
                <div class="col-6">
                    <h5 class="mb-0"><?php echo formatCurrency($total_sales_amount); ?></h5>
                    <p class="text-muted">Revenue</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8 mb-4">
        <div class="table-container h-100">
            <h4 class="mb-4">Edit Profile</h4>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo sanitize($user['username']); ?>" required>
                </div>
                
                <h5 class="mt-4 mb-3">Change Password</h5>
                
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="current_password" name="current_password">
                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password">
                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="form-text">Leave blank to keep current password</div>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const toggleBtns = document.querySelectorAll('.toggle-password');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const passwordInput = document.getElementById(targetId);
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });
});
</script> 