<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';

function expectMobileApproval(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$csrf = '';
$page = 'posts';
$mobileAvailable = true;
$mobileCounts = ['pending' => 0, 'reported' => 0, 'flagged' => 0];
$mobileQuery = '';
$aiModerationEnabled = false;
$aiModerationConfigured = false;
$mobilePostRow = [
    'id' => 7,
    'title' => 'Approved post',
    'category' => 'College',
    'username' => 'student',
    'moderation_status' => 'approved',
    'report_count' => 0,
    'ai_flagged' => false,
    'ai_categories' => '',
];
$mobilePosts = [$mobilePostRow];

ob_start();
require dirname(__DIR__) . '/app/Views/pages/mobile-list.php';
$approvedList = ob_get_clean();
expectMobileApproval(!str_contains($approvedList, '>Approve</button>'), 'Approved posts should not show the Approve button in the list.');
expectMobileApproval(str_contains($approvedList, '>Remove</button>'), 'Approved posts should retain the Remove button in the list.');

$mobilePosts[0]['moderation_status'] = 'pending';
ob_start();
require dirname(__DIR__) . '/app/Views/pages/mobile-list.php';
$pendingList = ob_get_clean();
expectMobileApproval(str_contains($pendingList, '>Approve</button>'), 'Pending posts should retain the Approve button in the list.');

$mobilePost = [
    'id' => 7,
    'title' => 'Approved post',
    'category' => 'College',
    'username' => 'student',
    'moderation_status' => 'approved',
    'report_count' => 0,
    'report_reason' => null,
    'ai_flagged' => false,
    'ai_categories' => '',
    'is_archived' => false,
    'content' => 'Post body',
    'comments' => [],
];
ob_start();
require dirname(__DIR__) . '/app/Views/pages/mobile-post.php';
$approvedDetail = ob_get_clean();
expectMobileApproval(!str_contains($approvedDetail, '>Approve</button>'), 'Approved posts should not show the Approve button in the detail view.');
expectMobileApproval(str_contains($approvedDetail, '>Remove</button>'), 'Approved posts should retain the Remove button in the detail view.');

$mobilePost['moderation_status'] = 'pending';
ob_start();
require dirname(__DIR__) . '/app/Views/pages/mobile-post.php';
$pendingDetail = ob_get_clean();
expectMobileApproval(str_contains($pendingDetail, '>Approve</button>'), 'Pending posts should retain the Approve button in the detail view.');

echo "Mobile approval action tests passed.\n";
