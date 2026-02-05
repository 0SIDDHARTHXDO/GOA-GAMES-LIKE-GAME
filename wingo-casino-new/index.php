<?php
// Unified index.php for Render.com deployment

// Enable CORS for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get the request URI
$requestUri = $_SERVER['REQUEST_URI'];

// Define API routes
$apiPaths = [
    '/api/webapi/',
    '/api/webapi/auth/',
    '/api/webapi/user/',
    '/api/webapi/wallet/',
    '/api/webapi/games/',
    '/api/webapi/bet/',
    '/api/webapi/promotion/',
    '/api/webapi/vip/',
    '/api/webapi/system/',
    '/api/webapi/admin/'
];

$isApiRequest = false;
foreach ($apiPaths as $path) {
    if (strpos($requestUri, $path) === 0) {
        $isApiRequest = true;
        break;
    }
}

if ($isApiRequest) {
    // Handle API requests
    // Strip the base path to get the actual API path
    $basePath = '/api/webapi';
    $relativePath = substr($requestUri, strlen($basePath));
    
    // Set up $_SERVER variables to match the expected path
    $_SERVER['REQUEST_URI'] = $basePath . $relativePath;
    
    // Include the main API entry point
    $apiEntryPoint = __DIR__ . '/api/webapi/index.php';
    if (file_exists($apiEntryPoints)) {
        require_once $apiEntryPoint;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
    }
} else {
    // Handle static file requests
    // Remove query string if present
    $cleanUri = strtok($requestUri, '?');
    
    // Map the clean URI to actual file paths
    if ($cleanUri === '/') {
        $filePath = __DIR__ . '/frontend/index.html';
    } elseif (strpos($cleanUri, '/admin/') === 0) {
        $filePath = __DIR__ . $cleanUri;
    } elseif (strpos($cleanUri, '/frontend/') === 0) {
        $filePath = __DIR__ . $cleanUri;
    } elseif (strpos($cleanUri, '/assets/') === 0) {
        $filePath = __DIR__ . $cleanUri;
    } else {
        // Default to frontend if path doesn't match known patterns
        $filePath = __DIR__ . '/frontend' . $cleanUri;
        
        // If the file doesn't exist, try the root
        if (!file_exists($filePath)) {
            $filePath = __DIR__ . $cleanUri;
        }
        
        // If still doesn't exist, default to index.html
        if (!file_exists($filePath)) {
            $filePath = __DIR__ . '/frontend/index.html';
        }
    }

    // Check if the file exists and serve it
    if (file_exists($filePath)) {
        $mimeType = getMimeType($filePath);
        header('Content-Type: ' . $mimeType);
        readfile($filePath);
    } else {
        // If no file found, serve the frontend index (for SPA routing)
        header('Content-Type: text/html');
        readfile(__DIR__ . '/frontend/index.html');
    }
}

// Helper function to determine MIME type
function getMimeType($filePath) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'html' => 'text/html',
        'htm' => 'text/html',
        'js' => 'application/javascript',
        'css' => 'text/css',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject'
    ];
    
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}
?>