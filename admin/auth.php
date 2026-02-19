<?php
// admin/auth.php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, username, password FROM admin_users WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $db_username, $db_password);
            $stmt->fetch();

            if ($password === $db_password) {
                // Password is correct
                $_SESSION['admin_id'] = $id;
                $_SESSION['admin_username'] = $db_username;
                header("Location: dashboard.php");
                exit();
            } else {
                header("Location: ../login.php?error=Invalid password");
                exit();
            }
        } else {
            header("Location: ../login.php?error=Invalid username");
            exit();
        }
        $stmt->close();
    } else {
        header("Location: ../login.php?error=Database error");
        exit();
    }
} else {
    header("Location: ../login.php");
    exit();
}
$conn->close();
?>