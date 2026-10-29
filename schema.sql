-- Showcase Tube & Asset CDN Database Schema
CREATE DATABASE IF NOT EXISTS `showcase_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `showcase_db`;

-- Users (Superadmin authentication)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'superadmin',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_login` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories (Video, Motion Comics, Images, Documents, Archives, etc.)
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50) DEFAULT 'bi-collection-play'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assets Registry (Videos, Images, Documents, Archives)
-- visibility: 0=private(admin only), 1=unlisted(link access), 2=public(everyone in feed)
CREATE TABLE IF NOT EXISTS `assets` (
  `id` VARCHAR(36) PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `copyright_info` TEXT NULL COMMENT 'Copyright notice / license info',
  `asset_type` ENUM('video', 'image', 'document', 'archive') NOT NULL,
  `category_id` INT NULL,
  `project` VARCHAR(100) NULL,
  `file_path` VARCHAR(500) NOT NULL UNIQUE,
  `file_name` VARCHAR(255) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `mime_type` VARCHAR(100) NOT NULL,
  `extension` VARCHAR(20) NOT NULL,
  `thumbnail_path` VARCHAR(500) NULL,
  `duration_seconds` INT UNSIGNED DEFAULT 0,
  `resolution` VARCHAR(50) NULL,
  `views_count` BIGINT UNSIGNED DEFAULT 0,
  `likes_count` INT UNSIGNED DEFAULT 0,
  `visibility` TINYINT(1) DEFAULT 1 COMMENT '0=private(admin only), 1=unlisted(link only, default), 2=public(everyone)',
  `is_public` TINYINT(1) DEFAULT 1 COMMENT 'Legacy: kept for compatibility, 1 if visibility>=1',
  `source` ENUM('web_upload', 'ftp_sync') DEFAULT 'ftp_sync',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_asset_type` (`asset_type`),
  INDEX `idx_category` (`category_id`),
  INDEX `idx_project` (`project`),
  INDEX `idx_created` (`created_at`),
  INDEX `idx_visibility` (`visibility`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asset Tags
CREATE TABLE IF NOT EXISTS `asset_tags` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `asset_id` VARCHAR(36) NOT NULL,
  `tag` VARCHAR(100) NOT NULL,
  INDEX `idx_tag_asset` (`asset_id`),
  INDEX `idx_tag_text` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Video Chapters (Timestamps for video playback)
CREATE TABLE IF NOT EXISTS `video_chapters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `asset_id` VARCHAR(36) NOT NULL,
  `timestamp_seconds` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  INDEX `idx_chapter_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CDN & Embed Access Logs
CREATE TABLE IF NOT EXISTS `cdn_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `asset_id` VARCHAR(36) NOT NULL,
  `referrer` VARCHAR(255) NULL,
  `ip_hash` VARCHAR(64) NULL,
  `accessed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cdn_asset` (`asset_id`),
  INDEX `idx_cdn_time` (`accessed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Video Playlists
CREATE TABLE IF NOT EXISTS `playlists` (
  `id` VARCHAR(36) PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `thumbnail_path` VARCHAR(500) NULL,
  `visibility` TINYINT(1) DEFAULT 1 COMMENT '0=private, 1=unlisted, 2=public',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_playlist_created` (`created_at`),
  INDEX `idx_playlist_visibility` (`visibility`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Playlist Items (Ordered collection of assets)
CREATE TABLE IF NOT EXISTS `playlist_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `playlist_id` VARCHAR(36) NOT NULL,
  `asset_id` VARCHAR(36) NOT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pi_playlist` (`playlist_id`),
  INDEX `idx_pi_asset` (`asset_id`),
  INDEX `idx_pi_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial default categories
INSERT INTO `categories` (`name`, `slug`, `icon`) VALUES
('Videos', 'videos', 'bi-camera-video'),
('Motion Comics', 'motion-comics', 'bi-film'),
('Images & Artwork', 'images', 'bi-image'),
('Documents & PDFs', 'documents', 'bi-file-earmark-text'),
('Archives & Zips', 'archives', 'bi-file-earmark-zip')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
