-- Tasks Two-Way Synchronisation & API Keys Migration

CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'open',
    `priority` VARCHAR(50) NOT NULL DEFAULT 'medium',
    `due_date` DATE NULL,
    `assignee_id` INT UNSIGNED NULL,
    `department` VARCHAR(80) NULL,
    `external_ref` VARCHAR(64) NULL,
    `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
    `blocked_reason` TEXT NULL,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_tasks_client_id` (`client_id`),
    INDEX `idx_tasks_assignee_id` (`assignee_id`),
    INDEX `idx_tasks_external_ref` (`external_ref`),
    INDEX `idx_tasks_updated_at` (`updated_at`),
    INDEX `idx_tasks_is_deleted` (`is_deleted`),
    INDEX `idx_tasks_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `key_token` VARCHAR(128) NOT NULL,
    `key_hash` VARCHAR(64) NOT NULL UNIQUE,
    `key_prefix` VARCHAR(16) NOT NULL,
    `scopes` JSON NOT NULL,
    `rate_limit_per_minute` INT UNSIGNED NOT NULL DEFAULT 300,
    `last_used_at` DATETIME NULL,
    `expires_at` DATETIME NULL,
    `status` ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_api_keys_hash` (`key_hash`),
    INDEX `idx_api_keys_token` (`key_token`),
    INDEX `idx_api_keys_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;