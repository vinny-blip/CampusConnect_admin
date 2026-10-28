<?php
$isComments = in_array($page, ['comments', 'mobile-comments'], true);
$isReports = in_array($page, ['reported', 'dismissed', 'mobile-reports'], true);
$isDismissedPage = $page === 'dismissed';
$isUsers = $page === 'users';
$isRemoved = $page === 'removed';
$isMobileStandalone = str_starts_with($page, 'mobile-');
$isRemovedPostsView = ($isRemoved && $mobileRemovedType === 'posts')
  || ($isReports && $mobileReportView === 'removed-posts');
$isRemovedCommentsView = ($isRemoved && $mobileRemovedType === 'comments')
  || ($isReports && $mobileReportView === 'removed-comments');
$isDismissedCommentsView = $isReports && $mobileReportView === 'dismissed-comments';
$showDismissedCommentReports = $isDismissedPage || $isDismissedCommentsView;
$splitReportColumns = ($page === 'reported' && $mobileReportView === 'current') || $isDismissedPage;
$isReportArchiveView = !$isDismissedPage && $isReports && ($isRemovedPostsView || $isRemovedCommentsView || $isDismissedCommentsView);
$visibleAiCategories = static function ($categories): string {
  $categories = array_map('trim', explode(',', (string)$categories));
  $categories = array_filter(
    $categories,
    static fn(string $category): bool => $category !== '' && strtolower($category) !== 'moderation_unavailable'
  );
  return implode(', ', $categories);
};
$heading = $isUsers ? 'Users' : ($isReportArchiveView ? 'Report & Removal History' : ($isRemoved ? 'Removed' : ($isDismissedPage ? 'Dismissed' : ($isComments ? 'Comments' : ($isReports ? 'Reported' : ($page === 'home' ? 'Overview' : 'Posts'))))));
?>
<?php $showAiToggle = in_array($page, ['control', 'posts', 'comments'], true); ?>
<?php if ($showAiToggle): ?><div class="moderation-page-header"><?php endif; ?>
<div class="page-heading">
  <h2><?= h($heading) ?></h2>
  <?php if ($page !== 'home'): ?>
    <p class="sub"><?= $isMobileStandalone ? 'Shared Hostinger data from the CampusConnect mobile application.' : 'Content from the CampusConnect mobile application.' ?></p>
  <?php endif; ?>
</div>
<?php if ($showAiToggle): ?>
  <form class="ai-moderation-form moderation-ai-toggle-form" method="post">
    <?= $csrf ?>
    <input type="hidden" name="action" value="toggle-ai-moderation">
    <input type="hidden" name="enabled" value="<?= $aiModerationEnabled ? '0' : '1' ?>">
    <button class="ai-moderation-toggle<?= $aiModerationEnabled ? ' enabled' : '' ?>" type="submit" aria-pressed="<?= $aiModerationEnabled ? 'true' : 'false' ?>">
      <span><?= $aiModerationEnabled ? 'AI Moderation is on' : 'Turn on AI Moderation' ?></span>
      <span class="ai-switch" aria-hidden="true"><span></span></span>
    </button>
  </form>
  </div>
  <p class="sub ai-moderation-note">When enabled, AI-flagged or unchecked content is held for admin review. The AI never removes content.</p>
  <?php if ($aiModerationEnabled && !$aiModerationConfigured): ?><p class="notice error" role="status">OPENAI_API_KEY is not configured. New content will be held for admin review until the API is available.</p><?php endif; ?>
<?php endif; ?>
<?php if (!$mobileAvailable): ?>
  <p class="empty">The mobile schema is not available in this database. Import the mobile schema, then reload.</p>
