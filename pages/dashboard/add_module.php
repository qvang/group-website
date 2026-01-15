<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['course_id']) && isset($_POST['module_name'])) {
    $course_id = intval($_POST['course_id']);
    $module_name = trim($_POST['module_name']);
    
    // Validate module name
    if (empty($module_name)) {
        header("Location: view_course.php?course_id=" . $course_id . "&error=empty_module_name");
        exit();
    }
    
    // Sanitize module name
    $module_name = htmlspecialchars($module_name, ENT_QUOTES, 'UTF-8');
    
    // Verify course exists
    $conn = getDBConnection();
    $course_stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    
    if ($course_result->num_rows === 0) {
        $course_stmt->close();
        closeDBConnection($conn);
        header("Location: teacher_dashboard.php?error=course_not_found");
        exit();
    }
    $course_stmt->close();
    
    // Get the highest display_order for this course to add new module at the end
    $order_stmt = $conn->prepare("SELECT MAX(display_order) as max_order FROM modules WHERE course_id = ?");
    $order_stmt->bind_param("i", $course_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order_row = $order_result->fetch_assoc();
    $next_order = ($order_row['max_order'] !== null) ? $order_row['max_order'] + 1 : 0;
    $order_stmt->close();
    
    // Insert new module
    $stmt = $conn->prepare("INSERT INTO modules (course_id, module_name, display_order) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $course_id, $module_name, $next_order);
    
    if ($stmt->execute()) {
        $stmt->close();
        closeDBConnection($conn);
        header("Location: view_course.php?course_id=" . $course_id . "&success=module_created");
        exit();
    } else {
        $stmt->close();
        closeDBConnection($conn);
        header("Location: view_course.php?course_id=" . $course_id . "&error=module_creation_failed");
        exit();
    }
} else {
    header("Location: teacher_dashboard.php");
    exit();
}
?>
