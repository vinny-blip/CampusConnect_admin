<div class="notif-header">
  <h2>Notifications</h2>
  <form method="post"><?= $csrf ?><input type="hidden" name="action" value="read-all"><button class="notif-readall" type="submit">Mark all as read</button></form>
</div>
<div class="notif-list">
  <?php foreach ($notifications as $item): ?>
    <a class="notif-item<?= $item['read'] ? '' : ' unread' ?>" href="notifications.php?open=<?= (int)$item['id'] ?>">
      <span class="notif-dot<?= $item['read'] ? ' read' : '' ?>" aria-label="<?= $item['read'] ? 'Read' : 'Unread' ?>"></span>
      <span class="notif-text"><?= h($item['text']) ?></span>
      <small class="notif-time"><?= h(ago((int)$item['t'])) ?></small>
    </a>
  <?php endforeach; ?>
  <?php if (!$notifications): ?><p class="empty">No notifications yet.</p><?php endif; ?>
</div>
