-- =========================================================
-- database.sql
-- Full schema, including the advanced additions:
-- priority_explanations, recommender_weights,
-- notification_preferences, activity_logs.
-- =========================================================


CREATE TABLE IF NOT EXISTS users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    phone_number  VARCHAR(20)   DEFAULT NULL,
    created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP
);

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

CREATE TABLE IF NOT EXISTS reminders (
    reminder_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id     INT NOT NULL,
    remind_at   DATETIME NOT NULL,
    sent        TINYINT(1) NOT NULL DEFAULT 0,
    sms_sent    TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- NEW: recommender_weights
-- One row per user, holding their own weight profile for the
-- 4-factor recommender. Falls back to the default 40/30/15/15
-- split (see includes/recommender.php) if a user has no row yet.
-- Weights are stored as whole percentages and should sum to 100.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS recommender_weights (
    user_id      INT PRIMARY KEY,
    w_urgency    TINYINT UNSIGNED NOT NULL DEFAULT 40,
    w_weight     TINYINT UNSIGNED NOT NULL DEFAULT 30,
    w_workload   TINYINT UNSIGNED NOT NULL DEFAULT 15,
    w_progress   TINYINT UNSIGNED NOT NULL DEFAULT 15,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- NEW: priority_explanations
-- One row every time the recommender picks a top task on
-- dashboard load. This is the audit trail: real historical
-- evidence of what the recommender decided, and why, used
-- directly in Chapter 6 evaluation.
-- ---------------------------------------------------------
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

-- ---------------------------------------------------------
-- NEW: notification_preferences
-- One row per user. Lets a student control SMS on/off and
-- how many days before a deadline each reminder fires,
-- instead of the previous fixed 7-day/1-day schedule.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id            INT PRIMARY KEY,
    sms_enabled        TINYINT(1) NOT NULL DEFAULT 1,
    first_reminder_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
    second_reminder_days TINYINT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- NEW: activity_logs
-- One row per key user action. Cheap audit trail, used to
-- strengthen the consistency evaluation and demonstrate
-- standard software engineering logging practice.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    action      VARCHAR(50) NOT NULL,
    details     VARCHAR(255) DEFAULT NULL,
    logged_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- NEW: chat_messages (AI Study Assistant)
-- Stores each question and AI answer, linked to a user and
-- (optionally) a task. Used to give the assistant short-term
-- conversation memory and as evaluation evidence.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    task_id     INT NULL,
    role        ENUM('user','assistant') NOT NULL,
    message     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (task_id)
);
