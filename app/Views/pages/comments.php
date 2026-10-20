<h2>Comments</h2>
<p class="sub"><?= count($comments) ?> comments. Remove any that break the rules.</p>
<?php if ($comments): ?>
  <?php foreach (array_slice($comments, 0, 60) as $comment): ?>
    <div class="row-item">
      <div><b><?= h($comment['author']) ?></b> <small>Comment posted <?= h(date_label((int)$comment['created'])) ?> on</small>
        <a href="post.php?id=<?= (int)$comment['postId'] ?>"><?= h($comment['postTitle']) ?></a>
        <small>• Post date: <?= h(date_label((int)$comment['postCreated'])) ?><?= $comment['postStatus'] === 'removed' ? ' • Removed ' . ($comment['postRemovedAt'] !== null ? h(date_label((int)$comment['postRemovedAt'])) : 'date unavailable') : '' ?></small>
        <div><?= $comment['hidden'] ? '<em>Comment hidden by a moderator.</em>' : h($comment['text']) ?></div>
        <?php if ($comment['reported']): ?>
          <span class="flag">Reported: <?= h($comment['reportReason']) ?><?= $comment['reportedAt'] !== null ? ' on ' . h(date_label((int)$comment['reportedAt'])) : '' ?></span>
          <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="clear-comment-report">
            <input type="hidden" name="post_id" value="<?= (int)$comment['postId'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
            <button class="linkbtn">Dismiss report</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="comment-list-actions">
        <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="<?= $comment['hidden'] ? 'restore-comment' : 'hide-comment' ?>">
          <input type="hidden" name="post_id" value="<?= (int)$comment['postId'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
          <button class="chip"><?= $comment['hidden'] ? 'Restore' : 'Hide' ?></button>
        </form>
        <form class="inline-action" method="post" onsubmit="return confirm('Permanently delete this comment?')"><?= $csrf ?><input type="hidden" name="action" value="delete-comment">
          <input type="hidden" name="post_id" value="<?= (int)$comment['postId'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
          <button class="chip danger">Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?><p class="empty">No comments to show.</p><?php endif; ?>
