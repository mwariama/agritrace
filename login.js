// API Service
const API = {
    baseUrl: '',
    
    async post(endpoint, data) {
        try {
            const url = `auth.php?action=${endpoint}`;
            console.log('Posting to:', url, data);
            
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            return { success: false, error: error.message };
        }
    }
};

// Auth Manager
const AuthManager = {
    init: function() {
        this.setupEventListeners();
        this.checkRememberedUser();
        this.checkAlreadyLoggedIn();
    },
    
    checkAlreadyLoggedIn: async function() {
        // Optional: Check if already logged in via session
        try {
            const response = await fetch('auth.php?action=check');
            const data = await response.json();
            if (data.logged_in) {
                // Already logged in, redirect based on role
                if (data.user.role === 'Admin') {
                    window.location.href = 'admin/dashboard.php';
                }
            }
        } catch (e) {
            // Ignore errors
        }
    },
    
    setupEventListeners: function() {
        // Show signup form
        const showSignup = document.getElementById('showSignup');
        if (showSignup) {
            showSignup.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelector('.login-box').style.display = 'none';
                document.getElementById('signupBox').style.display = 'block';
                this.clearMessages();
            });
        }
        
        // Show login form
        const showLogin = document.getElementById('showLogin');
        if (showLogin) {
            showLogin.addEventListener('click', (e) => {
                e.preventDefault();
                document.getElementById('signupBox').style.display = 'none';
                document.querySelector('.login-box').style.display = 'block';
                this.clearMessages();
            });
        }
        
        // Password strength indicator
        const signupPassword = document.getElementById('signupPassword');
        if (signupPassword) {
            signupPassword.addEventListener('input', (e) => {
                this.checkPasswordStrength(e.target.value);
            });
        }
    },
    
    checkRememberedUser: function() {
        const remembered = localStorage.getItem('rememberedEmail');
        if (remembered) {
            const emailInput = document.getElementById('loginEmail');
            if (emailInput) {
                emailInput.value = remembered;
            }
            const rememberMe = document.getElementById('rememberMe');
            if (rememberMe) {
                rememberMe.checked = true;
            }
        }
    },
    
    togglePassword: function(inputId) {
        const input = document.getElementById(inputId);
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
    },
    
    checkPasswordStrength: function(password) {
        const strengthBar = document.querySelector('.strength-bar');
        if (!strengthBar) return;
        
        strengthBar.className = 'strength-bar';
        
        if (password.length === 0) {
            strengthBar.style.width = '0';
            return;
        }
        
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        if (strength <= 2) {
            strengthBar.classList.add('weak');
        } else if (strength <= 4) {
            strengthBar.classList.add('medium');
        } else {
            strengthBar.classList.add('strong');
        }
    },
    
    clearMessages: function() {
        document.querySelectorAll('.error-message, .success-message').forEach(el => {
            el.style.display = 'none';
        });
    },
    
    showError: function(elementId, message) {
        const errorEl = document.getElementById(elementId);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
            
            setTimeout(() => {
                errorEl.style.display = 'none';
            }, 5000);
        }
    },
    
    showSuccess: function(elementId, message) {
        const successEl = document.getElementById(elementId);
        if (successEl) {
            successEl.textContent = message;
            successEl.style.display = 'block';
        }
    },
    
    setLoading: function(buttonId, isLoading) {
        const btn = document.getElementById(buttonId);
        if (!btn) return;
        
        const span = btn.querySelector('span');
        const spinner = btn.querySelector('.loading-spinner');
        
        if (isLoading) {
            btn.disabled = true;
            if (span) span.style.display = 'none';
            if (spinner) spinner.style.display = 'inline-block';
        } else {
            btn.disabled = false;
            if (span) span.style.display = 'inline';
            if (spinner) spinner.style.display = 'none';
        }
    },
    
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    validatePhone: function(phone) {
        const digits = phone.replace(/\D/g, '');
        return digits.length >= 10;
    },
    
    login: async function() {
        const email = document.getElementById('loginEmail')?.value.trim();
        const password = document.getElementById('loginPassword')?.value;
        const remember = document.getElementById('rememberMe')?.checked;
        
        if (!email || !password) {
            this.showError('loginError', 'Please enter both email and password');
            return;
        }
        
        if (!this.validateEmail(email)) {
            this.showError('loginError', 'Please enter a valid email address');
            return;
        }
        
        this.setLoading('loginBtn', true);
        
        const result = await API.post('login', { email, password });
        
        this.setLoading('loginBtn', false);
        
        if (result.success) {
            if (remember) {
                localStorage.setItem('rememberedEmail', email);
            } else {
                localStorage.removeItem('rememberedEmail');
            }
           
            
            // Redirect based on user type
            if (result.user.role === 'Admin') {
                window.location.href = 'admin/dashboard.php';
            } else if (result.user.role === 'Farmer') {
                window.location.href = 'farmer/dashboard.php';
            } else if (result.user.role === 'Buyer') {
                window.location.href = 'buyer/marketplace.php';
            } else {
                window.location.href = 'admin/dashboard.php';
            }
        } else {
            this.showError('loginError', result.error || 'Invalid email or password');
        }
    },
    
    signup: async function() {
        const firstName = document.getElementById('signupFirstName')?.value.trim();
        const lastName = document.getElementById('signupLastName')?.value.trim();
        const email = document.getElementById('signupEmail')?.value.trim();
        const phone = document.getElementById('signupPhone')?.value.trim();
        const userType = document.getElementById('signupUserType')?.value;
        const password = document.getElementById('signupPassword')?.value;
        const confirmPassword = document.getElementById('signupConfirmPassword')?.value;
        const termsAgree = document.getElementById('termsAgree')?.checked;
        
        if (!firstName || !lastName) {
            this.showError('signupError', 'Please enter your full name');
            return;
        }
        
        if (!email || !this.validateEmail(email)) {
            this.showError('signupError', 'Please enter a valid email address');
            return;
        }
        
        if (!phone || !this.validatePhone(phone)) {
            this.showError('signupError', 'Please enter a valid phone number');
            return;
        }
        
        if (!userType) {
            this.showError('signupError', 'Please select user type');
            return;
        }
        
        if (!password || password.length < 6) {
            this.showError('signupError', 'Password must be at least 6 characters');
            return;
        }
        
        if (password !== confirmPassword) {
            this.showError('signupError', 'Passwords do not match');
            return;
        }
        
        if (!termsAgree) {
            this.showError('signupError', 'You must agree to the Terms of Service');
            return;
        }
        
        this.setLoading('signupBtn', true);
        
        const result = await API.post('signup', {
            full_name: firstName + ' ' + lastName,
            email: email,
            phone_number: phone,
            user_type: userType,
            password: password
        });
        
        this.setLoading('signupBtn', false);
        
        if (result.success) {
            this.showSuccess('signupError', 'Account created successfully! You can now login.');
            
            document.getElementById('signupForm')?.reset();
            
            setTimeout(() => {
                document.getElementById('signupBox').style.display = 'none';
                document.querySelector('.login-box').style.display = 'block';
                const loginEmail = document.getElementById('loginEmail');
                if (loginEmail) loginEmail.value = email;
                this.clearMessages();
            }, 2000);
        } else {
            this.showError('signupError', result.error || 'Failed to create account');
        }
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('loginForm')) {
        AuthManager.init();
    }
});

// Make AuthManager globally available
window.AuthManager = AuthManager;