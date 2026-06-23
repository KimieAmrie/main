-- =====================================================
-- Student Registration System - Database Setup
-- Import this file into phpMyAdmin
-- =====================================================

CREATE DATABASE IF NOT EXISTS student_registration_db;
USE student_registration_db;

-- Single unified users table with role
CREATE TABLE IF NOT EXISTS users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100)  NOT NULL,
    username    VARCHAR(50)   UNIQUE NOT NULL,
    email       VARCHAR(100)  UNIQUE NOT NULL,
    phone       VARCHAR(15)   DEFAULT NULL,
    role        ENUM('student', 'staff', 'lecturer') NOT NULL DEFAULT 'student',
    password    VARCHAR(255)  NOT NULL,
    status      ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    -- Academic info (filled later by admin after approval)
    student_no  VARCHAR(20)   DEFAULT NULL,
    faculty     VARCHAR(100)  DEFAULT NULL,
    program     VARCHAR(100)  DEFAULT NULL,
    year_of_study INT         DEFAULT NULL,
    -- Staff/Lecturer info (filled later by admin)
    staff_no    VARCHAR(20)   DEFAULT NULL,
    department  VARCHAR(100)  DEFAULT NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Login logs
CREATE TABLE IF NOT EXISTS login_logs (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    role        ENUM('student', 'staff', 'lecturer') NOT NULL,
    login_time  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address  VARCHAR(50),
    status      ENUM('success', 'failed') DEFAULT 'success',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =====================================================
-- Sample Data (password for all = "password")
-- Hash = password_hash('password', PASSWORD_DEFAULT)
-- =====================================================

INSERT INTO users (full_name, username, email, phone, role, password, status, student_no, faculty, program, year_of_study) VALUES
('Ahmad Hakimi', 'hakimi01', 'hakimi@student.uptm.edu.my', '0123456789', 'student', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'UPTM2024001', 'Faculty of Computing', 'Bachelor of Computer Science', 2);

INSERT INTO users (full_name, username, email, phone, role, password, status, staff_no, department) VALUES
('Encik Farid', 'farid_staff', 'farid@uptm.edu.my', '0112345678', 'staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'STF001', 'Academic Affairs');

INSERT INTO users (full_name, username, email, phone, role, password, status, staff_no, department) VALUES
('Dr. Siti Aminah', 'dr_siti', 'siti@uptm.edu.my', '0198887766', 'lecturer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'LCT001', 'Faculty of Computing');
