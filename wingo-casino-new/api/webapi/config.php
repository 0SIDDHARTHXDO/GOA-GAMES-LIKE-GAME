<?php
// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'wingo_casino');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

// JWT Configuration
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-super-secret-jwt-key-change-in-production');

// Site Configuration
define('SITE_NAME', 'Wingo Casino');
define('MIN_BET_AMOUNT', 1.00);
define('MAX_BET_AMOUNT', 1000.00);
define('GAME_DURATION_MINUTES', 1);
define('BET_LOCK_SECONDS_BEFORE_END', 10);
define('DEMO_INITIAL_BALANCE', 1000.00);

// Odds Configuration
define('NUMBER_ODDS', 9.00); // 9:1 odds for guessing exact number
define('COLOR_ODDS', 2.00);  // 2:1 odds for color bet
define('SIZE_ODDS', 2.00);   // 2:1 odds for big/small bet

function getDatabaseConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}
?>