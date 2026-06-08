-- ============================================================
-- TDT Powersteel Corp. - Intern Management System
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS tdt_ims CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tdt_ims;

-- ------------------------------------------------------------
-- Users (Admin / HR Staff)
-- ------------------------------------------------------------
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','hr_staff') NOT NULL DEFAULT 'hr_staff',
    is_locked   TINYINT(1) NOT NULL DEFAULT 0,
    fail_count  TINYINT(3) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default admin account  (password: Admin@1234)
INSERT INTO users (name, email, password, role) VALUES
('System Admin', 'admin@tdtpowersteel.com', '$2y$10$NA31bX1lJf7HsfvzzHKZ5.KwGidTK5NXgdBz48A6SPN6jc0ZMGeFC', 'admin');

-- ------------------------------------------------------------
-- Departments
-- ------------------------------------------------------------
CREATE TABLE departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO departments (name) VALUES
('HR & Admin'),
('Sales and Marketing'),
('Business Development'),
('Operations Management'),
('Accounting');

-- ------------------------------------------------------------
-- Interns
-- ------------------------------------------------------------
CREATE TABLE interns (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id       INT UNSIGNED NOT NULL,
    -- Personal
    first_name          VARCHAR(80) NOT NULL,
    last_name           VARCHAR(80) NOT NULL,
    middle_name         VARCHAR(80),
    email               VARCHAR(150),
    phone               VARCHAR(30),
    address             TEXT,
    birthdate           DATE,
    gender              ENUM('Male','Female','Other'),
    nationality         VARCHAR(60),
    civil_status        VARCHAR(20),
    guardian_name       VARCHAR(100),
    guardian_contact    VARCHAR(30),
    -- Academic
    school              VARCHAR(150),
    course              VARCHAR(150),
    year_level          VARCHAR(30),
    school_address      VARCHAR(255),
    -- Internship
    required_hours      DECIMAL(6,2) NOT NULL DEFAULT 486.00,
    rendered_hours      DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    start_date          DATE,
    end_date            DATE,
    supervisor          VARCHAR(100),
    status              ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    profile_photo       VARCHAR(255),
    -- Timestamps
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- ------------------------------------------------------------
-- DTR Entries
-- ------------------------------------------------------------
CREATE TABLE dtr_entries (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    intern_id       INT UNSIGNED NOT NULL,
    entry_date      DATE NOT NULL,
    time_in         TIME,
    time_out        TIME,
    rendered_hours  DECIMAL(5,2) GENERATED ALWAYS AS (
                        CASE
                            WHEN time_in IS NOT NULL AND time_out IS NOT NULL AND time_out > time_in
                            THEN ROUND(TIME_TO_SEC(TIMEDIFF(time_out, time_in)) / 3600, 2)
                            ELSE 0
                        END
                    ) STORED,
    overtime        DECIMAL(5,2) GENERATED ALWAYS AS (
                        CASE
                            WHEN time_in IS NOT NULL AND time_out IS NOT NULL AND time_out > time_in
                                 AND ROUND(TIME_TO_SEC(TIMEDIFF(time_out, time_in)) / 3600, 2) > 8
                            THEN ROUND(TIME_TO_SEC(TIMEDIFF(time_out, time_in)) / 3600, 2) - 8
                            ELSE 0
                        END
                    ) STORED,
    undertime       DECIMAL(5,2) GENERATED ALWAYS AS (
                        CASE
                            WHEN time_in IS NOT NULL AND time_out IS NOT NULL AND time_out > time_in
                                 AND ROUND(TIME_TO_SEC(TIMEDIFF(time_out, time_in)) / 3600, 2) < 8
                            THEN 8 - ROUND(TIME_TO_SEC(TIMEDIFF(time_out, time_in)) / 3600, 2)
                            ELSE 0
                        END
                    ) STORED,
    is_archived     TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id) REFERENCES interns(id)
);

-- ------------------------------------------------------------
-- Requirement Items
-- ------------------------------------------------------------
CREATE TABLE requirement_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    intern_id       INT UNSIGNED NOT NULL,
    name            VARCHAR(200) NOT NULL,
    status          ENUM('Pending','Submitted','Approved') NOT NULL DEFAULT 'Pending',
    status_changed_at DATETIME,
    submission_date DATE,
    file_path       VARCHAR(255),
    file_name       VARCHAR(255),
    remarks         TEXT,
    is_archived     TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id) REFERENCES interns(id)
);

-- ------------------------------------------------------------
-- Audit Trail
-- ------------------------------------------------------------
CREATE TABLE audit_trail (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    user_name   VARCHAR(100),
    action      VARCHAR(50) NOT NULL,
    module      VARCHAR(50) NOT NULL,
    record_id   INT UNSIGNED,
    description TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
