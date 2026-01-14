<?php
/**
 * Setup script to create/update admin account with correct password hash
 * Run this once after setting up your database
 * 
 * Usage: Navigate to http://localhost/group-website/setup_admin.php in your browser
 * Or run: php setup_admin.php (if PHP CLI is available)
 */

require_once 'config/db_connection.php';

$admin_student_id = 99999999;
$admin_name = 'Admin';
$admin_email = 'admin@codex.edu';
$admin_password = 'admin123';
$admin_account_type = 'admin';

// Generate password hash
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

echo "Setting up admin account...\n";
echo "Student ID: $admin_student_id\n";
echo "Password: $admin_password\n";
echo "Generated Hash: $hashed_password\n\n";

// Connect to database
$conn = getDBConnection();

// Check if admin already exists
$check_stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
$check_stmt->bind_param("i", $admin_student_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    echo "Admin account exists. Updating password...\n";
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, name = ?, email = ?, account_type = ? WHERE student_id = ?");
    $update_stmt->bind_param("ssssi", $hashed_password, $admin_name, $admin_email, $admin_account_type, $admin_student_id);
    
    if ($update_stmt->execute()) {
        echo "✓ Admin password updated successfully!\n";
    } else {
        echo "✗ Error updating admin: " . $update_stmt->error . "\n";
    }
    $update_stmt->close();
} else {
    // Insert new admin
    echo "Creating new admin account...\n";
    $insert_stmt = $conn->prepare("INSERT INTO users (student_id, name, email, password, account_type) VALUES (?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("issss", $admin_student_id, $admin_name, $admin_email, $hashed_password, $admin_account_type);
    
    if ($insert_stmt->execute()) {
        echo "✓ Admin account created successfully!\n";
    } else {
        echo "✗ Error creating admin: " . $insert_stmt->error . "\n";
    }
    $insert_stmt->close();
}

$check_stmt->close();
closeDBConnection($conn);

echo "\nYou can now log in with:\n";
echo "Student ID: $admin_student_id\n";
echo "Password: $admin_password\n";
?>
