<?php
// Authentication helper functions

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_type'] !== 'admin') {
        header('Location: access_denied.php');
        exit;
    }
}

function requireStaff() {
    requireLogin();
    if (!in_array($_SESSION['user_type'], ['admin', 'staff'])) {
        header('Location: access_denied.php');
        exit;
    }
}

function getUserInfo() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'student_id' => $_SESSION['student_id'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'user_type' => $_SESSION['user_type'] ?? null
        ];
    }
    return null;
}

function logActivity($activity, $details = '') {
    global $conn;
    
    if (isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
        $sql = "INSERT INTO activity_logs (user_id, activity, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $stmt->bind_param("issss", $user_id, $activity, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}