<?php
session_start();

// Database connection settings
$host = "localhost";
$db = "ecg_ashanti_ict_db"; // Standardized DB name from your other files
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Ensure audit_logs table exists
$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($conn->real_escape_string($_POST['username']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if user exists using MySQLi
        $check_stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_stmt = $conn->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
            $insert_stmt->bind_param("ss", $username, $hashed_password);
            
            if ($insert_stmt->execute()) {
                // AUDIT LOG: Log the registration event
                $audit_sql = "INSERT INTO audit_logs (action) VALUES ('New administrator registered: $username')";
                $conn->query($audit_sql);

                $success = "Admin account created successfully! <a href='login.php'>Login here</a>";
            } else {
                $error = "Error creating account: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECG Ashanti West - Admin Registration</title>
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
            padding: 20px 0;
        }
        .auth-card {
            width: 100%;
            max-width: 450px;
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
        .success-msg {
            background: #dcfce7;
            color: #166534;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 15px;
            border: 1px solid #86efac;
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
        <h1>Admin Registration</h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-msg"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <form action="admin_register.php" method="POST">
        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" required placeholder="Choose a username">
        </div>
        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" required placeholder="Create password">
        </div>
        <div class="form-group">
            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required placeholder="Confirm password">
        </div>
        <button type="submit" class="auth-btn"><i class="fa-solid fa-user-plus"></i> Register Admin</button>
    </form>

    <div class="auth-footer">
        Already have an admin account? <a href="login.php">Login here</a><br>
        <a href="index.php" style="display:inline-block; margin-top:10px;"><i class="fa-solid fa-arrow-left"></i> Back to Request Form</a>
    </div>
</div>

</body>
</html>