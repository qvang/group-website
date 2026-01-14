-- Migration: Add status field to users table
-- Run this if you already have an existing database

USE codex_db;

-- Add status column to users table
ALTER TABLE users 
ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' 
AFTER account_type;

-- Set all existing users to 'approved' status (so they don't lose access)
UPDATE users SET status = 'approved' WHERE status IS NULL OR status = '';

-- Set admin account to approved
UPDATE users SET status = 'approved' WHERE account_type = 'admin';
