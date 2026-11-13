<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Models/ModerationActivityModel.php';
require dirname(__DIR__) . '/app/Models/ModeratorAccountModel.php';
require dirname(__DIR__) . '/app/Models/MobileModerationModel.php';
require dirname(__DIR__) . '/app/Controllers/PageController.php';

$dsn = getenv('CC_TEST_MYSQL_DSN');
if (!is_string($dsn) || preg_match('/(?:^|;)dbname=campusconnect_test(?:_[a-z0-9_]+)?(?:;|$)/i', $dsn) !== 1) {
    throw new RuntimeException('CC_TEST_MYSQL_DSN must point to a dedicated campusconnect_test database.');
}
$db = new PDO($dsn, (string)getenv('CC_TEST_DB_USER'), (string)getenv('CC_TEST_DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

function expectAdminActivity(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$id = random_int(1000000000, 2000000000);
$email = "activity-test-{$id}@example.edu";
$actor = ['id' => $id, 'name' => 'Test Admin', 'email' => $email, 'role' => 'admin'];
$controller = (new ReflectionClass(PageController::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(PageController::class, 'db'))->setValue($controller, $db);
(new ReflectionProperty(PageController::class, 'user'))->setValue($controller, $actor);
$loggedAction = new ReflectionMethod(PageController::class, 'loggedAction');
$accounts = new ModeratorAccountModel($db);

try {
    $db->prepare('INSERT INTO admin_users (id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, 'Test Moderator', $email, password_hash('test-password', PASSWORD_DEFAULT), 'moderator']);
    $sessionId = $accounts->startSession($id);
    expectAdminActivity($sessionId !== null, 'A moderator session should start.');
    $deadline = gmdate('Y-m-d H:i:s', time() + 3600);
    expectAdminActivity(
        $loggedAction->invoke($controller, 'suspend-moderator', 'moderator', $id,
            fn(): bool => $accounts->setSuspended($id, true, $deadline), 'Test reason'),
        'Suspension should succeed.'
    );
    $statement = $db->prepare('SELECT action, target_label, reason FROM superadmin_moderation_activity WHERE actor_id = ? ORDER BY id DESC LIMIT 1');
    $statement->execute([$id]);
    $activity = $statement->fetch();
    expectAdminActivity($activity['action'] === 'suspend-moderator' && $activity['reason'] === 'Test reason', 'Suspension and reason must be recorded.');
    expectAdminActivity($activity['target_label'] === $email, 'The target email must be preserved.');
    expectAdminActivity(!$accounts->sessionActive($sessionId, $id), 'Suspension must end the moderator session.');

    expectAdminActivity(
        $loggedAction->invoke($controller, 'remove-moderator', 'moderator', $id,
            fn(): bool => $accounts->remove($id), 'Removal reason'),
        'Removal should succeed.'
    );
    $statement->execute([$id]);
    $activity = $statement->fetch();
    expectAdminActivity($activity['action'] === 'remove-moderator' && $activity['target_label'] === $email, 'Removal history must retain the deleted account identity.');
    expectAdminActivity($activity['reason'] === 'Removal reason', 'Removal reason must be retained.');

    $db->exec('CREATE TEMPORARY TABLE mobile_reports (id INT PRIMARY KEY, status VARCHAR(50))');
    $db->exec('CREATE TEMPORARY TABLE mobile_comment_reports (id INT PRIMARY KEY, status VARCHAR(50))');
    $db->exec('CREATE TEMPORARY TABLE mobile_appeals (id INT PRIMARY KEY, status VARCHAR(50))');
    $db->exec('CREATE TEMPORARY TABLE mobile_posts (id INT PRIMARY KEY)');
    $db->exec("INSERT INTO mobile_reports VALUES (1, 'Pending'), (2, 'Dismissed')");
    $db->exec("INSERT INTO mobile_comment_reports VALUES (1, 'Pending'), (2, 'pending')");
    $db->exec("INSERT INTO mobile_appeals VALUES (1, 'Pending'), (2, 'Resolved')");
    $db->exec('INSERT INTO mobile_posts VALUES (2147483640)');
    $mobile = new MobileModerationModel($db, $id);
    expectAdminActivity(
        $mobile->adminReportOverview() === ['post_reports' => 1, 'comment_reports' => 2, 'appeals' => 1, 'pending_posts' => 1],
        'Reports overview should count only pending records and posts.'
    );
    expectAdminActivity(
        $loggedAction->invoke($controller, 'mobile-dismiss-report', 'post report', 1,
            fn(): bool => $mobile->setReportStatus(1, 'Dismissed')),
        'Dismissing a report should succeed.'
    );
    expectAdminActivity($mobile->adminReportOverview()['post_reports'] === 0, 'Dismissed reports must leave the pending count.');

    echo "Admin activity MySQL tests passed.\n";
} finally {
    $db->prepare('DELETE FROM superadmin_moderation_activity WHERE actor_id = ? AND actor_email = ?')->execute([$id, $email]);
    $db->prepare('DELETE FROM superadmin_moderator_login_logs WHERE moderator_email = ?')->execute([$email]);
    $db->prepare('DELETE FROM admin_users WHERE id = ? AND email = ?')->execute([$id, $email]);
}
