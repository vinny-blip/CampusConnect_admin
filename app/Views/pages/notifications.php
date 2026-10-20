<h2>Notifications</h2>
<div class="sub">
  <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="read-all"><button class="linkbtn">Mark all as read</button></form>
</div>
<?php foreach ($notifications as $item): ?>
  <a class="row-item<?= $item['read'] ? '' : ' unread' ?>" href="notifications.php?open=<?= (int)$item['id'] ?>">
    <span><?php if (!$item['read']): ?><span class="unread-dot" aria-label="Unread"></span><?php endif; ?><?= h($item['text']) ?></span><small><?= h(ago((int)$item['t'])) ?></small>
  </a>
<?php endforeach; ?>
