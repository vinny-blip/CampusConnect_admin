<div class="page-heading">
  <h2>Student Moderator Login Logs</h2>
  <p class="sub">Recent Student Moderator sign-ins and recorded sign-outs.</p>
</div>
<div class="login-log-table-wrap">
  <table class="login-log-table">
    <thead><tr><th>Moderator</th><th>Email</th><th>Logged in</th><th>Ended</th><th>Status</th></tr></thead>
    <tbody data-moderator-log-rows>
      <?php foreach ($moderatorSessions as $session): ?>
        <?php
          $state = $session['state'];
          $statusClass = match ($state) {
              'signed-in' => 'active',
              'timed-out' => 'timed-out',
              default => 'logged-out',
          };
          $endedAt = $session['logout_at']
              ?: ($state === 'timed-out' ? '~' . $session['estimated_logout_at'] : 'Not recorded');
        ?>
        <tr data-moderator-log-id="<?= (int)$session['id'] ?>" data-moderator-log-state="<?= h($state) ?>">
          <td><?= h($session['moderator_name']) ?></td>
          <td><?= h($session['moderator_email']) ?></td>
          <td><?= h($session['login_at']) ?></td>
          <td><?= h($endedAt) ?></td>
          <td><span class="login-log-status <?= h($statusClass) ?>"><?= h(match ($state) {
              'signed-in' => 'Signed in',
              'logout' => 'Logged out',
              'suspended' => 'Account suspended',
              'removed' => 'Account removed',
              'timed-out' => 'Timed out',
              default => 'Timed out',
          }) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$moderatorSessions): ?>
        <tr><td class="login-log-empty" colspan="5">No Student Moderator sign-ins have been recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<p class="login-log-note">Sessions without a recorded sign-out are marked timed out after 5 minutes of inactivity; the end time is estimated.</p>
