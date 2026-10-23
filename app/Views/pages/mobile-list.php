<?php
$isComments = in_array($page, ['comments', 'mobile-comments'], true);
$isReports = in_array($page, ['reported', 'mobile-reports'], true);
$isUsers = in_array($page, ['mobile-users'], true);
$isMobileStandalone = str_starts_with($page, 'mobile-');
$heading = $isComments ? 'Comments' : ($isReports ? 'Reported' : ($page === 'queues' ? 'Queues' : ($page === 'home' ? 'Home' : 'Posts')));
?>
<h2><?= h($heading) ?></h2>
<p class="sub"><?= $isMobileStandalone ? 'Shared Hostinger data from the CampusConnect mobile application.' : 'Content from the CampusConnect mobile application.' ?></p>
<?php if ($page === 'queues'): ?>
  <form class="ai-moderation-form queue-header-toggle" method="post">
    <?= $csrf ?>
    <input type="hidden" name="action" value="toggle-ai-moderation">
    <input type="hidden" name="enabled" value="<?= $aiModerationEnabled ? '0' : '1' ?>">
    <button class="ai-moderation-toggle<?= $aiModerationEnabled ? ' enabled' : '' ?>" type="submit" aria-pressed="<?= $aiModerationEnabled ? 'true' : 'false' ?>">
      <span><?= $aiModerationEnabled ? 'AI Moderation is on' : 'Turn on AI Moderation' ?></span>
      <span class="ai-switch" aria-hidden="true"><span></span></span>
    </button>
  </form>
  <p class="sub ai-moderation-note">When enabled, AI-flagged or unchecked content is held for admin review. The AI never removes content.</p>
  <?php if ($aiModerationEnabled && !$aiModerationConfigured): ?><p class="notice error" role="status">OPENAI_API_KEY is not configured. New content will be held for admin review until the API is available.</p><?php endif; ?>
<?php endif; ?>
<?php if (!$mobileAvailable): ?>
  <p class="empty">The mobile schema is not available in this database. Import the mobile schema, then reload.</p>
<?php else: ?>
  <div class="stats">
    <a class="stat" href="posts.php"><b><?= (int)$mobileCounts['posts'] ?></b>Posts</a>
    <a class="stat" href="queues.php"><b><?= (int)$mobileCounts['pending'] ?></b>Pending</a>
    <a class="stat" href="reported.php"><b><?= (int)$mobileCounts['reported'] ?></b>Reports</a>
    <a class="stat" href="comments.php"><b><?= (int)$mobileCounts['comments'] ?></b>Comments</a>
  </div>
  <?php if ($isMobileStandalone): ?><nav class="tabs">
    <a class="<?= $page === 'mobile-feed' ? 'on' : '' ?>" href="mobile-feed.php">Feed</a>
    <a class="<?= $page === 'mobile-queues' ? 'on' : '' ?>" href="mobile-queues.php">Moderation queue</a>
    <a class="<?= $page === 'mobile-reports' ? 'on' : '' ?>" href="mobile-reports.php">Reports</a>
    <a class="<?= $page === 'mobile-comments' ? 'on' : '' ?>" href="mobile-comments.php">Comments</a>
    <a class="<?= $page === 'mobile-users' ? 'on' : '' ?>" href="mobile-users.php">Users</a>
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
          <form method="post"><?= $csrf ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>"><button name="action" value="mobile-dismiss-report">Dismiss</button></form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  <?php elseif ($isUsers): ?>
    <?php foreach ($mobileUsers as $item): ?>
      <div class="row-item"><span><b><?= h($item['full_name']) ?></b> @<?= h($item['username']) ?><br><small><?= h($item['institutional_email']) ?> · <?= (int)$item['posts_count'] ?> posts · <?= (int)$item['comments_count'] ?> comments · <?= h($item['moderation_status']) ?></small></span><form method="post"><?= $csrf ?><input type="hidden" name="user_id" value="<?= (int)$item['id'] ?>"><button name="action" value="mobile-<?= $item['moderation_status'] === 'suspended' ? 'restore-user' : 'suspend-user' ?>"><?= $item['moderation_status'] === 'suspended' ? 'Restore' : 'Suspend' ?></button></form></div>
    <?php endforeach; ?>
  <?php elseif ($isComments): ?>
    <?php foreach ($mobileComments as $item): ?>
      <div class="row-item"><span><b><?= h($item['username']) ?></b> on <a href="mobile-post.php?id=<?= (int)$item['post_id'] ?>"><?= h($item['title']) ?></a><br><?= h($item['content']) ?><?= !empty($item['ai_flagged']) ? '<br><span class="flag">AI flagged</span>' : (!empty($item['ai_categories']) ? '<br><span class="flag">AI review required</span>' : '') ?></span><form method="post"><?= $csrf ?><input type="hidden" name="comment_id" value="<?= (int)$item['id'] ?>"><button name="action" value="mobile-<?= $item['moderation_status'] === 'hidden' ? 'restore-comment' : 'hide-comment' ?>"><?= $item['moderation_status'] === 'hidden' ? 'Restore' : 'Hide' ?></button></form></div>
    <?php endforeach; ?>
  <?php else: ?>
    <?php foreach ($mobilePosts as $item): ?>
      <?php $aiFlagged = !empty($item['ai_flagged']); $aiReviewRequired = $aiFlagged || !empty($item['ai_categories']); ?>
      <div class="row-item mobile-post-row"><span><b><a href="mobile-post.php?id=<?= (int)$item['id'] ?>"><?= h($item['title']) ?></a></b><?php if ($aiFlagged): ?><span class="ai-flag-badge" title="This post was flagged by AI moderation">AI FLAGGED</span><?php elseif (!empty($item['ai_categories'])): ?><span class="ai-review-badge" title="This post requires moderation review">AI REVIEW</span><?php elseif ($item['moderation_status'] === 'pending'): ?><span class="pending-badge" title="This post is waiting for moderator review">PENDING REVIEW</span><?php endif; ?><br><small><?= h($item['category']) ?> · <?= h($item['username']) ?> · <?= h($item['moderation_status']) ?><?= (int)$item['report_count'] ? ' · reported' : '' ?><?= $aiReviewRequired ? ' · moderation review required' : '' ?></small><?php if (!empty($item['ai_categories'])): ?><br><span class="flag">AI detected: <?= h($item['ai_categories']) ?></span><?php endif; ?></span><form method="post" onsubmit="return confirm('Remove this post from the mobile app?')"><?= $csrf ?><input type="hidden" name="post_id" value="<?= (int)$item['id'] ?>"><button name="action" value="mobile-approve">Approve</button> <button name="action" value="mobile-remove">Remove</button></form></div>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if ($page === 'home'): ?>
    <?php $reportChartCompact = false; require dirname(__DIR__) . '/partials/report-chart.php'; ?>
  <?php endif; ?>
<?php endif; ?>
