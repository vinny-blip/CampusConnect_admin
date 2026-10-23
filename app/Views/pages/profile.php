<h2>Profile</h2>
<div class="about">
  <span class="avatar big"><?= h(initials($user['name'])) ?></span>
  <div><b><?= h($user['name']) ?></b><p class="sub"><?= h($user['email']) ?> • Student moderator</p></div>
</div>
<div class="stats">
  <a class="stat" href="queues.php"><b><?= (int)$counts['toApprove'] + (int)$counts['reported'] ?></b>In your queues</a>
</div>
<form class="form" method="post">
  <?= $csrf ?><input type="hidden" name="action" value="update-name">
  <label for="name">Display name</label><input id="name" name="name" value="<?= h($user['name']) ?>" required>
  <button class="button primary">Save changes</button>
</form>
