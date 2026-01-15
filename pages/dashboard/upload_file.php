<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'teacher') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['course_id']) && isset($_FILES['file'])) {
    $course_id = intval($_POST['course_id']);
    $module_id = isset($_POST['module_id']) && !empty($_POST['module_id']) ? intval($_POST['module_id']) : null;
    $lesson_id = isset($_POST['lesson_id']) && !empty($_POST['lesson_id']) ? intval($_POST['lesson_id']) : null;
    $upload_type = isset($_POST['upload_type']) ? $_POST['upload_type'] : 'lesson'; // 'lesson' or 'project'
    $user_id = $_SESSION['user_id'];
    
    // Allowed file types
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $allowed_extensions = ['pdf', 'doc', 'docx'];
    $max_file_size = 10 * 1024 * 1024; // 10MB
    
    // Verify course exists
    $conn = getDBConnection();
    $course_stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    
    if ($course_result->num_rows === 0) {
        $course_stmt->close();
        closeDBConnection($conn);
        header("Location: view_course.php?course_id=" . $course_id . "&error=course_not_found");
        exit();
    }
    $course_stmt->close();
    
    // Handle multiple files
    $uploaded_count = 0;
    $errors = [];
    
    $files = $_FILES['file'];
    $file_count = is_array($files['name']) ? count($files['name']) : 1;
    
    // If single file upload, convert to array format
    if (!is_array($files['name'])) {
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']]
        ];
    }
    
    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $original_name = $files['name'][$i];
            $file_type = $files['type'][$i];
            $file_size = $files['size'][$i];
            $tmp_name = $files['tmp_name'][$i];
            
            // Get file extension
            $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($file_extension, $allowed_extensions) && !in_array($file_type, $allowed_types)) {
                $errors[] = "File '$original_name' is not a valid PDF or Word document.";
                continue;
            }
            
            // Validate file size
            if ($file_size > $max_file_size) {
                $errors[] = "File '$original_name' is too large. Maximum size is 10MB.";
                continue;
            }
            
            // Generate unique filename
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_dir = '../../uploads/course_files/';
            $file_path = $upload_dir . $unique_filename;
            
            // Move uploaded file
            if (move_uploaded_file($tmp_name, $file_path)) {
                // Verify module exists (required for creating lesson/project)
                if ($module_id === null) {
                    @unlink($file_path);
                    $errors[] = "Module ID is required to create a " . $upload_type . " from file '$original_name'.";
                    continue;
                }
                
                // Create item name from file name (remove extension)
                $item_name = pathinfo($original_name, PATHINFO_FILENAME);
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    if ($upload_type === 'project') {
                        // Get the highest display_order for this course to add new project at the end
                        $order_stmt = $conn->prepare("SELECT MAX(display_order) as max_order FROM projects WHERE course_id = ?");
                        $order_stmt->bind_param("i", $course_id);
                        $order_stmt->execute();
                        $order_result = $order_stmt->get_result();
                        $order_row = $order_result->fetch_assoc();
                        $next_order = ($order_row['max_order'] !== null) ? $order_row['max_order'] + 1 : 0;
                        $order_stmt->close();
                        
                        // Create project
                        $project_stmt = $conn->prepare("INSERT INTO projects (course_id, module_id, project_name, display_order) VALUES (?, ?, ?, ?)");
                        $project_stmt->bind_param("iisi", $course_id, $module_id, $item_name, $next_order);
                        
                        if (!$project_stmt->execute()) {
                            throw new Exception("Failed to create project");
                        }
                        
                        $new_project_id = $project_stmt->insert_id;
                        $project_stmt->close();
                        
                        // Save file info to database, linked to the project
                        $relative_path = $unique_filename; // Store relative path
                        $file_stmt = $conn->prepare("
                            INSERT INTO course_files (course_id, module_id, project_id, file_name, original_name, file_path, file_type, file_size, uploaded_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $file_stmt->bind_param("iiissssii", 
                            $course_id, 
                            $module_id, 
                            $new_project_id, 
                            $unique_filename, 
                            $original_name, 
                            $relative_path, 
                            $file_type, 
                            $file_size, 
                            $user_id
                        );
                    } else {
                        // Default: create lesson
                        // Get the highest display_order for this module to add new lesson at the end
                        $order_stmt = $conn->prepare("SELECT MAX(display_order) as max_order FROM lessons WHERE module_id = ?");
                        $order_stmt->bind_param("i", $module_id);
                        $order_stmt->execute();
                        $order_result = $order_stmt->get_result();
                        $order_row = $order_result->fetch_assoc();
                        $next_order = ($order_row['max_order'] !== null) ? $order_row['max_order'] + 1 : 0;
                        $order_stmt->close();
                        
                        // Create lesson
                        $lesson_stmt = $conn->prepare("INSERT INTO lessons (module_id, lesson_name, display_order) VALUES (?, ?, ?)");
                        $lesson_stmt->bind_param("isi", $module_id, $item_name, $next_order);
                        
                        if (!$lesson_stmt->execute()) {
                            throw new Exception("Failed to create lesson");
                        }
                        
                        $new_lesson_id = $lesson_stmt->insert_id;
                        $lesson_stmt->close();
                        
                        // Save file info to database, linked to the lesson
                        $relative_path = $unique_filename; // Store relative path
                        $file_stmt = $conn->prepare("
                            INSERT INTO course_files (course_id, module_id, lesson_id, file_name, original_name, file_path, file_type, file_size, uploaded_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $file_stmt->bind_param("iiissssii", 
                            $course_id, 
                            $module_id, 
                            $new_lesson_id, 
                            $unique_filename, 
                            $original_name, 
                            $relative_path, 
                            $file_type, 
                            $file_size, 
                            $user_id
                        );
                    }
                    
                    if (!$file_stmt->execute()) {
                        throw new Exception("Failed to save file");
                    }
                    
                    $file_stmt->close();
                    $conn->commit();
                    $uploaded_count++;
                } catch (Exception $e) {
                    $conn->rollback();
                    @unlink($file_path);
                    $errors[] = "Failed to create " . $upload_type . " from file '$original_name': " . $e->getMessage();
                }
            } else {
                $errors[] = "Failed to upload file '$original_name'.";
            }
        } else {
            $errors[] = "Error uploading file. Error code: " . $files['error'][$i];
        }
    }
    
    closeDBConnection($conn);
    
    // Redirect with success or error message
    if ($uploaded_count > 0) {
        $success_msg = $uploaded_count == 1 ? "file_uploaded" : "files_uploaded";
        header("Location: view_course.php?course_id=" . $course_id . "&success=" . $success_msg);
    } else {
        $error_msg = !empty($errors) ? urlencode(implode(' ', $errors)) : "upload_failed";
        header("Location: view_course.php?course_id=" . $course_id . "&error=upload_failed");
    }
    exit();
} else {
    header("Location: teacher_dashboard.php");
    exit();
}
?>
