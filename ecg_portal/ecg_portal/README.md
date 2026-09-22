# ECG Ashanti West ICT Request Portal

The ECG Ashanti West ICT Request Portal is a PHP/MySQL web application for submitting, tracking, assigning, and updating internal ICT support requests.

The application provides:

- A public request form for staff.
- File attachments for request evidence or supporting documents.
- Staff login and request-history dashboard.
- Administrator login and request-management dashboard.
- User account and role management.
- Administrative status updates and supervisor sign-off.
- Audit logging.
- Request and audit-report exports.
- Browser notifications for new requests and administrative updates.
- Complaint and outage alert logic for administrators and customers.
- Separate admin and staff session handling so one user logout does not terminate the other session.
- Dashboard metrics and charts.

## Technology Stack

- PHP 8.x
- MySQL/MariaDB
- Apache through XAMPP
- HTML5, CSS3, and JavaScript
- MySQLi and PDO database access
- Chart.js for administrator dashboard visualizations
- Font Awesome for interface icons

## Project Structure

| File or folder | Purpose |
|---|---|
| `index.php` | Public ICT request form and client portal landing page |
| `submit_request.php` | Processes request submissions and file uploads |
| `user_login.php` | Staff login using staff number |
| `user_dashboard.php` | Staff request history and administrative update view |
| `fetch_user_notifications.php` | Staff-scoped polling endpoint for admin updates |
| `admin_login.php` / `login.php` | Administrator login pages |
| `admin_register.php` | Administrator account registration |
| `admin_dashboard.php` | Administrator welcome dashboard, summary cards, and charts |
| `admin.php` | Live request management, user management, and audit trail |
| `fetch_notifications.php` | Polling endpoint for new administrator requests |
| `export_requests.php` | Request and audit report export page |
| `export_request.php` | Legacy CSV export endpoint |
| `logout.php` | Logs out the current user scope (admin or staff) without destroying the other session |
| `database_schema.sql` | Canonical schema for a fresh database |
| `ecg_logo.jpg` | ECG logo used in page branding and favicons |
| `uploads/` | Uploaded request attachments |

## Requirements

- Windows with XAMPP, or an equivalent Apache/PHP/MySQL environment.
- Apache enabled.
- MySQL enabled.
- PHP 8.0 or newer recommended.
- PHP extensions:
  - `mysqli`
  - `PDO`
  - `pdo_mysql`
  - `fileinfo`
  - `mbstring`

## Local Setup with XAMPP

1. Copy the project directory into:

   ```text
   C:\xampp\htdocs\ecg_portal
   ```

2. Start **Apache** and **MySQL** in the XAMPP Control Panel.

