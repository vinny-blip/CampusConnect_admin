CREATE DATABASE IF NOT EXISTS campusconnect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE campusconnect;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'moderator') NOT NULL DEFAULT 'moderator',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY admin_users_email_unique (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_app_state (
  user_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(40) NOT NULL,
  state_data LONGTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, state_key),
  CONSTRAINT admin_app_state_user_fk FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_system_migrations (
  migration_key VARCHAR(100) NOT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (migration_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_user_restrictions (
  user_id BIGINT UNSIGNED NOT NULL,
  restricted_by BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  created_at BIGINT NOT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT admin_user_restrictions_user_fk FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE,
  CONSTRAINT admin_user_restrictions_admin_fk FOREIGN KEY (restricted_by) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS admin_mobile_user_moderation (
  mobile_user_id INT NOT NULL PRIMARY KEY,
  status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  reason VARCHAR(500) NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX admin_mobile_user_status (status)
) ENGINE=InnoDB;

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
