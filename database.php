<?php
/**
 * Database Setup and Connection Handler
 * DoPaper.com - Sri Lankan A/L Exam Paper System (MySQL Version)
 */

// MySQL Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dopaper');

/**
 * Create database connection using PDO (MySQL)
 */
function getDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Initialize database tables
 */
function initDatabase() {
    $pdo = getDB();
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(255),
        stream VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create papers table (customized exam papers)
    $pdo->exec("CREATE TABLE IF NOT EXISTS papers (
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
    )");
    
    // Create questions bank table (for A/L subjects)
    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
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
    )");
    
    // Create paper attempts table for tracking history
    $pdo->exec("CREATE TABLE IF NOT EXISTS paper_attempts (
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
    )");
    
    // Create wrong questions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS wrong_questions (
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
    )");
    
    // Create schedules table for "Do Later" papers
    $pdo->exec("CREATE TABLE IF NOT EXISTS schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        paper_id INT NOT NULL,
        scheduled_date DATE NOT NULL,
        scheduled_time TIME NOT NULL,
        reminder_sent INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
    )");
    
    // Insert sample questions for demonstration
    insertSampleQuestions($pdo);
    
    return true;
}

/**
 * Insert sample questions for demonstration
 */
function insertSampleQuestions($pdo) {
    // Check if questions already exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM questions");
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return; // Questions already exist
    }
    
    // Sample questions for all streams
    $questions = [
        // Science Stream - Chemistry
        ['Chemistry', 2021, 'Past Paper', 1, 'What is the oxidation state of carbon in methane (CH4)?', '-4', '+4', '0', '+2', '-2', 'A', 'In CH4, hydrogen has +1 oxidation state. Since the molecule is neutral, C must be -4.', 2],
        ['Chemistry', 2021, 'Past Paper', 2, 'Which compound has the highest boiling point?', 'CH4', 'C2H6', 'C3H8', 'C4H10', 'C5H12', 'D', 'Boiling point increases with molecular mass due to stronger Van der Waals forces.', 2],
        ['Chemistry', 2021, 'Past Paper', 3, 'What is the product of electrolysis of molten NaCl?', 'Na + Cl2', 'Na + HCl', 'NaOH + Cl2', 'NaClO + H2', 'Na2O + Cl2', 'A', 'Molten NaCl produces sodium at cathode and chlorine gas at anode.', 2],
        ['Chemistry', 2022, 'Past Paper', 1, 'Which element has the highest electronegativity?', 'F', 'O', 'Cl', 'N', 'Br', 'A', 'Fluorine has the highest electronegativity value of 4.0 on Pauling scale.', 2],
        ['Chemistry', 2022, 'Past Paper', 2, 'What type of bond is present in CO2?', 'Single bond', 'Double bond', 'Triple bond', 'Ionic', 'Coordinate bond', 'B', 'CO2 has double bonds between C and O atoms (O=C=O).', 2],
        ['Chemistry', 2023, 'Past Paper', 1, 'Which gas is produced when an acid reacts with metal?', 'O2', 'H2', 'N2', 'CO2', 'SO2', 'B', 'Acids react with metals to produce hydrogen gas.', 2],
        ['Chemistry', 2023, 'Past Paper', 2, 'What is the pH of a neutral solution?', '0', '7', '14', '1', '5', 'B', 'pH 7 indicates a neutral solution at 25°C.', 2],
        ['Chemistry', 2024, 'Past Paper', 1, 'Which is a characteristic of aldehydes?', 'Have -OH group', 'Have -CHO group', 'Have -COOH group', 'Have -NH2 group', 'Have -COOR group', 'B', 'Aldehydes contain the -CHO (aldehyde) functional group.', 2],
        
        // Science Stream - Physics
        ['Physics', 2021, 'Past Paper', 1, 'What is the SI unit of force?', 'Joule', 'Watt', 'Newton', 'Pascal', 'Meter', 'C', 'Force is measured in Newtons (N) in SI system.', 2],
        ['Physics', 2021, 'Past Paper', 2, 'Which wave has the highest frequency?', 'Radio waves', 'Microwave', 'X-ray', 'Visible light', 'Gamma rays', 'C', 'X-rays have very high frequency among electromagnetic waves.', 2],
        ['Physics', 2022, 'Past Paper', 1, 'What is the formula for kinetic energy?', 'mv', '1/2mv²', 'mgh', 'F/d', 'mv²', 'B', 'Kinetic energy = 1/2mv² where m is mass and v is velocity.', 2],
        ['Physics', 2023, 'Past Paper', 1, 'What is the speed of light in vacuum?', '3×10⁶ m/s', '3×10⁸ m/s', '3×10⁴ m/s', '3×10² m/s', '3×10¹⁰ m/s', 'B', 'Speed of light in vacuum is approximately 3×10⁸ m/s.', 2],
        ['Physics', 2024, 'Past Paper', 1, 'What is Ohms law?', 'V = IR', 'P = VI', 'F = ma', 'E = mc²', 'V = I/R', 'A', 'Ohms law states V = IR where V is voltage, I is current, R is resistance.', 2],
        
        // Science Stream - Biology
        ['Biology', 2021, 'Past Paper', 1, 'What is the powerhouse of the cell?', 'Nucleus', 'Mitochondria', 'Ribosome', 'Golgi body', 'Chloroplast', 'B', 'Mitochondria produce ATP through cellular respiration.', 2],
        ['Biology', 2021, 'Past Paper', 2, 'Which organelle is responsible for protein synthesis?', 'Lysosome', 'Ribosome', 'Vacuole', 'Chloroplast', 'Endoplasmic Reticulum', 'B', 'Ribosomes are responsible for protein synthesis in cells.', 2],
        ['Biology', 2022, 'Past Paper', 1, 'What is the basic unit of life?', 'Atom', 'Molecule', 'Cell', 'Tissue', 'Organ', 'C', 'Cell is the basic structural and functional unit of all living organisms.', 2],
        ['Biology', 2023, 'Past Paper', 1, 'What type of cell division produces gametes?', 'Mitosis', 'Meiosis', 'Binary fission', 'Budding', 'Spore formation', 'B', 'Meiosis produces haploid gametes for sexual reproduction.', 2],
        ['Biology', 2024, 'Past Paper', 1, 'What is DNA composed of?', 'Proteins', 'Lipids', 'Nucleotides', 'Carbohydrates', 'Amino acids', 'C', 'DNA is composed of nucleotide building blocks.', 2],
        
        // Science Stream - ICT
        ['ICT', 2021, 'Past Paper', 1, 'What does CPU stand for?', 'Central Processing Unit', 'Computer Personal Unit', 'Central Program Unit', 'Computer Processing Unit', 'Central Print Unit', 'A', 'CPU is the Central Processing Unit - the brain of the computer.', 2],
        ['ICT', 2021, 'Past Paper', 2, 'Which is a primary memory?', 'Hard Disk', 'RAM', 'USB', 'DVD', 'CD-ROM', 'B', 'RAM (Random Access Memory) is primary memory.', 2],
        ['ICT', 2022, 'Past Paper', 1, 'What is the binary representation of decimal 10?', '1000', '1001', '1010', '1100', '1110', 'C', '10 in decimal = 1010 in binary.', 2],
        ['ICT', 2023, 'Past Paper', 1, 'Which is a programming language?', 'Windows', 'Python', 'Microsoft', 'Intel', 'Adobe', 'B', 'Python is a high-level programming language.', 2],
        ['ICT', 2024, 'Past Paper', 1, 'What does HTML stand for?', 'Hyper Text Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 'Hyper Transfer Markup Language', 'Hyper Text Making Language', 'A', 'HTML is Hyper Text Markup Language for web pages.', 2],
        
        // Science Stream - Agriculture
        ['Agriculture', 2021, 'Past Paper', 1, 'What is the pH range for acidic soil?', 'Above 7', 'Below 7', 'Exactly 7', 'Above 10', 'Between 3-6', 'B', 'Acidic soil has pH below 7.', 2],
        ['Agriculture', 2022, 'Past Paper', 1, 'Which is a macronutrient for plants?', 'Iron', 'Zinc', 'Nitrogen', 'Copper', 'Manganese', 'C', 'Nitrogen is a primary macronutrient for plant growth.', 2],
        
        // Technology Stream
        ['SFT', 2021, 'Past Paper', 1, 'What does SFT stand for?', 'Science for Technology', 'System Flow Technology', 'Software Technology', 'Structural Engineering', 'Science Foundation Tech', 'A', 'SFT is Science for Technology.', 2],
        ['SFT', 2022, 'Past Paper', 1, 'What is the unit of electrical resistance?', 'Volt', 'Ampere', 'Ohm', 'Watt', 'Faraday', 'C', 'Resistance is measured in Ohms.', 2],
        ['ET', 2021, 'Past Paper', 1, 'What is Engineering Technology?', 'Design of systems', 'Study of chemicals', 'Art and craft', 'Music composition', 'Food Technology', 'A', 'ET focuses on design and application of technology.', 2],
        ['ET', 2022, 'Past Paper', 1, 'What is the purpose of a transformer?', 'Store energy', 'Change voltage', 'Generate power', 'Measure current', 'Convert DC to AC', 'B', 'Transformers change AC voltage levels.', 2],
        ['BST', 2021, 'Past Paper', 1, 'What does BST stand for?', 'Bio Systems Technology', 'Basic Science Technology', 'Biological Systems', 'Building Systems', 'Business Systems Tech', 'A', 'BST is Bio Systems Technology.', 2],
        ['BST', 2022, 'Past Paper', 1, 'Which organ in the human body pumps blood?', 'Lungs', 'Brain', 'Heart', 'Liver', 'Kidney', 'C', 'Heart pumps blood throughout the body.', 2],
        
        // Commerce Stream
        ['Business Studies', 2021, 'Past Paper', 1, 'What is the main objective of a business?', 'Social service', 'Profit maximization', 'Charity', 'Entertainment', 'Customer satisfaction', 'B', 'Business primary objective is profit maximization.', 2],
        ['Business Studies', 2022, 'Past Paper', 1, 'What is capital?', 'Money for expenses', 'Money invested in business', 'Salary', 'Rent', 'Assets', 'B', 'Capital is money invested to start/run a business.', 2],
        ['Economics', 2021, 'Past Paper', 1, 'What is supply?', 'Demand for goods', 'Amount offered for sale', 'Price', 'Quality', 'Quantity demanded', 'B', 'Supply is the quantity of goods offered for sale.', 2],
        ['Economics', 2022, 'Past Paper', 1, 'What causes inflation?', 'Too much money', 'Deflation', 'Low prices', 'High savings', 'Low interest rates', 'A', 'Inflation is caused by too much money in circulation.', 2],
        ['Accounting', 2021, 'Past Paper', 1, 'What is an asset?', 'Money owed', 'Resource owned', 'Expense', 'Loss', 'Revenue', 'B', 'Asset is a resource with economic value owned by a business.', 2],
        ['Accounting', 2022, 'Past Paper', 1, 'What is liability?', 'Profit', 'Debt owed', 'Revenue', 'Capital', 'Inventory', 'B', 'Liability is money or debt owed by a business.', 2],
        
        // Arts Stream
        ['Mathematics', 2021, 'Past Paper', 1, 'What is the value of π (pi)?', '3.14', '2.5', '4.0', '1.5', '3.14159', 'A', 'Pi (π) is approximately 3.14159.', 2],
        ['Mathematics', 2022, 'Past Paper', 1, 'What is the square root of 144?', '10', '11', '12', '13', '14', 'C', '√144 = 12 because 12×12 = 144.', 2],
        ['Geography', 2021, 'Past Paper', 1, 'What are latitude lines?', 'Vertical lines', 'Horizontal lines', 'Diagonal lines', 'Circular lines', 'Parallel lines', 'B', 'Latitude lines run horizontally (east-west).', 2],
        ['Geography', 2022, 'Past Paper', 1, 'What is the largest ocean?', 'Atlantic', 'Indian', 'Pacific', 'Arctic', 'Southern', 'C', 'Pacific Ocean is the largest ocean.', 2],
    ];
    
    // Insert questions
    $stmt = $pdo->prepare("INSERT INTO questions (subject, paper_year, paper_type, question_number, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($questions as $q) {
        $stmt->execute($q);
    }
}

/**
 * Start secure session with browser persistence
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie to expire when browser closes
        session_set_cookie_params(0, '/');
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    startSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user info
 */
function getCurrentUser() {
    startSession();
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, stream, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * JSON response helper
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Initialize database on include
initDatabase();
