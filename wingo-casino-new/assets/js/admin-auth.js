document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('adminLoginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleAdminLogin);
    }
});

async function handleAdminLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('adminUsername').value;
    const password = document.getElementById('adminPassword').value;
    
    if (!username || !password) {
        showMessage('Please enter both username and password', 'danger', 'adminLoginMessage');
        return;
    }
    
    try {
        // For demo purposes, we'll use a mock admin login
        // In a real application, you would send credentials to the server
        const response = await fetch('/api/webapi/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            // Check if user is admin by validating token
            const payload = parseJwt(data.token);
            if (payload && payload.role === 'admin') {
                localStorage.setItem('adminToken', data.token);
                showMessage('Admin login successful! Redirecting...', 'success', 'adminLoginMessage');
                
                // Redirect to admin dashboard after a short delay
                setTimeout(() => {
                    window.location.href = 'dashboard.html';
                }, 1500);
            } else {
                showMessage('Access denied. Admin privileges required.', 'danger', 'adminLoginMessage');
            }
        } else {
            showMessage(data.message || 'Login failed', 'danger', 'adminLoginMessage');
        }
    } catch (error) {
        console.error('Admin login error:', error);
        showMessage('An error occurred during login', 'danger', 'adminLoginMessage');
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