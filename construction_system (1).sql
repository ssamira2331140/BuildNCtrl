-- ============================================================
-- BuildNCtrl — Construction Project & Contractor Management
-- Database Schema: construction_system.sql
-- Compatible with: MySQL 5.7+ / MariaDB (XAMPP)
-- How to import: phpMyAdmin → New Database → Import this file
-- ============================================================

-- STEP 1: Create and select the database
CREATE DATABASE IF NOT EXISTS construction_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE construction_system;

-- ============================================================
-- TABLE 1: users
-- Stores ALL user accounts: admin, client, contractor, worker
-- One table for all roles — role column determines permissions
-- ============================================================

CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(100)    NOT NULL,
    last_name       VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,    -- login identifier
    password        VARCHAR(255)    NOT NULL,           -- bcrypt hash (never plain text)
    role            ENUM('admin','client','contractor','worker') NOT NULL DEFAULT 'client',
    contact         VARCHAR(20)     DEFAULT NULL,
    specialization  VARCHAR(150)    DEFAULT NULL,       -- only used for contractors
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE 2: project_requests
-- Clients submit project requests.
-- Admin reviews and either approves (→ creates a project row)
-- or rejects them.
-- ============================================================

CREATE TABLE project_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT             NOT NULL,           -- who submitted the request
    project_name    VARCHAR(200)    NOT NULL,
    location        VARCHAR(200)    NOT NULL,
    project_type    ENUM('Residential','Commercial','Industrial','Infrastructure') NOT NULL,
    budget          DECIMAL(15,2)   NOT NULL,
    deadline        DATE            NOT NULL,
    description     TEXT            DEFAULT NULL,
    document_path   VARCHAR(300)    DEFAULT NULL,       -- uploaded file path (if any)
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    submitted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key: client_id must exist in users table
    CONSTRAINT fk_req_client
        FOREIGN KEY (client_id) REFERENCES users(id)
        ON DELETE CASCADE
);

-- ============================================================
-- TABLE 3: projects
-- Created by admin when a project_request is approved.
-- Links client, contractor, and the original request.
-- ============================================================

