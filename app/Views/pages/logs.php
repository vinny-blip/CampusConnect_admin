<div class="page-heading">
  <h2>Mobile App Login Logs</h2>
  <p class="sub">Recent sign-ins and sign-outs from CampusConnect mobile accounts. A user is counted as active when their app has sent activity within the last 5 minutes.</p>
</div>
<section class="stat-grid login-log-stats" aria-label="Current sessions">
  <article class="stat-card">
    <span class="stat-label">Mobile users currently logged in</span>
    <strong data-active-user-count><?= (int)$activeUserCount ?></strong>
  </article>
</section>
<div class="login-log-table-wrap">
  <table class="login-log-table">
    <thead>
      <tr>
        <th>Mobile user</th>
        <th>Username</th>
        <th>Email</th>
        <th>Logged in</th>
        <th>Last activity</th>
        <th>Logged out</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody data-login-log-rows>
      <!-- Show each mobile session and use an estimate when explicit logout is missing. -->
      <?php foreach ($loginSessions as $session): ?>
        <?php
          $loggedOutAt = $session['logout_at'] ?: $session['estimated_logout_at'];
          $statusClass = $session['status_class'];
        ?>
        <tr>
          <td><?= h($session['full_name']) ?></td>
          <td><?= h($session['username']) ?></td>
          <td><?= h($session['institutional_email']) ?></td>
          <td><?= h($session['login_at']) ?></td>
          <td><?= h($session['last_activity_at']) ?></td>
          <td>
            <?php if ($session['logout_at']): ?>
              <?= h($session['logout_at']) ?>
            <?php elseif ($loggedOutAt): ?>
              ~<?= h($loggedOutAt) ?>
            <?php else: ?>
              <span class="login-log-dash">—</span>
            <?php endif; ?>
          </td>
          <td><span class="login-log-status <?= h($statusClass) ?>"><?= h($session['session_status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$loginSessions): ?>
        <tr><td class="login-log-empty" colspan="7">No mobile app login activity has been recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<p class="login-log-note">A logout time marked with “~” is estimated from the last mobile-app activity after 5 minutes without a heartbeat. Explicit app logouts are recorded exactly. Mobile users need the updated app and backend deployed for their sessions to be tracked.</p>
