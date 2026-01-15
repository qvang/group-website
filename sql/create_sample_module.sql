-- Sample SQL to create a module and lesson for testing
-- Run this after the main schema to create sample data

USE codex_db;

-- Example: Create a module for the Networks course
-- Replace course_id with the actual course ID from your courses table
INSERT INTO modules (course_id, module_name, display_order) 
SELECT id, 'Introduction to Networks', 0 
FROM courses 
WHERE course_code = 'networks' 
LIMIT 1;

-- Create sample lessons for the module
INSERT INTO lessons (module_id, lesson_name, display_order)
SELECT m.id, 'Lesson 1 - Introduction', 0
FROM modules m
JOIN courses c ON m.course_id = c.id
WHERE c.course_code = 'networks' AND m.module_name = 'Introduction to Networks'
LIMIT 1;

INSERT INTO lessons (module_id, lesson_name, display_order)
SELECT m.id, 'Lesson 2', 1
FROM modules m
JOIN courses c ON m.course_id = c.id
WHERE c.course_code = 'networks' AND m.module_name = 'Introduction to Networks'
LIMIT 1;

-- You can add more lessons by incrementing the display_order
