<div class="page-heading">
  <h2>User Accounts</h2>
  <p class="sub">View and manage accounts registered in the mobile app.</p>
</div>
<section class="stat-grid login-log-stats" aria-label="Mobile accounts">
  <article class="stat-card">
    <span class="stat-label"><?= $accountSearch === '' ? 'Mobile user accounts' : 'Matching accounts' ?></span>
    <strong><?= (int)$mobileAccounts['total'] ?></strong>
  </article>
</section>
<form class="moderator-search" method="get" action="user-accounts.php" role="search">
  <label for="account-search">Search users</label>
  <input id="account-search" name="q" type="search" value="<?= h($accountSearch) ?>" placeholder="Search by name, username or email">
  <button class="btn-action secondary" type="submit">Search</button>
</form>
<?php foreach ($mobileAccounts['accounts'] as $account): ?>
  <div class="row-item user-list-row moderator-account-row">
    <div class="user-item-info">
      <?php if (!empty($account['profile_pic_url'])): ?>
        <img class="registered-user-avatar" src="<?= h($account['profile_pic_url']) ?>" alt="">
      <?php else: ?>
        <span class="registered-user-avatar registered-user-initial"><?= h(mb_strtoupper(mb_substr((string)$account['full_name'], 0, 1))) ?></span>
      <?php endif; ?>
      <span class="user-list-identity">
        <b><?= h($account['full_name']) ?></b>
        <small>@<?= h($account['username']) ?> · <?= h($account['institutional_email']) ?></small>
        <small><?= h(ucfirst($account['moderation_status'])) ?> · Registered <?= h($account['created_at']) ?></small>
      </span>
    </div>
    <details class="moderator-remove">
      <summary class="btn-action ban">Delete account</summary>
      <p>This permanently deletes the account, its posts and comments, and comments by others on its posts. Uploaded files and login history may remain.</p>
      <form method="post" action="user-accounts.php">
        <?= $csrf ?>
        <input type="hidden" name="action" value="delete-mobile-account">
        <input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>">
        <button class="btn-action ban" type="submit">Confirm deletion</button>
      </form>
    </details>
  </div>
<?php endforeach; ?>
<?php if (!$mobileAccounts['accounts']): ?>
  <p class="empty"><?= $accountSearch === '' ? 'No mobile user accounts have been registered.' : 'No users match your search.' ?></p>
<?php endif; ?>
<?php if ($mobileAccounts['pages'] > 1): ?>
  <?php $pageQuery = $accountSearch === '' ? '' : '&amp;q=' . rawurlencode($accountSearch); ?>
  <nav class="login-log-pagination" aria-label="User account pages">
    <?php if ($mobileAccounts['page'] > 1): ?>
      <a href="user-accounts.php?page=<?= (int)($mobileAccounts['page'] - 1) ?><?= $pageQuery ?>">Previous</a>
    <?php endif; ?>
    <span>Page <?= (int)$mobileAccounts['page'] ?> of <?= (int)$mobileAccounts['pages'] ?></span>
    <?php if ($mobileAccounts['page'] < $mobileAccounts['pages']): ?>
      <a href="user-accounts.php?page=<?= (int)($mobileAccounts['page'] + 1) ?><?= $pageQuery ?>">Next</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
