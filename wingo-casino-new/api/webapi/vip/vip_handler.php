<?php
// VIP Handler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if ($request_method === 'GET') {
    if (isset($uri_parts[1]) && $uri_parts[1] === 'levels') {
        getVipLevels();
    } elseif (isset($uri_parts[1]) && $uri_parts[1] === 'my-status') {
        getMyVipStatus();
    } else {
        sendError('Invalid endpoint', 404);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

function getVipLevels() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM vip_levels ORDER BY min_balance ASC");
    $stmt->execute();
    $vip_levels = $stmt->fetchAll();
    
    sendResponse([
        'success' => true,
        'vip_levels' => $vip_levels
    ]);
}

function getMyVipStatus() {
    global $user_id;
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.balance, u.total_wins, u.vip_level_id, 
               v.name as vip_name, v.min_balance, v.bonus_percentage, v.color
        FROM users u
        LEFT JOIN vip_levels v ON u.vip_level_id = v.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_vip_info = $stmt->fetch();
    
    if (!$user_vip_info) {
        sendError('User not found', 404);
    }
    
    // Determine eligible VIP levels based on current balance
    $stmt = $pdo->prepare("SELECT * FROM vip_levels WHERE min_balance <= ? ORDER BY min_balance DESC LIMIT 1");
    $stmt->execute([$user_vip_info['balance']]);
    $eligible_vip = $stmt->fetch();
    
    if ($eligible_vip && $eligible_vip['id'] > $user_vip_info['vip_level_id']) {
        // User qualifies for a higher VIP level
        $user_vip_info['upgrade_available'] = true;
        $user_vip_info['eligible_vip'] = $eligible_vip;
    } else {
        $user_vip_info['upgrade_available'] = false;
    }
    
    sendResponse([
        'success' => true,
        'user_vip_info' => $user_vip_info
    ]);
}
?>