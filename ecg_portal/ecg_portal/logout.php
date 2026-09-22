<?php
session_start();

$logout_scope = $_GET['scope'] ?? 'admin';

if ($logout_scope === 'user') {
    unset($_SESSION['user_logged_in'], $_SESSION['client_staff_no'], $_SESSION['client_name'], $_SESSION['client_dept']);
    header('Location: user_login.php');
    exit;
}

// Database connection setup to log the event
$host = "localhost";
$db = "ecg_ashanti_ict_db";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);

// Capture username before destroying session for audit log
$admin_username = $_SESSION['admin_name'] ?? 'Unknown';

if (!$conn->connect_error) {
     $audit_action = "Administrator logged out: " . $admin_username;
     $audit_stmt = $conn->prepare("INSERT INTO audit_logs (action) VALUES (?)");
     if ($audit_stmt) {
         $audit_stmt->bind_param("s", $audit_action);
         $audit_stmt->execute();
         $audit_stmt->close();
     }
     $conn->close();
}

// Destroy only the admin login session state without affecting another authenticated user session in the same browser.
unset($_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);

// Redirect to login page
header("Location: login.php");
exit;
?>