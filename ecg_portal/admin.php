<?php

session_start();

// STRICT VERIFICATION CHECK: Force redirect back to login if unverified
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? 'Administrator';


$host = 'localhost';
$db = 'ecg_ashanti_ict_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed.");
}

// Ensure necessary tables exist
$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'Administrator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$user_notice = $_SESSION['user_notice'] ?? '';
unset($_SESSION['user_notice']);

// Handle Mark All Read
if (isset($_GET['mark_read'])) {
    $conn->query("UPDATE requests SET is_read = 1 WHERE is_read = 0");
    $conn->query("INSERT INTO audit_logs (action) VALUES ('Marked all incoming requests as read')");
    header("Location: admin.php");
    exit;
}

// Handle Record Deletion (CRUD)
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM requests WHERE id = $del_id");
    $conn->query("INSERT INTO audit_logs (action) VALUES ('Deleted request record ID #$del_id')");
    header("Location: admin.php?tab=records");
    exit;
}

// Handle User CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'Administrator');

    if ($username !== '' && strlen($password) >= 8) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user_stmt = $conn->prepare("INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)");
        $user_stmt->bind_param("sss", $username, $password_hash, $role);
        if ($user_stmt->execute()) {
            $audit_stmt = $conn->prepare("INSERT INTO audit_logs (action) VALUES (?)");
            $audit_action = "Added new system user: $username";
            $audit_stmt->bind_param("s", $audit_action);
            $audit_stmt->execute();
            $_SESSION['user_notice'] = 'User account created successfully.';
        } else {
            $_SESSION['user_notice'] = 'Unable to create the user. The username may already exist.';
        }
    } else {
        $_SESSION['user_notice'] = 'Enter a username and a password with at least 8 characters.';
    }
    header("Location: admin.php?tab=users");
    exit;
}

if (isset($_GET['delete_user'])) {
    $u_id = (int)$_GET['delete_user'];
    if ($u_id === (int)($_SESSION['admin_id'] ?? 0)) {
        $_SESSION['user_notice'] = 'You cannot delete the account currently being used.';
    } elseif ($conn->query("DELETE FROM admin_users WHERE id = $u_id")) {
        $audit_stmt = $conn->prepare("INSERT INTO audit_logs (action) VALUES (?)");
        $audit_action = "Deleted system user ID #$u_id";
        $audit_stmt->bind_param("s", $audit_action);
        $audit_stmt->execute();
        $_SESSION['user_notice'] = 'User account deleted successfully.';
    } else {
        $_SESSION['user_notice'] = 'Unable to delete that user account.';
    }
    header("Location: admin.php?tab=users");
    exit;
}

// Handle Request Update (Assessment & Sign-off)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $id = (int)$_POST['update_id'];
    $officer = $conn->real_escape_string($_POST['receiving_ict_officer']);
    $assessment = $conn->real_escape_string($_POST['details_of_assessment']);
    $status = $conn->real_escape_string($_POST['status_of_complaint']);
    $comments = $conn->real_escape_string($_POST['status_comments']);
    $sup_name = $conn->real_escape_string($_POST['supervisor_name']);
    $sup_sig = $conn->real_escape_string($_POST['supervisor_signature']);
    $sup_date = $_POST['supervisor_date'];

    $sql = "UPDATE requests SET 
            receiving_ict_officer='$officer', 
            date_received=CURDATE(), 
            details_of_assessment='$assessment', 
            status_of_complaint='$status', 
            status_comments='$comments',
            supervisor_name='$sup_name',
            supervisor_signature='$sup_sig',
            supervisor_date=" . (!empty($sup_date) ? "'$sup_date'" : "NULL") . ",
            is_read=1 
            WHERE id=$id";
    $conn->query($sql);
    $conn->query("INSERT INTO audit_logs (action) VALUES ('Updated assessment and status for request ID #$id')");
    header("Location: admin.php");
    exit;
}

