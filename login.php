<?php
require_once 'db_connect.php';
require_once 'auth.php';

// If already logged in, redirect to index
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // 'login' or 'register'

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // LOGIN
    if (isset($_POST['login'])) {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $result = login_user($email, $password);
            
            if ($result['success']) {
                header('Location: index.php');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // REGISTER
    if (isset($_POST['register'])) {
        $username = trim($_POST['reg_username'] ?? '');
        $email    = trim($_POST['reg_email'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $confirm  = $_POST['reg_confirm'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
            $mode = 'register';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
            $mode = 'register';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
            $mode = 'register';
        } else {
            // Check if email already exists
            $email_safe = $mysqli->real_escape_string($email);
            $check = $mysqli->query("SELECT id FROM users WHERE email = '$email_safe'");
            
            if ($check && $check->num_rows > 0) {
                $error = 'This email is already registered.';
                $mode = 'register';
            } else {
                // Create user
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $username_safe = $mysqli->real_escape_string($username);
                $hashed_safe   = $mysqli->real_escape_string($hashed);
                
                $result = $mysqli->query("INSERT INTO users (username, email, password) 
                                           VALUES ('$username_safe', '$email_safe', '$hashed_safe')");
                
                if ($result) {
                    $success = "Account created! You can now login.";
                    $mode = 'login';
                } else {
                    $error = 'Registration failed: ' . $mysqli->error;
                    $mode = 'register';
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
    <title>Login - 4D WMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-text">4D WMS</div>
            <div class="logo-subtitle">Warehouse Management System</div>
        </div>

        <!-- Tabs -->
        <div class="login-tabs">
            <a href="login.php?mode=login" 
            class="login-tab <?= $mode === 'login' ? 'active' : '' ?>">
                Login
            </a>

            <a href="login.php?mode=register" 
            class="login-tab <?= $mode === 'register' ? 'active' : '' ?>">
                Register
            </a>

        </div>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- LOGIN TAB -->
        <div id="login-tab" class="tab-content <?= $mode === 'login' ? 'active' : '' ?>">
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-input" 
                           placeholder="Enter your email"
                           value="<?= $mode === 'login' ? htmlspecialchars($email ?? '') : '' ?>"
                           required 
                           autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-toggle">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="Enter your password"
                               required>
                        <button type="button" class="password-toggle-btn" onclick="togglePassword('password')">Show</button>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-login">Login</button>
            </form>
        </div>

        <!-- REGISTER TAB -->
        <div id="register-tab" class="tab-content <?= $mode === 'register' ? 'active' : '' ?>">
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label class="form-label" for="reg_username">Username</label>
                    <input type="text" 
                           id="reg_username" 
                           name="reg_username" 
                           class="form-input" 
                           placeholder="Choose a username"
                           value="<?= $mode === 'register' ? htmlspecialchars($username ?? '') : '' ?>"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_email">Email</label>
                    <input type="email" 
                           id="reg_email" 
                           name="reg_email" 
                           class="form-input" 
                           placeholder="Enter your email"
                           value="<?= $mode === 'register' ? htmlspecialchars($email ?? '') : '' ?>"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_password">Password</label>
                    <div class="password-toggle">
                        <input type="password" 
                               id="reg_password" 
                               name="reg_password" 
                               class="form-input" 
                               placeholder="At least 6 characters"
                               required>
                        <button type="button" class="password-toggle-btn" onclick="togglePassword('reg_password')">Show</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_confirm">Confirm Password</label>
                    <div class="password-toggle">
                        <input type="password" 
                               id="reg_confirm" 
                               name="reg_confirm" 
                               class="form-input" 
                               placeholder="Re-enter password"
                               required>
                        <button type="button" class="password-toggle-btn" onclick="togglePassword('reg_confirm')">Show</button>
                    </div>
                </div>

                <button type="submit" name="register" class="btn-login">Create Account</button>
            </form>
        </div>

        <div class="footer-text">
            © 2026 4D Warehouse Management System
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
