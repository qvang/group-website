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

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teacher_id = $_SESSION['user_id'];
    
    // Get quiz title and week dates
    $quiz_title = isset($_POST['quiz_title']) ? trim($_POST['quiz_title']) : '';
    $week_start = isset($_POST['week_start_date']) ? $_POST['week_start_date'] : '';
    $week_end = isset($_POST['week_end_date']) ? $_POST['week_end_date'] : '';
    
    // Validate required fields
    if (empty($quiz_title) || empty($week_start) || empty($week_end)) {
        header("Location: create_quiz.php?error=missing_fields");
        exit();
    }
    
    // Validate week dates
    if (strtotime($week_start) > strtotime($week_end)) {
        header("Location: create_quiz.php?error=invalid_dates");
        exit();
    }
    
    $conn = getDBConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert quiz
        $quiz_stmt = $conn->prepare("
            INSERT INTO quizzes (teacher_id, quiz_title, total_questions, time_limit, week_start_date, week_end_date, is_active)
            VALUES (?, ?, 10, 30, ?, ?, 1)
        ");
        $quiz_stmt->bind_param("isss", $teacher_id, $quiz_title, $week_start, $week_end);
        
        if (!$quiz_stmt->execute()) {
            throw new Exception("Failed to create quiz: " . $quiz_stmt->error);
        }
        
        $quiz_id = $conn->insert_id;
        $quiz_stmt->close();
        
        // Process 10 questions
        for ($i = 1; $i <= 10; $i++) {
            $question_text = isset($_POST["question_{$i}"]) ? trim($_POST["question_{$i}"]) : '';
            
            if (empty($question_text)) {
                throw new Exception("Question $i is required");
            }
            
            // Insert question
            $question_stmt = $conn->prepare("
                INSERT INTO quiz_questions (quiz_id, question_text, question_order)
                VALUES (?, ?, ?)
            ");
            $question_stmt->bind_param("isi", $quiz_id, $question_text, $i);
            
            if (!$question_stmt->execute()) {
                throw new Exception("Failed to create question $i: " . $question_stmt->error);
            }
            
            $question_id = $conn->insert_id;
            $question_stmt->close();
            
            // Process 4 options (A, B, C, D)
            $option_letters = ['A', 'B', 'C', 'D'];
            $correct_answer = isset($_POST["correct_answer_{$i}"]) ? $_POST["correct_answer_{$i}"] : '';
            
            if (empty($correct_answer) || !in_array($correct_answer, $option_letters)) {
                throw new Exception("Question $i must have a valid correct answer (A, B, C, or D)");
            }
            
            foreach ($option_letters as $letter) {
                $option_text = isset($_POST["option_{$i}_{$letter}"]) ? trim($_POST["option_{$i}_{$letter}"]) : '';
                
                if (empty($option_text)) {
                    throw new Exception("Question $i, Option $letter is required");
                }
                
                $is_correct = ($letter === $correct_answer) ? 1 : 0;
                
                // Insert option
                $option_stmt = $conn->prepare("
                    INSERT INTO quiz_options (question_id, option_text, option_letter, is_correct)
                    VALUES (?, ?, ?, ?)
                ");
                $option_stmt->bind_param("issi", $question_id, $option_text, $letter, $is_correct);
                
                if (!$option_stmt->execute()) {
                    throw new Exception("Failed to create option $letter for question $i: " . $option_stmt->error);
                }
                
                $option_stmt->close();
            }
        }
        
        // Commit transaction
        $conn->commit();
        closeDBConnection($conn);
        
        header("Location: quiz.php?success=quiz_created");
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        closeDBConnection($conn);
        
        header("Location: create_quiz.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: create_quiz.php");
    exit();
}
?>
