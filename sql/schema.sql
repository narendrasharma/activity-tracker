-- ============================================================
-- Mini Activity Tracking & Audit System — Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS `activity_tracker`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `activity_tracker`;

-- ============================================================
-- Activity Logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED    NOT NULL,
    `action`      VARCHAR(100)    NOT NULL,
    `metadata`    JSON            NOT NULL DEFAULT (JSON_OBJECT()),
    `ip_address`  VARCHAR(45)     NOT NULL DEFAULT '',   -- supports IPv6
    `user_agent`  VARCHAR(512)    NOT NULL DEFAULT '',
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Frequently filtered columns
    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_action`     (`action`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_ip_address` (`ip_address`),

    -- Composite index for anomaly detection queries
    INDEX `idx_user_created` (`user_id`, `created_at`),

    -- Composite index for common filter + sort patterns
    INDEX `idx_user_action_created` (`user_id`, `action`, `created_at`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  ROW_FORMAT=COMPRESSED;


-- ============================================================
-- (Optional) Users reference table — for JOIN use cases
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(80)  NOT NULL,
    `email`      VARCHAR(190) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
