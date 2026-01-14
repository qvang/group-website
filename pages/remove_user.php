<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    require_once '../config/db_connection.php';
    
    $user_id = intval($_POST['user_id']);
    
    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        header("Location: dashboard/admin_dashboard.php?error=cannot_delete_self");
        exit();
    }
    
    $conn = getDBConnection();
    
    // Delete user (cascade will handle user_courses)
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        header("Location: dashboard/admin_dashboard.php?success=user_removed");
    } else {
        header("Location: dashboard/admin_dashboard.php?error=delete_failed");
    }
    
    $stmt->close();
    closeDBConnection($conn);
    exit();
} else {
    // If not POST request, redirect to admin dashboard
    header("Location: dashboard/admin_dashboard.php");
    exit();
}
?>
