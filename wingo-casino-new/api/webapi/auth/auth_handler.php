<?php
// Auth Handler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if ($request_method === 'POST' && isset($uri_parts[1])) {
    switch ($uri_parts[1]) {
        case 'register':
            registerUser();
            break;
        case 'login':
            loginUser();
            break;
        case 'logout':
            logoutUser();
            break;
        case 'refresh':
            refreshToken();
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            exit;
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

function registerUser() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $username = sanitizeInput($input['username'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        sendError('Username, email, and password are required', 400);
    }
    
    if (!validateUsername($username)) {
        sendError('Invalid username format', 400);
    }
    
    if (!validateEmail($email)) {
        sendError('Invalid email format', 400);
    }
    
    if (!validatePassword($password)) {
        sendError('Password must be at least 6 characters', 400);
    }
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    $existing_user = $stmt->fetch();
    
    if ($existing_user) {
        sendError('Username or email already exists', 409);
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Start transaction
    try {
        $pdo->beginTransaction();
        
        // Create user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, balance) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password_hash, DEMO_INITIAL_BALANCE]);
        $user_id = $pdo->lastInsertId();
        
        // Add initial balance transaction
        addTransaction($pdo, $user_id, 'deposit', DEMO_INITIAL_BALANCE, 0, DEMO_INITIAL_BALANCE, 'Demo account initial balance');
        
        $pdo->commit();
        
        // Generate token
        $payload = [
            'user_id' => $user_id,
            'username' => $username,
            'exp' => time() + (7 * 24 * 60 * 60) // 7 days
        ];
        $token = generateToken($payload);
        
        sendResponse([
            'success' => true,
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => [
                'id' => $user_id,
                'username' => $username,
                'email' => $email,
                'balance' => DEMO_INITIAL_BALANCE
            ]
        ]);
    } catch (Exception $e) {
        $pdo->rollback();
        sendError('Registration failed: ' . $e->getMessage(), 500);
    }
}

function loginUser() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        sendError('Username and password are required', 400);
    }
    
    // Get user from database
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, balance, is_active FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('Invalid credentials', 401);
    }
    
    if (!$user['is_active']) {
        sendError('Account is deactivated', 401);
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        sendError('Invalid credentials', 401);
    }
    
    // Generate token
    $payload = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'exp' => time() + (7 * 24 * 60 * 60) // 7 days
    ];
    $token = generateToken($payload);
    
    sendResponse([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'balance' => $user['balance']
        ]
    ]);
}

function logoutUser() {
    // In a real implementation, you might want to invalidate the token
    // For now, just return a success message
    sendResponse([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

function refreshToken() {
    global $pdo;
    
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        sendError('Authorization header required', 401);
    }
    
    $auth_header = $headers['Authorization'];
    if (strpos($auth_header, 'Bearer ') !== 0) {
        sendError('Invalid authorization format', 401);
    }
    
    $token = substr($auth_header, 7);
    $payload = validateToken($token);
    
    if (!$payload) {
        sendError('Invalid or expired token', 401);
    }
    
    $user_id = $payload['user_id'];
    
    // Get user from database
    $stmt = $pdo->prepare("SELECT id, username, email, balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    // Generate new token
    $new_payload = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'exp' => time() + (7 * 24 * 60 * 60) // 7 days
    ];
    $new_token = generateToken($new_payload);
    
    sendResponse([
        'success' => true,
        'message' => 'Token refreshed successfully',
        'token' => $new_token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'balance' => $user['balance']
        ]
    ]);
}
?>