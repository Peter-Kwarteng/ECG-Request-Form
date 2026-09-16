<?php
$host = 'localhost';$db   = 'ecg_ashanti_ict_db';
$user = 'root';$pass = '';

$conn = new mysqli($host,$user, $pass,$db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {     // 1. Capture user form fields safely$name_of_staff       = $conn->real_escape_string($_POST['name_of_staff'] ?? '');
    $staff_no            =$conn->real_escape_string($_POST['staff_no'] ?? '');$job_title_rank      = $conn->real_escape_string($_POST['job_title_rank'] ?? '');
    $department          =$conn->real_escape_string($_POST['department'] ?? '');$mobile_contact      = $conn->real_escape_string($_POST['mobile_contact'] ?? '');
    $request_type        =$conn->real_escape_string($_POST['request_type'] ?? '');$further_details     = $conn->real_escape_string($_POST['further_details'] ?? '');
    $requester_signature =$conn->real_escape_string($_POST['requester_signature'] ?? '');$date_submitted      = $conn->real_escape_string($_POST['date_submitted'] ?? date('Y-m-d'));

    // 2. Handle File Attachment Upload
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {$upload_dir = 'uploads/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        // Generate a safe, unique filename (timestamp + random string + original extension)
        $new_filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
        $target_file = $upload_dir .$new_filename;

        // Validate file type (Optional but recommended)
        $allowed_types = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
        if (in_array(strtolower($file_extension),$allowed_types)) {
            // Validate file size (Max 5MB)
            if ($_FILES['attachment']['size'] <= 5242880) {
                if (move_uploaded_file($_FILES['attachment']['tmp_name'],$target_file)) {
                    $attachment_path =$target_file; // Store path in database
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

   // if ($conn