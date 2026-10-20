<?php if ($post === null): ?>
  <h2>Post not found</h2><p class="sub">This post does not exist. <a href="posts.php">Go to all posts</a>.</p>
<?php else: ?>
  <p class="sub"><a href="<?= h($back) ?>"><?= icon_svg('back') ?> Back</a></p>
  <div class="review">
    <span class="tag <?= h($post['status']) ?>"><?= h($post['status']) ?></span>
    <a href="<?= h(community_url($post['community'])) ?>"><b><?= h($post['cname']) ?></b></a>
    <span>• <?= h($post['author']) ?></span><time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['created'], 1000))) ?>">• Posted <?= h(date_label((int)$post['created'])) ?></time>
    <?php if ($post['status'] === 'removed'): ?>
      <?php if ($post['removedAt'] !== null): ?><time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['removedAt'], 1000))) ?>">• Removed <?= h(date_label((int)$post['removedAt'])) ?></time>
      <?php else: ?><span>• Removal date unavailable</span><?php endif; ?>
    <?php endif; ?>
    <?php if ($post['reported']): ?><span class="flag">• Reported: <?= h($post['reportReason']) ?><?= $post['reportedAt'] !== null ? ' on ' . h(date_label((int)$post['reportedAt'])) : '' ?></span><?php endif; ?>
    <span class="grow"></span>
    <?php if ($post['status'] === 'pending'): ?>
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
      <div class="media"><img src="<?= h($post['image']) ?>" alt="Image attached to <?= h($post['author']) ?>'s post"></div>
      <div class="cap">
        <?php post_action($csrf, 'save', (int)$post['id'], '', 'bookmark', false, $post['saved'] ? 'bookmark-filled' : 'bookmark'); ?>
        <span><?= h($post['title']) ?></span>
      </div>
      <?php if ($post['tags']): ?><div class="post-tags"><?php foreach ($post['tags'] as $tag): ?><span class="tag"><?= h($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
      <?php if (!$post['reported'] && $post['status'] !== 'removed'): ?>
        <details class="report-control">
          <summary>Report post</summary>
          <form class="report-form" method="post">
            <?= $csrf ?><input type="hidden" name="action" value="report-post">
            <label for="post-report-reason">Reason</label>
            <select id="post-report-reason" name="reason" required>
              <option value="">Choose a reason</option>
              <?php foreach (report_reason_options() as $reason): ?><option value="<?= h($reason) ?>"><?= h($reason) ?></option><?php endforeach; ?>
            </select>
            <button class="button" type="submit">Submit report</button>
          </form>
        </details>
      <?php endif; ?>
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
              <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="restore-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="linkbtn">Restore comment</button></form>
            <?php elseif ($comment['reported']): ?><p class="flag">Reported: <?= h($comment['reportReason']) ?><?= $comment['reportedAt'] !== null ? ' on ' . h(date_label((int)$comment['reportedAt'])) : '' ?></p>
              <div class="comment-moderation">
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="clear-comment-report"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="linkbtn">Dismiss report</button></form>
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="hide-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="linkbtn">Hide comment</button></form>
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="delete-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="linkbtn danger-text">Delete comment</button></form>
              </div>
            <?php elseif ($post['status'] !== 'removed'): ?>
              <details class="report-control">
                <summary>Report comment</summary>
                <form class="report-form" method="post">
                  <?= $csrf ?><input type="hidden" name="action" value="report-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                  <label for="comment-report-<?= (int)$comment['id'] ?>">Reason</label>
                  <select id="comment-report-<?= (int)$comment['id'] ?>" name="reason" required>
                    <option value="">Choose a reason</option>
                    <?php foreach (report_reason_options() as $reason): ?><option value="<?= h($reason) ?>"><?= h($reason) ?></option><?php endforeach; ?>
                  </select>
                  <button class="button" type="submit">Submit report</button>
                </form>
              </details>
            <?php endif; ?>
            <?php if (!$comment['hidden']): ?>
              <div class="rx">
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="like-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button><?= icon_svg('thumb-up') ?> <?= (int)$comment['likes'] ?></button></form>
                <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="heart-comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button><?= icon_svg('heart') ?> <?= (int)$comment['hearts'] ?></button></form>
                <?php if (!$post['locked'] && $post['status'] === 'approved'): ?><a href="post.php?id=<?= (int)$post['id'] ?>&amp;reply=<?= rawurlencode($comment['author']) ?>#comment-form">Reply</a><?php endif; ?>
              </div>
            <?php endif; ?>
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
