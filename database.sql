-- ============================================================================
-- Smart College Management Portal - Database Schema & Synthetic Sample Data
-- Database: smart_clg_portal
-- Target Environment: XAMPP (Apache + MySQL / MariaDB, PHP 8+)
-- ============================================================================

-- 1. Create database if it does not exist
CREATE DATABASE IF NOT EXISTS `smart_clg_portal`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `smart_clg_portal`;

-- Temporarily disable foreign key checks for clean drops/creates
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `doubt_responses`;
DROP TABLE IF EXISTS `doubts`;
DROP TABLE IF EXISTS `assignment_submissions`;
DROP TABLE IF EXISTS `assignments`;
DROP TABLE IF EXISTS `notes`;
DROP TABLE IF EXISTS `attendance_records`;
DROP TABLE IF EXISTS `attendance_sessions`;
DROP TABLE IF EXISTS `teacher_assignments`;
DROP TABLE IF EXISTS `teacher_profiles`;
DROP TABLE IF EXISTS `student_profiles`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 2. Table Definitions
-- ============================================================================

-- Table 1: users (Shared authentication for Admin, Teacher, Student)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: departments (Academic departments e.g., CSE, IT, ECE)
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_name` VARCHAR(100) NOT NULL,
  `department_code` VARCHAR(20) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: classes (Class sections, semesters, academic year)
CREATE TABLE `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NOT NULL,
  `class_name` VARCHAR(50) NOT NULL,
  `semester` INT NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_classes_dept` (`department_id`),
  CONSTRAINT `fk_classes_department` FOREIGN KEY (`department_id`)
    REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: subjects (Course curriculum subjects)
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NOT NULL,
  `subject_name` VARCHAR(120) NOT NULL,
  `subject_code` VARCHAR(30) NOT NULL UNIQUE,
  `semester` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_subjects_dept` (`department_id`),
  CONSTRAINT `fk_subjects_department` FOREIGN KEY (`department_id`)
    REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 5: student_profiles (Extended details linked 1-to-1 with users)
CREATE TABLE `student_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `roll_number` VARCHAR(30) NOT NULL UNIQUE,
  `enrollment_number` VARCHAR(50) NOT NULL UNIQUE,
  `department_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `phone` VARCHAR(20) NULL,
  `gender` ENUM('Male', 'Female', 'Other') NULL,
  `dob` DATE NULL,
  `address` TEXT NULL,
  `guardian_name` VARCHAR(100) NULL,
  `guardian_phone` VARCHAR(20) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_students_class` (`class_id`),
  INDEX `idx_students_dept` (`department_id`),
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_students_dept` FOREIGN KEY (`department_id`)
    REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 6: teacher_profiles (Faculty details linked 1-to-1 with users)
CREATE TABLE `teacher_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `employee_id` VARCHAR(30) NOT NULL UNIQUE,
  `department_id` INT NOT NULL,
  `designation` VARCHAR(100) NOT NULL,
  `qualification` VARCHAR(100) NULL,
  `phone` VARCHAR(20) NULL,
  `joining_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_teachers_dept` (`department_id`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teachers_dept` FOREIGN KEY (`department_id`)
    REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 7: teacher_assignments (Maps teacher to subject, class, and academic year)
CREATE TABLE `teacher_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_teacher_subject_class_year` (`teacher_id`, `subject_id`, `class_id`, `academic_year`),
  CONSTRAINT `fk_ta_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teacher_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ta_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ta_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 8: attendance_sessions (Individual lecture/period attendance sessions)
CREATE TABLE `attendance_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `session_date` DATE NOT NULL,
  `session_time` TIME NOT NULL,
  `topic` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_att_sess_date` (`session_date`),
  CONSTRAINT `fk_att_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teacher_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 9: attendance_records (Individual student status for a given session)
