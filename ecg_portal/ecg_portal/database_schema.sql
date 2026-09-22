-- ECG Ashanti West ICT Portal
-- Canonical schema for a fresh installation.
-- This script does not insert administrator credentials.
--
-- Existing installations should be backed up before applying structural
-- changes. Uploaded files are stored in the application uploads/ directory;
-- only their relative path is stored in requests.attachment_path.
--
-- This schema reflects the runtime table structure used across the application,
-- including the admin login and management workflow, complaint submission,
-- customer-facing request tracking, and audit logging.

CREATE DATABASE IF NOT EXISTS ecg_ashanti_ict_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ecg_ashanti_ict_db;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Administrator',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name_of_staff VARCHAR(255) NOT NULL,
    staff_no VARCHAR(50) NOT NULL,
    department VARCHAR(255) NOT NULL,
    mobile_contact VARCHAR(50) NOT NULL,
    request_type VARCHAR(255) NOT NULL,
    further_details TEXT NOT NULL,
    job_title_rank VARCHAR(100) NULL,
    requester_signature VARCHAR(100) NULL,
    date_submitted DATE NOT NULL,
    receiving_ict_officer VARCHAR(255) NULL,
    date_received DATE NULL,
    admin_updated_at DATETIME NULL,
    details_of_assessment TEXT NULL,
    status_of_complaint VARCHAR(255) NOT NULL DEFAULT 'Resolution in progress',
    status_comments TEXT NULL,
    supervisor_name VARCHAR(255) NULL,
    supervisor_signature VARCHAR(255) NULL,
    supervisor_date DATE NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attachment_path VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_requests_staff_no (staff_no),
    KEY idx_requests_department (department),
    KEY idx_requests_status (status_of_complaint),
    KEY idx_requests_date_submitted (date_submitted),
    KEY idx_requests_is_read (is_read),
    KEY idx_requests_admin_updated_at (admin_updated_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action TEXT NULL,
    admin_user VARCHAR(100) NULL,
    action_performed TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_admin_user (admin_user)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
