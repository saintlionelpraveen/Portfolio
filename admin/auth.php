<?php
// admin/auth.php
session_start();
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        header("Location: ../login.php?error=Please enter both username and password");
        exit();
    }

    // Query admin_users table
    $stmt = $conn->prepare("SELECT id, username, password FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();

        // Password is stored as plain text in the database
        if ($password === $admin['password']) {
            // Login successful
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header("Location: dashboard.php");
            exit();
        }
    }

    // Login failed
    header("Location: ../login.php?error=Invalid username or password");
    exit();
} else {
    // If accessed directly without POST, redirect to login
    header("Location: ../login.php");
    exit();
}
?>