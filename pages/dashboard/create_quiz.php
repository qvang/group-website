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

$teacher_name = $_SESSION['name'];

// Calculate current week dates (Monday to Sunday)
$today = new DateTime();
$monday = clone $today;
$monday->modify('monday this week');
$sunday = clone $monday;
$sunday->modify('sunday this week');

$default_week_start = $monday->format('Y-m-d');
$default_week_end = $sunday->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Codex</title>
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
                <h1 class="dashboard-title">Create Quiz</h1>
                <p class="dashboard-subtitle">Create a new weekly quiz (10 questions, 30 minutes)</p>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        $error = $_GET['error'];
                        if ($error == 'missing_fields') echo 'Please fill in all required fields.';
                        elseif ($error == 'invalid_dates') echo 'Week end date must be after week start date.';
                        else echo htmlspecialchars($error);
                        ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="process_quiz.php" class="quiz-create-form" id="quizForm">
                    <!-- Quiz Basic Info -->
                    <div class="quiz-info-section">
                        <h2 class="quiz-section-title">Quiz Information</h2>
                        <div class="form-group">
                            <label for="quiz_title">Quiz Title *</label>
                            <input type="text" id="quiz_title" name="quiz_title" required placeholder="e.g., Week 1 Quiz - Introduction to Programming">
                        </div>
                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="week_start_date">Week Start Date *</label>
                                <input type="date" id="week_start_date" name="week_start_date" value="<?php echo $default_week_start; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="week_end_date">Week End Date *</label>
                                <input type="date" id="week_end_date" name="week_end_date" value="<?php echo $default_week_end; ?>" required>
                            </div>
                        </div>
                        <div class="quiz-info-box">
                            <p><strong>Quiz Details:</strong></p>
                            <ul>
                                <li>10 Questions (Multiple Choice)</li>
                                <li>30 Minute Time Limit</li>
                                <li>4 Options per Question (A, B, C, D)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Questions Section -->
                    <div class="quiz-questions-section">
                        <h2 class="quiz-section-title">Quiz Questions</h2>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <div class="question-block" data-question="<?php echo $i; ?>">
                                <div class="question-header">
                                    <h3 class="question-number">Question <?php echo $i; ?></h3>
                                </div>
                                <div class="form-group">
                                    <label for="question_<?php echo $i; ?>">Question Text *</label>
                                    <textarea id="question_<?php echo $i; ?>" name="question_<?php echo $i; ?>" rows="2" required placeholder="Enter your question here..."></textarea>
                                </div>
                                
                                <div class="options-group">
                                    <?php $options = ['A', 'B', 'C', 'D']; ?>
                                    <?php foreach ($options as $letter): ?>
                                        <div class="option-row">
                                            <label class="option-label">Option <?php echo $letter; ?> *</label>
                                            <div class="option-input-group">
                                                <input type="text" 
                                                       id="option_<?php echo $i; ?>_<?php echo $letter; ?>" 
                                                       name="option_<?php echo $i; ?>_<?php echo $letter; ?>" 
                                                       required 
                                                       placeholder="Enter option <?php echo $letter; ?>">
                                                <label class="radio-label">
                                                    <input type="radio" 
                                                           name="correct_answer_<?php echo $i; ?>" 
                                                           value="<?php echo $letter; ?>" 
                                                           required>
                                                    Correct
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="quiz-form-actions">
                        <button type="button" class="btn-cancel" onclick="window.location.href='quiz.php'">Cancel</button>
                        <button type="submit" class="btn-submit-quiz">Create Quiz</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
    <script>
        // Validate form before submission
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            let isValid = true;
            const errorMessages = [];
            
            // Check if quiz title is filled
            const quizTitle = document.getElementById('quiz_title').value.trim();
            if (!quizTitle) {
                errorMessages.push('Quiz title is required');
                isValid = false;
            }
            
            // Check week dates
            const weekStart = document.getElementById('week_start_date').value;
            const weekEnd = document.getElementById('week_end_date').value;
            if (weekStart && weekEnd && new Date(weekStart) > new Date(weekEnd)) {
                errorMessages.push('Week end date must be after week start date');
                isValid = false;
            }
            
            // Check all questions have text
            for (let i = 1; i <= 10; i++) {
                const questionText = document.getElementById('question_' + i).value.trim();
                if (!questionText) {
                    errorMessages.push(`Question ${i} is required`);
                    isValid = false;
                }
                
                // Check all options are filled
                ['A', 'B', 'C', 'D'].forEach(function(letter) {
                    const optionText = document.getElementById(`option_${i}_${letter}`).value.trim();
                    if (!optionText) {
                        errorMessages.push(`Question ${i}, Option ${letter} is required`);
                        isValid = false;
                    }
                });
                
                // Check correct answer is selected
                const correctAnswer = document.querySelector(`input[name="correct_answer_${i}"]:checked`);
                if (!correctAnswer) {
                    errorMessages.push(`Question ${i} must have a correct answer selected`);
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
                return false;
            }
            
            // Confirm submission
            if (!confirm('Are you sure you want to create this quiz? You cannot edit it after creation.')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
