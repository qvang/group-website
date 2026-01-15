<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login if not authenticated
    header("Location: ../login.php?error=login_required");
    exit();
}

// Check if user is approved
if (!isset($_SESSION['status']) || $_SESSION['status'] !== 'approved') {
    header("Location: ../login.php?error=pending_approval");
    exit();
}

// Redirect teachers to teacher dashboard
if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'teacher') {
    header("Location: teacher_dashboard.php");
    exit();
}

require_once '../../config/db_connection.php';

// Get user information from session
$user_name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];

// Get user's enrolled courses with actual lesson and project counts
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.course_code, 
        c.course_name,
        COALESCE(lesson_counts.total_lessons, 0) as total_lessons,
        COALESCE(project_counts.total_projects, 0) as total_projects
    FROM user_courses uc
    JOIN courses c ON uc.course_id = c.id
    LEFT JOIN (
        SELECT m.course_id, COUNT(l.id) as total_lessons
        FROM modules m
        LEFT JOIN lessons l ON m.id = l.module_id
        GROUP BY m.course_id
    ) as lesson_counts ON c.id = lesson_counts.course_id
    LEFT JOIN (
        SELECT course_id, COUNT(id) as total_projects
        FROM projects
        GROUP BY course_id
    ) as project_counts ON c.id = project_counts.course_id
    WHERE uc.user_id = ?
    ORDER BY c.course_name
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courses_result = $stmt->get_result();
$enrolled_courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $enrolled_courses[] = $row;
}
$stmt->close();
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Codex</title>
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
                <li><a href='dashboard.php' class="nav-active">Dashboard</a></li>
                <li><a href='#'>Quiz</a></li>
                <li><a href='profile.php'>Profile</a></li>
                <li><a href='../logout.php'>Log out</a></li>
            </ul>
        </nav>
    </header>

    <!-- Main content -->
    <main>
        <section class="dashboard">
            <div class="dashboard-container">
                <h1 class="dashboard-title">Dashboard</h1>
                <p class="dashboard-subtitle"><?php echo htmlspecialchars($user_name); ?></p>
                
                <div class="courses-section">
                    <h2 class="courses-heading">Courses</h2>
                    
                    <?php if (empty($enrolled_courses)): ?>
                        <p class="no-courses">No courses enrolled yet. <a href="../../index.html#courses">Browse courses</a> to get started.</p>
                    <?php else: ?>
                        <div class="courses-list">
                            <?php foreach ($enrolled_courses as $course): 
                                $lessons = intval($course['total_lessons']);
                                $projects = intval($course['total_projects']);
                            ?>
                                <div class="course-item">
                                    <div class="course-info">
                                        <h3 class="course-title"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                        <p class="course-details">
                                            <?php echo $lessons; ?> Lessons • <?php echo $projects; ?> Project<?php echo $projects != 1 ? 's' : ''; ?>
                                        </p>
                                    </div>
                                    <a href="student_view_course.php?course_id=<?php echo $course['id']; ?>" class="btn-view">View</a>
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
