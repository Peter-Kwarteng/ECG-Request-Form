<?php
session_start();

// Database connection setup to log the event
$host = "localhost";
$db = "ecg_ashanti_ict_db";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);

// Capture username before destroying session for audit log
$admin_username = $_SESSION['admin_username'] ?? 'Unknown';

if (!$conn->connect_error) {
     // AUDIT LOG: Record the logout event
     $conn->query("INSERT INTO audit_logs (action) VALUES ('Administrator logged out: $admin_username')");
     $conn->close();
}

// Perform logout operations
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>