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

// Get user's enrolled courses
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT c.course_code, c.course_name 
    FROM user_courses uc
    JOIN courses c ON uc.course_id = c.id
    WHERE uc.user_id = ?
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

// Course data with lessons and projects (you can make this dynamic later)
$course_data = [
    'networks' => ['lessons' => 15, 'projects' => 2],
    'data-structures' => ['lessons' => 10, 'projects' => 1],
    'web-dev' => ['lessons' => 7, 'projects' => 3],
    'software-eng' => ['lessons' => 12, 'projects' => 2],
    'javascript' => ['lessons' => 20, 'projects' => 5],
    'python' => ['lessons' => 18, 'projects' => 4]
];

// Map course codes to display names
$course_display_names = [
    'networks' => 'Networks',
    'data-structures' => 'Data structure & Algorithms',
    'web-dev' => 'Professional Web Development',
    'software-eng' => 'Software Engineering',
    'javascript' => 'Javascript',
    'python' => 'Python'
];
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
                                $course_code = $course['course_code'];
                                $lessons = isset($course_data[$course_code]['lessons']) ? $course_data[$course_code]['lessons'] : 0;
                                $projects = isset($course_data[$course_code]['projects']) ? $course_data[$course_code]['projects'] : 0;
                                $display_name = isset($course_display_names[$course_code]) ? $course_display_names[$course_code] : $course['course_name'];
                            ?>
                                <div class="course-item">
                                    <div class="course-info">
                                        <h3 class="course-title"><?php echo htmlspecialchars($display_name); ?></h3>
                                        <p class="course-details">
                                            <?php echo $lessons; ?> Lessons • <?php echo $projects; ?> Project<?php echo $projects != 1 ? 's' : ''; ?>
                                        </p>
                                    </div>
                                    <a href="#" class="btn-view">View</a>
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
