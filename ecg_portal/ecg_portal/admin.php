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

// Bring older audit tables forward to the current action-based format.
$audit_action_column = $conn->query("SHOW COLUMNS FROM audit_logs LIKE 'action'");
if ($audit_action_column && $audit_action_column->num_rows === 0) {
    $conn->query("ALTER TABLE audit_logs ADD COLUMN action TEXT NULL AFTER id");
}
$audit_admin_column = $conn->query("SHOW COLUMNS FROM audit_logs LIKE 'admin_user'");
if ($audit_admin_column && $audit_admin_column->num_rows === 0) {
    $conn->query("ALTER TABLE audit_logs ADD COLUMN admin_user VARCHAR(100) NULL AFTER action");
}
$audit_performed_column = $conn->query("SHOW COLUMNS FROM audit_logs LIKE 'action_performed'");
if ($audit_performed_column && $audit_performed_column->num_rows === 0) {
    $conn->query("ALTER TABLE audit_logs ADD COLUMN action_performed TEXT NULL AFTER admin_user");
}
$audit_ip_column = $conn->query("SHOW COLUMNS FROM audit_logs LIKE 'ip_address'");
if ($audit_ip_column && $audit_ip_column->num_rows === 0) {
    $conn->query("ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) NULL AFTER action_performed");
}
$conn->query("ALTER TABLE audit_logs MODIFY admin_user VARCHAR(100) NULL");
$conn->query("ALTER TABLE audit_logs MODIFY action_performed TEXT NULL");
$conn->query("ALTER TABLE audit_logs MODIFY ip_address VARCHAR(45) NULL");

$admin_update_column = $conn->query("SHOW COLUMNS FROM requests LIKE 'admin_updated_at'");
if ($admin_update_column && $admin_update_column->num_rows === 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN admin_updated_at DATETIME NULL AFTER date_received");
}

