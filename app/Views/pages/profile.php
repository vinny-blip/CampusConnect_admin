<h2>Profile</h2>
<div class="profile-summary">
  <?php if (!empty($user['profile_image'])): ?>
    <span class="avatar big profile-image"><img src="<?= h($user['profile_image']) ?>" alt="Profile picture"></span>
  <?php else: ?>
    <span class="avatar big"><?= h(initials($user['name'])) ?></span>
  <?php endif; ?>
  <div><b><?= h($user['name']) ?></b><p class="sub"><?= h($user['email']) ?> • Student moderator</p></div>
</div>
<form class="form" method="post">
  <?= $csrf ?><input type="hidden" name="action" value="update-name">
  <label for="name">Display name</label><input id="name" name="name" value="<?= h($user['name']) ?>" required>
  <button class="button primary">Save changes</button>
</form>
<form class="form profile-form" method="post" enctype="multipart/form-data">
  <?= $csrf ?><input type="hidden" name="action" value="update-profile-picture">
  <label for="profile-picture">Profile picture</label>
  <input id="profile-picture" name="profile_picture" type="file" accept="image/jpeg,image/png,image/webp" required>
  <small>JPG, PNG, or WebP; maximum 5 MB.</small>
  <button class="button primary">Upload picture</button>
</form>
<form class="form profile-form" method="post">
  <?= $csrf ?><input type="hidden" name="action" value="change-password">
  <h3>Change password</h3>
  <label for="current-password">Current password</label>
  <span class="password-field">
    <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
    <button class="password-visibility-toggle" type="button" aria-controls="current-password" aria-pressed="false">Show</button>
  </span>
  <label for="new-password">New password</label>
  <span class="password-field">
    <input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="8" required>
    <button class="password-visibility-toggle" type="button" aria-controls="new-password" aria-pressed="false">Show</button>
  </span>
  <label for="confirm-password">Confirm new password</label>
  <span class="password-field">
    <input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
    <button class="password-visibility-toggle" type="button" aria-controls="confirm-password" aria-pressed="false">Show</button>
  </span>
  <button class="button primary">Change password</button>
</form>
<script>
  document.querySelectorAll('.password-visibility-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const field = document.getElementById(toggle.getAttribute('aria-controls'));
      const isVisible = field.type === 'text';
      field.type = isVisible ? 'password' : 'text';
      toggle.textContent = isVisible ? 'Show' : 'Hide';
      toggle.setAttribute('aria-pressed', String(!isVisible));
    });
  });
</script>
