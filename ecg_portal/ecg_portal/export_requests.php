<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Access denied.');
}

$host = 'localhost';
$db   = 'ecg_ashanti_ict_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed.");
}

$format = $_GET['format'] ?? 'excel';
$dataset = $_GET['dataset'] ?? 'requests';

if ($format !== 'excel' && $format !== 'pdf' && $format !== 'csv' && $format !== 'doc' && $format !== 'txt') {
    http_response_code(400);
    exit('Unsupported report format.');
}
if ($dataset !== 'requests' && $dataset !== 'audit') {
    http_response_code(400);
    exit('Unsupported report dataset.');
}

$report_title = $dataset === 'audit' ? 'AUDIT TRAIL REPORT' : 'SAVED ICT REQUESTS REPORT';
$headers = $dataset === 'audit'
    ? ['Log ID', 'Activity Description', 'Created Date']
    : ['Request ID', 'Date Submitted', 'Staff Name', 'Staff No', 'Department', 'Request Type / Item', 'Mobile Contact', 'Status', 'Assigned Officer'];
$result = $dataset === 'audit'
    ? $conn->query("SELECT id, action, created_at FROM audit_logs ORDER BY created_at DESC")
    : $conn->query("SELECT id, date_submitted, name_of_staff, staff_no, department, request_type, mobile_contact, status_of_complaint, receiving_ict_officer FROM requests ORDER BY date_submitted DESC");

$download_filename = ($dataset === 'audit' ? 'ECG_Ashanti_West_Audit_Trail_' : 'ECG_Ashanti_West_ICT_Requests_') . date('Y-m-d');

if ($format === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=" . $download_filename . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
} elseif ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $download_filename . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
} elseif ($format === 'doc') {
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename=' . $download_filename . '.doc');
} elseif ($format === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $download_filename . '.txt');
} else {
    header("Content-Type: text/html; charset=utf-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ECG Ashanti West - ICT Directorate Report</title>
    <link rel="icon" type="image/jpeg" href="ecg_logo.jpg">
    <link rel="apple-touch-icon" href="ecg_logo.jpg">
    <style>
        body { font-family: Arial, sans-serif; color: #1e293b; margin: 20px; }
        h2, h4 { margin: 0 0 5px 0; text-align: center; }
        .subtitle { text-align: center; font-size: 0.85rem; color: #64748b; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.85rem; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        th { background-color: #f1f5f9; font-weight: bold; color: #0f172a; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .print-btn-container { text-align: center; margin: 20px 0; }
        .btn-print { background: #2563eb; color: white; border: none; padding: 10px 20px; font-size: 1rem; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-print:hover { background: #1d4ed8; }
        @media print {
            .print-btn-container { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

    <?php if ($format === 'pdf'): ?>
        <div class="print-btn-container">
            <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
        </div>
    <?php endif; ?>

    <h2>ELECTRICITY COMPANY OF GHANA LTD.</h2>
    <h4>ASHANTI WEST REGION - ICT DIRECTORATE <?php echo htmlspecialchars($report_title); ?></h4>
    <div class="subtitle">Generated on: <?php echo date('F j, Y, g:i a'); ?></div>

    <table>
        <thead>
            <tr>
                <?php foreach ($headers as $header): ?>
                    <th><?php echo htmlspecialchars($header); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <?php if ($dataset === 'audit'): ?>
                            <td>#<?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['action']); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <?php else: ?>
                            <td>#<?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['date_submitted']); ?></td>
                            <td><?php echo htmlspecialchars($row['name_of_staff']); ?></td>
                            <td><?php echo htmlspecialchars($row['staff_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo htmlspecialchars($row['request_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['mobile_contact']); ?></td>
                            <td><?php echo htmlspecialchars($row['status_of_complaint']); ?></td>
                            <td><?php echo htmlspecialchars($row['receiving_ict_officer'] ?: 'Unassigned'); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo count($headers); ?>" style="text-align: center; color: #64748b;">No records found in the database.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
