document.addEventListener('DOMContentLoaded', function() {
    if (authToken) {
        loadProfileData();
        setupEventListeners();
    } else {
        window.location.href = 'login.html';
    }
});

function setupEventListeners() {
    // Profile form submission
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', handleProfileUpdate);
    }
    
    // Save profile button
    const saveBtn = document.getElementById('saveProfileBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', handleProfileUpdate);
    }
}

async function loadProfileData() {
    try {
        const response = await apiRequest('/user/profile');
        if (response.ok) {
            const data = await response.json();
            currentUser = data.user;
            displayProfileData(data.user);
        }
    } catch (error) {
        console.error('Error loading profile data:', error);
        showMessage('Error loading profile data', 'danger');
    }
}

function displayProfileData(user) {
    // Update profile display elements
    const usernameEl = document.getElementById('profileUsername');
    if (usernameEl) {
        usernameEl.textContent = user.username;
    }
    
    const emailEl = document.getElementById('profileEmail');
    if (emailEl) {
        emailEl.textContent = user.email;
    }
    
    const emailInput = document.getElementById('profileEmailInput');
    if (emailInput) {
        emailInput.value = user.email;
    }
    
    // Update VIP badge
    const vipBadge = document.getElementById('profileVipBadge');
    if (vipBadge && user.vip_name && user.vip_color) {
        vipBadge.textContent = user.vip_name;
        vipBadge.style.backgroundColor = user.vip_color;
    }
    
    // Update stats
    const totalBetsEl = document.getElementById('totalBets');
    if (totalBetsEl) {
        totalBetsEl.textContent = `$${user.total_bets?.toFixed(2) || '0.00'}`;
    }
    
    const totalWinsEl = document.getElementById('totalWins');
    if (totalWinsEl) {
        totalWinsEl.textContent = `$${user.total_wins?.toFixed(2) || '0.00'}`;
    }
    
    // Update join date
    const joinDateEl = document.getElementById('joinDate');
    if (joinDateEl && user.created_at) {
        joinDateEl.textContent = new Date(user.created_at).toLocaleDateString();
    }
    
    // Update last login
    const lastLoginEl = document.getElementById('lastLogin');
    if (lastLoginEl && user.updated_at) {
        lastLoginEl.textContent = new Date(user.updated_at).toLocaleString();
    }
}

async function handleProfileUpdate(e) {
    e.preventDefault();
    
    const emailInput = document.getElementById('profileEmailInput');
    if (!emailInput) return;
    
    const newEmail = emailInput.value;
    
    // Validate email
    if (!validateInput(newEmail, 'email')) {
        showMessage('Please enter a valid email address', 'danger');
        return;
    }
    
    try {
        const response = await apiRequest('/user/profile', {
            method: 'PUT',
            body: JSON.stringify({
                email: newEmail
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showMessage('Profile updated successfully', 'success');
            
            // Update current user data
            if (currentUser) {
                currentUser.email = newEmail;
            }
            
            // Update display
            displayProfileData(currentUser);
        } else {
            showMessage(data.message || 'Failed to update profile', 'danger');
        }
    } catch (error) {
        console.error('Profile update error:', error);
        showMessage('An error occurred while updating profile', 'danger');
    }
}

// Refresh profile data periodically
setInterval(loadProfileData, 60000); // Refresh every minute