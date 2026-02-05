document.addEventListener('DOMContentLoaded', function() {
    if (adminToken) {
        loadDashboardData();
        setupEventListeners();
    } else {
        window.location.href = 'login.html';
    }
});

function setupEventListeners() {
    // Refresh stats button
    const refreshBtn = document.getElementById('refreshStatsBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadDashboardData);
    }
    
    // Submit create user button
    const submitUserBtn = document.getElementById('submitCreateUser');
    if (submitUserBtn) {
        submitUserBtn.addEventListener('click', handleCreateUser);
    }
    
    // Submit create promotion button
    const submitPromoBtn = document.getElementById('submitCreatePromotion');
    if (submitPromoBtn) {
        submitPromoBtn.addEventListener('click', handleCreatePromotion);
    }
}

async function loadDashboardData() {
    try {
        // Load dashboard stats
        const statsResponse = await adminApiRequest('/admin/dashboard');
        if (statsResponse) {
            const statsData = await statsResponse.json();
            displayDashboardStats(statsData.dashboard);
        }
        
        // Load recent activities
        loadRecentActivities();
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showMessage('Error loading dashboard data', 'danger');
    }
}

function displayDashboardStats(stats) {
    // Update stat displays
    const totalUsersEl = document.getElementById('totalUsers');
    if (totalUsersEl) {
        totalUsersEl.textContent = stats.total_users || 0;
    }
    
    const activeUsersEl = document.getElementById('activeUsers');
    if (activeUsersEl) {
        activeUsersEl.textContent = stats.active_users || 0;
    }
    
    const totalBetsEl = document.getElementById('totalBets');
    if (totalBetsEl) {
        totalBetsEl.textContent = stats.total_bets || 0;
    }
    
    const balanceEl = document.getElementById('platformBalance');
    if (balanceEl) {
        balanceEl.textContent = formatCurrency(stats.total_platform_balance || 0);
    }
}

async function loadRecentActivities(page = 1) {
    try {
        // For demo purposes, we'll create mock activity data
        // In a real application, you would fetch from an API
        const activities = [
            { user: 'john_doe', action: 'Placed bet', details: '$10 on number 5', time: new Date().toISOString() },
            { user: 'jane_smith', action: 'Won game', details: 'Won $90 on color red', time: new Date(Date.now() - 300000).toISOString() },
            { user: 'bob_johnson', action: 'Deposited funds', details: '$100 added', time: new Date(Date.now() - 600000).toISOString() },
            { user: 'alice_williams', action: 'Created account', details: 'New user registration', time: new Date(Date.now() - 900000).toISOString() }
        ];
        
        displayRecentActivities(activities);
    } catch (error) {
        console.error('Error loading recent activities:', error);
    }
}

function displayRecentActivities(activities) {
    const tbody = document.getElementById('recentActivities');
    if (!tbody) return;
    
    if (activities.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No recent activities</td></tr>';
        return;
    }
    
    let html = '';
    activities.forEach(activity => {
        const time = new Date(activity.time).toLocaleString();
        
        html += `
            <tr>
                <td>${activity.user}</td>
                <td>${activity.action}</td>
                <td>${activity.details}</td>
                <td>${time}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

async function handleCreateUser() {
    const username = document.getElementById('newUsername').value;
    const email = document.getElementById('newEmail').value;
    const password = document.getElementById('newPassword').value;
    const initialBalance = parseFloat(document.getElementById('initialBalance').value) || 1000.00;
    
    // Validate inputs
    if (!username || !email || !password) {
        showMessage('Please fill in all required fields', 'danger');
        return;
    }
    
    if (!validateInput(username, 'username')) {
        showMessage('Invalid username format', 'danger');
        return;
    }
    
    if (!validateInput(email, 'email')) {
        showMessage('Invalid email format', 'danger');
        return;
    }
    
    if (!validateInput(password, 'password')) {
        showMessage('Password must be at least 6 characters', 'danger');
        return;
    }
    
    try {
        const response = await adminApiRequest('/admin/users/create', {
            method: 'POST',
            body: JSON.stringify({
                username,
                email,
                password,
                initial_balance: initialBalance
            })
        });
        
        if (response) {
            const data = await response.json();
            
            if (response.ok) {
                showMessage('User created successfully', 'success');
                
                // Close modal and reset form
                const modal = bootstrap.Modal.getInstance(document.getElementById('createUserModal'));
                if (modal) {
                    modal.hide();
                }
                
                document.getElementById('createUserForm').reset();
                document.getElementById('initialBalance').value = '1000.00';
            } else {
                showMessage(data.message || 'Failed to create user', 'danger');
            }
        }
    } catch (error) {
        console.error('Error creating user:', error);
        showMessage('An error occurred while creating user', 'danger');
    }
}

async function handleCreatePromotion() {
    const title = document.getElementById('promotionTitle').value;
    const type = document.getElementById('promotionType').value;
    const bonusAmount = parseFloat(document.getElementById('bonusAmount').value) || 0;
    const bonusPercentage = parseFloat(document.getElementById('bonusPercentage').value) || 0;
    const minDeposit = parseFloat(document.getElementById('minDeposit').value) || 0;
    const maxBonus = parseFloat(document.getElementById('maxBonus').value) || 0;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const description = document.getElementById('promotionDescription').value;
    const isActive = document.getElementById('isActive').checked;
    
    // Validate required fields
    if (!title || !type) {
        showMessage('Please fill in all required fields', 'danger');
        return;
    }
    
    try {
        const response = await adminApiRequest('/admin/promotions', {
            method: 'POST',
            body: JSON.stringify({
                title,
                description,
                type,
                bonus_amount: bonusAmount,
                bonus_percentage: bonusPercentage,
                min_deposit: minDeposit,
                max_bonus: maxBonus,
                start_date: startDate || null,
                end_date: endDate || null,
                is_active: isActive
            })
        });
        
        if (response) {
            const data = await response.json();
            
            if (response.ok) {
                showMessage('Promotion created successfully', 'success');
                
                // Close modal and reset form
                const modal = bootstrap.Modal.getInstance(document.getElementById('createPromotionModal'));
                if (modal) {
                    modal.hide();
                }
                
                document.getElementById('createPromotionForm').reset();
                document.getElementById('isActive').checked = true;
            } else {
                showMessage(data.message || 'Failed to create promotion', 'danger');
            }
        }
    } catch (error) {
        console.error('Error creating promotion:', error);
        showMessage('An error occurred while creating promotion', 'danger');
    }
}

// Refresh data periodically
setInterval(loadDashboardData, 60000); // Refresh every minute