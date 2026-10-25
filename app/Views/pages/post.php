<?php if ($post === null): ?>
  <h2>Post not found</h2><p class="sub">This post does not exist. <a href="posts.php">Go to all posts</a>.</p>
<?php else: ?>
  <p class="sub"><a href="<?= h($back) ?>"><?= icon_svg('back') ?> Back</a></p>
  <div class="review">
    <span class="tag <?= h($post['status']) ?>"><?= h($post['status']) ?></span>
    <b><?= h($post['cname']) ?></b>
    <span>• <?= h($post['author']) ?></span><time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['created'], 1000))) ?>">• Posted <?= h(date_time_label((int)$post['created'])) ?></time>
    <?php if ($post['status'] === 'approved' && $post['approvedAt'] !== null): ?><time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['approvedAt'], 1000))) ?>">• Approved <?= h(date_time_label((int)$post['approvedAt'])) ?></time><?php endif; ?>
    <?php if ($post['status'] === 'removed'): ?>
      <?php if ($post['removedAt'] !== null): ?><time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['removedAt'], 1000))) ?>">• Removed <?= h(date_time_label((int)$post['removedAt'])) ?></time>
      <?php else: ?><span>• Removal date unavailable</span><?php endif; ?>
    <?php endif; ?>
    <?php if ($post['reported']): ?><span class="flag">• Reported: <?= h($post['reportReason']) ?><?= $post['reportedAt'] !== null ? ' on ' . h(date_label((int)$post['reportedAt'])) : '' ?></span><?php endif; ?>
    <?php if ($post['aiNeedsReview']): ?><span class="flag">• <?= $post['aiReviewError'] ? 'AI check unavailable; held for admin review' : 'AI flagged for admin review' ?><?= $post['aiCategories'] ? ': ' . h(implode(', ', $post['aiCategories'])) : '' ?></span><?php endif; ?>
    <span class="grow"></span>
    <?php if ($post['aiNeedsReview'] && ($user['role'] ?? '') !== 'admin'): ?>
      <span class="tag pending">Waiting for admin</span>
    <?php elseif ($post['status'] === 'pending'): ?>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="approve"><button class="button primary">Approve</button></form>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="remove"><button class="button danger">Hide post</button></form>
    <?php elseif ($post['status'] === 'approved'): ?>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="remove"><button class="button danger">Hide post</button></form>
    <?php endif; ?>
  </div>
  <?php if ($post['reported']): ?>
    <div class="notice error">Reported: <?= h($post['reportReason']) ?>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="clear-post-report"><button class="linkbtn">Dismiss report</button></form>
    </div>
  <?php endif; ?>
  <details class="moderation-tools">
    <summary>Content rule enforcement</summary>
    <div class="moderation-actions">
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="toggle-lock"><button class="button"><?= $post['locked'] ? 'Unlock comments' : 'Lock comments' ?></button></form>
      <?php if ($post['reported']): ?>
        <form class="inline-action" method="post" onsubmit="return confirm('Permanently delete this post and its comments?')"><?= $csrf ?><input type="hidden" name="action" value="delete-post"><button class="button danger">Delete post permanently</button></form>
      <?php endif; ?>
      <details class="edit-post">
        <summary class="button">Edit post and tags</summary>
        <form class="form" method="post">
          <?= $csrf ?><input type="hidden" name="action" value="edit-post">
          <label for="edit-title">Title</label><input id="edit-title" name="title" maxlength="140" required value="<?= h($post['title']) ?>">
          <label for="edit-body">Post text</label><textarea id="edit-body" name="body" rows="5" maxlength="5000"><?= h($post['body']) ?></textarea>
          <label for="edit-tags">Tags (comma-separated, up to five)</label><input id="edit-tags" name="tags" maxlength="200" value="<?= h(implode(', ', $post['tags'])) ?>">
          <button class="button primary" type="submit">Save changes</button>
        </form>
      </details>
    </div>
  </details>
  <div class="detail">
    <div>
      <div class="cap">
        <div class="post-author">
          <span>Posted by <?= h($post['author']) ?></span>
          <time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['created'], 1000))) ?>"><?= h(date_time_label((int)$post['created'])) ?> · <?= h(ago((int)$post['created'])) ?> ago</time>
        </div>
        <h2><?= h($post['title']) ?></h2>
      </div>
      <div class="media"><img src="<?= h($post['image']) ?>" alt="Image attached to <?= h($post['author']) ?>'s post"></div>
      <?php if ($post['tags']): ?><div class="post-tags"><?php foreach ($post['tags'] as $tag): ?><span class="tag"><?= h($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
    <aside class="cbox">
      <b><?= icon_svg('message') ?> Comments (<?= count($post['commentList']) ?>)</b>
      <?php if ($post['locked']): ?><p class="lock-notice">Comments are locked by a moderator.</p><?php endif; ?>
      <div class="clist">
        <?php if ($post['commentList']): foreach ($post['commentList'] as $comment): ?>
          <div class="cm">
            <b><?= h($comment['author']) ?></b><small><?= h(date_label((int)$comment['created'])) ?></small>
            <div><?= $comment['hidden'] ? '<em>Comment hidden by a moderator.</em>' : h($comment['text']) ?></div>
            <?php if ($comment['hidden']): ?>
              <?php if ($comment['aiNeedsReview']): ?>
                <p class="flag"><?= $comment['aiReviewError'] ? 'AI check unavailable; held for admin review' : 'AI flagged for admin review' ?><?= $comment['aiCategories'] ? ': ' . h(implode(', ', $comment['aiCategories'])) : '' ?></p>
                <a href="comments.php">Review AI-held comments</a>
              <?php else: ?>
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="restore-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="linkbtn">Restore comment</button></form>
              <?php endif; ?>
            <?php elseif ($comment['reported']): ?><p class="flag">Reported: <?= h($comment['reportReason']) ?><?= $comment['reportedAt'] !== null ? ' on ' . h(date_label((int)$comment['reportedAt'])) : '' ?></p>
              <div class="comment-moderation">
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="clear-comment-report"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="linkbtn">Dismiss report</button></form>
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="hide-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="linkbtn">Hide comment</button></form>
              </div>
            <?php endif; ?>
            <div class="rx">
              <?php if (!$comment['hidden']): ?>
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="like-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button><?= icon_svg('thumb-up') ?> <?= (int)$comment['likes'] ?></button></form>
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="heart-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button><?= icon_svg('heart') ?> <?= (int)$comment['hearts'] ?></button></form>
                <?php if (!$post['locked'] && $post['status'] === 'approved'): ?><a href="post.php?id=<?= (int)$post['id'] ?>&amp;reply=<?= rawurlencode($comment['author']) ?>#comment-form">Reply</a><?php endif; ?>
              <?php endif; ?>
              <?php if (!$comment['aiNeedsReview'] || ($user['role'] ?? '') === 'admin'): ?>
                <form class="inline-action" method="post" onsubmit="return confirm('Permanently delete this comment?')">
                  <?= $csrf ?><input type="hidden" name="action" value="delete-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                  <button class="linkbtn danger-text" type="submit">Remove</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; else: ?><p class="empty">No comments yet.</p><?php endif; ?>
      </div>
      <?php if (!$post['locked'] && $post['status'] === 'approved'): ?>
        <form id="comment-form" class="cwrite" method="post">
          <?= $csrf ?><input type="hidden" name="action" value="comment">
          <input name="text" value="<?= $replyTo !== '' ? h('@' . $replyTo . ' ') : '' ?>" placeholder="Write a comment…" aria-label="Write a comment">
          <button aria-label="Send comment"><?= icon_svg('send') ?></button>
        </form>
      <?php endif; ?>
    </aside>
  </div>
  <?php if ($post['body'] !== ''): ?><p class="bodytext"><?= h($post['body']) ?></p><?php endif; ?>
<?php endif; ?>
