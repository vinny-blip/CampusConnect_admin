<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Models/UserModel.php';
require dirname(__DIR__) . '/app/Models/ModeratorAccountModel.php';

$dsn = getenv('CC_TEST_MYSQL_DSN');
if (!is_string($dsn) || preg_match('/(?:^|;)dbname=campusconnect_test(?:_[a-z0-9_]+)?(?:;|$)/i', $dsn) !== 1) {
    throw new RuntimeException('CC_TEST_MYSQL_DSN must point to a dedicated campusconnect_test database.');
}
$db = new PDO($dsn, (string)getenv('CC_TEST_DB_USER'), (string)getenv('CC_TEST_DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$id = random_int(1000000000, 2000000000);
$email = "moderator-test-{$id}@example.edu";
$password = 'test-password';
$inserted = false;
try {
    $db->prepare('INSERT INTO admin_users (id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, 'Test Moderator', $email, password_hash($password, PASSWORD_DEFAULT), 'moderator']);
    $inserted = true;
    $accounts = new ModeratorAccountModel($db);
    $users = new UserModel($db);
    $first = $accounts->startSession($id);
    $second = $accounts->startSession($id);
    expect($first !== null && $second !== null && $accounts->sessionActive($first, $id), 'Moderator sessions were not created.');
    $future = (new DateTimeImmutable('+1 hour', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    expect($accounts->setSuspended($id, true, $future), 'Suspension failed.');
    $row = $db->query("SELECT suspended_until FROM admin_users WHERE id = {$id}")->fetch();
    expect($row['suspended_until'] === $future, 'Suspension deadline was not stored.');
    $accounts->restoreExpiredSuspensions();
    expect($users->login($email, $password, 'moderator') === null, 'Future suspension was restored early.');
    expect(!$accounts->sessionActive($first, $id) && !$accounts->sessionActive($second, $id), 'Suspension must end every session.');
    expect($accounts->startSession($id) === null && $users->login($email, $password, 'moderator') === null, 'Suspended moderator can sign in.');
    expect($users->findById($id, 'moderator') === null, 'Suspended account can resolve an existing session.');
    expect($accounts->setSuspended($id, false), 'Restoring the moderator failed.');
    expect($db->query("SELECT suspended_until FROM admin_users WHERE id = {$id}")->fetchColumn() === null, 'Manual restore must clear the deadline.');
    expect(!$accounts->sessionActive($first, $id), 'Restoring must not revive an old session.');
    expect($users->login($email, $password, 'moderator') !== null, 'Restored moderator cannot sign in.');
    expect($accounts->setSuspended($id, true, $future), 'Timed suspension failed.');
    $db->prepare('UPDATE admin_users SET suspended_until = ? WHERE id = ?')->execute([
        (new DateTimeImmutable('-1 minute', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        $id,
    ]);
    $accounts->list();
    expect($users->login($email, $password, 'moderator') !== null, 'Expired suspension was not restored on overview.');
    expect($db->query("SELECT suspended_until FROM admin_users WHERE id = {$id}")->fetchColumn() === null, 'Expired deadline was not cleared.');
    $db->prepare('UPDATE admin_users SET suspended = 1, suspended_until = NULL WHERE id = ?')->execute([$id]);
    $accounts->restoreExpiredSuspensions();
    expect($users->login($email, $password, 'moderator') === null, 'Legacy indefinite suspension must not expire.');
    expect($accounts->setSuspended($id, false), 'Restoring legacy suspension failed.');
    $third = $accounts->startSession($id);
    expect($third !== null, 'Restored moderator session was not created.');
    $accounts->endSession($third, $id);
    expect(!$accounts->sessionActive($third, $id), 'Logout did not end the session.');
    $fourth = $accounts->startSession($id);
    expect($fourth !== null && $accounts->remove($id), 'Removing moderator failed.');
    expect($users->login($email, $password, 'moderator') === null, 'Removed moderator can sign in.');
    $statement = $db->prepare('SELECT moderator_id, end_reason FROM superadmin_moderator_login_logs WHERE id = ?');
    $statement->execute([$fourth]);
    $log = $statement->fetch();
    expect($log && $log['moderator_id'] === null && $log['end_reason'] === 'removed', 'Removal must preserve and close the login log.');
    expect(!$accounts->remove($id), 'Removing a missing moderator should fail.');
    echo "Moderator account tests passed.\n";
} finally {
    if ($inserted) {
        $db->prepare('DELETE FROM superadmin_moderator_login_logs WHERE moderator_email = ?')->execute([$email]);
        $db->prepare('DELETE FROM admin_users WHERE id = ? AND email = ?')->execute([$id, $email]);
    }
}
