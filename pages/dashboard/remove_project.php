<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

if (isset($_GET['project_id']) && isset($_GET['course_id'])) {
    $project_id = intval($_GET['project_id']);
    $course_id = intval($_GET['course_id']);
    
    $conn = getDBConnection();
    
    // Get file associated with this project before deleting
    $file_stmt = $conn->prepare("
        SELECT file_path 
        FROM course_files 
        WHERE project_id = ?
    ");
    $file_stmt->bind_param("i", $project_id);
    $file_stmt->execute();
    $file_result = $file_stmt->get_result();
    $files_to_delete = [];
    while ($file_row = $file_result->fetch_assoc()) {
        $files_to_delete[] = $file_row['file_path'];
    }
    $file_stmt->close();
    
    // Verify project exists and belongs to the course
    $stmt = $conn->prepare("
        SELECT p.id 
        FROM projects p
        JOIN modules m ON p.module_id = m.id
        WHERE p.id = ? AND m.course_id = ?
    ");
    $stmt->bind_param("ii", $project_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Delete project (cascade will handle course_files)
        $delete_stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
        $delete_stmt->bind_param("i", $project_id);
        
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
            
            header("Location: view_course.php?course_id=" . $course_id . "&success=project_removed");
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
        header("Location: view_course.php?course_id=" . $course_id . "&error=project_not_found");
        exit();
    }
} else {
    header("Location: teacher_dashboard.php");
    exit();
}
?>
