<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriTrace - Login</title>
    <link rel="stylesheet" href="login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <span class="logo-icon">🌾</span>
                <span class="logo-text">AgriTrace</span>
            </div>
            
            <h2 class="welcome-title">Welcome Back</h2>
            <p class="welcome-subtitle">Sign in to continue</p>
            
            <!-- Login Form -->
            <form id="loginForm" class="login-form" onsubmit="event.preventDefault(); AuthManager.login();">
                <div class="form-group">
                    <label for="loginEmail">Email Address</label>
                    <input type="email" id="loginEmail" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <div class="password-input">
                        <input type="password" id="loginPassword" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="AuthManager.togglePassword('loginPassword')">👁️</button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" id="rememberMe">
                        <span class="checkmark"></span>
                        <span>Remember me</span>
                    </label>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">
                    <span>Sign In</span>
                    <span class="loading-spinner" style="display: none;"></span>
                </button>
                
                <div class="error-message" id="loginError" style="display: none;"></div>
            </form>
            
            <div class="signup-link">
                <p>Don't have an account? <a href="#" id="showSignup">Create account</a></p>
            </div>
        </div>
        
        <!-- Signup Box (Hidden by default) -->
        <div class="signup-box" id="signupBox" style="display: none;">
            <div class="logo">
                <span class="logo-icon">🌾</span>
                <span class="logo-text">AgriMarketplace</span>
            </div>
            
            <h2 class="welcome-title">Create Account</h2>
            <p class="welcome-subtitle">Join our agricultural marketplace</p>
            
            <!-- Signup Form -->
            <form id="signupForm" class="login-form" onsubmit="event.preventDefault(); AuthManager.signup();">
                <div class="form-row">
                    <div class="form-group">
                        <label for="signupFirstName">First Name</label>
                        <input type="text" id="signupFirstName" placeholder="James" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupLastName">Last Name</label>
                        <input type="text" id="signupLastName" placeholder="Wesonga" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="signupEmail">Email Address</label>
                    <input type="email" id="signupEmail" placeholder="example@email.com" required>
                </div>
                
                <div class="form-group">
                    <label for="signupPhone">Phone Number</label>
                    <input type="tel" id="signupPhone" placeholder="+254 XXX XXX XXX" required>
                </div>
                
                <div class="form-group">
                    <label for="signupUserType">I am a</label>
                    <select id="signupUserType" required>
                        <option value="">Select user type</option>
                        <option value="Farmer">Farmer</option>
                        <option value="Buyer">Buyer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="signupPassword">Password</label>
                    <div class="password-input">
                        <input type="password" id="signupPassword" placeholder="Create a password" required>
                        <button type="button" class="password-toggle" onclick="AuthManager.togglePassword('signupPassword')">👁️</button>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="signupConfirmPassword">Confirm Password</label>
                    <div class="password-input">
                        <input type="password" id="signupConfirmPassword" placeholder="Confirm your password" required>
                        <button type="button" class="password-toggle" onclick="AuthManager.togglePassword('signupConfirmPassword')">👁️</button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" id="termsAgree" required>
                        <span class="checkmark"></span>
                        <span>I agree to the <a href="#" target="_blank">Terms</a> and <a href="#" target="_blank">Privacy Policy</a></span>
                    </label>
                </div>
                
                <button type="submit" class="login-btn" id="signupBtn">
                    <span>Create Account</span>
                    <span class="loading-spinner" style="display: none;"></span>
                </button>
                
                <div class="error-message" id="signupError" style="display: none;"></div>
            </form>
            
            <div class="signup-link">
                <p>Already have an account? <a href="#" id="showLogin">Sign in</a></p>
            </div>
        </div>
    </div>
    
   

    <script src="login.js"></script>
</body>
</html>
