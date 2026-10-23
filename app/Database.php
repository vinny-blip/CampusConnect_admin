<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

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
            $mysql->query('SELECT id, name, email, password_hash, role FROM admin_users LIMIT 0');
            $mysql->query('SELECT user_id, state_key, state_data FROM admin_app_state LIMIT 0');
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
                'CREATE TABLE IF NOT EXISTS admin_mobile_user_moderation (
                    mobile_user_id INT NOT NULL PRIMARY KEY,
                    status ENUM("active","suspended") NOT NULL DEFAULT "active",
                    reason VARCHAR(500) NULL,
                    moderated_by BIGINT UNSIGNED NULL,
                    moderated_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX admin_mobile_user_status (status)
                ) ENGINE=InnoDB'
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
