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

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: student_quiz.php");
    exit();
}

if (!isset($_POST['quiz_id']) || !is_numeric($_POST['quiz_id'])) {
    header("Location: student_quiz.php?error=invalid_quiz");
    exit();
}

$quiz_id = intval($_POST['quiz_id']);
$student_id = $_SESSION['user_id'];

$conn = getDBConnection();

// Start transaction
$conn->begin_transaction();

try {
    // Verify quiz exists and is active
    $quiz_stmt = $conn->prepare("
        SELECT id, total_questions
        FROM quizzes
        WHERE id = ? AND is_active = 1
    ");
    $quiz_stmt->bind_param("i", $quiz_id);
    $quiz_stmt->execute();
    $quiz_result = $quiz_stmt->get_result();
    $quiz = $quiz_result->fetch_assoc();
    $quiz_stmt->close();
    
    if (!$quiz) {
        throw new Exception("Quiz not found or inactive");
    }
    
    // Check if student has already attempted this quiz
    $attempt_check = $conn->prepare("
        SELECT id FROM quiz_attempts
        WHERE quiz_id = ? AND student_id = ?
    ");
    $attempt_check->bind_param("ii", $quiz_id, $student_id);
    $attempt_check->execute();
    $attempt_check_result = $attempt_check->get_result();
    if ($attempt_check_result->num_rows > 0) {
        $attempt_check->close();
        throw new Exception("Quiz already attempted");
    }
    $attempt_check->close();
    
    // Get all questions for this quiz
    $questions_stmt = $conn->prepare("
        SELECT id FROM quiz_questions
        WHERE quiz_id = ?
        ORDER BY question_order ASC
    ");
    $questions_stmt->bind_param("i", $quiz_id);
    $questions_stmt->execute();
    $questions_result = $questions_stmt->get_result();
    $questions = [];
    while ($row = $questions_result->fetch_assoc()) {
        $questions[] = $row['id'];
    }
    $questions_stmt->close();
    
    // Calculate score
    $score = 0;
    $total_questions = count($questions);
    
    foreach ($questions as $question_id) {
        $answer_key = "answer_{$question_id}";
        
        if (!isset($_POST[$answer_key]) || !is_numeric($_POST[$answer_key])) {
            continue; // Question not answered
        }
        
        $selected_option_id = intval($_POST[$answer_key]);
        
        // Check if this option is correct
        $option_stmt = $conn->prepare("
            SELECT is_correct FROM quiz_options
            WHERE id = ? AND question_id = ?
        ");
        $option_stmt->bind_param("ii", $selected_option_id, $question_id);
        $option_stmt->execute();
        $option_result = $option_stmt->get_result();
        
        if ($option_row = $option_result->fetch_assoc()) {
            if ($option_row['is_correct'] == 1) {
                $score++;
            }
        }
        
        $option_stmt->close();
    }
    
    // Insert quiz attempt
    $insert_stmt = $conn->prepare("
        INSERT INTO quiz_attempts (quiz_id, student_id, score, total_questions)
        VALUES (?, ?, ?, ?)
    ");
    $insert_stmt->bind_param("iiii", $quiz_id, $student_id, $score, $total_questions);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to save quiz attempt: " . $insert_stmt->error);
    }
    
    $insert_stmt->close();
    
    // Commit transaction
    $conn->commit();
    closeDBConnection($conn);
    
    // Redirect to quiz page with success message
    header("Location: student_quiz.php?success=quiz_submitted&score={$score}&total={$total_questions}");
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    closeDBConnection($conn);
    
    header("Location: student_quiz.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
