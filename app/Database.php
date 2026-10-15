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
            $mysql->query('SELECT id, name, email, password_hash FROM users LIMIT 0');
            $mysql->query('SELECT user_id, state_key, state_data FROM app_state LIMIT 0');
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
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        self::$connection->exec(
            'CREATE TABLE IF NOT EXISTS app_state (
                user_id INTEGER NOT NULL,
                state_key TEXT NOT NULL,
                state_data TEXT NOT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, state_key),
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
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
