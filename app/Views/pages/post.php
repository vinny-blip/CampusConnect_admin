<?php if ($post === null): ?>
  <h2>Post not found</h2><p class="sub">This post does not exist. <a href="posts.php">Go to all posts</a>.</p>
<?php else: ?>
  <p class="sub"><a href="<?= h($back) ?>">← Back</a></p>
  <div class="review">
    <span class="tag <?= h($post['status']) ?>"><?= h($post['status']) ?></span>
    <a href="<?= h(community_url($post['community'])) ?>"><b><?= h($post['cname']) ?></b></a>
    <span>• <?= h($post['author']) ?></span><span>• <?= h(ago((int)$post['created'])) ?></span>
    <?php if ($post['reported']): ?><span class="flag">• Reported: <?= h($post['reportReason']) ?></span><?php endif; ?>
    <span class="grow"></span>
    <?php if ($post['status'] === 'pending'): ?>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="approve"><button class="button primary">Approve</button></form>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="remove"><button class="button danger">Remove</button></form>
    <?php endif; ?>
  </div>
  <div class="detail">
    <div>
      <div class="media"><img src="<?= h($post['image']) ?>" alt="Image attached to <?= h($post['author']) ?>'s post"></div>
      <div class="cap">
        <?php post_action($csrf, 'save', (int)$post['id'], $post['saved'] ? '♥' : '♡', 'bookmark'); ?>
        <span><?= h($post['title']) ?></span>
      </div>
    </div>
    <aside class="cbox">
      <b>💬 Comments (<?= count($post['commentList']) ?>)</b>
      <div class="clist">
        <?php if ($post['commentList']): foreach ($post['commentList'] as $comment): ?>
          <div class="cm">
            <b><?= h($comment['author']) ?></b><small><?= h(ago((int)$comment['created'])) ?></small>
            <div><?= h($comment['text']) ?></div>
            <div class="rx">
              <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="like-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button>👍 <?= (int)$comment['likes'] ?></button></form>
              <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="heart-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button>♥ <?= (int)$comment['hearts'] ?></button></form>
              <a href="post.php?id=<?= (int)$post['id'] ?>&amp;reply=<?= rawurlencode($comment['author']) ?>#comment-form">Reply</a>
            </div>
          </div>
        <?php endforeach; else: ?><p class="empty">No comments yet.</p><?php endif; ?>
      </div>
      <form id="comment-form" class="cwrite" method="post">
        <?= $csrf ?><input type="hidden" name="action" value="comment">
        <input name="text" value="<?= $replyTo !== '' ? h('@' . $replyTo . ' ') : '' ?>" placeholder="😊 Write a comment…" aria-label="Write a comment">
        <button aria-label="Send comment">➤</button>
      </form>
    </aside>
  </div>
  <?php if ($post['body'] !== ''): ?><p class="bodytext"><?= h($post['body']) ?></p><?php endif; ?>
<?php endif; ?>
