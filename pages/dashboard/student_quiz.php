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

// Only allow students
if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'student') {
    header("Location: dashboard.php");
    exit();
}

require_once '../../config/db_connection.php';

$conn = getDBConnection();
$student_id = $_SESSION['user_id'];

// Get the current active quiz (from any teacher)
$current_date = date('Y-m-d');
$quiz_stmt = $conn->prepare("
    SELECT q.id, q.quiz_title, q.total_questions, q.teacher_id, q.week_start_date, q.week_end_date, u.name as teacher_name
    FROM quizzes q
    JOIN users u ON q.teacher_id = u.id
    WHERE q.is_active = 1 AND ? BETWEEN q.week_start_date AND q.week_end_date
    ORDER BY q.created_at DESC
    LIMIT 1
");
$quiz_stmt->bind_param("s", $current_date);
$quiz_stmt->execute();
$quiz_result = $quiz_stmt->get_result();
$current_quiz = $quiz_result->fetch_assoc();
$quiz_stmt->close();

// Check if student has already attempted this quiz
$has_attempted = false;
$student_score = null;
$student_total = null;
if ($current_quiz) {
    $attempt_stmt = $conn->prepare("
        SELECT score, total_questions, attempted_at
        FROM quiz_attempts
        WHERE quiz_id = ? AND student_id = ?
    ");
    $attempt_stmt->bind_param("ii", $current_quiz['id'], $student_id);
    $attempt_stmt->execute();
    $attempt_result = $attempt_stmt->get_result();
    if ($attempt_row = $attempt_result->fetch_assoc()) {
        $has_attempted = true;
        $student_score = $attempt_row['score'];
        $student_total = $attempt_row['total_questions'];
    }
    $attempt_stmt->close();
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
                <li><a href='dashboard.php'>Dashboard</a></li>
                <li><a href='student_quiz.php' class="nav-active">Quiz</a></li>
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
                    <?php if (isset($_GET['success']) && $_GET['success'] == 'quiz_submitted'): ?>
                        <div class="success-message" style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <p><strong>Quiz Submitted Successfully!</strong></p>
                            <?php if (isset($_GET['score']) && isset($_GET['total'])): ?>
                                <p>Your Score: <?php echo htmlspecialchars($_GET['score']); ?>/<?php echo htmlspecialchars($_GET['total']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <?php
                            $error = $_GET['error'];
                            if ($error == 'invalid_quiz') echo 'Invalid quiz selected.';
                            elseif ($error == 'quiz_not_found') echo 'Quiz not found.';
                            elseif ($error == 'already_attempted') echo 'You have already attempted this quiz.';
                            elseif ($error == 'no_questions') echo 'This quiz has no questions.';
                            else echo htmlspecialchars($error);
                            ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($current_quiz): ?>
                        <p class="quiz-subtitle"><?php echo htmlspecialchars($current_quiz['quiz_title']); ?> By '<?php echo htmlspecialchars($current_quiz['teacher_name']); ?>'</p>
                        <p class="quiz-questions"><?php echo $current_quiz['total_questions']; ?> Questions</p>
                        
                        <?php if ($has_attempted): ?>
                            <div class="quiz-completed-message" style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin: 1rem 0; text-align: center;">
                                <p><strong>Quiz Completed!</strong></p>
                                <p>Your Score: <?php echo $student_score; ?>/<?php echo $student_total; ?></p>
                            </div>
                        <?php else: ?>
                            <div class="quiz-actions">
                                <a href="take_quiz.php?quiz_id=<?php echo $current_quiz['id']; ?>" class="btn-quiz btn-start-quiz">Start</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="quiz-subtitle">No active quiz this week</p>
                        <p class="quiz-questions">0 Questions</p>
                    <?php endif; ?>
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
