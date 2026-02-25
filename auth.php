<?php
/**
 * Authentication Functions
 * Handles login, logout, session checking, and API key validation
 */

// Start session for non-API requests
if (!defined('API_REQUEST') && session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

/**
 * Require login - redirect to login page if not authenticated
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Login user with email and password
 * 
 * @param string $email User email
 * @param string $password Plain text password
 * @return array ['success' => bool, 'message' => string]
 */
function login_user($email, $password) {
    global $mysqli;
    
    $email_safe = $mysqli->real_escape_string(trim($email));
    
    // Fetch user by email
    $query = "SELECT id, username, email, password FROM users WHERE email = '$email_safe' LIMIT 1";
    $result = $mysqli->query($query);
    
    if (!$result || $result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password against hash
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Store user data in session
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['username']   = $user['username'];
    
    // Update last login time
    $mysqli->query("UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");
    
    return ['success' => true, 'message' => 'Login successful'];
}

/**
 * Logout user - destroy session
 */
function logout_user() {
    $_SESSION = [];
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Validate API key from request headers
 * Used by API endpoints to authenticate incoming requests
 * 
 * @param string $expected_key The API key to check against
 * @return bool True if valid, false otherwise
 */
function validate_api_key($expected_key) {
    $headers = getallheaders();
    
    // Normalize header keys to lowercase
    $headers = array_change_key_case($headers, CASE_LOWER);
    
    // Check for x-api-key header
    if (!isset($headers['x-api-key'])) {
        return false;
    }
    
    return $headers['x-api-key'] === $expected_key;
}

/**
 * Return JSON error response and exit
 * Used by API endpoints
 * 
 * @param int $code HTTP status code
 * @param string $error Error message
 * @param string $details Additional details (optional)
 */
function api_error($code, $error, $details = '') {
    http_response_code($code);
    header('Content-Type: application/json');
    
    $response = ['error' => $error];
    if ($details) {
        $response['details'] = $details;
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Return JSON success response and exit
 * Used by API endpoints
 * 
 * @param array $data Data to return
 * @param int $code HTTP status code (default 200)
 */
function api_success($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
