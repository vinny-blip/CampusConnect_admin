<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';

function expectAdminView(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$csrf = '';
$moderators = [[
    'id' => 42, 'name' => 'Test Moderator', 'email' => 'test@example.edu',
    'profile_image' => null, 'suspended' => 0, 'suspended_until' => null,
    'created_at' => '2026-01-01 10:00:00', 'last_activity_at' => '2026-01-02 10:00:00',
]];
ob_start();
require dirname(__DIR__) . '/app/Views/pages/moderator-accounts.php';
$accounts = ob_get_clean();
expectAdminView(str_contains($accounts, 'data-moderator-search'), 'Moderator search is missing.');
expectAdminView(str_contains($accounts, 'Last activity: 2026-01-02 10:00:00'), 'Last activity is missing.');
expectAdminView(substr_count($accounts, 'name="reason"') === 2, 'Suspension and removal must both request a reason.');

$moderationActivity = [[
    'actor_name' => '<Moderator>', 'actor_email' => 'test@example.edu', 'actor_role' => 'moderator',
    'action' => 'mobile-remove', 'target_type' => 'post', 'target_id' => 4,
    'target_label' => null, 'reason' => null, 'created_at' => '2026-01-01 10:00:00',
]];
ob_start();
require dirname(__DIR__) . '/app/Views/pages/moderator-activity.php';
$activity = ob_get_clean();
expectAdminView(str_contains($activity, '&lt;Moderator&gt;'), 'Activity actor names must be escaped.');
expectAdminView(str_contains($activity, 'Post #4'), 'Activity must identify the target.');

$reportOverview = ['post_reports' => 2, 'comment_reports' => 3, 'appeals' => 4, 'pending_posts' => 5];
ob_start();
require dirname(__DIR__) . '/app/Views/pages/reports-overview.php';
$overview = ob_get_clean();
expectAdminView(str_contains($overview, 'Pending appeals</span><strong>4</strong>'), 'The pending appeal count is missing.');
expectAdminView(
    str_contains($overview, 'login-log-stats reports-overview-stats') && substr_count($overview, 'class="stat-card report-stat-card"') === 4,
    'The report counts should be displayed as four styled summary cards.'
);

echo "Admin overview view tests passed.\n";
