<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?error=login_required");
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

// Get all teachers
$conn = getDBConnection();
$teachers_stmt = $conn->prepare("
    SELECT id, name, student_id, email
    FROM users
    WHERE account_type = 'teacher'
    ORDER BY name
");
$teachers_stmt->execute();
$teachers_result = $teachers_stmt->get_result();
$teachers = [];
while ($row = $teachers_result->fetch_assoc()) {
    $teachers[] = $row;
}
$teachers_stmt->close();

// Get all students
$students_stmt = $conn->prepare("
    SELECT id, name, student_id, email
    FROM users
    WHERE account_type = 'student'
    ORDER BY name
");
$students_stmt->execute();
$students_result = $students_stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}
$students_stmt->close();
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
                <a href="../../index.html" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; color: inherit;">
                    <div class="logo-icon">C</div>
                    <span class="logo-text">Codex</span>
                </a>
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
                    <div class="success-message" style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        User removed successfully.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        $error = $_GET['error'];
                        if ($error == 'cannot_delete_self') echo 'You cannot delete your own account.';
                        elseif ($error == 'delete_failed') echo 'Failed to remove user. Please try again.';
                        else echo 'An error occurred.';
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="admin-dashboard-grid">
                    <!-- Left Section: Teachers -->
                    <div class="dashboard-section">
                        <h2 class="section-heading">Teachers</h2>
                        
                        <?php if (empty($teachers)): ?>
                            <p class="no-items">No teachers found.</p>
                        <?php else: ?>
                            <div class="items-list">
                                <?php foreach ($teachers as $teacher): ?>
                                    <div class="list-item">
                                        <div class="item-info">
                                            <h3 class="item-title">Teacher</h3>
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
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
</body>
</html>
