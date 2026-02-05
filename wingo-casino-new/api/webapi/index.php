<?php
// Main API Entry Point
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include configuration and helper functions
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// Initialize database connection
$pdo = getDatabaseConnection();

// Parse the request
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri_parts = explode('/', trim($request_uri, '/'));

// Remove the base path (api/webapi)
if ($uri_parts[0] === 'api' && $uri_parts[1] === 'webapi') {
    array_shift($uri_parts); // Remove 'api'
    array_shift($uri_parts); // Remove 'webapi'
}

if (empty($uri_parts[0])) {
    $uri_parts[0] = 'home';
}

$route = $uri_parts[0];

// Route to the appropriate handler
switch ($route) {
    case 'auth':
        require_once __DIR__ . '/auth/auth_handler.php';
        break;
    case 'user':
        require_once __DIR__ . '/user/user_handler.php';
        break;
    case 'wallet':
        require_once __DIR__ . '/wallet/wallet_handler.php';
        break;
    case 'games':
        require_once __DIR__ . '/games/games_handler.php';
        break;
    case 'bet':
        require_once __DIR__ . '/bet/bet_handler.php';
        break;
    case 'promotion':
        require_once __DIR__ . '/promotion/promotion_handler.php';
        break;
    case 'vip':
        require_once __DIR__ . '/vip/vip_handler.php';
        break;
    case 'system':
        require_once __DIR__ . '/system/system_handler.php';
        break;
    case 'admin':
        require_once __DIR__ . '/admin/admin_handler.php';
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
        exit;
}
?>