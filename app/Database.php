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
            $suspendedColumn = $mysql->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'suspended'"
            )->fetchColumn();
            if ((int)$suspendedColumn === 0) {
                $mysql->exec('ALTER TABLE admin_users ADD COLUMN suspended TINYINT(1) NOT NULL DEFAULT 0');
            }
            $moderatorSuspensionExpiryColumn = $mysql->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'suspended_until'"
            )->fetchColumn();
            if ((int)$moderatorSuspensionExpiryColumn === 0) {
                $mysql->exec('ALTER TABLE admin_users ADD COLUMN suspended_until DATETIME NULL AFTER suspended');
            }
            $mysql->query('SELECT id, name, email, password_hash, role, profile_image FROM admin_users LIMIT 0');
            $mysql->query('SELECT user_id, state_key, state_data FROM admin_app_state LIMIT 0');
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS superadmin_moderator_login_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    moderator_id BIGINT UNSIGNED NULL,
                    moderator_name VARCHAR(120) NOT NULL,
                    moderator_email VARCHAR(254) NOT NULL,
                    login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_activity_at DATETIME NULL,
                    logout_at DATETIME NULL,
                    end_reason ENUM("logout", "suspended", "removed") NULL,
                    INDEX superadmin_moderator_log_user (moderator_id, logout_at),
                    INDEX superadmin_moderator_log_time (login_at),
                    CONSTRAINT superadmin_moderator_log_user_fk FOREIGN KEY (moderator_id) REFERENCES admin_users (id) ON DELETE SET NULL
                ) ENGINE=InnoDB'
            );
            $moderatorActivityColumn = $mysql->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'superadmin_moderator_login_logs' AND COLUMN_NAME = 'last_activity_at'"
            )->fetchColumn();
            if ((int)$moderatorActivityColumn === 0) {
                $mysql->exec('ALTER TABLE superadmin_moderator_login_logs ADD COLUMN last_activity_at DATETIME NULL AFTER login_at');
            }
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS superadmin_moderation_activity (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    actor_id BIGINT UNSIGNED NOT NULL,
                    actor_role VARCHAR(20) NOT NULL,
                    actor_name VARCHAR(120) NOT NULL,
                    actor_email VARCHAR(254) NOT NULL,
                    action VARCHAR(80) NOT NULL,
                    target_type VARCHAR(40) NOT NULL,
                    target_id BIGINT UNSIGNED NOT NULL,
                    target_label VARCHAR(254) NULL,
                    reason VARCHAR(500) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX moderation_activity_time (created_at, id)
                ) ENGINE=InnoDB'
            );
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS superadmin_users (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(120) NOT NULL,
                    email VARCHAR(254) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    temp_password_hash VARCHAR(255) NULL,
                    temp_password_expires_at DATETIME NULL,
                    temp_password_sent_at DATETIME NULL,
                    temp_password_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    profile_image VARCHAR(255) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY superadmin_users_email_unique (email)
                ) ENGINE=InnoDB'
            );
            foreach (['admin_users', 'superadmin_users'] as $table) {
                $columns = $mysql->prepare(
                    'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
                );
                $columns->execute([$table]);
                $existingColumns = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));
                foreach ([
                    'temp_password_hash' => 'VARCHAR(255) NULL',
                    'temp_password_expires_at' => 'DATETIME NULL',
                    'temp_password_sent_at' => 'DATETIME NULL',
                    'temp_password_attempts' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
                ] as $column => $definition) {
                    if (!isset($existingColumns[$column])) {
                        $mysql->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                    }
                }
            }
            $mysql->exec(
                'CREATE TABLE IF NOT EXISTS superadmin_app_state (
                    user_id BIGINT UNSIGNED NOT NULL,
                    state_key VARCHAR(40) NOT NULL,
                    state_data LONGTEXT NOT NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id, state_key),
                    CONSTRAINT superadmin_app_state_user_fk FOREIGN KEY (user_id) REFERENCES superadmin_users (id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );
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
                    restricted_by BIGINT UNSIGNED NULL,
                    superadmin_restricted_by BIGINT UNSIGNED NULL,
                    reason VARCHAR(500) NOT NULL,
                    created_at BIGINT NOT NULL,
                    PRIMARY KEY (user_id),
                    CONSTRAINT admin_user_restrictions_user_fk FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE,
                    CONSTRAINT admin_user_restrictions_admin_fk FOREIGN KEY (restricted_by) REFERENCES admin_users (id) ON DELETE CASCADE,
                    CONSTRAINT admin_user_restrictions_superadmin_fk FOREIGN KEY (superadmin_restricted_by) REFERENCES superadmin_users (id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );
            $restrictionColumn = $mysql->query(
                "SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_restrictions' AND COLUMN_NAME = 'restricted_by'"
            )->fetchColumn();
            if ($restrictionColumn === 'NO') {
                $mysql->exec('ALTER TABLE admin_user_restrictions MODIFY restricted_by BIGINT UNSIGNED NULL');
            }
            $superadminRestrictionColumn = $mysql->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_restrictions' AND COLUMN_NAME = 'superadmin_restricted_by'"
            )->fetchColumn();
            if ((int)$superadminRestrictionColumn === 0) {
                $mysql->exec(
                    'ALTER TABLE admin_user_restrictions ADD COLUMN superadmin_restricted_by BIGINT UNSIGNED NULL,
                     ADD CONSTRAINT admin_user_restrictions_superadmin_fk FOREIGN KEY (superadmin_restricted_by) REFERENCES superadmin_users (id) ON DELETE CASCADE'
                );
            }
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
                    status VARCHAR(50) NOT NULL DEFAULT \'Pending\',
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
                "CREATE TABLE IF NOT EXISTS mobile_appeals (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    appeal_message TEXT NOT NULL,
                    status VARCHAR(50) DEFAULT 'Pending',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX mobile_appeals_user (user_id, id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
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
            foreach ([
                'admin_mobile_post_moderation' => 'moderated_by_role',
                'admin_mobile_comment_moderation' => 'moderated_by_role',
                'admin_mobile_user_moderation' => 'moderated_by_role',
                'admin_removed_mobile_posts' => 'removed_by_role',
                'admin_removed_mobile_comments' => 'removed_by_role',
            ] as $table => $column) {
                $check = $mysql->prepare(
                    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
                );
                $check->execute([$table, $column]);
                if ((int)$check->fetchColumn() === 0) {
                    $mysql->exec("ALTER TABLE {$table} ADD COLUMN {$column} ENUM('moderator', 'admin') NOT NULL DEFAULT 'moderator'");
                }
            }
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
