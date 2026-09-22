<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['client_staff_no'])) {
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
$staff_no_sql = $conn->real_escape_string($staff_no);
$latest_update_result = $conn->query("SELECT id, admin_updated_at, date_received, status_of_complaint, status_comments, details_of_assessment, receiving_ict_officer FROM requests WHERE staff_no = '$staff_no_sql' AND admin_updated_at IS NOT NULL ORDER BY admin_updated_at DESC, id DESC LIMIT 1");
$latest_update_row = $latest_update_result ? $latest_update_result->fetch_assoc() : null;
$latest_update_id = $latest_update_row ? (int)$latest_update_row['id'] : 0;
$latest_update_key = $latest_update_row ? sha1(
    $latest_update_row['id'] . '|' .
    $latest_update_row['admin_updated_at'] . '|' .
    $latest_update_row['date_received'] . '|' .
    $latest_update_row['status_of_complaint'] . '|' .
    $latest_update_row['status_comments'] . '|' .
    $latest_update_row['details_of_assessment'] . '|' .
    $latest_update_row['receiving_ict_officer']
) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests Dashboard - ECG Ashanti West</title>
    <link rel="icon" type="image/jpeg" href="ecg_logo.jpg">
    <link rel="apple-touch-icon" href="ecg_logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            --card: rgba(255, 255, 255, .92);
            --primary: #1e3a8a;
            --primary-dark: #172554;
            --primary-light: #3b82f6;
            --accent: #f59e0b;
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(148, 163, 184, .35);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at top right, rgba(147, 197, 253, .35) 0, transparent 32%), var(--bg);
            color: var(--text);
            margin: 0;
            padding: 28px 16px 50px;
        }
        .container {
            max-width: 920px;
            margin: 0 auto;
        }
        .header-card {
            background: linear-gradient(120deg, #0f172a 0%, #1e3a8a 58%, #2563eb 100%);
            color: white;
            padding: 26px 30px;
            border-radius: 20px;
            border: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 16px 30px rgba(30, 58, 138, .18);
        }
        .header-card h2 { margin: 0; font-size: 1.25rem; }
        .header-card p { margin: 5px 0 0 0; color: #bfdbfe; font-size: 0.88rem; }
        .btn-action {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.28);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action:hover { background: rgba(255,255,255,0.24); }
        .dashboard-tools { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .sound-btn { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; cursor: pointer; }
        .sound-btn.enabled { background: #eff6ff; color: #1d4ed8; border-color: #93c5fd; }
        .update-alert { display: none; margin-bottom: 22px; padding: 14px 18px; border-radius: 12px; background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; font-weight: 700; }
        .request-item {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 8px 18px rgba(30, 58, 138, .06);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .request-item:hover { transform: translateY(-2px); box-shadow: 0 14px 25px rgba(30, 58, 138, .1); }
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
        .status-resolved { background: #dcfce7; color: #166534; }
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
            border-left: 4px solid var(--primary-light);
            font-size: 0.88rem;
        }
        .feedback-box strong { color: var(--primary); }
        .empty-state { text-align: center; background: var(--card); border: 1px dashed #93c5fd; border-radius: 16px; padding: 45px 20px; color: var(--muted); }
        @media (max-width: 600px) {
            body { padding: 16px 10px 35px; }
            .header-card { padding: 22px 18px; align-items: flex-start; flex-direction: column; gap: 18px; }
            .dashboard-tools { width: 100%; }
            .btn-action { flex: 1; justify-content: center; }
            .req-header { align-items: flex-start; flex-direction: column; gap: 10px; }
            .request-item { padding: 18px; }
        }
        @media (max-width: 420px) {
            .header-card { padding: 20px 16px; }
            .header-card h2 { font-size: 1.08rem; }
            .dashboard-tools { flex-direction: column; }
            .btn-action { width: 100%; }
            .request-item { padding: 15px; }
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
        <div class="dashboard-tools">
            <button type="button" id="enableUserSound" class="btn-action sound-btn"><i class="fa-solid fa-volume-high"></i> Enable Sound</button>
            <a href="index.php" class="btn-action"><i class="fa-solid fa-plus"></i> New Request</a>
            <a href="logout.php?scope=user" class="btn-action" style="color: #ef4444;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <div id="updateAlert" class="update-alert"><i class="fa-solid fa-bell"></i> Your request history has a new administrative update.</div>
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
                        <?php
                            $attachment_path = ltrim((string)$row['attachment_path'], '/');
                            $attachment_url = strpos($attachment_path, 'uploads/') === 0 ? $attachment_path : 'uploads/' . $attachment_path;
                            $attachment_name = basename($attachment_path);
                        ?>
                        <a href="<?php echo htmlspecialchars($attachment_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn-action" style="padding: 6px 12px;">
                            <i class="fa-solid fa-paperclip"></i> View Attachment
                        </a>
                        <span style="color: var(--muted); margin-left: 8px;"><?php echo htmlspecialchars($attachment_name); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state"><i class="fa-regular fa-folder-open fa-2x"></i><p>You have not submitted any ICT support requests yet.</p></div>
    <?php endif; ?>
</div>

<script>
    let lastUpdateId = <?php echo $latest_update_id; ?>;
    let lastUpdateKey = <?php echo json_encode($latest_update_key); ?>;
    let userAudioContext = null;

    async function unlockUserSound() {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) {
            return false;
        }
        if (!userAudioContext) {
            userAudioContext = new AudioContext();
        }
        if (userAudioContext.state === 'suspended') {
            await userAudioContext.resume();
        }
        return userAudioContext.state === 'running';
    }

    async function playUserNotification() {
        if (!await unlockUserSound()) {
            return;
        }
        const start = userAudioContext.currentTime;
        [660, 990].forEach(function (frequency, index) {
            const oscillator = userAudioContext.createOscillator();
            const gain = userAudioContext.createGain();
            const toneStart = start + index * 0.16;
            oscillator.frequency.value = frequency;
            oscillator.type = 'sine';
            gain.gain.setValueAtTime(0.0001, toneStart);
            gain.gain.exponentialRampToValueAtTime(0.22, toneStart + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, toneStart + 0.38);
            oscillator.connect(gain);
            gain.connect(userAudioContext.destination);
            oscillator.start(toneStart);
            oscillator.stop(toneStart + 0.4);
        });
    }

    const soundButton = document.getElementById('enableUserSound');
    soundButton.addEventListener('click', function () {
        playUserNotification();
        soundButton.classList.add('enabled');
        soundButton.innerHTML = '<i class="fa-solid fa-volume-high"></i> Sound Enabled';
    });

    async function checkForUpdates() {
        try {
            const response = await fetch('fetch_user_notifications.php?check=' + Date.now(), { cache: 'no-store' });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            if (data.latest_update_id > lastUpdateId || (data.latest_update_key && data.latest_update_key !== lastUpdateKey)) {
                lastUpdateId = data.latest_update_id;
                lastUpdateKey = data.latest_update_key;
                document.getElementById('updateAlert').style.display = 'block';
                await playUserNotification();
                setTimeout(function () { window.location.reload(); }, 1800);
            }
        } catch (error) {
            // A temporary polling failure should not interrupt dashboard use.
        }
    }

    document.addEventListener('click', function () {
        unlockUserSound();
    }, { once: true });
    checkForUpdates();
    setInterval(checkForUpdates, 5000);
</script>

</body>
</html>