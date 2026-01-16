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

// Get quiz ID from URL
if (!isset($_GET['quiz_id']) || !is_numeric($_GET['quiz_id'])) {
    header("Location: student_quiz.php?error=invalid_quiz");
    exit();
}

$quiz_id = intval($_GET['quiz_id']);

// Get quiz details
$quiz_stmt = $conn->prepare("
    SELECT q.id, q.quiz_title, q.total_questions, q.time_limit, q.teacher_id, u.name as teacher_name
    FROM quizzes q
    JOIN users u ON q.teacher_id = u.id
    WHERE q.id = ? AND q.is_active = 1
");
$quiz_stmt->bind_param("i", $quiz_id);
$quiz_stmt->execute();
$quiz_result = $quiz_stmt->get_result();
$quiz = $quiz_result->fetch_assoc();
$quiz_stmt->close();

if (!$quiz) {
    header("Location: student_quiz.php?error=quiz_not_found");
    exit();
}

// Check if student has already attempted this quiz
$attempt_stmt = $conn->prepare("
    SELECT id FROM quiz_attempts
    WHERE quiz_id = ? AND student_id = ?
");
$attempt_stmt->bind_param("ii", $quiz_id, $student_id);
$attempt_stmt->execute();
$attempt_result = $attempt_stmt->get_result();
if ($attempt_result->num_rows > 0) {
    $attempt_stmt->close();
    closeDBConnection($conn);
    header("Location: student_quiz.php?error=already_attempted");
    exit();
}
$attempt_stmt->close();

// Get all questions with their options
$questions_stmt = $conn->prepare("
    SELECT id, question_text, question_order
    FROM quiz_questions
    WHERE quiz_id = ?
    ORDER BY question_order ASC
");
$questions_stmt->bind_param("i", $quiz_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();
$questions = [];
while ($question_row = $questions_result->fetch_assoc()) {
    $question_id = $question_row['id'];
    
    // Get options for this question
    $options_stmt = $conn->prepare("
        SELECT id, option_text, option_letter, is_correct
        FROM quiz_options
        WHERE question_id = ?
        ORDER BY option_letter ASC
    ");
    $options_stmt->bind_param("i", $question_id);
    $options_stmt->execute();
    $options_result = $options_stmt->get_result();
    $question_row['options'] = [];
    while ($option_row = $options_result->fetch_assoc()) {
        $question_row['options'][] = $option_row;
    }
    $options_stmt->close();
    
    $questions[] = $question_row;
}
$questions_stmt->close();
closeDBConnection($conn);

if (empty($questions)) {
    header("Location: student_quiz.php?error=no_questions");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz - Codex</title>
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
    <style>
        .quiz-timer {
            position: fixed;
            top: 80px;
            right: 20px;
            background-color: #007bff;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-size: 1.25rem;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        .quiz-timer.warning {
            background-color: #ffc107;
            color: #000;
        }
        .quiz-timer.danger {
            background-color: #dc3545;
        }
        .quiz-taking-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
        }
        .quiz-taking-title {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .quiz-taking-subtitle {
            color: #666;
            margin-bottom: 2rem;
        }
        .quiz-question-block {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .quiz-question-number {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        .quiz-question-text {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        .quiz-option-item {
            margin-bottom: 0.75rem;
        }
        .quiz-option-label {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quiz-option-label:hover {
            border-color: #007bff;
            background: #f0f7ff;
        }
        .quiz-option-label input[type="radio"] {
            margin-right: 0.75rem;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .quiz-option-letter {
            font-weight: bold;
            margin-right: 0.5rem;
            color: #007bff;
            min-width: 20px;
        }
        .quiz-submit-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #dee2e6;
            text-align: center;
        }
        .btn-submit-quiz {
            background-color: #28a745;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-submit-quiz:hover {
            background-color: #218838;
        }
        .btn-submit-quiz:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
    </style>
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

    <!-- Timer -->
    <div class="quiz-timer" id="quizTimer">
        Time Remaining: <span id="timeDisplay">30:00</span>
    </div>

    <!-- Main content -->
    <main>
        <section class="dashboard">
            <div class="dashboard-container">
                <div class="quiz-taking-container">
                    <a href="student_quiz.php" class="back-link">←</a>
                    <h1 class="quiz-taking-title"><?php echo htmlspecialchars($quiz['quiz_title']); ?></h1>
                    <p class="quiz-taking-subtitle">By <?php echo htmlspecialchars($quiz['teacher_name']); ?> • <?php echo $quiz['total_questions']; ?> Questions • <?php echo $quiz['time_limit']; ?> Minutes</p>
                    
                    <form method="POST" action="submit_quiz.php" id="quizForm">
                        <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                        
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="quiz-question-block">
                                <div class="quiz-question-number">Question <?php echo $index + 1; ?></div>
                                <div class="quiz-question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                                
                                <div class="quiz-options">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <div class="quiz-option-item">
                                            <label class="quiz-option-label">
                                                <input type="radio" 
                                                       name="answer_<?php echo $question['id']; ?>" 
                                                       value="<?php echo $option['id']; ?>" 
                                                       required>
                                                <span class="quiz-option-letter"><?php echo htmlspecialchars($option['option_letter']); ?>.</span>
                                                <span><?php echo htmlspecialchars($option['option_text']); ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="quiz-submit-section">
                            <button type="submit" class="btn-submit-quiz" id="submitBtn">Submit Quiz</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Timer functionality
        const timeLimitMinutes = <?php echo $quiz['time_limit']; ?>;
        let timeRemaining = timeLimitMinutes * 60; // Convert to seconds
        const timerElement = document.getElementById('quizTimer');
        const timeDisplay = document.getElementById('timeDisplay');
        const quizForm = document.getElementById('quizForm');
        const submitBtn = document.getElementById('submitBtn');
        
        function updateTimer() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            timeDisplay.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            // Change color based on time remaining
            if (timeRemaining <= 300) { // 5 minutes or less
                timerElement.className = 'quiz-timer danger';
            } else if (timeRemaining <= 600) { // 10 minutes or less
                timerElement.className = 'quiz-timer warning';
            }
            
            if (timeRemaining <= 0) {
                // Time's up - auto submit
                alert('Time is up! Your quiz will be submitted automatically.');
                quizForm.submit();
                return;
            }
            
            timeRemaining--;
        }
        
        // Update timer every second
        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();
        
        // Confirm submission
        quizForm.addEventListener('submit', function(e) {
            clearInterval(timerInterval);
            
            // Check if all questions are answered
            const questionIds = <?php echo json_encode(array_column($questions, 'id')); ?>;
            let allAnswered = true;
            const unansweredQuestions = [];
            
            questionIds.forEach(function(qId, index) {
                const answerInput = document.querySelector(`input[name="answer_${qId}"]:checked`);
                if (!answerInput) {
                    allAnswered = false;
                    unansweredQuestions.push(index + 1);
                }
            });
            
            if (!allAnswered) {
                e.preventDefault();
                clearInterval(timerInterval);
                const confirmSubmit = confirm(`You have not answered ${unansweredQuestions.length} question(s): ${unansweredQuestions.join(', ')}. Are you sure you want to submit?`);
                if (confirmSubmit) {
                    clearInterval(timerInterval);
                    return true; // Allow submission
                } else {
                    // Restart timer
                    const timerInterval = setInterval(updateTimer, 1000);
                    return false;
                }
            }
            
            // Submit without confirmation - quiz completion message is enough
        });
        
        // Prevent navigation away from page
        window.addEventListener('beforeunload', function(e) {
            if (timeRemaining > 0) {
                e.preventDefault();
                e.returnValue = 'Are you sure you want to leave? Your progress will be lost.';
                return e.returnValue;
            }
        });
    </script>
</body>
</html>
