<?php
// config/config.php

// Database credentials for InfinityFree (Production)
define('DB_HOST', 'sql211.infinityfree.com');
define('DB_USER', 'if0_41198642');
define('DB_PASS', 'praveen1328');
define('DB_NAME', 'if0_41198642_praveen');
define('BASE_URL', 'http://praveeny.gamer.gd/');

// Enable Error Reporting (Turn off display_errors for production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Changed to 0 for production

// Create connection with exception handling
try {
    // Enable mysqli exceptions
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // Log the error internally (if logging is set up) or show a generic error
    error_log("Database connection failed: " . $e->getMessage());
    die("Database Connection Error. Please verify that your InfinityFree database Host, Username, and Password are correct in the hosting control panel.");
}
?>