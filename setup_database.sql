-- DoPaper.com MySQL Database Setup
-- Run this script in your MySQL server to create the database and tables

-- Create database
CREATE DATABASE IF NOT EXISTS dopaper;
USE dopaper;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    stream VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create papers table
CREATE TABLE IF NOT EXISTS papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    stream VARCHAR(50) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    paper_type VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    num_questions INT NOT NULL,
    time_duration INT NOT NULL,
    total_marks INT DEFAULT 0,
    scheduled_at DATE,
    scheduled_time TIME,
    questions JSON,
    status VARCHAR(20) DEFAULT 'pending',
    reading_time_used INT DEFAULT 0,
    obtained_marks INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create questions bank table
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(100) NOT NULL,
    paper_year INT NOT NULL,
    paper_type VARCHAR(50) NOT NULL,
    question_number INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500),
    option_b VARCHAR(500),
    option_c VARCHAR(500),
    option_d VARCHAR(500),
    option_e VARCHAR(500),
    correct_answer VARCHAR(10) NOT NULL,
    explanation TEXT,
    marks INT DEFAULT 2,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subject_year (subject, paper_year)
);

-- Create paper attempts table
CREATE TABLE IF NOT EXISTS paper_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paper_id INT NOT NULL,
    user_id INT NOT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    obtained_marks INT DEFAULT NULL,
    time_taken INT DEFAULT NULL,
    reading_time INT DEFAULT 0,
    FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create wrong questions table
CREATE TABLE IF NOT EXISTS wrong_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    paper_id INT NOT NULL,
    question_id INT NOT NULL,
    user_answer VARCHAR(10),
    correct_answer VARCHAR(10),
    is_wrong INT DEFAULT 1,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Create schedules table
CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    paper_id INT NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    reminder_sent INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
);

-- Insert sample questions
INSERT INTO questions (subject, paper_year, paper_type, question_number, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation, marks) VALUES
('Chemistry', 2021, 'Past Paper', 1, 'What is the oxidation state of carbon in methane (CH4)?', '-4', '+4', '0', '+2', '-2', 'A', 'In CH4, hydrogen has +1 oxidation state. Since the molecule is neutral, C must be -4.', 2),
('Chemistry', 2021, 'Past Paper', 2, 'Which compound has the highest boiling point?', 'CH4', 'C2H6', 'C3H8', 'C4H10', 'C5H12', 'D', 'Boiling point increases with molecular mass.', 2),
('Chemistry', 2022, 'Past Paper', 1, 'Which element has the highest electronegativity?', 'F', 'O', 'Cl', 'N', 'Br', 'A', 'Fluorine has the highest electronegativity value.', 2),
('Chemistry', 2023, 'Past Paper', 1, 'What is the pH of a neutral solution?', '0', '7', '14', '1', '5', 'B', 'pH 7 indicates a neutral solution.', 2),
('Physics', 2021, 'Past Paper', 1, 'What is the SI unit of force?', 'Joule', 'Watt', 'Newton', 'Pascal', 'Meter', 'C', 'Force is measured in Newtons.', 2),
('Physics', 2022, 'Past Paper', 1, 'What is the formula for kinetic energy?', 'mv', '1/2mv2', 'mgh', 'F/d', 'mv2', 'B', 'Kinetic energy = 1/2mv2', 2),
('Physics', 2023, 'Past Paper', 1, 'What is the speed of light in vacuum?', '3x106 m/s', '3x108 m/s', '3x104 m/s', '3x102 m/s', '3x1010 m/s', 'B', 'Speed of light is 3x108 m/s.', 2),
('Biology', 2021, 'Past Paper', 1, 'What is the powerhouse of the cell?', 'Nucleus', 'Mitochondria', 'Ribosome', 'Golgi body', 'Chloroplast', 'B', 'Mitochondria produce ATP.', 2),
('Biology', 2022, 'Past Paper', 1, 'What is the basic unit of life?', 'Atom', 'Molecule', 'Cell', 'Tissue', 'Organ', 'C', 'Cell is the basic unit of life.', 2),
('ICT', 2021, 'Past Paper', 1, 'What does CPU stand for?', 'Central Processing Unit', 'Computer Personal Unit', 'Central Program Unit', 'Computer Processing Unit', 'Central Print Unit', 'A', 'CPU is Central Processing Unit.', 2),
('ICT', 2022, 'Past Paper', 1, 'What is binary of 10?', '1000', '1001', '1010', '1100', '1110', 'C', '10 = 1010 in binary.', 2),
('SFT', 2021, 'Past Paper', 1, 'What does SFT stand for?', 'Science for Technology', 'System Flow Technology', 'Software Technology', 'Structural Engineering', 'Science Foundation Tech', 'A', 'SFT is Science for Technology.', 2),
('ET', 2021, 'Past Paper', 1, 'What is Engineering Technology?', 'Design of systems', 'Study of chemicals', 'Art and craft', 'Music composition', 'Food Technology', 'A', 'ET focuses on design and application of technology.', 2),
('BST', 2021, 'Past Paper', 1, 'What does BST stand for?', 'Bio Systems Technology', 'Basic Science Technology', 'Biological Systems', 'Building Systems', 'Business Systems Tech', 'A', 'BST is Bio Systems Technology.', 2),
('Business Studies', 2021, 'Past Paper', 1, 'What is main objective of business?', 'Social service', 'Profit maximization', 'Charity', 'Entertainment', 'Customer satisfaction', 'B', 'Profit maximization is the main objective.', 2),
('Economics', 2021, 'Past Paper', 1, 'What is supply?', 'Demand for goods', 'Amount offered for sale', 'Price', 'Quality', 'Quantity demanded', 'B', 'Supply is the quantity of goods offered for sale.', 2),
('Accounting', 2021, 'Past Paper', 1, 'What is an asset?', 'Money owed', 'Resource owned', 'Expense', 'Loss', 'Revenue', 'B', 'Asset is resource with economic value owned by a business.', 2),
('Mathematics', 2021, 'Past Paper', 1, 'What is value of pi?', '3.14', '2.5', '4.0', '1.5', '3.14159', 'A', 'Pi (π) is approximately 3.14159.', 2),
('Geography', 2021, 'Past Paper', 1, 'What are latitude lines?', 'Vertical lines', 'Horizontal lines', 'Diagonal lines', 'Circular lines', 'Parallel lines', 'B', 'Latitude lines run horizontally (east-west).', 2);

SELECT 'Database setup completed!' AS Status;