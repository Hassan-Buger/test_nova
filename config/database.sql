-- TriNova Accounting Client Portal Database Schema
-- CREATE DATABASE IF NOT EXISTS `trinova_portal`
--   DEFAULT CHARACTER SET utf8mb4
--   COLLATE utf8mb4_unicode_ci;
-- 
-- USE `trinova_portal`;

-- Core Users Table
CREATE TABLE IF NOT EXISTS `users` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `otp_challenges` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `user_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(150) NOT NULL, `purpose` VARCHAR(50) NOT NULL, `otp_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL, `used_at` DATETIME NULL, `attempt_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_otp_user_purpose` (`user_id`,`purpose`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clients Core Profile
CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `phone` VARCHAR(30) NULL,
  `address` TEXT NULL,
  `aml_status` ENUM('Complete', 'Action Required') NOT NULL DEFAULT 'Action Required',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  CONSTRAINT `fk_clients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_clients_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Multi-business linkage per client
CREATE TABLE IF NOT EXISTS `client_entities` (
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
  CONSTRAINT `fk_entities_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  INDEX `idx_entities_client` (`client_id`),
  INDEX `idx_entities_company_number` (`company_number`),
  INDEX `idx_entities_tax_reference` (`tax_reference`),
  INDEX `idx_entities_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entity_directors` (
  `entity_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_by_user_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`entity_id`, `user_id`),
  INDEX `idx_entity_directors_user` (`user_id`, `entity_id`),
  INDEX `idx_entity_directors_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_entity_directors_entity` FOREIGN KEY (`entity_id`) REFERENCES `client_entities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_entity_directors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_entity_directors_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entity_contacts` (
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
  UNIQUE KEY `uq_entity_contact_name` (`entity_id`,`name`),
  INDEX `idx_contact_entity_email` (`entity_id`,`email`),
  CONSTRAINT `fk_entity_contacts_entity` FOREIGN KEY (`entity_id`) REFERENCES `client_entities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_entity_contacts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents Management
CREATE TABLE IF NOT EXISTS `documents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT UNSIGNED NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `scope` ENUM('company','personal') NOT NULL DEFAULT 'company',
  `uploaded_by_user_id` INT UNSIGNED NOT NULL,
  `direction` ENUM('client_upload', 'from_trinova') NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `stored_path` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Ready',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  CONSTRAINT `fk_docs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_docs_entity` FOREIGN KEY (`entity_id`) REFERENCES `client_entities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_docs_user` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`),
  INDEX `idx_docs_client_dir` (`client_id`, `direction`),
  INDEX `idx_docs_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document Requests Workflow
CREATE TABLE IF NOT EXISTS `document_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT UNSIGNED NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `scope` ENUM('company','personal') NOT NULL DEFAULT 'company',
  `created_by_user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `due_date` DATE NOT NULL,
  `status` ENUM('Awaiting Client', 'Uploaded', 'Under Review', 'Completed') NOT NULL DEFAULT 'Awaiting Client',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  CONSTRAINT `fk_reqs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reqs_entity` FOREIGN KEY (`entity_id`) REFERENCES `client_entities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reqs_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  INDEX `idx_reqs_client_status` (`client_id`, `status`),
  INDEX `idx_reqs_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Threaded Messaging
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT UNSIGNED NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `scope` ENUM('company','personal') NOT NULL DEFAULT 'company',
  `sender_id` INT UNSIGNED NOT NULL,
  `thread_id` INT UNSIGNED NULL,
  `body` TEXT NOT NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  CONSTRAINT `fk_msg_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_entity` FOREIGN KEY (`entity_id`) REFERENCES `client_entities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  INDEX `idx_msg_client_read` (`client_id`, `read_at`),
  INDEX `idx_msg_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compliance Deadlines
CREATE TABLE IF NOT EXISTS `deadlines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT UNSIGNED NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `scope` ENUM('company','personal') NOT NULL DEFAULT 'company',
  `type` VARCHAR(100) NOT NULL,
  `due_date` DATE NOT NULL,
  `status` ENUM('Pending', 'Overdue', 'Completed') NOT NULL DEFAULT 'Pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  CONSTRAINT `fk_deadlines_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_deadlines_entity` FOREIGN KEY (`entity_id`) REFERENCES `client_entities` (`id`) ON DELETE CASCADE,
  INDEX `idx_deadlines_client_date` (`client_id`, `due_date`),
  INDEX `idx_deadlines_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meetings Integration Audit
CREATE TABLE IF NOT EXISTS `meetings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT UNSIGNED NOT NULL,
  `type` ENUM('existing_client_meeting', 'telephone_call') NOT NULL,
  `external_booking_reference` VARCHAR(100) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  CONSTRAINT `fk_meetings_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  INDEX `idx_meetings_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compliance & Security Audit Log
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `target_type` VARCHAR(50) NOT NULL,
  `target_id` INT UNSIGNED NULL,
  `import_metadata` JSON NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_audit_user_action` (`user_id`, `action_type`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Persistent client CSV import ledger and duplicate protection
CREATE TABLE IF NOT EXISTS `client_csv_imports` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `practice_key` VARCHAR(64) NOT NULL,
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
  INDEX `idx_csv_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_csv_import_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Notifications Queue
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `related_entity` VARCHAR(50) NULL,
  `title` VARCHAR(160) NULL,
  `message` TEXT NULL,
  `action_url` VARCHAR(255) NULL,
  `sent_at` DATETIME NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Digital Signature Workflows & Requests
CREATE TABLE IF NOT EXISTS `signature_requests` (
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
  INDEX `idx_sig_req_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_sig_req_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sig_req_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sig_req_entity` FOREIGN KEY (`entity_id`) REFERENCES `client_entities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sig_req_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sig_req_signed_doc` FOREIGN KEY (`signed_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signature_signers` (
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
  INDEX `idx_signers_status` (`status`),
  CONSTRAINT `fk_signers_req` FOREIGN KEY (`request_id`) REFERENCES `signature_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_signers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signature_fields` (
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
  INDEX `idx_sig_fields_page` (`page`),
  CONSTRAINT `fk_sig_fields_req` FOREIGN KEY (`request_id`) REFERENCES `signature_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sig_fields_signer` FOREIGN KEY (`signer_id`) REFERENCES `signature_signers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signature_audit_events` (
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
  INDEX `idx_sig_audit_created` (`created_at`),
  CONSTRAINT `fk_sig_audit_req` FOREIGN KEY (`request_id`) REFERENCES `signature_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sig_audit_signer` FOREIGN KEY (`signer_id`) REFERENCES `signature_signers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sig_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Seed Data: Password for all default accounts is "password123"
-- Hashed using password_hash('password123', PASSWORD_BCRYPT)
-- $2y$12$uUgV4QcXGo9b9eOEO3/rmuHsKgbwXBm06PfvEgIchptJEsOTFv4ee

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `status`) VALUES
(1, 'Test Staff One', 'staff.one@example.invalid', '$2y$12$uUgV4QcXGo9b9eOEO3/rmuHsKgbwXBm06PfvEgIchptJEsOTFv4ee', 'staff', 'active'),
(2, 'Test Staff Two', 'staff.two@example.invalid', '$2y$12$uUgV4QcXGo9b9eOEO3/rmuHsKgbwXBm06PfvEgIchptJEsOTFv4ee', 'staff', 'active'),
(3, 'Test Staff Three', 'staff.three@example.invalid', '$2y$12$uUgV4QcXGo9b9eOEO3/rmuHsKgbwXBm06PfvEgIchptJEsOTFv4ee', 'staff', 'active'),
(4, 'Test Staff Four', 'staff.four@example.invalid', '$2y$12$uUgV4QcXGo9b9eOEO3/rmuHsKgbwXBm06PfvEgIchptJEsOTFv4ee', 'staff', 'active'),
(5, 'Test Client Alpha', 'test.client.alpha@example.invalid', '$2y$12$uUgV4QcXGo9b9eOEO3/rmuHsKgbwXBm06PfvEgIchptJEsOTFv4ee', 'client', 'active');

INSERT INTO `clients` (`id`, `user_id`, `phone`, `address`, `aml_status`, `notes`) VALUES
(1, 5, '07700 900000', '1 Example Street, Exampletown EX1 1AA', 'Complete', 'Fictional test client record. Use for non-production testing only.');

INSERT INTO `client_entities` (`id`, `client_id`, `company_name`, `entity_type`, `entity_scope`, `company_number`, `tax_reference`, `attributes`) VALUES
(1, 1, 'Example Test Company Ltd', 'Limited Company', 'company', '00000000', 'TEST-REF-001', JSON_OBJECT('vat_number','GB000000000','accounting_year_end','2026-03-31')),
(2, 1, 'Example Test Services Ltd', 'Sole Trader', 'company', '00000001', 'TEST-REF-002', JSON_OBJECT()),
(3, 1, 'Personal Tax Test Record', 'Personal Tax Return', 'personal', NULL, 'TEST-REF-003', JSON_OBJECT('tax_year','2026/27'));

INSERT INTO `entity_directors` (`entity_id`,`user_id`) VALUES (1,5),(2,5);

INSERT INTO `deadlines` (`id`, `client_id`, `entity_id`, `scope`, `type`, `due_date`, `status`) VALUES
(1, 1, 1, 'company', 'Payroll', '2026-07-26', 'Pending'),
(2, 1, 1, 'company', 'Next VAT Return Due', '2026-08-07', 'Pending'),
(3, 1, 1, 'company', 'Confirmation Statement', '2026-09-03', 'Pending'),
(4, 1, 1, 'company', 'Corporation Tax Due', '2026-10-01', 'Pending'),
(5, 1, 3, 'personal', 'Tax Return Due', '2027-01-31', 'Pending');

INSERT INTO `document_requests` (`id`, `client_id`, `entity_id`, `scope`, `created_by_user_id`, `title`, `description`, `due_date`, `status`) VALUES
(1, 1, 1, 'company', 3, 'June bank statements', 'Upload PDF statements for June accounts', '2026-07-28', 'Awaiting Client'),
(2, 1, 1, 'company', 3, 'CIS certificates', 'Two subcontractor documents needed', '2026-07-30', 'Awaiting Client');

INSERT INTO `documents` (`id`, `client_id`, `entity_id`, `scope`, `uploaded_by_user_id`, `direction`, `filename`, `stored_path`, `description`, `status`) VALUES
(1, 1, 1, 'company', 3, 'from_trinova', 'Draft accounts 2025.pdf', 'draft_accounts_2025_hash.pdf', 'Uploaded by Test Staff Three', 'New'),
(2, 1, 1, 'company', 3, 'from_trinova', 'VAT return Q2.pdf', 'vat_return_q2_hash.pdf', 'Submitted copy', 'Ready');

INSERT INTO `messages` (`id`, `client_id`, `entity_id`, `scope`, `sender_id`, `thread_id`, `body`, `read_at`) VALUES
(1, 1, 1, 'company', 3, 1, 'Hello Test Client Alpha, could you send over the June payroll figures when convenient?', NOW()),
(2, 1, 1, 'company', 5, 1, 'Of course — I have just uploaded them to the portal.', NOW()),
(3, 1, 1, 'company', 3, 1, 'Perfect, received. Thank you, Test Client Alpha. I will process these today.', NULL);

-- --------------------------------------------------------
-- Digital Signature Infrastructure
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `signature_requests` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signature_signers` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signature_fields` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signature_audit_events` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
