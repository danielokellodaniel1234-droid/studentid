<?php
// Start session at the VERY TOP of the file
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kumi University ID System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-blue: #1a5f9e;
            --secondary-blue: #2c80c6;
            --accent-red: #e74c3c;
            --light-blue: #e6f2ff;
            --dark-gray: #2c3e50;
            --light-gray: #f5f5f5;
            --white: #ffffff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--light-blue);
            color: var(--dark-gray);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 15px 0;
            box-shadow: var(--shadow);
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo img {
            height: 50px;
            width: auto;
        }

        .logo-text h1 {
            font-size: 24px;
            font-weight: 700;
        }

        .logo-text p {
            font-size: 14px;
            opacity: 0.9;
        }

        .back-home {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .back-home:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Login Container */
        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .login-box {
            background-color: white;
            border-radius: 10px;
            width: 100%;
            max-width: 440px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            margin: 0 auto;
            text-align: left;
        }

        .login-header {
            text-align: left;
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: var(--primary-blue);
            font-size: 32px;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 15px;
            text-align: left;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            text-align: left;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-blue);
        }

        .btn {
            padding: 15px 35px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            justify-content: center;
            margin-top: 15px;
        }

        .btn-primary {
            background-color: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-blue);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(26, 95, 158, 0.3);
        }

        .form-footer {
            margin-top: 20px;
            text-align: left;
        }

        .form-footer p {
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .form-footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 14px;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .register-link {
            margin-top: 25px;
            text-align: left;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .register-link p {
            color: #666;
            margin-bottom: 10px;
        }

        .btn-secondary {
            background-color: white;
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
        }

        .btn-secondary:hover {
            background-color: var(--light-blue);
            transform: translateY(-3px);
        }

        /* Error/Success Messages */
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            color: var(--accent-red);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .error {
            border-color: var(--accent-red) !important;
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Footer */
        .footer {
            background-color: var(--dark-gray);
            color: white;
            padding: 40px 0 20px;
            margin-top: auto;
            text-align: left;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .footer-section h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: white;
            text-align: left;
        }

        .footer-section p, .footer-section a {
            color: #bbb;
            margin-bottom: 6px;
            display: block;
            text-decoration: none;
            font-size: 14px;
            line-height: 1.4;
        }

        .footer-section a:hover {
            color: white;
        }

        .footer-section i {
            width: 20px;
            margin-right: 8px;
        }

        .copyright {
            text-align: left;
            padding-top: 15px;
            border-top: 1px solid #444;
            color: #bbb;
            font-size: 12px;
            line-height: 1.3;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-box {
                padding: 30px 20px;
            }
            
            .login-header h2 {
                font-size: 28px;
            }
            
            .footer-content {
                gap: 25px;
            }
            
            .footer-section {
                margin-bottom: 20px;
            }
        }

        @media (max-width: 992px) {
            .mobile-menu-btn {
                display: block;
                background: none;
                border: none;
                color: white;
                font-size: 24px;
                cursor: pointer;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container nav-container">
            <div class="logo">
                <img src="KU Logo.png" alt="Kumi University Logo">
                <div class="logo-text">
                    <h1>Kumi University</h1>
                    <p>Student ID Management System</p>
                </div>
            </div>
            
            <a href="main.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </header>

    <!-- Login Section -->
    <section class="login-container">
        <div class="container">
            <div class="login-box">
                <div class="login-header">
                    <h2>Login to Your Account</h2>
                    <p>Access your student ID management dashboard</p>
                </div>
                
                <!-- Error/Success Messages -->
                <?php
                // Check for session messages
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
                    unset($_SESSION['error']);
                }
                if (isset($_SESSION['success'])) {
                    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
                    unset($_SESSION['success']);
                }
                
                // Check for GET parameters (for redirects from other pages)
                if (isset($_GET['error'])) {
                    $error_messages = [
                        'invalid' => 'Invalid credentials. Please check your ID/email and password.',
                        'notfound' => 'Account not found or inactive.',
                        'expired' => 'Your session has expired. Please login again.',
                        'required' => 'Please login to access this page.'
                    ];
                    $error_key = $_GET['error'];
                    $error_message = isset($error_messages[$error_key]) ? $error_messages[$error_key] : 'An error occurred.';
                    echo '<div class="alert alert-error">' . htmlspecialchars($error_message) . '</div>';
                }
                
                if (isset($_GET['success'])) {
                    $success_messages = [
                        'registered' => 'Registration successful! Please login with your credentials.',
                        'logout' => 'You have been successfully logged out.',
                        'password_reset' => 'Password reset successful. Please login with your new password.'
                    ];
                    $success_key = $_GET['success'];
                    $success_message = isset($success_messages[$success_key]) ? $success_messages[$success_key] : 'Success!';
                    echo '<div class="alert alert-success">' . htmlspecialchars($success_message) . '</div>';
                }
                ?>
                
                <div id="formMessages" class="alert" style="display: none;"></div>
                
                <form id="loginForm" action="login_process.php" method="POST">
                    <div class="form-group">
                        <label for="login_id">Student/Staff ID or Email *</label>
                        <input type="text" id="login_id" name="login_id" placeholder="Enter your ID number or email" required>
                        <div class="error-message" id="loginIdError"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <div class="error-message" id="passwordError"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="userType">Login As</label>
                        <select id="userType" name="userType">
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="remember" name="remember" style="width: auto;">
                            <label for="remember" style="margin-bottom: 0; font-size: 14px;">Remember me</label>
                        </div>
                        <a href="forgot_password.php" style="font-size: 14px;">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="register-link">
                    <p>Don't have an account?</p>
                    <a href="register.php" class="btn btn-secondary">
                        <i class="fas fa-user-plus"></i> Create New Account
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Kumi University</h3>
                    <p>Student ID Management System</p>
                    <p>Providing secure and efficient student identity management solutions since 2023.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="main.php">Home</a>
                    <a href="#">Features</a>
                    <a href="#">How It Works</a>
                    <a href="#">FAQ</a>
                    <a href="#">Privacy Policy</a>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Kumi University, Main Campus</p>
                    <p><i class="fas fa-phone"></i> +256 123 456 789</p>
                    <p><i class="fas fa-envelope"></i> idcard@kumiuniversity.ac.ug</p>
                    <p><i class="fas fa-clock"></i> Mon-Fri: 8:00 AM - 5:00 PM</p>
                </div>
                
                <div class="footer-section">
                    <h3>Need Help?</h3>
                    <p>Visit our help center or contact our support team for assistance with your ID card.</p>
                    <a href="#" class="btn btn-secondary" style="margin-top: 8px; padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-headset"></i> Get Support
                    </a>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; 2023 Kumi University - Student ID Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Enhanced form validation
        function validateLoginForm() {
            let isValid = true;
            
            // Clear previous errors
            clearErrors();
            
            // Get form values
            const loginId = document.getElementById('login_id').value.trim();
            const password = document.getElementById('password').value;
            
            // Validate Login ID
            if (!loginId) {
                showError('loginIdError', 'Student ID or Email is required');
                document.getElementById('login_id').classList.add('error');
                isValid = false;
            } else {
                document.getElementById('login_id').classList.remove('error');
            }
            
            // Validate Password
            if (!password) {
                showError('passwordError', 'Password is required');
                document.getElementById('password').classList.add('error');
                isValid = false;
            } else {
                document.getElementById('password').classList.remove('error');
            }
            
            return isValid;
        }
        
        // Helper function to show error messages
        function showError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }
        
        // Helper function to clear all errors
        function clearErrors() {
            const errorElements = document.querySelectorAll('.error-message');
            errorElements.forEach(element => {
                element.textContent = '';
                element.style.display = 'none';
            });
            
            const inputElements = document.querySelectorAll('input, select');
            inputElements.forEach(element => {
                element.classList.remove('error');
            });
            
            // Hide form messages
            const formMessages = document.getElementById('formMessages');
            if (formMessages) {
                formMessages.style.display = 'none';
                formMessages.className = 'alert';
                formMessages.textContent = '';
            }
        }
        
        // Show message
        function showMessage(type, message) {
            const formMessages = document.getElementById('formMessages');
            if (formMessages) {
                formMessages.textContent = message;
                formMessages.className = `alert alert-${type}`;
                formMessages.style.display = 'block';
                
                // Auto-hide success messages after 3 seconds
                if (type === 'success') {
                    setTimeout(() => {
                        formMessages.style.display = 'none';
                    }, 3000);
                }
            }
        }
        
        // Real-time validation
        document.getElementById('login_id').addEventListener('blur', function() {
            const loginId = this.value.trim();
            if (!loginId) {
                showError('loginIdError', 'Student ID or Email is required');
                this.classList.add('error');
            } else {
                document.getElementById('loginIdError').style.display = 'none';
                this.classList.remove('error');
            }
        });
        
        document.getElementById('password').addEventListener('blur', function() {
            const password = this.value;
            if (!password) {
                showError('passwordError', 'Password is required');
                this.classList.add('error');
            } else {
                document.getElementById('passwordError').style.display = 'none';
                this.classList.remove('error');
            }
        });
        
        // Form submission with AJAX
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateLoginForm()) {
                return false;
            }
            
            // Show loading indicator
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            
            // Collect form data
            const formData = new FormData(this);
            
            // Send AJAX request
            fetch('login_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is JSON
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    return response.json();
                } else {
                    // If not JSON, get text and try to parse
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid server response');
                        }
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    showMessage('success', 'Login successful! Redirecting...');
                    
                    // Store user data in localStorage if remember me is checked
                    const remember = document.getElementById('remember').checked;
                    if (remember && data.user) {
                        localStorage.setItem('remembered_id', data.user.student_id || data.user.email);
                        localStorage.setItem('user_name', data.user.full_name);
                    }
                    
                    // Redirect after delay
                    setTimeout(() => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.href = 'dashboard.php';
                        }
                    }, 1500);
                } else {
                    showMessage('error', data.message || 'Login failed. Please try again.');
                    
                    // Reset button
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'An error occurred. Please try again.');
                
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            });
            
            // Fallback: If fetch fails, submit normally
            return true;
        });
        
        // Clear error when user starts typing
        const inputs = document.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('error');
                const errorId = this.id + 'Error';
                const errorElement = document.getElementById(errorId);
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            });
        });
        
        // Check for remembered login
        document.addEventListener('DOMContentLoaded', function() {
            const rememberedId = localStorage.getItem('remembered_id');
            if (rememberedId) {
                document.getElementById('login_id').value = rememberedId;
                document.getElementById('remember').checked = true;
            }
            
            // Auto-focus on login ID field
            const loginIdField = document.getElementById('login_id');
            if (loginIdField && !loginIdField.value) {
                loginIdField.focus();
            }
            
            // Hide any session messages after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert:not(#formMessages)');
                alerts.forEach(alert => {
                    alert.style.display = 'none';
                });
            }, 5000);
        });
    </script>
</body>
</html>