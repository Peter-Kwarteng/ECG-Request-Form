<?php
session_start();

// Check if admin is logged in for security
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit("Access Denied. Please login.");
}

$host = 'localhost';
$db   = 'ecg_ashanti_ict_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed");
}

// Ensure audit_logs table exists
$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$format = $_GET['format'] ?? 'excel';

if ($format === 'excel' || $format === 'csv') {
    // AUDIT LOG: Log the export action
    $admin_user = $_SESSION['admin_name'] ?? 'Unknown Admin';
    $audit_action = $admin_user . ' exported requests data to CSV';
    $audit_stmt = $conn->prepare("INSERT INTO audit_logs (action) VALUES (?)");
    if ($audit_stmt) {
        $audit_stmt->bind_param("s", $audit_action);
        $audit_stmt->execute();
        $audit_stmt->close();
    }

    $filename = "ICT_Requests_Report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    // Add BOM for proper UTF-8 display in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Set CSV Headers
    fputcsv($output, [
        'Request ID', 'Date Submitted', 'Staff Name', 'Staff No', 'Department', 
        'Contact', 'Requested Item', 'Purpose', 'Job Title / Rank', 
        'Receiving ICT Officer', 'Date Received', 'Assessment Details', 'Status', 
        'Status Comments', 'ICT Supervisor Name', 'Supervisor Date'
    ]);
    
    // Fetch Data
    $result = $conn->query("SELECT * FROM requests ORDER BY date_submitted DESC");
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['date_submitted'],
            $row['name_of_staff'],
            $row['staff_no'],
            $row['department'],
            $row['mobile_contact'],
            $row['request_type'],
            $row['further_details'],
            $row['job_title_rank'],
            $row['receiving_ict_officer'],
            $row['date_received'],
            $row['details_of_assessment'],
            $row['status_of_complaint'],
            $row['status_comments'],
            $row['supervisor_name'],
            $row['supervisor_date']
        ]);
    }
    fclose($output);
    exit;
}
$conn->close();
?>