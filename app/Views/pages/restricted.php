<h2>Restricted</h2>
<p class="sub">Restricted members cannot post or comment.</p>
<form class="form inline" method="post">
  <?= $csrf ?><input type="hidden" name="action" value="restrict">
  <input name="username" placeholder="Username" aria-label="Username" required>
  <input name="reason" placeholder="Reason" aria-label="Reason" required>
  <button class="button primary">Restrict</button>
</form>
<?php if ($restrictedUsers): ?>
  <?php foreach ($restrictedUsers as $restrictedUser): ?>
    <div class="row-item">
      <span><b><?= h($restrictedUser['username']) ?></b> • <?= h($restrictedUser['reason']) ?> <small><?= h(ago((int)$restrictedUser['t'])) ?></small></span>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="unrestrict"><input type="hidden" name="username" value="<?= h($restrictedUser['username']) ?>"><button class="chip">Unrestrict</button></form>
    </div>
  <?php endforeach; ?>
<?php else: ?><p class="empty">Nobody is restricted.</p><?php endif; ?>
