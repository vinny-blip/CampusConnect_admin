<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h(ucfirst($page)) ?> · CampusConnect</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/base.css?v=<?= (int)filemtime(dirname(__DIR__, 2) . '/public/assets/css/base.css') ?>">
  <link rel="stylesheet" href="assets/css/layout.css?v=<?= (int)filemtime(dirname(__DIR__, 2) . '/public/assets/css/layout.css') ?>">
  <link rel="stylesheet" href="assets/css/components.css?v=<?= (int)filemtime(dirname(__DIR__, 2) . '/public/assets/css/components.css') ?>">
  <link rel="stylesheet" href="assets/css/moderation.css?v=<?= (int)filemtime(dirname(__DIR__, 2) . '/public/assets/css/moderation.css') ?>">
  <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= (int)filemtime(dirname(__DIR__, 2) . '/public/assets/css/dashboard.css') ?>">
</head>
<body class="dash">
<div id="app">
  <div class="shell">
    <header class="topbar">
      <a class="brand" href="home.php"><img src="assets/images/campusLogo.png" alt="">CampusConnect</a>
      <form class="searchform" action="search.php" method="get" role="search">
        <input id="search" class="search" name="q" type="search" placeholder="Search CampusConnect" aria-label="Search CampusConnect" value="<?= h($_GET['q'] ?? '') ?>">
      </form>
      <div class="top-actions">
        <a class="icon-btn" href="notifications.php" aria-label="Notifications<?= $noteCount ? ', ' . (int)$noteCount . ' unread' : '' ?>" title="Notifications">
          <?= icon_svg('bell') ?><span class="badge<?= $noteCount ? ' has-unread' : '' ?>" data-unread="<?= (int)$noteCount ?>" aria-live="polite"><?= $noteCount ?: '' ?></span>
        </a>
        <a class="avatar<?= !empty($user['profile_image']) ? ' profile-image' : '' ?>" href="profile.php" aria-label="Profile" title="<?= h($user['name']) ?>"><?php if (!empty($user['profile_image'])): ?><img src="<?= h($user['profile_image']) ?>" alt=""><?php else: ?><?= h(initials($user['name'])) ?><?php endif; ?></a>
      </div>
    </header>
    <div class="body">
      <nav class="sidebar" aria-label="Main">
        <a class="<?= $page === 'home' ? 'on' : '' ?>" href="home.php"><span class="ico"><?= icon_svg('home') ?></span>Overview</a>
        <a class="<?= in_array($page, ['control', 'posts', 'comments', 'reported', 'removed', 'dismissed', 'post', 'restricted'], true) ? 'on' : '' ?>" href="control.php"><span class="ico"><?= icon_svg('sliders') ?></span>Control Feed</a>
        <a class="<?= $page === 'users' || $page === 'user' ? 'on' : '' ?>" href="users.php"><span class="ico"><?= icon_svg('users') ?></span>Users</a>
        <a class="<?= $page === 'logs' ? 'on' : '' ?>" href="logs.php"><span class="ico"><?= icon_svg('history') ?></span>Logs</a>
        <form class="logout-form" method="post" action="<?= h($page) ?>.php">
          <?= $csrf ?>
          <input type="hidden" name="action" value="logout">
          <button class="logout" type="submit">Log out</button>
        </form>
      </nav>
      <main class="main">
        <?php
          $tabPages = [
              'posts' => 'Posts', 'comments' => 'Comments',
              'restricted' => 'Restricted', 'reported' => 'Reported', 'removed' => 'Removed',
              'dismissed' => 'Dismissed',
          ];
          $shortPages = ['post' => 'Posts'];
          $tabSet = in_array($page, ['posts', 'comments', 'restricted', 'reported', 'removed', 'dismissed', 'control', 'post'], true);
          if ($tabSet):
        ?>
          <nav class="tabs" aria-label="Moderation">
            <?php foreach ($tabPages as $tabPage => $tabLabel): ?>
              <?php if ($tabPage === 'restricted' && ($user['role'] ?? '') !== 'admin') continue; ?>
              <?php if ($page === 'control' && !in_array($tabPage, ['posts', 'comments', 'reported', 'removed', 'dismissed'], true)) continue; ?>
              <?php if ($page === 'post' && !in_array($tabPage, ['posts', 'reported', 'removed', 'dismissed'], true)) continue; ?>
              <a href="<?= h($tabPage) ?>.php" class="<?= ($page === $tabPage || (in_array($page, ['post', 'control'], true) && $tabPage === 'posts')) ? 'on' : '' ?>"><?= h($tabLabel) ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
        <div class="page<?= in_array($page, ['reported', 'dismissed'], true) ? ' report-queue-page' : '' ?>">
          <?php flash_message($flash); ?>
          <?php require __DIR__ . '/pages.php'; ?>
        </div>
      </main>
    </div>
  </div>
