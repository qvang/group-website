<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

if (isset($_GET['file_id']) && isset($_GET['course_id'])) {
    $file_id = intval($_GET['file_id']);
    $course_id = intval($_GET['course_id']);
    
    $conn = getDBConnection();
    
    // Get file information
    $stmt = $conn->prepare("
        SELECT file_path, course_id 
        FROM course_files 
        WHERE id = ? AND course_id = ?
    ");
    $stmt->bind_param("ii", $file_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $file = $result->fetch_assoc();
        $file_path = '../../uploads/course_files/' . $file['file_path'];
        
        // Delete file from database
        $delete_stmt = $conn->prepare("DELETE FROM course_files WHERE id = ?");
        $delete_stmt->bind_param("i", $file_id);
        
        if ($delete_stmt->execute()) {
            // Delete physical file
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            $delete_stmt->close();
            $stmt->close();
            closeDBConnection($conn);
            
            header("Location: view_course.php?course_id=" . $course_id . "&success=file_removed");
            exit();
        } else {
            $delete_stmt->close();
            $stmt->close();
            closeDBConnection($conn);
            header("Location: view_course.php?course_id=" . $course_id . "&error=remove_failed");
            exit();
        }
    } else {
        $stmt->close();
        closeDBConnection($conn);
        header("Location: view_course.php?course_id=" . $course_id . "&error=file_not_found");
        exit();
    }
} else {
    header("Location: teacher_dashboard.php");
    exit();
}
?>
