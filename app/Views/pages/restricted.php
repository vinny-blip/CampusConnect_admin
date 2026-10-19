<h2>Restricted</h2>
<p class="sub">Restrict a moderator account from creating posts or comments across the app.</p>
<form class="form inline" method="post">
  <?= $csrf ?><input type="hidden" name="action" value="restrict">
  <input name="email" type="email" placeholder="Moderator email" aria-label="Moderator email" required>
  <input name="reason" placeholder="Reason" aria-label="Reason" required>
  <button class="button primary">Restrict</button>
</form>
<?php if ($restrictedUsers): ?>
  <?php foreach ($restrictedUsers as $restrictedUser): ?>
    <div class="row-item">
      <span><b><?= h($restrictedUser['name']) ?></b> <small><?= h($restrictedUser['email']) ?></small> • <?= h($restrictedUser['reason']) ?> <small><?= h(ago((int)$restrictedUser['t'])) ?></small></span>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="unrestrict"><input type="hidden" name="user_id" value="<?= (int)$restrictedUser['user_id'] ?>"><button class="chip">Unrestrict</button></form>
    </div>
  <?php endforeach; ?>
<?php else: ?><p class="empty">Nobody is restricted.</p><?php endif; ?>
