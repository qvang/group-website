<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

if (isset($_GET['module_id']) && isset($_GET['course_id'])) {
    $module_id = intval($_GET['module_id']);
    $course_id = intval($_GET['course_id']);
    
    $conn = getDBConnection();
    
    // Verify module exists and belongs to the course
    $stmt = $conn->prepare("
        SELECT id, course_id 
        FROM modules 
        WHERE id = ? AND course_id = ?
    ");
    $stmt->bind_param("ii", $module_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Delete module (cascade will handle lessons, projects, and files)
        $delete_stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
        $delete_stmt->bind_param("i", $module_id);
        
        if ($delete_stmt->execute()) {
            // Also delete any course files associated with this module
            $delete_files_stmt = $conn->prepare("DELETE FROM course_files WHERE module_id = ?");
            $delete_files_stmt->bind_param("i", $module_id);
            $delete_files_stmt->execute();
            $delete_files_stmt->close();
            
            $delete_stmt->close();
            $stmt->close();
            closeDBConnection($conn);
            
            header("Location: view_course.php?course_id=" . $course_id . "&success=module_removed");
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
        header("Location: view_course.php?course_id=" . $course_id . "&error=module_not_found");
        exit();
    }
} else {
    header("Location: teacher_dashboard.php");
    exit();
}
?>
