<?php
require_once 'config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $error = '';
    
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter both username and password';
        header('Location: login.php');
        exit;
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect to dashboard
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['login_error'] = 'Invalid password';
                header('Location: login.php');
                exit;
            }
        } else {
            $_SESSION['login_error'] = 'User not found';
            header('Location: login.php');
            exit;
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Get error from session if it exists
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card animate-card">
            <div class="login-header">
                <h2 class="m-0"><?php echo SITE_NAME; ?></h2>
                <p class="text-light mb-0">Manage your inventory efficiently</p>
            </div>
            <div class="login-body">
                <?php if (isset($error) && !empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="input-group input-group-lg mb-4 login-input">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="fas fa-user text-primary"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" 
                               id="username" name="username" placeholder="Username" required>
                    </div>
                    
                    <div class="input-group input-group-lg mb-4 login-input">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="fas fa-lock text-primary"></i>
                        </span>
                        <input type="password" class="form-control border-start-0 ps-0" 
                               id="password" name="password" placeholder="Password" required>
                        <button type="button" class="btn btn-outline-secondary border-start-0" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg login-btn">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </div>
                </form>
                
                <div class="mt-4 text-center copyright-text">
                    <small>© Copyright 2025 | Orhen Technologies</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
</body>
</html> 