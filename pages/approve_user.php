<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php?error=login_required");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id']) && isset($_POST['action'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action']; // 'approve' or 'reject'
    
    // Get the user being approved/rejected
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT account_type, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        closeDBConnection($conn);
        header("Location: " . ($_SESSION['account_type'] === 'admin' ? 'dashboard/admin_dashboard.php' : 'dashboard/teacher_dashboard.php') . "?error=user_not_found");
        exit();
    }
    
    $target_user = $result->fetch_assoc();
    $stmt->close();
    
    // Authorization checks
    $is_admin = $_SESSION['account_type'] === 'admin';
    $is_teacher = $_SESSION['account_type'] === 'teacher';
    
    // Admins can approve/reject teachers
    // Teachers can approve/reject students
    $can_approve = false;
    if ($is_admin && $target_user['account_type'] === 'teacher') {
        $can_approve = true;
    } elseif ($is_teacher && $target_user['account_type'] === 'student') {
        $can_approve = true;
    }
    
    if (!$can_approve) {
        closeDBConnection($conn);
        header("Location: " . ($_SESSION['account_type'] === 'admin' ? 'dashboard/admin_dashboard.php' : 'dashboard/teacher_dashboard.php') . "?error=unauthorized");
        exit();
    }
    
    // Update user status
    $new_status = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        closeDBConnection($conn);
        $success_msg = $action === 'approve' ? 'user_approved' : 'user_rejected';
        header("Location: " . ($_SESSION['account_type'] === 'admin' ? 'dashboard/admin_dashboard.php' : 'dashboard/teacher_dashboard.php') . "?success=" . $success_msg);
        exit();
    } else {
        $stmt->close();
        closeDBConnection($conn);
        header("Location: " . ($_SESSION['account_type'] === 'admin' ? 'dashboard/admin_dashboard.php' : 'dashboard/teacher_dashboard.php') . "?error=update_failed");
        exit();
    }
} else {
    // If not POST request, redirect to appropriate dashboard
    header("Location: " . (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'admin' ? 'dashboard/admin_dashboard.php' : 'dashboard/teacher_dashboard.php'));
    exit();
}
?>
