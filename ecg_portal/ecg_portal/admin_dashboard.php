<?php
// Keep local XAMPP HTTP development working; enforce HTTPS when the site is deployed behind TLS.
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if (!$is_https && ($_SERVER['SERVER_NAME'] ?? '') !== 'localhost' && ($_SERVER['SERVER_ADDR'] ?? '') !== '127.0.0.1') {
    $secure_host = $_SERVER['SERVER_NAME'] ?? '';
    if ($secure_host === '') {
        http_response_code(400);
        exit('Unable to determine secure host.');
    }
    $https_url = 'https://' . $secure_host . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $https_url, true, 301);
    exit;
}

session_set_cookie_params([
    'httponly' => true,
    'secure' => $is_https,
    'samesite' => 'Lax'
]);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
if ($is_https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
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

$conn = new mysqli('localhost', 'root', '', 'ecg_ashanti_ict_db');
if ($conn->connect_error) {
    http_response_code(503);
    exit('Unable to load dashboard data.');
}

function dashboard_count(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    if (!$result) {
        error_log('Dashboard query failed: ' . $conn->error);
        return [];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

$status_label = "COALESCE(NULLIF(TRIM(status_of_complaint), ''), 'Unspecified')";
$department_label = "COALESCE(NULLIF(TRIM(department), ''), 'Unspecified')";
$status_rows = dashboard_count($conn, "SELECT {$status_label} AS label, COUNT(*) AS total FROM requests GROUP BY {$status_label} ORDER BY total DESC, label ASC");
$department_rows = dashboard_count($conn, "SELECT {$department_label} AS label, COUNT(*) AS total FROM requests GROUP BY {$department_label} ORDER BY total DESC, label ASC LIMIT 8");
$monthly_rows = dashboard_count($conn, "SELECT DATE_FORMAT(date_submitted, '%b %Y') AS label, COUNT(*) AS total FROM requests GROUP BY DATE_FORMAT(date_submitted, '%Y-%m') ORDER BY DATE_FORMAT(date_submitted, '%Y-%m') DESC LIMIT 6");
$monthly_rows = array_reverse($monthly_rows);
$summary_rows = dashboard_count($conn, "SELECT COUNT(*) AS total, SUM(is_read = 0) AS unread, SUM(status_of_complaint = 'Resolved and closed') AS resolved FROM requests");
$summary = $summary_rows[0] ?? ['total' => 0, 'unread' => 0, 'resolved' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECG Ashanti West - Admin Dashboard</title>
    <link rel="icon" type="image/jpeg" href="ecg_logo.jpg">
    <link rel="apple-touch-icon" href="ecg_logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #312e81;
            --primary-light: #6366f1;
            --accent: #f97316;
            --bg-gradient: linear-gradient(135deg, #eef2ff 0%, #f8fafc 48%, #ecfeff 100%);
            --card-bg: rgba(255, 255, 255, .88);
            --text-main: #172554;
            --text-muted: #64748b;
            --border: rgba(148, 163, 184, .28);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            margin: 0;
            padding: 24px 16px;
            min-height: 100vh;
        }
        .dashboard-container {
            max-width: 920px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(49, 46, 129, .14);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .8);
        }
        .dashboard-header {
            position: relative;
            isolation: isolate;
            background: linear-gradient(120deg, #172554 0%, #312e81 52%, #4f46e5 100%);
            color: white;
            padding: 22px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            overflow: hidden;
        }
        .dashboard-header::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            right: -80px;
            top: -150px;
            background: rgba(129, 140, 248, .35);
            z-index: -1;
        }
        .brand-section {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .brand-logo {
            width: 54px;
            height: 54px;
            object-fit: contain;
            border-radius: 50%;
            background: white;
            padding: 4px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .22);
        }
        .dashboard-header h1 {
            margin: 0;
            font-size: clamp(1.1rem, 2vw, 1.45rem);
            letter-spacing: -.02em;
        }
        .dashboard-header p {
            margin: 3px 0 0 0;
            font-size: 0.82rem;
            color: #c7d2fe;
        }
        .logout-btn {
            background: rgba(255, 255, 255, .12);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .24);
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background .2s, transform .2s;
        }
        .logout-btn:hover {
            background: rgba(239, 68, 68, .85);
            transform: translateY(-2px);
        }
        .dashboard-body {
            padding: 26px;
        }
        .welcome-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(110deg, #eef2ff, #f5f3ff 55%, #fff7ed);
            border: 1px solid #ddd6fe;
            padding: 24px 26px;
            border-radius: 16px;
            margin-bottom: 24px;
            color: #3730a3;
        }
        .welcome-card::after {
            content: "\f135";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 28px;
            top: 50%;
            transform: translateY(-50%) rotate(-12deg);
            color: rgba(99, 102, 241, .14);
            font-size: 4.8rem;
        }
        .welcome-card h3 {
            margin: 0 0 8px 0;
            font-size: 1.2rem;
        }
        .welcome-card p {
            margin: 0;
            font-size: 0.95rem;
            color: #6366f1;
        }
        .nav-card {
            display: block;
            background: rgba(255, 255, 255, .72);
            border: 1px solid var(--border);
            padding: 22px;
            border-radius: 16px;
            text-decoration: none;
            color: var(--text-main);
            transition: all .25s;
            margin-bottom: 15px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
        }
        .nav-card:hover {
            background: #fff;
            border-color: #a5b4fc;
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(79, 70, 229, .12);
        }
        .nav-card h4 { margin: 0 0 7px 0; color: var(--primary); font-size: 1rem; }
        .nav-card h4 i { color: var(--accent); margin-right: 5px; }
        .nav-card p { margin: 0; font-size: 0.85rem; color: var(--text-muted); }
        .dashboard-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-bottom: 25px; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 26px; }
        .stat-card { position: relative; overflow: hidden; padding: 22px; border-radius: 18px; color: white; box-shadow: 0 14px 26px rgba(15, 23, 42, .12); }
        .stat-card::after { position: absolute; right: 18px; bottom: -12px; font-family: "Font Awesome 6 Free"; font-weight: 900; font-size: 4rem; opacity: .15; }
        .stat-card:nth-child(1)::after { content: "\f0ae"; }
        .stat-card:nth-child(2)::after { content: "\f017"; }
        .stat-card:nth-child(3)::after { content: "\f058"; }
        .stat-card small { display: block; opacity: .9; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; font-size: .72rem; }
        .stat-card strong { display: block; margin-top: 10px; font-size: 2.15rem; letter-spacing: -.04em; }
        .stat-blue { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
        .stat-amber { background: linear-gradient(135deg, #ea580c, #f59e0b); }
        .stat-green { background: linear-gradient(135deg, #059669, #14b8a6); }
        .charts-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-bottom: 25px; }
        .chart-card { min-width: 0; background: rgba(255, 255, 255, .8); border: 1px solid var(--border); border-radius: 18px; padding: 21px; box-shadow: 0 8px 24px rgba(15, 23, 42, .04); }
        .chart-card.wide { grid-column: 1 / -1; }
        .chart-card h3 { margin: 0 0 15px; color: var(--text-main); font-size: 1rem; }
        .chart-wrap { position: relative; height: 260px; }
        @media (max-width: 900px) {
            body { padding: 18px 12px; }
            .dashboard-header { padding: 20px 22px; }
            .dashboard-body { padding: 22px; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .stat-card:last-child { grid-column: 1 / -1; }
        }
        @media (max-width: 700px) {
            body { padding: 10px 8px; }
            .dashboard-container { border-radius: 18px; }
            .dashboard-header { align-items: flex-start; flex-direction: column; gap: 15px; }
            .brand-section { align-items: flex-start; }
            .brand-logo { width: 46px; height: 46px; }
            .dashboard-header h1 { font-size: 1.05rem; line-height: 1.35; }
            .dashboard-header p { font-size: .78rem; }
            .logout-btn { width: 100%; justify-content: center; }
            .dashboard-body { padding: 18px 14px; }
            .dashboard-actions, .stats-grid, .charts-grid { grid-template-columns: 1fr; }
            .stat-card:last-child { grid-column: auto; }
            .stat-card { padding: 19px; }
            .stat-card strong { font-size: 1.85rem; }
            .welcome-card { padding: 20px; }
            .welcome-card h3 { font-size: 1.05rem; }
            .chart-card { padding: 16px; }
            .chart-card.wide { grid-column: auto; }
            .chart-wrap { height: 220px; }
            .welcome-card::after { right: 12px; font-size: 3.2rem; }
        }
        @media (max-width: 420px) {
            .dashboard-header { padding: 18px 16px; }
            .brand-section { gap: 10px; }
            .dashboard-body { padding: 14px 10px; }
            .welcome-card { padding: 17px; }
            .welcome-card p { max-width: 75%; font-size: .86rem; }
            .chart-card h3 { font-size: .9rem; }
            .chart-wrap { height: 190px; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="brand-section">
            <img src="ecg_logo.jpg" alt="ECG Logo" class="brand-logo">
            <div>
                <h1>ECG Ashanti West - Admin Control Panel</h1>
                <p>Welcome back, <?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <a href="logout.php?scope=admin" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="dashboard-body">
        <div class="welcome-card">
            <h3>Dashboard Overview</h3>
            <p>You are successfully logged in as an administrator.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card stat-blue"><small>Total Requests</small><strong><?php echo (int)$summary['total']; ?></strong></div>
            <div class="stat-card stat-amber"><small>Awaiting Action</small><strong><?php echo (int)$summary['unread']; ?></strong></div>
            <div class="stat-card stat-green"><small>Resolved</small><strong><?php echo (int)$summary['resolved']; ?></strong></div>
        </div>

        <div class="charts-grid">
            <div class="chart-card wide">
                <h3><i class="fa-solid fa-chart-line" style="color: var(--primary-light);"></i> Requests Over Time</h3>
                <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3><i class="fa-solid fa-chart-pie" style="color: var(--accent);"></i> Request Status</h3>
                <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3><i class="fa-solid fa-building" style="color: var(--primary);"></i> Top Departments</h3>
                <div class="chart-wrap"><canvas id="departmentChart"></canvas></div>
            </div>
        </div>

        <div class="dashboard-actions">
            <a href="admin.php" class="nav-card">
                <h4><i class="fa-solid fa-list-check"></i> Manage ICT Requests</h4>
                <p>View, search, filter, assign, and update staff ICT requests.</p>
            </a>
            <a href="admin.php?tab=audit" class="nav-card">
                <h4><i class="fa-solid fa-shield-halved"></i> Activity Logs / Audit Trail</h4>
                <p>Review administrative actions and system activity history.</p>
            </a>
        </div>
    </div>
</div>

<script>
    const statusData = <?php echo json_encode($status_rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const departmentData = <?php echo json_encode($department_rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const monthlyData = <?php echo json_encode($monthly_rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const chartColors = ['#2563eb', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4', '#f97316', '#64748b'];

    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: { labels: monthlyData.map(item => item.label), datasets: [{ label: 'Requests', data: monthlyData.map(item => Number(item.total)), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.14)', fill: true, tension: .35 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: statusData.map(item => item.label), datasets: [{ data: statusData.map(item => Number(item.total)), backgroundColor: chartColors }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    new Chart(document.getElementById('departmentChart'), {
        type: 'bar',
        data: { labels: departmentData.map(item => item.label), datasets: [{ label: 'Requests', data: departmentData.map(item => Number(item.total)), backgroundColor: '#10b981', borderRadius: 6 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
</script>

</body>
</html>