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

// Check if user is a teacher or student
if (!isset($_SESSION['account_type']) || !in_array($_SESSION['account_type'], ['teacher', 'student'])) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

// Get project ID from URL
if (!isset($_GET['project_id']) || !isset($_GET['course_id'])) {
    $redirect_page = ($_SESSION['account_type'] === 'teacher') ? 'teacher_dashboard.php' : 'dashboard.php';
    header("Location: " . $redirect_page . "?error=invalid_project");
    exit();
}

$project_id = intval($_GET['project_id']);
$course_id = intval($_GET['course_id']);

// Get project and file information
$conn = getDBConnection();
$project_stmt = $conn->prepare("
    SELECT p.id, p.project_name, p.module_id, m.module_name, c.course_name
    FROM projects p
    JOIN modules m ON p.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE p.id = ? AND m.course_id = ?
");
$project_stmt->bind_param("ii", $project_id, $course_id);
$project_stmt->execute();
$project_result = $project_stmt->get_result();

if ($project_result->num_rows === 0) {
    $project_stmt->close();
    closeDBConnection($conn);
    $redirect_page = ($_SESSION['account_type'] === 'teacher') ? 'view_course.php' : 'student_view_course.php';
    header("Location: " . $redirect_page . "?course_id=" . $course_id . "&error=project_not_found");
    exit();
}

$project = $project_result->fetch_assoc();
$project_stmt->close();

// If student, verify enrollment in course
if ($_SESSION['account_type'] === 'student') {
    $user_id = $_SESSION['user_id'];
    $enrollment_stmt = $conn->prepare("
        SELECT uc.id 
        FROM user_courses uc
        WHERE uc.user_id = ? AND uc.course_id = ?
    ");
    $enrollment_stmt->bind_param("ii", $user_id, $course_id);
    $enrollment_stmt->execute();
    $enrollment_result = $enrollment_stmt->get_result();
    
    if ($enrollment_result->num_rows === 0) {
        $enrollment_stmt->close();
        closeDBConnection($conn);
        header("Location: dashboard.php?error=not_enrolled");
        exit();
    }
    $enrollment_stmt->close();
}

// Get file associated with this project
$file_stmt = $conn->prepare("
    SELECT id, file_name, original_name, file_path, file_type, file_size
    FROM course_files
    WHERE project_id = ?
    LIMIT 1
");
$file_stmt->bind_param("i", $project_id);
$file_stmt->execute();
$file_result = $file_stmt->get_result();

if ($file_result->num_rows === 0) {
    $file_stmt->close();
    closeDBConnection($conn);
    $redirect_page = ($_SESSION['account_type'] === 'teacher') ? 'view_course.php' : 'student_view_course.php';
    header("Location: " . $redirect_page . "?course_id=" . $course_id . "&error=file_not_found");
    exit();
}

$file = $file_result->fetch_assoc();
$file_stmt->close();
closeDBConnection($conn);

// Determine file path
$file_path = '../../uploads/course_files/' . $file['file_path'];

// Check if file exists
if (!file_exists($file_path)) {
    $redirect_page = ($_SESSION['account_type'] === 'teacher') ? 'view_course.php' : 'student_view_course.php';
    header("Location: " . $redirect_page . "?course_id=" . $course_id . "&error=file_missing");
    exit();
}

// Set headers for file download/view
$file_extension = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
$content_type = 'application/octet-stream';
$disposition = 'inline'; // Default: view inline

if ($file_extension === 'pdf') {
    $content_type = 'application/pdf';
    $disposition = 'inline'; // PDFs can be viewed inline
} elseif (in_array($file_extension, ['doc', 'docx'])) {
    $content_type = 'application/msword';
    $disposition = 'attachment'; // Word docs should download
}

header('Content-Type: ' . $content_type);
header('Content-Disposition: ' . $disposition . '; filename="' . htmlspecialchars($file['original_name']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file
readfile($file_path);
exit();
?>
