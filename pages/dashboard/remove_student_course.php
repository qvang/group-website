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

// Only teachers can remove students from courses
if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

// Get enrollment ID and student ID from URL
if (!isset($_GET['enrollment_id']) || !isset($_GET['student_id'])) {
    header("Location: teacher_dashboard.php?error=invalid_request");
    exit();
}

$enrollment_id = intval($_GET['enrollment_id']);
$student_id = intval($_GET['student_id']);

// Verify enrollment exists and belongs to a student
$conn = getDBConnection();
$verify_stmt = $conn->prepare("
    SELECT uc.id, u.account_type, u.status
    FROM user_courses uc
    JOIN users u ON uc.user_id = u.id
    WHERE uc.id = ? AND uc.user_id = ?
");
$verify_stmt->bind_param("ii", $enrollment_id, $student_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $verify_stmt->close();
    closeDBConnection($conn);
    header("Location: view_student.php?student_id=" . $student_id . "&error=course_not_found");
    exit();
}

$verify_stmt->close();

// Delete the enrollment
$delete_stmt = $conn->prepare("DELETE FROM user_courses WHERE id = ?");
$delete_stmt->bind_param("i", $enrollment_id);

if ($delete_stmt->execute()) {
    $delete_stmt->close();
    closeDBConnection($conn);
    header("Location: view_student.php?student_id=" . $student_id . "&success=course_removed");
    exit();
} else {
    $delete_stmt->close();
    closeDBConnection($conn);
    header("Location: view_student.php?student_id=" . $student_id . "&error=removal_failed");
    exit();
}
?>
