<h2 class="profile-title">Mobile post</h2>
<?php if (!$mobileAvailable || $mobilePost === null): ?>
  <p class="empty">Mobile post not found or the mobile schema is unavailable.</p>
<?php else: ?>
  <article class="post-card">
    <?php if (!empty($mobilePost['ai_flagged'])): ?><div class="ai-alert" role="alert"><strong>AI FLAGGED</strong><span>This post was flagged for possible policy violations. Human review is required.</span><?php if (!empty($mobilePost['ai_categories'])): ?><small>Detected: <?= h($mobilePost['ai_categories']) ?></small><?php endif; ?></div><?php elseif (!empty($mobilePost['ai_categories'])): ?><div class="ai-alert ai-alert-review" role="status"><strong>AI REVIEW REQUIRED</strong><span>Automated moderation could not clear this post.</span><small>Reason: <?= h($mobilePost['ai_categories']) ?></small></div><?php endif; ?>
    <p class="sub"><?= h($mobilePost['category']) ?> · <?= h($mobilePost['username']) ?> · <?= h($mobilePost['moderation_status']) ?><?= !empty($mobilePost['is_archived']) ? ' archive' : '' ?></p>
    <h3>Title: <?= h($mobilePost['title']) ?></h3>
    <p><?= nl2br(h($mobilePost['content'])) ?></p>
    <?php if ((int)$mobilePost['report_count']): ?><p class="sub">Reports: <?= (int)$mobilePost['report_count'] ?><?= $mobilePost['report_reason'] ? ' · ' . h($mobilePost['report_reason']) : '' ?></p><?php endif; ?>
    <?php if (!empty($mobilePost['is_archived'])): ?>
      <form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="archive_id" value="<?= (int)$mobilePost['archive_id'] ?>"><button class="btn-action approve" name="action" value="mobile-restore-post" onclick="return confirm('Restore this post to the mobile app?')">Restore post</button></form>
    <?php else: ?>
      <form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="post_id" value="<?= (int)$mobilePost['id'] ?>"><button class="btn-action approve" name="action" value="mobile-approve">Approve</button> <button class="btn-action remove" name="action" value="mobile-remove" onclick="return confirm('Remove this post from the mobile app?')">Remove</button></form>
    <?php endif; ?>
  </article>
  <h3>Comments (<?= count($mobilePost['comments']) ?>)</h3>
  <?php foreach ($mobilePost['comments'] as $comment): ?>
    <div class="row-item"><span><b><?= h($comment['username']) ?></b><?php if (!empty($comment['ai_flagged'])): ?> <span class="ai-flag-badge" title="This comment was flagged by AI moderation">AI FLAGGED</span><?php endif; ?><br><?= h($comment['content']) ?></span><form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="btn-action remove" name="action" value="mobile-delete-comment" onclick="return confirm('Delete this comment from the mobile app?')">Delete</button></form></div>
  <?php endforeach; ?>
<?php endif; ?>
