// Global App Configuration
const API_BASE_URL = '/api/webapi';

// Store authentication token
let authToken = localStorage.getItem('authToken');
let currentUser = null;

// Set up axios defaults
if (window.axios) {
    axios.defaults.baseURL = API_BASE_URL;
    axios.defaults.headers.common['Authorization'] = authToken ? `Bearer ${authToken}` : '';
}

// DOM ready function
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Initialize app
function initializeApp() {
    // Load user info if logged in
    if (authToken) {
        loadUserInfo();
    }
    
    // Set up logout button if exists
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logout);
    }
    
    // Update username display if exists
    updateUsernameDisplay();
}

// Load user info from API
async function loadUserInfo() {
    try {
        const response = await fetch(`${API_BASE_URL}/user/profile`, {
            headers: {
                'Authorization': `Bearer ${authToken}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            currentUser = data.user;
            updateUIWithUserInfo();
        } else {
            console.error('Failed to load user info');
        }
    } catch (error) {
        console.error('Error loading user info:', error);
    }
}

// Update UI with user info
function updateUIWithUserInfo() {
    if (currentUser) {
        // Update username display
        updateUsernameDisplay();
        
        // Update balance display if element exists
        const balanceDisplay = document.getElementById('balanceDisplay');
        if (balanceDisplay) {
            balanceDisplay.textContent = `$${currentUser.balance.toFixed(2)}`;
        }
        
        // Update VIP level if element exists
        const vipLevelDisplay = document.getElementById('vipLevel');
        if (vipLevelDisplay && currentUser.vip_name) {
            vipLevelDisplay.textContent = currentUser.vip_name;
        }
    }
}

// Update username display
function updateUsernameDisplay() {
    const usernameDisplay = document.getElementById('usernameDisplay');
    if (usernameDisplay && currentUser) {
        usernameDisplay.textContent = currentUser.username;
    } else if (usernameDisplay) {
        // Try to get username from token payload
        if (authToken) {
            try {
                const payload = parseJwt(authToken);
                usernameDisplay.textContent = payload.username || 'User';
            } catch (e) {
                usernameDisplay.textContent = 'User';
            }
        }
    }
}

// Parse JWT token
function parseJwt(token) {
    try {
        const base64Url = token.split('.')[1];
        const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
        const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
        
        return JSON.parse(jsonPayload);
    } catch (e) {
        console.error('Error parsing JWT:', e);
        return null;
    }
}

// Login function
function login(username, password) {
    return fetch(`${API_BASE_URL}/auth/login`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ username, password })
    });
}

// Register function
function register(username, email, password) {
    return fetch(`${API_BASE_URL}/auth/register`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ username, email, password })
    });
}

// Logout function
function logout() {
    authToken = null;
    currentUser = null;
    localStorage.removeItem('authToken');
    
    // Redirect to login page
    window.location.href = 'login.html';
}

// Set authentication token
function setAuthToken(token) {
    authToken = token;
    localStorage.setItem('authToken', token);
    
    // Update axios header
    if (window.axios) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
}

// API request wrapper
async function apiRequest(endpoint, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;
    const config = {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${authToken}`,
            ...options.headers
        }
    };
    
    const response = await fetch(url, config);
    
    if (response.status === 401) {
        // Token expired or invalid, redirect to login
        logout();
        return null;
    }
    
    return response;
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Show message
function showMessage(message, type = 'info', elementId = 'message') {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }
}

// Validate form input
function validateInput(value, type) {
    switch (type) {
        case 'email':
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        case 'username':
            return /^[a-zA-Z0-9_]{3,20}$/.test(value);
        case 'password':
            return value.length >= 6;
        case 'amount':
            const num = parseFloat(value);
            return !isNaN(num) && num > 0;
        default:
            return true;
    }
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}