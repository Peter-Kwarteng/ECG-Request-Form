<?php
header('Content-Type: application/json');
$host = 'localhost';
$db   = 'ecg_ashanti_ict_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['unread' => 0]);
    exit;
}

$result = $conn->query("SELECT COUNT(*) as unread FROM requests WHERE is_read = 0");
$row = $result->fetch_assoc();
echo json_encode(['unread' => (int)$row['unread']]);
$conn->close();
?>