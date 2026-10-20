<?php
$showStatus = in_array($mode, ['posts', 'control'], true);
?>
<?php if ($about !== null): ?>
  <div class="about">
    <span class="cicon big"></span>
    <div><h2><?= h($about['name']) ?></h2><p class="sub"><?= h($about['visitors']) ?> visitors • <?= (int)$about['contrib'] ?> contributions past week</p></div>
    <a class="button" href="communities.php">Manage</a>
  </div>
<?php else: ?>
  <h2><?= h($feedTitle) ?></h2>
<?php endif; ?>
<?php if ($feedSub !== ''): ?>
  <?php if ($mode === 'control'): ?>
    <p class="sub">Pin or remove posts in the feed. Pending posts have to be checked in the <a href="queues.php">queue</a> first.</p>
  <?php elseif ($mode === 'reported'): ?>
    <p class="sub">Posts that members reported. Review pending ones in the <a href="queues.php?tab=reported">reported queue</a>.</p>
  <?php else: ?>
    <p class="sub"><?= $mode === 'community' && $about === null ? $feedSub : h($feedSub) ?></p>
  <?php endif; ?>
<?php endif; ?>

<?php if ($mode === 'search'): ?>
  <h3 class="chead">Communities</h3>
  <?php if ($searchCommunities): ?>
    <?php foreach ($searchCommunities as $community): ?>
      <div class="crow"><span class="cicon"></span><div class="info">
        <a class="name" href="<?= h(community_url($community['slug'])) ?>"><?= h($community['name']) ?></a><br>
        <small><?= h($community['visitors']) ?> visitors • <?= (int)$community['contrib'] ?> contributions past week</small>
      </div></div>
    <?php endforeach; ?>
  <?php else: ?><p class="empty">No matching communities.</p><?php endif; ?>
  <h3 class="chead">Posts</h3>
<?php endif; ?>

<?php if ($showStatus): ?>
  <nav class="filters" aria-label="Post status">
    <?php foreach (['all', 'pending', 'approved', 'removed'] as $status): ?>
      <a class="filter-link <?= $filter === $status ? 'on' : '' ?>" href="<?= h($mode) ?>.php?status=<?= h($status) ?>"><?= h(ucfirst($status)) ?></a>
    <?php endforeach; ?>
    <span class="count"><?= count($feedPosts) ?> posts</span>
  </nav>
<?php endif; ?>

<?php if ($mode === 'home'): ?>
  <div class="home-workspace">
    <section class="home-posts" aria-label="Approved posts">
<?php endif; ?>

<?php if ($feedPosts): ?>
  <?php foreach ($feedPosts as $post): ?>
    <?php $postUrl = 'post.php?id=' . (int)$post['id'] . ($post['status'] === 'pending' ? '&amp;review=1' : ''); ?>
    <article class="card">
      <div>
        <div class="meta">
          <span class="avatar sm"><?= h(mb_strtoupper(mb_substr($post['author'], 0, 1))) ?></span>
          <a href="<?= h(community_url($post['community'])) ?>"><b><?= h($post['cname']) ?></b></a>
          <span>• <?= h($post['author']) ?></span><time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['created'], 1000))) ?>">• Posted <?= h(date_label((int)$post['created'])) ?></time>
          <?php if ($post['status'] === 'removed'): ?>
            <?php if ($post['removedAt'] !== null): ?><time datetime="<?= h(date(DATE_ATOM, intdiv((int)$post['removedAt'], 1000))) ?>">• Removed <?= h(date_label((int)$post['removedAt'])) ?></time>
            <?php else: ?><span>• Removal date unavailable</span><?php endif; ?>
          <?php endif; ?>
          <?php if ($post['pinned']): ?><span class="tag"><?= icon_svg('pin') ?> Pinned</span><?php endif; ?>
          <?php if ($post['locked']): ?><span class="tag">Comments locked</span><?php endif; ?>
          <?php if (!in_array($mode, ['home', 'newest', 'community'], true)): ?><span class="tag <?= h($post['status']) ?>"><?= h($post['status']) ?></span><?php endif; ?>
          <?php if ($post['reported']): ?><span class="flag">• Reported: <?= h($post['reportReason']) ?><?= $post['reportedAt'] !== null ? ' on ' . h(date_label((int)$post['reportedAt'])) : '' ?></span><?php endif; ?>
        </div>
        <h3><a href="<?= $postUrl ?>"><?= h($post['title']) ?></a></h3>
        <?php if ($post['tags']): ?><div class="post-tags"><?php foreach ($post['tags'] as $tag): ?><span class="tag"><?= h($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
        <div class="foot">
          <?php if ($mode === 'control' && $post['status'] === 'approved'): ?>
            <a class="chip" href="post.php?id=<?= (int)$post['id'] ?>"><?= icon_svg('message') ?> <?= (int)$post['commentCount'] ?></a>
            <?php post_action($csrf, 'pin', (int)$post['id'], $post['pinned'] ? 'Unpin' : 'Pin'); ?>
            <?php post_action($csrf, 'remove', (int)$post['id'], 'Remove'); ?>
          <?php elseif ($mode === 'control' && $post['status'] === 'removed'): ?>
            <a class="chip" href="post.php?id=<?= (int)$post['id'] ?>"><?= icon_svg('message') ?> <?= (int)$post['commentCount'] ?></a>
            <?php post_action($csrf, 'restore', (int)$post['id'], 'Restore to queue'); ?>
          <?php elseif ($mode === 'control' || ($mode === 'reported' && $post['status'] === 'pending')): ?>
            <a class="chip ok" href="<?= $postUrl ?>">Review</a>
          <?php else: ?>
            <?php post_action($csrf, 'like', (int)$post['id'], (string)$post['likes'], 'chip', false, $post['liked'] ? 'heart-filled' : 'heart'); ?>
            <a class="chip" href="post.php?id=<?= (int)$post['id'] ?>"><?= icon_svg('message') ?> <?= (int)$post['commentCount'] ?></a>
            <?php post_action($csrf, 'save', (int)$post['id'], $post['saved'] ? 'Saved' : 'Save', 'chip', false, 'bookmark'); ?>
            <?php if (in_array($mode, ['posts', 'search'], true) && $post['status'] === 'pending'): ?><a class="chip ok" href="<?= $postUrl ?>">Review</a><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <img class="thumb" src="<?= h($post['image']) ?>" alt="Image attached to <?= h($post['author']) ?>'s post">
    </article>
  <?php endforeach; ?>
<?php else: ?>
  <p class="empty">Nothing to show here yet.</p>
<?php endif; ?>

<?php if ($mode === 'home'): ?>
    </section>
    <aside class="home-insights" aria-label="Today's activity">
      <section class="trending-panel" aria-labelledby="trending-title">
        <div class="trending-heading">
          <div>
            <h3 id="trending-title">Top 5 most-liked posts today</h3>
            <p>Today’s approved posts, ranked by likes</p>
          </div>
          <?= icon_svg('heart') ?>
        </div>
        <?php if ($topLikedToday): ?>
          <ol class="top-liked-list">
            <?php foreach ($topLikedToday as $post): ?>
              <li>
                <a href="post.php?id=<?= (int)$post['id'] ?>"><?= h($post['title']) ?></a>
                <span><?= (int)$post['likes'] ?> likes</span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="empty trending-empty">No approved posts from today yet.</p>
        <?php endif; ?>
      </section>
      <?php $reportChartCompact = true; require dirname(__DIR__) . '/partials/report-chart.php'; ?>
    </aside>
  </div>
<?php endif; ?>
