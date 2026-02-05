<?php
// Wallet Handler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

$user_id = authenticateUser();

if ($request_method === 'GET') {
    if (isset($uri_parts[1]) && $uri_parts[1] === 'balance') {
        getUserBalance();
    } elseif (isset($uri_parts[1]) && $uri_parts[1] === 'transactions') {
        getUserTransactions();
    } else {
        sendError('Invalid endpoint', 404);
    }
} elseif ($request_method === 'POST') {
    if (isset($uri_parts[1]) && $uri_parts[1] === 'deposit') {
        depositFunds();
    } elseif (isset($uri_parts[1]) && $uri_parts[1] === 'withdraw') {
        withdrawFunds();
    } else {
        sendError('Invalid endpoint', 404);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

function getUserBalance() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    sendResponse([
        'success' => true,
        'balance' => floatval($user['balance'])
    ]);
}

function getUserTransactions() {
    global $pdo, $user_id;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    $type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : null;
    
    $sql = "SELECT * FROM wallet_transactions WHERE user_id = ?";
    $params = [$user_id];
    
    if ($type) {
        $sql .= " AND type = ?";
        $params[] = $type;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM wallet_transactions WHERE user_id = ?";
    $countParams = [$user_id];
    
    if ($type) {
        $countSql .= " AND type = ?";
        $countParams[] = $type;
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

function depositFunds() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $amount = floatval($input['amount'] ?? 0);
    $method = sanitizeInput($input['method'] ?? 'demo');
    $reference = sanitizeInput($input['reference'] ?? '');
    
    if ($amount <= 0) {
        sendError('Amount must be greater than 0', 400);
    }
    
    // In a real system, you'd process the payment here
    // For demo purposes, we'll just add the funds
    
    // Get current user balance
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    $current_balance = floatval($user['balance']);
    $new_balance = $current_balance + $amount;
    
    // Update user balance
    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->execute([$new_balance, $user_id]);
    
    // Add transaction record
    addTransaction(
        $pdo, 
        $user_id, 
        'deposit', 
        $amount, 
        $current_balance, 
        $new_balance, 
        "Deposit via {$method}"
    );
    
    sendResponse([
        'success' => true,
        'message' => 'Deposit successful',
        'new_balance' => $new_balance,
        'transaction' => [
            'amount' => $amount,
            'type' => 'deposit',
            'reference' => $reference
        ]
    ]);
}

function withdrawFunds() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $amount = floatval($input['amount'] ?? 0);
    $method = sanitizeInput($input['method'] ?? 'demo');
    $reference = sanitizeInput($input['reference'] ?? '');
    
    if ($amount <= 0) {
        sendError('Amount must be greater than 0', 400);
    }
    
    // Get current user balance
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    $current_balance = floatval($user['balance']);
    
    if ($current_balance < $amount) {
        sendError('Insufficient funds', 400);
    }
    
    $new_balance = $current_balance - $amount;
    
    // Update user balance
    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->execute([$new_balance, $user_id]);
    
    // Add transaction record
    addTransaction(
        $pdo, 
        $user_id, 
        'withdrawal', 
        $amount, 
        $current_balance, 
        $new_balance, 
        "Withdrawal via {$method}"
    );
    
    sendResponse([
        'success' => true,
        'message' => 'Withdrawal successful',
        'new_balance' => $new_balance,
        'transaction' => [
            'amount' => $amount,
            'type' => 'withdrawal',
            'reference' => $reference
        ]
    ]);
}
?>