<?php

$env = parse_ini_file(__DIR__ . '/.env');

$mysqli = new mysqli(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASS'],
    $env['DB_NAME'],
    (int)$env['DB_PORT']       //delete this line, i only needed it for my local
);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");