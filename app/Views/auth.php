<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $page === 'login' ? 'Login' : ($page === 'register' ? 'Register' : 'Forgot password') ?> · CampusConnect</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/base.css?v=<?= (int)filemtime(dirname(__DIR__, 2) . '/public/assets/css/base.css') ?>">
  <link rel="stylesheet" href="assets/css/auth.css?v=<?= (int)filemtime(dirname(__DIR__, 2) . '/public/assets/css/auth.css') ?>">
</head>
<body class="auth">
<main class="auth-card">
  <?php if ($page === 'login'): ?>
    <img class="logo" src="assets/images/campusLogo.png" alt="">
    <img class="logo" src="assets/images/ccName.png" alt="CampusConnect">
    <p class="tagline">Connect, Share, and Learn Together</p>
    <form method="post" action="login.php">
      <?= $csrf ?>
      <input type="hidden" name="action" value="login">
      <label for="email">Institutional Email</label>
      <input id="email" name="email" type="email" required autocomplete="username">
      <label for="password">Password</label>
      <span class="auth-password-field">
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button class="auth-password-toggle" type="button" aria-label="Show password" aria-controls="password" aria-pressed="false" title="Show password" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path>
            <circle cx="12" cy="12" r="3"></circle>
            <path class="auth-password-slash" d="M4 4l16 16"></path>
          </svg>
        </button>
      </span>
      <label for="role">Role</label>
      <select id="role" name="role" required>
        <option value="" selected disabled>Select your role</option>
        <option value="moderator">Student Moderator</option>
        <option value="admin">Admin</option>
      </select>
      <?php flash_message($flash); ?>
      <button class="btn" type="submit">Login</button>
    </form>
    <p class="alt">New moderator? <a href="register.php">Create an account</a></p>
    <p class="alt"><a href="forgot-password.php">Forgot password?</a></p>
  <?php elseif ($page === 'register'): ?>
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
  <?php else: ?>
    <h1>Forgot password?</h1>
    <p class="tagline">Get a temporary password by email</p>
    <p class="auth-recovery-note">Enter the email and role for your account. If it matches, we'll email a temporary password that expires in 30 minutes. Use it to log in, then change your password in Profile. If no email arrives, check spam or contact an administrator.</p>
    <form method="post" action="forgot-password.php">
      <?= $csrf ?>
      <input type="hidden" name="action" value="request-temporary-password">
      <label for="recovery-email">Institutional Email</label>
      <input id="recovery-email" name="email" type="email" required autocomplete="email">
      <label for="recovery-role">Role</label>
      <select id="recovery-role" name="role" required>
        <option value="" selected disabled>Select your role</option>
        <option value="moderator">Student Moderator</option>
        <option value="admin">Admin</option>
      </select>
      <?php flash_message($flash); ?>
      <button class="btn" type="submit">Send temporary password</button>
    </form>
    <p class="alt">Remembered it? <a href="login.php">Back to login</a></p>
  <?php endif; ?>
</main>
<script>
  const passwordToggle = document.querySelector('.auth-password-toggle');
  if (passwordToggle) {
    const passwordField = document.getElementById(passwordToggle.getAttribute('aria-controls'));
    passwordToggle.hidden = false;
    passwordToggle.addEventListener('click', () => {
      const visible = passwordField.type === 'password';
      passwordField.type = visible ? 'text' : 'password';
      passwordToggle.setAttribute('aria-pressed', String(visible));
      passwordToggle.title = visible ? 'Hide password' : 'Show password';
    });
  }
</script>
</body>
</html>
