-- =========================================================
-- database.sql
-- Run this whole file once in phpMyAdmin (Import) to create
-- the database and its tables.
-- =========================================================

CREATE DATABASE IF NOT EXISTS todolist;
USE todolist;

-- ---------------------------------------------------------
-- Table: users
-- One row per registered student. phone_number is optional —
-- only needed if the student wants SMS reminders.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    phone_number  VARCHAR(20)   DEFAULT NULL,   -- e.g. +4512345678, for SMS reminders
    created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- Table: tasks
-- One row per task. module_name, assessment_weight,
-- estimated_hours, and progress_percent feed the recommender.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    task_id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NOT NULL,
    title             VARCHAR(150) NOT NULL,
    module_name       VARCHAR(100) DEFAULT NULL,
    due_date          DATE NOT NULL,
    assessment_weight TINYINT UNSIGNED DEFAULT 20,
    estimated_hours   DECIMAL(4,1) UNSIGNED DEFAULT 2.0,
    progress_percent  TINYINT UNSIGNED DEFAULT 0,
    status            ENUM('active','complete') NOT NULL DEFAULT 'active',
    priority_score    DECIMAL(5,2) DEFAULT 0,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at      DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- Table: reminders
-- One row per scheduled reminder (in-app and/or SMS).
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS reminders (
    reminder_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id     INT NOT NULL,
    remind_at   DATETIME NOT NULL,
    sent        TINYINT(1) NOT NULL DEFAULT 0,
    sms_sent    TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
);