CREATE TABLE projects (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    request_id      INT             DEFAULT NULL,       -- which request this came from (nullable — admin can create projects directly)
    client_id       INT             NOT NULL,
    contractor_id   INT             DEFAULT NULL,       -- assigned after project creation
    project_name    VARCHAR(200)    NOT NULL,
    location        VARCHAR(200)    NOT NULL,
    project_type    VARCHAR(100)    DEFAULT NULL,
    budget          DECIMAL(15,2)   NOT NULL DEFAULT 0,
    start_date      DATE            DEFAULT NULL,
    end_date        DATE            DEFAULT NULL,       -- target completion date
    description     TEXT            DEFAULT NULL,
    status          ENUM('pending','active','completed','on_hold') NOT NULL DEFAULT 'pending',
    progress        TINYINT UNSIGNED NOT NULL DEFAULT 0 CHECK (progress BETWEEN 0 AND 100),
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_proj_request
        FOREIGN KEY (request_id) REFERENCES project_requests(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_proj_client
        FOREIGN KEY (client_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_proj_contractor
        FOREIGN KEY (contractor_id) REFERENCES users(id)
        ON DELETE SET NULL
);

-- ============================================================
-- TABLE 4: milestones
-- Each project has multiple milestones.
-- Contractor updates milestone status.
-- Admin can see all milestones via view_details.php
-- ============================================================

CREATE TABLE milestones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT             NOT NULL,
    title           VARCHAR(200)    NOT NULL,
    description     TEXT            DEFAULT NULL,
    start_date      DATE            DEFAULT NULL,
    end_date        DATE            DEFAULT NULL,       -- milestone deadline
    budget          DECIMAL(15,2)   NOT NULL DEFAULT 0,
    status          ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mile_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE
);

-- ============================================================
-- TABLE 5: workers
-- Tracks which workers are assigned to which project,
-- by which contractor. Workers are users with role='worker'.
-- A contractor can hire workers for their projects.
-- ============================================================

CREATE TABLE project_workers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT             NOT NULL,
    worker_id       INT             NOT NULL,           -- user with role='worker'
    contractor_id   INT             NOT NULL,           -- who hired this worker
    hired_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Prevent assigning the same worker to the same project twice
    UNIQUE KEY uq_worker_project (project_id, worker_id),

    CONSTRAINT fk_pw_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_pw_worker
        FOREIGN KEY (worker_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_pw_contractor
        FOREIGN KEY (contractor_id) REFERENCES users(id)
        ON DELETE CASCADE
);

-- ============================================================
-- TABLE 6: tasks
-- Tasks are assigned to workers by contractors.
-- Tasks belong to a project and optionally to a milestone.
-- Workers see tasks in worker/mytasks.php
-- ============================================================

CREATE TABLE tasks (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT             NOT NULL,
    milestone_id    INT             DEFAULT NULL,       -- optional: task can belong to a milestone
    worker_id       INT             NOT NULL,           -- assigned to this worker
    assigned_by     INT             NOT NULL,           -- contractor who assigned it
    title           VARCHAR(200)    NOT NULL,
    description     TEXT            DEFAULT NULL,
    priority        ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    due_date        DATE            DEFAULT NULL,
    status          ENUM('pending','accepted','in_progress','completed','rejected') NOT NULL DEFAULT 'pending',
    assigned_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_task_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_task_milestone
        FOREIGN KEY (milestone_id) REFERENCES milestones(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_task_worker
        FOREIGN KEY (worker_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_task_assigner
        FOREIGN KEY (assigned_by) REFERENCES users(id)
        ON DELETE CASCADE
);

-- ============================================================
-- TABLE 7: work_logs
-- Daily work log entries.
-- Both contractors AND workers can submit logs.
-- role_type column records who submitted it.
-- ============================================================

CREATE TABLE work_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT             NOT NULL,
    submitted_by    INT             NOT NULL,           -- user ID (worker or contractor)
    role_type       ENUM('worker','contractor') NOT NULL,
    description     TEXT            NOT NULL,
    log_date        DATE            NOT NULL DEFAULT (CURRENT_DATE),
    submitted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_log_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_log_user
        FOREIGN KEY (submitted_by) REFERENCES users(id)
        ON DELETE CASCADE
);

-- ============================================================
-- TABLE 8: materials
-- Materials tracked per project.
-- Added by contractors. Admin can view in admin/materials.php
-- ============================================================

CREATE TABLE materials (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT             NOT NULL,
    added_by        INT             NOT NULL,           -- contractor who added it
    material_name   VARCHAR(150)    NOT NULL,
    quantity        DECIMAL(10,2)   NOT NULL DEFAULT 0,
    unit            VARCHAR(50)     NOT NULL,           -- Bags, Tons, Kg, Pieces, etc.
    unit_cost       DECIMAL(10,2)   NOT NULL DEFAULT 0,
    date_used       DATE            DEFAULT NULL,
    status          ENUM('in_stock','low_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mat_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_mat_user
        FOREIGN KEY (added_by) REFERENCES users(id)
        ON DELETE CASCADE
);

-- ============================================================
-- TABLE 9: messages
-- Simple chat system.
-- sender sends a message to receiver.
-- Each page loads messages between the logged-in user
-- and the selected conversation partner.
-- ============================================================

CREATE TABLE messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sender_id       INT             NOT NULL,
    receiver_id     INT             NOT NULL,
    message         TEXT            NOT NULL,
    is_read         TINYINT(1)      NOT NULL DEFAULT 0, -- 0 = unread, 1 = read
    sent_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_msg_sender
        FOREIGN KEY (sender_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_msg_receiver
        FOREIGN KEY (receiver_id) REFERENCES users(id)
        ON DELETE CASCADE
);

-- ============================================================
-- INDEXES
-- Speed up common queries (searching by project, user, status)
-- ============================================================

-- project_requests: admin views by status and by client
CREATE INDEX idx_req_client   ON project_requests(client_id);
CREATE INDEX idx_req_status   ON project_requests(status);

-- projects: often filtered by status, client, or contractor
CREATE INDEX idx_proj_client     ON projects(client_id);
CREATE INDEX idx_proj_contractor ON projects(contractor_id);
CREATE INDEX idx_proj_status     ON projects(status);

-- milestones: looked up by project
CREATE INDEX idx_mile_project  ON milestones(project_id);

-- tasks: worker sees tasks assigned to them
CREATE INDEX idx_task_worker   ON tasks(worker_id);
CREATE INDEX idx_task_project  ON tasks(project_id);

-- work_logs: contractor sees logs for their projects
CREATE INDEX idx_log_project   ON work_logs(project_id);
CREATE INDEX idx_log_user      ON work_logs(submitted_by);

-- materials: looked up by project
CREATE INDEX idx_mat_project   ON materials(project_id);

-- messages: both sender and receiver need fast lookup
CREATE INDEX idx_msg_sender    ON messages(sender_id);
CREATE INDEX idx_msg_receiver  ON messages(receiver_id);

-- ============================================================
-- SAMPLE DATA
-- One admin account + one of each role so you can test
-- immediately after importing.
--
-- PASSWORDS (all same for easy testing):
--   admin@buildnctrl.com  → Admin@123
--   client@test.com       → Admin@123
--   contractor@test.com   → Admin@123
--   worker@test.com       → Admin@123
--
-- These passwords are hashed with PHP password_hash() using
-- PASSWORD_BCRYPT. Do NOT change the hash strings below.
-- ============================================================

INSERT INTO users (first_name, last_name, email, password, role, contact, specialization) VALUES

-- Admin account
('Farah',   'Zabin',
 'admin@buildnctrl.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin',      '01900000001', NULL),

-- Client account
('Sumsun',  'Samira',
 'client@test.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'client',     '01900000002', NULL),

-- Contractor account
('Naziha',  'Islam',
 'contractor@test.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'contractor', '01900000003', 'Residential Construction'),

-- Worker account
('Maria',   'Tabassum',
 'worker@test.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'worker',     '01900000004', NULL);

-- ============================================================
-- SAMPLE PROJECT REQUEST (submitted by client, id=2)
-- ============================================================

INSERT INTO project_requests
    (client_id, project_name, location, project_type, budget, deadline, description, status)
VALUES
    (2, 'House Construction', 'Chittagong', 'Residential',
     150000.00, '2026-09-19',
     'Residential building construction including foundation, structure, electrical and finishing work.',
     'approved'),

    (2, 'School Building', 'Dhaka', 'Commercial',
     200000.00, '2026-07-03',
     'New school building with classrooms, labs, and sports facilities.',
     'pending');

-- ============================================================
-- SAMPLE PROJECTS (approved by admin)
-- ============================================================

INSERT INTO projects
    (request_id, client_id, contractor_id, project_name, location, project_type,
     budget, start_date, end_date, description, status, progress)
VALUES
    (1, 2, 3, 'House Construction', 'Chittagong', 'Residential',
     150000.00, '2025-12-10', '2026-09-19',
     'Residential building construction including foundation, structure, electrical and finishing work.',
     'active', 34);

-- ============================================================
-- SAMPLE MILESTONES (for project id=1)
-- ============================================================

INSERT INTO milestones (project_id, title, description, start_date, end_date, budget, status) VALUES
    (1, 'Foundation',  'Initial base structure',          '2025-12-10', '2025-12-25', 10000.00, 'completed'),
    (1, 'Structure',   'Building frame construction',     '2025-12-26', '2026-01-20', 25000.00, 'completed'),
    (1, 'Roofing',     'Roof framework and covering',     '2026-01-21', '2026-02-20', 15000.00, 'in_progress'),
    (1, 'Electrical',  'Wiring and electrical setup',     '2026-02-21', '2026-03-20',  8000.00, 'pending'),
    (1, 'Interior',    'Interior design and finishing',   '2026-03-21', '2026-05-30', 20000.00, 'pending');

-- ============================================================
-- SAMPLE PROJECT WORKER ASSIGNMENT
-- Worker (id=4) hired by Contractor (id=3) for Project (id=1)
-- ============================================================

INSERT INTO project_workers (project_id, worker_id, contractor_id) VALUES
    (1, 4, 3);

-- ============================================================
-- SAMPLE TASK (assigned to worker id=4 by contractor id=3)
-- ============================================================

INSERT INTO tasks
    (project_id, milestone_id, worker_id, assigned_by, title, description, priority, due_date, status)
VALUES
    (1, 3, 4, 3, 'Roofing Framework',
     'Install and align roofing framework on the main structure.',
     'high', '2026-02-15', 'in_progress'),

    (1, 4, 4, 3, 'Electrical Wiring — Floor 1',
     'Complete outer wall painting before inspection.',
     'medium', '2026-03-10', 'pending');

-- ============================================================
-- SAMPLE WORK LOGS
-- ============================================================

INSERT INTO work_logs (project_id, submitted_by, role_type, description, log_date) VALUES
    (1, 3, 'contractor',
     'Supervised foundation work and ensured proper concrete mixing; coordinated with workers to maintain safety standards.',
     '2025-11-24'),

    (1, 4, 'worker',
     'Completed electrical installation on Floor 1. All wiring tested and functional. Materials used: 250m wire, 20 outlets.',
     '2025-11-24');

-- ============================================================
-- SAMPLE MATERIALS
-- ============================================================

INSERT INTO materials (project_id, added_by, material_name, quantity, unit, unit_cost, date_used, status) VALUES
    (1, 3, 'Cement',    200, 'Bags',   5.00,   '2026-05-01', 'in_stock'),
    (1, 3, 'Steel Rod',  50, 'Tons',   2500.00,'2026-05-02', 'in_stock'),
    (1, 3, 'Sand',       10, 'Trucks', 300.00, '2026-05-03', 'low_stock');

-- ============================================================
-- SAMPLE MESSAGES (admin ↔ client)
-- ============================================================

INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES
    (2, 1, 'Hello! I need an approval for a new project.',  1),
    (1, 2, 'Approved already.',                             1);

-- ============================================================
-- END OF SCHEMA
-- ============================================================
