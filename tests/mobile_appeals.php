<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';

function expectMobileAppeal(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$page = 'users';
$mobileAvailable = true;
$mobileUserCounts = ['total' => 1, 'suspended' => 0, 'banned' => 1];
$mobileUserFilter = 'banned';
$mobileQuery = '';
$csrf = '';
$mobileUsers = [[
    'id' => 42,
    'full_name' => 'Appealing User',
    'username' => 'appealing-user',
    'institutional_email' => 'appeal@example.edu',
    'profile_pic_url' => 'https://mobile.example/avatar.png',
    'moderation_status' => 'banned',
    'suspended_until' => null,
    'suspension_timezone_offset' => null,
    'posts_count' => 2,
    'comments_count' => 1,
    'violation_count' => 0,
    'appeal_message' => '<img src=x onerror=alert(1)> "Please review"',
    'appeal_status' => 'Pending',
    'appeal_created_at' => '2025-01-02 03:04:05',
]];

ob_start();
require dirname(__DIR__) . '/app/Views/pages/mobile-list.php';
$html = ob_get_clean();

expectMobileAppeal(str_contains($html, '>View appeal</button>'), 'The banned user does not have a View appeal button.');
expectMobileAppeal(str_contains($html, '>Unban</button>'), 'The appeal button is not beside the Unban action.');
expectMobileAppeal(str_contains($html, 'data-appeal-status="Pending"'), 'Appeal status is missing from the button data.');
expectMobileAppeal(str_contains($html, 'data-appeal-created-at="2025-01-02 03:04:05"'), 'Appeal submission time is missing from the button data.');
expectMobileAppeal(str_contains($html, 'data-appeal-message="&lt;img src=x onerror=alert(1)&gt; &quot;Please review&quot;"'), 'The appeal message was not safely escaped in the button data.');
expectMobileAppeal(!str_contains($html, '<img src=x'), 'Untrusted appeal content was rendered as HTML.');
expectMobileAppeal(str_contains($html, 'data-appeal-dialog aria-labelledby="appeal-dialog-title"'), 'The appeal dialog is missing its accessible label.');

$mobileUsers[0]['appeal_message'] = '';
ob_start();
require dirname(__DIR__) . '/app/Views/pages/mobile-list.php';
$htmlWithoutAppeal = ob_get_clean();
expectMobileAppeal(!str_contains($htmlWithoutAppeal, '>View appeal</button>'), 'A user without an appeal should not have a View appeal button.');
expectMobileAppeal(str_contains($htmlWithoutAppeal, '>Unban</button>'), 'The Unban action is missing when there is no appeal.');

echo "Mobile appeal rendering tests passed.\n";
