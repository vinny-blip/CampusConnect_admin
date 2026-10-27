CREATE DATABASE IF NOT EXISTS campusconnect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE campusconnect;

-- Stores dashboard administrator and moderator accounts.
CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'moderator') NOT NULL DEFAULT 'moderator',
  profile_image VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY admin_users_email_unique (email)
) ENGINE=InnoDB;

-- Records mobile-app login, activity, and logout times for the dashboard Logs page.
CREATE TABLE IF NOT EXISTS admin_mobile_login_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mobile_user_id INT NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL,
  institutional_email VARCHAR(100) NOT NULL,
  session_token_hash CHAR(64) NOT NULL,
  login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  logout_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY admin_mobile_login_token (session_token_hash),
  KEY admin_mobile_login_active (logout_at, last_activity_at),
  KEY admin_mobile_login_user (mobile_user_id, login_at)
) ENGINE=InnoDB;

-- Stores per-account dashboard data as JSON by state category.
CREATE TABLE IF NOT EXISTS admin_app_state (
  user_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(40) NOT NULL,
  state_data LONGTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, state_key),
  CONSTRAINT admin_app_state_user_fk FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Records which one-time database migrations have already run.
CREATE TABLE IF NOT EXISTS admin_system_migrations (
  migration_key VARCHAR(100) NOT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (migration_key)
) ENGINE=InnoDB;

-- Tracks moderators restricted by an administrator and the reason.
CREATE TABLE IF NOT EXISTS admin_user_restrictions (
  user_id BIGINT UNSIGNED NOT NULL,
  restricted_by BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  created_at BIGINT NOT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT admin_user_restrictions_user_fk FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE,
  CONSTRAINT admin_user_restrictions_admin_fk FOREIGN KEY (restricted_by) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Stores whether AI moderation is enabled in the dashboard.
CREATE TABLE IF NOT EXISTS admin_ai_moderation_settings (
  id TINYINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- Admin-owned state for the shared mobile application's records. The IDs are
-- intentionally not foreign keys so this schema can be imported before the
-- mobile schema without breaking either application.
CREATE TABLE IF NOT EXISTS admin_mobile_post_moderation (
  mobile_post_id INT NOT NULL PRIMARY KEY,
  status ENUM('pending', 'approved', 'removed') NOT NULL DEFAULT 'pending',
  report_count INT UNSIGNED NOT NULL DEFAULT 0,
  report_reason VARCHAR(500) NULL,
  ai_flagged TINYINT(1) NOT NULL DEFAULT 0,
  ai_categories VARCHAR(1000) NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX admin_mobile_post_status (status),
  INDEX admin_mobile_post_reports (report_count)
) ENGINE=InnoDB;

-- Snapshot of mobile posts removed from the dashboard, because the post
-- itself is deleted from the shared mobile_posts table.
CREATE TABLE IF NOT EXISTS admin_removed_mobile_posts (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  mobile_post_id INT NOT NULL,
  mobile_user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NULL,
  category VARCHAR(255) NULL,
  author_name VARCHAR(255) NULL,
  author_username VARCHAR(255) NULL,
  ai_flagged TINYINT(1) NOT NULL DEFAULT 0,
  ai_categories VARCHAR(1000) NULL,
  reason VARCHAR(500) NULL,
  removed_by BIGINT UNSIGNED NULL,
  removed_by_name VARCHAR(120) NULL,
  post_created_at TIMESTAMP NULL,
  removed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  post_data LONGTEXT NULL,
  INDEX admin_removed_post_removed_at (removed_at)
) ENGINE=InnoDB;

-- Keeps a snapshot of each mobile comment removed by dashboard moderation.
CREATE TABLE IF NOT EXISTS admin_removed_mobile_comments (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  mobile_comment_id INT NOT NULL,
  mobile_post_id INT NOT NULL,
  mobile_user_id INT NOT NULL,
  content TEXT NULL,
  post_title VARCHAR(255) NULL,
  author_name VARCHAR(255) NULL,
  author_username VARCHAR(255) NULL,
  ai_flagged TINYINT(1) NOT NULL DEFAULT 0,
  ai_categories VARCHAR(1000) NULL,
  reason VARCHAR(500) NULL,
  removed_by BIGINT UNSIGNED NULL,
  removed_by_name VARCHAR(120) NULL,
  comment_created_at TIMESTAMP NULL,
  removed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  comment_data LONGTEXT NULL,
  INDEX admin_removed_comment_removed_at (removed_at)
) ENGINE=InnoDB;

-- Stores dashboard-owned status and moderation details for mobile comments.
CREATE TABLE IF NOT EXISTS admin_mobile_comment_moderation (
  mobile_comment_id INT NOT NULL PRIMARY KEY,
  status ENUM('approved', 'hidden', 'removed') NOT NULL DEFAULT 'approved',
  report_count INT UNSIGNED NOT NULL DEFAULT 0,
  report_reason VARCHAR(500) NULL,
  ai_flagged TINYINT(1) NOT NULL DEFAULT 0,
  ai_categories VARCHAR(1000) NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX admin_mobile_comment_status (status),
  INDEX admin_mobile_comment_reports (report_count)
) ENGINE=InnoDB;

-- Stores reports submitted by mobile users for comments.
CREATE TABLE IF NOT EXISTS mobile_comment_reports (
  id INT NOT NULL AUTO_INCREMENT,
  comment_id INT NOT NULL,
  reporter_user_id INT NOT NULL,
  reason VARCHAR(255) NULL,
  additional_comments TEXT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'Pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY mobile_comment_reports_status (status),
  KEY mobile_comment_reports_comment (comment_id),
  KEY mobile_comment_reports_reporter (reporter_user_id)
) ENGINE=InnoDB;

-- Stores suspension and ban status for mobile-app user accounts.
CREATE TABLE IF NOT EXISTS admin_mobile_user_moderation (
  mobile_user_id INT NOT NULL PRIMARY KEY,
  status ENUM('active', 'suspended', 'banned') NOT NULL DEFAULT 'active',
  suspended_until DATETIME NULL,
  suspension_timezone_offset SMALLINT NULL,
  reason VARCHAR(500) NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX admin_mobile_user_status (status)
) ENGINE=InnoDB;

-- Sends moderation updates from the dashboard to mobile-app users.
CREATE TABLE IF NOT EXISTS mobile_notifications (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL DEFAULT 'moderation',
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  post_id INT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX mobile_notifications_user (user_id, is_read, created_at)
) ENGINE=InnoDB;
