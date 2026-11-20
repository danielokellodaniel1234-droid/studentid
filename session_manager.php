<?php
// Session manager to handle session security
session_start();

// Set session timeout (30 minutes)
$timeout = 1800; // 30 minutes in seconds

// Check if session is expired
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    // Session expired
    session_unset();
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 300) { // Regenerate every 5 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Check for brute force protection
if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 5) {
    $lockout_time = 900; // 15 minutes
    if (isset($_SESSION['last_attempt']) && (time() - $_SESSION['last_attempt'] < $lockout_time)) {
        die('Too many login attempts. Please try again later.');
    } else {
        // Reset attempts after lockout period
        $_SESSION['login_attempts'] = 0;
    }
}
?>