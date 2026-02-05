document.addEventListener('DOMContentLoaded', function() {
    if (authToken) {
        loadBetHistory();
        loadGameHistory();
        setupEventListeners();
    } else {
        window.location.href = 'login.html';
    }
});

function setupEventListeners() {
    // Bet status filter
    const betStatusFilter = document.getElementById('betStatusFilter');
    if (betStatusFilter) {
        betStatusFilter.addEventListener('change', loadBetHistory);
    }
}

async function loadBetHistory(page = 1) {
    try {
        const filterSelect = document.getElementById('betStatusFilter');
        const status = filterSelect ? filterSelect.value : '';
        
        let url = `/bet/my-bets?page=${page}&limit=10`;
        if (status) {
            url += `&status=${status}`;
        }
        
        const response = await apiRequest(url);
        if (response.ok) {
            const data = await response.json();
            displayBetHistory(data.bets, data.pagination);
        }
    } catch (error) {
        console.error('Error loading bet history:', error);
        showMessage('Error loading bet history', 'danger');
    }
}

function displayBetHistory(bets, pagination) {
    const tbody = document.getElementById('betsTableBody');
    if (!tbody) return;
    
    if (bets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No bets found</td></tr>';
        return;
    }
    
    let html = '';
    bets.forEach(bet => {
        const date = new Date(bet.created_at).toLocaleString();
        const statusClass = getStatusClass(bet.status);
        const potentialWin = (bet.amount * bet.odds).toFixed(2);
        
        html += `
            <tr>
                <td>${date}</td>
                <td>${bet.issue_number || '-'}</td>
                <td>${bet.bet_type}</td>
                <td>${bet.bet_value}</td>
                <td>$${bet.amount.toFixed(2)}</td>
                <td>${bet.odds}:1</td>
                <td><span class="badge bg-${statusClass}">${bet.status}</span></td>
                <td>$${potentialWin}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    
    // Display pagination
    displayPagination(pagination, (page) => loadBetHistory(page), 'bets');
}

async function loadGameHistory(page = 1) {
    try {
        const response = await apiRequest(`/games/recent?page=${page}&limit=10`);
        if (response.ok) {
            const data = await response.json();
            displayGameHistory(data.games, data.pagination);
        }
    } catch (error) {
        console.error('Error loading game history:', error);
        showMessage('Error loading game history', 'danger');
    }
}

function displayGameHistory(games, pagination) {
    const tbody = document.getElementById('gamesTableBody');
    if (!tbody) return;
    
    if (games.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No games found</td></tr>';
        return;
    }
    
    let html = '';
    games.forEach(game => {
        if (game.winning_number !== null) {
            const endTime = new Date(game.end_time).toLocaleString();
            
            html += `
                <tr>
                    <td>${game.issue_number}</td>
                    <td>${endTime}</td>
                    <td><span class="badge bg-primary">${game.winning_number}</span></td>
                    <td><span class="badge ${getClassForColor(game.winning_color)}">${game.winning_color}</span></td>
                    <td><span class="badge bg-info">${game.winning_size}</span></td>
                </tr>
            `;
        }
    });
    
    tbody.innerHTML = html;
    
    // Display pagination
    displayPagination(pagination, (page) => loadGameHistory(page), 'games');
}

function getStatusClass(status) {
    switch (status) {
        case 'won': return 'success';
        case 'lost': return 'danger';
        case 'pending': return 'warning';
        case 'cancelled': return 'secondary';
        default: return 'secondary';
    }
}

function getClassForColor(color) {
    switch (color) {
        case 'red': return 'bg-danger';
        case 'green': return 'bg-success';
        case 'violet': return 'bg-secondary';
        default: return 'bg-light';
    }
}

function displayPagination(pagination, onPageChange, prefix) {
    const paginationContainer = document.getElementById(`${prefix}Pagination`);
    if (!paginationContainer) return;
    
    if (pagination.total_pages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    if (pagination.current_page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="load${prefix === 'bets' ? 'Bet' : 'Game'}History(${pagination.current_page - 1}); return false;">Previous</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
        if (i === pagination.current_page) {
            html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="load${prefix === 'bets' ? 'Bet' : 'Game'}History(${i}); return false;">${i}</a></li>`;
        }
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="load${prefix === 'bets' ? 'Bet' : 'Game'}History(${pagination.current_page + 1}); return false;">Next</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    paginationContainer.innerHTML = html;
}

// Expose functions to global scope for pagination
window.loadBetHistory = loadBetHistory;
window.loadGameHistory = loadGameHistory;