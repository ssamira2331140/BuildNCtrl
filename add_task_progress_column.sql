-- ============================================================
-- PATCH: Add progress column to tasks table
-- Run this ONCE in phpMyAdmin → SQL tab
-- or run via: mysql -u root construction_system < this_file.sql
-- ============================================================

USE construction_system;

ALTER TABLE tasks
  ADD COLUMN progress TINYINT UNSIGNED NOT NULL DEFAULT 0
  CHECK (progress BETWEEN 0 AND 100)
  AFTER status;

-- Update existing sample data with sensible progress values
-- matching their current status
UPDATE tasks SET progress = 60 WHERE title = 'Roofing Framework';
UPDATE tasks SET progress = 10 WHERE title = 'Electrical Wiring — Floor 1';

-- ============================================================
