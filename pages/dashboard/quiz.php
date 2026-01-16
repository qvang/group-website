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

// Only allow teachers and admins
if (!isset($_SESSION['account_type']) || ($_SESSION['account_type'] !== 'teacher' && $_SESSION['account_type'] !== 'admin')) {
    header("Location: dashboard.php");
    exit();
}

require_once '../../config/db_connection.php';

$conn = getDBConnection();
$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['name'];

// Get the current active quiz for this teacher
$current_date = date('Y-m-d');
$quiz_stmt = $conn->prepare("
    SELECT id, quiz_title, total_questions, teacher_id, week_start_date, week_end_date
    FROM quizzes
    WHERE teacher_id = ? AND is_active = 1 AND ? BETWEEN week_start_date AND week_end_date
    ORDER BY created_at DESC
    LIMIT 1
");
$quiz_stmt->bind_param("is", $teacher_id, $current_date);
$quiz_stmt->execute();
$quiz_result = $quiz_stmt->get_result();
$current_quiz = $quiz_result->fetch_assoc();
$quiz_stmt->close();

// Get the teacher name for the current quiz if it exists
$quiz_teacher_name = $teacher_name;
if ($current_quiz && $current_quiz['teacher_id'] != $teacher_id) {
    $teacher_name_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $teacher_name_stmt->bind_param("i", $current_quiz['teacher_id']);
    $teacher_name_stmt->execute();
    $teacher_name_result = $teacher_name_stmt->get_result();
    if ($teacher_name_row = $teacher_name_result->fetch_assoc()) {
        $quiz_teacher_name = $teacher_name_row['name'];
    }
    $teacher_name_stmt->close();
}

// Get quiz attempts/leaderboard for the current quiz
$leaderboard = [];
if ($current_quiz) {
    $leaderboard_stmt = $conn->prepare("
        SELECT u.name, qa.score, qa.total_questions, qa.attempted_at
        FROM quiz_attempts qa
        JOIN users u ON qa.student_id = u.id
        WHERE qa.quiz_id = ?
        ORDER BY qa.score DESC, qa.attempted_at ASC
    ");
    $leaderboard_stmt->bind_param("i", $current_quiz['id']);
    $leaderboard_stmt->execute();
    $leaderboard_result = $leaderboard_stmt->get_result();
    while ($row = $leaderboard_result->fetch_assoc()) {
        $leaderboard[] = $row;
    }
    $leaderboard_stmt->close();
}

// Calculate time until reset (next Monday at 00:00)
$now = new DateTime();
$reset_date = new DateTime('next Monday');
if ($now->format('N') == 1 && $now->format('H:i') == '00:00') {
    $reset_date = clone $now;
} else {
    $reset_date = new DateTime('next Monday');
}
$reset_date->setTime(0, 0, 0);
$time_diff = $now->diff($reset_date);
$hours_until_reset = $time_diff->h + ($time_diff->days * 24);
$minutes_until_reset = $time_diff->i;
$reset_time_display = sprintf("%02d:%02d", $hours_until_reset, $minutes_until_reset);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Codex</title>
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
                <!-- Weekly Quiz Section -->
                <div class="quiz-section">
                    <h1 class="quiz-title">Weekly Quiz</h1>
                    <?php if ($current_quiz): ?>
                        <p class="quiz-subtitle">Current Quiz By '<?php echo htmlspecialchars($quiz_teacher_name); ?>'</p>
                        <p class="quiz-questions"><?php echo $current_quiz['total_questions']; ?> Questions</p>
                    <?php else: ?>
                        <p class="quiz-subtitle">No active quiz this week</p>
                        <p class="quiz-questions">0 Questions</p>
                    <?php endif; ?>
                    
                    <div class="quiz-actions">
                        <a href="create_quiz.php" class="btn-quiz btn-create-quiz">Create Quiz</a>
                        <a href="manage_quiz.php" class="btn-quiz btn-manage-quiz">Manage Quiz</a>
                    </div>
                </div>

                <!-- Weekly Leaderboard Section -->
                <div class="leaderboard-section">
                    <h2 class="leaderboard-title">Weekly Leaderboard</h2>
                    <p class="leaderboard-reset">Resets in: <span id="reset-timer"><?php echo $reset_time_display; ?></span></p>
                    
                    <div class="leaderboard-box">
                        <div class="leaderboard-header">
                            <div class="leaderboard-col-name">Name</div>
                            <div class="leaderboard-col-score">Score</div>
                        </div>
                        
                        <?php if (!empty($leaderboard)): ?>
                            <?php $rank = 1; foreach ($leaderboard as $entry): ?>
                                <div class="leaderboard-entry">
                                    <div class="leaderboard-col-name"><?php echo $rank; ?>. <?php echo htmlspecialchars($entry['name']); ?></div>
                                    <div class="leaderboard-col-score"><?php echo $entry['score']; ?>/<?php echo $entry['total_questions']; ?></div>
                                </div>
                                <?php $rank++; ?>
                            <?php endforeach; ?>
                            <?php if ($rank <= 10): ?>
                                <?php for ($i = $rank; $i <= 10; $i++): ?>
                                    <div class="leaderboard-entry leaderboard-entry-empty">
                                        <div class="leaderboard-col-name"></div>
                                        <div class="leaderboard-col-score"></div>
                                    </div>
                                <?php endfor; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <div class="leaderboard-entry leaderboard-entry-empty">
                                    <div class="leaderboard-col-name"></div>
                                    <div class="leaderboard-col-score"></div>
                                </div>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
    <script>
        // Update reset timer every minute
        function updateResetTimer() {
            const resetDate = new Date('<?php echo $reset_date->format('Y-m-d H:i:s'); ?>');
            const now = new Date();
            const diff = resetDate - now;
            
            if (diff <= 0) {
                document.getElementById('reset-timer').textContent = '00:00';
                location.reload();
                return;
            }
            
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const display = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
            document.getElementById('reset-timer').textContent = display;
        }
        
        // Update timer every minute
        setInterval(updateResetTimer, 60000);
        updateResetTimer();
    </script>
</body>
</html>
