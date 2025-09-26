<?php
session_start();
require_once 'config/database.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'member';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = 'Please fill in all required fields';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long';
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $userExists = $stmt->fetchColumn();
            
            if ($userExists > 0) {
                $error = 'Username or email already exists';
            } else {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, phone, role, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', TRUE)");
                
                if ($stmt->execute([$username, $email, $hashedPassword, $first_name, $last_name, $phone, $role])) {
                    $success = 'Registration successful! You can now login.';
                    // Clear form data
                    $username = $email = $first_name = $last_name = $phone = '';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ClubMaster</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #0a0a0a;
            color: #ffffff;
            line-height: 1.6;
            overflow-x: hidden;
            min-height: 100vh;
            padding: 2rem 0;
        }

        /* Background Animation */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            background: radial-gradient(ellipse at center, rgba(102, 126, 234, 0.1) 0%, rgba(10, 10, 10, 1) 70%);
        }

        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .shape {
            position: absolute;
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        .shape-1 {
            width: 200px;
            height: 200px;
            top: 10%;
            left: 80%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 150px;
            height: 150px;
            top: 60%;
            left: 10%;
            animation-delay: 2s;
        }

        .shape-3 {
            width: 100px;
            height: 100px;
            top: 80%;
            left: 70%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        /* Register Container */
        .register-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: slideUp 1s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header */
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .logo i {
            margin-right: 0.5rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5rem;
        }

        .register-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, #ffffff, #b0b0b0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .register-subtitle {
            color: #b0b0b0;
            font-size: 1rem;
        }

        /* Form Styles */
        .register-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #ffffff;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group label .required {
            color: #ff6b6b;
            margin-left: 0.25rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-select {
            padding-left: 3rem;
            cursor: pointer;
        }

        .form-input:focus, .form-select:focus {
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
        }

        .form-input::placeholder {
            color: #888;
        }

        .form-select option {
            background: #2a2a2a;
            color: #ffffff;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .form-group:focus-within .input-icon {
            color: #667eea;
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        /* Role Selection */
        .role-selection {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }

        .role-option {
            position: relative;
        }

        .role-option input[type="radio"] {
            display: none;
        }

        .role-label {
            display: block;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .role-label:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(102, 126, 234, 0.5);
        }

        .role-option input[type="radio"]:checked + .role-label {
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border-color: #667eea;
            color: #667eea;
        }

        .role-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .role-title {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .role-desc {
            font-size: 0.75rem;
            color: #b0b0b0;
            margin-top: 0.25rem;
        }

        /* Terms and Conditions */
        .terms-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .terms-group input[type="checkbox"] {
            margin-top: 0.25rem;
            accent-color: #667eea;
        }

        .terms-group label {
            font-size: 0.9rem;
            color: #b0b0b0;
            cursor: pointer;
            line-height: 1.5;
        }

        .terms-link {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .terms-link:hover {
            color: #764ba2;
        }

        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .btn-secondary {
            background: transparent;
            color: #667eea;
            border: 1px solid #667eea;
            margin-top: 1rem;
            width: 100%;
        }

        .btn-secondary:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            animation: slideDown 0.3s ease;
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            color: #ff6b6b;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Footer */
        .register-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .register-footer p {
            color: #b0b0b0;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .login-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .login-link:hover {
            color: #764ba2;
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #b0b0b0;
            text-decoration: none;
            margin-top: 1rem;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-home:hover {
            color: #667eea;
        }

        /* Loading Animation */
        .btn-loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: #ff6b6b; width: 25%; }
        .strength-fair { background: #feca57; width: 50%; }
        .strength-good { background: #48dbfb; width: 75%; }
        .strength-strong { background: #4caf50; width: 100%; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .register-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
                max-width: none;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .role-selection {
                grid-template-columns: 1fr;
            }

            .register-title {
                font-size: 1.8rem;
            }

            .form-input, .form-select {
                padding: 0.8rem 0.8rem 0.8rem 2.5rem;
            }

            .input-icon {
                left: 0.8rem;
            }

            .password-toggle {
                right: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 1.5rem 1rem;
            }

            .register-title {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
    </div>

    <div class="register-container">
        <div class="register-header">
            <div class="logo">
                <i class="fas fa-users"></i>
                <span>ClubMaster</span>
            </div>
            <h1 class="register-title">Create Account</h1>
            <p class="register-subtitle">Join our club management community</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form class="register-form" method="POST" id="registerForm">
            <!-- Personal Information -->
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name<span class="required">*</span></label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-user"></i>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="form-input" 
                            placeholder="Enter first name"
                            value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name<span class="required">*</span></label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-user"></i>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            class="form-input" 
                            placeholder="Enter last name"
                            value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
                            required
                        >
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="form-group">
                <label for="username">Username<span class="required">*</span></label>
                <div style="position: relative;">
                    <i class="input-icon fas fa-at"></i>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input" 
                        placeholder="Choose a username"
                        value="<?php echo htmlspecialchars($username ?? ''); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address<span class="required">*</span></label>
                <div style="position: relative;">
                    <i class="input-icon fas fa-envelope"></i>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <div style="position: relative;">
                    <i class="input-icon fas fa-phone"></i>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        class="form-input" 
                        placeholder="Enter phone number"
                        value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                    >
                </div>
            </div>

            <!-- Password Fields -->
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password<span class="required">*</span></label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Create password"
                            required
                        >
                        <i class="password-toggle fas fa-eye" id="passwordToggle"></i>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password<span class="required">*</span></label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input" 
                            placeholder="Confirm password"
                            required
                        >
                        <i class="password-toggle fas fa-eye" id="confirmPasswordToggle"></i>
                    </div>
                </div>
            </div>

            <!-- Role Selection -->
            <div class="form-group">
                <label>Account Type<span class="required">*</span></label>
                <div class="role-selection">
                    <div class="role-option">
                        <input type="radio" id="role_manager" name="role" value="manager" checked>
                        <label for="role_manager" class="role-label">
                            <i class="role-icon fas fa-user-tie"></i>
                            <div class="role-title">Club Leader</div>
                            <div class="role-desc">Manage club activities</div>
                        </label>
                    </div>
                    <div class="role-option">
                        <input type="radio" id="role_admin" name="role" value="admin">
                        <label for="role_admin" class="role-label">
                            <i class="role-icon fas fa-crown"></i>
                            <div class="role-title">Administrator</div>
                            <div class="role-desc">Full system access</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Terms and Conditions -->
            <div class="terms-group">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                    I agree to the <a href="#" class="terms-link">Terms of Service</a> 
                    and <a href="#" class="terms-link">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" id="registerBtn">
                <i class="fas fa-user-plus"></i>
                Create Account
            </button>

            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i>
                Already have an account? Sign In
            </a>
        </form>

        <div class="register-footer">
            <p>Already have an account? <a href="login.php" class="login-link">Sign in here</a></p>
            <a href="index.html" class="back-home">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
        </div>
    </div>

    <script>
        // Password toggle functionality
        function togglePassword(inputId, toggleId) {
            document.getElementById(toggleId).addEventListener('click', function() {
                const passwordInput = document.getElementById(inputId);
                const passwordToggle = document.getElementById(toggleId);
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordToggle.classList.remove('fa-eye');
                    passwordToggle.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    passwordToggle.classList.remove('fa-eye-slash');
                    passwordToggle.classList.add('fa-eye');
                }
            });
        }

        togglePassword('password', 'passwordToggle');
        togglePassword('confirm_password', 'confirmPasswordToggle');

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;

            // Check password criteria
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;

            // Update strength bar
            strengthBar.className = 'password-strength-bar';
            if (strength >= 2) strengthBar.classList.add('strength-weak');
            if (strength >= 3) strengthBar.classList.add('strength-fair');
            if (strength >= 4) strengthBar.classList.add('strength-good');
            if (strength >= 5) strengthBar.classList.add('strength-strong');
        });

        // Form submission with loading state
        document.getElementById('registerForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('registerBtn');
            submitBtn.classList.add('btn-loading');
            submitBtn.innerHTML = '<div class="spinner"></div> Creating Account...';
        });

        // Input focus effects
        document.querySelectorAll('.form-input, .form-select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentNode.parentNode.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentNode.parentNode.classList.remove('focused');
            });
        });

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });

        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            if (username.length > 0 && username.length < 3) {
                this.style.borderColor = '#ff6b6b';
            } else {
                this.style.borderColor = '';
            }
        });

        // Email validation
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.length > 0 && !emailRegex.test(email)) {
                this.style.borderColor = '#ff6b6b';
            } else {
                this.style.borderColor = '';
            }
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            if (confirmPassword.length > 0 && password !== confirmPassword) {
                this.style.borderColor = '#ff6b6b';
            } else {
                this.style.borderColor = '';
            }
        });

        console.log('📝 Registration page initialized successfully!');
    </script>
</body>
</html>