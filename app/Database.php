<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;
    private static bool $usingLocalStorage = false;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require dirname(__DIR__) . '/config/database.php';
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
            $mysql->query('SELECT id, name, email, password_hash FROM admin_users LIMIT 0');
            $mysql->query('SELECT user_id, state_key, state_data FROM admin_app_state LIMIT 0');
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
            self::$connection = $mysql;
            return $mysql;
        } catch (PDOException $mysqlException) {
            if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                throw $mysqlException;
            }
        }

        $storageDirectory = __DIR__ . '/data';
        if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0770, true) && !is_dir($storageDirectory)) {
            throw new RuntimeException('Could not create local app storage at ' . $storageDirectory);
        }

        self::$connection = new PDO('sqlite:' . $storageDirectory . '/campusconnect.sqlite', null, null, $options);
        self::$connection->exec('PRAGMA foreign_keys = ON');
        self::$connection->exec(
            'CREATE TABLE IF NOT EXISTS admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        self::$connection->exec(
            'CREATE TABLE IF NOT EXISTS admin_app_state (
                user_id INTEGER NOT NULL,
                state_key TEXT NOT NULL,
                state_data TEXT NOT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, state_key),
                FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE
            )'
        );
        self::$connection->exec(
            'CREATE TABLE IF NOT EXISTS admin_user_restrictions (
                user_id INTEGER PRIMARY KEY,
                restricted_by INTEGER NOT NULL,
                reason TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE,
                FOREIGN KEY (restricted_by) REFERENCES admin_users (id) ON DELETE CASCADE
            )'
        );
        self::$connection->exec(
            'CREATE TABLE IF NOT EXISTS admin_ai_moderation_settings (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                enabled INTEGER NOT NULL DEFAULT 0
            )'
        );
        self::$usingLocalStorage = true;

        return self::$connection;
    }

    public static function usingLocalStorage(): bool
    {
        return self::$usingLocalStorage;
    }
}
