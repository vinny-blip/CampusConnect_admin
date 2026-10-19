<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h(ucfirst($page)) ?> · CampusConnect</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= (int)filemtime(dirname(__DIR__, 2) . '/public/assets/css/style.css') ?>">
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
        <a class="icon-btn" href="messages.php" aria-label="Messages" title="Messages">💬<span class="badge"><?= $messageCount ?: '' ?></span></a>
        <a class="create" href="create.php"><span aria-hidden="true">＋</span> Create Post</a>
        <a class="icon-btn" href="notifications.php" aria-label="Notifications" title="Notifications">🔔<span class="badge"><?= $noteCount ?: '' ?></span></a>
        <a class="avatar" href="profile.php" aria-label="Profile" title="<?= h($user['name']) ?>"><?= h(initials($user['name'])) ?></a>
      </div>
    </header>
    <div class="body">
      <nav class="sidebar" aria-label="Main">
        <a class="<?= $page === 'home' ? 'on' : '' ?>" href="home.php"><span class="ico" aria-hidden="true">⌂</span>Home</a>
        <a class="<?= $page === 'newest' ? 'on' : '' ?>" href="newest.php"><span class="ico" aria-hidden="true">↗</span>Newest</a>
        <a class="<?= $page === 'control' ? 'on' : '' ?>" href="control.php">Control Posts/Feed</a>
        <h4><a class="<?= $page === 'communities' ? 'on' : '' ?>" href="communities.php">Moderating</a></h4>
        <a class="<?= $page === 'queues' ? 'on' : '' ?>" href="queues.php"><span class="ico" aria-hidden="true">▣</span>Queues</a>
        <a class="<?= $page === 'modmail' ? 'on' : '' ?>" href="modmail.php"><span class="ico" aria-hidden="true">✉</span>Mod Mail</a>
        <a class="community-nav <?= $page === 'designatmobbin' || ($page === 'community' && ($_GET['c'] ?? '') === 'designatmobbin') ? 'on' : '' ?>" href="designatmobbin.php"><span class="ico" aria-hidden="true">●</span><span>r/designatmobbin</span></a>
        <form class="logout-form" method="post" action="<?= h($page) ?>.php">
          <?= $csrf ?>
          <input type="hidden" name="action" value="logout">
          <button class="logout" type="submit">Log out</button>
        </form>
      </nav>
      <main class="main">
        <?php
          $tabPages = [
              'overview' => 'Overview', 'posts' => 'Posts', 'comments' => 'Comments',
              'saved' => 'Saved', 'restricted' => 'Restricted', 'reported' => 'Reported',
          ];
          $shortPages = ['post' => 'Posts'];
          $tabSet = in_array($page, ['overview', 'posts', 'comments', 'saved', 'restricted', 'reported', 'control', 'post'], true);
          if ($tabSet):
        ?>
          <nav class="tabs" aria-label="Moderation">
            <?php foreach ($tabPages as $tabPage => $tabLabel): ?>
              <?php if ($tabPage === 'restricted' && ($user['role'] ?? '') !== 'admin') continue; ?>
              <?php if ($page === 'control' && !in_array($tabPage, ['overview', 'posts', 'comments', 'saved', 'reported'], true)) continue; ?>
              <?php if ($page === 'post' && !in_array($tabPage, ['overview', 'posts', 'reported'], true)) continue; ?>
              <a href="<?= h($tabPage) ?>.php" class="<?= ($page === $tabPage || ($page === 'post' && $tabPage === 'posts')) ? 'on' : '' ?>"><?= h($tabLabel) ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
        <div class="page<?= $page === 'queues' ? ' fill' : '' ?>">
          <?php flash_message($flash); ?>
          <?php require __DIR__ . '/pages.php'; ?>
        </div>
      </main>
    </div>
  </div>
</div>
</body>
</html>