$conn->query("CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'Administrator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Older installations may have been created before role management was added.
$role_column = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'role'");
if ($role_column && $role_column->num_rows === 0) {
    $conn->query("ALTER TABLE admin_users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'ICT Officer' AFTER password_hash");
}

$allowed_roles = ['ICT Officer', 'ICT Supervisor', 'System Administrator', 'Administrator'];

function send_alert_message(string $channel, string $recipient, string $subject, string $message): array
{
    $result = [
        'channel' => strtolower($channel),
        'recipient' => $recipient,
        'status' => 'disabled',
    ];

    $email_config = getenv('ECG_ALERT_EMAIL') ?: '';
    $sms_config = getenv('ECG_ALERT_SMS') ?: '';

    if ($channel === 'email' && $email_config === '') {
        $result['status'] = 'skipped';
        $result['detail'] = 'No outbound email gateway is configured.';
        return $result;
    }

    if ($channel === 'sms' && $sms_config === '') {
        $result['status'] = 'skipped';
        $result['detail'] = 'No outbound SMS gateway is configured.';
        return $result;
    }

    $result['status'] = 'queued';
    $result['detail'] = 'Alert queued for delivery via the configured gateway.';

    if ($channel === 'email') {
        $headers = "From: " . $email_config . "\r\n" . "Reply-To: " . $email_config . "\r\n" . "MIME-Version: 1.0\r\n" . "Content-Type: text/html; charset=UTF-8\r\n";
        @mail($recipient, $subject, $message, $headers);
    } elseif ($channel === 'sms') {
        error_log('SMS alert queued for ' . $recipient . ': ' . $message);
    }

    return $result;
}

function notify_admin_of_new_complaint(string $customer_name, string $request_type, string $department = '', string $phone = ''): array
{
    $subject = 'New complaint filed on ECG ICT portal';
    $message = "A new complaint has been filed by {$customer_name}.\n";
    $message .= "Request type: {$request_type}\n";
    if ($department !== '') {
        $message .= "Department: {$department}\n";
    }
    if ($phone !== '') {
        $message .= "Contact: {$phone}\n";
    }
    $message .= "Please review the complaint in the admin dashboard immediately.";

    $admin_email = getenv('ECG_ADMIN_ALERT_EMAIL') ?: 'admin@ecg.local';
    $admin_sms = getenv('ECG_ADMIN_ALERT_SMS') ?: '';
    $deliveries = [];

    $deliveries[] = send_alert_message('email', $admin_email, $subject, nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')));
    if ($admin_sms !== '') {
        $deliveries[] = send_alert_message('sms', $admin_sms, 'New complaint filed', $message);
    }

    return $deliveries;
}

function send_customer_resolution_alert(string $customer_email, string $customer_phone, string $service_area, string $message): array
{
    $result = [
        'status' => 'skipped',
        'channel' => 'none',
        'detail' => 'No customer notification gateway is configured.'
    ];

    $email_gateway = getenv('ECG_CUSTOMER_EMAIL_GATEWAY') ?: '';
    $sms_gateway = getenv('ECG_CUSTOMER_SMS_GATEWAY') ?: '';
    $notification_message = "Hello, this is ECG Ashanti West. The outage affecting {$service_area} has been resolved. {$message}";

    if ($customer_email !== '' && $email_gateway !== '') {
        $headers = "From: " . $email_gateway . "\r\n" . "Reply-To: " . $email_gateway . "\r\n" . "MIME-Version: 1.0\r\n" . "Content-Type: text/html; charset=UTF-8\r\n";
        @mail($customer_email, 'Outage resolved - ECG Ashanti West', nl2br(htmlspecialchars($notification_message, ENT_QUOTES, 'UTF-8')), $headers);
        $result = ['status' => 'queued', 'channel' => 'email', 'detail' => 'Resolution email queued for delivery.'];
    }

    if ($customer_phone !== '' && $sms_gateway !== '') {
        error_log('SMS outage update queued for ' . $customer_phone . ': ' . $notification_message);
        $result = ['status' => 'queued', 'channel' => 'sms', 'detail' => 'Resolution SMS queued for delivery.'];
    }

    if ($customer_email === '' && $customer_phone === '') {
        $result['detail'] = 'No email or mobile number was provided for this customer notification.';
    }

    return $result;
}

function add_audit_log(mysqli $conn, string $action): bool
{
    $admin_user = $_SESSION['admin_name'] ?? 'Unknown administrator';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $audit_stmt = $conn->prepare("INSERT INTO audit_logs (action, admin_user, action_performed, ip_address) VALUES (?, ?, ?, ?)");
    if (!$audit_stmt) {
        error_log('Unable to prepare audit log statement: ' . $conn->error);
        return false;
    }

    $audit_stmt->bind_param("ssss", $action, $admin_user, $action, $ip_address);
    $saved = $audit_stmt->execute();
    if (!$saved) {
        error_log('Unable to save audit log: ' . $audit_stmt->error);
    }
    $audit_stmt->close();
    return $saved;
}

$user_notice = $_SESSION['user_notice'] ?? '';
unset($_SESSION['user_notice']);

if (isset($_GET['send_complaint_alert'])) {
    $customer_name = trim((string)($_GET['customer_name'] ?? 'Customer'));
    $request_type = trim((string)($_GET['request_type'] ?? 'New ICT complaint'));
    $department = trim((string)($_GET['department'] ?? ''));
    $phone = trim((string)($_GET['phone'] ?? ''));

    $alert_results = notify_admin_of_new_complaint($customer_name, $request_type, $department, $phone);
    $_SESSION['user_notice'] = 'Complaint alert queued for admin delivery.';
    if (isset($alert_results[0]['status']) && $alert_results[0]['status'] === 'queued') {
        $_SESSION['user_notice'] = 'Complaint alert sent to the configured admin email/SMS channels.';
    }
}

// Handle Mark All Read
if (isset($_GET['mark_read'])) {
    $conn->query("UPDATE requests SET is_read = 1 WHERE is_read = 0");
    add_audit_log($conn, 'Marked all incoming requests as read');
    header("Location: admin.php");
    exit;
}

// Handle Record Deletion (CRUD)
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM requests WHERE id = $del_id");
    add_audit_log($conn, "Deleted request record ID #$del_id");
    header("Location: admin.php?tab=records");
    exit;
}

// Handle User CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'ICT Officer');

    if (!in_array($role, $allowed_roles, true)) {
        $_SESSION['user_notice'] = 'Select a valid access role.';
    } elseif ($username !== '' && strlen($password) >= 8) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user_stmt = $conn->prepare("INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)");
        if (!$user_stmt) {
            error_log('Unable to prepare user creation statement: ' . $conn->error);
            $_SESSION['user_notice'] = 'Unable to create the user because the user table is not available.';
        } else {
            $user_stmt->bind_param("sss", $username, $password_hash, $role);
            if ($user_stmt->execute()) {
                add_audit_log($conn, "Added new system user: $username");
                $user_stmt->close();
                $_SESSION['user_notice'] = 'User account created successfully.';
            } else {
                $_SESSION['user_notice'] = $user_stmt->errno === 1062
                    ? 'Unable to create the user. The username already exists.'
                    : 'Unable to create the user.';
                $user_stmt->close();
            }
        }
    } else {
        $_SESSION['user_notice'] = 'Enter a username and a password with at least 8 characters.';
    }
    header("Location: admin.php?tab=users");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    if ($user_id <= 0 || $username === '' || !in_array($role, $allowed_roles, true)) {
        $_SESSION['user_notice'] = 'Enter a valid username and access role.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $_SESSION['user_notice'] = 'A new password must contain at least 8 characters.';
    } else {
        if ($password !== '') {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE admin_users SET username = ?, password_hash = ?, role = ? WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("sssi", $username, $password_hash, $role, $user_id);
            }
        } else {
            $update_stmt = $conn->prepare("UPDATE admin_users SET username = ?, role = ? WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("ssi", $username, $role, $user_id);
            }
        }

        if (!isset($update_stmt) || !$update_stmt) {
            error_log('Unable to prepare user update statement: ' . $conn->error);
            $_SESSION['user_notice'] = 'Unable to update the user account.';
        } elseif ($update_stmt->execute()) {
            if ($user_id === (int)($_SESSION['admin_id'] ?? 0)) {
                $_SESSION['admin_name'] = $username;
                $_SESSION['admin_role'] = $role;
            }
            add_audit_log($conn, "Updated system user: $username");
            $_SESSION['user_notice'] = 'User account updated successfully.';
            $update_stmt->close();
        } else {
            $_SESSION['user_notice'] = $update_stmt->errno === 1062
                ? 'Unable to update the user. The username already exists.'
                : 'Unable to update the user account.';
            $update_stmt->close();
        }
    }
    header("Location: admin.php?tab=users");
    exit;
}

