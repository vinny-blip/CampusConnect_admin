<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    /** Connects to MySQL, verifies required tables, and applies setup migrations. */
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require dirname(__DIR__) . '/config/database.php';
        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('PDO MySQL is not enabled for this PHP installation.');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['db_host'],
                $config['db_port'],
                $config['db_name']
            );
            $mysql = new PDO($dsn, $config['db_user'], $config['db_password'], $options);
            $roleColumn = $mysql->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'role'"
            )->fetchColumn();
            if ((int)$roleColumn === 0) {
                $mysql->exec("ALTER TABLE admin_users ADD COLUMN role ENUM('admin', 'moderator') NOT NULL DEFAULT 'moderator'");
            }
            $profileImageColumn = $mysql->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'profile_image'"
            )->fetchColumn();
            if ((int)$profileImageColumn === 0) {
                $mysql->exec('ALTER TABLE admin_users ADD COLUMN profile_image VARCHAR(255) NULL AFTER role');
            }
            $mysql->query('SELECT id, name, email, password_hash, role, profile_image FROM admin_users LIMIT 0');
            $mysql->query('SELECT user_id, state_key, state_data FROM admin_app_state LIMIT 0');
            // Store mobile login times and a hash of each session token for secure lookups.
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS admin_mobile_login_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    mobile_user_id INT NOT NULL,
                    full_name VARCHAR(100) NOT NULL,
                    username VARCHAR(50) NOT NULL,
                    institutional_email VARCHAR(100) NOT NULL,
                    session_token_hash CHAR(64) NOT NULL,
                    login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    logout_at DATETIME NULL,
                    UNIQUE KEY admin_mobile_login_token (session_token_hash),
                    INDEX admin_mobile_login_active (logout_at, last_activity_at),
                    INDEX admin_mobile_login_user (mobile_user_id, login_at)
                ) ENGINE=InnoDB'
            );
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS admin_system_migrations (
                    migration_key VARCHAR(100) NOT NULL PRIMARY KEY,
                    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB'
            );
            $migration = $mysql->prepare(
                'SELECT 1 FROM admin_system_migrations WHERE migration_key = ? LIMIT 1'
            );
            $migration->execute(['clear_legacy_state']);
            if ($migration->fetchColumn() === false) {
                $mysql->beginTransaction();
                try {
                    $mysql->exec('DELETE FROM admin_app_state');
                    $record = $mysql->prepare(
                        'INSERT INTO admin_system_migrations (migration_key) VALUES (?)'
                    );
                    $record->execute(['clear_legacy_state']);
                    $mysql->commit();
                } catch (Throwable $exception) {
                    if ($mysql->inTransaction()) {
                        $mysql->rollBack();
                    }
                    throw $exception;
                }
            }
            $migration->execute(['remove_communities_state']);
            if ($migration->fetchColumn() === false) {
                $mysql->prepare(
                    'DELETE FROM admin_app_state WHERE state_key = ?'
                )->execute(['communities']);
                $mysql->prepare(
                    'INSERT INTO admin_system_migrations (migration_key) VALUES (?)'
                )->execute(['remove_communities_state']);
            }
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS admin_user_restrictions (
                    user_id BIGINT UNSIGNED NOT NULL,
                    restricted_by BIGINT UNSIGNED NOT NULL,
                    reason VARCHAR(500) NOT NULL,
                    created_at BIGINT NOT NULL,
                    PRIMARY KEY (user_id),
                    CONSTRAINT admin_user_restrictions_user_fk FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE,
                    CONSTRAINT admin_user_restrictions_admin_fk FOREIGN KEY (restricted_by) REFERENCES admin_users (id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS admin_ai_moderation_settings (
                    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                    enabled TINYINT(1) NOT NULL DEFAULT 0
                ) ENGINE=InnoDB'
            );
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS admin_mobile_post_moderation (
                    mobile_post_id INT NOT NULL PRIMARY KEY,
                    status ENUM("pending","approved","removed") NOT NULL DEFAULT "pending",
                    report_count INT UNSIGNED NOT NULL DEFAULT 0,
                    report_reason VARCHAR(500) NULL,
                    ai_flagged TINYINT(1) NOT NULL DEFAULT 0,
                    ai_categories VARCHAR(1000) NULL,
                    moderated_by BIGINT UNSIGNED NULL,
                    moderated_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX admin_mobile_post_status (status),
                    INDEX admin_mobile_post_reports (report_count)
                ) ENGINE=InnoDB'
            );
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS admin_removed_mobile_posts (
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
                ) ENGINE=InnoDB'
            );
            $postDataColumn = $mysql->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_removed_mobile_posts' AND COLUMN_NAME = 'post_data'"
            )->fetchColumn();
            if ((int)$postDataColumn === 0) {
                $mysql->exec('ALTER TABLE admin_removed_mobile_posts ADD COLUMN post_data LONGTEXT NULL');
            }
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS admin_removed_mobile_comments (
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
                ) ENGINE=InnoDB'
            );
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS admin_mobile_comment_moderation (
                    mobile_comment_id INT NOT NULL PRIMARY KEY,
                    status ENUM("approved","hidden","removed") NOT NULL DEFAULT "approved",
                    report_count INT UNSIGNED NOT NULL DEFAULT 0,
                    report_reason VARCHAR(500) NULL,
                    ai_flagged TINYINT(1) NOT NULL DEFAULT 0,
                    ai_categories VARCHAR(1000) NULL,
                    moderated_by BIGINT UNSIGNED NULL,
                    moderated_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX admin_mobile_comment_status (status),
                    INDEX admin_mobile_comment_reports (report_count)
                ) ENGINE=InnoDB'
            );
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS mobile_comment_reports (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    comment_id INT NOT NULL,
                    reporter_user_id INT NOT NULL,
                    reason VARCHAR(255) NULL,
                    additional_comments TEXT NULL,
                    status VARCHAR(50) NOT NULL DEFAULT "Pending",
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX mobile_comment_reports_status (status),
                    INDEX mobile_comment_reports_comment (comment_id),
                    INDEX mobile_comment_reports_reporter (reporter_user_id)
                ) ENGINE=InnoDB'
            );
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS admin_mobile_user_moderation (
                    mobile_user_id INT NOT NULL PRIMARY KEY,
                    status ENUM("active","suspended","banned") NOT NULL DEFAULT "active",
                    suspended_until DATETIME NULL,
                    suspension_timezone_offset SMALLINT NULL,
                    reason VARCHAR(500) NULL,
                    moderated_by BIGINT UNSIGNED NULL,
                    moderated_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX admin_mobile_user_status (status)
                ) ENGINE=InnoDB'
            );
            $userStatusType = (string)$mysql->query(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_mobile_user_moderation' AND COLUMN_NAME = 'status'"
            )->fetchColumn();
            if ($userStatusType !== '' && !str_contains($userStatusType, 'banned')) {
                $mysql->exec("ALTER TABLE admin_mobile_user_moderation MODIFY status ENUM('active','suspended','banned') NOT NULL DEFAULT 'active'");
            }
            $suspensionExpiryType = (string)$mysql->query(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_mobile_user_moderation' AND COLUMN_NAME = 'suspended_until'"
            )->fetchColumn();
            if ($suspensionExpiryType === '') {
                $mysql->exec('ALTER TABLE admin_mobile_user_moderation ADD COLUMN suspended_until DATETIME NULL AFTER status');
            } elseif ($suspensionExpiryType === 'date') {
                $mysql->exec('ALTER TABLE admin_mobile_user_moderation MODIFY suspended_until DATETIME NULL');
            }
            $suspensionTimezoneColumn = $mysql->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_mobile_user_moderation' AND COLUMN_NAME = 'suspension_timezone_offset'"
            )->fetchColumn();
            if ((int)$suspensionTimezoneColumn === 0) {
                $mysql->exec('ALTER TABLE admin_mobile_user_moderation ADD COLUMN suspension_timezone_offset SMALLINT NULL AFTER suspended_until');
            }
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS mobile_notifications (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    type VARCHAR(50) NOT NULL DEFAULT "moderation",
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    post_id INT NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX mobile_notifications_user (user_id, is_read, created_at)
                ) ENGINE=InnoDB'
            );
            self::$connection = $mysql;
            return $mysql;
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'MySQL connection failed. Check the Hostinger hostname, database name, username, password, and schema.',
                0,
                $exception
            );
        }
    }
}
