<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?error=login_required");
    exit();
}

// Check if user is approved
if (!isset($_SESSION['status']) || $_SESSION['status'] !== 'approved') {
    header("Location: ../login.php?error=pending_approval");
    exit();
}

// Check if user is a teacher
if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: teacher_dashboard.php");
    exit();
}

require_once '../../config/db_connection.php';

// Get course ID from URL
if (!isset($_GET['course_id'])) {
    header("Location: teacher_dashboard.php?error=invalid_course");
    exit();
}

$course_id = intval($_GET['course_id']);
$user_id = $_SESSION['user_id'];

// Get course information
$conn = getDBConnection();
$course_stmt = $conn->prepare("SELECT id, course_code, course_name, description FROM courses WHERE id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

if ($course_result->num_rows === 0) {
    $course_stmt->close();
    closeDBConnection($conn);
    header("Location: teacher_dashboard.php?error=course_not_found");
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

// Get course files
$files_stmt = $conn->prepare("
    SELECT id, file_name, original_name, file_path, file_type, file_size, uploaded_at,
           module_id, lesson_id
    FROM course_files 
    WHERE course_id = ? 
    ORDER BY uploaded_at DESC
");
$files_stmt->bind_param("i", $course_id);
$files_stmt->execute();
$files_result = $files_stmt->get_result();
$files = [];
while ($row = $files_result->fetch_assoc()) {
    $files[] = $row;
}
$files_stmt->close();

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
                <h1 class="dashboard-title"><?php echo htmlspecialchars($course['course_name']); ?></h1>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-message" style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        $success = $_GET['success'];
                        if ($success == 'file_uploaded') echo 'File uploaded successfully and lesson/project created.';
                        elseif ($success == 'files_uploaded') echo 'Files uploaded successfully and lessons/projects created.';
                        elseif ($success == 'file_removed') echo 'File removed successfully.';
                        elseif ($success == 'module_created') echo 'Module created successfully.';
                        elseif ($success == 'module_removed') echo 'Module removed successfully.';
                        elseif ($success == 'lesson_removed') echo 'Lesson removed successfully.';
                        elseif ($success == 'project_removed') echo 'Project removed successfully.';
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php
                        $error = $_GET['error'];
                        if ($error == 'upload_failed') echo 'File upload failed. Please try again.';
                        elseif ($error == 'invalid_file') echo 'Invalid file type. Only PDF and Word documents are allowed.';
                        elseif ($error == 'file_too_large') echo 'File is too large. Maximum size is 10MB.';
                        elseif ($error == 'remove_failed') echo 'Failed to remove file. Please try again.';
                        elseif ($error == 'empty_module_name') echo 'Module name cannot be empty.';
                        elseif ($error == 'module_creation_failed') echo 'Failed to create module. Please try again.';
                        elseif ($error == 'module_not_found') echo 'Module not found.';
                        elseif ($error == 'remove_failed') echo 'Failed to remove module. Please try again.';
                        elseif ($error == 'lesson_not_found') echo 'Lesson not found.';
                        elseif ($error == 'file_not_found') echo 'File not found.';
                        elseif ($error == 'file_missing') echo 'File is missing from server.';
                        elseif ($error == 'project_not_found') echo 'Project not found.';
                        else echo 'An error occurred.';
                        ?>
                    </div>
                <?php endif; ?>
                
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
                            <button class="btn-edit" type="button">Edit</button>
                        </div>
                        
                        <div class="lessons-section">
                            <?php if (empty($module_lessons)): ?>
                                <p class="no-items">No lessons yet.</p>
                            <?php else: ?>
                                <ul class="lessons-list">
                                    <?php foreach ($module_lessons as $lesson): ?>
                                        <li class="lesson-item">
                                            <a href="<?php echo $lesson['file_id'] ? 'view_lesson.php?lesson_id=' . $lesson['id'] . '&course_id=' . $course_id : '#'; ?>" class="lesson-link">Lesson <?php echo $lesson['display_order'] + 1; ?> - <?php echo htmlspecialchars($lesson['lesson_name']); ?></a>
                                            <div class="lesson-actions">
                                                <?php if ($lesson['file_id']): ?>
                                                    <a href="view_lesson.php?lesson_id=<?php echo $lesson['id']; ?>&course_id=<?php echo $course_id; ?>" class="btn-view-lesson">View</a>
                                                <?php endif; ?>
                                                <a href="remove_lesson.php?lesson_id=<?php echo $lesson['id']; ?>&course_id=<?php echo $course_id; ?>" class="btn-remove-link" onclick="return confirm('Are you sure you want to remove this lesson? This will also delete the associated file.');">Remove</a>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <div class="module-actions">
                            <form method="POST" action="upload_file.php" enctype="multipart/form-data" class="upload-form" style="display: inline;">
                                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                <label for="file-<?php echo $module['id']; ?>" class="btn-upload" style="cursor: pointer; display: inline-block;">Upload Files</label>
                                <input type="file" name="file[]" id="file-<?php echo $module['id']; ?>" multiple accept=".pdf,.doc,.docx" style="display: none;" onchange="this.form.submit()">
                            </form>
                        </div>
                        
                        <div class="module-divider"></div>
                        
                        <div class="projects-section">
                            <h3 class="projects-title">Projects</h3>
                            <?php if (empty($module_projects)): ?>
                                <p class="no-items">No projects yet.</p>
                                <form method="POST" action="upload_file.php" enctype="multipart/form-data" class="upload-form" style="margin-top: 1rem;">
                                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                    <input type="hidden" name="upload_type" value="project">
                                    <label for="project-file-<?php echo $module['id']; ?>" class="btn-upload">Upload Files</label>
                                    <input type="file" name="file[]" id="project-file-<?php echo $module['id']; ?>" multiple accept=".pdf,.doc,.docx" style="display: none;" onchange="this.form.submit()">
                                </form>
                            <?php else: ?>
                                <ul class="projects-list">
                                    <?php foreach ($module_projects as $project): ?>
                                        <li class="project-item">
                                            <a href="<?php echo $project['file_id'] ? 'view_project.php?project_id=' . $project['id'] . '&course_id=' . $course_id : '#'; ?>" class="project-link"><?php echo htmlspecialchars($project['project_name']); ?></a>
                                            <div class="lesson-actions">
                                                <?php if ($project['file_id']): ?>
                                                    <a href="view_project.php?project_id=<?php echo $project['id']; ?>&course_id=<?php echo $course_id; ?>" class="btn-view-lesson">View</a>
                                                <?php endif; ?>
                                                <a href="remove_project.php?project_id=<?php echo $project['id']; ?>&course_id=<?php echo $course_id; ?>" class="btn-remove-link" onclick="return confirm('Are you sure you want to remove this project? This will also delete the associated file.');">Remove</a>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <form method="POST" action="upload_file.php" enctype="multipart/form-data" class="upload-form" style="margin-top: 1rem;">
                                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                    <input type="hidden" name="upload_type" value="project">
                                    <label for="project-file-<?php echo $module['id']; ?>" class="btn-upload">Upload Files</label>
                                    <input type="file" name="file[]" id="project-file-<?php echo $module['id']; ?>" multiple accept=".pdf,.doc,.docx" style="display: none;" onchange="this.form.submit()">
                                </form>
                            <?php endif; ?>
                            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                                <a href="remove_module.php?module_id=<?php echo $module['id']; ?>&course_id=<?php echo $course_id; ?>" class="btn-remove-module" onclick="return confirm('Are you sure you want to remove this module? This will also remove all lessons and projects in this module.');">Remove Module</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($modules)): ?>
                    <div class="course-module-section">
                        <div class="add-module-section">
                            <h2 class="section-heading">No modules created yet</h2>
                            <p class="no-items">Create a module to start adding lessons and projects.</p>
                            <form method="POST" action="add_module.php" class="add-module-form">
                                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <div class="form-group-inline">
                                    <input type="text" name="module_name" placeholder="Enter module name" required class="module-input">
                                    <button type="submit" class="btn-add-module">Add Module</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Add Module Button (when modules exist) -->
                    <div class="add-module-section" style="margin-top: 2rem;">
                        <form method="POST" action="add_module.php" class="add-module-form">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            <div class="form-group-inline">
                                <input type="text" name="module_name" placeholder="Add another module" required class="module-input">
                                <button type="submit" class="btn-add-module">Add Module</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Files Section -->
                <?php if (!empty($files)): ?>
                    <div class="course-files-section">
                        <h2 class="section-heading">Uploaded Files</h2>
                        <div class="files-list">
                            <?php foreach ($files as $file): 
                                $file_size_mb = round($file['file_size'] / 1024 / 1024, 2);
                                $file_icon = strpos($file['file_type'], 'pdf') !== false ? '📄' : '📝';
                            ?>
                                <div class="file-item">
                                    <div class="file-info">
                                        <span class="file-icon"><?php echo $file_icon; ?></span>
                                        <div>
                                            <h4 class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></h4>
                                            <p class="file-details"><?php echo $file_size_mb; ?> MB • Uploaded <?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="file-actions">
                                        <a href="../../uploads/course_files/<?php echo htmlspecialchars($file['file_path']); ?>" class="btn-view" target="_blank">View</a>
                                        <a href="remove_file.php?file_id=<?php echo $file['id']; ?>&course_id=<?php echo $course_id; ?>" class="btn-remove-link" onclick="return confirm('Are you sure you want to remove this file?');">Remove</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

</body>
</html>
