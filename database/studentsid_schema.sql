CREATE DATABASE IF NOT EXISTS `studentsid`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `studentsid`;

CREATE TABLE IF NOT EXISTS `students` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` VARCHAR(80) NOT NULL,
    `username` VARCHAR(80) NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `gender` VARCHAR(20) NULL,
    `email` VARCHAR(190) NOT NULL,
    `phone` VARCHAR(50) NULL,
    `program` VARCHAR(150) NULL,
    `password` VARCHAR(255) NOT NULL,
    `security_question` VARCHAR(20) NULL,
    `security_answer` VARCHAR(255) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `registration_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_students_student_id` (`student_id`),
    UNIQUE KEY `uq_students_username` (`username`),
    UNIQUE KEY `uq_students_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_registry` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `registration_number` VARCHAR(80) NOT NULL,
    `card_number` VARCHAR(50) NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `course` VARCHAR(150) NULL,
    `programme` VARCHAR(150) NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_student_registry_registration` (`registration_number`),
    UNIQUE KEY `uq_student_registry_card_number` (`card_number`),
    KEY `idx_student_registry_name` (`full_name`),
    KEY `idx_student_registry_course` (`course`),
    KEY `idx_student_registry_programme` (`programme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `staff_accounts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(80) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `id_applications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `application_type` ENUM('new','renewal','lost','damaged') NOT NULL,
    `reason` VARCHAR(1000) NULL,
    `surname` VARCHAR(100) NULL,
    `given_name` VARCHAR(150) NULL,
    `nationality` VARCHAR(80) NULL,
    `sex` VARCHAR(20) NULL,
    `card_reg_no` VARCHAR(80) NULL,
    `date_admitted` DATE NULL,
    `faculty_id` INT UNSIGNED NULL,
    `course_id` INT UNSIGNED NULL,
    `graduation_year` SMALLINT UNSIGNED NULL,
    `source_photo_path` VARCHAR(255) NULL,
    `photo_path` VARCHAR(255) NOT NULL,
    `signature_path` VARCHAR(255) NOT NULL,
    `application_status` ENUM('submitted','under_review','approved','rejected','ready_to_print','printed','issued') NOT NULL DEFAULT 'submitted',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_applications_student` (`student_id`, `created_at`),
    KEY `idx_applications_status` (`application_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_faculties` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_student_faculties_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_courses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `faculty_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_student_courses_faculty_name` (`faculty_id`, `name`),
    KEY `idx_student_courses_status` (`faculty_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_announcements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(180) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_announcements_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient_role` ENUM('admin','staff') NOT NULL DEFAULT 'admin',
    `title` VARCHAR(180) NOT NULL,
    `message` VARCHAR(1000) NOT NULL,
    `notification_type` VARCHAR(40) NOT NULL DEFAULT 'system',
    `reference_type` VARCHAR(40) NULL,
    `reference_id` BIGINT UNSIGNED NULL,
    `created_by` INT UNSIGNED NULL,
    `read_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_notifications_inbox` (`recipient_role`, `read_at`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `reference` VARCHAR(120) NULL,
    `amount` DECIMAL(12,2) NULL,
    `receipt_path` VARCHAR(255) NULL,
    `payment_status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    `verified_by` INT UNSIGNED NULL,
    `verified_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `application_id` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `id_cards` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `card_number` VARCHAR(50) NULL,
    `card_status` ENUM('ready_to_print','printed','issued') NOT NULL DEFAULT 'ready_to_print',
    `verification_token` CHAR(48) NULL,
    `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `printed_at` DATETIME NULL,
    `issued_at` DATETIME NULL,
    `expires_at` DATE NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `application_id` (`application_id`),
    UNIQUE KEY `card_number` (`card_number`),
    UNIQUE KEY `verification_token` (`verification_token`),
    KEY `idx_cards_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `actor_id` INT UNSIGNED NOT NULL,
    `actor_role` VARCHAR(20) NOT NULL,
    `action` VARCHAR(80) NOT NULL,
    `details` VARCHAR(1000) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_application` (`application_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_service_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `service_key` VARCHAR(40) NOT NULL,
    `subject` VARCHAR(180) NOT NULL,
    `details` TEXT NOT NULL,
    `request_status` ENUM('submitted','in_review','completed','rejected') NOT NULL DEFAULT 'submitted',
    `staff_response` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_service_requests_student` (`student_id`, `created_at`),
    KEY `idx_service_requests_status` (`request_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_audit_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `user_role` VARCHAR(20) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` VARCHAR(1000) NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_system_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `activity` VARCHAR(100) NOT NULL,
    `details` VARCHAR(1000) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_user` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` VARCHAR(80) NOT NULL,
    `login_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_login_logs_student` (`student_id`, `login_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(128) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_remember_tokens_user` (`user_id`),
    UNIQUE KEY `uq_remember_tokens_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
