<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

if (isset($_GET['quiz_id'])) {
    $quiz_id = intval($_GET['quiz_id']);
    $teacher_id = $_SESSION['user_id'];
    
    $conn = getDBConnection();
    
    // Verify quiz exists and belongs to this teacher
    $stmt = $conn->prepare("
        SELECT id, teacher_id 
        FROM quizzes 
        WHERE id = ? AND teacher_id = ?
    ");
    $stmt->bind_param("ii", $quiz_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Delete quiz (cascade will handle quiz_questions, quiz_options, and quiz_attempts)
        $delete_stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
        $delete_stmt->bind_param("i", $quiz_id);
        
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            $stmt->close();
            closeDBConnection($conn);
            
            header("Location: manage_quiz.php?success=quiz_removed");
            exit();
        } else {
            $delete_stmt->close();
            $stmt->close();
            closeDBConnection($conn);
            header("Location: manage_quiz.php?error=remove_failed");
            exit();
        }
    } else {
        $stmt->close();
        closeDBConnection($conn);
        header("Location: manage_quiz.php?error=quiz_not_found");
        exit();
    }
} else {
    header("Location: manage_quiz.php");
    exit();
}
?>
