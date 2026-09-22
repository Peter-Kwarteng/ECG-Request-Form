<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
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
$latest_result = $conn->query("SELECT id FROM requests WHERE is_read = 0 ORDER BY id DESC LIMIT 1");
$latest_row = $latest_result ? $latest_result->fetch_assoc() : null;
echo json_encode([
    'unread' => (int)$row['unread'],
    'latest_unread_id' => $latest_row ? (int)$latest_row['id'] : 0
]);
$conn->close();
?>