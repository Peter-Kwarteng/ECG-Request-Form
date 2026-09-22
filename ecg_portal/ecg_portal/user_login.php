<?php
session_start();

$host = 'localhost';
$db   = 'ecg_ashanti_ict_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_no = trim($conn->real_escape_string($_POST['staff_no']));
    // Note: 'name_of_staff' was in your HTML form but not used in the query below based on your original code.

    if (!empty($staff_no)) {
        // Check if at least one request exists for this staff number to allow login
        $stmt = $conn->prepare("SELECT name_of_staff, staff_no, department FROM requests WHERE staff_no = ? LIMIT 1");
        $stmt->bind_param("s", $staff_no);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $staff_data = $result->fetch_assoc();
            $_SESSION['user_logged_in'] = true;
            $_SESSION['client_staff_no'] = $staff_data['staff_no'];
            $_SESSION['client_name'] = $staff_data['name_of_staff'];
            $_SESSION['client_dept'] = $staff_data['department'];
            
            header("Location: user_dashboard.php");
            exit;
        } else {
            $error = "No requests found matching this Staff Number. Please submit a request first or check your number.";
        }
    } else {
        $error = "Please enter your Staff Number.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal Login - ECG Ashanti West ICT</title>
    <link rel="icon" type="image/jpeg" href="ecg_logo.jpg">
    <link rel="apple-touch-icon" href="ecg_logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: linear-gradient(135deg, #eef2ff 0%, #f8fafc 48%, #ecfeff 100%);
            --card: rgba(255, 255, 255, .92);
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --text: #172554;
            --muted: #64748b;
            --border: rgba(148, 163, 184, .35);
            --error-bg: #fff1f2;
            --error-text: #be123c;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 24px 16px;
        }
        .auth-card {
            position: relative;
            overflow: hidden;
            background: var(--card);
            width: 100%;
            max-width: 400px;
            padding: 42px 38px 32px;
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(15, 118, 110, .14);
            border: 1px solid rgba(255, 255, 255, .85);
        }
        .auth-card::before {
            content: "";
            position: absolute;
            width: 190px;
            height: 190px;
            border-radius: 50%;
            right: -90px;
            top: -95px;
            background: rgba(129, 140, 248, .24);
            pointer-events: none;
        }
        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand-header i {
            display: inline-grid;
            place-items: center;
            width: 68px;
            height: 68px;
            font-size: 2rem;
            color: white;
            margin-bottom: 14px;
            border-radius: 20px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            box-shadow: 0 10px 20px rgba(79, 70, 229, .25);
        }
        .brand-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 800;
        }
        .brand-header p {
            margin: 5px 0 0 0;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            background: rgba(248, 250, 252, .85);
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .form-group input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, .15);
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            color: white;
            border: none;
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            box-shadow: 0 10px 18px rgba(79, 70, 229, .2);
            transition: transform .2s, box-shadow .2s;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, var(--primary-dark), #6d28d9);
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(79, 70, 229, .28);
        }
        .alert {
            padding: 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #fecdd3;
            background: var(--error-bg);
            color: var(--error-text);
        }
        .nav-links {
            display: flex;
            justify-content: space-between;
            margin-top: 26px;
            gap: 10px;
            font-size: 0.85rem;
        }
        .nav-links a {
            color: var(--muted);
            text-decoration: none;
        }
        .nav-links a:hover {
            color: var(--primary);
        }
        @media (max-width: 480px) {
            body { padding: 14px 10px; }
            .auth-card { padding: 32px 20px 24px; border-radius: 20px; }
            .brand-header { margin-bottom: 24px; }
            .brand-header h2 { font-size: 1.2rem; }
            .nav-links { flex-direction: column; align-items: center; gap: 12px; }
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand-header">
        <i class="fa-solid fa-id-card"></i>
        <h2>Staff Portal Login</h2>
        <p>Track your submitted ICT support requests</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Staff ID / Number</label>
            <input type="text" name="staff_no" required placeholder="Enter your staff number (e.g., ECG/...)">
        </div>
        <!-- Note: Removed redundant Name field from login as it wasn't used in validation in original code -->
        <button type="submit" class="btn-submit">View My Requests</button>
    </form>

    <div class="nav-links">
        <a href="index.php"><i class="fa-solid fa-plus"></i> Submit New Request</a>
        <a href="admin_login.php"><i class="fa-solid fa-lock"></i> ICT Admin Portal</a>
    </div>
</div>

</body>
</html>