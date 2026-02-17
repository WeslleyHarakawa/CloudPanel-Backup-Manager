-- Backup Admin Panel - Database Schema
-- This script creates the required tables for the backup admin system.

CREATE DATABASE IF NOT EXISTS `backupadmin-db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `backupadmin-db`;

CREATE TABLE IF NOT EXISTS `sites` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `domain` VARCHAR(255) NOT NULL,
  `site_user` VARCHAR(255) NOT NULL,
  `docroot` VARCHAR(512) NOT NULL,
  `db_host` VARCHAR(255) NOT NULL,
  `db_name` VARCHAR(255) NOT NULL,
  `db_user` VARCHAR(255) NOT NULL,
  `db_pass` VARCHAR(255) NOT NULL,
  `auto_backup_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `auto_backup_frequency` VARCHAR(16) NOT NULL DEFAULT 'daily',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `backups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `site_id` INT NOT NULL,
  `backup_path` VARCHAR(512) NOT NULL,
  `site_zip_path` VARCHAR(512) NOT NULL,
  `db_sql_path` VARCHAR(512) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `backups_ibfk_1`
    FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(64) NOT NULL UNIQUE,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

