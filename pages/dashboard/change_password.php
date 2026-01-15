<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?error=login_required");
    exit();
}

// Check if user is approved
if (!isset($_SESSION['status']) || $_SESSION['status'] !== 'approved') {
    header("Location: ../login.php?error=pending_approval");
    exit();
}

// Only students and teachers can change password (not admins)
if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'admin') {
    header("Location: admin_dashboard.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: profile.php");
    exit();
}

// Get form data
$old_password = isset($_POST['old_password']) ? $_POST['old_password'] : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

// Validate all fields are filled
if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
    header("Location: profile.php?error=empty_fields");
    exit();
}

// Validate new passwords match
if ($new_password !== $confirm_password) {
    header("Location: profile.php?error=password_mismatch");
    exit();
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Connect to database
$conn = getDBConnection();

// Get current password hash from database
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    header("Location: profile.php?error=user_not_found");
    exit();
}

$user = $result->fetch_assoc();
$current_password_hash = $user['password'];
$stmt->close();

// Verify old password
if (!password_verify($old_password, $current_password_hash)) {
    closeDBConnection($conn);
    header("Location: profile.php?error=wrong_old_password");
    exit();
}

// Check if new password is different from old password
if (password_verify($new_password, $current_password_hash)) {
    closeDBConnection($conn);
    header("Location: profile.php?error=same_password");
    exit();
}

// Hash new password
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update password in database
$update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update_stmt->bind_param("si", $new_password_hash, $user_id);

if ($update_stmt->execute()) {
    $update_stmt->close();
    closeDBConnection($conn);
    header("Location: profile.php?success=password_changed");
    exit();
} else {
    $update_stmt->close();
    closeDBConnection($conn);
    header("Location: profile.php?error=update_failed");
    exit();
}
?>