</div>
<script>
  (() => {
    const activeUsers = document.querySelector('[data-active-user-count]');
    // Refresh the Logs count and rows while the page is visible.
    const updateActiveUsers = async () => {
      if (!activeUsers || document.visibilityState !== 'visible') return;
      try {
        const response = await fetch('logs.php?poll=1', {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`Active user check failed (${response.status}).`);
        const data = await response.json();
        if (Number.isInteger(data.active_users) && data.active_users >= 0) {
          activeUsers.textContent = String(data.active_users);
        }
        const rows = document.querySelector('[data-login-log-rows]');
        if (rows && Array.isArray(data.sessions)) {
          rows.replaceChildren();
          if (data.sessions.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.className = 'login-log-empty';
            cell.colSpan = 7;
            cell.textContent = 'No mobile app login activity has been recorded yet.';
            row.append(cell);
            rows.append(row);
          } else {
            for (const session of data.sessions) {
              const row = document.createElement('tr');
              const logoutAt = session.logout_at || session.estimated_logout_at;
              const values = [
                session.full_name,
                session.username,
                session.institutional_email,
                session.login_at,
                session.last_activity_at,
                session.logout_at || (logoutAt ? `~${logoutAt}` : '—'),
              ];
              for (const value of values) {
                const cell = document.createElement('td');
                cell.textContent = value || '';
                row.append(cell);
              }
              const statusCell = document.createElement('td');
              const status = document.createElement('span');
              status.className = `login-log-status ${session.status_class}`;
              status.textContent = session.session_status || '';
              statusCell.append(status);
              row.append(statusCell);
              rows.append(row);
            }
          }
        }
      } catch (error) {
        console.error('Unable to update mobile app active user count.', error);
      }
    };
    if (activeUsers) {
      document.addEventListener('visibilitychange', updateActiveUsers);
      window.setInterval(updateActiveUsers, 30000);
      updateActiveUsers();
    }

    const usersPage = document.querySelector('[data-users-page]');
    const userRows = document.querySelectorAll('[data-mobile-user-row]');
    document.querySelectorAll('[data-suspension-lift][data-utc]').forEach((liftTime) => {
      const timestamp = new Date(liftTime.dataset.utc);
      if (!Number.isNaN(timestamp.getTime())) {
        liftTime.textContent = timestamp.toLocaleString(undefined, {
          dateStyle: 'medium',
          timeStyle: 'short',
        });
      }
    });
    // Poll Users so expired suspensions are restored even if the page stays open.
    const refreshExpiredSuspensions = async () => {
      if (!usersPage || document.visibilityState !== 'visible') return;
      try {
        const timezoneOffset = new Date().getTimezoneOffset();
        const response = await fetch(`users.php?poll=1&timezone_offset=${timezoneOffset}`, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store',
        });
        if (!response.ok) throw new Error(`Suspension check failed (${response.status}).`);
        const data = await response.json();
        if (!Array.isArray(data.users)) throw new Error('Suspension check returned invalid user data.');
        if (Number(data.restored) > 0) {
          window.location.reload();
          return;
        }
        const statuses = new Map(data.users.map((user) => [String(user.id), user.moderation_status]));
        const changed = Array.from(userRows).some((row) => {
          const userId = row.dataset.mobileUserRow;
          const statusNode = row.querySelector('[data-mobile-user-status]');
          const currentStatus = statusNode.textContent.includes('suspended')
            ? 'suspended'
            : (statusNode.textContent.includes('banned') ? 'banned' : 'active');
          return statuses.has(userId) && statuses.get(userId) !== currentStatus;
        });
        if (changed) window.location.reload();
      } catch (error) {
        console.error('Unable to check for expired user suspensions.', error);
      }
    };
    if (usersPage) {
      document.addEventListener('visibilitychange', refreshExpiredSuspensions);
      window.setInterval(refreshExpiredSuspensions, 15000);
      refreshExpiredSuspensions();
    }

    const suspensionDialog = document.querySelector('[data-suspension-dialog]');
    if (suspensionDialog) {
      const suspensionUserId = suspensionDialog.querySelector('[data-suspension-user-id]');
      const suspensionValue = suspensionDialog.querySelector('[data-suspension-value]');
      const suspensionTimezone = suspensionDialog.querySelector('[data-suspension-timezone]');
      const monthLabel = suspensionDialog.querySelector('[data-calendar-month]');
      const calendarDays = suspensionDialog.querySelector('[data-calendar-days]');
      const selectedDateLabel = suspensionDialog.querySelector('[data-suspension-selection]');
      const hourSelect = suspensionDialog.querySelector('[data-suspension-hour]');
      const minuteSelect = suspensionDialog.querySelector('[data-suspension-minute]');
      const periodSelect = suspensionDialog.querySelector('[data-suspension-period]');
      const submitSuspension = suspensionDialog.querySelector('[data-suspension-submit]');
      let calendarMonth = new Date();
      let selectedDate = '';

      // Formats a local calendar date without converting it through UTC.
      const dateKey = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      };

      // Rebuilds the custom calendar and disables dates before today.
      const renderSuspensionCalendar = () => {
        const year = calendarMonth.getFullYear();
        const month = calendarMonth.getMonth();
        monthLabel.textContent = calendarMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        calendarDays.replaceChildren();

        const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = dateKey(new Date());
        for (let index = 0; index < firstWeekday; index++) {
          const spacer = document.createElement('span');
          spacer.className = 'calendar-day-spacer';
          spacer.setAttribute('aria-hidden', 'true');
          calendarDays.append(spacer);
        }
        for (let day = 1; day <= daysInMonth; day++) {
          const date = new Date(year, month, day);
          const key = dateKey(date);
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'calendar-day';
          button.textContent = String(day);
          button.dataset.date = key;
          button.setAttribute('aria-label', date.toLocaleDateString(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
          }));
          if (key === today) button.classList.add('today');
          if (key === selectedDate) {
            button.classList.add('selected');
            button.setAttribute('aria-pressed', 'true');
          } else {
            button.setAttribute('aria-pressed', 'false');
          }
          if (key < today) button.disabled = true;
          calendarDays.append(button);
        }
        const previousMonth = new Date(year, month - 1, 1);
        suspensionDialog.querySelector('[data-calendar-previous]').disabled =
          previousMonth.getFullYear() < new Date().getFullYear()
          || (previousMonth.getFullYear() === new Date().getFullYear() && previousMonth.getMonth() < new Date().getMonth());
      };

      // Updates the preview and only enables submission for a future lift time.
      const updateSuspensionSelection = () => {
        const hour = Number(hourSelect.value);
        const minute = minuteSelect.value;
        const period = periodSelect.value;
        if (!selectedDate || !hour || minute === '' || !period) {
          suspensionValue.value = '';
          selectedDateLabel.textContent = selectedDate
            ? 'Choose a lift time to continue.'
            : 'Select a date and time.';
          submitSuspension.disabled = true;
          return;
        }

        let hour24 = hour % 12;
        if (period === 'PM') hour24 += 12;
        const time = `${String(hour24).padStart(2, '0')}:${String(Number(minute)).padStart(2, '0')}`;
        const liftDateTime = new Date(`${selectedDate}T${time}:00`);
        const isFuture = liftDateTime.getTime() > Date.now();
        suspensionValue.value = isFuture ? `${selectedDate}T${time}` : '';
        suspensionTimezone.value = String(liftDateTime.getTimezoneOffset());
        selectedDateLabel.textContent = `${liftDateTime.toLocaleDateString(undefined, {
          weekday: 'long',
          month: 'long',
          day: 'numeric',
          year: 'numeric',
        })} at ${liftDateTime.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`;
        if (!isFuture) selectedDateLabel.textContent += ' — choose a future time.';
        submitSuspension.disabled = !isFuture;
      };

      document.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-open-suspension]');
        if (openButton) {
          suspensionUserId.value = openButton.dataset.userId || '';
          selectedDate = '';
          suspensionValue.value = '';
          suspensionTimezone.value = '';
          hourSelect.value = '';
          minuteSelect.value = '';
          periodSelect.value = '';
          calendarMonth = new Date();
          updateSuspensionSelection();
          renderSuspensionCalendar();
          suspensionDialog.showModal();
          return;
        }
        if (event.target.closest('[data-close-suspension]')) {
          suspensionDialog.close();
          return;
        }
        const calendarButton = event.target.closest('[data-calendar-days] .calendar-day');
        if (calendarButton && !calendarButton.disabled) {
          selectedDate = calendarButton.dataset.date;
          renderSuspensionCalendar();
          updateSuspensionSelection();
          return;
        }
        if (event.target.closest('[data-calendar-previous]')) {
          calendarMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() - 1, 1);
          renderSuspensionCalendar();
          return;
        }
        if (event.target.closest('[data-calendar-next]')) {
          calendarMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 1);
          renderSuspensionCalendar();
        }
      });
      [hourSelect, minuteSelect, periodSelect].forEach((select) => {
        select.addEventListener('change', updateSuspensionSelection);
      });
      suspensionDialog.querySelector('form').addEventListener('submit', (event) => {
        updateSuspensionSelection();
        if (!suspensionValue.value) {
          event.preventDefault();
          selectedDateLabel.focus();
        }
      });
      suspensionDialog.addEventListener('click', (event) => {
        if (event.target === suspensionDialog) suspensionDialog.close();
      });
    }

    const badge = document.querySelector('.top-actions .badge');
    const notificationLink = document.querySelector('.top-actions .icon-btn');
    if (!badge || !notificationLink) return;

    let unreadCount = Number(badge.dataset.unread || 0);
    const isNotificationsPage = window.location.pathname.endsWith('/notifications.php');
    // Keeps the notification badge and accessible label in sync with unread items.
    const updateBadge = (count) => {
      badge.dataset.unread = String(count);
      badge.textContent = count > 99 ? '99+' : (count > 0 ? String(count) : '');
      badge.classList.toggle('has-unread', count > 0);
      notificationLink.setAttribute('aria-label', count > 0 ? `Notifications, ${count} unread` : 'Notifications');
      notificationLink.title = count > 0 ? `${count} unread notification${count === 1 ? '' : 's'}` : 'Notifications';
    };

    // Retrieves the latest unread count and refreshes the notification badge.
    const pollNotifications = async () => {
      if (document.visibilityState !== 'visible') return;
      try {
        const response = await fetch('notifications.php?poll=1', {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`Notification check failed (${response.status}).`);
        const data = await response.json();
        const nextCount = Number(data.unread);
        if (!Number.isInteger(nextCount) || nextCount < 0) {
          throw new Error('Notification check returned an invalid unread count.');
        }
        const hasNewNotification = nextCount > unreadCount;
        unreadCount = nextCount;
        updateBadge(unreadCount);
        if (isNotificationsPage && hasNewNotification) window.location.reload();
      } catch (error) {
        console.error('Unable to check for new notifications.', error);
      }
    };

    window.setInterval(pollNotifications, 30000);
    document.addEventListener('visibilitychange', pollNotifications);
  })();
</script>
</body>
</html>
