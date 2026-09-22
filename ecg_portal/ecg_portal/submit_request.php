<?php
function send_admin_new_complaint_alert(string $customer_name, string $request_type, string $department = '', string $phone = ''): array
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
    $message .= "Please review the issue in the admin dashboard immediately.";

    $admin_email = getenv('ECG_ADMIN_ALERT_EMAIL') ?: 'admin@ecg.local';
    $admin_sms = getenv('ECG_ADMIN_ALERT_SMS') ?: '';

    $deliveries = [];
    $email_gateway = getenv('ECG_ALERT_EMAIL') ?: '';
    if ($email_gateway !== '') {
        $headers = "From: " . $email_gateway . "\r\n" . "Reply-To: " . $email_gateway . "\r\n" . "MIME-Version: 1.0\r\n" . "Content-Type: text/html; charset=UTF-8\r\n";
        @mail($admin_email, $subject, nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')), $headers);
        $deliveries[] = ['channel' => 'email', 'status' => 'queued'];
    }
    if ($admin_sms !== '') {
        error_log('SMS complaint alert queued for ' . $admin_sms . ': ' . $message);
        $deliveries[] = ['channel' => 'sms', 'status' => 'queued'];
    }

    if (empty($deliveries)) {
        $deliveries[] = ['channel' => 'none', 'status' => 'skipped', 'detail' => 'No alert gateway configured.'];
    }

    return $deliveries;
}

$host = 'localhost';$db   = 'ecg_ashanti_ict_db';
$user = 'root';$pass = '';

$conn = new mysqli($host,$user, $pass,$db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture user form fields safely.
    $name_of_staff       = $conn->real_escape_string($_POST['name_of_staff'] ?? '');
    $staff_no            = $conn->real_escape_string($_POST['staff_no'] ?? '');
    $job_title_rank      = $conn->real_escape_string($_POST['job_title_rank'] ?? '');
    $department          = $conn->real_escape_string($_POST['department'] ?? '');
    $mobile_contact      = $conn->real_escape_string($_POST['mobile_contact'] ?? '');
    $request_type        = $conn->real_escape_string($_POST['request_type'] ?? '');
    $further_details     = $conn->real_escape_string($_POST['further_details'] ?? '');
    $requester_signature = $conn->real_escape_string($_POST['requester_signature'] ?? '');
    $date_submitted      = $conn->real_escape_string($_POST['date_submitted'] ?? date('Y-m-d'));

    // 2. Handle File Attachment Upload
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        // Generate a safe, unique filename (timestamp + random string + original extension)
        $new_filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;

        // Validate file type (Optional but recommended)
        $allowed_types = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
        if (in_array(strtolower($file_extension),$allowed_types)) {
            // Validate file size (Max 5MB)
            if ($_FILES['attachment']['size'] <= 5242880) {
                if (move_uploaded_file($_FILES['attachment']['tmp_name'],$target_file)) {
                    $attachment_path = $target_file; // Store path in database
                }
            } else {
                echo "<script>alert('Error: File size exceeds 5MB limit.'); window.location.href='index.php';</script>";
                exit;
            }
        } else {
            echo "<script>alert('Error: Invalid file type. Only PDF, Word, and Images allowed.'); window.location.href='index.php';</script>";
            exit;
        }
    }

    // 3. Insert into database with attachment_path
    $sql = "INSERT INTO requests (
                name_of_staff, 
                staff_no, 
                job_title_rank, 
                department, 
                mobile_contact, 
                request_type, 
                further_details, 
                requester_signature, 
                date_submitted, 
                attachment_path,
                is_read
            ) VALUES (
                '$name_of_staff', 
                '$staff_no', 
                '$job_title_rank', 
                '$department', 
                '$mobile_contact', 
                '$request_type', 
                '$further_details', 
                '$requester_signature', 
                '$date_submitted', 
                " . ($attachment_path ? "'$attachment_path'" : "NULL") . ",
                0
            )";

    if ($conn->query($sql)) {
        $complaint_id = $conn->insert_id;
        send_admin_new_complaint_alert($name_of_staff, $request_type, $department, $mobile_contact);
        header('Location: index.php?submitted=1&complaint_id=' . urlencode((string)$complaint_id));
        exit;
    }

    http_response_code(500);
    echo 'Unable to submit the request: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
    exit;
}

http_response_code(405);
echo 'Invalid request method.';