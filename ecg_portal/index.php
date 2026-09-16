<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECG Ashanti West - ICT Item Request Portal</title>
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
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            margin: 0;
            padding: 30px 20px;
            min-height: 100vh;
        }

        .form-container {
            max-width: 850px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border-top: 6px solid var(--primary);
        }

        .form-header {
            background: linear-gradient(to right, #0f172a, #1e3a8a);
            color: white;
            padding: 25px 30px;
            text-align: center;
            border-bottom: 4px solid var(--accent);
        }

        .form-header h2 {
            margin: 0;
            font-size: 1.2rem;
            letter-spacing: 1px;
            color: #93c5fd;
            text-transform: uppercase;
        }

        .form-header h1 {
            margin: 8px 0 4px 0;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .form-header p {
            margin: 0;
            font-size: 0.9rem;
            color: #cbd5e1;
            letter-spacing: 0.5px;
        }

        .form-body {
            padding: 30px;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 6px;
            margin-top: 25px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }

        .section-title-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .staff-login-inline-btn {
            background: var(--primary-light);
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s;
        }

        .staff-login-inline-btn:hover {
            opacity: 0.9;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
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

        input[type="text"],
        input[type="date"],
        input[type="tel"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            background: #f8fafc;
            color: var(--text-main);
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-light);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .submit-btn {
            background: linear-gradient(to right, var(--primary), var(--primary-light));
            color: white;
            border: none;
            width: 100%;
            padding: 14px;
            font-size: 1rem;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: opacity 0.2s ease, transform 0.1s ease;
            box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.3);
            margin-top: 10px;
        }

        .submit-btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .section-title-flex {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="form-header">
        <h2>Electricity Company of Ghana Limited</h2>
        <h1>Ashanti West Region</h1>
        <p><i class="fa-solid fa-file-lines"></i> Internal Memorandum — ICT Item(s) Request Form</p>
    </div>

    <div class="form-body">
        <!-- UPDATED: Added enctype="multipart/form-data" to allow file uploads -->
        <form action="submit_request.php" method="POST" enctype="multipart/form-data">
            
            <div class="section-title section-title-flex">
                <div>
                    <i class="fa-solid fa-user"></i> Requesting Officer Details
                </div>
                <a href="user_login.php" class="staff-login-inline-btn"><i class="fa-solid fa-user-check"></i> Staff Login</a>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label>Requesting Officer's Name:</label>
                    <input type="text" name="name_of_staff" required placeholder="Enter full name">
                </div>
                <div class="form-group">
                    <label>Staff No:</label>
                    <input type="text" name="staff_no" required placeholder="e.g. ECG/AST/12345">
                </div>
            </div>

            <div class="grid-2">
                <div class="grid-2">
                <div class="form-group">
                    <label>Section / Department:</label>
                    <!-- Updated: Changed from input type="text" to input with list="dept_options" -->
                    <input type="text" name="department" required placeholder="Select or type department..." list="dept_options">
                    
                    <!-- Defined the list of options -->
                    <datalist id="dept_options">
                        <option value="District Engineer 27">
                        <option value="Field Investigators 26">
                        <option value="DCO/DMO 25">
                        <option value="District Technical Officer 24">
                        <option value="District Manager's Secretary 23.">
                        <option value="Billing & Revenue 22">
                        <option value="Public Relations 21">
                        <option value="Conference Room 19">
                        <option value="Marketing & MIS 18">
                        <option value="MTS 17">
                        <option value="Expenditure 16">
                        <option value="District Account Officer 15">
                        <option value="Account Examination Unit 14">
                        <option value="Room 13">
                        <option value="HR Manager 12">
                        <option value="Registry 11">
                        <option value="HR Officer 10">
                        <option value="Accounts Office 09">
                        <option value="Accounts Office 08">
                        <option value="Supervisors Office">
                        <option value="Room 07">
                        <option value="Commercial Manager 06">
                        <option value="Accounts Manager 05">
                        <option value="Regional Engineer 04">
                        <option value="Materials & Transport Manager 03">
                        <option value="RP Manager 02">
                        <option value="General Manager 01">
                        <option value="Customers Office">
                        <option value="Customers Service">
                        <option value="Supervisor's Office">
                        <option value="District HR Officer">
                        <option value="Ghana Water">
                        <option value="ICT Department 28">
                        <option value="CREDIT UNION">
                        <option value="MAPPERS/ESTIMATOR 29">
                        <option value="Baby Bay Office">
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Phone No:</label>
                    <input type="tel" name="mobile_contact" required placeholder="024XXXXXXX">
                </div>
            </div>
                <div class="form-group">
                    <label>Phone No:</label>
                    <input type="tel" name="mobile_contact" required placeholder="024XXXXXXX">
                </div>
            </div>

            <div class="section-title">
                <i class="fa-solid fa-laptop"></i> Request Specifics
            </div>

            <div class="form-group">
                <label>Requested Item(s):</label>
                <input type="text" name="request_type" list="request_options" required placeholder="Select from list or type your own preference...">
                <datalist id="request_options">
                    <option value="Password Reset (Mail/Domain)">
                    <option value="LAN Network/Internet/Wi-Fi">
                    <option value="ISP/Examination/Result">
                    <option value="Intercom/Landline/VOIP">
                    <option value="Hardware/Software Installation Support">
                    <option value="Components Replacement/Acquisition">
                    <option value="Printer/Copier/Scanner Issues">
                    <option value="Biometrics/Clock in">
                </datalist>
            </div>

            <div class="form-group">
                <label>Purpose / Justification:</label>
                <textarea name="further_details" required placeholder="State clearly why the item(s) are required..."></textarea>
            </div>

            <!-- NEW: File Attachment Input -->
            <div class="form-group">
                <label>Attach Supporting Document (Invoice, Memo, etc. - Optional):</label>
                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg">
                <small style="color: var(--text-muted);">Allowed: PDF, Word, Images (Max 5MB)</small>
            </div>

            <div class="grid-3">
              <div class="form-group">
                    <label for="job_title_rank">Job Title / Rank</label>
                    <input type="text" id="job_title_rank" name="job_title_rank" class="form-control" required placeholder="Enter your job title or rank">
                </div>
                <div class="form-group">
                    <label>Signature (Initials):</label>
                    <input type="text" name="requester_signature" placeholder="Sign name" required>
                </div>
                <div class="form-group">
                    <label>Date:</label>
                    <input type="date" name="date_submitted" required>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fa-solid fa-paper-plane"></i> Submit ICT Request
            </button>

        </form>
    </div>
</div>

<script>
    // Automatically set today's date on load
    document.querySelector('input[name="date_submitted"]').valueAsDate = new Date();
</script>

</body>
</html>