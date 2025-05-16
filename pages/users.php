<?php
require_once '../config/config.php';
requireAdmin();

$conn = getConnection();
$message = '';

// Add new user
if (isset($_POST['add_user'])) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $role = sanitize($_POST['role']);
    
    if (empty($username) || empty($password)) {
        $_SESSION['message'] = displayError('Username and password are required');
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['message'] = displayError('Username already exists');
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = displayAlert('User added successfully');
            } else {
                $_SESSION['message'] = displayError('Error adding user: ' . $conn->error);
            }
        }
        
        $stmt->close();
    }
    
    // Redirect to GET request
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Delete user
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Cannot delete yourself
    if ($id == $_SESSION['user_id']) {
        $_SESSION['message'] = displayError('You cannot delete your own account');
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = displayAlert('User deleted successfully');
        } else {
            $_SESSION['message'] = displayError('Error deleting user: ' . $conn->error);
        }
        
        $stmt->close();
    }
    
    // Redirect to GET request (without delete parameter)
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Edit user
if (isset($_POST['edit_user'])) {
    $id = (int)$_POST['id'];
    $username = sanitize($_POST['username']);
    $role = sanitize($_POST['role']);
    $password = $_POST['password'];
    
    if (empty($username)) {
        $_SESSION['message'] = displayError('Username is required');
    } else {
        // Check if username exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['message'] = displayError('Username already exists');
        } else {
            // Update user
            if (!empty($password)) {
                // Update with new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssi", $username, $hashed_password, $role, $id);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $role, $id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['message'] = displayAlert('User updated successfully');
            } else {
                $_SESSION['message'] = displayError('Error updating user: ' . $conn->error);
            }
        }
        
        $stmt->close();
    }
    
    // Redirect to GET request
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get all users
$users = [];
$result = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY username");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}

// Display any message stored in session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it
}

$conn->close();
?>

<?php include '../includes/templates/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="mb-4"><i class="fas fa-users me-2"></i>User Management</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-1"></i>Add New User
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
                    <th>Username</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <?php echo sanitize($user['username']); ?>
                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                <span class="badge bg-info ms-1">You</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'success'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                        <td><?php echo formatDate($user['created_at']); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary edit-btn" 
                                    data-id="<?php echo $user['id']; ?>" 
                                    data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"
                                    data-role="<?php echo $user['role']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#editUserModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this user?')">
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button type="button" class="btn btn-outline-secondary" id="toggleAddPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Password (leave blank to keep current)</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <button type="button" class="btn btn-outline-secondary" id="toggleEditPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Leave blank to keep current password</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Update User</button>
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
            const username = this.dataset.username;
            const role = this.dataset.role;
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = '';
        });
    });
    
    // Toggle password visibility for add form
    const toggleAddPassword = document.querySelector('#toggleAddPassword');
    const passwordInput = document.querySelector('#password');
    
    toggleAddPassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });
    
    // Toggle password visibility for edit form
    const toggleEditPassword = document.querySelector('#toggleEditPassword');
    const editPasswordInput = document.querySelector('#edit_password');
    
    toggleEditPassword.addEventListener('click', function() {
        const type = editPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        editPasswordInput.setAttribute('type', type);
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });
});
</script> 