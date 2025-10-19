<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $auth = new Auth();
        
        if ($_POST['action'] === 'login') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            if (empty($username) || empty($password)) {
                $error = 'Please fill in all fields';
            } else {
                $result = $auth->login($username, $password);
                if ($result['success']) {
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = $result['message'];
                }
            }
        } elseif ($_POST['action'] === 'register') {
            $username = trim($_POST['reg_username']);
            $email = trim($_POST['reg_email']);
            $password = $_POST['reg_password'];
            $confirmPassword = $_POST['reg_confirm_password'];
            
            if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
                $error = 'Please fill in all fields';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address';
            } else {
                $result = $auth->register($username, $email, $password);
                if ($result['success']) {
                    $success = 'Registration successful! Please log in.';
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VM Platform - Login</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-server"></i>
                    <h1>VM Platform</h1>
                </div>
                <p>Advanced Virtual Machine Management</p>
            </div>

            <div class="auth-tabs">
                <button class="tab-btn active" onclick="switchTab('login')">Login</button>
                <button class="tab-btn" onclick="switchTab('register')">Register</button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form id="loginForm" class="auth-form active" method="POST">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>

            <!-- Register Form -->
            <form id="registerForm" class="auth-form" method="POST">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="reg_username">Username</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="reg_username" name="reg_username" required 
                               value="<?php echo htmlspecialchars($_POST['reg_username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_email">Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="reg_email" name="reg_email" required 
                               value="<?php echo htmlspecialchars($_POST['reg_email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="reg_password" name="reg_password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('reg_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-fill"></div>
                        </div>
                        <span class="strength-text">Password strength</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_confirm_password">Confirm Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="reg_confirm_password" name="reg_confirm_password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('reg_confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" required>
                        <span class="checkmark"></span>
                        I agree to the <a href="terms.php" target="_blank">Terms of Service</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </button>
            </form>

            <div class="auth-footer">
                <p>&copy; 2024 VM Platform. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
</body>
</html>