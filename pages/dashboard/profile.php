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

// Only students and teachers can access profile (not admins)
if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'admin') {
    header("Location: admin_dashboard.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

// Get user information from database
$user_id = $_SESSION['user_id'];
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id, student_id, name, email, password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    header("Location: " . ($_SESSION['account_type'] === 'teacher' ? 'teacher_dashboard.php' : 'dashboard.php') . "?error=user_not_found");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();
closeDBConnection($conn);

// Determine redirect URL based on account type
$dashboard_url = $_SESSION['account_type'] === 'teacher' ? 'teacher_dashboard.php' : 'dashboard.php';
$quiz_url = $_SESSION['account_type'] === 'teacher' ? 'quiz.php' : 'student_quiz.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Codex</title>
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
</head>
<body>
    <!-- Navigation bar + Header -->
    <header id="site-header">
        <nav id="navbar" class="navbar">
            <div class="nav-brand">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: inherit; cursor: default;">
                    <div class="logo-icon">C</div>
                    <span class="logo-text">Codex</span>
                </div>
            </div>
            <ul class="nav-links">
                <li><a href='<?php echo $dashboard_url; ?>'>Dashboard</a></li>
                <li><a href='<?php echo $quiz_url; ?>'>Quiz</a></li>
                <li><a href='profile.php' class="nav-active">Profile</a></li>
                <li><a href='../logout.php'>Log out</a></li>
            </ul>
        </nav>
    </header>

    <!-- Main content -->
    <main>
        <section class="dashboard">
            <div class="dashboard-container">
                <h1 class="dashboard-title">Profile</h1>
                
                <!-- Profile Information -->
                <div class="profile-section" style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 600px;">
                    <h2 class="section-heading" style="margin-top: 0; margin-bottom: 1.5rem;">Account Information</h2>
                    <div class="profile-details" style="display: grid; gap: 1.5rem;">
                        <div class="detail-row" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <strong style="font-size: 0.875rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Name</strong>
                            <span style="font-size: 1.1rem; color: #333;"><?php echo htmlspecialchars($user['name']); ?></span>
                        </div>
                        <div class="detail-row" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <strong style="font-size: 0.875rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Email</strong>
                            <span style="font-size: 1.1rem; color: #333;"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="detail-row" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <strong style="font-size: 0.875rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">ID</strong>
                            <span style="font-size: 1.1rem; color: #333;"><?php echo htmlspecialchars($user['student_id']); ?></span>
                        </div>
                        <div class="detail-row" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <strong style="font-size: 0.875rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Password</strong>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span style="font-size: 1.1rem; color: #333; font-family: monospace; letter-spacing: 2px;">••••••••</span>
                                <button type="button" id="btn-change-password" class="btn-change-password" style="padding: 0.5rem 1rem; background-color: #f5f5f5; color: #333; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; font-family: inherit;">Change</button>
                            </div>
                            
                            <!-- Password Change Form (initially hidden) -->
                            <div id="password-change-form" style="display: none; margin-top: 1rem; padding: 1.5rem; background: #f8f9fa; border-radius: 6px; border: 1px solid #e0e0e0;">
                                <?php if (isset($_GET['error'])): ?>
                                    <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem;">
                                        <?php
                                        $error = $_GET['error'];
                                        if ($error == 'empty_fields') echo 'Please fill in all fields.';
                                        elseif ($error == 'wrong_old_password') echo 'Old password is incorrect.';
                                        elseif ($error == 'password_mismatch') echo 'New passwords do not match.';
                                        elseif ($error == 'same_password') echo 'New password must be different from the old password.';
                                        elseif ($error == 'update_failed') echo 'Failed to update password. Please try again.';
                                        else echo 'An error occurred. Please try again.';
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($_GET['success']) && $_GET['success'] == 'password_changed'): ?>
                                    <div class="success-message" style="background-color: #d4edda; color: #155724; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem;">
                                        Password changed successfully.
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="change_password.php" style="display: flex; flex-direction: column; gap: 1rem;">
                                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <label for="old_password" style="font-size: 0.875rem; font-weight: 500; color: #333;">Old Password</label>
                                        <input type="password" id="old_password" name="old_password" required style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; font-family: inherit;">
                                    </div>
                                    
                                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <label for="new_password" style="font-size: 0.875rem; font-weight: 500; color: #333;">New Password</label>
                                        <input type="password" id="new_password" name="new_password" required style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; font-family: inherit;">
                                    </div>
                                    
                                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <label for="confirm_password" style="font-size: 0.875rem; font-weight: 500; color: #333;">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" required style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; font-family: inherit;">
                                    </div>
                                    
                                    <div style="display: flex; gap: 1rem;">
                                        <button type="submit" class="btn-submit-password" style="padding: 0.75rem 1.5rem; background-color: #333; color: white; border: none; border-radius: 6px; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; font-family: inherit;">Update Password</button>
                                        <button type="button" id="btn-cancel-password" class="btn-cancel-password" style="padding: 0.75rem 1.5rem; background-color: #f5f5f5; color: #333; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; font-family: inherit;">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
    <script>
        // Toggle password change form visibility
        document.addEventListener('DOMContentLoaded', function() {
            const changeBtn = document.getElementById('btn-change-password');
            const cancelBtn = document.getElementById('btn-cancel-password');
            const passwordForm = document.getElementById('password-change-form');
            
            if (changeBtn && passwordForm) {
                changeBtn.addEventListener('click', function() {
                    passwordForm.style.display = passwordForm.style.display === 'none' ? 'block' : 'none';
                });
            }
            
            if (cancelBtn && passwordForm) {
                cancelBtn.addEventListener('click', function() {
                    passwordForm.style.display = 'none';
                    // Clear form fields
                    document.getElementById('old_password').value = '';
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';
                });
            }
            
            // Show form if there are error or success messages
            <?php if (isset($_GET['error']) || isset($_GET['success'])): ?>
                if (passwordForm) {
                    passwordForm.style.display = 'block';
                }
            <?php endif; ?>
            
            // Client-side validation for password match
            const form = document.querySelector('#password-change-form form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match. Please try again.');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>
