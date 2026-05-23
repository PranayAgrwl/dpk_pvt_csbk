-- =============================================================
-- dpk_pvt_csbk — schema + seed
-- -------------------------------------------------------------
-- How to import:
--   1) Open phpMyAdmin → Import → choose this file → Go
--      (creates database, table, and the seed admin user)
--   2) OR via CLI:
--      mysql -u root -p < database/db_dpk_pvt_csbk.sql
-- =============================================================

-- ---- DATABASE ----------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `db_dpk_pvt_csbk`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `db_dpk_pvt_csbk`;

-- ---- TABLES ------------------------------------------------------------------
-- users: application accounts (auth)
CREATE TABLE IF NOT EXISTS `users` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)    NOT NULL,
    `username`   VARCHAR(50)     NOT NULL,
    `password`   VARCHAR(255)    NOT NULL,         -- bcrypt hash
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- master: clients (covers both customers and suppliers).
-- Kept simple per the master bible: id, name, station, remark, timestamps.
CREATE TABLE IF NOT EXISTS `master` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255)    NOT NULL,
    `station`    VARCHAR(255)    NULL,
    `remark`     VARCHAR(255)    NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Non-unique index on name so we can lookup/list quickly later.
    -- The bible explicitly allows duplicate names, so no UNIQUE constraint.
    KEY `idx_master_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trx: cashbook transactions.
-- Per trx bible:
--   - one entry per transaction (no contra entry — this IS the cashbook)
--   - exactly one of `cr` or `dr` is populated (the other stays NULL)
--   - `master_id` references master.id (the column is described in the bible
--     as "master (id from master)" — we use `master_id` to avoid colliding
--     with the table name and to follow standard FK naming)
--   - `trx_id` is a user-facing ordering integer (last trx_id + 1 by default,
--     editable later to allow inserting between existing rows in the sequence)
--   - rows are sorted by `trx_id` everywhere
CREATE TABLE IF NOT EXISTS `trx` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `master_id`  BIGINT UNSIGNED NOT NULL,
    `trx_date`   DATE            NOT NULL,
    `trx_id`     BIGINT UNSIGNED NOT NULL,
    `cr`         DECIMAL(15,2)   NULL,
    `dr`         DECIMAL(15,2)   NULL,
    `remark`     VARCHAR(255)    NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Index FK and sort columns for fast list/balance queries.
    KEY `idx_trx_master_id` (`master_id`),
    KEY `idx_trx_trx_id`    (`trx_id`),
    KEY `idx_trx_date`      (`trx_date`),
    -- Belt-and-braces: enforce "exactly one of cr/dr is set" at the DB layer.
    -- (MySQL 8 honours CHECK; older 5.7 silently ignores it - PHP still validates.)
    CONSTRAINT `chk_trx_cr_xor_dr` CHECK (
        (`cr` IS NOT NULL AND `dr` IS NULL) OR
        (`cr` IS NULL     AND `dr` IS NOT NULL)
    ),
    CONSTRAINT `fk_trx_master` FOREIGN KEY (`master_id`)
        REFERENCES `master` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- SEED --------------------------------------------------------------------
-- Default admin user (per outline bible):
--   name     : p
--   username : admin
--   password : 321  (stored as a bcrypt hash with cost 12)
--
-- The hash below was generated with:
--   php -r "echo password_hash('321', PASSWORD_BCRYPT, ['cost' => 12]);"
-- A fresh hash is computed by the running PHP app; this seed hash also works.
INSERT INTO `users` (`name`, `username`, `password`, `created_at`, `updated_at`)
SELECT 'p', 'admin', '$2y$12$tXp/3ZoCVw8t5clfvTFy3OIOchp6fAER8yhAPllKMBKXjy3LzjQp2', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `users` WHERE `username` = 'admin'
);