CREATE TABLE `attendance_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `status` ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL DEFAULT 'Present',
  `remarks` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_session_student` (`session_id`, `student_id`),
  INDEX `idx_att_status` (`status`),
  CONSTRAINT `fk_ar_session` FOREIGN KEY (`session_id`)
    REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ar_student` FOREIGN KEY (`student_id`)
    REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 10: notes (Study materials, PDFs, slides uploaded by teachers)
CREATE TABLE `notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(50) DEFAULT 'pdf',
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notes_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notes_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notes_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teacher_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 11: assignments (Homework, lab assignments created by teachers)
CREATE TABLE `assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `due_date` DATETIME NOT NULL,
  `max_marks` INT NOT NULL DEFAULT 100,
  `attachment_path` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_assign_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assign_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assign_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teacher_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 12: assignment_submissions (Student submission entries and grades)
CREATE TABLE `assignment_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `submission_text` TEXT NULL,
  `file_path` VARCHAR(255) NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `marks_obtained` DECIMAL(5,2) NULL,
  `feedback` TEXT NULL,
  `status` ENUM('submitted', 'graded', 'late', 'resubmitted') DEFAULT 'submitted',
  UNIQUE KEY `uk_assignment_student` (`assignment_id`, `student_id`),
  CONSTRAINT `fk_asub_assignment` FOREIGN KEY (`assignment_id`)
    REFERENCES `assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asub_student` FOREIGN KEY (`student_id`)
    REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 13: doubts (Student question queries to subject teachers)
CREATE TABLE `doubts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('open', 'answered', 'closed') DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_doubts_student` FOREIGN KEY (`student_id`)
    REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_doubts_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 14: doubt_responses (Replies from teachers or students to doubts)
