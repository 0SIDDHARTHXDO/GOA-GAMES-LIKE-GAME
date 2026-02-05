document.addEventListener('DOMContentLoaded', function() {
    if (authToken) {
        initializeGame();
        setupEventListeners();
    } else {
        window.location.href = 'login.html';
    }
});

let selectedBet = null;
let timerInterval = null;
let currentGame = null;

function setupEventListeners() {
    // Place bet button
    const placeBetBtn = document.getElementById('placeBetBtn');
    if (placeBetBtn) {
        placeBetBtn.addEventListener('click', placeBet);
    }
    
    // Bet amount input
    const betAmountInput = document.getElementById('betAmount');
    if (betAmountInput) {
        betAmountInput.addEventListener('input', updatePlaceBetButton);
    }
}

function initializeGame() {
    // Create number board
    createNumberBoard();
    
    // Load game data
    loadGameData();
    
    // Set up periodic updates
    setInterval(loadGameData, 5000); // Update every 5 seconds
}

function createNumberBoard() {
    const board = document.getElementById('numbersBoard');
    if (!board) return;
    
    let html = '';
    for (let i = 0; i <= 9; i++) {
        html += `<div class="number-option" data-type="number" data-value="${i}">${i}</div>`;
    }
    board.innerHTML = html;
    
    // Add event listeners to number options
    document.querySelectorAll('#numbersBoard .number-option').forEach(option => {
        option.addEventListener('click', handleBetSelection);
    });
    
    // Add event listeners to color and size options
    document.querySelectorAll('#colorsBoard .number-option, #sizesBoard .number-option').forEach(option => {
        option.addEventListener('click', handleBetSelection);
    });
}

function handleBetSelection(e) {
    const type = e.target.getAttribute('data-type');
    const value = e.target.getAttribute('data-value');
    
    // Remove selected class from all options in this type
    document.querySelectorAll(`[data-type="${type}"]`).forEach(el => {
        el.classList.remove('selected');
    });
    
    // Add selected class to clicked option
    e.target.classList.add('selected');
    
    // Update selected bet
    selectedBet = { type, value };
    updateSelectedBetDisplay();
    updatePlaceBetButton();
}

function updateSelectedBetDisplay() {
    const display = document.getElementById('selectedBetDisplay');
    if (!display) return;
    
    if (selectedBet) {
        display.textContent = `${selectedBet.type.charAt(0).toUpperCase() + selectedBet.type.slice(1)}: ${selectedBet.value}`;
        display.className = 'alert alert-info';
    } else {
        display.textContent = 'No bet selected';
        display.className = 'alert alert-secondary';
    }
}

function updatePlaceBetButton() {
    const placeBetBtn = document.getElementById('placeBetBtn');
    const betAmountInput = document.getElementById('betAmount');
    
    if (!placeBetBtn || !betAmountInput) return;
    
    const betAmount = parseFloat(betAmountInput.value) || 0;
    
    if (selectedBet && betAmount > 0) {
        placeBetBtn.disabled = false;
        placeBetBtn.textContent = `Place Bet ($${betAmount.toFixed(2)})`;
    } else {
        placeBetBtn.disabled = true;
        placeBetBtn.textContent = 'Place Bet ($0.00)';
    }
}

async function loadGameData() {
    try {
        // Load user balance
        const balanceResponse = await apiRequest('/wallet/balance');
        if (balanceResponse.ok) {
            const balanceData = await balanceResponse.json();
            const balanceDisplay = document.getElementById('balanceDisplay');
            if (balanceDisplay) {
                balanceDisplay.textContent = `$${balanceData.balance.toFixed(2)}`;
            }
        }
        
        // Load current game
        const gameResponse = await apiRequest('/games/current');
        if (gameResponse.ok) {
            const gameData = await gameResponse.json();
            currentGame = gameData.game;
            updateGameDisplay(currentGame);
        }
        
        // Load recent results
        const recentResponse = await apiRequest('/games/recent?limit=10');
        if (recentResponse.ok) {
            const recentData = await recentResponse.json();
            displayRecentResults(recentData.games);
        }
    } catch (error) {
        console.error('Error loading game data:', error);
    }
}

