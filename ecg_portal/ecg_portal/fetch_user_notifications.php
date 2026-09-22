<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['client_staff_no'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'ecg_ashanti_ict_db');
if ($conn->connect_error) {
    http_response_code(503);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$staff_no = $_SESSION['client_staff_no'];
$stmt = $conn->prepare(
    "SELECT id, admin_updated_at, date_received, status_of_complaint, status_comments, details_of_assessment, receiving_ict_officer
     FROM requests
     WHERE staff_no = ? AND admin_updated_at IS NOT NULL
     ORDER BY admin_updated_at DESC, id DESC
     LIMIT 1"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to prepare notification query']);
    exit;
}

$stmt->bind_param('s', $staff_no);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'latest_update_id' => $row ? (int)$row['id'] : 0,
    'latest_update_date' => $row ? $row['admin_updated_at'] : null,
    'latest_update_key' => $row ? sha1(
        $row['id'] . '|' .
        $row['admin_updated_at'] . '|' .
        $row['date_received'] . '|' .
        $row['status_of_complaint'] . '|' .
        $row['status_comments'] . '|' .
        $row['details_of_assessment'] . '|' .
        $row['receiving_ict_officer']
    ) : ''
]);

$stmt->close();
$conn->close();
