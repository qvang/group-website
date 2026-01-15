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

// Check if user is a student
if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'student') {
    header("Location: dashboard.php");
    exit();
}

require_once '../../config/db_connection.php';

// Get course ID from URL
if (!isset($_GET['course_id'])) {
    header("Location: dashboard.php?error=invalid_course");
    exit();
}

$course_id = intval($_GET['course_id']);
$user_id = $_SESSION['user_id'];

// Verify student is enrolled in this course
$conn = getDBConnection();
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

// Get course information
$course_stmt = $conn->prepare("SELECT id, course_code, course_name, description FROM courses WHERE id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

if ($course_result->num_rows === 0) {
    $course_stmt->close();
    closeDBConnection($conn);
    header("Location: dashboard.php?error=course_not_found");
    exit();
}

$course = $course_result->fetch_assoc();
$course_stmt->close();

// Get modules for this course
$modules_stmt = $conn->prepare("
    SELECT id, module_name, display_order 
    FROM modules 
    WHERE course_id = ? 
    ORDER BY display_order ASC, created_at ASC
");
$modules_stmt->bind_param("i", $course_id);
$modules_stmt->execute();
$modules_result = $modules_stmt->get_result();
$modules = [];
while ($row = $modules_result->fetch_assoc()) {
    $modules[] = $row;
}
$modules_stmt->close();

// Get lessons for each module with their associated files
$lessons_by_module = [];
foreach ($modules as $module) {
    $lessons_stmt = $conn->prepare("
        SELECT l.id, l.lesson_name, l.display_order,
               cf.id as file_id, cf.file_path, cf.original_name, cf.file_type
        FROM lessons l
        LEFT JOIN course_files cf ON l.id = cf.lesson_id
        WHERE l.module_id = ? 
        ORDER BY l.display_order ASC, l.created_at ASC
    ");
    $lessons_stmt->bind_param("i", $module['id']);
    $lessons_stmt->execute();
    $lessons_result = $lessons_stmt->get_result();
    $lessons = [];
    while ($lesson_row = $lessons_result->fetch_assoc()) {
        $lessons[] = $lesson_row;
    }
    $lessons_by_module[$module['id']] = $lessons;
    $lessons_stmt->close();
}

// Get projects for this course with their associated files
$projects_stmt = $conn->prepare("
    SELECT p.id, p.project_name, p.description, p.module_id,
           cf.id as file_id, cf.file_path, cf.original_name, cf.file_type
    FROM projects p
    LEFT JOIN course_files cf ON p.id = cf.project_id
    WHERE p.course_id = ? 
    ORDER BY p.display_order ASC, p.created_at ASC
");
$projects_stmt->bind_param("i", $course_id);
$projects_stmt->execute();
$projects_result = $projects_stmt->get_result();
$projects = [];
while ($row = $projects_result->fetch_assoc()) {
    $projects[] = $row;
}
$projects_stmt->close();

// Calculate totals
$total_lessons = 0;
foreach ($lessons_by_module as $module_lessons) {
    $total_lessons += count($module_lessons);
}
$total_projects = count($projects);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_name']); ?> - Codex</title>
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
                <a href="dashboard.php" class="back-link">←</a>
                <h1 class="dashboard-title"><?php echo htmlspecialchars($course['course_name']); ?></h1>
                
                <?php if (empty($modules)): ?>
                    <p class="no-items">No modules available yet. Please check back later.</p>
                <?php else: ?>
                    <?php foreach ($modules as $module): 
                        $module_lessons = isset($lessons_by_module[$module['id']]) ? $lessons_by_module[$module['id']] : [];
                        $module_projects = array_filter($projects, function($p) use ($module) {
                            return $p['module_id'] == $module['id'];
                        });
                    ?>
                        <div class="course-module-section">
                            <div class="module-header">
                                <div class="module-title-section">
                                    <h2 class="module-title"><?php echo htmlspecialchars($module['module_name']); ?></h2>
                                    <p class="module-summary">
                                        <?php echo count($module_lessons); ?> Lessons <?php echo count($module_projects); ?> Projects
                                    </p>
                                </div>
                            </div>
                            
                            <div class="lessons-section">
                                <?php if (empty($module_lessons)): ?>
                                    <p class="no-items">No lessons yet.</p>
                                <?php else: ?>
                                    <ul class="lessons-list">
                                        <?php foreach ($module_lessons as $lesson): ?>
                                            <li class="lesson-item">
                                                <a href="<?php echo $lesson['file_id'] ? 'view_lesson.php?lesson_id=' . $lesson['id'] . '&course_id=' . $course_id : '#'; ?>" class="lesson-link">Lesson <?php echo $lesson['display_order'] + 1; ?> - <?php echo htmlspecialchars($lesson['lesson_name']); ?></a>
                                                <?php if ($lesson['file_id']): ?>
                                                    <div class="lesson-actions">
                                                        <a href="view_lesson.php?lesson_id=<?php echo $lesson['id']; ?>&course_id=<?php echo $course_id; ?>" class="btn-view-lesson">View</a>
                                                    </div>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            
                            <div class="module-divider"></div>
                            
                            <div class="projects-section">
                                <h3 class="projects-title">Projects</h3>
                                <?php if (empty($module_projects)): ?>
                                    <p class="no-items">No projects yet.</p>
                                <?php else: ?>
                                    <ul class="projects-list">
                                        <?php foreach ($module_projects as $project): ?>
                                            <li class="project-item">
                                                <a href="<?php echo $project['file_id'] ? 'view_project.php?project_id=' . $project['id'] . '&course_id=' . $course_id : '#'; ?>" class="project-link"><?php echo htmlspecialchars($project['project_name']); ?></a>
                                                <?php if ($project['file_id']): ?>
                                                    <div class="lesson-actions">
                                                        <a href="view_project.php?project_id=<?php echo $project['id']; ?>&course_id=<?php echo $course_id; ?>" class="btn-view-lesson">View</a>
                                                    </div>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
</body>
</html>