3. Create the database using the canonical schema:

   ```text
   http://localhost/phpmyadmin/
   ```

   Open the **Import** tab and import:

   ```text
   C:\xampp\htdocs\ecg_portal\database_schema.sql
   ```

   Alternatively, use the MySQL command line:

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -uroot < C:\xampp\htdocs\ecg_portal\database_schema.sql
   ```

4. Confirm the database settings used by the PHP files:

   ```text
   Host: localhost
   Database: ecg_ashanti_ict_db
   User: root
   Password: empty by default in XAMPP
   ```

5. Ensure the attachment directory exists and is writable by Apache:

   ```text
   C:\xampp\htdocs\ecg_portal\uploads
   ```

6. Open the client portal:

   ```text
   http://localhost/ecg_portal/
   ```

7. Create an administrator account at:

   ```text
   http://localhost/ecg_portal/admin_register.php
   ```

8. Sign in to the administrator area:

   ```text
   http://localhost/ecg_portal/admin_login.php
   ```

## Main User Flows

### Staff request flow

1. Staff opens `index.php`.
2. Staff completes the request form.
3. Optional supporting files are uploaded.
4. `submit_request.php` stores the request in `requests`.
5. New requests are marked with `is_read = 0`.
6. Administrators see new requests first in `admin.php`.
7. Staff can use their staff number to log in to `user_dashboard.php`.

### Administrator workflow

1. Administrator signs in.
2. `admin_dashboard.php` displays live request statistics and charts.
3. `admin.php` provides:
   - Search and filtering.
   - Request assignment.
   - Assessment notes.
   - Status updates.
   - Supervisor sign-off.
   - User management.
   - Audit Trail.
   - Export reports in common readable formats.
4. Saving an administrative update sets:

   ```text
   date_received = current date
   admin_updated_at = current date and time
   is_read = 1
   ```

5. When a complaint is submitted, the system can queue an alert to the configured admin email/SMS channel.
6. When an issue is marked as "Resolved and closed", the system can trigger a customer outage-resolution notification if contact details are available.
7. The staff dashboard polls for `admin_updated_at` changes and refreshes the request history.

## Basic API and Backend Endpoint Documentation

These endpoints use normal browser requests rather than a versioned REST API. JSON endpoints return JSON and use HTTP status codes for authentication or server failures.

### Submit a request

```text
POST /ecg_portal/submit_request.php
Content-Type: multipart/form-data
```

Important fields:

| Field | Required | Description |
|---|---:|---|
| `name_of_staff` | Yes | Requesting staff member |
| `staff_no` | Yes | Staff number |
| `department` | Yes | Department or section |
| `mobile_contact` | Yes | Contact phone number |
| `request_type` | Yes | Requested ICT service or item |
| `further_details` | Yes | Request description |
| `job_title_rank` | No | Staff rank or job title |
| `requester_signature` | No | Requester signature value |
| `date_submitted` | Yes | Submission date |
| `attachment` | No | PDF, DOC, DOCX, PNG, JPG, or JPEG up to 5 MB |

Successful submissions redirect to:

```text
/ecg_portal/index.php?submitted=1
```

### Administrator new-request polling

```text
GET /ecg_portal/fetch_notifications.php
```

Example response:

```json
{
  "unread": 2,
  "latest_unread_id": 15
}
```

This endpoint is used by the authenticated administrator page to detect new untouched requests.

### Staff update polling

```text
GET /ecg_portal/fetch_user_notifications.php
```

Authentication requirement:

```text
$_SESSION['client_staff_no']
```

Unauthenticated response:

```http
401 Unauthorized
```

Example authenticated response:

```json
{
  "latest_update_id": 15,
  "latest_update_date": "2026-09-16 11:20:00",
  "latest_update_key": "generated-update-fingerprint"
}
```

The response is scoped to the logged-in staff number.

### Export reports

```text
GET /ecg_portal/export_requests.php?dataset=requests&format=excel
GET /ecg_portal/export_requests.php?dataset=audit&format=excel
GET /ecg_portal/export_requests.php?dataset=requests&format=csv
GET /ecg_portal/export_requests.php?dataset=requests&format=pdf
GET /ecg_portal/export_requests.php?dataset=audit&format=pdf
```

Supported export formats:

- `excel` for Excel-compatible spreadsheet output
- `csv` for CSV reports in a readable tabular format
- `pdf` for printable PDF output
- `doc` / `txt` when used in legacy compatibility workflows

Authentication requirement:

```text
$_SESSION['admin_logged_in'] === true
```

Unauthenticated requests receive HTTP `403`.

## Database Design

### `admin_users`

Stores administrator login accounts. Passwords are stored as PHP password hashes, never as plain text.

Important fields:

- `username`
- `password_hash`
- `role`
- `created_at`

### `requests`

Stores the complete support request lifecycle.

Important workflow fields:

- `is_read`: `0` for untouched/new requests and `1` after administrative handling.
- `date_received`: date an ICT officer receives the request.
- `admin_updated_at`: timestamp of the latest administrative save.
- `status_of_complaint`: current request status.
- `details_of_assessment`: technical assessment.
- `status_comments`: administrative remarks.
- `attachment_path`: relative path to the uploaded file.

### `audit_logs`

Stores administrative activity such as user management, request updates, exports, and logout activity.

The table includes both the current `action` field and compatibility fields used by older installations:

- `action`
- `admin_user`
- `action_performed`
- `ip_address`
- `created_at`

## Security and Privacy

The current application applies the following protections:

- Session checks protect administrator and staff-only pages.
- Passwords use `password_hash()` and `password_verify()`.
- Output displayed in HTML is escaped with `htmlspecialchars()`.
- User-management queries use prepared MySQLi statements.
- Login queries use PDO prepared statements.
- Export endpoints reject unauthenticated users.
- JSON notification endpoints disable caching.
- Uploaded files are restricted by extension and limited to 5 MB.
- Administrator dashboard security headers include:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `Referrer-Policy`
- HTTPS is enforced for non-localhost deployments in the administrator dashboard.

For production, also configure:

- HTTPS certificates for Apache.
- A non-root MySQL account with a strong password.
- PHP `display_errors = Off`.
- Server-side MIME/content validation for uploads.
- Web-server protection for the `uploads/` directory.
- CSRF tokens on all state-changing forms.
- Centralized environment configuration instead of hard-coded credentials.

## Architecture Decisions

### Server-rendered PHP

The application uses server-rendered PHP because the portal is form-driven and runs naturally in a traditional XAMPP environment. This keeps deployment simple for an internal organizational system.

### MySQL as the source of truth

Requests, users, workflow status, timestamps, attachments, and audit events are stored in MySQL. This provides durable records and supports reporting.

### Session-based authentication

Sessions were selected for the staff and administrator portals because users interact through browser pages rather than third-party API clients. Separate session keys distinguish staff and administrator access. The logout flow is scoped so that a staff user logging out clears only the staff session, while an admin logout clears only the administrator session.

### Explicit request lifecycle fields

The request table uses separate fields for submission, assignment, assessment, status, supervisor sign-off, and administrative-update timestamps. This makes the workflow understandable and allows the dashboards to identify untouched and recently updated requests.

### Polling for notifications

The current notification system uses lightweight five-second polling instead of WebSockets. This is easier to deploy on XAMPP and is sufficient for an internal request portal with moderate traffic.

### Progressive enhancement for sound

Notification tones use the browser Web Audio API and require a user interaction to satisfy autoplay policies. The interface provides an explicit **Enable Sound** button so users understand how to activate notifications.

### Auditability

Administrative actions are written to `audit_logs`. This supports operational accountability and provides an activity history for supervisors or future reporting.

### Responsive presentation

CSS media queries and flexible grids are used rather than separate mobile pages. This keeps the same workflow available on desktops, tablets, and phones.

## Testing and Maintenance

Run PHP syntax checks after changes:

```powershell
Get-ChildItem C:\xampp\htdocs\ecg_portal -Filter *.php | ForEach-Object {
    C:\xampp\php\php.exe -l $_.FullName
}
```

Useful smoke-test URLs:

```text
http://localhost/ecg_portal/
http://localhost/ecg_portal/admin_login.php
http://localhost/ecg_portal/user_login.php
http://localhost/ecg_portal/fetch_notifications.php
```

Before production deployment:

1. Back up the database.
2. Back up the `uploads/` directory.
3. Import or migrate the schema carefully.
4. Create a least-privileged database user.
5. Enable HTTPS.
6. Test administrator login and logout.
7. Submit a request with an attachment.
8. Save an administrative update.
9. Verify the staff dashboard history and notification.
10. Verify the Audit Trail and exports.

## Internship Report Summary

The project demonstrates a complete internal service-request workflow. It combines a public data-capture form, a staff self-service history page, and an administrator control panel for operational processing. The design separates user-facing and administrator-facing workflows while using a shared relational data model.

The main engineering considerations were data consistency, traceability, responsive usability, authentication, file handling, and incremental enhancement. The system began with server-rendered forms and was extended with asynchronous notification polling, dashboard visualizations, audit logs, responsive cards, and browser-based notification sounds without requiring a large frontend framework or complex deployment infrastructure.
