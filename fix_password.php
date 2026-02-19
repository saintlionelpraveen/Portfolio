<?php
// fix_password.php
require_once 'config/config.php';

// The credentials you want
$username = 'admin';
$password = 'admin123';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Update database
$stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $hashed_password, $username);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "<h1>Success!</h1>";
        echo "<p>Password for user '<strong>$username</strong>' has been updated to '<strong>$password</strong>'.</p>";
        echo "<p><a href='login.php'>Go to Login</a></p>";
    } else {
        // If no rows affected, maybe the user doesn't exist? Let's try inserting.
        $stmt_insert = $conn->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
        $stmt_insert->bind_param("ss", $username, $hashed_password);
        if ($stmt_insert->execute()) {
            echo "<h1>Success!</h1>";
            echo "<p>User '<strong>$username</strong>' was missing, so I created it with password '<strong>$password</strong>'.</p>";
            echo "<p><a href='login.php'>Go to Login</a></p>";
        } else {
            echo "<h1>Error</h1>";
            echo "<p>Could not update or insert user: " . $conn->error . "</p>";
        }
    }
} else {
    echo "<h1>Error</h1>";
    echo "<p>Database error: " . $conn->error . "</p>";
}
?>