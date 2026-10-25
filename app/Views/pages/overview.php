<h2>Overview</h2>
<p class="sub">What needs your attention across your communities.</p>
<div class="stats">
  <a class="stat" href="control.php?status=pending"><b><?= (int)$counts['toApprove'] ?></b>To approve</a>
  <a class="stat" href="reported.php"><b><?= (int)$counts['reported'] ?></b>Reported</a>
  <a class="stat" href="posts.php?status=approved"><b><?= (int)$counts['approved'] ?></b>Approved posts</a>
  <a class="stat" href="posts.php?status=removed"><b><?= (int)$counts['removed'] ?></b>Removed posts</a>
  <a class="stat" href="comments.php"><b><?= (int)$counts['comments'] ?></b>Comments</a>
</div>
<?php $reportChartCompact = false; require dirname(__DIR__) . '/partials/report-chart.php'; ?>
<h3 class="chead">Recent moderator activity</h3>
<?php if ($activity): ?>
  <?php foreach ($activity as $item): ?>
    <div class="row-item"><span><b><?= h($item['mod']) ?></b> <?= h(mb_strtolower($item['action'])) ?>
      <a href="post.php?id=<?= (int)$item['postId'] ?>"><?= h($item['title']) ?></a></span>
      <small><?= h(date_label((int)$item['t'])) ?></small></div>
  <?php endforeach; ?>
<?php else: ?>
  <p class="empty">No mobile moderation activity yet.</p>
<?php endif; ?>
