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

// Only allow teachers
if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: dashboard.php");
    exit();
}

require_once '../../config/db_connection.php';

$conn = getDBConnection();
$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['name'];

// Get all quizzes for this teacher
$quizzes_stmt = $conn->prepare("
    SELECT id, quiz_title, total_questions, week_start_date, week_end_date, is_active, created_at
    FROM quizzes
    WHERE teacher_id = ?
    ORDER BY created_at DESC
");
$quizzes_stmt->bind_param("i", $teacher_id);
$quizzes_stmt->execute();
$quizzes_result = $quizzes_stmt->get_result();
$quizzes = [];
while ($row = $quizzes_result->fetch_assoc()) {
    $quizzes[] = $row;
}
$quizzes_stmt->close();

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quiz - Codex</title>
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
                <li><a href='teacher_dashboard.php'>Dashboard</a></li>
                <li><a href='quiz.php' class="nav-active">Quiz</a></li>
                <li><a href='profile.php'>Profile</a></li>
                <li><a href='../logout.php'>Log out</a></li>
            </ul>
        </nav>
    </header>

    <!-- Main content -->
    <main>
        <section class="dashboard">
            <div class="dashboard-container">
                <a href="quiz.php" class="back-link">←</a>
                <h1 class="dashboard-title">Manage Quiz</h1>
                <p class="dashboard-subtitle">View and manage your quizzes</p>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-message" style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        if ($_GET['success'] == 'quiz_removed') echo 'Quiz removed successfully.';
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        $error = $_GET['error'];
                        if ($error == 'quiz_not_found') echo 'Quiz not found.';
                        elseif ($error == 'remove_failed') echo 'Failed to remove quiz. Please try again.';
                        else echo 'An error occurred.';
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($quizzes)): ?>
                    <div class="dashboard-section" style="margin-top: 2rem;">
                        <h2 class="section-heading">Your Quizzes</h2>
                        <div class="courses-list">
                            <?php foreach ($quizzes as $quiz): ?>
                                <div class="course-item">
                                    <div class="course-info">
                                        <div class="course-title"><?php echo htmlspecialchars($quiz['quiz_title']); ?></div>
                                        <p class="course-details">
                                            <?php echo $quiz['total_questions']; ?> Questions | 
                                            Week: <?php echo date('M j', strtotime($quiz['week_start_date'])); ?> - <?php echo date('M j, Y', strtotime($quiz['week_end_date'])); ?> |
                                            Status: <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 1rem; align-items: center;">
                                        <a href="remove_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" 
                                           class="btn-remove" 
                                           onclick="return confirm('Are you sure you want to remove this quiz? This action cannot be undone.');">Remove</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="background-color: #f9f9f9; padding: 2rem; border-radius: 8px; border: 1px solid #e0e0e0; margin-top: 2rem; text-align: center;">
                        <p style="color: var(--text-secondary);">You haven't created any quizzes yet.</p>
                        <a href="create_quiz.php" class="btn-quiz" style="margin-top: 1rem; display: inline-block;">Create Your First Quiz</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
</body>
</html>
