<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?error=login_required");
    exit();
}

// Check if user is approved (admins should always be approved, but check anyway)
if (!isset($_SESSION['status']) || $_SESSION['status'] !== 'approved') {
    header("Location: ../login.php?error=pending_approval");
    exit();
}

// Redirect non-admins to their respective dashboards
if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'admin') {
    if ($_SESSION['account_type'] === 'teacher') {
        header("Location: teacher_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

require_once '../../config/db_connection.php';

// Get user information from session
$user_name = $_SESSION['name'];

// Get pending teachers (for approval)
$conn = getDBConnection();
$pending_teachers_stmt = $conn->prepare("
    SELECT id, name, student_id, email
    FROM users
    WHERE account_type = 'teacher' AND status = 'pending'
    ORDER BY created_at DESC
");
$pending_teachers_stmt->execute();
$pending_teachers_result = $pending_teachers_stmt->get_result();
$pending_teachers = [];
while ($row = $pending_teachers_result->fetch_assoc()) {
    $pending_teachers[] = $row;
}
$pending_teachers_stmt->close();

// Get approved teachers
$teachers_stmt = $conn->prepare("
    SELECT id, name, student_id, email
    FROM users
    WHERE account_type = 'teacher' AND status = 'approved'
    ORDER BY name
");
$teachers_stmt->execute();
$teachers_result = $teachers_stmt->get_result();
$teachers = [];
while ($row = $teachers_result->fetch_assoc()) {
    $teachers[] = $row;
}
$teachers_stmt->close();

// Get approved students
$students_stmt = $conn->prepare("
    SELECT id, name, student_id, email
    FROM users
    WHERE account_type = 'student' AND status = 'approved'
    ORDER BY name
");
$students_stmt->execute();
$students_result = $students_stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}
$students_stmt->close();

// Get pending students (for approval)
$pending_students_stmt = $conn->prepare("
    SELECT id, name, student_id, email
    FROM users
    WHERE account_type = 'student' AND status = 'pending'
    ORDER BY created_at DESC
");
$pending_students_stmt->execute();
$pending_students_result = $pending_students_stmt->get_result();
$pending_students = [];
while ($row = $pending_students_result->fetch_assoc()) {
    $pending_students[] = $row;
}
$pending_students_stmt->close();
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Codex</title>
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
                <li><a href='admin_dashboard.php' class="nav-active">Dashboard</a></li>
                <li><a href='#'>Quiz</a></li>
                <li><a href='../logout.php'>Log out</a></li>
            </ul>
        </nav>
    </header>

    <!-- Main content -->
    <main>
        <section class="dashboard">
            <div class="dashboard-container">
                <h1 class="dashboard-title">Dashboard</h1>
                <p class="dashboard-subtitle"><?php echo htmlspecialchars($user_name); ?></p>
                
                <?php if (isset($_GET['success'])): ?>
                    <?php
                    $success = $_GET['success'];
                    $is_rejected = $success == 'user_rejected';
                    $bg_color = $is_rejected ? '#f8d7da' : '#d4edda';
                    $text_color = $is_rejected ? '#721c24' : '#155724';
                    ?>
                    <div class="success-message" style="background-color: <?php echo $bg_color; ?>; color: <?php echo $text_color; ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        if ($success == 'user_approved') echo 'User approved successfully.';
                        elseif ($success == 'user_rejected') echo 'User rejected successfully.';
                        else echo 'User removed successfully.';
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        $error = $_GET['error'];
                        if ($error == 'cannot_delete_self') echo 'You cannot delete your own account.';
                        elseif ($error == 'delete_failed') echo 'Failed to remove user. Please try again.';
                        elseif ($error == 'user_not_found') echo 'User not found.';
                        elseif ($error == 'unauthorized') echo 'You are not authorized to perform this action.';
                        elseif ($error == 'update_failed') echo 'Failed to update user status. Please try again.';
                        else echo 'An error occurred.';
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="admin-dashboard-grid">
                    <!-- Left Section: Approved Teachers -->
                    <div class="dashboard-section">
                        <h2 class="section-heading">Teachers</h2>
                        
                        <?php if (empty($teachers)): ?>
                            <p class="no-items">No teachers found.</p>
                        <?php else: ?>
                            <div class="items-list">
                                <?php foreach ($teachers as $teacher): ?>
                                    <div class="list-item">
                                        <div class="item-info">
                                            <h3 class="item-title"><?php echo htmlspecialchars($teacher['name']); ?></h3>
                                            <p class="item-details"><?php echo htmlspecialchars($teacher['student_id']); ?></p>
                                        </div>
                                        <form method="POST" action="../remove_user.php" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $teacher['id']; ?>">
                                            <button type="submit" class="btn-remove" onclick="return confirm('Are you sure you want to remove this teacher?');">Remove</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Section: Students -->
                    <div class="dashboard-section">
                        <h2 class="section-heading">Students</h2>
                        
                        <?php if (empty($students)): ?>
                            <p class="no-items">No students found.</p>
                        <?php else: ?>
                            <div class="items-list">
                                <?php foreach ($students as $student): ?>
                                    <div class="list-item">
                                        <div class="item-info">
                                            <h3 class="item-title"><?php echo htmlspecialchars($student['name']); ?></h3>
                                            <p class="item-details"><?php echo htmlspecialchars($student['student_id']); ?></p>
                                        </div>
                                        <form method="POST" action="../remove_user.php" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" class="btn-remove" onclick="return confirm('Are you sure you want to remove this student?');">Remove</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Applications Section (Pending Teachers and Students) -->
                <?php if (!empty($pending_teachers) || !empty($pending_students)): ?>
                    <div class="applications-section">
                        <div class="applications-header">
                            <div class="applications-divider"></div>
                            <h2 class="applications-title">Applications</h2>
                        </div>
                        <div class="applications-list">
                            <?php foreach ($pending_teachers as $teacher): ?>
                                <div class="application-item">
                                    <div class="application-info">
                                        <h3 class="application-name"><?php echo htmlspecialchars($teacher['name']); ?></h3>
                                        <p class="application-email"><?php echo htmlspecialchars($teacher['email']); ?> (Teacher)</p>
                                    </div>
                                    <div class="application-actions">
                                        <form method="POST" action="../approve_user.php" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $teacher['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-approve">Approve</button>
                                        </form>
                                        <form method="POST" action="../approve_user.php" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $teacher['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn-decline">Decline</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php foreach ($pending_students as $student): ?>
                                <div class="application-item">
                                    <div class="application-info">
                                        <h3 class="application-name"><?php echo htmlspecialchars($student['name']); ?></h3>
                                        <p class="application-email"><?php echo htmlspecialchars($student['email']); ?> (Student)</p>
                                    </div>
                                    <div class="application-actions">
                                        <form method="POST" action="../approve_user.php" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-approve">Approve</button>
                                        </form>
                                        <form method="POST" action="../approve_user.php" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn-decline">Decline</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
</body>
</html>
