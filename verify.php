<?php
session_start();
require 'db.php';

if (isset($_GET['code'])) {
    $code = trim($_GET['code']);
    
    $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE verification_code = ? AND is_verified = 0");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['message'] = "Email verified successfully! You can now login.";
    } else {
        $_SESSION['error'] = "Invalid or already verified code.";
    }
    
    header("Location: login.php");
    exit();
} else {
    $_SESSION['error'] = "Verification code missing.";
    header("Location: login.php");
    exit();
}
?>
