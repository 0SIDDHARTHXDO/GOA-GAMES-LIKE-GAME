document.addEventListener('DOMContentLoaded', function() {
    if (authToken) {
        loadDashboardData();
    } else {
        window.location.href = 'login.html';
    }
});

async function loadDashboardData() {
    try {
        // Load user profile
        const profileResponse = await apiRequest('/user/profile');
        if (profileResponse.ok) {
            const profileData = await profileResponse.json();
            currentUser = profileData.user;
            updateUIWithUserInfo();
        }
        
        // Load recent games
        const gamesResponse = await apiRequest('/games/recent?limit=5');
        if (gamesResponse.ok) {
            const gamesData = await gamesResponse.json();
            displayRecentGames(gamesData.games);
        }
        
        // Load current game
        const currentGameResponse = await apiRequest('/games/current');
        if (currentGameResponse.ok) {
            const currentGameData = await currentGameResponse.json();
            displayCurrentGame(currentGameData.game);
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showMessage('Error loading dashboard data', 'danger');
    }
}

function displayRecentGames(games) {
    const container = document.getElementById('recentGamesList');
    if (!container) return;
    
    if (games.length === 0) {
        container.innerHTML = '<p class="text-muted">No recent games</p>';
        return;
    }
    
    let html = '<div class="list-group">';
    games.forEach(game => {
        let resultText = 'N/A';
        if (game.winning_number !== null) {
            resultText = `#${game.issue_number}: ${game.winning_number} (${game.winning_color}, ${game.winning_size})`;
        } else {
            resultText = `#${game.issue_number}: Pending...`;
        }
        
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <div>${resultText}</div>
                    <small class="text-muted">${new Date(game.end_time).toLocaleString()}</small>
                </div>
                <span class="badge ${
                    game.winning_number !== null ? 'bg-success' : 'bg-warning'
                }">
                    ${game.winning_number !== null ? 'Completed' : 'Pending'}
                </span>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function displayCurrentGame(game) {
    const activeGameElement = document.getElementById('activeGame');
    if (!activeGameElement) return;
    
    if (game) {
        activeGameElement.textContent = `#${game.issue_number}`;
    } else {
        activeGameElement.textContent = 'Initializing...';
    }
}

// Refresh data periodically
setInterval(loadDashboardData, 30000); // Refresh every 30 seconds