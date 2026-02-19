<?php
// config/config.php

// Database credentials for InfinityFree (Production)
define('DB_HOST', 'sql211.infinityfree.com');
define('DB_USER', 'if0_41198642');
define('DB_PASS', 'praveen1328');
define('DB_NAME', 'if0_41198642_praveen');
define('BASE_URL', 'http://praveeny.gamer.gd/');

// Enable Error Reporting temporarily for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>