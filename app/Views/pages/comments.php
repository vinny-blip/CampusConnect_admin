<h2>Comments</h2>
<p class="sub"><?= count($comments) ?> comments. Remove any that break the rules.</p>
<?php if ($comments): ?>
  <?php foreach (array_slice($comments, 0, 60) as $comment): ?>
    <div class="row-item">
      <div><b><?= h($comment['author']) ?></b> <small><?= h(ago((int)$comment['created'])) ?> on</small>
        <a href="post.php?id=<?= (int)$comment['postId'] ?>"><?= h($comment['postTitle']) ?></a>
        <div><?= h($comment['text']) ?></div>
      </div>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="delete-comment">
        <input type="hidden" name="post_id" value="<?= (int)$comment['postId'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
        <button class="chip">Remove</button>
      </form>
    </div>
  <?php endforeach; ?>
<?php else: ?><p class="empty">No comments to show.</p><?php endif; ?>
