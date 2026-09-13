-- =========================================================
-- migration_add_sms.sql
-- Run this ONLY if you already have the database from before
-- and don't want to reimport everything. Safe to run once —
-- running it twice just shows a harmless "duplicate column" error.
-- =========================================================

USE todolist;

ALTER TABLE users
    ADD COLUMN phone_number VARCHAR(20) DEFAULT NULL AFTER password_hash;

ALTER TABLE reminders
    ADD COLUMN sms_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER sent;
