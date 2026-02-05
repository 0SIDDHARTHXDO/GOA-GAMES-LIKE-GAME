<?php
// Admin Handler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

authenticateAdmin();

if ($request_method === 'GET') {
    if (isset($uri_parts[1])) {
        switch ($uri_parts[1]) {
            case 'dashboard':
                getAdminDashboard();
                break;
            case 'users':
                getUsers();
                break;
            case 'games':
                getGames();
                break;
            case 'bets':
                getBets();
                break;
            case 'wallet':
                getWalletTransactions();
                break;
            case 'promotions':
                getPromotions();
                break;
            case 'settings':
                getAdminSettings();
                break;
            default:
                sendError('Invalid endpoint', 404);
        }
    } else {
        sendError('Invalid endpoint', 404);
    }
} elseif ($request_method === 'POST') {
    if (isset($uri_parts[1])) {
        switch ($uri_parts[1]) {
            case 'users':
                if (isset($uri_parts[2]) && $uri_parts[2] === 'create') {
                    createUser();
                } else {
                    sendError('Invalid endpoint', 404);
                }
                break;
            case 'promotions':
                createPromotion();
                break;
            case 'games':
                if (isset($uri_parts[2]) && $uri_parts[2] === 'create') {
                    createGame();
                } else {
                    sendError('Invalid endpoint', 404);
                }
                break;
            default:
                sendError('Invalid endpoint', 404);
        }
    } else {
        sendError('Invalid endpoint', 404);
    }
} elseif ($request_method === 'PUT' && isset($uri_parts[1])) {
    switch ($uri_parts[1]) {
        case 'users':
            updateUser();
            break;
        case 'games':
            updateGame();
            break;
        case 'promotions':
            updatePromotion();
            break;
        case 'settings':
            updateSettings();
            break;
        default:
            sendError('Invalid endpoint', 404);
    }
} elseif ($request_method === 'DELETE' && isset($uri_parts[1])) {
    switch ($uri_parts[1]) {
        case 'promotions':
            deletePromotion();
            break;
        default:
            sendError('Invalid endpoint', 404);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

function getAdminDashboard() {
    global $pdo;
    
    // Get summary statistics
    $stats = [];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $stmt->fetch()['count'];
    
    // Active users (logged in last 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['active_users'] = $stmt->fetch()['count'];
    
    // Total balance across all users
    $stmt = $pdo->query("SELECT SUM(balance) as total FROM users");
    $stats['total_platform_balance'] = floatval($stmt->fetch()['total']);
    
    // Total bets placed
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bets");
    $stats['total_bets'] = $stmt->fetch()['count'];
    
    // Total bet amount
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM bets");
    $stats['total_bet_amount'] = floatval($stmt->fetch()['total']);
    
    // Total wins paid
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE type = 'win'");
    $stats['total_wins_paid'] = floatval($stmt->fetch()['total']);
    
    sendResponse([
        'success' => true,
        'dashboard' => $stats
    ]);
}

function getUsers() {
    global $pdo;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    
    $sql = "SELECT id, username, email, balance, is_active, created_at, updated_at FROM users";
    $params = [];
    
    if ($search) {
        $sql .= " WHERE username LIKE ? OR email LIKE ?";
        $params = ["%{$search}%", "%{$search}%"];
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM users";
    $countParams = [];
    
    if ($search) {
        $countSql .= " WHERE username LIKE ? OR email LIKE ?";
        $countParams = ["%{$search}%", "%{$search}%"];
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();
    
    sendResponse([
        'success' => true,
        'users' => $users,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

function getGames() {
    global $pdo;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT gi.*, gr.winning_number, gr.winning_color, gr.winning_size
        FROM game_issues gi
        LEFT JOIN game_results gr ON gi.id = gr.issue_id
        ORDER BY gi.end_time DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $games = $stmt->fetchAll();
    
    // Get total count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM game_issues");
    $countStmt->execute();
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

function getBets() {
    global $pdo;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    $sql = "
        SELECT b.*, u.username, gi.issue_number, gi.status as issue_status
        FROM bets b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN game_issues gi ON b.issue_id = gi.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($status) {
        $sql .= " AND b.status = ?";
        $params[] = $status;
    }
    
    if ($user_id > 0) {
        $sql .= " AND b.user_id = ?";
        $params[] = $user_id;
    }
    
    $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bets = $stmt->fetchAll();
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM bets b WHERE 1=1";
    $countParams = [];
    
    if ($status) {
        $countSql .= " AND b.status = ?";
        $countParams[] = $status;
    }
    
    if ($user_id > 0) {
        $countSql .= " AND b.user_id = ?";
        $countParams[] = $user_id;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
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

function getWalletTransactions() {
    global $pdo;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    $type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    $sql = "
        SELECT wt.*, u.username
        FROM wallet_transactions wt
        LEFT JOIN users u ON wt.user_id = u.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($type) {
        $sql .= " AND wt.type = ?";
        $params[] = $type;
    }
    
    if ($user_id > 0) {
        $sql .= " AND wt.user_id = ?";
        $params[] = $user_id;
    }
    
    $sql .= " ORDER BY wt.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM wallet_transactions wt WHERE 1=1";
    $countParams = [];
    
    if ($type) {
        $countSql .= " AND wt.type = ?";
        $countParams[] = $type;
    }
    
    if ($user_id > 0) {
        $countSql .= " AND wt.user_id = ?";
        $countParams[] = $user_id;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();
    
    sendResponse([
        'success' => true,
        'transactions' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

function getPromotions() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM promotions ORDER BY created_at DESC");
    $stmt->execute();
    $promotions = $stmt->fetchAll();
    
    sendResponse([
        'success' => true,
        'promotions' => $promotions
    ]);
}

function getAdminSettings() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT setting_key, setting_value, description FROM system_settings ORDER BY setting_key");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    sendResponse([
        'success' => true,
        'settings' => $settings
    ]);
}

function createUser() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $username = sanitizeInput($input['username'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $initial_balance = floatval($input['initial_balance'] ?? DEMO_INITIAL_BALANCE);
    
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
    
    try {
        $pdo->beginTransaction();
        
        // Create user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, balance) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password_hash, $initial_balance]);
        $user_id = $pdo->lastInsertId();
        
        // Add initial balance transaction
        addTransaction($pdo, $user_id, 'deposit', $initial_balance, 0, $initial_balance, 'Admin created account with initial balance');
        
        $pdo->commit();
        
        sendResponse([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $user_id
        ]);
    } catch (Exception $e) {
        $pdo->rollback();
        sendError('Failed to create user: ' . $e->getMessage(), 500);
    }
}

function updateUser() {
    global $pdo;
    
    $user_id = intval($uri_parts[2] ?? 0);
    if ($user_id <= 0) {
        sendError('User ID is required', 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $allowed_fields = ['username', 'email', 'is_active', 'balance'];
    $updated_fields = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            if ($field === 'balance') {
                $updated_fields[] = "$field = ?";
                $params[] = floatval($input[$field]);
            } else {
                $updated_fields[] = "$field = ?";
                $params[] = sanitizeInput($input[$field]);
            }
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
                'message' => 'User updated successfully'
            ]);
        } else {
            sendError('Failed to update user', 500);
        }
    } catch (Exception $e) {
        sendError('Error updating user: ' . $e->getMessage(), 500);
    }
}

function createPromotion() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $title = sanitizeInput($input['title'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $type = sanitizeInput($input['type'] ?? '');
    $bonus_amount = floatval($input['bonus_amount'] ?? 0);
    $bonus_percentage = floatval($input['bonus_percentage'] ?? 0);
    $min_deposit = floatval($input['min_deposit'] ?? 0);
    $max_bonus = floatval($input['max_bonus'] ?? 0);
    $start_date = sanitizeInput($input['start_date'] ?? '');
    $end_date = sanitizeInput($input['end_date'] ?? '');
    $is_active = boolval($input['is_active'] ?? true);
    
    if (empty($title) || empty($type)) {
        sendError('Title and type are required', 400);
    }
    
    if (!in_array($type, ['welcome_bonus', 'deposit_bonus', 'cashback', 'referral', 'vip_bonus'])) {
        sendError('Invalid promotion type', 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO promotions (title, description, type, bonus_amount, bonus_percentage, min_deposit, max_bonus, start_date, end_date, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $title, $description, $type, $bonus_amount, $bonus_percentage, $min_deposit, 
        $max_bonus, $start_date, $end_date, $is_active
    ]);
    
    if ($result) {
        sendResponse([
            'success' => true,
            'message' => 'Promotion created successfully',
            'promotion_id' => $pdo->lastInsertId()
        ]);
    } else {
        sendError('Failed to create promotion', 500);
    }
}

function updatePromotion() {
    global $pdo;
    
    $promotion_id = intval($uri_parts[2] ?? 0);
    if ($promotion_id <= 0) {
        sendError('Promotion ID is required', 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $allowed_fields = ['title', 'description', 'type', 'bonus_amount', 'bonus_percentage', 'min_deposit', 'max_bonus', 'start_date', 'end_date', 'is_active'];
    $updated_fields = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            if (in_array($field, ['bonus_amount', 'bonus_percentage', 'min_deposit', 'max_bonus'])) {
                $updated_fields[] = "$field = ?";
                $params[] = floatval($input[$field]);
            } elseif ($field === 'is_active') {
                $updated_fields[] = "$field = ?";
                $params[] = boolval($input[$field]);
            } else {
                $updated_fields[] = "$field = ?";
                $params[] = sanitizeInput($input[$field]);
            }
        }
    }
    
    if (empty($updated_fields)) {
        sendError('No valid fields to update', 400);
    }
    
    // Add promotion_id to params for WHERE clause
    $params[] = $promotion_id;
    
    $sql = "UPDATE promotions SET " . implode(', ', $updated_fields) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            sendResponse([
                'success' => true,
                'message' => 'Promotion updated successfully'
            ]);
        } else {
            sendError('Failed to update promotion', 500);
        }
    } catch (Exception $e) {
        sendError('Error updating promotion: ' . $e->getMessage(), 500);
    }
}

function deletePromotion() {
    global $pdo;
    
    $promotion_id = intval($uri_parts[2] ?? 0);
    if ($promotion_id <= 0) {
        sendError('Promotion ID is required', 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
    $result = $stmt->execute([$promotion_id]);
    
    if ($result) {
        sendResponse([
            'success' => true,
            'message' => 'Promotion deleted successfully'
        ]);
    } else {
        sendError('Failed to delete promotion', 500);
    }
}

function createGame() {
    // This would typically be handled by the games handler
    // Admins can create special games or events
    global $pdo;
    
    // For now, just return a success message
    sendResponse([
        'success' => true,
        'message' => 'Game creation endpoint - implementation depends on specific requirements'
    ]);
}

function updateGame() {
    global $pdo;
    
    $game_id = intval($uri_parts[2] ?? 0);
    if ($game_id <= 0) {
        sendError('Game ID is required', 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    // Only allow updating specific fields for security
    $allowed_fields = ['status'];
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
    
    // Add game_id to params for WHERE clause
    $params[] = $game_id;
    
    $sql = "UPDATE game_issues SET " . implode(', ', $updated_fields) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            sendResponse([
                'success' => true,
                'message' => 'Game updated successfully'
            ]);
        } else {
            sendError('Failed to update game', 500);
        }
    } catch (Exception $e) {
        sendError('Error updating game: ' . $e->getMessage(), 500);
    }
}

function updateSettings() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    if (!isset($input['setting_key']) || !isset($input['setting_value'])) {
        sendError('setting_key and setting_value are required', 400);
    }
    
    $setting_key = sanitizeInput($input['setting_key']);
    $setting_value = $input['setting_value'];
    
    // Prevent updating critical security settings
    $forbidden_keys = ['jwt_secret', 'db_password', 'encryption_key'];
    if (in_array(strtolower($setting_key), $forbidden_keys)) {
        sendError('Cannot update this setting', 403);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if setting exists
        $stmt = $pdo->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$setting_key]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing setting
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $result = $stmt->execute([$setting_value, $setting_key]);
        } else {
            // Insert new setting
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $result = $stmt->execute([$setting_key, $setting_value]);
        }
        
        $pdo->commit();
        
        if ($result) {
            sendResponse([
                'success' => true,
                'message' => 'Setting updated successfully'
            ]);
        } else {
            sendError('Failed to update setting', 500);
        }
    } catch (Exception $e) {
        $pdo->rollback();
        sendError('Error updating setting: ' . $e->getMessage(), 500);
    }
}
?>