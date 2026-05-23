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
