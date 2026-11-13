<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Models/ModerationActivityModel.php';
require dirname(__DIR__) . '/app/Models/ModeratorAccountModel.php';
require dirname(__DIR__) . '/app/Controllers/PageController.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('This isolated transaction test requires PDO SQLite.');
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE changes (id INTEGER PRIMARY KEY)');
$db->exec(
    'CREATE TABLE superadmin_moderation_activity (
        actor_id INTEGER, actor_role TEXT, actor_name TEXT, actor_email TEXT,
        action TEXT, target_type TEXT, target_id INTEGER, reason TEXT, target_label TEXT
    )'
);
$controller = (new ReflectionClass(PageController::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(PageController::class, 'db'))->setValue($controller, $db);
(new ReflectionProperty(PageController::class, 'user'))->setValue($controller, [
    'id' => 1, 'role' => 'moderator', 'name' => 'Moderator', 'email' => 'test@example.edu',
]);
$action = new ReflectionMethod(PageController::class, 'loggedAction');
$run = static fn(callable $operation): bool => $action->invoke($controller, 'mobile-remove', 'post', 9, $operation);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$expect($run(static function () use ($db): bool {
    $db->exec('INSERT INTO changes VALUES (1)');
    return true;
}), 'A successful operation should succeed.');
$expect((int)$db->query('SELECT COUNT(*) FROM superadmin_moderation_activity')->fetchColumn() === 1, 'The successful action should be logged.');

$expect(!$run(static function () use ($db): bool {
    $db->exec('INSERT INTO changes VALUES (2)');
    return false;
}), 'An unsuccessful operation should return false.');
$expect((int)$db->query('SELECT COUNT(*) FROM changes')->fetchColumn() === 1, 'A failed operation must be rolled back.');

$db->exec("CREATE TABLE admin_users (id INTEGER PRIMARY KEY, role TEXT, suspended INTEGER, suspended_until TEXT)");
$db->exec("CREATE TABLE superadmin_moderator_login_logs (moderator_id INTEGER, logout_at TEXT, end_reason TEXT)");
$db->exec("INSERT INTO admin_users VALUES (4, 'moderator', 0, NULL)");
$moderators = new ModeratorAccountModel($db);
$expect($run(static fn(): bool => $moderators->setSuspended(4, true, '2030-01-01 00:00:00')), 'Suspension inside an audit transaction should succeed.');
$expect((int)$db->query('SELECT suspended FROM admin_users WHERE id = 4')->fetchColumn() === 1, 'Suspension must commit with its audit entry.');

$db->exec('DROP TABLE superadmin_moderation_activity');
try {
    $run(static function () use ($db): bool {
        $db->exec('INSERT INTO changes VALUES (3)');
        return true;
    });
    throw new RuntimeException('A failed audit insert must raise an error.');
} catch (PDOException) {
    $expect((int)$db->query('SELECT COUNT(*) FROM changes')->fetchColumn() === 1, 'A failed audit insert must roll back the operation.');
}
try {
    $run(static fn(): bool => $moderators->setSuspended(4, false));
    throw new RuntimeException('An account change without an audit entry must fail.');
} catch (PDOException) {
    $expect((int)$db->query('SELECT suspended FROM admin_users WHERE id = 4')->fetchColumn() === 1, 'An audit failure must roll back the moderator account change.');
}

echo "Moderation activity transaction tests passed.\n";
