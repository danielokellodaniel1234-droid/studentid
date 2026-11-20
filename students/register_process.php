<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/student_registry.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
ini_set('display_errors', '0');

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize input data
    $student_id = trim((string)($_POST['regId'] ?? ''));
    $full_name = trim((string)($_POST['fullName'] ?? ''));
    $gender = trim((string)($_POST['gender'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $program = trim((string)($_POST['program'] ?? ''));
    $password = (string)($_POST['regPassword'] ?? '');
    $confirm_password = (string)($_POST['confirmPassword'] ?? '');
    $security_question = trim((string)($_POST['securityQuestion'] ?? ''));
    $security_answer = trim((string)($_POST['securityAnswer'] ?? ''));
    $security_questions = ['pet', 'school', 'city'];
    
    // Validation flag
    $valid = true;
    $errors = [];
    
    // Validate required fields
    if (empty($student_id)) {
        $errors[] = "Student ID is required";
        $valid = false;
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
        $valid = false;
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
        $valid = false;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
        $valid = false;
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
        $valid = false;
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
        $valid = false;
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
        $valid = false;
    }
    
    // Check password strength
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
        $valid = false;
    }

    if (($security_question === '') !== ($security_answer === '')
        || ($security_question !== '' && !in_array($security_question, $security_questions, true))
        || strlen($security_answer) > 190) {
        $errors[] = "Choose a security question and enter an answer, or leave both blank.";
        $valid = false;
    }
    
    // Check if terms are accepted
    if (!isset($_POST['terms'])) {
        $errors[] = "You must accept the terms and conditions";
        $valid = false;
    }
    
    // Verify the identity against the university-maintained enrollment roster.
    if ($valid) {
        $registryStudent = studentRegistryVerify($conn, $student_id, $full_name);
        if (!$registryStudent) {
            $errors[] = "The registration number and full name do not match an active university student record. Contact the administrator if your record is missing.";
            $valid = false;
        } else {
            $full_name = (string)$registryStudent['full_name'];
            $registryProgram = trim((string)$registryStudent['programme']);
            if ($registryProgram === '') {
                $registryProgram = trim((string)$registryStudent['course']);
            }
            if ($registryProgram !== '') {
                $program = $registryProgram;
            }
        }
    }

    // Check if student ID already exists
    if ($valid) {
        $check_sql = "SELECT id FROM students WHERE student_id = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $student_id, $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $errors[] = "Student ID or Email already exists";
            $valid = false;
        }
        $check_stmt->close();
    }
    
    // If validation passes, insert into database
    if ($valid) {
        // Hash passwords and optional security answers before storing them.
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $normalized_security_answer = function_exists('mb_strtolower')
            ? mb_strtolower($security_answer, 'UTF-8')
            : strtolower($security_answer);
        $hashed_security_answer = $security_answer === ''
            ? null
            : password_hash($normalized_security_answer, PASSWORD_DEFAULT);
        
        // Prepare SQL statement
        $sql = "INSERT INTO students (student_id, full_name, gender, email, phone, program, password, security_question, security_answer, registration_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Bind parameters
            $stmt->bind_param("sssssssss",
                $student_id, 
                $full_name, 
                $gender, 
                $email, 
                $phone, 
                $program, 
                $hashed_password, 
                $security_question,
                $hashed_security_answer
            );
            
            // Execute statement
            if ($stmt->execute()) {
                $success_message = "Registration successful!";
                $last_id = $conn->insert_id;
                
                // Redirect to success page or login page after 2 seconds
                header("refresh:2;url=login.php");
            } else {
                $errors[] = "Error: " . $stmt->error;
            }
            
            $stmt->close();
        } else {
            $errors[] = "Registration could not be completed. Please contact the system administrator.";
            error_log("Student registration prepare failed: " . $conn->error);
        }
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Result</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1a5f9e, #2c80c6);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .result-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 440px;
            width: 100%;
            text-align: left;
        }
        
        .success {
            color: #27ae60;
        }
        
        .error {
            color: #e74c3c;
        }
        
        .message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
        }
        
        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        ul {
            text-align: left;
            margin: 10px 0;
            padding-left: 0;
            list-style-position: inside;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #1a5f9e;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #2c80c6;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <?php if (isset($success_message)): ?>
            <h2 class="success">Registration Successful!</h2>
            <div class="message success-message">
                <p><?php echo $success_message; ?></p>
                <p>Redirecting to login page...</p>
            </div>
            <a href="login.php" class="btn">Go to Login</a>
            
        <?php elseif (isset($errors) && !empty($errors)): ?>
            <h2 class="error">Registration Failed</h2>
            <div class="message error-message">
                <p>Please fix the following errors:</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <a href="javascript:history.back()" class="btn">Go Back</a>
            
        <?php else: ?>
            <h2>No Form Submission</h2>
            <p>Please use the registration form to submit your data.</p>
            <a href="register.html" class="btn">Go to Registration</a>
        <?php endif; ?>
    </div>
</body>
</html>