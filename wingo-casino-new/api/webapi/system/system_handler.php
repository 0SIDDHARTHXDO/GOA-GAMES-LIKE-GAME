<?php
// System Handler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if ($request_method === 'GET') {
    if (isset($uri_parts[1]) && $uri_parts[1] === 'settings') {
        getSystemSettings();
    } else {
        sendError('Invalid endpoint', 404);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

function getSystemSettings() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT setting_key, setting_value, description FROM system_settings WHERE setting_key IN ('site_name', 'min_bet_amount', 'max_bet_amount', 'game_duration_minutes', 'bet_lock_seconds_before_end', 'demo_initial_balance')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Convert numeric values
    $settings['min_bet_amount'] = floatval($settings['min_bet_amount'] ?? MIN_BET_AMOUNT);
    $settings['max_bet_amount'] = floatval($settings['max_bet_amount'] ?? MAX_BET_AMOUNT);
    $settings['game_duration_minutes'] = intval($settings['game_duration_minutes'] ?? GAME_DURATION_MINUTES);
    $settings['bet_lock_seconds_before_end'] = intval($settings['bet_lock_seconds_before_end'] ?? BET_LOCK_SECONDS_BEFORE_END);
    $settings['demo_initial_balance'] = floatval($settings['demo_initial_balance'] ?? DEMO_INITIAL_BALANCE);
    
    sendResponse([
        'success' => true,
        'settings' => $settings
    ]);
}
?>