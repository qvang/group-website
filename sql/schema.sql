-- Codex Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS codex_db;
USE codex_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT(8) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    account_type ENUM('student', 'teacher', 'admin') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    course_selection VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courses table (for reference)
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default courses
INSERT INTO courses (course_code, course_name) VALUES
('networks', 'Networks'),
('data-structures', 'Data structure & Algorithms'),
('web-dev', 'Professional Web Development'),
('software-eng', 'Software Engineering'),
('javascript', 'Javascript'),
('python', 'Python');

-- User courses junction table (for many-to-many relationship)
CREATE TABLE IF NOT EXISTS user_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_course (user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin account
-- Default credentials: Student ID: 99999999, Password: admin123
-- IMPORTANT: Change the password after first login for security
-- 
-- NOTE: The password hash below is a placeholder. After running this schema,
-- run setup_admin.php to generate a proper password hash for "admin123"
-- Or manually update the password using:
-- UPDATE users SET password = '[generated_hash]' WHERE student_id = 99999999;
--
-- To generate a hash, use: generate_admin_hash.php or run:
-- php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
INSERT INTO users (student_id, name, email, password, account_type, status) VALUES
(99999999, 'Admin', 'admin@codex.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'approved')
ON DUPLICATE KEY UPDATE name=name;

