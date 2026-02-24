-- =============================================================
-- Eliskapp Database Schema
-- MySQL 8.0+ / MariaDB 10.6+
-- Character set: utf8mb4 / utf8mb4_unicode_ci
-- =============================================================

CREATE DATABASE IF NOT EXISTS `eliskapp`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `eliskapp`;

-- -------------------------------------------------------------
-- TABLE: users
-- Admin accounts. uid is used in /go/[uid] user route.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `uid`           CHAR(16)        NOT NULL,
    `username`      VARCHAR(64)     NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `display_name`  VARCHAR(128)    NOT NULL DEFAULT '',
    `last_login`    DATETIME        NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`uid`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- TABLE: block_groups
-- Tree structure. parent_id=NULL = root level.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `block_groups` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_uid`      CHAR(16)        NOT NULL,
    `parent_id`     INT UNSIGNED    NULL DEFAULT NULL,
    `name`          VARCHAR(128)    NOT NULL,
    `bg_color`      CHAR(7)         NOT NULL DEFAULT '#4A90D9',
    `sort_order`    INT             NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_block_groups_user`   (`user_uid`, `sort_order`),
    KEY `idx_block_groups_parent` (`parent_id`, `sort_order`),
    CONSTRAINT `fk_block_groups_user`
        FOREIGN KEY (`user_uid`)  REFERENCES `users`(`uid`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_block_groups_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `block_groups`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- TABLE: blocks
-- Individual picture/word blocks. group_id=NULL = root level.
-- block_type drives consecutive-type rule.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blocks` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_uid`      CHAR(16)        NOT NULL,
    `group_id`      INT UNSIGNED    NULL DEFAULT NULL,
    `text`          VARCHAR(128)    NOT NULL DEFAULT '',
    `image_path`    VARCHAR(512)    NULL DEFAULT NULL,
    `block_type`    ENUM('noun','verb','adverb','adjective','other')
                                    NOT NULL DEFAULT 'other',
    `audio_path`    VARCHAR(512)    NULL DEFAULT NULL,
    `arasaac_id`    INT UNSIGNED    NULL DEFAULT NULL,
    `sort_order`    INT             NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_blocks_user`  (`user_uid`, `sort_order`),
    KEY `idx_blocks_group` (`group_id`, `sort_order`),
    CONSTRAINT `fk_blocks_user`
        FOREIGN KEY (`user_uid`)  REFERENCES `users`(`uid`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_blocks_group`
        FOREIGN KEY (`group_id`) REFERENCES `block_groups`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- TABLE: settings
-- Key-value store per user.
-- Known keys: max_sentence_blocks (default '7')
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `user_uid`      CHAR(16)        NOT NULL,
    `setting_key`   VARCHAR(64)     NOT NULL,
    `setting_value` TEXT            NOT NULL,
    PRIMARY KEY (`user_uid`, `setting_key`),
    CONSTRAINT `fk_settings_user`
        FOREIGN KEY (`user_uid`) REFERENCES `users`(`uid`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
