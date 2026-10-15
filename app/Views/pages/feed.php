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

<?php if ($feedPosts): ?>
  <?php foreach ($feedPosts as $post): ?>
    <?php $postUrl = 'post.php?id=' . (int)$post['id'] . ($post['status'] === 'pending' ? '&amp;review=1' : ''); ?>
    <article class="card">
      <div>
        <div class="meta">
          <span class="avatar sm"><?= h(mb_strtoupper(mb_substr($post['author'], 0, 1))) ?></span>
          <a href="<?= h(community_url($post['community'])) ?>"><b><?= h($post['cname']) ?></b></a>
          <span>• <?= h($post['author']) ?></span><span>• <?= h(ago((int)$post['created'])) ?></span>
          <?php if ($post['pinned']): ?><span class="tag">📌 Pinned</span><?php endif; ?>
          <?php if (!in_array($mode, ['home', 'newest', 'community'], true)): ?><span class="tag <?= h($post['status']) ?>"><?= h($post['status']) ?></span><?php endif; ?>
          <?php if ($post['reported'] && $post['status'] === 'pending'): ?><span class="flag">• Reported: <?= h($post['reportReason']) ?></span><?php endif; ?>
        </div>
        <h3><a href="<?= $postUrl ?>"><?= h($post['title']) ?></a></h3>
        <div class="foot">
          <?php if ($mode === 'control' && $post['status'] === 'approved'): ?>
            <a class="chip" href="post.php?id=<?= (int)$post['id'] ?>">💬 <?= (int)$post['commentCount'] ?></a>
            <?php post_action($csrf, 'pin', (int)$post['id'], $post['pinned'] ? 'Unpin' : 'Pin'); ?>
            <?php post_action($csrf, 'remove', (int)$post['id'], 'Remove'); ?>
          <?php elseif ($mode === 'control' && $post['status'] === 'removed'): ?>
            <a class="chip" href="post.php?id=<?= (int)$post['id'] ?>">💬 <?= (int)$post['commentCount'] ?></a>
            <?php post_action($csrf, 'restore', (int)$post['id'], 'Restore to queue'); ?>
          <?php elseif ($mode === 'control' || ($mode === 'reported' && $post['status'] === 'pending')): ?>
            <a class="chip ok" href="<?= $postUrl ?>">Review</a>
          <?php else: ?>
            <?php post_action($csrf, 'like', (int)$post['id'], ($post['liked'] ? '♥ ' : '♡ ') . $post['likes']); ?>
            <a class="chip" href="post.php?id=<?= (int)$post['id'] ?>">💬 <?= (int)$post['commentCount'] ?></a>
            <?php post_action($csrf, 'save', (int)$post['id'], $post['saved'] ? '★ Saved' : '☆ Save'); ?>
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
