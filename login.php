<?php
require_once 'db_connect.php';
require_once 'auth.php';

// If already logged in, redirect to index
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="form-input" 
                       placeholder="Enter your email"
                       value="<?= htmlspecialchars($email ?? '') ?>"
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

            <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="footer-text">
            © 2026 4D Warehouse Management System
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>
