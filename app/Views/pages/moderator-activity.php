<div class="page-heading">
  <h2>Moderator Activity</h2>
  <p class="sub">Recent moderation actions and administrator account decisions. Activity is recorded from deployment onward.</p>
</div>
<div class="login-log-table-wrap">
  <table class="login-log-table">
    <thead><tr><th>Actor</th><th>Action</th><th>Target</th><th>Reason</th><th>Time</th></tr></thead>
    <tbody>
      <?php foreach ($moderationActivity as $activity): ?>
        <tr>
          <td><?= h($activity['actor_name']) ?><br><small><?= h($activity['actor_email']) ?> · <?= h(ucfirst($activity['actor_role'])) ?></small></td>
          <td><?= h(ucfirst(str_replace('-', ' ', $activity['action']))) ?></td>
          <td><?= h(ucfirst($activity['target_type'])) ?> #<?= (int)$activity['target_id'] ?><?php if ($activity['target_label']): ?><br><small><?= h($activity['target_label']) ?></small><?php endif; ?></td>
          <td><?= h($activity['reason'] ?: '—') ?></td>
          <td><?= h($activity['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$moderationActivity): ?>
        <tr><td class="login-log-empty" colspan="5">No moderation activity has been recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<p class="login-log-note">Showing the latest 200 recorded actions.</p>
