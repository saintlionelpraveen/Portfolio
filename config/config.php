<?php
// config/config.php

// Database credentials
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    // Localhost (XAMPP)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'praveen');
    define('BASE_URL', 'http://localhost/Praveen-Portfolio/');

    // Suggest error reporting for development
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // InfinityFree (Production)
    define('DB_HOST', 'sql211.infinityfree.com');
    define('DB_USER', 'if0_41198642');
    define('DB_PASS', 'praveen1328');
    define('DB_NAME', 'if0_41198642_praveen');

    // Temporarily show errors in production to debug the 500 error
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Only show detailed error if local
    if ($_SERVER['HTTP_HOST'] == 'localhost') {
        die("Connection failed: " . $conn->connect_error);
    } else {
        die("Connection failed. Please check configuration.");
    }
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>