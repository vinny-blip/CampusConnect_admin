<span hidden data-moderator-accounts-page></span>
<div class="page-heading">
  <h2>Student Moderator Accounts</h2>
  <p class="sub">Manage Student Moderator access to the dashboard.</p>
</div>
<section class="stat-grid login-log-stats" aria-label="Moderator accounts">
  <article class="stat-card">
    <span class="stat-label">Student moderators</span>
    <strong><?= count($moderators) ?></strong>
  </article>
  <article class="stat-card">
    <span class="stat-label">Suspended</span>
    <strong><?= count(array_filter($moderators, static fn(array $moderator): bool => (bool)$moderator['suspended'])) ?></strong>
  </article>
</section>
<?php foreach ($moderators as $moderator): ?>
  <div class="row-item user-list-row moderator-account-row" data-moderator-row="<?= (int)$moderator['id'] ?>" data-suspended="<?= $moderator['suspended'] ? '1' : '0' ?>">
    <div class="user-item-info">
      <?php if (!empty($moderator['profile_image'])): ?>
        <img class="registered-user-avatar" src="<?= h($moderator['profile_image']) ?>" alt="">
      <?php else: ?>
        <span class="registered-user-avatar registered-user-initial"><?= h(mb_strtoupper(mb_substr((string)$moderator['name'], 0, 1))) ?></span>
      <?php endif; ?>
      <span class="user-list-identity">
        <b><?= h($moderator['name']) ?></b>
        <small><?= h($moderator['email']) ?> · <?= $moderator['suspended'] ? 'Suspended' : 'Active' ?></small>
        <?php if ($moderator['suspended'] && !empty($moderator['suspended_until'])): ?>
          <small>Lifts <time data-suspension-lift data-utc="<?= h((new DateTimeImmutable((string)$moderator['suspended_until'], new DateTimeZone('UTC')))->format(DATE_ATOM)) ?>"><?= h($moderator['suspended_until']) ?> UTC</time></small>
        <?php endif; ?>
        <small>Registered <?= h($moderator['created_at']) ?></small>
      </span>
    </div>
    <div class="moderator-actions">
      <?php if ($moderator['suspended']): ?>
        <form method="post" action="home.php">
          <?= $csrf ?>
          <input type="hidden" name="moderator_id" value="<?= (int)$moderator['id'] ?>">
          <input type="hidden" name="action" value="restore-moderator">
          <button class="btn-action approve" type="submit">Restore</button>
        </form>
      <?php else: ?>
        <button class="btn-action remove" type="button" data-open-suspension data-user-id="<?= (int)$moderator['id'] ?>">Suspend</button>
      <?php endif; ?>
      <details class="moderator-remove">
        <summary class="btn-action ban">Remove account</summary>
        <p>Permanently delete this account and its dashboard data?</p>
        <form method="post" action="home.php">
          <?= $csrf ?>
          <input type="hidden" name="moderator_id" value="<?= (int)$moderator['id'] ?>">
          <input type="hidden" name="action" value="remove-moderator">
          <button class="btn-action ban" type="submit">Confirm removal</button>
        </form>
      </details>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$moderators): ?><p class="empty">No Student Moderator accounts yet.</p><?php endif; ?>
<?php
  $suspensionAction = 'suspend-moderator';
  $suspensionIdName = 'moderator_id';
  $suspensionTitle = 'Suspend moderator';
  require dirname(__DIR__) . '/partials/suspension-dialog.php';
?>
