<?php

$mysqli = new mysqli(
    $_ENV['DB_HOST'] ?? '127.0.0.1',
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? 'root',
    $_ENV['DB_NAME'] ?? '4d_wms',
    (int)($_ENV['DB_PORT'] ?? 8889)
);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");
