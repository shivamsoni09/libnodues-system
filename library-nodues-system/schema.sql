-- =====================================================================
-- Library No Dues Management System — Database Schema
-- Engine: MySQL / MariaDB
-- =====================================================================

CREATE DATABASE IF NOT EXISTS nodues_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nodues_system;

-- ---------------------------------------------------------------------
-- Roles
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(30) NOT NULL UNIQUE,   -- user, frontdesk, eresources, librarian, admin
    role_name VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

INSERT INTO roles (role_key, role_name) VALUES
('user', 'Applicant / Patron'),
('frontdesk', 'Front Desk / Circulation'),
('eresources', 'E-Resources'),
('librarian', 'Librarian'),
('admin', 'Administrator');

-- ---------------------------------------------------------------------
-- Departments & Designations (used on the No Dues form)
-- ---------------------------------------------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE designations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,   -- e.g. Student, Research Scholar, Faculty, Staff
    active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO designations (name) VALUES ('Student'), ('Research Scholar'), ('Faculty'), ('Staff');

-- ---------------------------------------------------------------------
-- Users (staff + applicants who log in to this system)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(60) DEFAULT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    library_card_no VARCHAR(50) DEFAULT NULL,   -- links to Koha borrower cardnumber
    koha_borrower_id INT DEFAULT NULL,           -- cached Koha borrowernumber
    department_id INT DEFAULT NULL,
    designation_id INT DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (designation_id) REFERENCES designations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Applications (the "No Dues" requests)
-- ---------------------------------------------------------------------
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(30) NOT NULL UNIQUE,       -- e.g. ND-2026-000001
    applicant_id INT DEFAULT NULL,               -- NULL for public walk-in submissions
    applicant_name VARCHAR(150) DEFAULT NULL,    -- from public form
    applicant_email VARCHAR(150) DEFAULT NULL,
    applicant_phone VARCHAR(20) DEFAULT NULL,
    applicant_library_card VARCHAR(50) DEFAULT NULL,  -- optional, from public apply form
    joining_date DATE DEFAULT NULL,
    relieving_date DATE DEFAULT NULL,
    department_id INT DEFAULT NULL,
    designation_id INT DEFAULT NULL,
    reason VARCHAR(255) DEFAULT NULL,            -- e.g. "Course completion", "Transfer"
    -- Koha circulation snapshot, captured at "Check Koha" time
    koha_checked TINYINT(1) DEFAULT 0,
    koha_books_issued INT DEFAULT NULL,
    koha_fine_amount DECIMAL(10,2) DEFAULT NULL,
    koha_lost_items INT DEFAULT NULL,
    koha_account_status VARCHAR(50) DEFAULT NULL,
    koha_clear TINYINT(1) DEFAULT NULL,          -- 1 = cleared, 0 = outstanding issue
    koha_checked_at TIMESTAMP NULL DEFAULT NULL,
    -- Current stage in workflow
    current_stage ENUM('submitted','frontdesk','eresources','librarian','completed','rejected')
        NOT NULL DEFAULT 'submitted',
    status ENUM('pending','in_progress','approved','rejected') NOT NULL DEFAULT 'pending',
    certificate_path VARCHAR(255) DEFAULT NULL,
    certificate_issued_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (designation_id) REFERENCES designations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Workflow log — every stage transition, who did it, and when
-- ---------------------------------------------------------------------
CREATE TABLE workflow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    from_stage VARCHAR(30) DEFAULT NULL,
    to_stage VARCHAR(30) NOT NULL,
    action VARCHAR(50) NOT NULL,        -- submitted, forwarded, approved, rejected, returned
    acted_by INT DEFAULT NULL,          -- users.id
    acted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (acted_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Remarks — free-text notes attached at any stage
-- ---------------------------------------------------------------------
CREATE TABLE remarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    stage VARCHAR(30) NOT NULL,
    remark TEXT NOT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Audit log — system-wide action trail (logins, edits, admin actions)
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Ticket number sequence helper (per-year counter)
-- ---------------------------------------------------------------------
CREATE TABLE ticket_sequence (
    year INT PRIMARY KEY,
    last_number INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Default admin account
-- Password: ChangeMe@123  (bcrypt hash below) — CHANGE THIS AFTER FIRST LOGIN
-- ---------------------------------------------------------------------
INSERT INTO users (full_name, username, email, password_hash, role_id, active)
VALUES (
    'System Administrator',
    'admin',                                                       -- placeholder, regenerated by install.sh
    'admin@library.local',                                         -- placeholder, regenerated by install.sh
    '$2y$10$w9c8N2Zt8u2q1yV1nQxU0.z6h1c2s8gk2mQ0y8w0jv3B4bYFq5V0e', -- placeholder, regenerated by install.sh
    (SELECT id FROM roles WHERE role_key = 'admin'),
    1
);