<?php else: ?>
  <?php if ($isUsers): ?>
  <span hidden data-users-page></span>
  <div class="stats">
    <a class="stat" href="users.php"><b><?= (int)$mobileUserCounts['total'] ?></b>Total accounts</a>
    <a class="stat" href="users.php?status=suspended"><b><?= (int)$mobileUserCounts['suspended'] ?></b>Suspended</a>
    <a class="stat" href="users.php?status=banned"><b><?= (int)$mobileUserCounts['banned'] ?></b>Banned</a>
  </div>
  <?php elseif ($page === 'comments'): ?>
  <div class="stats">
    <span class="stat"><b><?= (int)$mobileCommentCounts['total'] ?></b>Total comments</span>
    <span class="stat"><b><?= (int)$mobileCommentCounts['reported'] ?></b>Reported</span>
    <span class="stat"><b><?= (int)$mobileCommentCounts['flagged'] ?></b>Flagged</span>
  </div>
  <?php elseif (!$isRemoved && !$isDismissedPage && !$isReportArchiveView): ?>
  <div class="stats">
    <a class="stat" href="control.php?status=pending"><b><?= (int)$mobileCounts['pending'] ?></b>Pending</a>
    <a class="stat" href="reported.php"><b><?= (int)$mobileCounts['reported'] ?></b>Reported</a>
    <a class="stat" href="control.php?status=pending"><b><?= (int)$mobileCounts['flagged'] ?></b>Flagged</a>
  </div>
  <?php endif; ?>
  <?php if ($isMobileStandalone): ?><nav class="tabs">
    <a class="<?= $page === 'mobile-feed' ? 'on' : '' ?>" href="mobile-feed.php">Feed</a>
    <a class="<?= $page === 'mobile-queues' ? 'on' : '' ?>" href="mobile-queues.php">Moderation queue</a>
    <a class="<?= $page === 'mobile-reports' ? 'on' : '' ?>" href="mobile-reports.php">Reports</a>
    <a class="<?= $page === 'mobile-comments' ? 'on' : '' ?>" href="mobile-comments.php">Comments</a>
  </nav><?php endif; ?>
  <form class="searchform" action="<?= h($page) ?>.php" method="get">
    <?php if ($isRemoved): ?>
      <select class="removed-type-select" name="type" aria-label="Choose removed content type" onchange="this.form.submit()">
        <option value="posts"<?= $mobileRemovedType === 'posts' ? ' selected' : '' ?>>Posts</option>
        <option value="comments"<?= $mobileRemovedType === 'comments' ? ' selected' : '' ?>>Comments</option>
      </select>
    <?php endif; ?>
    <?php if ($isUsers): ?>
      <select class="removed-type-select" name="status" aria-label="Filter users by account status" onchange="this.form.submit()">
        <option value="">All accounts</option>
        <option value="active"<?= $mobileUserFilter === 'active' ? ' selected' : '' ?>>Active</option>
        <option value="suspended"<?= $mobileUserFilter === 'suspended' ? ' selected' : '' ?>>Suspended</option>
        <option value="banned"<?= $mobileUserFilter === 'banned' ? ' selected' : '' ?>>Banned</option>
      </select>
    <?php endif; ?>
    <input class="search" name="q" value="<?= h($mobileQuery) ?>" placeholder="Search content">
  </form>
  <?php if ($isReports): ?>
    <?php if (!$isReportArchiveView): ?>
    <?php if ($splitReportColumns): ?>
    <div class="reported-columns">
      <section class="reported-column" aria-labelledby="reported-posts-heading">
    <?php endif; ?>
    <?php if ($page === 'reported' && $mobileReportView === 'current'): ?>
    <h3<?= $splitReportColumns ? ' id="reported-posts-heading"' : '' ?>>Reported Posts</h3>
    <?php elseif ($isDismissedPage): ?>
    <h3 id="reported-posts-heading">Dismissed post reports</h3>
    <?php endif; ?>
    <?php if ($splitReportColumns): ?><div class="reported-scroll" aria-label="Post reports"><?php endif; ?>
    <?php foreach ($mobileReports as $report): ?>
      <?php $reportStatus = strtolower((string)$report['status']); ?>
      <article class="report-card">
        <div class="report-card-main">
          <div class="report-card-heading">
            <a class="report-card-title" href="mobile-post.php?id=<?= (int)$report['post_id'] ?>"><?= h($report['title']) ?></a>
            <span class="report-status-badge <?= h($reportStatus) ?>"><?= h(ucfirst($reportStatus)) ?></span>
          </div>
          <div class="report-card-meta">
            <span>Reported by <strong><?= h($report['reporter_name'] ?: $report['reporter_username']) ?></strong></span>
            <time><?= h($report['created_at']) ?></time>
          </div>
          <?php if (($report['reason'] ?? '') !== '' || ($report['additional_comments'] ?? '') !== ''): ?>
            <div class="report-card-details">
              <?php if (($report['reason'] ?? '') !== ''): ?>
                <div class="report-card-detail">
                  <span class="report-card-label">Reason</span>
                  <p><?= h($report['reason']) ?></p>
                </div>
              <?php endif; ?>
              <?php if (($report['additional_comments'] ?? '') !== ''): ?>
                <div class="report-card-detail">
                  <span class="report-card-label">Additional details</span>
                  <p><?= nl2br(h($report['additional_comments'])) ?></p>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php if (in_array($reportStatus, ['pending', 'dismissed'], true)): ?>
          <form class="row-actions report-card-actions" method="post">
            <?= $csrf ?>
            <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
            <input type="hidden" name="post_id" value="<?= (int)$report['post_id'] ?>">
            <?php if ($reportStatus === 'pending'): ?>
              <button class="btn-action remove" name="action" value="mobile-remove" onclick="return confirm('Remove this post from the mobile app?')">Remove post</button>
              <button class="btn-action secondary" name="action" value="mobile-dismiss-report">Dismiss</button>
            <?php else: ?>
              <button class="btn-action approve" name="action" value="mobile-restore-report" onclick="return confirm('Restore this report to current reports?')">Restore</button>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    <?php if (!$mobileReports && $isDismissedPage): ?><p class="empty">No dismissed post reports.</p><?php elseif (!$mobileReports && $page === 'reported' && $mobileReportView === 'current'): ?><p class="empty">No reported posts.</p><?php elseif (!$mobileReports && !($mobileReportView === 'current' && $mobileCommentReports)): ?><p class="empty">No reports match this view.</p><?php endif; ?>
    <?php if ($splitReportColumns): ?></div><?php endif; ?>
    <?php if ($splitReportColumns): ?>
      </section>
      <section class="reported-column" aria-labelledby="reported-comments-heading">
    <?php endif; ?>
    <?php if ($mobileReportView === 'current'): ?>
    <?php if ($page === 'reported'): ?>
      <h3<?= $splitReportColumns ? ' id="reported-comments-heading"' : '' ?>>Reported Comments</h3>
    <?php else: ?>
      <h3>Reported comments</h3>
    <?php endif; ?>
    <?php if ($splitReportColumns && !$isDismissedPage): ?><div class="reported-scroll" aria-label="Comment reports"><?php endif; ?>
    <?php foreach ($mobileCommentReports as $report): ?>
      <article class="report-card">
        <div class="report-card-main">
          <div class="report-card-heading">
            <a class="report-card-title" href="mobile-post.php?id=<?= (int)$report['post_id'] ?>"><?= h($report['title']) ?></a>
            <span class="report-status-badge pending">Pending</span>
          </div>
          <div class="report-card-meta">
            <span>Reported by <strong><?= h($report['reporter_name'] ?: ($report['reporter_username'] ?: 'Unknown user')) ?></strong></span>
            <time><?= h($report['created_at']) ?></time>
          </div>
          <div class="report-card-details">
            <div class="report-card-detail">
              <span class="report-card-label">Comment by <?= h($report['author_name'] ?: $report['author_username']) ?></span>
              <p><?= nl2br(h($report['content'])) ?></p>
            </div>
            <?php if (($report['reason'] ?? '') !== ''): ?>
              <div class="report-card-detail">
                <span class="report-card-label">Reason</span>
                <p><?= h($report['reason']) ?></p>
              </div>
            <?php endif; ?>
            <?php if (($report['additional_comments'] ?? '') !== ''): ?>
              <div class="report-card-detail">
                <span class="report-card-label">Additional details</span>
                <p><?= nl2br(h($report['additional_comments'])) ?></p>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <form class="row-actions report-card-actions" method="post">
          <?= $csrf ?>
          <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
          <input type="hidden" name="comment_id" value="<?= (int)$report['comment_id'] ?>">
          <button class="btn-action remove" name="action" value="mobile-delete-comment" onclick="return confirm('Remove this comment from the mobile app?')">Remove Comment</button>
          <button class="btn-action secondary" name="action" value="mobile-dismiss-comment-report">Dismiss</button>
        </form>
      </article>
    <?php endforeach; ?>
    <?php if (!$mobileCommentReports): ?><p class="empty">No pending comment reports.</p><?php endif; ?>
    <?php if ($splitReportColumns && !$isDismissedPage): ?></div><?php endif; ?>
    <?php endif; ?>
    <?php if ($splitReportColumns && !$isDismissedPage): ?>
      </section>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($showDismissedCommentReports): ?>
    <h3<?= $isDismissedPage ? ' id="reported-comments-heading"' : '' ?>>Dismissed comment reports</h3>
    <?php if ($isDismissedPage): ?><div class="reported-scroll" aria-label="Dismissed comment reports"><?php endif; ?>
    <?php foreach ($mobileDismissedCommentReports as $report): ?>
      <article class="report-card">
        <div class="report-card-main">
          <div class="report-card-heading">
            <a class="report-card-title" href="mobile-post.php?id=<?= (int)$report['post_id'] ?>"><?= h($report['title']) ?></a>
            <span class="report-status-badge dismissed">Dismissed</span>
          </div>
          <div class="report-card-meta">
            <span>Reported by <strong><?= h($report['reporter_name'] ?: ($report['reporter_username'] ?: 'Unknown user')) ?></strong></span>
            <time><?= h($report['created_at']) ?></time>
          </div>
          <div class="report-card-details">
            <div class="report-card-detail">
              <span class="report-card-label">Comment by <?= h($report['author_name'] ?: $report['author_username']) ?></span>
              <p><?= nl2br(h($report['content'])) ?></p>
            </div>
            <?php if (($report['reason'] ?? '') !== ''): ?>
              <div class="report-card-detail">
                <span class="report-card-label">Reason</span>
                <p><?= h($report['reason']) ?></p>
              </div>
            <?php endif; ?>
            <?php if (($report['additional_comments'] ?? '') !== ''): ?>
              <div class="report-card-detail">
                <span class="report-card-label">Additional details</span>
                <p><?= nl2br(h($report['additional_comments'])) ?></p>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <form class="row-actions report-card-actions" method="post">
          <?= $csrf ?>
          <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
          <button class="btn-action approve" name="action" value="mobile-restore-comment-report">Restore report</button>
        </form>
      </article>
    <?php endforeach; ?>
    <?php if (!$mobileDismissedCommentReports): ?><p class="empty">No dismissed comment reports.</p><?php endif; ?>
    <?php if ($isDismissedPage): ?>
      </div>
      </section>
    </div>
    <?php endif; ?>
  <?php elseif ($isRemovedCommentsView): ?>
    <?php foreach ($mobileRemovedComments as $item): ?>
      <article class="comment-row">
        <span class="comment-avatar" aria-hidden="true"><?= h(mb_strtoupper(mb_substr((string)($item['author_username'] ?: $item['author_name']), 0, 1))) ?></span>
        <div class="comment-body">
          <div class="comment-head">
            <b><?= h($item['author_username'] ?: $item['author_name']) ?></b>
            <span>commented on</span>
            <b><?= h($item['post_title'] ?: 'a deleted post') ?></b>
            <?php if (!empty($item['ai_flagged'])): ?><span class="ai-flag-badge">AI FLAGGED</span><?php endif; ?>
          </div>
          <p class="comment-text"><?= nl2br(h($item['content'])) ?></p>
          <span class="flag">Removed <?= h($item['removed_at']) ?><?= !empty($item['removed_by_name']) ? ' by ' . h($item['removed_by_name']) : '' ?></span>
        </div>
        <form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="archive_id" value="<?= (int)$item['id'] ?>"><button class="btn-action approve" name="action" value="mobile-restore-comment-archive" onclick="return confirm('Restore this comment to the mobile app?')">Restore</button></form>
      </article>
    <?php endforeach; ?>
    <?php if (!$mobileRemovedComments): ?><p class="empty">No removed comments are available to restore.</p><?php endif; ?>
  <?php elseif ($isRemovedPostsView): ?>
    <?php foreach ($mobileRemovedPosts as $item): ?>
      <?php $removedPostAiCategories = $visibleAiCategories($item['ai_categories'] ?? ''); ?>
      <div class="row-item mobile-post-row">
        <span class="post-main-info">
          <b><a href="mobile-post.php?id=<?= (int)$item['mobile_post_id'] ?>&archive=1"><?= h($item['title']) ?></a></b><?php if (!empty($item['ai_flagged'])): ?><span class="ai-flag-badge" title="This post was flagged by AI moderation">AI FLAGGED</span><?php endif; ?><?php if ($removedPostAiCategories !== ''): ?><br><span class="flag">AI detected: <?= h($removedPostAiCategories) ?></span><?php endif; ?>
        </span>
        <dl class="post-meta-table">
          <div class="post-meta-row">
            <dt>College</dt>
            <dd><?= h($item['category']) ?></dd>
          </div>
          <div class="post-meta-row">
            <dt>User</dt>
            <dd><?= h($item['author_username'] ?: $item['author_name']) ?></dd>
          </div>
          <div class="post-meta-row">
            <dt>Removed</dt>
            <dd><?= h($item['removed_at']) ?><?= !empty($item['removed_by_name']) ? ' by ' . h($item['removed_by_name']) : '' ?></dd>
          </div>
        </dl>
        <form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="archive_id" value="<?= (int)$item['id'] ?>"><button class="btn-action approve" name="action" value="mobile-restore-post" onclick="return confirm('Restore this post to the mobile app?')">Restore</button></form>
      </div>
    <?php endforeach; ?>
    <?php if (!$mobileRemovedPosts): ?><p class="empty">No removed posts are available to restore.</p><?php endif; ?>
  <?php elseif ($isUsers): ?>
    <?php foreach ($mobileUsers as $item): ?>
      <div class="row-item user-list-row" data-mobile-user-row="<?= (int)$item['id'] ?>">
        <div class="user-item-info">
          <?php if (!empty($item['profile_pic_url'])): ?>
            <img class="registered-user-avatar" src="<?= h($item['profile_pic_url']) ?>" alt="">
          <?php else: ?>
            <span class="registered-user-avatar registered-user-initial"><?= h(mb_strtoupper(mb_substr((string)$item['full_name'], 0, 1))) ?></span>
          <?php endif; ?>
          <span class="user-list-identity"><b><?= h($item['full_name']) ?></b> (@<?= h($item['username']) ?>)<small><?= h($item['institutional_email']) ?><span data-mobile-user-status><?= $item['moderation_status'] !== 'active' ? ' · ' . h($item['moderation_status']) : '' ?></span><?php if ($item['moderation_status'] === 'suspended' && !empty($item['suspended_until'])): ?> · Lifts <?php if ($item['suspension_timezone_offset'] !== null): ?><time data-suspension-lift data-utc="<?= h((new DateTimeImmutable((string)$item['suspended_until'], new DateTimeZone('UTC')))->format(DATE_ATOM)) ?>"><?= h($item['suspended_until']) ?> UTC</time><?php else: ?><?= h(date('M j, Y g:i A', strtotime((string)$item['suspended_until']))) ?><?php endif; ?><?php endif; ?></small></span>
        </div>
        <a class="user-stats-link" href="user.php?id=<?= (int)$item['id'] ?>" title="View all posts and comments by this user">
        <dl class="post-meta-table user-stats-table" aria-label="User activity">
          <div class="post-meta-row">
            <dt>Posts</dt>
            <dd><?= (int)$item['posts_count'] ?></dd>
          </div>
          <div class="post-meta-row">
            <dt>Comments</dt>
            <dd><?= (int)$item['comments_count'] ?></dd>
          </div>
          <div class="post-meta-row" title="AI-flagged posts and comments, plus removed posts">
            <dt>Violations</dt>
            <dd><?= (int)$item['violation_count'] ?></dd>
          </div>
        </dl>
        </a>
        <form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="user_id" value="<?= (int)$item['id'] ?>"><?php if ($item['moderation_status'] === 'active'): ?><button class="btn-action remove" type="button" data-open-suspension data-user-id="<?= (int)$item['id'] ?>">Suspend</button><button class="btn-action ban" name="action" value="mobile-ban-user" onclick="return confirm('Ban this user?')">Banned</button><?php else: ?><button class="btn-action approve" name="action" value="mobile-restore-user"><?= $item['moderation_status'] === 'banned' ? 'Unban' : 'Restore' ?></button><?php endif; ?></form>
      </div>
    <?php endforeach; ?>
    <?php if (!$mobileUsers): ?><p class="empty">No users match this filter.</p><?php endif; ?>
  <?php elseif ($isComments): ?>
    <?php foreach ($mobileComments as $item): ?>
      <?php
        $commentAiFlagged = !empty($item['ai_flagged']);
        $commentAiCategories = $visibleAiCategories($item['ai_categories'] ?? '');
        $commentAiUnavailable = str_contains(strtolower((string)($item['ai_categories'] ?? '')), 'moderation_unavailable');
      ?>
      <article class="comment-row">
        <span class="comment-avatar" aria-hidden="true"><?= h(mb_strtoupper(mb_substr((string)$item['username'], 0, 1))) ?></span>
        <div class="comment-body">
          <div class="comment-head">
            <b><?= h($item['username']) ?></b>
            <span>commented on</span>
            <a href="mobile-post.php?id=<?= (int)$item['post_id'] ?>"><?= h($item['title']) ?></a>
            <?php if ($commentAiFlagged): ?><span class="ai-flag-badge" title="This comment was flagged by AI moderation">AI FLAGGED</span><?php elseif ($commentAiCategories !== ''): ?><span class="ai-review-badge" title="This comment requires moderation review">AI REVIEW</span><?php elseif ($aiModerationEnabled || $commentAiUnavailable): ?><span class="pending-badge" title="This comment is waiting for moderator review">PENDING REVIEW</span><?php endif; ?>
            <?php if ((int)$item['report_count'] > 0): ?><span class="flag"><?= (int)$item['report_count'] ?> report<?= (int)$item['report_count'] === 1 ? '' : 's' ?></span><?php endif; ?>
          </div>
          <p class="comment-text"><?= nl2br(h($item['content'])) ?></p>
          <?php if ($commentAiCategories !== ''): ?><span class="flag">AI detected: <?= h($commentAiCategories) ?></span><?php endif; ?>
        </div>
        <form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="comment_id" value="<?= (int)$item['id'] ?>"><button class="btn-action remove" name="action" value="mobile-delete-comment" onclick="return confirm('Remove this comment from the mobile app?')">Remove</button></form>
      </article>
    <?php endforeach; ?>
    <?php if (!$mobileComments): ?><p class="empty">No comments to show.</p><?php endif; ?>
  <?php elseif (!$isReports && $page !== 'home'): ?>
    <?php foreach ($mobilePosts as $item): ?>
      <?php
        $aiFlagged = !empty($item['ai_flagged']);
        $aiCategories = $visibleAiCategories($item['ai_categories'] ?? '');
      ?>
      <div class="row-item mobile-post-row">
        <span class="post-main-info">
          <b><a href="mobile-post.php?id=<?= (int)$item['id'] ?>"><?= h($item['title']) ?></a></b><?php if ($aiFlagged): ?><span class="ai-flag-badge" title="This post was flagged by AI moderation">AI FLAGGED</span><?php elseif ($aiCategories !== ''): ?><span class="ai-review-badge" title="This post requires moderation review">AI REVIEW</span><?php elseif ($item['moderation_status'] === 'pending'): ?><span class="pending-badge" title="This post is waiting for moderator review">PENDING REVIEW</span><?php endif; ?><?php if ($aiCategories !== ''): ?><br><span class="flag">AI detected: <?= h($aiCategories) ?></span><?php endif; ?>
        </span>
        <dl class="post-meta-table">
          <div class="post-meta-row">
            <dt>College</dt>
            <dd><?= h($item['category']) ?></dd>
          </div>
          <div class="post-meta-row">
            <dt>User</dt>
            <dd><?= h($item['username']) ?></dd>
          </div>
          <div class="post-meta-row">
            <dt>Status</dt>
            <dd><?= h($item['moderation_status']) ?><?= (int)$item['report_count'] ? ' · <span class="flag">reported</span>' : '' ?></dd>
          </div>
        </dl>
        <form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="post_id" value="<?= (int)$item['id'] ?>"><button class="btn-action approve" name="action" value="mobile-approve">Approve</button> <button class="btn-action remove" name="action" value="mobile-remove" onclick="return confirm('Remove this post from the mobile app?')">Remove</button></form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if ($page === 'home'): ?>
    <div class="home-insights">
      <?php $reportChartCompact = false; require dirname(__DIR__) . '/partials/report-chart.php'; ?>
      <section class="registered-users-card" aria-labelledby="registered-users-title">
        <div>
          <h3 id="registered-users-title">Registered users</h3>
          <p>All users registered in the CampusConnect mobile app.</p>
        </div>
        <strong><?= (int)$mobileCounts['users'] ?></strong>
        <span>registered users</span>
        <?php if ($mobileUsers): ?>
          <ul class="registered-users-list">
            <?php foreach ($mobileUsers as $registeredUser): ?>
              <li>
                <?php if (!empty($registeredUser['profile_pic_url'])): ?>
                  <img class="registered-user-avatar" src="<?= h($registeredUser['profile_pic_url']) ?>" alt="">
                <?php else: ?>
                  <span class="registered-user-avatar registered-user-initial"><?= h(mb_strtoupper(mb_substr((string)$registeredUser['full_name'], 0, 1))) ?></span>
                <?php endif; ?>
                <span><b><?= h($registeredUser['full_name']) ?></b><small>(@<?= h($registeredUser['username']) ?>)</small></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="registered-users-empty">No registered users yet.</p>
        <?php endif; ?>
        <a class="button" href="users.php">View all users</a>
      </section>
    </div>
  <?php endif; ?>
  <?php if ($isUsers): ?>
    <dialog class="suspension-dialog" data-suspension-dialog aria-labelledby="suspension-dialog-title">
      <form method="post" class="suspension-dialog-form">
        <?= $csrf ?>
        <input type="hidden" name="action" value="mobile-suspend-user">
        <input type="hidden" name="user_id" value="" data-suspension-user-id>
        <input type="hidden" name="suspended_until" value="" data-suspension-value>
        <input type="hidden" name="suspension_timezone_offset" value="" data-suspension-timezone>
        <header class="suspension-dialog-heading">
          <span class="suspension-dialog-icon" aria-hidden="true">!</span>
          <div>
            <h3 id="suspension-dialog-title">Suspend user</h3>
            <p>Choose when this account can return.</p>
          </div>
        </header>
        <section class="suspension-calendar" aria-label="Choose suspension end date">
          <div class="suspension-calendar-heading">
            <button class="calendar-nav" type="button" aria-label="Previous month" data-calendar-previous>&lsaquo;</button>
            <strong data-calendar-month></strong>
            <button class="calendar-nav" type="button" aria-label="Next month" data-calendar-next>&rsaquo;</button>
          </div>
          <div class="suspension-weekdays" aria-hidden="true">
            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
          </div>
          <div class="suspension-calendar-days" role="group" aria-label="Calendar dates" data-calendar-days></div>
        </section>
        <section class="suspension-time" aria-label="Choose suspension end time">
          <label>Lift time</label>
          <div class="suspension-time-controls">
            <select aria-label="Hour" data-suspension-hour>
              <option value="">Hour</option>
              <?php for ($hour = 1; $hour <= 12; $hour++): ?>
                <option value="<?= $hour ?>"><?= sprintf('%02d', $hour) ?></option>
              <?php endfor; ?>
            </select>
            <span>:</span>
            <select aria-label="Minute" data-suspension-minute>
              <option value="">Min</option>
              <?php for ($minute = 0; $minute < 60; $minute++): ?>
                <option value="<?= $minute ?>"><?= sprintf('%02d', $minute) ?></option>
              <?php endfor; ?>
            </select>
            <select aria-label="AM or PM" data-suspension-period>
              <option value="">AM/PM</option>
              <option value="AM">AM</option>
              <option value="PM">PM</option>
            </select>
          </div>
        </section>
        <p class="suspension-selection" aria-live="polite" tabindex="-1" data-suspension-selection>Select a date and time.</p>
        <div class="suspension-dialog-actions">
          <button class="btn-action secondary" type="button" data-close-suspension>Cancel</button>
          <button class="btn-action remove" type="submit" data-suspension-submit disabled>Confirm suspension</button>
        </div>
      </form>
    </dialog>
  <?php endif; ?>
<?php endif; ?>
