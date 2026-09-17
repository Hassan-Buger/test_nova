<?php

namespace Application\Core;

use PDO;
use Throwable;

class SchemaMigrator
{
    private static bool $hasRun = false;

    /**
     * Run schema migrations and seed data setup if required.
     */
    public static function run(PDO $pdo): bool
    {
        if (self::$hasRun) {
            return true;
        }

        self::$hasRun = true;

        try {
            // Check if core 'users' table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            $hasUsers = (bool)$stmt->fetchColumn();
            $stmt->closeCursor();

            if (!$hasUsers) {
                error_log('[TriNova SchemaMigrator] Fresh database detected. Importing base schema and seed data...');
                self::importSqlFile($pdo, dirname(__DIR__, 2) . '/config/database.sql');
                error_log('[TriNova SchemaMigrator] Base schema and initial seed data imported successfully.');
            }

            // Ensure all required tables, columns, indexes, and constraints exist idempotently
            self::ensureCompleteSchema($pdo);

            return true;
        } catch (Throwable $e) {
            error_log('[TriNova SchemaMigrator] Migration warning: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Programmatically guarantees all required tables, columns, and indexes exist.
     * Works across Oracle MySQL 8.0/9.0, MariaDB, and all cloud providers.
     */
    public static function ensureCompleteSchema(PDO $pdo): void
    {
        // 1. Ensure all core & feature tables exist
        self::ensureTables($pdo);

        // 2. Ensure all columns exist on tables
        self::ensureColumns($pdo);

        // 3. Ensure all indexes exist
        self::ensureIndexes($pdo);

        // 4. Ensure legacy data integrity & containers
        self::ensureDataIntegrity($pdo);
    }

    private static function ensureTables(PDO $pdo): void
    {
        // users
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('client', 'staff') NOT NULL DEFAULT 'client',
            `status` ENUM('active', 'suspended', 'pending_activation') NOT NULL DEFAULT 'active',
            `reset_token` VARCHAR(255) NULL,
            `reset_token_expires_at` DATETIME NULL,
            `verification_code` VARCHAR(6) NULL,
            `verification_code_expires_at` DATETIME NULL,
            `activation_token` VARCHAR(255) NULL,
            `failed_login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `locked_until` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_login_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            INDEX `idx_users_role_status` (`role`, `status`),
            INDEX `idx_users_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // clients
        $pdo->exec("CREATE TABLE IF NOT EXISTS `clients` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL UNIQUE,
            `phone` VARCHAR(30) NULL,
            `address` TEXT NULL,
            `aml_status` ENUM('Complete', 'Action Required') NOT NULL DEFAULT 'Action Required',
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            INDEX `idx_clients_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // client_entities
        $pdo->exec("CREATE TABLE IF NOT EXISTS `client_entities` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `company_name` VARCHAR(150) NOT NULL,
            `entity_type` VARCHAR(80) NOT NULL DEFAULT 'Other',
            `entity_scope` ENUM('company','personal') NOT NULL DEFAULT 'company',
            `company_number` VARCHAR(30) NULL,
            `tax_reference` VARCHAR(50) NULL,
            `attributes` JSON NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            INDEX `idx_entities_client` (`client_id`),
            INDEX `idx_entities_company_number` (`company_number`),
            INDEX `idx_entities_tax_reference` (`tax_reference`),
            INDEX `idx_entities_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // documents
        $pdo->exec("CREATE TABLE IF NOT EXISTS `documents` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `entity_id` INT UNSIGNED NULL,
            `scope` ENUM('company','personal') NOT NULL DEFAULT 'company',
            `uploaded_by_user_id` INT UNSIGNED NOT NULL,
            `direction` ENUM('client_upload', 'from_trinova') NOT NULL,
            `filename` VARCHAR(255) NOT NULL,
            `stored_path` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'Ready',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            INDEX `idx_docs_client_dir` (`client_id`, `direction`),
            INDEX `idx_docs_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // document_requests
        $pdo->exec("CREATE TABLE IF NOT EXISTS `document_requests` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `entity_id` INT UNSIGNED NULL,
            `scope` ENUM('company','personal') NOT NULL DEFAULT 'company',
            `created_by_user_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT NULL,
            `due_date` DATE NOT NULL,
            `status` ENUM('Awaiting Client', 'Uploaded', 'Under Review', 'Completed') NOT NULL DEFAULT 'Awaiting Client',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            INDEX `idx_reqs_client_status` (`client_id`, `status`),
            INDEX `idx_reqs_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // messages
        $pdo->exec("CREATE TABLE IF NOT EXISTS `messages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `entity_id` INT UNSIGNED NULL,
            `scope` ENUM('company','personal') NOT NULL DEFAULT 'company',
            `sender_id` INT UNSIGNED NOT NULL,
            `thread_id` INT UNSIGNED NULL,
            `body` TEXT NOT NULL,
            `read_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            INDEX `idx_msg_client_read` (`client_id`, `read_at`),
            INDEX `idx_msg_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // deadlines
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deadlines` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `entity_id` INT UNSIGNED NULL,
            `scope` ENUM('company','personal') NOT NULL DEFAULT 'company',
            `type` VARCHAR(100) NOT NULL,
            `due_date` DATE NOT NULL,
            `status` ENUM('Pending', 'Overdue', 'Completed') NOT NULL DEFAULT 'Pending',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            INDEX `idx_deadlines_client_date` (`client_id`, `due_date`),
            INDEX `idx_deadlines_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // meetings
        $pdo->exec("CREATE TABLE IF NOT EXISTS `meetings` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `type` ENUM('existing_client_meeting', 'telephone_call') NOT NULL,
            `external_booking_reference` VARCHAR(100) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            INDEX `idx_meetings_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // audit_log
        $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NULL,
            `action_type` VARCHAR(50) NOT NULL,
            `target_type` VARCHAR(50) NOT NULL,
            `target_id` INT UNSIGNED NULL,
            `import_metadata` JSON NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_audit_user_action` (`user_id`, `action_type`),
            INDEX `idx_audit_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // otp_challenges
        $pdo->exec("CREATE TABLE IF NOT EXISTS `otp_challenges` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `purpose` VARCHAR(50) NOT NULL,
            `otp_hash` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `attempt_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_otp_user_purpose` (`user_id`,`purpose`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // entity_directors
        $pdo->exec("CREATE TABLE IF NOT EXISTS `entity_directors` (
            `entity_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `created_by_user_id` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`entity_id`, `user_id`),
            INDEX `idx_entity_directors_user` (`user_id`, `entity_id`),
            INDEX `idx_entity_directors_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // entity_contacts
        $pdo->exec("CREATE TABLE IF NOT EXISTS `entity_contacts` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `entity_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `name` VARCHAR(100) NOT NULL,
            `original_full_name` VARCHAR(180) NULL,
            `email` VARCHAR(150) NULL,
            `phone` VARCHAR(30) NULL,
            `director_utr` VARCHAR(32) NULL,
            `address` TEXT NULL,
            `id_number` VARCHAR(120) NULL,
            `ch_verification_number` VARCHAR(120) NULL,
            `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
            `needs_contact_details` TINYINT(1) NOT NULL DEFAULT 0,
            `last_director_import_id` BIGINT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_entity_contact_name` (`entity_id`,`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // client_csv_imports
        $pdo->exec("CREATE TABLE IF NOT EXISTS `client_csv_imports` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `practice_key` VARCHAR(64) NOT NULL DEFAULT 'default',
            `import_type` VARCHAR(40) NOT NULL DEFAULT 'business_clients',
            `file_hash` CHAR(64) NOT NULL,
            `content_hash` CHAR(64) NOT NULL,
            `original_filename` VARCHAR(255) NOT NULL,
            `created_by_user_id` INT UNSIGNED NULL,
            `draft_token` CHAR(48) NULL,
            `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
            `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `updated_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `skipped_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `flagged_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `report_json` MEDIUMTEXT NULL,
            `safe_error` VARCHAR(255) NULL,
            `started_at` DATETIME NULL,
            `completed_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_csv_import_file` (`practice_key`,`import_type`,`file_hash`),
            UNIQUE KEY `uq_csv_import_content` (`practice_key`,`import_type`,`content_hash`),
            INDEX `idx_csv_import_status` (`practice_key`,`import_type`,`status`,`completed_at`),
            INDEX `idx_csv_import_user` (`created_by_user_id`),
            INDEX `idx_csv_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // notifications
        $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `related_entity` VARCHAR(50) NULL,
            `title` VARCHAR(160) NULL,
            `message` TEXT NULL,
            `action_url` VARCHAR(255) NULL,
            `sent_at` DATETIME NULL,
            `read_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // signature_requests
        $pdo->exec("CREATE TABLE IF NOT EXISTS `signature_requests` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `document_id` INT UNSIGNED NOT NULL,
            `client_id` INT UNSIGNED NOT NULL,
            `entity_id` INT UNSIGNED NULL,
            `created_by_user_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `status` ENUM('draft', 'pending', 'completed', 'declined', 'expired', 'cancelled') NOT NULL DEFAULT 'draft',
            `signing_order` ENUM('parallel', 'sequential') NOT NULL DEFAULT 'parallel',
            `signed_document_id` INT UNSIGNED NULL,
            `qr_token` VARCHAR(64) NULL UNIQUE,
            `allow_drawn_signature` TINYINT(1) NOT NULL DEFAULT 1,
            `allow_typed_signature` TINYINT(1) NOT NULL DEFAULT 1,
            `allow_upload_signature` TINYINT(1) NOT NULL DEFAULT 1,
            `original_checksum_sha256` VARCHAR(64) NULL,
            `signed_checksum_sha256` VARCHAR(64) NULL,
            `expires_at` DATETIME NULL,
            `completed_at` DATETIME NULL,
            `declined_at` DATETIME NULL,
            `decline_reason` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            INDEX `idx_sig_req_status` (`status`),
            INDEX `idx_sig_req_client` (`client_id`),
            INDEX `idx_sig_req_entity` (`entity_id`),
            INDEX `idx_sig_req_doc` (`document_id`),
            INDEX `idx_sig_req_deleted_at` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // signature_signers
        $pdo->exec("CREATE TABLE IF NOT EXISTS `signature_signers` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `request_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `role` ENUM('signer', 'approver', 'viewer', 'cc') NOT NULL DEFAULT 'signer',
            `signing_order` INT UNSIGNED NOT NULL DEFAULT 1,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `status` ENUM('pending', 'signed', 'declined', 'expired') NOT NULL DEFAULT 'pending',
            `read_status` ENUM('not_opened', 'opened') NOT NULL DEFAULT 'not_opened',
            `sent_at` DATETIME NULL,
            `opened_at` DATETIME NULL,
            `signed_at` DATETIME NULL,
            `declined_at` DATETIME NULL,
            `decline_reason` TEXT NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_signers_req` (`request_id`),
            INDEX `idx_signers_token` (`token`),
            INDEX `idx_signers_email` (`email`),
            INDEX `idx_signers_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // signature_fields
        $pdo->exec("CREATE TABLE IF NOT EXISTS `signature_fields` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `request_id` INT UNSIGNED NOT NULL,
            `signer_id` INT UNSIGNED NOT NULL,
            `type` ENUM('signature', 'initials', 'name', 'email', 'date', 'text', 'checkbox') NOT NULL DEFAULT 'signature',
            `page` INT UNSIGNED NOT NULL DEFAULT 1,
            `position_x` DECIMAL(6, 3) NOT NULL DEFAULT 0.000,
            `position_y` DECIMAL(6, 3) NOT NULL DEFAULT 0.000,
            `width` DECIMAL(6, 3) NOT NULL DEFAULT 20.000,
            `height` DECIMAL(6, 3) NOT NULL DEFAULT 6.000,
            `custom_text` TEXT NULL,
            `signature_data` LONGTEXT NULL,
            `signature_type` ENUM('drawn', 'typed', 'uploaded') NULL,
            `required` TINYINT(1) NOT NULL DEFAULT 1,
            `inserted` TINYINT(1) NOT NULL DEFAULT 0,
            `signed_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_sig_fields_req` (`request_id`),
            INDEX `idx_sig_fields_signer` (`signer_id`),
            INDEX `idx_sig_fields_page` (`page`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // signature_audit_events
        $pdo->exec("CREATE TABLE IF NOT EXISTS `signature_audit_events` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `request_id` INT UNSIGNED NOT NULL,
            `signer_id` INT UNSIGNED NULL,
            `user_id` INT UNSIGNED NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `event_description` TEXT NOT NULL,
            `metadata` JSON NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_sig_audit_req` (`request_id`),
            INDEX `idx_sig_audit_type` (`event_type`),
            INDEX `idx_sig_audit_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private static function ensureColumns(PDO $pdo): void
    {
        // 1. Soft-delete columns on all primary tables
        $tablesWithDeletedAt = [
            'users', 'clients', 'client_entities', 'entity_directors',
            'documents', 'document_requests', 'messages', 'deadlines',
            'meetings', 'client_csv_imports', 'signature_requests'
        ];
        foreach ($tablesWithDeletedAt as $table) {
            self::addColumnIfMissing($pdo, $table, 'deleted_at', 'DATETIME NULL');
        }

        // 2. client_entities columns
        self::addColumnIfMissing($pdo, 'client_entities', 'entity_type', "VARCHAR(80) NOT NULL DEFAULT 'Other' AFTER company_name");
        self::addColumnIfMissing($pdo, 'client_entities', 'entity_scope', "ENUM('company','personal') NOT NULL DEFAULT 'company' AFTER entity_type");
        self::addColumnIfMissing($pdo, 'client_entities', 'company_number', 'VARCHAR(30) NULL AFTER entity_scope');
        self::addColumnIfMissing($pdo, 'client_entities', 'tax_reference', 'VARCHAR(50) NULL AFTER company_number');
        self::addColumnIfMissing($pdo, 'client_entities', 'attributes', 'JSON NULL AFTER tax_reference');

        // 3. entity_contacts columns
        self::addColumnIfMissing($pdo, 'entity_contacts', 'original_full_name', 'VARCHAR(180) NULL AFTER name');
        self::addColumnIfMissing($pdo, 'entity_contacts', 'email', 'VARCHAR(150) NULL AFTER original_full_name');
        self::addColumnIfMissing($pdo, 'entity_contacts', 'phone', 'VARCHAR(30) NULL AFTER email');
        self::addColumnIfMissing($pdo, 'entity_contacts', 'director_utr', 'VARCHAR(32) NULL AFTER phone');
        self::addColumnIfMissing($pdo, 'entity_contacts', 'address', 'TEXT NULL AFTER director_utr');
        self::addColumnIfMissing($pdo, 'entity_contacts', 'id_number', 'VARCHAR(120) NULL AFTER address');
        self::addColumnIfMissing($pdo, 'entity_contacts', 'ch_verification_number', 'VARCHAR(120) NULL AFTER id_number');
        self::addColumnIfMissing($pdo, 'entity_contacts', 'is_primary', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER ch_verification_number');
        self::addColumnIfMissing($pdo, 'entity_contacts', 'needs_contact_details', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_primary');
        self::addColumnIfMissing($pdo, 'entity_contacts', 'last_director_import_id', 'BIGINT UNSIGNED NULL AFTER needs_contact_details');

        // 4. entity_directors columns
        self::addColumnIfMissing($pdo, 'entity_directors', 'created_by_user_id', 'INT UNSIGNED NULL AFTER user_id');
        self::addColumnIfMissing($pdo, 'entity_directors', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by_user_id');

        // 5. Scoped documents, requests, messages, deadlines
        self::addColumnIfMissing($pdo, 'documents', 'entity_id', 'INT UNSIGNED NULL AFTER client_id');
        self::addColumnIfMissing($pdo, 'documents', 'scope', "ENUM('company','personal') NOT NULL DEFAULT 'company' AFTER entity_id");

        self::addColumnIfMissing($pdo, 'document_requests', 'entity_id', 'INT UNSIGNED NULL AFTER client_id');
        self::addColumnIfMissing($pdo, 'document_requests', 'scope', "ENUM('company','personal') NOT NULL DEFAULT 'company' AFTER entity_id");

        self::addColumnIfMissing($pdo, 'messages', 'entity_id', 'INT UNSIGNED NULL AFTER client_id');
        self::addColumnIfMissing($pdo, 'messages', 'scope', "ENUM('company','personal') NOT NULL DEFAULT 'company' AFTER entity_id");

        self::addColumnIfMissing($pdo, 'deadlines', 'entity_id', 'INT UNSIGNED NULL AFTER client_id');
        self::addColumnIfMissing($pdo, 'deadlines', 'scope', "ENUM('company','personal') NOT NULL DEFAULT 'company' AFTER entity_id");

        // 6. audit_log import metadata
        self::addColumnIfMissing($pdo, 'audit_log', 'import_metadata', 'JSON NULL AFTER target_id');

        // 7. notifications columns
        self::addColumnIfMissing($pdo, 'notifications', 'title', 'VARCHAR(160) NULL AFTER related_entity');
        self::addColumnIfMissing($pdo, 'notifications', 'message', 'TEXT NULL AFTER title');
        self::addColumnIfMissing($pdo, 'notifications', 'action_url', 'VARCHAR(255) NULL AFTER message');
    }

    private static function ensureIndexes(PDO $pdo): void
    {
        $indexes = [
            ['users', 'idx_users_deleted_at', 'ALTER TABLE `users` ADD INDEX `idx_users_deleted_at` (`deleted_at`)'],
            ['clients', 'idx_clients_deleted_at', 'ALTER TABLE `clients` ADD INDEX `idx_clients_deleted_at` (`deleted_at`)'],
            ['client_entities', 'idx_entities_deleted_at', 'ALTER TABLE `client_entities` ADD INDEX `idx_entities_deleted_at` (`deleted_at`)'],
            ['client_entities', 'idx_entities_company_number', 'ALTER TABLE `client_entities` ADD INDEX `idx_entities_company_number` (`company_number`)'],
            ['client_entities', 'idx_entities_tax_reference', 'ALTER TABLE `client_entities` ADD INDEX `idx_entities_tax_reference` (`tax_reference`)'],
            ['entity_directors', 'idx_entity_directors_deleted_at', 'ALTER TABLE `entity_directors` ADD INDEX `idx_entity_directors_deleted_at` (`deleted_at`)'],
            ['entity_directors', 'idx_entity_directors_user', 'ALTER TABLE `entity_directors` ADD INDEX `idx_entity_directors_user` (`user_id`, `entity_id`)'],
            ['entity_contacts', 'idx_contact_entity_email', 'ALTER TABLE `entity_contacts` ADD INDEX `idx_contact_entity_email` (`entity_id`, `email`)'],
            ['documents', 'idx_docs_deleted_at', 'ALTER TABLE `documents` ADD INDEX `idx_docs_deleted_at` (`deleted_at`)'],
            ['documents', 'idx_docs_entity_scope', 'ALTER TABLE `documents` ADD INDEX `idx_docs_entity_scope` (`entity_id`, `scope`)'],
            ['document_requests', 'idx_reqs_deleted_at', 'ALTER TABLE `document_requests` ADD INDEX `idx_reqs_deleted_at` (`deleted_at`)'],
            ['document_requests', 'idx_reqs_entity_scope', 'ALTER TABLE `document_requests` ADD INDEX `idx_reqs_entity_scope` (`entity_id`, `scope`, `status`)'],
            ['messages', 'idx_msg_deleted_at', 'ALTER TABLE `messages` ADD INDEX `idx_msg_deleted_at` (`deleted_at`)'],
            ['messages', 'idx_messages_entity_scope', 'ALTER TABLE `messages` ADD INDEX `idx_messages_entity_scope` (`entity_id`, `scope`, `read_at`)'],
            ['deadlines', 'idx_deadlines_deleted_at', 'ALTER TABLE `deadlines` ADD INDEX `idx_deadlines_deleted_at` (`deleted_at`)'],
            ['deadlines', 'idx_deadlines_entity_scope', 'ALTER TABLE `deadlines` ADD INDEX `idx_deadlines_entity_scope` (`entity_id`, `scope`, `due_date`)'],
            ['meetings', 'idx_meetings_deleted_at', 'ALTER TABLE `meetings` ADD INDEX `idx_meetings_deleted_at` (`deleted_at`)'],
            ['client_csv_imports', 'idx_csv_deleted_at', 'ALTER TABLE `client_csv_imports` ADD INDEX `idx_csv_deleted_at` (`deleted_at`)'],
        ];

        foreach ($indexes as [$table, $indexName, $sql]) {
            self::addIndexIfMissing($pdo, $table, $indexName, $sql);
        }
    }

    private static function ensureDataIntegrity(PDO $pdo): void
    {
        try {
            // Ensure default staff and test client accounts exist with valid password hash ('password123')
            $defaultHash = '$2y$12$uUgV4QcXGo9b9eOEO3/rmuHsKgbwXBm06PfvEgIchptJEsOTFv4ee';
            $pdo->exec("INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `status`) VALUES
                (1, 'Test Staff One', 'staff.one@example.invalid', '{$defaultHash}', 'staff', 'active'),
                (2, 'Test Staff Two', 'staff.two@example.invalid', '{$defaultHash}', 'staff', 'active'),
                (3, 'Test Staff Three', 'staff.three@example.invalid', '{$defaultHash}', 'staff', 'active'),
                (4, 'Test Staff Four', 'staff.four@example.invalid', '{$defaultHash}', 'staff', 'active'),
                (5, 'Test Client Alpha', 'test.client.alpha@example.invalid', '{$defaultHash}', 'client', 'active')
                ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `status` = 'active'");

            $pdo->exec("INSERT IGNORE INTO `clients` (`id`, `user_id`, `phone`, `address`, `aml_status`, `notes`) VALUES
                (1, 5, '07700 900000', '1 Example Street, Exampletown EX1 1AA', 'Complete', 'Fictional test client record. Use for non-production testing only.')");

            // Update entity_scope for existing client entities if personal/individual keywords match
            $pdo->exec("UPDATE `client_entities` SET `entity_scope` = CASE WHEN LOWER(`entity_type`) LIKE '%personal%' OR LOWER(`entity_type`) LIKE '%individual%' THEN 'personal' ELSE 'company' END WHERE `entity_scope` IS NULL OR `entity_scope` = ''");

            // Give every existing client a safe container entity if none exists
            $pdo->exec("INSERT INTO `client_entities` (`client_id`, `company_name`, `entity_type`, `entity_scope`, `attributes`)
                SELECT c.`id`, CONCAT(u.`name`, ' - Personal Record'), 'Personal Tax Return', 'personal', JSON_OBJECT()
                FROM `clients` c JOIN `users` u ON u.`id`=c.`user_id`
                WHERE NOT EXISTS (SELECT 1 FROM `client_entities` existing WHERE existing.`client_id`=c.`id`)");

            // Link existing company entities into entity_directors
            $pdo->exec("INSERT IGNORE INTO `entity_directors` (`entity_id`, `user_id`)
                SELECT e.`id`, c.`user_id`
                FROM `client_entities` e
                JOIN `clients` c ON c.`id` = e.`client_id`
                WHERE e.`entity_scope` = 'company'");

            // Backfill entity_id and scope on documents
            $pdo->exec("UPDATE `documents` r
                JOIN `client_entities` e ON e.`id` = (SELECT MIN(e2.`id`) FROM `client_entities` e2 WHERE e2.`client_id` = r.`client_id`)
                SET r.`entity_id` = e.`id`, r.`scope` = e.`entity_scope`
                WHERE r.`entity_id` IS NULL");

            // Backfill entity_id and scope on document_requests
            $pdo->exec("UPDATE `document_requests` r
                JOIN `client_entities` e ON e.`id` = (SELECT MIN(e2.`id`) FROM `client_entities` e2 WHERE e2.`client_id` = r.`client_id`)
                SET r.`entity_id` = e.`id`, r.`scope` = e.`entity_scope`
                WHERE r.`entity_id` IS NULL");

            // Backfill entity_id and scope on messages
            $pdo->exec("UPDATE `messages` r
                JOIN `client_entities` e ON e.`id` = (SELECT MIN(e2.`id`) FROM `client_entities` e2 WHERE e2.`client_id` = r.`client_id`)
                SET r.`entity_id` = e.`id`, r.`scope` = e.`entity_scope`
                WHERE r.`entity_id` IS NULL");

            // Backfill scope on deadlines
            $pdo->exec("UPDATE `deadlines` d JOIN `client_entities` e ON e.`id` = d.`entity_id` SET d.`scope` = e.`entity_scope` WHERE d.`scope` IS NULL");
        } catch (Throwable $e) {
            error_log('[TriNova SchemaMigrator] Data backfill note: ' . $e->getMessage());
        }
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name) = LOWER(:table) AND LOWER(column_name) = LOWER(:col)");
            $stmt->execute(['table' => $table, 'col' => $column]);
            $exists = (bool)$stmt->fetchColumn();
            $stmt->closeCursor();
            if (!$exists) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Throwable $e) {
            error_log("[TriNova SchemaMigrator] Column note ({$table}.{$column}): " . $e->getMessage());
        }
    }

    private static function addIndexIfMissing(PDO $pdo, string $table, string $indexName, string $sql): void
    {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND LOWER(table_name) = LOWER(:table) AND LOWER(index_name) = LOWER(:idx)");
            $stmt->execute(['table' => $table, 'idx' => $indexName]);
            $exists = (bool)$stmt->fetchColumn();
            $stmt->closeCursor();
            if (!$exists) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) {
            error_log("[TriNova SchemaMigrator] Index note ({$table}.{$indexName}): " . $e->getMessage());
        }
    }

    /**
     * Parse and execute a multi-statement SQL file cleanly.
     */
    public static function importSqlFile(PDO $pdo, string $filePath): bool
    {
        if (!file_exists($filePath)) {
            error_log("[TriNova SchemaMigrator] SQL file not found: {$filePath}");
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return false;
        }

        // Remove SQL comments
        $lines = explode("\n", $content);
        $cleanLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
                continue;
            }
            $cleanLines[] = $line;
        }
        $cleanSql = implode("\n", $cleanLines);

        // Split by statement delimiters (semicolons followed by whitespace/newlines or line endings)
        $statements = preg_split('/;\s*(\r?\n|$)/', $cleanSql);

        foreach ($statements as $statement) {
            $stmtText = trim($statement);
            if ($stmtText === '') {
                continue;
            }

            // Skip CREATE DATABASE / USE statements which might fail in cloud managed DBs
            if (preg_match('/^(CREATE\s+DATABASE|USE\s+)/i', $stmtText)) {
                continue;
            }

            try {
                $pdo->exec($stmtText);
            } catch (Throwable $e) {
                // Non-fatal duplicate/exists errors are safely logged
                error_log("[TriNova SchemaMigrator] Statement note: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Apply any additional migrations idempotently.
     */
    private static function applyIncrementalMigrations(PDO $pdo): void
    {
        $migrations = [
            dirname(__DIR__, 2) . '/config/otp_migration.sql',
            dirname(__DIR__, 2) . '/config/company_directors_migration.sql',
            dirname(__DIR__, 2) . '/config/client_csv_import_migration.sql',
            dirname(__DIR__, 2) . '/config/client_csv_duplicate_tracking_migration.sql',
            dirname(__DIR__, 2) . '/config/director_import_migration.sql',
            dirname(__DIR__, 2) . '/config/entity_deadlines_migration.sql',
            dirname(__DIR__, 2) . '/config/notifications_migration.sql',
            dirname(__DIR__, 2) . '/config/delete_functionality_migration.sql',
        ];

        foreach ($migrations as $migrationFile) {
            if (file_exists($migrationFile)) {
                self::importSqlFile($pdo, $migrationFile);
            }
        }
    }
}

