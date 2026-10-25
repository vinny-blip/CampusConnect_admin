<?php
$isComments = in_array($page, ['comments', 'mobile-comments'], true);
$isReports = in_array($page, ['reported', 'mobile-reports'], true);
$isUsers = $page === 'users';
$isRemoved = $page === 'removed';
$isMobileStandalone = str_starts_with($page, 'mobile-');
$heading = $isUsers ? 'Users' : ($isRemoved ? 'Removed Posts' : ($isComments ? 'Comments' : ($isReports ? 'Reported' : ($page === 'home' ? 'Home' : 'Posts'))));
?>
<?php if ($page === 'control'): ?><div class="moderation-page-header"><?php endif; ?>
<div class="page-heading">
  <h2><?= h($heading) ?></h2>
  <p class="sub"><?= $isMobileStandalone ? 'Shared Hostinger data from the CampusConnect mobile application.' : 'Content from the CampusConnect mobile application.' ?></p>
</div>
<?php if ($page === 'control'): ?>
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
  <div class="stats">
    <a class="stat" href="control.php?status=pending"><b><?= (int)$mobileCounts['pending'] ?></b>Pending</a>
    <a class="stat" href="reported.php"><b><?= (int)$mobileCounts['reported'] ?></b>Reported</a>
    <a class="stat" href="control.php?status=pending"><b><?= (int)$mobileCounts['flagged'] ?></b>Flagged</a>
  </div>
  <?php if ($isMobileStandalone): ?><nav class="tabs">
    <a class="<?= $page === 'mobile-feed' ? 'on' : '' ?>" href="mobile-feed.php">Feed</a>
    <a class="<?= $page === 'mobile-queues' ? 'on' : '' ?>" href="mobile-queues.php">Moderation queue</a>
    <a class="<?= $page === 'mobile-reports' ? 'on' : '' ?>" href="mobile-reports.php">Reports</a>
    <a class="<?= $page === 'mobile-comments' ? 'on' : '' ?>" href="mobile-comments.php">Comments</a>
  </nav><?php endif; ?>
  <form class="searchform" action="<?= h($page) ?>.php" method="get">
    <input class="search" name="q" value="<?= h($mobileQuery) ?>" placeholder="Search content">
  </form>
  <?php if ($isReports): ?>
    <?php if ($isMobileStandalone): ?><nav class="tabs">
      <?php foreach (['pending' => 'Pending', 'reviewed' => 'Reviewed', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $reportStatus => $reportLabel): ?>
        <a class="<?= $mobileReportStatus === $reportStatus ? 'on' : '' ?>" href="mobile-reports.php?status=<?= h($reportStatus) ?>"><?= h($reportLabel) ?></a>
      <?php endforeach; ?>
    </nav><?php endif; ?>
    <?php foreach ($mobileReports as $report): ?>
      <article class="row-item">
        <span>
          <b><a href="mobile-post.php?id=<?= (int)$report['post_id'] ?>"><?= h($report['title']) ?></a></b>
          <br><small>Reported by <?= h($report['reporter_name'] ?: $report['reporter_username']) ?> · <?= h($report['created_at']) ?> · <?= h($report['status']) ?></small>
          <?php if ($report['reason'] !== null && $report['reason'] !== ''): ?><br><b>Reason:</b> <?= h($report['reason']) ?><?php endif; ?>
          <?php if ($report['additional_comments'] !== null && $report['additional_comments'] !== ''): ?><br><?= nl2br(h($report['additional_comments'])) ?><?php endif; ?>
        </span>
        <?php if (strtolower((string)$report['status']) === 'pending'): ?>
          <form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>"><button class="btn-action secondary" name="action" value="mobile-dismiss-report">Dismiss</button></form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  <?php elseif ($isRemoved): ?>
    <?php foreach ($mobileRemovedPosts as $item): ?>
      <div class="row-item mobile-post-row">
        <span class="post-main-info">
          <b><?= h($item['title']) ?></b><?php if (!empty($item['ai_flagged'])): ?><span class="ai-flag-badge" title="This post was flagged by AI moderation">AI FLAGGED</span><?php endif; ?><?php if (!empty($item['ai_categories'])): ?><br><span class="flag">AI detected: <?= h($item['ai_categories']) ?></span><?php endif; ?>
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
      </div>
    <?php endforeach; ?>
    <?php if (!$mobileRemovedPosts): ?><p class="empty">No removed posts yet. Posts removed from Control Posts/Feed will appear here.</p><?php endif; ?>
  <?php elseif ($isUsers): ?>
    <?php foreach ($mobileUsers as $item): ?>
      <div class="row-item user-list-row">
        <div class="user-item-info">
          <?php if (!empty($item['profile_pic_url'])): ?>
            <img class="registered-user-avatar" src="<?= h($item['profile_pic_url']) ?>" alt="">
          <?php else: ?>
            <span class="registered-user-avatar registered-user-initial"><?= h(mb_strtoupper(mb_substr((string)$item['full_name'], 0, 1))) ?></span>
          <?php endif; ?>
          <span class="user-list-identity"><b><?= h($item['full_name']) ?></b> (@<?= h($item['username']) ?>)<small><?= h($item['institutional_email']) ?><?= $item['moderation_status'] === 'suspended' ? ' · suspended' : '' ?></small></span>
        </div>
        <dl class="post-meta-table user-stats-table" aria-label="User activity">
          <div class="post-meta-row">
            <dt>Posts</dt>
            <dd><?= (int)$item['posts_count'] ?></dd>
          </div>
          <div class="post-meta-row">
            <dt>Comments</dt>
            <dd><?= (int)$item['comments_count'] ?></dd>
          </div>
        </dl>
        <form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="user_id" value="<?= (int)$item['id'] ?>"><button class="btn-action <?= $item['moderation_status'] === 'suspended' ? 'approve' : 'remove' ?>" name="action" value="mobile-<?= $item['moderation_status'] === 'suspended' ? 'restore-user' : 'suspend-user' ?>"><?= $item['moderation_status'] === 'suspended' ? 'Restore' : 'Suspend' ?></button></form>
      </div>
    <?php endforeach; ?>
  <?php elseif ($isComments): ?>
    <?php foreach ($mobileComments as $item): ?>
      <div class="row-item"><span><b><?= h($item['username']) ?></b> on <a href="mobile-post.php?id=<?= (int)$item['post_id'] ?>"><?= h($item['title']) ?></a><br><?= h($item['content']) ?><?= !empty($item['ai_flagged']) ? '<br><span class="flag">AI flagged</span>' : (!empty($item['ai_categories']) ? '<br><span class="flag">AI review required</span>' : '') ?></span><form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="comment_id" value="<?= (int)$item['id'] ?>"><button class="btn-action remove" name="action" value="mobile-delete-comment" onclick="return confirm('Delete this comment from the mobile app?')">Delete</button></form></div>
    <?php endforeach; ?>
  <?php else: ?>
    <?php foreach ($mobilePosts as $item): ?>
      <?php $aiFlagged = !empty($item['ai_flagged']); $aiReviewRequired = $aiFlagged || !empty($item['ai_categories']); ?>
      <div class="row-item mobile-post-row">
        <span class="post-main-info">
          <b><a href="mobile-post.php?id=<?= (int)$item['id'] ?>"><?= h($item['title']) ?></a></b><?php if ($aiFlagged): ?><span class="ai-flag-badge" title="This post was flagged by AI moderation">AI FLAGGED</span><?php elseif (!empty($item['ai_categories'])): ?><span class="ai-review-badge" title="This post requires moderation review">AI REVIEW</span><?php elseif ($item['moderation_status'] === 'pending'): ?><span class="pending-badge" title="This post is waiting for moderator review">PENDING REVIEW</span><?php endif; ?><?php if (!empty($item['ai_categories'])): ?><br><span class="flag">AI detected: <?= h($item['ai_categories']) ?></span><?php endif; ?>
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
<?php endif; ?>
