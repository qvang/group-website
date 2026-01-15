<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

if (isset($_GET['lesson_id']) && isset($_GET['course_id'])) {
    $lesson_id = intval($_GET['lesson_id']);
    $course_id = intval($_GET['course_id']);
    
    $conn = getDBConnection();
    
    // Get file associated with this lesson before deleting
    $file_stmt = $conn->prepare("
        SELECT file_path 
        FROM course_files 
        WHERE lesson_id = ?
    ");
    $file_stmt->bind_param("i", $lesson_id);
    $file_stmt->execute();
    $file_result = $file_stmt->get_result();
    $files_to_delete = [];
    while ($file_row = $file_result->fetch_assoc()) {
        $files_to_delete[] = $file_row['file_path'];
    }
    $file_stmt->close();
    
    // Verify lesson exists and belongs to the course
    $stmt = $conn->prepare("
        SELECT l.id 
        FROM lessons l
        JOIN modules m ON l.module_id = m.id
        WHERE l.id = ? AND m.course_id = ?
    ");
    $stmt->bind_param("ii", $lesson_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Delete lesson (cascade will handle course_files)
        $delete_stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
        $delete_stmt->bind_param("i", $lesson_id);
        
        if ($delete_stmt->execute()) {
            // Delete physical files
            foreach ($files_to_delete as $file_path) {
                $full_path = '../../uploads/course_files/' . $file_path;
                if (file_exists($full_path)) {
                    @unlink($full_path);
                }
            }
            
            $delete_stmt->close();
            $stmt->close();
            closeDBConnection($conn);
            
            header("Location: view_course.php?course_id=" . $course_id . "&success=lesson_removed");
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
        header("Location: view_course.php?course_id=" . $course_id . "&error=lesson_not_found");
        exit();
    }
} else {
    header("Location: teacher_dashboard.php");
    exit();
}
?>
