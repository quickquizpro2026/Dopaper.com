<?php
/**
 * Authentication Handler
 * Quick Quiz - Login/Register/Session Management
 */

require_once 'database.php';

startSession();

// Handle API requests
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check_session':
        checkSession();
        break;
    case 'get_user':
        getUser();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * Handle user login
 */
function handleLogin() {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($email) || empty($password)) {
        jsonResponse(['error' => 'Email and password are required'], 400);
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, username, email, password, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful - set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            
            jsonResponse([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name']
                ]
            ]);
        } else {
            jsonResponse(['error' => 'Invalid email or password'], 401);
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Login failed: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle user registration
 */
function handleRegister() {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!empty($errors)) {
        jsonResponse(['error' => implode(', ', $errors)], 400);
    }
    
    try {
        $pdo = getDB();
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Email or username already exists'], 400);
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword, $fullName]);
        
        $userId = $pdo->lastInsertId();
        
        // Set session
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['full_name'] = $fullName;
        
        jsonResponse([
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'full_name' => $fullName
            ]
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    startSession();
    
    // Destroy session
    session_unset();
    session_destroy();
    
    jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}

/**
 * Check if user session is active
 */
function checkSession() {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        jsonResponse([
            'logged_in' => true,
            'user' => $user
        ]);
    } else {
        jsonResponse(['logged_in' => false]);
    }
}

/**
 * Get current user info
 */
function getUser() {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        jsonResponse([
            'success' => true,
            'user' => $user
        ]);
    } else {
        jsonResponse(['error' => 'Not logged in'], 401);
    }
}
