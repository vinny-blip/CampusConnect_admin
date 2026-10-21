<h2>Create Community</h2>
<p class="sub">Set up a new community. You can moderate <?= max(0, 10 - (int)$communityCount) ?> more.</p>
<?php if ($communityCount >= 10): ?>
  <p class="notice error" role="status">You’re at the limit of 10 communities. <a href="communities.php">Manage your communities</a> to leave one before creating another.</p>
<?php else: ?>
  <form class="form" method="post">
    <?= $csrf ?><input type="hidden" name="action" value="create-community">
    <label for="community-name">Community name</label>
    <input id="community-name" name="community_name" maxlength="60" minlength="3" autocomplete="off" required>
    <label for="community-category">Category</label>
    <select id="community-category" name="community_category" required>
      <option value="">Choose a category</option>
      <option>Academic Department</option>
      <option>Course or Subject</option>
      <option>Student Organization</option>
      <option>Other</option>
    </select>
    <label for="community-description">Description</label>
    <textarea id="community-description" name="community_description" rows="5" maxlength="500" required></textarea>
    <button class="button primary" type="submit">Create Community</button>
    <a class="button" href="communities.php">Cancel</a>
  </form>
<?php endif; ?>
