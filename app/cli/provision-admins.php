<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This command is only available from the server command line.');
}

$emails = array_map(static fn(string $email): string => strtolower(trim($email)), array_slice($argv, 1));
if (!$emails || count($emails) !== count(array_unique($emails))) {
    fwrite(STDERR, "Provide one or more distinct administrator email addresses.\n");
    exit(1);
}
foreach ($emails as $email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        fwrite(STDERR, "An administrator email address is invalid.\n");
        exit(1);
    }
}

fwrite(STDERR, "Password (input is visible): ");
$passwordInput = fgets(STDIN);
fwrite(STDERR, "Confirm password (input is visible): ");
$confirmationInput = fgets(STDIN);
if ($passwordInput === false || $confirmationInput === false) {
    fwrite(STDERR, "Password input was not provided.\n");
    exit(1);
}
$password = rtrim($passwordInput, "\r\n");
$confirmation = rtrim($confirmationInput, "\r\n");
if (strlen($password) < 8 || $password !== $confirmation) {
    fwrite(STDERR, "Passwords must match and contain at least 8 characters.\n");
    exit(1);
}
unset($passwordInput, $confirmationInput, $confirmation);

try {
    $config = require dirname(__DIR__, 2) . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );
    $db = new PDO($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    require_once dirname(__DIR__) . '/Models/UserModel.php';
    $db->beginTransaction();
    try {
        $placeholders = implode(', ', array_fill(0, count($emails), '?'));
        $check = $db->prepare("SELECT email FROM superadmin_users WHERE email IN ({$placeholders}) FOR UPDATE");
        $check->execute($emails);
        if ($check->fetch() !== false) {
            throw new RuntimeException('An email is already an Admin account. No existing passwords were changed.');
        }

        $insert = $db->prepare('INSERT INTO superadmin_users (name, email, password_hash) VALUES (?, ?, ?)');
        foreach ($emails as $email) {
            $name = strstr($email, '@', true);
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        }

        $users = new UserModel($db);
        foreach ($emails as $email) {
            if ($users->login($email, $password, 'admin') === null) {
                throw new RuntimeException('Admin login verification failed.');
            }
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
    echo count($emails) . " Admin account(s) created and verified.\n";
    if (count($emails) > 1) {
        echo "For security, have each administrator change the shared password immediately.\n";
    }
} catch (PDOException $exception) {
    fwrite(STDERR, 'Database operation failed (SQLSTATE ' . $exception->getCode() . "). No accounts were created.\n");
    exit(1);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    unset($password);
}