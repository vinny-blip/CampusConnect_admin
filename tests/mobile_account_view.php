<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';

$csrf = '<input name="csrf_token" value="test">';
$accountSearch = 'name & <test>';
$mobileAccounts = [
    'total' => 26,
    'page' => 1,
    'pages' => 2,
    'accounts' => [[
        'id' => 42,
        'full_name' => '<Test User>',
        'username' => 'test',
        'institutional_email' => 'test@example.edu',
        'profile_pic_url' => null,
        'moderation_status' => 'active',
        'created_at' => '2026-01-01 10:00:00',
    ]],
];
ob_start();
require dirname(__DIR__) . '/app/Views/pages/user-accounts.php';
$html = ob_get_clean();

foreach ([
    '&lt;Test User&gt;' => 'Account names must be escaped.',
    'name &amp; &lt;test&gt;' => 'Search terms must be escaped.',
    'name="csrf_token"' => 'The deletion form must include CSRF protection.',
    'name="user_id" value="42"' => 'The form must identify the selected account.',
    'comments by others on its posts' => 'The deletion warning must describe affected comments.',
    'user-accounts.php?page=2&amp;q=name%20%26%20%3Ctest%3E' => 'Pagination must preserve the encoded search.',
] as $expected => $message) {
    if (!str_contains($html, $expected)) throw new RuntimeException($message);
}
echo "Mobile account view tests passed.\n";
