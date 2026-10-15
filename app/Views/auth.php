<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $page === 'login' ? 'Login' : 'Register' ?> · CampusConnect</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth">
<main class="auth-card">
  <?php if ($page === 'login'): ?>
    <img class="logo" src="assets/images/campusLogo.png" alt="">
    <img class="logo" src="assets/images/ccName.png" alt="CampusConnect">
    <p class="tagline">Connect, Share, and Learn Together</p>
    <?php if ($databaseUnavailable): ?>
      <p class="notice error" role="status">A database is unavailable and local storage could not be started. Check that PHP's PDO SQLite extension is enabled.</p>
    <?php elseif ($usingLocalStorage): ?>
      <p class="notice" role="status">MySQL is unavailable or not set up. CampusConnect is using local SQLite storage; the dashboard remains available.</p>
    <?php endif; ?>
    <form method="post" action="login.php">
      <?= $csrf ?>
      <input type="hidden" name="action" value="login">
      <label for="email">Institutional Email</label>
      <input id="email" name="email" type="email" required autocomplete="username">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required autocomplete="current-password">
      <?php flash_message($flash); ?>
      <button class="btn" type="submit">Login</button>
    </form>
    <p class="alt">New moderator? <a href="register.php">Create an account</a></p>
  <?php else: ?>
    <h1>CampusConnect</h1>
    <p class="tagline">Create your student moderator account</p>
    <form method="post" action="register.php">
      <?= $csrf ?>
      <input type="hidden" name="action" value="register">
      <label for="firstName">First name</label><input id="firstName" name="firstName" required autocomplete="given-name">
      <label for="lastName">Last name</label><input id="lastName" name="lastName" required autocomplete="family-name">
      <label for="email">Institutional Email</label><input id="email" name="email" type="email" required autocomplete="username">
      <label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password">
      <label for="confirm">Confirm password</label><input id="confirm" name="confirm" type="password" required autocomplete="new-password">
      <?php flash_message($flash); ?>
      <button class="btn" type="submit">Register</button>
    </form>
    <p class="alt">Already registered? <a href="login.php">Log in</a></p>
  <?php endif; ?>
</main>
</body>
</html>
