// Global Admin App Configuration
const API_BASE_URL = '/api/webapi';

// Store admin authentication token
let adminToken = localStorage.getItem('adminToken');

// Set up axios defaults
if (window.axios) {
    axios.defaults.baseURL = API_BASE_URL;
    axios.defaults.headers.common['Authorization'] = adminToken ? `Bearer ${adminToken}` : '';
}

// DOM ready function
document.addEventListener('DOMContentLoaded', function() {
    initializeAdminApp();
});

// Initialize admin app
function initializeAdminApp() {
    // Set up admin logout button if exists
    const logoutBtn = document.getElementById('adminLogoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', adminLogout);
    }
    
    // Check if user is logged in
    if (!adminToken) {
        // Redirect to admin login if not logged in
        if (!window.location.pathname.includes('login.html')) {
            window.location.href = 'login.html';
        }
    } else {
        // Verify admin token is valid
        verifyAdminToken();
    }
}

// Verify admin token
function verifyAdminToken() {
    // In a real application, you would make a request to verify the token
    // For now, we'll just check if it exists and has admin role
    try {
        const payload = parseJwt(adminToken);
        if (!payload || payload.role !== 'admin') {
            adminLogout();
        }
    } catch (e) {
        adminLogout();
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

// Admin logout function
function adminLogout() {
    adminToken = null;
    localStorage.removeItem('adminToken');
    
    // Redirect to admin login page
    window.location.href = 'login.html';
}

// Set admin authentication token
function setAdminToken(token) {
    adminToken = token;
    localStorage.setItem('adminToken', token);
    
    // Update axios header
    if (window.axios) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
}

// Admin API request wrapper
async function adminApiRequest(endpoint, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;
    const config = {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${adminToken}`,
            ...options.headers
        }
    };
    
    const response = await fetch(url, config);
    
    if (response.status === 401 || response.status === 403) {
        // Token expired or invalid, redirect to login
        adminLogout();
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