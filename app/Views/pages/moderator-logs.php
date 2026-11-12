<div class="page-heading">
  <h2>Student Moderator Login Logs</h2>
  <p class="sub">Recent Student Moderator sign-ins and recorded sign-outs.</p>
</div>
<div class="login-log-table-wrap">
  <table class="login-log-table">
    <thead><tr><th>Moderator</th><th>Email</th><th>Logged in</th><th>Ended</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($moderatorSessions as $session): ?>
        <tr>
          <td><?= h($session['moderator_name']) ?></td>
          <td><?= h($session['moderator_email']) ?></td>
          <td><?= h($session['login_at']) ?></td>
          <td><?= h($session['logout_at'] ?: 'Not recorded') ?></td>
          <td><span class="login-log-status <?= $session['end_reason'] === null ? 'inactive' : 'logged-out' ?>"><?= h(match ($session['end_reason']) {
              'logout' => 'Logged out',
              'suspended' => 'Account suspended',
              'removed' => 'Account removed',
              default => 'No sign-out recorded',
          }) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$moderatorSessions): ?>
        <tr><td class="login-log-empty" colspan="5">No Student Moderator sign-ins have been recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<p class="login-log-note">Sessions are recorded after this feature is deployed. Closing a browser may not record a sign-out.</p>
