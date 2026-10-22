CREATE DATABASE IF NOT EXISTS campusconnect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE campusconnect;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
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
