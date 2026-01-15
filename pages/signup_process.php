<?php
session_start();
require_once '../config/db_connection.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $account_type = isset($_POST['account_type']) ? $_POST['account_type'] : '';
    $course_selection = isset($_POST['course_selection']) ? $_POST['course_selection'] : [];
    
    // Validate input (course_selection only required for students)
    if (empty($name) || empty($email) || empty($account_type)) {
        header("Location: signup.php?error=empty_fields");
        exit();
    }
    
    // Course selection is required for students only
    if ($account_type === 'student') {
        // Ensure course_selection is an array
        if (!is_array($course_selection)) {
            $course_selection = [];
        }
        // Filter out empty values
        $course_selection = array_filter($course_selection);
        
        if (empty($course_selection)) {
            header("Location: signup.php?error=empty_fields");
            exit();
        }
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: signup.php?error=invalid_email");
        exit();
    }
    
    // Validate account type
    if (!in_array($account_type, ['student', 'teacher'])) {
        header("Location: signup.php?error=invalid_account_type");
        exit();
    }
    
    // Connect to database
    $conn = getDBConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        closeDBConnection($conn);
        header("Location: signup.php?error=email_exists");
        exit();
    }
    $stmt->close();
    
    // Generate a unique 8-digit student ID
    $student_id = generateStudentID($conn);
    
    // Generate a temporary password (user should change this on first login)
    // In production, you might want to send this via email
    $temp_password = generateTempPassword();
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    // Insert new user (course_selection can be NULL for teachers)
    // All new users start with 'pending' status and need approval
    $course_selection_value = $account_type === 'student' && !empty($course_selection) ? implode(',', $course_selection) : null;
    $status = 'pending';
    $stmt = $conn->prepare("INSERT INTO users (student_id, name, email, password, account_type, status, course_selection) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $student_id, $name, $email, $hashed_password, $account_type, $status, $course_selection_value);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        // Only enroll students in courses (teachers don't need course enrollment)
        if ($account_type === 'student' && !empty($course_selection)) {
            // Enroll student in all selected courses
            $course_stmt = $conn->prepare("SELECT id FROM courses WHERE course_code = ?");
            $link_stmt = $conn->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (?, ?)");
            
            foreach ($course_selection as $course_code) {
                $course_code = trim($course_code);
                if (empty($course_code)) continue;
                
                $course_stmt->bind_param("s", $course_code);
                $course_stmt->execute();
                $course_result = $course_stmt->get_result();
                
                if ($course_result->num_rows > 0) {
                    $course = $course_result->fetch_assoc();
                    $course_id = $course['id'];
                    
                    // Check if enrollment already exists (prevent duplicates)
                    $check_stmt = $conn->prepare("SELECT id FROM user_courses WHERE user_id = ? AND course_id = ?");
                    $check_stmt->bind_param("ii", $user_id, $course_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows === 0) {
                        // Link user to course
                        $link_stmt->bind_param("ii", $user_id, $course_id);
                        $link_stmt->execute();
                    }
                    $check_stmt->close();
                }
            }
            $course_stmt->close();
            $link_stmt->close();
        }
        
        $stmt->close();
        closeDBConnection($conn);
        
        // Redirect to login with success message
        // In production, you might want to email the ID and temp password
        $id_label = $account_type === 'teacher' ? 'teacher_id' : 'student_id';
        header("Location: login.php?signup=success&" . $id_label . "=" . $student_id . "&temp_password=" . urlencode($temp_password) . "&account_type=" . $account_type);
        exit();
    } else {
        $stmt->close();
        closeDBConnection($conn);
        header("Location: signup.php?error=registration_failed");
        exit();
    }
} else {
    // If not POST request, redirect to signup page
    header("Location: signup.php");
    exit();
}

// Function to generate unique 8-digit student ID
function generateStudentID($conn) {
    do {
        $student_id = rand(10000000, 99999999);
        $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } while ($result->num_rows > 0);
    
    return $student_id;
}

// Function to generate temporary password
function generateTempPassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}
?>
