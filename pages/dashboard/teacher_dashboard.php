<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login if not authenticated
    header("Location: ../login.php?error=login_required");
    exit();
}

// Redirect students to student dashboard
if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'student') {
    header("Location: dashboard.php");
    exit();
}

require_once '../../config/db_connection.php';

// Get user information from session
$user_name = $_SESSION['name'];

// Get all courses
$conn = getDBConnection();
$courses_stmt = $conn->prepare("SELECT id, course_code, course_name FROM courses ORDER BY course_name");
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
$all_courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $all_courses[] = $row;
}
$courses_stmt->close();

// Get all students enrolled in courses
$students_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.student_id, u.email
    FROM users u
    JOIN user_courses uc ON u.id = uc.user_id
    WHERE u.account_type = 'student'
    ORDER BY u.name
");
$students_stmt->execute();
$students_result = $students_stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}
$students_stmt->close();
closeDBConnection($conn);

// Course data with lessons and projects
$course_data = [
    'networks' => ['lessons' => 15, 'projects' => 2],
    'data-structures' => ['lessons' => 10, 'projects' => 1],
    'web-dev' => ['lessons' => 7, 'projects' => 3],
    'software-eng' => ['lessons' => 12, 'projects' => 2],
    'javascript' => ['lessons' => 20, 'projects' => 5],
    'python' => ['lessons' => 18, 'projects' => 4]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Codex</title>
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
</head>
<body>
    <!-- Navigation bar + Header -->
    <header id="site-header">
        <nav id="navbar" class="navbar">
            <div class="nav-brand">
                <a href="../../index.html" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; color: inherit;">
                    <div class="logo-icon">C</div>
                    <span class="logo-text">Codex</span>
                </a>
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
                <h1 class="dashboard-title">Dashboard</h1>
                <p class="dashboard-subtitle"><?php echo htmlspecialchars($user_name); ?></p>
                
                <div class="teacher-dashboard-grid">
                    <!-- Left Section: My Courses -->
                    <div class="dashboard-section">
                        <h2 class="section-heading">My Courses</h2>
                        
                        <?php if (empty($all_courses)): ?>
                            <p class="no-items">No courses available.</p>
                        <?php else: ?>
                            <div class="items-list">
                                <?php foreach ($all_courses as $course): 
                                    $course_code = $course['course_code'];
                                    $lessons = isset($course_data[$course_code]['lessons']) ? $course_data[$course_code]['lessons'] : 0;
                                    $projects = isset($course_data[$course_code]['projects']) ? $course_data[$course_code]['projects'] : 0;
                                ?>
                                    <div class="list-item">
                                        <div class="item-info">
                                            <h3 class="item-title"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                            <p class="item-details">
                                                <?php echo $lessons; ?> Lessons <?php echo $projects; ?> Projects
                                            </p>
                                        </div>
                                        <a href="#" class="btn-view">View</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Section: Students -->
                    <div class="dashboard-section">
                        <h2 class="section-heading">Students</h2>
                        
                        <?php if (empty($students)): ?>
                            <p class="no-items">No students enrolled yet.</p>
                        <?php else: ?>
                            <div class="items-list">
                                <?php foreach ($students as $student): ?>
                                    <div class="list-item">
                                        <div class="item-info">
                                            <h3 class="item-title"><?php echo htmlspecialchars($student['name']); ?></h3>
                                            <p class="item-details"><?php echo htmlspecialchars($student['student_id']); ?></p>
                                        </div>
                                        <a href="#" class="btn-view">View</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
</body>
</html>
