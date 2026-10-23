<h2>Mobile post</h2>
<?php if (!$mobileAvailable || $mobilePost === null): ?>
  <p class="empty">Mobile post not found or the mobile schema is unavailable.</p>
<?php else: ?>
  <article class="post-card">
    <h3><?= h($mobilePost['title']) ?></h3>
    <?php if (!empty($mobilePost['ai_flagged'])): ?><div class="ai-alert" role="alert"><strong>AI FLAGGED</strong><span>This post was flagged for possible policy violations. Human review is required.</span><?php if (!empty($mobilePost['ai_categories'])): ?><small>Detected: <?= h($mobilePost['ai_categories']) ?></small><?php endif; ?></div><?php elseif (!empty($mobilePost['ai_categories'])): ?><div class="ai-alert ai-alert-review" role="status"><strong>AI REVIEW REQUIRED</strong><span>Automated moderation could not clear this post.</span><small>Reason: <?= h($mobilePost['ai_categories']) ?></small></div><?php endif; ?>
    <p class="sub"><?= h($mobilePost['category']) ?> · <?= h($mobilePost['username']) ?> · <?= h($mobilePost['moderation_status']) ?></p>
    <p><?= nl2br(h($mobilePost['content'])) ?></p>
    <?php if ((int)$mobilePost['report_count']): ?><p class="sub">Reports: <?= (int)$mobilePost['report_count'] ?><?= $mobilePost['report_reason'] ? ' · ' . h($mobilePost['report_reason']) : '' ?></p><?php endif; ?>
    <form method="post" onsubmit="return confirm('Remove this post from the mobile app?')"><?= $csrf ?><input type="hidden" name="post_id" value="<?= (int)$mobilePost['id'] ?>"><button name="action" value="mobile-approve">Approve</button> <button name="action" value="mobile-remove">Remove</button> <button name="action" value="mobile-pending">Reset to pending</button></form>
  </article>
  <h3>Comments (<?= count($mobilePost['comments']) ?>)</h3>
  <?php foreach ($mobilePost['comments'] as $comment): ?>
    <div class="row-item"><span><b><?= h($comment['username']) ?></b> · <?= h($comment['moderation_status']) ?><br><?= h($comment['content']) ?></span><form method="post"><?= $csrf ?><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button name="action" value="mobile-<?= $comment['moderation_status'] === 'hidden' ? 'restore-comment' : 'hide-comment' ?>"><?= $comment['moderation_status'] === 'hidden' ? 'Restore' : 'Hide' ?></button></form></div>
  <?php endforeach; ?>
<?php endif; ?>
