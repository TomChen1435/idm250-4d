<?php
require_once 'auth.php';

// Logout the user
logout_user();

// Get base path for redirect
$script_dir = dirname($_SERVER['SCRIPT_NAME']);

// If at root, use empty string, otherwise use the directory
if ($script_dir === '/' || $script_dir === '\\') {
    $base_path = '';
} else {
    $base_path = rtrim($script_dir, '/\\');
}

// Redirect to login page
header('Location: ' . $base_path . '/login.php');
exit;
