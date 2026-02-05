document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
});

async function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    if (!username || !password) {
        showMessage('Please enter both username and password', 'danger', 'loginMessage');
        return;
    }
    
    try {
        const response = await login(username, password);
        const data = await response.json();
        
        if (response.ok) {
            setAuthToken(data.token);
            showMessage('Login successful! Redirecting...', 'success', 'loginMessage');
            
            // Store user info
            currentUser = data.user;
            
            // Redirect to home page after a short delay
            setTimeout(() => {
                window.location.href = 'home.html';
            }, 1500);
        } else {
            showMessage(data.message || 'Login failed', 'danger', 'loginMessage');
        }
    } catch (error) {
        console.error('Login error:', error);
        showMessage('An error occurred during login', 'danger', 'loginMessage');
    }
}

async function handleRegister(e) {
    e.preventDefault();
    
    const username = document.getElementById('regUsername').value;
    const email = document.getElementById('regEmail').value;
    const password = document.getElementById('regPassword').value;
    
    // Validate inputs
    if (!username || !email || !password) {
        showMessage('Please fill in all fields', 'danger', 'registerMessage');
        return;
    }
    
    if (!validateInput(username, 'username')) {
        showMessage('Invalid username format. 3-20 characters, letters, numbers, and underscores only.', 'danger', 'registerMessage');
        return;
    }
    
    if (!validateInput(email, 'email')) {
        showMessage('Invalid email format', 'danger', 'registerMessage');
        return;
    }
    
    if (!validateInput(password, 'password')) {
        showMessage('Password must be at least 6 characters', 'danger', 'registerMessage');
        return;
    }
    
    try {
        const response = await register(username, email, password);
        const data = await response.json();
        
        if (response.ok) {
            showMessage('Registration successful! You can now log in.', 'success', 'registerMessage');
            
            // Clear form
            document.getElementById('regUsername').value = '';
            document.getElementById('regEmail').value = '';
            document.getElementById('regPassword').value = '';
        } else {
            showMessage(data.message || 'Registration failed', 'danger', 'registerMessage');
        }
    } catch (error) {
        console.error('Registration error:', error);
        showMessage('An error occurred during registration', 'danger', 'registerMessage');
    }
}

// Override the global login and register functions
function login(username, password) {
    return fetch(`${API_BASE_URL}/auth/login`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ username, password })
    });
}

function register(username, email, password) {
    return fetch(`${API_BASE_URL}/auth/register`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ username, email, password })
    });
}