<h2>Profile</h2>
<div class="about">
  <span class="avatar big"><?= h(initials($user['name'])) ?></span>
  <div><b><?= h($user['name']) ?></b><p class="sub"><?= h($user['email']) ?> • Student moderator</p></div>
</div>
<div class="stats">
  <a class="stat" href="queues.php"><b><?= (int)$counts['toApprove'] + (int)$counts['reported'] ?></b>In your queues</a>
  <a class="stat" href="communities.php"><b><?= count($communities) ?></b>Communities</a>
  <a class="stat" href="saved.php"><b><?= (int)$savedCount ?></b>Saved posts</a>
</div>
<form class="form" method="post">
  <?= $csrf ?><input type="hidden" name="action" value="update-name">
  <label for="name">Display name</label><input id="name" name="name" value="<?= h($user['name']) ?>" required>
  <button class="button primary">Save changes</button>
</form>
<h3 class="chead">Demo data</h3>
<p class="sub">Put the posts, mail and notifications back to how they started.</p>
<form method="post">
  <?= $csrf ?><input type="hidden" name="action" value="reset">
  <button class="button">Reset demo data</button>
</form>
