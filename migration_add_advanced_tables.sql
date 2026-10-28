-- =========================================================
-- migration_add_advanced_tables.sql
-- Run this ONLY if you already have the database from before.
-- Adds the 4 new tables without touching existing data.
-- Safe to run once; running twice just shows a harmless
-- "table already exists" error.
-- =========================================================

USE todolist;

CREATE TABLE IF NOT EXISTS recommender_weights (
    user_id      INT PRIMARY KEY,
    w_urgency    TINYINT UNSIGNED NOT NULL DEFAULT 40,
    w_weight     TINYINT UNSIGNED NOT NULL DEFAULT 30,
    w_workload   TINYINT UNSIGNED NOT NULL DEFAULT 15,
    w_progress   TINYINT UNSIGNED NOT NULL DEFAULT 15,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS priority_explanations (
    explanation_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    task_id         INT NOT NULL,
    urgency_score   DECIMAL(5,4) NOT NULL,
    weight_score    DECIMAL(5,4) NOT NULL,
    workload_score  DECIMAL(5,4) NOT NULL,
    progress_score  DECIMAL(5,4) NOT NULL,
    total_score     DECIMAL(5,4) NOT NULL,
    explanation     TEXT NOT NULL,
    calculated_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id            INT PRIMARY KEY,
    sms_enabled        TINYINT(1) NOT NULL DEFAULT 1,
    first_reminder_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
    second_reminder_days TINYINT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_logs (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    action      VARCHAR(50) NOT NULL,
    details     VARCHAR(255) DEFAULT NULL,
    logged_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
