<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kumi University - Student ID Management System</title>
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
            font-size: 12px;
        }

        body * {
            font-size: 12px !important;
        }

        /* Header & Navigation */
        .header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 15px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
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

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 4px;
        }

        .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .login-btn {
            background-color: var(--accent-red);
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
        }

        .login-btn:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            padding: 80px 0;
            background: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.9)), 
                        url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
        }

        .hero-content {
            text-align: left;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h2 {
            font-size: 42px;
            color: var(--primary-blue);
            margin-bottom: 20px;
            font-weight: 700;
        }

        .hero p {
            font-size: 18px;
            color: var(--dark-gray);
            margin-bottom: 30px;
        }

        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
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

        .btn-secondary {
            background-color: white;
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
        }

        .btn-secondary:hover {
            background-color: var(--light-blue);
            transform: translateY(-3px);
        }

        /* Features Section - REDUCED SIZE */
        .features {
            padding: 60px 0;
            background-color: var(--white);
        }

        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title h2 {
            font-size: 36px;
            color: var(--primary-blue);
            margin-bottom: 15px;
        }

        .section-title p {
            color: #666;
            font-size: 18px;
            max-width: 700px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 340px));
            justify-content: center;
            gap: 20px;
        }

        .hero, .features, .how-it-works, .footer, .feature-card, .footer-section {
            text-align: left;
        }

        .feature-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border-top: 4px solid var(--primary-blue);
            text-align: left;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            background-color: var(--light-blue);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }

        .feature-icon i {
            font-size: 25px;
            color: var(--primary-blue);
        }

        .feature-card h3 {
            font-size: 20px;
            color: var(--dark-gray);
            margin-bottom: 12px;
        }

        .feature-card p {
            color: #666;
            margin-bottom: 15px;
            font-size: 15px;
            line-height: 1.5;
        }

        /* How It Works */
        .how-it-works {
            padding: 60px 0;
            background-color: var(--light-gray);
        }

        .steps {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 40px;
            gap: 15px;
        }

        .step {
            flex: 1;
            min-width: 250px;
            text-align: left;
            padding: 25px 15px;
            position: relative;
        }

        .step-number {
            width: 55px;
            height: 55px;
            background-color: var(--primary-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            margin: 0 auto 15px;
        }

        .step h3 {
            font-size: 18px;
            margin-bottom: 12px;
            color: var(--dark-gray);
        }

        .step p {
            color: #666;
            font-size: 15px;
            line-height: 1.5;
        }

        /* Footer - REDUCED SPACING */
        .footer {
            background-color: var(--dark-gray);
            color: white;
            padding: 40px 0 20px;
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

        .mobile-menu-btn {
            display: none;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: white;
            cursor: pointer;
            padding: 8px;
        }

        .mobile-menu-btn i {
            font-size: 20px !important;
        }

        .mobile-nav-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1999;
            border: 0;
            background: rgba(15, 32, 48, .45);
        }

        .mobile-nav-backdrop:not([hidden]) {
            display: block;
        }

        .mobile-nav {
            display: none;
            position: fixed;
            top: 0;
            right: 0;
            width: min(280px, 85vw);
            height: 100%;
            overflow-y: auto;
            padding: 25px;
            background-color: white;
            box-shadow: -5px 0 15px rgba(0, 0, 0, .1);
            z-index: 2000;
            transform: translateX(100%);
            transition: transform .3s ease;
        }

        .mobile-nav.active {
            display: block;
            transform: translateX(0);
        }

        body.mobile-nav-open {
            overflow: hidden;
        }

        .mobile-nav-header button {
            border: 0;
            background: transparent;
            color: var(--dark-gray);
            cursor: pointer;
            padding: 6px;
        }

        .mobile-nav-header button i {
            font-size: 18px !important;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .nav-links {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .hero h2 {
                font-size: 32px;
            }
        }

        @media (max-width: 768px) {
            .hero {
                padding: 60px 0;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
            
            .features, .how-it-works {
                padding: 50px 0;
            }
            
            .section-title {
                margin-bottom: 30px;
            }
            
            .section-title h2 {
                font-size: 28px;
            }
            
            .section-title p {
                font-size: 16px;
            }
            
            .footer-content {
                gap: 25px;
            }
            
            .footer-section {
                margin-bottom: 20px;
            }
        }

        /* Mobile Navigation */
        .mobile-nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .mobile-nav-links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .mobile-nav-links a {
            color: var(--dark-gray);
            text-decoration: none;
            font-size: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
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
            
            <div class="nav-links">
                <a href="main.php">Home</a>
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#faq">FAQ</a>
                <a href="#contact">Contact</a>
                <a href="login.php" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </div>
            
            <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Open menu" aria-controls="mobileNav" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Navigation -->
    <button class="mobile-nav-backdrop" id="mobileNavBackdrop" type="button" aria-label="Close menu" hidden></button>
    <nav class="mobile-nav" id="mobileNav" aria-label="Main navigation" aria-hidden="true">
        <div class="mobile-nav-header">
            <h3>Menu</h3>
            <button id="closeMobileNav" type="button" aria-label="Close menu">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mobile-nav-links">
            <a href="main.php">Home</a>
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact</a>
            <a href="login.php">Login</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <h2>Manage Your Student ID Card Online</h2>
                <p>Apply for new ID cards, track your application status, download digital copies, and manage your student identity with our easy-to-use platform. Secure, fast, and convenient.</p>
                
                <div class="cta-buttons">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-id-card"></i> Apply for ID Card
                    </a>
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-search"></i> Check Application Status
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Our Features</h2>
                <p>Everything you need to manage your student identity in one place</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-id-badge"></i>
                    </div>
                    <h3>Apply for ID Card</h3>
                    <p>Submit your application for a new or replacement student ID card online with just a few clicks.</p>
                    <a href="login.php" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px;">Apply Now</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Track Application</h3>
                    <p>Monitor your ID card application status in real-time from submission to collection.</p>
                    <a href="login.html" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px;">Track Status</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <h3>Digital ID Card</h3>
                    <p>Download a digital version of your ID card for online verification and emergency use.</p>
                    <a href="login.html" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px;">Download</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3>Renewal Reminders</h3>
                    <p>Get automated notifications when your ID card is about to expire and needs renewal.</p>
                    <a href="login.html" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px;">Set Reminders</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <h3>Update Information</h3>
                    <p>Easily update your personal information, contact details, or upload a new photo.</p>
                    <a href="login.html" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px;">Update Profile</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h3>Payment History</h3>
                    <p>View and download receipts for all your ID card payments and transactions.</p>
                    <a href="login.html" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px;">View Payments</a>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-title">
                <h2>How It Works</h2>
                <p>Get your student ID card in 4 simple steps</p>
            </div>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create Account</h3>
                    <p>Register with your student details and verify your email address.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Apply Online</h3>
                    <p>Fill the application form and upload required documents & photo.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Make Payment</h3>
                    <p>Pay the ID card fee securely through our online payment gateway.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Collect ID Card</h3>
                    <p>Track your application and collect your ID card when ready.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Kumi University</h3>
                    <p>Student ID Management System</p>
                    <p>Providing secure and efficient student identity management solutions since 2023.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="main.html">Home</a>
                    <a href="#features">Features</a>
                    <a href="#how-it-works">How It Works</a>
                    <a href="#faq">FAQ</a>
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
        // Mobile navigation
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileNav = document.getElementById('mobileNav');
        const closeMobileNav = document.getElementById('closeMobileNav');
        const mobileNavBackdrop = document.getElementById('mobileNavBackdrop');

        function setMobileNavOpen(open) {
            mobileNav.classList.toggle('active', open);
            mobileNav.setAttribute('aria-hidden', open ? 'false' : 'true');
            mobileMenuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            mobileMenuBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            mobileNavBackdrop.hidden = !open;
            document.body.classList.toggle('mobile-nav-open', open);
            if (open) {
                closeMobileNav.focus();
            } else {
                mobileMenuBtn.focus();
            }
        }
        
        mobileMenuBtn.addEventListener('click', () => {
            setMobileNavOpen(mobileMenuBtn.getAttribute('aria-expanded') !== 'true');
        });
        
        closeMobileNav.addEventListener('click', () => {
            setMobileNavOpen(false);
        });

        mobileNavBackdrop.addEventListener('click', () => {
            setMobileNavOpen(false);
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && mobileMenuBtn.getAttribute('aria-expanded') === 'true') {
                setMobileNavOpen(false);
            }
        });
        
        // Close mobile nav when clicking a link
        document.querySelectorAll('.mobile-nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                setMobileNavOpen(false);
            });
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>