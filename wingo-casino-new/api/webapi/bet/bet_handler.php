<?php
// Bet Handler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

$user_id = authenticateUser();

if ($request_method === 'POST') {
    placeBet();
} elseif ($request_method === 'GET') {
    if (isset($uri_parts[1]) && $uri_parts[1] === 'my-bets') {
        getMyBets();
    } else {
        sendError('Invalid endpoint', 404);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

function placeBet() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $bet_type = sanitizeInput($input['bet_type'] ?? '');
    $bet_value = sanitizeInput($input['bet_value'] ?? '');
    $amount = floatval($input['amount'] ?? 0);
    $issue_id = intval($input['issue_id'] ?? 0);
    
    // Validate input
    if (empty($bet_type) || empty($bet_value) || $amount <= 0 || $issue_id <= 0) {
        sendError('Bet type, value, amount, and issue ID are required', 400);
    }
    
    // Validate bet type
    if (!in_array($bet_type, ['number', 'color', 'size'])) {
        sendError('Invalid bet type. Must be number, color, or size', 400);
    }
    
    // Validate bet value based on type
    switch ($bet_type) {
        case 'number':
            if (!preg_match('/^[0-9]$/', $bet_value)) {
                sendError('Invalid number bet. Must be between 0-9', 400);
            }
            $odds = NUMBER_ODDS;
            break;
        case 'color':
            if (!in_array(strtolower($bet_value), ['red', 'green', 'violet'])) {
                sendError('Invalid color bet. Must be red, green, or violet', 400);
            }
            $odds = COLOR_ODDS;
            break;
        case 'size':
            if (!in_array(strtolower($bet_value), ['big', 'small'])) {
                sendError('Invalid size bet. Must be big or small', 400);
            }
            $odds = SIZE_ODDS;
            break;
        default:
            sendError('Invalid bet type', 400);
    }
    
    // Validate amount
    if ($amount < MIN_BET_AMOUNT) {
        sendError("Minimum bet amount is " . MIN_BET_AMOUNT, 400);
    }
    
    if ($amount > MAX_BET_AMOUNT) {
        sendError("Maximum bet amount is " . MAX_BET_AMOUNT, 400);
    }
    
    // Get current game issue
    $stmt = $pdo->prepare("SELECT * FROM game_issues WHERE id = ?");
    $stmt->execute([$issue_id]);
    $issue = $stmt->fetch();
    
    if (!$issue) {
        sendError('Invalid game issue', 400);
    }
    
    // Check if betting is still allowed (not locked and not completed)
    if ($issue['status'] === 'locked' || $issue['status'] === 'completed') {
        sendError('Betting is closed for this round', 400);
    }
    
    // Check if user has sufficient balance
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || $user['balance'] < $amount) {
        sendError('Insufficient balance', 400);
    }
    
    // Check for duplicate bet (same user, same issue, same type, same value)
    $stmt = $pdo->prepare("
        SELECT id FROM bets 
        WHERE user_id = ? AND issue_id = ? AND bet_type = ? AND bet_value = ?
    ");
    $stmt->execute([$user_id, $issue_id, $bet_type, $bet_value]);
    $duplicate = $stmt->fetch();
    
    if ($duplicate) {
        sendError('Duplicate bet detected. You already placed this bet.', 400);
    }
    
    // Start transaction
    try {
        $pdo->beginTransaction();
        
        // Deduct amount from user balance
        $new_balance = $user['balance'] - $amount;
        $stmt = $pdo->prepare("UPDATE users SET balance = ?, total_bets = total_bets + ? WHERE id = ?");
        $stmt->execute([$new_balance, $amount, $user_id]);
        
        // Add transaction record for bet
        addTransaction(
            $pdo,
            $user_id,
            'bet',
            $amount,
            $user['balance'],
            $new_balance,
            "Bet on issue {$issue['issue_number']} - {$bet_type}:{$bet_value}"
        );
        
        // Insert the bet
        $potential_win = $amount * $odds;
        $stmt = $pdo->prepare("
            INSERT INTO bets (user_id, issue_id, bet_type, bet_value, amount, odds, potential_win, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$user_id, $issue_id, $bet_type, $bet_value, $amount, $odds, $potential_win]);
        
        $bet_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        sendResponse([
            'success' => true,
            'message' => 'Bet placed successfully',
            'bet' => [
                'id' => $bet_id,
                'user_id' => $user_id,
                'issue_id' => $issue_id,
                'bet_type' => $bet_type,
                'bet_value' => $bet_value,
                'amount' => $amount,
                'odds' => $odds,
                'potential_win' => $potential_win,
                'status' => 'pending'
            ],
            'new_balance' => $new_balance
        ]);
    } catch (Exception $e) {
        $pdo->rollback();
        sendError('Failed to place bet: ' . $e->getMessage(), 500);
    }
}

function getMyBets() {
    global $pdo, $user_id;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
    $issue_id = isset($_GET['issue_id']) ? intval($_GET['issue_id']) : null;
    
    $sql = "
        SELECT b.*, gi.issue_number, gi.status as issue_status, 
               gi.winning_number, gi.winning_color, gi.winning_size
        FROM bets b
        LEFT JOIN game_issues gi ON b.issue_id = gi.id
        WHERE b.user_id = ?
    ";
    $params = [$user_id];
    
    if ($status) {
        $sql .= " AND b.status = ?";
        $params[] = $status;
    }
    
    if ($issue_id) {
        $sql .= " AND b.issue_id = ?";
        $params[] = $issue_id;
    }
    
    $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bets = $stmt->fetchAll();
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM bets WHERE user_id = ?";
    $countParams = [$user_id];
    
    if ($status) {
        $countSql .= " AND status = ?";
        $countParams[] = $status;
    }
    
    if ($issue_id) {
        $countSql .= " AND issue_id = ?";
        $countParams[] = $issue_id;
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
?>