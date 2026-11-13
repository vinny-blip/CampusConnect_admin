<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Models/MobileLoginLogModel.php';

$dsn = getenv('CC_TEST_MYSQL_DSN');
if (!is_string($dsn) || preg_match('/(?:^|;)dbname=campusconnect_test(?:_[a-z0-9_]+)?(?:;|$)/i', $dsn) !== 1) {
    throw new RuntimeException('CC_TEST_MYSQL_DSN must point to a dedicated campusconnect_test database.');
}
$db = new PDO($dsn, (string)getenv('CC_TEST_DB_USER'), (string)getenv('CC_TEST_DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

function expectMobileLoginPaginationModel(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$prefix = 'pag' . bin2hex(random_bytes(6)) . 'x';
$inserted = false;
try {
    $countBefore = (int)$db->query('SELECT COUNT(*) FROM admin_mobile_login_logs')->fetchColumn();
    $insert = $db->prepare(
        'INSERT INTO admin_mobile_login_logs
         (mobile_user_id, full_name, username, institutional_email, session_token_hash, login_at, last_activity_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $inserted = true;
    for ($index = 0; $index < 26; $index++) {
        $username = $prefix . $index;
        $insert->execute([
            random_int(1, 1000000000),
            'Pagination Test',
            $username,
            $username . '@example.edu',
            hash('sha256', $prefix . ':' . $index),
            '9999-12-30 23:59:59',
            '9999-12-30 23:59:59',
        ]);
    }
    $logs = new MobileLoginLogModel($db);
    $firstPage = $logs->sessionPage(1);
    $secondPage = $logs->sessionPage(2);
    expectMobileLoginPaginationModel(count($firstPage['sessions']) === 25, 'A page should contain no more than 25 sessions.');
    expectMobileLoginPaginationModel(
        $firstPage['total_pages'] === (int)ceil(($countBefore + 26) / 25),
        'The page count should include all recorded sessions.'
    );
    expectMobileLoginPaginationModel(
        $firstPage['sessions'][0]['username'] === $prefix . '25',
        'The first page should show the newest sessions first.'
    );
    expectMobileLoginPaginationModel(
        $secondPage['sessions'][0]['username'] === $prefix . '0',
        'The next page should contain the next session after the first 25.'
    );
    $lastPage = $logs->sessionPage(PHP_INT_MAX);
    expectMobileLoginPaginationModel(
        $lastPage['current_page'] === $lastPage['total_pages'],
        'Out-of-range pages should be clamped to the last available page.'
    );

    echo "Mobile login log pagination model tests passed.\n";
} finally {
    if ($inserted) {
        $db->prepare('DELETE FROM admin_mobile_login_logs WHERE username LIKE ?')->execute([$prefix . '%']);
    }
}
