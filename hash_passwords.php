<?php
/**
 * Run this ONCE to hash all existing user passwords
 * After running, delete this file for security
 */

require_once 'db_connect.php';

echo "<h2>Password Hash Utility</h2>";
echo "<p>This will hash all plain-text passwords in the users table.</p>";

// Fetch all users
$result = $mysqli->query("SELECT id, username, password FROM users");
$users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

if (empty($users)) {
    die("<p style='color:red;'>No users found in database.</p>");
}

echo "<h3>Users to update:</h3><ul>";

foreach ($users as $user) {
    // Check if password is already hashed (bcrypt hashes start with $2y$)
    if (str_starts_with($user['password'], '$2y$')) {
        echo "<li><b>{$user['username']}</b> - already hashed ✓</li>";
        continue;
    }

    // Hash the plain-text password
    $hashed = password_hash($user['password'], PASSWORD_BCRYPT);
    $hashed_safe = $mysqli->real_escape_string($hashed);

    $ok = $mysqli->query("UPDATE users SET password = '$hashed_safe' WHERE id = {$user['id']}");

    if ($ok) {
        echo "<li><b>{$user['username']}</b> - password hashed </li>";
    } else {
        echo "<li><b>{$user['username']}</b> - ERROR: {$mysqli->error} </li>";
    }
}

echo "</ul>";
echo "<p style='color:green; font-weight:bold;'>Done! Delete this file now for security.</p>";
