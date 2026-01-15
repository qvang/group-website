-- Migration: Add project_id column to course_files table
-- Run this if you have an existing database that needs to be updated

ALTER TABLE course_files 
ADD COLUMN project_id INT NULL AFTER lesson_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
ADD INDEX idx_project_id (project_id);