// Tab navigation & filtering/search setup
$tab = $_GET['tab'] ?? 'dashboard';
$search = $_GET['search'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_dept = $_GET['filter_dept'] ?? '';

// Pagination variables
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$where_clauses = ["1=1"];
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $where_clauses[] = "(name_of_staff LIKE '%$s%' OR staff_no LIKE '%$s%' OR request_type LIKE '%$s%' OR department LIKE '%$s%')";
}
if (!empty($filter_status)) {
    $fs = $conn->real_escape_string($filter_status);
    $where_clauses[] = "status_of_complaint = '$fs'";
}
if (!empty($filter_dept)) {
    $fd = $conn->real_escape_string($filter_dept);
    $where_clauses[] = "department = '$fd'";
}
$where_sql = implode(" AND ", $where_clauses);

// Fetch total records for pagination
$total_query = $conn->query("SELECT COUNT(*) as total FROM requests WHERE $where_sql");
$total_row = $total_query->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Fetch filtered and paginated records
$result = $conn->query("SELECT * FROM requests WHERE $where_sql ORDER BY date_submitted DESC LIMIT $limit OFFSET $offset");

// Metrics & lists
$unread_result = $conn->query("SELECT COUNT(*) as unread FROM requests WHERE is_read = 0");
$unread_count = ($unread_result) ? $unread_result->fetch_assoc()['unread'] : 0;
$total_result = $conn->query("SELECT COUNT(*) as total FROM requests");
$total_count = ($total_result) ? $total_result->fetch_assoc()['total'] : 0;

