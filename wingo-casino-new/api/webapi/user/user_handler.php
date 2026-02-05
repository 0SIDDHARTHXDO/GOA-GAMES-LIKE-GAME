<?php
// User Handler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

$user_id = authenticateUser();

if ($request_method === 'GET') {
    if (isset($uri_parts[1]) && $uri_parts[1] === 'profile') {
        getUserProfile();
    } elseif (isset($uri_parts[1]) && $uri_parts[1] === 'history') {
        if (isset($uri_parts[2]) && $uri_parts[2] === 'bets') {
            getUserBetHistory();
        } elseif (isset($uri_parts[2]) && $uri_parts[2] === 'games') {
            getUserGameHistory();
        } else {
            sendError('Invalid history type', 400);
        }
    } else {
        sendError('Invalid endpoint', 404);
    }
} elseif ($request_method === 'PUT' && isset($uri_parts[1]) && $uri_parts[1] === 'profile') {
    updateUserProfile();
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

function getUserProfile() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.balance, u.vip_level_id, u.total_bets, u.total_wins, 
               v.name as vip_name, v.bonus_percentage, v.color as vip_color
        FROM users u
        LEFT JOIN vip_levels v ON u.vip_level_id = v.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    sendResponse([
        'success' => true,
        'user' => $user
    ]);
}

function getUserBetHistory() {
    global $pdo, $user_id;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT b.*, gi.issue_number, gi.winning_number, gi.winning_color, gi.winning_size, gi.status as issue_status
        FROM bets b
        LEFT JOIN game_issues gi ON b.issue_id = gi.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    $bets = $stmt->fetchAll();
    
    // Get total count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bets WHERE user_id = ?");
    $countStmt->execute([$user_id]);
    $total = $countStmt->fetchColumn();
    
    sendResponse([
        'success' => true,
        'bets' => $bets,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

function getUserGameHistory() {
    global $pdo, $user_id;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT gi.*, gr.winning_number, gr.winning_color, gr.winning_size
        FROM game_issues gi
        LEFT JOIN game_results gr ON gi.id = gr.issue_id
        WHERE gi.id IN (
            SELECT DISTINCT issue_id FROM bets WHERE user_id = ?
        )
        ORDER BY gi.end_time DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    $games = $stmt->fetchAll();
    
    // Get total count for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT gi.id) 
        FROM game_issues gi
        JOIN bets b ON gi.id = b.issue_id
        WHERE b.user_id = ?
    ");
    $countStmt->execute([$user_id]);
    $total = $countStmt->fetchColumn();
    
    sendResponse([
        'success' => true,
        'games' => $games,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

function updateUserProfile() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $allowed_fields = ['email'];
    $updated_fields = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            $updated_fields[] = "$field = ?";
            $params[] = sanitizeInput($input[$field]);
        }
    }
    
    if (empty($updated_fields)) {
        sendError('No valid fields to update', 400);
    }
    
    // Add user_id to params for WHERE clause
    $params[] = $user_id;
    
    $sql = "UPDATE users SET " . implode(', ', $updated_fields) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            sendResponse([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
        } else {
            sendError('Failed to update profile', 500);
        }
    } catch (Exception $e) {
        sendError('Error updating profile: ' . $e->getMessage(), 500);
    }
}
?>