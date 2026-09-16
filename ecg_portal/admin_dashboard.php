<?php
//check isset routing
session_start();
//echo($_SESSION['admin_logged_in']);

// STRICT VERIFICATION CHECK: Force redirect back to login if unverified
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// // Check if admin is logged in
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header("Location: login.php");
//     exit();
// }

$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECG Ashanti West - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-light: #3b82f6;
            --accent: #f59e0b;
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #475569;
            --border: #cbd5e1;
        }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            margin: 0;
            padding: 30px 20px;
            min-height: 100vh;
        }
        .dashboard-container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border-top: 6px solid var(--primary);
        }
        .dashboard-header {
            background: linear-gradient(to right, #0f172a, #1e3a8a);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 4px solid var(--accent);
        }
        .brand-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .brand-logo {
            width: 45px;
            height: 45px;
            object-fit: contain;
            border-radius: 50%;
            background: white;
            padding: 2px;
        }
        .dashboard-header h1 {
            margin: 0;
            font-size: 1.25rem;
        }
        .dashboard-header p {
            margin: 3px 0 0 0;
            font-size: 0.82rem;
            color: #93c5fd;
        }
        .logout-btn {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.4);
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.3);
            color: #fff;
        }
        .dashboard-body {
            padding: 30px;
        }
        .welcome-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            color: #1e40af;
        }
        .welcome-card h3 {
            margin: 0 0 8px 0;
        }
        .welcome-card p {
            margin: 0;
            font-size: 0.95rem;
        }
        .nav-card {
            display: block;
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-main);
            transition: all 0.2s;
            margin-bottom: 15px;
        }
        .nav-card:hover {
            background: #edf2f7;
            border-color: var(--primary-light);
            transform: translateY(-2px);
        }
        .nav-card h4 { margin: 0 0 5px 0; color: var(--primary); }
        .nav-card p { margin: 0; font-size: 0.85rem; color: var(--text-muted); }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="brand-section">
            <img src="ecg_logo.jpg" alt="ECG Logo" class="brand-logo">
            <div>
                <h1>ECG Ashanti West - Admin Control Panel</h1>
                <p>Welcome back, <?php echo htmlspecialchars($admin_name); ?></p>
            </div>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="dashboard-body">
        <div class="welcome-card">
            <h3>Dashboard Overview</h3>
            <p>You are successfully logged in as an administrator.</p>
        </div>

        <a href="admin.php" class="nav-card">
            <h4>Manage ICT Requests</h4>
            <p>View, search, filter, assign, and update status of staff ICT requests.</p>
        </a>
    </div>
</div>

</body>
</html>