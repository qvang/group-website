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

// Only teachers can view student details
if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

// Get student ID from URL
if (!isset($_GET['student_id'])) {
    header("Location: teacher_dashboard.php?error=invalid_student");
    exit();
}

$student_id = intval($_GET['student_id']);

// Get student information
$conn = getDBConnection();
$student_stmt = $conn->prepare("
    SELECT id, name, student_id, email
    FROM users
    WHERE id = ? AND account_type = 'student' AND status = 'approved'
");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows === 0) {
    $student_stmt->close();
    closeDBConnection($conn);
    header("Location: teacher_dashboard.php?error=student_not_found");
    exit();
}

$student = $student_result->fetch_assoc();
$student_stmt->close();

// Get enrolled courses for this student
$courses_stmt = $conn->prepare("
    SELECT c.id, c.course_code, c.course_name, uc.id as enrollment_id
    FROM courses c
    JOIN user_courses uc ON c.id = uc.course_id
    WHERE uc.user_id = ?
    ORDER BY c.course_name
");
$courses_stmt->bind_param("i", $student_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
$enrolled_courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $enrolled_courses[] = $row;
}
$courses_stmt->close();
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details - Codex</title>
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
</head>
<body>
    <!-- Navigation bar + Header -->
    <header id="site-header">
        <nav id="navbar" class="navbar">
            <div class="nav-brand">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: inherit; cursor: default;">
                    <div class="logo-icon">C</div>
                    <span class="logo-text">Codex</span>
                </div>
            </div>
            <ul class="nav-links">
                <li><a href='teacher_dashboard.php' class="nav-active">Dashboard</a></li>
                <li><a href='#'>Quiz</a></li>
                <li><a href='../logout.php'>Log out</a></li>
            </ul>
        </nav>
    </header>

    <!-- Main content -->
    <main>
        <section class="dashboard">
            <div class="dashboard-container">
                <a href="teacher_dashboard.php" class="back-link">←</a>
                <h1 class="dashboard-title">Student Details</h1>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-message" style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        if ($_GET['success'] == 'course_removed') echo 'Student removed from course successfully.';
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        $error = $_GET['error'];
                        if ($error == 'course_not_found') echo 'Course not found.';
                        elseif ($error == 'removal_failed') echo 'Failed to remove student from course. Please try again.';
                        else echo 'An error occurred.';
                        ?>
                    </div>
                <?php endif; ?>
                
                <!-- Student Information -->
                <div class="student-info-section" style="background: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 class="section-heading" style="margin-top: 0; margin-bottom: 1.5rem;">Student Information</h2>
                    <div class="student-details" style="display: grid; gap: 1rem;">
                        <div class="detail-row">
                            <strong style="display: inline-block; min-width: 120px;">Name:</strong>
                            <span><?php echo htmlspecialchars($student['name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong style="display: inline-block; min-width: 120px;">Student ID:</strong>
                            <span><?php echo htmlspecialchars($student['student_id']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong style="display: inline-block; min-width: 120px;">Email:</strong>
                            <span><?php echo htmlspecialchars($student['email']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Enrolled Courses -->
                <div class="courses-section" style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 class="section-heading" style="margin-top: 0; margin-bottom: 1.5rem;">Enrolled Courses</h2>
                    
                    <?php if (empty($enrolled_courses)): ?>
                        <p class="no-items">This student is not enrolled in any courses.</p>
                    <?php else: ?>
                        <div class="courses-list" style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($enrolled_courses as $course): ?>
                                <div class="course-item" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6;">
                                    <div class="course-info">
                                        <h3 style="margin: 0 0 0.25rem 0; font-size: 1.1rem; color: #333;">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </h3>
                                        <p style="margin: 0; font-size: 0.875rem; color: #666;">
                                            <?php echo htmlspecialchars($course['course_code']); ?>
                                        </p>
                                    </div>
                                    <a href="remove_student_course.php?enrollment_id=<?php echo $course['enrollment_id']; ?>&student_id=<?php echo $student_id; ?>" 
                                       class="btn-remove-link" 
                                       onclick="return confirm('Are you sure you want to remove this student from <?php echo htmlspecialchars(addslashes($course['course_name'])); ?>?');">
                                        Remove from Course
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
</body>
</html>
