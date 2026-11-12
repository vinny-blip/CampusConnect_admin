<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Models/UserModel.php';
require dirname(__DIR__) . '/app/Models/StateModel.php';
require dirname(__DIR__) . '/app/Models/RestrictedModel.php';
require dirname(__DIR__) . '/app/Models/MobileModerationModel.php';

final class TestState extends StateModel
{
    public function put(string $value): void
    {
        $this->save('role-test', ['value' => $value]);
    }

    public function get(): string
    {
        return $this->load('role-test', static fn() => [])['value'];
    }
}

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
$email = "role-test-{$id}@example.edu";
$moderatorPassword = 'student-test-password';
$adminPassword = 'admin-test-password';
$db->beginTransaction();
try {
    $db->prepare('INSERT INTO admin_users (id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, 'Student', $email, password_hash($moderatorPassword, PASSWORD_DEFAULT), 'moderator']);
    $db->prepare('INSERT INTO superadmin_users (id, name, email, password_hash) VALUES (?, ?, ?, ?)')
        ->execute([$id, 'Administrator', $email, password_hash($adminPassword, PASSWORD_DEFAULT)]);

    $users = new UserModel($db);
    expect(($users->login($email, $moderatorPassword, 'moderator')['role'] ?? '') === 'moderator', 'Moderator login failed.');
    expect(($users->login($email, $adminPassword, 'admin')['role'] ?? '') === 'admin', 'Admin login failed.');
    expect($users->login($email, $moderatorPassword, 'admin') === null, 'Moderator password must not grant admin access.');
    expect($users->login($email, $adminPassword, 'moderator') === null, 'Admin password must not grant moderator access.');
    expect($users->login($email, $adminPassword, 'invalid') === null, 'Invalid role must be rejected.');
    expect(($users->findById($id, 'admin')['name'] ?? '') === 'Administrator', 'Admin session resolved the wrong account.');
    expect(($users->findById($id, 'moderator')['name'] ?? '') === 'Student', 'Moderator session resolved the wrong account.');

    $users->updateName($id, 'Admin Updated', 'admin');
    $users->updateProfileImage($id, 'admin-image.png', 'admin');
    expect($users->changePassword($id, $adminPassword, 'replacement-password', 'admin'), 'Admin password change failed.');
    expect(($users->findById($id, 'moderator')['name'] ?? '') === 'Student', 'Admin profile update affected moderator.');
    expect(($users->findById($id, 'admin')['profile_image'] ?? '') === 'admin-image.png', 'Admin image was not saved.');
    expect($users->login($email, 'replacement-password', 'admin') !== null, 'Admin replacement password failed.');
    expect($users->login($email, $moderatorPassword, 'moderator') !== null, 'Moderator password was changed.');

    $studentState = new TestState($db, $id, 'moderator');
    $adminState = new TestState($db, $id, 'admin');
    $studentState->put('student');
    $adminState->put('admin');
    expect($studentState->get() === 'student' && $adminState->get() === 'admin', 'Account state collided.');

    $restrictions = new RestrictedModel($db);
    expect($restrictions->add($email, 'Test', $id) === null, 'Admin could not restrict moderator.');
    $statement = $db->prepare('SELECT restricted_by, superadmin_restricted_by FROM admin_user_restrictions WHERE user_id = ?');
    $statement->execute([$id]);
    $restriction = $statement->fetch();
    expect($restriction['restricted_by'] === null && (int)$restriction['superadmin_restricted_by'] === $id, 'Restriction actor was not the admin.');

    $mobile = new MobileModerationModel($db, $id, 'admin');
    $mobile->setPostStatus($id, 'approved');
    $mobile->setCommentStatus($id, 'hidden');
    $mobile->setUserStatus($id, 'banned');
    foreach ([
        ['admin_mobile_post_moderation', 'mobile_post_id'],
        ['admin_mobile_comment_moderation', 'mobile_comment_id'],
        ['admin_mobile_user_moderation', 'mobile_user_id'],
    ] as [$table, $key]) {
        $statement = $db->prepare("SELECT moderated_by, moderated_by_role FROM {$table} WHERE {$key} = ?");
        $statement->execute([$id]);
        $actor = $statement->fetch();
        expect((int)$actor['moderated_by'] === $id && $actor['moderated_by_role'] === 'admin', 'Moderation actor was ambiguous.');
    }
    $mobile = new MobileModerationModel($db, $id, 'moderator');
    $mobile->setPostStatus($id, 'pending');
    $mobile->setCommentStatus($id, 'approved');
    $mobile->setUserStatus($id, 'active');
    foreach ([
        ['admin_mobile_post_moderation', 'mobile_post_id'],
        ['admin_mobile_comment_moderation', 'mobile_comment_id'],
        ['admin_mobile_user_moderation', 'mobile_user_id'],
    ] as [$table, $key]) {
        $statement = $db->prepare("SELECT moderated_by_role FROM {$table} WHERE {$key} = ?");
        $statement->execute([$id]);
        expect($statement->fetchColumn() === 'moderator', 'Moderation actor role was not updated.');
    }
} finally {
    $db->rollBack();
}

$db->exec('CREATE TEMPORARY TABLE mobile_users (id INT PRIMARY KEY, full_name VARCHAR(100), username VARCHAR(50))');
$db->exec('CREATE TEMPORARY TABLE mobile_posts (id INT PRIMARY KEY, user_id INT, title VARCHAR(255), content TEXT, category VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TEMPORARY TABLE mobile_comments (id INT PRIMARY KEY, user_id INT, post_id INT, content TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TEMPORARY TABLE mobile_comment_reports (comment_id INT, status VARCHAR(50))');
try {
    $db->prepare('INSERT INTO admin_users (id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, 'Student', $email, password_hash($moderatorPassword, PASSWORD_DEFAULT), 'moderator']);
    $db->prepare('INSERT INTO superadmin_users (id, name, email, password_hash) VALUES (?, ?, ?, ?)')
        ->execute([$id, 'Administrator', $email, password_hash($adminPassword, PASSWORD_DEFAULT)]);
    $db->prepare('INSERT INTO mobile_users (id, full_name, username) VALUES (?, ?, ?)')->execute([$id, 'Author', 'author']);
    $db->prepare('INSERT INTO mobile_posts (id, user_id, title, content, category) VALUES (?, ?, ?, ?, ?), (?, ?, ?, ?, ?)')
        ->execute([$id, $id, 'Admin post', 'Content', 'Test', $id + 1, $id, 'Student post', 'Content', 'Test']);
    $db->prepare('INSERT INTO mobile_comments (id, user_id, post_id, content) VALUES (?, ?, ?, ?), (?, ?, ?, ?)')
        ->execute([$id, $id, $id, 'Admin comment', $id + 1, $id, $id, 'Student comment']);

    $admin = new MobileModerationModel($db, $id, 'admin');
    $moderator = new MobileModerationModel($db, $id, 'moderator');
    expect($admin->removeComment($id) && $moderator->removeComment($id + 1), 'Comment removal failed.');
    expect($admin->removePost($id) && $moderator->removePost($id + 1), 'Post removal failed.');
    foreach ([
        ['admin_removed_mobile_comments', 'mobile_comment_id'],
        ['admin_removed_mobile_posts', 'mobile_post_id'],
    ] as [$table, $key]) {
        $statement = $db->prepare("SELECT removed_by, removed_by_role, removed_by_name FROM {$table} WHERE {$key} = ?");
        foreach ([[$id, 'admin', 'Administrator'], [$id + 1, 'moderator', 'Student']] as [$contentId, $role, $name]) {
            $statement->execute([$contentId]);
            $actor = $statement->fetch();
            expect((int)$actor['removed_by'] === $id && $actor['removed_by_role'] === $role && $actor['removed_by_name'] === $name, 'Archived content was attributed to the wrong role.');
        }
    }
    echo "Separate role tests passed.\n";
} finally {
    $db->prepare('DELETE FROM admin_removed_mobile_posts WHERE mobile_post_id IN (?, ?)')->execute([$id, $id + 1]);
    $db->prepare('DELETE FROM admin_removed_mobile_comments WHERE mobile_comment_id IN (?, ?)')->execute([$id, $id + 1]);
    $db->prepare('DELETE FROM mobile_notifications WHERE user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
    $db->prepare('DELETE FROM superadmin_users WHERE id = ?')->execute([$id]);
}
