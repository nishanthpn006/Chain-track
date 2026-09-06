-- =============================================================================
-- ChainTrack -- STEP 32: custody_history table
-- Run this AFTER importing chaintrack_schema.sql (the original schema).
-- =============================================================================
USE `chaintrack`;

-- -----------------------------------------------------------------------------
-- TABLE: custody_history
--
-- Purpose:
--   A permanent, append-only, chronological audit table of every custody event.
--   This table is the single source of truth for the chain-of-custody timeline.
--
-- Differences from custody_transfers:
--   custody_transfers  – Manages the two-party workflow (initiate → confirm/reject).
--   custody_history    – Records the permanent, immutable fact once custody changes.
--                        Initial assignment is recorded here with action_type = 'initial_assignment'.
--                        Confirmed transfers are also written here at confirmation time.
--
-- Relationships:
--   items             ──< custody_history   (1 item : many history events)
--   users (from)      ──< custody_history   (NULL for initial assignment — no prior custodian)
--   users (to)        ──< custody_history   (the new custodian receiving the item)
--   users (recorded)  ──< custody_history   (the officer who performed the action)
--   locations (from)  ──< custody_history   (NULL for initial assignment — no prior location)
--   locations (to)    ──< custody_history   (where the item is going / currently placed)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `custody_history` (
  `custody_history_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Which item this event belongs to
  `item_id`            INT UNSIGNED NOT NULL,

  -- Who previously held the item (NULL only for initial_assignment — no prior custodian exists)
  `from_user_id`       INT UNSIGNED DEFAULT NULL,

  -- Who is receiving / now holds the item
  `to_user_id`         INT UNSIGNED NOT NULL,

  -- Where the item was previously (NULL only for initial_assignment)
  `from_location_id`   INT UNSIGNED DEFAULT NULL,

  -- Where the item is going / currently stored
  `to_location_id`     INT UNSIGNED DEFAULT NULL,

  -- The nature of the custody event
  -- 'initial_assignment' – first event when an item enters the system
  -- 'transfer'           – subsequent custody changes between officers
  -- 'returned'           – item returned to a previous custodian
  -- 'archived'           – item removed from active circulation
  `action_type`        ENUM(
                          'initial_assignment',
                          'transfer',
                          'returned',
                          'archived'
                        ) NOT NULL,

  -- Human-readable justification for the event
  `remarks`            TEXT DEFAULT NULL,

  -- FK back to custody_transfers for traceability (NULL for initial_assignment)
  `related_transfer_id` INT UNSIGNED DEFAULT NULL,

  -- The officer who performed / recorded this event (the logged-in registrar)
  `recorded_by`        INT UNSIGNED NOT NULL,

  -- Immutable timestamp — auto-set at INSERT, never updated
  `recorded_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- ─── Keys ────────────────────────────────────────────────────────────────
  PRIMARY KEY (`custody_history_id`),

  -- For fast retrieval of all events for a given item (most common query)
  KEY `idx_ch_item_id`     (`item_id`),

  -- For lookups by custodian (e.g. "show all items ever held by Officer X")
  KEY `idx_ch_from_user`   (`from_user_id`),
  KEY `idx_ch_to_user`     (`to_user_id`),

  -- For location-based queries
  KEY `idx_ch_from_loc`    (`from_location_id`),
  KEY `idx_ch_to_loc`      (`to_location_id`),

  -- For auditor queries by action type or date
  KEY `idx_ch_action_type` (`action_type`),
  KEY `idx_ch_recorded_at` (`recorded_at`),

  -- For linking back to the transfer workflow record
  KEY `idx_ch_transfer`    (`related_transfer_id`),

  -- ─── Foreign Keys ────────────────────────────────────────────────────────
  CONSTRAINT `fk_ch_item`
    FOREIGN KEY (`item_id`)            REFERENCES `items`            (`id`),
  CONSTRAINT `fk_ch_from_user`
    FOREIGN KEY (`from_user_id`)       REFERENCES `users`            (`id`),
  CONSTRAINT `fk_ch_to_user`
    FOREIGN KEY (`to_user_id`)         REFERENCES `users`            (`id`),
  CONSTRAINT `fk_ch_from_location`
    FOREIGN KEY (`from_location_id`)   REFERENCES `locations`        (`id`),
  CONSTRAINT `fk_ch_to_location`
    FOREIGN KEY (`to_location_id`)     REFERENCES `locations`        (`id`),
  CONSTRAINT `fk_ch_transfer`
    FOREIGN KEY (`related_transfer_id`) REFERENCES `custody_transfers`(`id`),
  CONSTRAINT `fk_ch_recorded_by`
    FOREIGN KEY (`recorded_by`)        REFERENCES `users`            (`id`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Permanent, append-only chronological custody event log for every item.';

-- No UPDATE or DELETE privileges should ever be granted on this table in production.
