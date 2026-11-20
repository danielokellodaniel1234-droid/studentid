<?php
// Start session at the top
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "studentsID";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// Set charset
$conn->set_charset("utf8");

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if form is submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input
    $login_id = isset($_POST['login_id']) ? trim($_POST['login_id']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $user_type = isset($_POST['userType']) ? $_POST['userType'] : 'student';
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate inputs
    if (empty($login_id) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Please fill in all required fields'
        ]);
        exit;
    }

    if (in_array($user_type, ['admin', 'staff'], true)) {
        $staff_sql = "SELECT id, username, full_name, email, password_hash, role
                      FROM staff_accounts
                      WHERE (username = ? OR email = ?)
                        AND role = ? AND status = 'active'
                      LIMIT 1";
        $staff_stmt = $conn->prepare($staff_sql);
        $staff_stmt->bind_param('sss', $login_id, $login_id, $user_type);
        $staff_stmt->execute();
        $staff_user = $staff_stmt->get_result()->fetch_assoc();
        $staff_stmt->close();

        if (!$staff_user || !password_verify($password, $staff_user['password_hash'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid credentials. Please check your username or email and password.'
            ]);
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$staff_user['id'];
        $_SESSION['student_id'] = '';
        $_SESSION['full_name'] = $staff_user['full_name'];
        $_SESSION['email'] = $staff_user['email'];
        $_SESSION['phone'] = '';
        $_SESSION['program'] = '';
        $_SESSION['user_type'] = $staff_user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'user' => [
                'id' => (int)$staff_user['id'],
                'student_id' => '',
                'full_name' => $staff_user['full_name'],
                'email' => $staff_user['email']
            ],
            'redirect' => $staff_user['role'] === 'admin'
                ? 'dashboard.php'
                : 'staff_dashboard.php'
        ]);
        exit;
    }

    if ($user_type !== 'student') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Choose a valid account type.'
        ]);
        exit;
    }
    
    // Check if login_id is email or student_id
    $is_email = filter_var($login_id, FILTER_VALIDATE_EMAIL);
    
    // Prepare SQL query based on whether login_id is email or student_id
    if ($is_email) {
        $sql = "SELECT id, student_id, full_name, email, phone, program, password, status, 
                       registration_date, last_login 
                FROM students 
                WHERE email = ? AND status = 'active'";
    } else {
        $sql = "SELECT id, student_id, full_name, email, phone, program, password, status, 
                       registration_date, last_login 
                FROM students 
                WHERE student_id = ? AND status = 'active'";
    }
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $conn->error
        ]);
        exit;
    }
    
    $stmt->bind_param("s", $login_id);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Database query failed: ' . $stmt->error
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Password is correct
            
            // Update last login
            $update_sql = "UPDATE students SET last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Log login activity (only if table exists)
            try {
                $log_sql = "INSERT INTO login_logs (student_id, login_time, ip_address, user_agent, success) 
                           VALUES (?, NOW(), ?, ?, ?)";
                $log_stmt = $conn->prepare($log_sql);
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $success = true;
                $log_stmt->bind_param("sssi", $user['student_id'], $ip_address, $user_agent, $success);
                $log_stmt->execute();
                $log_stmt->close();
            } catch (Exception $e) {
                // Log table might not exist, continue anyway
                error_log("Login log error: " . $e->getMessage());
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['phone'] = $user['phone'] ?? '';
            $_SESSION['program'] = $user['program'] ?? '';
            $_SESSION['user_type'] = $user_type;
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // Set cookie if remember me is checked (30 days)
            if ($remember) {
                $cookie_name = "remember_user";
                $cookie_value = $user['id'] . ":" . hash('sha256', $user['password']);
                setcookie($cookie_name, $cookie_value, time() + (30 * 24 * 60 * 60), "/", "", false, true);
                
                // Store in database for validation (if table exists)
                try {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
                    
                    $token_sql = "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE token = ?, expires_at = ?";
                    $token_stmt = $conn->prepare($token_sql);
                    $token_stmt->bind_param("issss", $user['id'], $token, $expires, $token, $expires);
                    $token_stmt->execute();
                    $token_stmt->close();
                    
                    setcookie("remember_token", $token, time() + (30 * 24 * 60 * 60), "/", "", false, true);
                } catch (Exception $e) {
                    // Remember tokens table might not exist, skip it
                    error_log("Remember token error: " . $e->getMessage());
                }
            }
            
            // Prepare user data for response
            $user_data = [
                'id' => $user['id'],
                'student_id' => $user['student_id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'] ?? '',
                'program' => $user['program'] ?? '',
                'registration_date' => $user['registration_date']
            ];
            
            // Determine redirect URL based on user type
            $redirect_url = 'dashboard.php';
            switch ($user_type) {
                case 'admin':
                    $redirect_url = 'admin_dashboard.php';
                    break;
                case 'staff':
                    $redirect_url = 'staff_dashboard.php';
                    break;
                case 'student':
                default:
                    $redirect_url = 'student_dashboard.php';
                    break;
            }
            
            // Check if dashboard files exist, fallback to main dashboard
            if (!file_exists($redirect_url)) {
                $redirect_url = 'dashboard.php';
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Login successful!',
                'user' => $user_data,
                'redirect' => $redirect_url
            ]);
            
        } else {
            // Log failed login attempt (only if table exists)
            try {
                $log_sql = "INSERT INTO login_logs (student_id, login_time, ip_address, user_agent, success) 
                           VALUES (?, NOW(), ?, ?, ?)";
                $log_stmt = $conn->prepare($log_sql);
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $success = false;
                $student_id = $is_email ? ($user['student_id'] ?? 'unknown') : $login_id;
                $log_stmt->bind_param("sssi", $student_id, $ip_address, $user_agent, $success);
                $log_stmt->execute();
                $log_stmt->close();
            } catch (Exception $e) {
                // Log table might not exist, continue anyway
                error_log("Failed login log error: " . $e->getMessage());
            }
            
            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid credentials. Please check your ID/email and password.'
            ]);
        }
    } else {
        // User not found
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'message' => 'Account not found or inactive. Please check your credentials or contact support.'
        ]);
    }
    
    $stmt->close();
    
} else {
    // Invalid request method
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method. Please use POST.'
    ]);
}

$conn->close();
exit;
?>