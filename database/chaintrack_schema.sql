-- =============================================================================
-- ChainTrack -- Digital Chain-of-Custody Tracking System
-- DATABASE SCHEMA  |  Version 1.0
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP DATABASE IF EXISTS `chaintrack`;
CREATE DATABASE `chaintrack`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `chaintrack`;

-- =============================================================================
-- TABLE 1: roles
-- role_slug is used in PHP auth checks -- never compare raw id values.
-- =============================================================================
CREATE TABLE `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name`   VARCHAR(100) NOT NULL,
  `role_slug`   VARCHAR(50)  NOT NULL COMMENT 'administrator|investigator|custodian|analyst|auditor',
  `description` TEXT         DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`role_name`),
  UNIQUE KEY `uq_roles_slug` (`role_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 2: departments
-- Investigation departments or operational units.
-- =============================================================================
CREATE TABLE `departments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept_code`   VARCHAR(30)  DEFAULT NULL,
  `dept_name`   VARCHAR(150) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_code` (`dept_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 3: users
-- All system users. NEVER hard-delete; set is_active = 0 instead.
-- =============================================================================
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`   VARCHAR(50)  DEFAULT NULL COMMENT 'Badge / employee number',
  `full_name`     VARCHAR(150) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `username`      VARCHAR(80)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'PHP password_hash() BCRYPT',
  `role_id`       INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login_at` DATETIME     DEFAULT NULL,
  `created_by`    INT UNSIGNED DEFAULT NULL COMMENT 'NULL for the first system admin',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email`       (`email`),
  UNIQUE KEY `uq_users_username`    (`username`),
  UNIQUE KEY `uq_users_employee_id` (`employee_id`),
  KEY `fk_users_role_id`       (`role_id`),
  KEY `fk_users_department_id` (`department_id`),
  KEY `fk_users_created_by`    (`created_by`),
  CONSTRAINT `fk_users_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`       (`id`),
  CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_users_creator`    FOREIGN KEY (`created_by`)    REFERENCES `users`       (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 4: item_categories
-- =============================================================================
CREATE TABLE `item_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cat_name`    VARCHAR(100) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_name` (`cat_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 5: item_statuses
-- color_badge maps to Bootstrap badge variant (success, warning, danger, etc.)
-- status_slug is used in PHP logic -- never compare raw id.
-- =============================================================================
CREATE TABLE `item_statuses` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `status_name` VARCHAR(100) NOT NULL,
  `status_slug` VARCHAR(50)  NOT NULL,
  `color_badge` VARCHAR(20)  NOT NULL DEFAULT 'secondary' COMMENT 'Bootstrap badge variant',
  `description` TEXT         DEFAULT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_status_name` (`status_name`),
  UNIQUE KEY `uq_status_slug` (`status_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 6: locations
-- Physical or logical locations. parent_id supports hierarchical sub-locations.
-- =============================================================================
CREATE TABLE `locations` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `location_code` VARCHAR(50)  DEFAULT NULL,
  `location_name` VARCHAR(150) NOT NULL,
  `location_type` ENUM('storage','lab','office','field','external','other') NOT NULL DEFAULT 'storage',
  `parent_id`     INT UNSIGNED DEFAULT NULL COMMENT 'Self-referential for sub-locations',
  `department_id` INT UNSIGNED DEFAULT NULL,
  `description`   TEXT         DEFAULT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_location_code` (`location_code`),
  KEY `fk_locations_parent`     (`parent_id`),
  KEY `fk_locations_department` (`department_id`),
  CONSTRAINT `fk_locations_parent`     FOREIGN KEY (`parent_id`)     REFERENCES `locations`   (`id`),
  CONSTRAINT `fk_locations_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 7: investigations
-- Cases or investigation files. One investigation -> many items.
-- =============================================================================
CREATE TABLE `investigations` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inv_reference` VARCHAR(80)  NOT NULL COMMENT 'e.g. INV-2026-001',
  `title`         VARCHAR(255) NOT NULL,
  `description`   TEXT         DEFAULT NULL,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `lead_user_id`  INT UNSIGNED DEFAULT NULL,
  `start_date`    DATE         NOT NULL,
  `end_date`      DATE         DEFAULT NULL,
  `status`        ENUM('open','closed','archived') NOT NULL DEFAULT 'open',
  `created_by`    INT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_reference`   (`inv_reference`),
  KEY `fk_inv_department`         (`department_id`),
  KEY `fk_inv_lead_user`          (`lead_user_id`),
  KEY `fk_inv_created_by`         (`created_by`),
  KEY `idx_investigations_status` (`status`),
  CONSTRAINT `fk_inv_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_inv_lead_user`  FOREIGN KEY (`lead_user_id`)  REFERENCES `users`       (`id`),
  CONSTRAINT `fk_inv_created_by` FOREIGN KEY (`created_by`)    REFERENCES `users`       (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 8: items
-- The controlled items. Stores CURRENT STATE snapshot only.
-- Full history lives in custody_transfers + item_status_history.
-- =============================================================================
CREATE TABLE `items` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_reference`       VARCHAR(100) NOT NULL COMMENT 'e.g. EVD-2026-00001',
  `investigation_id`     INT UNSIGNED NOT NULL,
  `category_id`          INT UNSIGNED NOT NULL,
  `item_name`            VARCHAR(255) NOT NULL,
  `description`          TEXT         DEFAULT NULL,
  `physical_description` TEXT         DEFAULT NULL COMMENT 'Color, dimensions, serial numbers, markings',
  `acquisition_date`     DATE         DEFAULT NULL,
  `acquisition_location` VARCHAR(255) DEFAULT NULL COMMENT 'Free-text original collection point',
  `registered_by`        INT UNSIGNED NOT NULL,
  `current_custodian_id` INT UNSIGNED DEFAULT NULL,
  `current_location_id`  INT UNSIGNED DEFAULT NULL,
  `current_status_id`    INT UNSIGNED NOT NULL,
  `notes`                TEXT         DEFAULT NULL,
  `is_archived`          TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_reference`      (`item_reference`),
  KEY `fk_items_investigation`        (`investigation_id`),
  KEY `fk_items_category`             (`category_id`),
  KEY `fk_items_registered_by`        (`registered_by`),
  KEY `fk_items_current_custodian`    (`current_custodian_id`),
  KEY `fk_items_current_location`     (`current_location_id`),
  KEY `fk_items_current_status`       (`current_status_id`),
  KEY `idx_items_is_archived`         (`is_archived`),
  CONSTRAINT `fk_items_investigation`     FOREIGN KEY (`investigation_id`)    REFERENCES `investigations`  (`id`),
  CONSTRAINT `fk_items_category`          FOREIGN KEY (`category_id`)         REFERENCES `item_categories` (`id`),
  CONSTRAINT `fk_items_registered_by`     FOREIGN KEY (`registered_by`)       REFERENCES `users`          (`id`),
  CONSTRAINT `fk_items_current_custodian` FOREIGN KEY (`current_custodian_id`) REFERENCES `users`          (`id`),
  CONSTRAINT `fk_items_current_location`  FOREIGN KEY (`current_location_id`)  REFERENCES `locations`      (`id`),
  CONSTRAINT `fk_items_current_status`    FOREIGN KEY (`current_status_id`)    REFERENCES `item_statuses`  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 9: custody_transfers
-- CORE chain-of-custody table. APPEND-ONLY. Never delete rows.
-- Status lifecycle: initiated -> pending -> confirmed | rejected
-- from_user_id = NULL only for the very first registration event.
-- =============================================================================
CREATE TABLE `custody_transfers` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_reference` VARCHAR(80)  NOT NULL COMMENT 'e.g. TRF-2026-00001',
  `item_id`            INT UNSIGNED NOT NULL,
  `from_user_id`       INT UNSIGNED DEFAULT NULL COMMENT 'NULL for initial registration',
  `to_user_id`         INT UNSIGNED NOT NULL,
  `from_location_id`   INT UNSIGNED DEFAULT NULL,
  `to_location_id`     INT UNSIGNED DEFAULT NULL,
  `transfer_status`    ENUM('initiated','pending','confirmed','rejected') NOT NULL DEFAULT 'initiated',
  `reason`             TEXT         NOT NULL,
  `initiated_by`       INT UNSIGNED NOT NULL COMMENT 'May differ from from_user_id (admin-initiated)',
  `initiated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_by`       INT UNSIGNED DEFAULT NULL,
  `confirmed_at`       DATETIME     DEFAULT NULL,
  `rejection_reason`   TEXT         DEFAULT NULL,
  `notes`              TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transfer_reference` (`transfer_reference`),
  KEY `fk_ct_item`          (`item_id`),
  KEY `fk_ct_from_user`     (`from_user_id`),
  KEY `fk_ct_to_user`       (`to_user_id`),
  KEY `fk_ct_from_location` (`from_location_id`),
  KEY `fk_ct_to_location`   (`to_location_id`),
  KEY `fk_ct_initiated_by`  (`initiated_by`),
  KEY `fk_ct_confirmed_by`  (`confirmed_by`),
  KEY `idx_ct_status`       (`transfer_status`),
  KEY `idx_ct_initiated_at` (`initiated_at`),
  CONSTRAINT `fk_ct_item`          FOREIGN KEY (`item_id`)          REFERENCES `items`     (`id`),
  CONSTRAINT `fk_ct_from_user`     FOREIGN KEY (`from_user_id`)     REFERENCES `users`     (`id`),
  CONSTRAINT `fk_ct_to_user`       FOREIGN KEY (`to_user_id`)       REFERENCES `users`     (`id`),
  CONSTRAINT `fk_ct_from_location` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `fk_ct_to_location`   FOREIGN KEY (`to_location_id`)   REFERENCES `locations` (`id`),
  CONSTRAINT `fk_ct_initiated_by`  FOREIGN KEY (`initiated_by`)     REFERENCES `users`     (`id`),
  CONSTRAINT `fk_ct_confirmed_by`  FOREIGN KEY (`confirmed_by`)     REFERENCES `users`     (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 10: item_status_history
-- APPEND-ONLY. Immutable record of every status change for every item.
-- previous_status_id = NULL only for the very first (registration) assignment.
-- =============================================================================
CREATE TABLE `item_status_history` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id`             INT UNSIGNED NOT NULL,
  `previous_status_id`  INT UNSIGNED DEFAULT NULL COMMENT 'NULL for initial status on registration',
  `new_status_id`       INT UNSIGNED NOT NULL,
  `changed_by`          INT UNSIGNED NOT NULL,
  `related_transfer_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK to custody_transfers if triggered by a transfer',
  `reason`              TEXT         DEFAULT NULL,
  `changed_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ish_item`        (`item_id`),
  KEY `fk_ish_prev_status` (`previous_status_id`),
  KEY `fk_ish_new_status`  (`new_status_id`),
  KEY `fk_ish_changed_by`  (`changed_by`),
  KEY `fk_ish_transfer`    (`related_transfer_id`),
  KEY `idx_ish_changed_at` (`changed_at`),
  CONSTRAINT `fk_ish_item`        FOREIGN KEY (`item_id`)             REFERENCES `items`            (`id`),
  CONSTRAINT `fk_ish_prev_status` FOREIGN KEY (`previous_status_id`)  REFERENCES `item_statuses`    (`id`),
  CONSTRAINT `fk_ish_new_status`  FOREIGN KEY (`new_status_id`)       REFERENCES `item_statuses`    (`id`),
  CONSTRAINT `fk_ish_changed_by`  FOREIGN KEY (`changed_by`)          REFERENCES `users`            (`id`),
  CONSTRAINT `fk_ish_transfer`    FOREIGN KEY (`related_transfer_id`) REFERENCES `custody_transfers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 11: audit_logs
-- System-wide audit trail. IMMUTABLE -- no UPDATE or DELETE ever.
-- action format: entity.event  e.g. 'item.created', 'transfer.confirmed'
-- =============================================================================
CREATE TABLE `audit_logs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL COMMENT 'NULL for unauthenticated/system events',
  `action`      VARCHAR(100) NOT NULL COMMENT 'Dot-notation: entity.event',
  `entity_type` VARCHAR(80)  DEFAULT NULL,
  `entity_id`   INT UNSIGNED DEFAULT NULL,
  `description` TEXT         DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_al_user`       (`user_id`),
  KEY `idx_al_action`    (`action`),
  KEY `idx_al_entity`    (`entity_type`, `entity_id`),
  KEY `idx_al_created`   (`created_at`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 12: custody_history
-- Permanent, append-only chronological custody event log for every item.
-- Single source of truth for the chain-of-custody timeline.
-- =============================================================================
CREATE TABLE `custody_history` (
  `custody_history_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id`             INT UNSIGNED NOT NULL,
  `from_user_id`        INT UNSIGNED DEFAULT NULL COMMENT 'NULL only for initial_assignment',
  `to_user_id`          INT UNSIGNED NOT NULL,
  `from_location_id`    INT UNSIGNED DEFAULT NULL COMMENT 'NULL only for initial_assignment',
  `to_location_id`      INT UNSIGNED DEFAULT NULL,
  `action_type`         ENUM('initial_assignment','transfer','returned','archived') NOT NULL,
  `remarks`             TEXT DEFAULT NULL,
  `related_transfer_id` INT UNSIGNED DEFAULT NULL,
  `recorded_by`         INT UNSIGNED NOT NULL,
  `recorded_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`custody_history_id`),
  KEY `idx_ch_item_id`     (`item_id`),
  KEY `idx_ch_from_user`   (`from_user_id`),
  KEY `idx_ch_to_user`     (`to_user_id`),
  KEY `idx_ch_from_loc`    (`from_location_id`),
  KEY `idx_ch_to_loc`      (`to_location_id`),
  KEY `idx_ch_action_type` (`action_type`),
  KEY `idx_ch_recorded_at` (`recorded_at`),
  KEY `idx_ch_transfer`    (`related_transfer_id`),
  CONSTRAINT `fk_ch_item`          FOREIGN KEY (`item_id`)             REFERENCES `items`            (`id`),
  CONSTRAINT `fk_ch_from_user`      FOREIGN KEY (`from_user_id`)        REFERENCES `users`            (`id`),
  CONSTRAINT `fk_ch_to_user`        FOREIGN KEY (`to_user_id`)          REFERENCES `users`            (`id`),
  CONSTRAINT `fk_ch_from_location`  FOREIGN KEY (`from_location_id`)    REFERENCES `locations`        (`id`),
  CONSTRAINT `fk_ch_to_location`    FOREIGN KEY (`to_location_id`)      REFERENCES `locations`        (`id`),
  CONSTRAINT `fk_ch_transfer`       FOREIGN KEY (`related_transfer_id`) REFERENCES `custody_transfers`(`id`),
  CONSTRAINT `fk_ch_recorded_by`    FOREIGN KEY (`recorded_by`)         REFERENCES `users`            (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
-- END OF SCHEMA