$users_result = $conn->query("SELECT * FROM admin_users ORDER BY id DESC");
$audit_result = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 20");
$departments = [
    'District Engineer 27',
    'Field Investigators 26',
    'DCO/DMO 25',
    'District Technical Officer 24',
    "District Manager's Secretary 23.",
    'Billing & Revenue 22',
    'Public Relations 21',
    'Conference Room 19',
    'Marketing & MIS 18',
    'MTS 17',
    'Expenditure 16',
    'District Account Officer 15',
    'Account Examination Unit 14',
    'Room 13',
    'HR Manager 12',
    'Registry 11',
    'HR Officer 10',
    'Accounts Office 09',
    'Accounts Office 08',
    'Supervisors Office',
    'Room 07',
    'Commercial Manager 06',
    'Accounts Manager 05',
    'Regional Engineer 04',
    'Materials & Transport Manager 03',
    'RP Manager 02',
    'General Manager 01',
    'Customes Office',
    'Customer Service',
    "Supervisor's Office",
    'District HR Officer',
    'Ghana Water',
    'ICT Department 28',
    'CREDIT UNION',
    'MAPPERS/ESTIMATOR 29',
    'Baby Bay Office'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Admin Dashboard - ECG Ashanti West ICT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #0f172a;
            --main-bg: #f8fafc;
            --card-bg: #ffffff;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--main-bg);
            color: var(--text-main);
            margin: 0;
            display: flex;
            min-height: 100vh;
        }
        sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
        }
        .sidebar-brand {
            padding: 0px 0px;
            font-size: 1.1rem;
            font-weight: 800;
            background: rgba(0,0,0,0.2);
            /* border-bottom: 1px solid rgba(255,255,255,0.08); */
            display: flex;
            align-items: center;
            gap: 12px;
            color: #93c5fd;
        }
        .sidebar-logo-brand {
            min-height: 175px;
            padding: 0px 16px;
            justify-content: center;
        }
        .sidebar-logo {
            display: block;
            width: 145px;
            height: 145px;
            object-fit: contain;
            border-radius: 50%;
            background: white;
            padding: 5px;
            box-sizing: border-box;
        }
        .sidebar-brand2 {
            font-size: 1.1rem;
            font-weight: 800;
            background: rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;

        }
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
            flex-grow: 1;
        }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .sidebar-menu li.active a, .sidebar-menu li a:hover {
            color: white;
            background: rgba(255,255,255,0.06);
            border-left: 4px solid var(--primary);
        }
        .main-content {
            margin-left: 260px;
            flex-grow: 1;
            padding: 30px;
            box-sizing: border-box;
        }
        .top-navbar {
            background: var(--card-bg);
            padding: 20px 30px;
            border-radius: 14px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }
        .top-navbar h2 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
        }
        .action-cluster {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn-export {
            background: #059669;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-export:hover { background: #047857; }
        .export-menu {
            position: fixed;
            inset: 0;
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
        }
        .export-menu.open { display: flex; }
        .export-dialog {
            width: min(430px, calc(100% - 32px));
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
        }
        .export-dialog h3 { margin: 0 0 18px; }
        .export-dialog label { display: block; margin: 12px 0 5px; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); }
        .export-dialog select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; }
        .export-dialog-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }
        .btn-export-cancel { background: #e2e8f0; color: #334155; }
        .notification-pill {
            background: var(--danger);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-panel {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .filter-panel label { font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 5px; text-transform: uppercase; }
        .filter-panel input, .filter-panel select {
            width: 100%; padding: 9px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.88rem; background: #fff; box-sizing: border-box;
        }
        .search-input-wrapper { position: relative; }
        .search-input-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        .search-input-wrapper input { padding-left: 34px; }
        .btn-primary { background: var(--primary); color: white; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger { background: var(--danger); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-size: 0.82rem; }
        .requests-grid { display: grid; gap: 20px; }
        .request-card {
            background: var(--card-bg);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .request-card.unresolved-card { border-left: 5px solid #f59e0b; background: #fffdfa; }
        .card-header-flex {
            padding: 16px 24px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-body-grid {
            padding: 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        @media (max-width: 1024px) {
            .card-body-grid { grid-template-columns: 1fr; }
            .filter-panel { grid-template-columns: 1fr; }
            sidebar { width: 70px; }
            .sidebar-logo-brand { min-height: 100px; padding: 12px 8px; }
            .sidebar-logo { width: 56px; height: 56px; padding: 3px; }
            sidebar .sidebar-brand span, sidebar .sidebar-menu span { display: none; }
            .main-content { margin-left: 70px; }
        }
        .info-group { margin-bottom: 12px; font-size: 0.9rem; }
        .info-group label {
            font-weight: 600;
            color: var(--text-muted);
            display: block;
            font-size: 0.78rem;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .admin-form-panel { background: #f1f5f9; padding: 20px; border-radius: 10px; border: 1px solid #cbd5e1; }
        .admin-form-panel label { font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-top: 10px; margin-bottom: 4px; }
        .admin-form-panel input, .admin-form-panel select, .admin-form-panel textarea {
            width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; background: white; box-sizing: border-box;
        }
        .admin-form-panel textarea { min-height: 70px; resize: vertical; }
        .save-btn {
            background: var(--primary); color: white; border: none; width: 100%; padding: 10px; border-radius: 6px; font-weight: 700; margin-top: 15px; cursor: pointer;
        }
        .save-btn:hover { background: var(--primary-dark); }
        .pagination { display: flex; gap: 8px; justify-content: center; margin-top: 30px; align-items: center; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 6px; border: 1px solid var(--border); text-decoration: none; font-size: 0.88rem; font-weight: 600; color: var(--text-main); background: white; }
        .pagination a:hover { background: #f1f5f9; }
        .pagination .active { background: var(--primary); color: white; border-color: var(--primary); }
        .data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
        .data-table th, .data-table td { padding: 14px 20px; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .data-table th { background: #f8fafc; font-weight: 700; color: #334155; text-transform: uppercase; font-size: 0.78rem; }
    </style>
</head>
<body>

<audio id="notificationSound" preload="auto">
    <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
</audio>

<sidebar>
     <div class="sidebar-brand sidebar-logo-brand">
         <img src="ecg_logo.jpg" alt="ECG Logo" class="sidebar-logo">
    </div>
    <div class="sidebar-brand">
        <i class="fa-solid fa-bolt fa-lg"></i>
        <span>ECG Ashanti West</span>
    </div>
    <ul class="sidebar-menu">
        <li class="<?php echo ($tab == 'dashboard') ? 'active' : ''; ?>"><a href="admin.php?tab=dashboard"><i class="fa-solid fa-chart-line"></i> <span>Live Requests</span></a></li>
        <li class="<?php echo ($tab == 'users') ? 'active' : ''; ?>"><a href="admin.php?tab=users"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
        <li class="<?php echo ($tab == 'audit') ? 'active' : ''; ?>"><a href="admin.php?tab=audit"><i class="fa-solid fa-shield-halved"></i> <span>Audit Trail</span></a></li>
        <li><a href="#" class="open-export-menu"><i class="fa-solid fa-file-export"></i> <span>Export Reports</span></a></li>
        <li><a href="index.php" target="_blank"><i class="fa-solid fa-desktop"></i> <span>Client Portal</span></a></li>
        <li>
            <a href="admin_dashboard.php" class="back-dashboard-btn" title="Return to Dashboard">
                <i class="fa-solid fa-arrow-left"></i> <span>Back to Dashboard</span>
            </a>
        </li>
    </ul>
</sidebar>

<div class="main-content">
    <div class="top-navbar">
        <h2><i class="fa-solid fa-shield-halved"></i> ICT Directorate Executive Dashboard</h2>
        <div class="action-cluster">
            <?php if ($unread_count > 0): ?>
                <span id="notificationBadge" class="notification-pill">
                    <i class="fa-solid fa-bell"></i> <span id="countNum"><?php echo $unread_count; ?></span> New Request(s)
                </span>
            <?php endif; ?>
            <a href="admin.php?mark_read=1" style="font-size: 0.85rem; font-weight: 600; color: var(--primary); text-decoration: none;">Mark All Read</a>
            <a href="#" class="btn-export open-export-menu">
                <i class="fa-solid fa-file-arrow-down"></i> Export Report
            </a>
        </div>
    </div>

    <?php if ($tab == 'dashboard'): ?>
        <!-- Search, Filter & Pagination Controls -->
        <form method="GET" action="admin.php" class="filter-panel">
            <input type="hidden" name="tab" value="dashboard">
            <div>
                <label>Search Dataset</label>
                <div class="search-input-wrapper">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Staff name, ID, request type..." aria-label="Search dataset">
                </div>
            </div>
            <div>
                <label>Filter Status</label>
                <select name="filter_status">
                    <option value="">All Statuses</option>
                    <option <?php if($filter_status=='Resolved and closed') echo 'selected'; ?>>Resolved and closed</option>
                    <option <?php if($filter_status=='Resolution in progress') echo 'selected'; ?>>Resolution in progress</option>
                    <option <?php if($filter_status=='Pending (in wait of input/support)') echo 'selected'; ?>>Pending (in wait of input/support)</option>
                    <option <?php if($filter_status=='Waiting for procurement of material') echo 'selected'; ?>>Waiting for procurement of material</option>
                </select>
            </div>
            <div>
                <label>Filter Department</label>
                <select name="filter_dept">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>" <?php if ($filter_dept === $department) echo 'selected'; ?>><?php echo htmlspecialchars($department); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="admin.php?tab=dashboard" style="background:#e2e8f0; color:#334155;" class="btn-primary">Reset</a>
            </div>
        </form>

        <div class="requests-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="request-card <?php echo ($row['is_read'] == 0) ? 'unresolved-card' : ''; ?>">
                        <div class="card-header-flex">
                            <div>
                                <strong style="font-size: 1rem; color: #0f172a;">Request ID #<?php echo $row['id']; ?></strong>
                                <span style="margin-left: 12px; font-size: 0.85rem; color: var(--text-muted);"><i class="fa-regular fa-calendar"></i> Submitted: <?php echo $row['date_submitted']; ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 0.82rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; background: #e2e8f0; color: #334155;">
                                    <?php echo htmlspecialchars($row['status_of_complaint']); ?>
                                </span>
                                <a href="admin.php?delete_id=<?php echo $row['id']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to permanently delete this request record?');"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </div>

                        <div class="card-body-grid">
                            <div>
                                <div class="info-group">
                                    <label>Requesting Officer</label>
                                    <span style="font-size: 1.05rem; font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($row['name_of_staff']); ?></span> (Staff No: <strong><?php echo htmlspecialchars($row['staff_no']); ?></strong>)
                                </div>
                                <div class="info-group">
                                    <label>Job Title / Rank</label>
                                    <span style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($row['job_title_rank'] ?: 'Not Specified'); ?></span>
                                </div>
                                <div class="info-group">
                                    <label>Department / Contact</label>
                                    <?php echo htmlspecialchars($row['department']); ?> | <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($row['mobile_contact']); ?>
                                </div>
                                <div class="info-group">
                                    <label>Requested Item(s)</label>
                                    <span style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($row['request_type']); ?></span>
                                </div>
                                <div class="info-group">
                                    <label>Purpose / Justification</label>
                                    <p style="margin: 4px 0; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid var(--border);"><?php echo htmlspecialchars($row['further_details']); ?></p>
                                </div>
                            </div>

                            <div>
                                <form method="POST" class="admin-form-panel">
                                    <input type="hidden" name="update_id" value="<?php echo $row['id']; ?>">
                                    
                                    <label>Receiving ICT Officer Name</label>
                                    <input type="text" name="receiving_ict_officer" value="<?php echo htmlspecialchars($row['receiving_ict_officer'] ?? ''); ?>" required placeholder="Officer name">
                                    
                                    <label>Details of Assessment / Diagnosis</label>
                                    <textarea name="details_of_assessment" placeholder="Diagnostic notes..."><?php echo htmlspecialchars($row['details_of_assessment'] ?? ''); ?></textarea>
                                    
                                    <label>Status of Request</label>
                                    <select name="status_of_complaint">
                                        <option <?php if(($row['status_of_complaint'] ?? '')=='Resolved and closed') echo 'selected'; ?>>Resolved and closed</option>
                                        <option <?php if(($row['status_of_complaint'] ?? '')=='Resolution in progress') echo 'selected'; ?>>Resolution in progress</option>
                                        <option <?php if(($row['status_of_complaint'] ?? '')=='Pending (in wait of input/support)') echo 'selected'; ?>>Pending (in wait of input/support)</option>
                                        <option <?php if(($row['status_of_complaint'] ?? '')=='Waiting for procurement of material') echo 'selected'; ?>>Waiting for procurement of material</option>
                                    </select>

                                    <label>Status Comments / Remarks</label>
                                    <input type="text" name="status_comments" value="<?php echo htmlspecialchars($row['status_comments'] ?? ''); ?>" placeholder="Optional remarks">

                                    <hr style="margin: 15px 0; border: 0; border-top: 1px solid #cbd5e1;">
                                    <div style="font-size: 0.85rem; font-weight: 800; color: #0f172a; margin-bottom: 5px;"><i class="fa-solid fa-user-tie"></i> ICT Supervisor Sign-off</div>

                                    <label>Supervisor's Full Name</label>
                                    <input type="text" name="supervisor_name" value="<?php echo htmlspecialchars($row['supervisor_name'] ?? ''); ?>" placeholder="Supervisor name">

                                    <label>Supervisor's Signature</label>
                                    <input type="text" name="supervisor_signature" value="<?php echo htmlspecialchars($row['supervisor_signature'] ?? ''); ?>" placeholder="Signature">

                                    <label>Supervisor's Date</label>
                                    <input type="date" name="supervisor_date" value="<?php echo htmlspecialchars($row['supervisor_date'] ?? ''); ?>">

                                    <button type="submit" class="save-btn">
                                        <i class="fa-solid fa-floppy-disk"></i> Save Administrative Update
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>

                <!-- Pagination Links -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="admin.php?tab=dashboard&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_dept=<?php echo urlencode($filter_dept); ?>" class="<?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align: center; padding: 40px; background: white; border-radius: 14px; border: 1px solid var(--border);">
                    <i class="fa-regular fa-folder-open fa-2x" style="color: var(--text-muted); margin-bottom: 10px;"></i>
                    <p style="color: var(--text-muted); font-weight: 600;">No request records match your criteria.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab == 'users'): ?>
        <!-- CRUD Management for System Users -->
        <?php if ($user_notice !== ''): ?>
            <div style="background: #eff6ff; color: #1e40af; padding: 12px 16px; border: 1px solid #bfdbfe; border-radius: 8px; margin-bottom: 20px;">
                <i class="fa-solid fa-circle-info"></i> <?php echo htmlspecialchars($user_notice); ?>
            </div>
        <?php endif; ?>
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
            <div style="background: white; padding: 25px; border-radius: 14px; border: 1px solid var(--border); height: fit-content;">
                <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 700;"><i class="fa-solid fa-user-plus"></i> Add System User</h3>
                <form method="POST">
                    <input type="hidden" name="save_user" value="1">
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 5px;">Username / Staff ID</label>
                        <input type="text" name="username" required style="width: 100%; padding: 9px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 5px;">Temporary Password</label>
                        <input type="password" name="password" minlength="8" required style="width: 100%; padding: 9px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 5px;">Access Role</label>
                        <select name="role" style="width: 100%; padding: 9px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                            <option>ICT Officer</option>
                            <option>ICT Supervisor</option>
                            <option>System Administrator</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">Save User</button>
                </form>
            </div>

            <div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Created Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_result && $users_result->num_rows > 0): ?>
                            <?php while($u = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $u['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['role']); ?></td>
                                    <td><?php echo $u['created_at']; ?></td>
                                    <td>
                                        <a href="admin.php?tab=users&delete_user=<?php echo $u['id']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this user profile?');"><i class="fa-solid fa-trash"></i> Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No system users registered.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'audit'): ?>
        <!-- Activity Logs / Audit Trail -->
        <div style="background: white; border-radius: 14px; border: 1px solid var(--border); overflow: hidden;">
            <div style="padding: 20px 24px; background: #f8fafc; border-bottom: 1px solid var(--border);">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;"><i class="fa-solid fa-list-check"></i> System Audit Trail & Activity Log</h3>
                <p style="margin: 4px 0 0; font-size: 0.85rem; color: var(--text-muted);">Tracking organizational workflow transparency and operational accountability.</p>
            </div>
            <table class="data-table" style="border: none;">
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Activity Description</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($audit_result && $audit_result->num_rows > 0): ?>
                        <?php while($log = $audit_result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $log['id']; ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo $log['created_at']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No logs recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="exportMenu" class="export-menu" role="dialog" aria-modal="true" aria-labelledby="exportMenuTitle">
    <div class="export-dialog">
        <h3 id="exportMenuTitle"><i class="fa-solid fa-file-export"></i> Export Report</h3>
        <form action="export_requests.php" method="GET" target="_blank">
            <label for="exportDataset">Choose records</label>
            <select id="exportDataset" name="dataset">
                <option value="requests">Saved requests</option>
                <option value="audit">Audit trails</option>
            </select>
            <label for="exportFormat">Choose file type</label>
            <select id="exportFormat" name="format">
                <option value="excel">Excel</option>
                <option value="pdf">PDF / Print report</option>
            </select>
            <div class="export-dialog-actions">
                <button type="button" class="btn-primary btn-export-cancel close-export-menu">Cancel</button>
                <button type="submit" class="btn-export"><i class="fa-solid fa-download"></i> Download / Open</button>
            </div>
        </form>
    </div>
</div>

<script>
    const exportMenu = document.getElementById('exportMenu');
    document.querySelectorAll('.open-export-menu').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            exportMenu.classList.add('open');
        });
    });
    document.querySelectorAll('.close-export-menu').forEach(function (button) {
        button.addEventListener('click', function () {
            exportMenu.classList.remove('open');
        });
    });
    exportMenu.addEventListener('click', function (event) {
        if (event.target === exportMenu) {
            exportMenu.classList.remove('open');
        }
    });

    let lastUnreadCount = <?php echo $unread_count; ?>;

    setInterval(function() {
        fetch('fetch_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.unread > lastUnreadCount) {
                    const audio = document.getElementById('notificationSound');
                    audio.play().then(() => {
                        setTimeout(() => location.reload(), 2000);
                    }).catch(e => {
                        location.reload();
                    });
                }
                lastUnreadCount = data.unread;
            })
            .catch(err => console.log('Polling inactive'));
    }, 5000);

    document.addEventListener('click', function() {
        const audio = document.getElementById('notificationSound');
        if(audio) {
            audio.play().then(() => { audio.pause(); audio.currentTime = 0; }).catch(e => {});
        }
    }, { once: true });
</script>

</body>
</html>