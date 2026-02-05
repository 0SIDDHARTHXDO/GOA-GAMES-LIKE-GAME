<?php
// Promotion Handler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if ($request_method === 'GET') {
    if (isset($uri_parts[1]) && $uri_parts[1] === 'available') {
        getAvailablePromotions();
    } elseif (isset($uri_parts[1]) && $uri_parts[1] === 'my-promotions') {
        getMyPromotions();
    } else {
        sendError('Invalid endpoint', 404);
    }
} elseif ($request_method === 'POST' && isset($uri_parts[1]) && $uri_parts[1] === 'claim') {
    claimPromotion();
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

function getAvailablePromotions() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM promotions 
        WHERE is_active = 1 
        AND (start_date IS NULL OR start_date <= CURDATE()) 
        AND (end_date IS NULL OR end_date >= CURDATE())
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $promotions = $stmt->fetchAll();
    
    sendResponse([
        'success' => true,
        'promotions' => $promotions
    ]);
}

function getMyPromotions() {
    global $user_id;
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT up.*, p.title, p.description, p.type, p.bonus_amount, p.bonus_percentage, 
               p.min_deposit, p.max_bonus, p.start_date, p.end_date
        FROM user_promotions up
        JOIN promotions p ON up.promotion_id = p.id
        WHERE up.user_id = ?
        ORDER BY up.claimed_at DESC
    ");
    $stmt->execute([$user_id]);
    $promotions = $stmt->fetchAll();
    
    sendResponse([
        'success' => true,
        'promotions' => $promotions
    ]);
}

function claimPromotion() {
    global $user_id;
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Invalid JSON input', 400);
    }
    
    $promotion_id = intval($input['promotion_id'] ?? 0);
    
    if ($promotion_id <= 0) {
        sendError('Promotion ID is required', 400);
    }
    
    // Check if promotion exists and is active
    $stmt = $pdo->prepare("
        SELECT * FROM promotions 
        WHERE id = ? 
        AND is_active = 1 
        AND (start_date IS NULL OR start_date <= CURDATE()) 
        AND (end_date IS NULL OR end_date >= CURDATE())
    ");
    $stmt->execute([$promotion_id]);
    $promotion = $stmt->fetch();
    
    if (!$promotion) {
        sendError('Promotion not found or not available', 404);
    }
    
    // Check if user already claimed this promotion
    $stmt = $pdo->prepare("SELECT id FROM user_promotions WHERE user_id = ? AND promotion_id = ?");
    $stmt->execute([$user_id, $promotion_id]);
    $already_claimed = $stmt->fetch();
    
    if ($already_claimed) {
        sendError('You have already claimed this promotion', 400);
    }
    
    // Check minimum deposit requirement if applicable
    if ($promotion['min_deposit'] > 0) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_deposits 
            FROM wallet_transactions 
            WHERE user_id = ? AND type = 'deposit'
        ");
        $stmt->execute([$user_id]);
        $deposits_result = $stmt->fetch();
        $total_deposits = floatval($deposits_result['total_deposits']);
        
        if ($total_deposits < $promotion['min_deposit']) {
            sendError("You need to deposit at least {$promotion['min_deposit']} to claim this promotion", 400);
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mark promotion as claimed
        $stmt = $pdo->prepare("INSERT INTO user_promotions (user_id, promotion_id, status) VALUES (?, ?, 'claimed')");
        $stmt->execute([$user_id, $promotion_id]);
        
        // Calculate bonus amount
        $bonus_amount = 0;
        
        if ($promotion['type'] === 'deposit_bonus' && $promotion['bonus_percentage'] > 0) {
            // For deposit bonuses, we typically apply it to the last deposit
            // For demo purposes, we'll just give the bonus amount as specified
            $bonus_amount = $promotion['bonus_amount'] ?: ($total_deposits * $promotion['bonus_percentage'] / 100);
            if ($promotion['max_bonus'] > 0 && $bonus_amount > $promotion['max_bonus']) {
                $bonus_amount = $promotion['max_bonus'];
            }
        } else {
            $bonus_amount = $promotion['bonus_amount'] ?: 0;
        }
        
        // Add bonus to user balance if applicable
        if ($bonus_amount > 0) {
            // Get current user balance
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                $new_balance = $user['balance'] + $bonus_amount;
                
                // Update user balance
                $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt->execute([$new_balance, $user_id]);
                
                // Add transaction record
                addTransaction(
                    $pdo,
                    $user_id,
                    'bonus',
                    $bonus_amount,
                    $user['balance'],
                    $new_balance,
                    "Bonus from promotion: {$promotion['title']}"
                );
            }
        }
        
        $pdo->commit();
        
        sendResponse([
            'success' => true,
            'message' => 'Promotion claimed successfully',
            'bonus_added' => $bonus_amount,
            'new_balance' => $new_balance ?? null
        ]);
    } catch (Exception $e) {
        $pdo->rollback();
        sendError('Failed to claim promotion: ' . $e->getMessage(), 500);
    }
}
?>