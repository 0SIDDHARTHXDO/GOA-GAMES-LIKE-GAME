-- Database Schema for Wingo Casino Platform
CREATE DATABASE IF NOT EXISTS wingo_casino;
USE wingo_casino;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    vip_level_id INT DEFAULT 1,
    total_bets DECIMAL(10, 2) DEFAULT 0.00,
    total_wins DECIMAL(10, 2) DEFAULT 0.00
);

-- Admins table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'moderator', 'support') DEFAULT 'moderator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- VIP Levels table
CREATE TABLE vip_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    min_balance DECIMAL(10, 2) DEFAULT 0.00,
    bonus_percentage DECIMAL(5, 2) DEFAULT 0.00,
    color VARCHAR(7) DEFAULT '#FFFFFF' -- Hex color for UI
);

-- Insert default VIP levels
INSERT INTO vip_levels (name, min_balance, bonus_percentage, color) VALUES
('Bronze', 0.00, 0.00, '#CD7F32'),
('Silver', 100.00, 2.00, '#C0C0C0'),
('Gold', 500.00, 5.00, '#FFD700'),
('Platinum', 1000.00, 8.00, '#E5E4E2');

-- Wallet transactions table
CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal', 'bet', 'win', 'loss', 'bonus', 'adjustment') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    balance_before DECIMAL(10, 2) NOT NULL,
    balance_after DECIMAL(10, 2) NOT NULL,
    transaction_ref VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Games table
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Game issues (rounds) table
CREATE TABLE game_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    issue_number VARCHAR(20) UNIQUE NOT NULL, -- e.g., "20231231001" (date + sequence)
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    status ENUM('pending', 'active', 'locked', 'completed') DEFAULT 'pending',
    winning_number TINYINT,
    winning_color ENUM('red', 'green', 'violet'),
    winning_size ENUM('big', 'small'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);

-- Bets table
CREATE TABLE bets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    issue_id INT NOT NULL,
    bet_type ENUM('number', 'color', 'size') NOT NULL,
    bet_value VARCHAR(20) NOT NULL, -- e.g., '5', 'red', 'big'
    amount DECIMAL(10, 2) NOT NULL,
    odds DECIMAL(5, 2) NOT NULL,
    potential_win DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'won', 'lost', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (issue_id) REFERENCES game_issues(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_bet (user_id, issue_id, bet_type, bet_value)
);

-- Game results table
CREATE TABLE game_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    winning_number TINYINT NOT NULL,
    winning_color ENUM('red', 'green', 'violet') NOT NULL,
    winning_size ENUM('big', 'small') NOT NULL,
    result_data JSON, -- Additional result details
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES game_issues(id) ON DELETE CASCADE
);

-- Promotions table
CREATE TABLE promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('welcome_bonus', 'deposit_bonus', 'cashback', 'referral', 'vip_bonus') NOT NULL,
    bonus_amount DECIMAL(10, 2),
    bonus_percentage DECIMAL(5, 2),
    min_deposit DECIMAL(10, 2),
    max_bonus DECIMAL(10, 2),
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User promotions (claimed promotions)
CREATE TABLE user_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    promotion_id INT NOT NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('claimed', 'used', 'expired') DEFAULT 'claimed',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE
);

-- System settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'Wingo Casino', 'Name of the site'),
('min_bet_amount', '1.00', 'Minimum bet amount allowed'),
('max_bet_amount', '1000.00', 'Maximum bet amount allowed'),
('game_duration_minutes', '1', 'Duration of each game round in minutes'),
('bet_lock_seconds_before_end', '10', 'Seconds before end time when betting locks'),
('demo_initial_balance', '1000.00', 'Initial balance for demo accounts');