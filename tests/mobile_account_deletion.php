<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Models/ModerationActivityModel.php';
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

function expectMobileAccountDeletion(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$id = random_int(1000000000, 1999999900);
$other = $id + 1;
$email = "delete-test-{$id}@example.edu";
$otherEmail = "delete-test-{$other}@example.edu";
$controller = (new ReflectionClass(PageController::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(PageController::class, 'db'))->setValue($controller, $db);
(new ReflectionProperty(PageController::class, 'user'))->setValue($controller, [
    'id' => $id, 'name' => 'Test Admin', 'email' => $email, 'role' => 'admin',
]);
$action = new ReflectionMethod(PageController::class, 'loggedAction');
$mobile = new MobileModerationModel($db, $id, 'admin');

try {
    $users = $db->prepare('INSERT INTO mobile_users (id, institutional_email, id_number, full_name, username, password_hash) VALUES (?, ?, ?, ?, ?, ?)');
    $users->execute([$id, $email, "id{$id}", 'Delete Test', "delete{$id}", 'test']);
    $users->execute([$other, $otherEmail, "id{$other}", 'Keep Test', "keep{$other}", 'test']);
    $posts = $db->prepare('INSERT INTO mobile_posts (id, user_id, title, category, content) VALUES (?, ?, ?, ?, ?)');
    $posts->execute([$id, $id, 'Delete post', 'test', 'test']);
    $posts->execute([$other, $other, 'Keep post', 'test', 'test']);
    $comments = $db->prepare('INSERT INTO mobile_comments (id, post_id, user_id, content) VALUES (?, ?, ?, ?)');
    $comments->execute([$id, $other, $id, 'Delete author comment']);
    $comments->execute([$other, $id, $other, 'Delete post comment']);
    $comments->execute([$id + 2, $other, $other, 'Keep comment']);
    $db->prepare('INSERT INTO mobile_reports (id, post_id, reporter_user_id) VALUES (?, ?, ?), (?, ?, ?)')
        ->execute([$id, $id, $other, $other, $other, $other]);
    $db->prepare('INSERT INTO mobile_comment_reports (id, comment_id, reporter_user_id) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?)')
        ->execute([$id, $other, $other, $other, $id + 2, $other, $id + 2, $id + 2, $id]);
    $db->prepare('INSERT INTO admin_mobile_post_moderation (mobile_post_id) VALUES (?)')->execute([$id]);
    $db->prepare('INSERT INTO admin_mobile_comment_moderation (mobile_comment_id) VALUES (?), (?)')->execute([$id, $other]);
    $db->prepare('INSERT INTO admin_removed_mobile_posts (mobile_post_id, mobile_user_id, title) VALUES (?, ?, ?)')->execute([$id + 2, $id, 'Archived']);
    $db->prepare('INSERT INTO admin_removed_mobile_comments (mobile_comment_id, mobile_post_id, mobile_user_id) VALUES (?, ?, ?)')
        ->execute([$id + 3, $id + 2, $other]);
    $db->prepare('INSERT INTO mobile_appeals (user_id, appeal_message) VALUES (?, ?)')->execute([$id, 'Test']);
    $db->prepare('INSERT INTO mobile_notifications (id, user_id, title, message, post_id) VALUES (?, ?, ?, ?, ?), (?, ?, ?, ?, ?), (?, ?, ?, ?, ?)')
        ->execute([$id, $other, 'Delete', 'Test', $id, $other, $other, 'Keep', 'Test', $other, $id + 2, $id, 'Delete', 'Test', $other]);
    $db->prepare('INSERT INTO mobile_otps (email, otp_code, expires_at) VALUES (?, ?, ?)')->execute([$email, '123456', '2030-01-01 00:00:00']);
    $db->prepare('INSERT INTO admin_mobile_user_moderation (mobile_user_id, status) VALUES (?, ?)')->execute([$id, 'banned']);
    $db->prepare('INSERT INTO admin_mobile_login_logs (mobile_user_id, full_name, username, institutional_email, session_token_hash) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, 'Delete Test', "delete{$id}", $email, hash('sha256', "delete{$id}")]);

    $page = $mobile->accountPage("delete{$id}", 1);
    expectMobileAccountDeletion($page['total'] === 1 && (int)$page['accounts'][0]['id'] === $id, 'Account search should find the matching user.');
    for ($index = 0; $index < 26; $index++) {
        $batchId = $id + 10 + $index;
        $users->execute([$batchId, "batch{$id}-{$index}@example.edu", "batch{$id}-{$index}", 'Batch Test', "batch{$id}-{$index}", 'test']);
    }
    $firstPage = $mobile->accountPage("batch{$id}-", 1);
    $secondPage = $mobile->accountPage("batch{$id}-", 2);
    expectMobileAccountDeletion($firstPage['total'] === 26 && count($firstPage['accounts']) === 25 && $firstPage['pages'] === 2, 'The first results page must be limited to 25 accounts.');
    expectMobileAccountDeletion(count($secondPage['accounts']) === 1 && $secondPage['page'] === 2, 'The second results page must contain the remaining account.');
    expectMobileAccountDeletion($mobile->accountPage("batch{$id}-", 999)['page'] === 2, 'Out-of-range pages must be clamped.');
    try {
        $action->invoke($controller, 'delete-mobile-account', 'mobile user', $id, function () use ($mobile, $id): bool {
            $mobile->deleteUserAccount($id);
            throw new RuntimeException('test rollback');
        });
        throw new RuntimeException('A failed deletion should not succeed.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'test rollback') throw $exception;
    }
    $check = $db->prepare('SELECT COUNT(*) FROM mobile_users WHERE id = ?');
    $check->execute([$id]);
    expectMobileAccountDeletion((int)$check->fetchColumn() === 1, 'An error must roll back deletion.');
    expectMobileAccountDeletion($action->invoke($controller, 'delete-mobile-account', 'mobile user', $id, fn(): bool => $mobile->deleteUserAccount($id)), 'Deletion should succeed.');
    $check->execute([$id]);
    expectMobileAccountDeletion((int)$check->fetchColumn() === 0, 'The selected account must be deleted.');
    $check->execute([$other]);
    expectMobileAccountDeletion((int)$check->fetchColumn() === 1, 'Other accounts must remain.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM mobile_posts WHERE id = {$other}")->fetchColumn() === 1, 'Other users posts must remain.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM mobile_comments WHERE id = " . ($id + 2))->fetchColumn() === 1, 'Unrelated comments must remain.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM mobile_comments WHERE id IN ({$id}, {$other})")->fetchColumn() === 0, 'The user comments and comments on their posts must be removed.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM mobile_comment_reports WHERE id IN ({$id}, " . ($id + 2) . ')')->fetchColumn() === 0, 'Related comment reports must be removed.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM mobile_comment_reports WHERE id = {$other}")->fetchColumn() === 1, 'Unrelated comment reports must remain.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM admin_removed_mobile_comments WHERE mobile_comment_id = " . ($id + 3))->fetchColumn() === 0, 'Archived comments on deleted posts must be removed.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM admin_removed_mobile_posts WHERE mobile_user_id = {$id}")->fetchColumn() === 0, 'Archived posts must be removed.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM mobile_appeals WHERE user_id = {$id}")->fetchColumn() === 0, 'The account appeals must be removed.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM mobile_notifications WHERE id IN ({$id}, " . ($id + 2) . ')')->fetchColumn() === 0, 'Notifications for the account and its posts must be removed.');
    expectMobileAccountDeletion((int)$db->query("SELECT COUNT(*) FROM mobile_notifications WHERE id = {$other}")->fetchColumn() === 1, 'Unrelated notifications must remain.');
    $otp = $db->prepare('SELECT COUNT(*) FROM mobile_otps WHERE email = ?');
    $otp->execute([$email]);
    expectMobileAccountDeletion((int)$otp->fetchColumn() === 0, 'Outstanding password reset codes must be removed.');
    $log = $db->prepare('SELECT logout_at FROM admin_mobile_login_logs WHERE mobile_user_id = ?');
    $log->execute([$id]);
    expectMobileAccountDeletion(is_string($log->fetchColumn()), 'Existing mobile sessions must be ended while retaining their logs.');
    $audit = $db->prepare('SELECT target_label FROM superadmin_moderation_activity WHERE actor_id = ? AND action = ?');
    $audit->execute([$id, 'delete-mobile-account']);
    expectMobileAccountDeletion($audit->fetchColumn() === $email, 'The audit log must retain the deleted account identity.');
    expectMobileAccountDeletion(!$action->invoke($controller, 'delete-mobile-account', 'mobile user', $id, fn(): bool => $mobile->deleteUserAccount($id)), 'Repeating deletion must not succeed.');

    echo "Mobile account deletion MySQL tests passed.\n";
} finally {
    $db->prepare('DELETE FROM superadmin_moderation_activity WHERE actor_id = ? AND actor_email = ?')->execute([$id, $email]);
    $db->prepare('DELETE FROM mobile_comment_reports WHERE id IN (?, ?, ?)')->execute([$id, $other, $id + 2]);
    $db->prepare('DELETE FROM admin_mobile_comment_moderation WHERE mobile_comment_id IN (?, ?, ?)')->execute([$id, $other, $id + 3]);
    $db->prepare('DELETE FROM admin_mobile_post_moderation WHERE mobile_post_id IN (?, ?)')->execute([$id, $id + 2]);
    $db->prepare('DELETE FROM admin_removed_mobile_comments WHERE mobile_comment_id = ?')->execute([$id + 3]);
    $db->prepare('DELETE FROM admin_removed_mobile_posts WHERE mobile_post_id = ?')->execute([$id + 2]);
    $db->prepare('DELETE FROM mobile_appeals WHERE user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM mobile_otps WHERE email = ?')->execute([$email]);
    $db->prepare('DELETE FROM admin_mobile_user_moderation WHERE mobile_user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM admin_mobile_login_logs WHERE mobile_user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM mobile_users WHERE username LIKE ?')->execute(["batch{$id}-%"]);
    $db->prepare('DELETE FROM mobile_users WHERE id IN (?, ?)')->execute([$id, $other]);
}
