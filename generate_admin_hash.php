<?php
/**
 * Script to generate password hash for admin123
 * Run this if you need to manually generate a hash
 * 
 * Usage: Navigate to http://localhost/group-website/generate_admin_hash.php
 */

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Admin Password Hash Generator</h2>";
echo "<p><strong>Password:</strong> admin123</p>";
echo "<p><strong>Generated Hash:</strong> <code>" . htmlspecialchars($hash) . "</code></p>";

echo "<h3>SQL Update Query:</h3>";
echo "<pre>UPDATE users SET password = '" . htmlspecialchars($hash) . "' WHERE student_id = 99999999;</pre>";

// Verify the hash works
if (password_verify('admin123', $hash)) {
    echo "<p style='color: green;'>✓ Hash verification successful! This hash will work for password 'admin123'</p>";
} else {
    echo "<p style='color: red;'>✗ Hash verification failed!</p>";
}
?>