function updateGameDisplay(game) {
    if (!game) return;
    
    // Update issue number
    const issueNumberEl = document.getElementById('issueNumber');
    if (issueNumberEl) {
        issueNumberEl.textContent = game.issue_number;
    }
    
    // Update timer
    updateTimer(game);
    
    // Update result display
    const resultDisplay = document.getElementById('resultDisplay');
    if (resultDisplay) {
        if (game.status === 'completed' && game.winning_number !== null) {
            resultDisplay.innerHTML = `
                <span class="winning-number">${game.winning_number} (${game.winning_color}, ${game.winning_size})</span>
            `;
        } else if (game.status === 'active' || game.status === 'locked') {
            resultDisplay.textContent = 'Game in progress...';
        } else {
            resultDisplay.textContent = 'Waiting for result...';
        }
    }
    
    // Enable/disable betting based on game status
    const placeBetBtn = document.getElementById('placeBetBtn');
    if (placeBetBtn) {
        placeBetBtn.disabled = game.status !== 'active';
    }
}

function updateTimer(game) {
    if (!game) return;
    
    const timerDisplay = document.getElementById('timer');
    const progressBar = document.getElementById('timerProgress');
    
    if (!timerDisplay || !progressBar) return;
    
    const now = new Date().getTime();
    const endTime = new Date(game.end_time).getTime();
    const remainingMs = endTime - now;
    
    if (remainingMs <= 0) {
        timerDisplay.textContent = '00:00';
        progressBar.style.width = '0%';
        progressBar.setAttribute('aria-valuenow', '0');
        return;
    }
    
    // Calculate minutes and seconds
    const totalSeconds = Math.floor(remainingMs / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    
    // Format as MM:SS
    const formattedTime = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    timerDisplay.textContent = formattedTime;
    
    // Update progress bar (assuming 1-minute games)
    const totalTime = 60; // 60 seconds for 1-minute game
    const percentage = Math.max(0, Math.min(100, (totalSeconds / totalTime) * 100));
    progressBar.style.width = `${percentage}%`;
    progressBar.setAttribute('aria-valuenow', percentage.toString());
    
    // Change color based on time remaining
    if (percentage < 25) {
        progressBar.className = 'progress-bar bg-danger';
    } else if (percentage < 50) {
        progressBar.className = 'progress-bar bg-warning';
    } else {
        progressBar.className = 'progress-bar bg-success';
    }
    
    // Disable betting when time is almost up
    const placeBetBtn = document.getElementById('placeBetBtn');
    if (placeBetBtn && totalSeconds < 10) { // Disable betting 10 seconds before end
        placeBetBtn.disabled = true;
        placeBetBtn.textContent = 'Betting Locked';
    }
}

function displayRecentResults(results) {
    const container = document.getElementById('recentResults');
    if (!container) return;
    
    if (results.length === 0) {
        container.innerHTML = '<p class="text-muted">No recent results</p>';
        return;
    }
    
    let html = '<div class="list-group">';
    results.forEach(result => {
        if (result.winning_number !== null) {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>#${result.issue_number}</strong><br>
                        <span class="badge bg-primary">${result.winning_number}</span>
                        <span class="badge ${getClassForColor(result.winning_color)}">${result.winning_color}</span>
                        <span class="badge bg-info">${result.winning_size}</span>
                    </div>
                    <small class="text-muted">${new Date(result.end_time).toLocaleTimeString()}</small>
                </div>
            `;
        }
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function getClassForColor(color) {
    switch (color) {
        case 'red': return 'bg-danger';
        case 'green': return 'bg-success';
        case 'violet': return 'bg-secondary';
        default: return 'bg-light';
    }
}

async function placeBet() {
    if (!selectedBet) {
        showMessage('Please select a bet type and value', 'danger');
        return;
    }
    
    const betAmountInput = document.getElementById('betAmount');
    if (!betAmountInput) return;
    
    const amount = parseFloat(betAmountInput.value);
    if (isNaN(amount) || amount <= 0) {
        showMessage('Please enter a valid bet amount', 'danger');
        return;
    }
    
    if (!currentGame) {
        showMessage('No active game available', 'danger');
        return;
    }
    
    try {
        const response = await apiRequest('/bet', {
            method: 'POST',
            body: JSON.stringify({
                bet_type: selectedBet.type,
                bet_value: selectedBet.value,
                amount: amount,
                issue_id: currentGame.id
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showMessage('Bet placed successfully!', 'success');
            
            // Reset bet selection
            selectedBet = null;
            document.querySelectorAll('.number-option.selected').forEach(el => {
                el.classList.remove('selected');
            });
            updateSelectedBetDisplay();
            updatePlaceBetButton();
            
            // Reload game data to update balance
            loadGameData();
        } else {
            showMessage(data.message || 'Failed to place bet', 'danger');
        }
    } catch (error) {
        console.error('Place bet error:', error);
        showMessage('An error occurred while placing bet', 'danger');
    }
}