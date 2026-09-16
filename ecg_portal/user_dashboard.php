<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['client_staff_no'])) {
    header("Location: user_login.php");
    exit;
}

$staff_no = $_SESSION['client_staff_no'];
$client_name = $_SESSION['client_name'];

$host = 'localhost';
$db   = 'ecg_ashanti_ict_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed.");
}

// Fetch requests for this specific staff member
$stmt = $conn->prepare("SELECT * FROM requests WHERE staff_no = ? ORDER BY date_submitted DESC");
$stmt->bind_param("s", $staff_no);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests Dashboard - ECG Ashanti West</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --primary: #059669;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header-card {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        .header-card h2 { margin: 0; font-size: 1.25rem; }
        .header-card p { margin: 5px 0 0 0; color: var(--muted); font-size: 0.88rem; }
        .btn-action {
            background: #f1f5f9;
            color: var(--text);
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid var(--border);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action:hover { background: #e2e8f0; }
        .request-item {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.01);
        }
        .req-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-progress { background: #e0e7ff; color: #3730a3; }
        .status-resolved { background: #d1fae5; color: #065f46; }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
        .info-block label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .info-block p {
            margin: 0;
            font-size: 0.9rem;
        }
        .feedback-box {
            margin-top: 15px;
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            font-size: 0.88rem;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-card">
        <div>
            <h2>Welcome, <?php echo htmlspecialchars($client_name); ?></h2>
            <p>Staff ID: <strong><?php echo htmlspecialchars($staff_no); ?></strong></p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn-action"><i class="fa-solid fa-plus"></i> New Request</a>
            <a href="logout.php" class="btn-action" style="color: #ef4444;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <h3 style="font-size: 1.1rem; margin-bottom: 20px;">Your Support Requests History</h3>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
            <?php 
                // Map database status to display styles
                $status_class = 'status-pending';
                $display_status = htmlspecialchars($row['status_of_complaint']);
                
                if (in_array($display_status, ['Resolution in progress', 'Waiting for procurement of material'])) {
                    $status_class = 'status-progress';
                } elseif ($display_status == 'Resolved and closed') {
                    $status_class = 'status-resolved';
                    $display_status = 'Resolved'; // Shorten for display
                }
            ?>
            <div class="request-item">
                <div class="req-header">
                    <div>
                        <strong>Request #<?php echo $row['id']; ?></strong>
                        <span style="color: var(--muted); font-size: 0.85rem; margin-left: 10px;"><i class="fa-regular fa-calendar"></i> <?php echo $row['date_submitted']; ?></span>
                    </div>
                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $display_status; ?></span>
                </div>

                <div class="grid-2">
                    <div class="info-block">
                        <label>Request Type</label>
                        <p><strong><?php echo htmlspecialchars($row['request_type']); ?></strong></p>
                        
                        <label style="margin-top: 10px;">Purpose / Justification</label>
                        <p><?php echo htmlspecialchars($row['further_details'] ?? ''); ?></p>
                    </div>
                    
                    <div class="info-block">
                        <label>Assigned ICT Officer</label>
                        <p><?php echo !empty($row['receiving_ict_officer']) ? htmlspecialchars($row['receiving_ict_officer']) : '<span style="color:var(--muted); font-style:italic;">Pending Assignment</span>'; ?></p>
                        
                        <label style="margin-top: 10px;">Department / Contact</label>
                        <p><?php echo htmlspecialchars($row['department']); ?> / <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($row['mobile_contact']); ?></p>
                    </div>
                </div>

                <?php if (!empty($row['details_of_assessment']) || !empty($row['status_comments'])): ?>
                    <div class="feedback-box">
                        <strong>ICT Assessment / Remarks:</strong>
                        <p style="margin: 4px 0 0 0;"><?php echo htmlspecialchars($row['details_of_assessment'] ?: $row['status_comments']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($row['attachment_path'])): ?>
                    <div style="margin-top: 15px; font-size: 0.85rem;">
                        <a href="<?php echo htmlspecialchars($row['attachment_path']); ?>" target="_blank" class="btn-action" style="padding: 6px 12px;">
                            <i class="fa-solid fa-paperclip"></i> View Attachment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--muted); padding: 40px; background: white; border-radius: 12px; border: 1px solid var(--border);">You have not submitted any ICT support requests yet.</p>
    <?php endif; ?>
</div>

</body>
</html>