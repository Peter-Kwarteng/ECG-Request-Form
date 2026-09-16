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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --primary: #059669; /* Emerald Green theme for client/staff */
            --primary-dark: #047857;
            --text: #1e293b;
            --muted: #64748b;
            --border: #cbd5e1;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .auth-card {
            background: var(--card);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            box-sizing: border-box;
        }
        .brand-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .brand-header i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        .brand-header h2 {
            margin: 0;
            font-size: 1.35rem;
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
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            border-color: var(--primary);
        }
        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: var(--primary-dark);
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
            background: var(--error-bg);
            color: var(--error-text);
        }
        .nav-links {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            font-size: 0.85rem;
        }
        .nav-links a {
            color: var(--muted);
            text-decoration: none;
        }
        .nav-links a:hover {
            color: var(--primary);
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