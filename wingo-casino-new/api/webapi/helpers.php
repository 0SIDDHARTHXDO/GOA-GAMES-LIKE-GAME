<?php
// Helper Functions

// JWT Functions
function generateToken($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    
    $header_base64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $payload_base64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $header_base64 . '.' . $payload_base64, JWT_SECRET, true);
    $signature_base64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $header_base64 . '.' . $payload_base64 . '.' . $signature_base64;
}

function validateToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    
    $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[2]));
    
    $expected_signature = hash_hmac('sha256', $parts[0] . '.' . $parts[1], JWT_SECRET, true);
    
    if (!hash_equals($expected_signature, $signature)) {
        return false;
    }
    
    $payload_data = json_decode($payload, true);
    
    // Check if token is expired
    if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
        return false;
    }
    
    return $payload_data;
}

function getUserIdFromToken($token) {
    $payload = validateToken($token);
    if ($payload && isset($payload['user_id'])) {
        return $payload['user_id'];
    }
    return null;
}

function isAdminToken($token) {
    $payload = validateToken($token);
    if ($payload && isset($payload['user_id']) && isset($payload['role']) && $payload['role'] === 'admin') {
        return true;
    }
    return false;
}

// Authentication Middleware
function authenticateUser() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization header required']);
        exit;
    }
    
    $auth_header = $headers['Authorization'];
    if (strpos($auth_header, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid authorization format']);
        exit;
    }
    
    $token = substr($auth_header, 7);
    $user_id = getUserIdFromToken($token);
    
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }
    
    return $user_id;
}

function authenticateAdmin() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization header required']);
        exit;
    }
    
    $auth_header = $headers['Authorization'];
    if (strpos($auth_header, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid authorization format']);
        exit;
    }
    
    $token = substr($auth_header, 7);
    if (!isAdminToken($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    return true;
}

// Input Validation
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

// Game Logic Helpers
function generateIssueNumber() {
    $date = date('Ymd');
    $random = rand(100, 999);
    return $date . sprintf('%03d', $random);
}

function getGameEndTime($startTime) {
    return date('Y-m-d H:i:s', strtotime($startTime) + (GAME_DURATION_MINUTES * 60));
}

function getWinningNumber() {
    return rand(0, 9);
}

function getWinningColor($number) {
    if (in_array($number, [2, 4, 6, 8])) {
        return 'red';
    } elseif (in_array($number, [1, 3, 7, 9])) {
        return 'green';
    } else { // 0, 5
        return 'violet';
    }
}

function getWinningSize($number) {
    if ($number >= 5) {
        return 'big';
    } else {
        return 'small';
    }
}

// Database Helper Functions
function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id, username, email, balance, vip_level_id FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function updateUserBalance($pdo, $user_id, $new_balance) {
    $stmt = $pdo->prepare("UPDATE users SET balance = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$new_balance, $user_id]);
}

function addTransaction($pdo, $user_id, $type, $amount, $balance_before, $balance_after, $description = '') {
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $type, $amount, $balance_before, $balance_after, $description]);
}

function getCurrentIssue($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM game_issues WHERE status IN ('pending', 'active', 'locked') ORDER BY end_time ASC LIMIT 1");
    return $stmt->fetch();
}

function getRecentIssues($pdo, $limit = 10) {
    $stmt = $pdo->prepare("SELECT * FROM game_issues WHERE status = 'completed' ORDER BY end_time DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getUserBetsForIssue($pdo, $user_id, $issue_id) {
    $stmt = $pdo->prepare("SELECT * FROM bets WHERE user_id = ? AND issue_id = ?");
    $stmt->execute([$user_id, $issue_id]);
    return $stmt->fetchAll();
}

function calculateBetResult($bet_value, $bet_type, $winning_number, $winning_color, $winning_size) {
    switch ($bet_type) {
        case 'number':
            return $bet_value == $winning_number ? 'won' : 'lost';
        case 'color':
            return $bet_value == $winning_color ? 'won' : 'lost';
        case 'size':
            return $bet_value == $winning_size ? 'won' : 'lost';
        default:
            return 'lost';
    }
}

// Utility Functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function sendResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

function sendError($message, $status_code = 400) {
    http_response_code($status_code);
    echo json_encode(['error' => $message]);
    exit;
}
?>