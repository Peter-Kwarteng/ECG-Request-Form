<?php
session_start();
// Database connection setup (Update credentials as needed for XAMPP)
$host = "localhost";
$db = "ecg_ashanti_ict_db";
$user = "root";
$pass = "";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'Administrator',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $role_column = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'role'");
        if (!$role_column->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'Administrator' AFTER password_hash");
        }

        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM admin_users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } catch (PDOException $e) {
        $error = "Unable to access the administrator database. Please check that MySQL is running.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECG Ashanti West - Admin Login</title>
    <link rel="icon" type="image/jpeg" href="ecg_logo.jpg">
    <link rel="apple-touch-icon" href="ecg_logo.jpg">
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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border-top: 6px solid var(--primary);
            padding: 30px;
            box-sizing: border-box;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .auth-header h2 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--primary-light);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .auth-header h1 {
            margin: 6px 0 0 0;
            font-size: 1.4rem;
            color: var(--primary);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            background: #f8fafc;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: var(--primary-light);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .auth-btn {
            background: linear-gradient(to right, var(--primary), var(--primary-light));
            color: white;
            border: none;
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.3);
        }
        .error-msg {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 15px;
            border: 1px solid #f87171;
        }
        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .auth-footer a {
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-header">
        <h2>ECG Ashanti West</h2>
        <h1>Admin Portal Login</h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <form action="admin_login.php" method="POST">
        <div class="form-group">
            <label>Username or Email:</label>
            <input type="text" name="username" required placeholder="Enter admin username">
        </div>
        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" required placeholder="Enter password">
        </div>
        <button type="submit" class="auth-btn"><i class="fa-solid fa-right-to-bracket"></i> Login as Admin</button>
    </form>

    <div class="auth-footer">
        Don't have an admin account? <a href="admin_register.php">Register here</a><br>
        <a href="index.php" style="display:inline-block; margin-top:10px;"><i class="fa-solid fa-arrow-left"></i> Back to Request Form</a>
    </div>
</div>

</body>
</html>