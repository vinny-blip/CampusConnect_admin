<?php
$activity = $mobileUserActivity ?? null;
$statusLabel = $activity ? (string)$activity['account_status'] : 'active';
?>
<p class="ua-back"><a href="users.php">&larr; Back to Users</a></p>
<?php if (!$mobileAvailable || $activity === null): ?>
  <p class="empty">User not found or the mobile schema is unavailable.</p>
<?php else: ?>
  <section class="ua-profile">
    <?php if (!empty($activity['profile_pic_url'])): ?>
      <img class="ua-avatar" src="<?= h($activity['profile_pic_url']) ?>" alt="">
    <?php else: ?>
      <span class="ua-avatar ua-avatar-initial"><?= h(mb_strtoupper(mb_substr((string)$activity['full_name'], 0, 1))) ?></span>
    <?php endif; ?>
    <div class="ua-identity">
      <h2><?= h($activity['full_name']) ?><span class="ua-status ua-status-<?= h($statusLabel) ?>"><?= h(ucfirst($statusLabel)) ?></span></h2>
      <p>@<?= h($activity['username']) ?> · <?= h($activity['institutional_email']) ?></p>
    </div>
    <div class="ua-counts">
      <span><b><?= count($activity['posts']) ?></b>Posts</span>
      <span><b><?= count($activity['comments']) ?></b>Comments</span>
    </div>
  </section>

  <h3 class="ua-heading">Posts</h3>
  <?php foreach ($activity['posts'] as $post): ?>
    <article class="ua-card">
      <div class="ua-card-head">
        <a class="ua-title" href="mobile-post.php?id=<?= (int)$post['id'] ?>"><?= h($post['title']) ?></a>
        <?php if (!empty($post['ai_flagged'])): ?><span class="ai-flag-badge">AI FLAGGED</span><?php endif; ?>
        <span class="ua-pill ua-pill-<?= h($post['moderation_status']) ?>"><?= h(ucfirst($post['moderation_status'])) ?></span>
      </div>
      <p class="ua-body"><?= nl2br(h($post['content'])) ?></p>
      <div class="ua-meta"><span><?= h($post['category']) ?></span><span><?= h($post['created_at']) ?></span></div>
    </article>
  <?php endforeach; ?>
  <?php if (!$activity['posts']): ?><p class="empty">This user has not posted anything.</p><?php endif; ?>

  <h3 class="ua-heading">Comments</h3>
  <?php foreach ($activity['comments'] as $comment): ?>
    <article class="ua-card">
      <div class="ua-card-head">
        <span class="ua-on">Commented on</span>
        <a class="ua-title" href="mobile-post.php?id=<?= (int)$comment['post_id'] ?>"><?= h($comment['title']) ?></a>
        <?php if (!empty($comment['ai_flagged'])): ?><span class="ai-flag-badge">AI FLAGGED</span><?php endif; ?>
      </div>
      <p class="ua-body ua-bubble"><?= nl2br(h($comment['content'])) ?></p>
      <div class="ua-meta"><span><?= h($comment['created_at']) ?></span></div>
    </article>
  <?php endforeach; ?>
  <?php if (!$activity['comments']): ?><p class="empty">This user has not commented on anything.</p><?php endif; ?>
<?php endif; ?>