CREATE TABLE `doubt_responses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doubt_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `response_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_dr_doubt` FOREIGN KEY (`doubt_id`)
    REFERENCES `doubts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dr_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 15: notifications (System notices, role broadcasts, or personal alerts)
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `target_role` ENUM('all', 'admin', 'teacher', 'student') DEFAULT 'all',
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user` (`user_id`),
  INDEX `idx_notif_role` (`target_role`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. Synthetic Sample Data
--
-- Demo Passwords (Bcrypt-hashed):
--   Admin:   Admin@123
--   Teacher: Teacher@123
--   Student: Student@123
-- ============================================================================

-- Insert Users
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `full_name`, `status`) VALUES
(1, 'admin', 'admin@smartcollege.edu', '$2y$10$gm8SaKDd4rbkXMZrYZYN5eki4mwEoLKBSgj31X8ciNDyT5x2RFzu.', 'admin', 'Administrator System', 'active'),
(2, 'teacher_sharma', 'r.sharma@smartcollege.edu', '$2y$10$847/RRb4ytaXLacQSCFRX.MzmP.eAQwh6k0fXMsIx8YnPKY23Us4i', 'teacher', 'Dr. Rajesh Sharma', 'active'),
(3, 'teacher_verma', 'p.verma@smartcollege.edu', '$2y$10$847/RRb4ytaXLacQSCFRX.MzmP.eAQwh6k0fXMsIx8YnPKY23Us4i', 'teacher', 'Prof. Priya Verma', 'active'),
(4, 'student_aarav', 'aarav.patel@student.smartcollege.edu', '$2y$10$JVeoBkiO0YTRU4IxvdJnRuOAe0cSDJzzr/.jW86XETDT.MZuECxI.', 'student', 'Aarav Patel', 'active'),
(5, 'student_diya', 'diya.sharma@student.smartcollege.edu', '$2y$10$JVeoBkiO0YTRU4IxvdJnRuOAe0cSDJzzr/.jW86XETDT.MZuECxI.', 'student', 'Diya Sharma', 'active'),
(6, 'student_rohan', 'rohan.mehta@student.smartcollege.edu', '$2y$10$JVeoBkiO0YTRU4IxvdJnRuOAe0cSDJzzr/.jW86XETDT.MZuECxI.', 'student', 'Rohan Mehta', 'active');

-- Insert Departments
INSERT INTO `departments` (`id`, `department_name`, `department_code`, `description`) VALUES
(1, 'Computer Science and Engineering', 'CSE', 'Department of Computer Science and Engineering'),
(2, 'Information Technology', 'IT', 'Department of Information Technology'),
(3, 'Electronics and Communication Engineering', 'ECE', 'Department of Electronics and Communication');

-- Insert Classes
INSERT INTO `classes` (`id`, `department_id`, `class_name`, `semester`, `academic_year`) VALUES
(1, 1, 'CSE-5A', 5, '2025-2026'),
(2, 1, 'CSE-5B', 5, '2025-2026'),
(3, 2, 'IT-5A', 5, '2025-2026');

-- Insert Subjects
INSERT INTO `subjects` (`id`, `department_id`, `subject_name`, `subject_code`, `semester`) VALUES
(1, 1, 'Database Management Systems', 'CS501', 5),
(2, 1, 'Web Technologies & PHP', 'CS502', 5),
(3, 1, 'Operating Systems', 'CS503', 5),
(4, 2, 'Software Engineering', 'IT501', 5);

-- Insert Teacher Profiles
INSERT INTO `teacher_profiles` (`id`, `user_id`, `employee_id`, `department_id`, `designation`, `qualification`, `phone`, `joining_date`) VALUES
(1, 2, 'EMP-T101', 1, 'Associate Professor', 'Ph.D. in Computer Science', '9876543210', '2019-07-15'),
(2, 3, 'EMP-T102', 1, 'Assistant Professor', 'M.Tech in Software Engineering', '9876543211', '2021-08-01');

-- Insert Student Profiles
INSERT INTO `student_profiles` (`id`, `user_id`, `roll_number`, `enrollment_number`, `department_id`, `class_id`, `phone`, `gender`, `dob`, `address`, `guardian_name`, `guardian_phone`) VALUES
(1, 4, 'CSE-25-001', 'ENR2023001', 1, 1, '9820011111', 'Male', '2004-03-12', '12 Lake View Residency, City Center', 'Suresh Patel', '9820011112'),
(2, 5, 'CSE-25-002', 'ENR2023002', 1, 1, '9820022222', 'Female', '2004-07-25', '45 Green Park Avenue, North Campus', 'Sunil Sharma', '9820022223'),
(3, 6, 'CSE-25-003', 'ENR2023003', 1, 1, '9820033333', 'Male', '2003-11-05', '88 Sunrise Apartments, South Block', 'Manoj Mehta', '9820033334');

-- Insert Teacher Subject Assignments
INSERT INTO `teacher_assignments` (`id`, `teacher_id`, `subject_id`, `class_id`, `academic_year`) VALUES
(1, 1, 1, 1, '2025-2026'), -- Dr. Rajesh Sharma -> DBMS -> CSE-5A
(2, 1, 2, 1, '2025-2026'), -- Dr. Rajesh Sharma -> Web Tech -> CSE-5A
(3, 2, 3, 1, '2025-2026'); -- Prof. Priya Verma -> OS -> CSE-5A

-- Insert Attendance Sessions
INSERT INTO `attendance_sessions` (`id`, `class_id`, `subject_id`, `teacher_id`, `session_date`, `session_time`, `topic`) VALUES
(1, 1, 1, 1, '2026-10-15', '09:00:00', 'Introduction to Relational Algebra & SQL Normalization'),
(2, 1, 2, 1, '2026-10-16', '10:00:00', 'PHP PDO Connection and Prepared Statements Architecture'),
(3, 1, 3, 2, '2026-10-17', '11:00:00', 'Process Scheduling Algorithms: FCFS and Round Robin');

-- Insert Attendance Records
INSERT INTO `attendance_records` (`session_id`, `student_id`, `status`, `remarks`) VALUES
-- Session 1
(1, 1, 'Present', 'On time'),
(1, 2, 'Present', 'On time'),
(1, 3, 'Absent', 'Medical leave'),
-- Session 2
(2, 1, 'Present', 'Active in class'),
(2, 2, 'Late', 'Arrived 10 mins late'),
(2, 3, 'Present', 'On time'),
-- Session 3
(3, 1, 'Present', 'On time'),
(3, 2, 'Present', 'On time'),
(3, 3, 'Present', 'On time');

-- Insert Notes / Study Materials
INSERT INTO `notes` (`id`, `subject_id`, `class_id`, `teacher_id`, `title`, `description`, `file_path`, `file_type`) VALUES
(1, 1, 1, 1, 'Unit 1: ER Modeling and Normalization Guide', 'Comprehensive slides and reference notes covering 1NF, 2NF, 3NF and BCNF.', 'uploads/notes/dbms_unit1.pdf', 'pdf'),
(2, 2, 1, 1, 'Web Tech: PHP PDO & MySQL Architecture', 'Lecture slides on database connection, secure PDO parameters and error handling.', 'uploads/notes/webtech_php_pdo.pdf', 'pdf');

-- Insert Assignments
INSERT INTO `assignments` (`id`, `subject_id`, `class_id`, `teacher_id`, `title`, `description`, `due_date`, `max_marks`, `attachment_path`) VALUES
(1, 1, 1, 1, 'DBMS Assignment 1: Schema Design & SQL Queries', 'Design an ER diagram and write SQL queries for a library management system.', '2026-10-30 23:59:59', 50, 'uploads/assignments/dbms_hw1.pdf'),
(2, 2, 1, 1, 'Web Tech Assignment 1: PHP CRUD with Bootstrap 5', 'Build a student record submission form using PHP PDO and Bootstrap 5.', '2026-11-05 23:59:59', 50, 'uploads/assignments/webtech_hw1.pdf');

-- Insert Assignment Submissions
INSERT INTO `assignment_submissions` (`assignment_id`, `student_id`, `submission_text`, `file_path`, `submitted_at`, `marks_obtained`, `feedback`, `status`) VALUES
(1, 1, 'Completed all normalization steps and SQL queries as instructed.', 'uploads/submissions/aarav_dbms_hw1.pdf', '2026-10-20 18:30:00', 48.00, 'Excellent schema design and relational constraints.', 'graded'),
(1, 2, 'Attached SQL script and ER diagram document.', 'uploads/submissions/diya_dbms_hw1.pdf', '2026-10-21 14:15:00', NULL, NULL, 'submitted');

-- Insert Doubts
INSERT INTO `doubts` (`id`, `student_id`, `subject_id`, `title`, `description`, `status`) VALUES
(1, 1, 1, 'Difference between BCNF and 3NF with multi-valued attributes', 'Could you clarify when a relation is in 3NF but fails BCNF with a practical example?', 'answered'),
(2, 2, 2, 'How does PDO prepared statement prevent SQL injection under the hood?', 'Looking for a detailed explanation of parameter binding vs string concatenation.', 'open');

-- Insert Doubt Responses
INSERT INTO `doubt_responses` (`id`, `doubt_id`, `user_id`, `response_text`) VALUES
(1, 1, 2, 'In 3NF, the condition is that for every functional dependency X -> A, either X is a superkey or A is a prime attribute. In BCNF, X must strictly be a superkey, eliminating non-trivial dependencies on prime attributes.');

-- Insert Notifications
INSERT INTO `notifications` (`user_id`, `target_role`, `title`, `message`, `is_read`) VALUES
(NULL, 'all', 'Mid-Term Examination Schedule Announced', 'The mid-term examination schedule for 5th semester will commence from next month. Check the college notice board for detailed datesheets.', 0),
(NULL, 'student', 'New Web Technologies Assignment Uploaded', 'Dr. Rajesh Sharma has uploaded Assignment 1 for Web Technologies. Deadline is November 5th.', 0),
(4, 'student', 'Assignment 1 Evaluated', 'Your DBMS Assignment 1 has been evaluated with score 48/50.', 0);
