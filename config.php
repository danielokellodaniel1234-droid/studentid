<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'studentsID');

// Set this environment variable to the public ERP application URL used in card verification barcodes.
define('STUDENT_ID_PUBLIC_URL', rtrim((string)(getenv('STUDENT_ID_PUBLIC_URL') ?: ''), '/'));

// Session configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('SESSION_REGENERATE', 300); // 5 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Security settings
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL', false);

// Create connection
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8");

// Start session with security settings
session_start([
    'cookie_lifetime' => SESSION_TIMEOUT,
    'cookie_secure' => isset($_SERVER['HTTPS']), // Only send over HTTPS
    'cookie_httponly' => true, // Prevent JavaScript access
    'use_strict_mode' => true, // Prevent session fixation
    'use_only_cookies' => true, // Prevent session ID in URL
    'cookie_samesite' => 'Strict' // CSRF protection
]);
?>