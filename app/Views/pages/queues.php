<div class="queue-heading">
  <div class="queue-title">
    <div class="queue-title-row">
      <h2>Queues</h2>
    </div>
    <p class="sub">Review posts and comments against the community guidelines. Reported content appears in the reported tab.</p>
  </div>
</div>
<p class="sub ai-moderation-note">When enabled, AI-flagged or unchecked content is held for admin review. The AI never removes content.</p>
<?php if ($aiModerationEnabled && !$aiModerationConfigured): ?><p class="notice error" role="status">OPENAI_API_KEY is not configured. New posts and comments will be held for admin review until the API is available.</p><?php endif; ?>
<nav class="filters queue-filter-bar" aria-label="Moderation queues">
  <div class="queue-tabs">
    <a class="filter-link <?= $kind === 'approve' ? 'on' : '' ?>" href="queues.php">To approve (<?= (int)$counts['toApprove'] ?>)</a>
    <a class="filter-link <?= $kind === 'reported' ? 'on' : '' ?>" href="queues.php?tab=reported">Reported (<?= (int)$counts['reported'] ?>)</a>
    <a class="filter-link <?= $kind === 'ai' ? 'on' : '' ?>" href="queues.php?tab=ai">AI Review (<?= (int)$counts['aiReview'] ?>)</a>
  </div>
  <form class="queue-search" method="get" action="queues.php">
    <?php if ($kind !== 'approve'): ?><input type="hidden" name="tab" value="<?= h($kind) ?>"><?php endif; ?>
    <input class="localsearch" type="search" name="q" placeholder="Filter this queue" value="<?= h($query) ?>" aria-label="Filter this queue">
    <button class="button" type="submit">Search</button>
  </form>
  <div class="queue-status">
    <?php if (($user['role'] ?? '') === 'admin'): ?>
      <form class="ai-moderation-form" method="post">
        <?= $csrf ?>
        <input type="hidden" name="action" value="toggle-ai-moderation">
        <input type="hidden" name="enabled" value="<?= $aiModerationEnabled ? '0' : '1' ?>">
        <button class="ai-moderation-toggle<?= $aiModerationEnabled ? ' enabled' : '' ?>" type="submit" aria-pressed="<?= $aiModerationEnabled ? 'true' : 'false' ?>">
          <span><?= $aiModerationEnabled ? 'AI Moderation is on' : 'Turn on AI Moderation' ?></span>
          <span class="ai-switch" aria-hidden="true"><span></span></span>
        </button>
      </form>
    <?php else: ?>
      <span class="sub">AI moderation setting managed by admin.</span>
    <?php endif; ?>
    <span class="count"><?= count($posts) + count($reportedComments) + count($aiReviewComments) ?> items shown</span>
  </div>
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
          <time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['created'], 1000))) ?>">• Posted <?= h(date_label((int)$post['created'])) ?></time>
          <?php if ($post['status'] === 'removed'): ?>
            <?php if ($post['removedAt'] !== null): ?><time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['removedAt'], 1000))) ?>">• Removed <?= h(date_label((int)$post['removedAt'])) ?></time>
            <?php else: ?><span>• Removal date unavailable</span><?php endif; ?>
          <?php endif; ?>
          <span>• <?= (int)$post['likes'] ?> likes</span>
          <span>• <?= (int)$post['commentCount'] ?> comments</span>
          <?php if ($post['reported']): ?><span class="flag">• Reported: <?= h($post['reportReason']) ?><?= $post['reportedAt'] !== null ? ' on ' . h(date_label((int)$post['reportedAt'])) : '' ?></span><?php endif; ?>
          <?php if ($post['locked']): ?><span class="tag">Comments locked</span><?php endif; ?>
        </div>
        <p class="title"><a href="<?= $reviewUrl ?>"><?= h($post['title']) ?></a></p>
        <?php if ($post['tags']): ?><div class="post-tags"><?php foreach ($post['tags'] as $tag): ?><span class="tag"><?= h($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
        <?php if ($post['aiNeedsReview']): ?><span class="flag"><?= $post['aiReviewError'] ? 'AI check unavailable; admin review required' : 'AI flagged for admin review' ?><?= $post['aiCategories'] ? ': ' . h(implode(', ', $post['aiCategories'])) : '' ?></span><?php endif; ?>
        <span class="tag <?= $post['reviewed'] ? 'approved' : 'pending' ?>"><?= $post['reviewed'] ? 'Checked' : 'Needs review' ?></span>
      </div>
      <img class="thumb" src="<?= h($post['image']) ?>" alt="Attached image for <?= h($post['author']) ?>'s post">
      <div class="acts">
        <a href="<?= $reviewUrl ?>"><?= $post['reviewed'] ? 'View' : 'Review' ?></a>
        <?php if ($post['aiNeedsReview'] && ($user['role'] ?? '') !== 'admin'): ?>
          <span class="tag pending">Waiting for admin</span>
        <?php elseif ($post['status'] === 'pending'): ?>
          <?php post_action($csrf, 'approve', (int)$post['id'], 'Approve', 'ok', !$post['reviewed']); ?>
          <?php post_action($csrf, 'remove', (int)$post['id'], 'Hide', '', !$post['reviewed']); ?>
        <?php elseif ($post['status'] === 'approved'): ?>
          <?php post_action($csrf, 'remove', (int)$post['id'], 'Hide'); ?>
        <?php endif; ?>
        <?php if ($post['reported']): ?><?php post_action($csrf, 'clear-post-report', (int)$post['id'], 'Dismiss report'); ?><?php endif; ?>
        <?php if ($kind === 'reported'): ?>
          <form class="inline-action" method="post" onsubmit="return confirm('Permanently delete this post and its comments?')"><?= $csrf ?><input type="hidden" name="action" value="delete-post"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><button class="chip danger" type="submit"<?= $post['status'] === 'pending' && !$post['reviewed'] ? ' disabled' : '' ?>>Delete</button></form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
<?php elseif ($kind === 'approve' || ($kind === 'reported' && !$reportedComments) || ($kind === 'ai' && !$aiReviewComments)): ?>
  <p class="empty"><?= $kind === 'reported' ? 'No reported posts waiting.' : ($kind === 'ai' ? 'No content awaiting AI review.' : 'Nothing left to approve.') ?></p>
<?php endif; ?>
<?php if ($kind === 'reported' || $kind === 'ai'): ?>
  <?php $reviewComments = $kind === 'ai' ? $aiReviewComments : $reportedComments; ?>
  <section class="reported-comments" aria-labelledby="reported-comments-title">
    <h3 id="reported-comments-title"><?= $kind === 'ai' ? 'AI-held comments' : 'Reported comments' ?></h3>
    <?php if ($reviewComments): ?>
      <?php foreach ($reviewComments as $comment): ?>
        <article class="reported-comment">
          <div>
            <b><?= h($comment['author']) ?></b>
            <small>Comment posted <?= h(date_label((int)$comment['created'])) ?><?= $comment['reportedAt'] !== null ? ' • Reported ' . h(date_label((int)$comment['reportedAt'])) : '' ?> on <a href="post.php?id=<?= (int)$comment['postId'] ?>"><?= h($comment['postTitle']) ?></a> (post date: <?= h(date_label((int)$comment['postCreated'])) ?>)</small>
            <p><?= h($comment['text']) ?></p>
            <?php if ($comment['reported']): ?><span class="flag">Reported: <?= h($comment['reportReason']) ?></span><?php endif; ?>
            <?php if ($comment['aiNeedsReview']): ?><span class="flag"><?= $comment['aiReviewError'] ? 'AI check unavailable; held for admin review' : 'AI flagged for admin review' ?><?= $comment['aiCategories'] ? ': ' . h(implode(', ', $comment['aiCategories'])) : '' ?></span><?php endif; ?>
          </div>
          <div class="acts">
            <?php if ($comment['aiNeedsReview'] && ($user['role'] ?? '') === 'admin'): ?>
              <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="approve-ai-comment"><input type="hidden" name="post_id" value="<?= (int)$comment['postId'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['commentId'] ?>"><button class="chip ok">Approve comment</button></form>
              <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="hide-comment"><input type="hidden" name="post_id" value="<?= (int)$comment['postId'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['commentId'] ?>"><button class="chip">Hide</button></form>
            <?php elseif ($comment['aiNeedsReview']): ?>
              <span class="tag pending">Waiting for admin</span>
            <?php elseif ($comment['reported']): ?>
              <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="clear-comment-report"><input type="hidden" name="post_id" value="<?= (int)$comment['postId'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['commentId'] ?>"><button class="chip">Dismiss</button></form>
              <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="hide-comment"><input type="hidden" name="post_id" value="<?= (int)$comment['postId'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['commentId'] ?>"><button class="chip">Hide</button></form>
              <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="delete-comment"><input type="hidden" name="post_id" value="<?= (int)$comment['postId'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['commentId'] ?>"><button class="chip danger">Delete</button></form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty">No reported comments.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>
</section>
