<?php
// Games Handler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if ($request_method === 'GET') {
    if (isset($uri_parts[1]) && $uri_parts[1] === 'current') {
        getCurrentGame();
    } elseif (isset($uri_parts[1]) && $uri_parts[1] === 'recent') {
        getRecentGames();
    } elseif (isset($uri_parts[1]) && $uri_parts[1] === 'settings') {
        getGameSettings();
    } else {
        sendError('Invalid endpoint', 404);
    }
} elseif ($request_method === 'POST' && isset($uri_parts[1]) && $uri_parts[1] === 'simulate') {
    // Only for demo/testing purposes
    simulateNextGame();
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

function getCurrentGame() {
    global $pdo;
    
    // Get the current active game
    $stmt = $pdo->prepare("
        SELECT gi.*, gr.winning_number, gr.winning_color, gr.winning_size
        FROM game_issues gi
        LEFT JOIN game_results gr ON gi.id = gr.issue_id
        WHERE gi.status IN ('pending', 'active', 'locked')
        ORDER BY gi.end_time ASC
        LIMIT 1
    ");
    $current_game = $stmt->fetch();
    
    if (!$current_game) {
        // Create a new game if none exists
        $current_game = createNewGame();
    } else {
        // Check if the game should be locked
        $now = new DateTime();
        $end_time = new DateTime($current_game['end_time']);
        $lock_time = clone $end_time;
        $lock_time->sub(new DateInterval('PT' . BET_LOCK_SECONDS_BEFORE_END . 'S'));
        
        if ($now > $lock_time && $current_game['status'] !== 'completed') {
            // Lock the game for betting
            $stmt = $pdo->prepare("UPDATE game_issues SET status = 'locked' WHERE id = ?");
            $stmt->execute([$current_game['id']]);
            $current_game['status'] = 'locked';
        }
        
        // Check if the game should be completed
        if ($now > $end_time && $current_game['status'] !== 'completed') {
            // Complete the game and generate results
            completeGame($current_game['id']);
            
            // Refresh the game data
            $stmt = $pdo->prepare("
                SELECT gi.*, gr.winning_number, gr.winning_color, gr.winning_size
                FROM game_issues gi
                LEFT JOIN game_results gr ON gi.id = gr.issue_id
                WHERE gi.id = ?
            ");
            $stmt->execute([$current_game['id']]);
            $current_game = $stmt->fetch();
            
            // Create a new game for the next round
            createNewGame();
        }
    }
    
    sendResponse([
        'success' => true,
        'game' => $current_game
    ]);
}

function getRecentGames() {
    global $pdo;
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($limit > 50) $limit = 50; // Max limit
    
    $stmt = $pdo->prepare("
        SELECT gi.*, gr.winning_number, gr.winning_color, gr.winning_size
        FROM game_issues gi
        LEFT JOIN game_results gr ON gi.id = gr.issue_id
        WHERE gi.status = 'completed'
        ORDER BY gi.end_time DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $recent_games = $stmt->fetchAll();
    
    sendResponse([
        'success' => true,
        'games' => $recent_games
    ]);
}

function getGameSettings() {
    global $pdo;
    
    $settings = [
        'min_bet_amount' => MIN_BET_AMOUNT,
        'max_bet_amount' => MAX_BET_AMOUNT,
        'game_duration_minutes' => GAME_DURATION_MINUTES,
        'bet_lock_seconds_before_end' => BET_LOCK_SECONDS_BEFORE_END,
        'odds' => [
            'number' => NUMBER_ODDS,
            'color' => COLOR_ODDS,
            'size' => SIZE_ODDS
        ]
    ];
    
    sendResponse([
        'success' => true,
        'settings' => $settings
    ]);
}

function createNewGame() {
    global $pdo;
    
    $issue_number = generateIssueNumber();
    $start_time = date('Y-m-d H:i:s');
    $end_time = getGameEndTime($start_time);
    
    $stmt = $pdo->prepare("
        INSERT INTO game_issues (game_id, issue_number, start_time, end_time, status)
        VALUES (1, ?, ?, ?, 'active')
    ");
    $stmt->execute([1, $issue_number, $start_time, $end_time]);
    
    $game_id = $pdo->lastInsertId();
    
    // Return the newly created game
    $stmt = $pdo->prepare("
        SELECT gi.*, gr.winning_number, gr.winning_color, gr.winning_size
        FROM game_issues gi
        LEFT JOIN game_results gr ON gi.id = gr.issue_id
        WHERE gi.id = ?
    ");
    $stmt->execute([$game_id]);
    return $stmt->fetch();
}

function completeGame($issue_id) {
    global $pdo;
    
    // Generate random winning number
    $winning_number = getWinningNumber();
    $winning_color = getWinningColor($winning_number);
    $winning_size = getWinningSize($winning_number);
    
    // Update the game issue with results
    $stmt = $pdo->prepare("
        UPDATE game_issues 
        SET status = 'completed', 
            winning_number = ?, 
            winning_color = ?, 
            winning_size = ?
        WHERE id = ?
    ");
    $stmt->execute([$winning_number, $winning_color, $winning_size, $issue_id]);
    
    // Insert into game results table
    $stmt = $pdo->prepare("
        INSERT INTO game_results (issue_id, winning_number, winning_color, winning_size, result_data)
        VALUES (?, ?, ?, ?, ?)
    ");
    $result_data = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'algorithm' => 'random'
    ]);
    $stmt->execute([$issue_id, $winning_number, $winning_color, $winning_size, $result_data]);
    
    // Process all bets for this issue
    processBetsForIssue($pdo, $issue_id, $winning_number, $winning_color, $winning_size);
}

function processBetsForIssue($pdo, $issue_id, $winning_number, $winning_color, $winning_size) {
    // Get all bets for this issue
    $stmt = $pdo->prepare("SELECT * FROM bets WHERE issue_id = ?");
    $stmt->execute([$issue_id]);
    $bets = $stmt->fetchAll();
    
    foreach ($bets as $bet) {
        $result = calculateBetResult($bet['bet_value'], $bet['bet_type'], $winning_number, $winning_color, $winning_size);
        
        // Update bet status
        $stmt = $pdo->prepare("UPDATE bets SET status = ? WHERE id = ?");
        $stmt->execute([$result, $bet['id']]);
        
        if ($result === 'won') {
            // Calculate winnings
            $winnings = $bet['amount'] * $bet['odds'];
            
            // Update user balance
            $user_stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $user_stmt->execute([$bet['user_id']]);
            $user = $user_stmt->fetch();
            
            if ($user) {
                $new_balance = $user['balance'] + $winnings;
                
                $update_stmt = $pdo->prepare("UPDATE users SET balance = ?, total_wins = total_wins + ? WHERE id = ?");
                $update_stmt->execute([$new_balance, $winnings, $bet['user_id']]);
                
                // Add transaction record
                addTransaction(
                    $pdo,
                    $bet['user_id'],
                    'win',
                    $winnings,
                    $user['balance'],
                    $new_balance,
                    "Win from game issue {$issue_id}"
                );
            }
        }
    }
}

function simulateNextGame() {
    global $pdo;
    
    // Only for demo purposes - create a new game and complete it immediately
    $current_game = createNewGame();
    
    if ($current_game) {
        completeGame($current_game['id']);
        
        // Refresh the game data
        $stmt = $pdo->prepare("
            SELECT gi.*, gr.winning_number, gr.winning_color, gr.winning_size
            FROM game_issues gi
            LEFT JOIN game_results gr ON gi.id = gr.issue_id
            WHERE gi.id = ?
        ");
        $stmt->execute([$current_game['id']]);
        $completed_game = $stmt->fetch();
        
        sendResponse([
            'success' => true,
            'message' => 'Game simulated successfully',
            'game' => $completed_game
        ]);
    } else {
        sendError('Failed to simulate game', 500);
    }
}
?>