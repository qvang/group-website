<?php
session_start();
require_once '../config/db_connection.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input
    $student_id = isset($_POST['id']) ? trim($_POST['id']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate input
    if (empty($student_id) || empty($password)) {
        header("Location: login.php?error=empty_fields");
        exit();
    }
    
    // Validate student ID format (8 digits)
    if (!preg_match('/^\d{8}$/', $student_id)) {
        header("Location: login.php?error=invalid_id");
        exit();
    }
    
    // Connect to database
    $conn = getDBConnection();
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, student_id, name, email, password, account_type FROM users WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Password is correct, start session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['account_type'] = $user['account_type'];
            $_SESSION['logged_in'] = true;
            
            // Redirect based on account type
            if ($user['account_type'] === 'teacher') {
                header("Location: dashboard/teacher_dashboard.php");
            } else {
                header("Location: dashboard/dashboard.php");
            }
            exit();
        } else {
            // Invalid password
            header("Location: login.php?error=invalid_credentials");
            exit();
        }
    } else {
        // User not found
        header("Location: login.php?error=invalid_credentials");
        exit();
    }
    
    $stmt->close();
    closeDBConnection($conn);
} else {
    // If not POST request, redirect to login page
    header("Location: login.php");
    exit();
}
?>
