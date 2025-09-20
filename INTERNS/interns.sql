-- interns.sql - self-contained schema + seed
-- Engine: MySQL 5.7+/MariaDB 10+
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS interns_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE interns_app;

-- Users table (admins, students, company reps)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','student','supervisor','lecturer') NOT NULL DEFAULT 'student',
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Companies
CREATE TABLE IF NOT EXISTS companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  address TEXT,
  contact_person VARCHAR(120),
  contact_email VARCHAR(190),
  contact_phone VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Courses
CREATE TABLE IF NOT EXISTS courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  department VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Students
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  matric_no VARCHAR(50) NOT NULL UNIQUE,
  course_id INT,
  company_id INT,
  start_date DATE,
  end_date DATE,
  supervisor_name VARCHAR(120),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- System settings
CREATE TABLE IF NOT EXISTS system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed minimal data
INSERT IGNORE INTO system_settings (name, value) VALUES
('name','Internship Timesheet System'),
('allow_self_register','0');

-- Admin user (email: admin@example.com, password: Admin@123)
INSERT IGNORE INTO users (id, name, email, password_hash, role) VALUES
(1, 'System Admin', 'admin@example.com', '$2y$10$8jV4E.0o2fC3WJmXHSuO4e8mQx5H5lGm9uHkJt4H4eQv7wC1r7WWS', 'admin');

-- Sample course
INSERT IGNORE INTO courses (code, title, department) VALUES
('DFP40182','Software Requirement and Design','IT'),
('DFP40203','Python Programming','IT'),
('DFP40263','Secure Mobile Computing','IT');

SET FOREIGN_KEY_CHECKS = 1;