if (isset($_GET['delete_user'])) {
    $u_id = (int)$_GET['delete_user'];
    if ($u_id === (int)($_SESSION['admin_id'] ?? 0)) {
        $_SESSION['user_notice'] = 'You cannot delete the account currently being used.';
    } elseif ($conn->query("DELETE FROM admin_users WHERE id = $u_id")) {
        add_audit_log($conn, "Deleted system user ID #$u_id");
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

    $existing_request = $conn->query("SELECT name_of_staff, department, mobile_contact, status_of_complaint FROM requests WHERE id = $id LIMIT 1");
    $existing_row = $existing_request ? $existing_request->fetch_assoc() : null;
    $previous_status = $existing_row['status_of_complaint'] ?? '';

    $sql = "UPDATE requests SET 
            receiving_ict_officer='$officer', 
            date_received=CURDATE(), 
            admin_updated_at=NOW(),
            details_of_assessment='$assessment', 
            status_of_complaint='$status', 
            status_comments='$comments',
            supervisor_name='$sup_name',
            supervisor_signature='$sup_sig',
            supervisor_date=" . (!empty($sup_date) ? "'$sup_date'" : "NULL") . ",
            is_read=1 
            WHERE id=$id";
    $conn->query($sql);

    if ($status === 'Resolved and closed' && $previous_status !== 'Resolved and closed') {
        $customer_name = trim((string)($existing_row['name_of_staff'] ?? 'Customer'));
        $service_area = trim((string)($existing_row['department'] ?? 'your service area'));
        $customer_phone = trim((string)($existing_row['mobile_contact'] ?? ''));
        $customer_alert = send_customer_resolution_alert('', $customer_phone, $service_area, "Your complaint submitted by {$customer_name} has been resolved.");
        if (($customer_alert['status'] ?? 'skipped') === 'queued') {
            add_audit_log($conn, "Sent outage resolution alert for request ID #$id via {$customer_alert['channel']}");
        }
    }

    add_audit_log($conn, "Updated assessment and status for request ID #$id");
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
$result = $conn->query("SELECT * FROM requests WHERE $where_sql ORDER BY is_read ASC, id DESC LIMIT $limit OFFSET $offset");

// Metrics & lists
$unread_result = $conn->query("SELECT COUNT(*) as unread FROM requests WHERE is_read = 0");
$unread_count = ($unread_result) ? $unread_result->fetch_assoc()['unread'] : 0;
$latest_unread_result = $conn->query("SELECT id FROM requests WHERE is_read = 0 ORDER BY id DESC LIMIT 1");
$latest_unread_row = $latest_unread_result ? $latest_unread_result->fetch_assoc() : null;
$latest_unread_id = $latest_unread_row ? (int)$latest_unread_row['id'] : 0;
$total_result = $conn->query("SELECT COUNT(*) as total FROM requests");
$total_count = ($total_result) ? $total_result->fetch_assoc()['total'] : 0;

$users_result = $conn->query("SELECT * FROM admin_users ORDER BY id DESC");
$audit_result = $conn->query("SELECT id, COALESCE(action, action_performed) AS action, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 20");
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
    <link rel="icon" type="image/jpeg" href="ecg_logo.jpg">
    <link rel="apple-touch-icon" href="ecg_logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #172554;
            --main-bg: #eef2ff;
            --card-bg: rgba(255, 255, 255, .9);
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --text-main: #172554;
            --text-muted: #64748b;
            --border: rgba(148, 163, 184, .3);
            --danger: #e11d48;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 52%, #ecfeff 100%);
            color: var(--text-main);
            margin: 0;
            display: flex;
            min-height: 100vh;
        }
        sidebar {
            width: 260px;
            background: linear-gradient(180deg, #172554 0%, #312e81 58%, #1e1b4b 100%);
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
            min-height: 220px;
            padding: 20px 16px 18px;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            text-align: center;
            box-sizing: border-box;
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
            box-shadow: 0 10px 24px rgba(15, 23, 42, .28);
        }
        .sidebar-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            color: #bfdbfe;
            font-size: 1.15rem;
            line-height: 1.25;
            text-align: center;
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
            background: linear-gradient(90deg, rgba(129, 140, 248, .25), rgba(129, 140, 248, .04));
            border-left: 4px solid #f97316;
        }
        .main-content {
            margin-left: 260px;
            flex-grow: 1;
            padding: 24px;
            box-sizing: border-box;
            min-width: 0;
        }
        .top-navbar {
            background: var(--card-bg);
            padding: 20px 24px;
            border-radius: 18px;
            box-shadow: 0 14px 32px rgba(49, 46, 129, .1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, .8);
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
            background: linear-gradient(135deg, #059669, #14b8a6);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
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
            padding: 22px;
            border-radius: 18px;
            border: 1px solid var(--border);
            margin-bottom: 24px;
            box-shadow: 0 10px 28px rgba(49, 46, 129, .06);
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
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; border: none; padding: 10px 18px; border-radius: 9px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: transform .2s, box-shadow .2s; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger { background: var(--danger); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-size: 0.82rem; }
        .requests-grid { display: grid; gap: 20px; }
        .request-card {
            background: var(--card-bg);
            border-radius: 18px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 10px 26px rgba(49, 46, 129, .07);
        }
        .request-card.unresolved-card { border: 2px solid #f59e0b; border-left-width: 8px; background: #fffaf0; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.14), 0 10px 22px rgba(245, 158, 11, 0.12); animation: pending-request-pulse 2.2s ease-in-out infinite; }
        @keyframes pending-request-pulse {
            0%, 100% { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.14), 0 10px 22px rgba(245, 158, 11, 0.12); }
            50% { border-color: #dc2626; box-shadow: 0 0 0 6px rgba(220, 38, 38, 0.14), 0 12px 26px rgba(220, 38, 38, 0.16); }
        }
        .new-request-label { display: inline-flex; align-items: center; gap: 5px; margin-left: 10px; padding: 4px 8px; border-radius: 999px; background: #fee2e2; color: #b91c1c; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .notification-sound-btn { border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; padding: 8px 11px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; cursor: pointer; }
        .notification-sound-btn.enabled { background: #dcfce7; border-color: #86efac; color: #166534; }
        .card-header-flex {
            padding: 16px 24px;
            background: linear-gradient(110deg, #eef2ff, #fff7ed);
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
            .sidebar-logo-brand { min-height: 120px; padding: 12px 6px; gap: 6px; }
            .sidebar-logo { width: 56px; height: 56px; padding: 3px; }
            sidebar .sidebar-brand span, sidebar .sidebar-menu span { display: none; }
            .sidebar-title { width: auto; }
            .main-content { margin-left: 70px; }
        }
        @media (max-width: 700px) {
            sidebar { width: 58px; }
            .sidebar-logo-brand { min-height: 86px; padding: 10px 4px; }
            .sidebar-logo { width: 42px; height: 42px; }
            .sidebar-title { display: none; }
            .sidebar-menu { padding-top: 12px; }
            .sidebar-menu li a { justify-content: center; padding: 14px 4px; font-size: 1rem; }
            .main-content { margin-left: 58px; padding: 12px 9px; }
            .top-navbar { align-items: flex-start; flex-direction: column; gap: 14px; padding: 16px; margin-bottom: 16px; }
            .top-navbar h2 { font-size: 1rem; line-height: 1.35; }
            .action-cluster { width: 100%; flex-wrap: wrap; gap: 8px; }
            .notification-pill, .notification-sound-btn, .btn-export { font-size: .72rem; }
            .filter-panel { padding: 16px; gap: 12px; margin-bottom: 16px; }
            .filter-panel > div:last-child { flex-wrap: wrap; }
            .filter-panel > div:last-child .btn-primary { flex: 1; justify-content: center; }
            .card-header-flex { align-items: flex-start; flex-direction: column; gap: 12px; padding: 16px; }
            .card-header-flex > div:last-child { width: 100%; justify-content: space-between; }
            .new-request-label { margin-left: 0; margin-top: 7px; }
            .card-body-grid { padding: 16px; gap: 18px; }
            .admin-form-panel { padding: 15px; }
            .pagination { flex-wrap: wrap; gap: 5px; }
            .pagination a, .pagination span { padding: 7px 10px; }
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
        .admin-form-panel {         background: linear-gradient(135deg, #f8fafc, #eef2ff); padding: 20px; border-radius: 14px; border: 1px solid #c7d2fe; }
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
        .users-layout { display: grid; grid-template-columns: minmax(230px, 0.8fr) minmax(0, 2fr); gap: 25px; align-items: start; }
        .user-create-card, .user-list-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04); }
        .user-create-card { padding: 24px; }
        .user-create-card h3, .user-list-header h3 { margin: 0; color: #0f172a; }
        .user-create-card h3 { font-size: 1.05rem; }
        .user-create-card .field { margin-top: 17px; }
        .user-create-card label { display: block; margin-bottom: 6px; color: var(--text-muted); font-size: 0.76rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; }
        .user-create-card input, .user-create-card select, .user-edit-form input, .user-edit-form select {
            width: 100%; min-width: 0; padding: 10px 11px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; background: #fff; color: var(--text-main); font: inherit;
        }
        .user-create-card input:focus, .user-create-card select:focus, .user-edit-form input:focus, .user-edit-form select:focus { outline: 3px solid rgba(37, 99, 235, 0.15); border-color: var(--primary); }
        .user-create-card .btn-primary { margin-top: 20px; min-height: 42px; }
        .user-list-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 20px 22px; border-bottom: 1px solid var(--border); background: linear-gradient(135deg, #eff6ff, #ffffff); border-radius: 16px 16px 0 0; }
        .user-list-header p { margin: 4px 0 0; color: var(--text-muted); font-size: 0.8rem; }
        .user-table-wrap { padding: 14px; }
        .users-grid { display: grid; gap: 12px; }
        .user-row { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; grid-template-areas: "id account actions"; gap: 16px; align-items: center; padding: 14px; border: 1px solid var(--border); border-radius: 12px; background: #fff; }
        .user-row:hover { border-color: #bfdbfe; box-shadow: 0 5px 14px rgba(37, 99, 235, 0.08); }
        .user-account { min-width: 0; grid-area: account; }
        .user-account-name { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .user-meta { color: var(--text-muted); font-size: 0.75rem; margin-top: 5px; }
        .user-id { color: var(--text-muted); font-weight: 700; grid-area: id; }
        .user-name { color: #0f172a; font-weight: 700; overflow-wrap: anywhere; }
        .role-badge { display: inline-flex; align-items: center; padding: 5px 9px; border-radius: 999px; background: #dbeafe; color: #1e40af; font-size: 0.74rem; font-weight: 800; white-space: nowrap; }
        .user-edit-form { display: grid; grid-template-columns: minmax(120px, 1fr) minmax(130px, 1fr) minmax(140px, 1.2fr) auto; gap: 7px; align-items: center; }
        .user-edit-form .btn-primary { padding: 10px 12px; white-space: nowrap; }
        .user-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; grid-area: actions; }
        .user-delete { white-space: nowrap; }
        @media (max-width: 1180px) {
            .users-layout { grid-template-columns: 1fr; }
            .user-create-card { max-width: none; }
        }
        @media (max-width: 700px) {
            .user-list-header { align-items: flex-start; flex-direction: column; }
            .user-table-wrap { padding: 10px; }
            .user-row { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "account actions" "id actions"; gap: 10px; }
            .user-id { align-self: end; }
            .user-edit-form { grid-template-columns: 1fr 1fr; }
            .user-edit-form input[type="password"] { grid-column: span 2; }
            .user-edit-form .btn-primary { width: 100%; justify-content: center; }
            .user-actions { align-items: flex-end; }
        }
    </style>
</head>
<body>

<sidebar>
     <div class="sidebar-brand sidebar-logo-brand">
         <img src="ecg_logo.jpg" alt="ECG Logo" class="sidebar-logo">
        <div class="sidebar-title">
            <i class="fa-solid fa-bolt fa-lg"></i>
            <span>ECG Ashanti West</span>
        </div>
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
            <button type="button" id="enableNotificationSound" class="notification-sound-btn" title="Enable and test notification sound">
                <i class="fa-solid fa-volume-high"></i> Enable Sound
            </button>
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
                                <?php if ((int)$row['is_read'] === 0): ?>
                                    <span class="new-request-label"><i class="fa-solid fa-circle-exclamation"></i> New - Action required</span>
                                <?php endif; ?>
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
        <div class="users-layout">
            <div class="user-create-card">
                <h3><i class="fa-solid fa-user-plus" style="color: var(--primary);"></i> Add System User</h3>
                <form method="POST">
                    <input type="hidden" name="save_user" value="1">
                    <div class="field">
                        <label>Username / Staff ID</label>
                        <input type="text" name="username" required placeholder="e.g. ICT-001">
                    </div>
                    <div class="field">
                        <label>Temporary Password</label>
                        <input type="password" name="password" minlength="8" required placeholder="At least 8 characters">
                    </div>
                    <div class="field">
                        <label>Access Role</label>
                        <select name="role">
                            <option>ICT Officer</option>
                            <option>ICT Supervisor</option>
                            <option>System Administrator</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;"><i class="fa-solid fa-user-check"></i> Save User</button>
                </form>
            </div>

            <div class="user-list-card">
                <div class="user-list-header">
                    <div>
                        <h3><i class="fa-solid fa-users" style="color: var(--primary);"></i> System Users</h3>
                        <p>Edit access details below. Leave password blank to keep it unchanged.</p>
                    </div>
                    <span class="role-badge"><i class="fa-solid fa-lock" style="margin-right: 5px;"></i> Protected</span>
                </div>
                <div class="user-table-wrap">
                <div class="users-grid">
                        <?php if ($users_result && $users_result->num_rows > 0): ?>
                            <?php while($u = $users_result->fetch_assoc()): ?>
                                <div class="user-row">
                                    <div class="user-id">#<?php echo $u['id']; ?></div>
                                    <div class="user-account">
                                        <div class="user-account-name">
                                            <span class="user-name"><?php echo htmlspecialchars($u['username']); ?></span>
                                            <span class="role-badge"><?php echo htmlspecialchars($u['role']); ?></span>
                                        </div>
                                        <div class="user-meta">Created <?php echo htmlspecialchars(date('M j, Y', strtotime($u['created_at']))); ?></div>
                                        <form method="POST" class="user-edit-form">
                                            <input type="hidden" name="update_user" value="1">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="text" name="username" value="<?php echo htmlspecialchars($u['username']); ?>" required aria-label="Username">
                                            <select name="role" aria-label="Access role">
                                                <?php foreach ($allowed_roles as $available_role): ?>
                                                    <option value="<?php echo htmlspecialchars($available_role); ?>" <?php echo $u['role'] === $available_role ? 'selected' : ''; ?>><?php echo htmlspecialchars($available_role); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="password" name="password" minlength="8" placeholder="New password (optional)" aria-label="New password">
                                            <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                                        </form>
                                    </div>
                                    <div class="user-actions">
                                        <a href="admin.php?tab=users&delete_user=<?php echo $u['id']; ?>" class="btn-danger user-delete" onclick="return confirm('Are you sure you want to permanently delete this user profile?');"><i class="fa-solid fa-trash"></i> Delete</a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 30px 10px;">No system users registered.</div>
                        <?php endif; ?>
                </div>
                </div>
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
                <option value="excel">Excel (.xls)</option>
                <option value="csv">CSV Spreadsheet (.csv)</option>
                <option value="doc">Microsoft Word (.doc)</option>
                <option value="txt">Plain Text (.txt)</option>
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
    let lastUnreadId = <?php echo $latest_unread_id; ?>;
    let notificationAudioContext = null;

    async function unlockNotificationSound() {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) {
            return false;
        }

        if (!notificationAudioContext) {
            notificationAudioContext = new AudioContext();
        }
        if (notificationAudioContext.state === 'suspended') {
            await notificationAudioContext.resume();
        }
        return notificationAudioContext.state === 'running';
    }

    async function playNotificationSound() {
        if (!await unlockNotificationSound()) {
            return;
        }

        const now = notificationAudioContext.currentTime;
        [880, 1175].forEach(function (frequency, index) {
            const oscillator = notificationAudioContext.createOscillator();
            const gain = notificationAudioContext.createGain();
            const start = now + (index * 0.16);
            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.24, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.38);
            oscillator.connect(gain);
            gain.connect(notificationAudioContext.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.4);
        });
    }

    function pollNotifications() {
        fetch('fetch_notifications.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Notification request failed');
                }
                return response.json();
            })
            .then(data => {
                if (data.unread > lastUnreadCount || data.latest_unread_id > lastUnreadId) {
                    playNotificationSound();
                    setTimeout(() => location.reload(), 2000);
                }
                lastUnreadCount = data.unread;
                lastUnreadId = data.latest_unread_id;
            })
            .catch(() => {});
    }

    const enableSoundButton = document.getElementById('enableNotificationSound');
    if (enableSoundButton) {
        enableSoundButton.addEventListener('click', function () {
            playNotificationSound();
            enableSoundButton.classList.add('enabled');
            enableSoundButton.innerHTML = '<i class="fa-solid fa-volume-high"></i> Sound Enabled';
        });
    }

    pollNotifications();
    setInterval(pollNotifications, 5000);

    document.addEventListener('click', function() {
        unlockNotificationSound();
    }, { once: true });
</script>

</body>
</html>
