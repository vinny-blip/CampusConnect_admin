<div class="queue-heading">
  <div class="queue-title">
    <div class="queue-title-row">
    <h2>Queues</h2>
    <form class="ai-moderation-form" method="post">
      <?= $csrf ?>
      <input type="hidden" name="action" value="toggle-ai-moderation">
      <input type="hidden" name="enabled" value="<?= $aiModerationEnabled ? '0' : '1' ?>">
      <button class="ai-moderation-toggle<?= $aiModerationEnabled ? ' enabled' : '' ?>" type="submit" aria-pressed="<?= $aiModerationEnabled ? 'true' : 'false' ?>">
        <span><?= $aiModerationEnabled ? 'AI Moderation is on' : 'Turn on AI Moderation' ?></span>
        <span class="ai-switch" aria-hidden="true"><span></span></span>
      </button>
    </form>
    </div>
    <p class="sub">Open each post and check it before you approve or remove it. Reported posts are kept in their own list.</p>
  </div>
</div>
<p class="sub ai-moderation-note">This toggle saves your preference only. Posts still require manual review.</p>
<nav class="filters" aria-label="Moderation queues">
  <a class="filter-link <?= $kind === 'approve' ? 'on' : '' ?>" href="queues.php">To approve (<?= (int)$counts['toApprove'] ?>)</a>
  <a class="filter-link <?= $kind === 'reported' ? 'on' : '' ?>" href="queues.php?tab=reported">Reported (<?= (int)$counts['reported'] ?>)</a>
  <form class="queue-search" method="get" action="queues.php">
    <?php if ($kind === 'reported'): ?><input type="hidden" name="tab" value="reported"><?php endif; ?>
    <input class="localsearch" type="search" name="q" placeholder="Filter this queue" value="<?= h($query) ?>" aria-label="Filter this queue">
    <button class="button" type="submit">Search</button>
  </form>
  <span class="count"><?= count($posts) ?> shown</span>
</nav>
<section class="queue" aria-live="polite">
<?php if ($posts): ?>
  <?php foreach ($posts as $post): ?>
    <?php $reviewUrl = 'post.php?id=' . (int)$post['id'] . '&amp;review=1'; ?>
    <article class="post">
      <div>
        <div class="meta">
          <span class="avatar sm"><?= h(mb_strtoupper(mb_substr($post['author'], 0, 1))) ?></span>
          <b><?= h($post['author']) ?></b>
          <span>• <a href="<?= h(community_url($post['community'])) ?>"><?= h($post['cname']) ?></a></span>
          <span>• <?= h(ago((int)$post['created'])) ?></span>
          <span>• <?= (int)$post['likes'] ?> likes</span>
          <span>• <?= (int)$post['commentCount'] ?> comments</span>
          <?php if ($post['reported']): ?><span class="flag">• Reported: <?= h($post['reportReason']) ?></span><?php endif; ?>
        </div>
        <p class="title"><a href="<?= $reviewUrl ?>"><?= h($post['title']) ?></a></p>
        <span class="tag <?= $post['reviewed'] ? 'approved' : 'pending' ?>"><?= $post['reviewed'] ? 'Checked' : 'Needs review' ?></span>
      </div>
      <img class="thumb" src="<?= h($post['image']) ?>" alt="Attached image for <?= h($post['author']) ?>'s post">
      <div class="acts">
        <a href="<?= $reviewUrl ?>"><?= $post['reviewed'] ? 'View' : 'Review' ?></a>
        <?php post_action($csrf, 'approve', (int)$post['id'], 'Approve', 'ok', !$post['reviewed']); ?>
        <?php post_action($csrf, 'remove', (int)$post['id'], 'Remove', '', !$post['reviewed']); ?>
      </div>
    </article>
  <?php endforeach; ?>
<?php else: ?>
  <p class="empty"><?= $kind === 'reported' ? 'No reported posts waiting. Nice work.' : 'Nothing left to approve. Nice work.' ?></p>
<?php endif; ?>
</section>
