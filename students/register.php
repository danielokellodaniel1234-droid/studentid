<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Kumi University ID System</title>
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

        /* Register Container */
        .register-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .register-box {
            background-color: white;
            border-radius: 10px;
            width: 100%;
            max-width: 520px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-height: 85vh;
            overflow-y: auto;
            margin: 0 auto;
            text-align: left;
        }

        /* Custom scrollbar */
        .register-box::-webkit-scrollbar {
            width: 8px;
        }

        .register-box::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .register-box::-webkit-scrollbar-thumb {
            background: var(--primary-blue);
            border-radius: 4px;
        }

        .register-box::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-blue);
        }

        .register-header {
            text-align: left;
            margin-bottom: 30px;
        }

        .register-header h2 {
            color: var(--primary-blue);
            font-size: 32px;
            margin-bottom: 10px;
        }

        .register-header p {
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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

        .login-link {
            margin-top: 25px;
            text-align: left;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .login-link p {
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

        /* Form Sections */
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .form-section h3 {
            color: var(--primary-blue);
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .form-section h3 i {
            font-size: 18px;
        }

        /* Validation styles */
        .error-message {
            color: var(--accent-red);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .error {
            border-color: var(--accent-red) !important;
        }

        .success {
            border-color: #27ae60 !important;
        }

        .password-strength {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
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
            .register-box {
                padding: 30px 20px;
            }
            
            .register-header h2 {
                font-size: 28px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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

    <!-- Register Section -->
    <section class="register-container">
        <div class="container">
            <div class="register-box">
                <div class="register-header">
                    <h2>Create New Account</h2>
                    <p>Use your official student registration number and full name. Your enrollment will be checked against the university registry.</p>
                </div>
                
                <form id="registerForm" action="register_process.php" method="POST">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                        
                        <div class="form-group">
                            <label for="regId">Student registration number *</label>
                            <input type="text" id="regId" name="regId" placeholder="Enter your registration number" required>
                            <div class="error-message" id="regIdError"></div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fullName">Full Name *</label>
                                <input type="text" id="fullName" name="fullName" placeholder="Enter your full name" required>
                                <div class="error-message" id="fullNameError"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" placeholder="Enter your email" required>
                            <div class="error-message" id="emailError"></div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
                                <div class="error-message" id="phoneError"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="program">Program/Course</label>
                                <input type="text" id="program" name="program" placeholder="e.g., Computer Science">
                            </div>
                        </div>
                    </div>

                    <!-- Account Security -->
                    <div class="form-section">
                        <h3><i class="fas fa-lock"></i> Account Security</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="regPassword">Create Password *</label>
                                <input type="password" id="regPassword" name="regPassword" placeholder="Create a password" required>
                                <div class="error-message" id="passwordError"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirmPassword">Confirm Password *</label>
                                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                                <div class="error-message" id="confirmPasswordError"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="securityQuestion">Security Question</label>
                            <select id="securityQuestion" name="securityQuestion">
                                <option value="">Select a security question</option>
                                <option value="pet">What is your pet's name?</option>
                                <option value="school">What was your first school?</option>
                                <option value="city">In what city were you born?</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="securityAnswer">Security Answer</label>
                            <input type="text" id="securityAnswer" name="securityAnswer" placeholder="Your answer">
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="form-group">
                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                            <input type="checkbox" id="terms" name="terms" required style="width: auto; margin-top: 5px;">
                            <label for="terms" style="margin-bottom: 0;">
                                I agree to the <a href="#" style="color: var(--primary-blue);">Terms and Conditions</a> and <a href="#" style="color: var(--primary-blue);">Privacy Policy</a> of Kumi University Student ID Management System
                            </label>
                        </div>
                        <div class="error-message" id="termsError"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="login-link">
                    <p>Already have an account?</p>
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> Login to Your Account
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
        function validateForm() {
            let isValid = true;
            
            // Clear previous errors
            clearErrors();
            
            // Get form values
            const regId = document.getElementById('regId').value.trim();
            const fullName = document.getElementById('fullName').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('regPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const terms = document.getElementById('terms').checked;
            
            // Validate Student ID
            if (!regId) {
                showError('regIdError', 'Student ID is required');
                document.getElementById('regId').classList.add('error');
                isValid = false;
            } else if (regId.length < 3) {
                showError('regIdError', 'Student ID must be at least 3 characters');
                document.getElementById('regId').classList.add('error');
                isValid = false;
            } else {
                document.getElementById('regId').classList.remove('error');
            }
            
            // Validate Full Name
            if (!fullName) {
                showError('fullNameError', 'Full name is required');
                document.getElementById('fullName').classList.add('error');
                isValid = false;
            } else if (fullName.length < 3) {
                showError('fullNameError', 'Full name must be at least 3 characters');
                document.getElementById('fullName').classList.add('error');
                isValid = false;
            } else {
                document.getElementById('fullName').classList.remove('error');
            }
            
            // Validate Email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email) {
                showError('emailError', 'Email address is required');
                document.getElementById('email').classList.add('error');
                isValid = false;
            } else if (!emailRegex.test(email)) {
                showError('emailError', 'Please enter a valid email address');
                document.getElementById('email').classList.add('error');
                isValid = false;
            } else {
                document.getElementById('email').classList.remove('error');
            }
            
            // Validate Phone Number
            const phoneRegex = /^[\d\s\-\+\(\)]{10,}$/;
            if (!phone) {
                showError('phoneError', 'Phone number is required');
                document.getElementById('phone').classList.add('error');
                isValid = false;
            } else if (!phoneRegex.test(phone)) {
                showError('phoneError', 'Please enter a valid phone number (at least 10 digits)');
                document.getElementById('phone').classList.add('error');
                isValid = false;
            } else {
                document.getElementById('phone').classList.remove('error');
            }
            
            // Validate Password
            if (!password) {
                showError('passwordError', 'Password is required');
                document.getElementById('regPassword').classList.add('error');
                isValid = false;
            } else if (password.length < 8) {
                showError('passwordError', 'Password must be at least 8 characters long');
                document.getElementById('regPassword').classList.add('error');
                isValid = false;
            } else if (!/(?=.*[A-Z])(?=.*[0-9])/.test(password)) {
                showError('passwordError', 'Password must contain at least one uppercase letter and one number');
                document.getElementById('regPassword').classList.add('error');
                isValid = false;
            } else {
                document.getElementById('regPassword').classList.remove('error');
            }
            
            // Validate Confirm Password
            if (!confirmPassword) {
                showError('confirmPasswordError', 'Please confirm your password');
                document.getElementById('confirmPassword').classList.add('error');
                isValid = false;
            } else if (password !== confirmPassword) {
                showError('confirmPasswordError', 'Passwords do not match');
                document.getElementById('confirmPassword').classList.add('error');
                isValid = false;
            } else {
                document.getElementById('confirmPassword').classList.remove('error');
            }
            
            // Validate Terms
            if (!terms) {
                showError('termsError', 'You must accept the terms and conditions');
                isValid = false;
            }
            
            return isValid;
        }
        
        // Helper function to show error messages
        function showError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            errorElement.textContent = message;
            errorElement.style.display = 'block';
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
        }
        
        // Real-time validation
        document.getElementById('regId').addEventListener('blur', function() {
            const regId = this.value.trim();
            if (regId && regId.length < 3) {
                showError('regIdError', 'Student ID must be at least 3 characters');
                this.classList.add('error');
            } else if (regId) {
                document.getElementById('regIdError').style.display = 'none';
                this.classList.remove('error');
            }
        });
        
        document.getElementById('fullName').addEventListener('blur', function() {
            const fullName = this.value.trim();
            if (fullName && fullName.length < 3) {
                showError('fullNameError', 'Full name must be at least 3 characters');
                this.classList.add('error');
            } else if (fullName) {
                document.getElementById('fullNameError').style.display = 'none';
                this.classList.remove('error');
            }
        });
        
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && !emailRegex.test(email)) {
                showError('emailError', 'Please enter a valid email address');
                this.classList.add('error');
            } else if (email) {
                document.getElementById('emailError').style.display = 'none';
                this.classList.remove('error');
            }
        });
        
        document.getElementById('phone').addEventListener('blur', function() {
            const phone = this.value.trim();
            const phoneRegex = /^[\d\s\-\+\(\)]{10,}$/;
            if (phone && !phoneRegex.test(phone)) {
                showError('phoneError', 'Please enter a valid phone number (at least 10 digits)');
                this.classList.add('error');
            } else if (phone) {
                document.getElementById('phoneError').style.display = 'none';
                this.classList.remove('error');
            }
        });
        
        document.getElementById('regPassword').addEventListener('blur', function() {
            const password = this.value;
            if (password && password.length < 8) {
                showError('passwordError', 'Password must be at least 8 characters');
                this.classList.add('error');
            } else if (password && !/(?=.*[A-Z])(?=.*[0-9])/.test(password)) {
                showError('passwordError', 'Password must contain at least one uppercase letter and one number');
                this.classList.add('error');
            } else if (password) {
                document.getElementById('passwordError').style.display = 'none';
                this.classList.remove('error');
            }
        });
        
        document.getElementById('confirmPassword').addEventListener('blur', function() {
            const password = document.getElementById('regPassword').value;
            const confirmPassword = this.value;
            if (confirmPassword && password !== confirmPassword) {
                showError('confirmPasswordError', 'Passwords do not match');
                this.classList.add('error');
            } else if (confirmPassword) {
                document.getElementById('confirmPasswordError').style.display = 'none';
                this.classList.remove('error');
            }
        });
        
        // Password strength check (enhanced)
        document.getElementById('regPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.createElement('div');
            
            // Remove previous indicator if exists
            const existingIndicator = this.parentNode.querySelector('.password-strength');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            if (password.length > 0) {
                let strength = 'Weak';
                let color = '#e74c3c';
                let score = 0;
                
                // Length check
                if (password.length >= 8) score++;
                if (password.length >= 12) score++;
                
                // Complexity checks
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;
                
                // Determine strength
                if (score >= 4) {
                    strength = 'Strong';
                    color = '#27ae60';
                } else if (score >= 3) {
                    strength = 'Medium';
                    color = '#f39c12';
                }
                
                strengthIndicator.className = 'password-strength';
                strengthIndicator.innerHTML = `Password strength: <span style="color: ${color}; font-weight: bold;">${strength}</span>`;
                strengthIndicator.style.fontSize = '12px';
                strengthIndicator.style.marginTop = '5px';
                strengthIndicator.style.color = '#666';
                
                this.parentNode.appendChild(strengthIndicator);
            }
        });
        
        // Form submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return false;
            }
            
            // Show loading indicator
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            
            // Simulate AJAX call or allow form to submit normally
            setTimeout(() => {
                // If you want to use AJAX instead of form submission, uncomment below
                /*
                const formData = new FormData(this);
                
                fetch('register_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Registration successful!');
                        window.location.href = 'login.php';
                    } else {
                        alert('Registration failed: ' + data.message);
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('loading');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                });
                */
                
                // For now, allow form to submit normally
                this.submit();
            }, 1000);
        });
        
        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            
            // Format as Ugandan phone number if starts with 256
            if (value.startsWith('256') && value.length > 9) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d+)/, '+$1 $2 $3 $4');
            } else if (value.length > 6) {
                value = value.replace(/(\d{3})(\d{3})(\d+)/, '$1 $2 $3');
            } else if (value.length > 3) {
                value = value.replace(/(\d{3})(\d+)/, '$1 $2');
            }
            
            this.value = value;
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
    </script>
</body>
</html>