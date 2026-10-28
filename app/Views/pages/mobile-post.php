<h2 class="profile-title">Mobile post</h2>
<?php if (!$mobileAvailable || $mobilePost === null): ?>
  <p class="empty">Mobile post not found or the mobile schema is unavailable.</p>
<?php else: ?>
  <article class="post-card">
    <?php
      $mobilePostAiCategories = array_map('trim', explode(',', (string)($mobilePost['ai_categories'] ?? '')));
      $mobilePostAiCategories = array_filter(
          $mobilePostAiCategories,
          static fn(string $category): bool => $category !== '' && strtolower($category) !== 'moderation_unavailable'
      );
      $mobilePostAiCategoriesText = implode(', ', $mobilePostAiCategories);
    ?>
    <?php if (!empty($mobilePost['ai_flagged'])): ?><div class="ai-alert" role="alert"><strong>AI FLAGGED</strong><span>This post was flagged for possible policy violations. Human review is required.</span><?php if ($mobilePostAiCategoriesText !== ''): ?><small>Detected: <?= h($mobilePostAiCategoriesText) ?></small><?php endif; ?></div><?php elseif ($mobilePostAiCategoriesText !== ''): ?><div class="ai-alert ai-alert-review" role="status"><strong>AI REVIEW REQUIRED</strong><span>Automated moderation could not clear this post.</span><small>Reason: <?= h($mobilePostAiCategoriesText) ?></small></div><?php endif; ?>
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
    <div class="row-item"><span><b><?= h($comment['username']) ?></b><?php if (!empty($comment['ai_flagged'])): ?> <span class="ai-flag-badge" title="This comment was flagged by AI moderation">AI FLAGGED</span><?php endif; ?><br><?= nl2br(h($comment['content'])) ?><?php if ((int)$comment['report_count'] > 0): ?><br><span class="flag"><?= (int)$comment['report_count'] ?> report<?= (int)$comment['report_count'] === 1 ? '' : 's' ?><?= !empty($comment['report_reasons']) ? ': ' . h($comment['report_reasons']) : '' ?></span><?php endif; ?></span><form class="row-actions" method="post"><?= $csrf ?><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="btn-action remove" name="action" value="mobile-delete-comment" onclick="return confirm('Remove this comment from the mobile app?')">Remove</button></form></div>
  <?php endforeach; ?>
<?php endif; ?>
