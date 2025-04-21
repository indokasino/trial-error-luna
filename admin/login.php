<?php
/**
 * Admin Login Page
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/auth.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Check if user is already logged in
if (auth()->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!auth()->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } else {
            $result = auth()->login($username, $password);
            
            if ($result['success']) {
                header('Location: index.php');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Generate new CSRF token
$csrfToken = auth()->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Chatbot - Admin Login</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>Luna Chatbot</h1>
            <p>Admin Panel</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo sanitize($success); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php" class="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </div>
            
            <div class="form-footer">
                <a href="reset_password.php">Forgot password?</a>
            </div>
        </form>
    </div>
    
    <script src="../assets/js/validation.js"></script>
</body>
</html>