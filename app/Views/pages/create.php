<h2>Create post</h2>
<p class="sub">Posts from moderators are published straight away.</p>
<form class="form" method="post" enctype="multipart/form-data">
  <?= $csrf ?><input type="hidden" name="action" value="create-post">
  <label for="community">Community</label>
  <select id="community" name="community"><option value="">Choose a community</option>
    <?php foreach ($communities as $community): ?><option value="<?= h($community['slug']) ?>"><?= h($community['name']) ?></option><?php endforeach; ?>
  </select>
  <label for="title">Title</label><input id="title" name="title" maxlength="140" autocomplete="off">
  <label for="body">Text (optional)</label><textarea id="body" name="body" rows="5"></textarea>
  <label for="tags">Tags (optional, comma-separated; up to five)</label><input id="tags" name="tags" maxlength="200">
  <label for="image">Image (optional)</label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
  <button class="button primary" type="submit">Post</button>
  <a class="button" href="home.php">Cancel</a>
</form>
