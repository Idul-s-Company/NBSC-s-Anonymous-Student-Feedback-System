CREATE DATABASE IF NOT EXISTS feedback_db;
USE feedback_db;

CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,  
    school_id VARCHAR(20) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
    department VARCHAR(50) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP);

-- Sample Data for Users
INSERT INTO users (school_id, first_name, last_name, email, password, role, department, status) VALUES
('ADM-001', 'Admin', 'NBSC', 'admin@nbsc.edu.ph', ' $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administration', 'active'), 
('S-0008', 'Rosa', 'Villanueva', 'r.villanueva@nbsc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi ', 'staff', 'SAS', 'active'),
('2024-00102','Rhics', 'Geonzon', 'r.geonzon@nbsc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi ', 'student', 'IT', 'active'),
('2023-00045','Troy', 'Rojo', 't.rojo@nbsc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi ', 'student', 'Business', 'inactive');

-- Categories Table
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT, 
    category_name VARCHAR(50) NOT NULL,
    description VARCHAR(200) NOT NULL);

-- SAMPLE DATA FOR CATEGORIES
INSERT INTO categories (category_name, description) VALUES
('Academic','Concerns related to courses, grading, and curriculum'),
('Facilities','Concerns about campus buildings, rooms, and equipment'),
('Faculty','Concerns regarding teachers and their conduct'),
('Services','Administrative and student services concerns'),
('Safety','Campus safety, security, and emergency concerns'),
('Other','General or miscellaneous feedback');

-- Activity Logs Table
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(60) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- SAMPLE DATA FOR ACTIVITY LOGS
INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'LOGIN', 'Admin logged into the system', '192.168.1.10'),
(2, 'REVIEW_FEEDBACK', 'Admin reviewed feedback NBSC-D3E4F and added notes', '192.168.1.10'),
(3, 'STATUS_CHANGED', 'Feedback NBSC-J7K8L marked as resolved', '192.168.1.10'),
(4, 'USER_CREATED', 'Admin created user account for Maria Santos', '192.168.1.10');

CREATE TABLE feedback (
    feedback_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Low',
    message VARCHAR(200) NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    submitted_at   DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);  

-- SAMPLE DATA FOR FEEDBACK
INSERT INTO feedback (category_id, priority, message, status) VALUES
(1, 'High',   'The grading system for our major subjects lacks transparency. A clear, published rubric would greatly help.', 'pending'),
(2, 'Urgent', 'The restrooms near the Engineering building have been broken for over two weeks.', 'reviewed'),
(3, 'High',   'One of our professors consistently starts class 20-30 minutes late without covering required topics.', 'pending'),
(4, 'Medium', 'The registration portal was extremely slow and kept timing out during enrollment.', 'resolved');

-- Feedback Attachments Table
CREATE TABLE feedback_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id   INT NOT NULL,
    file_name     VARCHAR(255) NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    file_type     VARCHAR(80),
    file_size_kb  INT,
    uploaded_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES feedback(feedback_id)
);

CREATE TABLE feedback_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT,
    reviewed_by INT NOT NULL,
    review_notes TEXT,
    status_changed ENUM('pending', 'reviewed', 'resolved'),
    reviewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (feedback_id) REFERENCES feedback(feedback_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
);

-- SAMPLE DATA FOR FEEDBACK REVIEWS
INSERT INTO feedback_reviews (feedback_id, reviewed_by, review_notes, status_changed) VALUES
(1, 1, 'Forwarded to the facilities management team. Awaiting repair schedule.', 'reviewed'),
(2, 1, 'IT department addressed the server load issue. New infrastructure deployed.', 'resolved'),
(3, 1, 'Safety officer notified. Temporary mats installed.', 'reviewed'),
(4, 1, 'Electrical team replaced bulbs and repaired wiring. All lights operational.', 'resolved');

-- Notifications Table
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- SAMPLE DATA FOR NOTIFICATIONS
INSERT INTO notifications (user_id, title, message, is_read) VALUES
(1, 'New Urgent Feedback', 'A new Urgent feedback was submitted under Safety.', 0),
(2, 'New Feedback Submitted', 'A new High priority feedback submitted under Academic.', 0),
(3, 'Feedback Resolved', 'Feedback NBSC-J7K8L has been marked as resolved.', 1);

-- Query to Retrieve All Feedback with Category
SELECT
    c.category_name,
    f.priority,
    f.message,
    f.status,
    f.submitted_at
FROM feedback f
INNER JOIN categories c ON f.category_id = c.category_id
ORDER BY f.submitted_at DESC;

-- Query to Retrieve Feedback with Admin Review Notes
SELECT
    c.category_name,
    f.priority,
    f.status,
    r.notes AS admin_notes,
    CONCAT(u.first_name, ' ', u.last_name) AS reviewed_by,
    r.reviewed_at
FROM feedback f
INNER JOIN categories c ON f.category_id = c.category_id
INNER JOIN feedback_reviews r ON f.feedback_id = r.feedback_id
INNER JOIN users u ON r.reviewed_by = u.user_id;

-- Query to Retrieve Activity Logs with User Information
SELECT
    a.log_id,
    CONCAT(u.first_name, ' ', u.last_name) AS full_name,
    u.email,
    u.role,
    a.action,
    a.description,
    a.ip_address,
    a.created_at
FROM activity_logs a
INNER JOIN users u ON a.user_id = u.user_id;

-- Query to Retrieve All Users
SELECT
    user_id,
    school_id,
    CONCAT(first_name, ' ', last_name) AS full_name,
    email,
    role,
    department,
    status,
    created_at
FROM users
ORDER BY role, last_name ASC;