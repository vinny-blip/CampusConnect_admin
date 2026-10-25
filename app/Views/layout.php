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
        <a class="<?= $page === 'home' ? 'on' : '' ?>" href="home.php"><span class="ico"><?= icon_svg('home') ?></span>Home</a>
        <a class="<?= in_array($page, ['control', 'posts', 'comments', 'reported', 'removed', 'post', 'restricted'], true) ? 'on' : '' ?>" href="control.php"><span class="ico"><?= icon_svg('sliders') ?></span>Control Posts/Feed</a>
        <a class="<?= $page === 'users' ? 'on' : '' ?>" href="users.php"><span class="ico"><?= icon_svg('users') ?></span>Users</a>
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
              'restricted' => 'Restricted', 'reported' => 'Reported', 'removed' => 'Removed Posts',
          ];
          $shortPages = ['post' => 'Posts'];
          $tabSet = in_array($page, ['posts', 'comments', 'restricted', 'reported', 'removed', 'control', 'post'], true);
          if ($tabSet):
        ?>
          <nav class="tabs" aria-label="Moderation">
            <?php foreach ($tabPages as $tabPage => $tabLabel): ?>
              <?php if ($tabPage === 'restricted' && ($user['role'] ?? '') !== 'admin') continue; ?>
              <?php if ($page === 'control' && !in_array($tabPage, ['posts', 'comments', 'reported', 'removed'], true)) continue; ?>
              <?php if ($page === 'post' && !in_array($tabPage, ['posts', 'reported', 'removed'], true)) continue; ?>
              <a href="<?= h($tabPage) ?>.php" class="<?= ($page === $tabPage || (in_array($page, ['post', 'control'], true) && $tabPage === 'posts')) ? 'on' : '' ?>"><?= h($tabLabel) ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
        <div class="page">
          <?php flash_message($flash); ?>
          <?php require __DIR__ . '/pages.php'; ?>
        </div>
      </main>
    </div>
  </div>
</div>
<script>
  (() => {
    const badge = document.querySelector('.top-actions .badge');
    const notificationLink = document.querySelector('.top-actions .icon-btn');
    if (!badge || !notificationLink) return;

    let unreadCount = Number(badge.dataset.unread || 0);
    const isNotificationsPage = window.location.pathname.endsWith('/notifications.php');
    const updateBadge = (count) => {
      badge.dataset.unread = String(count);
      badge.textContent = count > 99 ? '99+' : (count > 0 ? String(count) : '');
      badge.classList.toggle('has-unread', count > 0);
      notificationLink.setAttribute('aria-label', count > 0 ? `Notifications, ${count} unread` : 'Notifications');
      notificationLink.title = count > 0 ? `${count} unread notification${count === 1 ? '' : 's'}` : 'Notifications';
    };

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
