<?php
require_once 'auth.php';

// Logout the user
logout_user();

// Get base path for redirect
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = $script_dir === '/' ? '' : $script_dir;

// Redirect to login page
header('Location: ' . $base_path . '/login.php');
exit;
