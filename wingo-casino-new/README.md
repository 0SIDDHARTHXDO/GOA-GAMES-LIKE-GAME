# Wingo Casino Platform

A complete Wingo / Color Prediction / Lottery-style gaming platform built with PHP, MySQL, and JavaScript.

## 🎯 Features

- **Wingo Game**: Real-time 1-minute prediction game
- **User Management**: Registration, login, profile management
- **Wallet System**: Virtual balance, deposits, withdrawals, transaction history
- **Betting System**: Bet on numbers, colors, and sizes with different odds
- **VIP Program**: Tiered membership with benefits
- **Promotions**: Welcome bonuses, deposit bonuses, cashbacks
- **Admin Panel**: Full control over users, games, and settings
- **Responsive UI**: Mobile-first design with Bootstrap

## 🛠️ Tech Stack

- **Backend**: PHP (REST API)
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript
- **Framework**: Bootstrap 5
- **Authentication**: JWT-based token system

## 📁 Folder Structure

```
/api/
  /webapi/
    auth/
    user/
    wallet/
    games/
    bet/
    promotion/
    vip/
    system/
    admin/
/admin/
  login.html
  dashboard.html
  users.html
  wallet.html
  games.html
  results.html
  promotions.html
  settings.html
/frontend/
  index.html
  login.html
  register.html
  home.html
  wallet.html
  game-wingo.html
  history.html
  vip.html
  profile.html
/assets/
  /css/
  /js/
  /images/
/database/
  schema.sql
```

## 🚀 Installation

1. Clone the repository
2. Import the database schema from `/database/schema.sql`
3. Configure your database connection in `/api/webapi/config.php`
4. Serve the application using PHP built-in server:
   ```
   php -S localhost:8000
   ```

## 🔐 Database Configuration

Update the database configuration in `/api/webapi/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'wingo_casino');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_PORT', '3306');
```

## 🎲 Game Rules

- Each game lasts 1 minute
- Players can bet on:
  - Exact number (0-9) - 9:1 odds
  - Color (Red, Green, Violet) - 2:1 odds
  - Size (Big 5-9, Small 0-4) - 2:1 odds
- Betting closes 10 seconds before game ends
- Results are generated server-side with provably fair algorithm

## 🏦 Virtual Currency

- Demo mode with virtual credits
- Initial balance: $1000 for new users
- No real money transactions

## 🛡️ Security

- JWT-based authentication
- Input validation and sanitization
- Prepared statements to prevent SQL injection
- Session management
- Rate limiting (to be implemented)

## 👥 Admin Panel

- User management
- Game control
- Odds configuration
- Promotion management
- Financial oversight
- Activity monitoring

## 📊 API Endpoints

### Authentication
- `POST /api/webapi/auth/login` - User login
- `POST /api/webapi/auth/register` - User registration

### User Management
- `GET /api/webapi/user/profile` - Get user profile
- `PUT /api/webapi/user/profile` - Update user profile
- `GET /api/webapi/user/history/bets` - Get bet history
- `GET /api/webapi/user/history/games` - Get game history

### Wallet
- `GET /api/webapi/wallet/balance` - Get balance
- `GET /api/webapi/wallet/transactions` - Get transaction history
- `POST /api/webapi/wallet/deposit` - Add funds
- `POST /api/webapi/wallet/withdraw` - Withdraw funds

### Games
- `GET /api/webapi/games/current` - Get current game
- `GET /api/webapi/games/recent` - Get recent games
- `GET /api/webapi/games/settings` - Get game settings

### Betting
- `POST /api/webapi/bet` - Place a bet
- `GET /api/webapi/bet/my-bets` - Get user's bets

### Promotions
- `GET /api/webapi/promotion/available` - Get available promotions
- `GET /api/webapi/promotion/my-promotions` - Get user's promotions
- `POST /api/webapi/promotion/claim` - Claim a promotion

### VIP
- `GET /api/webapi/vip/levels` - Get VIP levels
- `GET /api/webapi/vip/my-status` - Get user's VIP status

## 📱 Frontend Pages

- `index.html` - Landing page
- `login.html` - User login
- `register.html` - User registration
- `home.html` - User dashboard
- `game-wingo.html` - Main game interface
- `wallet.html` - Wallet management
- `history.html` - Game and bet history
- `vip.html` - VIP program
- `profile.html` - User profile

## 🖥️ Admin Panel Pages

- `admin/login.html` - Admin login
- `admin/dashboard.html` - Admin dashboard
- `admin/users.html` - User management
- `admin/games.html` - Game management
- `admin/results.html` - Game results
- `admin/wallet.html` - Wallet management
- `admin/promotions.html` - Promotion management
- `admin/settings.html` - System settings

## ⚠️ Important Notes

- This is a DEMO MODE application with no real money enforcement
- For production use, implement proper payment gateways
- Add additional security measures as needed
- Ensure compliance with local gambling laws

## 🤝 Contributing

Feel free to fork this repository and submit pull requests for improvements.

## 📄 License

This project is licensed under the MIT License.